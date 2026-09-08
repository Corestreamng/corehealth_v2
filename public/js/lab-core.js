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

// Global state
let currentPatient = null;
let currentPatientData = null; // Store full patient data including allergies
let queueRefreshInterval = null;
let vitalTooltip = null;
const isApprover = (window.WORKBENCH_CONFIG?.isApprover || false);
const requiresApproval = (window.WORKBENCH_CONFIG?.requiresApproval || false);
const currentUserId = (window.CURRENT_USER_ID || null);
let currentApprovalId = null;

var _PI_LAB_REQ_APPROVAL = '';
var _PI_DR_SELF_LAB      = '';
var _PI_NR_SELF_LAB      = '';

function _autoApproveIfEnabled(requestId, type) {
    if ($('#invest_res_is_edit').val() == '1') { return; }
    if (!_PI_LAB_REQ_APPROVAL) { return; }
    if (!_PI_DR_SELF_LAB && !_PI_NR_SELF_LAB) { return; }
    $.post('/lab-workbench/self-approve/' + requestId, { _token: $('meta[name="csrf-token"]').attr('content') })
        .done(function (res) {
            if (res && res.success) { toastr.success('Result approved automatically.'); }
            else { toastr.warning('Result saved. Auto-approval failed: ' + ((res && res.message) || '')); }
        })
        .fail(function () { toastr.warning('Result saved but auto-approval could not be completed.'); });
}

$(document).ready(function() {
    // Initialize shared result entry module
    InvestResultEntry.bindFormSubmit(function() {
        if (currentPatient) loadPatient(currentPatient);
        var ctx = window._investResultContext;
        if (ctx) { _autoApproveIfEnabled(ctx.id, ctx.type); window._investResultContext = null; }
    });

    // Initialize
    loadQueueCounts();
    startQueueRefresh();
    initializeEventListeners();
    loadUserPreferences();
    createVitalTooltip();

    // Auto-select patient from URL query parameter (e.g., from Patient list workbench button)
    const urlParams = new URLSearchParams(window.location.search);
    const patientId = urlParams.get('patient_id');
    if (patientId) {
        loadPatient(patientId);
    }

    // Auto-open queue from URL parameter (e.g., from dashboard queue widget click)
    const queueFilter = urlParams.get('queue_filter');
    if (queueFilter && ['billing', 'sample', 'results', 'completed'].includes(queueFilter)) {
        setTimeout(function() { showQueue(queueFilter); }, 500);
    }
});

function initializeEventListeners() {
    // Patient search (shared module)
    PatientSearch.init();

    // Workspace tabs
    $('.workspace-tab').on('click', function() {
        const tab = $(this).data('tab');
        switchWorkspaceTab(tab);
    });

    // Pending sub-tabs
    $('.pending-subtab').on('click', function() {
        const status = $(this).data('status');
        $('.pending-subtab').removeClass('active');
        $(this).addClass('active');
        renderPendingSubtabContent(status);
    });

    // Navigation buttons
    $('#btn-back-to-search').on('click', function() {
        // Mobile: go back to search pane
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    });

    $('#btn-view-work-pane').on('click', function() {
        // Mobile: switch to work pane without selecting a patient
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    });

    $('#btn-toggle-search').on('click', function() {
        // Desktop/Tablet: toggle search pane visibility
        $('#left-panel').toggleClass('hidden');
    });

    $('#btn-clinical-context').on('click', function() {
        // Open clinical context modal with shared module
        if (currentPatient) {
            ClinicalContext.load(currentPatient);
        } else {
            $('#clinical-context-modal').modal('show');
        }
    });

    // Clinical modal refresh buttons
    $('.refresh-clinical-btn').on('click', function() {
        const panel = $(this).data('panel');
        refreshClinicalPanel(panel);
    });

    // Clinical panel collapse (legacy - keeping for compatibility)
    $('.clinical-panel-header').on('click', function(e) {
        if (!$(e.target).closest('.clinical-panel-actions').length) {
            $(this).next('.clinical-panel-body').slideToggle(200);
            $(this).find('.collapse-btn i').toggleClass('fa-chevron-up fa-chevron-down');
        }
    });

    // Queue filter buttons
    $('.queue-item').on('click', function() {
        const filter = $(this).data('filter');
        showQueue(filter);
    });

    // Show all queue button
    $('#show-all-queue-btn, #view-queue-btn').on('click', function() {
        showQueue('all');
    });

    // Close queue button
    $('#btn-close-queue').on('click', function() {
        hideQueue();
    });

    // Apply queue date filter
    $('#apply-queue-filter').on('click', function() {
        if (currentQueueFilter) {
            initializeQueueDataTable(currentQueueFilter);
        }
    });

    // Reset queue date filter
    $('#reset-queue-filter').on('click', function() {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        
        const formatDate = (d) => {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        };
        
        $('#queue-start-date').val(formatDate(firstDay));
        $('#queue-end-date').val(formatDate(lastDay));
        if (currentQueueFilter) {
            initializeQueueDataTable(currentQueueFilter);
        }
    });

    // ── Sample Collection Modal Buttons ──────────────────────────────
    $('#btnAutoLabNumber').on('click', function() {
        autoGenerateLabNumber();
    });

    $('#btnManualLabNumber').on('click', function() {
        $('#sample_lab_number').prop('readonly', false).val('').focus();
        $('#labNumberFormatHint').text('Enter lab number manually');
        $('#btnConfirmCollectSample').prop('disabled', false);
    });

    $('#sample_lab_number').on('input', function() {
        let val = $(this).val().trim();
        $('#labNumberError').addClass('d-none');
        $('#labNumberDuplicateWarning').addClass('d-none');
        $('#btnConfirmCollectSample').prop('disabled', val.length === 0);

        // Check for duplicates after typing stops
        clearTimeout(window._labNumCheckTimeout);
        if (val.length> 0) {
            window._labNumCheckTimeout = setTimeout(function() {
                $.get(wbRoute('lab.checkLabNumber', '/lab-workbench/check-lab-number'), { lab_number: val }, function(data) {
                    if (data.exists) {
                        let warn = 'This lab number is already used by ' + data.count + ' item(s)';
                        if (data.items && data.items.length> 0) {
                            warn += ': ' + data.items.map(i => i.service_name).join(', ');
                        }
                        $('#labNumberDuplicateWarning').removeClass('d-none').text(warn);
                    }
                });
            }, 500);
        }
    });

    $('#btnConfirmCollectSample').on('click', function() {
        confirmCollectSample();
    });
}

