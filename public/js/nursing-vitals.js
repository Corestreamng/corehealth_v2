// ============================================
// INVESTIGATION RESULT ENTRY / VIEW FUNCTIONS
// Uses shared InvestResultEntry module for enter/edit
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

var _PI_LAB_REQ_APPROVAL = '';
var _PI_IMG_REQ_APPROVAL = '';
var _PI_DR_SELF_LAB      = '';
var _PI_NR_SELF_LAB      = '';
var _PI_DR_SELF_IMG      = '';
var _PI_NR_SELF_IMG      = '';

function _autoApproveIfEnabled(requestId, type) {
    if ($('#invest_res_is_edit').val() == '1') { return; }
    var reqApproval = (type === 'lab') ? _PI_LAB_REQ_APPROVAL : _PI_IMG_REQ_APPROVAL;
    if (!reqApproval) { return; }
    var canSelf = (type === 'lab') ? (_PI_DR_SELF_LAB || _PI_NR_SELF_LAB) : (_PI_DR_SELF_IMG || _PI_NR_SELF_IMG);
    if (!canSelf) { return; }
    var url = (type === 'lab') ? '/lab-workbench/self-approve/' + requestId : '/imaging-workbench/self-approve/' + requestId;
    $.post(url, { _token: $('meta[name="csrf-token"]').attr('content') })
        .done(function (res) {
            if (res && res.success) { toastr.success('Result approved automatically.'); }
            else { toastr.warning('Result saved. Auto-approval failed: ' + ((res && res.message) || '')); }
        })
        .fail(function () { toastr.warning('Result saved but auto-approval could not be completed.'); });
}

// Initialize shared result entry module
InvestResultEntry.bindFormSubmit(function() {
    // Refresh main history DataTables
    if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
        $('#investigation_history_list').DataTable().ajax.reload(null, false);
    }
    if ($.fn.DataTable.isDataTable('#cr_lab_history_list')) {
        $('#cr_lab_history_list').DataTable().ajax.reload(null, false);
    }
    if ($.fn.DataTable.isDataTable('#cr_imaging_history_list')) {
        $('#cr_imaging_history_list').DataTable().ajax.reload(null, false);
    }
    var ctx = window._investResultContext;
    if (ctx) { _autoApproveIfEnabled(ctx.id, ctx.type); window._investResultContext = null; }
});

// setResViewInModal, PrintElem, getFileIcon now provided by invest_res_view_js partial

// Delete Lab Request with Reason
let deleteRequestId = null;
let deleteEncounterId = null;

function deleteLabRequest(requestId, encounterId, serviceName) {
    deleteRequestId = requestId;
    deleteEncounterId = encounterId;
    $('#delete_service_name').text(serviceName);
    $('#delete_request_id').text(requestId);
    $('#delete_reason').val('');
    $('#deleteReasonModal').modal('show');
}

$('#deleteRequestForm').on('submit', function(e) {
    e.preventDefault();

    const reason = $('#delete_reason').val();

    if (reason.length < 10) {
        alert('Please provide a detailed reason (minimum 10 characters)');
        return;
    }

    $.ajax({
        url: wbUrl(`/lab-workbench/lab-service-requests/${deleteRequestId}`),
        method: 'DELETE',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            reason: reason
        },
        success: function(response) {
            $('#deleteReasonModal').modal('hide');
            alert(response.message);

            // Reload patient data if we're on a patient
            if (currentPatient) {
                loadPatient(currentPatient);
            }
        },
        error: function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to delete request'));
        }
    });
});

// Dismiss Lab Request
let dismissRequestId = null;

function dismissSingleRequest(requestId, serviceName) {
    dismissRequestId = requestId;
    $('#dismiss_service_name').text(serviceName);
    $('#dismiss_request_id').text(requestId);
    $('#dismiss_reason').val('');
    $('#dismissReasonModal').modal('show');
}

$('#dismissRequestForm').on('submit', function(e) {
    e.preventDefault();

    const reason = $('#dismiss_reason').val();

    if (reason.length < 10) {
        alert('Please provide a detailed reason (minimum 10 characters)');
        return;
    }

    $.ajax({
        url: wbUrl(`/lab-workbench/lab-service-requests/${dismissRequestId}/dismiss`),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            reason: reason
        },
        success: function(response) {
            $('#dismissReasonModal').modal('hide');
            alert(response.message);

            // Reload patient data
            if (currentPatient) {
                loadPatient(currentPatient);
            }
        },
        error: function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Failed to dismiss request'));
        }
    });
});













// ============================================
// ENHANCEMENT FUNCTIONS
// ============================================

// Show vital tooltip with details
function showVitalTooltip(event, vitalType, value, normalRange) {
    const tooltip = vitalTooltip;
    const $target = $(event.currentTarget);
    const offset = $target.offset();

    let deviation = '';
    let status = 'Normal';

    // Calculate deviation based on vital type
    if (vitalType === 'temperature' && value !== 'N/A') {
        const temp = parseFloat(value);
        const idealTemp = 37.0;
        const diff = Math.abs(temp - idealTemp);
        deviation = temp> idealTemp ? `+${diff.toFixed(1)}┬░C above ideal` : `-${diff.toFixed(1)}┬░C below ideal`;
        status = (temp>= 36.1 && temp <= 38.0) ? 'Normal' : 'Abnormal';
    } else if (vitalType === 'pulse' && value !== 'N/A') {
        const pulse = parseInt(value);
        const idealPulse = 80;
        const diff = Math.abs(pulse - idealPulse);
        deviation = pulse> idealPulse ? `+${diff} bpm above ideal` : `-${diff} bpm below ideal`;
        status = (pulse>= 60 && pulse <= 100) ? 'Normal' : 'Abnormal';
    } else if (vitalType === 'bp' && value !== 'N/A' && value.includes('/')) {
        const [sys, dia] = value.split('/').map(v => parseInt(v));
        status = (sys>= 90 && sys <= 140 && dia>= 60 && dia <= 90) ? 'Normal' : 'Abnormal';
        deviation = sys> 140 ? 'High BP' : sys < 90 ? 'Low BP' : 'Optimal';
    }

    const content = `
        <div style="font-weight: 600; margin-bottom: 0.5rem;">${vitalType.toUpperCase()}</div>
        <div><strong>Value:</strong> ${value}</div>
        <div><strong>Normal Range:</strong> ${normalRange}</div>
        <div><strong>Status:</strong> <span style="color: ${status === 'Normal' ? '#28a745' : '#dc3545'}">${status}</span></div>
        ${deviation ? `<div><strong>Deviation:</strong> ${deviation}</div>` : ''}
    `;

    tooltip.html(content);
    tooltip.css({
        top: offset.top - tooltip.outerHeight() - 10,
        left: offset.left + ($target.outerWidth() / 2) - (tooltip.outerWidth() / 2)
    });
    tooltip.addClass('active');
}

