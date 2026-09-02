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

function loadTrashData() {
    const patientId = currentPatient || null;

    // Load dismissed requests
    $.ajax({
        url: wbUrl('/imaging-workbench/dismissed-requests/' + (patientId || '')),
        method: 'GET',
        success: function(data) {
            $('#dismissed-count').text(data.length);
            updateTrashTotalCount();

            // Check if DataTables is loaded
            if (typeof $.fn.DataTable === 'undefined') {
                console.error('DataTables library is not loaded');
                $('#dismissed-table').html('<tr><td class="text-danger">Error: DataTables library not loaded</td></tr>');
                return;
            }

            if ($.fn.DataTable.isDataTable('#dismissed-table')) {
                $('#dismissed-table').DataTable().destroy();
            }

            $('#dismissed-table').DataTable({
                data: data,
                columns: [{
                    data: null,
                    render: function(data) {
                        return createTrashCard(data, 'dismissed');
                    }
                }],
                ordering: false,
                searching: true,
                pageLength: 10,
                language: {
                    emptyTable: "No dismissed requests found"
                }
            });
        }
    });

    // Load deleted requests
    $.ajax({
        url: wbUrl('/imaging-workbench/deleted-requests/' + (patientId || '')),
        method: 'GET',
        success: function(data) {
            $('#deleted-count').text(data.length);
            updateTrashTotalCount();

            // Check if DataTables is loaded
            if (typeof $.fn.DataTable === 'undefined') {
                console.error('DataTables library is not loaded');
                $('#deleted-table').html('<tr><td class="text-danger">Error: DataTables library not loaded</td></tr>');
                return;
            }

            if ($.fn.DataTable.isDataTable('#deleted-table')) {
                $('#deleted-table').DataTable().destroy();
            }

            $('#deleted-table').DataTable({
                data: data,
                columns: [{
                    data: null,
                    render: function(data) {
                        return createTrashCard(data, 'deleted');
                    }
                }],
                ordering: false,
                searching: true,
                pageLength: 10,
                language: {
                    emptyTable: "No deleted requests found"
                }
            });
        }
    });
}

function createTrashCard(data, type) {
    const serviceName = data.service ? data.service.service_name : 'N/A';
    const patientName = data.patient ? `${data.patient.user.firstname} ${data.patient.user.surname}` : 'N/A';
    const fileNo = data.patient ? data.patient.file_no : 'N/A';
    const doctorName = data.doctor ? `${data.doctor.firstname} ${data.doctor.surname}` : 'N/A';

    const date = type === 'deleted'
        ? new Date(data.deleted_at).toLocaleString()
        : new Date(data.dismissed_at).toLocaleString();

    const reason = type === 'deleted' ? data.deletion_reason : data.dismiss_reason;

    const badgeClass = type === 'deleted' ? 'badge-danger' : 'badge-warning';
    const icon = type === 'deleted' ? 'fa-trash' : 'fa-ban';

    let html = `
        <div class="card-modern mb-2" style="border-left: 4px solid ${type === 'deleted' ? '#dc3545' : '#ffc107'};">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0">
                        <span class="badge ${badgeClass}">
                            <i class="fa ${icon}"></i> ${serviceName}
                        </span>
                    </h6>
                    <button class="btn btn-sm btn-success" onclick="restoreRequest(${data.id}, '${type}')">
                        <i class="fa fa-undo"></i> Restore
                    </button>
                </div>
                <small>
                    <div><strong>Patient:</strong> ${patientName} (${fileNo})</div>
                    <div><strong>Doctor:</strong> ${doctorName}</div>
                    <div><strong>${type === 'deleted' ? 'Deleted' : 'Dismissed'}:</strong> ${date}</div>
                    <div><strong>Reason:</strong> ${reason}</div>
                </small>
            </div>
        </div>
    `;

    return html;
}

function updateTrashTotalCount() {
    const dismissed = parseInt($('#dismissed-count').text()) || 0;
    const deleted = parseInt($('#deleted-count').text()) || 0;
    const total = dismissed + deleted;
    $('#trash-total-count').text(total);

    if (total> 0) {
        $('#trash-total-count').show();
    } else {
        $('#trash-total-count').hide();
    }
}

// Audit Log Management
let auditLogTable = null;

$('#openAuditLog').on('click', function() {
    $('#auditLogModal').modal('show');
    loadAuditLogs();
});

