{{--
    Bulk Result Entry JavaScript Module
    Include in views that have the bulk_result_entry_modal partial.

    Required: The view must set `window._bulkResultConfig` before including this partial:
        window._bulkResultConfig = {
            fetchUrlPattern: '/lab-workbench/lab-service-requests/{id}',
            attachUrlPattern: '/lab-workbench/lab-service-requests/{id}/attachments',
            saveUrl: '/lab-workbench/save-result',
            csrfToken: '...',
            onAllSaved: function() { ... }  // optional callback after all saved
        };
--}}

<script>
(function() {
    var _bulkRequests = [];
    var _bulkCurrentIndex = 0;
    var _bulkEditors = {};
    var _bulkDirtyTabs = {};
    var _bulkSavedTabs = {};
    var _bulkRequestData = {};
    var _bulkV1TemplatesLoaded = false;
    var _bulkV1TemplateData = [];
    var _bulkV2Structures = {};  // idx -> parsed V2 structure
    var _bulkActiveMode = {};    // idx -> '1' or '2' (which mode is currently active)

    /**
     * Open the bulk result entry modal with all pending result requests.
     */
    window.openBulkResultEntry = function() {
        if (!window._pendingResultRequests || window._pendingResultRequests.length === 0) {
            toastr.warning('No requests pending result entry.');
            return;
        }

        // Destroy any existing CKEditor instances from a previous session
        Object.keys(_bulkEditors).forEach(function(key) {
            if (_bulkEditors[key]) {
                _bulkEditors[key].destroy().catch(function() {});
            }
        });

        _bulkRequests = window._pendingResultRequests.slice();
        _bulkCurrentIndex = 0;
        _bulkEditors = {};
        _bulkDirtyTabs = {};
        _bulkSavedTabs = {};
        _bulkRequestData = {};
        _bulkV2Structures = {};
        _bulkActiveMode = {};

        _buildTabs();
        _updateProgress();
        $('#bulkResultEntryModal').modal('show');

        // Load templates
        _loadBulkV1Templates();

        // Load first tab data
        _loadTabData(0);
    };

    function _buildTabs() {
        var $tabs = $('#bulkResultTabs');
        var $content = $('#bulkResultTabContent');
        $tabs.empty();
        $content.empty();

        _bulkRequests.forEach(function(req, idx) {
            var serviceName = (req.service && (req.service.service_name || req.service.name)) ? (req.service.service_name || req.service.name) : ('Request #' + req.id);
            var shortName = serviceName.length > 25 ? serviceName.substring(0, 22) + '...' : serviceName;
            var active = idx === 0 ? 'active' : '';
            var show = idx === 0 ? 'show active' : '';

            $tabs.append(
                '<li class="nav-item" role="presentation">' +
                    '<button class="nav-link ' + active + ' bulk-result-tab-btn" id="bulk-tab-' + idx + '" ' +
                    'data-bs-toggle="tab" data-bs-target="#bulk-pane-' + idx + '" type="button" role="tab" ' +
                    'data-index="' + idx + '" title="' + serviceName + '">' +
                    '<span class="bulk-tab-status-icon"></span> ' + shortName +
                    '</button>' +
                '</li>'
            );

            $content.append(
                '<div class="tab-pane fade ' + show + '" id="bulk-pane-' + idx + '" role="tabpanel">' +
                    '<div class="bulk-pane-loading text-center py-5">' +
                        '<i class="fa fa-spinner fa-spin fa-2x"></i>' +
                        '<p class="mt-2 text-muted">Loading...</p>' +
                    '</div>' +
                    '<div class="bulk-version-toggle" data-index="' + idx + '" style="display:none;">' +
                        '<div class="d-flex align-items-center gap-2 mb-2">' +
                            '<span class="text-muted small me-1">Entry Mode:</span>' +
                            '<div class="btn-group btn-group-sm" role="group">' +
                                '<input type="radio" class="btn-check bulk-version-radio" name="bulk_version_toggle_' + idx + '" id="bulk_toggle_v1_' + idx + '" value="1" data-index="' + idx + '" autocomplete="off">' +
                                '<label class="btn btn-outline-secondary" for="bulk_toggle_v1_' + idx + '"><i class="mdi mdi-file-document-edit-outline"></i> Free Text (V1)</label>' +
                                '<input type="radio" class="btn-check bulk-version-radio" name="bulk_version_toggle_' + idx + '" id="bulk_toggle_v2_' + idx + '" value="2" data-index="' + idx + '" autocomplete="off">' +
                                '<label class="btn btn-outline-secondary" for="bulk_toggle_v2_' + idx + '"><i class="mdi mdi-form-select"></i> Structured (V2)</label>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="bulk-pane-content" style="display:none;">' +
                        '<div class="mb-3">' +
                            '<div class="d-flex align-items-center gap-2 mb-2">' +
                                '<select class="form-select form-select-sm bulk-v1-template-select" data-index="' + idx + '" style="max-width: 350px;">' +
                                    '<option value="">-- Insert Template --</option>' +
                                '</select>' +
                                '<button type="button" class="btn btn-outline-primary btn-sm bulk-v1-insert-btn" data-index="' + idx + '" disabled>' +
                                    '<i class="mdi mdi-file-import"></i> Insert' +
                                '</button>' +
                            '</div>' +
                        '</div>' +
                        '<div class="bulk-editor-container" id="bulk-editor-' + idx + '" ' +
                            'style="min-height: 300px; border: 1px solid #ddd; padding: 10px;"></div>' +
                        '<div class="mt-3">' +
                            '<label class="form-label"><i class="fa fa-paperclip"></i> Attachments</label>' +
                            '<input type="file" class="form-control bulk-attachments" data-index="' + idx + '" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">' +
                            '<small class="text-muted">Allowed: PDF, Images, Word Docs (Max 10MB)</small>' +
                        '</div>' +
                    '</div>' +
                    '<div class="bulk-pane-v2" style="display:none;">' +
                        '<div class="bulk-v2-form-fields" id="bulk-v2-fields-' + idx + '"></div>' +
                        '<div class="mt-3">' +
                            '<label class="form-label"><i class="fa fa-paperclip"></i> Attachments</label>' +
                            '<input type="file" class="form-control bulk-attachments-v2" data-index="' + idx + '" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">' +
                            '<small class="text-muted">Allowed: PDF, Images, Word Docs (Max 10MB)</small>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
        });

        // Tab switch handler
        $tabs.find('.bulk-result-tab-btn').on('shown.bs.tab', function() {
            var newIdx = parseInt($(this).data('index'));
            _bulkCurrentIndex = newIdx;
            _loadTabData(newIdx);
            _updateProgress();
        });
    }

    function _loadTabData(idx) {
        if (_bulkRequestData[idx]) return; // already loaded

        var req = _bulkRequests[idx];
        var config = window._bulkResultConfig;
        var fetchUrl = config.fetchUrlPattern.replace('{id}', req.id);

        $.ajax({
            url: fetchUrl,
            method: 'GET',
            success: function(request) {
                _bulkRequestData[idx] = request;
                var $pane = $('#bulk-pane-' + idx);
                $pane.find('.bulk-pane-loading').hide();

                var isV2 = request.service && request.service.template_version == 2;
                var hasV2Structure = false;

                // Parse & store V2 structure if available
                if (isV2) {
                    var structure = request.service.template_structure;
                    if (typeof structure === 'string') {
                        try { structure = JSON.parse(structure); } catch(e) { structure = null; }
                    }
                    if (structure) {
                        _bulkV2Structures[idx] = structure;
                        hasV2Structure = true;
                    }
                }

                // Show/hide version toggle
                if (hasV2Structure) {
                    $pane.find('.bulk-version-toggle').show();
                    $('#bulk_toggle_v2_' + idx).prop('checked', true);
                    _bulkActiveMode[idx] = '2';
                } else {
                    $pane.find('.bulk-version-toggle').hide();
                    $('#bulk_toggle_v1_' + idx).prop('checked', true);
                    _bulkActiveMode[idx] = '1';
                }

                if (hasV2Structure) {
                    // V2 structured template — hide V1 elements, show V2
                    $pane.find('.bulk-pane-content').hide();
                    $pane.find('.bulk-pane-v2').show();
                    _loadBulkV2Template(idx, request);
                } else {
                    // V1 WYSIWYG template
                    $pane.find('.bulk-pane-content').show();
                    $pane.find('.bulk-pane-v2').hide();
                    var content = request.result || (request.service ? request.service.template_body : '');
                    if (content && typeof content === 'string') {
                        content = content.replace(/contenteditable="false"/g, 'contenteditable="true"');
                        content = content.replace(/contenteditable='false'/g, "contenteditable='true'");
                    }
                    _initBulkEditor(idx, content || '');
                    _populateBulkTemplateSelect(idx);
                }
            },
            error: function(xhr) {
                var $pane = $('#bulk-pane-' + idx);
                $pane.find('.bulk-pane-loading').html(
                    '<div class="text-danger"><i class="fa fa-exclamation-triangle"></i> Error loading request</div>'
                );
            }
        });
    }

    function _initBulkEditor(idx, content) {
        if (_bulkEditors[idx]) {
            _bulkEditors[idx].setData(content);
            return;
        }

        var el = document.querySelector('#bulk-editor-' + idx);
        if (!el) return;

        ClassicEditor
            .create(el, {
                toolbar: {
                    items: [
                        'undo', 'redo',
                        '|', 'heading',
                        '|', 'bold', 'italic',
                        '|', 'link', 'insertTable',
                        '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                    ]
                }
            })
            .then(function(editor) {
                _bulkEditors[idx] = editor;
                editor.setData(content);
                editor.model.document.on('change:data', function() {
                    _bulkDirtyTabs[idx] = true;
                    _updateTabStatus(idx);
                });
            })
            .catch(function(err) {
                console.error('Bulk CKEditor init error for tab ' + idx, err);
            });
    }

    function _loadBulkV2Template(idx, request) {
        var structure = _bulkV2Structures[idx];
        if (!structure) return;

        var existingData = null;
        if (request.result_data) {
            try {
                existingData = typeof request.result_data === 'string' ? JSON.parse(request.result_data) : request.result_data;
            } catch(e) {}
        }

        var formHtml = '<div class="v2-result-form">';
        var parameters = structure.parameters ? structure.parameters.sort(function(a,b) { return a.order - b.order; }) : [];

        parameters.forEach(function(param) {
            if (param.show_in_report === false) return;
            var fieldId = 'bulk_v2_param_' + idx + '_' + param.id;
            var value = '';
            if (existingData && existingData[param.id]) {
                if (typeof existingData[param.id] === 'object' && existingData[param.id] !== null && existingData[param.id].hasOwnProperty('value')) {
                    value = existingData[param.id].value;
                } else {
                    value = existingData[param.id];
                }
            }
            if (value === null || value === undefined) value = '';

            formHtml += '<div class="form-group row mb-2">';
            formHtml += '<label class="col-md-4 col-form-label">' + param.name;
            if (param.unit) formHtml += ' <small class="text-muted">(' + param.unit + ')</small>';
            formHtml += '</label>';
            formHtml += '<div class="col-md-8">';

            if (param.type === 'string' || param.type === 'integer' || param.type === 'float') {
                var inputType = param.type === 'string' ? 'text' : 'number';
                var step = param.type === 'float' ? '0.01' : (param.type === 'integer' ? '1' : '');
                formHtml += '<input type="' + inputType + '" ' + (step ? 'step="' + step + '"' : '') + ' class="form-control bulk-v2-param-field" ';
                formHtml += 'data-param-id="' + param.id + '" data-index="' + idx + '" ';
                formHtml += 'id="' + fieldId + '" value="' + value + '" placeholder="Enter ' + param.name + '">';
            } else if (param.type === 'boolean') {
                formHtml += '<select class="form-control bulk-v2-param-field" data-param-id="' + param.id + '" data-index="' + idx + '" id="' + fieldId + '">';
                formHtml += '<option value="">Select</option>';
                formHtml += '<option value="true" ' + (value === true || value === 'true' ? 'selected' : '') + '>Yes/Positive</option>';
                formHtml += '<option value="false" ' + (value === false || value === 'false' ? 'selected' : '') + '>No/Negative</option>';
                formHtml += '</select>';
            } else if (param.type === 'enum' && param.options) {
                formHtml += '<select class="form-control bulk-v2-param-field" data-param-id="' + param.id + '" data-index="' + idx + '" id="' + fieldId + '">';
                formHtml += '<option value="">Select</option>';
                param.options.forEach(function(opt) {
                    var optVal = typeof opt === 'object' ? opt.value : opt;
                    var optLabel = typeof opt === 'object' ? opt.label : opt;
                    formHtml += '<option value="' + optVal + '" ' + (value === optVal ? 'selected' : '') + '>' + optLabel + '</option>';
                });
                formHtml += '</select>';
            } else if (param.type === 'long_text') {
                formHtml += '<textarea class="form-control bulk-v2-param-field" data-param-id="' + param.id + '" data-index="' + idx + '" id="' + fieldId + '" rows="3">' + value + '</textarea>';
            }

            if (param.reference_range) {
                formHtml += '<small class="form-text text-muted">';
                if ((param.type === 'integer' || param.type === 'float') && param.reference_range.min !== null && param.reference_range.max !== null) {
                    formHtml += 'Normal: ' + param.reference_range.min + ' - ' + param.reference_range.max;
                } else if (param.reference_range.text) {
                    formHtml += param.reference_range.text;
                }
                formHtml += '</small>';
            }

            formHtml += '</div></div>';
        });

        formHtml += '</div>';
        $('#bulk-v2-fields-' + idx).html(formHtml);

        // Mark dirty on change
        $('#bulk-v2-fields-' + idx).find('.bulk-v2-param-field').on('change input', function() {
            _bulkDirtyTabs[idx] = true;
            _updateTabStatus(idx);
        });
    }

    function _loadBulkV1Templates() {
        if (_bulkV1TemplatesLoaded) return;
        $.get('{{ route("v1-result-templates.list") }}', function(response) {
            if (response.success && response.groups && response.groups.length > 0) {
                _bulkV1TemplateData = response.groups;
                _bulkV1TemplatesLoaded = true;
                // Populate all existing selects
                _bulkRequests.forEach(function(req, idx) {
                    _populateBulkTemplateSelect(idx);
                });
            }
        });
    }

    function _populateBulkTemplateSelect(idx) {
        if (!_bulkV1TemplatesLoaded) return;
        var $select = $('#bulk-pane-' + idx + ' .bulk-v1-template-select');
        if ($select.find('optgroup').length > 0) return; // already populated
        _bulkV1TemplateData.forEach(function(group) {
            var $optgroup = $('<optgroup>').attr('label', group.category);
            group.templates.forEach(function(t) {
                $optgroup.append($('<option>').val(t.id).text(t.name).data('content', t.content));
            });
            $select.append($optgroup);
        });
    }

    // Template select change handler
    $(document).on('change', '.bulk-v1-template-select', function() {
        var idx = $(this).data('index');
        var $btn = $('#bulk-pane-' + idx + ' .bulk-v1-insert-btn');
        $btn.prop('disabled', !$(this).val());
    });

    // Template insert handler
    $(document).on('click', '.bulk-v1-insert-btn', function() {
        var idx = $(this).data('index');
        var $select = $('#bulk-pane-' + idx + ' .bulk-v1-template-select');
        var content = $select.find('option:selected').data('content');
        if (!content || !_bulkEditors[idx]) return;

        var currentContent = _bulkEditors[idx].getData();
        if (currentContent && currentContent.trim() !== '' && currentContent !== '<p>&nbsp;</p>') {
            _bulkEditors[idx].setData(currentContent + content);
        } else {
            _bulkEditors[idx].setData(content);
        }
        _bulkDirtyTabs[idx] = true;
        _updateTabStatus(idx);

        $select.val('');
        $(this).prop('disabled', true);
    });

    // V1/V2 toggle handler for bulk tabs
    $(document).on('change', '.bulk-version-radio', function() {
        var idx = parseInt($(this).data('index'));
        var version = $(this).val();
        _bulkActiveMode[idx] = version;
        var $pane = $('#bulk-pane-' + idx);

        if (version === '1') {
            $pane.find('.bulk-pane-v2').hide();
            $pane.find('.bulk-pane-content').show();
            // Init V1 editor if not already done
            if (!_bulkEditors[idx] && _bulkRequestData[idx]) {
                var content = _bulkRequestData[idx].result || (_bulkRequestData[idx].service ? _bulkRequestData[idx].service.template_body : '');
                if (content && typeof content === 'string') {
                    content = content.replace(/contenteditable="false"/g, 'contenteditable="true"');
                    content = content.replace(/contenteditable='false'/g, "contenteditable='true'");
                }
                _initBulkEditor(idx, content || '');
                _populateBulkTemplateSelect(idx);
            }
        } else if (version === '2') {
            $pane.find('.bulk-pane-content').hide();
            $pane.find('.bulk-pane-v2').show();
            // Load V2 form if not already done
            if (_bulkV2Structures[idx] && $pane.find('.bulk-v2-form-fields .v2-result-form').length === 0) {
                _loadBulkV2Template(idx, _bulkRequestData[idx]);
            }
        }
    });

    function _updateTabStatus(idx) {
        var $tab = $('#bulk-tab-' + idx);
        var $icon = $tab.find('.bulk-tab-status-icon');
        if (_bulkSavedTabs[idx]) {
            $icon.html('<i class="mdi mdi-check-circle text-success"></i>');
            $tab.removeClass('text-warning').addClass('text-success');
        } else if (_bulkDirtyTabs[idx]) {
            $icon.html('<i class="mdi mdi-pencil text-warning"></i>');
            $tab.removeClass('text-success').addClass('text-warning');
        } else {
            $icon.html('');
            $tab.removeClass('text-success text-warning');
        }
    }

    function _updateProgress() {
        var saved = Object.keys(_bulkSavedTabs).length;
        var total = _bulkRequests.length;
        $('#bulkResultProgress').text(saved + ' / ' + total + ' saved');
        $('#bulkResultStatusText').text('Tab ' + (_bulkCurrentIndex + 1) + ' of ' + total);

        // Update button text
        if (_bulkCurrentIndex >= total - 1) {
            $('#bulkNextBtn').hide();
            $('#bulkSaveAndNextBtn').html('<i class="mdi mdi-content-save"></i> Save');
        } else {
            $('#bulkNextBtn').show();
            $('#bulkSaveAndNextBtn').html('<i class="mdi mdi-content-save"></i> Save & Next');
        }
    }

    /**
     * Save the current tab's result and move to the next tab.
     */
    window.bulkSaveAndNext = function() {
        _saveBulkTab(_bulkCurrentIndex, function() {
            _bulkSavedTabs[_bulkCurrentIndex] = true;
            _bulkDirtyTabs[_bulkCurrentIndex] = false;
            _updateTabStatus(_bulkCurrentIndex);
            _updateProgress();

            if (_bulkCurrentIndex < _bulkRequests.length - 1) {
                bulkGoNext();
            } else {
                // All done - check if all saved
                var allSaved = _bulkRequests.every(function(r, i) { return _bulkSavedTabs[i]; });
                if (allSaved) {
                    toastr.success('All results saved successfully!');
                    $('#bulkResultEntryModal').modal('hide');
                    if (window._bulkResultConfig && typeof window._bulkResultConfig.onAllSaved === 'function') {
                        window._bulkResultConfig.onAllSaved();
                    }
                }
            }
        });
    };

    /**
     * Move to the next tab without saving.
     */
    window.bulkGoNext = function() {
        if (_bulkCurrentIndex < _bulkRequests.length - 1) {
            var nextIdx = _bulkCurrentIndex + 1;
            $('#bulk-tab-' + nextIdx).tab('show');
        }
    };

    function _saveBulkTab(idx, onSuccess) {
        var req = _bulkRequests[idx];
        var requestData = _bulkRequestData[idx];
        if (!requestData) {
            toastr.error('Request data not loaded yet for this tab.');
            return;
        }

        var config = window._bulkResultConfig;
        var isV2 = _bulkActiveMode[idx] === '2';
        var formData = new FormData();
        formData.append('_token', config.csrfToken);
        formData.append('invest_res_entry_id', req.id);
        formData.append('invest_res_is_edit', '0');
        formData.append('invest_res_template_version', isV2 ? '2' : '1');

        if (isV2) {
            // Collect V2 structured data
            var data = {};
            $('#bulk-v2-fields-' + idx + ' .bulk-v2-param-field').each(function() {
                var paramId = $(this).data('param-id');
                data[paramId] = $(this).val() || null;
            });
            formData.append('invest_res_template_data', JSON.stringify(data));
            formData.append('invest_res_template_submited', '<p>Structured result data (V2 template)</p>');
        } else {
            // V1 - get editor content
            var editor = _bulkEditors[idx];
            if (editor) {
                formData.append('invest_res_template_submited', editor.getData());
            }
        }

        // Attachments — get from whichever mode is active
        var $fileInput;
        if (isV2) {
            $fileInput = $('#bulk-pane-' + idx + ' .bulk-attachments-v2')[0];
        } else {
            $fileInput = $('#bulk-pane-' + idx + ' .bulk-attachments')[0];
        }
        if ($fileInput && $fileInput.files) {
            for (var i = 0; i < $fileInput.files.length; i++) {
                formData.append('result_attachments[]', $fileInput.files[i]);
            }
        }

        var $btn = $('#bulkSaveAndNextBtn');
        var origHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');

        $.ajax({
            url: config.saveUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                toastr.success('Result saved for: ' + (req.service ? (req.service.service_name || req.service.name || 'Request #' + req.id) : 'Request #' + req.id));
                if (typeof onSuccess === 'function') onSuccess();
            },
            error: function(xhr) {
                var errorMsg = xhr.responseJSON?.message || 'Unknown error';
                toastr.error('Error saving result: ' + errorMsg);
            },
            complete: function() {
                $btn.prop('disabled', false).html(origHtml);
            }
        });
    }

    /**
     * Close the bulk modal with unsaved warning.
     */
    window.closeBulkResultModal = function() {
        var unsaved = [];
        _bulkRequests.forEach(function(r, idx) {
            if (_bulkDirtyTabs[idx] && !_bulkSavedTabs[idx]) {
                unsaved.push(r.service ? r.service.name : 'Request #' + r.id);
            }
        });

        if (unsaved.length > 0) {
            if (!confirm('You have unsaved results for:\n\n' + unsaved.join('\n') + '\n\nAre you sure you want to close? Unsaved changes will be lost.')) {
                return;
            }
        }

        // Destroy CKEditor instances to prevent memory leaks
        Object.keys(_bulkEditors).forEach(function(key) {
            if (_bulkEditors[key]) {
                _bulkEditors[key].destroy().catch(function() {});
            }
        });
        _bulkEditors = {};
        _bulkRequestData = {};
        _bulkDirtyTabs = {};
        _bulkSavedTabs = {};
        _bulkV2Structures = {};
        _bulkActiveMode = {};

        $('#bulkResultEntryModal').modal('hide');

        // Reload the patient to refresh statuses
        if (window._bulkResultConfig && typeof window._bulkResultConfig.onAllSaved === 'function') {
            window._bulkResultConfig.onAllSaved();
        }
    };

})();
</script>
