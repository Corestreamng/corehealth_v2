{{--
    Shared Investigation Result Entry JavaScript Module

    Include this partial in any blade view that needs result entry/edit functionality.
    The investResModal partial must also be included in the same view.

    Required variables (pass via @include):
        $resultEntryConfig  - array with keys:
            'getRequestUrl'     => base URL pattern for fetching a request (use {id} placeholder)
            'getAttachmentsUrl' => base URL pattern for fetching attachments (use {id} placeholder)
            'onSaveSuccess'     => JS callback name to call after successful save (optional)
--}}

<script>
/**
 * InvestResultEntry – shared module for lab / imaging result entry & editing.
 * Works with V1 (CKEditor WYSIWYG) and V2 (structured JSON) templates.
 */
window.InvestResultEntry = (function() {

    /**
     * Open the result entry modal for a new result.
     * @param {number} requestId  - The lab/imaging service request ID
     * @param {string} fetchUrl   - Full URL to GET the request data
     * @param {string} attachUrl  - Full URL to GET attachments (with {id} replaced)
     * @param {string} [saveUrl]  - Optional URL to override the form action (for multi-context pages)
     */
    function enterResult(requestId, fetchUrl, attachUrl, saveUrl) {
        $.ajax({
            url: fetchUrl,
            method: 'GET',
            success: function(request) {
                _setResTempInModal(request, attachUrl);
                if (saveUrl) {
                    $('#investResForm').attr('action', saveUrl);
                }
                $('#investResModal').modal('show');
            },
            error: function(xhr) {
                toastr.error('Error loading request: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        });
    }

    /**
     * Open the result entry modal in edit mode.
     * @param {number} requestId  - The lab/imaging service request ID
     * @param {string} fetchUrl   - Full URL to GET the request data
     * @param {string} attachUrl  - Full URL to GET attachments
     * @param {string} [saveUrl]  - Optional URL to override the form action (for multi-context pages)
     */
    function editResult(requestId, fetchUrl, attachUrl, saveUrl) {
        $.ajax({
            url: fetchUrl,
            method: 'GET',
            success: function(request) {
                _setResTempInModal(request, attachUrl);
                if (saveUrl) {
                    $('#investResForm').attr('action', saveUrl);
                }
                // Set Edit Mode UI
                $('#invest_res_is_edit').val(1);
                $('#investResModalLabel').html('Edit Result (<span id="invest_res_service_name_edit">' + (request.service ? request.service.name : '') + '</span>)');
                $('#investResForm button[type="submit"]').html('<i class="mdi mdi-content-save"></i> Update Result');
                $('#investResModal').modal('show');
            },
            error: function(xhr) {
                toastr.error('Error loading request: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        });
    }

    /**
     * Populate the modal with request data and template.
     */
    function _setResTempInModal(request, attachUrl) {
        $('#investResModal').find('form').trigger('reset');
        $('#invest_res_service_name').text(request.service ? request.service.name : '');
        $('#invest_res_entry_id').val(request.id);
        $('#invest_res_is_edit').val(0);
        $('#deleted_attachments').val('[]');
        $('#existing_attachments_container').hide();
        $('#existing_attachments_list').html('');

        // Reset title back to default
        $('#investResModalLabel').html('Enter Result (<span id="invest_res_service_name">' + (request.service ? request.service.name : '') + '</span>)');
        $('#investResForm button[type="submit"]').html('<i class="mdi mdi-content-save"></i> Save Result');

        // Check template version
        const isV2 = request.service && request.service.template_version == 2;

        if (isV2) {
            let structure = request.service.template_structure;
            if (typeof structure === 'string') {
                try {
                    structure = JSON.parse(structure);
                } catch (e) {
                    console.error('Error parsing V2 template structure:', e);
                    structure = null;
                }
            }

            if (structure) {
                let existingData = null;
                if (request.result_data) {
                    try {
                        existingData = typeof request.result_data === 'string' ? JSON.parse(request.result_data) : request.result_data;
                    } catch (e) {
                        console.error('Error parsing result_data:', e);
                    }
                }
                _loadV2Template(structure, existingData);
            } else {
                console.error('Invalid V2 template structure');
                let content = request.result || (request.service ? request.service.template_body : '');
                _loadV1Template(content);
            }
        } else {
            let content = request.result || (request.service ? request.service.template_body : '');
            _loadV1Template(content);
        }

        // Load existing attachments
        if (attachUrl) {
            _loadExistingAttachments(attachUrl);
        }
    }

    /**
     * Load V1 (CKEditor WYSIWYG) template into the modal.
     */
    function _loadV1Template(template) {
        $('#invest_res_template_version').val('1');
        $('#v1_template_container').show();
        $('#v2_template_container').hide();

        if (template && typeof template === 'string') {
            template = template.replace(/contenteditable="false"/g, 'contenteditable="true"');
            template = template.replace(/contenteditable='false'/g, "contenteditable='true'");
        }

        if (!window.investResEditor) {
            ClassicEditor
                .create(document.querySelector('#invest_res_template_editor'), {
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
                .then(editor => {
                    window.investResEditor = editor;
                    editor.setData(template || '');
                })
                .catch(err => {
                    console.error(err);
                });
        } else {
            window.investResEditor.setData(template || '');
        }
    }

    /**
     * Load V2 (structured JSON) template form into the modal.
     */
    function _loadV2Template(template, existingData) {
        $('#invest_res_template_version').val('2');
        $('#v1_template_container').hide();
        $('#v2_template_container').show();

        let formHtml = '<div class="v2-result-form">';
        formHtml += '<h6 class="mb-3">' + (template.template_name || 'Result Entry') + '</h6>';

        let parameters = template.parameters ? template.parameters.sort((a, b) => a.order - b.order) : [];

        parameters.forEach(param => {
            if (param.show_in_report === false) return;

            formHtml += '<div class="form-group row">';
            formHtml += '<label class="col-md-4 col-form-label">';
            formHtml += param.name;
            if (param.unit) {
                formHtml += ' <small class="text-muted">(' + param.unit + ')</small>';
            }
            if (param.required) {
                formHtml += ' <span class="text-danger">*</span>';
            }
            formHtml += '</label>';
            formHtml += '<div class="col-md-8">';

            let fieldId = 'param_' + param.id;
            let value = '';
            if (existingData && existingData[param.id]) {
                if (typeof existingData[param.id] === 'object' && existingData[param.id] !== null && existingData[param.id].hasOwnProperty('value')) {
                    value = existingData[param.id].value;
                } else {
                    value = existingData[param.id];
                }
            }
            if (value === null || value === undefined) value = '';

            if (param.type === 'string') {
                formHtml += '<input type="text" class="form-control v2-param-field" ';
                formHtml += 'data-param-id="' + param.id + '" data-param-type="' + param.type + '" ';
                formHtml += 'id="' + fieldId + '" value="' + value + '" ';
                if (param.required) formHtml += 'required ';
                formHtml += 'placeholder="Enter ' + param.name + '">';

            } else if (param.type === 'integer') {
                formHtml += '<input type="number" step="1" class="form-control v2-param-field" ';
                formHtml += 'data-param-id="' + param.id + '" data-param-type="' + param.type + '" ';
                if (param.reference_range) {
                    formHtml += 'data-ref-min="' + (param.reference_range.min || '') + '" ';
                    formHtml += 'data-ref-max="' + (param.reference_range.max || '') + '" ';
                }
                formHtml += 'id="' + fieldId + '" value="' + value + '" ';
                if (param.required) formHtml += 'required ';
                formHtml += 'placeholder="Enter ' + param.name + '">';

            } else if (param.type === 'float') {
                formHtml += '<input type="number" step="0.01" class="form-control v2-param-field" ';
                formHtml += 'data-param-id="' + param.id + '" data-param-type="' + param.type + '" ';
                if (param.reference_range) {
                    formHtml += 'data-ref-min="' + (param.reference_range.min || '') + '" ';
                    formHtml += 'data-ref-max="' + (param.reference_range.max || '') + '" ';
                }
                formHtml += 'id="' + fieldId + '" value="' + value + '" ';
                if (param.required) formHtml += 'required ';
                formHtml += 'placeholder="Enter ' + param.name + '">';

            } else if (param.type === 'boolean') {
                formHtml += '<select class="form-control v2-param-field" ';
                formHtml += 'data-param-id="' + param.id + '" data-param-type="' + param.type + '" ';
                if (param.reference_range && param.reference_range.reference_value !== undefined) {
                    formHtml += 'data-ref-value="' + param.reference_range.reference_value + '" ';
                }
                formHtml += 'id="' + fieldId + '" ';
                if (param.required) formHtml += 'required ';
                formHtml += '>';
                formHtml += '<option value="">Select</option>';
                formHtml += '<option value="true" ' + (value === true || value === 'true' ? 'selected' : '') + '>Yes/Positive</option>';
                formHtml += '<option value="false" ' + (value === false || value === 'false' ? 'selected' : '') + '>No/Negative</option>';
                formHtml += '</select>';

            } else if (param.type === 'enum') {
                formHtml += '<select class="form-control v2-param-field" ';
                formHtml += 'data-param-id="' + param.id + '" data-param-type="' + param.type + '" ';
                if (param.reference_range && param.reference_range.reference_value) {
                    formHtml += 'data-ref-value="' + param.reference_range.reference_value + '" ';
                }
                formHtml += 'id="' + fieldId + '" ';
                if (param.required) formHtml += 'required ';
                formHtml += '>';
                formHtml += '<option value="">Select</option>';
                if (param.options) {
                    param.options.forEach(opt => {
                        let optVal = typeof opt === 'object' ? opt.value : opt;
                        let optLabel = typeof opt === 'object' ? opt.label : opt;
                        formHtml += '<option value="' + optVal + '" ' + (value === optVal ? 'selected' : '') + '>' + optLabel + '</option>';
                    });
                }
                formHtml += '</select>';

            } else if (param.type === 'long_text') {
                formHtml += '<textarea class="form-control v2-param-field" ';
                formHtml += 'data-param-id="' + param.id + '" data-param-type="' + param.type + '" ';
                formHtml += 'id="' + fieldId + '" rows="3" ';
                if (param.required) formHtml += 'required ';
                formHtml += 'placeholder="Enter ' + param.name + '">' + value + '</textarea>';
            }

            // Reference range info
            if (param.reference_range) {
                formHtml += '<small class="form-text text-muted">';
                if (param.type === 'integer' || param.type === 'float') {
                    if (param.reference_range.min !== null && param.reference_range.max !== null) {
                        formHtml += 'Normal range: ' + param.reference_range.min + ' - ' + param.reference_range.max;
                    }
                } else if (param.type === 'boolean' && param.reference_range.reference_value !== undefined) {
                    formHtml += 'Normal: ' + (param.reference_range.reference_value ? 'Yes/Positive' : 'No/Negative');
                } else if (param.type === 'enum' && param.reference_range.reference_value) {
                    formHtml += 'Normal: ' + param.reference_range.reference_value;
                } else if (param.reference_range.text) {
                    formHtml += param.reference_range.text;
                }
                formHtml += '</small>';
            }

            // Status indicator
            formHtml += '<div class="mt-1"><span class="param-status" id="status_' + param.id + '"></span></div>';
            formHtml += '</div>';
            formHtml += '</div>';
        });

        formHtml += '</div>';
        $('#v2_form_fields').html(formHtml);

        $('.v2-param-field').on('blur change', function() {
            _updateParameterStatus($(this));
        });

        $('.v2-param-field').each(function() {
            if ($(this).val()) {
                _updateParameterStatus($(this));
            }
        });
    }

    /**
     * Update Normal/High/Low/Abnormal badge for a V2 parameter field.
     */
    function _updateParameterStatus($field) {
        let paramId = $field.data('param-id');
        let paramType = $field.data('param-type');
        let value = $field.val();
        let $statusSpan = $('#status_' + paramId);

        if (!value || value === '') {
            $statusSpan.html('');
            return;
        }

        let status = '';
        let statusClass = '';

        if (paramType === 'integer' || paramType === 'float') {
            let numValue = parseFloat(value);
            let min = $field.data('ref-min');
            let max = $field.data('ref-max');

            if (min !== undefined && max !== undefined && min !== '' && max !== '') {
                if (numValue < min) {
                    status = 'Low';
                    statusClass = 'badge-warning';
                } else if (numValue > max) {
                    status = 'High';
                    statusClass = 'badge-danger';
                } else {
                    status = 'Normal';
                    statusClass = 'badge-success';
                }
            }
        } else if (paramType === 'boolean') {
            let refValue = $field.data('ref-value');
            if (refValue !== undefined) {
                let boolValue = value === 'true';
                let refBool = refValue === true || refValue === 'true';
                if (boolValue === refBool) {
                    status = 'Normal';
                    statusClass = 'badge-success';
                } else {
                    status = 'Abnormal';
                    statusClass = 'badge-warning';
                }
            }
        } else if (paramType === 'enum') {
            let refValue = $field.data('ref-value');
            if (refValue) {
                if (value === refValue) {
                    status = 'Normal';
                    statusClass = 'badge-success';
                } else {
                    status = 'Abnormal';
                    statusClass = 'badge-warning';
                }
            }
        }

        if (status) {
            $statusSpan.html('<span class="badge ' + statusClass + '">' + status + '</span>');
        } else {
            $statusSpan.html('');
        }
    }

    /**
     * Load existing attachments for a request.
     */
    function _loadExistingAttachments(attachUrl) {
        const container = $('#existing_attachments_list');
        const wrapper = $('#existing_attachments_container');
        container.empty();
        wrapper.hide();

        $.ajax({
            url: attachUrl,
            method: 'GET',
            success: function(attachments) {
                if (attachments && attachments.length > 0) {
                    wrapper.show();
                    attachments.forEach(att => {
                        const attDiv = $('<div>').addClass('attachment-item mb-2 d-flex justify-content-between align-items-center');
                        const link = $('<a>').attr('href', att.url).attr('target', '_blank').text(att.filename);
                        const deleteBtn = $('<button>')
                            .addClass('btn btn-sm btn-danger')
                            .html('<i class="fa fa-trash"></i>')
                            .on('click', function() {
                                _markAttachmentForDeletion(att.id);
                                attDiv.remove();
                                if (container.children().length === 0) {
                                    wrapper.hide();
                                }
                            });
                        attDiv.append(link).append(deleteBtn);
                        container.append(attDiv);
                    });
                }
            }
        });
    }

    function _markAttachmentForDeletion(attachmentId) {
        const current = $('#deleted_attachments').val();
        const deleted = current ? JSON.parse(current) : [];
        deleted.push(attachmentId);
        $('#deleted_attachments').val(JSON.stringify(deleted));
    }

    /**
     * Copy template data from editors/inputs to hidden form fields before submit.
     * Must be called before the form is submitted.
     */
    function copyResTemplateToField() {
        let version = $('#invest_res_template_version').val();

        if (version === '2') {
            let data = {};
            $('.v2-param-field').each(function() {
                let paramId = $(this).data('param-id');
                let paramType = $(this).data('param-type');
                let value = $(this).val();

                if (paramType === 'integer') {
                    data[paramId] = value ? parseInt(value) : null;
                } else if (paramType === 'float') {
                    data[paramId] = value ? parseFloat(value) : null;
                } else if (paramType === 'boolean') {
                    data[paramId] = value === 'true' ? true : (value === 'false' ? false : null);
                } else {
                    data[paramId] = value || null;
                }
            });

            $('#invest_res_template_data').val(JSON.stringify(data));
            $('#invest_res_template_submited').val('<p>Structured result data (V2 template)</p>');
        } else {
            if (window.investResEditor) {
                $('#invest_res_template_submited').val(window.investResEditor.getData());
            }
        }
        return true;
    }

    /**
     * Bind the form submit handler.
     * @param {Function|null} onSuccess - Optional callback after successful save
     */
    function bindFormSubmit(onSuccess) {
        $('#investResForm').off('submit').on('submit', function(e) {
            e.preventDefault();
            copyResTemplateToField();

            const formData = new FormData(this);
            const $submitBtn = $(this).find('button[type="submit"]');
            const originalBtnHtml = $submitBtn.html();
            $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');

            $.ajax({
                url: $(this).attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    toastr.success('Result saved successfully!');
                    $('#investResModal').modal('hide');
                    if (typeof onSuccess === 'function') {
                        onSuccess(response);
                    }
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.message || 'Unknown error';
                    toastr.error('Error saving result: ' + errorMsg);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html(originalBtnHtml);
                }
            });
        });
    }

    // Public API
    return {
        enterResult: enterResult,
        editResult: editResult,
        copyResTemplateToField: copyResTemplateToField,
        bindFormSubmit: bindFormSubmit
    };

})();

// Global alias so the onsubmit="copyResTemplateToField()" on the form works
function copyResTemplateToField() {
    return InvestResultEntry.copyResTemplateToField();
}
</script>
