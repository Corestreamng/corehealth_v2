// ============================================
// ENHANCEMENT FUNCTIONS
// ============================================

// Load user preferences from localStorage
function loadUserPreferences() {
    const clinicalVisible = localStorage.getItem('pharmacyClinicalPanelVisible') === 'true';
    if (clinicalVisible) {
        $('#right-panel').addClass('active');
        $('#toggle-clinical-btn').html('📊 Clinical Context ×');
    }
}

// Create vital tooltip element
function createVitalTooltip() {
    vitalTooltip = $('<div class="vital-tooltip"></div>').appendTo('body');

    // Hide on mouse leave
    $(document).on('mouseleave', '.vital-item', function() {
        vitalTooltip.removeClass('active');
    });
}

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
        deviation = temp> idealTemp ? `+${diff.toFixed(1)}°C above ideal` : `-${diff.toFixed(1)}°C below ideal`;
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

// =============================================
// VIEW MANAGEMENT HELPERS
// =============================================

// Hide all overlapping views - call this before showing any new view
function hideAllViews() {
    $('#empty-state').hide();
    $('#queue-view').removeClass('active').hide();
    $('.queue-item').removeClass('active');
    $('#pharmacy-reports-view').removeClass('active').hide();
    $('#pharmacy-returns-view').removeClass('active').hide();
    $('#pharmacy-return-create-view').removeClass('active').hide();
    $('#pharmacy-damages-view').removeClass('active').hide();
    $('#pharmacy-damage-create-view').removeClass('active').hide();
    $('#pharmacy-stock-reports-view').removeClass('active').hide();
    $('#patient-header').removeClass('active');
    $('#workspace-content').removeClass('active').hide();
}

// =============================================
// QUEUE FUNCTIONALITY
// =============================================

let queueDataTable = null;
let currentQueueFilter = 'all';

function showQueue(filter) {
    currentQueueFilter = filter;

    // Update queue title
    const titles = {
        'all': '🟡 All Unpaid Items',
        'hmo': '🟢 HMO Items',
        'credit': '🟠 Credit Accounts',
    };
    $('#queue-view-title').html(`<i class="mdi mdi-format-list-bulleted"></i> ${titles[filter] || titles['all']}`);

    // Update active state on queue buttons
    $('.queue-item').removeClass('active');
    if (filter !== 'all') {
        $(`.queue-item[data-filter="${filter}"]`).addClass('active');
    }

    // Hide other views, show queue view
    hideAllViews();
    $('#queue-view').show().addClass('active');

    // On mobile, hide search pane and show main workspace
    if (window.innerWidth < 768) {
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    }

    // Initialize or reload DataTable
    initializeQueueDataTable(filter);
}

function hideQueue() {
    $('#queue-view').removeClass('active');
    $('.queue-item').removeClass('active');

    if (currentPatient) {
        // If patient was selected, show their workspace
        $('#patient-header').addClass('active');
        $('#workspace-content').addClass('active');
    } else {
        // Otherwise show empty state
        $('#empty-state').show();
    }

    // On mobile, go back to search pane
    if (window.innerWidth < 768) {
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    }
}

