if (typeof window.wbUrl !== 'function') {
    window.wbUrl = function(path) {
        var base = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.baseUrl) ? window.WORKBENCH_CONFIG.baseUrl : '';
        base = base.replace(/\/$/, '');
        var cleanPath = (path || '').replace(/^\//, '');
        return base ? (base + '/' + cleanPath) : ('/' + cleanPath);
    };
}
if (typeof window.wbRoute !== 'function') {
    window.wbRoute = function(name, fallbackPath) {
        if (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.routes) {
            if (window.WORKBENCH_CONFIG.routes[name]) return window.WORKBENCH_CONFIG.routes[name];
            var dotKey = name.replace(/_/g, '.');
            if (window.WORKBENCH_CONFIG.routes[dotKey]) return window.WORKBENCH_CONFIG.routes[dotKey];
            var underscoreKey = name.replace(/\./g, '_');
            if (window.WORKBENCH_CONFIG.routes[underscoreKey]) return window.WORKBENCH_CONFIG.routes[underscoreKey];
        }
        return window.wbUrl(fallbackPath || '');
    };
}

// REPORTS VIEW FUNCTIONS
// ==========================================

function showReports() {
    // Hide all views to prevent stacking
    hideAllViews();

    // Show reports view
    $('#reports-view').show().addClass('active');

    // On mobile, switch to main workspace
    if (window.innerWidth < 768) {
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    }

    // Load statistics and initialize if not already done
    if (!window.reportsInitialized) {
        // Don't set default dates - load all records initially
        // setDefaultDateFilters();
        loadFilterOptions();
        loadReportsStatistics();
        initializeReportsDataTable();
        // initializeReportsCharts(); // TODO: Implement when Chart.js is added
        window.reportsInitialized = true;
    } else {
        // Refresh statistics
        loadReportsStatistics();
        if (window.reportsDataTable) {
            window.reportsDataTable.ajax.reload();
        }
    }
}