$('#applyAuditFilter').on('click', function() {
    loadAuditLogs();
});

function loadAuditLogs() {
    const filters = {
        action: $('#audit_action_filter').val(),
        from_date: $('#audit_from_date').val(),
        to_date: $('#audit_to_date').val()
    };

    if (currentPatient) {
        filters.patient_id = currentPatient;
    }

    if (auditLogTable) {
        auditLogTable.destroy();
    }

    auditLogTable = $('#audit-log-table').DataTable({
        ajax: {
            url: wbUrl('/imaging-workbench/audit-logs'),
            data: filters
        },
        columns: [
            {
                data: 'created_at',
                render: function(data) {
                    return new Date(data).toLocaleString();
                }
            },
            {
                data: 'user',
                render: function(data) {
                    return data ? `${data.firstname} ${data.surname}` : 'N/A';
                }
            },
            {
                data: 'action',
                render: function(data) {
                    const badges = {
                        'view': 'badge-info',
                        'edit': 'badge-warning',
                        'delete': 'badge-danger',
                        'restore': 'badge-success',
                        'dismiss': 'badge-warning',
                        'undismiss': 'badge-success',
                        'billing': 'badge-primary',
                        'result_entry': 'badge-success'
                    };
                    const badgeClass = badges[data] || 'badge-secondary';
                    return `<span class="badge ${badgeClass}">${data.toUpperCase()}</span>`;
                }
            },
            {
                data: 'description',
                render: function(data, type, row) {
                    let desc = data || 'No description';
                    if (row.new_values && row.new_values.reason) {
                        desc += `<br><small class="text-muted">Reason: ${row.new_values.reason}</small>`;
                    }
                    return desc;
                }
            },
            { data: 'ip_address' }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            emptyTable: "No audit logs found"
        }
    });
}

$('#exportAuditLog').on('click', function() {
    // TODO: Implement export to Excel functionality
    toastr.info('Export feature coming soon!');
});

// Load trash counts on page load
$(document).ready(function() {
    loadTrashData();

    // Refresh trash counts every 60 seconds
    setInterval(function() {
        if (!$('#trashPanel').is(':visible')) {
            const patientId = currentPatient || null;
            $.ajax({
                url: wbUrl('/imaging-workbench/dismissed-requests/' + (patientId || '')),
                method: 'GET',
                success: function(data) {
                    $('#dismissed-count').text(data.length);
                    updateTrashTotalCount();
                }
            });
            $.ajax({
                url: wbUrl('/imaging-workbench/deleted-requests/' + (patientId || '')),
                method: 'GET',
                success: function(data) {
                    $('#deleted-count').text(data.length);
                    updateTrashTotalCount();
                }
            });
        }
    }, 60000);
});

