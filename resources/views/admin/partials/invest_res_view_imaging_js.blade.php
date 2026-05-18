{{--
    Shared Imaging Result View JavaScript Function

    Populates the #imagingResViewModal with data from the clicked button's data attributes.
    Requires: invest_res_view_imaging_modal partial to be included in the same view.
--}}

<script>
if (typeof trackResultView !== 'function') {
    window.trackResultView = function(type, id, viewType = 'modal') {
        $.ajax({
            url: '{{ route("result-views.store") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                viewable_type: type,
                viewable_id: id,
                view_type: viewType
            },
            success: function(response) {
                if (response.success) {
                    console.log('Result view tracked successfully', response);
                    if (window.loadUnviewedCounts && window.currentPatientId) {
                        window.loadUnviewedCounts(window.currentPatientId);
                    }
                }
            },
            error: function(err) {
                console.error('Error tracking result view:', err);
            }
        });
    };
}

if (typeof trackResultPrint !== 'function') {
    window.trackResultPrint = function(type, id) {
        trackResultView(type, id, 'print');
    };
}

if (typeof loadResultAuditHistory !== 'function') {
    window.loadResultAuditHistory = function(type, id, containerId, rowsId) {
        var container = $('#' + containerId);
        var rows = $('#' + rowsId);
        
        container.hide();
        rows.empty();
        
        $.ajax({
            url: '/result-views/' + type + '/' + id,
            type: 'GET',
            success: function(response) {
                if (response.success && response.views && response.views.length > 0) {
                    var html = '';
                    response.views.forEach(function(view) {
                        var badgeClass = view.view_type === 'print' ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-info-subtle text-info border border-info-subtle';
                        var badgeIcon = view.view_type === 'print' ? 'mdi-printer' : 'mdi-eye-outline';
                        var actionText = view.view_type === 'print' ? 'Printed' : 'Viewed';
                        
                        html += '<tr>' +
                            '<td class="px-3"><strong>' + view.user_name + '</strong></td>' +
                            '<td><span class="badge ' + badgeClass + ' p-1 px-2"><i class="mdi ' + badgeIcon + ' me-1"></i>' + actionText + '</span></td>' +
                            '<td class="pe-3 text-end text-muted">' + view.viewed_at + '</td>' +
                            '</tr>';
                    });
                    rows.html(html);
                    container.show();
                }
            },
            error: function(err) {
                console.error('Error loading result audit history:', err);
            }
        });
    };
}

