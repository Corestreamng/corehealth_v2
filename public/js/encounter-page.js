
    

        $(function() {
            // Diagnosis search and display is handled by the clinical_notes partial
            // which provides: addReason(), removeReason(), updateSelectedReasonsDisplay()
            // using clinicalSelectedReasons[] and table-based per-diagnosis display

            // Make removeReason accessible globally (clinical_notes partial defines it globally already)
            window.removeReasonByValue = function(value) {
                if (typeof removeReason === 'function') removeReason(value);
            };

            // AJAX search for reasons is handled by clinical_notes partial
            // which binds to #reasons_for_encounter_search and uses addReason(value, display, code, name)

            // Initialize Select2 for edit modal diagnosis dropdown (keep for edit modal)
            if (true) {
            $("#editEncounterReasons").select2({
                dropdownParent: $('#editEncounterModal'),
                placeholder: 'Select diagnosis codes',
                allowClear: true,
                tags: true
            });
            }

            // Handle Diagnosis Applicable Toggle for New Encounter Form
            $('#diagnosisApplicable').on('change', function() {
                const isChecked = $(this).is(':checked');
                console.log('New Encounter - Diagnosis Applicable toggle changed:', isChecked);

                const $diagnosisFields = $('#diagnosisFields');

                if (isChecked) {
                    // Diagnosis IS applicable - show fields with animation
                    $diagnosisFields.removeClass('hidden collapsed');
                    $diagnosisFields.attr('style', ''); // Remove inline style
                    $diagnosisFields.css({
                        'display': 'block',
                        'opacity': '1'
                    });

                    // Clear NA values if present
                    if ($('#reasons_for_encounter_comment_1').val() === 'NA') {
                        $('#reasons_for_encounter_comment_1').val('');
                    }
                    if ($('#reasons_for_encounter_comment_2').val() === 'NA') {
                        $('#reasons_for_encounter_comment_2').val('');
                    }
                } else {
                    // Diagnosis is NOT applicable - hide fields with animation
                    $diagnosisFields.css('opacity', '0');
                    setTimeout(function() {
                        $diagnosisFields.addClass('collapsed');
                    }, 300); // Wait for animation to complete

                    // Set values to NA/null
                    $('#reasons_for_encounter').val(null).trigger('change');
                    $('#reasons_for_encounter_comment_1').val('NA');
                    $('#reasons_for_encounter_comment_2').val('NA');
                }
            });

            // Set initial max-height for animation
            setTimeout(function() {
                const $diagnosisFields = $('#diagnosisFields');
                if ($diagnosisFields.length) {
                    $diagnosisFields.css('max-height', $diagnosisFields[0].scrollHeight + 'px');
                }
            }, 100);
        });
        function getForm() {
            let form_ = $('#patient_form_form');
            let form_id = $('#form_type').val();
            let encounter_id = $('#encounter_id__').val();
            let patient_id = $('#encounter_patient_id__').val();
            if (form_id != '') {
                getformreq = $.ajax({
                    type: 'GET',
                    url: wbRoute('patient-form.create', '/patient-form/create'),
                    data: {
                        form_id: form_id,
                    },
                    success: function(data) {
                        console.log(data);
                        form_.html(data.formdata);
                        mar = `
                            <input type="hidden" name="form_id" value="${data.form_id}">
                            <input type="hidden" name="patient_id" value="${patient_id}">
                            <input type="hidden" name="encounter_id" value="${encounter_id}">
                            <button type="submit" class="btn btn-primary">Save</button>
                        `;
                        form_.append(mar);
                        form_.append(`(window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '').attr('content') || '')`);
                    },
                    error: function(x, y, z) {
                        console.log(x, y, z);
                        form_.html('Sorry, Failed to obtain form, please try again later');
                    }
                });
            }
        }
        // ClassicEditor
        //     .create(document.querySelector('.classic-editor'), {
        //         toolbar: {
        //             items: [
        //                 'undo', 'redo',
        //                 '|', 'heading',
        //                 '|', 'bold', 'italic',
        //                 '|', 'link', 'uploadImage', 'insertTable', 'mediaEmbed',
        //                 '|', 'bulletedList', 'numberedList', 'outdent', 'indent',
        //             ]
        //         },
        //         cloudServices: {
        //             // All predefined builds include the Easy Image feature.
        //             // Provide correct configuration values to use it.
        //             // tokenUrl: 'https://example.com/cs-token-endpoint',
        //             // uploadUrl: 'https://your-organization-id.cke-cs.com/easyimage/upload/'
        //             // Read more about Easy Image - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/easy-image.html.
        //             // For other image upload methods see the guide - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/image-upload.html.
        //         }
        //     })
        //     .then(editor => {
        //         window.editor = editor;
        //     })
        //     .catch(err => {
        //         console.error(err);
        //     });

        ClassicEditor
            .create(document.querySelector('.classic-editor2'), {
                toolbar: {
                    items: [
                        'undo', 'redo',
                        '|', 'heading',
                        '|', 'bold', 'italic',
                        '|', 'link', 'uploadImage', 'insertTable', 'mediaEmbed',
                        '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                    ]
                },
                cloudServices: {
                    // All predefined builds include the Easy Image feature.
                    // Provide correct configuration values to use it.
                    // tokenUrl: 'https://example.com/cs-token-endpoint',
                    // uploadUrl: 'https://your-organization-id.cke-cs.com/easyimage/upload/'
                    // Read more about Easy Image - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/easy-image.html.
                    // For other image upload methods see the guide - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/image-upload.html.
                }
            })
            .then(editor => {
                window.editor = editor;
            })
            .catch(err => {
                console.error(err);
            });
        function autosavenotes() {
            console.log('...');
            // Use the specific CKEditor instance for clinical notes (not the report builder editor)
            let notes = '';
            if (window.editor && typeof window.editor.getData === 'function') {
                notes = window.editor.getData();
            } else {
                notes = $('#doctor_diagnosis_text').val();
            }
            let encounter_id = $('#encounter_id__').val();
            let autosavesatustext = $('#autosave_status_text');
            let diagnosisData = $('#reasons_for_encounter_data').val() || '[]';
            let hasDiagnosis = diagnosisData && diagnosisData !== '[]';
            let hasNotes = notes && notes.trim() !== '' && notes.trim() !== '<p>&nbsp;</p>';
            if (hasNotes || hasDiagnosis) {
                autosavesatustext.html('<i class="mdi mdi-floppy"></i> <i class="fa fa-spinner fa-spin"></i> Autosaving...');
                autosavereq = $.ajax({
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: wbRoute('auto-save-encounter-note', '/auto-save-encounter-note'),
                    data: {
                        patient_id: "(window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '')",
                        notes: notes,
                        encounter_id: encounter_id,
                        reasons_for_encounter_data: $('#reasons_for_encounter_data').val() || '[]'
                    },
                    success: function(data) {
                        console.log(data);
                        autosavesatustext.html('<i class="mdi mdi-floppy"></i> <i class="mdi mdi-cloud-check-outline text-success"></i> Autosaved')

                    },
                    error: function(x, y, z) {
                        console.log(x, y, z);
                        autosavesatustext.html('<i class="mdi mdi-floppy"></i> <i class="mdi mdi-cloud-alert text-danger"></i> Autosave failed')
                    }
                });
            }
        }

        setInterval(autosavenotes, 10000);
        $(function() {
            $('#profile_forms_table').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 5,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": wbRoute('patient-form-list', '/patient-form-list'),
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "form_data",
                        name: "form_data"
                    },
                ],

                "paging": true
            });
        });
        $(function() {
            $('#encounter_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": wbRoute('EncounterHistoryList', '/EncounterHistoryList'),
                    "type": "GET",
                    "data": function(d) {
                        d.exclude_encounter_id = '';
                    }
                },
                "columns": [
                    {
                        data: "info",
                        name: "info",
                        orderable: false
                    }
                ],

                "paging": true
            });
        });
        $(function() {
            $('#investigation_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "(window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '')",
                    "type": "GET"
                },
                "columns": [
                    {
                        data: "info",
                        name: "info",
                        orderable: false
                    }
                ],

                "paging": true
            });
        });
        $(function() {
            $('#imaging_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "(window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '')",
                    "type": "GET"
                },
                "columns": [
                    {
                        data: "info",
                        name: "info",
                        orderable: false
                    }
                ],

                "paging": true
            });
        });
        $(function() {
            $('#presc_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "(window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '')",
                    "type": "GET"
                },
                "columns": [
                    {
                        data: "info",
                        name: "info",
                        orderable: false
                    }
                ],

                "paging": true
            });
        });
        /* ═══ Re-order / Re-prescribe from history (Plan §5.2) ═══ */

        function dispenseFreeFormMed(requestId) {
            Swal.fire({
                title: 'Mark as Dispensed',
                text: 'Enter the quantity dispensed:',
                input: 'number',
                inputValue: 1,
                inputAttributes: {
                    min: 1,
                    step: 1
                },
                showCancelButton: true,
                confirmButtonText: 'Record as Dispensed',
                showLoaderOnConfirm: true,
                preConfirm: (qty) => {
                    return $.ajax({
                        url: wbUrl('/pharmacy-workbench/dispense-free-form'),
                        method: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            request_id: requestId,
                            qty_dispensed: qty
                        }
                    }).catch(error => {
                        Swal.showValidationMessage(
                            `Request failed: ${error.responseJSON?.message || error.statusText}`
                        );
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Success', 'Medication marked as dispensed.', 'success');
                    if ($.fn.DataTable.isDataTable('#presc_history_list')) {
                        $('#presc_history_list').DataTable().ajax.reload(null, false);
                    }
                }
            });
        }

        $(document).on('click', '.re-order-btn', function() {
            var $btn = $(this);
            if ($btn.prop('disabled')) return;

            var type          = $btn.data('type');
            var name          = $btn.data('name');
            var price         = $btn.data('price') || 0;
            var coverageMode  = $btn.data('coverage-mode') || null;
            var claims        = $btn.data('claims') || null;
            var payable       = $btn.data('payable') || null;
            var dose          = $btn.data('dose') || '';
            if (coverageMode === '') coverageMode = null;

            if (type === 'labs') {
                var serviceId = parseInt($btn.data('service-id'));
                if (ClinicalOrdersKit.isAlreadyAdded('labs', serviceId)) {
                    toastr.warning(name + ' is already in your current lab requests');
                    return;
                }
                setSearchValSer(name, serviceId, price, coverageMode, claims, payable);
            } else if (type === 'imaging') {
                var serviceId = parseInt($btn.data('service-id'));
                if (ClinicalOrdersKit.isAlreadyAdded('imaging', serviceId)) {
                    toastr.warning(name + ' is already in your current imaging requests');
                    return;
                }
                setSearchValImaging(name, serviceId, price, coverageMode, claims, payable);
            } else if (type === 'prescriptions') {
                var productId = parseInt($btn.data('product-id'));
                if (ClinicalOrdersKit.isAlreadyAdded('meds', productId)) {
                    toastr.warning(name + ' is already in your current prescriptions');
                    return;
                }
                setSearchValProd(name, productId, price, coverageMode, claims, payable, dose);
            }

            $btn.prop('disabled', true).html('<i class="fa fa-check text-success"></i> Added');
        });
        $(function() {
            $('#scheduled_consult_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": wbUrl('patientsList'),
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "fullname",
                        name: "fullname"
                    },
                    {
                        data: "file_no",
                        name: "file_no"
                    },
                    {
                        data: "hmo_id",
                        name: "hmo_id"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                    {
                        data: "view",
                        name: "view"
                    },
                ],

                "paging": true
            });
        });
        let doseStructuredMode = window.WORKBENCH_CONFIG?.defaultDoseMode || 'simple';

        // Legacy dose functions (toggleDoseMode, buildStructuredDoseHtml, updateStructuredDoseValue,
        // autoCalculateQty, collapseStructuredDose, freqMultiplierMap, durUnitMultiplierMap) removed.
        // All dose logic now lives in ClinicalOrdersKit (clinical-orders-shared.js) per Plan §2.1–§2.3.

        function removeProdRow(obj) {
            var $tr = $(obj).closest('tr');
            var recordId = $tr.data('record-id');
            var recordType = $tr.data('record-type');
            var serviceId = $tr.data('service-id');

            // If row has been auto-saved, fire DELETE to server
            if (recordId && recordType) {
                var deleteUrl;
                if (recordType === 'lab') {
                    deleteUrl = '/encounters/' + encounterId + '/labs/' + recordId;
                } else if (recordType === 'imaging') {
                    deleteUrl = '/encounters/' + encounterId + '/imaging/' + recordId;
                } else if (recordType === 'prescription') {
                    deleteUrl = '/encounters/' + encounterId + '/prescriptions/' + recordId;
                }
                if (deleteUrl) {
                    ClinicalOrdersKit.removeItem({
                        url: deleteUrl,
                        csrfToken: $('meta[name="csrf-token"]').attr('content'),
                        rowSelector: $tr,
                        type: recordType === 'lab' ? 'labs' : (recordType === 'imaging' ? 'imaging' : 'meds'),
                        referenceId: serviceId ? parseInt(serviceId) : null,
                        tableSelector: $tr.closest('tbody').attr('id') ? '#' + $tr.closest('tbody').attr('id') : null
                    });
                    return;
                }
            }
            // Fallback: just remove the row (for medications that aren't auto-saved)
            $tr.remove();
        }

        // Phase 2b (Plan §4.3): Two-phase medication auto-save
        // Phase 1 — instant POST with empty dose; Phase 2 — debounced PUT on dose field changes
        function setSearchValProd(name, id, price, coverageMode = null, claims = null, payable = null, initialDose = '') {
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            const rowId = 'rx_' + Date.now() + '_' + id;
            const coverageBadge = ClinicalOrdersKit.renderCoverageBadge(coverageMode, payable ?? price, claims ?? 0);

            ClinicalOrdersKit.addItem({
                url: wbUrl(`/encounters/${encounterId}/add-prescription`),
                payload: { product_id: id, dose: initialDose },
                csrfToken: csrfToken,
                tableSelector: '#selected-products',
                type: 'meds',
                referenceId: parseInt(id),
                buildRowHtml: function(resp) {
                    const recordId = resp.id;
                    const doseOnchange = "ClinicalOrdersKit.updateDoseValue(this, ''); ";

                    let doseCell;
                    if (doseStructuredMode) {
                        doseCell = '<td>' + ClinicalOrdersKit.buildStructuredDoseHtml({
                            cssPrefix: '',
                            hiddenName: 'consult_presc_dose[]',
                            onchange: doseOnchange,
                            drugName: name,
                            rowId: rowId
                        }) + '<input type="hidden" name="consult_presc_id[]" value="' + id + '"></td>';
                    } else {
                        var simpleDoseCmd = "ClinicalOrdersKit.updateDoseValue(this, '');";
                        var initialDoseEscaped = initialDose ? initialDose.replace(/"/g, '&quot;') : '';
                        doseCell = '<td><input type="text" class="form-control" name="consult_presc_dose[]" value="' + initialDoseEscaped + '" ' +
                            'placeholder="e.g. 500mg BD x 5days" ' +
                            'onfocus="ClinicalOrdersKit.startPeriodicSave(this)" ' +
                            'onblur="ClinicalOrdersKit.stopPeriodicSave(this); ClinicalOrdersKit.cancelIdleTimer(this); ' + simpleDoseCmd + '" ' +
                            'oninput="ClinicalOrdersKit.scheduleIdleUpdate(this, function(){ ' + simpleDoseCmd + ' }, 3000)" required>' +
                            '<input type="hidden" name="consult_presc_id[]" value="' + id + '"></td>';
                    }

                    return '<tr data-record-id="' + recordId + '" data-record-type="prescription" data-service-id="' + id + '" data-drug-name="' + name.replace(/"/g, '&quot;') + '" data-row-id="' + rowId + '">' +
                        '<td style="word-break:break-word; white-space:normal; vertical-align:top;">' +
                            '<div class="fw-semibold" style="word-break:break-word;">' + name + coverageBadge + '</div>' +
                            '<div class="text-muted small mt-1">' + (payable ?? price) + '</div>' +
                        '</td>' +
                        doseCell +
                        '<td><button class="btn btn-danger btn-sm" onclick="removeProdRow(this)"><span class="co-remove-btn"><i class="fa fa-times"></i></span></button></td>' +
                    '</tr>';
                },
                onSuccess: function(resp) {
                    if ($.fn.DataTable.isDataTable('#presc_history_list')) {
                        $('#presc_history_list').DataTable().ajax.reload(null, false);
                    }
                    if (initialDose) {
                        var $row = $('tr[data-row-id="' + rowId + '"]');
                        if ($row.length) {
                            if (typeof doseStructuredMode !== 'undefined' && doseStructuredMode) {
                                var parts = initialDose.split('|');
                                if (parts.length >= 7) {
                                    $row.find('.dose-amount').val(parts[0]);
                                    $row.find('.dose-unit').val(parts[1]);
                                    $row.find('.dose-route').val(parts[2]);
                                    $row.find('.dose-frequency').val(parts[3]);
                                    $row.find('.dose-duration').val(parts[4]);
                                    $row.find('.dose-duration-unit').val(parts[5]);
                                    $row.find('.dose-qty').val(parts[6]);
                                    $row.find('.structured-dose-value').val(initialDose);
                                }
                            }
                        }
                    }
                }
            });
            $('#consult_presc_res').html('');
        }

        let searchProdTimeout = null;
        let searchProdRequest = null;

        function searchProducts(q) {
            if (searchProdRequest) {
                searchProdRequest.abort();
            }
            clearTimeout(searchProdTimeout);

            if (q.length < 2) {
                $('#consult_presc_res').html('').hide();
                return;
            }

            // Show true loading state
            $('#consult_presc_res').html('<li class="list-group-item text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</li>').show();

            searchProdTimeout = setTimeout(function() {
                searchProdRequest = $.ajax({
                    url: wbUrl('live-search-products'),
                    method: "GET",
                    dataType: 'json',
                    data: {
                        term: q,
                        patient_id: window.WORKBENCH_CONFIG?.patientId || ''
                    },
                    success: function(data) {
                        // Clear existing options from the select field
                        $('#consult_presc_res').html('');
                        // data = JSON.parse(data);

                        ClinicalOrdersKit.appendFreeFormLink($('#consult_presc_res'), q, 'Add Free-Form Medication', 'Enter the medication name:', '#consult_presc_search', function(val) { setSearchValProd(val + ' [Free-form]', 'FF_' + val, 0, 'cash', 0, 0); });
                        if(data.length === 0) { $('#consult_presc_res').show(); return; }

                        for (var i = 0; i < data.length; i++) {
                            const item = data[i] || {};
                            const isCombo = item.is_combo || false;
                            const bundleItems = item.bundle_items || [];
                            if (isCombo) { window.comboDataMap = window.comboDataMap || {}; window.comboDataMap[item.id] = item; }
                            const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                            const name = item.product_name || 'Unknown';
                            const code = item.product_code || '';
                            const qty = item.stock && item.stock.current_quantity !== undefined ? item.stock.current_quantity : 0;
                            const price = isCombo
                                ? (item.price && item.price.sale_price !== undefined ? item.price.sale_price : (item.payable_amount || 0))
                                : (item.price && item.price.initial_sale_price !== undefined ? item.price.initial_sale_price : 0);
                            const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                            const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                            const mode = item.coverage_mode || null;

                            let onClick = '';
                            let alreadyAdded = false;

                            if (isCombo) {
                                alreadyAdded = false; // combos are always available
                                onClick = 'applyComboEncounter(' + item.id + ', "' + wbRoute('encounters.applyCombo', '/encounters/applyCombo') + '")';
                            } else {
                                const displayName = `${name}[${code}](${qty} avail.)`;
                                alreadyAdded = ClinicalOrdersKit.isAlreadyAdded('meds', parseInt(item.id));
                                onClick = alreadyAdded ? '' : `setSearchValProd('${displayName.replace(/'/g,"\\'")}', '${item.id}', '${price}', '${mode}', '${claims}', '${payable}')`;
                            }

                            var mk = ClinicalOrdersKit.renderSearchResultItem({
                                id: item.id,
                                category: category,
                                name: name,
                                code: code,
                                qty: isCombo ? null : qty,
                                price: price,
                                payable: payable,
                                claims: claims,
                                mode: mode,
                                alreadyAdded: alreadyAdded,
                                alreadyLabel: 'Already Added',
                                onClick: onClick,
                                isCombo: isCombo,
                                bundleItems: bundleItems
                            });
                            $('#consult_presc_res').append(mk);
                            $('#consult_presc_res').show();
                        }
                    },
                    error: function(jqXHR, textStatus) {
                        if (textStatus !== 'abort') {
                            $('#consult_presc_res').html('<li class="list-group-item text-center text-danger">Error fetching results</li>').show();
                        }
                    }
                });
            }, 300);
        }
        function switch_tab(e, id_of_next_tab) {
            if (e) {
                if (typeof e.preventDefault === 'function') e.preventDefault();
                if (typeof e.stopPropagation === 'function') e.stopPropagation();
            }

            // Intercept leaving Treatment Plans tab if no active plan is selected
            if (id_of_next_tab !== 'treatment_plans_tab' && id_of_next_tab !== 'treatment_plans') {
                var $tpPane = $('#treatment_plans');
                var isTpActive = ($tpPane.length && $tpPane.hasClass('active')) || $('#treatment_plans_tab').hasClass('active');
                var tpReq = (typeof _PI_TP_REQUIRED !== 'undefined' && _PI_TP_REQUIRED) || false;
                var needsPrompt = tpReq ? !window._activeTreatmentPlan : (!window._activeTreatmentPlan && !window._hasShownNoActivePlanPrompt);
                if (isTpActive && needsPrompt && $('#tpNoActivePlanPromptModal').length) {
                    window.tpPendingNavClick = id_of_next_tab;
                    $('#tpNoActivePlanPromptModal').modal('show');
                    return false;
                }
            }

            var $tab = $('#' + id_of_next_tab);

            // Explicitly deactivate all top-level tab panes to prevent tab stacking
            $('#myTabContent > .tab-pane').removeClass('show active');

            // Find target pane ID from href or data attributes
            var targetPaneId = $tab.attr('data-bs-target') || $tab.attr('data-target') || $tab.attr('href');
            var $targetPane = null;
            if (targetPaneId && targetPaneId.indexOf('#') !== -1) {
                $targetPane = $('#' + targetPaneId.replace('#', ''));
            }
            if (!$targetPane || !$targetPane.length) {
                var inferredId = id_of_next_tab.replace('_tab', '').replace('mobile_', '').replace('_data', '');
                $targetPane = $('#' + inferredId);
            }

            if ($targetPane && $targetPane.length) {
                $targetPane.addClass('show active');
            }

            // Sync sidebar / mobile tab active states
            $('.encounter-sidebar .nav-link, .mobile-bottom-nav .nav-link').removeClass('active');
            $tab.addClass('active');

            if (typeof window.updateFloatingNav === 'function') {
                window.updateFloatingNav(id_of_next_tab);
            }

            if ($tab.length && typeof $.fn.tab === 'function') {
                try { $tab.tab('show'); } catch(err) {}
            }
            
            // Explicitly trigger the shown event because manual class manipulation above 
            // often causes Bootstrap to skip firing it.
            if ($tab.length) {
                $tab.trigger('shown.bs.tab');
                if ($tab[0]) {
                    try {
                        $tab[0].dispatchEvent(new Event('shown.bs.tab', { bubbles: true, cancelable: true }));
                    } catch(e) {}
                }
            }

            // Scroll to top
            $('html, body, .content-wrapper, .encounter-sidebar-wrapper').animate({ scrollTop: 0 }, 'fast');
        }

        function toggleAdmitNote(obj) {
            var opt = $(obj).val();
            console.log(opt);
            if (opt == '0') {
                $('#admit-note-div').html('');
            } else {
                var mk = `
                    <textarea name="admit_note" id="admit_note" class="form-control" placeholder="Enter brief note"></textarea>
                `;
                $('#admit-note-div').append(mk);
            }
        }


        function setSearchValSer(name, id, price, coverageMode = null, claims = null, payable = null) {
            var csrfToken = $('meta[name="csrf-token"]').attr('content');

            ClinicalOrdersKit.addItem({
                url: wbUrl('/encounters/' + encounterId + '/add-lab'),
                payload: { service_id: id, note: '' },
                csrfToken: csrfToken,
                tableSelector: '#selected-services',
                type: 'labs',
                referenceId: parseInt(id),
                buildRowHtml: function(response) {
                    var coverageBadge = coverageMode ? '<div class="small mt-1"><span class="badge bg-info">' + (coverageMode||'').toUpperCase() + '</span> <span class="text-danger">Pay: ' + (payable ?? price) + '</span> <span class="text-success">Claims: ' + (claims ?? 0) + '</span></div>' : '';
                    return '<tr data-record-id="' + response.id + '" data-record-type="lab" data-service-id="' + id + '">' +
                        '<td>' + name + coverageBadge + '</td>' +
                        '<td>' + (payable ?? price) + '</td>' +
                        '<td>' +
                            '<input type="text" class="form-control" name="consult_invest_note[]" placeholder="e.g. Fasting, urgent, repeat in 2wks" ' +
                            'onblur="ClinicalOrdersKit.cancelIdleTimer(this); ClinicalOrdersKit.debouncedUpdate({url:\'/encounters/' + encounterId + '/labs/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + csrfToken + '\',flashTarget:this.closest(\'td\')})" ' +
                            'oninput="ClinicalOrdersKit.scheduleIdleUpdate(this, function(){ ClinicalOrdersKit.debouncedUpdate({url:\'/encounters/' + encounterId + '/labs/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + csrfToken + '\',flashTarget:this.closest(\'td\')}) }, 3000)">' +
                            '<input type="hidden" name="consult_invest_id[]" value="' + id + '">' +
                        '</td>' +
                        '<td><button class="btn btn-danger btn-sm" onclick="removeProdRow(this)"><span class="co-remove-btn"><i class="fa fa-times"></i></span></button></td>' +
                    '</tr>';
                },
                onSuccess: function(resp) {
                    if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
                        $('#investigation_history_list').DataTable().ajax.reload(null, false);
                    }
                }
            });

            $('#consult_invest_res').html('');
        }

        function setSearchValImaging(name, id, price, coverageMode = null, claims = null, payable = null) {
            var csrfToken = $('meta[name="csrf-token"]').attr('content');

            ClinicalOrdersKit.addItem({
                url: wbUrl('/encounters/' + encounterId + '/add-imaging'),
                payload: { service_id: id, note: '' },
                csrfToken: csrfToken,
                tableSelector: '#selected-imaging-services',
                type: 'imaging',
                referenceId: parseInt(id),
                buildRowHtml: function(response) {
                    var coverageBadge = coverageMode ? '<div class="small mt-1"><span class="badge bg-info">' + (coverageMode||'').toUpperCase() + '</span> <span class="text-danger">Pay: ' + (payable ?? price) + '</span> <span class="text-success">Claims: ' + (claims ?? 0) + '</span></div>' : '';
                    return '<tr data-record-id="' + response.id + '" data-record-type="imaging" data-service-id="' + id + '">' +
                        '<td>' + name + coverageBadge + '</td>' +
                        '<td>' + (payable ?? price) + '</td>' +
                        '<td>' +
                            '<input type="text" class="form-control" name="consult_imaging_note[]" placeholder="e.g. R/O fracture, contrast required" ' +
                            'onblur="ClinicalOrdersKit.cancelIdleTimer(this); ClinicalOrdersKit.debouncedUpdate({url:\'/encounters/' + encounterId + '/imaging/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + csrfToken + '\',flashTarget:this.closest(\'td\')})" ' +
                            'oninput="ClinicalOrdersKit.scheduleIdleUpdate(this, function(){ ClinicalOrdersKit.debouncedUpdate({url:\'/encounters/' + encounterId + '/imaging/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + csrfToken + '\',flashTarget:this.closest(\'td\')}) }, 3000)">' +
                            '<input type="hidden" name="consult_imaging_id[]" value="' + id + '">' +
                        '</td>' +
                        '<td><button class="btn btn-danger btn-sm" onclick="removeProdRow(this)"><span class="co-remove-btn"><i class="fa fa-times"></i></span></button></td>' +
                    '</tr>';
                },
                onSuccess: function(resp) {
                    if ($.fn.DataTable.isDataTable('#imaging_history_list')) {
                        $('#imaging_history_list').DataTable().ajax.reload(null, false);
                    }
                }
            });

            $('#consult_imaging_res').html('');
        }

        /**
         * Apply a service combo bundle to the encounter.
         * Shows a confirmation modal first so the user understands the bundle contents.
         */
        function applyComboEncounter(comboId, route) {
            var comboData = (window.comboDataMap || {})[comboId] || {};
            var name = comboData.service_name || comboData.product_name || 'Combo';
            var bundleItems = comboData.bundle_items || [];
            var price   = parseFloat(comboData.base_price  || 0);
            var payable = parseFloat(comboData.payable_amount != null ? comboData.payable_amount : price);
            var claims  = parseFloat(comboData.claims_amount  || 0);
            var mode    = comboData.coverage_mode || null;

            // Dismiss the dropdown immediately
            $('#consult_invest_res, #consult_imaging_res, #consult_presc_res').html('');
            $('#consult_invest_search, #consult_imaging_search, #consult_presc_search').val('');


            ComboConfirmModal.show({
                name        : name,
                bundleItems : bundleItems,
                price       : price,
                payable     : payable,
                claims      : claims,
                mode        : mode,
                onConfirm   : function() {
                    $.ajax({
                        type: 'POST',
                        url: route,
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data: { 
                            service_id: comboId,
                            treatment_plan_id: window._activeTreatmentPlan ? window._activeTreatmentPlan.id : null,
                            treatment_plan_name: window._activeTreatmentPlan ? window._activeTreatmentPlan.name : null
                        },
                        success: function(response) {
                            if (response.success) {
                                toastr.success('Combo applied — all items added.', '', { timeOut: 5000 });
                                if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
                                    $('#investigation_history_list').DataTable().ajax.reload(null, false);
                                }
                                if ($.fn.DataTable.isDataTable('#imaging_history_list')) {
                                    $('#imaging_history_list').DataTable().ajax.reload(null, false);
                                }
                                if ($.fn.DataTable.isDataTable('#presc_history_list')) {
                                    $('#presc_history_list').DataTable().ajax.reload(null, false);
                                }
                                if (typeof refreshProceduresList === 'function') refreshProceduresList();
                            } else {
                                toastr.error(response.message || 'Failed to apply combo');
                            }
                        },
                        error: function(xhr) {
                            const msg = xhr.responseJSON?.message || 'Network error occurred';
                            toastr.error(msg, '', { timeOut: 5000 });
                        }
                    });
                }
            });
        }

        function searchServices(q) {
            if (q != "") {
                searchRequest = $.ajax({
                    url: wbUrl('live-search-services'),
                    method: "GET",
                    dataType: 'json',
                    data: {
                        term: q,
                        patient_id: window.WORKBENCH_CONFIG?.patientId || ''
                    },
                    success: function(data) {
                        $('#consult_invest_res').html('');
                        console.log(data);

                        ClinicalOrdersKit.appendFreeFormLink($('#consult_invest_res'), q, 'Add Free-Form Lab Test', 'Enter the lab test name:', '#consult_invest_search', function(val) { setSearchValSer(val + ' [Free-form]', 'FF_' + val, 0, 'cash', 0, 0); });
                        if(data.length === 0) { $('#consult_invest_res').show(); return; }

                        for (var i = 0; i < data.length; i++) {
                            const item = data[i] || {};
                            const category = item.category || 'N/A';
                            const name = item.service_name || 'Unknown';
                            const code = item.service_code || '';
                            const basePrice = item.base_price !== undefined ? item.base_price : 0;
                            const payable = item.payable_amount !== undefined ? item.payable_amount : basePrice;
                            const claims = item.claims_amount !== undefined ? item.claims_amount : 0;
                            const mode = item.coverage_mode || null;
                            const isCombo = item.is_combo || false;
                            const bundleItems = item.bundle_items || [];
                            if (isCombo) { window.comboDataMap = window.comboDataMap || {}; window.comboDataMap[item.id] = item; }

                            const displayName = `${name}[${code}]`;
                            const alreadyAdded = ClinicalOrdersKit.isAlreadyAdded('labs', parseInt(item.id));

                            let onClick = '';
                            if (!alreadyAdded) {
                                if (isCombo) {
                                    // For combos, show confirmation modal first
                                    onClick = 'applyComboEncounter(' + item.id + ', "' + wbRoute('encounters.applyCombo', '/encounters/applyCombo') + '")';
                                } else {
                                    // For direct services, use existing handler
                                    onClick = `setSearchValSer('${displayName}', '${item.id}', '${basePrice}', '${mode}', '${claims}', '${payable}')`;
                                }
                            }

                            // Render result with combo detection
                            var mk = ClinicalOrdersKit.renderSearchResultItem({
                                id: item.id,
                                category: category,
                                name: name,
                                code: code,
                                price: basePrice,
                                payable: payable,
                                claims: claims,
                                mode: mode,
                                alreadyAdded: alreadyAdded,
                                alreadyLabel: 'Already Added',
                                onClick: onClick,
                                isCombo: isCombo,
                                bundleItems: bundleItems
                            });
                            $('#consult_invest_res').append(mk);
                            $('#consult_invest_res').show();
                        }
                    }
                });
            } else {
                $('#consult_invest_res').html('');
            }
        }
        function searchImagingServices(q) {
            if (q != "") {
                searchRequest = $.ajax({
                    url: wbUrl('live-search-services'),
                    method: "GET",
                    dataType: 'json',
                    data: {
                        term: q,
                        category_id: (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : ''),
                        patient_id: window.WORKBENCH_CONFIG?.patientId || ''
                    },
                    success: function(data) {
                        $('#consult_imaging_res').html('');
                        console.log(data);

                        ClinicalOrdersKit.appendFreeFormLink($('#consult_imaging_res'), q, 'Add Free-Form Imaging Request', 'Enter the imaging request name:', '#consult_imaging_search', function(val) { setSearchValImaging(val + ' [Free-form]', 'FF_' + val, 0, 'cash', 0, 0); });
                        if(data.length === 0) { $('#consult_imaging_res').show(); return; }

                        for (var i = 0; i < data.length; i++) {
                            const item = data[i] || {};
                            const category = item.category || 'N/A';
                            const name = item.service_name || 'Unknown';
                            const code = item.service_code || '';
                            const basePrice = item.base_price !== undefined ? item.base_price : 0;
                            const payable = item.payable_amount !== undefined ? item.payable_amount : basePrice;
                            const claims = item.claims_amount !== undefined ? item.claims_amount : 0;
                            const mode = item.coverage_mode || null;
                            const isCombo = item.is_combo || false;
                            const bundleItems = item.bundle_items || [];
                            if (isCombo) { window.comboDataMap = window.comboDataMap || {}; window.comboDataMap[item.id] = item; }

                            const displayName = `${name}[${code}]`;
                            const alreadyAdded = ClinicalOrdersKit.isAlreadyAdded('imaging', parseInt(item.id));

                            let onClick = '';
                            if (!alreadyAdded) {
                                if (isCombo) {
                                    onClick = 'applyComboEncounter(' + item.id + ', "' + wbRoute('encounters.applyCombo', '/encounters/applyCombo') + '")';
                                } else {
                                    onClick = `setSearchValImaging('${displayName}', '${item.id}', '${basePrice}', '${mode}', '${claims}', '${payable}')`;
                                }
                            }

                            var mk = ClinicalOrdersKit.renderSearchResultItem({
                                id: item.id,
                                category: category,
                                name: name,
                                code: code,
                                price: basePrice,
                                payable: payable,
                                claims: claims,
                                mode: mode,
                                alreadyAdded: alreadyAdded,
                                alreadyLabel: 'Already Added',
                                onClick: onClick,
                                isCombo: isCombo,
                                bundleItems: bundleItems
                            });
                            $('#consult_imaging_res').append(mk);
                            $('#consult_imaging_res').show();
                        }
                    }
                });
            } else {
                $('#consult_imaging_res').html('');
            }
        }
        $(function() {
            $('#nurse_note_hist_1').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "(window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '')",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
        $(function() {
            $('#nurse_note_hist_2').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "(window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '')",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
        $(function() {
            $('#nurse_note_hist_3').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "(window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '')",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
        $(function() {
            $('#nurse_note_hist_4').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "(window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '')",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
        $(function() {
            $('#nurse_note_hist_5').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "(window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '')",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
        function setNoteInModal(obj) {
            $('#note_type_name_').text($(obj).attr('data-service-name'));
            $('#nursing_note_template_').html($(obj).attr('data-template'));
            $('#nursingNoteModal').modal('show');
        }
        function toggle_group(class_) {
            var x = document.getElementsByClassName(class_);
            for (i = 0; i < x.length; i++) {
                if (x[i].style.display === "none") {
                    x[i].style.display = "block";
                } else {
                    x[i].style.display = "none";
                }
            }

        }
        // setResViewInModal, PrintElem, getFileIcon now provided by invest_res_view_js partial

        // ============================================
        // INVESTIGATION RESULT ENTRY FUNCTIONS
        // Uses shared InvestResultEntry module
        // ============================================

        // Lab result entry (called from investigation history DataTable "Enter Result" button)
        function enterLabResult(requestId) {
            window._investResultContext = { type: 'lab', id: requestId };
            InvestResultEntry.enterResult(
                requestId,
                `/lab-workbench/lab-service-requests/${requestId}`,
                `/lab-workbench/lab-service-requests/${requestId}/attachments`,
                wbRoute('lab.saveResult', '/lab/saveResult')
            );
        }

        // Lab result edit (called from investigation history DataTable "Edit" button)
        function editLabResult(obj) {
            const requestId = $(obj).data('id');
            InvestResultEntry.editResult(
                requestId,
                `/lab-workbench/lab-service-requests/${requestId}`,
                `/lab-workbench/lab-service-requests/${requestId}/attachments`,
                wbRoute('lab.saveResult', '/lab/saveResult')
            );
        }

        // Imaging result entry (called from imaging history DataTable "Enter Result" button)
        function enterImagingResult(requestId) {
            window._investResultContext = { type: 'imaging', id: requestId };
            InvestResultEntry.enterResult(
                requestId,
                `/imaging-workbench/imaging-service-requests/${requestId}`,
                `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
                wbRoute('imaging.saveResult', '/imaging/saveResult')
            );
        }

        // Imaging result edit (called from imaging history DataTable "Edit" button)
        function editImagingResult(obj) {
            const requestId = $(obj).data('id');
            InvestResultEntry.editResult(
                requestId,
                `/imaging-workbench/imaging-service-requests/${requestId}`,
                `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
                wbRoute('imaging.saveResult', '/imaging/saveResult')
            );
        }

        // Self-approve config (server-baked JS constants)
        var _PI_LAB_REQ_APPROVAL     = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');
        var _PI_IMG_REQ_APPROVAL     = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');
        var _PI_DR_SELF_LAB          = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');
        var _PI_NR_SELF_LAB          = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');
        var _PI_DR_SELF_IMG          = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');
        var _PI_NR_SELF_IMG          = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');
        var _PI_TP_ENABLED           = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');
        var _PI_TP_REQUIRED          = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');

        function _autoApproveIfEnabled(requestId, type) {
            // Never auto-approve edits
            if ($('#invest_res_is_edit').val() == '1') { return; }

            var requiresApproval = (type === 'lab') ? _PI_LAB_REQ_APPROVAL : _PI_IMG_REQ_APPROVAL;
            if (!requiresApproval) { return; }

            var canSelf = (type === 'lab')
                ? (_PI_DR_SELF_LAB || _PI_NR_SELF_LAB)
                : (_PI_DR_SELF_IMG || _PI_NR_SELF_IMG);
            if (!canSelf) { return; }

            var approveUrl = (type === 'lab')
                ? '/lab-workbench/self-approve/' + requestId
                : '/imaging-workbench/self-approve/' + requestId;

            $.post(approveUrl, { _token: $('meta[name="csrf-token"]').attr('content') })
                .done(function (res) {
                    if (res && res.success) {
                        toastr.success('Result approved automatically.');
                    } else {
                        toastr.warning('Result saved. Auto-approval failed: ' + ((res && res.message) || ''));
                    }
                })
                .fail(function () {
                    toastr.warning('Result saved but auto-approval could not be completed.');
                });
        }

        // Initialize shared result entry module
        InvestResultEntry.bindFormSubmit(function() {
            if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
                $('#investigation_history_list').DataTable().ajax.reload(null, false);
            }
            if ($.fn.DataTable.isDataTable('#imaging_history_list')) {
                $('#imaging_history_list').DataTable().ajax.reload(null, false);
            }
            var ctx = window._investResultContext;
            if (ctx) {
                _autoApproveIfEnabled(ctx.id, ctx.type);
                window._investResultContext = null;
            }
        });
        // Wait for document to be ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize nurse chart tabs - using bootstrap 5 syntax
            const nurseChartTabTriggers = document.querySelectorAll('#nurseChartTabs a[data-toggle="tab"]');
            nurseChartTabTriggers.forEach(trigger => {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabId = this.getAttribute('href');
                    const tab = new bootstrap.Tab(trigger);
                    tab.show();
                });
            });

            // Initialize date filter with default dates (last 30 days)
            initDateFilter();

            // Add event listener for apply filter button
            document.getElementById('apply-date-filter').addEventListener('click', function() {
                loadNurseCharts();
            });

            // Add event listener for reset filter button
            document.getElementById('reset-date-filter').addEventListener('click', function() {
                initDateFilter();
                loadNurseCharts();
            });

            // Add event listener for nurse chart tab activation
            $('#nurse_charts_tab').on('shown.bs.tab', function(e) {
                loadNurseCharts();
            });

            // Helper function to extract medication name from various possible data structures
            function extractMedicationName(obj, prescriptions) {
                if (!obj) return 'N/A';

                // Try different possible paths to find the medication name
                if (obj.medication_name) return obj.medication_name;
                if (obj.product_name) return obj.product_name;
                if (obj.name) return obj.name;

                if (obj.product) {
                    if (typeof obj.product === 'object') {
                        return obj.product.product_name || obj.product.name || 'N/A';
                    }
                    return obj.product;
                }

                if (obj.prescription && obj.prescription.product) {
                    const product = obj.prescription.product;
                    return product.product_name || product.name || 'N/A';
                }

                // For administration records with a product_or_service_request_id
                // Look up the corresponding prescription
                if (obj.product_or_service_request_id && prescriptions && prescriptions.length> 0) {
                    const prescription = prescriptions.find(p => p.id === obj.product_or_service_request_id);
                    if (prescription) {
                        // Recursively call the function to extract the name from the prescription
                        // Passing null as second parameter to avoid infinite recursion
                        return extractMedicationName(prescription, null);
                    }
                }

                // Extract from dosage if it contains medication name
                if (obj.dose && typeof obj.dose === 'string' && obj.dose.includes(':')) {
                    const parts = obj.dose.split(':');
                    if (parts.length> 0) return parts[0].trim();
                }

                return 'N/A';
            }

            // Date utility functions - 30 days with today in the middle
            function getDefaultStartDate() {
                const date = new Date();
                date.setDate(date.getDate() - 15); // 15 days before today
                return date.toISOString().split('T')[0]; // Format as YYYY-MM-DD
            }

            function getDefaultEndDate() {
                const date = new Date();
                date.setDate(date.getDate() + 15); // 15 days after today
                return date.toISOString().split('T')[0]; // Format as YYYY-MM-DD
            }

            function formatDateForApi(dateString) {
                if (!dateString) return '';
                return dateString; // Already in YYYY-MM-DD format for API
            }

            // Initialize date filter with defaults
            function initDateFilter() {
                document.getElementById('chart-date-from').value = getDefaultStartDate();
                document.getElementById('chart-date-to').value = getDefaultEndDate();
            }

            // Format a date for display
            function formatDateForDisplay(dateString) {
                if (!dateString) return '';
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }

            // Update the date range summary display
            function updateDateRangeSummary(startDate, endDate) {
                const startFormatted = formatDateForDisplay(startDate);
                const endFormatted = formatDateForDisplay(endDate);
                const summaryElement = document.getElementById('date-range-summary');

                if (startDate && endDate) {
                    summaryElement.textContent = `Showing data from ${startFormatted} to ${endFormatted}`;
                } else if (startDate) {
                    summaryElement.textContent = `Showing data from ${startFormatted} onwards`;
                } else if (endDate) {
                    summaryElement.textContent = `Showing data up to ${endFormatted}`;
                } else {
                    summaryElement.textContent = `Showing all data`;
                }
            }

            // Load nurse charts data
            function loadNurseCharts() {
                const patientId = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');

                // Initialize date filter if needed
                if (!document.getElementById('chart-date-from').value) {
                    initDateFilter();
                }

                // Get current filter values
                const startDate = document.getElementById('chart-date-from').value;
                const endDate = document.getElementById('chart-date-to').value;

                // Update the date range summary
                updateDateRangeSummary(startDate, endDate);

                // Load medication chart with date filter
                loadMedicationChart(patientId, startDate, endDate);

                // Load intake/output charts with date filter
                loadIntakeOutputCharts(patientId, startDate, endDate);
            }

            // Function to load medication chart
            function loadMedicationChart(patientId, startDate, endDate) {
                // Show loading indicator
                const container = document.getElementById('medication-chart-content');
                container.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading medication data...</p>
                    </div>
                `;

                // Build URL with query parameters
                const url = new URL(wbUrl('/patients/' + patientId + '/nurse-chart/medication'));
                if (startDate) url.searchParams.append('start_date', formatDateForApi(startDate));
                if (endDate) url.searchParams.append('end_date', formatDateForApi(endDate));

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        console.log('Medication Chart Data:', data);
                        const container = document.getElementById('medication-chart-content');

                        // Update summary stats
                        const prescriptionCount = data.prescriptions ? data.prescriptions.length : 0;
                        const administrationCount = data.administrations ? data.administrations.length : 0;

                        // Count direct entries (ward_stock + patient_own that are not linked to a prescription)
                        const prescriptionIds = new Set((data.prescriptions || []).map(p => p.id));
                        const directAdmins = (data.administrations || []).filter(a =>
                            !a.product_or_service_request_id || !prescriptionIds.has(a.product_or_service_request_id)
                        );
                        const wardStockCount = directAdmins.filter(a => a.drug_source === 'ward_stock').length;
                        const patientOwnCount = directAdmins.filter(a => a.drug_source === 'patient_own').length;

                        let statsHtml = `
                            <span class="badge bg-primary rounded-pill fs-6">
                                <i class="mdi mdi-pill me-1"></i> ${prescriptionCount} medications
                            </span>
                            <span class="badge bg-info rounded-pill fs-6">
                                <i class="mdi mdi-history me-1"></i> ${administrationCount} administrations
                            </span>`;
                        if (wardStockCount> 0) {
                            statsHtml += `
                            <span class="badge bg-primary rounded-pill fs-6" style="background-color: #0d6efd !important;">
                                <i class="mdi mdi-hospital-box me-1"></i> ${wardStockCount} ward stock
                            </span>`;
                        }
                        if (patientOwnCount> 0) {
                            statsHtml += `
                            <span class="badge bg-warning text-dark rounded-pill fs-6">
                                <i class="mdi mdi-account-arrow-left me-1"></i> ${patientOwnCount} patient's own
                            </span>`;
                        }
                        document.getElementById('data-summary-stats').innerHTML = statsHtml;

                        // Render calendar grid view
                        renderDoctorMedicationCalendar(data, startDate, endDate);
                    })
                    .catch(error => {
                        console.error('Error loading medication chart:', error);
                        document.getElementById('medication-chart-content').innerHTML =
                            '<div class="alert alert-danger">Failed to load medication chart data. Please try again later.</div>';
                    });
            }

            // Function to render medication calendar grid for doctor's view (read-only)
            // Unified calendar showing ALL medications in one grid
            function renderDoctorMedicationCalendar(data, startDateStr, endDateStr) {
                const container = document.getElementById('medication-chart-content');

                console.log('Rendering unified calendar with data:', data);
                console.log('Prescriptions count:', data.prescriptions ? data.prescriptions.length : 0);
                console.log('Administrations count:', data.administrations ? data.administrations.length : 0);

                // ─── Build unified prescriptions array: pharmacy + direct entries ───
                const allAdmins = data.administrations || [];
                const prescriptionIds = new Set((data.prescriptions || []).map(p => p.id));

                // Find orphan administrations (ward_stock/patient_own not linked to any prescription)
                const orphanAdmins = allAdmins.filter(a =>
                    !a.product_or_service_request_id || !prescriptionIds.has(a.product_or_service_request_id)
                );

                // Group orphans into virtual prescriptions by drug_source + identifier
                const directGroups = {};
                orphanAdmins.forEach(admin => {
                    let key;
                    if (admin.drug_source === 'patient_own') {
                        key = 'po_' + (admin.external_drug_name || 'unknown').toLowerCase();
                    } else if (admin.drug_source === 'ward_stock') {
                        key = 'ws_' + (admin.product_id || admin.id);
                    } else {
                        return; // skip pharmacy_dispensed orphans (shouldn't happen)
                    }
                    if (!directGroups[key]) {
                        directGroups[key] = {
                            admins: [],
                            drug_source: admin.drug_source,
                            product_id: admin.product_id,
                            external_drug_name: admin.external_drug_name,
                            product_name: admin.product_name,
                        };
                    }
                    directGroups[key].admins.push(admin);
                });

                // Create virtual prescription entries for direct groups
                const virtualPrescriptions = Object.entries(directGroups).map(([key, group]) => {
                    const isPatientOwn = group.drug_source === 'patient_own';
                    const medName = isPatientOwn
                        ? (group.external_drug_name || 'Unknown Drug')
                        : (group.product_name || 'Unknown Product');

                    return {
                        id: 'direct_' + key,
                        is_direct_entry: true,
                        drug_source: group.drug_source,
                        product_name: medName,
                        external_drug_name: group.external_drug_name,
                        product_id: group.product_id,
                        schedules: [], // direct entries use unscheduled administrations
                        _direct_admins: group.admins, // stash for calendar rendering
                    };
                });

                // Merge prescription + virtual entries
                const unifiedPrescriptions = [
                    ...(data.prescriptions || []).map(p => ({ ...p, is_direct_entry: false, drug_source: 'pharmacy_dispensed' })),
                    ...virtualPrescriptions,
                ];

                if (unifiedPrescriptions.length === 0) {
                    container.innerHTML = '<div class="alert alert-info">No active medications found for this period.</div>';
                    return;
                }

                // Assign unique colors to each medication
                const medicationColors = [
                    { bg: '#e3f2fd', border: '#1976d2', text: '#0d47a1' },  // Blue
                    { bg: '#e8f5e9', border: '#388e3c', text: '#1b5e20' },  // Green
                    { bg: '#fff3e0', border: '#f57c00', text: '#e65100' },  // Orange
                    { bg: '#f3e5f5', border: '#8e24aa', text: '#6a1b9a' },  // Purple
                    { bg: '#e0f7fa', border: '#0097a7', text: '#006064' },  // Cyan
                    { bg: '#fce4ec', border: '#c2185b', text: '#880e4f' },  // Pink
                    { bg: '#fff8e1', border: '#ffa000', text: '#ff6f00' },  // Amber
                    { bg: '#e8eaf6', border: '#3f51b5', text: '#1a237e' },  // Indigo
                    { bg: '#efebe9', border: '#6d4c41', text: '#3e2723' },  // Brown
                    { bg: '#eceff1', border: '#546e7a', text: '#263238' },  // Blue Grey
                ];

                // Build medication color map
                const medColorMap = {};
                unifiedPrescriptions.forEach((p, idx) => {
                    medColorMap[p.id] = medicationColors[idx % medicationColors.length];
                });

                // Add unified calendar CSS
                let html = ``;

                // Parse dates - default 30 days with today in the middle
                let start, end;
                if (startDateStr) {
                    start = new Date(startDateStr);
                } else {
                    start = new Date();
                    start.setDate(start.getDate() - 15);
                }
                if (endDateStr) {
                    end = new Date(endDateStr);
                } else {
                    end = new Date();
                    end.setDate(end.getDate() + 15);
                }
                start.setHours(0, 0, 0, 0);
                end.setHours(23, 59, 59, 999);

                html += '<div class="unified-med-calendar">';

                // Weekday header
                html += '<div class="calendar-weekday-header">';
                ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
                    html += `<div class="weekday-name">${day}</div>`;
                });
                html += '</div>';

                // Calendar grid
                html += '<div class="medication-calendar-grid">';

                // Add empty cells for alignment
                const daysBeforeStart = start.getDay();
                for (let i = 0; i < daysBeforeStart; i++) {
                    html += '<div class="calendar-day-cell empty-day"></div>';
                }

                // Today reference
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                // Generate days
                const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                let currentDate = new Date(start);

                while (currentDate <= end) {
                    const dateStr = currentDate.toISOString().split('T')[0];
                    const dayNum = currentDate.getDate();
                    const dayOfWeek = currentDate.getDay();
                    const isToday = currentDate.toDateString() === today.toDateString();
                    const isPast = currentDate < today;
                    const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

                    // Build cell classes
                    let cellClass = 'calendar-day-cell';
                    if (isToday) cellClass += ' today';
                    else if (isPast) cellClass += ' past-date';
                    if (isWeekend && !isToday) cellClass += ' weekend';

                    html += `<div class="${cellClass}">`;
                    html += `<div class="day-header">
                        <span class="day-name">${dayNames[dayOfWeek]}</span>
                        <span class="day-number">${dayNum}</span>
                    </div>`;
                    html += '<div class="schedule-items">';

                    // Collect all items for this day across ALL medications
                    let dayItems = [];

                    unifiedPrescriptions.forEach(prescription => {
                        const medicationName = prescription.is_direct_entry
                            ? (prescription.product_name || prescription.external_drug_name || 'Unknown')
                            : extractMedicationName(prescription, null);
                        const color = medColorMap[prescription.id];
                        const schedules = prescription.schedules || [];

                        // Resolve administrations depending on source type
                        let administrations;
                        if (prescription.is_direct_entry) {
                            // Direct entries: use stashed admins from the virtual prescription
                            administrations = prescription._direct_admins || [];
                        } else {
                            // Regular prescriptions: filter by POSR id
                            administrations = (data.administrations || []).filter(a =>
                                a.product_or_service_request_id === prescription.id
                            );
                        }

                        // Drug source badge suffix for display
                        const sourceBadge = prescription.drug_source === 'ward_stock' ? ' [WS]'
                            : prescription.drug_source === 'patient_own' ? ' [PO]' : '';

                        // Log for debugging
                        if (currentDate.toDateString() === today.toDateString()) {
                            console.log(`${medicationName}: ${schedules.length} schedules, ${administrations.length} administrations`);
                        }

                        // Find schedules for this day
                        const daySchedules = schedules.filter(s => {
                            if (!s.scheduled_time && !s.scheduled_at) return false;
                            const schedTime = s.scheduled_time || s.scheduled_at;
                            const schedDate = new Date(schedTime);
                            return schedDate.toDateString() === currentDate.toDateString();
                        });

                        // Find administrations for this day
                        const dayAdministrations = administrations.filter(a => {
                            if (!a.administered_at) return false;
                            const adminDate = new Date(a.administered_at);
                            return adminDate.toDateString() === currentDate.toDateString();
                        });

                        // Process schedules
                        daySchedules.forEach(schedule => {
                            const schedTime = schedule.scheduled_time || schedule.scheduled_at;
                            const schedDate = new Date(schedTime);
                            const time = schedDate.toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            });

                            // Check if administered
                            const admin = dayAdministrations.find(a => a.schedule_id === schedule.id);

                            // Check if discontinued
                            const isDiscontinued = prescription.discontinued_at &&
                                new Date(prescription.discontinued_at) < new Date(schedTime);

                            let status = 'scheduled';
                            let icon = 'mdi-clock-outline';
                            let tooltip = `<strong>${medicationName}</strong><br>Scheduled: ${time}<br>Dose: ${schedule.dose || 'N/A'}<br>Route: ${schedule.route || 'N/A'}`;

                            if (isDiscontinued) {
                                status = 'discontinued';
                                icon = 'mdi-close-circle-outline';
                                tooltip = `<strong>${medicationName}</strong><br>Discontinued<br>Time: ${time}`;
                            } else if (admin) {
                                status = 'given';
                                icon = 'mdi-check-circle';
                                const adminTime = new Date(admin.administered_at).toLocaleTimeString('en-US', {
                                    hour: 'numeric',
                                    minute: '2-digit',
                                    hour12: true
                                });
                            } else if (isPast) {
                                status = 'missed';
                                icon = 'mdi-alert-circle';
                            }

                            dayItems.push({
                                sortTime: schedDate.getTime(),
                                time: time,
                                medName: medicationName,
                                color: color,
                                status: status,
                                icon: icon,
                                isPrn: false,
                                drugSource: prescription.drug_source || 'pharmacy_dispensed',
                                sourceBadge: sourceBadge,
                                // Detailed data for modal
                                dose: admin ? (admin.dose || schedule.dose) : schedule.dose,
                                route: admin ? (admin.route || schedule.route) : schedule.route,
                                scheduledTime: time,
                                administeredTime: admin ? new Date(admin.administered_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : null,
                                administeredBy: admin ? admin.administered_by_name : null,
                                comment: admin ? admin.comment : null,
                                scheduleId: schedule.id,
                                adminId: admin ? admin.id : null,
                                storeName: admin ? admin.store_name : null,
                                externalDrugName: admin ? admin.external_drug_name : null,
                                externalSourceNote: admin ? admin.external_source_note : null,
                            });
                        });

                        // Process PRN (unscheduled) administrations
                        dayAdministrations.filter(a => !a.schedule_id).forEach(admin => {
                            const adminDate = new Date(admin.administered_at);
                            const adminTime = adminDate.toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            });

                            // For direct entries, use a specific icon
                            const directIcon = prescription.drug_source === 'ward_stock' ? 'mdi-hospital-box'
                                : prescription.drug_source === 'patient_own' ? 'mdi-account-arrow-left'
                                : 'mdi-plus-circle';

                            dayItems.push({
                                sortTime: adminDate.getTime(),
                                time: adminTime,
                                medName: medicationName,
                                color: color,
                                status: 'given',
                                icon: prescription.is_direct_entry ? directIcon : 'mdi-plus-circle',
                                isPrn: !prescription.is_direct_entry,
                                isDirectEntry: prescription.is_direct_entry || false,
                                drugSource: prescription.drug_source || 'pharmacy_dispensed',
                                sourceBadge: sourceBadge,
                                // Detailed data for modal
                                dose: admin.dose,
                                route: admin.route,
                                scheduledTime: null,
                                administeredTime: adminTime,
                                administeredBy: admin.administered_by_name,
                                comment: admin.comment,
                                scheduleId: null,
                                adminId: admin.id,
                                storeName: admin.store_name,
                                externalDrugName: admin.external_drug_name,
                                externalSourceNote: admin.external_source_note,
                            });
                        });
                    });

                    // Sort items by time
                    dayItems.sort((a, b) => a.sortTime - b.sortTime);

                    if (dayItems.length === 0) {
                        html += '<span class="no-schedules">-</span>';
                    } else {
                        dayItems.forEach((item, idx) => {
                            const iconColor = item.status === 'given' ? 'text-success' :
                                              item.status === 'missed' ? 'text-danger' :
                                              item.status === 'discontinued' ? 'text-muted' : 'text-primary';
                            const prnLabel = item.isPrn ? ' (PRN)' : '';

                            // Drug source label for non-pharmacy items
                            let sourceLabel = '';
                            if (item.drugSource === 'ward_stock') {
                                sourceLabel = ' <span style="font-size:9px;background:#0d6efd;color:#fff;padding:1px 3px;border-radius:3px;">WS</span>';
                            } else if (item.drugSource === 'patient_own') {
                                sourceLabel = ' <span style="font-size:9px;background:#ffc107;color:#000;padding:1px 3px;border-radius:3px;">PO</span>';
                            }

                            // Encode item data as JSON for the click handler
                            const itemData = JSON.stringify(item).replace(/"/g, '&quot;');

                            html += `<div class="med-item ${item.status}"
                                style="background-color: ${item.color.bg}; border-left-color: ${item.color.border}; color: ${item.color.text};"
                                onclick="showMedDetails(this)" data-med-details="${itemData}">
                                <i class="mdi ${item.icon} ${iconColor}"></i>
                                <div class="med-details">
                                    <span class="med-name">${item.medName}${prnLabel}${sourceLabel}</span>
                                    <span class="med-time">${item.time}</span>
                                </div>
                            </div>`;
                        });
                    }

                    html += '</div></div>';

                    // Next day
                    currentDate.setDate(currentDate.getDate() + 1);
                }

                html += '</div></div>';

                container.innerHTML = html;
            }

            // Show medication details modal - exposed globally for onclick handlers
            window.showMedDetails = function(element) {
                const data = JSON.parse(element.getAttribute('data-med-details'));

                // Status badge
                let statusBadge = '';
                switch(data.status) {
                    case 'given':
                        statusBadge = '<span class="badge bg-success"><i class="mdi mdi-check-circle me-1"></i>Given</span>';
                        break;
                    case 'scheduled':
                        statusBadge = '<span class="badge bg-primary"><i class="mdi mdi-clock-outline me-1"></i>Scheduled</span>';
                        break;
                    case 'missed':
                        statusBadge = '<span class="badge bg-danger"><i class="mdi mdi-alert-circle me-1"></i>Missed</span>';
                        break;
                    case 'discontinued':
                        statusBadge = '<span class="badge bg-secondary"><i class="mdi mdi-close-circle me-1"></i>Discontinued</span>';
                        break;
                }

                if (data.isPrn) {
                    statusBadge += ' <span class="badge bg-purple"><i class="mdi mdi-plus-circle me-1"></i>PRN</span>';
                }

                // Drug source badge
                if (data.drugSource === 'ward_stock') {
                    statusBadge += ' <span class="badge bg-primary"><i class="mdi mdi-hospital-box me-1"></i>Ward Stock</span>';
                } else if (data.drugSource === 'patient_own') {
                    statusBadge += ' <span class="badge bg-warning text-dark"><i class="mdi mdi-account-arrow-left me-1"></i>Patient\'s Own</span>';
                }

                // Build modal content
                let content = `
                    <div class="row">
                        <div class="col-12 mb-3">
                            <h5 class="mb-2" style="color: ${data.color.text};">
                                <i class="mdi mdi-pill me-2"></i>${data.medName}
                            </h5>
                            ${statusBadge}
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Dose</label>
                            <div class="fw-bold">${data.dose || 'N/A'}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Route</label>
                            <div class="fw-bold">${data.route || 'N/A'}</div>
                        </div>
                    </div>`;

                if (data.scheduledTime) {
                    content += `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Scheduled Time</label>
                            <div class="fw-bold"><i class="mdi mdi-clock-outline me-1 text-primary"></i>${data.scheduledTime}</div>
                        </div>`;

                    if (data.administeredTime) {
                        content += `
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Administered Time</label>
                            <div class="fw-bold"><i class="mdi mdi-check-circle me-1 text-success"></i>${data.administeredTime}</div>
                        </div>`;
                    } else {
                        content += `<div class="col-md-6 mb-3"></div>`;
                    }
                    content += `</div>`;
                } else if (data.administeredTime) {
                    content += `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Administered Time</label>
                            <div class="fw-bold"><i class="mdi mdi-check-circle me-1 text-success"></i>${data.administeredTime}</div>
                        </div>
                        <div class="col-md-6 mb-3"></div>
                    </div>`;
                }

                if (data.administeredBy) {
                    content += `
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="text-muted small">Administered By</label>
                            <div class="fw-bold"><i class="mdi mdi-account me-1"></i>${data.administeredBy}</div>
                        </div>
                    </div>`;
                }

                if (data.comment) {
                    content += `
                    <div class="row">
                        <div class="col-12">
                            <label class="text-muted small">Notes</label>
                            <div class="p-2 bg-light rounded">${data.comment}</div>
                        </div>
                    </div>`;
                }

                // Drug source details for ward stock / patient's own
                if (data.drugSource === 'ward_stock' && data.storeName) {
                    content += `
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="text-muted small">Dispensed From</label>
                            <div class="fw-bold"><i class="mdi mdi-store me-1"></i>${data.storeName}</div>
                        </div>
                    </div>`;
                }

                if (data.drugSource === 'patient_own') {
                    let poDetails = '';
                    if (data.externalDrugName) {
                        poDetails += `<div><strong>Drug Name:</strong> ${data.externalDrugName}</div>`;
                    }
                    if (data.externalSourceNote) {
                        poDetails += `<div><strong>Source Note:</strong> ${data.externalSourceNote}</div>`;
                    }
                    if (poDetails) {
                        content += `
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="text-muted small">Patient's Own Drug Details</label>
                                <div class="p-2 bg-warning bg-opacity-10 rounded border border-warning">
                                    ${poDetails}
                                </div>
                            </div>
                        </div>`;
                    }
                }

                document.getElementById('medDetailsModalBody').innerHTML = content;
                document.getElementById('medDetailsModalLabel').textContent = 'Medication Details';

                // Move modal to body to avoid z-index/overflow issues
                const modalEl = document.getElementById('medDetailsModal');
                if (modalEl.parentElement !== document.body) {
                    document.body.appendChild(modalEl);
                }

                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }

            // Build calendar grid HTML for a single medication (kept for compatibility)
            function buildMedicationCalendarGrid(prescription, schedules, allAdministrations, startDate, endDate) {
                // Filter administrations for this prescription
                const administrations = (allAdministrations || []).filter(a =>
                    a.product_or_service_request_id === prescription.id
                );

                let html = '';

                // Weekday header
                html += '<div class="calendar-weekday-header">';
                ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
                    html += `<div class="weekday-name">${day}</div>`;
                });
                html += '</div>';

                // Calendar grid
                html += '<div class="medication-calendar-grid">';

                // Add empty cells for alignment
                const daysBeforeStart = startDate.getDay();
                for (let i = 0; i < daysBeforeStart; i++) {
                    html += '<div class="calendar-day-cell empty-day"></div>';
                }

                // Today reference
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                // Generate days
                const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                let currentDate = new Date(startDate);

                while (currentDate <= endDate) {
                    const dateStr = currentDate.toISOString().split('T')[0];
                    const dayNum = currentDate.getDate();
                    const dayOfWeek = currentDate.getDay();
                    const isToday = currentDate.toDateString() === today.toDateString();
                    const isPast = currentDate < today;
                    const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

                    // Build cell classes
                    let cellClass = 'calendar-day-cell';
                    if (isToday) cellClass += ' today';
                    else if (isPast) cellClass += ' past-date';
                    if (isWeekend && !isToday) cellClass += ' weekend';

                    // Find schedules for this day
                    const daySchedules = schedules.filter(s => {
                        if (!s.scheduled_time && !s.scheduled_at) return false;
                        const schedTime = s.scheduled_time || s.scheduled_at;
                        const schedDate = new Date(schedTime);
                        return schedDate.toDateString() === currentDate.toDateString();
                    });

                    // Find administrations for this day
                    const dayAdministrations = administrations.filter(a => {
                        if (!a.administered_at) return false;
                        const adminDate = new Date(a.administered_at);
                        return adminDate.toDateString() === currentDate.toDateString();
                    });

                    html += `<div class="${cellClass}">`;
                    html += `<div class="day-header">
                        <span class="day-name">${dayNames[dayOfWeek]}</span>
                        <span class="day-number">${dayNum}</span>
                    </div>`;
                    html += '<div class="schedule-items">';

                    if (daySchedules.length === 0 && dayAdministrations.length === 0) {
                        html += '<span class="no-schedules">-</span>';
                    } else {
                        // Render scheduled doses
                        daySchedules.forEach(schedule => {
                            const schedTime = schedule.scheduled_time || schedule.scheduled_at;
                            const time = new Date(schedTime).toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            });

                            // Check if administered
                            const admin = dayAdministrations.find(a => a.schedule_id === schedule.id);

                            // Check if discontinued
                            const isDiscontinued = prescription.discontinued_at &&
                                new Date(prescription.discontinued_at) < new Date(schedTime);

                            let badgeClass = 'scheduled';
                            let icon = 'mdi-clock-outline';
                            let tooltip = `Scheduled: ${time}<br>Dose: ${schedule.dose || 'N/A'}<br>Route: ${schedule.route || 'N/A'}`;

                            if (isDiscontinued) {
                                badgeClass = 'discontinued';
                                icon = 'mdi-close-circle-outline';
                                tooltip = `Discontinued<br>Time: ${time}`;
                            } else if (admin) {
                                badgeClass = 'given';
                                icon = 'mdi-check-circle';
                                const adminTime = new Date(admin.administered_at).toLocaleTimeString('en-US', {
                                    hour: 'numeric',
                                    minute: '2-digit',
                                    hour12: true
                                });
                                tooltip = `Given at ${adminTime}<br>Dose: ${admin.dose || schedule.dose || 'N/A'}<br>By: ${admin.administered_by_name || 'Unknown'}<br>Store: ${admin.store_name || 'N/A'}`;
                            } else if (isPast) {
                                badgeClass = 'missed';
                                icon = 'mdi-alert-circle';
                                tooltip = `Missed<br>Scheduled: ${time}<br>Dose: ${schedule.dose || 'N/A'}`;
                            }

                            html += `<div class="schedule-badge ${badgeClass}" data-bs-toggle="tooltip" data-bs-html="true" title="${tooltip}">
                                <i class="mdi ${icon}"></i> ${time}
                            </div>`;
                        });

                        // Render PRN (unscheduled) administrations
                        dayAdministrations.filter(a => !a.schedule_id).forEach(admin => {
                            const adminTime = new Date(admin.administered_at).toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            });
                            const tooltip = `PRN Given at ${adminTime}<br>Dose: ${admin.dose || 'N/A'}<br>By: ${admin.administered_by_name || 'Unknown'}<br>Store: ${admin.store_name || 'N/A'}`;

                            html += `<div class="schedule-badge prn" data-bs-toggle="tooltip" data-bs-html="true" title="${tooltip}">
                                <i class="mdi mdi-plus-circle"></i> ${adminTime}
                            </div>`;
                        });
                    }

                    html += '</div></div>';

                    // Next day
                    currentDate.setDate(currentDate.getDate() + 1);
                }

                html += '</div>';

                return html;
            }

            // Function to load intake/output charts
            function loadIntakeOutputCharts(patientId, startDate, endDate) {
                // Show loading indicators
                const fluidContainer = document.getElementById('fluid-io-chart-content');
                const solidContainer = document.getElementById('solid-io-chart-content');

                const loadingHtml = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading data...</p>
                    </div>
                `;

                fluidContainer.innerHTML = loadingHtml;
                solidContainer.innerHTML = loadingHtml;

                // Build URL with query parameters
                const url = new URL(wbUrl('/patients/' + patientId + '/nurse-chart/intake-output'));
                if (startDate) url.searchParams.append('start_date', formatDateForApi(startDate));
                if (endDate) url.searchParams.append('end_date', formatDateForApi(endDate));

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        // Process fluid periods
                        renderIntakeOutputChart(data.fluidPeriods, 'fluid-io-chart-content', 'Fluid');

                        // Process solid periods
                        renderIntakeOutputChart(data.solidPeriods, 'solid-io-chart-content', 'Solid');

                        // Count total I/O records
                        let totalRecords = 0;
                        let totalPeriods = 0;

                        if (data.fluidPeriods) {
                            totalPeriods += data.fluidPeriods.length;
                            data.fluidPeriods.forEach(period => {
                                if (period.records) totalRecords += period.records.length;
                            });
                        }

                        if (data.solidPeriods) {
                            totalPeriods += data.solidPeriods.length;
                            data.solidPeriods.forEach(period => {
                                if (period.records) totalRecords += period.records.length;
                            });
                        }

                        // Add to existing summary stats
                        const statsContainer = document.getElementById('data-summary-stats');
                        statsContainer.innerHTML += `
                            <span class="badge bg-warning rounded-pill fs-6">
                                <i class="mdi mdi-water me-1"></i> ${totalRecords} I/O records
                            </span>
                        `;
                    })
                    .catch(error => {
                        console.error('Error loading intake/output charts:', error);
                        document.getElementById('fluid-io-chart-content').innerHTML =
                            '<div class="alert alert-danger">Failed to load fluid intake/output data. Please try again later.</div>';
                        document.getElementById('solid-io-chart-content').innerHTML =
                            '<div class="alert alert-danger">Failed to load solid intake/output data. Please try again later.</div>';
                    });
            }

            // Helper function to render intake/output chart
            function renderIntakeOutputChart(periods, containerId, type) {
                const container = document.getElementById(containerId);

                // Sort periods by started_at, newest first
                const sortedPeriods = [...periods].sort((a, b) => {
                    return new Date(b.started_at) - new Date(a.started_at);
                });

                if (sortedPeriods.length === 0) {
                    container.innerHTML = `<div class="alert alert-info">No ${type.toLowerCase()} intake/output records found</div>`;
                    return;
                }

                let html = '';

                // Add legend
                html += `<div class="card-modern mb-2">
                    <div class="card-body p-2">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <small class="text-muted me-2">Legend:</small>
                            <span class="badge bg-primary rounded-pill d-flex align-items-center">
                                <i class="mdi mdi-arrow-down-bold me-1"></i> Intake
                            </span>
                            <span class="badge bg-warning rounded-pill d-flex align-items-center">
                                <i class="mdi mdi-arrow-up-bold me-1"></i> Output
                            </span>
                            <span class="badge bg-success rounded-pill d-flex align-items-center">
                                <i class="mdi mdi-clock-start me-1"></i> Active
                            </span>
                            <span class="badge bg-secondary rounded-pill d-flex align-items-center">
                                <i class="mdi mdi-clock-end me-1"></i> Ended
                            </span>
                        </div>
                    </div>
                </div>`;

                // Process each period
                sortedPeriods.forEach((period, index) => {
                    const isActive = !period.ended_at;
                    const startTime = new Date(period.started_at).toLocaleString();
                    const endTime = period.ended_at ? new Date(period.ended_at).toLocaleString() : 'Ongoing';

                    // Calculate totals
                    let intakeTotal = 0;
                    let outputTotal = 0;

                    // Sort records by created_at, newest first
                    const sortedRecords = [...period.records].sort((a, b) => {
                        return new Date(b.created_at) - new Date(a.created_at);
                    });

                    sortedRecords.forEach(record => {
                        if (record.type === 'intake') {
                            intakeTotal += parseFloat(record.amount);
                        } else {
                            outputTotal += parseFloat(record.amount);
                        }
                    });

                    const balance = intakeTotal - outputTotal;
                    const balanceClass = balance> 0 ? 'text-success' : (balance < 0 ? 'text-danger' : 'text-muted');

                    // Period card
                    html += `<div class="card-modern shadow-sm mb-3 period-card">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">
                                    Period ${isActive ?
                                        '<span class="badge bg-success ms-1">Active</span>' :
                                        '<span class="badge bg-secondary ms-1">Ended</span>'
                                    }
                                </h6>
                                <small class="text-muted">Started: ${startTime}</small>
                                ${period.ended_at ? `<br><small class="text-muted">Ended: ${endTime}</small>` : ''}
                            </div>
                            <div class="text-end">
                                <span class="fw-bold">Nurse: ${period.nurse_name}</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Time</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Amount</th>
                                            <th>Recorded By</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;

                    if (sortedRecords.length> 0) {
                        sortedRecords.forEach(record => {
                            const recordTime = new Date(record.created_at).toLocaleString();
                            const recordType = record.type === 'intake' ? 'Intake' : 'Output';
                            const typeClass = record.type === 'intake' ? 'bg-primary' : 'bg-warning';
                            const typeIcon = record.type === 'intake' ? 'mdi-arrow-down-bold' : 'mdi-arrow-up-bold';

                            html += `<tr>
                                <td>${recordTime}</td>
                                <td><span class="badge ${typeClass} rounded-pill"><i class="mdi ${typeIcon} me-1"></i> ${recordType}</span></td>
                                <td>${record.description || 'N/A'}</td>
                                <td>${record.amount} ${record.unit || ''}</td>
                                <td>${record.nurse_name}</td>
                            </tr>`;
                        });
                    } else {
                        html += `<tr><td colspan="5" class="text-center">No records found for this period</td></tr>`;
                    }

                    // Add balance row
                    html += `</tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-light">
                                <div class="row">
                                    <div class="col-md-4">
                                        <span class="text-primary fw-bold">Total Intake: ${intakeTotal} ${type === 'Fluid' ? 'ml' : 'g'}</span>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-warning fw-bold">Total Output: ${outputTotal} ${type === 'Fluid' ? 'ml' : 'g'}</span>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <span class="${balanceClass} fw-bold">Balance: ${balance} ${type === 'Fluid' ? 'ml' : 'g'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;

                    // Add a separator between periods
                    if (index < sortedPeriods.length - 1) {
                        html += '<hr class="my-3 opacity-50">';
                    }
                });

                container.innerHTML = html;
            }
        });
        // AJAX Functions for Incremental Saving
        const encounterId = window.WORKBENCH_CONFIG?.encounterId || '';
        const patientId = window.WORKBENCH_CONFIG?.patientId || '';
        const queueId = window.WORKBENCH_CONFIG?.queueId || '';
        const currentDoctorStaffId = window.WORKBENCH_CONFIG?.staffId || '';

        // Initialize consultation timer
        $(function() {
            if (typeof ConsultationTimer !== 'undefined') {
                ConsultationTimer.init(queueId);
            }
        });

        // Patient weight from last vital that recorded a weight (for dose calculators)
        window.patientWeight = window.WORKBENCH_CONFIG?.patientWeight || null;

        // Initialize dose mode toggle — structured by default (Plan §2.2)
        // Uses the shared ClinicalOrdersKit from clinical-orders-shared.js
        var doctorDoseState = ClinicalOrdersKit.initDoseModeToggle({
            prefix: '',
            cssPrefix: '',
            tableSelector: '#selected-products',
            idInputName: 'consult_presc_id[]',
            doseInputName: 'consult_presc_dose[]',
            onchange: 'ClinicalOrdersKit.updateDoseValue(this, \"\")',
            onToggle: function(isStructured) { doseStructuredMode = isStructured; }
        });
        // Sync legacy variable with new default (structured = true)
        doseStructuredMode = doctorDoseState.isStructured;

        // Phase 2b (Plan §4.3): Register debounced dose auto-save for medications
        // When any structured dose field changes → updateDoseValue fires → triggers this handler
        ClinicalOrdersKit.onDoseUpdate('', function(recordId, doseValue, flashEl) {
            ClinicalOrdersKit.debouncedUpdate({
                url: wbUrl('/encounters/' + encounterId + '/prescriptions/' + recordId + '/dose'),
                payload: { dose: doseValue },
                csrfToken: $('meta[name="csrf-token"]').attr('content'),
                flashTarget: flashEl,
                onSuccess: function() { $('#presc_history_list').DataTable().ajax.reload(null, false); }
            });
        });

        // B1 fix (Plan §4.4): Scan pre-existing rows to populate duplicate tracking
        ClinicalOrdersKit.scanExistingRows('#selected-services', 'labs');
        ClinicalOrdersKit.scanExistingRows('#selected-imaging-services', 'imaging');
        ClinicalOrdersKit.scanExistingRows('#selected-products', 'meds');
        ClinicalOrdersKit.scanExistingRows('#selected-procedures-table tbody', 'procedures');

        // Phase 4d (Plan §6.4): Initialize treatment plans module
        ClinicalOrdersKit.initTreatmentPlans({
            applyUrl: '/encounters/' + encounterId + '/apply-treatment-plan',
            csrfToken: $('meta[name="csrf-token"]').attr('content'),
            extraPayload: {},
            onApplySuccess: function(response) {
                // Reload all history tables after applying a plan (A2 fix: include procedures)
                if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
                    $('#investigation_history_list').DataTable().ajax.reload(null, false);
                }
                if ($.fn.DataTable.isDataTable('#imaging_history_list')) {
                    $('#imaging_history_list').DataTable().ajax.reload(null, false);
                }
                if ($.fn.DataTable.isDataTable('#presc_history_list')) {
                    $('#presc_history_list').DataTable().ajax.reload(null, false);
                }
                if ($.fn.DataTable.isDataTable('#procedure_history_list')) {
                    $('#procedure_history_list').DataTable().ajax.reload(null, false);
                }
            },
            currentItemsGatherer: function() {
                // Gather all auto-saved items from selection tables (all 4 types)
                var items = [];
                $('#selected-services tr[data-record-id]').each(function() {
                    items.push({
                        item_type: 'lab',
                        reference_id: parseInt($(this).data('service-id')),
                        display_name: $(this).find('td:first').text().trim(),
                        note: $(this).find('input[name="consult_invest_note[]"]').val() || ''
                    });
                });
                $('#selected-imaging-services tr[data-record-id]').each(function() {
                    items.push({
                        item_type: 'imaging',
                        reference_id: parseInt($(this).data('service-id')),
                        display_name: $(this).find('td:first').text().trim(),
                        note: $(this).find('input[name="consult_imaging_note[]"]').val() || ''
                    });
                });
                $('#selected-products tr[data-record-id]').each(function() {
                    items.push({
                        item_type: 'medication',
                        reference_id: parseInt($(this).data('service-id')),
                        display_name: $(this).find('td:first').text().trim(),
                        dose: $(this).find('input[name="consult_presc_dose[]"]').val() || ''
                    });
                });
                $('#selected-procedures tr[data-record-id]').each(function() {
                    items.push({
                        item_type: 'procedure',
                        reference_id: parseInt($(this).data('service-id')),
                        display_name: $(this).find('td:first').text().trim(),
                        note: ''
                    });
                });
                return items;
            }
        });

        // Phase 3c (Plan §5.3): Initialize re-prescribe from encounter dropdown
        ClinicalOrdersKit.initRePrescribeFromEncounter({
            recentUrl: '/encounters/' + encounterId + '/recent-encounters',
            encounterItemsUrl: '/encounters/' + encounterId + '/encounter-items/{id}',
            rePrescribeUrl: '/encounters/' + encounterId + '/re-prescribe',
            csrfToken: $('meta[name="csrf-token"]').attr('content'),
            dropdownSelector: '#rp-encounter-dropdown',
            onRePrescribed: function() {
                // Reload all history tables (B3 fix: include procedures)
                if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
                    $('#investigation_history_list').DataTable().ajax.reload(null, false);
                }
                if ($.fn.DataTable.isDataTable('#imaging_history_list')) {
                    $('#imaging_history_list').DataTable().ajax.reload(null, false);
                }
                if ($.fn.DataTable.isDataTable('#presc_history_list')) {
                    $('#presc_history_list').DataTable().ajax.reload(null, false);
                }
                if ($.fn.DataTable.isDataTable('#procedure_history_list')) {
                    $('#procedure_history_list').DataTable().ajax.reload(null, false);
                }
            }
        });

        // Initialize Non-Pharmacological Care Orders
        window.NonPharmManager.init({
            patientId: patientId,
            encounterId: encounterId,
            containerId: '#non-pharm-encounter-container',
            isNurseView: false
        });

        // Helper function to show messages
        function showMessage(elementId, message, type = 'success') {
            const element = document.getElementById(elementId);
            const typeMap = { success: 'alert-success', error: 'alert-danger', warning: 'alert-warning', info: 'alert-info' };
            const alertClass = typeMap[type] || 'alert-info';
            element.innerHTML = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
            // Scroll the message into view
            element.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setTimeout(() => { element.innerHTML = ''; }, 5000);
        }

        // Helper function to disable/enable button
        function setButtonLoading(buttonId, loading) {
            const btn = document.getElementById(buttonId);
            if (!btn) return;
            
            if (loading) {
                btn.disabled = true;
                btn.dataset.originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
            } else {
                btn.disabled = false;
                if (btn.dataset.originalHtml) {
                    btn.innerHTML = btn.dataset.originalHtml;
                } else {
                    btn.innerHTML = '<i class="fa fa-save"></i> Save';
                }
            }
        }

        // Save Diagnosis
        function saveDiagnosis(showModal = true) {
            setButtonLoading('save_diagnosis_btn', true);

            // Get diagnosis from CKEditor if available, otherwise from textarea
            let diagnosisText = '';
            if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances['doctor_diagnosis_text']) {
                diagnosisText = CKEDITOR.instances['doctor_diagnosis_text'].getData();
            } else {
                diagnosisText = $('#doctor_diagnosis_text').val();
            }

            const formData = new FormData();
            formData.append('doctor_diagnosis', diagnosisText);

            if (true) {
            // Check if diagnosis is applicable
            const diagnosisApplicable = $('#diagnosisApplicable').is(':checked');
            formData.append('diagnosis_applicable', diagnosisApplicable ? '1' : '0');

            if (diagnosisApplicable) {
                // Get selected reasons from the new AJAX search component
                const reasonsData = $('#reasons_for_encounter_data').val();
                let parsedReasons = [];

                try {
                    parsedReasons = JSON.parse(reasonsData);
                } catch (e) {
                    console.error('Error parsing reasons data:', e);
                }

                if (!parsedReasons || parsedReasons.length === 0) {
                    // Inline highlight and error message
                    $('#reasons_for_encounter_search').addClass('is-invalid');
                    if ($('#reasons_inline_error').length === 0) {
                        $('#reasons_for_encounter_search').parent().after('<div id="reasons_inline_error" class="invalid-feedback d-block fw-bold mb-2"><i class="fa fa-exclamation-triangle"></i> Please select at least one applicable diagnosis, or toggle off "Diagnosis Applicable".</div>');
                    }
                    
                    // Scroll to the field
                    $('html, body, .content-wrapper, .encounter-sidebar-wrapper').animate({
                        scrollTop: $('#reasons_for_encounter_search').offset().top - 120
                    }, 500);
                    
                    // Rich Modal alert
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Missing Diagnosis',
                            html: 'You have indicated that a diagnosis is applicable.<br><br><b>Please select at least one diagnosis reason to proceed</b>, or toggle off the switch if no diagnosis is applicable yet.',
                            confirmButtonColor: '#d33',
                            confirmButtonText: '<i class="fa fa-check"></i> Understood'
                        });
                    }

                    showMessage('diagnosis_save_message', 'Please select at least one diagnosis reason or toggle off "Diagnosis Applicable"', 'error');
                    setButtonLoading('save_diagnosis_btn', false);
                    return;
                } else {
                    $('#reasons_for_encounter_search').removeClass('is-invalid');
                    $('#reasons_inline_error').remove();
                }

                // Send reasons as values (code-name format) for backward compat
                parsedReasons.forEach(reason => {
                    formData.append('reasons_for_encounter[]', reason.value);
                });

                // Send per-diagnosis comments as JSON
                const perDiagnosisComments = parsedReasons.map(r => ({
                    code: r.code || '',
                    name: r.name || '',
                    value: r.value || '',
                    comment_1: r.comment_1 || 'NA',
                    comment_2: r.comment_2 || 'NA'
                }));
                formData.append('per_diagnosis_comments', JSON.stringify(perDiagnosisComments));

                // Legacy global comments (first diagnosis values or NA)
                formData.append('reasons_for_encounter_comment_1', parsedReasons[0]?.comment_1 || 'NA');
                formData.append('reasons_for_encounter_comment_2', parsedReasons[0]?.comment_2 || 'NA');
            }
            }

            $.ajax({
                url: wbUrl(`/encounters/${encounterId}/save-diagnosis`),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    showMessage('diagnosis_save_message', response.message, 'success');
                    updateSummary();

                    // Reload encounter history DataTable if it exists
                    if ($.fn.DataTable.isDataTable('#encounter_history_list')) {
                        $('#encounter_history_list').DataTable().ajax.reload(null, false);
                    }

                    // Show conclusion modal after successful save if requested
                    if (showModal) {
                        setTimeout(() => {
                            $('#concludeEncounterModal').modal('show');
                        }, 500);
                    }
                },
                error: function(xhr) {
                    let message = 'Error saving diagnosis';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.errors) {
                            message = Object.values(xhr.responseJSON.errors).flat().join(', ');
                        } else if (xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                    }
                    showMessage('diagnosis_save_message', message, 'error');
                },
                complete: function() {
                    setButtonLoading('save_diagnosis_btn', false);
                }
            });
        }

        function saveDiagnosisAndNext() {
            saveDiagnosis(false);
            setTimeout(() => $('#laboratory_services_tab').click(), 800);
        }

        // Save Labs
        function saveLabs() {
            const services = [];
            const notes = [];
            var autoSavedCount = 0;

            $('#selected-services tr').each(function() {
                // Skip auto-saved rows (already persisted via addItem)
                if ($(this).data('record-id')) {
                    autoSavedCount++;
                    return; // continue
                }
                const serviceId = $(this).find('input[name="consult_invest_id[]"]').val();
                const note = $(this).find('input[name="consult_invest_note[]"]').val();
                if (serviceId) {
                    services.push(serviceId);
                    notes.push(note || '');
                }
            });

            // If all items were auto-saved and nothing new to batch-save
            if (services.length === 0 && autoSavedCount> 0) {
                showMessage('labs_save_message', autoSavedCount + ' lab(s) already saved', 'success');
                updateSummary();
                $('#selected-services').empty();
                ClinicalOrdersKit.addedIds.labs.clear(); // A3 fix: only clear labs, not all types
                if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
                    $('#investigation_history_list').DataTable().ajax.reload();
                }
                try { new bootstrap.Tab(document.getElementById('lab-history-tab')).show(); } catch(e) { $('#lab-history-tab').tab('show'); }
                return;
            }

            if (services.length === 0) {
                showMessage('labs_save_message', 'No lab services selected', 'error');
                return;
            }

            setButtonLoading('save_labs_btn', true);

            $.ajax({
                url: wbUrl(`/encounters/${encounterId}/save-labs`),
                method: 'POST',
                data: {
                    consult_invest_id: services,
                    consult_invest_note: notes,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    showMessage('labs_save_message', response.message, 'success');
                    updateSummary();
                    // Clear selected list
                    $('#selected-services').empty();
                    // Reload history DataTable
                    if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
                        $('#investigation_history_list').DataTable().ajax.reload();
                    }
                    // Switch to history tab
                    try { new bootstrap.Tab(document.getElementById('lab-history-tab')).show(); } catch(e) { $('#lab-history-tab').tab('show'); }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Error saving lab requests';
                    showMessage('labs_save_message', message, 'error');
                },
                complete: function() {
                    setButtonLoading('save_labs_btn', false);
                }
            });
        }

        function saveLabsAndNext() {
            saveLabs();
            setTimeout(() => $('#imaging_services_tab').click(), 800);
        }

        // Save Imaging
        function saveImaging() {
            const services = [];
            const notes = [];
            var autoSavedCount = 0;

            $('#selected-imaging-services tr').each(function() {
                if ($(this).data('record-id')) {
                    autoSavedCount++;
                    return;
                }
                const serviceId = $(this).find('input[name="consult_imaging_id[]"]').val();
                const note = $(this).find('input[name="consult_imaging_note[]"]').val();
                if (serviceId) {
                    services.push(serviceId);
                    notes.push(note || '');
                }
            });

            if (services.length === 0 && autoSavedCount> 0) {
                showMessage('imaging_save_message', autoSavedCount + ' imaging request(s) already saved', 'success');
                updateSummary();
                $('#selected-imaging-services').empty();
                ClinicalOrdersKit.addedIds.imaging.clear(); // A3 fix: only clear imaging
                if ($.fn.DataTable.isDataTable('#imaging_history_list')) {
                    $('#imaging_history_list').DataTable().ajax.reload();
                }
                try { new bootstrap.Tab(document.getElementById('imaging-history-tab')).show(); } catch(e) { $('#imaging-history-tab').tab('show'); }
                return;
            }

            if (services.length === 0) {
                showMessage('imaging_save_message', 'No imaging services selected', 'error');
                return;
            }

            setButtonLoading('save_imaging_btn', true);

            $.ajax({
                url: wbUrl(`/encounters/${encounterId}/save-imaging`),
                method: 'POST',
                data: {
                    consult_imaging_id: services,
                    consult_imaging_note: notes,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    showMessage('imaging_save_message', response.message, 'success');
                    updateSummary();
                    // Clear selected list
                    $('#selected-imaging-services').empty();
                    // Reload history DataTable
                    if ($.fn.DataTable.isDataTable('#imaging_history_list')) {
                        $('#imaging_history_list').DataTable().ajax.reload();
                    }
                    // Switch to history tab
                    try { new bootstrap.Tab(document.getElementById('imaging-history-tab')).show(); } catch(e) { $('#imaging-history-tab').tab('show'); }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Error saving imaging requests';
                    showMessage('imaging_save_message', message, 'error');
                },
                complete: function() {
                    setButtonLoading('save_imaging_btn', false);
                }
            });
        }

        function saveImagingAndNext() {
            saveImaging();
            setTimeout(() => $('#medications_tab').click(), 800);
        }

        // Save Prescriptions (Phase 2b: skip auto-saved rows — Plan §4.3)
        function savePrescriptions() {
            const products = [];
            const doses = [];
            let hasEmptyDose = false;
            let autoSavedCount = 0;

            $('#selected-products tr').each(function() {
                // Skip rows already auto-saved (Phase 2b)
                if ($(this).data('record-id')) {
                    autoSavedCount++;
                    return; // continue
                }
                const productId = $(this).find('input[name="consult_presc_id[]"]').val();
                // Try structured hidden input first, fallback to text input
                let dose = $(this).find('.structured-dose-value').val();
                if (dose === undefined || dose === null) {
                    dose = $(this).find('input[name="consult_presc_dose[]"]').val();
                }
                if (productId) {
                    products.push(productId);
                    doses.push(dose || '');
                    if (!dose || dose.trim() === '') {
                        hasEmptyDose = true;
                    }
                }
            });

            // If ALL rows are auto-saved, show success and clear
            if (products.length === 0 && autoSavedCount> 0) {
                showMessage('prescriptions_save_message', autoSavedCount + ' prescription(s) already auto-saved', 'success');
                $('#selected-products').empty();
                ClinicalOrdersKit.addedIds.meds.clear(); // A3 fix: only clear meds
                if ($.fn.DataTable.isDataTable('#presc_history_list')) {
                    $('#presc_history_list').DataTable().ajax.reload();
                }
                try { new bootstrap.Tab(document.getElementById('presc-history-tab')).show(); } catch(e) { $('#presc-history-tab').tab('show'); }
                return;
            }

            if (products.length === 0) {
                showMessage('prescriptions_save_message', 'No prescriptions selected', 'error');
                return;
            }

            if (hasEmptyDose) {
                if (!confirm('Some prescriptions have empty dosage fields. Do you want to continue?')) {
                    return;
                }
            }

            setButtonLoading('save_prescriptions_btn', true);

            $.ajax({
                url: wbUrl(`/encounters/${encounterId}/save-prescriptions`),
                method: 'POST',
                data: {
                    consult_presc_id: products,
                    consult_presc_dose: doses,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    const saved = autoSavedCount> 0 ? ` (${autoSavedCount} auto-saved earlier)` : '';
                    const msgType = response.empty_doses && response.empty_doses.length> 0 ? 'warning' : 'success';
                    showMessage('prescriptions_save_message', response.message + saved, msgType);
                    updateSummary();
                    // Clear selected list
                    $('#selected-products').empty();
                    ClinicalOrdersKit.addedIds.meds.clear(); // A3 fix: only clear meds
                    // Reload history DataTable
                    if ($.fn.DataTable.isDataTable('#presc_history_list')) {
                        $('#presc_history_list').DataTable().ajax.reload();
                    }
                    // Switch to history tab
                    try { new bootstrap.Tab(document.getElementById('presc-history-tab')).show(); } catch(e) { $('#presc-history-tab').tab('show'); }
                },
                error: function(xhr) {
                    let message = 'Error saving prescriptions';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.errors) {
                            message = Object.values(xhr.responseJSON.errors).flat().join(', ');
                        } else if (xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                    }
                    showMessage('prescriptions_save_message', message, 'error');
                },
                complete: function() {
                    setButtonLoading('save_prescriptions_btn', false);
                }
            });
        }

        function savePrescriptionsAndNext() {
            savePrescriptions();
            setTimeout(() => $('#admissions_tab').click(), 800);
        }

        // Finalize Encounter
        function finalizeEncounter() {
            if (!confirm('Are you sure you want to complete this encounter?')) {
                return;
            }

            const btn = document.getElementById('finalize_encounter_btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Completing...';

            $.ajax({
                url: wbUrl(`/encounters/${encounterId}/finalize`),
                method: 'POST',
                data: {
                    end_consultation: $('#end_consultation').is(':checked') ? 1 : 0,
                    consult_admit: $('#consult_admit').is(':checked') ? 1 : 0,
                    admit_note: $('#admit_note').val(),
                    queue_id: queueId,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    showMessage('finalize_message', response.message, 'success');
                    setTimeout(() => {
                        window.location.href = response.redirect;
                    }, 1500);
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Error completing encounter';
                    showMessage('finalize_message', message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-check-circle"></i> Complete Encounter';
                }
            });
        }

        // Update Summary
        function updateSummary() {
            // Fetch real encounter data from database
            $.ajax({
                url: wbUrl(`/encounters/${encounterId}/summary`),
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        const data = response.data;

                        // Update diagnosis summary
                        if (data.diagnosis.saved) {
                            const notesPreview = data.diagnosis.notes ?
                                (data.diagnosis.notes.substring(0, 100) + (data.diagnosis.notes.length> 100 ? '...' : '')) :
                                'Saved';
                            $('#summary_diagnosis').html(`
                                <span class="text-success"><i class="fa fa-check-circle"></i> <strong>Saved</strong></span>
                                <br><small>${notesPreview}</small>
                            `);
                        } else {
                            $('#summary_diagnosis').html(`<span class="text-muted">Not saved yet</span>`);
                        }

                        // Update labs summary
                        if (data.labs.length> 0) {
                            let labsHtml = `<span class="badge bg-success mb-2">${data.labs.length} service(s)</span><br>`;
                            labsHtml += '<ul class="small mb-0 ps-3">';
                            data.labs.forEach(lab => {
                                labsHtml += `<li>${lab.name} ${lab.code ? '[' + lab.code + ']' : ''}</li>`;
                            });
                            labsHtml += '</ul>';
                            $('#summary_labs').html(labsHtml);
                        } else {
                            $('#summary_labs').html(`<span class="text-muted">None selected</span>`);
                        }

                        // Update imaging summary
                        if (data.imaging.length> 0) {
                            let imagingHtml = `<span class="badge bg-success mb-2">${data.imaging.length} service(s)</span><br>`;
                            imagingHtml += '<ul class="small mb-0 ps-3">';
                            data.imaging.forEach(img => {
                                imagingHtml += `<li>${img.name} ${img.code ? '[' + img.code + ']' : ''}</li>`;
                            });
                            imagingHtml += '</ul>';
                            $('#summary_imaging').html(imagingHtml);
                        } else {
                            $('#summary_imaging').html(`<span class="text-muted">None selected</span>`);
                        }

                        // Update prescriptions summary
                        if (data.prescriptions.length> 0) {
                            let prescHtml = `<span class="badge bg-success mb-2">${data.prescriptions.length} medication(s)</span><br>`;
                            prescHtml += '<ul class="small mb-0 ps-3">';
                            data.prescriptions.forEach(presc => {
                                prescHtml += `<li>${presc.name}${presc.dose ? ' - ' + presc.dose : ''}</li>`;
                            });
                            prescHtml += '</ul>';
                            $('#summary_prescriptions').html(prescHtml);
                        } else {
                            $('#summary_prescriptions').html(`<span class="text-muted">None selected</span>`);
                        }

                        // Update procedures summary
                        if (data.procedures && data.procedures.length > 0) {
                            let procHtml = `<span class="badge bg-success mb-2">${data.procedures.length} procedure(s)</span><br>`;
                            procHtml += '<ul class="small mb-0 ps-3">';
                            data.procedures.forEach(proc => {
                                procHtml += `<li>${proc.name} ${proc.code ? '[' + proc.code + ']' : ''}</li>`;
                            });
                            procHtml += '</ul>';
                            $('#summary_procedures').html(procHtml);
                        } else {
                            $('#summary_procedures').html(`<span class="text-muted">None selected</span>`);
                        }

                        // Update referrals summary
                        if (data.referrals && data.referrals.length > 0) {
                            let refHtml = `<span class="badge bg-success mb-2">${data.referrals.length} referral(s)</span><br>`;
                            refHtml += '<ul class="small mb-0 ps-3">';
                            data.referrals.forEach(ref => {
                                refHtml += `<li>${ref.target}</li>`;
                            });
                            refHtml += '</ul>';
                            $('#summary_referrals').html(refHtml);
                        } else {
                            $('#summary_referrals').html(`<span class="text-muted">None selected</span>`);
                        }

                        // Update care plans summary
                        if (data.care_plans && data.care_plans.length > 0) {
                            let cpHtml = `<span class="badge bg-success mb-2">${data.care_plans.length} care plan(s)</span><br>`;
                            cpHtml += '<ul class="small mb-0 ps-3">';
                            data.care_plans.forEach(cp => {
                                cpHtml += `<li>${cp.category} (${cp.frequency})</li>`;
                            });
                            cpHtml += '</ul>';
                            $('#summary_care_plans').html(cpHtml);
                        } else {
                            $('#summary_care_plans').html(`<span class="text-muted">None selected</span>`);
                        }
                    }
                },
                error: function(xhr) {
                    console.error('Error loading encounter summary:', xhr);
                }
            });
        }

        // Toggle admit note section
        function toggleAdmitNote() {
            const admitChecked = $('#consult_admit').is(':checked');
            $('#admit_note_section').toggle(admitChecked);
        }

        // Initialize summary on page load
        $(document).ready(function() {
            // Don't load on page load, only when modal is opened
        });
        $(function() {
            $('#admission-request-list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": wbRoute('patient-admission-requests-list', '/patient-admission-requests-list'),
                    "type": "GET"
                },
                "columns": [{
                    data: "info",
                    name: "info",
                    orderable: false
                }],
                "paging": true
            });
        });

        // Toggle internal/external referral fields
        $(document).on('change', '#referral-type-select', function() {
            if ($(this).val() === 'external') {
                $('#referral-internal-fields').hide();
                $('#referral-external-fields').show();
            } else {
                $('#referral-internal-fields').show();
                $('#referral-external-fields').hide();
            }
        });

        // Show referral form (new mode)
        $(document).on('click', '#toggle-referral-form-btn', function() {
            resetReferralForm();
            $('#referral-form-card').toggleClass('d-none');
        });

        // Cancel referral form
        $(document).on('click', '#cancel-referral-form-btn', function() {
            resetReferralForm();
            $('#referral-form-card').addClass('d-none');
        });

        // Reset form to "create" mode
        function resetReferralForm() {
            $('#create-referral-form')[0].reset();
            $('#referral-edit-id').val('');
            $('#referral-submit-btn').html('<i class="mdi mdi-send"></i> Submit Referral');
            $('#referral-internal-fields').show();
            $('#referral-external-fields').hide();
            $('#referral-type-select').val('internal');
        }

        // Load referrals when main referrals tab is shown
        var _patientRefLoaded = false;
        var _incomingRefLoaded = false;
        $('button[data-bs-target="#referrals"]').on('shown.bs.tab', function() {
            loadEncounterReferrals();
        });
        // Lazy-load patient referrals subtab
        $('#refSubTab-patient').on('shown.bs.tab', function() {
            if (!_patientRefLoaded) {
                loadPatientReferrals();
                _patientRefLoaded = true;
            }
        });
        // Lazy-load incoming referrals subtab
        $('#refSubTab-incoming').on('shown.bs.tab', function() {
            if (!_incomingRefLoaded) {
                loadIncomingReferrals();
                _incomingRefLoaded = true;
            }
        });

        function loadEncounterReferrals() {
            $.ajax({
                url: wbRoute('encounters.referrals.list', '/encounters/referrals/list').replace('__EID__', encounterId),
                type: 'GET',
                success: function(data) {
                    $('#referrals-loading').hide();
                    var html = '';
                    if (data.referrals && data.referrals.length> 0) {
                        $('.referral-count-badge').text(data.referrals.length).show();
                        $('#ref-encounter-count').text(data.referrals.length);
                        data.referrals.forEach(function(ref) {
                            var urgencyBadge = {
                                'emergency': 'bg-danger',
                                'urgent': 'bg-warning text-dark',
                                'routine': 'bg-secondary'
                            }[ref.urgency] || 'bg-secondary';

                            var statusBadge = {
                                'pending': 'bg-warning text-dark',
                                'accepted': 'bg-info',
                                'scheduled': 'bg-primary',
                                'booked': 'bg-primary',
                                'completed': 'bg-success',
                                'cancelled': 'bg-danger',
                                'declined': 'bg-dark',
                                'referred_out': 'bg-purple'
                            }[ref.status] || 'bg-secondary';

                            var urgencyIcon = { 'emergency': 'mdi-alert-circle', 'urgent': 'mdi-alert', 'routine': 'mdi-check-circle' }[ref.urgency] || 'mdi-check-circle';
                            var canEdit = ref.can_edit;

                            html += '<div class="card-modern border-start border-4 mb-2" style="border-color: ' + (ref.urgency === 'emergency' ? '#dc3545' : ref.urgency === 'urgent' ? '#ffc107' : '#6c757d') + ' !important;">';
                            html += '<div class="card-body py-2 px-3">';

                            // Header row: badges + actions
                            html += '<div class="d-flex justify-content-between align-items-start">';
                            html += '<div>';
                            html += '<span class="badge ' + urgencyBadge + ' me-1"><i class="mdi ' + urgencyIcon + ' me-1"></i>' + ref.urgency + '</span>';
                            html += '<span class="badge ' + statusBadge + ' me-1">' + ref.status + '</span>';
                            html += '<span class="badge ' + (ref.type === 'internal' ? 'bg-info' : 'bg-secondary') + '">' + (ref.type === 'internal' ? '<i class="mdi mdi-hospital-building me-1"></i>Internal' : '<i class="mdi mdi-hospital-marker me-1"></i>External') + '</span>';
                            if (ref.is_mine) html += '<span class="badge bg-primary ms-1"><i class="mdi mdi-account me-1"></i>Mine</span>';
                            html += '</div>';
                            html += '<div class="d-flex align-items-center gap-1">';
                            html += '<small class="text-muted me-2">' + (ref.created_at || '') + '</small>';
                            if (canEdit) {
                                html += '<button class="btn btn-sm btn-outline-primary py-0 px-1 btn-edit-referral" data-referral=\'' + JSON.stringify(ref) + '\' title="Edit"><i class="mdi mdi-pencil" style="font-size:.8rem;"></i></button>';
                                html += '<button class="btn btn-sm btn-outline-danger py-0 px-1 btn-delete-referral" data-id="' + ref.id + '" title="Delete"><i class="mdi mdi-delete" style="font-size:.8rem;"></i></button>';
                            }
                            html += '</div>';
                            html += '</div>';

                            // Details
                            html += '<div class="mt-1">';
                            if (ref.target_clinic) {
                                html += '<small><i class="mdi mdi-hospital-building text-info me-1"></i><strong>Clinic:</strong> ' + ref.target_clinic + '</small><br>';
                            }
                            if (ref.target_doctor) {
                                html += '<small><i class="mdi mdi-doctor text-success me-1"></i><strong>Doctor:</strong> ' + ref.target_doctor + '</small><br>';
                            }
                            if (ref.external_facility) {
                                html += '<small><i class="mdi mdi-hospital-marker text-danger me-1"></i><strong>Facility:</strong> ' + ref.external_facility + '</small>';
                                if (ref.external_doctor) html += ' &mdash; <small>' + ref.external_doctor + '</small>';
                                html += '<br>';
                            }
                            if (ref.provisional_diagnosis) {
                                html += '<small><i class="mdi mdi-clipboard-pulse text-primary me-1"></i><strong>Diagnosis:</strong> ' + ref.provisional_diagnosis + '</small><br>';
                            }
                            if (ref.clinical_summary) {
                                html += '<small><i class="mdi mdi-file-document-outline text-secondary me-1"></i><strong>Summary:</strong> ' + ref.clinical_summary + '</small><br>';
                            }
                            if (ref.reason) {
                                html += '<small class="text-muted"><i class="mdi mdi-text-box-outline me-1"></i>' + ref.reason + '</small>';
                            }
                            if (ref.referring_doctor) {
                                html += '<br><small class="text-muted fst-italic">Referred by ' + ref.referring_doctor + '</small>';
                            }
                            html += '</div>';
                            html += '</div></div>';
                        });
                    } else {
                        html = '<div class="text-center text-muted py-3"><i class="mdi mdi-account-switch" style="font-size: 2rem;"></i><br>No referrals for this encounter</div>';
                    }
                    $('#referrals-list').html(html);
                },
                error: function() {
                    $('#referrals-loading').hide();
                    $('#referrals-list').html('<div class="alert alert-danger">Failed to load referrals</div>');
                }
            });
        }

        // ─── Patient-wide Referrals (all encounters, all doctors) ───
        function loadPatientReferrals() {
            $('#patient-referrals-loading').show();
            $('#patient-referrals-list').empty();
            $.ajax({
                url: wbRoute('encounters.referrals.patient-all', '/encounters/referrals/patient-all').replace('__EID__', encounterId),
                type: 'GET',
                success: function(data) {
                    $('#patient-referrals-loading').hide();
                    var html = '';
                    if (data.referrals && data.referrals.length> 0) {
                        $('#ref-patient-count').text(data.referrals.length);
                        data.referrals.forEach(function(ref) {
                            var urgencyBadge = { 'emergency': 'bg-danger', 'urgent': 'bg-warning text-dark', 'routine': 'bg-secondary' }[ref.urgency] || 'bg-secondary';
                            var statusBadge = { 'pending': 'bg-warning text-dark', 'booked': 'bg-primary', 'completed': 'bg-success', 'cancelled': 'bg-danger', 'declined': 'bg-dark', 'referred_out': 'bg-purple' }[ref.status] || 'bg-secondary';
                            var urgencyIcon = { 'emergency': 'mdi-alert-circle', 'urgent': 'mdi-alert', 'routine': 'mdi-check-circle' }[ref.urgency] || 'mdi-check-circle';
                            var borderColor = ref.urgency === 'emergency' ? '#dc3545' : ref.urgency === 'urgent' ? '#ffc107' : '#6c757d';

                            html += '<div class="card-modern border-start border-4 mb-2" style="border-color: ' + borderColor + ' !important;">';
                            html += '<div class="card-body py-2 px-3">';

                            // Header: badges + encounter source label + action buttons
                            html += '<div class="d-flex justify-content-between align-items-start">';
                            html += '<div>';
                            html += '<span class="badge ' + urgencyBadge + ' me-1"><i class="mdi ' + urgencyIcon + ' me-1"></i>' + ref.urgency + '</span>';
                            html += '<span class="badge ' + statusBadge + ' me-1">' + ref.status + '</span>';
                            html += '<span class="badge ' + (ref.type === 'internal' ? 'bg-info' : 'bg-secondary') + ' me-1">' + (ref.type === 'internal' ? 'Internal' : 'External') + '</span>';
                            if (ref.is_current_encounter) {
                                html += '<span class="badge bg-success me-1"><i class="mdi mdi-check me-1"></i>This Encounter</span>';
                            } else {
                                html += '<span class="badge bg-light text-dark border me-1">Other Encounter</span>';
                            }
                            if (ref.is_mine) {
                                html += '<span class="badge bg-primary me-1"><i class="mdi mdi-account me-1"></i>Mine</span>';
                            }
                            html += '</div>';
                            html += '<div class="d-flex align-items-center gap-1">';
                            html += '<small class="text-muted me-2">' + (ref.created_at || '') + '</small>';
                            if (ref.can_edit) {
                                html += '<button class="btn btn-sm btn-outline-primary py-0 px-1 btn-edit-referral" data-referral=\'' + JSON.stringify(ref) + '\' title="Edit"><i class="mdi mdi-pencil" style="font-size:.8rem;"></i></button>';
                                html += '<button class="btn btn-sm btn-outline-danger py-0 px-1 btn-delete-referral" data-id="' + ref.id + '" title="Delete"><i class="mdi mdi-delete" style="font-size:.8rem;"></i></button>';
                            }
                            html += '</div>';
                            html += '</div>';

                            // Details
                            html += '<div class="mt-1">';
                            if (ref.referring_doctor) {
                                html += '<small><i class="mdi mdi-arrow-up-bold text-danger me-1"></i><strong>From:</strong> ' + ref.referring_doctor;
                                if (ref.referring_clinic) html += ' <span class="text-muted">(' + ref.referring_clinic + ')</span>';
                                html += '</small><br>';
                            }
                            if (ref.target_clinic) {
                                html += '<small><i class="mdi mdi-arrow-down-bold text-success me-1"></i><strong>To:</strong> ' + ref.target_clinic;
                                if (ref.target_doctor) html += ' &mdash; ' + ref.target_doctor;
                                html += '</small><br>';
                            }
                            if (ref.external_facility) {
                                html += '<small><i class="mdi mdi-hospital-marker text-danger me-1"></i><strong>Facility:</strong> ' + ref.external_facility;
                                if (ref.external_doctor) html += ' &mdash; ' + ref.external_doctor;
                                html += '</small><br>';
                            }
                            if (ref.provisional_diagnosis) {
                                html += '<small><i class="mdi mdi-clipboard-pulse text-primary me-1"></i><strong>Diagnosis:</strong> ' + ref.provisional_diagnosis + '</small><br>';
                            }
                            if (ref.reason) {
                                html += '<small class="text-muted"><i class="mdi mdi-text-box-outline me-1"></i>' + ref.reason + '</small>';
                            }
                            if (ref.action_notes) {
                                html += '<br><small class="text-info"><i class="mdi mdi-note-text me-1"></i><em>' + ref.action_notes + '</em></small>';
                            }
                            html += '</div>';

                            html += '</div></div>';
                        });
                    } else {
                        html = '<div class="text-center text-muted py-3"><i class="mdi mdi-account-switch" style="font-size: 2rem;"></i><br>No referrals found for this patient</div>';
                    }
                    $('#patient-referrals-list').html(html);
                },
                error: function() {
                    $('#patient-referrals-loading').hide();
                    $('#patient-referrals-list').html('<div class="alert alert-danger">Failed to load patient referrals</div>');
                }
            });
        }

        // ─── Incoming Referrals (to this doctor from other encounters) ───
        function loadIncomingReferrals() {
            $('#incoming-referrals-loading').show();
            $('#incoming-referrals-list').empty();
            $.ajax({
                url: wbRoute('encounters.referrals.incoming', '/encounters/referrals/incoming').replace('__EID__', encounterId),
                type: 'GET',
                success: function(data) {
                    $('#incoming-referrals-loading').hide();
                    var html = '';
                    if (data.referrals && data.referrals.length> 0) {
                        $('#incoming-referral-count').text(data.referrals.length).show();
                        data.referrals.forEach(function(ref) {
                            var urgencyBadge = { 'emergency': 'bg-danger', 'urgent': 'bg-warning text-dark', 'routine': 'bg-secondary' }[ref.urgency] || 'bg-secondary';
                            var urgencyIcon = { 'emergency': 'mdi-alert-circle', 'urgent': 'mdi-alert', 'routine': 'mdi-check-circle' }[ref.urgency] || 'mdi-check-circle';
                            var borderColor = ref.urgency === 'emergency' ? '#dc3545' : ref.urgency === 'urgent' ? '#ffc107' : '#17a2b8';

                            html += '<div class="card-modern border-start border-4 mb-2" style="border-color: ' + borderColor + ' !important;">';
                            html += '<div class="card-body py-2 px-3">';

                            // Header
                            html += '<div class="d-flex justify-content-between align-items-start">';
                            html += '<div>';
                            html += '<span class="badge ' + urgencyBadge + ' me-1"><i class="mdi ' + urgencyIcon + ' me-1"></i>' + ref.urgency + '</span>';
                            html += '<span class="badge ' + (ref.type === 'internal' ? 'bg-info' : 'bg-secondary') + ' me-1">' + ref.type + '</span>';
                            html += '<span class="badge bg-light text-dark">' + ref.patient_name + ' — ' + ref.patient_file_no + '</span>';
                            html += '</div>';
                            html += '<small class="text-muted">' + (ref.created_at || '') + '</small>';
                            html += '</div>';

                            // Details
                            html += '<div class="mt-1">';
                            html += '<small><i class="mdi mdi-doctor text-primary me-1"></i><strong>From:</strong> ' + ref.referring_doctor + '</small>';
                            if (ref.referring_clinic) html += ' <small class="text-muted">(' + ref.referring_clinic + ')</small>';
                            html += '<br>';
                            if (ref.provisional_diagnosis) {
                                html += '<small><i class="mdi mdi-clipboard-pulse text-primary me-1"></i><strong>Diagnosis:</strong> ' + ref.provisional_diagnosis + '</small><br>';
                            }
                            if (ref.reason) {
                                html += '<small class="text-muted"><i class="mdi mdi-text-box-outline me-1"></i>' + ref.reason + '</small><br>';
                            }
                            html += '</div>';

                            // Actions
                            html += '<div class="mt-2 d-flex gap-2">';
                            html += '<button class="btn btn-sm btn-success btn-accept-incoming-referral" data-id="' + ref.id + '" data-patient="' + ref.patient_id + '" data-patient-name="' + ref.patient_name + '"><i class="mdi mdi-check-circle me-1"></i> Accept &amp; Start Encounter</button>';
                            html += '<button class="btn btn-sm btn-outline-warning btn-decline-incoming-referral" data-id="' + ref.id + '"><i class="mdi mdi-close-circle me-1"></i> Decline</button>';
                            html += '</div>';

                            html += '</div></div>';
                        });
                    } else {
                        html = '<div class="text-center text-muted py-2"><i class="mdi mdi-check-circle" style="font-size:1.5rem;"></i><br><small>No pending incoming referrals</small></div>';
                    }
                    $('#incoming-referrals-list').html(html);
                },
                error: function() {
                    $('#incoming-referrals-loading').hide();
                    $('#incoming-referrals-list').html('<div class="alert alert-sm alert-danger">Failed to load incoming referrals</div>');
                }
            });
        }

        // Accept incoming referral — mark as accepted and navigate to new encounter
        $(document).on('click', '.btn-accept-incoming-referral', function() {
            var refId = $(this).data('id');
            var patientId = $(this).data('patient');
            var patientName = $(this).data('patient-name');
            var $btn = $(this);

            if (!confirm('Accept this referral for ' + patientName + '? A new encounter will be started.')) return;

            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Accepting...');
            $.post(wbUrl('referrals/' + refId + '/accept'), {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')}, function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Referral accepted');
                    // Navigate to new encounter for this patient
                    if (res.encounter_url) {
                        window.open(res.encounter_url, '_blank');
                    }
                    loadIncomingReferrals();
                } else {
                    toastr.error(res.message || 'Failed to accept referral');
                }
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to accept referral');
                $btn.prop('disabled', false).html('<i class="mdi mdi-check-circle me-1"></i> Accept & Start Encounter');
            });
        });

        // Decline incoming referral
        $(document).on('click', '.btn-decline-incoming-referral', function() {
            var refId = $(this).data('id');
            var reason = prompt('Reason for declining this referral:');
            if (!reason) return;
            var $btn = $(this);
            $btn.prop('disabled', true);
            $.post(wbRoute('referrals.decline', '/referrals/decline').replace('__RID__', refId), {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')}, function(res) {
                if (res.success) {
                    toastr.success('Referral declined');
                    loadIncomingReferrals();
                }
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to decline');
                $btn.prop('disabled', false);
            });
        });

        // Submit referral form (create or update)
        $(document).on('submit', '#create-referral-form', function(e) {
            e.preventDefault();
            var btn = $('#referral-submit-btn');
            var editId = $('#referral-edit-id').val();
            var isEdit = editId && editId.length> 0;
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + (isEdit ? 'Updating...' : 'Submitting...'));

            var url, method;
            if (isEdit) {
                url = wbUrl('encounters/' + encounterId + '/referrals/' + editId);
                method = 'PUT';
            } else {
                url = wbRoute('encounters.referrals.create', '/encounters/referrals/create').replace('__EID__', encounterId);
                method = 'POST';
            }

            $.ajax({
                url: url,
                type: method,
                data: $(this).serialize(),
                success: function(response) {
                    toastr.success(response.message || (isEdit ? 'Referral updated' : 'Referral created successfully'));
                    resetReferralForm();
                    $('#referral-form-card').addClass('d-none');
                    loadEncounterReferrals();
                    _patientRefLoaded = false; // force reload patient referrals
                    _incomingRefLoaded = false; // force reload incoming referrals
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON?.message || 'Failed to save referral';
                    if (xhr.responseJSON?.errors) {
                        var errs = Object.values(xhr.responseJSON.errors).flat();
                        msg = errs.join('<br>');
                    }
                    toastr.error(msg);
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="mdi mdi-send"></i> ' + (isEdit ? 'Update Referral' : 'Submit Referral'));
                }
            });
        });

        // Edit referral — populate form
        $(document).on('click', '.btn-edit-referral', function() {
            var ref = $(this).data('referral');
            resetReferralForm();

            $('#referral-edit-id').val(ref.id);
            $('#referral-submit-btn').html('<i class="mdi mdi-pencil"></i> Update Referral');

            // Set type and toggle fields
            $('#referral-type-select').val(ref.type).trigger('change');

            // Set values
            $('[name="target_clinic_id"]').val(ref.target_clinic_id || '');
            $('[name="target_doctor_id"]').val(ref.target_doctor_id || '');
            $('[name="external_facility_name"]').val(ref.external_facility || '');
            $('[name="external_doctor_name"]').val(ref.external_doctor || '');
            $('[name="external_facility_address"]').val(ref.external_facility_address || '');
            $('[name="external_facility_phone"]').val(ref.external_facility_phone || '');
            $('[name="urgency"]').val(ref.urgency || 'routine');
            $('[name="provisional_diagnosis"]').val(ref.provisional_diagnosis || '');
            $('[name="clinical_summary"]').val(ref.clinical_summary || '');
            $('[name="reason"]').val(ref.reason || '');

            // Show form
            $('#referral-form-card').removeClass('d-none');
            $('#referral-form-card')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        // Delete referral
        $(document).on('click', '.btn-delete-referral', function() {
            var refId = $(this).data('id');
            var $btn = $(this);

            if (!confirm('Delete this referral? This cannot be undone.')) return;

            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            $.ajax({
                url: wbUrl('encounters/' + encounterId + '/referrals/' + refId),
                type: 'DELETE',
                data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')},
                success: function(response) {
                    toastr.success(response.message || 'Referral deleted');
                    loadEncounterReferrals();
                    _patientRefLoaded = false; // force reload patient referrals
                    _incomingRefLoaded = false; // force reload incoming referrals
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to delete referral');
                    $btn.prop('disabled', false).html('<i class="mdi mdi-delete" style="font-size:.8rem;"></i>');
                }
            });
        });

        // Toggle Death Notification Fields
        $(document).on('change', '#encounter-outcome', function() {
            var val = $(this).val();
            if (val && val.startsWith('death')) {
                $('#death-notification-fields').slideDown();
                $('#followup-fields').closest('.mb-3').hide(); // Hide follow-up if deceased
                $('#schedule-followup-check').prop('checked', false).trigger('change');
            } else {
                $('#death-notification-fields').slideUp();
                $('#followup-fields').closest('.mb-3').show();
            }
        });
    function showBundleRemove(btn) {
        var parentId = btn.dataset.parentId;
        var bundleName = btn.dataset.bundleName;
        var items = JSON.parse(btn.dataset.items || '[]');
        var removeUrl = btn.dataset.removeUrl;
        BundleRemoveModal.show({
            bundleId: parentId,
            bundleName: bundleName,
            items: items,
            onConfirm: function(callback) {
                $.ajax({
                    url: removeUrl,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    contentType: 'application/json',
                    dataType: 'json',
                    data: JSON.stringify({ parent_request_id: parentId }),
                    success: function(r) {
                        if (r.success) {
                            toastr.success(r.message || 'Combo removed');
                            callback(false);
                            if ($.fn.DataTable.isDataTable('#investigation_history_list')) { $('#investigation_history_list').DataTable().ajax.reload(null, false); }
                            if ($.fn.DataTable.isDataTable('#presc_history_list')) { $('#presc_history_list').DataTable().ajax.reload(null, false); }
                            if ($.fn.DataTable.isDataTable('#imaging_history_list')) { $('#imaging_history_list').DataTable().ajax.reload(null, false); }
                        } else {
                            toastr.error(r.message || 'Failed to remove combo');
                            callback(true);
                        }
                    },
                    error: function() { toastr.error('Error removing combo'); callback(true); }
                });
            }
        });
    }

    // Quick add allergy from sticky header
    function promptAddAllergy(patientId) {
        var newAllergy = prompt("Enter new allergy (e.g., Penicillin, Peanuts):");
        if (newAllergy && newAllergy.trim() !== "") {
            var currentAllergiesText = "";
            $('#sticky-allergies-container .badge').each(function() {
                var text = $(this).text().trim();
                if(text !== 'None known') {
                    currentAllergiesText += (currentAllergiesText ? ", " : "") + text;
                }
            });
            var combinedAllergies = currentAllergiesText ? currentAllergiesText + ", " + newAllergy : newAllergy;
            
            $.ajax({
                url: wbUrl('/patient/' + patientId + '/update-allergies'),
                method: 'PUT',
                data: {
                    allergies: combinedAllergies,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(res) {
                    if (res.status === 'success') {
                        var html = '';
                        res.data.forEach(function(a) {
                            html += '<span class="badge bg-danger">' + a + '</span> ';
                        });
                        $('#sticky-allergies-container').html(html);
                        toastr.success('Allergy added successfully.');
                    }
                },
                error: function() {
                    toastr.error('Failed to add allergy.');
                }
            });
        }
    }

    $(document).ready(function() {
        window.currentPatientId = (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '');
        if(typeof loadUnviewedCounts === 'function') {
            loadUnviewedCounts((window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : ''));
        }

        if(typeof ClinicalAlerts !== 'undefined') {
            ClinicalAlerts.init((window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : ''), 'doctors');
        }
        
        // Fetch latest vitals for sticky header
        if(typeof currentPatient !== 'undefined' || typeof (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '') !== 'undefined') {
            $.get('/clinical-context/patient/' + (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : '') + '/vitals', function(vitals) {
                if(vitals && vitals.length > 0) {
                    var latest = vitals[0];
                    $('#sticky-vital-temp').html('<i class="fa fa-thermometer-half text-danger"></i> ' + (latest.temp ? latest.temp + '°C' : '--'));
                    $('#sticky-vital-bp').html('<i class="fa fa-heart text-danger"></i> ' + (latest.blood_pressure ? latest.blood_pressure : '--'));
                    $('#sticky-vital-wt').html('<i class="fa fa-weight text-primary"></i> ' + (latest.weight ? latest.weight + 'kg' : '--'));
                    $('#sticky-vital-hr').html('<i class="fa fa-stethoscope text-success"></i> ' + (latest.heart_rate ? latest.heart_rate + 'bpm' : '--'));
                }
            });
        }
    });


            $(document).ready(function() {
                if (typeof PatientSummaryManager !== 'undefined') {
                    window.patientSummary = new PatientSummaryManager({
                        patientId: (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : ''),
                        encounterId: '',
                        autoOpen: '',
                        voiceEnabled: '',
                        voiceRate: ''
                    });
                }
            });

    
    $(document).ready(function() {
        // Define the tab navigation order (tab_pane_id → sidebar_tab_id)
        var tabOrder = [
            { pane: 'treatment_plans', tab: 'treatment_plans_tab', label: 'Plans' },
            { pane: 'clinical_story', tab: 'clinical_story_tab', label: 'Story' },
            { pane: 'vitals', tab: 'vitals_data_tab', label: 'Vitals' },
            { pane: 'nurse_charts', tab: 'nurse_charts_tab', label: 'Nurse Charts' },
            { pane: 'inj_imm_history', tab: 'inj_imm_history_tab', label: 'Inj/Imm' },
            { pane: 'clinical_notes', tab: 'clinical_notes_tab', label: 'Notes' },
            { pane: 'laboratory_services', tab: 'laboratory_services_tab', label: 'Labs' },
            { pane: 'imaging_services', tab: 'imaging_services_tab', label: 'Imaging' },
            { pane: 'medications', tab: 'medications_tab', label: 'Meds' },
            { pane: 'non_pharm', tab: 'non_pharm_tab', label: 'Care Plan' },
            { pane: 'procedures', tab: 'procedures_tab', label: 'Procedures' },
            { pane: 'admissions', tab: 'admissions_tab', label: 'Admissions' },
            { pane: 'referrals', tab: 'referrals_tab', label: 'Referrals' }
        ];

        window.encounterTabOrder = tabOrder;

        window.updateFloatingNav = function(activeTabId) {
            var activePane = activeTabId ? activeTabId.replace('_tab', '').replace('mobile_', '').replace('_data', '') : 'treatment_plans';
            var idx = window.encounterTabOrder.findIndex(function(t) { return t.pane === activePane || t.tab === activeTabId; });
            if (idx === -1) {
                // Fallback check
                if (activeTabId === 'vitals_data_tab' || activeTabId === 'vitals') idx = 2;
                else idx = 0;
            }

            var item = window.encounterTabOrder[idx];
            var prevHtml = '', saveHtml = '', nextHtml = '';

            // Previous Button
            if (idx > 0) {
                var prev = window.encounterTabOrder[idx - 1];
                prevHtml = '<button type="button" class="btn btn-outline-secondary shadow-sm" onclick="switch_tab(event, \'' + prev.tab + '\')">' +
                    '<i class="fa fa-chevron-left me-1"></i> ' + prev.label + '</button>';
            }

            // Save Button (for tabs that have forms — Notes)
            if (item.pane === 'clinical_notes') {
                saveHtml = '<button type="button" id="save_diagnosis_btn" class="btn btn-outline-success shadow-sm ms-2 me-2" onclick="if(typeof saveDiagnosis === \'function\') saveDiagnosis(true);" title="Save">' +
                    '<i class="fa fa-save me-1"></i> Save</button>';
            }

            // Next Button
            if (idx < window.encounterTabOrder.length - 1) {
                var next = window.encounterTabOrder[idx + 1];
                nextHtml = '<button type="button" class="btn btn-primary shadow-sm" onclick="switch_tab(event, \'' + next.tab + '\')">' +
                    next.label + ' <i class="fa fa-chevron-right ms-1"></i></button>';
            } else {
                nextHtml = '<button type="button" class="btn btn-success shadow-sm" onclick="$(\'#concludeEncounterModal\').modal(\'show\')">' +
                    '<i class="fa fa-check-circle me-1"></i> Conclude Encounter</button>';
            }

            $('#floating-nav-prev-container').html(prevHtml);
            $('#floating-nav-save-container').html(saveHtml);
            $('#floating-nav-next-container').html(nextHtml);
        };

        // Initialize floating nav for initial active tab
        var initialTab = $('#myTabContent > .tab-pane.active').attr('id') || 'treatment_plans';
        window.updateFloatingNav(initialTab);

        // Listen for all Bootstrap tab changes to update floating nav automatically
        $('a[data-toggle="tab"], a[data-bs-toggle="tab"], button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            var targetId = $(e.target).attr('href') || $(e.target).attr('data-bs-target') || $(e.target).attr('data-target');
            if (targetId) {
                targetId = targetId.replace('#', '');
                
                // Only update floating nav if it's a main tab (avoids resetting on sub-tab navigation)
                var activePaneToCheck = targetId.replace('_tab', '').replace('mobile_', '').replace('_data', '');
                var isMainTab = window.encounterTabOrder.some(function(t) { 
                    return t.pane === activePaneToCheck || t.tab === targetId; 
                });
                
                // Extra check for fallback tabs
                if (targetId === 'vitals' || targetId === 'vitals_data_tab') isMainTab = true;
                
                if (isMainTab) {
                    window.updateFloatingNav(targetId);
                    // Scroll to top of main content when switching main tabs, with a delay
                    setTimeout(function() {
                        var anchor = document.getElementById('tab-top-scroll-anchor');
                        if (anchor) {
                            anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }, 300);
                }
            }
        });
    });