function loadPatient(patientId) {
    currentPatient = patientId;

    // Hide all views to prevent stacking
    hideAllViews();

    // Show patient workspace
    $('#workspace-content').show().addClass('active');
    $('#patient-header').addClass('active');

    // Mobile: Switch to work pane
    $('#left-panel').addClass('hidden');
    $('#main-workspace').addClass('active');

    // Update quick actions visibility
    updateQuickActions();

    // Load patient requests
    $.ajax({
        url: wbUrl(`/lab-workbench/patient/${patientId}/requests`),
        method: 'GET',
        success: function(data) {
            currentPatientData = data.patient; // Store patient data including allergies
            displayPatientInfo(data.patient);

            // Initialize Clinical Alerts
            try { 
                $('#btn-manage-alerts').show();
                if(typeof ClinicalAlerts !== 'undefined') {
                    ClinicalAlerts.init(patientId, 'lab_imaging');
                }
            } catch(e) { console.error('ClinicalAlerts init error:', e); }

            displayPendingRequests(data.requests);

            // Initialize history DataTable
            initializeHistoryDataTable(patientId);

            // Initialize procedures DataTable
            initializeProceduresDataTable(patientId);
        },
        error: function() {
            toastr.error('Failed to load patient data');
        }
    });
}

function initializeHistoryDataTable(patientId) {
    if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
        $('#investigation_history_list').DataTable().destroy();
    }

    $('#investigation_history_list').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        ajax: {
            url: wbUrl(`/investigationHistoryList/${patientId}`),
            type: 'GET'
        },
        columns: [
            {
                data: "info",
                name: "info",
                orderable: false,
                searchable: true
            }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            emptyTable: "No investigation history found for this patient",
            processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
        },
        drawCallback: function() {
            // Add click handler for view result buttons
            $('.view-invest-result-btn').off('click').on('click', function() {
                const requestId = $(this).data('request-id');
                viewInvestigationResult(requestId);
            });
        }
    });
}