// Check for drug allergies
function checkForAllergies(medications, patientAllergies) {
    if (!patientAllergies) {
        return [];
    }

    // Normalize allergies to array format
    let allergiesArray = [];

    if (typeof patientAllergies === 'string') {
        // Handle comma-separated string
        allergiesArray = patientAllergies.split(',').map(a => a.trim()).filter(a => a.length> 0);
    } else if (Array.isArray(patientAllergies)) {
        // Handle array (could be array of strings or array of objects)
        allergiesArray = patientAllergies.map(a => {
            if (typeof a === 'string') return a.trim();
            if (typeof a === 'object' && a !== null) return (a.name || a.allergy || a.allergen || '').trim();
            return '';
        }).filter(a => a.length> 0);
    } else if (typeof patientAllergies === 'object' && patientAllergies !== null) {
        // Handle single object or object with values
        if (patientAllergies.name || patientAllergies.allergy || patientAllergies.allergen) {
            allergiesArray = [(patientAllergies.name || patientAllergies.allergy || patientAllergies.allergen).trim()];
        } else {
            // Try to extract values from object
            allergiesArray = Object.values(patientAllergies).map(a => {
                if (typeof a === 'string') return a.trim();
                if (typeof a === 'object' && a !== null) return (a.name || a.allergy || a.allergen || '').trim();
                return '';
            }).filter(a => a.length> 0);
        }
    }

    if (allergiesArray.length === 0) {
        return [];
    }

    const alerts = [];
    medications.forEach(med => {
        const drugName = (med.drug_name || med.product_name || '').toLowerCase();
        allergiesArray.forEach(allergy => {
            if (drugName.includes(allergy.toLowerCase())) {
                alerts.push({
                    medication: med.drug_name || med.product_name,
                    allergy: allergy
                });
            }
        });
    });

    return alerts;
}

// Display allergy alert banner
function displayAllergyAlert(alerts) {
    if (alerts.length === 0) return '';

    const allergyList = alerts.map(alert =>
        `<strong>${alert.medication}</strong> (Allergic to: ${alert.allergy})`
    ).join('<br>');

    return `
        <div class="allergy-alert">
            <div class="allergy-alert-icon">⚠️</div>
            <div>
                <strong>ALLERGY WARNING!</strong><br>
                ${allergyList}
            </div>
        </div>
    `;
}

// Animate refresh button
function animateRefresh(buttonElement) {
    const $btn = $(buttonElement);
    $btn.addClass('refreshing');
    setTimeout(() => $btn.removeClass('refreshing'), 600);
}

// ==========================================
// REPORTS VIEW FUNCTIONS
// ==========================================
// NOTE: The lab-specific reports functionality has been disabled for nursing workbench.
// Nursing reports should use shift-summary and handover routes instead.

function showReports() {
    // Temporarily show message that reports are being redesigned for nursing
    toastr.info('Nursing reports feature is being configured. Please use Shift Summary and Handover reports from the left panel.');
    return;
}

function hideReports() {
    $('#reports-view').removeClass('active');

    // Show appropriate view based on patient selection state
    if (currentPatient) {
        $('#patient-header').addClass('active');
        $('#workspace-content').show().addClass('active');
    } else {
        $('#empty-state').show();
    }

    // On mobile, go back to search pane
    if (window.innerWidth < 768) {
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    }
}

function setDefaultDateFilters() {
    // Set date filters to current month
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    // Format as YYYY-MM-DD
    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    $('#report-date-from').val(formatDate(firstDay));
    $('#report-date-to').val(formatDate(lastDay));
}

/* LAB-SPECIFIC REPORT FILTER FUNCTIONS - DISABLED FOR NURSING WORKBENCH
function loadFilterOptions() {
    // Load doctors
    $.ajax({
        url: wbRoute('lab.filterDoctors', '/lab/filterDoctors'),
        method: 'GET',
        success: function(doctors) {
            let options = '<option value="">All Doctors</option>';
            doctors.forEach(function(doctor) {
                options += `<option value="${doctor.id}">${doctor.name}</option>`;
            });
            $('#report-doctor-filter').html(options);
        },
        error: function(xhr) {
            console.error('Failed to load doctors:', xhr);
        }
    });

    // Load HMOs with optgroups
    $.ajax({
        url: wbRoute('lab.filterHmos', '/lab/filterHmos'),
        method: 'GET',
        success: function(hmoGroups) {
            let options = '<option value="">All HMOs</option>';
            Object.keys(hmoGroups).forEach(function(schemeName) {
                options += `<optgroup label="${schemeName}">`;
                hmoGroups[schemeName].forEach(function(hmo) {
                    options += `<option value="${hmo.id}">${hmo.name}</option>`;
                });
                options += '</optgroup>';
            });
            $('#report-hmo-filter').html(options);
        },
        error: function(xhr) {
            console.error('Failed to load HMOs:', xhr);
        }
    });

    // Load services
    $.ajax({
        url: wbRoute('lab.filterServices', '/lab/filterServices'),
        method: 'GET',
        success: function(services) {
            let options = '<option value="">All Services</option>';
            services.forEach(function(service) {
                options += `<option value="${service.id}">${service.name}</option>`;
            });
            $('#report-service-filter').html(options);
        },
        error: function(xhr) {
            console.error('Failed to load services:', xhr);
        }
    });
}
*/

/* REPLACED BY NEW IMPLEMENTATION
function loadReportsStatistics(filters = {}) {
    // If no filters provided, use current form values
    if (Object.keys(filters).length === 0) {
        filters = {
            date_from: $('#report-date-from').val(),
            date_to: $('#report-date-to').val(),
            status: $('#report-status-filter').val(),
            service_id: $('#report-service-filter').val(),
            doctor_id: $('#report-doctor-filter').val(),
            hmo_id: $('#report-hmo-filter').val(),
            patient_search: $('#report-patient-search').val()
        };
    }

    $.ajax({
        url: wbRoute('lab.statistics', '/lab/statistics'),
        method: 'GET',
        data: filters,
        success: function(data) {
            // Update summary cards
            $('#stat-total-requests').text(data.summary.total_requests);
            $('#stat-completed').text(data.summary.completed);
            $('#stat-pending').text(data.summary.pending);
            $('#stat-avg-tat').text(data.summary.avg_tat + 'h');

            // Store data for charts
            window.reportsData = data;

            // Update charts if they exist
            if (window.statusChart) {
                updateStatusChart(data.by_status);
            }
            if (window.trendsChart) {
                updateTrendsChart(data.monthly_trends);
            }

            // Update top services
            if (data.top_services && data.top_services.length> 0) {
                let servicesHtml = '<ul class="list-group list-group-flush">';
                data.top_services.forEach(function(service, index) {
                    const percentage = data.summary.total_requests> 0 ? Math.round((service.count / data.summary.total_requests) * 100) : 0;
                    servicesHtml += `<li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div style="flex: 1; min-width: 0; margin-right: 15px;">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-truncate font-weight-bold" title="${service.service}">${index + 1}. ${service.service}</span>
                                <span class="badge badge-primary badge-pill">${service.count}</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-info" role="progressbar" style="width: ${percentage}%" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        <small class="text-muted" style="min-width: 35px; text-align: right;">${percentage}%</small>
                    </li>`;
                });
                servicesHtml += '</ul>';
                $('#top-services-list').html(servicesHtml);
            } else {
                $('#top-services-list').html('<p class="text-muted">No data available</p>');
            }
        },
        error: function(xhr) {
            console.error('Failed to load statistics:', xhr);
            toastr.error('Failed to load statistics');
        }
    });
}
*/