// ============================================
// ENHANCEMENT FUNCTIONS
// ============================================

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
    $('#reports-view').removeClass('active').hide();
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

    // Update queue title - No sample stage for imaging
    const titles = {
        'billing': '🟢 Awaiting Billing',
        'results': '🔴 Awaiting Result Entry',
        'approval': '🟣 Awaiting Approval',
        'all': '📋 All Pending Requests'
    };
    $('#queue-view-title').html(`<i class="mdi mdi-format-list-bulleted"></i> ${titles[filter] || titles['all']}`);

    if (filter === 'approval') {
        $('#approval-queue-hint').show();
    } else {
        $('#approval-queue-hint').hide();
    }

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

    // Map filter to status
    let filterMap = {
        'all': '1,2',
        'billing': '1',
        'results': '2',
        'approval': 'approval',
        'freeform': 'freeform'
    };
    let status = filterMap[filter] || '1,2';

    // Initialize DataTable for imaging queue
    queueDataTable = $('#queue-datatable').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: wbRoute('imaging.queue', '/imaging/queue'),
            data: { status: status }
        },
        columns: [
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    const card = row.card_data;
                    if (!card) return '<div class="text-danger p-2">Error loading data</div>';

                    // Status badge
                    let statusBadge = '';
                    let reverseBtn = '';
                    if (row.status == 1) {
                        statusBadge = '<span class="badge badge-warning">Awaiting Billing</span>';
                    } else if (row.status == 2) {
                        statusBadge = '<span class="badge badge-danger">Awaiting Results</span>';
                    } else if (row.status == 4 && card.approved_by) {
                        statusBadge = '<span class="badge badge-success"><i class="mdi mdi-check-circle"></i> Approved</span>';
                        if (card.approved_by == currentUserId) {
                            reverseBtn = `<button class="btn btn-sm btn-outline-warning reverse-approval-btn" data-request-id="${card.id}" style="margin-left: auto; font-size: 0.75rem; padding: 2px 8px;" title="Reverse this approval"><i class="mdi mdi-undo-variant"></i> Reverse</button>`;
                        }
                    } else if (row.status == 5) {
                        statusBadge = '<span class="badge badge-purple">Pending Approval</span>';
                    } else if (row.status == 6) {
                        statusBadge = '<span class="badge badge-danger"><i class="mdi mdi-close-circle"></i> Rejected</span>';
                    }

                    // Format Date
                    const dateStr = row.created_at ? new Date(row.created_at).toLocaleString() : '';
                    const serviceName = card.service_name || row.service_name || (row.service ? row.service.service_name : 'Imaging Request');
                    let tpBadge = '';
                    if (card.treatment_plan_id && card.treatment_plan_name) {
                        tpBadge = ` <br><a href="#" class="tp-view-link badge mt-1" style="background-color: #e0f2f1; color: #00796b; border: 1px solid #00897b; text-decoration: none;" onclick="ClinicalOrdersKit.viewTreatmentPlan(${card.treatment_plan_id}); event.stopPropagation(); return false;"><i class="fa fa-clipboard-list"></i> ${escapeHtml(card.treatment_plan_name)}</a>`;
                    }
                    const hmoText = card.hmo && card.hmo !== 'N/A' ? `<br><small><i class="mdi mdi-hospital-building"></i> ${escapeHtml(card.hmo)}</small>` : '';

                    return `
                        <div class="queue-patient-item" data-patient-id="${card.patient_id}" style="cursor: pointer; padding: 1rem; border-bottom: 1px solid #e9ecef;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="font-weight: 600; font-size: 1rem; color: #212529;">${escapeHtml(card.patient_name)}</div>
                                <span class="badge badge-primary">${card.file_no}</span>
                                ${statusBadge}
                                ${reverseBtn}
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.9rem; color: #6c757d;">
                                <i class="mdi mdi-radioactive"></i> ${escapeHtml(serviceName)}${tpBadge}
                                <br><small class="text-muted"><i class="mdi mdi-account-clock"></i> Req: ${escapeHtml(card.requested_by)} on ${card.requested_at}</small>
                                ${card.approved_at ? `<br><small class="text-muted"><i class="mdi mdi-clock-check-outline"></i> Approved: ${card.approved_at}</small>` : ''}
                                ${hmoText}
                            </div>
                            <div style="margin-top: 0.25rem; font-size: 0.8rem; color: #adb5bd;">
                                <i class="mdi mdi-clock-outline"></i> ${dateStr}
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
            emptyTable: "No imaging requests in this queue",
            zeroRecords: "No requests found",
            info: "Showing _START_ to _END_ of _TOTAL_ requests",
            infoEmpty: "No requests to show",
            infoFiltered: "(filtered from _MAX_ total requests)"
        }
    });

    // Click handler for patient selection from queue
    $('#queue-datatable').on('click', '.queue-patient-item', function() {
        const patientId = $(this).data('patient-id');
        hideQueue();
        loadPatient(patientId);
    });
}

// Update queue counts
function getQueueCounts() {
    $.ajax({
        url: wbRoute('imaging.queue-counts', '/imaging/queue-counts'),
        method: 'GET',
        success: function(data) {
            // Update counts in the queue widget
            $('#count-billing').text(data.billing || 0);
            $('#count-sample').text(data.sample || 0); // Might remain 0 as Imaging doesn't have sample
            $('#count-results').text(data.results || 0);

            // Also update the trash counts if they are part of the queue counts
            // or rely on the separate calls currently in setInterval
        },
        error: function(err) {
            console.error('Failed to fetch queue counts', err);
        }
    });
}

// Call getQueueCounts on load and every minute
$(document).ready(function() {
    getQueueCounts();
    setInterval(getQueueCounts, 60000);
});

// Search Imaging Services (New Request)
let imagingSearchTimer = null;

function searchImagingServices(q) {
    const $results = $('#service-search-results');
    const patientId = currentPatient ? currentPatient : null;

    if (typeof SearchManager !== 'undefined') {
        SearchManager.execute({
            inputVal: q,
            minLength: 2,
            delay: 300,
            url: wbUrl('live-search-services'),
            data: {
                term: q,
                category_id: window.WORKBENCH_CONFIG?.imagingCategoryId || 6,
                patient_id: patientId
            },
            onStart: function() {
                $results.html('<li class="list-group-item text-center text-muted"><i class="mdi mdi-loading mdi-spin"></i> Searching...</li>').show();
            },
            onSuccess: function(data) {
                renderImagingSearchResults(data, q);
            },
            onEmptyQuery: function() {
                $results.html('').hide();
            }
        });
    } else {
        clearTimeout(imagingSearchTimer);
        if (!q || q.trim().length < 2) {
            $results.html('').hide();
            return;
        }

        $results.html('<li class="list-group-item text-center text-muted"><i class="mdi mdi-loading mdi-spin"></i> Searching...</li>').show();
        imagingSearchTimer = setTimeout(function() {
            $.ajax({
                url: wbUrl('live-search-services'),
                method: "GET",
                dataType: 'json',
                data: {
                    term: q,
                    category_id: window.WORKBENCH_CONFIG?.imagingCategoryId || 6,
                    patient_id: patientId
                },
                success: function(data) {
                    renderImagingSearchResults(data, q);
                },
                error: function() {
                    $results.html('<li class="list-group-item text-center text-danger"><i class="mdi mdi-alert"></i> Search failed. Please try again.</li>').show();
                }
            });
        }, 300);
    }
}

function renderImagingSearchResults(data, q) {
    const $results = $('#service-search-results');
    $results.html('');
    
    // Inject Free-Form option at the top
    const freeFormHtml = `
        <li class="list-group-item list-group-item-action text-primary" onclick="addFreeFormImagingWorkbench()" style="cursor:pointer;">
            <i class="mdi mdi-plus-circle"></i> Not listed? Add free-form '${q}'
        </li>
    `;
    $results.append(freeFormHtml);

    if (!data || data.length === 0) {
        $results.append('<li class="list-group-item text-center text-muted"><i class="mdi mdi-alert-circle-outline"></i> No imaging services found for "' + q + '"</li>');
        $results.show();
        return;
    }
    for (var i = 0; i < data.length; i++) {
        const item = data[i] || {};
        const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
        const name = item.service_name || 'Unknown';
        const code = item.service_code || '';
        const price = item.price && item.price.sale_price !== undefined ? item.price.sale_price : 0;
        const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
        const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
        const mode = item.coverage_mode || null;
        const isCombo = item.is_combo || false;
        const bundleItems = item.bundle_items || [];
        if (isCombo) { window.comboDataMap = window.comboDataMap || {}; window.comboDataMap[item.id] = item; }

        const coverageBadge = mode && mode !== 'cash' ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ${payable}</span> <span class='text-success ms-1'>Claim: ${claims}</span>` : '';
        const comBoBadge = isCombo ? `<span class='badge bg-primary ms-1'>COMBO</span>` : '';
        const displayName = `${name}[${code}]`;

        const escapedDisplayName = displayName.replace(/'/g, "\'");
        const escapedId = (item.id + '').replace(/'/g, "\'");

        let mk = '';
        if (isCombo) {
            // Combo rendering with bundle items
            const bundleList = bundleItems.map(bi => `<li class="ms-3"><small>${bi.name || 'Unknown'} (${bi.qty || 1})</small></li>`).join('');
            mk = `<li class='list-group-item' style="background-color: #e8f4f8; cursor: pointer;">
                    <div onclick="ImagingWorkbench.applyCombo('${escapedId}', '${escapedDisplayName}')">
                        [${category}] <b>${name}[${code}]</b> ${comBoBadge} NGN ${Number(price).toLocaleString()} ${coverageBadge}
                        <div style="margin-top: 8px; font-size: 12px; color: #666; border-left: 2px solid #0066cc; padding-left: 8px;">
                            <strong>Includes:</strong>
                            <ul style="margin: 4px 0 0 0; padding-left: 20px; list-style: disc;">
                                ${bundleList}
                            </ul>
                        </div>
                    </div>
                 </li>`;
        } else {
            // Direct service rendering
            mk = `<li class='list-group-item'
                   style="background-color: #f0f0f0; cursor: pointer;"
                   onclick="setSearchValImaging('${escapedDisplayName}', '${escapedId}', '${price}', '${mode}', '${claims}', '${payable}')">
                   [${category}] <b>${name}[${code}]</b> NGN ${Number(price).toLocaleString()} ${coverageBadge}</li>`;
        }
        $results.append(mk);
    }
    $results.show();
}

function setSearchValImaging(name, id, price, coverageMode = null, claims = null, payable = null) {
    const coverageBadge = coverageMode && coverageMode !== 'null' ? `<div class="small mt-1"><span class="badge bg-info">${coverageMode.toUpperCase()}</span> <span class="text-danger">Pay: ${payable ?? price}</span> <span class="text-success">Claims: ${claims ?? 0}</span></div>` : '';

    // Ensure coverageMode is not 'null' string
    if (coverageMode === 'null') coverageMode = null;

    var mk = `
        <tr>
            <td>${name}${coverageBadge}</td>
            <td>${payable ?? price}</td>
            <td>
                <input type='text' class='form-control' name='consult_imaging_note[]' placeholder='Optional note'>
                <input type='hidden' name='consult_imaging_id[]' value='${id}'>
            </td>
            <td><button type="button" class='btn btn-danger btn-sm' onclick="removeProdRow(this)"><i class="fa fa-times"></i></button></td>
        </tr>
    `;

    $('#selected-imaging-services').append(mk);
    $('#service-search-results').html('').hide();
    $('#service-search-input').val('');
}

// Namespace for imaging workbench combo operations
const ImagingWorkbench = {
    applyCombo: function(comboId, comboName) {
        var comboData = (window.comboDataMap || {})[comboId] || {};
        var name = comboName || comboData.service_name || 'Combo';
        var bundleItems = comboData.bundle_items || [];
        var price   = parseFloat(comboData.base_price  || 0);
        var payable = parseFloat(comboData.payable_amount != null ? comboData.payable_amount : price);
        var claims  = parseFloat(comboData.claims_amount  || 0);
        var mode    = comboData.coverage_mode || null;

        ComboConfirmModal.show({
            name        : name,
            bundleItems : bundleItems,
            price       : price,
            payable     : payable,
            claims      : claims,
            mode        : mode,
            onConfirm   : function() {
                $.ajax({
                    url         : '/imaging-workbench/clinical-requests/apply-combo',
                    method      : 'POST',
                    headers     : { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data        : JSON.stringify({ service_id: comboId, patient_id: currentPatient, note: '' }),
                    contentType : 'application/json',
                    dataType    : 'json',
                    success     : function(response) {
                        if (response.success) {
                            toastr.success(response.message, 'Combo Applied');
                            $('#service-search-results').html('').hide();
                            $('#service-search-input').val('');
                            if (typeof loadImagingServices === 'function') loadImagingServices();
                            if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
                                $('#investigation_history_list').DataTable().ajax.reload(null, false);
                            }
                        } else {
                            toastr.error(response.message || 'Failed to apply combo', 'Error');
                        }
                    },
                    error       : function(err) {
                        toastr.error((err.responseJSON && err.responseJSON.message) ? err.responseJSON.message : ('Error: ' + err.statusText), 'Error');
                    }
                });
            }
        });
    }
};

function removeProdRow(btn) {
    $(btn).closest('tr').remove();
}

// ==========================================
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
        ? '<span class=\"badge bg-danger me-2\"><i class=\"fa fa-bolt\"></i> EMERGENCY</span>'
        : (data.priority === 'urgent' ? '<span class=\"badge bg-warning text-dark me-2\">Urgent</span>' : '');

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

    // No sample stage for imaging - skip directly to result status

    // Result - for imaging, status 2 = awaiting results, status 4 = completed
    if (data.result_by && data.result_at) {
        badges += `
            <div class="queue-card-status-item completed">
                <div class="queue-card-status-label"><i class="mdi mdi-flask"></i> Result</div>
                <div class="queue-card-status-value">${data.result_by}<br><small>${data.result_at}</small></div>
            </div>
        `;
    } else if (data.status>= 2) {
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
    }    switchWorkspaceTab('new-request');
    $('#new-request-patient-name').text(currentPatientData ? currentPatientData.name : '');
});

