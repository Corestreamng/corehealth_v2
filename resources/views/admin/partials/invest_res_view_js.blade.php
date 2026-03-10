{{--
    Investigation Result View JavaScript (shared partial)

    Populates the #investResViewModal with data from the clicked button's data attributes.
    Requires: invest_res_view_modal partial to be included in the same view.

    Provides:
        setResViewInModal(obj)  — populates & opens the result view modal
        PrintElem(elemId)       — prints the content of the given element
        getFileIcon(type)       — returns an icon tag for a file type/extension
--}}

<script>
function setResViewInModal(obj) {
    var res_obj = JSON.parse($(obj).attr('data-result-obj'));

    // Basic service info
    $('.invest_res_service_name_view').text($(obj).attr('data-service-name'));

    // Patient information (null-safe)
    var patientName = (res_obj.patient && res_obj.patient.user)
        ? res_obj.patient.user.firstname + ' ' + res_obj.patient.user.surname
        : 'N/A';
    $('#res_patient_name').html(patientName);
    $('#res_patient_id').html(res_obj.patient ? res_obj.patient.file_no : 'N/A');

    // Calculate age from date of birth
    var age = 'N/A';
    if (res_obj.patient && res_obj.patient.date_of_birth) {
        var dob = new Date(res_obj.patient.date_of_birth);
        var today = new Date();
        var ageYears = today.getFullYear() - dob.getFullYear();
        var monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            ageYears--;
        }
        age = ageYears + ' years';
    }
    $('#res_patient_age').html(age);

    // Gender
    var gender = (res_obj.patient && res_obj.patient.gender) ? res_obj.patient.gender.toUpperCase() : 'N/A';
    $('#res_patient_gender').html(gender);

    // Test information
    $('#res_test_id').html(res_obj.id);
    $('#res_lab_number').html(res_obj.lab_number || 'N/A');
    $('#res_sample_date').html(res_obj.sample_date || 'N/A');
    $('#res_result_date').html(res_obj.result_date || 'N/A');

    // Results person (null-safe)
    var resultByName = 'N/A';
    if (res_obj.results_person) {
        resultByName = res_obj.results_person.firstname + ' ' + res_obj.results_person.surname;
    }
    $('#res_result_by').html(resultByName);

    // Signature date
    $('#res_signature_date').html(res_obj.result_date || '');

    // Generated date (current)
    var now = new Date();
    var generatedDate = now.toLocaleDateString('en-US', {
        year: 'numeric', month: 'long', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
    $('#res_generated_date').html(generatedDate);

    // Handle V2 results (structured data)
    if (res_obj.result_data) {
        var resultData = res_obj.result_data;
        if (typeof resultData === 'string') {
            try { resultData = JSON.parse(resultData); } catch (e) { resultData = null; }
        }

        if (resultData && typeof resultData === 'object') {
            var paramsArray = Array.isArray(resultData) ? resultData : [];

            if (paramsArray.length > 0) {
                var resultsHtml = '<table class="result-table"><thead><tr>';
                resultsHtml += '<th style="width: 40%;">Test Parameter</th>';
                resultsHtml += '<th style="width: 25%;">Results</th>';
                resultsHtml += '<th style="width: 25%;">Reference Range</th>';
                resultsHtml += '<th style="width: 10%;">Status</th>';
                resultsHtml += '</tr></thead><tbody>';

                paramsArray.forEach(function(param) {
                    resultsHtml += '<tr>';
                    resultsHtml += '<td><strong>' + param.name + '</strong>';
                    if (param.code) {
                        resultsHtml += ' <span style="color: #999;">(' + param.code + ')</span>';
                    }
                    resultsHtml += '</td>';

                    // Value with unit
                    var valueDisplay = param.value !== undefined && param.value !== null ? param.value : '';
                    if (param.unit) valueDisplay += ' ' + param.unit;
                    resultsHtml += '<td>' + valueDisplay + '</td>';

                    // Reference range
                    var refRange = 'N/A';
                    if (param.reference_range) {
                        if (param.type === 'integer' || param.type === 'float') {
                            if (param.reference_range.min !== undefined && param.reference_range.max !== undefined) {
                                refRange = param.reference_range.min + ' - ' + param.reference_range.max;
                                if (param.unit) refRange += ' ' + param.unit;
                            }
                        } else if (param.type === 'boolean' || param.type === 'enum') {
                            refRange = param.reference_range.reference_value || 'N/A';
                        } else if (param.reference_range.text) {
                            refRange = param.reference_range.text;
                        }
                    }
                    resultsHtml += '<td>' + refRange + '</td>';

                    // Status badge
                    var statusHtml = '';
                    if (param.status) {
                        var statusClass = 'status-' + param.status.toLowerCase().replace(' ', '-');
                        statusHtml = '<span class="result-status-badge ' + statusClass + '">' + param.status + '</span>';
                    }
                    resultsHtml += '<td>' + statusHtml + '</td>';
                    resultsHtml += '</tr>';
                });

                resultsHtml += '</tbody></table>';
                $('#invest_res').html(resultsHtml);
            } else {
                $('#invest_res').html(res_obj.result || '<p class="text-muted">No result available</p>');
            }
        } else {
            $('#invest_res').html(res_obj.result || '<p class="text-muted">No result available</p>');
        }
    } else {
        // V1 results (HTML content)
        $('#invest_res').html(res_obj.result || '<p class="text-muted">No result available</p>');
    }

    // Handle attachments
    $('#invest_attachments').html('');
    if (res_obj.attachments) {
        var attachments = typeof res_obj.attachments === 'string' ? JSON.parse(res_obj.attachments) : res_obj.attachments;
        if (attachments && attachments.length > 0) {
            var attachHtml = '<div class="result-attachments"><h6 style="margin-bottom: 15px;"><i class="mdi mdi-paperclip"></i> Attachments</h6><div class="row">';
            attachments.forEach(function(attachment) {
                var url = '{{ asset("storage") }}/' + attachment.path;
                var icon = getFileIcon(attachment.type || attachment.name);
                attachHtml += '<div class="col-md-4 mb-2">' +
                    '<a href="' + url + '" target="_blank" class="btn btn-outline-primary btn-sm btn-block">' +
                    icon + ' ' + attachment.name +
                    '</a></div>';
            });
            attachHtml += '</div></div>';
            $('#invest_attachments').html(attachHtml);
        }
    }

    $('#investResViewModal').modal('show');
}

function PrintElem(elem) {
    var mywindow = window.open('', 'PRINT', 'height=600,width=800');
    mywindow.document.write('<html><head><title>' + document.title + '</title>');
    mywindow.document.write('<style>');
    mywindow.document.write('body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }');
    mywindow.document.write('.result-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 20px; border-bottom: 3px solid #000; }');
    mywindow.document.write('.result-header-left { display: flex; align-items: center; gap: 15px; }');
    mywindow.document.write('.result-logo { width: 70px; height: 70px; object-fit: contain; }');
    mywindow.document.write('.result-hospital-name { font-size: 1.4rem; font-weight: bold; }');
    mywindow.document.write('.result-header-right { text-align: right; font-size: 0.85rem; line-height: 1.6; }');
    mywindow.document.write('.result-title-section { background: #333; color: white; text-align: center; padding: 10px; font-size: 1rem; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }');
    mywindow.document.write('.result-patient-info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 15px 20px; }');
    mywindow.document.write('.result-info-box { padding: 10px; }');
    mywindow.document.write('.result-info-row { display: flex; margin-bottom: 5px; }');
    mywindow.document.write('.result-info-label { font-weight: bold; min-width: 120px; font-size: 0.85rem; }');
    mywindow.document.write('.result-info-value { font-size: 0.85rem; }');
    mywindow.document.write('.result-section { padding: 15px 20px; }');
    mywindow.document.write('.result-section-title { font-size: 0.95rem; font-weight: bold; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 2px solid #333; text-transform: uppercase; }');
    mywindow.document.write('.result-table { width: 100%; border-collapse: collapse; }');
    mywindow.document.write('.result-table th { background: #333; color: white; padding: 8px 10px; text-align: left; font-size: 0.85rem; -webkit-print-color-adjust: exact; print-color-adjust: exact; }');
    mywindow.document.write('.result-table td { padding: 8px 10px; border-bottom: 1px solid #ddd; font-size: 0.85rem; }');
    mywindow.document.write('.result-status-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: bold; }');
    mywindow.document.write('.status-normal { background: #d4edda; color: #155724; -webkit-print-color-adjust: exact; print-color-adjust: exact; }');
    mywindow.document.write('.status-high { background: #f8d7da; color: #721c24; -webkit-print-color-adjust: exact; print-color-adjust: exact; }');
    mywindow.document.write('.status-low { background: #fff3cd; color: #856404; -webkit-print-color-adjust: exact; print-color-adjust: exact; }');
    mywindow.document.write('.status-abnormal { background: #f8d7da; color: #721c24; -webkit-print-color-adjust: exact; print-color-adjust: exact; }');
    mywindow.document.write('.result-attachments { margin: 10px 20px; padding: 10px; background: #f8f9fa; border-radius: 5px; }');
    mywindow.document.write('.result-footer { padding: 15px 20px; border-top: 2px solid #ccc; font-size: 0.75rem; color: #666; text-align: center; margin-top: 20px; }');
    mywindow.document.write('</style>');
    mywindow.document.write('</head><body>');
    mywindow.document.write(document.getElementById(elem).innerHTML);
    mywindow.document.write('</body></html>');
    mywindow.document.close();
    mywindow.focus();
    mywindow.print();
    mywindow.close();
    return true;
}

function getFileIcon(typeOrName) {
    if (!typeOrName) return '<i class="mdi mdi-file"></i>';
    var t = typeOrName.toLowerCase();
    if (t.includes('image') || t.includes('jpg') || t.includes('jpeg') || t.includes('png'))
        return '<i class="mdi mdi-file-image"></i>';
    if (t.includes('pdf'))
        return '<i class="mdi mdi-file-pdf"></i>';
    if (t.includes('doc') || t.includes('word'))
        return '<i class="mdi mdi-file-word"></i>';
    return '<i class="mdi mdi-file"></i>';
}
</script>