/* LAB-SPECIFIC REPORTS DATATABLE - DISABLED FOR NURSING WORKBENCH
function initializeReportsDataTable() {
    if (window.reportsDataTable) {
        window.reportsDataTable.destroy();
    }

    window.reportsDataTable = $('#reports-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbRoute('lab.reports', '/lab/reports'),
            data: function(d) {
                // Add filter values to request
                d.date_from = $('#report-date-from').val();
                d.date_to = $('#report-date-to').val();
                d.status = $('#report-status-filter').val();
                d.service_id = $('#report-service-filter').val();
                d.doctor_id = $('#report-doctor-filter').val();
                d.hmo_id = $('#report-hmo-filter').val();
                d.patient_search = $('#report-patient-search').val();

                // Debug: Log what we're sending
                console.log('DataTable AJAX params:', {
                    date_from: d.date_from,
                    date_to: d.date_to,
                    status: d.status,
                    service_id: d.service_id,
                    doctor_id: d.doctor_id,
                    hmo_id: d.hmo_id,
                    patient_search: d.patient_search
                });
            }
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'file_no', name: 'patient.file_no' },
            { data: 'patient_name', name: 'patient_name', orderable: false },
            { data: 'service_name', name: 'service.service_name' },
            { data: 'doctor_name', name: 'doctor_name', orderable: false },
            { data: 'hmo_name', name: 'hmo_name', orderable: false },
            { data: 'status_badge', name: 'status', orderable: false },
            { data: 'tat', name: 'tat', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: false,
        scrollX: true
    });
}
*/

function initializeReportsCharts() {
    // TODO: Implement Chart.js charts
    // - Status breakdown (bar chart)
    // - Monthly trends (line chart)
    console.log('Charts will be implemented with Chart.js');
}

function updateStatusChart(data) {
    // TODO: Update status chart with new data
}

function updateTrendsChart(data) {
    // TODO: Update trends chart with new data
}

function renderQueueCard(data) {
    // Status badges
    const statusBadges = getStatusBadges(data);

    // Patient meta
    const patientMeta = `
        <div class="queue-card-patient-meta">
            <div class="queue-card-patient-meta-item">
                <i class="mdi mdi-account"></i>
                <span>${data.age} • ${data.gender}</span>
            </div>
            <div class="queue-card-patient-meta-item">
                <i class="mdi mdi-card-account-details"></i>
                <span>${data.file_no}</span>
            </div>
            <div class="queue-card-patient-meta-item">
                <i class="mdi mdi-hospital-building"></i>
                <span>${data.hmo}</span>
            </div>
        </div>
    `;

    // Note section
    const noteSection = data.note ? `
        <div class="queue-card-note">
            <div class="queue-card-note-label"><i class="mdi mdi-note-text"></i> Request Note</div>
            <div>${data.note}</div>
        </div>
    ` : '';

    // Attachments
    let attachmentsSection = '';
    if (data.attachments && data.attachments.length> 0) {
        const attachmentLinks = data.attachments.map(att => {
            const icon = getFileIcon(att.type);
            return `<a href="/storage/${att.path}" target="_blank" class="queue-card-attachment">
                ${icon} ${att.name}
            </a>`;
        }).join('');
        attachmentsSection = `
            <div class="queue-card-attachments">
                <strong><i class="mdi mdi-paperclip"></i> Attachments:</strong>
                ${attachmentLinks}
            </div>
        `;
    }

    // Result section
    const resultSection = data.result ? `
        <div class="queue-card-note" style="background: #f0f9ff; border-color: #0ea5e9;">
            <div class="queue-card-note-label" style="color: #0ea5e9;"><i class="mdi mdi-flask"></i> Result</div>
            <div>${data.result}</div>
        </div>
    ` : '';

    return `
        <div class="queue-card" data-patient-id="${data.patient_id}">
            <div class="queue-card-header">
                <div class="queue-card-patient">
                    <div class="queue-card-patient-name">${data.patient_name}</div>
                    ${patientMeta}
                </div>
                <div class="queue-card-service">${data.service_name}</div>
            </div>
            <div class="queue-card-body">
                <div class="queue-card-status-row">
                    ${statusBadges}
                </div>
                ${noteSection}
                ${resultSection}
                ${attachmentsSection}
            </div>
            <div class="queue-card-actions">
                <button class="btn btn-primary btn-select-patient-from-queue" data-patient-id="${data.patient_id}">
                    <i class="mdi mdi-account-arrow-right"></i> Select Patient
                </button>
            </div>
        </div>
    `;
}

function getStatusBadges(data) {
    let badges = '';

    // Requested
    badges += `
        <div class="queue-card-status-item completed">
            <div class="queue-card-status-label"><i class="mdi mdi-calendar-check"></i> Requested</div>
            <div class="queue-card-status-value">${data.requested_by}<br><small>${data.requested_at}</small></div>
        </div>
    `;

    // Billing
    if (data.billed_by && data.billed_at) {
        badges += `
            <div class="queue-card-status-item completed">
                <div class="queue-card-status-label"><i class="mdi mdi-cash-register"></i> Billed</div>
                <div class="queue-card-status-value">${data.billed_by}<br><small>${data.billed_at}</small></div>
            </div>
        `;
    } else {
        badges += `
            <div class="queue-card-status-item pending">
                <div class="queue-card-status-label"><i class="mdi mdi-cash-register"></i> Billing</div>
                <div class="queue-card-status-value">Awaiting billing</div>
            </div>
        `;
    }

    // Sample
    if (data.sample_taken_by && data.sample_taken_at) {
        badges += `
            <div class="queue-card-status-item completed">
                <div class="queue-card-status-label"><i class="mdi mdi-test-tube"></i> Sample Taken</div>
                <div class="queue-card-status-value">${data.sample_taken_by}<br><small>${data.sample_taken_at}</small></div>
            </div>
        `;
    } else if (data.status>= 2) {
        badges += `
            <div class="queue-card-status-item pending">
                <div class="queue-card-status-label"><i class="mdi mdi-test-tube"></i> Sample</div>
                <div class="queue-card-status-value">Awaiting sample collection</div>
            </div>
        `;
    }

    // Result
    if (data.result_by && data.result_at) {
        badges += `
            <div class="queue-card-status-item completed">
                <div class="queue-card-status-label"><i class="mdi mdi-flask"></i> Result</div>
                <div class="queue-card-status-value">${data.result_by}<br><small>${data.result_at}</small></div>
            </div>
        `;
    } else if (data.status>= 3) {
        badges += `
            <div class="queue-card-status-item pending">
                <div class="queue-card-status-label"><i class="mdi mdi-flask"></i> Result</div>
                <div class="queue-card-status-value">Awaiting result entry</div>
            </div>
        `;
    }

    return badges;
}