function initializeQueueDataTable(filter) {
    // Destroy existing DataTable if it exists
    if (queueDataTable) {
        queueDataTable.destroy();
    }

    // Initialize DataTable for payment queue
    queueDataTable = $('#queue-datatable').DataTable({
        ajax: {
            url: wbUrl('/pharmacy-workbench/prescription-queue'),
            data: { filter: filter },
            dataSrc: ''
        },
        columns: [
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    var emergencyBadge = row.is_emergency
                        ? '<span class=\"badge bg-danger me-2\"><i class=\"fa fa-bolt\"></i> EMERGENCY</span>'
                        : '';
                    var borderStyle = row.is_emergency
                        ? 'border-left: 4px solid #dc3545; background: #fff8f8;'
                        : '';
                    return `
                        <div class="queue-patient-item" data-patient-id="${row.patient_id}" style="cursor: pointer; padding: 1rem; border-bottom: 1px solid #e9ecef; ${borderStyle}">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                ${emergencyBadge}
                                <div style="font-weight: 600; font-size: 1rem; color: #212529;">${row.patient_name}</div>
                                <span class="badge badge-primary">${row.file_no}</span>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.9rem; color: #6c757d;">
                                <i class="mdi mdi-pill"></i> ${row.prescription_count || 0} prescription(s)
                                ${row.unbilled_count> 0 ? `<span class="badge badge-warning ml-2">${row.unbilled_count} unbilled</span>` : ''}
                                ${row.ready_count> 0 ? `<span class="badge badge-success ml-2">${row.ready_count} ready</span>` : ''}
                                ${row.hmo ? `<br><small><i class="mdi mdi-hospital-building"></i> ${row.hmo}</small>` : ''}
                            </div>
                        </div>
                    `;
                }
            }
        ],
        paging: true,
        pageLength: 10,
        searching: true,
        ordering: false,
        info: true,
        responsive: true,
        language: {
            emptyTable: "No patients in this queue",
            zeroRecords: "No patients found",
            info: "Showing _START_ to _END_ of _TOTAL_ patients",
            infoEmpty: "No patients to show",
            infoFiltered: "(filtered from _MAX_ total patients)"
        }
    });

    // Click handler for patient selection from queue
    $('#queue-datatable').on('click', '.queue-patient-item', function() {
        const patientId = $(this).data('patient-id');
        hideQueue();
        loadPatient(patientId);
    });
}

// ==========================================
// REPORTS VIEW FUNCTIONS
// ==========================================

function showPharmacyReports() {
    // Hide all views to prevent stacking
    hideAllViews();

    // Show pharmacy reports view
    $('#pharmacy-reports-view').show().addClass('active');

    // On mobile, switch to main workspace
    if (window.innerWidth < 768) {
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    }

    // Initialize reports if not already done
    if (!window.pharmacyReportsInitialized) {
        initPharmacyReportsFilters();
        loadPharmacyReportsData();
        initPharmacyReportsDataTables();
        initPharmacyReportsCharts();
        window.pharmacyReportsInitialized = true;
    } else {
        // Refresh data
        loadPharmacyReportsData();
    }
}

function hidePharmacyReports() {
    $('#pharmacy-reports-view').removeClass('active');
    $('#empty-state').show();

    // On mobile, go back to search pane
    if (window.innerWidth < 768) {
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    }
}

// Legacy showReports for backward compatibility
function showReports() {
    showPharmacyReports();
}

