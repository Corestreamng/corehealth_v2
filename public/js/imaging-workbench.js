// Global state
let currentPatient = null;
let currentPatientData = null; // Store full patient data including allergies
let queueRefreshInterval = null;
let vitalTooltip = null;
const isApprover = [];
const requiresApproval = [];
const currentUserId = '';
let currentApprovalId = null;

var _PI_IMG_REQ_APPROVAL = window.WORKBENCH_CONFIG?.requireApproval || false;
var _PI_DR_SELF_IMG      = window.WORKBENCH_CONFIG?.drSelfImg || false;
var _PI_NR_SELF_IMG      = window.WORKBENCH_CONFIG?.nrSelfImg || false;

function _autoApproveIfEnabled(requestId, type) {
    if ($('#invest_res_is_edit').val() == '1') { return; }
    if (!_PI_IMG_REQ_APPROVAL) { return; }
    if (!_PI_DR_SELF_IMG && !_PI_NR_SELF_IMG) { return; }
    $.post('/imaging-workbench/self-approve/' + requestId, { _token: $('meta[name="csrf-token"]').attr('content') })
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
        getQueueCounts();
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
    if (queueFilter && ['billing', 'results', 'freeform'].includes(queueFilter)) {
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
        url: `wbUrl('/imaging-workbench/patient')/${patientId}/requests`,
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
            url: `wbUrl('/imagingHistoryList')/${patientId}`,
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
            emptyTable: "No imaging history found for this patient",
            processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
        },
        drawCallback: function() {
            // Re-order button handler — add service to the new request tab
            $('.re-order-btn').off('click').on('click', function() {
                let svcId   = $(this).data('service-id');
                let svcName = $(this).data('name');
                let svcPrice = $(this).data('price') || 0;
                addReorderServiceToCart(svcId, svcName, svcPrice);
            });
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
            url: `wbUrl('/patient-procedures/list-by-patient')/${patientId}`,
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

function viewInvestigationResult(requestId) {
    // Open modal to view completed result
    $.ajax({
        url: `wbUrl('/imaging-workbench/imaging-service-requests')/${requestId}`,
        method: 'GET',
        success: function(request) {
            if (request.result || request.result_data) {
                // Populate the view modal with result data
                populateResultViewModal(request);
                $('#investResViewModal').modal('show');
            } else {
                toastr.warning('No result data found for this request');
            }
        },
        error: function(xhr) {
            toastr.error('Error loading result: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}

function populateResultViewModal(res_obj) {
    // Basic service info
    $('.invest_res_service_name_view').text(res_obj.service ? res_obj.service.name : 'N/A');

    // Patient information (with null checks)
    let patientName = 'N/A';
    if (res_obj.patient && res_obj.patient.user) {
        patientName = (res_obj.patient.user.firstname || '') + ' ' + (res_obj.patient.user.surname || '');
    }
    $('#res_patient_name').html(patientName.trim() || 'N/A');
    $('#res_patient_id').html(res_obj.patient ? res_obj.patient.file_no : 'N/A');

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
    $('#res_patient_age').html(age);

    // Gender
    let gender = (res_obj.patient && res_obj.patient.gender) ? res_obj.patient.gender.toUpperCase() : 'N/A';
    $('#res_patient_gender').html(gender);

    // Test information
    $('#res_test_id').html(res_obj.id || 'N/A');
    $('#res_sample_date').html(res_obj.sample_date || 'N/A');
    $('#res_result_date').html(res_obj.result_date || 'N/A');

    // Result by (with null check)
    let resultByName = 'N/A';
    if (res_obj.results_person && (res_obj.results_person.firstname || res_obj.results_person.surname)) {
        resultByName = (res_obj.results_person.firstname || '') + ' ' + (res_obj.results_person.surname || '');
    }
    $('#res_result_by').html(resultByName.trim() || 'N/A');

    // Signature date (use result date)
    $('#res_signature_date').html(res_obj.result_date || '');

    // Generated date (current date)
    let now = new Date();
    let generatedDate = now.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    $('#res_generated_date').html(generatedDate);

    // Handle V2 results (structured data)
    if (res_obj.result_data) {
        let resultData = res_obj.result_data;
        if (typeof resultData === 'string') {
            try {
                resultData = JSON.parse(resultData);
            } catch (e) {
                console.error('Error parsing result data:', e);
                resultData = null;
            }
        }

        if (resultData && typeof resultData === 'object') {
            let paramsArray = [];
            if (Array.isArray(resultData)) {
                paramsArray = resultData;
            }

            if (paramsArray.length> 0) {
                let resultsHtml = '<table class="result-table"><thead><tr>';
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

                    let valueDisplay = param.value || 'N/A';
                    if (param.unit) {
                        valueDisplay += ' ' + param.unit;
                    }
                    resultsHtml += '<td>' + valueDisplay + '</td>';

                    let refRange = 'N/A';
                    if (param.reference_range) {
                        if (param.reference_range.min !== undefined && param.reference_range.max !== undefined) {
                            refRange = param.reference_range.min + ' - ' + param.reference_range.max;
                            if (param.unit) refRange += ' ' + param.unit;
                        } else if (param.reference_range.text) {
                            refRange = param.reference_range.text;
                        }
                    }
                    resultsHtml += '<td>' + refRange + '</td>';

                    let statusHtml = '';
                    if (param.status) {
                        let statusClass = 'status-' + param.status.toLowerCase().replace(' ', '-');
                        statusHtml = '<span class="result-status-badge ' + statusClass + '">' + param.status + '</span>';
                    }
                    resultsHtml += '<td>' + statusHtml + '</td>';
                    resultsHtml += '</tr>';
                });

                resultsHtml += '</tbody></table>';
                $('#invest_res').html(resultsHtml);
            } else {
                $('#invest_res').html(res_obj.result || '<p>No result content available</p>');
            }
        } else {
            $('#invest_res').html(res_obj.result || '<p>No result content available</p>');
        }
    } else {
        // V1 results (HTML content)
        $('#invest_res').html(res_obj.result || '<p>No result content available</p>');
    }

    // Handle attachments
    $('#invest_attachments').html('');
    if (res_obj.attachments) {
        let attachments = typeof res_obj.attachments === 'string' ? JSON.parse(res_obj.attachments) : res_obj.attachments;
        if (attachments && attachments.length> 0) {
            let attachHtml = '<div class="result-attachments"><h6 style="margin-bottom: 15px;"><i class="mdi mdi-paperclip"></i> Attachments</h6><div class="row">';
            attachments.forEach(function(attachment) {
                let url = wbUrl('/' + attachment.path);
                let icon = getFileIcon(attachment.type);
                attachHtml += `<div class="col-md-4 mb-2">
                    <a href="${url}" target="_blank" class="btn btn-outline-primary btn-sm btn-block">
                        ${icon} ${attachment.name}
                    </a>
                </div>`;
            });
            attachHtml += '</div></div>';
            $('#invest_attachments').html(attachHtml);
        }
    }
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
    billing: new Set()
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
    // No sample stage for imaging
    const approvalItems = (requests.pending_approval || []).length + (requests.rejected || []).length;
    const totalPending = requests.billing.length + requests.results.length + approvalItems;
    $('#pending-badge').text(totalPending);

    // Store pending results for bulk entry
    window._pendingResultRequests = requests.results || [];
    window._bulkResultConfig = {
        fetchUrlPattern: '/imaging-workbench/imaging-service-requests/{id}',
        attachUrlPattern: '/imaging-workbench/imaging-service-requests/{id}/attachments',
        saveUrl: wbRoute('imaging.saveResult', '/imaging/saveResult'),
        csrfToken: window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '',
        onAllSaved: function() { if (currentPatient) loadPatient(currentPatient); }
    };

    updatePendingSubtabBadges(requests);
    renderPendingSubtabContent(currentPendingFilter);
}

function updatePendingSubtabBadges(requests) {
    // No sample stage for imaging
    const approvalItems = (requests.pending_approval || []).length + (requests.rejected || []).length;
    const freeformCount = (requests.freeform || []).length;
    const totalPending = requests.billing.length + requests.results.length + approvalItems + freeformCount;
    $('#all-pending-badge').text(totalPending);
    $('#billing-subtab-badge').text(requests.billing.length);
    $('#results-subtab-badge').text(requests.results.length);
    $('#approval-subtab-badge').text(approvalItems);
    $('#freeform-subtab-badge').text(freeformCount);
}

function renderPendingSubtabContent(filter) {
    if (!currentPendingRequests) return;

    currentPendingFilter = filter;
    const requests = currentPendingRequests;
    // No sample stage for imaging
    const approvalItems = (requests.pending_approval || []).length + (requests.rejected || []).length;
    const freeformCount = (requests.freeform || []).length;
    const totalPending = requests.billing.length + requests.results.length + approvalItems + freeformCount;

    const $container = $('#pending-subtab-container');
    $container.empty();

    if (totalPending === 0) {
        $container.html('<div class="alert alert-info">No pending imaging requests for this patient</div>');
        return;
    }

    // Free-Form Section (Status 1, 2)
    const freeformItems = requests.freeform || [];
    if ((filter === 'all' || filter === 'freeform') && freeformItems.length > 0) {
        const freeformHtml = `
            <div class="request-section" data-section="freeform">
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-file-document-edit" class="text-dark"></i>
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

    // No sample collection stage for imaging - requests go directly from billing to results

    // Results Section (Status 2 for imaging - awaiting results)
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
    } else if (section === 'results') {
        statusBadges = '<span class="request-status-badge status-results">Awaiting Result</span>';
        borderStyle = 'border-left: 4px solid #198754;';

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
    } else if (section === 'freeform') {
        statusBadges = '<span class="badge bg-secondary">Free-Form</span>';
        borderStyle = 'border-left: 4px solid #6c757d;';
        if (request.status == 1) {
            statusBadges += ' <span class="badge bg-warning text-dark">Unbilled</span>';
        } else if (request.status == 2) {
            statusBadges += ' <span class="badge bg-success">Awaiting Result</span>';
        }
    }

    // Price display
    const priceHtml = price> 0 ? `<div class="request-card-price">₦${Number(price).toLocaleString()}</div>` : '';

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

    // Meta info section (like pharmacy — billed by)
    let metaDetails = '';
    if (section !== 'billing') {
        let metaItems = '';
        if (request.billed_by_name) {
            metaItems += `<div><i class="mdi mdi-cash-register"></i> Billed: ${request.billed_by_name}${request.billed_at_formatted ? ' (' + request.billed_at_formatted + ')' : ''}</div>`;
        }
        if (metaItems) {
            metaDetails = `<div class="request-card-audit small text-muted mt-2 pt-2 border-top">${metaItems}</div>`;
        }
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
    } else if (section === 'freeform' || request.is_free_form == 1 || request.is_free_form === true) {
        checkboxOrAction = `
            <div style="width: 40px; text-align: center; display: flex; align-items: center; justify-content: center;">
                <span class="badge bg-secondary" style="font-size: 0.7rem;">Ext.</span>
            </div>
        `;
    } else {
        checkboxOrAction = `
            <div class="request-card-checkbox">
                <input type="checkbox" class="request-checkbox" data-request-id="${request.id}" data-section="${section}"
                       data-price="${price}">
            </div>
        `;
    }

    return `
        <div class="request-card" data-request-id="${request.id}" style="${borderStyle}">
            ${checkboxOrAction}
            <div class="request-card-content">
                <div class="request-card-header">
                    <div>
                        <div class="request-service-name">${serviceName}</div>
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
// Notes display is still handled locally since it has Imaging-specific formatting
function loadClinicalNotes(patientId) {
    $.get(`/imaging-workbench/patient/${patientId}/notes?limit=10`, function(notes) {
        displayNotes(notes);
    });
}

// displayVitals, classifiers are now handled by ClinicalContext module (clinical-context.js)

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
    $.get(wbRoute('imaging.queue-counts', '/imaging/queue-counts'), function(counts) {
        $('#queue-billing-count').text(counts.billing);
        $('#queue-results-count').text(counts.results);
        $('#queue-sample-count').text(0); // Imaging doesn't have sample stage
        $('#queue-freeform-count').text(counts.freeform || 0);
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
    $.get(`/imaging-workbench/patient/${currentPatient}/requests`, function(data) {
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
        $.get(`/imaging-workbench/patient/${currentPatient}/notes?limit=10`, function(notes) {
            displayNotes(notes);
            $btn.find('i').removeClass('fa-spin');
        });
    } else {
        // For vitals/medications/allergies — the shared module handles these via delegated click events
        setTimeout(function() { $btn.find('i').removeClass('fa-spin'); }, 1000);
    }
}

function switchWorkspaceTab(tab) {
    $('.workspace-tab').removeClass('active');
    $(`.workspace-tab[data-tab="${tab}"]`).addClass('active');

    $('.workspace-tab-content').removeClass('active');
    $(`#${tab}-tab`).addClass('active');
}

function loadUserPreferences() {
    const clinicalVisible = localStorage.getItem('clinicalPanelVisible') === 'true';
    if (clinicalVisible) {
        $('#right-panel').addClass('active');
        $('#toggle-clinical-btn').html('📊 Clinical Context ×');
    }
}

// Action handlers for imaging requests
function recordBilling(requestIds) {
    $.ajax({
        url: wbRoute('imaging.recordBilling', '/imaging/recordBilling'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')},
        beforeSend: function() {
            toastr.info(`Recording billing for ${requestIds.length} item(s)...`);
        },
        success: function(response) {
            toastr.success('Billing recorded successfully!');
            $('#cartReviewModal').modal('hide');
            $('#floating-cart').fadeOut(200);
            clearCheckedItems('billing');
            loadPatient(currentPatient);
        },
        error: function(xhr) {
            toastr.error('Error recording billing: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}

// Note: No collectSample function for imaging - no sample collection stage

function dismissRequests(requestIds, section) {
    $.ajax({
        url: wbRoute('imaging.dismissRequests', '/imaging/dismissRequests'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')},
        beforeSend: function() {
            toastr.info(`Dismissing ${requestIds.length} request(s)...`);
        },
        success: function(response) {
            toastr.success('Requests dismissed successfully!');
            $('#cartReviewModal').modal('hide');
            $('#floating-cart').fadeOut(200);
            clearCheckedItems();
            loadPatient(currentPatient);
        },
        error: function(xhr) {
            toastr.error('Error dismissing requests: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}

function enterResult(requestId) {
    window._investResultContext = { type: 'imaging', id: requestId };
    InvestResultEntry.enterResult(
        requestId,
        `/imaging-workbench/imaging-service-requests/${requestId}`,
        `/imaging-workbench/imaging-service-requests/${requestId}/attachments`
    );
}

// ── Floating Cart Logic ──────────────────────────────────────────────
function getSelectedItems() {
    const items = { billing: [] };

    $('.request-checkbox[data-section="billing"]:checked').each(function() {
        const $card = $(this).closest('.request-card');
        items.billing.push({
            id: $(this).data('request-id'),
            name: $card.find('.request-service-name').text().trim() || 'Unknown Service',
            doctor: $card.find('.request-meta-item:first span').text().trim(),
            date: $card.find('.request-meta-item:last span').text().trim(),
            price: parseFloat($(this).data('price')) || 0
        });
    });

    return items;
}

function updateFloatingCart() {
    const items = getSelectedItems();
    const totalCount = items.billing.length;

    if (totalCount === 0) {
        $('#floating-cart').fadeOut(200);
        return;
    }
    const totalPrice = items.billing.reduce((sum, i) => sum + i.price, 0);
    $('#cart-item-count').text(totalCount);
    if (totalPrice> 0) {
        $('#cart-total-display').text('₦' + Number(totalPrice).toLocaleString()).show();
    } else {
        $('#cart-total-display').hide();
    }
    $('#floating-cart').fadeIn(300);
    $('.floating-cart-btn').addClass('pulse');
    setTimeout(() => $('.floating-cart-btn').removeClass('pulse'), 300);
}

function openCartReviewModal() {
    const items = getSelectedItems();
    const totalCount = items.billing.length;
    $('#modal-cart-count').text(totalCount);

    let html = '';

    if (totalCount === 0) {
        html = `<div class="cart-empty">
            <i class="mdi mdi-cart-outline"></i>
            <p>No items selected</p>
            <p class="small">Check items in the queue, then open the cart</p>
        </div>`;
    } else {
        // Billing section
        if (items.billing.length> 0) {
            const billingTotal = items.billing.reduce((sum, i) => sum + i.price, 0);
            html += `<div class="cart-section">
                <div class="cart-section-header">
                    <span><i class="mdi mdi-cash-register text-success"></i> Awaiting Billing</span>
                    <span class="badge bg-success">${items.billing.length}</span>
                </div>`;
            items.billing.forEach(item => {
                html += `<div class="cart-item">
                    <div>
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-meta"><i class="mdi mdi-doctor"></i> ${item.doctor} &middot; ${item.date}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        ${item.price> 0 ? `<span class="cart-item-price">₦${Number(item.price).toLocaleString()}</span>` : ''}
                        <button class="cart-item-remove" onclick="uncheckItem(${item.id}, 'billing')" title="Remove">
                            <i class="mdi mdi-close-circle"></i>
                        </button>
                    </div>
                </div>`;
            });
            if (billingTotal> 0) {
                html += `<div class="cart-section-total text-end small text-muted pe-2">Subtotal: <strong>₦${Number(billingTotal).toLocaleString()}</strong></div>`;
            }
            html += `<div class="cart-action-row">
                <button class="btn btn-success btn-sm" onclick="cartRecordBilling()">
                    <i class="mdi mdi-check-circle"></i> Record Billing (${items.billing.length})
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="cartDismiss('billing')">
                    <i class="mdi mdi-close-circle"></i> Dismiss (${items.billing.length})
                </button>
            </div></div>`;
        }
    }

    $('#cart-review-body').html(html);
    $('#cartReviewModal').modal('show');
}

function uncheckItem(requestId, section) {
    $(`.request-checkbox[data-section="${section}"][data-request-id="${requestId}"]`).prop('checked', false).trigger('change');
    openCartReviewModal();
}

function cartRecordBilling() {
    const ids = $('.request-checkbox[data-section="billing"]:checked').map(function() {
        return $(this).data('request-id');
    }).get();
    if (ids.length> 0) recordBilling(ids);
}

function cartDismiss(section) {
    const ids = $(`.request-checkbox[data-section="${section}"]:checked`).map(function() {
        return $(this).data('request-id');
    }).get();
    if (ids.length> 0) dismissRequests(ids, section);
}
// ── End Floating Cart Logic ──────────────────────────────────────────

function editImagingResult(obj) {
    const requestId = $(obj).data('id');
    InvestResultEntry.editResult(
        requestId,
        `/imaging-workbench/imaging-service-requests/${requestId}`,
        `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
        wbRoute('imaging.saveResult', '/imaging/saveResult')
    );
}

function enterImagingResult(requestId) {
    InvestResultEntry.enterResult(
        requestId,
        `/imaging-workbench/imaging-service-requests/${requestId}`,
        `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
        wbRoute('imaging.saveResult', '/imaging/saveResult')
    );
}

// Delete imaging request (created from encounter)
function deleteImagingRequest(imagingId, encounterId, serviceName) {
    deleteRequestId = imagingId;
    $('#delete_service_name').text(serviceName);
    $('#delete_request_id').text(imagingId);
    $('#delete_reason').val('');
    $('#deleteReasonModal').modal('show');
}

// Delete nurse-created clinical request (no encounter)
function deleteNurseClinicalRequest(type, id, name) {
    deleteRequestId = id;
    $('#delete_service_name').text(name);
    $('#delete_request_id').text(id);
    $('#delete_reason').val('');
    $('#deleteReasonModal').modal('show');
}

// Re-order: add a service from history into the new request cart
function addReorderServiceToCart(serviceId, serviceName, price) {
    // Check if service is already selected
    if ($('#selected-imaging-services tr[data-service-id="' + serviceId + '"]').length) {
        toastr.info(serviceName + ' is already in the request list');
        return;
    }
    let row = `<tr data-service-id="${serviceId}">
        <td>${serviceName}<input type="hidden" name="service_ids[]" value="${serviceId}"></td>
        <td>${parseFloat(price).toLocaleString()}</td>
        <td><input type="text" class="form-control form-control-sm" name="notes[]" placeholder="Optional note"></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="$(this).closest('tr').remove(); updateFloatingCart();"><i class="fa fa-times"></i></button></td>
    </tr>`;

    $('#selected-imaging-services').append(row);
    updateFloatingCart();
    switchWorkspaceTab('new-request');
    toastr.success(serviceName + ' added to new request');
}

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
        toastr.warning('Please provide a detailed reason (minimum 10 characters)');
        return;
    }
    $.ajax({
        url: `wbUrl('/imaging-workbench/imaging-service-requests')/${deleteRequestId}`,
        method: 'DELETE',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')},
        success: function(response) {
            $('#deleteReasonModal').modal('hide');
            toastr.success(response.message || 'Request deleted successfully');

            // Reload patient data if we're on a patient
            if (currentPatient) {
                loadPatient(currentPatient);
            }

            // Refresh trash panel if it's open
            if ($('#trashPanel').is(':visible')) {
                loadTrashData();
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to delete request');
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
        toastr.warning('Please provide a detailed reason (minimum 10 characters)');
        return;
    }
    $.ajax({
        url: `wbUrl('/imaging-workbench/imaging-service-requests')/${dismissRequestId}/dismiss`,
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')},
        success: function(response) {
            $('#dismissReasonModal').modal('hide');
            toastr.success(response.message || 'Request dismissed successfully');

            // Reload patient data
            if (currentPatient) {
                loadPatient(currentPatient);
            }

            // Refresh trash panel
            if ($('#trashPanel').is(':visible')) {
                loadTrashData();
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to dismiss request');
        }
    });
});

// Restore Request (from deleted or dismissed)
function restoreRequest(requestId, type) {
    const url = type === 'deleted'
        ? `/imaging-workbench/imaging-service-requests/${requestId}/restore`
        : `/imaging-workbench/imaging-service-requests/${requestId}/undismiss`;

    toastr.info('Restoring request...');
    $.ajax({
        url: url,
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')},
        success: function(response) {
            toastr.success(response.message || 'Request restored successfully');

            // Reload patient data
            if (currentPatient) {
                loadPatient(currentPatient);
            }

            // Refresh trash panel
            loadTrashData();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to restore request');
        }
    });
}

// Trash Panel Management
$('#openTrashPanel').on('click', function() {
    $('#trashPanel').fadeIn(300);
    loadTrashData();
});

$('#closeTrashPanel').on('click', function() {
    $('#trashPanel').fadeOut(300);
});

$('.trash-tab').on('click', function() {
    const tab = $(this).data('trash-tab');
    $('.trash-tab').removeClass('active');
    $(this).addClass('active');
    $('.trash-tab-content').removeClass('active');
    $(`#${tab}-content`).addClass('active');
});

function loadTrashData() {
    const patientId = currentPatient || null;

    // Load dismissed requests
    $.ajax({
        url: `wbUrl('/imaging-workbench/dismissed-requests')/${patientId || ''}`,
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
        url: `wbUrl('/imaging-workbench/deleted-requests')/${patientId || ''}`,
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
                url: `wbUrl('/imaging-workbench/dismissed-requests')/${patientId || ''}`,
                method: 'GET',
                success: function(data) {
                    $('#dismissed-count').text(data.length);
                    updateTrashTotalCount();
                }
            });
            $.ajax({
                url: `wbUrl('/imaging-workbench/deleted-requests')/${patientId || ''}`,
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
        url: `wbUrl('/imaging-workbench/approval')/${currentApprovalId}/approve`,
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
        url: `wbUrl('/imaging-workbench/approval')/${currentReverseId}/reverse`,
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
        url: `wbUrl('/imaging-workbench/approval')/${currentApprovalId}/reject`,
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