function getFileIcon(extension) {
    const icons = {
        'pdf': '<i class="mdi mdi-file-pdf"></i>',
        'doc': '<i class="mdi mdi-file-word"></i>',
        'docx': '<i class="mdi mdi-file-word"></i>',
        'jpg': '<i class="mdi mdi-file-image"></i>',
        'jpeg': '<i class="mdi mdi-file-image"></i>',
        'png': '<i class="mdi mdi-file-image"></i>',
    };
    return icons[extension] || '<i class="mdi mdi-file"></i>';
}

// Handle patient selection from queue
$(document).on('click', '.btn-select-patient-from-queue', function() {
    const patientId = $(this).data('patient-id');
    loadPatient(patientId);
    hideQueue();
});

// ==========================================
// WARD DASHBOARD EVENT HANDLERS
// ==========================================

// Open ward dashboard view
$('#btn-ward-dashboard').on('click', function() {
    showWardDashboard();
});

// Close ward dashboard view
$('#btn-close-ward-dashboard').on('click', function() {
    hideWardDashboard();
});

function showWardDashboard() {
    // Hide all other views first to prevent stacking
    hideAllViews();

    // Show ward dashboard
    $('#ward-dashboard-view').addClass('active');

    // Initialize ward dashboard
    if (typeof WardDashboard !== 'undefined') {
        WardDashboard.init();
    }

    // On mobile, show main workspace
    if (window.innerWidth < 768) {
        $('#main-workspace').addClass('active');
        $('#left-panel').addClass('hidden');
    }
}

function hideWardDashboard() {
    $('#ward-dashboard-view').removeClass('active');

    // Show appropriate view based on patient selection state
    if (currentPatient) {
        $('#patient-header').addClass('active');
        $('#workspace-content').show().addClass('active');
    } else {
        $('#empty-state').show();
    }

    // On mobile, go back to search pane
    if (window.innerWidth < 768) {
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    }
}

// ==========================================
// QUICK ACTION HANDLERS
// ==========================================

// Quick Vitals button - enabled when patient is selected
function updateQuickActionsState() {
    if (currentPatient) {
        $('#btn-quick-vitals').prop('disabled', false).attr('title', 'Record vitals for ' + (currentPatientData?.name || 'patient'));
    } else {
        $('#btn-quick-vitals').prop('disabled', true).attr('title', 'Select a patient first');
    }
}

// Quick Vitals button handler
$('#btn-quick-vitals').on('click', function() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }
    // Switch to vitals tab
    $('.workspace-tab[data-tab="vitals"]').click();
});

// Nursing Reports button handler
$('#btn-nursing-reports').on('click', function() {
    NursingReports.show();
});