function viewInvestigationResult(requestId) {
    // Open modal to view completed result
    $.ajax({
        url: wbUrl(`/lab-workbench/lab-service-requests/${requestId}`),
        method: 'GET',
        success: function(request) {
            // Show result in a view-only modal or open in new tab
            if (request.result_document) {
                window.open(request.result_document, '_blank');
            } else {
                toastr.warning('No result document found');
            }
        },
        error: function(xhr) {
            toastr.error('Error loading result: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}

function initializeProceduresDataTable(patientId) {
    if ($.fn.DataTable.isDataTable('#procedures_history_list')) {
        $('#procedures_history_list').DataTable().destroy();
    }

    $('#procedures_history_list').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        ajax: {
            url: wbUrl(`/patient-procedures/list-by-patient/${patientId}`),
            type: 'GET'
        },
        columns: [
            {
                data: "info",
                name: "info",
                orderable: false,
                searchable: true
            }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            emptyTable: "No procedures found for this patient",
            processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
        }
    });
}

function displayPatientInfo(patient) {
    $('#patient-name').text(`${patient.name} (#${patient.file_no})`);
    $('#patient-meta').html(`
        <div class="patient-meta-item">
            <i class="mdi mdi-account"></i>
            <span>${patient.age} ${patient.gender}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-water"></i>
            <span>${patient.blood_group} ${patient.genotype !== 'N/A' ? '(' + patient.genotype + ')' : ''}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-phone"></i>
            <span>${patient.phone}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-hospital-building"></i>
            <span>${patient.hmo} ${patient.hmo_category !== 'N/A' ? '[' + patient.hmo_category + ']' : ''} ${patient.hmo_no !== 'N/A' ? '(' + patient.hmo_no + ')' : ''}</span>
        </div>
    `);

    // Build detailed information grid - show ALL fields
    let detailsHtml = '';

    // Age (detailed)
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-calendar-clock"></i> Age</div>
            <div class="patient-detail-value">${patient.age}</div>
        </div>
    `;

    // Gender
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-gender-male-female"></i> Gender</div>
            <div class="patient-detail-value">${patient.gender}</div>
        </div>
    `;

    // Blood Group
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-water"></i> Blood Group</div>
            <div class="patient-detail-value">${patient.blood_group}</div>
        </div>
    `;

    // Genotype
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-dna"></i> Genotype</div>
            <div class="patient-detail-value">${patient.genotype}</div>
        </div>
    `;

    // Phone
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-phone"></i> Phone Number</div>
            <div class="patient-detail-value">${patient.phone}</div>
        </div>
    `;

    // Address
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-map-marker"></i> Address</div>
            <div class="patient-detail-value">${patient.address}</div>
        </div>
    `;

    // Nationality
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-flag"></i> Nationality</div>
            <div class="patient-detail-value">${patient.nationality}</div>
        </div>
    `;

    // Ethnicity
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-account-group"></i> Ethnicity</div>
            <div class="patient-detail-value">${patient.ethnicity}</div>
        </div>
    `;

    // Disability Status
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-wheelchair-accessibility"></i> Disability</div>
            <div class="patient-detail-value">${patient.disability}</div>
        </div>
    `;

    // HMO
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-hospital-building"></i> HMO</div>
            <div class="patient-detail-value">${patient.hmo}</div>
        </div>
    `;

    // HMO Category
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-tag"></i> HMO Category</div>
            <div class="patient-detail-value">${patient.hmo_category}</div>
        </div>
    `;

    // HMO Number
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-card-account-details"></i> HMO Number</div>
            <div class="patient-detail-value">${patient.hmo_no}</div>
        </div>
    `;

    // Insurance Scheme
    detailsHtml += `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-shield-account"></i> Insurance Scheme</div>
            <div class="patient-detail-value">${patient.insurance_scheme}</div>
        </div>
    `;

    // Allergies - handle array, comma-separated string, JSON string, object, or null
    let allergiesArray = [];
    if (patient.allergies) {
        if (Array.isArray(patient.allergies)) {
            allergiesArray = patient.allergies;
        } else if (typeof patient.allergies === 'string') {
            try {
                const parsed = JSON.parse(patient.allergies);
                allergiesArray = Array.isArray(parsed) ? parsed : (parsed ? [parsed] : []);
            } catch(e) {
                allergiesArray = patient.allergies.split(',').map(a => a.trim()).filter(a => a);
            }
        } else if (typeof patient.allergies === 'object') {
            allergiesArray = Object.values(patient.allergies).filter(a => a);
        }
    }

    if (allergiesArray.length> 0) {
        const allergiesList = allergiesArray.map(allergy =>
            `<span class="allergy-tag"><i class="mdi mdi-alert"></i> ${allergy}</span>`
        ).join('');
        detailsHtml += `
            <div class="patient-detail-item full-width">
                <div class="patient-detail-label"><i class="mdi mdi-alert-circle"></i> Allergies</div>
                <div class="patient-detail-value">
                    <div class="allergies-list">${allergiesList}</div>
                </div>
            </div>
        `;
    } else {
        detailsHtml += `
            <div class="patient-detail-item full-width">
                <div class="patient-detail-label"><i class="mdi mdi-alert-circle"></i> Allergies</div>
                <div class="patient-detail-value">No known allergies</div>
            </div>
        `;
    }

    // Medical History
    detailsHtml += `
        <div class="patient-detail-item full-width">
            <div class="patient-detail-label"><i class="mdi mdi-clipboard-text"></i> Medical History</div>
            <div class="patient-detail-value text-content">${patient.medical_history}</div>
        </div>
    `;

    // Miscellaneous Notes
    detailsHtml += `
        <div class="patient-detail-item full-width">
            <div class="patient-detail-label"><i class="mdi mdi-note-text"></i> Additional Notes</div>
            <div class="patient-detail-value text-content">${patient.misc}</div>
        </div>
    `;

    $('#patient-details-grid').html(detailsHtml);

    // Toggle expand/collapse functionality
    $('#btn-expand-patient').off('click').on('click', function() {
        $(this).toggleClass('expanded');
        $('#patient-details-expanded').toggleClass('show');
    });
}

let currentPendingRequests = null;
let currentPendingFilter = 'all';

// ── Selection Preservation State (pharmacy-style) ────────────────────
let checkedItemsState = {
    billing: new Set(),
    sample: new Set()
};

function saveCheckedItemsState(section) {
    checkedItemsState[section] = new Set();
    $(`.request-checkbox[data-section="${section}"]:checked`).each(function() {
        checkedItemsState[section].add($(this).data('request-id'));
    });
}

function restoreCheckedItemsState() {
    Object.keys(checkedItemsState).forEach(section => {
        if (checkedItemsState[section].size === 0) return;

        checkedItemsState[section].forEach(id => {
            const $cb = $(`.request-checkbox[data-section="${section}"][data-request-id="${id}"]`);
            if ($cb.length) {
                $cb.prop('checked', true);
                $cb.closest('.request-card').addClass('selected');
            } else {
                // Item no longer in DOM — remove from state
                checkedItemsState[section].delete(id);
            }
        });

        // Sync select-all checkbox
        const total = $(`.request-checkbox[data-section="${section}"]`).length;
        const checked = $(`.request-checkbox[data-section="${section}"]:checked`).length;
        $(`#select-all-${section}`).prop('checked', total> 0 && checked === total);
    });

    // Refresh floating cart to reflect restored selections
    updateFloatingCart();
}

function clearCheckedItems(section) {
    if (section) {
        checkedItemsState[section] = new Set();
    } else {
        Object.keys(checkedItemsState).forEach(k => checkedItemsState[k] = new Set());
    }
}

function hasActiveSelections() {
    return Object.values(checkedItemsState).some(s => s.size> 0);
}
// ── End Selection Preservation State ─────────────────────────────────

function displayPendingRequests(requests) {
    currentPendingRequests = requests;
    const approvalItems = (requests.pending_approval || []).length + (requests.rejected || []).length;
    const totalPending = requests.billing.length + requests.sample.length + requests.results.length + approvalItems;
    $('#pending-badge').text(totalPending);

    // Store pending results for bulk entry
    window._pendingResultRequests = requests.results || [];
    window._bulkResultConfig = {
        fetchUrlPattern: '/lab-workbench/lab-service-requests/{id}',
        attachUrlPattern: '/lab-workbench/lab-service-requests/{id}/attachments',
        saveUrl: wbRoute('lab.saveResult', '/lab-workbench/save-result'),
        csrfToken: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
        onAllSaved: function() { if (currentPatient) loadPatient(currentPatient); }
    };

    updatePendingSubtabBadges(requests);
    renderPendingSubtabContent(currentPendingFilter);
}

function updatePendingSubtabBadges(requests) {
    const approvalItems = (requests.pending_approval || []).length + (requests.rejected || []).length;
    const totalPending = requests.billing.length + requests.sample.length + requests.results.length + approvalItems + (requests.freeform || []).length;
    $('#all-pending-badge').text(totalPending);
    $('#billing-subtab-badge').text(requests.billing.length);
    $('#sample-subtab-badge').text(requests.sample.length);
    $('#results-subtab-badge').text(requests.results.length);
    $('#approval-subtab-badge').text(approvalItems);
    $('#freeform-subtab-badge').text((requests.freeform || []).length);
}

function renderPendingSubtabContent(filter) {
    if (!currentPendingRequests) return;

    currentPendingFilter = filter;
    
    // Sync active tab UI
    $('.pending-subtab').removeClass('active');
    $(`.pending-subtab[data-status="${filter}"]`).addClass('active');

    const requests = currentPendingRequests;
    const approvalItems = (requests.pending_approval || []).length + (requests.rejected || []).length;
    const totalPending = requests.billing.length + requests.sample.length + requests.results.length + approvalItems + (requests.freeform || []).length;

    const $container = $('#pending-subtab-container');
    $container.empty();

    if (totalPending === 0) {
        $container.html('<div class="alert alert-info">No pending lab requests for this patient</div>');
        return;
    }

    // Freeform Section
    const freeformItems = requests.freeform || [];
    if ((filter === 'all' || filter === 'freeform') && freeformItems.length > 0) {
        const freeformHtml = `
            <div class="request-section" data-section="freeform">
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-file-document-edit text-dark fw-bold"></i>
                        <span class="text-dark fw-bold">Free-Form / External (${freeformItems.length})</span>
                    </h5>
                </div>
                <div class="request-cards-container" id="freeform-cards"></div>
            </div>
        `;
        $container.append(freeformHtml);

        freeformItems.forEach(request => {
            $('#freeform-cards').append(createRequestCard(request, 'freeform'));
        });
    }

    // Billing Section (Status 1)
    if ((filter === 'all' || filter === 'billing') && requests.billing.length> 0) {
        const billingHtml = `
            <div class="request-section" data-section="billing">
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-cash-register"></i>
                        Awaiting Billing (${requests.billing.length})
                    </h5>
                </div>
                <div class="request-cards-container" id="billing-cards"></div>
                <div class="section-actions-footer">
                    <div class="select-all-container">
                        <input type="checkbox" id="select-all-billing" class="select-all-checkbox">
                        <label for="select-all-billing">Select All</label>
                    </div>
                    <div class="action-buttons">
                        <span class="text-muted small"><i class="mdi mdi-cart-outline"></i> Actions in cart</span>
                    </div>
                </div>
            </div>
        `;
        $container.append(billingHtml);

        requests.billing.forEach(request => {
            $('#billing-cards').append(createRequestCard(request, 'billing'));
        });
    }

    // Sample Section (Status 2)
    if ((filter === 'all' || filter === 'sample') && requests.sample.length> 0) {
        const sampleHtml = `
            <div class="request-section" data-section="sample">
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-test-tube"></i>
                        Sample Collection (${requests.sample.length})
                    </h5>
                </div>
                <div class="request-cards-container" id="sample-cards"></div>
                <div class="section-actions-footer">
                    <div class="select-all-container">
                        <input type="checkbox" id="select-all-sample" class="select-all-checkbox">
                        <label for="select-all-sample">Select All</label>
                    </div>
                    <div class="action-buttons">
                        <span class="text-muted small"><i class="mdi mdi-cart-outline"></i> Actions in cart</span>
                    </div>
                </div>
            </div>
        `;
        $container.append(sampleHtml);

        requests.sample.forEach(request => {
            $('#sample-cards').append(createRequestCard(request, 'sample'));
        });
    }

    // Results Section (Status 3)
    if ((filter === 'all' || filter === 'results') && requests.results.length> 0) {
        const resultsHtml = `
            <div class="request-section" data-section="results">
                <div class="request-section-header d-flex justify-content-between align-items-center">
                    <h5>
                        <i class="mdi mdi-file-document-edit"></i>
                        Result Entry (${requests.results.length})
                    </h5>
                    ${requests.results.length> 1 ? `
                    <button class="btn btn-sm btn-primary" onclick="openBulkResultEntry()">
                        <i class="mdi mdi-file-multiple"></i> Bulk Result Entry
                    </button>` : ''}
                </div>
                <div class="request-cards-container" id="results-cards"></div>
                <div class="section-actions-footer">
                    <div class="select-all-container">
                        <span class="text-muted"><i class="mdi mdi-information"></i> Results can be entered individually or in bulk</span>
                    </div>
                </div>
            </div>
        `;
        $container.append(resultsHtml);

        requests.results.forEach(request => {
            $('#results-cards').append(createRequestCard(request, 'results'));
        });
    }

    // Pending Approval Section (Status 5)
    const pendingApproval = requests.pending_approval || [];
    if ((filter === 'all' || filter === 'approval') && pendingApproval.length> 0) {
        const approvalHtml = `
            <div class="request-section" data-section="approval">
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-check-decagram" style="color: #6f42c1;"></i>
                        <span style="color: #6f42c1;">Pending Approval (${pendingApproval.length})</span>
                    </h5>
                </div>
                <div class="request-cards-container" id="approval-cards"></div>
            </div>
        `;
        $container.append(approvalHtml);

        pendingApproval.forEach(request => {
            $('#approval-cards').append(createRequestCard(request, 'approval'));
        });
    }

    // Rejected Section (Status 6)
    const rejectedItems = requests.rejected || [];
    if ((filter === 'all' || filter === 'approval') && rejectedItems.length> 0) {
        const rejectedHtml = `
            <div class="request-section" data-section="rejected">
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-close-circle" style="color: #dc3545;"></i>
                        <span style="color: #dc3545;">Rejected - Needs Correction (${rejectedItems.length})</span>
                    </h5>
                </div>
                <div class="request-cards-container" id="rejected-cards"></div>
            </div>
        `;
        $container.append(rejectedHtml);

        rejectedItems.forEach(request => {
            $('#rejected-cards').append(createRequestCard(request, 'rejected'));
        });
    }


    // Initialize event handlers + restore preserved selections
    initializeRequestHandlers();
    restoreCheckedItemsState();
}

function createRequestCard(request, section) {
    let serviceName = request.service_name || request.service?.service_name || 'Unknown Service';
    if (request.treatment_plan_id && request.treatment_plan_name) {
        serviceName += ` <br><a href="#" class="tp-view-link badge mt-1" style="background-color: #e0f2f1; color: #00796b; border: 1px solid #00897b; text-decoration: none;" onclick="ClinicalOrdersKit.viewTreatmentPlan(${request.treatment_plan_id}); event.stopPropagation(); return false;"><i class="fa fa-clipboard-list"></i> ${escapeHtml(request.treatment_plan_name)}</a>`;
    }
    const doctorName = request.doctor ? (request.doctor.firstname + ' ' + request.doctor.surname) : 'N/A';
    const requestDate = formatDateTime(request.created_at);
    const note = request.note || '';
    
    let freeFormBadge = '';
    let isFreeForm = request.is_free_form == 1 || request.is_free_form === true;
    if (isFreeForm) {
        freeFormBadge = `<span class="badge bg-secondary text-white ms-2" style="font-size:0.75rem;">[Free-form]</span>`;
    }

    const price = parseFloat(request.service?.price_assign || 0);
    const payableAmount = parseFloat(request.payable_amount || 0);
    const claimsAmount = parseFloat(request.claims_amount || 0);
    const coverageMode = request.coverage_mode || '';
    const isPaid = request.is_paid || false;
    const isValidated = request.is_validated || false;
    const validationStatus = request.validation_status || '';

    const hasNote = note && note.trim() !== '';
    const noteHtml = hasNote ? `<div class="request-note"><i class="mdi mdi-note-text"></i> ${note}</div>` : '';

    // --- Status badges & border-left per stage (pharmacy-style) ---
    let statusBadges = '';
    let borderStyle = '';
    let pendingAlerts = '';

    if (section === 'billing') {
        statusBadges = '<span class="request-status-badge status-billing">Unbilled</span>';
        borderStyle = 'border-left: 4px solid #ffc107;';
    } else if (section === 'sample') {
        statusBadges = '<span class="request-status-badge status-sample">Sample Pending</span>';
        borderStyle = 'border-left: 4px solid #0d6efd;';

        // Payment status (billed items)
        if (payableAmount> 0 && !isPaid) {
            statusBadges += ' <span class="badge bg-danger">Awaiting Payment</span>';
            pendingAlerts += `<div class="alert alert-danger py-2 px-3 mb-2 mt-2" style="font-size: 0.85rem;">
                <i class="mdi mdi-cash-clock"></i> <strong>Payment Required:</strong> ₦${Number(payableAmount).toLocaleString()}</div>`;
        } else if (payableAmount> 0 && isPaid) {
            statusBadges += ' <span class="badge bg-success"><i class="mdi mdi-check"></i> Paid</span>';
        }
        // HMO validation status
        if (claimsAmount> 0 && (!validationStatus || validationStatus === 'pending')) {
            statusBadges += ' <span class="badge bg-info">Awaiting HMO Validation</span>';
            pendingAlerts += `<div class="alert alert-info py-2 px-3 mb-2 mt-2" style="font-size: 0.85rem;">
                <i class="mdi mdi-shield-alert"></i> <strong>HMO Validation Required:</strong> ₦${Number(claimsAmount).toLocaleString()} claim pending</div>`;
        } else if (claimsAmount> 0 && validationStatus === 'rejected') {
            statusBadges += ' <span class="badge bg-danger"><i class="mdi mdi-close"></i> HMO Rejected</span>';
        } else if (claimsAmount> 0 && isValidated) {
            statusBadges += ' <span class="badge bg-success"><i class="mdi mdi-check"></i> HMO OK</span>';
        }
    } else if (section === 'results') {
        statusBadges = '<span class="request-status-badge status-results">Awaiting Result</span>';
        borderStyle = 'border-left: 4px solid #198754;';

        // Payment status
        if (payableAmount> 0 && !isPaid) {
            statusBadges += ' <span class="badge bg-danger">Awaiting Payment</span>';
            pendingAlerts += `<div class="alert alert-danger py-2 px-3 mb-2 mt-2" style="font-size: 0.85rem;">
                <i class="mdi mdi-cash-clock"></i> <strong>Payment Required:</strong> ₦${Number(payableAmount).toLocaleString()}</div>`;
        } else if (payableAmount> 0 && isPaid) {
            statusBadges += ' <span class="badge bg-success"><i class="mdi mdi-check"></i> Paid</span>';
        }
        // HMO validation status
        if (claimsAmount> 0 && (!validationStatus || validationStatus === 'pending')) {
            statusBadges += ' <span class="badge bg-info">Awaiting HMO Validation</span>';
            pendingAlerts += `<div class="alert alert-info py-2 px-3 mb-2 mt-2" style="font-size: 0.85rem;">
                <i class="mdi mdi-shield-alert"></i> <strong>HMO Validation Required:</strong> ₦${Number(claimsAmount).toLocaleString()} claim pending</div>`;
        } else if (claimsAmount> 0 && validationStatus === 'rejected') {
            statusBadges += ' <span class="badge bg-danger"><i class="mdi mdi-close"></i> HMO Rejected</span>';
        } else if (claimsAmount> 0 && isValidated) {
            statusBadges += ' <span class="badge bg-success"><i class="mdi mdi-check"></i> HMO OK</span>';
        }
    } else if (section === 'approval') {
        statusBadges = '<span class="request-status-badge status-approval"><i class="mdi mdi-check-decagram"></i> Pending Approval</span>';
        borderStyle = 'border-left: 4px solid #6f42c1;';
    } else if (section === 'rejected') {
        statusBadges = '<span class="request-status-badge status-rejected"><i class="mdi mdi-close-circle"></i> Rejected</span>';
        borderStyle = 'border-left: 4px solid #dc3545;';
        if (request.rejection_reason) {
            pendingAlerts += `<div class="alert alert-danger py-2 px-3 mb-2 mt-2" style="font-size: 0.85rem;">
                <i class="mdi mdi-message-alert"></i> <strong>Rejection Reason:</strong> ${request.rejection_reason}</div>`;
        }
    }

    // Price display
    let priceHtml = price > 0 ? `<div class="request-card-price">₦${Number(price).toLocaleString()}</div>` : '';

    // HMO coverage split info
    let hmoHtml = '';
    if (coverageMode && coverageMode !== 'null' && coverageMode !== 'none' && coverageMode !== '') {
        hmoHtml = `
            <div class="request-card-hmo-info">
                <span class="badge bg-info">${coverageMode.toUpperCase()}</span>
                ${payableAmount> 0 ? `<span class="text-danger ms-2">Pay: ₦${Number(payableAmount).toLocaleString()}</span>` : ''}
                ${claimsAmount> 0 ? `<span class="text-success ms-2">HMO: ₦${Number(claimsAmount).toLocaleString()}</span>` : ''}
            </div>
        `;
    }

    // Tariff preview for unbilled HMO items
    let tariffPreviewHtml = '';
    if (section === 'billing' && request.tariff_preview) {
        const tp = request.tariff_preview;
        if (tp.no_tariff) {
            tariffPreviewHtml = `<div class="alert alert-warning py-2 px-3 mb-2 mt-1" style="font-size:0.85rem;">
                <i class="mdi mdi-alert-circle-outline"></i> <strong>No HMO tariff found</strong> — will use base price on billing
            </div>`;
        } else {
            const modeLabel = (tp.coverage_mode || '').toUpperCase();
            const payable = Number(tp.payable_amount || 0);
            const claims = Number(tp.claims_amount || 0);
            tariffPreviewHtml = `<div class="d-flex align-items-center gap-2 flex-wrap py-1 px-2 mb-2 mt-1 rounded" style="background:#e8f4fd; font-size:0.85rem;">
                <span class="badge bg-info">${modeLabel}</span>
                <span class="text-muted">Estimated:</span>
                ${payable> 0 ? `<span class="text-danger fw-semibold">Pay ₦${payable.toLocaleString()}</span>` : ''}
                ${claims> 0 ? `<span class="text-success fw-semibold">HMO ₦${claims.toLocaleString()}</span>` : ''}
            </div>`;
        }
    }

    // Delivery check
    const deliveryCheck = request.delivery_check;
    const canDeliver = deliveryCheck ? deliveryCheck.can_deliver : true;

    // Bundled procedure indicator
    const bundledInfo = request.bundled_info;
    let bundledHtml = '';
    if (bundledInfo && bundledInfo.is_bundled) {
        bundledHtml = `<div class="mt-1"><span class="badge" style="background: #6f42c1; color: #fff;">
            <i class="fa fa-procedures"></i> Bundled: ${bundledInfo.procedure_name || 'Procedure'}
        </span></div>`;
    }

    // Delivery warning (only if pending alerts don't already explain the block)
    let deliveryWarningHtml = '';
    if (!canDeliver && deliveryCheck && !pendingAlerts) {
        deliveryWarningHtml = `
            <div class="alert alert-warning py-2 px-2 mb-2 mt-2" style="font-size: 0.85rem;">
                <i class="fa fa-exclamation-triangle"></i> <strong>${deliveryCheck.reason}</strong><br>
                <small>${deliveryCheck.hint}</small>
            </div>
        `;
    }

    // Meta info section (like pharmacy — billed by, sample by)
    let metaDetails = '';
    if (section !== 'billing') {
        let metaItems = '';
        if (request.billed_by_name) {
            metaItems += `<div><i class="mdi mdi-cash-register"></i> Billed: ${request.billed_by_name}${request.billed_at_formatted ? ' (' + request.billed_at_formatted + ')' : ''}</div>`;
        }
        if (request.sample_by_name) {
            metaItems += `<div><i class="mdi mdi-test-tube"></i> Sample: ${request.sample_by_name}${request.sample_at_formatted ? ' (' + request.sample_at_formatted + ')' : ''}</div>`;
        }
        if (request.lab_number) {
            metaItems += `<div><i class="mdi mdi-tag-text"></i> Lab#: <span class="badge bg-info text-white">${request.lab_number}</span></div>`;
        }
        if (metaItems) {
            metaDetails = `<div class="request-card-audit small text-muted mt-2 pt-2 border-top">${metaItems}</div>`;
        }
    }

    if (isFreeForm) {
        statusBadges = '';
        pendingAlerts = '';
        priceHtml = '';
        hmoHtml = '';
        tariffPreviewHtml = '';
        deliveryWarningHtml = '';
        metaDetails = '';
    }

    // Results section has individual action button instead of checkbox
    let checkboxOrAction = '';
    if (section === 'results') {
        checkboxOrAction = `
            <button class="btn btn-sm btn-primary enter-result-btn" data-request-id="${request.id}" ${!canDeliver ? 'disabled title="' + (deliveryCheck?.reason || 'Cannot deliver service') + '"' : ''}>
                <i class="mdi mdi-file-document-edit"></i>
                Enter Result
            </button>
        `;
    } else if (section === 'approval') {
        checkboxOrAction = isApprover ? `
            <button class="btn btn-sm btn-outline-primary review-approval-btn" data-request-id="${request.id}">
                <i class="mdi mdi-eye"></i>
                Review
            </button>
        ` : `<span class="badge badge-purple"><i class="mdi mdi-clock"></i> Pending</span>`;
    } else if (section === 'rejected') {
        checkboxOrAction = `
            <button class="btn btn-sm btn-warning enter-result-btn" data-request-id="${request.id}">
                <i class="mdi mdi-pencil"></i>
                Re-enter
            </button>
        `;
    } else if (isFreeForm) {
        checkboxOrAction = `
            <button class="btn btn-sm btn-success enter-result-btn" data-request-id="${request.id}" title="Free-form: record result, no billing">
                <i class="mdi mdi-check"></i>
                Record Result
            </button>
        `;
    } else {
        checkboxOrAction = `
            <div class="request-card-checkbox">
                <input type="checkbox" class="request-checkbox" data-request-id="${request.id}" data-section="${section}"
                       data-price="${price}">
            </div>
        `;
    }

    if (isFreeForm) {
        borderStyle = 'border-left: 4px solid #6c757d; background-color: #f8f9fa;';
    }

    return `
        <div class="request-card" data-request-id="${request.id}" style="${borderStyle}">
            ${checkboxOrAction}
            <div class="request-card-content">
                <div class="request-card-header">
                    <div>
                        <div class="request-service-name">${serviceName}${freeFormBadge}</div>
                        ${bundledHtml}
                        <div class="request-card-meta">
                            <div class="request-meta-item">
                                <i class="mdi mdi-doctor"></i>
                                <span>${doctorName}</span>
                            </div>
                            <div class="request-meta-item">
                                <i class="mdi mdi-clock-outline"></i>
                                <span>${requestDate}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-end">
                        ${priceHtml}
                        <div>${statusBadges}</div>
                    </div>
                </div>
                ${pendingAlerts}
                ${hmoHtml}
                ${tariffPreviewHtml}
                ${noteHtml}
                ${deliveryWarningHtml}
                ${metaDetails}
            </div>
        </div>
    `;
}

function initializeRequestHandlers() {
    // Unbind previous direct handlers to avoid duplicates (cards are rebuilt each render)
    $('.select-all-checkbox').off('change');
    $('.request-checkbox').off('change');
    $('.enter-result-btn').off('click');

    // Select all checkboxes
    $('.select-all-checkbox').on('change', function() {
        const section = $(this).attr('id').replace('select-all-', '');
        const isChecked = $(this).is(':checked');
        $(`.request-checkbox[data-section="${section}"]`).each(function() {
            $(this).prop('checked', isChecked);
            // Update Set state
            const rid = $(this).data('request-id');
            if (isChecked) {
                checkedItemsState[section]?.add(rid);
                $(this).closest('.request-card').addClass('selected');
            } else {
                checkedItemsState[section]?.delete(rid);
                $(this).closest('.request-card').removeClass('selected');
            }
        });
        updateFloatingCart();
    });

    // Individual checkboxes — update floating cart + highlight card + track in Set
    $('.request-checkbox').on('change', function() {
        const section = $(this).data('section');
        const rid = $(this).data('request-id');
        const $card = $(this).closest('.request-card');

        // Toggle selected highlight + track in state Set
        if ($(this).is(':checked')) {
            $card.addClass('selected');
            checkedItemsState[section]?.add(rid);
        } else {
            $card.removeClass('selected');
            checkedItemsState[section]?.delete(rid);
        }

        const checkedCount = $(`.request-checkbox[data-section="${section}"]:checked`).length;

        // Update select all checkbox state
        const totalCount = $(`.request-checkbox[data-section="${section}"]`).length;
        $(`#select-all-${section}`).prop('checked', checkedCount === totalCount);

        // Update the floating cart
        updateFloatingCart();
    });

    // Enter Result buttons (individual)
    $('.enter-result-btn').on('click', function() {
        const requestId = $(this).data('request-id');
        enterResult(requestId);
    });
}

// loadClinicalContext is now handled by ClinicalContext.load() from clinical-context.js
// Notes display is still handled locally since it has Lab-specific formatting
function loadClinicalNotes(patientId) {
    $.get(`/lab-workbench/patient/${patientId}/notes?limit=10`, function(notes) {
        displayNotes(notes);
    });
}

// displayVitals, classifiers, and calculateBMI are now handled by ClinicalContext module (clinical-context.js)

function displayNotes(notes) {
    // Check if DataTables is loaded
    if (typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables library is not loaded');
        $('#notes-panel-body').html('<p class="text-danger">Error: DataTables library not loaded</p>');
        return;
    }


    // Destroy existing DataTable if present
    if ($.fn.DataTable.isDataTable('#notes-table')) {
        $('#notes-table').DataTable().destroy();
    }

    // Initialize DataTable with custom card rendering
    $('#notes-table').DataTable({
        data: notes,
        paging: false,
        searching: false,
        info: false,
        ordering: false,
        dom: 't',
        language: {
            emptyTable: '<p class="text-muted">No recent doctor notes</p>'
        },
        columns: [{
            data: null,
            render: function(data, type, row, meta) {
                const noteDate = row.date_formatted || row.date;
                const doctor = row.doctor || 'Unknown Doctor';
                const content = row.notes || 'No notes recorded';
                const truncatedContent = truncateText(content, 200);
                const noteId = `note-${meta.row}`;

                // Build reasons for encounter display (supports JSON per-diagnosis + legacy CSV)
                let reasonsHtml = '';
                let isJsonDiagnosis = false;
                if (row.reasons_for_encounter && row.reasons_for_encounter.trim() !== '') {
                    try {
                        let parsed = JSON.parse(row.reasons_for_encounter);
                        if (Array.isArray(parsed) && parsed.length && parsed[0].code) {
                            isJsonDiagnosis = true;
                            reasonsHtml = '<table class="table table-sm table-bordered mb-1" style="font-size:0.8rem;"><thead><tr><th>Code</th><th>Diagnosis</th><th>Status</th><th>Course</th></tr></thead><tbody>';
                            parsed.forEach(dx => {
                                let c1 = dx.comment_1 ? `<span class="badge bg-secondary">${escapeHtml(dx.comment_1)}</span>` : '<span class="text-muted">-</span>';
                                let c2 = dx.comment_2 ? `<span class="badge bg-secondary">${escapeHtml(dx.comment_2)}</span>` : '<span class="text-muted">-</span>';
                                reasonsHtml += `<tr><td><code>${escapeHtml(dx.code)}</code></td><td>${escapeHtml(dx.name)}</td><td>${c1}</td><td>${c2}</td></tr>`;
                            });
                            reasonsHtml += '</tbody></table>';
                        }
                    } catch(e) { /* not JSON, use legacy */ }
                    if (!isJsonDiagnosis) {
                        const reasons = row.reasons_for_encounter.split(',');
                        reasonsHtml = reasons.map(r => `<span class="badge bg-light text-dark me-1 mb-1">${r.trim()}</span>`).join('');
                    }
                }

                return `
                    <div class="card-modern mb-2" style="border-left: 4px solid var(--hospital-primary, #0d6efd);">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0">
                                    <i class="mdi mdi-account-circle"></i>
                                    <span class="text-primary">${doctor}</span>
                                </h6>
                                <span class="badge bg-info">${noteDate}</span>
                            </div>

                            ${reasonsHtml ? `
                                <div class="mb-2">
                                    <small><b><i class="mdi mdi-format-list-bulleted"></i> Reason(s) for Encounter/Diagnosis (ICPC-2):</b></small><br>
                                    ${reasonsHtml}
                                </div>
                            ` : ''}

                            ${!isJsonDiagnosis && row.reasons_for_encounter_comment_1 ? `
                                <div class="mb-2">
                                    <small><b><i class="mdi mdi-comment-text"></i> Diagnosis Comment 1:</b> ${escapeHtml(row.reasons_for_encounter_comment_1)}</small>
                                </div>
                            ` : ''}

                            ${!isJsonDiagnosis && row.reasons_for_encounter_comment_2 ? `
                                <div class="mb-2">
                                    <small><b><i class="mdi mdi-comment-text"></i> Diagnosis Comment 2:</b> ${escapeHtml(row.reasons_for_encounter_comment_2)}</small>
                                </div>
                            ` : ''}

                            <div class="alert alert-light mb-0 p-2" id="${noteId}">
                                <small><b><i class="mdi mdi-note-text"></i> Clinical Notes:</b><br>
                                <span class="note-text ${content.length> 200 ? 'truncated' : ''}" data-full-text="${escapeHtml(content)}">${truncatedContent}</span></small>
                                ${content.length> 200 ? `<br><a href="#" class="read-more-link small" data-note-id="${noteId}">Read More</a>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }
        }],
        drawCallback: function() {
            // Add Read More toggle handler
            $('#notes-table').off('click', '.read-more-link').on('click', '.read-more-link', function(e) {
                e.preventDefault();
                const $link = $(this);
                const $noteText = $link.siblings('.note-text');
                const fullText = $noteText.data('full-text');
                const truncatedText = truncateText(fullText, 200);

                if ($noteText.hasClass('truncated')) {
                    $noteText.removeClass('truncated').html(fullText);
                    $link.text('Read Less');
                } else {
                    $noteText.addClass('truncated').html(truncatedText);
                    $link.text('Read More');
                }
            });

            // Add "Show All" link
            const $wrapper = $('#notes-table_wrapper');
            $wrapper.find('.show-all-link').remove();
            $wrapper.append(`
                <a href="/patient/${currentPatient}?section=doctorNotesCardBody" target="_blank" class="show-all-link">
                    Show All Notes →
                </a>
            `);
        }
    });
}

function truncateText(text, maxLength) {
    if (!text || text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// displayMedications is now handled by ClinicalContext.displayMedications() from clinical-context.js

function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    const dateOptions = { month: 'short', day: 'numeric' };
    const timeOptions = { hour: '2-digit', minute: '2-digit' };
    return date.toLocaleDateString('en-US', dateOptions) + ', ' + date.toLocaleTimeString('en-US', timeOptions);
}

function loadQueueCounts() {
    $.get(wbRoute('lab.queue-counts', '/lab-workbench/queue-counts'), function(counts) {
        $('#queue-billing-count').text(counts.billing);
        $('#queue-sample-count').text(counts.sample);
        $('#queue-results-count').text(counts.results);
        $('#queue-completed-count').text(counts.completed);
        $('#queue-freeform-count').text(counts.freeform);
        $('#freeform-subtab-badge').text(counts.freeform);
        var emergencyCount = counts.emergency || 0;
        $('#queue-emergency-count').text(emergencyCount);
        if (emergencyCount> 0) {
            $('#queue-emergency-count').closest('.queue-item').addClass('emergency-pulse');
        } else {
            $('#queue-emergency-count').closest('.queue-item').removeClass('emergency-pulse');
        }
        // Approval counts
        var approvalCount = counts.approval || 0;
        $('#queue-approval-count').text(approvalCount);
        $('#approval-float-count').text(approvalCount);
        if (approvalCount> 0) {
            $('#openApprovalQueue').show();
        }
        updateSyncIndicator();
    });
}

function startQueueRefresh() {
    queueRefreshInterval = setInterval(function() {
        loadQueueCounts();

        // Also refresh current patient data if a patient is selected
        if (currentPatient) {
            refreshCurrentPatientData();
        }

        // Refresh queue DataTable if queue view is active
        if ($('#queue-view').hasClass('active') && queueDataTable) {
            queueDataTable.ajax.reload(null, false);
        }
    }, 30000); // 30 seconds
}

function refreshCurrentPatientData() {
    if (!currentPatient) return;

    // Skip auto-refresh if user has active checkbox selections (pharmacy-style guard)
    if (hasActiveSelections()) {
        console.log('Skipping auto-refresh: active selections detected');
        return;
    }


    // Silently reload patient requests
    $.get(`/lab-workbench/patient/${currentPatient}/requests`, function(data) {
        displayPendingRequests(data.requests);
        updatePendingSubtabBadges(data.requests);
    }).fail(function() {
        console.error('Failed to refresh patient data');
    });
}

let lastSyncTimestamp = null;
let syncTimeUpdateInterval = null;

function updateSyncIndicator() {
    lastSyncTimestamp = Date.now();
    updateSyncTimeDisplay();

    // Start interval to update relative time every 10 seconds
    if (syncTimeUpdateInterval) {
        clearInterval(syncTimeUpdateInterval);
    }
    syncTimeUpdateInterval = setInterval(updateSyncTimeDisplay, 10000);
}

function updateSyncTimeDisplay() {
    if (!lastSyncTimestamp) {
        $('#last-sync-time').text('Just now');
        return;
    }


    const secondsAgo = Math.floor((Date.now() - lastSyncTimestamp) / 1000);

    if (secondsAgo < 10) {
        $('#last-sync-time').text('Just now');
    } else if (secondsAgo < 60) {
        $('#last-sync-time').text(secondsAgo + 's ago');
    } else {
        const minutesAgo = Math.floor(secondsAgo / 60);
        $('#last-sync-time').text(minutesAgo + 'm ago');
    }
}

function refreshClinicalPanel(panel) {
    if (!currentPatient) return;

    const $btn = $(`.refresh-clinical-btn[data-panel="${panel}"]`);
    $btn.find('i').addClass('fa-spin');

    // Vitals and medications refresh is handled by clinical-context.js refresh handlers
    if (panel === 'notes') {
        $.get(`/lab-workbench/patient/${currentPatient}/notes?limit=10`, function(notes) {
            displayNotes(notes);
            $btn.find('i').removeClass('fa-spin');
        });
    } else {
        // For vitals/medications/allergies — the shared module handles these via delegated click events
        // Just stop the spinner after a short delay
        setTimeout(function() { $btn.find('i').removeClass('fa-spin'); }, 1000);
    }
}

function switchWorkspaceTab(tab) {
    $('.workspace-tab').removeClass('active');
    $(`.workspace-tab[data-tab="${tab}"]`).addClass('active');

    $('.workspace-tab-content').removeClass('active');
    $(`#${tab}-tab`).addClass('active');
}