// New Imaging Request Form Submit Handler
$('#new-imaging-request-form').on('submit', function(e) {
    e.preventDefault();

    // Collect selected service IDs
    const serviceIds = [];
    const notes = [];
    $('#selected-imaging-services tr').each(function() {
        const serviceId = $(this).find('input[name="consult_imaging_id[]"]').val();
        const note = $(this).find('input[name="consult_imaging_note[]"]').val();
        if (serviceId) {
            serviceIds.push(serviceId);
            notes.push(note || '');
        }
    });

    if (serviceIds.length === 0) {
        toastr.warning('Please select at least one imaging service');
        return;
    }
    if (!currentPatient) {
        toastr.error('No patient selected');
        return;
    }
    const $submitBtn = $(this).find('button[type="submit"]');
    const originalBtnHtml = $submitBtn.html();
    $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Submitting...');

    $.ajax({
        url: wbRoute('imaging.createRequest', '/imaging/createRequest'),
        method: 'POST',
        data: {
            patient_id: currentPatient,
            service_ids: serviceIds,
            notes: notes,
            clinical_notes: $('#request-clinical-notes').val(),
            special_instructions: $('#request-special-instructions').val(),
            urgency: $('#request-urgency').val(),
            priority: $('#request-priority').val(),
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                // Clear the form
                $('#selected-imaging-services').empty();
                $('#request-clinical-notes').val('');
                $('#request-special-instructions').val('');
                $('#request-urgency').val('routine');
                $('#request-priority').val('normal');
                // Refresh patient data and queue counts
                loadPatient(currentPatient);
                getQueueCounts();
                // Switch to pending tab
                switchWorkspaceTab('pending');
            } else {
                toastr.error(response.message || 'Failed to create request');
            }
        },
        error: function(xhr) {
            const message = xhr.responseJSON?.message || 'Error creating imaging request';
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
        2: { name: 'Awaiting Results', color: '#17a2b8' },
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

    $.get(`/imaging-workbench/approval/${requestId}`, function(response) {
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

function approveImagingResult() {
    if (!currentApprovalId) return;
    $('#approvalReviewModal').modal('hide');
    $('#approveConfirmModal').modal('show');
}

function confirmApproveImagingResult() {
    if (!currentApprovalId) return;

    $('#btn-confirm-approve').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Approving...');

    $.ajax({
        url: wbUrl('/imaging-workbench/approval/' + currentApprovalId + '/approve'),
        type: 'POST',
        data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')},
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

function reverseImagingApproval(requestId) {
    currentReverseId = requestId;
    $('#reverseApprovalModal').modal('show');
}

function confirmReverseImagingApproval() {
    if (!currentReverseId) return;

    $('#btn-confirm-reverse').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Reversing...');

    $.ajax({
        url: wbUrl('/imaging-workbench/approval/' + currentReverseId + '/reverse'),
        type: 'POST',
        data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')},
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

function rejectImagingResult() {
    if (!currentApprovalId) return;
    $('#approvalReviewModal').modal('hide');
    $('#rejection_reason').val('');
    $('#rejectionReasonModal').modal('show');
}

function confirmRejectImagingResult() {
    const reason = $('#rejection_reason').val().trim();
    if (!reason) {
        toastr.error('Please provide a rejection reason.');
        return;
    }
    $.ajax({
        url: wbUrl('/imaging-workbench/approval/' + currentApprovalId + '/reject'),
        type: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')},
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
    approveImagingResult();
});

$(document).on('click', '#btn-confirm-approve', function() {
    confirmApproveImagingResult();
});

$(document).on('click', '.reverse-approval-btn', function(e) {
    e.stopPropagation();
    const requestId = $(this).data('request-id');
    reverseImagingApproval(requestId);
});

$(document).on('click', '#btn-confirm-reverse', function() {
    confirmReverseImagingApproval();
});

$(document).on('click', '#btn-reject-result', function() {
    rejectImagingResult();
});

$(document).on('submit', '#rejectionReasonForm', function(e) {
    e.preventDefault();
    confirmRejectImagingResult();
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
    window.patientFormConfig.hmos = window.WORKBENCH_CONFIG?.hmos || [];


    // Show modal first
    showPatientFormModal('create');

    // After modal is shown, enable walk-in mode
    $('#patientFormModal').one('shown.bs.modal', function() {
        enableWalkInMode('IMG-');
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