// ==========================================
// NURSING REPORTS MODULE
// ==========================================
const NursingReports = (function() {
    // State
    let charts = {};
    let dataTables = {};
    let isInitialized = false;
    let currentFilters = {
        date_range: '7days',
        date_from: null,
        date_to: null,
        ward_id: null,
        nurse_id: null,
        shift_type: null
    };

    // Base URL
    const BASE_URL = '/nursing-workbench/reports';

    // Initialize
    function init() {
        if (isInitialized) return;

        bindEvents();
        loadNurseOptions();
        loadWardOptions();
        setDefaultDateRange();
        isInitialized = true;
    }

    // Bind events
    function bindEvents() {
        // Close button
        $('#btn-close-nursing-reports').on('click', hide);

        // Apply filters button
        $('#nr-apply-filters').on('click', applyFilters);

        // Reset filters button
        $('#nr-reset-filters').on('click', clearFilters);

        // Date range select
        $('#nr-date-range').on('change', function() {
            const val = $(this).val();
            if (val === 'custom') {
                $('#nr-custom-dates').show();
            } else {
                $('#nr-custom-dates').hide();
                currentFilters.date_range = val;
                currentFilters.date_from = null;
                currentFilters.date_to = null;
            }
        });

        // Tab change - load data for the tab
        $('#nursingReportsTabs a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            const target = $(e.target).attr('href');
            loadTabData(target);
        });
    }

    // Show reports view
    function show() {
        init();
        hideAllViews();
        $('#nursing-reports-view').show();

        // Load activity summary (first tab)
        loadTabData('#nr-activity');
    }

    // Hide reports view
    function hide() {
        $('#nursing-reports-view').hide();
    }

    // Set default date range (last 7 days)
    function setDefaultDateRange() {
        const today = new Date();
        const weekAgo = new Date(today);
        weekAgo.setDate(today.getDate() - 6);

        $('#nr-date-from').val(formatDateInput(weekAgo));
        $('#nr-date-to').val(formatDateInput(today));
    }

    // Format date for input
    function formatDateInput(date) {
        return date.toISOString().split('T')[0];
    }

    // Load nurse options
    function loadNurseOptions() {
        $.get(BASE_URL + '/nurses', function(response) {
            if (response.success && response.nurses) {
                const $select = $('#nr-nurse-filter');
                $select.find('option:not(:first)').remove();
                response.nurses.forEach(nurse => {
                    $select.append(`<option value="${nurse.id}">${nurse.name}</option>`);
                });
            }
        });
    }

    // Load ward options
    function loadWardOptions() {
        $.get('/nursing-workbench/wards', function(response) {
            if (response.wards) {
                const $select = $('#nr-ward-filter');
                $select.find('option:not(:first)').remove();
                response.wards.forEach(ward => {
                    $select.append(`<option value="${ward.id}">${ward.name}</option>`);
                });
            }
        });
    }

    // Get current filters
    function getFilters() {
        return {
            date_range: $('#nr-date-range').val(),
            date_from: $('#nr-date-from').val(),
            date_to: $('#nr-date-to').val(),
            ward_id: $('#nr-ward-filter').val() || null,
            nurse_id: $('#nr-nurse-filter').val() || null,
            shift_type: $('#nr-shift-filter').val() || null
        };
    }

    // Apply filters
    function applyFilters() {
        currentFilters = getFilters();
        const activeTab = $('#nursingReportsTabs .active').attr('href');
        loadTabData(activeTab, true);
    }

    // Clear filters
    function clearFilters() {
        $('#nr-date-range').val('7days');
        $('#nr-custom-dates').hide();
        $('#nr-ward-filter').val('');
        $('#nr-nurse-filter').val('');
        $('#nr-shift-filter').val('');
        setDefaultDateRange();
        currentFilters = {
            date_range: '7days',
            date_from: null,
            date_to: null,
            ward_id: null,
            nurse_id: null,
            shift_type: null
        };
        applyFilters();
    }

    // Load data for specific tab
    function loadTabData(tabId, forceReload = false) {
        const filters = getFilters();

        switch (tabId) {
            case '#nr-activity':
                loadActivitySummary(filters);
                break;
            case '#nr-vitals':
                loadVitalsReport(filters, forceReload);
                break;
            case '#nr-medications':
                loadMedicationsReport(filters, forceReload);
                break;
            case '#nr-injections':
                loadInjectionsReport(filters, forceReload);
                break;
            case '#nr-io':
                loadIOReport(filters, forceReload);
                break;
            case '#nr-notes':
                loadNotesReport(filters, forceReload);
                break;
            case '#nr-shifts':
                loadShiftsReport(filters, forceReload);
                break;
            case '#nr-occupancy':
                loadOccupancyReport(filters);
                break;
        }
    }

    // Load Activity Summary
    function loadActivitySummary(filters) {
        showLoading('#nr-activity');

        $.get(BASE_URL + '/activity-summary', filters, function(response) {
            if (response.success) {
                // Update stats - matching HTML element IDs
                $('#nr-stat-patients').text(response.stats.patients_served || 0);
                $('#nr-stat-vitals').text(response.stats.vitals_recorded || 0);
                $('#nr-stat-medications').text(response.stats.medications_given || 0);
                $('#nr-stat-injections').text(response.stats.injections || 0);
                $('#nr-stat-immunizations').text(response.stats.immunizations || 0);
                $('#nr-stat-notes').text(response.stats.notes_written || 0);
                $('#nr-stat-handovers').text(response.stats.handovers || 0);
                $('#nr-stat-shifts').text(response.stats.shifts_completed || 0);

                // Render charts
                renderActivityTrendChart(response.trend);
                renderDistributionChart(response.distribution);
                renderPeakHoursChart(response.peak_hours);
                renderTopPerformersTable(response.top_performers);
            }
            hideLoading('#nr-activity');
        }).fail(function() {
            hideLoading('#nr-activity');
            toastr.error('Failed to load activity summary');
        });
    }

    // Render Activity Trend Chart
    function renderActivityTrendChart(data) {
        const ctx = document.getElementById('nr-activity-trend-chart');
        if (!ctx) return;

        if (charts.activityTrend) {
            charts.activityTrend.destroy();
        }

        charts.activityTrend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(d => d.date),
                datasets: [
                    {
                        label: 'Vitals',
                        data: data.map(d => d.vitals),
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Medications',
                        data: data.map(d => d.medications),
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Injections',
                        data: data.map(d => d.injections),
                        borderColor: '#17a2b8',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Notes',
                        data: data.map(d => d.notes),
                        borderColor: '#6c757d',
                        backgroundColor: 'rgba(108, 117, 125, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Render Distribution Chart
    function renderDistributionChart(data) {
        const ctx = document.getElementById('nr-activity-distribution-chart');
        if (!ctx) return;

        if (charts.distribution) {
            charts.distribution.destroy();
        }

        charts.distribution = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(d => d.label),
                datasets: [{
                    data: data.map(d => d.value),
                    backgroundColor: data.map(d => d.color)
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // Render Peak Hours Chart
    function renderPeakHoursChart(data) {
        const ctx = document.getElementById('nr-peak-hours-chart');
        if (!ctx) return;

        if (charts.peakHours) {
            charts.peakHours.destroy();
        }

        charts.peakHours = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.map(d => d.hour),
                datasets: [{
                    label: 'Activities',
                    data: data.map(d => d.count),
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderColor: '#667eea',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });
    }

    // Render Top Performers Table
    function renderTopPerformersTable(data) {
        const $tbody = $('#nr-top-performers-table tbody');
        $tbody.empty();

        if (!data || data.length === 0) {
            $tbody.append('<tr><td colspan="4" class="text-center text-muted">No data available</td></tr>');
            return;
        }

        data.forEach((performer, index) => {
            $tbody.append(`
                <tr>
                    <td><span class="badge badge-${index < 3 ? 'primary' : 'secondary'}">#${index + 1}</span> ${performer.nurse}</td>
                    <td>${performer.actions}</td>
                    <td>${performer.patients}</td>
                    <td>${performer.shifts}</td>
                </tr>
            `);
        });
    }

    // Load Vitals Report
    function loadVitalsReport(filters, forceReload) {
        // Load stats
        $.get(BASE_URL + '/vitals', filters, function(response) {
            if (response.success) {
                $('#nr-vitals-total').text(response.stats.total || 0);
                $('#nr-vitals-abnormal').text(response.stats.abnormal || 0);
                $('#nr-vitals-fever').text(response.stats.fever || 0);
                $('#nr-vitals-hypertension').text(response.stats.hypertension || 0);
            }
        });

        // Initialize or reload DataTable
        if (!dataTables.vitals || forceReload) {
            if (dataTables.vitals) {
                dataTables.vitals.destroy();
            }
            dataTables.vitals = $('#nr-vitals-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: BASE_URL + '/vitals',
                    data: function(d) {
                        return Object.assign(d, getFilters());
                    }
                },
                columns: [
                    { data: 'datetime', title: 'Date/Time' },
                    { data: 'patient_name', title: 'Patient' },
                    { data: 'file_no', title: 'File No' },
                    { data: 'temp', title: 'Temp (┬░C)' },
                    { data: 'blood_pressure', title: 'BP' },
                    { data: 'heart_rate', title: 'Pulse' },
                    { data: 'spo2', title: 'SpO2' },
                    { data: 'recorded_by', title: 'Recorded By' },
                    {
                        data: 'status',
                        title: 'Status',
                        render: function(data) {
                            const colors = { normal: 'success', warning: 'warning', critical: 'danger' };
                            return `<span class="badge badge-${colors[data] || 'secondary'}">${data}</span>`;
                        }
                    }
                ],
                order: [[0, 'desc']],
                pageLength: 15,
                dom: 'Bfrtip',
                buttons: ['excel', 'pdf', 'print']
            });
        } else {
            dataTables.vitals.ajax.reload();
        }
    }

    // Load Medications Report
    function loadMedicationsReport(filters, forceReload) {
        // Load stats
        $.get(BASE_URL + '/medications', filters, function(response) {
            if (response.success) {
                $('#nr-meds-total').text(response.stats.total || 0);
                $('#nr-meds-ontime').text(response.stats.ontime_rate || '0%');
                $('#nr-meds-late').text(response.stats.late || 0);
                $('#nr-meds-missed').text(response.stats.missed || 0);
            }
        });

        // Initialize or reload DataTable
        if (!dataTables.medications || forceReload) {
            if (dataTables.medications) {
                dataTables.medications.destroy();
            }
            dataTables.medications = $('#nr-medications-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: BASE_URL + '/medications',
                    data: function(d) {
                        return Object.assign(d, getFilters());
                    }
                },
                columns: [
                    { data: 'datetime', title: 'Administered At' },
                    { data: 'patient_name', title: 'Patient' },
                    { data: 'medication', title: 'Medication' },
                    { data: 'dose', title: 'Dose' },
                    { data: 'route', title: 'Route' },
                    { data: 'scheduled_time', title: 'Scheduled' },
                    { data: 'administered_by_name', title: 'Given By' },
                    {
                        data: 'status',
                        title: 'Status',
                        render: function(data) {
                            return `<span class="badge badge-${data === 'ontime' ? 'success' : 'warning'}">${data === 'ontime' ? 'On Time' : 'Late'}</span>`;
                        }
                    }
                ],
                order: [[0, 'desc']],
                pageLength: 15
            });
        } else {
            dataTables.medications.ajax.reload();
        }
    }

    // Load Injections Report
    function loadInjectionsReport(filters, forceReload) {
        // Stats
        $.get(BASE_URL + '/injections', filters, function(response) {
            if (response.success) {
                $('#nr-injections-total').text(response.total || 0);
            }
        });

        $.get(BASE_URL + '/immunizations', filters, function(response) {
            if (response.success) {
                $('#nr-immunizations-total').text(response.total || 0);
            }
        });

        // Injections DataTable
        if (!dataTables.injections || forceReload) {
            if (dataTables.injections) {
                dataTables.injections.destroy();
            }
            dataTables.injections = $('#nr-injections-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: BASE_URL + '/injections',
                    data: function(d) {
                        return Object.assign(d, getFilters());
                    }
                },
                columns: [
                    { data: 'datetime', title: 'Date/Time' },
                    { data: 'patient_name', title: 'Patient' },
                    { data: 'drug_name', title: 'Drug' },
                    { data: 'dose', title: 'Dose' },
                    { data: 'route', title: 'Route' },
                    { data: 'site', title: 'Site' },
                    { data: 'administered_by_name', title: 'Given By' }
                ],
                order: [[0, 'desc']],
                pageLength: 15
            });
        } else {
            dataTables.injections.ajax.reload();
        }

        // Immunizations DataTable
        if (!dataTables.immunizations || forceReload) {
            if (dataTables.immunizations) {
                dataTables.immunizations.destroy();
            }
            dataTables.immunizations = $('#nr-immunizations-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: BASE_URL + '/immunizations',
                    data: function(d) {
                        return Object.assign(d, getFilters());
                    }
                },
                columns: [
                    { data: 'datetime', title: 'Date/Time' },
                    { data: 'patient_name', title: 'Patient' },
                    { data: 'patient_age', title: 'Age' },
                    { data: 'vaccine', title: 'Vaccine' },
                    { data: 'dose_number', title: 'Dose #' },
                    { data: 'batch_no', title: 'Batch No' },
                    { data: 'administered_by_name', title: 'Given By' }
                ],
                order: [[0, 'desc']],
                pageLength: 15
            });
        } else {
            dataTables.immunizations.ajax.reload();
        }
    }

    // Load I/O Report
    function loadIOReport(filters, forceReload) {
        // Stats
        $.get(BASE_URL + '/io', filters, function(response) {
            if (response.success) {
                $('#nr-io-records').text(response.stats.records || 0);
                $('#nr-io-positive').text(response.stats.positive || 0);
                $('#nr-io-negative').text(response.stats.negative || 0);
                $('#nr-io-critical').text(response.stats.critical || 0);
            }
        });

        // DataTable
        if (!dataTables.io || forceReload) {
            if (dataTables.io) {
                dataTables.io.destroy();
            }
            dataTables.io = $('#nr-io-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: BASE_URL + '/io',
                    data: function(d) {
                        return Object.assign(d, getFilters());
                    }
                },
                columns: [
                    { data: 'date_formatted', title: 'Date' },
                    { data: 'patient_name', title: 'Patient' },
                    { data: 'ward_bed', title: 'Ward / Bed' },
                    { data: 'total_intake', title: 'Total Intake' },
                    { data: 'total_output', title: 'Total Output' },
                    {
                        data: 'balance',
                        title: 'Balance',
                        render: function(data, type, row) {
                            const cls = row.status === 'critical' ? 'text-danger' : (row.status === 'warning' ? 'text-warning' : 'text-success');
                            return `<span class="${cls} font-weight-bold">${data}</span>`;
                        }
                    },
                    { data: 'recorded_by', title: 'Recorded By' }
                ],
                order: [[0, 'desc']],
                pageLength: 15
            });
        } else {
            dataTables.io.ajax.reload();
        }
    }

    // Load Notes Report
    function loadNotesReport(filters, forceReload) {
        // Stats
        $.get(BASE_URL + '/notes', filters, function(response) {
            if (response.success) {
                $('#nr-notes-total').text(response.stats.total || 0);
                $('#nr-notes-critical').text(response.stats.critical || 0);
                $('#nr-notes-patients').text(response.stats.patients || 0);
            }
        });

        // DataTable
        if (!dataTables.notes || forceReload) {
            if (dataTables.notes) {
                dataTables.notes.destroy();
            }
            dataTables.notes = $('#nr-notes-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: BASE_URL + '/notes',
                    data: function(d) {
                        return Object.assign(d, getFilters());
                    }
                },
                columns: [
                    { data: 'datetime', title: 'Date/Time' },
                    { data: 'patient_name', title: 'Patient' },
                    { data: 'note_type', title: 'Type' },
                    { data: 'summary', title: 'Summary' },
                    { data: 'written_by', title: 'Written By' },
                    {
                        data: 'status',
                        title: 'Status',
                        render: function(data) {
                            return `<span class="badge badge-${data === 'completed' ? 'success' : 'warning'}">${data}</span>`;
                        }
                    }
                ],
                order: [[0, 'desc']],
                pageLength: 15
            });
        } else {
            dataTables.notes.ajax.reload();
        }
    }

    // Load Shifts Report
    function loadShiftsReport(filters, forceReload) {
        // Stats
        $.get(BASE_URL + '/shifts', filters, function(response) {
            if (response.success) {
                $('#nr-shifts-total').text(response.stats.total || 0);
                $('#nr-shifts-avg-duration').text(response.stats.avg_duration || '0h');
                $('#nr-shifts-handovers').text(response.stats.handover_rate || '0%');
                $('#nr-shifts-overdue').text(response.stats.overdue || 0);
            }
        });

        // DataTable
        if (!dataTables.shifts || forceReload) {
            if (dataTables.shifts) {
                dataTables.shifts.destroy();
            }
            dataTables.shifts = $('#nr-shifts-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: BASE_URL + '/shifts',
                    data: function(d) {
                        return Object.assign(d, getFilters());
                    }
                },
                columns: [
                    { data: 'date', title: 'Date' },
                    { data: 'nurse_name', title: 'Nurse' },
                    { data: 'shift_type_label', title: 'Shift' },
                    { data: 'ward_name', title: 'Ward' },
                    { data: 'start_time', title: 'Start' },
                    { data: 'end_time', title: 'End' },
                    { data: 'duration', title: 'Duration' },
                    { data: 'actions_count', title: 'Actions' },
                    {
                        data: 'handover_status',
                        title: 'Handover',
                        render: function(data) {
                            return `<span class="badge badge-${data === 'Yes' ? 'success' : 'secondary'}">${data}</span>`;
                        }
                    },
                    { data: 'status_label', title: 'Status' }
                ],
                order: [[0, 'desc']],
                pageLength: 15
            });
        } else {
            dataTables.shifts.ajax.reload();
        }
    }

    // Load Occupancy Report
    function loadOccupancyReport(filters) {
        showLoading('#nr-occupancy');

        $.get(BASE_URL + '/occupancy', filters, function(response) {
            if (response.success) {
                // Bed stats
                $('#nr-beds-total').text(response.stats.total_beds || 0);
                $('#nr-beds-occupied').text(response.stats.occupied || 0);
                $('#nr-beds-available').text(response.stats.available || 0);
                $('#nr-beds-maintenance').text(response.stats.maintenance || 0);

                // Admission/Discharge stats
                $('#nr-admissions-today').text(response.admissions.today || 0);
                $('#nr-admissions-period').text(response.admissions.period || 0);
                $('#nr-avg-los').text(response.admissions.avg_los || '0d');
                $('#nr-discharges-today').text(response.discharges.today || 0);
                $('#nr-discharges-period').text(response.discharges.period || 0);
                $('#nr-pending-discharges').text(response.discharges.pending || 0);

                // Render ward table
                renderWardOccupancyTable(response.wards);

                // Render occupancy chart
                renderOccupancyChart(response.stats);
            }
            hideLoading('#nr-occupancy');
        }).fail(function() {
            hideLoading('#nr-occupancy');
            toastr.error('Failed to load occupancy data');
        });
    }

    // Render Ward Occupancy Table
    function renderWardOccupancyTable(data) {
        const $tbody = $('#nr-occupancy-table tbody');
        $tbody.empty();

        if (!data || data.length === 0) {
            $tbody.append('<tr><td colspan="6" class="text-center text-muted">No wards configured</td></tr>');
            return;
        }

        data.forEach(ward => {
            const occupancyPct = parseInt(ward.occupancy_rate);
            const barClass = occupancyPct> 90 ? 'bg-danger' : (occupancyPct> 70 ? 'bg-warning' : 'bg-success');
            $tbody.append(`
                <tr>
                    <td><strong>${ward.ward}</strong></td>
                    <td>${ward.total}</td>
                    <td><span class="text-danger">${ward.occupied}</span></td>
                    <td><span class="text-success">${ward.available}</span></td>
                    <td><span class="text-warning">${ward.maintenance}</span></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="progress flex-grow-1" style="height: 8px;">
                                <div class="progress-bar ${barClass}" style="width: ${ward.occupancy_rate}"></div>
                            </div>
                            <span class="ml-2 font-weight-bold">${ward.occupancy_rate}</span>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    // Render Occupancy Chart
    function renderOccupancyChart(data) {
        const ctx = document.getElementById('bed-occupancy-chart');
        if (!ctx) return;

        if (charts.occupancy) {
            charts.occupancy.destroy();
        }

        charts.occupancy = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Occupied', 'Available', 'Maintenance'],
                datasets: [{
                    data: [data.occupied, data.available, data.maintenance],
                    backgroundColor: ['#dc3545', '#28a745', '#ffc107']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // Show loading state
    function showLoading(container) {
        $(container).find('.card-body').each(function() {
            if (!$(this).find('.loading-overlay').length) {
                $(this).append('<div class="loading-overlay"><div class="spinner-border text-primary"></div></div>');
            }
        });
    }

    // Hide loading state
    function hideLoading(container) {
        $(container).find('.loading-overlay').remove();
    }

    // Public API
    return {
        init: init,
        show: show,
        hide: hide,
        applyFilters: applyFilters,
        clearFilters: clearFilters
    };
})();


// Admission Summary button handler
$('#btn-admission-summary').on('click', function() {
    // Show today's admissions and discharges summary
    showWardDashboard();
    // Focus on admission queue
    setTimeout(function() {
        $('.queue-tab[data-queue="admission"]').click();
    }, 100);
});

// Shift Handover button handler
$('#btn-shift-handover').on('click', function() {
    // Use ShiftManager to show the handovers modal
    if (typeof ShiftManager !== 'undefined' && ShiftManager.showHandoversList) {
        ShiftManager.showHandoversList();
    } else {
        // Fallback - show modal directly
        $('#handoversListModal').modal('show');
    }
});

// Medication Round quick action button handler
$('.quick-action-btn[data-filter="medication-due"]').on('click', function() {
    showQueue('medication-due');
});

// Update medication round badge
function updateMedicationRoundBadge() {
    $.get('/nursing-workbench/queue-counts', function(counts) {
        var medCount = counts.medication_due || 0;
        var $badge = $('#med-round-badge');
        if (medCount> 0) {
            $badge.text(medCount).show();
        } else {
            $badge.hide();
        }
    });
}

// Call on page load
updateMedicationRoundBadge();

// ==========================================
// REPORTS VIEW EVENT HANDLERS
// ==========================================

// Open reports view (btn-view-reports only - btn-nursing-reports has its own handler above)
$('#btn-view-reports').on('click', function() {
    showReports();
});

// Close reports view
$('#btn-close-reports').on('click', function() {
    hideReports();
});

// Reports filter form submission
$('#reports-filter-form').on('submit', function(e) {
    e.preventDefault();

    // Reload statistics with filters
    const filters = {
        date_from: $('#report-date-from').val(),
        date_to: $('#report-date-to').val(),
        status: $('#report-status-filter').val(),
        service_id: $('#report-service-filter').val(),
        doctor_id: $('#report-doctor-filter').val(),
        hmo_id: $('#report-hmo-filter').val(),
        patient_search: $('#report-patient-search').val()
    };

    loadReportsStatistics(filters);

    // Reload DataTable
    if (window.reportsDataTable) {
        window.reportsDataTable.ajax.reload();
    }
});

// Clear reports filters
$('#clear-report-filters').on('click', function() {
    $('#reports-filter-form')[0].reset();
    loadReportsStatistics();
    if (window.reportsDataTable) {
        window.reportsDataTable.ajax.reload();
    }
});

// Export buttons (TODO: Implement with DataTables buttons extension)
$('#export-excel').on('click', function() {
    toastr.info('Excel export will be implemented with DataTables buttons extension');
});

$('#export-pdf').on('click', function() {
    toastr.info('PDF export will be implemented with DataTables buttons extension');
});

$('#print-report').on('click', function() {
    toastr.info('Print functionality will be implemented with DataTables buttons extension');
});

// Show new request button and update quick actions when patient is selected
function updateQuickActions() {
    if (currentPatient) {
        $('#btn-new-request').show();
        // Enable patient-dependent buttons
        $('#btn-quick-vitals').prop('disabled', false).attr('title', 'Record vitals for ' + (currentPatientData?.name || 'patient'));
        $('#btn-clinical-context').prop('disabled', false).attr('title', 'View clinical context for ' + (currentPatientData?.name || 'patient'));
    } else {
        $('#btn-new-request').hide();
        // Disable patient-dependent buttons
        $('#btn-quick-vitals').prop('disabled', true).attr('title', 'Select a patient first');
        $('#btn-clinical-context').prop('disabled', true).attr('title', 'Select a patient first');
    }
}

// New request button handler
$('#btn-new-request').on('click', function() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }
    switchWorkspaceTab('new-request');
    $('#new-request-patient-name').text(currentPatient.name);
});

// ==========================================
// REPORTS & ANALYTICS HELPER FUNCTIONS
// NOTE: Lab-specific reports disabled for nursing workbench
// ==========================================

/* LAB-SPECIFIC STATISTICS - DISABLED FOR NURSING WORKBENCH
function loadReportsStatistics(filters = {}) {
    // Show loading state
    $('#stat-total-requests').text('Loading...');
    $('#stat-completed').text('Loading...');
    $('#stat-pending').text('Loading...');
    $('#stat-avg-tat').text('Loading...');

    $.ajax({
        url: wbRoute('lab.statistics', '/lab/statistics'),
        method: 'GET',
        data: filters,
        success: function(response) {
            // Update Summary Cards
            updateSummaryCards(response.summary);

            // Render Top Services
            renderTopServices(response.top_services, response.summary ? response.summary.total_requests : 0);

            // Render Top Doctors
            renderTopDoctors(response.top_doctors);

            // Initialize/Update Charts
            initializeReportsCharts(response.by_status, response.monthly_trends);
        },
        error: function(xhr) {
            console.error('Error loading statistics:', xhr);
            toastr.error('Failed to load report statistics');
        }
    });
}
*/

function updateSummaryCards(summary) {
    if(!summary) return;

    $('#stat-total-requests').text(summary.total_requests || 0);
    $('#stat-completed').text(summary.completed_requests || 0);
    $('#stat-pending').text(summary.pending_requests || 0);

    // Update Avg TAT
    $('#stat-avg-tat').text((summary.avg_tat || 0) + ' hrs');
}

function renderTopServices(services, totalRequests = 0) {
    const container = $('#top-services-list');

    if (!services || services.length === 0) {
        container.html('<p class="text-muted text-center p-3">No data available for the selected period.</p>');
        return;
    }

    let html = '<ul class="list-group list-group-flush">';

    services.forEach((service, index) => {
        const percentage = totalRequests> 0 ? Math.round((service.count / totalRequests) * 100) : 0;

        html += `<li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <div style="flex: 1; min-width: 0; margin-right: 15px;">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-truncate font-weight-bold" title="${service.name}">${index + 1}. ${service.name}</span>
                    <span class="badge badge-primary badge-pill">${service.count}</span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-info" role="progressbar" style="width: ${percentage}%" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
            <small class="text-muted" style="min-width: 35px; text-align: right;">${percentage}%</small>
        </li>`;
    });

    html += '</ul>';
    container.html(html);
}

function renderTopDoctors(doctors) {
    const container = $('#top-doctors-list');

    if (!doctors || doctors.length === 0) {
        container.html('<p class="text-muted text-center p-3">No data available.</p>');
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-hover table-sm"><thead><tr><th>Doctor Name</th><th class="text-center">Requests</th><th class="text-right">Total Revenue</th></tr></thead><tbody>';

    doctors.forEach(doc => {
        const docName = doc.doctor ? (doc.doctor.firstname + ' ' + doc.doctor.surname) : 'Unknown';
        html += `
            <tr>
                <td><i class="mdi mdi-doctor mr-1"></i> ${docName}</td>
                <td class="text-center"><span class="badge badge-pill badge-info">${doc.count}</span></td>
                <td class="text-right">₦${parseFloat(doc.revenue).toLocaleString()}</td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.html(html);
}

let statusChartInstance = null;
let trendsChartInstance = null;

function initializeReportsCharts(byStatus, monthlyTrends) {
    // 1. Status Chart (Doughnut)
    const statusCtx = document.getElementById('status-chart').getContext('2d');

    // Destroy existing chart if it exists
    if (statusChartInstance) {
        statusChartInstance.destroy();
    }

    const statusLabels = [];
    const statusData = [];
    const statusColors = [];

    // Map status IDs to names and colors
    const statusMap = {
        1: { name: 'Awaiting Billing', color: '#ffc107' },
        2: { name: 'Awaiting Sample', color: '#17a2b8' },
        3: { name: 'Awaiting Results', color: '#007bff' },
        4: { name: 'Completed', color: '#28a745' },
        5: { name: 'Pending Approval', color: '#6f42c1' },
        6: { name: 'Rejected', color: '#dc3545' }
    };

    if (byStatus && byStatus.length> 0) {
        byStatus.forEach(item => {
            const info = statusMap[item.status] || { name: 'Unknown', color: '#6c757d' };
            statusLabels.push(info.name);
            statusData.push(item.count);
            statusColors.push(info.color);
        });
    } else {
        // Empty state
        statusLabels.push('No Data');
        statusData.push(0);
        statusColors.push('#e9ecef');
    }

    statusChartInstance = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: statusColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'right'
            }
        }
    });

    // 2. Monthly Trends Chart (Line)
    const trendsCtx = document.getElementById('trends-chart').getContext('2d');

    if (trendsChartInstance) {
        trendsChartInstance.destroy();
    }

    const trendLabels = [];
    const trendData = [];

    if (monthlyTrends && monthlyTrends.length> 0) {
        monthlyTrends.forEach(item => {
            trendLabels.push(item.month);
            trendData.push(item.count);
        });
    }

    trendsChartInstance = new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Requests',
                data: trendData,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true,
                        precision: 0
                    }
                }]
            }
        }
    });
}