function setImagingResViewInModal(obj) {
    let res_obj = JSON.parse($(obj).attr('data-result-obj'));
    
    let viewableType = $(obj).attr('data-viewable-type');
    let viewableId = $(obj).attr('data-viewable-id');
    if (viewableType && viewableId) {
        trackResultView(viewableType, viewableId, 'modal');
        loadResultAuditHistory(viewableType, viewableId, 'imaging_view_history_section', 'imaging_view_history_rows');
    } else {
        $('#imaging_view_history_section').hide();
    }

    // Basic service info
    $('.imaging_res_service_name_view').text($(obj).attr('data-service-name'));

    // Patient information
    let patientName = (res_obj.patient && res_obj.patient.user)
        ? res_obj.patient.user.firstname + ' ' + res_obj.patient.user.surname
        : 'N/A';
    $('#imaging_patient_name').html(patientName);
    $('#imaging_patient_id').html(res_obj.patient ? res_obj.patient.file_no : 'N/A');

    // Calculate age from date of birth
    let age = 'N/A';
    if (res_obj.patient && res_obj.patient.date_of_birth) {
        let dob = new Date(res_obj.patient.date_of_birth);
        let today = new Date();
        let ageYears = today.getFullYear() - dob.getFullYear();
        let monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            ageYears--;
        }
        age = ageYears + ' years';
    }
    $('#imaging_patient_age').html(age);

    // Gender
    let gender = (res_obj.patient && res_obj.patient.gender) ? res_obj.patient.gender.toUpperCase() : 'N/A';
    $('#imaging_patient_gender').html(gender);

    // Test information
    $('#imaging_test_id').html(res_obj.id);
    $('#imaging_result_date').html(res_obj.result_date || 'N/A');

    // Results person — null-safe
    let resultByName = 'N/A';
    if (res_obj.results_person) {
        resultByName = res_obj.results_person.firstname + ' ' + res_obj.results_person.surname;
    }
    $('#imaging_result_by').html(resultByName);

    // Status
    let statusBadge = '';
    if (res_obj.status) {
        let statusClass = 'badge-';
        let statusText = String(res_obj.status);
        switch(statusText.toLowerCase()) {
            case 'completed':
            case '3':
            case '4':
                statusClass += 'success';
                statusText = 'Completed';
                break;
            case 'pending':
            case '1':
                statusClass += 'warning';
                statusText = 'Pending';
                break;
            case 'in progress':
            case '2':
                statusClass += 'info';
                statusText = 'In Progress';
                break;
            default: statusClass += 'secondary';
        }
        statusBadge = '<span class="badge ' + statusClass + '">' + statusText + '</span>';
    }
    $('#imaging_status').html(statusBadge || 'N/A');

    // Signature date (use result date)
    $('#imaging_signature_date').html(res_obj.result_date || '');

    // Generated date (current date)
    let now = new Date();
    let generatedDate = now.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    $('#imaging_generated_date').html(generatedDate);

    // Handle V2 results (structured data)
    if (res_obj.result_data && typeof res_obj.result_data === 'object') {
        let resultsHtml = '<table class="imaging-result-table"><thead><tr>';
        resultsHtml += '<th style="width: 40%;">Parameter</th>';
        resultsHtml += '<th style="width: 25%;">Results</th>';
        resultsHtml += '<th style="width: 25%;">Reference Range</th>';
        resultsHtml += '<th style="width: 10%;">Status</th>';
        resultsHtml += '</tr></thead><tbody>';

        res_obj.result_data.forEach(function(param) {
            resultsHtml += '<tr>';
            resultsHtml += '<td><strong>' + param.name + '</strong>';
            if (param.code) {
                resultsHtml += ' <span style="color: #999;">(' + param.code + ')</span>';
            }
            resultsHtml += '</td>';

            // Value with unit
            let valueDisplay = param.value;
            if (param.unit) {
                valueDisplay += ' ' + param.unit;
            }
            resultsHtml += '<td>' + valueDisplay + '</td>';

            // Reference range
            let refRange = 'N/A';
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
            let statusHtml = '';
            if (param.status) {
                let statusClass = 'imaging-status-' + param.status.toLowerCase().replace(' ', '-');
                statusHtml = '<span class="imaging-result-status-badge ' + statusClass + '">' + param.status + '</span>';
            }
            resultsHtml += '<td>' + statusHtml + '</td>';
            resultsHtml += '</tr>';
        });

        resultsHtml += '</tbody></table>';
        $('#imaging_res').html(resultsHtml);
    } else {
        // V1 results (HTML content)
        $('#imaging_res').html(res_obj.result || '<p class="text-muted">No result available</p>');
    }

    // Handle attachments
    $('#imaging_attachments').html('');
    if (res_obj.attachments) {
        let attachments = typeof res_obj.attachments === 'string' ? JSON.parse(res_obj.attachments) : res_obj.attachments;
        if (attachments && attachments.length> 0) {
            let attachHtml = '<div class="imaging-result-attachments"><h6 style="margin-bottom: 15px;"><i class="mdi mdi-paperclip"></i> Attachments</h6><div class="row">';
            attachments.forEach(function(attachment) {
                let url = '{{ asset("storage") }}/' + attachment.path;
                let icon = typeof getFileIcon === 'function' ? getFileIcon(attachment.type) : '<i class="fa fa-file"></i>';
                attachHtml += `<div class="col-md-4 mb-2">
                    <a href="${url}" target="_blank" class="btn btn-outline-primary btn-sm btn-block">
                        ${icon} ${attachment.name}
                    </a>
                </div>`;
            });
            attachHtml += '</div></div>';
            $('#imaging_attachments').html(attachHtml);
        }
    }

    $('#imagingResViewModal').modal('show');
}
</script>