function hideReports() {
    $('#reports-view').removeClass('active');
    $('#empty-state').show();

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

function loadFilterOptions() {
    // Load doctors
    $.ajax({
        url: wbRoute('lab.filterDoctors', '/lab-workbench/filter-doctors'),
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
        url: wbRoute('lab.filterHmos', '/lab-workbench/filter-hmos'),
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
        url: wbRoute('lab.filterServices', '/lab-workbench/filter-services'),
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
        url: wbRoute('lab.statistics', '/lab-workbench/statistics'),
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
            url: wbRoute('lab.reports', '/lab-workbench/reports'),
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

    // Emergency badge
    const emergencyBadge = data.priority === 'emergency'
        ? '<span class="badge bg-danger me-2"><i class="fa fa-bolt"></i> EMERGENCY</span>'
        : (data.priority === 'urgent' ? '<span class="badge bg-warning text-dark me-2">Urgent</span>' : '');

    return `
        <div class="queue-card" data-patient-id="${data.patient_id}" ${data.priority === 'emergency' ? 'style="border-left: 4px solid #dc3545; background: #fff8f8;"' : ''}>
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
// NEW LAB REQUEST — SEARCH, SELECT, SUBMIT
// ==========================================
let labSearchTimer = null;

function searchLabServices(q) {
    const $results = $('#service-search-results');
    const patientId = currentPatient ? (typeof currentPatient === 'object' ? currentPatient.id : currentPatient) : null;

    if (typeof SearchManager !== 'undefined') {
        SearchManager.execute({
            inputVal: q,
            minLength: 2,
            delay: 300,
            url: wbUrl('live-search-services'),
            data: {
                term: q,
                category_id: '',
                patient_id: patientId
            },
            onStart: function() {
                $results.html('<li class="list-group-item text-center text-muted"><i class="mdi mdi-loading mdi-spin"></i> Searching...</li>').show();
            },
            onSuccess: function(data) {
                renderLabSearchResults(data, q);
            },
            onEmptyQuery: function() {
                $results.html('').hide();
            }
        });
    } else {
        clearTimeout(labSearchTimer);
        if (!q || q.trim().length < 2) {
            $results.html('').hide();
            return;
        }

        $results.html('<li class="list-group-item text-center text-muted"><i class="mdi mdi-loading mdi-spin"></i> Searching...</li>').show();
        labSearchTimer = setTimeout(function() {
            $.ajax({
                url: wbUrl('live-search-services'),
                method: "GET",
                dataType: 'json',
                data: {
                    term: q,
                    category_id: '',
                    patient_id: patientId
                },
                success: function(data) {
                    renderLabSearchResults(data, q);
                }
            });
        }, 300);
    }
}

function renderLabSearchResults(data, q) {
    const $results = $('#service-search-results');
    $results.html('');
    
    // Inject Free-Form option at the top
    const freeFormHtml = `
        <li class="list-group-item list-group-item-action text-primary" onclick="addFreeFormLabWorkbench()" style="cursor:pointer;">
            <i class="mdi mdi-plus-circle"></i> Not listed? Add free-form '${q}'
        </li>
    `;
    $results.append(freeFormHtml);

    if (!data || data.length === 0) {
        $results.append('<li class="list-group-item text-center text-muted"><i class="mdi mdi-alert-circle-outline"></i> No lab services found for "' + q + '"</li>');
        $results.show();
        return;
    }


                for (var i = 0; i < data.length; i++) {
                    const item = data[i] || {};
                    const isCombo = item.is_combo || false;
                    const bundleItems = item.bundle_items || [];
                    if (isCombo) { window.comboDataMap = window.comboDataMap || {}; window.comboDataMap[item.id] = item; }
                    const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                    const name = item.service_name || 'Unknown';
                    const code = item.service_code || '';
                    const price = item.price && item.price.sale_price !== undefined ? item.price.sale_price : (item.base_price || 0);
                    const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                    const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                    const mode = item.coverage_mode || null;
                    const coverageBadge = mode && mode !== 'cash' ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ${payable}</span> <span class='text-success ms-1'>Claim: ${claims}</span>` : '';
                    const displayName = `${name}[${code}]`;

                    const escapedDisplayName = displayName.replace(/'/g, "\\'");
                    const escapedId = (item.id + '').replace(/'/g, "\\'");

                    var mk;
                    if (isCombo) {
                        var itemList = bundleItems.length > 0
                            ? bundleItems.map(function(bi) { return '<span class="badge bg-secondary me-1">' + (bi.name || 'Item') + '</span>'; }).join('')
                            : '<span class="text-muted fst-italic small">Preview not available</span>';
                        mk = `<li class='list-group-item list-group-item-action'
                               style="cursor: pointer; border-left: 3px solid #0d6efd;"
                               onclick="applyLabComboWb('${escapedId}')">
                               <div class="d-flex justify-content-between align-items-start">
                                   <div>
                                       <span class="badge bg-primary me-1"><i class="fa fa-cubes"></i> BUNDLE</span>
                                       <b>${name}</b>
                                       <div class="small text-muted mt-1">${itemList}</div>
                                   </div>
                                   <div class="text-end">
                                       <div class="fw-bold text-primary">NGN ${Number(payable).toLocaleString()}</div>
                                       ${coverageBadge}
                                   </div>
                               </div>
                           </li>`;
                    } else {
                        mk =
                            `<li class='list-group-item'
                               style="background-color: #f0f0f0; cursor: pointer;"
                               onclick="setSearchValLab('${escapedDisplayName}', '${escapedId}', '${price}', '${mode}', '${claims}', '${payable}')">
                               [${category}] <b>${name}[${code}]</b> NGN ${Number(price).toLocaleString()} ${coverageBadge}</li>`;
                    }
                    $results.append(mk);
                }
                $results.show();
}

function addFreeFormLabWorkbench() {
    Swal.fire({
        title: 'Add Free-Form Lab Test',
        text: 'Enter the name of the lab test to request:',
        input: 'text',
        showCancelButton: true,
        confirmButtonText: 'Add',
        inputValidator: (value) => {
            if (!value) return 'You need to write something!'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            setSearchValLab(result.value + ' [Free-form]', 'FF_' + result.value, 0, 'cash', 0, 0);
            $('#service-search-input').val('');
            $('#service-search-results').hide();
        }
    });
}

function setSearchValLab(name, id, price, coverageMode, claims, payable) {
    coverageMode = (coverageMode === 'null' || coverageMode === 'undefined') ? null : coverageMode;
    const coverageBadge = coverageMode && coverageMode !== 'cash' ?
        `<div class="small mt-1"><span class="badge bg-info">${coverageMode.toUpperCase()}</span> <span class="text-danger">Pay: ${payable ?? price}</span> <span class="text-success">Claims: ${claims ?? 0}</span></div>` : '';

    var mk = `
        <tr>
            <td>${name}${coverageBadge}</td>
            <td>NGN ${Number(payable ?? price).toLocaleString()}</td>
            <td>
                <input type='text' class='form-control form-control-sm' name='consult_lab_note[]' placeholder='Optional note'>
                <input type='hidden' name='consult_lab_id[]' value='${id}'>
            </td>
            <td><button type="button" class='btn btn-danger btn-sm' onclick="removeProdRow(this)"><i class="fa fa-times"></i></button></td>
        </tr>
    `;

    $('#selected-lab-services').append(mk);
    $('#service-search-results').html('').hide();
    $('#service-search-input').val('');
}

function applyLabComboWb(comboId) {
    var comboData = (window.comboDataMap || {})[comboId] || {};
    var name = comboData.service_name || 'Combo';
    var bundleItems = comboData.bundle_items || [];
    var price   = parseFloat(comboData.base_price  || 0);
    var payable = parseFloat(comboData.payable_amount != null ? comboData.payable_amount : price);
    var claims  = parseFloat(comboData.claims_amount  || 0);
    var mode    = comboData.coverage_mode || null;

    var patientId = currentPatient
        ? (typeof currentPatient === 'object' ? currentPatient.id : currentPatient)
        : null;

    if (!patientId) {
        toastr.warning('Please select a patient first before applying a bundle.');
        return;
    }


    ComboConfirmModal.show({
        name        : name,
        bundleItems : bundleItems,
        price       : price,
        payable     : payable,
        claims      : claims,
        mode        : mode,
        onConfirm   : function() {
            $.ajax({
                url         : wbRoute('lab.applyCombo', '/lab-workbench/apply-combo'),
                method      : 'POST',
                contentType : 'application/json',
                dataType    : 'json',
                headers     : { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data        : JSON.stringify({ service_id: comboId, patient_id: patientId, note: '' }),
                success     : function(r) {
                    if (r.success) {
                        toastr.success(r.message || 'Combo applied successfully!');
                        $('#service-search-results').html('').hide();
                        $('#service-search-input').val('');
                        if (typeof loadLabServices === 'function') loadLabServices();
                        if (typeof LabWorkbench !== 'undefined' && typeof LabWorkbench.refreshPendingQueue === 'function') {
                            LabWorkbench.refreshPendingQueue();
                        }
                        if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
                            $('#investigation_history_list').DataTable().ajax.reload(null, false);
                        }
                    } else {
                        toastr.error(r.message || 'Failed to apply combo');
                    }
                },
                error       : function(e) {
                    toastr.error((e.responseJSON && e.responseJSON.message) ? e.responseJSON.message : 'Error applying combo');
                }
            });
        }
    });
}

function removeProdRow(btn) {
    $(btn).closest('tr').remove();
}

// Lab request form submission
$('#new-lab-request-form').on('submit', function(e) {
    e.preventDefault();

    const serviceIds = [];
    const notes = [];
    $('#selected-lab-services tr').each(function() {
        const serviceId = $(this).find('input[name="consult_lab_id[]"]').val();
        const note = $(this).find('input[name="consult_lab_note[]"]').val();
        if (serviceId) {
            serviceIds.push(serviceId);
            notes.push(note || '');
        }
    });

    if (serviceIds.length === 0) {
        toastr.warning('Please select at least one lab service');
        return;
    }


    const patientId = currentPatient ? (typeof currentPatient === 'object' ? currentPatient.id : currentPatient) : null;
    if (!patientId) {
        toastr.error('No patient selected');
        return;
    }


    const $submitBtn = $(this).find('button[type="submit"]');
    const originalBtnHtml = $submitBtn.html();
    $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Submitting...');

    $.ajax({
        url: wbRoute('lab.storeRequest', '/lab-workbench/store-request'),
        method: 'POST',
        data: {
            patient_id: patientId,
            service_ids: serviceIds,
            notes: notes,
            clinical_notes: $('#request-clinical-notes').val(),
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                $('#selected-lab-services').empty();
                $('#request-clinical-notes').val('');
                loadPatient(patientId);
                getQueueCounts();
                switchWorkspaceTab('pending');
            } else {
                toastr.error(response.message || 'Failed to create request');
            }
        },
        error: function(xhr) {
            const message = xhr.responseJSON?.message || 'Error creating lab request';
            toastr.error(message);
        },
        complete: function() {
            $submitBtn.prop('disabled', false).html(originalBtnHtml);
        }
    });
});

// Close search dropdown when clicking outside
$(document).on('click', function(e) {
    if (!$(e.target).closest('#service-search-input, #service-search-results').length) {
        $('#service-search-results').html('').hide();
    }
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
        url: wbRoute('lab.statistics', '/lab-workbench/statistics'),
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

// =============================================
// RESULT APPROVAL WORKFLOW
// =============================================

    // Click on queue item opens patient workspace
    $('#queue-datatable').on('click', '.queue-patient-item', function(e) {
        // Prevent opening workspace if they clicked an inline action button
        if ($(e.target).closest('.enter-result-inline-btn').length || $(e.target).closest('.reverse-approval-btn').length) {
            return;
        }

        const patientId = $(this).data('patient-id');
        currentPatientId = patientId;
        
        // Show loading state
        $('#main-workspace').addClass('active');
    });

function openApprovalReview(requestId) {
    currentApprovalId = requestId;
    $('#approval-review-footer').hide();
    $('#approval-review-body').html(`
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">Loading result details...</p>
        </div>
    `);
    $('#approvalReviewModal').modal('show');

    $.get(`/lab-workbench/approval/${requestId}`, function(response) {
        if (!response.success) {
            $('#approval-review-body').html(`<div class="alert alert-danger">${response.message}</div>`);
            return;
        }

        const d = response.data;
        let rejectionBanner = '';
        if (d.status === 6) {
            rejectionBanner = `
                <div class="approval-rejection-banner">
                    <strong><i class="mdi mdi-alert"></i> Previously Rejected</strong><br>
                    <small>By: ${d.rejected_by_name || 'N/A'} on ${d.rejected_at || 'N/A'}</small><br>
                    <strong>Reason:</strong> ${d.rejection_reason || 'No reason provided'}
                </div>
            `;
        }

        let attachmentsHtml = '';
        if (d.attachments && d.attachments.length> 0) {
            const items = d.attachments.map(att => {
                const fileName = typeof att === 'string' ? att.split('/').pop() : (att.name || 'File');
                const filePath = typeof att === 'string' ? att : att.path;
                return `<a href="${filePath}" target="_blank" class="approval-attachment-item">
                    <i class="mdi mdi-file"></i> ${fileName}
                </a>`;
            }).join('');
            attachmentsHtml = `
                <h6 class="mt-3"><i class="mdi mdi-paperclip"></i> Attachments</h6>
                <div class="approval-attachments">${items}</div>
            `;
        }

        let resultDataHtml = '';
        if (d.result_data && typeof d.result_data === 'object') {
            const rows = Object.entries(d.result_data).map(([key, val]) => {
                if (typeof val === 'object' && val !== null) {
                    return `<tr>
                        <td><strong>${val.parameter || key}</strong></td>
                        <td>${val.value || ''}</td>
                        <td>${val.unit || ''}</td>
                        <td>${val.reference_range || val.range || ''}</td>
                        <td>${val.flag || ''}</td>
                    </tr>`;
                }
                return `<tr><td><strong>${key}</strong></td><td colspan="4">${val}</td></tr>`;
            }).join('');

            if (rows) {
                resultDataHtml = `
                    <h6 class="mt-3"><i class="mdi mdi-table"></i> Structured Results</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead><tr><th>Parameter</th><th>Value</th><th>Unit</th><th>Reference Range</th><th>Flag</th></tr></thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>
                `;
            }
        }

        $('#approval-review-body').html(`
            ${rejectionBanner}
            <div class="approval-meta-grid">
                <div class="approval-meta-item">
                    <label>Service</label>
                    <span>${d.service_name}</span>
                </div>
                <div class="approval-meta-item">
                    <label>Patient</label>
                    <span>${d.patient_name}</span>
                </div>
                <div class="approval-meta-item">
                    <label>Entered By</label>
                    <span>${d.entered_by}</span>
                </div>
                <div class="approval-meta-item">
                    <label>Result Date</label>
                    <span>${d.result_date || 'N/A'}</span>
                </div>
            </div>

            ${resultDataHtml}

            <h6 class="mt-3"><i class="mdi mdi-file-document"></i> Result Report</h6>
            <div class="approval-result-preview">
                ${d.result_html || '<em class="text-muted">No report content</em>'}
            </div>

            ${attachmentsHtml}
        `);

        // Show footer only if status is pending approval (5)
        if (d.status === 5) {
            $('#approval-review-footer').show();
        } else {
            $('#approval-review-footer').hide();
        }
    }).fail(function(xhr) {
        $('#approval-review-body').html(`<div class="alert alert-danger">Failed to load result details.</div>`);
    });
}

function approveLabResult() {
    if (!currentApprovalId) return;
    $('#approvalReviewModal').modal('hide');
    $('#approveConfirmModal').modal('show');
}

function confirmApproveLabResult() {
    if (!currentApprovalId) return;

    $('#btn-confirm-approve').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Approving...');

    $.ajax({
        url: wbUrl(`/lab-workbench/approval/${currentApprovalId}/approve`),
        type: 'POST',
        data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) },
        success: function(response) {
            $('#approveConfirmModal').modal('hide');
            if (response.success) {
                toastr.success(response.message);
                loadQueueCounts();
                if (queueDataTable) queueDataTable.ajax.reload(null, false);
                if (currentPatient) refreshCurrentPatientData();
            } else {
                toastr.error(response.message);
            }
        },
        error: function(xhr) {
            $('#approveConfirmModal').modal('hide');
            toastr.error(xhr.responseJSON?.message || 'Failed to approve result.');
        },
        complete: function() {
            $('#btn-confirm-approve').prop('disabled', false).html('<i class="mdi mdi-check-bold"></i> Yes, Approve');
        }
    });
}

let currentReverseId = null;

function reverseLabApproval(requestId) {
    currentReverseId = requestId;
    $('#reverseApprovalModal').modal('show');
}

function confirmReverseLabApproval() {
    if (!currentReverseId) return;

    $('#btn-confirm-reverse').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Reversing...');

    $.ajax({
        url: wbUrl(`/lab-workbench/approval/${currentReverseId}/reverse`),
        type: 'POST',
        data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) },
        success: function(response) {
            $('#reverseApprovalModal').modal('hide');
            if (response.success) {
                toastr.success(response.message);
                loadQueueCounts();
                if (queueDataTable) queueDataTable.ajax.reload(null, false);
                if (currentPatient) refreshCurrentPatientData();
            } else {
                toastr.error(response.message);
            }
        },
        error: function(xhr) {
            $('#reverseApprovalModal').modal('hide');
            toastr.error(xhr.responseJSON?.message || 'Failed to reverse approval.');
        },
        complete: function() {
            currentReverseId = null;
            $('#btn-confirm-reverse').prop('disabled', false).html('<i class="mdi mdi-undo-variant"></i> Yes, Reverse');
        }
    });
}

function rejectLabResult() {
    if (!currentApprovalId) return;
    $('#approvalReviewModal').modal('hide');
    $('#rejection_reason').val('');
    $('#rejectionReasonModal').modal('show');
}

function confirmRejectLabResult() {
    const reason = $('#rejection_reason').val().trim();
    if (!reason) {
        toastr.error('Please provide a rejection reason.');
        return;
    }


    $.ajax({
        url: wbUrl(`/lab-workbench/approval/${currentApprovalId}/reject`),
        type: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            rejection_reason: reason
        },
        success: function(response) {
            if (response.success) {
                $('#rejectionReasonModal').modal('hide');
                toastr.success(response.message);
                loadQueueCounts();
                if (currentPatient) refreshCurrentPatientData();
            } else {
                toastr.error(response.message);
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to reject result.');
        }
    });
}

// Bind approval event listeners
$(document).on('click', '.review-approval-btn', function() {
    const requestId = $(this).data('request-id');
    openApprovalReview(requestId);
});

$(document).on('click', '#btn-approve-result', function() {
    approveLabResult();
});

$(document).on('click', '#btn-confirm-approve', function() {
    confirmApproveLabResult();
});

$(document).on('click', '.reverse-approval-btn', function(e) {
    e.stopPropagation();
    const requestId = $(this).data('request-id');
    reverseLabApproval(requestId);
});

    // Handle inline Enter Result click
    $('#queue-datatable').on('click', '.enter-result-inline-btn', function(e) {
        e.stopPropagation();
        const requestId = $(this).data('request-id');
        window.enterLabResult(requestId);
    });

    $('#queue-datatable').on('click', '.dismiss-freeform-btn', function(e) {
        e.stopPropagation();
    });

$(document).on('click', '#btn-confirm-reverse', function() {
    confirmReverseLabApproval();
});

$(document).on('click', '#btn-reject-result', function() {
    rejectLabResult();
});

$(document).on('submit', '#rejectionReasonForm', function(e) {
    e.preventDefault();
    confirmRejectLabResult();
});

$(document).on('click', '#openApprovalQueue', function() {
    showQueue('approval');
});

// --- Walk-in Registration ---
function openWalkInRegistration() {
    // Extend config to preserve registerUrl and other defaults
    window.patientFormConfig = window.patientFormConfig || {};
    window.patientFormConfig.onSuccess = function(patientId) {
        loadPatient(patientId);
        $('#patientFormModal').modal('hide');
        toastr.success('Patient registered successfully.');
        
        // Clear search if open
        if ($('#patient_search_results').is(':visible')) {
            $('#patient_search').val('');
            $('#patient_search_results').hide();
        }
    };
    window.patientFormConfig.onCancel = function() {
        disableWalkInMode();
    };
    window.patientFormConfig.hmos = (window.WORKBENCH_CONFIG?.hmos || []);


    // Show modal first
    showPatientFormModal('create');

    // After modal is shown, enable walk-in mode
    $('#patientFormModal').one('shown.bs.modal', function() {
        enableWalkInMode('LAB-');
    });
}

// Ensure walk-in mode is disabled when modal is hidden
$('#patientFormModal').on('hidden.bs.modal', function() {
    disableWalkInMode();
});

$(document).on('click', '#btn-register-walkin', function() {
    openWalkInRegistration();
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