function hideReports() {
    hidePharmacyReports();
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

    const emergencyBadge = data.is_emergency
        ? '<span class="badge bg-danger mb-1"><i class="fa fa-bolt"></i> EMERGENCY</span> '
        : '';
    const emergencyBorderStyle = data.is_emergency ? 'border-left: 4px solid #dc3545;' : '';

    return `
        <div class="queue-card" data-patient-id="${data.patient_id}" style="${emergencyBorderStyle}">
            <div class="queue-card-header">
                <div class="queue-card-patient">
                    <div class="queue-card-patient-name">${emergencyBadge}${data.patient_name}</div>
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
// REPORTS VIEW EVENT HANDLERS
// ==========================================

// Open reports view
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

// Show new request button when patient is selected
function updateQuickActions() {
    if (currentPatient) {
        $('#btn-new-request').show();
    } else {
        $('#btn-new-request').hide();
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
// ==========================================

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
        4: { name: 'Completed', color: '#28a745' }
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

// Override billPrescItems to use correct checkbox class for pharmacy workbench
window.billPrescItems = function() {
    if (!currentPatient) {
        toastr.error('Please select a patient first');
        return;
    }

    // Get selected items from DataTable checkboxes - use scoped selector and attr
    const selectedIds = [];
    $('#presc_billing_table').find('.presc-billing-check:checked').each(function() {
        const id = $(this).attr('data-id') || $(this).data('id');
        if (id) selectedIds.push(id);
    });

    // Also check card-based checkboxes (unbilled section)
    $('.request-section[data-section="unbilled"] .prescription-checkbox:checked').each(function() {
        const id = $(this).attr('data-id') || $(this).data('id');
        if (id) selectedIds.push(id);
    });

    console.log('Bill - Found checkboxes:', $('#presc_billing_table').find('.presc-billing-check:checked').length);
    console.log('Bill - Selected IDs:', selectedIds);

    // Validate
    if (selectedIds.length === 0) {
        toastr.warning('Please select at least one item to bill');
        return;
    }

    if (!confirm('Are you sure you want to bill the selected items?')) {
        return;
    }

    const $btn = $('#btn-bill-presc');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Billing...');

    $.ajax({
        url: wbUrl('/product-bill-patient-ajax'),
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            selectedPrescBillRows: selectedIds,
            patient_id: currentPatient,
            patient_user_id: currentPatientData?.user_id || ''
        },
        success: function(response) {
            $btn.prop('disabled', false).html(originalHtml);
            if (response.success) {
                toastr.success(response.message || 'Items billed successfully');
                // Refresh all prescription tables for live update
                refreshAllPrescTables();
                loadPrescriptionItems(currentStatusFilter);
                prescBillingTotal = 0;
                updatePrescBillingTotalPharmacy();
                // Update queue counts as items may have moved
                loadQueueCounts();
            } else {
                toastr.error(response.message || 'Failed to bill items');
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalHtml);
            console.error('Billing failed', xhr);
            toastr.error(xhr.responseJSON?.message || 'Failed to bill items');
        }
    });
};

// Override dismissPrescItems to show modal (instead of confirm())
window.dismissPrescItems = function(type) {
    if (!currentPatient) {
        toastr.error('Please select a patient first');
        return;
    }

    // Just show the modal - the actual dismiss happens in confirmDismiss()
    showDismissModal(type);
};

// Actual dismiss function (called after modal confirmation)
window.dismissPrescItemsConfirmed = function(type) {
    if (!currentPatient) {
        toastr.error('Please select a patient first');
        return;
    }

    const selectedIds = [];

    if (type === 'billing') {
        // From DataTable - use scoped selector and attr
        $('#presc_billing_table').find('.presc-billing-check:checked').each(function() {
            const id = $(this).attr('data-id') || $(this).data('id');
            if (id) selectedIds.push(id);
        });
        // From card-based view
        $('.request-section[data-section="unbilled"] .prescription-checkbox:checked').each(function() {
            const id = $(this).attr('data-id') || $(this).data('id');
            if (id) selectedIds.push(id);
        });
    } else if (type === 'pending') {
        // From DataTable - use scoped selector and attr
        $('#presc_pending_table').find('.presc-pending-check:checked').each(function() {
            const id = $(this).attr('data-id') || $(this).data('id');
            if (id) selectedIds.push(id);
        });
        // From card-based view
        $('.request-section[data-section="billed"] .prescription-checkbox:checked').each(function() {
            const id = $(this).attr('data-id') || $(this).data('id');
            if (id) selectedIds.push(id);
        });
    } else if (type === 'dispense') {
        // From DataTable - use scoped selector and attr
        $('#presc_dispense_table').find('.presc-dispense-check:checked').each(function() {
            const id = $(this).attr('data-id') || $(this).data('id');
            if (id) selectedIds.push(id);
        });
        // From card-based view
        $('.request-section[data-section="ready"] .prescription-checkbox:checked').each(function() {
            const id = $(this).attr('data-id') || $(this).data('id');
            if (id) selectedIds.push(id);
        });
    }

    console.log('Dismiss - Type:', type, 'Selected IDs:', selectedIds);

    if (selectedIds.length === 0) {
        toastr.warning('Please select at least one item to dismiss');
        return;
    }

    // Show loading on button
    const $btn = $('#confirm-dismiss-btn');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Dismissing...');

    $.ajax({
        url: wbUrl('/product-dismiss-patient-ajax'),
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            prescription_ids: selectedIds,
            patient_id: currentPatient
        },
        success: function(response) {
            $btn.prop('disabled', false).html(originalHtml);
            if (response.success) {
                toastr.success(response.message || 'Items dismissed successfully');
                // Clear stored data
                selectedItemsData[type] = [];
                // Reload DataTables
                initializePrescriptionDataTables(currentPatient);
                loadPrescriptionItems(currentStatusFilter);
                prescBillingTotal = 0;
                updatePrescBillingTotalPharmacy();
            } else {
                toastr.error(response.message || 'Failed to dismiss items');
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalHtml);
            console.error('Dismiss failed', xhr);
            toastr.error(xhr.responseJSON?.message || 'Failed to dismiss items');
        }
    });
};

// ===========================================
// CLINICAL CONTEXT FUNCTIONS
// Now handled by shared ClinicalContext module (clinical-context.js)
// Vitals, medications, allergies are loaded by ClinicalContext.load()
// Notes, injections, procedures are lazy-loaded by the shared modal IIFEs
// ===========================================

// ===========================================
// DISPENSE CART MANAGEMENT (MODAL-BASED)
// ===========================================

// Cart data structure
let dispenseCart = [];

// Open the dispense cart modal
function openDispenseCartModal() {
    renderDispenseCart();
    $('#dispenseCartModal').modal('show');

    // If store is selected and cart has items, fetch stock
    const storeId = $('#modal-store-select').val();
    if (storeId && dispenseCart.length> 0) {
        fetchCartStockLevels();
    }
}

// Add selected items to cart and open modal
function addSelectedToCartAndOpen() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    // Get selected items from DataTable
    const $checkedItems = $('#presc_dispense_table').find('.presc-dispense-check:checked');

    if ($checkedItems.length === 0) {
        toastr.warning('Please select items to add to cart');
        return;
    }

    let addedCount = 0;
    $checkedItems.each(function() {
        const $checkbox = $(this);
        const $card = $checkbox.closest('tr').find('.presc-card');
        const id = $checkbox.attr('data-id') || $checkbox.data('id');

        // Skip if already in cart
        if (dispenseCart.find(item => item.id == id)) {
            return;
        }

        // Get item details from card data-attributes (most reliable)
        const productId = $card.attr('data-product-id') || $checkbox.attr('data-product-id');
        const productName = $card.find('.presc-card-title').text().trim() || 'Unknown Product';
        const qty = parseInt($card.attr('data-qty')) || 1;
        const price = parseFloat($card.attr('data-total-price')) || 0;

        dispenseCart.push({
            id: id,
            product_id: productId,
            product_name: productName,
            qty: qty,
            price: price / qty, // Store as unit price for consistency in renderDispenseCart
            total_billed: price,
            stock: null,
            stock_status: 'pending' // Will check when store is selected
        });

        addedCount++;
        $checkbox.prop('checked', false);
    });

    // Uncheck select all
    $('#select-all-dispense').prop('checked', false);

    if (addedCount> 0) {
        toastr.success(`Added ${addedCount} item(s) to cart`);
    }

    // Open modal
    renderDispenseCart();
    $('#dispenseCartModal').modal('show');

    // If store already selected, fetch stock
    const storeId = $('#modal-store-select').val();
    if (storeId) {
        fetchCartStockLevels();
    }
}

// Legacy function for backward compatibility
function addSelectedToCart() {
    addSelectedToCartAndOpen();
}

// Render the dispense cart in modal
function renderDispenseCart() {
    const $cartBody = $('#modal-cart-body');
    const $cartEmpty = $('#modal-cart-empty');
    const $cartContent = $('#modal-cart-content');
    const $cartTotal = $('#modal-cart-total');
    const $modalCartCount = $('#modal-cart-count');
    const $headerCartCount = $('#header-cart-count');
    const $floatingCartCount = $('#floating-cart-count');

    // Update all cart count badges
    const cartCount = dispenseCart.length;
    $modalCartCount.text(cartCount);

    if (cartCount> 0) {
        $headerCartCount.text(cartCount).show();
        $floatingCartCount.text(cartCount).show();
    } else {
        $headerCartCount.hide();
        $floatingCartCount.hide();
    }

    if (dispenseCart.length === 0) {
        $cartEmpty.show();
        $cartContent.hide();
        updateCartStockStatus();
        return;
    }

    $cartEmpty.hide();
    $cartContent.show();

    let totalPrice = 0;
    let html = '';

    dispenseCart.forEach((item, index) => {
        totalPrice += item.price * item.qty;

        let batchDisplay = '';
        let statusBadge = '';
        let rowClass = '';

        if (item.stock_status === 'pending') {
            batchDisplay = '<span class="text-muted small">Select store first</span>';
            statusBadge = '<span class="badge bg-light text-dark badge-sm">Select store</span>';
        } else if (item.stock_status === 'loading') {
            batchDisplay = '<span class="text-muted"><i class="mdi mdi-loading mdi-spin"></i> Loading...</span>';
            statusBadge = '<span class="badge bg-secondary text-white badge-sm">...</span>';
        } else if (item.stock_status === 'sufficient') {
            // Build batch selection dropdown
            batchDisplay = buildBatchDropdown(item, index);
            statusBadge = '<span class="badge badge-stock-ok badge-sm"><i class="mdi mdi-check"></i></span>';
        } else if (item.stock_status === 'insufficient') {
            batchDisplay = `<span class="text-danger small"><i class="mdi mdi-alert-circle"></i> Only ${item.stock || 0} available (need ${item.qty})</span>`;
            statusBadge = '<span class="badge badge-stock-out badge-sm"><i class="mdi mdi-alert"></i></span>';
            rowClass = 'table-danger';
        } else {
            batchDisplay = '<span class="text-warning small"><i class="mdi mdi-help-circle"></i> Unknown</span>';
            statusBadge = '<span class="badge bg-warning text-dark badge-sm">?</span>';
        }

        html += `
            <tr class="${rowClass}" data-cart-index="${index}" data-item-id="${item.id}" data-product-id="${item.product_id || ''}">
                <td>
                    <strong class="d-block">${item.product_name}</strong>
                    <small class="text-muted">PR #${item.id} | Prod #${item.product_id || 'N/A'}</small>
                </td>
                <td class="text-center">${item.qty}</td>
                <td class="text-center cart-batch-cell">${batchDisplay}</td>
                <td class="text-end">₦${(item.price * item.qty).toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                <td class="text-center">${statusBadge}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeFromCart(${index})" title="Remove">
                        <i class="mdi mdi-close-circle"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    $cartBody.html(html);
    $cartTotal.text('₦' + totalPrice.toLocaleString('en-NG', {minimumFractionDigits: 2}));

    updateCartStockStatus();
}

// Remove item from cart
function removeFromCart(index) {
    dispenseCart.splice(index, 1);
    renderDispenseCart();

    if (dispenseCart.length === 0) {
        toastr.info('Cart is now empty');
    }
}

// Clear entire cart
function clearDispenseCart() {
    if (dispenseCart.length === 0) {
        toastr.info('Cart is already empty');
        return;
    }

    dispenseCart = [];
    renderDispenseCart();
    toastr.info('Cart cleared');
}

// Get current store ID from modal
function getCurrentStoreId() {
    return $('#modal-store-select').val();
}

// Fetch stock levels for all cart items
function fetchCartStockLevels() {
    const storeId = getCurrentStoreId();
    console.log('fetchCartStockLevels called, storeId:', storeId, 'cart length:', dispenseCart.length);

    if (!storeId) {
        $('#modal-store-status').html('<span class="text-warning"><i class="mdi mdi-store-alert"></i> Select a store above to check availability</span>');
        updateCartStockStatus();
        return;
    }

    const storeName = $('#modal-store-select option:selected').text();
    $('#modal-store-status').html(`<span class="text-info"><i class="mdi mdi-loading mdi-spin"></i> Checking stock at ${storeName}...</span>`);

    if (dispenseCart.length === 0) {
        $('#modal-store-status').html(`<span class="text-muted"><i class="mdi mdi-store"></i> Ready to dispense from: <strong>${storeName}</strong></span>`);
        return;
    }

    let pendingChecks = dispenseCart.length;

    dispenseCart.forEach((item, index) => {
        console.log('Checking item:', index, 'product_id:', item.product_id);

        if (item.product_id) {
            // Fetch batch info for this product
            fetchProductBatches(item.product_id, storeId, function(batchData) {
                console.log('Batch data received for product', item.product_id, ':', batchData);

                const totalAvailable = batchData.total_available || 0;
                const batches = batchData.batches || [];

                dispenseCart[index].stock = totalAvailable;
                dispenseCart[index].batches = batches;
                dispenseCart[index].stock_status = totalAvailable>= item.qty ? 'sufficient' : 'insufficient';

                updateCartRowStock(index);

                pendingChecks--;
                console.log('Pending checks remaining:', pendingChecks);

                if (pendingChecks <= 0) {
                    updateCartStockStatus();
                    $('#modal-store-status').html(`<span class="text-muted"><i class="mdi mdi-store"></i> Dispensing from: <strong>${storeName}</strong></span>`);
                }
            });
        } else {
            console.warn('Cart item missing product_id:', item);
            dispenseCart[index].stock = 0;
            dispenseCart[index].batches = [];
            dispenseCart[index].stock_status = 'insufficient';
            updateCartRowStock(index);
            pendingChecks--;
        }
    });
}

// Fetch product batches from server
function fetchProductBatches(productId, storeId, callback) {
    $.ajax({
        url: wbRoute('pharmacy.product-batches', '/pharmacy/product-batches'),
        method: 'GET',
        data: {
            product_id: productId,
            store_id: storeId
        },
        success: function(response) {
            if (response.success) {
                callback({
                    total_available: response.total_available,
                    batches: response.batches.map(b => ({
                        id: b.id,
                        batch_number: b.batch_number || b.name || `BTH-${b.id}`,
                        current_qty: b.qty || b.current_qty,
                        expiry_date: b.expiry_date,
                        is_expiring_soon: b.is_expiring_soon || false,
                        is_expired: b.is_expired || false
                    }))
                });
            } else {
                callback({ total_available: 0, batches: [] });
            }
        },
        error: function() {
            // Fallback to old stock check method
            fetchPharmacyProductStock(productId, function(stockData) {
                const storeStock = stockData.stores.find(s => s.store_id == storeId);
                callback({
                    total_available: storeStock ? storeStock.quantity : 0,
                    batches: []
                });
            });
        }
    });
}

// Update a single cart row's stock/batch display
function updateCartRowStock(index) {
    const item = dispenseCart[index];
    const $row = $(`#modal-cart-body tr[data-cart-index="${index}"]`);

    if (!$row.length) return;

    let batchDisplay = '';
    let statusBadge = '';

    if (item.stock_status === 'sufficient') {
        batchDisplay = buildBatchDropdown(item, index);
        statusBadge = '<span class="badge badge-stock-ok badge-sm"><i class="mdi mdi-check"></i></span>';
        $row.removeClass('table-danger');
    } else if (item.stock_status === 'insufficient') {
        batchDisplay = `<span class="text-danger small"><i class="mdi mdi-alert-circle"></i> Only ${item.stock || 0} available (need ${item.qty})</span>`;
        statusBadge = '<span class="badge badge-stock-out badge-sm"><i class="mdi mdi-alert"></i></span>';
        $row.addClass('table-danger');
    }

    $row.find('.cart-batch-cell').html(batchDisplay);
    $row.find('td:eq(4)').html(statusBadge);
}

// Build batch selection dropdown for a cart item
function buildBatchDropdown(item, index) {
    const batches = item.batches || [];
    const useFifo = $('#use-fifo-auto').is(':checked');
    const selectedBatchId = item.selected_batch_id || '';

    if (batches.length === 0) {
        // No batch info, show simple stock count
        return `<span class="text-success small"><i class="mdi mdi-check-circle"></i> ${item.stock} in stock</span>`;
    }

    if (useFifo && !selectedBatchId) {
        // FIFO mode - show recommended batch
        const fifoBatch = batches[0]; // First batch is oldest (FIFO)
        return `
            <div class="batch-fifo-display">
                <span class="badge bg-info-subtle text-info small">
                    <i class="mdi mdi-sort-clock-ascending"></i> FIFO
                </span>
                <span class="small d-block text-muted mt-1">
                    ${fifoBatch.batch_number || 'Auto'}
                    ${fifoBatch.expiry_date ? `<br>Exp: ${fifoBatch.expiry_date}` : ''}
                </span>
                <button type="button" class="btn btn-link btn-sm p-0 small" onclick="toggleBatchManualSelection(${index})">
                    <i class="mdi mdi-pencil"></i> Change
                </button>
            </div>
        `;
    }

    // Manual selection mode - show dropdown
    let options = '<option value="">Auto (FIFO)</option>';
    batches.forEach(batch => {
        const isSelected = selectedBatchId == batch.id ? 'selected' : '';
        const expiryClass = batch.is_expiring_soon ? 'text-warning' : (batch.is_expired ? 'text-danger' : '');
        const expiryText = batch.expiry_date ? ` | Exp: ${batch.expiry_date}` : '';
        options += `<option value="${batch.id}" ${isSelected} class="${expiryClass}">
            ${batch.batch_number} (${batch.current_qty} avail)${expiryText}
        </option>`;
    });

    return `
        <select class="form-select form-select-sm batch-select" data-cart-index="${index}" onchange="onBatchSelected(this, ${index})">
            ${options}
        </select>
    `;
}

// Toggle manual batch selection for an item
function toggleBatchManualSelection(index) {
    const item = dispenseCart[index];
    item.manual_batch_mode = true;
    updateCartRowStock(index);
}

// Handle batch selection change
function onBatchSelected(selectEl, index) {
    const batchId = $(selectEl).val();
    dispenseCart[index].selected_batch_id = batchId || null;
}

// Update overall cart stock status
function updateCartStockStatus() {
    const $statusDiv = $('#modal-stock-status');
    const $warning = $('#modal-stock-warning');
    const $dispenseBtn = $('#btn-dispense-cart');

    if (dispenseCart.length === 0) {
        $statusDiv.html('<span class="badge bg-secondary">Cart empty</span>');
        $warning.hide();
        $dispenseBtn.prop('disabled', true);
        return;
    }

    const storeId = getCurrentStoreId();
    if (!storeId) {
        $statusDiv.html('<span class="badge bg-warning text-dark"><i class="mdi mdi-store-alert"></i> Select a store to check stock</span>');
        $warning.hide();
        $dispenseBtn.prop('disabled', true);
        return;
    }

    const hasPending = dispenseCart.some(i => i.stock_status === 'pending');
    const hasLoading = dispenseCart.some(i => i.stock_status === 'loading');
    const hasInsufficient = dispenseCart.some(i => i.stock_status === 'insufficient');
    const allSufficient = dispenseCart.every(i => i.stock_status === 'sufficient');

    if (hasPending) {
        // Stock not checked yet - trigger check
        fetchCartStockLevels();
        return;
    }

    if (hasLoading) {
        $statusDiv.html('<span class="badge bg-info"><i class="mdi mdi-loading mdi-spin"></i> Checking stock...</span>');
        $warning.hide();
        $dispenseBtn.prop('disabled', true);
    } else if (hasInsufficient) {
        const insufficientCount = dispenseCart.filter(i => i.stock_status === 'insufficient').length;
        $statusDiv.html(`<span class="badge badge-stock-out"><i class="mdi mdi-alert-circle"></i> ${insufficientCount} item(s) insufficient stock</span>`);
        $warning.show();
        $('#modal-stock-warning-text').text(`${insufficientCount} item(s) have insufficient stock to fulfill the order`);
        $dispenseBtn.prop('disabled', true);
    } else if (allSufficient) {
        $statusDiv.html(`<span class="badge badge-stock-ok"><i class="mdi mdi-check-circle"></i> All items ready</span>`);
        $warning.hide();
        $dispenseBtn.prop('disabled', false);
    }
}

// Dispense from cart
function dispenseFromCart() {
    if (!currentPatient) {
        toastr.error('Please select a patient first');
        return;
    }

    const storeId = getCurrentStoreId();
    if (!storeId) {
        toastr.warning('Please select a dispensing store');
        return;
    }

    if (dispenseCart.length === 0) {
        toastr.warning('Cart is empty');
        return;
    }

    // Check for insufficient stock
    const insufficientItems = dispenseCart.filter(i => i.stock_status === 'insufficient');
    if (insufficientItems.length> 0) {
        toastr.error('Cannot dispense: Some items have insufficient stock');
        return;
    }

    // Check for loading items
    const loadingItems = dispenseCart.filter(i => i.stock_status === 'loading');
    if (loadingItems.length> 0) {
        toastr.warning('Please wait for stock check to complete');
        return;
    }

    const storeName = $('#modal-store-select option:selected').text();
    if (!confirm(`Dispense ${dispenseCart.length} item(s) from ${storeName}?`)) {
        return;
    }

    const itemIds = dispenseCart.map(i => i.id);

    // Build batch selections array for items with manual selection
    const batchSelections = [];
    dispenseCart.forEach(item => {
        if (item.selected_batch_id) {
            batchSelections.push({
                product_request_id: item.id,
                batch_id: item.selected_batch_id
            });
        }
    });

    const $btn = $('#btn-dispense-cart');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Dispensing...');

    $.ajax({
        url: wbRoute('pharmacy.dispense-with-batch', '/pharmacy/dispense-with-batch'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            patient_id: currentPatient,
            product_request_ids: itemIds,
            store_id: storeId,
            batch_selections: batchSelections
        },
        success: function(response) {
            $btn.prop('disabled', false).html(originalHtml);
            toastr.success(response.message || 'Prescriptions dispensed successfully');

            // Clear the cart and close modal
            dispenseCart = [];
            renderDispenseCart();
            $('#dispenseCartModal').modal('hide');

            // Refresh tables
            refreshAllPrescTables();
            loadPrescriptionItems(currentStatusFilter);
            loadQueueCounts();
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalHtml);

            if (xhr.status === 422 && xhr.responseJSON?.validation_errors) {
                const errors = xhr.responseJSON.validation_errors;
                let errorHtml = '<strong>Cannot Dispense:</strong><ul class="mb-0 mt-1">';
                errors.forEach(err => {
                    errorHtml += `<li>${err.product || 'Item'}: ${err.error}</li>`;
                });
                errorHtml += '</ul>';

                toastr.error(errorHtml, 'Validation Failed', {
                    closeButton: true,
                    timeOut: 10000,
                    extendedTimeOut: 5000,
                    escapeHtml: false
                });

                fetchCartStockLevels();
            } else {
                toastr.error(xhr.responseJSON?.message || 'Failed to dispense prescriptions');
            }
        }
    });
}

// Print cart prescriptions
function printCartPrescriptions() {
    if (dispenseCart.length === 0) {
        toastr.warning('Cart is empty');
        return;
    }

    const itemIds = dispenseCart.map(i => i.id);
    printPrescription(itemIds);
}

// Modal store change handler - use event delegation to ensure it works
$(document).on('change', '#modal-store-select', function() {
    const storeId = $(this).val();
    console.log('Store changed to:', storeId); // Debug log

    if (dispenseCart.length> 0 && storeId) {
        // Reset stock status and re-fetch
        dispenseCart.forEach((item, index) => {
            dispenseCart[index].stock = null;
            dispenseCart[index].batches = [];
            dispenseCart[index].selected_batch_id = null;
            dispenseCart[index].stock_status = 'loading';
        });
        renderDispenseCart();
        fetchCartStockLevels();
    } else if (!storeId) {
        // No store selected - update status
        updateCartStockStatus();
    }
});

// FIFO mode toggle handler - re-render cart when toggled
$(document).on('change', '#use-fifo-auto', function() {
    // Re-render to show/hide batch dropdowns
    renderDispenseCart();
});

