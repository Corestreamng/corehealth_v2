<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>
<script src="{{ asset('js/workbench-notes-shared.js') }}"></script>
<script src="{{ asset('js/speech-dictation.js') }}"></script>
<script src="{{ asset('js/clinical-orders-shared.js') }}"></script>
<script src="{{ asset('js/clinical-context.js') }}"></script>
<script>
window.BILLING_KIT_CONFIG = {
    csrf: '{{ csrf_token() }}',
    addServiceRoute: '{{ route("nursing-workbench.billing.add-service") }}',
    addLabRoute: '{{ route("nursing-workbench.billing.add-lab-bill") }}',
    addImagingRoute: '{{ route("nursing-workbench.billing.add-imaging-bill") }}',
    addConsumableRoute: '{{ route("nursing-workbench.billing.add-consumable") }}',
    removeBillBase: '/nursing-workbench/remove-bill',
    pendingBillsBase: '/nursing-workbench/patient',
    serviceRequestsBase: '/nursing-workbench/patient',
    searchServicesRoute: '{{ route("nursing-workbench.search-services") }}',
    searchProductsRoute: '{{ route("nursing-workbench.search-products") }}',
    productBatchesRoute: '{{ route("nursing-workbench.product-batches") }}',
    investigationCategoryId: '{{ appsettings("investigation_category_id", "") }}',
    imagingCategoryId: 6,
    resolvedStoreId: '{{ $resolvedStore->id ?? "" }}',
    resolvedStoreName: '{{ $resolvedStore->store_name ?? "" }}',
    showMedicationOption: true,
};
</script>
@include('admin.shared.modals.request_details')
<script src="{{ asset('js/billing-shared.js') }}"></script>
<script src="{{ asset('js/request-details.js') }}"></script>
<script src="{{ asset('js/immunization-module.js') }}"></script>
@include('admin.partials.patient_search_js', ['search_context' => 'nursing'])
@include('admin.partials.invest_res_js')
@include('admin.partials.perform_investigation_modal')
@include('admin.partials.combo_confirm_modal')
<script>
// Global state
let currentPatient = null;
let currentPatientData = null; // Store full patient data including allergies
let queueRefreshInterval = null;
let vitalTooltip = null;
let queueDataTable = null;
let currentQueueFilter = 'admitted';
var medicationChartPrescribedRoute = "{{ route('nurse.medication.prescribed_drugs', [':patient']) }}";
let injectionPrescriptions = [];
let injectionPrescriptionsLoaded = false;

// =============================================
// VIEW MANAGEMENT HELPERS
// =============================================

// Hide all overlapping views - call this before showing any new view
function hideAllViews() {
    // Hide empty state
    $('#empty-state').hide();

    // Hide queue view
    $('#queue-view').removeClass('active').hide();
    $('.queue-item').removeClass('active');

    // Hide reports view
    $('#reports-view').removeClass('active').hide();

    // Hide ward dashboard
    $('#ward-dashboard-view').removeClass('active').hide();

    // Hide patient workspace
    $('#patient-header').removeClass('active');
    $('#workspace-content').removeClass('active').hide();
}

// =============================================
// QUEUE FUNCTIONALITY (derived from billing workbench pattern)
// =============================================

// Show queue view with specific filter
function showQueue(filter) {
    // First hide all other views to prevent stacking
    hideAllViews();
    currentQueueFilter = filter;

    // Update queue title based on filter type
    const titles = {
        'admitted': '<i class="mdi mdi-bed"></i> Admitted Patients',
        'vitals': '<i class="mdi mdi-heart-pulse"></i> Vitals Queue',
        'bed-requests': '<i class="mdi mdi-bed-empty"></i> Bed Requests',
        'discharge-requests': '<i class="mdi mdi-account-minus"></i> Discharge Requests',
        'medication-due': '<i class="mdi mdi-pill"></i> Medication Due',
        'emergency': '<i class="mdi mdi-ambulance"></i> Emergency Queue',
        'all': '<i class="mdi mdi-format-list-bulleted"></i> All Patients'
    };
    $('#queue-view-title').html(titles[filter] || titles['admitted']);

    // Update active state on queue buttons
    $('.queue-item').removeClass('active');
    $(`.queue-item[data-filter="${filter}"]`).addClass('active');

    // Show queue view
    $('#queue-view').addClass('active').css('display', 'flex');

    // On mobile, hide search pane and show main workspace
    if (window.innerWidth < 768) {
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    }

    // Load queue data based on filter
    loadQueueData(filter);
}

// Hide queue view
function hideQueue() {
    $('#queue-view').removeClass('active').css('display', 'none');
    $('.queue-item').removeClass('active');

    if (currentPatient) {
        // If patient was selected, show their workspace
        $('#patient-header').addClass('active');
        $('#workspace-content').show().addClass('active');
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

// Load queue data based on filter type
function loadQueueData(filter) {
    const $container = $('#queue-view .queue-view-content');
    $container.html('<div class="text-center p-4"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading...</div>');

    // Determine endpoint and handler based on filter
    let url = '';
    let handler = null;

    switch(filter) {
        case 'admitted':
            url = '{{ route("nursing-workbench.admitted-patients") }}';
            handler = displayAdmittedPatientsQueue;
            break;
        case 'vitals':
            url = '{{ route("nursing-workbench.vitals-queue") }}';
            handler = displayVitalsQueue;
            break;
        case 'bed-requests':
            url = '{{ route("nursing-workbench.bed-requests-queue") }}';
            handler = displayBedRequestsQueue;
            break;
        case 'discharge-requests':
            url = '{{ route("nursing-workbench.discharge-queue") }}';
            handler = displayDischargeRequestsQueue;
            break;
        case 'medication-due':
            url = '{{ route("nursing-workbench.medication-due") }}';
            handler = displayMedicationDueQueue;
            break;
        case 'emergency':
            url = '{{ route("emergency.queue") }}';
            handler = displayEmergencyQueue;
            break;
        case 'deceased':
            url = '/nursing-workbench/deceased-queue';
            handler = displayDeceasedQueue;
            break;
        default:
            url = '{{ route("nursing-workbench.admitted-patients") }}';
            handler = displayAdmittedPatientsQueue;
    }

    $.ajax({
        url: url,
        method: 'GET',
        success: function(response) {
            // Handle both array response and DataTables format
            const data = response.data || response;
            if (!data || (Array.isArray(data) && data.length === 0)) {
                $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-account-off mdi-48px"></i><br>No patients found in this queue</div>');
                return;
            }
            handler(data);
        },
        error: function(xhr) {
            console.error('Error loading queue:', xhr);
            $container.html('<div class="text-center p-4 text-danger"><i class="mdi mdi-alert-circle mdi-48px"></i><br>Failed to load patients</div>');
        }
    });
}

// Display admitted patients in queue (card-based)
// Display admitted patients in queue — ward-grouped with filters
let admittedPatientsData = [];
let admittedWardFilter = 'all';
let admittedStatusFilter = 'all';

function displayAdmittedPatientsQueue(patients) {
    admittedPatientsData = patients;
    const $container = $('#queue-view .queue-view-content');

    // Collect unique wards
    const wards = [...new Set(patients.map(p => p.ward).filter(w => w && w !== 'N/A'))];

    // Filter bar
    let filterHtml = `<div class="d-flex flex-wrap gap-2 mb-3 p-2 bg-light rounded align-items-center">
        <div class="d-flex align-items-center gap-2">
            <label class="mb-0 fw-bold small"><i class="mdi mdi-hospital-building"></i> Ward:</label>
            <select class="form-select form-select-sm" id="admitted-ward-filter" style="width: auto; min-width: 150px;">
                <option value="all">All Wards (${patients.length})</option>
                ${wards.map(w => {
                    const count = patients.filter(p => p.ward === w).length;
                    return `<option value="${w}">${w} (${count})</option>`;
                }).join('')}
            </select>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="mb-0 fw-bold small"><i class="mdi mdi-filter-variant"></i> Status:</label>
            <select class="form-select form-select-sm" id="admitted-status-filter" style="width: auto; min-width: 140px;">
                <option value="all">All Statuses</option>
                <option value="admitted">Admitted</option>
                <option value="discharge_requested">Discharge Requested</option>
                <option value="pending_checklist">Pending Checklist</option>
            </select>
        </div>
        <div class="ms-auto d-flex gap-2 small">
            <span class="badge bg-danger">${patients.filter(p => p.overdue_meds> 0).length} overdue meds</span>
            <span class="badge bg-warning text-dark">${patients.filter(p => p.vitals_due).length} vitals due</span>
            <span class="badge bg-info">${patients.filter(p => p.priority === 'emergency').length} emergency</span>
        </div>
    </div>`;

    let cardsHtml = renderAdmittedCards(patients);

    $container.html(filterHtml + '<div id="admitted-cards-container">' + cardsHtml + '</div>');

    // Attach filter handlers
    $('#admitted-ward-filter').on('change', function() {
        admittedWardFilter = $(this).val();
        applyAdmittedFilters();
    });
    $('#admitted-status-filter').on('change', function() {
        admittedStatusFilter = $(this).val();
        applyAdmittedFilters();
    });
}

function applyAdmittedFilters() {
    let filtered = admittedPatientsData;
    if (admittedWardFilter !== 'all') {
        filtered = filtered.filter(p => p.ward === admittedWardFilter);
    }
    if (admittedStatusFilter !== 'all') {
        filtered = filtered.filter(p => p.admission_status === admittedStatusFilter);
    }
    $('#admitted-cards-container').html(renderAdmittedCards(filtered));
}

function renderAdmittedCards(patients) {
    if (patients.length === 0) {
        return '<div class="text-center p-4 text-muted"><i class="mdi mdi-bed mdi-48px"></i><br>No patients match the selected filters</div>';
    }

    // Group by ward
    const wardGroups = {};
    patients.forEach(p => {
        const ward = p.ward || 'Unassigned';
        if (!wardGroups[ward]) wardGroups[ward] = [];
        wardGroups[ward].push(p);
    });

    let html = '';
    const wardTypeIcons = {
        'icu': 'mdi-heart-pulse',
        'emergency': 'mdi-ambulance',
        'pediatric': 'mdi-baby-carriage',
        'maternity': 'mdi-mother-nurse',
        'isolation': 'mdi-biohazard',
        'general': 'mdi-hospital-building',
    };

    Object.keys(wardGroups).sort().forEach(ward => {
        const wardPatients = wardGroups[ward];
        const wardType = wardPatients[0]?.ward_type || 'general';
        const wardIcon = wardTypeIcons[wardType] || 'mdi-hospital-building';

        html += `<div class="mb-3">
            <div class="d-flex align-items-center gap-2 mb-2 px-2">
                <h6 class="mb-0 fw-bold text-primary"><i class="mdi ${wardIcon}"></i> ${ward}</h6>
                <span class="badge bg-primary rounded-pill">${wardPatients.length}</span>
            </div>
            <div class="row px-2">`;

        wardPatients.forEach(p => {
            const priorityBorder = p.priority === 'emergency' ? 'border-left: 4px solid #dc3545;'
                : (p.priority === 'urgent' ? 'border-left: 4px solid #fd7e14;' : '');
            const priorityBadge = p.priority === 'emergency'
                ? '<span class="badge bg-danger"><i class="mdi mdi-alert"></i> Emergency</span>'
                : (p.priority === 'urgent' ? '<span class="badge bg-warning text-dark">Urgent</span>' : '');

            html += `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card-modern queue-patient-card" style="cursor: pointer; ${priorityBorder}" onclick="loadPatient(${p.patient_id}); hideQueue();">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <h6 class="mb-0 ${p.priority === 'emergency' ? 'text-danger fw-bold' : ''}">${p.name || 'N/A'}</h6>
                                ${priorityBadge}
                            </div>
                            <small class="text-muted d-block">${p.file_no || ''} | ${p.age || ''} ${p.gender || ''}</small>
                            ${p.hmo ? `<small class="text-info d-block"><i class="mdi mdi-shield-check"></i> ${p.hmo}</small>` : ''}
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small"><i class="mdi mdi-bed text-primary"></i> ${p.bed_name || 'No bed'}</span>
                                <span class="small text-muted"><i class="mdi mdi-calendar"></i> Day ${p.days_admitted || 0}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-muted"><i class="mdi mdi-doctor"></i> ${p.doctor || 'N/A'}</span>
                                <span class="small text-muted"><i class="mdi mdi-heart-pulse"></i> ${p.last_vitals || 'Never'}</span>
                            </div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                ${p.overdue_meds> 0 ? `<span class="badge bg-danger"><i class="mdi mdi-pill"></i> ${p.overdue_meds} overdue</span>` : ''}
                                ${p.pending_meds> 0 && p.overdue_meds === 0 ? `<span class="badge bg-warning text-dark"><i class="mdi mdi-pill"></i> ${p.pending_meds} due</span>` : ''}
                                ${p.vitals_due ? '<span class="badge bg-warning text-dark"><i class="mdi mdi-heart-pulse"></i> Vitals due</span>' : ''}
                                ${p.chief_complaint ? `<span class="badge bg-info" title="${p.chief_complaint}"><i class="mdi mdi-comment-medical"></i> CC</span>` : ''}
                            </div>
                        </div>
                    </div>
                </div>`;
        });

        html += '</div></div>';
    });

    return html;
}

// Display vitals queue with wait times and clinic filter
let vitalsQueueData = [];
let vitalsClinicFilter = 'all';
let vitalsClinicsLoaded = false;
let vitalsClinicsCache = [];

function displayVitalsQueue(patients) {
    vitalsQueueData = Array.isArray(patients) ? patients : (patients.data || []);
    const $container = $('#queue-view .queue-view-content');

    if (vitalsQueueData.length === 0) {
        $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-heart-pulse mdi-48px"></i><br>No patients pending vitals</div>' +
            '<div id="consulting-section" class="mt-3"></div>');
        loadConsultingQueue();
        return;
    }

    // Load clinics for filter if not already loaded
    if (!vitalsClinicsLoaded) {
        $.get('{{ route("nursing-workbench.clinics") }}', function(data) {
            vitalsClinicsCache = data.clinics || [];
            vitalsClinicsLoaded = true;
            renderVitalsQueueFull();
        });
    } else {
        renderVitalsQueueFull();
    }

    function renderVitalsQueueFull() {
        const patients = vitalsQueueData;
        const criticalWaits = patients.filter(p => p.wait_level === 'critical').length;
        const warningWaits = patients.filter(p => p.wait_level === 'warning').length;
        const emergencies = patients.filter(p => p.priority === 'emergency').length;

        // Calculate average wait time
        let avgWait = 0;
        if (patients.length > 0) {
            avgWait = Math.round(patients.reduce((acc, p) => acc + (p.wait_minutes || 0), 0) / patients.length);
        }

        let filterHtml = `
            <div class="vitals-dashboard-header mb-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="bh-stat-card bh-stat-blue">
                            <div class="bh-stat-icon"><i class="mdi mdi-account-group"></i></div>
                            <div>
                                <div class="bh-stat-value">${patients.length}</div>
                                <div class="bh-stat-label">Total Pending</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bh-stat-card bh-stat-pink">
                            <div class="bh-stat-icon"><i class="mdi mdi-alert-circle"></i></div>
                            <div>
                                <div class="bh-stat-value">${emergencies}</div>
                                <div class="bh-stat-label">Emergencies</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bh-stat-card bh-stat-purple">
                            <div class="bh-stat-icon"><i class="mdi mdi-clock-alert"></i></div>
                            <div>
                                <div class="bh-stat-value">${criticalWaits}</div>
                                <div class="bh-stat-label">Long Waits (>45m)</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bh-stat-card bh-stat-green">
                            <div class="bh-stat-icon"><i class="mdi mdi-timer-outline"></i></div>
                            <div>
                                <div class="bh-stat-value">${avgWait}m</div>
                                <div class="bh-stat-label">Avg Wait Time</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center p-3 bg-white rounded shadow-sm border">
                    <div class="d-flex align-items-center gap-3">
                        <div class="input-group input-group-sm" style="width: 300px;">
                            <span class="input-group-text bg-light border-end-0"><i class="mdi mdi-hospital-building text-primary"></i></span>
                            <select class="form-select border-start-0" id="vitals-clinic-filter">
                                <option value="all">All Clinics (${patients.length})</option>
                                ${vitalsClinicsCache.map(c => `<option value="${c.id}" ${vitalsClinicFilter == c.id ? 'selected' : ''}>${c.name}</option>`).join('')}
                            </select>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="refreshVitalsQueue()"><i class="mdi mdi-refresh"></i></button>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge rounded-pill bg-light text-dark border"><i class="mdi mdi-circle text-success small me-1"></i> Normal: ${patients.length - criticalWaits - warningWaits}</span>
                        <span class="badge rounded-pill bg-light text-dark border"><i class="mdi mdi-circle text-warning small me-1"></i> Warning: ${warningWaits}</span>
                        <span class="badge rounded-pill bg-light text-dark border"><i class="mdi mdi-circle text-danger small me-1"></i> Critical: ${criticalWaits}</span>
                    </div>
                </div>
            </div>`;

        $container.html(filterHtml + '<div id="vitals-cards-container">' + renderVitalsCards(patients) + '</div>' +
            '<div id="consulting-section" class="mt-3"></div>');

        // Load currently consulting patients
        loadConsultingQueue();

        $('#vitals-clinic-filter').on('change', function() {
            vitalsClinicFilter = $(this).val();
            // Show loading state
            $('#vitals-cards-container').html('<div class="text-center p-5"><i class="mdi mdi-loading mdi-spin mdi-48px text-primary"></i><br>Filtering queue...</div>');

            $.get('{{ route("nursing-workbench.vitals-queue") }}', { clinic_id: vitalsClinicFilter }, function(data) {
                vitalsQueueData = data;
                // Re-render the whole thing to update stats as well
                renderVitalsQueueFull();
            });
        });
    }
}

function renderVitalsCards(patients) {
    if (patients.length === 0) {
        return '<div class="text-center p-4 text-muted"><i class="mdi mdi-heart-pulse mdi-48px"></i><br>No patients pending vitals</div>';
    }

    let html = '<div class="row p-2">';
    patients.forEach((p, index) => {
        const waitColor = p.wait_level === 'critical' ? '#dc3545'
            : (p.wait_level === 'warning' ? '#fd7e14' : '#28a745');
        const waitBg = p.wait_level === 'critical' ? 'bg-danger'
            : (p.wait_level === 'warning' ? 'bg-warning text-dark' : 'bg-success');
        const priorityBorder = p.priority === 'emergency' ? 'border-left: 4px solid #dc3545;'
            : (p.priority === 'urgent' ? 'border-left: 4px solid #fd7e14;' : `border-left: 4px solid ${waitColor};`);
        const priorityBadge = p.priority === 'emergency'
            ? '<span class="badge bg-danger"><i class="mdi mdi-alert"></i> Emergency</span>'
            : (p.priority === 'urgent' ? '<span class="badge bg-warning text-dark">Urgent</span>' : '');
        let sourceBadge = '<span class="queue-source-badge source-walkin"><i class="mdi mdi-walk"></i> Walk-in</span>';
        if (p.source === 'appointment') {
            sourceBadge = '<span class="queue-source-badge source-appointment"><i class="mdi mdi-calendar-check"></i> Scheduled</span>';
        } else if (p.source === 'emergency' || p.source === 'emergency_intake') {
            sourceBadge = '<span class="queue-source-badge source-emergency"><i class="mdi mdi-ambulance"></i> Emergency</span>';
        }

        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card-modern queue-patient-card" style="cursor: pointer; ${priorityBorder}" onclick="loadPatient(${p.patient_id}); hideQueue();">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div>
                                <span class="badge bg-light text-dark border me-1">#${index + 1}</span>
                                <strong class="${p.priority === 'emergency' ? 'text-danger' : ''}">${p.patient_name || 'N/A'}</strong>
                            </div>
                            <div class="d-flex gap-1">
                                ${priorityBadge}${sourceBadge}
                            </div>
                        </div>
                        <small class="text-muted d-block">${p.file_no || ''} | ${p.age || ''} ${p.gender || ''}</small>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small"><i class="mdi mdi-hospital-building text-primary"></i> ${p.clinic || 'N/A'}</span>
                            <span class="small"><i class="mdi mdi-doctor"></i> ${p.doctor || 'N/A'}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted"><i class="mdi mdi-clock-outline"></i> Queued ${p.queued_at || ''}</span>
                            <span class="badge ${waitBg}"><i class="mdi mdi-timer-sand"></i> ${p.wait_display || '0min'}</span>
                        </div>
                        ${p.triage_note ? `<div class="mt-2 p-2 bg-light rounded small"><i class="mdi mdi-note-text text-info"></i> ${p.triage_note.substring(0, 100)}${p.triage_note.length> 100 ? '...' : ''}</div>` : ''}
                    </div>
                </div>
            </div>`;
    });
    html += '</div>';
    return html;
}

function refreshVitalsQueue() {
    $.get('{{ route("nursing-workbench.vitals-queue") }}', { clinic_id: vitalsClinicFilter }, function(data) {
        vitalsQueueData = data;
        renderVitalsQueueFull();
        toastr.success('Queue refreshed');
    });
}

/**
 * Load currently consulting patients with mini-timers
 */
function loadConsultingQueue() {
    $.get('{{ route("nursing-workbench.consulting-queue") }}', { clinic_id: vitalsClinicFilter }, function(data) {
        if (!data || data.length === 0) {
            $('#consulting-section').html('');
            return;
        }
        $('#consulting-section').html(renderConsultingCards(data));
        initNurseMiniTimers();
    });
}

function renderConsultingCards(patients) {
    if (!patients || patients.length === 0) return '';

    let html = `<div class="border-top pt-3 mt-2">
        <div class="d-flex align-items-center gap-2 mb-2 px-2">
            <i class="mdi mdi-stethoscope text-primary"></i>
            <strong class="small text-uppercase text-muted">Currently In Consultation</strong>
            <span class="badge bg-primary">${patients.length}</span>
        </div>
        <div class="row p-2">`;

    patients.forEach(p => {
        const started = p.consultation_started_at;
        const paused = p.consultation_paused_seconds || 0;
        const isPaused = p.is_paused ? '1' : '0';
        const lastPaused = p.last_paused_at || '';
        const priorityBorder = p.priority === 'emergency' ? 'border-left: 4px solid #dc3545;'
            : (p.priority === 'urgent' ? 'border-left: 4px solid #fd7e14;' : 'border-left: 4px solid #0d6efd;');

        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card-modern" style="${priorityBorder}">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <strong>${p.patient_name || 'N/A'}</strong>
                            <span class="badge bg-success-subtle text-success nurse-mini-timer"
                                data-started="${started}"
                                data-paused-seconds="${paused}"
                                data-is-paused="${isPaused}"
                                data-last-paused-at="${lastPaused}">
                                <i class="mdi mdi-timer"></i> <span class="timer-value">00:00:00</span>
                                ${p.is_paused ? ' <i class="mdi mdi-pause-circle text-warning"></i>' : ''}
                            </span>
                        </div>
                        <small class="text-muted d-block">${p.file_no || ''} | ${p.age || ''} ${p.gender || ''}</small>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small"><i class="mdi mdi-hospital-building text-primary"></i> ${p.clinic || 'N/A'}</span>
                            <span class="small"><i class="mdi mdi-doctor"></i> ${p.doctor || 'N/A'}</span>
                        </div>
                    </div>
                </div>
            </div>`;
    });

    html += '</div></div>';
    return html;
}

/**
 * Initialize mini-timers for nurse consulting section
 */
function initNurseMiniTimers() {
    $('.nurse-mini-timer[data-started]').each(function() {
        var $el = $(this);
        if ($el.data('nurse-timer-init')) return;
        $el.data('nurse-timer-init', true);

        var startedAt = new Date($el.data('started'));
        var pausedSeconds = parseInt($el.data('paused-seconds')) || 0;
        var isPaused = $el.data('is-paused') == true || $el.data('is-paused') === 'true' || $el.data('is-paused') == 1;
        var lastPausedAt = $el.data('last-paused-at') ? new Date($el.data('last-paused-at')) : null;

        setInterval(function() {
            if (isPaused) return;
            var now = new Date();
            var total = Math.floor((now - startedAt) / 1000) - pausedSeconds;
            total = Math.max(0, total);
            var h = Math.floor(total / 3600);
            var m = Math.floor((total % 3600) / 60);
            var s = total % 60;
            $el.find('.timer-value').text(
                String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0')
            );
        }, 1000);
    });
}

// Display bed requests queue (card-based)
function displayBedRequestsQueue(requests) {
    const $container = $('#queue-view .queue-view-content');

    if (!Array.isArray(requests)) {
        requests = requests.data || [];
    }

    if (requests.length === 0) {
        $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-bed-empty mdi-48px"></i><br>No bed requests at this time</div>');
        return;
    }

    let html = '<div class="row p-2">';
    requests.forEach(r => {
        const priorityLower = (r.priority || 'routine').toLowerCase();
        const statusClass = priorityLower === 'urgent' || priorityLower === 'emergency' ? 'border-danger' : 'border-info';
        const badgeClass = priorityLower === 'urgent' || priorityLower === 'emergency' ? 'badge-danger' : 'badge-secondary';

        // Escape quotes for use in onclick attributes
        const patientName = (r.patient_name || r.name || 'N/A').replace(/'/g, "\\'");
        const fileNo = (r.file_no || '').replace(/'/g, "\\'");

        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card-modern ${statusClass} queue-patient-card" style="cursor: pointer;" onclick="loadPatient(${r.patient_id}); hideQueue();">
                    <div class="card-body p-3">
                        <h6 class="mb-1">${r.patient_name || r.name || 'N/A'}</h6>
                        <small class="text-muted d-block">${r.file_no || ''}</small>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <span><i class="mdi mdi-bed"></i> ${r.requested_ward || 'Any ward'}</span>
                            <span class="badge ${badgeClass}">${(r.priority || 'routine').toUpperCase()}</span>
                        </div>
                        <small class="text-muted mt-2 d-block">${r.reason || ''}</small>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-info" onclick="event.stopPropagation(); WardDashboard.openBedAssignment(${r.admission_id || r.id}, '${patientName}', '${fileNo}');">
                                <i class="mdi mdi-clipboard-check"></i> Process Admission
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    $container.html(html);
}

// Display discharge requests queue — detailed cards with billing warnings
function displayDischargeRequestsQueue(requests) {
    const $container = $('#queue-view .queue-view-content');

    if (!Array.isArray(requests)) {
        requests = requests.data || [];
    }

    if (requests.length === 0) {
        $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-account-minus mdi-48px"></i><br>No discharge requests at this time</div>');
        return;
    }

    // Summary bar
    const withUnpaid = requests.filter(r => r.unpaid_bills> 0).length;
    const checklistPhase = requests.filter(r => r.admission_status === 'discharge_checklist').length;

    let summaryHtml = `<div class="d-flex flex-wrap gap-2 mb-3 p-2 bg-light rounded align-items-center">
        <span class="fw-bold small"><i class="mdi mdi-account-minus"></i> ${requests.length} discharge requests</span>
        <div class="ms-auto d-flex gap-2 small">
            ${withUnpaid> 0 ? `<span class="badge bg-danger"><i class="mdi mdi-cash-remove"></i> ${withUnpaid} unpaid bills</span>` : ''}
            ${checklistPhase> 0 ? `<span class="badge bg-info"><i class="mdi mdi-clipboard-check"></i> ${checklistPhase} in checklist</span>` : ''}
        </div>
    </div>`;

    let html = '<div class="row p-2">';
    requests.forEach(r => {
        const patientName = (r.patient_name || r.name || 'N/A').replace(/'/g, "\\'");
        const fileNo = (r.file_no || '').replace(/'/g, "\\'");
        const bedName = (r.bed_name || 'No bed').replace(/'/g, "\\'");
        const hasUnpaid = r.unpaid_bills> 0;
        const borderStyle = hasUnpaid ? 'border-left: 4px solid #dc3545;' : 'border-left: 4px solid #ffc107;';
        const statusBadge = r.admission_status === 'discharge_checklist'
            ? '<span class="badge bg-info">Checklist In Progress</span>'
            : '<span class="badge bg-warning text-dark">Discharge Requested</span>';

        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card-modern queue-patient-card" style="cursor: pointer; ${borderStyle}" onclick="loadPatient(${r.patient_id}); hideQueue();">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="mb-0">${r.patient_name || r.name || 'N/A'}</h6>
                            ${statusBadge}
                        </div>
                        <small class="text-muted d-block">${r.file_no || ''}</small>
                        ${r.hmo ? `<small class="text-info d-block"><i class="mdi mdi-shield-check"></i> ${r.hmo}</small>` : ''}
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small"><i class="mdi mdi-bed text-primary"></i> ${r.bed_name || 'No bed'}</span>
                            <span class="small text-muted"><i class="mdi mdi-hospital-building"></i> ${r.ward || 'N/A'}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted"><i class="mdi mdi-doctor"></i> ${r.doctor || 'N/A'}</span>
                            <span class="small text-muted"><i class="mdi mdi-calendar"></i> ${r.days_admitted || 0} days admitted</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted"><i class="mdi mdi-clock"></i> Requested: ${r.wait_display || r.discharge_requested_at || 'N/A'}</small>
                        </div>
                        ${r.discharge_reason ? `<small class="d-block text-muted mb-2"><i class="mdi mdi-comment-text"></i> ${r.discharge_reason}</small>` : ''}
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            ${hasUnpaid ? `<span class="badge bg-danger"><i class="mdi mdi-cash-remove"></i> ${r.unpaid_bills} unpaid bills</span>` : '<span class="badge bg-success"><i class="mdi mdi-check-circle"></i> Bills clear</span>'}
                        </div>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-warning w-100" onclick="event.stopPropagation(); WardDashboard.openDischarge(${r.admission_id}, '${patientName}', '${fileNo}', '${bedName}');">
                                <i class="mdi mdi-clipboard-check"></i> Process Discharge
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    $container.html(summaryHtml + html);
}

// Display medication due queue — detailed with overdue timing and ward grouping
function displayMedicationDueQueue(patients) {
    const $container = $('#queue-view .queue-view-content');

    if (!Array.isArray(patients)) {
        patients = patients.data || [];
    }

    if (patients.length === 0) {
        $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-pill mdi-48px"></i><br>No medications due at this time</div>');
        return;
    }

    // Sort by overdue_minutes desc (most overdue first)
    patients.sort((a, b) => (b.overdue_minutes || 0) - (a.overdue_minutes || 0));

    const overdueCount = patients.filter(p => p.overdue).length;
    const dueCount = patients.filter(p => !p.overdue).length;

    // Summary bar
    let summaryHtml = `<div class="d-flex flex-wrap gap-2 mb-3 p-2 bg-light rounded align-items-center">
        <span class="fw-bold small"><i class="mdi mdi-pill"></i> Medication Round</span>
        <div class="ms-auto d-flex gap-2 small">
            ${overdueCount> 0 ? `<span class="badge bg-danger"><i class="mdi mdi-clock-alert"></i> ${overdueCount} overdue</span>` : ''}
            ${dueCount> 0 ? `<span class="badge bg-warning text-dark"><i class="mdi mdi-clock"></i> ${dueCount} due now</span>` : ''}
            <span class="badge bg-secondary">${patients.length} patients total</span>
        </div>
    </div>`;

    let html = '<div class="row p-2">';
    patients.forEach(p => {
        const isOverdue = p.overdue;
        const borderStyle = isOverdue
            ? 'border-left: 4px solid #dc3545;'
            : 'border-left: 4px solid #ffc107;';
        const urgencyBadge = isOverdue
            ? `<span class="badge bg-danger"><i class="mdi mdi-clock-alert"></i> ${p.overdue_display || 'Overdue'}</span>`
            : '<span class="badge bg-warning text-dark"><i class="mdi mdi-clock"></i> Due now</span>';
        const priorityBadge = p.priority === 'emergency'
            ? ' <span class="badge bg-danger"><i class="mdi mdi-alert"></i> Emergency</span>'
            : '';

        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card-modern queue-patient-card" style="cursor: pointer; ${borderStyle}" onclick="loadPatient(${p.patient_id}); hideQueue();">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="mb-0 ${isOverdue ? 'text-danger fw-bold' : ''}">${p.name || p.patient_name || 'N/A'}</h6>
                            <div class="d-flex gap-1">${urgencyBadge}${priorityBadge}</div>
                        </div>
                        <small class="text-muted d-block">${p.file_no || ''}</small>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small"><i class="mdi mdi-bed text-primary"></i> ${p.bed_name || 'N/A'}</span>
                            <span class="small text-muted"><i class="mdi mdi-hospital-building"></i> ${p.ward || 'N/A'}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small"><i class="mdi mdi-pill text-warning"></i> <strong>${p.medication_count || 0}</strong> medications${p.overdue_count> 0 ? ` (${p.overdue_count} overdue)` : ''}</span>
                            ${p.next_med_time ? `<span class="small text-muted"><i class="mdi mdi-clock-fast"></i> Next: ${p.next_med_time}</span>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    $container.html(summaryHtml + html);
}

// Display emergency queue patients
function displayEmergencyQueue(patients) {
    const $container = $('#queue-view .queue-view-content');

    if (!Array.isArray(patients)) {
        patients = patients.data || [];
    }

    if (patients.length === 0) {
        $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-ambulance mdi-48px"></i><br>No emergency patients at this time</div>');
        return;
    }

    const esiColors = { 1: '#dc3545', 2: '#fd7e14', 3: '#ffc107', 4: '#28a745', 5: '#17a2b8' };

    let html = '<div class="row p-2">';
    patients.forEach(p => {
        const esiLevel = p.esi_level;
        const esiColor = esiColors[esiLevel] || '#dc3545';
        const esiLabel = esiLevel ? ('ESI-' + esiLevel + ' ' + (p.esi_label || '')) : 'Emergency';

        const bedInfo = p.bed && p.bed !== 'Unassigned'
            ? `<span class="badge badge-info"><i class="mdi mdi-bed"></i> ${p.bed}</span>`
            : '<span class="badge badge-secondary">No Bed</span>';
        const wardInfo = p.ward && p.ward !== 'N/A' ? `<small class="text-muted">${p.ward}</small>` : '';

        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card-modern queue-patient-card" style="border-left: 4px solid ${esiColor};">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start" style="cursor:pointer;" onclick="loadPatient(${p.patient_id}); hideQueue();">
                            <h6 class="mb-1">${p.patient_name || 'N/A'}</h6>
                            <span class="badge" style="background: ${esiColor}; color: #fff; font-size: 0.7rem;">${esiLabel}</span>
                        </div>
                        <small class="text-muted d-block">${p.file_no || ''} | ${p.hmo || 'Private'}</small>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            ${bedInfo} ${wardInfo}
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            ${p.status_badge || ''}
                            <small class="text-muted"><i class="mdi mdi-clock"></i> ${p.admitted_at || ''} (${p.duration || ''})</small>
                        </div>
                        <div class="mt-2 d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary flex-fill" onclick="openTransferWardModal(${p.admission_id}, '${(p.patient_name || '').replace(/'/g, "\\'")}')">
                                <i class="mdi mdi-swap-horizontal"></i> Transfer
                            </button>
                            <button class="btn btn-sm btn-outline-warning flex-fill" onclick="event.stopPropagation(); WardDashboard.openDischarge(${p.admission_id}, '${(p.patient_name || '').replace(/'/g, "\\'")}', '${(p.file_no || '').replace(/'/g, "\\'")}', '${(p.bed || '').replace(/'/g, "\\'")}')">
                                <i class="mdi mdi-account-minus"></i> Discharge
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="loadPatient(${p.patient_id}); hideQueue();">
                                <i class="mdi mdi-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    $container.html(html);
}

function displayDeceasedQueue(records) {
    const $container = $('#queue-view .queue-view-content');

    if (records.length === 0) {
        $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-emoticon-dead-outline mdi-48px"></i><br>No deceased patients pending last office</div>');
        return;
    }

    let html = '<div class="row p-2">';
    records.forEach(r => {
        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card-modern queue-patient-card" style="border-left: 4px solid #6c757d;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <h6 class="mb-1">${r.name || 'N/A'}</h6>
                            <span class="badge bg-dark">${r.death_type}</span>
                        </div>
                        <small class="text-muted d-block">${r.file_no || ''} | ${r.gender}, ${r.age}</small>
                        <hr class="my-2">
                        <div class="mb-2 small">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Date of Death:</span>
                                <span>${r.date_of_death} ${r.time_of_death}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Certified By:</span>
                                <span>${r.certified_by}</span>
                            </div>
                        </div>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-dark w-100" onclick="openLastOfficeModal(${r.id}, '${(r.name || '').replace(/'/g, "\\'")}')">
                                <i class="mdi mdi-medical-bag"></i> Last Office Procedure
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';

    $container.html(html);
}

function openLastOfficeModal(recordId, patientName) {
    $('#last-office-record-id').val(recordId);
    $('#last-office-patient-name').text(patientName);
    $('#last-office-disposition').val('');
    $('#last-office-notes').val('');
    $('#lastOfficeModal').modal('show');
}

$(document).on('click', '#btn-submit-last-office', function() {
    const recordId = $('#last-office-record-id').val();
    const disposition = $('#last-office-disposition').val();
    const notes = $('#last-office-notes').val();

    if (!disposition) {
        toastr.warning('Please select a disposition.');
        return;
    }

    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

    $.ajax({
        url: '{{ url('/nursing-workbench/deceased') }}/' + recordId + '/last-office',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            disposition: disposition,
            notes: notes
        },
        success: function(response) {
            toastr.success(response.message);
            $('#lastOfficeModal').modal('hide');
            loadQueueCounts();
            if (typeof currentQueueFilter !== 'undefined' && currentQueueFilter === 'deceased') {
                loadQueueData('deceased');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to complete last office.');
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="mdi mdi-check-circle"></i> Complete Procedure');
        }
    });
});

// ===== EMERGENCY WARD TRANSFER =====
function openTransferWardModal(admissionId, patientName) {
    $('#transfer-ward-admission-id').val(admissionId);
    $('#transfer-ward-patient-name').text(patientName);
    $('#transfer-ward-bed-select').html('<option value="">Loading beds...</option>');

    // Load available non-emergency wards/beds
    $.get('{{ route("nursing-workbench.ward-dashboard.available-beds") }}', function(beds) {
        const $sel = $('#transfer-ward-bed-select').empty().append('<option value="">-- Select target bed --</option>');
        beds.forEach(function(b) {
            $sel.append(`<option value="${b.id}">${b.name} — ${b.ward_name}</option>`);
        });
    });

    $('#transferWardModal').modal('show');
}

function submitWardTransfer() {
    const admissionId = $('#transfer-ward-admission-id').val();
    const bedId = $('#transfer-ward-bed-select').val();

    if (!bedId) {
        toastr.warning('Please select a target bed.');
        return;
    }

    const $btn = $('#transfer-ward-submit-btn');
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Transferring...');

    $.ajax({
        url: '{{ url("nursing-workbench") }}/admission/' + admissionId + '/transfer-ward',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            bed_id: bedId
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                $('#transferWardModal').modal('hide');
                loadQueueCounts();
                // Refresh emergency queue view
                if (currentQueueFilter === 'emergency') {
                    loadQueueData('emergency');
                }
            } else {
                toastr.error(response.message || 'Transfer failed.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Server error during transfer.');
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Transfer Patient');
        }
    });
}

// Create vital tooltip element (defined early to avoid hoisting issues)
function createVitalTooltip() {
    vitalTooltip = $('<div class="vital-tooltip"></div>').appendTo('body');

    // Hide on mouse leave
    $(document).on('mouseleave', '.vital-item', function() {
        vitalTooltip.removeClass('active');
    });
}

$(document).ready(function() {
    // Initialize
    if (window.BillingKit && window.BILLING_KIT_CONFIG) {
        BillingKit.init(window.BILLING_KIT_CONFIG);
    }
    loadQueueCounts();
    startQueueRefresh();
    initializeEventListeners();
    loadUserPreferences();
    createVitalTooltip();
    updateQuickActions(); // Set initial state for patient-dependent buttons

    // Auto-select patient from URL query parameter (e.g., from Patient list workbench button)
    const urlParams = new URLSearchParams(window.location.search);
    const patientId = urlParams.get('patient_id');
    if (patientId) {
        loadPatient(patientId);
    }

    // Auto-open queue from URL parameter (e.g., from dashboard queue widget click)
    const queueFilter = urlParams.get('queue_filter');
    if (queueFilter && ['admitted', 'vitals', 'bed-requests', 'discharge-requests', 'medication-due', 'emergency'].includes(queueFilter)) {
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
        // Check if patient is selected
        if (!currentPatient) {
            toastr.warning('Please select a patient first');
            return;
        }
        // Open clinical context modal with shared module
        ClinicalContext.load(currentPatient);
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

    // Queue filter buttons - use data-filter and showQueue
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
    window.currentPatientId = patientId;

    if (window.loadUnviewedCounts) {
        window.loadUnviewedCounts(patientId);
    }

    // Set PATIENT_ID for medication and I/O charts
    PATIENT_ID = patientId;

    // Initialize patient summary manager for LLM integration
    if (typeof PatientSummaryManager !== 'undefined') {
        window.patientSummary = new PatientSummaryManager({
            patientId: patientId,
            encounterId: null,
            autoOpen: false
        });
    }

    // CRITICAL: Hide all other views first to prevent stacking
    hideAllViews();

    // Show patient workspace
    $('#workspace-content').show().addClass('active');
    $('#patient-header').addClass('active');

    // Mobile: Switch to work pane
    $('#left-panel').addClass('hidden');
    $('#main-workspace').addClass('active');

    // Update quick actions visibility
    updateQuickActions();

    // Load patient details
    $.ajax({
        url: `{{ url('/nursing-workbench/patient/${patientId}/details') }}`,
        method: 'GET',
        success: function(data) {
            console.log('Patient details loaded:', data); // Debug log
            currentPatientData = data; // Store patient data including allergies

            // Store patient weight for dose calculators (last recorded weight from vitals)
            window.patientWeight = data.last_weight || null;

            displayPatientInfo(data);

            // Load overview content using the already fetched data
            populateOverviewTab(data);

            // Initialize Clinical Requests module early — search handlers must bind
            // before any potentially-failing module init below
            try { ClinicalRequests.init(patientId); } catch(e) { console.error('ClinicalRequests init error:', e); }

            // Initialize Clinical Alerts
            try { 
                $('#btn-manage-alerts').show();
                if(typeof ClinicalAlerts !== 'undefined') {
                    ClinicalAlerts.init(patientId, 'nurses_maternity');
                }
            } catch(e) { console.error('ClinicalAlerts init error:', e); }

            // Initialize medication and I/O charts for this patient
            initMedicationChart(patientId);
            initIntakeOutputChart(patientId);

            // Initialize Unified Vitals
            if(typeof window.initUnifiedVitals === 'function') {
                window.initUnifiedVitals(patientId, null, data.clinic_name, data.vitals_template, data.dynamic_ranges);
            }

            // Load other tab data
            loadInjectionHistory(patientId);
            loadImmunizationSchedule(patientId);
            loadImmunizationHistory(patientId);
            if (window.BillingKit) {
                BillingKit.setPatient(patientId);
            }
            loadNotesHistory(patientId);

            // Reset note autosave state when switching patients
            if (typeof WorkbenchNotesKit !== 'undefined' && WorkbenchNotesKit.autosaveTimers['nursing']) {
                clearTimeout(WorkbenchNotesKit.autosaveTimers['nursing']);
            }
            if (typeof nurseNoteAutosaveTimer !== 'undefined') {
                clearTimeout(nurseNoteAutosaveTimer);
            }
            $('#note-autosave-status').html('');
            if (nursingNoteEditor) { try { nursingNoteEditor.setData(''); } catch(e) {} }

            // Initialize procedures DataTable
            initializeProceduresDataTable(patientId);

            // Switch to overview tab
            switchWorkspaceTab('overview');
        },
        error: function(xhr) {
            console.error('Failed to load patient data:', xhr);
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
            url: `{{ url('/investigationHistoryList/${patientId}') }}`,
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

// =====================================
// CLINICAL REQUESTS MODULE
// =====================================
const ClinicalRequests = (function() {
    let patientId = null;
    let selectedProcedures = [];
    let crDoseStructuredMode = true; // Plan §2.2: structured is now the default
    const CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
    const procedureCategoryId = {{ appsettings('procedure_category_id', 0) }};
    const investigationCategoryId = '{{ appsettings("investigation_category_id", "") }}';

    function init(pid) {
        patientId = pid;
        $('#cr-patient-badge').text('Patient #' + pid).removeClass('bg-info').addClass('bg-primary');
        selectedProcedures = [];

        // Initialize Non-Pharmacological Bedside Care Orders
        window.NonPharmManager.init({
            patientId: pid,
            encounterId: null,
            containerId: '#cr-non-pharm-container',
            isNurseView: true
        });

        // Setup search handlers FIRST (only once) — must bind before any
        // ClinicalOrdersKit calls that could throw and abort the rest of init()
        if (!ClinicalRequests._searchBound) {
            setupSearchHandlers();
            ClinicalRequests._searchBound = true;
        }

        // Clear selection tables
        $('#cr-selected-products').empty();
        $('#cr-selected-labs').empty();
        $('#cr-selected-imaging').empty();
        $('#cr-selected-procedures').empty();

        // Init history DataTables
        initPrescHistory();
        initLabHistory();
        initImagingHistory();
        initProcHistory();

        // Clear duplicate tracking (Plan §4.4)
        ClinicalOrdersKit.clearAddedIds();

        // Initialize dose mode toggle, treatment plans, re-prescribe — wrapped in
        // try-catch so errors here cannot block the rest of the workbench
        try {
            if (!ClinicalRequests._doseToggleInit) {
                var nurseDoseState = ClinicalOrdersKit.initDoseModeToggle({
                    prefix: 'cr_',
                    cssPrefix: 'cr-',
                    tableSelector: '#cr-selected-products',
                    idInputName: 'cr_presc_id[]',
                    doseInputName: 'cr_presc_dose[]',
                    onchange: 'ClinicalOrdersKit.updateDoseValue(this, "cr-")',
                    onToggle: function(isStructured) { crDoseStructuredMode = isStructured; }
                });
                crDoseStructuredMode = nurseDoseState.isStructured;

                // Phase 2b (Plan §4.3): Register debounced dose auto-save for medications
                ClinicalOrdersKit.onDoseUpdate('cr-', function(recordId, doseValue, flashEl) {
                    ClinicalOrdersKit.debouncedUpdate({
                        url: '{{ url('/nursing-workbench/clinical-requests/prescriptions') }}/' + recordId + '/dose',
                        payload: { dose: doseValue },
                        csrfToken: CSRF_TOKEN,
                        flashTarget: flashEl,
                        onSuccess: function() { initPrescHistory(); }
                    });
                });

                // Phase 4d (Plan §6.4): Initialize treatment plans module
                ClinicalOrdersKit.initTreatmentPlans({
                    applyUrl: '/nursing-workbench/clinical-requests/apply-treatment-plan',
                    csrfToken: CSRF_TOKEN,
                    extraPayload: { patient_id: patientId },
                    onApplySuccess: function(response) {
                        initLabHistory();
                        initImagingHistory();
                        initPrescHistory();
                        initProcHistory();
                    },
                    currentItemsGatherer: function() {
                        // Gather all auto-saved items from selection tables (all 4 types)
                        var items = [];
                        $('#cr-selected-labs tr[data-record-id]').each(function() {
                            items.push({
                                item_type: 'lab',
                                reference_id: parseInt($(this).data('service-id')),
                                display_name: $(this).find('td:first').text().trim(),
                                note: $(this).find('input[name="cr_lab_note[]"]').val() || ''
                            });
                        });
                        $('#cr-selected-imaging tr[data-record-id]').each(function() {
                            items.push({
                                item_type: 'imaging',
                                reference_id: parseInt($(this).data('service-id')),
                                display_name: $(this).find('td:first').text().trim(),
                                note: $(this).find('input[name="cr_imaging_note[]"]').val() || ''
                            });
                        });
                        $('#cr-selected-products tr[data-record-id]').each(function() {
                            items.push({
                                item_type: 'medication',
                                reference_id: parseInt($(this).data('service-id')),
                                display_name: $(this).find('td:first').text().trim(),
                                dose: $(this).find('input[name="cr_presc_dose[]"]').val() || ''
                            });
                        });
                        $('#cr-selected-procedures tr[data-record-id]').each(function() {
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
                    recentUrl: '/nursing-workbench/clinical-requests/recent-encounters',
                    encounterItemsUrl: '/nursing-workbench/clinical-requests/encounter-items/{id}',
                    rePrescribeUrl: '/nursing-workbench/clinical-requests/re-prescribe',
                    csrfToken: CSRF_TOKEN,
                    extraPayload: { patient_id: patientId },
                    dropdownSelector: '#cr-rp-encounter-dropdown',
                    onRePrescribed: function() {
                        initLabHistory();
                        initImagingHistory();
                        initPrescHistory();
                        initProcHistory();
                    }
                });

                ClinicalRequests._doseToggleInit = true;
            }

            // A5 fix: Update treatment plan & re-prescribe config on EVERY patient switch
            // (Plan §6.4 + §5.3) — keeps extraPayload.patient_id current
            ClinicalOrdersKit.updateTreatmentPlanConfig({ extraPayload: { patient_id: patientId } });
            ClinicalOrdersKit.updateRePrescribeConfig({ extraPayload: { patient_id: patientId } });
        } catch (e) {
            console.error('ClinicalRequests: error initializing ClinicalOrdersKit features:', e);
        }
    }

    // ===== HISTORY DATATABLES =====
    function initPrescHistory() {
        if ($.fn.DataTable.isDataTable('#cr_presc_history_list')) {
            $('#cr_presc_history_list').DataTable().destroy();
        }
        $('#cr_presc_history_list').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '{{ url('/prescHistoryList') }}/' + patientId, type: 'GET' },
            columns: [{ data: 'info', name: 'info', orderable: false }],
            order: [[0, 'desc']], pageLength: 10,
            language: { emptyTable: 'No prescription history', processing: '<i class="fa fa-spinner fa-spin"></i> Loading...' }
        });
    }
    function initLabHistory() {
        if ($.fn.DataTable.isDataTable('#cr_lab_history_list')) {
            $('#cr_lab_history_list').DataTable().destroy();
        }
        $('#cr_lab_history_list').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '{{ url('/investigationHistoryList') }}/' + patientId, type: 'GET' },
            columns: [{ data: 'info', name: 'info', orderable: false }],
            order: [[0, 'desc']], pageLength: 10,
            language: { emptyTable: 'No lab history', processing: '<i class="fa fa-spinner fa-spin"></i> Loading...' }
        });
    }
    function initImagingHistory() {
        if ($.fn.DataTable.isDataTable('#cr_imaging_history_list')) {
            $('#cr_imaging_history_list').DataTable().destroy();
        }
        $('#cr_imaging_history_list').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '{{ url('/imagingHistoryList') }}/' + patientId, type: 'GET' },
            columns: [{ data: 'info', name: 'info', orderable: false }],
            order: [[0, 'desc']], pageLength: 10,
            language: { emptyTable: 'No imaging history', processing: '<i class="fa fa-spinner fa-spin"></i> Loading...' }
        });
    }
    function initProcHistory() {
        if ($.fn.DataTable.isDataTable('#cr_proc_history_list')) {
            $('#cr_proc_history_list').DataTable().destroy();
        }
        $('#cr_proc_history_list').DataTable({
            processing: true, serverSide: true,
            ajax: { url: '{{ url('/procedureHistoryList') }}/' + patientId, type: 'GET' },
            columns: [
                { data: 'info', name: 'info', orderable: false, searchable: false }
            ],
            order: [], pageLength: 10,
            language: { emptyTable: 'No procedure history', processing: '<i class="fa fa-spinner fa-spin"></i> Loading...' }
        });
    }

    // ===== SEARCH HANDLERS =====
    function setupSearchHandlers() {
        let searchTimeout;

        // Re-order / Re-prescribe from history (Plan §5.2)
        $(document).off('click.reorder').on('click.reorder', '.re-order-btn', function() {
            var $btn = $(this);
            if ($btn.prop('disabled')) return;

            var type          = $btn.data('type');
            var name          = $btn.data('name');
            var price         = $btn.data('price') || 0;
            var coverageMode  = $btn.data('coverage-mode') || null;
            var claims        = $btn.data('claims') || null;
            var payable       = $btn.data('payable') || null;
            if (coverageMode === '') coverageMode = null;

            if (type === 'labs') {
                var serviceId = parseInt($btn.data('service-id'));
                if (ClinicalOrdersKit.isAlreadyAdded('labs', serviceId)) {
                    toastr.warning(name + ' is already in your current lab requests');
                    return;
                }
                addLabService(name, serviceId, price, coverageMode, claims, payable);
            } else if (type === 'imaging') {
                var serviceId = parseInt($btn.data('service-id'));
                if (ClinicalOrdersKit.isAlreadyAdded('imaging', serviceId)) {
                    toastr.warning(name + ' is already in your current imaging requests');
                    return;
                }
                addImagingService(name, serviceId, price, coverageMode, claims, payable);
            } else if (type === 'prescriptions') {
                var productId = parseInt($btn.data('product-id'));
                if (ClinicalOrdersKit.isAlreadyAdded('meds', productId)) {
                    toastr.warning(name + ' is already in your current prescriptions');
                    return;
                }
                addProduct(name, productId, price, coverageMode, claims, payable);
            }

            $btn.prop('disabled', true).html('<i class="fa fa-check text-success"></i> Added');
        });

        // Initialize floating dropdowns (escape overflow:hidden ancestors)
        ClinicalOrdersKit.initSearchDropdown('#cr_presc_search', '#cr_presc_results');
        ClinicalOrdersKit.initSearchDropdown('#cr_lab_search', '#cr_lab_results');
        ClinicalOrdersKit.initSearchDropdown('#cr_imaging_search', '#cr_imaging_results');
        ClinicalOrdersKit.initSearchDropdown('#cr_proc_search', '#cr_proc_results');

        // Drug search
        $('#cr_presc_search').on('keyup', function() {
            const q = $(this).val();
            clearTimeout(searchTimeout);
            if (q.length < 2) { $('#cr_presc_results').hide(); return; }
            ClinicalOrdersKit.positionDropdown('#cr_presc_search', '#cr_presc_results');
            ClinicalOrdersKit.showSearchLoading('#cr_presc_results');
            searchTimeout = setTimeout(() => searchProducts(q), 300);
        });

        // Lab search
        $('#cr_lab_search').on('keyup', function() {
            const q = $(this).val();
            clearTimeout(searchTimeout);
            if (q.length < 2) { $('#cr_lab_results').hide(); return; }
            ClinicalOrdersKit.positionDropdown('#cr_lab_search', '#cr_lab_results');
            ClinicalOrdersKit.showSearchLoading('#cr_lab_results');
            searchTimeout = setTimeout(() => searchLabServices(q), 300);
        });

        // Imaging search
        $('#cr_imaging_search').on('keyup', function() {
            const q = $(this).val();
            clearTimeout(searchTimeout);
            if (q.length < 2) { $('#cr_imaging_results').hide(); return; }
            ClinicalOrdersKit.positionDropdown('#cr_imaging_search', '#cr_imaging_results');
            ClinicalOrdersKit.showSearchLoading('#cr_imaging_results');
            searchTimeout = setTimeout(() => searchImagingServices(q), 300);
        });

        // Procedure search
        $('#cr_proc_search').on('keyup', function() {
            const q = $(this).val();
            clearTimeout(searchTimeout);
            if (q.length < 2) { $('#cr_proc_results').hide(); return; }
            ClinicalOrdersKit.positionDropdown('#cr_proc_search', '#cr_proc_results');
            ClinicalOrdersKit.showSearchLoading('#cr_proc_results');
            searchTimeout = setTimeout(() => searchProcedureServices(q), 300);
        });
    }

    // ===== SEARCH FUNCTIONS =====
    let crSearchProdTimeout = null;
    let crSearchProdRequest = null;

    function searchProducts(q) {
        if (crSearchProdRequest) crSearchProdRequest.abort();
        clearTimeout(crSearchProdTimeout);

        const $res = $('#cr_presc_results');
        if (q.length < 2) { $res.empty().hide(); return; }

        $res.html('<li class="list-group-item text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</li>').show();

        crSearchProdTimeout = setTimeout(() => {
            crSearchProdRequest = $.ajax({
                url: '{{ url('/live-search-products') }}',
                method: 'GET',
                dataType: 'json',
                data: { term: q, patient_id: patientId },
                success: (data) => {
                    $res.empty();
                    ClinicalOrdersKit.appendFreeFormLink($res, q, 'Add Free-Form Medication', 'Enter medication name:', '#cr_presc_search', function(val) {
                        ClinicalRequests.addProduct(val + ' [Free-form]', 'FF_' + val, 0, 'cash', 0, 0);
                    });
                    if (!data.length) { ClinicalOrdersKit.showSearchEmpty('#cr_presc_results', 'products'); return; }
                    else {
                        data.forEach(item => {
                            const name = item.product_name || 'Unknown';
                            const code = item.product_code || '';
                            const qty = item.stock?.current_quantity ?? 0;
                            const price = item.price?.initial_sale_price ?? 0;
                            const payable = item.payable_amount ?? price;
                            const claims = item.claims_amount ?? 0;
                            const mode = item.coverage_mode || null;
                            const displayName = `${name}[${code}](${qty} avail.)`;

                            const isCombo = item.is_combo || false;
                            const bundleItems = item.bundle_items || [];
                            if (isCombo) { window.comboDataMap = window.comboDataMap || {}; window.comboDataMap[item.id] = item; }

                            const alreadyAdded = isCombo ? false : ClinicalOrdersKit.isAlreadyAdded('meds', parseInt(item.id));
                            const onClick = alreadyAdded ? '' : (isCombo ? `ClinicalRequests.applyProductCombo(${item.id}, '${name.replace(/'/g,"\\'")}')` : `ClinicalRequests.addProduct('${displayName.replace(/'/g,"\\'")}', ${item.id}, ${price}, '${mode}', ${claims}, ${payable})`);
                            $res.append(ClinicalOrdersKit.renderSearchResultItem({
                                id: item.id,
                                name: name,
                                code: code,
                                qty: qty,
                                price: price,
                                payable: payable,
                                claims: claims,
                                mode: mode,
                                alreadyAdded: alreadyAdded,
                                alreadyLabel: 'Already Added',
                                onClick: onClick,
                                isCombo: isCombo,
                                bundleItems: bundleItems
                            }));
                        });
                    }
                    $res.show();
                },
                error: (jqXHR, textStatus) => {
                    if (textStatus !== 'abort') {
                        $res.html('<li class="list-group-item text-center text-danger">Error fetching results</li>').show();
                    }
                }
            });
        }, 300);
    }

    function searchLabServices(q) {
        const reqData = { term: q, patient_id: patientId };
        if (investigationCategoryId) reqData.category_id = investigationCategoryId;
        if (typeof SearchManager !== 'undefined') {
            SearchManager.execute({
                inputVal: q, minLength: 2, url: '{{ url('/live-search-services') }}', data: reqData,
                onStart: () => $('#cr_lab_results').html('<li class="list-group-item text-center text-muted"><i class="mdi mdi-loading mdi-spin"></i> Searching...</li>'),
                onEmptyQuery: () => $('#cr_lab_results').empty(),
                onSuccess: (data) => {
                    const $res = $('#cr_lab_results').empty();
                    ClinicalOrdersKit.appendFreeFormLink($res, q, 'Add Free-Form Lab Test', 'Enter lab test name:', '#cr_lab_search', function(val) {
                        ClinicalRequests.addLabService(val + ' [Free-form]', 'FF_' + val, 0, 'cash', 0, 0);
                    });
            if (!data.length) { ClinicalOrdersKit.showSearchEmpty('#cr_lab_results', 'lab services'); return; }
            else {
                data.forEach(item => {
                    const name = item.service_name || 'Unknown';
                    const code = item.service_code || '';
                    const price = item.price?.sale_price ?? 0;
                    const payable = item.payable_amount ?? price;
                    const claims = item.claims_amount ?? 0;
                    const mode = item.coverage_mode || null;
                    const isCombo = item.is_combo || false;
                    const bundleItems = item.bundle_items || [];
                    if (isCombo) { window.comboDataMap = window.comboDataMap || {}; window.comboDataMap[item.id] = item; }
                    
                    // For combos, check if already applied; for direct items, check if added to table
                    const alreadyAdded = false; // combos can't really be "added" twice, but can be selected again
                    const onClick = isCombo 
                        ? `ClinicalRequests.applyLabCombo(${item.id}, '${name.replace(/'/g,"\\'")}')` 
                        : `ClinicalRequests.addLabService('${(name+'['+code+']').replace(/'/g,"\\'")}', ${item.id}, ${price}, '${mode}', ${claims}, ${payable})`;
                    
                    $res.append(ClinicalOrdersKit.renderSearchResultItem({
                        id: item.id,
                        category: item.category?.category_name || 'Lab',
                        name: name,
                        code: code,
                        price: price,
                        payable: payable,
                        claims: claims,
                        mode: mode,
                        alreadyAdded: alreadyAdded,
                        alreadyLabel: 'Already Added',
                        onClick: onClick,
                        isCombo: isCombo,
                        bundleItems: bundleItems
                    }));
                });
            }
            $res.show();
                }
            });
        }
    }

    function searchImagingServices(q) {
        $.get('/live-search-services', { term: q, category_id: 6, patient_id: patientId }, function(data) {
            const $res = $('#cr_imaging_results').empty();
            ClinicalOrdersKit.appendFreeFormLink($res, q, 'Add Free-Form Imaging Request', 'Enter imaging request name:', '#cr_imaging_search', function(val) {
                ClinicalRequests.addImagingService(val + ' [Free-form]', 'FF_' + val, 0, 'cash', 0, 0);
            });
            if (!data.length) { ClinicalOrdersKit.showSearchEmpty('#cr_imaging_results', 'imaging services'); return; }
            else {
                data.forEach(item => {
                    const name = item.service_name || 'Unknown';
                    const code = item.service_code || '';
                    const price = item.price?.sale_price ?? 0;
                    const payable = item.payable_amount ?? price;
                    const claims = item.claims_amount ?? 0;
                    const mode = item.coverage_mode || null;
                    const isCombo = item.is_combo || false;
                    const bundleItems = item.bundle_items || [];
                    if (isCombo) { window.comboDataMap = window.comboDataMap || {}; window.comboDataMap[item.id] = item; }
                    
                    const alreadyAdded = false; // combos can't be "added" twice
                    const onClick = isCombo 
                        ? `ClinicalRequests.applyImagingCombo(${item.id}, '${name.replace(/'/g,"\\'")}')` 
                        : `ClinicalRequests.addImagingService('${(name+'['+code+']').replace(/'/g,"\\'")}', ${item.id}, ${price}, '${mode}', ${claims}, ${payable})`;
                    
                    $res.append(ClinicalOrdersKit.renderSearchResultItem({
                        id: item.id,
                        category: item.category?.category_name || 'Imaging',
                        name: name,
                        code: code,
                        price: price,
                        payable: payable,
                        claims: claims,
                        mode: mode,
                        alreadyAdded: alreadyAdded,
                        alreadyLabel: 'Already Added',
                        onClick: onClick,
                        isCombo: isCombo,
                        bundleItems: bundleItems
                    }));
                });
            }
            $res.show();
        });
    }

    function searchProcedureServices(q) {
        $.get('/live-search-services', { term: q, category_id: procedureCategoryId, patient_id: patientId }, function(data) {
            const $res = $('#cr_proc_results').empty();
            ClinicalOrdersKit.appendFreeFormLink($res, q, 'Add Free-Form Procedure', 'Enter procedure name:', '#cr_proc_search', function(val) {
                ClinicalRequests.addProcedure({ id: 'FF_' + val, service_name: val + ' [Free-form]', price: {sale_price: 0}, claims_amount: 0, coverage_mode: 'cash' });
            });
            if (!data.length) { ClinicalOrdersKit.showSearchEmpty('#cr_proc_results', 'procedures'); return; }
            else {
                data.forEach(item => {
                    const isSelected = ClinicalOrdersKit.isAlreadyAdded('procedures', item.id);
                    const name = item.service_name || 'Unknown';
                    const code = item.service_code || '';
                    const price = item.price?.sale_price ?? 0;
                    const payable = item.payable_amount ?? price;
                    const onClick = isSelected ? '' : `ClinicalRequests.addProcedure(${JSON.stringify(item).replace(/"/g, '&quot;')})`;
                    $res.append(ClinicalOrdersKit.renderSearchResultItem({
                        id: item.id,
                        category: item.category?.category_name || 'Procedure',
                        name: name,
                        code: code,
                        price: price,
                        payable: payable,
                        claims: item.claims_amount ?? 0,
                        mode: item.coverage_mode || null,
                        alreadyAdded: isSelected,
                        alreadyLabel: 'Already Added',
                        onClick: onClick
                    }));
                });
            }
            $res.show();
        });
    }

    // ===== ADD TO SELECTION TABLE =====
    // Phase 2b (Plan §4.3): Two-phase medication auto-save
    // Phase 1 — instant POST with empty dose; Phase 2 — debounced PUT on dose field changes
    function addProduct(name, id, price, mode, claims, payable) {
        const rowId = 'crx_' + Date.now() + '_' + id;
        const coverageBadge = ClinicalOrdersKit.renderCoverageBadge(
            mode && mode !== 'null' ? mode : null, payable ?? price, claims ?? 0
        );

        ClinicalOrdersKit.addItem({
            url: '{{ url('/nursing-workbench/clinical-requests/add-prescription') }}',
            payload: { patient_id: patientId, product_id: id, dose: '' },
            csrfToken: CSRF_TOKEN,
            tableSelector: '#cr-selected-products',
            type: 'meds',
            referenceId: parseInt(id),
            buildRowHtml: function(resp) {
                const recordId = resp.id;
                const doseOnchange = "ClinicalOrdersKit.updateDoseValue(this, 'cr-'); ";

                let doseCell;
                if (crDoseStructuredMode) {
                    doseCell = '<td>' + ClinicalOrdersKit.buildStructuredDoseHtml({
                        cssPrefix: 'cr-',
                        hiddenName: 'cr_presc_dose[]',
                        onchange: doseOnchange,
                        drugName: name,
                        rowId: rowId
                    }) + '<input type="hidden" name="cr_presc_id[]" value="' + id + '"></td>';
                } else {
                    var simpleDoseCmd = "ClinicalOrdersKit.updateDoseValue(this, 'cr-');";
                    doseCell = '<td><input type="text" class="form-control form-control-sm" name="cr_presc_dose[]" ' +
                        'placeholder="e.g. 500mg BD x 5days" ' +
                        'onfocus="ClinicalOrdersKit.startPeriodicSave(this)" ' +
                        'onblur="ClinicalOrdersKit.stopPeriodicSave(this); ClinicalOrdersKit.cancelIdleTimer(this); ' + simpleDoseCmd + '" ' +
                        'oninput="ClinicalOrdersKit.scheduleIdleUpdate(this, function(){ ' + simpleDoseCmd + ' }, 3000)" required>' +
                        '<input type="hidden" name="cr_presc_id[]" value="' + id + '"></td>';
                }

                return '<tr data-record-id="' + recordId + '" data-record-type="prescription" data-service-id="' + id + '" data-drug-name="' + name.replace(/"/g, '&quot;') + '" data-row-id="' + rowId + '">' +
                    '<td>' + name + coverageBadge + '</td>' +
                    '<td>' + (payable ?? price) + '</td>' +
                    doseCell +
                    '<td><button class="btn btn-sm btn-danger" onclick="ClinicalRequests.removeAutoSavedRow(this,\'prescription\',' + recordId + ',' + id + ')"><span class="co-remove-btn"><i class="fa fa-times"></i></span></button></td>' +
                '</tr>';
            },
            onSuccess: function(resp) {
                initPrescHistory();
            }
        });
        $('#cr_presc_search').val('');
        $('#cr_presc_results').hide();
    }

    // Legacy dose functions (buildCrStructuredDoseHtml, crFreqMultiplierMap, crDurUnitMultiplierMap,
    // autoCalculateCrQty, updateDoseVal, toggleDoseMode) removed.
    // All dose logic now lives in ClinicalOrdersKit (clinical-orders-shared.js) per Plan §2.1—2.3.

    // Phase 0d (Plan §2.3): Old global calculator functions removed.
    // Per-drug inline calculator now lives in ClinicalOrdersKit (clinical-orders-shared.js).

    function addLabService(name, id, price, mode, claims, payable) {
        var csrfToken = $('meta[name="csrf-token"]').attr('content');

        ClinicalOrdersKit.addItem({
            url: '{{ url('/nursing-workbench/clinical-requests/add-lab') }}',
            payload: { service_id: id, patient_id: patientId, note: '' },
            csrfToken: csrfToken,
            tableSelector: '#cr-selected-labs',
            type: 'labs',
            referenceId: parseInt(id),
            buildRowHtml: function(response) {
                var coverageBadge = mode && mode !== 'null' ? '<div class="small mt-1"><span class="badge bg-info">' + (mode||'').toUpperCase() + '</span> <span class="text-danger">Pay: ' + payable + '</span> <span class="text-success">Claims: ' + claims + '</span></div>' : '';
                return '<tr data-record-id="' + response.id + '" data-record-type="lab" data-service-id="' + id + '">' +
                    '<td>' + name + coverageBadge + '</td>' +
                    '<td>' + (payable ?? price) + '</td>' +
                    '<td><input type="text" class="form-control form-control-sm" name="cr_lab_note[]" placeholder="e.g. Fasting, urgent, repeat in 2wks" ' +
                    'onblur="ClinicalOrdersKit.cancelIdleTimer(this); ClinicalOrdersKit.debouncedUpdate({url:\'/nursing-workbench/clinical-requests/labs/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + csrfToken + '\',flashTarget:this.closest(\'td\')})" ' +
                    'oninput="ClinicalOrdersKit.scheduleIdleUpdate(this, function(){ ClinicalOrdersKit.debouncedUpdate({url:\'/nursing-workbench/clinical-requests/labs/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + csrfToken + '\',flashTarget:this.closest(\'td\')}) }, 3000)">' +
                    '<input type="hidden" name="cr_lab_id[]" value="' + id + '"></td>' +
                    '<td><button class="btn btn-sm btn-danger" onclick="ClinicalRequests.removeAutoSavedRow(this,\'lab\',' + response.id + ',' + id + ')"><span class="co-remove-btn"><i class="fa fa-times"></i></span></button></td>' +
                '</tr>';
            },
            onSuccess: function(resp) {
                initLabHistory();
            }
        });

        $('#cr_lab_search').val('');
        $('#cr_lab_results').hide();
    }

    function addImagingService(name, id, price, mode, claims, payable) {
        var csrfToken = $('meta[name="csrf-token"]').attr('content');

        ClinicalOrdersKit.addItem({
            url: '{{ url('/nursing-workbench/clinical-requests/add-imaging') }}',
            payload: { service_id: id, patient_id: patientId, note: '' },
            csrfToken: csrfToken,
            tableSelector: '#cr-selected-imaging',
            type: 'imaging',
            referenceId: parseInt(id),
            buildRowHtml: function(response) {
                var coverageBadge = mode && mode !== 'null' ? '<div class="small mt-1"><span class="badge bg-info">' + (mode||'').toUpperCase() + '</span> <span class="text-danger">Pay: ' + payable + '</span> <span class="text-success">Claims: ' + claims + '</span></div>' : '';
                return '<tr data-record-id="' + response.id + '" data-record-type="imaging" data-service-id="' + id + '">' +
                    '<td>' + name + coverageBadge + '</td>' +
                    '<td>' + (payable ?? price) + '</td>' +
                    '<td><input type="text" class="form-control form-control-sm" name="cr_imaging_note[]" placeholder="e.g. R/O fracture, contrast required" ' +
                    'onblur="ClinicalOrdersKit.cancelIdleTimer(this); ClinicalOrdersKit.debouncedUpdate({url:\'/nursing-workbench/clinical-requests/imaging/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + csrfToken + '\',flashTarget:this.closest(\'td\')})" ' +
                    'oninput="ClinicalOrdersKit.scheduleIdleUpdate(this, function(){ ClinicalOrdersKit.debouncedUpdate({url:\'/nursing-workbench/clinical-requests/imaging/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + csrfToken + '\',flashTarget:this.closest(\'td\')}) }, 3000)">' +
                    '<input type="hidden" name="cr_imaging_id[]" value="' + id + '"></td>' +
                    '<td><button class="btn btn-sm btn-danger" onclick="ClinicalRequests.removeAutoSavedRow(this,\'imaging\',' + response.id + ',' + id + ')"><span class="co-remove-btn"><i class="fa fa-times"></i></span></button></td>' +
                '</tr>';
            },
            onSuccess: function(resp) {
                initImagingHistory();
            }
        });

        $('#cr_imaging_search').val('');
        $('#cr_imaging_results').hide();
    }

    function addProcedure(item) {
        // Phase 2a (Plan §4.1): Auto-save procedure via ClinicalOrdersKit.addItem
        const procId = item.id;
        if (ClinicalOrdersKit.isAlreadyAdded('procedures', procId)) {
            toastr.warning('Procedure already added');
            return;
        }
        const priority = $('#cr_proc_priority').val();
        const scheduledDate = $('#cr_proc_scheduled_date').val();
        const preNotes = $('#cr_proc_notes').val();

        const payable = item.payable_amount ?? (item.price?.sale_price ?? 0);
        const priorityClass = { routine: 'bg-success', urgent: 'bg-warning text-dark', emergency: 'bg-danger' }[priority] || 'bg-secondary';
        const priorityLabel = priority.charAt(0).toUpperCase() + priority.slice(1);

        ClinicalOrdersKit.addItem({
            url: '{{ url('/nursing-workbench/clinical-requests/add-procedure') }}',
            payload: {
                patient_id: patientId,
                service_id: procId,
                priority: priority,
                scheduled_date: scheduledDate,
                pre_notes: preNotes
            },
            csrfToken: CSRF_TOKEN,
            tableSelector: '#cr-selected-procedures',
            type: 'procedures',
            referenceId: procId,
            buildRowHtml: function(resp) {
                var isFreeForm = String(procId).startsWith('FF_');
                var nameHtml = isFreeForm ? '<h6 class="mb-0"><span class="badge bg-info text-dark">' + (item.service_name || 'N/A').replace(' [Free-form]', '') + '</span></h6>' : '<strong>' + (item.service_name || 'N/A') + '</strong><br><small class="text-muted">' + (item.service_code || '') + '</small>';
                var priceHtml = isFreeForm ? '<span class="text-muted">N/A</span>' : 'NGN ' + payable;
                return '<tr data-record-id="' + resp.id + '" data-record-type="procedure" data-service-id="' + procId + '">' +
                    '<td>' + nameHtml +
                    (preNotes ? '<br><small class="text-info"><i class="fa fa-sticky-note"></i> ' + preNotes.substring(0, 60) + '</small>' : '') + '</td>' +
                    '<td>' + priceHtml + '</td>' +
                    '<td><span class="badge ' + priorityClass + '">' + priorityLabel + '</span>' +
                    (scheduledDate ? '<br><small>' + scheduledDate + '</small>' : '') + '</td>' +
                    '<td><button class="btn btn-sm btn-danger" onclick="ClinicalRequests.removeAutoSavedRow(this,\'procedure\',' + resp.id + ',' + procId + ')"><span class="co-remove-btn"><i class="fa fa-times"></i></span></button></td>' +
                '</tr>';
            },
            onSuccess: function() {
                initProcHistory();
            }
        });
        $('#cr_proc_search').val('');
        $('#cr_proc_results').hide();
    }

    function removeProcedure(procId) {
        // Legacy fallback — for non-auto-saved rows only
        selectedProcedures = selectedProcedures.filter(p => p.id !== procId);
        renderSelectedProcedures();
    }

    function renderSelectedProcedures() {
        const $tb = $('#cr-selected-procedures').empty();
        if (selectedProcedures.length === 0) {
            $tb.append('<tr><td colspan="4" class="text-center text-muted"><i class="fa fa-info-circle"></i> No procedures selected</td></tr>');
            return;
        }
        selectedProcedures.forEach(p => {
            const payable = p.payable_amount ?? (p.price?.sale_price ?? 0);
            const priorityClass = { routine: 'bg-success', urgent: 'bg-warning text-dark', emergency: 'bg-danger' }[p.priority] || 'bg-secondary';
            $tb.append(`
                <tr>
                    <td><strong>${p.service_name || 'N/A'}</strong><br><small class="text-muted">${p.service_code || ''}</small>${p.pre_notes ? '<br><small class="text-info"><i class="fa fa-sticky-note"></i> ' + p.pre_notes.substring(0, 60) + '</small>' : ''}</td>
                    <td>NGN ${payable}</td>
                    <td><span class="badge ${priorityClass}">${p.priority}</span>${p.scheduled_date ? '<br><small>' + p.scheduled_date + '</small>' : ''}</td>
                    <td><button class="btn btn-sm btn-danger" onclick="ClinicalRequests.removeProcedure(${p.id})"><span class="co-remove-btn"><i class="fa fa-times"></i></span></button></td>
                </tr>
            `);
        });
    }

    // ===== SAVE FUNCTIONS =====
    function showMessage(containerId, msg, type) {
        const alertType = type === 'error' ? 'danger' : type;
        $(`#${containerId}`).html(`<div class="alert alert-${alertType} alert-dismissible fade show">${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
        document.getElementById(containerId).scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        setTimeout(() => $(`#${containerId} .alert`).alert('close'), 5000);
    }

    // Phase 2b (Plan §4.3): skip auto-saved prescription rows
    function savePrescriptions() {
        if (!patientId) { toastr.error('No patient selected'); return; }
        const products = [], doses = [];
        let autoSavedCount = 0;

        $('#cr-selected-products tr').each(function() {
            // Skip rows already auto-saved (Phase 2b)
            if ($(this).data('record-id')) { autoSavedCount++; return; }
            const id = $(this).find('input[name="cr_presc_id[]"]').val();
            // Try structured hidden input first, fallback to text input
            let dose = $(this).find('.cr-structured-dose-value').val();
            if (dose === undefined || dose === null) {
                dose = $(this).find('input[name="cr_presc_dose[]"]').val();
            }
            if (id) { products.push(id); doses.push(dose || ''); }
        });

        // If ALL rows are auto-saved, show success and clear
        if (products.length === 0 && autoSavedCount> 0) {
            showMessage('cr_presc_message', autoSavedCount + ' prescription(s) already auto-saved', 'success');
            $('#cr-selected-products').empty();
            ClinicalOrdersKit.addedIds.meds.clear(); // A3 fix: only clear meds
            initPrescHistory();
            try { new bootstrap.Tab($('[data-bs-target="#cr-presc-history"]')[0]).show(); } catch(e) { $('[data-bs-target="#cr-presc-history"]').tab('show'); }
            return;
        }

        if (products.length === 0) { showMessage('cr_presc_message', 'No prescriptions selected.', 'error'); return; }

        const $btn = $('#cr-save-prescriptions-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        $.ajax({
            url: '{{ url('/nursing-workbench/clinical-requests/prescriptions') }}',
            method: 'POST',
            data: { patient_id: patientId, product_ids: products, doses: doses, _token: CSRF_TOKEN },
            success: function(r) {
                if (r.success) {
                    const saved = autoSavedCount> 0 ? ` (${autoSavedCount} auto-saved earlier)` : '';
                    showMessage('cr_presc_message', r.message + saved, 'success');
                    $('#cr-selected-products').empty();
                    ClinicalOrdersKit.addedIds.meds.clear(); // A3 fix: only clear meds
                    initPrescHistory();
                    // Switch to history tab
                    try { new bootstrap.Tab($('[data-bs-target="#cr-presc-history"]')[0]).show(); } catch(e) { $('[data-bs-target="#cr-presc-history"]').tab('show'); }
                } else showMessage('cr_presc_message', r.message, 'error');
            },
            error: function(xhr) { showMessage('cr_presc_message', xhr.responseJSON?.message || 'Server error', 'error'); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-content-save"></i> Save Prescriptions'); }
        });
    }

    function saveLabs() {
        if (!patientId) { toastr.error('No patient selected'); return; }
        const services = [], notes = [];
        var autoSavedCount = 0;

        $('#cr-selected-labs tr').each(function() {
            if ($(this).data('record-id')) { autoSavedCount++; return; }
            const id = $(this).find('input[name="cr_lab_id[]"]').val();
            const note = $(this).find('input[name="cr_lab_note[]"]').val();
            if (id) { services.push(id); notes.push(note || ''); }
        });

        // If all items were auto-saved
        if (services.length === 0 && autoSavedCount> 0) {
            showMessage('cr_lab_message', autoSavedCount + ' lab(s) already saved', 'success');
            $('#cr-selected-labs').empty();
            ClinicalOrdersKit.addedIds.labs.clear(); // A3 fix: only clear labs
            initLabHistory();
            try { new bootstrap.Tab($('[data-bs-target="#cr-lab-history"]')[0]).show(); } catch(e) { $('[data-bs-target="#cr-lab-history"]').tab('show'); }
            return;
        }

        if (services.length === 0) { showMessage('cr_lab_message', 'No lab services selected.', 'error'); return; }

        const $btn = $('#cr-save-labs-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        $.ajax({
            url: '{{ url('/nursing-workbench/clinical-requests/labs') }}',
            method: 'POST',
            data: { patient_id: patientId, service_ids: services, notes: notes, _token: CSRF_TOKEN },
            success: function(r) {
                if (r.success) {
                    showMessage('cr_lab_message', r.message, 'success');
                    $('#cr-selected-labs').empty();
                    initLabHistory();
                    // Switch to history tab
                    try { new bootstrap.Tab($('[data-bs-target="#cr-lab-history"]')[0]).show(); } catch(e) { $('[data-bs-target="#cr-lab-history"]').tab('show'); }
                } else showMessage('cr_lab_message', r.message, 'error');
            },
            error: function(xhr) { showMessage('cr_lab_message', xhr.responseJSON?.message || 'Server error', 'error'); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-content-save"></i> Save Lab Requests'); }
        });
    }

    function saveImaging() {
        if (!patientId) { toastr.error('No patient selected'); return; }
        const services = [], notes = [];
        var autoSavedCount = 0;

        $('#cr-selected-imaging tr').each(function() {
            if ($(this).data('record-id')) { autoSavedCount++; return; }
            const id = $(this).find('input[name="cr_imaging_id[]"]').val();
            const note = $(this).find('input[name="cr_imaging_note[]"]').val();
            if (id) { services.push(id); notes.push(note || ''); }
        });

        if (services.length === 0 && autoSavedCount> 0) {
            showMessage('cr_imaging_message', autoSavedCount + ' imaging request(s) already saved', 'success');
            $('#cr-selected-imaging').empty();
            ClinicalOrdersKit.addedIds.imaging.clear(); // A3 fix: only clear imaging
            initImagingHistory();
            try { new bootstrap.Tab($('[data-bs-target="#cr-imaging-history"]')[0]).show(); } catch(e) { $('[data-bs-target="#cr-imaging-history"]').tab('show'); }
            return;
        }

        if (services.length === 0) { showMessage('cr_imaging_message', 'No imaging services selected.', 'error'); return; }

        const $btn = $('#cr-save-imaging-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        $.ajax({
            url: '{{ url('/nursing-workbench/clinical-requests/imaging') }}',
            method: 'POST',
            data: { patient_id: patientId, service_ids: services, notes: notes, _token: CSRF_TOKEN },
            success: function(r) {
                if (r.success) {
                    showMessage('cr_imaging_message', r.message, 'success');
                    $('#cr-selected-imaging').empty();
                    initImagingHistory();
                    // Switch to history tab
                    try { new bootstrap.Tab($('[data-bs-target="#cr-imaging-history"]')[0]).show(); } catch(e) { $('[data-bs-target="#cr-imaging-history"]').tab('show'); }
                } else showMessage('cr_imaging_message', r.message, 'error');
            },
            error: function(xhr) { showMessage('cr_imaging_message', xhr.responseJSON?.message || 'Server error', 'error'); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-content-save"></i> Save Imaging Requests'); }
        });
    }

    function saveProcedures() {
        if (!patientId) { toastr.error('No patient selected'); return; }
        if (selectedProcedures.length === 0) { showMessage('cr_proc_message', 'No procedures selected.', 'error'); return; }

        const $btn = $('#cr-save-procedures-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        $.ajax({
            url: '{{ url('/nursing-workbench/clinical-requests/procedures') }}',
            method: 'POST',
            data: {
                patient_id: patientId,
                _token: CSRF_TOKEN,
                procedures: selectedProcedures.map(p => ({
                    service_id: p.id,
                    priority: p.priority,
                    scheduled_date: p.scheduled_date,
                    pre_notes: p.pre_notes
                }))
            },
            success: function(r) {
                if (r.success) {
                    showMessage('cr_proc_message', r.message, 'success');
                    selectedProcedures = [];
                    renderSelectedProcedures();
                    initProcHistory();
                    // Switch to history tab
                    try { new bootstrap.Tab($('[data-bs-target="#cr-proc-history"]')[0]).show(); } catch(e) { $('[data-bs-target="#cr-proc-history"]').tab('show'); }
                } else showMessage('cr_proc_message', r.message, 'error');
            },
            error: function(xhr) { showMessage('cr_proc_message', xhr.responseJSON?.message || 'Server error', 'error'); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-content-save"></i> Save Procedures'); }
        });
    }

    /**
     * Remove an auto-saved row (lab/imaging) via DELETE.
     * Called from inline onclick in auto-saved rows.
     */
    function removeAutoSavedRow(btn, type, recordId, serviceId) {
        var deleteUrl;
        var tableSelector;
        if (type === 'lab') {
            deleteUrl = '/nursing-workbench/clinical-requests/labs/' + recordId;
            tableSelector = '#cr-selected-labs';
        } else if (type === 'imaging') {
            deleteUrl = '/nursing-workbench/clinical-requests/imaging/' + recordId;
            tableSelector = '#cr-selected-imaging';
        } else if (type === 'prescription') {
            deleteUrl = '/nursing-workbench/clinical-requests/prescriptions/' + recordId;
            tableSelector = '#cr-selected-products';
        } else if (type === 'procedure') {
            deleteUrl = '/nursing-workbench/clinical-requests/procedures/' + recordId;
            tableSelector = '#cr-selected-procedures';
        }

        // Map onclick type strings to addedIds keys
        var idsType = { lab: 'labs', imaging: 'imaging', prescription: 'meds', procedure: 'procedures' }[type] || type;

        ClinicalOrdersKit.removeItem({
            url: deleteUrl,
            csrfToken: $('meta[name="csrf-token"]').attr('content'),
            rowSelector: $(btn).closest('tr'),
            type: idsType,
            referenceId: serviceId ? parseInt(serviceId) : null,
            tableSelector: tableSelector
        });
    }

    function applyProductCombo(comboId, comboName) {
        var comboData = (window.comboDataMap || {})[comboId] || {};
        var name = comboName || comboData.product_name || comboData.service_name || 'Combo';

        $('#cr_presc_search').val('');
        $('#cr_presc_results').hide();

        ComboConfirmModal.show({
            name        : name,
            bundleItems : comboData.bundle_items || [],
            price       : parseFloat(comboData.base_price || 0),
            payable     : parseFloat(comboData.payable_amount != null ? comboData.payable_amount : (comboData.base_price || 0)),
            claims      : parseFloat(comboData.claims_amount || 0),
            mode        : comboData.coverage_mode || null,
            onConfirm   : function() {
                $.ajax({
                    url         : '/nursing-workbench/clinical-requests/apply-combo',
                    method      : 'POST',
                    headers     : { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data        : JSON.stringify({ service_id: comboId, patient_id: patientId, note: '' }),
                    contentType : 'application/json',
                    dataType    : 'json',
                    success     : function(response) {
                        if (response.success) {
                            toastr.success(response.message, 'Combo Applied');
                            if (typeof initPrescHistory === 'function') { initPrescHistory(); }
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

    function applyLabCombo(comboId, comboName) {
        var comboData = (window.comboDataMap || {})[comboId] || {};
        var name = comboName || comboData.service_name || 'Combo';

        $('#cr_lab_search').val('');
        $('#cr_lab_results').hide();

        ComboConfirmModal.show({
            name        : name,
            bundleItems : comboData.bundle_items || [],
            price       : parseFloat(comboData.base_price || 0),
            payable     : parseFloat(comboData.payable_amount != null ? comboData.payable_amount : (comboData.base_price || 0)),
            claims      : parseFloat(comboData.claims_amount || 0),
            mode        : comboData.coverage_mode || null,
            onConfirm   : function() {
                $.ajax({
                    url         : '/nursing-workbench/clinical-requests/apply-combo',
                    method      : 'POST',
                    headers     : { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data        : JSON.stringify({ service_id: comboId, patient_id: patientId, note: '' }),
                    contentType : 'application/json',
                    dataType    : 'json',
                    success     : function(response) {
                        if (response.success) {
                            toastr.success(response.message, 'Combo Applied');
                            initLabHistory();
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

    function applyImagingCombo(comboId, comboName) {
        var comboData = (window.comboDataMap || {})[comboId] || {};
        var name = comboName || comboData.service_name || 'Combo';

        $('#cr_imaging_search').val('');
        $('#cr_imaging_results').hide();

        ComboConfirmModal.show({
            name        : name,
            bundleItems : comboData.bundle_items || [],
            price       : parseFloat(comboData.base_price || 0),
            payable     : parseFloat(comboData.payable_amount != null ? comboData.payable_amount : (comboData.base_price || 0)),
            claims      : parseFloat(comboData.claims_amount || 0),
            mode        : comboData.coverage_mode || null,
            onConfirm   : function() {
                $.ajax({
                    url         : '/nursing-workbench/clinical-requests/apply-combo',
                    method      : 'POST',
                    headers     : { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data        : JSON.stringify({ service_id: comboId, patient_id: patientId, note: '' }),
                    contentType : 'application/json',
                    dataType    : 'json',
                    success     : function(response) {
                        if (response.success) {
                            toastr.success(response.message, 'Combo Applied');
                            initImagingHistory();
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

    return {
        init: init,
        addProduct: addProduct,
        applyProductCombo: applyProductCombo,
        addLabService: addLabService,
        addImagingService: addImagingService,
        applyLabCombo: applyLabCombo,
        applyImagingCombo: applyImagingCombo,
        addProcedure: addProcedure,
        removeProcedure: removeProcedure,
        removeAutoSavedRow: removeAutoSavedRow,
        savePrescriptions: savePrescriptions,
        saveLabs: saveLabs,
        saveImaging: saveImaging,
        saveProcedures: saveProcedures,
        toggleDoseMode: function() { /* removed — now handled by ClinicalOrdersKit.initDoseModeToggle (Plan §2.2) */ },
        toggleCalculator: function() { /* removed — global calculator replaced by per-drug calc (Plan §2.3) */ },
        calculate: function() { /* removed */ },
        applyToSelected: function() { /* removed */ },
        updateDoseVal: function() { /* removed — now handled by ClinicalOrdersKit.updateDoseValue (Plan §2.2) */ },
        _searchBound: false,
        _doseToggleInit: false
    };
})();

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
            url: `{{ url('/patient-procedures/list-by-patient/${patientId}') }}`,
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

/* LAB-SPECIFIC - DISABLED
function viewInvestigationResult(requestId) {
    // Open modal to view completed result
    $.ajax({
        url: `{{ url('/lab-workbench/lab-service-requests/${requestId}') }}`,
        method: 'GET',
        success: function(request) {
            // Show result in a view-only modal or open in new tab
            if (request.result_document) {
                window.open(request.result_document, '_blank');
            } else {
                alert('No result document found');
            }
        },
        error: function(xhr) {
            alert('Error loading result: ' + (xhr.responseJSON?.message || 'Unknown error'));
        }
    });
}
*/

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
            // Already an array
            allergiesArray = patient.allergies;
        } else if (typeof patient.allergies === 'string') {
            // Try parsing as JSON first
            try {
                const parsed = JSON.parse(patient.allergies);
                allergiesArray = Array.isArray(parsed) ? parsed : (parsed ? [parsed] : []);
            } catch(e) {
                // Not JSON, treat as comma-separated string
                allergiesArray = patient.allergies.split(',').map(a => a.trim()).filter(a => a);
            }
        } else if (typeof patient.allergies === 'object') {
            // Object - extract values
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

function displayPendingRequests(requests) {
    currentPendingRequests = requests;
    const totalPending = requests.billing.length + requests.sample.length + requests.results.length;
    $('#pending-badge').text(totalPending);

    updatePendingSubtabBadges(requests);
    renderPendingSubtabContent(currentPendingFilter);
}

function updatePendingSubtabBadges(requests) {
    const totalPending = requests.billing.length + requests.sample.length + requests.results.length;
    $('#all-pending-badge').text(totalPending);
    $('#billing-subtab-badge').text(requests.billing.length);
    $('#sample-subtab-badge').text(requests.sample.length);
    $('#results-subtab-badge').text(requests.results.length);
}

function renderPendingSubtabContent(filter) {
    if (!currentPendingRequests) return;

    currentPendingFilter = filter;
    const requests = currentPendingRequests;
    const totalPending = requests.billing.length + requests.sample.length + requests.results.length;

    const $container = $('#pending-subtab-container');
    $container.empty();

    if (totalPending === 0) {
        $container.html('<div class="alert alert-info">No pending lab requests for this patient</div>');
        return;
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
                        <button class="btn-action btn-action-billing" id="btn-record-billing" disabled>
                            <i class="mdi mdi-check-circle"></i>
                            Record Billing
                        </button>
                        <button class="btn-action btn-action-dismiss" id="btn-dismiss-billing" disabled>
                            <i class="mdi mdi-close-circle"></i>
                            Dismiss
                        </button>
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
                        <button class="btn-action btn-action-sample" id="btn-collect-sample" disabled>
                            <i class="mdi mdi-check-circle"></i>
                            Collect Sample
                        </button>
                        <button class="btn-action btn-action-dismiss" id="btn-dismiss-sample" disabled>
                            <i class="mdi mdi-close-circle"></i>
                            Dismiss
                        </button>
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
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-file-document-edit"></i>
                        Result Entry (${requests.results.length})
                    </h5>
                </div>
                <div class="request-cards-container" id="results-cards"></div>
                <div class="section-actions-footer">
                    <div class="select-all-container">
                        <span class="text-muted"><i class="mdi mdi-information"></i> Results must be entered individually</span>
                    </div>
                    <div class="action-buttons">
                        <button class="btn-action btn-action-dismiss" id="btn-dismiss-results" disabled>
                            <i class="mdi mdi-close-circle"></i>
                            Dismiss Selected
                        </button>
                    </div>
                </div>
            </div>
        `;
        $container.append(resultsHtml);

        requests.results.forEach(request => {
            $('#results-cards').append(createRequestCard(request, 'results'));
        });
    }

    // Initialize event handlers
    initializeRequestHandlers();
}

function createRequestCard(request, section) {
    let serviceName = request.service?.service_name || request.service_name || request.name || 'Unknown Service';
    if (request.treatment_plan_id && request.treatment_plan_name) {
        serviceName += ` <br><a href="#" class="tp-view-link badge mt-1" style="background-color: #e0f2f1; color: #00796b; border: 1px solid #00897b; text-decoration: none;" onclick="ClinicalOrdersKit.viewTreatmentPlan(${request.treatment_plan_id}); event.stopPropagation(); return false;"><i class="fa fa-clipboard-list"></i> ${escapeHtml(request.treatment_plan_name)}</a>`;
    }
    const doctorName = request.doctor ? (request.doctor.firstname + ' ' + request.doctor.surname) : 'N/A';
    const requestDate = formatDateTime(request.created_at);
    const note = request.note || '';

    const hasNote = note && note.trim() !== '';
    const noteHtml = hasNote ? `<div class="request-note"><i class="mdi mdi-note-text"></i> ${note}</div>` : '';

    // Check delivery status
    const deliveryCheck = request.delivery_check;
    const canDeliver = deliveryCheck ? deliveryCheck.can_deliver : true;

    // Delivery warning message
    let deliveryWarningHtml = '';
    if (!canDeliver && deliveryCheck) {
        deliveryWarningHtml = `
            <div class="alert alert-warning py-2 px-2 mb-2 mt-2" style="font-size: 0.85rem;">
                <i class="fa fa-exclamation-triangle"></i> <strong>${deliveryCheck.reason}</strong><br>
                <small>${deliveryCheck.hint}</small>
            </div>
        `;
    }

    // Results section has individual action button instead of checkbox
    const checkboxOrAction = section === 'results' ? `
        <button class="btn btn-sm btn-primary enter-result-btn" data-request-id="${request.id}" ${!canDeliver ? 'disabled title="' + (deliveryCheck?.reason || 'Cannot deliver service') + '"' : ''}>
            <i class="mdi mdi-file-document-edit"></i>
            Enter Result
        </button>
    ` : `
        <div class="request-card-checkbox">
            <input type="checkbox" class="request-checkbox" data-request-id="${request.id}" data-section="${section}">
        </div>
    `;

    return `
        <div class="request-card">
            ${checkboxOrAction}
            <div class="request-card-content">
                <div class="request-card-header">
                    <div>
                        <div class="request-service-name">${serviceName}</div>
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
                </div>
                ${noteHtml}
                ${deliveryWarningHtml}
            </div>
        </div>
    `;
}

function initializeRequestHandlers() {
    // Select all checkboxes
    $('.select-all-checkbox').on('change', function() {
        const section = $(this).attr('id').replace('select-all-', '');
        const isChecked = $(this).is(':checked');
        $(`.request-checkbox[data-section="${section}"]`).prop('checked', isChecked).trigger('change');
    });

    // Individual checkboxes
    $('.request-checkbox').on('change', function() {
        const section = $(this).data('section');
        const checkedCount = $(`.request-checkbox[data-section="${section}"]:checked`).length;

        // Enable/disable action buttons
        $(`#btn-record-${section}, #btn-collect-${section}, #btn-dismiss-${section}`).prop('disabled', checkedCount === 0);

        // Update select all checkbox state
        const totalCount = $(`.request-checkbox[data-section="${section}"]`).length;
        $(`#select-all-${section}`).prop('checked', checkedCount === totalCount);
    });

    // Record Billing button
    $('#btn-record-billing').on('click', function() {
        const selectedIds = $('.request-checkbox[data-section="billing"]:checked').map(function() {
            return $(this).data('request-id');
        }).get();

        if (selectedIds.length> 0) {
            recordBilling(selectedIds);
        }
    });

    // Collect Sample button
    $('#btn-collect-sample').on('click', function() {
        const selectedIds = $('.request-checkbox[data-section="sample"]:checked').map(function() {
            return $(this).data('request-id');
        }).get();

        if (selectedIds.length> 0) {
            collectSample(selectedIds);
        }
    });

    // Dismiss buttons
    $('.btn-action-dismiss').on('click', function() {
        const btnId = $(this).attr('id');
        const section = btnId.replace('btn-dismiss-', '');
        const selectedIds = $(`.request-checkbox[data-section="${section}"]:checked`).map(function() {
            return $(this).data('request-id');
        }).get();

        if (selectedIds.length> 0) {
            dismissRequests(selectedIds, section);
        }
    });

    // Enter Result buttons (individual)
    $('.enter-result-btn').on('click', function() {
        const requestId = $(this).data('request-id');
        enterResult(requestId);
    });
}

// loadClinicalContext, displayVitals, displayNotes, displayMedications, and classifiers
// are now handled by ClinicalContext module (clinical-context.js)

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
    $.get('{{ route("nursing-workbench.queue-counts") }}', function(counts) {
        $('#queue-admitted-count').text(counts.admitted || 0);
        $('#queue-vitals-count').text(counts.vitals || 0);
        $('#queue-bed-count').text(counts.bed_requests || 0);
        $('#queue-discharge-count').text(counts.discharge_requests || 0);
        $('#queue-medication-count').text(counts.medication_due || 0);
        $('#queue-emergency-count').text(counts.emergency || 0);
        $('#queue-deceased-count').text(counts.deceased || 0);
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

    // Silently reload patient data
    if (window.BillingKit) { BillingKit.refresh(); }
    loadInjectionHistory(currentPatient);
    loadInjectionPrescriptions(true);
    loadImmunizationHistory(currentPatient);
    loadNotesHistory(currentPatient);
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

    // Vitals, medications, and allergies refresh is handled by clinical-context.js
    // Only notes refresh is handled locally (if needed)
    if (panel === 'medications') {
        // Reload medication chart (nursing-specific)
        loadMedicationsList();
        $btn.find('i').removeClass('fa-spin');
    } else {
        // For vitals/allergies — the shared module handles these via delegated click events
        setTimeout(function() { $btn.find('i').removeClass('fa-spin'); }, 1000);
    }
}



function loadUserPreferences() {
    const clinicalVisible = localStorage.getItem('clinicalPanelVisible') === 'true';
    if (clinicalVisible) {
        $('#right-panel').addClass('active');
        $('#toggle-clinical-btn').html('📊 Clinical Context 🔽');
    }
}

// Removed lab-specific functions (recordBilling, collectSample, dismissRequests, enterResult)
// These were carried over from lab workbench and are not needed for nursing workbench

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
        '{{ route("lab.saveResult") }}'
    );
}

// Lab result edit (called from investigation history DataTable "Edit" button)
function editLabResult(obj) {
    const requestId = $(obj).data('id');
    InvestResultEntry.editResult(
        requestId,
        `/lab-workbench/lab-service-requests/${requestId}`,
        `/lab-workbench/lab-service-requests/${requestId}/attachments`,
        '{{ route("lab.saveResult") }}'
    );
}

// Imaging result entry (called from imaging history DataTable "Enter Result" button)
function enterImagingResult(requestId) {
    window._investResultContext = { type: 'imaging', id: requestId };
    InvestResultEntry.enterResult(
        requestId,
        `/imaging-workbench/imaging-service-requests/${requestId}`,
        `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
        '{{ route("imaging.saveResult") }}'
    );
}

// Imaging result edit (called from imaging history DataTable "Edit" button)
function editImagingResult(obj) {
    const requestId = $(obj).data('id');
    InvestResultEntry.editResult(
        requestId,
        `/imaging-workbench/imaging-service-requests/${requestId}`,
        `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
        '{{ route("imaging.saveResult") }}'
    );
}

var _PI_LAB_REQ_APPROVAL = {{ appsettings('lab_results_require_approval') ? 'true' : 'false' }};
var _PI_IMG_REQ_APPROVAL = {{ appsettings('imaging_results_require_approval') ? 'true' : 'false' }};
var _PI_DR_SELF_LAB      = {{ appsettings('doctor_self_approve_lab_result') ? 'true' : 'false' }};
var _PI_NR_SELF_LAB      = {{ appsettings('nurse_self_approve_lab_result') ? 'true' : 'false' }};
var _PI_DR_SELF_IMG      = {{ appsettings('doctor_self_approve_imaging_result') ? 'true' : 'false' }};
var _PI_NR_SELF_IMG      = {{ appsettings('nurse_self_approve_imaging_result') ? 'true' : 'false' }};

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
        url: `{{ url('/lab-workbench/lab-service-requests/${deleteRequestId}') }}`,
        method: 'DELETE',
        data: {
            _token: '{{ csrf_token() }}',
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
        url: `{{ url('/lab-workbench/lab-service-requests/${dismissRequestId}/dismiss') }}`,
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
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
        url: '{{ route("lab.filterDoctors") }}',
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
        url: '{{ route("lab.filterHmos") }}',
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
        url: '{{ route("lab.filterServices") }}',
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
        url: '{{ route("lab.statistics") }}',
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
            url: '{{ route("lab.reports") }}',
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
        url: '{{ route("lab.statistics") }}',
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

// =====================================
// NURSING WORKBENCH SPECIFIC FUNCTIONS
// =====================================

// Populate Overview Tab with patient data (called directly with data object)
function populateOverviewTab(data) {
    console.log('Populating overview tab with:', data); // Debug log

    // Populate Patient Information Card
    let patientInfoHtml = `
        <table class="table table-sm mb-0">
            <tr><th width="40%">File No:</th><td>${data.file_no || 'N/A'}</td></tr>
            <tr><th>Name:</th><td>${data.name || 'N/A'}</td></tr>
            <tr><th>Age/Gender:</th><td>${data.age || 'N/A'} / ${data.gender || 'N/A'}</td></tr>
            <tr><th>Phone:</th><td>${data.phone || 'N/A'}</td></tr>
            <tr><th>HMO:</th><td>${data.hmo || 'Private'}</td></tr>
            <tr><th>Blood Group:</th><td>${data.blood_group || 'Unknown'}</td></tr>
        </table>
    `;
    $('#overview-patient-info').html(patientInfoHtml);

    // Populate Admission Status Card
    let admissionHtml = '';
    if (data.admission) {
        admissionHtml = `
            <table class="table table-sm mb-0">
                <tr><th width="40%">Bed/Ward:</th><td><span class="badge badge-info">${data.admission.bed || 'N/A'}</span></td></tr>
                <tr><th>Admitted:</th><td>${data.admission.admitted_date || 'N/A'}</td></tr>
                <tr><th>Duration:</th><td>${data.admission.days_admitted || '0'} day(s)</td></tr>
                <tr><th>Reason:</th><td>${data.admission.reason || 'N/A'}</td></tr>
            </table>
        `;
    } else {
        admissionHtml = '<p class="text-muted text-center py-3">Not currently admitted</p>';
    }
    $('#overview-admission-info').html(admissionHtml);

    // Populate Latest Vitals Card
    let vitalsHtml = '';
    if (data.last_vitals) {
        vitalsHtml = `
            <table class="table table-sm mb-0">
                <tr><th width="50%">BP:</th><td>${data.last_vitals.bp || 'N/A'}</td></tr>
                <tr><th>Heart Rate:</th><td>${data.last_vitals.heart_rate || 'N/A'} bpm</td></tr>
                <tr><th>Temp:</th><td>${data.last_vitals.temp || 'N/A'} ┬░C</td></tr>
                <tr><th>Resp Rate:</th><td>${data.last_vitals.resp_rate || 'N/A'} /min</td></tr>
                <tr><th>Recorded:</th><td><small class="text-muted">${data.last_vitals.time || 'N/A'}</small></td></tr>
            </table>
        `;
    } else {
        vitalsHtml = '<p class="text-muted text-center py-3">No vitals recorded</p>';
    }
    $('#overview-vitals-info').html(vitalsHtml);

    // Populate Latest Nurse Note
    let nurseNoteHtml = '';
    if (data.latest_nurse_note) {
        nurseNoteHtml = `
            <div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="badge badge-primary">${data.latest_nurse_note.type}</span>
                    <small class="text-muted">${data.latest_nurse_note.time_ago}</small>
                </div>
                <div class="note-preview mb-2" style="font-size: 0.9rem;">
                    ${data.latest_nurse_note.note}
                </div>
                <div class="text-right">
                    <small class="text-muted">By: <strong>${data.latest_nurse_note.created_by}</strong></small>
                </div>
            </div>
        `;
    } else {
        nurseNoteHtml = '<p class="text-muted text-center py-2">No nursing notes</p>';
    }
    $('#overview-nurse-note').html(nurseNoteHtml);

    // Populate Latest Doctor Note
    let doctorNoteHtml = '';
    if (data.latest_doctor_note) {
        doctorNoteHtml = `
            <div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="badge badge-info text-white">Doctor Note</span>
                    <small class="text-muted">${data.latest_doctor_note.time_ago}</small>
                </div>
                <div class="note-preview mb-2" style="font-size: 0.9rem;">
                    ${data.latest_doctor_note.note}
                </div>
                <div class="text-right">
                    <small class="text-muted">By: <strong>${data.latest_doctor_note.created_by}</strong></small>
                </div>
            </div>
        `;
    } else {
        doctorNoteHtml = '<p class="text-muted text-center py-2">No doctor notes</p>';
    }
    $('#overview-doctor-note').html(doctorNoteHtml);

    // Populate Allergies & Alerts Card - handle array, comma-separated string, JSON string, object, or null
    let allergiesHtml = '';
    let allergiesArray = [];
    if (data.allergies) {
        if (Array.isArray(data.allergies)) {
            allergiesArray = data.allergies;
        } else if (typeof data.allergies === 'string') {
            try {
                const parsed = JSON.parse(data.allergies);
                allergiesArray = Array.isArray(parsed) ? parsed : (parsed ? [parsed] : []);
            } catch(e) {
                allergiesArray = data.allergies.split(',').map(a => a.trim()).filter(a => a);
            }
        } else if (typeof data.allergies === 'object') {
            allergiesArray = Object.values(data.allergies).filter(a => a);
        }
    }

    if (allergiesArray.length> 0) {
        allergiesHtml = '<div class="d-flex flex-wrap">';
        allergiesArray.forEach(function(allergy) {
            allergiesHtml += `<span class="badge badge-danger m-1 p-2"><i class="mdi mdi-alert"></i> ${allergy}</span>`;
        });
        allergiesHtml += '</div>';
    } else {
        allergiesHtml = '<p class="text-success text-center py-2"><i class="mdi mdi-check-circle"></i> No known allergies</p>';
    }
    $('#overview-allergies').html(allergiesHtml);

    // Load pending medications for overview
    loadOverviewPendingMeds(data.id);

    // Load tasks for overview
    loadOverviewTasks(data.id);
}

// Load Patient Overview (fetches data first - used when switching tabs)
function loadPatientOverview(patientId) {
    if (currentPatientData && currentPatientData.id == patientId) {
        // Use cached data if available
        populateOverviewTab(currentPatientData);
        return;
    }

    $.ajax({
        url: `{{ url('/nursing-workbench/patient/${patientId}/details') }}`,
        method: 'GET',
        success: function(data) {
            currentPatientData = data;
            populateOverviewTab(data);
        },
        error: function() {
            $('#overview-patient-info').html('<p class="text-danger">Failed to load</p>');
            $('#overview-admission-info').html('<p class="text-danger">Failed to load</p>');
            $('#overview-vitals-info').html('<p class="text-danger">Failed to load</p>');
            $('#overview-nurse-note').html('<p class="text-danger">Failed to load</p>');
            $('#overview-doctor-note').html('<p class="text-danger">Failed to load</p>');
            $('#overview-allergies').html('<p class="text-danger">Failed to load</p>');
        }
    });
}

// Load pending medications for overview card (uses medication chart data)
function loadOverviewPendingMeds(patientId) {
    // Try to get medications from the existing medication chart API
    $.ajax({
        url: `{{ url('/patients/${patientId}/nurse-chart/medication') }}`,
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            let html = '';
            let pendingMeds = [];

            // Filter for pending/due medications if data is an array
            if (Array.isArray(data)) {
                pendingMeds = data.filter(med => med.status === 'pending' || med.status === 'due');
            } else if (data.medications) {
                pendingMeds = data.medications.filter(med => !med.is_administered);
            }

            if (pendingMeds.length> 0) {
                $('#overview-pending-meds-count').text(pendingMeds.length);
                html = '<ul class="list-group list-group-flush">';
                pendingMeds.slice(0, 5).forEach(function(med) {
                    html += `
                        <li class="list-group-item p-2 d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${med.drug_name || med.name || 'Unknown'}</strong>
                                <small class="d-block text-muted">${med.dose || ''} ${med.route || ''}</small>
                            </div>
                            <span class="badge badge-warning">${med.due_time || med.scheduled_time || 'Pending'}</span>
                        </li>
                    `;
                });
                html += '</ul>';
                if (pendingMeds.length> 5) {
                    html += `<small class="text-muted d-block text-center mt-2">+${pendingMeds.length - 5} more</small>`;
                }
            } else {
                $('#overview-pending-meds-count').text('0');
                html = '<p class="text-muted text-center py-2">No pending medications</p>';
            }
            $('#overview-pending-meds').html(html);
        },
        error: function() {
            // Silently fail - medications can be viewed in medication tab
            $('#overview-pending-meds').html('<p class="text-muted text-center py-2">View in Medication Tab</p>');
            $('#overview-pending-meds-count').text('-');
        }
    });
}

// Load tasks for overview card (placeholder - can be enhanced later)
function loadOverviewTasks(patientId) {
    // For now, just show a placeholder since tasks may not have a dedicated API yet
    $('#overview-tasks').html('<p class="text-muted text-center py-2">No tasks pending</p>');
    $('#overview-tasks-count').text('0');
}

// Load Admitted Patients Queue
function loadAdmittedPatients() {
    $.ajax({
        url: '{{ route("nursing-workbench.admitted-patients") }}',
        method: 'GET',
        success: function(data) {
            if (data.length === 0) {
                showNotification('info', 'No admitted patients found');
                return;
            }
            // Display patients in a list/cards
            displayAdmittedPatientsQueue(data);
        },
        error: function() {
            showNotification('error', 'Failed to load admitted patients');
        }
    });
}

// Load Vitals Queue
function loadVitalsQueue() {
    $('#empty-state').hide();
    $('#workspace-content').removeClass('active');
    $('.patient-header').removeClass('active');
    $('#queue-view').addClass('active').css('display', 'flex');
    $('#queue-view-title').html('<i class="mdi mdi-heart-pulse"></i> Vitals Queue');

    if ($.fn.DataTable.isDataTable('#queue-datatable')) {
        $('#queue-datatable').DataTable().destroy();
    }

    $('#queue-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("nursing-workbench.vitals-queue") }}',
        columns: [
            { data: 'info', name: 'info' }
        ],
        language: {
             emptyTable: "No patients pending vitals"
        },
        drawCallback: function() {
            // Attach click handlers to cards
        }
    });
}

// Load Bed Requests Queue
function loadBedRequestsQueue() {
    $('#empty-state').hide();
    $('#workspace-content').removeClass('active');
    $('.patient-header').removeClass('active');
    $('#queue-view').addClass('active').css('display', 'flex');
    $('#queue-view-title').html('<i class="mdi mdi-bed"></i> Bed Requests');

    if ($.fn.DataTable.isDataTable('#queue-datatable')) {
        $('#queue-datatable').DataTable().destroy();
    }

    $('#queue-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("nursing-workbench.bed-requests-queue") }}',
        columns: [
            { data: 'info', name: 'info' }
        ],
        language: {
             emptyTable: "No pending bed requests"
        }
    });
}

// Load Medication Due
function loadMedicationDue() {
    showNotification('info', 'Medication due feature coming soon');
    // TODO: Implement medication due loading
}

// ========================================
// INJECTION MODULE - Drug Search & Administration
// ========================================

function setInjectionDrugSource(source) {
    $('#injection-drug-source').val(source);
    $('[data-inj-source]').removeClass('active');
    $(`[data-inj-source="${source}"]`).addClass('active');

    $('.source-section').hide();
    $('#inj-source-' + (source === 'ward_stock' ? 'ward' : source === 'patient_own' ? 'patient' : 'pharmacy')).show();

    // §7.1: Only ward_stock needs the hospital product search (Step 2)
    // Pharmacy uses prescription dropdown, Patient's Own uses free-text fields
    if (source === 'ward_stock') {
        $('.inj-non-pharmacy').show();
    } else {
        $('.inj-non-pharmacy').hide();
    }

    // Clear any previously selected items when switching source to avoid mixed payloads
    $('#injection-selected-body').empty();
    updateInjectionTotals();
    $('#injection-stock-error').remove();
}

function loadInjectionPrescriptions(force = false) {
    if (injectionPrescriptionsLoaded && !force) {
        return $.Deferred().resolve(injectionPrescriptions).promise();
    }
    if (!currentPatient) return $.Deferred().resolve([]).promise();

    const url = medicationChartPrescribedRoute.replace(':patient', currentPatient);
    return $.ajax({ url: url, type: 'GET' })
        .then(function(res) {
            if (res && res.success) {
                injectionPrescriptions = res.prescriptions || [];
                injectionPrescriptionsLoaded = true;
                populateInjectionRxSelect();
            }
            return injectionPrescriptions;
        })
        .catch(function(err) {
            console.error('Failed to load prescriptions for injections', err);
            return [];
        });
}

function populateInjectionRxSelect() {
    const select = $('#injection-rx-select');
    if (!select.length) return;

    select.empty();
    const dispensed = (injectionPrescriptions || []).filter(p => p.is_dispensed);

    if (dispensed.length === 0) {
        select.append('<option value="">No dispensed prescriptions</option>');
        return;
    }

    select.append('<option value="">-- Select dispensed prescription --</option>');
    dispensed.forEach(function(rx) {
        const label = `${rx.product_name || 'Drug'} (${rx.product_code || ''}) from ${rx.dispensed_from_store || 'Pharmacy'}`;
        select.append(`<option value="${rx.id}" data-product-id="${rx.product_id || ''}" data-remaining="${rx.remaining_doses ?? ''}">${label}</option>`);
    });
}

function addInjectionRxToTable() {
    const rxId = $('#injection-rx-select').val();
    if (!rxId) {
        showNotification('warning', 'Select a dispensed prescription first');
        return;
    }
    const rx = (injectionPrescriptions || []).find(p => p.id == rxId);
    if (!rx || !rx.is_dispensed) {
        showNotification('warning', 'Only dispensed prescriptions can be charted');
        return;
    }

    // Prevent duplicates
    if ($(`#injection-selected-body tr[data-product-request-id="${rx.id}"]`).length> 0) {
        showNotification('info', 'Prescription already added');
        return;
    }

    const price = parseFloat(rx.payable_amount || rx.claims_amount || 0) || 0;
    const coverage = rx.coverage_mode || 'cash';
    const coverageInfo = coverage && coverage !== 'cash'
        ? `<span class="badge bg-info">${coverage.toUpperCase()}</span>`
        : '<span class="badge bg-secondary">Cash</span>';

    const remaining = rx.remaining_doses ?? null;
    const remainText = remaining !== null ? `<div class="small text-muted">Remaining: ${remaining}</div>` : '';

    const row = `
        <tr data-product-id="${rx.product_id}" data-product-request-id="${rx.id}" data-price="${price}" data-source="pharmacy_dispensed">
            <td><input type="checkbox" class="form-check-input injection-row-check" checked></td>
            <td>
                <strong>${rx.product_name || 'Drug'}</strong><br>
                <small class="text-muted">[${rx.product_code || ''}]</small>
                ${remainText}
            </td>
            <td>
                <input type="number" class="form-control form-control-sm injection-qty" value="1" min="1" style="width: 60px;" readonly>
            </td>
            <td class="batch-cell text-muted">N/A</td>
            <td class="stock-cell text-muted">N/A</td>
            <td>₦${price.toFixed(2)}</td>
            <td>${coverageInfo}</td>
            <td>
                <input type="text" class="form-control form-control-sm" name="injection_dose[]" placeholder="e.g., 5mg" required>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeInjectionRow(this)">
                    <i class="mdi mdi-close"></i>
                </button>
            </td>
        </tr>`;

    $('#injection-selected-body').append(row);
    updateInjectionTotals();

    $('#injection-rx-summary').text(`${rx.product_name || 'Drug'} added`).show();
}

// Drug source toggle
$(document).on('click', '[data-inj-source]', function() {
    const source = $(this).data('inj-source');
    setInjectionDrugSource(source);
});

$('#injection-add-rx').on('click', function() {
    addInjectionRxToTable();
});

// Default selection
setInjectionDrugSource('pharmacy_dispensed');

// Injection Drug Search (uses same endpoint as prescription form)
let injectionSearchTimeout;
$('#injection-drug-search').on('input', function() {
    const query = $(this).val();
    clearTimeout(injectionSearchTimeout);

    if (query.length < 2) {
        $('#injection-drug-results').hide();
        return;
    }

    injectionSearchTimeout = setTimeout(function() {
        $.ajax({
            url: "{{ url('live-search-products') }}",
            method: 'GET',
            dataType: 'json',
            data: { term: query, patient_id: currentPatient },
            success: function(data) {
                $('#injection-drug-results').html('');

                if (data.length === 0) {
                    $('#injection-drug-results').html('<li class="list-group-item text-muted">No products found</li>').show();
                    return;
                }

                data.forEach(function(item) {
                    const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                    const name = item.product_name || 'Unknown';
                    const code = item.product_code || '';
                    const qty = item.stock && item.stock.current_quantity !== undefined ? item.stock.current_quantity : 0;
                    const price = item.price && item.price.initial_sale_price !== undefined ? item.price.initial_sale_price : 0;
                    const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                    const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                    const mode = item.coverage_mode || 'cash';

                    const coverageBadge = mode && mode !== 'cash'
                        ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ₦${payable}</span> <span class='text-success ms-1'>Claim: ₦${claims}</span>`
                        : '';

                    const qtyClass = qty> 0 ? 'text-success' : 'text-danger';

                    const mk = `<li class='list-group-item list-group-item-action' style="cursor: pointer;"
                               data-id="${item.id}"
                               data-name="${name}"
                               data-code="${code}"
                               data-qty="${qty}"
                               data-price="${price}"
                               data-payable="${payable}"
                               data-claims="${claims}"
                               data-mode="${mode}"
                               data-category="${category}"
                               onclick="addInjectionDrug(this)">
                               <div class="d-flex justify-content-between align-items-start">
                                   <div>
                                       <strong>${name}</strong> <small class="text-muted">[${code}]</small>
                                       <div class="small text-muted">${category}</div>
                                   </div>
                                   <div class="text-end">
                                       <div class="${qtyClass}"><strong>${qty}</strong> avail.</div>
                                       <div>₦${price}</div>
                                   </div>
                               </div>
                               ${coverageBadge ? `<div class="small mt-1">${coverageBadge}</div>` : ''}
                           </li>`;
                    $('#injection-drug-results').append(mk);
                });
                $('#injection-drug-results').show();
            },
            error: function(xhr) {
                console.error('Product search failed', xhr);
                $('#injection-drug-results').html('<li class="list-group-item text-danger">Search failed</li>').show();
            }
        });
    }, 300);
});

// Hide dropdown when clicking outside
$(document).on('click', function(e) {
    if (!$(e.target).closest('#injection-drug-search, #injection-drug-results').length) {
        $('#injection-drug-results').hide();
    }
    if (!$(e.target).closest('#vaccine-drug-search, #vaccine-drug-results').length) {
        $('#vaccine-drug-results').hide();
    }
});

// Add selected drug to injection table
function addInjectionDrug(element) {
    const $el = $(element);
    const id = $el.data('id');
    const name = $el.data('name');
    const code = $el.data('code');
    const qty = $el.data('qty');
    const price = parseFloat($el.data('price')) || 0;
    const payable = parseFloat($el.data('payable')) || price;
    const claims = parseFloat($el.data('claims')) || 0;
    const mode = $el.data('mode') || 'cash';

    const drugSource = $('#injection-drug-source').val();

    // Check if store is selected first
    const storeId = $('#injection-store').val();
    if (drugSource === 'ward_stock' && !storeId) {
        showNotification('warning', 'Please select a ward store first');
        $('#injection-store').focus();
        return;
    }

    // Check if already added
    if ($(`#injection-selected-body tr[data-product-id="${id}"]`).length> 0) {
        showNotification('warning', 'This drug is already in the list');
        $('#injection-drug-results').hide();
        $('#injection-drug-search').val('');
        return;
    }

    const coverageInfo = mode && mode !== 'cash'
        ? `<span class="badge bg-info">${mode.toUpperCase()}</span><br><small class="text-danger">₦${payable}</small>`
        : '<span class="badge bg-secondary">Cash</span>';

    const row = `
        <tr data-product-id="${id}" data-price="${payable}" data-source="${drugSource}">
            <td><input type="checkbox" class="form-check-input injection-row-check" checked></td>
            <td>
                <strong>${name}</strong><br>
                <small class="text-muted">[${code}]</small>
                <input type="hidden" name="injection_products[]" value="${id}">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm injection-qty"
                       name="injection_qty[]" value="1" min="1" style="width: 60px;">
            </td>
            <td class="batch-cell">${drugSource === 'ward_stock' ? '<div class="batch-loading"><i class="mdi mdi-loading mdi-spin"></i> Loading...</div><select class="form-control form-control-sm batch-select-dropdown d-none" name="injection_batch_id[]"><option value="">Auto (FIFO)</option></select><input type="hidden" name="injection_selected_batch_id[]" value="">' : '<span class="text-muted">N/A</span>'}</td>
            <td class="stock-cell">${drugSource === 'ward_stock' ? '<span class="text-muted"><i class="mdi mdi-loading mdi-spin"></i></span>' : '<span class="text-muted">N/A</span>'}</td>
            <td>₦${payable.toFixed(2)}</td>
            <td>${coverageInfo}</td>
            <td>
                <input type="text" class="form-control form-control-sm"
                       name="injection_dose[]" placeholder="e.g., 5mg" required>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeInjectionRow(this)">
                    <i class="mdi mdi-close"></i>
                </button>
            </td>
        </tr>
    `;

    $('#injection-selected-body').append(row);
    updateInjectionTotals();

    // Fetch and populate batch dropdown for this product if ward stock
    if (drugSource === 'ward_stock') {
        fetchAndPopulateBatchDropdown(id, storeId, `#injection-selected-body tr[data-product-id="${id}"]`);
    }

    $('#injection-drug-results').hide();
    $('#injection-drug-search').val('');
}

// Remove row from injection table
function removeInjectionRow(btn) {
    $(btn).closest('tr').remove();
    updateInjectionTotals();
}

// §7.2: Insert a virtual row for patient's own drug (no hospital product_id)
function addPatientOwnInjectionRow() {
    const drugName  = $('#inj-external-name').val()?.trim();
    const qty       = $('#inj-external-qty').val();
    const batch     = $('#inj-external-batch').val()?.trim() || '';
    const expiry    = $('#inj-external-expiry').val() || '';
    const note      = $('#inj-external-note').val()?.trim() || '';

    if (!drugName) {
        showNotification('warning', 'Enter the drug name');
        $('#inj-external-name').focus();
        return;
    }
    if (!qty || parseFloat(qty) <= 0) {
        showNotification('warning', 'Enter a valid quantity');
        $('#inj-external-qty').focus();
        return;
    }

    // Prevent duplicate virtual rows with same drug name
    const duplicate = $('#injection-selected-body tr[data-source="patient_own"]').filter(function() {
        return $(this).find('td:eq(1) strong').text().toLowerCase() === drugName.toLowerCase();
    });
    if (duplicate.length> 0) {
        showNotification('warning', 'This drug is already in the list');
        return;
    }

    const uid = 'po_' + Date.now(); // virtual row identifier

    const row = `
        <tr data-source="patient_own" data-virtual-id="${uid}" data-price="0"
            data-ext-name="${drugName}" data-ext-qty="${qty}"
            data-ext-batch="${batch}" data-ext-expiry="${expiry}" data-ext-note="${note}">
            <td><input type="checkbox" class="form-check-input injection-row-check" checked></td>
            <td>
                <strong>${drugName}</strong><br>
                <span class="badge bg-purple text-white" style="background:#9c27b0;">Patient's Own</span>
                ${batch ? `<small class="text-muted ms-1">Batch: ${batch}</small>` : ''}
                ${expiry ? `<small class="text-muted ms-1">Exp: ${expiry}</small>` : ''}
            </td>
            <td>
                <input type="number" class="form-control form-control-sm injection-qty"
                       name="injection_qty[]" value="${qty}" min="0.01" step="0.01" style="width: 60px;">
            </td>
            <td><span class="text-muted">N/A</span></td>
            <td><span class="text-muted">N/A</span></td>
            <td><span class="text-muted">—</span></td>
            <td><span class="badge bg-secondary">No Billing</span></td>
            <td>
                <input type="text" class="form-control form-control-sm"
                       name="injection_dose[]" placeholder="e.g., 5mg" required>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeInjectionRow(this)">
                    <i class="mdi mdi-close"></i>
                </button>
            </td>
        </tr>
    `;

    $('#injection-selected-body').append(row);
    updateInjectionTotals();

    // Clear the input fields so nurse can add another if needed
    $('#inj-external-name').val('').focus();
    $('#inj-external-qty').val('');
    $('#inj-external-batch').val('');
    $('#inj-external-expiry').val('');
    $('#inj-external-note').val('');

    showNotification('success', `"${drugName}" added to list`);
}

// Update injection totals
function updateInjectionTotals() {
    let total = 0;
    $('#injection-selected-body tr').each(function() {
        const price = parseFloat($(this).data('price')) || 0;
        const qty = parseInt($(this).find('.injection-qty').val()) || 1;
        total += price * qty;
    });
    $('#injection-total-price').html(`<strong>₦${total.toFixed(2)}</strong>`);
}

// ===========================================
// BATCH SELECTION HELPER FUNCTIONS
// ===========================================

/**
 * Fetch batches from server and populate dropdown
 * @param {int} productId - Product ID
 * @param {int} storeId - Store ID
 * @param {string} rowSelector - Selector for the table row
 */
function fetchAndPopulateBatchDropdown(productId, storeId, rowSelector) {
    const $row = $(rowSelector);
    const $batchCell = $row.find('.batch-cell');
    const $batchLoading = $batchCell.find('.batch-loading');
    const $batchSelect = $batchCell.find('.batch-select-dropdown');
    const $stockCell = $row.find('.stock-cell');

    $.ajax({
        url: '{{ route("nursing-workbench.product-batches") }}',
        method: 'GET',
        data: { product_id: productId, store_id: storeId },
        success: function(response) {
            $batchLoading.addClass('d-none');

            if (response.success && response.batches.length> 0) {
                // Build dropdown options
                let options = '<option value="">Auto (FIFO)</option>';
                response.batches.forEach((batch, index) => {
                    const isFirst = index === 0;
                    const expiryClass = batch.is_expired ? 'batch-option-expired' :
                                       batch.is_expiring_soon ? 'batch-option-expiring' : '';
                    const expiryText = batch.expiry_formatted ? ` | Exp: ${batch.expiry_formatted}` : '';
                    const fifoLabel = isFirst ? ' ★ FIFO' : '';

                    options += `<option value="${batch.id}" class="${expiryClass}"
                                data-expiry="${batch.expiry_date || ''}"
                                data-qty="${batch.current_qty}">
                        ${batch.batch_number} (${batch.current_qty} avail)${expiryText}${fifoLabel}
                    </option>`;
                });

                $batchSelect.html(options).removeClass('d-none');

                // Update stock cell
                const totalQty = response.total_available;
                const reqQty = parseInt($row.find('.injection-qty').val()) || 1;
                const stockClass = totalQty>= reqQty ? 'text-success' : 'text-danger';
                const stockIcon = totalQty>= reqQty ? 'mdi-check-circle' : 'mdi-alert-circle';
                $stockCell.html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> ${totalQty}</span>`);

                // Store batches data for later reference
                $row.data('batches', response.batches);
            } else {
                // No batches available
                $batchSelect.html('<option value="">No batches available</option>').removeClass('d-none');
                $stockCell.html('<span class="text-danger"><i class="mdi mdi-alert-circle"></i> 0</span>');
            }
        },
        error: function() {
            $batchLoading.addClass('d-none');
            $batchSelect.html('<option value="">Error loading batches</option>').removeClass('d-none');
            $stockCell.html('<span class="text-warning"><i class="mdi mdi-help-circle"></i> ?</span>');
        }
    });
}

/**
 * Fetch batches for a single product and populate a standalone dropdown
 * @param {int} productId - Product ID
 * @param {int} storeId - Store ID
 * @param {string} selectId - ID of the select element to populate
 * @param {function} callback - Optional callback with batch data
 */
function fetchProductBatchesForSelect(productId, storeId, selectId, callback) {
    const $select = $(selectId);
    $select.html('<option value="">Loading batches...</option>').prop('disabled', true);

    $.ajax({
        url: '{{ route("nursing-workbench.product-batches") }}',
        method: 'GET',
        data: { product_id: productId, store_id: storeId },
        success: function(response) {
            $select.prop('disabled', false);

            if (response.success && response.batches.length> 0) {
                let options = '<option value="">Auto (FIFO) - Recommended</option>';

                response.batches.forEach((batch, index) => {
                    const isFirst = index === 0;
                    const expiryClass = batch.is_expired ? 'batch-option-expired' :
                                       batch.is_expiring_soon ? 'batch-option-expiring' : '';
                    const expiryText = batch.expiry_formatted ? ` | Exp: ${batch.expiry_formatted}` : '';
                    const fifoLabel = isFirst ? ' ★' : '';

                    options += `<option value="${batch.id}" class="${expiryClass}"
                                data-expiry="${batch.expiry_date || ''}"
                                data-qty="${batch.current_qty}"
                                data-batch-number="${batch.batch_number}">
                        ${batch.batch_number} (${batch.current_qty} avail)${expiryText}${fifoLabel}
                    </option>`;
                });

                $select.html(options);

                if (callback) callback(response);
            } else {
                $select.html('<option value="">No batches in this store</option>');
                if (callback) callback({ success: false, batches: [], total_available: 0 });
            }
        },
        error: function() {
            $select.prop('disabled', false);
            $select.html('<option value="">Error loading batches</option>');
            if (callback) callback({ success: false, error: true });
        }
    });
}

/**
 * Build batch info display for FIFO mode
 */
function buildBatchFifoDisplay(batch) {
    if (!batch) return '<span class="text-muted">No batch available</span>';

    const expiryText = batch.expiry_formatted ? `<span class="batch-expiry">Exp: ${batch.expiry_formatted}</span>` : '';

    return `
        <div class="batch-info-display">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="batch-fifo-badge"><i class="mdi mdi-sort-clock-ascending"></i> FIFO</span>
                    <span class="batch-number ml-2">${batch.batch_number}</span>
                </div>
                <span class="batch-qty">${batch.current_qty}</span>
            </div>
            ${expiryText ? `<div class="mt-1 small">${expiryText}</div>` : ''}
            <button type="button" class="batch-manual-select-btn mt-1" onclick="$(this).closest('.batch-cell').find('.batch-select-dropdown').removeClass('d-none'); $(this).closest('.batch-info-display').addClass('d-none');">
                <i class="mdi mdi-pencil"></i> Change Batch
            </button>
        </div>
    `;
}

/**
 * Handle batch select change - update expiry field if linked
 */
$(document).on('change', '.batch-select-dropdown, #consumable-batch-select, #modal-vaccine-batch-select', function() {
    const $selected = $(this).find(':selected');
    const expiryDate = $selected.data('expiry');
    const batchNumber = $selected.data('batch-number');

    // Update linked expiry field if exists
    const $form = $(this).closest('form, .modal-body, .card-body');
    const $expiryField = $form.find('input[type="date"][id*="expiry"]');
    if ($expiryField.length && expiryDate) {
        $expiryField.val(expiryDate);
    }

    // Store selected batch ID in hidden field if exists
    const $hiddenField = $(this).siblings('input[type="hidden"]');
    if ($hiddenField.length) {
        $hiddenField.val($(this).val());
    }
});

// Recalculate on qty change and clear validation
$(document).on('change', '.injection-qty', function() {
    updateInjectionTotals();

    // Re-fetch batches when quantity changes to show stock status
    const $row = $(this).closest('tr');
    const productId = $row.data('product-id');
    const storeId = $('#injection-store').val();
    const reqQty = parseInt($(this).val()) || 1;

    // Update stock display based on stored batches
    const batches = $row.data('batches');
    if (batches) {
        const totalQty = batches.reduce((sum, b) => sum + b.current_qty, 0);
        const $stockCell = $row.find('.stock-cell');
        const stockClass = totalQty>= reqQty ? 'text-success' : 'text-danger';
        const stockIcon = totalQty>= reqQty ? 'mdi-check-circle' : 'mdi-alert-circle';
        $stockCell.html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> ${totalQty}</span>`);
    }

    $(this).removeClass('is-invalid');
    $(this).siblings('.validation-error').remove();
});

// Clear validation on dose input change
$(document).on('input', 'input[name="injection_dose[]"]', function() {
    $(this).removeClass('is-invalid');
    $(this).siblings('.validation-error').remove();
});

// ===========================================
// STORE STOCK DISPLAY HELPERS
// ===========================================

// Fetch product stock by store
function fetchProductStockByStore(productId, callback) {
    $.ajax({
        url: `{{ url('/pharmacy-workbench/product/${productId}/stock') }}`,
        method: 'GET',
        success: function(response) {
            callback(response);
        },
        error: function() {
            callback({ global_stock: 0, stores: [] });
        }
    });
}

// Update injection stock display when store changes
$('#injection-store').on('change', function() {
    const storeId = $(this).val();
    if (storeId) {
        $('#injection-store-placeholder').hide();
        $('#injection-store-info').show();
        updateInjectionStockDisplay();
    } else {
        $('#injection-store-info').hide();
        $('#injection-store-placeholder').show();
    }
});

// Update injection table stock display
function updateInjectionStockDisplay() {
    const storeId = $('#injection-store').val();
    if (!storeId) return;

    let stockSummaryHtml = '';
    const rows = $('#injection-selected-body tr');

    if (rows.length === 0) {
        stockSummaryHtml = '<p class="text-muted mb-0">Add drugs to see stock</p>';
    } else {
        rows.each(function() {
            const productId = $(this).data('product-id');
            const productName = $(this).find('td:eq(1) strong').text();
            const qty = parseInt($(this).find('.injection-qty').val()) || 1;
            const $stockCell = $(this).find('.stock-cell');

            fetchProductStockByStore(productId, function(stockData) {
                const storeStock = stockData.stores.find(s => s.store_id == storeId);
                const availableQty = storeStock ? storeStock.quantity : 0;
                const stockClass = availableQty>= qty ? 'text-success' : 'text-danger';
                const stockIcon = availableQty>= qty ? 'mdi-check-circle' : 'mdi-alert-circle';

                $stockCell.html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> ${availableQty}</span>`);
            });
        });
    }

    $('#injection-store-stock-summary').html(stockSummaryHtml || '<p class="text-muted mb-0">Stock shown in table</p>');
}

// Consumable store change handler
$('#consumable-store').on('change', function() {
    const storeId = $(this).val();
    if (storeId) {
        $('#consumable-store-placeholder').hide();
        $('#consumable-store-info').show();
        updateConsumableStockDisplay();
    } else {
        $('#consumable-store-info').hide();
        $('#consumable-store-placeholder').show();
    }
});

// Update consumable stock display
function updateConsumableStockDisplay() {
    const storeId = $('#consumable-store').val();
    const productId = $('#consumable-id').val();

    if (!storeId || !productId) {
        $('#consumable-store-stock-summary').html('<p class="text-muted mb-0">Select a product to see stock</p>');
        return;
    }

    fetchProductStockByStore(productId, function(stockData) {
        const storeStock = stockData.stores.find(s => s.store_id == storeId);
        const availableQty = storeStock ? storeStock.quantity : 0;
        const qty = parseInt($('#consumable-quantity').val()) || 1;
        const stockClass = availableQty>= qty ? 'text-success' : 'text-danger';
        const stockIcon = availableQty>= qty ? 'mdi-check-circle' : 'mdi-alert-circle';

        let html = `<div class="${stockClass}"><i class="mdi ${stockIcon}"></i> Available: <strong>${availableQty}</strong></div>`;
        if (availableQty < qty) {
            html += `<div class="text-danger small"><i class="mdi mdi-alert"></i> Insufficient stock!</div>`;
        }

        $('#consumable-store-stock-summary').html(html);
        $('#consumable-stock-info').html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> Stock: ${availableQty}</span>`);
    });
}

// Immunization modal store change handler
$('#modal-vaccine-store').on('change', function() {
    const storeId = $(this).val();
    if (storeId) {
        $('#modal-vaccine-store-placeholder').hide();
        $('#modal-vaccine-store-info').show();
        updateImmunizationStockDisplay();
    } else {
        $('#modal-vaccine-store-info').hide();
        $('#modal-vaccine-store-placeholder').show();
    }
});

// Update immunization stock display
function updateImmunizationStockDisplay() {
    const storeId = $('#modal-vaccine-store').val();
    const productId = $('#modal-product-id').val();

    if (!storeId || !productId) {
        $('#modal-vaccine-store-stock').html('<p class="text-muted mb-0">Select a vaccine to see stock</p>');
        return;
    }

    fetchProductStockByStore(productId, function(stockData) {
        const storeStock = stockData.stores.find(s => s.store_id == storeId);
        const availableQty = storeStock ? storeStock.quantity : 0;
        const stockClass = availableQty> 0 ? 'text-success' : 'text-danger';
        const stockIcon = availableQty> 0 ? 'mdi-check-circle' : 'mdi-alert-circle';

        let html = `<div class="${stockClass}"><i class="mdi ${stockIcon}"></i> Available: <strong>${availableQty}</strong></div>`;
        if (availableQty <= 0) {
            html += `<div class="text-danger small"><i class="mdi mdi-alert"></i> Out of stock!</div>`;
        }

        $('#modal-vaccine-store-stock').html(html);
        $('#modal-selected-product-stock').html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> Stock in selected store: ${availableQty}</span>`);
    });
}

// Update consumable quantity change
$('#consumable-quantity').on('change', function() {
    updateConsumableStockDisplay();
});

// ===========================================
// STOCK VALIDATION HELPERS
// ===========================================

// Check stock availability before submission - returns Promise
function validateStockAvailability(storeId, products) {
    return new Promise((resolve, reject) => {
        const stockChecks = products.map(p => {
            return new Promise((res) => {
                fetchProductStockByStore(p.product_id, function(stockData) {
                    const storeStock = stockData.stores.find(s => s.store_id == storeId);
                    const availableQty = storeStock ? storeStock.quantity : 0;
                    res({
                        product_id: p.product_id,
                        product_name: p.product_name || 'Product',
                        requested_qty: parseInt(p.qty) || 1,
                        available_qty: availableQty,
                        sufficient: availableQty>= (parseInt(p.qty) || 1)
                    });
                });
            });
        });

        Promise.all(stockChecks).then(results => {
            const insufficientItems = results.filter(r => !r.sufficient);
            if (insufficientItems.length> 0) {
                reject({
                    type: 'insufficient_stock',
                    items: insufficientItems,
                    message: insufficientItems.map(i =>
                        `${i.product_name}: Need ${i.requested_qty}, only ${i.available_qty} available`
                    ).join('\n')
                });
            } else {
                resolve(results);
            }
        });
    });
}

// Show stock validation error with visual feedback
function showStockValidationError(items, tableSelector) {
    let errorHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    errorHtml += '<strong><i class="mdi mdi-alert-circle"></i> Insufficient Stock!</strong><br>';
    errorHtml += '<ul class="mb-0 pl-3">';

    items.forEach(item => {
        errorHtml += `<li>${item.product_name || 'Item'}: Requested <strong>${item.requested_qty}</strong>, Available <strong>${item.available_qty}</strong></li>`;

        // Highlight the row in the table
        if (tableSelector) {
            const $row = $(`${tableSelector} tr[data-product-id="${item.product_id}"]`);
            $row.addClass('table-danger').find('.stock-cell').addClass('text-danger fw-bold');
            $row.find('.injection-qty, .vaccine-qty').addClass('is-invalid');
        }
    });

    errorHtml += '</ul>';
    errorHtml += '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
    errorHtml += '</div>';

    return errorHtml;
}

// Injection Form Submit — §7.3 rewrite: 3-path source model
$('#injection-form').on('submit', function(e) {
    e.preventDefault();

    const drugSource = $('#injection-drug-source').val();
    const storeId = $('#injection-store').val();
    const billPatient = drugSource === 'ward_stock' ? ($('#injection-bill-patient').is(':checked') ? 1 : 0) : 0;

    // Ward stock requires store
    if (drugSource === 'ward_stock' && !storeId) {
        showNotification('error', 'Please select a store to dispense from');
        $('#injection-store').focus();
        return;
    }

    // Collect selected products/virtual rows
    const products = [];
    $('#injection-selected-body tr').each(function() {
        if (!$(this).find('.injection-row-check').is(':checked')) return;

        const rowSource = $(this).data('source') || drugSource;
        const batchId = $(this).find('.batch-select-dropdown').val() || null;
        const productRequestId = $(this).data('product-request-id') || null;

        if (rowSource === 'patient_own') {
            // §7.2: Virtual row — read external data from data attributes
            products.push({
                product_id: null, // no hospital product for patient's own
                product_name: $(this).find('td:eq(1) strong').text(),
                qty: $(this).find('.injection-qty').val(),
                dose: $(this).find('input[name="injection_dose[]"]').val(),
                batch_id: null,
                product_request_id: null,
                external_drug_name: $(this).data('ext-name') || $(this).find('td:eq(1) strong').text(),
                external_qty: $(this).data('ext-qty') || $(this).find('.injection-qty').val(),
                external_batch_number: $(this).data('ext-batch') || null,
                external_expiry_date: $(this).data('ext-expiry') || null,
                external_source_note: $(this).data('ext-note') || null,
            });
        } else {
            // pharmacy_dispensed or ward_stock — real hospital product
            products.push({
                product_id: $(this).data('product-id'),
                product_name: $(this).find('td:eq(1) strong').text(),
                qty: $(this).find('.injection-qty').val(),
                dose: $(this).find('input[name="injection_dose[]"]').val(),
                batch_id: batchId,
                product_request_id: productRequestId,
            });
        }
    });

    if (products.length === 0) {
        if (drugSource === 'patient_own') {
            showNotification('error', "Click 'Add Drug to List' first, then submit");
        } else {
            showNotification('error', 'Please select at least one drug');
        }
        return;
    }

    // Pharmacy dispensed must have product_request_id
    if (drugSource === 'pharmacy_dispensed') {
        const missingRx = products.some(p => !p.product_request_id);
        if (missingRx) {
            showNotification('error', 'Select from dispensed prescriptions before administering');
            return;
        }
    }

    // Dose is required for all rows
    const missingDose = products.some(p => !p.dose || !p.dose.trim());
    if (missingDose) {
        showNotification('warning', 'Enter a dose for every drug in the list');
        return;
    }

    // Clear previous validation errors
    $('#injection-selected-body .is-invalid').removeClass('is-invalid');
    $('#injection-selected-body .validation-error').remove();
    $('#injection-selected-body tr').removeClass('table-danger');
    $('#injection-stock-error').remove();

    // Validate stock before submission (ward_stock only)
    const $submitBtn = $(this).find('button[type="submit"]');
    const originalBtnHtml = $submitBtn.html();
    $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Checking Stock...');

    const stockPromise = drugSource === 'ward_stock'
        ? validateStockAvailability(storeId, products.filter(p => p.product_id))
        : Promise.resolve();

    stockPromise
        .then(() => {
            $submitBtn.html('<i class="mdi mdi-loading mdi-spin"></i> Administering...');

            const data = {
                patient_id: currentPatient,
                drug_source: drugSource,
                bill_patient: billPatient,
                products: products.map(p => ({
                    product_id: p.product_id || null,
                    qty: p.qty,
                    dose: p.dose,
                    batch_id: p.batch_id || null,
                    product_request_id: p.product_request_id || null,
                    external_drug_name: p.external_drug_name || null,
                    external_qty: p.external_qty || null,
                    external_batch_number: p.external_batch_number || null,
                    external_expiry_date: p.external_expiry_date || null,
                    external_source_note: p.external_source_note || null,
                })),
                route: $('#injection-route').val(),
                site: $('#injection-site').val(),
                administered_at: $('#injection-time').val(),
                notes: $('#injection-notes').val(),
                store_id: drugSource === 'ward_stock' ? storeId : null
            };

            $.ajax({
                url: '{{ route("nursing-workbench.injection.administer") }}',
                method: 'POST',
                data: data,
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                success: function(response) {
                    $submitBtn.prop('disabled', false).html(originalBtnHtml);
                    showNotification('success', response.message || 'Injection administered successfully');
                    $('#injection-form')[0].reset();
                    $('#injection-selected-body').empty();
                    setInjectionDrugSource('pharmacy_dispensed');
                    updateInjectionTotals();
                    loadInjectionHistory(currentPatient);
                },
                error: function(xhr) {
                    $submitBtn.prop('disabled', false).html(originalBtnHtml);
                    const response = xhr.responseJSON;
                    handleInjectionSubmitError(response, products);
                }
            });
        })
        .catch(stockError => {
            $submitBtn.prop('disabled', false).html(originalBtnHtml);

            const errorHtml = showStockValidationError(stockError.items, '#injection-selected-body');
            $('#injection-selected-drugs').before(`<div id="injection-stock-error">${errorHtml}</div>`);

            showNotification('error', 'Insufficient stock for one or more items');
        });
});

// Handle injection submit error (separated for cleaner code)
function handleInjectionSubmitError(response, products) {
    const checkedRows = $('#injection-selected-body tr').filter(function() {
        return $(this).find('.injection-row-check').is(':checked');
    });

    if (response?.errors) {
        let errorMessages = [];

        Object.keys(response.errors).forEach(function(field) {
            // Parse field like "products.0.dose" or "products.1.qty"
            const match = field.match(/^products\.(\d+)\.(\w+)$/);
            if (match) {
                const index = parseInt(match[1]);
                const fieldName = match[2];
                const row = checkedRows.eq(index);

                if (row.length) {
                    const productName = row.find('td:eq(1) strong').text();
                    let inputField;

                    if (fieldName === 'dose') {
                        inputField = row.find('input[name="injection_dose[]"]');
                    } else if (fieldName === 'qty') {
                        inputField = row.find('.injection-qty');
                    }

                    if (inputField && inputField.length) {
                        inputField.addClass('is-invalid');
                        const errorMsg = response.errors[field][0].replace(/products\.\d+\./, '');
                        inputField.after(`<div class="validation-error text-danger small">${errorMsg}</div>`);
                    }

                    errorMessages.push(`${productName}: ${response.errors[field][0].replace(/products\.\d+\./, '')}`);
                }
            } else {
                errorMessages.push(response.errors[field][0]);
            }
        });

        if (errorMessages.length> 0) {
            showNotification('error', 'Validation failed: ' + errorMessages.join(', '));
        } else {
            showNotification('error', response.message || 'Validation failed');
        }
    } else {
        showNotification('error', response?.message || 'Failed to administer injection');
    }
}

// Load Injection History with DataTable
function loadInjectionHistory(patientId) {
    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable('#injection-history-table')) {
        $('#injection-history-table').DataTable().destroy();
    }

    $('#injection-history-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: `{{ url('/nursing-workbench/patient/${patientId}/injections') }}`,
            dataSrc: ''
        },
        columns: [
            { data: 'administered_at' },
            {
                data: 'product_name',
                render: function(data, type, row) {
                    let name = data || 'N/A';
                    if (row.drug_source === 'patient_own') {
                        let tip = 'Patient\'s Own Drug';
                        if (row.external_qty) tip += ' | Qty: ' + row.external_qty;
                        if (row.external_batch_number) tip += ' | Batch: ' + row.external_batch_number;
                        if (row.external_expiry_date) tip += ' | Exp: ' + row.external_expiry_date;
                        if (row.external_source_note) tip += ' | Note: ' + row.external_source_note;
                        name = '<span title="' + tip + '">' + name + '</span> <span class="badge badge-warning badge-sm">Patient\'s Own</span>';
                    } else if (row.drug_source === 'ward_stock') {
                        name += ' <span class="badge badge-info badge-sm">Ward Stock</span>';
                    }
                    return name;
                }
            },
            { data: 'dose' },
            { data: 'route' },
            { data: 'site' },
            { data: 'administered_by' }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        language: {
            emptyTable: "No injection history found"
        }
    });
}

// ========================================
// IMMUNIZATION MODULE - Vaccine Search & Administration
// ========================================

// Vaccine/Drug Search (uses same endpoint as prescription form)
let vaccineSearchTimeout;
$('#vaccine-drug-search').on('input', function() {
    const query = $(this).val();
    clearTimeout(vaccineSearchTimeout);

    if (query.length < 2) {
        $('#vaccine-drug-results').hide();
        return;
    }

    vaccineSearchTimeout = setTimeout(function() {
        $.ajax({
            url: "{{ url('live-search-products') }}",
            method: 'GET',
            dataType: 'json',
            data: { term: query, patient_id: currentPatient },
            success: function(data) {
                $('#vaccine-drug-results').html('');

                if (data.length === 0) {
                    $('#vaccine-drug-results').html('<li class="list-group-item text-muted">No products found</li>').show();
                    return;
                }

                data.forEach(function(item) {
                    const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                    const name = item.product_name || 'Unknown';
                    const code = item.product_code || '';
                    const qty = item.stock && item.stock.current_quantity !== undefined ? item.stock.current_quantity : 0;
                    const price = item.price && item.price.initial_sale_price !== undefined ? item.price.initial_sale_price : 0;
                    const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                    const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                    const mode = item.coverage_mode || 'cash';

                    const coverageBadge = mode && mode !== 'cash'
                        ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ₦${payable}</span> <span class='text-success ms-1'>Claim: ₦${claims}</span>`
                        : '';

                    const qtyClass = qty> 0 ? 'text-success' : 'text-danger';

                    const mk = `<li class='list-group-item list-group-item-action' style="cursor: pointer;"
                               data-id="${item.id}"
                               data-name="${name}"
                               data-code="${code}"
                               data-qty="${qty}"
                               data-price="${price}"
                               data-payable="${payable}"
                               data-claims="${claims}"
                               data-mode="${mode}"
                               data-category="${category}"
                               onclick="addVaccineDrug(this)">
                               <div class="d-flex justify-content-between align-items-start">
                                   <div>
                                       <strong>${name}</strong> <small class="text-muted">[${code}]</small>
                                       <div class="small text-muted">${category}</div>
                                   </div>
                                   <div class="text-end">
                                       <div class="${qtyClass}"><strong>${qty}</strong> avail.</div>
                                       <div>₦${price}</div>
                                   </div>
                               </div>
                               ${coverageBadge ? `<div class="small mt-1">${coverageBadge}</div>` : ''}
                           </li>`;
                    $('#vaccine-drug-results').append(mk);
                });
                $('#vaccine-drug-results').show();
            },
            error: function(xhr) {
                console.error('Product search failed', xhr);
                $('#vaccine-drug-results').html('<li class="list-group-item text-danger">Search failed</li>').show();
            }
        });
    }, 300);
});

// Add selected vaccine to table
function addVaccineDrug(element) {
    const $el = $(element);
    const id = $el.data('id');
    const name = $el.data('name');
    const code = $el.data('code');
    const qty = $el.data('qty');
    const price = parseFloat($el.data('price')) || 0;
    const payable = parseFloat($el.data('payable')) || price;
    const claims = parseFloat($el.data('claims')) || 0;
    const mode = $el.data('mode') || 'cash';

    // Check if already added
    if ($(`#vaccine-selected-body tr[data-product-id="${id}"]`).length> 0) {
        showNotification('warning', 'This vaccine is already in the list');
        $('#vaccine-drug-results').hide();
        $('#vaccine-drug-search').val('');
        return;
    }

    const coverageInfo = mode && mode !== 'cash'
        ? `<span class="badge bg-info">${mode.toUpperCase()}</span><br><small class="text-danger">Pay: ₦${payable}</small><br><small class="text-success">Claim: ₦${claims}</small>`
        : '<span class="badge bg-secondary">Cash</span>';

    const row = `
        <tr data-product-id="${id}" data-price="${payable}">
            <td><input type="checkbox" class="form-check-input vaccine-row-check" checked></td>
            <td>
                <strong>${name}</strong><br>
                <small class="text-muted">[${code}]</small>
                <input type="hidden" name="vaccine_products[]" value="${id}">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm vaccine-qty"
                       name="vaccine_qty[]" value="1" min="1" max="${qty}" style="width: 70px;">
                <small class="text-muted">${qty} avail.</small>
            </td>
            <td>₦${payable.toFixed(2)}</td>
            <td>${coverageInfo}</td>
            <td>
                <input type="text" class="form-control form-control-sm"
                       name="vaccine_dose[]" placeholder="Dose amount">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeVaccineRow(this)">
                    <i class="mdi mdi-close"></i>
                </button>
            </td>
        </tr>
    `;

    $('#vaccine-selected-body').append(row);
    updateVaccineTotals();
    $('#vaccine-drug-results').hide();
    $('#vaccine-drug-search').val('');
}

// Remove row from vaccine table
function removeVaccineRow(btn) {
    $(btn).closest('tr').remove();
    updateVaccineTotals();
}

// Update vaccine totals
function updateVaccineTotals() {
    let total = 0;
    $('#vaccine-selected-body tr').each(function() {
        const price = parseFloat($(this).data('price')) || 0;
        const qty = parseInt($(this).find('.vaccine-qty').val()) || 1;
        total += price * qty;
    });
    $('#vaccine-total-price').html(`<strong>₦${total.toFixed(2)}</strong>`);
}

// Recalculate on qty change
$(document).on('change', '.vaccine-qty', function() {
    updateVaccineTotals();
});

// Immunization Form Submit
$('#immunization-form').on('submit', function(e) {
    e.preventDefault();

    // Collect selected products
    const products = [];
    $('#vaccine-selected-body tr').each(function() {
        if ($(this).find('.vaccine-row-check').is(':checked')) {
            products.push({
                product_id: $(this).data('product-id'),
                qty: $(this).find('.vaccine-qty').val(),
                dose: $(this).find('input[name="vaccine_dose[]"]').val()
            });
        }
    });

    if (products.length === 0) {
        showNotification('error', 'Please select at least one vaccine');
        return;
    }

    const data = {
        patient_id: currentPatient,
        products: products,
        dose_number: $('#vaccine-dose-number').val(),
        routine: $('#vaccine-routine').val(),
        site: $('#vaccine-site').val(),
        administered_at: $('#vaccine-time').val(),
        batch_number: $('#vaccine-batch').val(),
        expiry_date: $('#vaccine-expiry').val(),
        notes: $('#vaccine-notes').val()
    };

    $.ajax({
        url: '{{ route("nursing-workbench.immunization.administer") }}',
        method: 'POST',
        data: data,
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        success: function(response) {
            showNotification('success', response.message || 'Vaccine administered successfully');
            $('#immunization-form')[0].reset();
            $('#vaccine-selected-body').empty();
            updateVaccineTotals();
            loadImmunizationHistory(currentPatient);
            loadImmunizationSchedule(currentPatient);
        },
        error: function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to administer vaccine');
        }
    });
});

// Load Immunization Schedule (New Schedule System)
function loadImmunizationSchedule(patientId) {
    ImmunizationModule.configure({
        baseUrl: '/nursing-workbench',
        csrfToken: '{{ csrf_token() }}',
        currentPatientId: patientId,
        onScheduleReload: function() { loadImmunizationSchedule(patientId); },
        onHistoryReload: function() { loadImmunizationHistory(patientId); },
        storesHtml: $('#ctx-store-select').html() || '', // Or whatever provides store HTML if needed, but it's handled by modal
        productSearchUrl: '{{ route("nursing-workbench.search-products") }}',
        productBatchesUrl: '/nursing-workbench/product-batches'
    });
    
    ImmunizationModule.initModalEvents();

    ImmunizationModule.loadSchedule(
        patientId,
        '#immunization-schedule-container',
        `/nursing-workbench/patient/${patientId}/schedule`,
        {
            activeSchedulesId: '#active-schedules-badges'
        }
    );
}

// Load immunization timeline view
function loadImmunizationTimeline(patientId) {
    ImmunizationModule.loadTimeline(patientId, '#history-timeline-view', `/nursing-workbench/patient/${patientId}/immunization-history`);
}

// Load immunization calendar view
function loadImmunizationCalendar(patientId) {
    ImmunizationModule.loadCalendar(patientId, '#history-calendar-view', `/nursing-workbench/patient/${patientId}/immunization-history`);
}

// Load Immunization History Table View with DataTable
function loadImmunizationHistoryTable(patientId) {
    ImmunizationModule.loadHistoryTable(patientId, '#history-table-view', '#immunization-history-table', `/nursing-workbench/patient/${patientId}/immunization-history`);
}

// Function to load active history view
function loadImmunizationHistory(patientId) {
    if (!patientId) return;
    
    // Determine which view is active
    let activeView = 'timeline';
    if ($('#view-calendar-btn').hasClass('active')) activeView = 'calendar';
    if ($('#view-table-btn').hasClass('active')) activeView = 'table';
    
    if (activeView === 'timeline') {
        loadImmunizationTimeline(patientId);
    } else if (activeView === 'calendar') {
        loadImmunizationCalendar(patientId);
    } else {
        loadImmunizationHistoryTable(patientId);
    }
}

// History view toggles
$(document).on('click', '#view-timeline-btn, #view-calendar-btn, #view-table-btn', function() {
    const view = $(this).data('view');
    
    // Update active state
    $('#view-timeline-btn, #view-calendar-btn, #view-table-btn').removeClass('active');
    $(this).addClass('active');
    
    // Show correct container
    $('#history-timeline-view, #history-calendar-view, #history-table-view').addClass('d-none');
    $(`#history-${view}-view`).removeClass('d-none');
    
    // Load data
    if (currentPatient) {
        if (view === 'timeline') loadImmunizationTimeline(currentPatient);
        else if (view === 'calendar') loadImmunizationCalendar(currentPatient);
        else loadImmunizationHistoryTable(currentPatient);
    }
});

// Load Note Types
// Functions for Notes
// Note Types loading removed as requested
// function loadNoteTypes() { ... }

// Nursing Note Form Submit
// Initialize CKEditor for nursing note
// Initialize CKEditor for nursing note using the shared WorkbenchNotesKit
let nursingNoteEditor;
var nurseNoteAutosaveTimer = null;

function initNursingNoteCKEditor() {
    WorkbenchNotesKit.initEditor({
        prefix: 'nursing',
        editorSelector: '#nursing-note-editor',
        formSelector: '#nursing-note-form',
        statusSelector: '#note-autosave-status',
        noteTypeId: 5,
        csrfToken: '{{ csrf_token() }}',
        getSaveUrl: function(patientId) {
            return '{{ route("nursing-workbench.notes.store") }}';
        },
        getPatientId: function() {
            return currentPatient;
        },
        onSaveSuccess: function() {
            // Switch to history tab to see the new note
            $('#notes-history-tab-link').tab('show');
            loadNotesHistory(currentPatient);
        }
    });
    
    // Keep nursingNoteEditor synced for reference
    nursingNoteEditor = WorkbenchNotesKit.editors['nursing'];
}

// Ensure editor is initialized when tab shown
$('a[data-toggle="tab"][href="#notes-tab"]').on('shown.bs.tab', function (e) {
    initNursingNoteCKEditor();
});
$('a[data-toggle="tab"][href="#notes-add"]').on('shown.bs.tab', function (e) {
    initNursingNoteCKEditor();
});

// Also try to init on page load after a brief delay
setTimeout(initNursingNoteCKEditor, 1000);

// Load Notes History with Cards (DataTable)
function loadNotesHistory(patientId) {
    if (!patientId) return;

    if ($.fn.DataTable.isDataTable('#nursing-notes-table')) {
        $('#nursing-notes-table').DataTable().ajax.url(`/nursing-workbench/patient/${patientId}/nursing-notes`).load();
    } else {
        $('#nursing-notes-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: `/nursing-workbench/patient/${patientId}/nursing-notes`,
            columns: [
                { data: 'info', name: 'info', orderable: false, searchable: false }
            ],
            ordering: false,
            lengthChange: false,
            pageLength: 10,
            searching: false,
            dom: "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-5'i><'col-sm-7'p>>",
            language: {
                emptyTable: `<div class="text-center py-5">
                                <i class="mdi mdi-note-outline mdi-48px text-muted"></i>
                                <p class="text-muted mt-2">No nursing notes found</p>
                            </div>`,
                processing: `<div class="text-center">
                                <i class="mdi mdi-loading mdi-spin mdi-24px text-primary"></i>
                             </div>`
            }
        });
    }
}

// Initialize nursing-specific features on tab switch
function switchWorkspaceTab(tab) {
    $('.workspace-tab').removeClass('active');
    $('.workspace-tab-content').removeClass('active');

    $(`.workspace-tab[data-tab="${tab}"]`).addClass('active');
    $(`#${tab}-tab`).addClass('active');

    // Load tab-specific content
    if (!currentPatient) return;

    switch(tab) {
        case 'overview':
            loadPatientOverview(currentPatient);
            break;
        case 'clinical-story':
            if (currentPatient) {
                const $storyWrapper = $('.clinical-story-wrapper');
                $storyWrapper.data('patient-id', currentPatient);
                $storyWrapper.attr('data-patient-id', currentPatient);
                
                const inst = $storyWrapper.data('clinicalStory');
                if (inst) {
                    inst.patientId = currentPatient;
                    inst.resetTimeline();
                    inst.loadTimeline();
                } else {
                    $('.clinical-story-wrapper').each(function () {
                        const $w = $(this);
                        $w.data('clinicalStory', new ClinicalStory(this));
                    });
                }
            }
            break;
        case 'medication':
            // Initialize medication chart with current patient
            if (typeof initMedicationChart === 'function') {
                initMedicationChart(currentPatient);
            }
            break;
        case 'intake-output':
            // Initialize I/O chart with current patient
            if (typeof initIntakeOutputChart === 'function') {
                initIntakeOutputChart(currentPatient);
            }
            break;
        case 'injection':
            loadInjectionHistory(currentPatient);
            // Set current time
            $('#injection-time').val(new Date().toISOString().slice(0, 16));
            break;
        case 'immunization':
            loadImmunizationSchedule(currentPatient);
            loadImmunizationHistory(currentPatient);
            $('#vaccine-time').val(new Date().toISOString().slice(0, 16));
            break;
        case 'billing':
            if (window.BillingKit) {
                BillingKit.setPatient(currentPatient);
            }
            break;
        case 'notes':
            // Removed loadNoteTypes call as it is no longer needed
            loadNotesHistory(currentPatient);
            break;
    }
}

// Notification helper
function showNotification(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
    const html = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>`;

    // Create a notification container if it doesn't exist
    if ($('#notification-container').length === 0) {
        $('body').append('<div id="notification-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999; width: 350px;"></div>');
    }

    $('#notification-container').append(html);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        $('#notification-container .alert').first().alert('close');
    }, 5000);
}

</script>

{{-- ================================================================ --}}
{{-- MEDICATION & I/O CHART SCRIPTS (Adapted from nurse_chart_scripts_enhanced) --}}
{{-- ================================================================ --}}
<script>
// Workbench-specific wrapper variables for medication and I/O charts
// These will be set dynamically when a patient is selected
var PATIENT_ID = null;
var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// Route templates - will be used with actual patient ID
var medicationChartIndexRoute = "{{ route('nurse.medication.index', ['patient' => ':patient']) }}";
var medicationChartScheduleRoute = "{{ route('nurse.medication.schedule') }}";
var medicationChartAdministerRoute = "{{ route('nurse.medication.administer') }}";
var medicationChartDiscontinueRoute = "{{ route('nurse.medication.discontinue') }}";
var medicationChartResumeRoute = "{{ route('nurse.medication.resume') }}";
var medicationChartDeleteRoute = "{{ route('nurse.medication.delete') }}";
var medicationChartEditRoute = "{{ route('nurse.medication.edit') }}";
var medicationChartRemoveScheduleRoute = "{{ route('nurse.medication.remove_schedule') }}";
var medicationChartCalendarRoute = "{{ route('nurse.medication.calendar', ['patient' => ':patient', 'medication' => ':medication', 'start_date' => ':start_date']) }}";
var medicationChartPrescribedRoute = "{{ route('nurse.medication.prescribed_drugs', ['patient' => ':patient']) }}";
var medicationChartDismissRoute = "{{ route('nurse.medication.dismiss_prescription', ['patient' => ':patient']) }}";
var medicationChartAdministerDirectRoute = "{{ route('nurse.medication.administer_direct', ['patient' => ':patient']) }}";
var medicationChartDirectCalendarRoute = "{{ route('nurse.medication.direct_calendar', ['patient' => ':patient']) }}";
var medicationChartOverviewRoute = "{{ route('nurse.medication.overview', ['patient' => ':patient']) }}";

var intakeOutputChartIndexRoute = "{{ route('nurse.intake_output.index', ['patient' => ':patient']) }}";
var intakeOutputChartLogsRoute = "{{ route('nurse.intake_output.logs', ['patient' => ':patient', 'period' => ':period']) }}";
var intakeOutputChartStartRoute = "{{ route('nurse.intake_output.start') }}";
var intakeOutputChartEndRoute = "{{ route('nurse.intake_output.end') }}";
var intakeOutputChartRecordRoute = "{{ route('nurse.intake_output.record') }}";
var intakeOutputChartDeleteRecordRoute = "{{ route('nurse.intake_output.delete_record', ['record' => ':record']) }}";

// Edit window from settings
var NOTE_EDIT_WINDOW = {{ appsettings('note_edit_window', 30) }};

// Global variables for medication chart
let selectedMedication = null;
let calendarStartDate = new Date();
calendarStartDate.setDate(calendarStartDate.getDate() - 15);
let medications = [];
let medicationStatus = {};
let currentSchedules = [];
let currentAdministrations = [];
let medicationHistory = {};
let patientPrescriptions = [];
let patientPrescriptionsLoaded = false;

// §4.6: Drug source badge helper
function getDrugSourceBadge(drugSource, productRequestId) {
    switch (drugSource) {
        case 'patient_own':
            return '<span class="badge" style="background:#7b1fa2;"><i class="mdi mdi-account-heart"></i> Patient\'s Own</span>';
        case 'ward_stock':
            if (productRequestId) {
                return '<span class="badge bg-primary"><i class="mdi mdi-hospital-building"></i> Ward Stock (Billed)</span>';
            }
            return '<span class="badge bg-info"><i class="mdi mdi-hospital-building"></i> Ward Stock</span>';
        case 'pharmacy_dispensed':
        default:
            return '<span class="badge bg-success"><i class="mdi mdi-pill"></i> Pharmacy Dispensed</span>';
    }
}

function setDrugSource(source) {
    $('#administer_drug_source').val(source || 'pharmacy_dispensed');
}

// Helper: set datetime-local input to current time
function setCurrentDateTime(inputId) {
    var now = new Date();
    var offset = now.getTimezoneOffset();
    var local = new Date(now.getTime() - offset * 60000);
    document.getElementById(inputId).value = local.toISOString().slice(0, 16);
}

// §6.1: Select2 template for dropdown results (rich format)
function formatRxOption(option) {
    if (!option.id) return option.text;

    var $opt = $(option.element);

    // Handle separator
    if ($opt.data('is-separator')) {
        return $('<div class="text-muted fw-bold small py-1 border-top mt-1">' + option.text + '</div>');
    }

    // Handle direct administration entries (ward stock / patient's own)
    var directEntry = $opt.data('direct-entry');
    if (directEntry) {
        var deIcon = $opt.data('status-icon') || '';
        var isPatientOwn = directEntry.drug_source === 'patient_own';
        var deLabel = isPatientOwn ? "Patient's Own" : 'Ward Stock';
        var deBadgeClass = isPatientOwn ? 'bg-purple' : 'bg-info';
        var deBadgeHtml = '<span class="badge ' + deBadgeClass + '">' + deLabel + '</span>';
        var deDrugName = directEntry.product_name || directEntry.external_drug_name || 'Unknown';
        var deCodeStr = directEntry.product_code ? '(' + directEntry.product_code + ')' : '';
        var deSchedCount = directEntry.times_scheduled || 0;
        var deAdminCount = directEntry.times_administered || 0;

        return $(
            '<div class="d-flex flex-column py-1">' +
                '<div class="d-flex align-items-center gap-2">' +
                    '<span style="font-size:1.1em;">' + deIcon + '</span>' +
                    '<strong>' + deDrugName + '</strong>' +
                    '<small class="text-muted">' + deCodeStr + '</small>' +
                    deBadgeHtml +
                '</div>' +
                '<div class="d-flex gap-3 ms-4">' +
                    '<small class="text-muted">Scheduled: ' + deSchedCount + '</small>' +
                    '<small class="text-info">Administered: ' + deAdminCount + '</small>' +
                    '<small class="text-muted">by ' + directEntry.nurse_name + '</small>' +
                '</div>' +
            '</div>'
        );
    }

    // Handle pharmacy prescriptions
    var rx = $opt.data('rx');
    if (!rx) return option.text;

    var icon = $opt.data('status-icon') || '';
    var badge = $opt.data('status-badge') || '';
    var adminText = $opt.data('admin-text') || '';
    var doctorText = $opt.data('doctor-text') || '';
    var isDisabled = option.disabled;

    return $(
        '<div class="d-flex flex-column py-1 ' + (isDisabled ? 'opacity-50' : '') + '">' +
            '<div class="d-flex align-items-center gap-2">' +
                '<span style="font-size:1.1em;">' + icon + '</span>' +
                '<strong>' + rx.product_name + '</strong>' +
                '<small class="text-muted">(' + rx.product_code + ')</small>' +
                badge +
            '</div>' +
            '<div class="d-flex gap-3 ms-4">' +
                '<small class="text-muted">Prescribed: ' + rx.qty_prescribed + '</small>' +
                '<small class="text-muted">Administered: ' + (rx.qty_administered || 0) + '</small>' +
                '<small class="text-muted">Remaining: ' + (rx.remaining_doses || 0) + '</small>' +
                '<small class="text-muted">Scheduled: ' + (rx.times_scheduled || 0) + '</small>' +
                (adminText ? '<small class="text-info">' + adminText + '</small>' : '') +
                (doctorText ? '<small class="text-muted">' + doctorText + '</small>' : '') +
                (rx.remaining_doses === 0 && rx.is_dispensed ? '<small class="text-success fw-bold">✓ Fully administered</small>' : '') +
            '</div>' +
            (isDisabled ? '<small class="text-danger ms-4"><i class="mdi mdi-lock"></i> ' + rx.status_label + ' — cannot chart</small>' : '') +
        '</div>'
    );
}

// §6.1: Select2 template for selected item (compact)
function formatRxSelection(option) {
    if (!option.id) return option.text;

    var $opt = $(option.element);

    // Handle direct entry selections
    var directEntry = $opt.data('direct-entry');
    if (directEntry) {
        var deIcon = $opt.data('status-icon') || '';
        var deDrugName = directEntry.product_name || directEntry.external_drug_name || 'Unknown';
        var deCodeStr = directEntry.product_code ? '(' + directEntry.product_code + ')' : '';
        return deIcon + ' ' + deDrugName + ' ' + deCodeStr;
    }

    var rx = $opt.data('rx');
    if (!rx) return option.text;

    var icon = $opt.data('status-icon') || '';
    var remainStr = (rx.remaining_doses !== undefined) ? ' [' + (rx.qty_administered || 0) + '/' + rx.qty_prescribed + ' used]' : '';
    return icon + ' ' + rx.product_name + ' (' + rx.product_code + ')' + remainStr;
}

// =============================================
// OVERVIEW TAB FUNCTIONS
// =============================================
var overviewCurrentStart = null;
var overviewDataCache = null;

function loadMedOverview(startDate) {
    if (!PATIENT_ID) return;

    if (!startDate) {
        // Default to current week start (Monday)
        var d = new Date();
        d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); // Monday
        startDate = formatDateForApi(d);
    }
    overviewCurrentStart = startDate;

    var endDate = new Date(startDate);
    endDate.setDate(endDate.getDate() + 6);
    var endStr = formatDateForApi(endDate);

    $('#overview-loading').show();
    $('#unified-overview-container').html('');

    var url = medicationChartOverviewRoute.replace(':patient', PATIENT_ID);

    $.ajax({
        url: url,
        type: 'GET',
        data: { start_date: startDate, end_date: endStr },
        success: function(data) {
            $('#overview-loading').hide();
            overviewDataCache = data;

            // Update stats
            var stats = data.stats || {};
            $('#stat-total-meds').text(stats.total_medications || 0);
            $('#stat-given').text(stats.total_given || 0);
            $('#stat-scheduled').text(stats.total_scheduled || 0);
            $('#stat-missed').text(stats.total_missed || 0);

            // Render the 7-day calendar
            renderOverviewCalendar(data, startDate, endStr);
        },
        error: function(xhr) {
            $('#overview-loading').hide();
            $('#unified-overview-container').html('<div class="alert alert-danger"><i class="mdi mdi-alert"></i> Failed to load overview.</div>');
        }
    });
}

function renderOverviewCalendar(data, startStr, endStr) {
    var container = $('#unified-overview-container');
    container.html('');

    var startDate = new Date(startStr);
    var today = new Date();
    today.setHours(0,0,0,0);

    var dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    // Build day columns header
    var headerHtml = '<div class="calendar-weekday-header d-flex">';
    for (var i = 0; i < 7; i++) {
        var day = new Date(startDate);
        day.setDate(day.getDate() + i);
        var isToday = day.toDateString() === today.toDateString();
        var isWeekend = (day.getDay() === 0 || day.getDay() === 6);
        headerHtml += '<div class="weekday-name flex-fill text-center py-1 small fw-bold ' +
            (isToday ? 'bg-primary text-white rounded' : '') +
            (isWeekend ? ' text-muted' : '') + '">' +
            dayNames[day.getDay()] + ' ' + day.getDate() + '/' + (day.getMonth()+1) +
            '</div>';
    }
    headerHtml += '</div>';

    // Group schedules and admins by date
    var schedulesByDay = {};
    var adminsByDay = {};

    (data.schedules || []).forEach(function(s) {
        var dateKey = s.scheduled_time.substring(0, 10);
        if (!schedulesByDay[dateKey]) schedulesByDay[dateKey] = [];
        schedulesByDay[dateKey].push(s);
    });

    (data.unscheduled_admins || []).forEach(function(a) {
        var dateKey = a.administered_at.substring(0, 10);
        if (!adminsByDay[dateKey]) adminsByDay[dateKey] = [];
        adminsByDay[dateKey].push(a);
    });

    // Build day columns
    var gridHtml = '<div class="medication-calendar-grid d-flex" style="min-height:200px;">';
    for (var i = 0; i < 7; i++) {
        var day = new Date(startDate);
        day.setDate(day.getDate() + i);
        var dateKey = formatDateForApi(day);
        var isToday = day.toDateString() === today.toDateString();
        var isPast = day < today && !isToday;
        var isWeekend = (day.getDay() === 0 || day.getDay() === 6);

        var cellClass = 'calendar-day-cell flex-fill border-end p-1';
        if (isToday) cellClass += ' today';
        if (isPast) cellClass += ' past-date';
        if (isWeekend) cellClass += ' weekend';

        gridHtml += '<div class="' + cellClass + '" data-date="' + dateKey + '">';
        gridHtml += '<div class="schedule-items">';

        // Render schedules
        var daySchedules = (schedulesByDay[dateKey] || []).sort(function(a,b) {
            return a.scheduled_time.localeCompare(b.scheduled_time);
        });

        daySchedules.forEach(function(s) {
            var time = new Date(s.scheduled_time);
            var timeStr = time.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit', hour12:true});
            var statusClass = s.is_administered ? 'status-given' : (isPast ? 'status-missed' : 'status-pending');
            var sourceIcon = s.drug_source === 'ward_stock' ? '🏥' : (s.drug_source === 'patient_own' ? '👤' : '💊');
            var statusIcon = s.is_administered ? '✅' : (isPast ? '❌' : '🕐');

            gridHtml += '<div class="med-item ' + statusClass + '" title="' + s.drug_name + ' - ' + s.dose + ' ' + s.route + '">';
            gridHtml += '<span class="med-time">' + timeStr + '</span> ';
            gridHtml += '<span class="med-name">' + sourceIcon + ' ' + truncate(s.drug_name, 15) + '</span> ';
            gridHtml += '<span class="med-status">' + statusIcon + '</span>';
            gridHtml += '</div>';
        });

        // Render unscheduled administrations
        var dayAdmins = adminsByDay[dateKey] || [];
        dayAdmins.forEach(function(a) {
            var time = new Date(a.administered_at);
            var timeStr = time.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit', hour12:true});
            var sourceIcon = a.drug_source === 'ward_stock' ? '🏥' : (a.drug_source === 'patient_own' ? '👤' : '💊');

            gridHtml += '<div class="med-item status-given" title="' + a.drug_name + ' - ' + a.dose + ' (unscheduled)">';
            gridHtml += '<span class="med-time">' + timeStr + '</span> ';
            gridHtml += '<span class="med-name">' + sourceIcon + ' ' + truncate(a.drug_name, 15) + '</span> ';
            gridHtml += '<span class="med-status">✅</span>';
            gridHtml += '</div>';
        });

        if (daySchedules.length === 0 && dayAdmins.length === 0) {
            gridHtml += '<div class="text-muted text-center small py-3"><i class="mdi mdi-calendar-blank"></i><br>No items</div>';
        }

        gridHtml += '</div></div>';
    }
    gridHtml += '</div>';

    container.html(headerHtml + gridHtml);
}

function truncate(str, len) {
    if (!str) return '';
    return str.length> len ? str.substring(0, len) + '…' : str;
}

// Overview nav buttons
$(document).on('click', '#overview-prev-btn', function() {
    if (!overviewCurrentStart) return;
    var d = new Date(overviewCurrentStart);
    d.setDate(d.getDate() - 7);
    loadMedOverview(formatDateForApi(d));
});

$(document).on('click', '#overview-next-btn', function() {
    if (!overviewCurrentStart) return;
    var d = new Date(overviewCurrentStart);
    d.setDate(d.getDate() + 7);
    loadMedOverview(formatDateForApi(d));
});

$(document).on('click', '#overview-today-btn', function() {
    loadMedOverview(null); // null → defaults to current week
});

// =============================================
// PRESCRIPTIONS TAB FUNCTIONS
// =============================================
var rxTabDataCache = null;
var rxCurrentFilter = 'all';

function loadPrescriptionsTab() {
    if (!PATIENT_ID) return;

    $('#rx-loading').show();
    $('#rx-table-wrap').hide();
    $('#rx-empty').hide();

    var url = medicationChartPrescribedRoute.replace(':patient', PATIENT_ID);

    $.ajax({
        url: url,
        type: 'GET',
        success: function(data) {
            $('#rx-loading').hide();
            rxTabDataCache = data;

            var rxList = data.prescriptions || [];
            var directList = data.direct_entries || [];

            // Update summary counts
            var dispensed = rxList.filter(function(r) { return r.status === 3; }).length;
            var billed = rxList.filter(function(r) { return r.status === 2; }).length;
            var requested = rxList.filter(function(r) { return r.status === 1; }).length;
            var total = rxList.length + directList.length;

            $('#rx-count-dispensed').text(dispensed);
            $('#rx-count-billed').text(billed);
            $('#rx-count-requested').text(requested);
            $('#rx-count-total').text(total);
            $('#rx-tab-badge').text(total).toggle(total> 0);

            // Render table
            renderPrescriptionsTable(rxList, directList, rxCurrentFilter);
        },
        error: function() {
            $('#rx-loading').hide();
            $('#rx-empty').show().find('p').text('Failed to load prescriptions.');
        }
    });
}

function renderPrescriptionsTable(rxList, directList, filter) {
    var $body = $('#rx-dashboard-body');
    $body.empty();

    var filtered = rxList;
    if (filter && filter !== 'all') {
        filtered = rxList.filter(function(r) { return r.status == filter; });
    }

    if (filtered.length === 0 && (filter !== 'all' || directList.length === 0)) {
        $('#rx-table-wrap').hide();
        $('#rx-empty').show();
        return;
    }

    $('#rx-empty').hide();
    $('#rx-table-wrap').show();

    // Pharmacy prescriptions
    filtered.forEach(function(rx) {
        var statusBadge, statusClass;
        switch (rx.status) {
            case 3:
                statusBadge = '<span class="badge bg-success">Dispensed</span>';
                statusClass = '';
                break;
            case 2:
                statusBadge = rx.is_paid
                    ? '<span class="badge bg-warning text-dark">Awaiting Pharmacy</span>'
                    : '<span class="badge bg-secondary">' + (rx.status_label || 'Awaiting Payment') + '</span>';
                statusClass = '';
                break;
            default:
                statusBadge = '<span class="badge bg-danger">Awaiting Billing</span>';
                statusClass = 'table-danger';
        }

        var qtyInfo = rx.qty_prescribed || 0;
        var adminInfo = (rx.qty_administered || 0) + ' / ' + qtyInfo;
        var remaining = rx.remaining_doses || 0;
        var adminBadge = rx.is_fully_administered
            ? '<span class="badge bg-success">Complete</span>'
            : '<span class="badge bg-' + (remaining <= 0 ? 'danger' : 'secondary') + '">' + adminInfo + '</span>';

        var prescDate = rx.prescribed_at ? formatDate(new Date(rx.prescribed_at)) : '-';

        var actionBtns = '';
        if (rx.can_chart) {
            actionBtns = '<button class="btn btn-sm btn-outline-primary rx-select-btn" data-posr-id="' + rx.posr_id + '" title="Select in chart"><i class="mdi mdi-pencil-plus"></i></button>';
        }
        if (rx.status !== 3 && rx.status !== 0) {
            actionBtns += ' <button class="btn btn-sm btn-outline-danger rx-dismiss-btn" data-product-request-id="' + rx.product_request_id + '" data-drug-name="' + (rx.product_name || '') + '" title="Dismiss"><i class="mdi mdi-close-circle"></i></button>';
        }

        $body.append(
            '<tr class="' + statusClass + '">' +
                '<td><strong>' + (rx.product_name || 'Unknown') + '</strong><br><small class="text-muted">' + (rx.product_code || '') + '</small></td>' +
                '<td>' + (rx.dose || '-') + '</td>' +
                '<td><small>' + (rx.doctor_name ? 'Dr. ' + rx.doctor_name : '-') + '</small></td>' +
                '<td><small>' + prescDate + '</small></td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' + adminBadge + '<br><small class="text-muted">Remaining: ' + remaining + '</small></td>' +
                '<td class="text-center">' + actionBtns + '</td>' +
            '</tr>'
        );
    });

    // Direct entries (show if filter = 'all')
    if (filter === 'all' && directList.length> 0) {
        $body.append('<tr class="table-light"><td colspan="7" class="fw-bold small text-muted py-1"><i class="mdi mdi-arrow-right"></i> Direct Administrations</td></tr>');

        directList.forEach(function(entry) {
            var isPatientOwn = entry.drug_source === 'patient_own';
            var sourceBadge = isPatientOwn
                ? '<span class="badge" style="background:#7b1fa2;">Patient\'s Own</span>'
                : '<span class="badge bg-info">Ward Stock</span>';
            var drugName = entry.product_name || entry.external_drug_name || 'Unknown';

            $body.append(
                '<tr>' +
                    '<td><strong>' + drugName + '</strong><br><small class="text-muted">' + (entry.product_code || '') + '</small></td>' +
                    '<td>-</td>' +
                    '<td><small>' + (entry.nurse_name || '-') + '</small></td>' +
                    '<td><small>' + (entry.last_administered_at ? formatDate(new Date(entry.last_administered_at)) : '-') + '</small></td>' +
                    '<td>' + sourceBadge + '</td>' +
                    '<td><span class="badge bg-secondary">' + (entry.times_administered || 0) + ' given</span>' +
                        '<br><small class="text-muted">Scheduled: ' + (entry.times_scheduled || 0) + '</small></td>' +
                    '<td class="text-center">' +
                        '<button class="btn btn-sm btn-outline-primary rx-select-direct-btn" ' + 'data-drug-source="' + entry.drug_source + '" ' + 'data-product-id="' + (entry.product_id || '') + '" ' + 'data-external-name="' + (entry.external_drug_name || '') + '" ' + 'title="Select in chart"><i class="mdi mdi-pencil-plus"></i></button>' +
                    '</td>' +
                '</tr>'
            );
        });
    }
}

// Filter buttons for prescriptions tab
$(document).on('click', '#rx-filter-group .btn', function() {
    $('#rx-filter-group .btn').removeClass('active');
    $(this).addClass('active');
    rxCurrentFilter = $(this).data('rx-filter') || 'all';

    if (rxTabDataCache) {
        renderPrescriptionsTable(
            rxTabDataCache.prescriptions || [],
            rxTabDataCache.direct_entries || [],
            rxCurrentFilter
        );
    }
});

// Refresh button
$(document).on('click', '#rx-refresh-btn', function() {
    loadPrescriptionsTab();
});

// Select prescription in chart (switch to Entry tab and pick the drug)
$(document).on('click', '.rx-select-btn', function() {
    var posrId = $(this).data('posr-id');
    if (posrId) {
        // Switch to Entry tab
        $('#med-entry-tab').tab('show');
        // Select the drug in the dropdown
        setTimeout(function() {
            $('#drug-select').val(posrId).trigger('change');
        }, 200);
    }
});

// Select direct entry in chart
$(document).on('click', '.rx-select-direct-btn', function() {
    var drugSource = $(this).data('drug-source');
    var productId = $(this).data('product-id');
    var externalName = $(this).data('external-name');

    // Switch to Entry tab
    $('#med-entry-tab').tab('show');

    // Find matching option in dropdown
    setTimeout(function() {
        var matchVal = null;
        $('#drug-select option').each(function() {
            var $opt = $(this);
            var de = $opt.data('direct-entry');
            if (de && de.drug_source === drugSource) {
                if (drugSource === 'ward_stock' && de.product_id == productId) {
                    matchVal = $opt.val();
                    return false;
                }
                if (drugSource === 'patient_own' && de.external_drug_name === externalName) {
                    matchVal = $opt.val();
                    return false;
                }
            }
        });
        if (matchVal) {
            $('#drug-select').val(matchVal).trigger('change');
        }
    }, 200);
});

// Dismiss prescription from prescriptions tab
$(document).on('click', '.rx-dismiss-btn', function() {
    var productRequestId = $(this).data('product-request-id');
    var drugName = $(this).data('drug-name');
    var reason = prompt('Dismiss "' + drugName + '"? Enter reason:');
    if (!reason) return;

    var url = medicationChartDismissRoute.replace(':patient', PATIENT_ID);

    $.ajax({
        url: url,
        type: 'POST',
        data: {
            _token: CSRF_TOKEN,
            product_request_id: productRequestId,
            reason: reason
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Prescription dismissed.');
                loadPrescriptionsTab();
                loadMedicationsList(); // Refresh the dropdown too
            } else {
                toastr.error(response.error || 'Failed to dismiss.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.error || 'Failed to dismiss prescription.');
        }
    });
});

// =============================================
// TAB SWITCH TRIGGERS
// =============================================
$(document).on('shown.bs.tab', '#med-overview-tab', function() {
    loadMedOverview(overviewCurrentStart);
});

$(document).on('shown.bs.tab', '#med-rx-tab', function() {
    loadPrescriptionsTab();
});

// Global variables for I/O chart
let fluidPeriods = [];
let solidPeriods = [];
let currentFluidPeriodId = null;
let currentSolidPeriodId = null;

// Initialize medication chart for a specific patient
function initMedicationChart(patientId) {
    if (!patientId) {
        console.error('No patient ID provided for medication chart');
        return;
    }

    PATIENT_ID = patientId;

    // Update hidden input for patient ID in modals
    $('#schedule_patient_id').val(patientId);
    $('#discontinue_patient_id').val(patientId);
    $('#resume_patient_id').val(patientId);

    // Reset medication state
    selectedMedication = null;
    medications = [];
    medicationStatus = {};
    currentSchedules = [];
    currentAdministrations = [];
    medicationHistory = {};

    // Reset UI
    $('#drug-select').empty().append('<option value="">-- Select a medication --</option>');
    $('#medication-calendar').hide();
    $('#calendar-legend').hide();
    $('#medication-status').empty();
    $('#set-schedule-btn, #discontinue-btn, #resume-btn').prop('disabled', true);

    // Load medications list
    loadMedicationsList();
}

// Initialize I/O chart for a specific patient
function initIntakeOutputChart(patientId) {
    if (!patientId) {
        console.error('No patient ID provided for I/O chart');
        return;
    }

    PATIENT_ID = patientId;

    // Initialize date filters with today - 7 days to today
    const today = new Date();
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);

    $('#fluid_start_date, #solid_start_date').val(weekAgo.toISOString().split('T')[0]);
    $('#fluid_end_date, #solid_end_date').val(today.toISOString().split('T')[0]);

    // Load I/O data
    loadFluidPeriods();
    loadSolidPeriods();
}

// Helper function to get user name
function getUserName(obj) {
    return obj.user_fullname || obj.user_name || obj.administered_by_name ||
        (obj.administeredBy && obj.administeredBy.name) ||
        obj.nurse_name ||
        (obj.nurse && obj.nurse.name) || 'Unknown';
}

// Format date for API calls
function formatDateForApi(date) {
    if (date instanceof Date) {
        return date.toISOString().split('T')[0];
    }
    return date;
}

// Format date for display
function formatDate(date) {
    if (!(date instanceof Date)) date = new Date(date);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Format time for display
function formatTime(date) {
    if (!(date instanceof Date)) date = new Date(date);
    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}

// Format datetime for display
function formatDateTime(date) {
    if (!(date instanceof Date)) date = new Date(date);
    return formatDate(date) + ' ' + formatTime(date);
}

// Get day of week
function getDayOfWeek(date) {
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    return days[date.getDay()];
}

// =============================================
// MEDICATION CHART FUNCTIONS
// =============================================

function loadMedicationsList() {
    if (!PATIENT_ID) {
        console.error('Patient ID is not set');
        return;
    }

    console.log('Loading medications list (enriched §6.1)...');
    $('#medication-loading').show();
    $('#medication-calendar').hide();

    // §6.1: Use prescribed-drugs API for enriched status data
    var prescribedUrl = medicationChartPrescribedRoute.replace(':patient', PATIENT_ID);

    $.ajax({
        url: prescribedUrl,
        type: 'GET',
        success: function(data) {
            console.log('Prescribed drugs loaded:', data);
            $('#medication-loading').hide();

            var prescriptions = data.prescriptions || [];

            // Store all prescriptions for reference
            window._rxLookup = {};
            prescriptions.forEach(function(rx) { window._rxLookup[rx.posr_id || rx.id] = rx; });

            // §6.1: Populate dropdown with rich, status-aware options
            var select = $('#drug-select');
            select.empty();
            select.append('<option value="">-- Select a medication --</option>');

            if (prescriptions.length === 0) {
                toastr.warning('No medications found for this patient.');
            } else {
                console.log('Found ' + prescriptions.length + ' prescriptions');

                prescriptions.forEach(function(rx) {
                    var posrId = rx.posr_id || '';
                    var canChart = rx.can_chart && posrId;

                    // §6.1: Status icon + color
                    var statusIcon, statusBadge;
                    switch (rx.status) {
                        case 3:
                            statusIcon = '🟢';
                            statusBadge = '<span class="badge bg-success">Dispensed</span>';
                            break;
                        case 2:
                            if (rx.is_paid) {
                                statusIcon = '🟡';
                                statusBadge = '<span class="badge bg-warning text-dark">Awaiting Pharmacy</span>';
                            } else {
                                statusIcon = '🟠';
                                statusBadge = '<span class="badge bg-secondary">' + rx.status_label + '</span>';
                            }
                            break;
                        default:
                            statusIcon = '🔴';
                            statusBadge = '<span class="badge bg-danger">Awaiting Billing</span>';
                    }

                    // Administered progress
                    var adminText = rx.is_dispensed
                        ? 'Administered: ' + rx.times_administered + '/' + rx.qty_prescribed
                        : '';

                    // Doctor info
                    var doctorText = rx.doctor_name ? 'Dr. ' + rx.doctor_name : '';

                    // Build display text
                    var plainText = statusIcon + ' ' + rx.product_name + ' (' + rx.product_code + ') — ' + rx.status_label;

                    var opt = new Option(plainText, posrId || ('rx_' + rx.id), false, false);
                    opt.disabled = !canChart;

                    // Store rich data on the option for Select2 templateResult
                    $(opt).data('rx', rx);
                    $(opt).data('status-icon', statusIcon);
                    $(opt).data('status-badge', statusBadge);
                    $(opt).data('admin-text', adminText);
                    $(opt).data('doctor-text', doctorText);
                    $(opt).data('drug-source', 'pharmacy_dispensed');
                    $(opt).data('product-request-id', rx.product_request_id);
                    $(opt).data('product-id', rx.product_id);

                    select.append(opt);

                    // Store medication status for discontinue/resume tracking
                    if (posrId) {
                        medicationStatus[posrId] = {
                            discontinued: false,
                            resumed: false,
                        };
                    }
                });

                // §6.1: Merge direct administration entries (ward stock + patient's own)
                var directEntries = data.direct_entries || [];
                if (directEntries.length> 0) {
                    // Add separator
                    var separator = new Option('── Direct Administrations ──', '', false, false);
                    separator.disabled = true;
                    $(separator).data('is-separator', true);
                    select.append(separator);

                    directEntries.forEach(function(entry) {
                        var isPatientOwn = entry.drug_source === 'patient_own';
                        var icon = isPatientOwn ? '🟣' : '🔵';
                        var label = isPatientOwn ? "Patient's Own" : 'Ward Stock';
                        var drugName = entry.product_name || entry.external_drug_name || 'Unknown';
                        var codeStr = entry.product_code ? ' (' + entry.product_code + ')' : '';
                        var plainText = icon + ' ' + drugName + codeStr + ' — ' + label;

                        var optVal = 'direct_' + entry.drug_source + '_' + (entry.product_id || entry.external_drug_name || entry.id);
                        var opt = new Option(plainText, optVal, false, false);

                        // Store data for Select2 template and calendar loading
                        $(opt).data('direct-entry', entry);
                        $(opt).data('drug-source', entry.drug_source);
                        $(opt).data('product-id', entry.product_id || null);
                        $(opt).data('external-drug-name', entry.external_drug_name || null);
                        $(opt).data('status-icon', icon);
                        $(opt).data('is-direct', true);

                        select.append(opt);
                    });

                    console.log('Added ' + directEntries.length + ' direct administration entries to dropdown');
                }

                // §6.1: Initialize Select2 with rich formatting
                if (select.hasClass('select2-hidden-accessible')) {
                    select.select2('destroy');
                }
                select.select2({
                    width: '100%',
                    placeholder: '-- Select a medication --',
                    allowClear: true,
                    templateResult: formatRxOption,
                    templateSelection: formatRxSelection,
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to load medications:', status, error);
            $('#medication-loading').hide();
            toastr.error('Failed to load medications: ' + error);
        }
    });
}

// Drug selection change
$(document).on('change', '#drug-select', function() {
    const medicationId = $(this).val();

    if (medicationId) {
        selectedMedication = medicationId;

        // Detect if this is a direct entry (ward_stock / patient_own)
        var $selectedOpt = $(this).find('option:selected');
        var isDirect = $selectedOpt.data('is-direct') || false;

        // Enable schedule button; disable discontinue/resume for direct entries
        $('#set-schedule-btn').prop('disabled', false);
        $('#discontinue-btn').prop('disabled', isDirect);
        $('#resume-btn').prop('disabled', isDirect);

        const endDate = new Date(calendarStartDate);
        endDate.setDate(endDate.getDate() + 30);

        const startDateStr = formatDateForApi(calendarStartDate);
        const endDateStr = formatDateForApi(endDate);
        $('#med-start-date').val(startDateStr);
        $('#med-end-date').val(endDateStr);

        loadMedicationCalendarWithDateRange(medicationId, startDateStr, endDateStr);
    } else {
        selectedMedication = null;
        $('#medication-calendar').hide();
        $('#calendar-legend').hide();
        $('#set-schedule-btn, #discontinue-btn, #resume-btn').prop('disabled', true);
        $('#medication-status').empty();
    }
});

// Calendar navigation buttons
$(document).on('click', '#prev-month-btn', function() {
    if (selectedMedication) {
        calendarStartDate.setDate(calendarStartDate.getDate() - 30);
        const endDate = new Date(calendarStartDate);
        endDate.setDate(endDate.getDate() + 30);

        const startDateStr = formatDateForApi(calendarStartDate);
        const endDateStr = formatDateForApi(endDate);
        $('#med-start-date').val(startDateStr);
        $('#med-end-date').val(endDateStr);

        loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
    }
});

$(document).on('click', '#next-month-btn', function() {
    if (selectedMedication) {
        calendarStartDate.setDate(calendarStartDate.getDate() + 30);
        const endDate = new Date(calendarStartDate);
        endDate.setDate(endDate.getDate() + 30);

        const startDateStr = formatDateForApi(calendarStartDate);
        const endDateStr = formatDateForApi(endDate);
        $('#med-start-date').val(startDateStr);
        $('#med-end-date').val(endDateStr);

        loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
    }
});

$(document).on('click', '#today-btn', function() {
    if (selectedMedication) {
        calendarStartDate = new Date();
        calendarStartDate.setDate(calendarStartDate.getDate() - 15);

        const endDate = new Date();
        endDate.setDate(endDate.getDate() + 15);

        const startDateStr = formatDateForApi(calendarStartDate);
        const endDateStr = formatDateForApi(endDate);
        $('#med-start-date').val(startDateStr);
        $('#med-end-date').val(endDateStr);

        loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
    }
});

$(document).on('click', '#apply-date-range-btn', function() {
    if (!selectedMedication) {
        toastr.warning('Please select a medication first.');
        return;
    }

    const startDateStr = $('#med-start-date').val();
    const endDateStr = $('#med-end-date').val();

    if (!startDateStr || !endDateStr) {
        toastr.warning('Please select both start and end dates.');
        return;
    }

    if (new Date(startDateStr)> new Date(endDateStr)) {
        toastr.warning('Start date cannot be after end date.');
        return;
    }

    calendarStartDate = new Date(startDateStr);
    loadMedicationCalendarWithDateRange(selectedMedication, startDateStr, endDateStr);
});

function loadMedicationCalendarWithDateRange(medicationId, startDate, endDate) {
    if (!medicationId || !PATIENT_ID) return;

    $('#medication-loading').show();
    $('#medication-calendar').hide();

    // Determine if this is a direct entry by checking the selected option
    var $selectedOpt = $('#drug-select').find('option[value="' + medicationId + '"]');
    var isDirect = $selectedOpt.data('is-direct') || false;
    var url;

    if (isDirect) {
        // Direct entry — use directCalendar endpoint with query params
        var drugSource = $selectedOpt.data('drug-source');
        var productId = $selectedOpt.data('product-id');
        var externalDrugName = $selectedOpt.data('external-drug-name');

        url = medicationChartDirectCalendarRoute.replace(':patient', PATIENT_ID);
        var queryParams = {
            drug_source: drugSource,
            start_date: startDate,
            end_date: endDate
        };
        if (productId) queryParams.product_id = productId;
        if (externalDrugName) queryParams.external_drug_name = externalDrugName;

        $.ajax({
            url: url,
            type: 'GET',
            data: queryParams,
            success: function(data) {
                $('#medication-loading').hide();
                handleCalendarResponse(data, medicationId);
            },
            error: function() {
                $('#medication-loading').hide();
                toastr.error('Failed to load medication calendar.');
            }
        });
    } else {
        // Standard POSR — use calendar route
        url = medicationChartCalendarRoute
            .replace(':patient', PATIENT_ID)
            .replace(':medication', medicationId)
            .replace(':start_date', startDate);

        $.ajax({
            url: url,
            type: 'GET',
            data: { start_date: startDate, end_date: endDate },
            success: function(data) {
                $('#medication-loading').hide();
                handleCalendarResponse(data, medicationId);
            },
            error: function() {
                $('#medication-loading').hide();
                toastr.error('Failed to load medication calendar.');
            }
        });
    }
}

// Shared handler for calendar response (works for both POSR and direct entries)
function handleCalendarResponse(data, medicationId) {
    if (data.medication) {
        const medication = data.medication;
        currentSchedules = data.schedules || [];
        currentAdministrations = data.administrations || [];

        // Store total counts from server (all-time, not just date range)
        window._currentMedCounts = {
            totalScheduled: data.total_scheduled || currentSchedules.length,
            totalAdministered: data.total_administered || currentAdministrations.filter(a => a.administered_at).length,
            rangeScheduled: currentSchedules.length,
            rangeAdministered: currentAdministrations.filter(a => a.administered_at).length
        };

        updateMedicationStatus(medication);
        updateMedicationButtons(medication);

        // Store history
        let logEntries = [];
        if (data.history && Array.isArray(data.history)) {
            logEntries = [...data.history];
        }
        if (data.adminHistory && Array.isArray(data.adminHistory)) {
            data.adminHistory.forEach(admin => {
                logEntries.push({
                    date: admin.administered_at,
                    action: 'administration',
                    details: `${admin.dose} ${admin.route} ${admin.comment ? '- ' + admin.comment : ''}`,
                    user: admin.administered_by_name || getUserName(admin) || 'Unknown',
                    id: admin.id
                });
            });
        }
        medicationHistory[selectedMedication] = logEntries;

        renderCalendarView(medication, currentSchedules, currentAdministrations, data.period);
        renderLegend();
        $('#medication-calendar').show();
        $('#calendar-legend').show();
    }
}

function updateMedicationStatus(medication) {
    let statusHtml = '';
    var counts = window._currentMedCounts || { totalScheduled: 0, totalAdministered: 0 };
    var countsHtml = '<span class="badge bg-light text-dark border me-1"><i class="mdi mdi-calendar-clock"></i> ' + counts.totalScheduled + ' scheduled</span>' +
                     '<span class="badge bg-light text-dark border"><i class="mdi mdi-check-circle"></i> ' + counts.totalAdministered + ' administered</span>';

    // Direct entries have product_name at top level; POSR entries have medication.product.product_name
    var productName = '';
    if (medication.is_direct_entry) {
        productName = medication.product_name || 'Direct Entry';
        var sourceLabel = medication.drug_source === 'patient_own' ? "Patient's Own" : 'Ward Stock';
        var sourceBadge = medication.drug_source === 'patient_own' ? 'bg-purple' : 'bg-info';
        statusHtml = `
            <div class="alert alert-info py-2 mb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <i class="mdi mdi-pill me-2"></i>
                        <strong>${productName}</strong>: <span class="badge ${sourceBadge}">${sourceLabel}</span>
                    </div>
                    <div>${countsHtml}</div>
                </div>
            </div>`;
    } else if (medication.product && medication.product.product_name) {
        productName = medication.product.product_name;

        if (medication.discontinued_at && !medication.resumed_at) {
            statusHtml = `
                <div class="alert alert-danger py-2 mb-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <i class="mdi mdi-calendar-remove me-2"></i>
                            <strong>${productName}</strong>: Discontinued
                            <div class="small">Reason: ${medication.discontinued_reason || 'N/A'}</div>
                        </div>
                        <div>${countsHtml}</div>
                    </div>
                </div>`;
        } else {
            statusHtml = `
                <div class="alert alert-success py-2 mb-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <i class="mdi mdi-check-circle me-2"></i>
                            <strong>${productName}</strong>: <span class="badge bg-success">Active</span>
                        </div>
                        <div>${countsHtml}</div>
                    </div>
                </div>`;
        }
    }

    $('#medication-status').html(statusHtml);
}

function updateMedicationButtons(medication) {
    // Direct entries don't support discontinue/resume
    if (medication.is_direct_entry) {
        $('#discontinue-btn').prop('disabled', true);
        $('#resume-btn').prop('disabled', true);
        $('#set-schedule-btn').prop('disabled', false);
        $('#view-logs-btn').prop('disabled', false);
        return;
    }

    const isDiscontinued = !!medication.discontinued_at;
    const isResumed = !!medication.resumed_at;
    const effectivelyDiscontinued = isDiscontinued && !isResumed;

    $('#discontinue-btn').prop('disabled', effectivelyDiscontinued);
    $('#resume-btn').prop('disabled', !effectivelyDiscontinued);
    $('#set-schedule-btn').prop('disabled', effectivelyDiscontinued);
    $('#view-logs-btn').prop('disabled', false);
}

// View logs button handler
$(document).on('click', '#view-logs-btn', function() {
    if (!selectedMedication) return;

    const logs = medicationHistory[selectedMedication] || [];
    let logsHtml = '';

    if (logs.length === 0) {
        logsHtml = '<div class="alert alert-info">No activity logs available for this medication.</div>';
    } else {
        logsHtml = '<div class="table-responsive"><table class="table table-sm table-striped">';
        logsHtml += '<thead><tr><th>Date</th><th>Action</th><th>Details</th><th>User</th></tr></thead><tbody>';

        logs.forEach(log => {
            const logDate = new Date(log.date);
            let actionBadge = 'bg-primary';
            let actionText = log.action || 'N/A';

            switch((log.action || '').toLowerCase()) {
                case 'administration': actionBadge = 'bg-success'; actionText = 'Administered'; break;
                case 'edit': actionBadge = 'bg-info'; actionText = 'Edited'; break;
                case 'delete': actionBadge = 'bg-dark'; actionText = 'Deleted'; break;
                case 'discontinue': actionBadge = 'bg-warning'; actionText = 'Discontinued'; break;
                case 'resume': actionBadge = 'bg-success'; actionText = 'Resumed'; break;
            }

            logsHtml += `<tr>
                <td><small>${formatDateTime(logDate)}</small></td>
                <td><span class="badge ${actionBadge}">${actionText}</span></td>
                <td>${log.details || log.reason || '-'}</td>
                <td><small>${log.user || 'Unknown'}</small></td>
            </tr>`;
        });

        logsHtml += '</tbody></table></div>';
    }

    // Get medication name from dropdown option data (works for both POSR and direct entries)
    let medicationName = 'Medication';
    const $logsSelectedOpt = $('#drug-select').find('option:selected');
    const logsDirectEntry = $logsSelectedOpt.data('direct-entry');
    if (logsDirectEntry) {
        medicationName = logsDirectEntry.product_name || logsDirectEntry.external_drug_name || 'Direct Entry';
    } else {
        const logsRx = $logsSelectedOpt.data('rx');
        if (logsRx) {
            medicationName = logsRx.product_name || 'Medication';
        }
    }

    $('#medication-logs-title').text('Activity Logs: ' + medicationName);
    $('#medication-logs-content').html(logsHtml);
    $('#medicationLogsModal').modal('show');
});

function renderLegend() {
    const legendHtml = `
        <div class="card-modern shadow-sm mb-3">
            <div class="card-body p-2">
                <h6 class="card-title mb-2"><i class="mdi mdi-information-outline text-primary me-1"></i> Legend</h6>
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-primary rounded-pill"><i class="mdi mdi-calendar-clock"></i> Scheduled</span>
                    <span class="badge bg-success rounded-pill"><i class="mdi mdi-check"></i> Administered</span>
                    <span class="badge bg-info rounded-pill"><i class="mdi mdi-pencil"></i> Edited</span>
                    <span class="badge bg-dark rounded-pill"><i class="mdi mdi-close"></i> Deleted</span>
                    <span class="badge bg-danger rounded-pill"><i class="mdi mdi-calendar-remove"></i> Missed</span>
                    <span class="badge bg-secondary rounded-pill"><i class="mdi mdi-calendar"></i> Discontinued</span>
                </div>
            </div>
        </div>`;

    $('#calendar-legend').html(legendHtml);
}

function renderCalendarView(medication, schedules, administrations, period) {
    const startDate = new Date(period.start);
    const endDate = new Date(period.end);
    const product = medication.product || {};
    // Direct entries have product_name at top level; POSR entries have it under .product
    const productName = medication.product_name || product.product_name || 'Medication';

    let doctorDose = medication.dose || '';
    let doctorInfoHtml = '';
    if (doctorDose) {
        doctorInfoHtml = `<div class="my-2"><span class="badge bg-warning text-dark fw-bold">Doctor's Order: ${doctorDose}</span></div>`;
    }

    const dateRange = `${formatDate(startDate)} to ${formatDate(endDate)}`;

    $('#calendar-title').html(`
        <div class="mb-1"><span class="text-primary fw-bold">${productName}</span></div>
        ${doctorInfoHtml}
        <div class="small text-muted"><i class="mdi mdi-calendar-range"></i> ${dateRange}</div>
    `);

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const days = [];
    for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
        days.push(new Date(d));
    }

    // Build weekday header
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    let headerHtml = '<div class="calendar-weekday-header">';
    weekdays.forEach(day => {
        headerHtml += `<div class="weekday-name">${day}</div>`;
    });
    headerHtml += '</div>';

    // Build calendar grid
    let gridHtml = '<div class="medication-calendar-grid">';

    // Add empty cells for padding to align first day with correct weekday
    const firstDayOfWeek = days.length> 0 ? days[0].getDay() : 0;
    for (let i = 0; i < firstDayOfWeek; i++) {
        gridHtml += '<div class="calendar-day-cell empty-day"></div>';
    }

    // Add day cells
    days.forEach((day) => {
        const isToday = day.toDateString() === today.toDateString();
        const isWeekend = day.getDay() === 0 || day.getDay() === 6;
        const isPast = day < today;

        let cellClasses = 'calendar-day-cell';
        if (isToday) cellClasses += ' today';
        if (isWeekend) cellClasses += ' weekend';
        if (isPast && !isToday) cellClasses += ' past-date';

        gridHtml += `<div class="${cellClasses}" data-date="${formatDateForApi(day)}">`;
        gridHtml += `<div class="calendar-day-header">`;
        gridHtml += `<span>${getDayOfWeek(day)}</span>`;
        gridHtml += `<span class="calendar-day-date">${day.getDate()}</span>`;
        gridHtml += `</div>`;
        gridHtml += `<div class="calendar-schedules">`;

        const daySchedules = schedules.filter(s => {
            const scheduleDate = new Date(s.scheduled_time);
            return scheduleDate.toDateString() === day.toDateString();
        });

        // Find unscheduled (direct) administrations for this day
        const dayUnscheduledAdmins = administrations.filter(a => {
            if (!a.administered_at || a.schedule_id) return false;
            const adminDate = new Date(a.administered_at);
            return adminDate.toDateString() === day.toDateString();
        });

        if (daySchedules.length === 0 && dayUnscheduledAdmins.length === 0) {
            gridHtml += `<span class="text-muted small fst-italic">No activity</span>`;
        } else {
            // Render scheduled items
            daySchedules.forEach(schedule => {
                const scheduleTime = new Date(schedule.scheduled_time);
                const formattedTime = formatTime(scheduleTime);
                const admin = administrations.find(a => a.schedule_id === schedule.id);

                // Detect if schedule is for a direct entry (ward_stock / patient_own)
                const isDirectSchedule = schedule.drug_source && schedule.drug_source !== 'pharmacy_dispensed';

                let badgeClass = 'bg-primary';
                let badgeContent = `<i class="mdi mdi-calendar-clock"></i> ${formattedTime}`;
                // All schedule slots open the administer modal (handler routes to correct endpoint)
                let adminAction = `data-bs-target="#administerModal" data-schedule-id="${schedule.id}"`;
                let tooltipContent = `Dose: ${schedule.dose}<br>Route: ${schedule.route}<br>Status: Scheduled`;
                if (isDirectSchedule) {
                    tooltipContent += `<br><em>${schedule.drug_source === 'ward_stock' ? 'Ward Stock' : "Patient's Own"}</em>`;
                }

                const isDiscontinued = medication.discontinued_at &&
                    new Date(medication.discontinued_at) < scheduleTime &&
                    (!medication.resumed_at || new Date(medication.resumed_at)> scheduleTime);

                if (isDiscontinued) {
                    badgeClass = 'bg-secondary';
                    adminAction = '';
                    tooltipContent = `Dose: ${schedule.dose}<br>Route: ${schedule.route}<br>Status: Discontinued`;
                } else if (admin) {
                    badgeClass = 'bg-success';
                    badgeContent = `<i class="mdi mdi-check"></i> ${formattedTime}`;
                    adminAction = `data-bs-target="#adminDetailsModal" data-admin-id="${admin.id}"`;
                    tooltipContent = `Dose: ${admin.dose}<br>Route: ${admin.route}<br>Status: Administered`;

                    if (admin.edited_at) {
                        badgeClass = 'bg-info';
                        badgeContent = `<i class="mdi mdi-pencil"></i> ${formattedTime}`;
                    }
                    if (admin.deleted_at) {
                        badgeClass = 'bg-dark';
                        badgeContent = `<i class="mdi mdi-close"></i> ${formattedTime}`;
                        adminAction = '';
                    }
                } else {
                    const now = new Date();
                    if (scheduleTime < now) {
                        badgeClass = 'bg-danger';
                        badgeContent = `<i class="mdi mdi-alert"></i> ${formattedTime}`;
                        tooltipContent = `Dose: ${schedule.dose}<br>Route: ${schedule.route}<br>Status: Missed`;
                    }
                }

                let removeBtn = '';
                if (!admin && !isDiscontinued) {
                    removeBtn = `<button class='btn btn-sm btn-outline-danger remove-schedule-btn' data-schedule-id='${schedule.id}' title='Remove schedule'><i class='mdi mdi-trash-can-outline'></i></button>`;
                }

                gridHtml += `<div class="calendar-schedule-item">`;
                gridHtml += `<span class="schedule-slot badge ${badgeClass}" ${adminAction} data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="${tooltipContent}">${badgeContent}</span>`;
                gridHtml += removeBtn;
                gridHtml += `</div>`;
            });

            // Render unscheduled (direct) administrations — these come from ward stock / patient's own buttons
            dayUnscheduledAdmins.forEach(admin => {
                const adminTime = new Date(admin.administered_at);
                const formattedTime = formatTime(adminTime);
                let badgeClass = 'bg-success';
                let badgeContent = `<i class="mdi mdi-check"></i> ${formattedTime}`;
                let adminAction = `data-bs-target="#adminDetailsModal" data-admin-id="${admin.id}"`;
                let tooltipContent = `Dose: ${admin.dose || 'N/A'}<br>Route: ${admin.route || 'N/A'}<br>Direct Administration`;

                if (admin.edited_at) {
                    badgeClass = 'bg-info';
                    badgeContent = `<i class="mdi mdi-pencil"></i> ${formattedTime}`;
                }
                if (admin.deleted_at) {
                    badgeClass = 'bg-dark';
                    badgeContent = `<i class="mdi mdi-close"></i> ${formattedTime}`;
                    adminAction = '';
                }

                gridHtml += `<div class="calendar-schedule-item">`;
                gridHtml += `<span class="schedule-slot badge ${badgeClass}" ${adminAction} data-bs-toggle="tooltip" data-bs-html="true" data-bs-title="${tooltipContent}">${badgeContent}</span>`;
                gridHtml += `</div>`;
            });
        }

        gridHtml += `</div></div>`;
    });

    // Add trailing empty cells to complete the last week row
    const lastDayOfWeek = days.length> 0 ? days[days.length - 1].getDay() : 6;
    for (let i = lastDayOfWeek + 1; i < 7; i++) {
        gridHtml += '<div class="calendar-day-cell empty-day"></div>';
    }

    gridHtml += '</div>';

    // Output to container
    $('#calendar-container').html(headerHtml + gridHtml);

    // Initialize tooltips
    $('.schedule-slot[data-bs-toggle="tooltip"]').tooltip('dispose');
    $('.schedule-slot[data-bs-toggle="tooltip"]').tooltip({
        placement: 'top',
        trigger: 'hover',
        container: 'body',
        html: true
    });
}

// Remove schedule handler
$(document).off('click', '.remove-schedule-btn').on('click', '.remove-schedule-btn', function(e) {
    e.preventDefault();
    const scheduleId = $(this).data('schedule-id');
    if (!scheduleId || !confirm('Remove this schedule entry?')) return;

    const btn = $(this);
    btn.prop('disabled', true);

    $.ajax({
        url: medicationChartRemoveScheduleRoute,
        type: 'POST',
        data: { schedule_id: scheduleId, _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Schedule removed.');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to remove schedule.');
                btn.prop('disabled', false);
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to remove schedule.');
            btn.prop('disabled', false);
        }
    });
});

// Set schedule button
$(document).on('click', '#set-schedule-btn', function() {
    if (!selectedMedication) return;

    // Detect if this is a direct entry
    var $selectedOpt = $('#drug-select').find('option:selected');
    var isDirect = $selectedOpt.data('is-direct') || false;

    if (isDirect) {
        // Direct entry: populate drug_source, product_id/external_drug_name
        var drugSource = $selectedOpt.data('drug-source') || '';
        var productId = $selectedOpt.data('product-id') || '';
        var externalDrugName = $selectedOpt.data('external-drug-name') || '';

        $('#schedule_medication_id').val(''); // No POSR for direct entries
        $('#schedule_drug_source').val(drugSource);
        $('#schedule_product_id').val(productId);
        $('#schedule_external_drug_name').val(externalDrugName);
    } else {
        // Standard POSR medication
        $('#schedule_medication_id').val(selectedMedication);
        $('#schedule_drug_source').val('pharmacy_dispensed');
        $('#schedule_product_id').val('');
        $('#schedule_external_drug_name').val('');
    }

    $('#schedule_date').val(new Date().toISOString().split('T')[0]);
    $('#setScheduleModal').modal('show');
});

// Set schedule form submit
$(document).on('submit', '#setScheduleForm', function(e) {
    e.preventDefault();

    const formData = $(this).serialize();

    $.ajax({
        url: medicationChartScheduleRoute,
        type: 'POST',
        data: formData + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Schedule created successfully.');
                $('#setScheduleModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to create schedule.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to create schedule.');
        }
    });
});

// Discontinue button
$(document).on('click', '#discontinue-btn', function() {
    if (!selectedMedication) return;
    $('#discontinue_medication_id').val(selectedMedication);
    const discRx = $('#drug-select').find('option:selected').data('rx');
    const discName = discRx?.product_name || 'Medication';
    $('#discontinue-medication-name').text(discName);
    $('#discontinueModal').modal('show');
});

// Discontinue form submit
$(document).on('submit', '#discontinueForm', function(e) {
    e.preventDefault();

    const btn = $('#discontinueSubmitBtn');
    btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

    $.ajax({
        url: medicationChartDiscontinueRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Medication discontinued.');
                $('#discontinueModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to discontinue.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to discontinue.');
        },
        complete: function() {
            btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
        }
    });
});

// Resume button
$(document).on('click', '#resume-btn', function() {
    if (!selectedMedication) return;
    $('#resume_medication_id').val(selectedMedication);
    const resRx = $('#drug-select').find('option:selected').data('rx');
    const resName = resRx?.product_name || 'Medication';
    $('#resume-medication-name').text(resName);
    $('#resumeModal').modal('show');
});

// Resume form submit
$(document).on('submit', '#resumeForm', function(e) {
    e.preventDefault();

    const btn = $('#resumeSubmitBtn');
    btn.prop('disabled', true).find('.spinner-border').removeClass('d-none');

    $.ajax({
        url: medicationChartResumeRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Medication resumed.');
                $('#resumeModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to resume.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to resume.');
        },
        complete: function() {
            btn.prop('disabled', false).find('.spinner-border').addClass('d-none');
        }
    });
});

// Store stock data cache for medication administration
var administerStockData = null;

// Administer modal population — §6.5: enriched with _rxLookup data
$(document).on('click', '[data-bs-target="#administerModal"]', function() {
    const scheduleId = $(this).data('schedule-id');
    const schedule = currentSchedules.find(s => s.id == scheduleId);

    if (schedule) {
        $('#administer_schedule_id').val(scheduleId);

        // Determine drug source from schedule
        var scheduleDrugSource = schedule.drug_source || 'pharmacy_dispensed';
        var isDirect = (scheduleDrugSource === 'ward_stock' || scheduleDrugSource === 'patient_own');
        var productId = '';

        // Reset all conditional sections
        $('#administer-ward-stock-section').addClass('d-none');
        $('#administer-patient-own-section').addClass('d-none');
        $('#administer-pharmacy-qty-section').addClass('d-none');
        $('#administer-stock-info').addClass('d-none');
        $('#administer-stock-warning').addClass('d-none');
        administerStockData = null;

        if (isDirect) {
            // Direct entry schedule: use schedule data directly
            productId = schedule.product_id || '';
            var drugName = schedule.external_drug_name || 'Direct Entry';

            // Try to get product name from the selected dropdown option
            var $selectedOpt = $('#drug-select').find('option:selected');
            var directEntry = $selectedOpt.data('direct-entry');
            if (directEntry) {
                drugName = directEntry.product_name || directEntry.external_drug_name || drugName;
            }

            $('#administer_product_id').val(productId);
            $('#administer_product_request_id').val('');
            $('#administer-medication-info').html('<i class="mdi mdi-pill"></i> ' + drugName);
            setDrugSource(scheduleDrugSource);

            // Set the external_drug_name hidden field for patient_own
            $('#administer_external_drug_name').val(schedule.external_drug_name || '');

            // Update source badge
            if (scheduleDrugSource === 'ward_stock') {
                $('#administer-source-badge').html(
                    '<span class="badge bg-warning text-dark"><i class="mdi mdi-hospital-box"></i> Ward Stock</span>' +
                    '<small class="text-muted ms-2">Stock will be deducted from selected store</small>'
                );
                // Show ward stock section + load stores
                $('#administer-ward-stock-section').removeClass('d-none');
                loadAdministerStores(productId);
            } else {
                $('#administer-source-badge').html(
                    '<span class="badge bg-info"><i class="mdi mdi-account-heart"></i> Patient\'s Own</span>' +
                    '<small class="text-muted ms-2">Patient-supplied medication</small>'
                );
                // Show patient's own section
                $('#administer-patient-own-section').removeClass('d-none');
            }
        } else {
            // §6.5: Pharmacy dispensed — use enriched _rxLookup
            var posrId = selectedMedication;
            var rx = window._rxLookup ? window._rxLookup[posrId] : null;

            if (rx) {
                productId = rx.product_id || '';
                var productName = rx.product_name || 'N/A';
                $('#administer_product_id').val(productId);
                $('#administer_product_request_id').val(rx.product_request_id || '');
                $('#administer-medication-info').html('<i class="mdi mdi-pill"></i> ' + productName);
            } else {
                var fallbackRx = window._rxLookup ? window._rxLookup[selectedMedication] : null;
                productId = fallbackRx ? (fallbackRx.product_id || '') : '';
                var productName = fallbackRx ? (fallbackRx.product_name || 'N/A') : 'N/A';
                $('#administer_product_id').val(productId);
                $('#administer-medication-info').html('<i class="mdi mdi-pill"></i> ' + productName);
            }

            setDrugSource('pharmacy_dispensed');
            $('#administer_external_drug_name').val('');

            // Restore pharmacy dispensed badge
            $('#administer-source-badge').html(
                '<span class="badge bg-success"><i class="mdi mdi-pill"></i> Pharmacy Dispensed</span>' +
                '<small class="text-muted ms-2">Source is determined by the selected medication</small>'
            );

            // Show pharmacy qty section and populate remaining info
            $('#administer-pharmacy-qty-section').removeClass('d-none');
            $('#administer_pharmacy_qty').val(1);
            if (rx) {
                var qtyAdmin = rx.qty_administered || 0;
                var remaining = rx.remaining_doses || 0;
                $('#administer-remaining-info').html(
                    '<i class="mdi mdi-pill"></i> Prescribed: <strong>' + (rx.qty_prescribed || 0) + '</strong>' +
                    ' &nbsp;|&nbsp; Administered: <strong>' + qtyAdmin + '</strong>' +
                    ' &nbsp;|&nbsp; Remaining: <strong class="' + (remaining <= 0 ? 'text-danger' : 'text-success') + '">' + remaining + '</strong>'
                );
            } else {
                $('#administer-remaining-info').html('');
            }
        }

        const scheduledTime = new Date(schedule.scheduled_time);
        $('#administer-scheduled-time').html('<i class="mdi mdi-clock-outline"></i> Scheduled: ' + formatDateTime(scheduledTime));
        $('#administered_at').val(new Date().toISOString().slice(0, 16));
        $('#administered_dose').val(schedule.dose);
        $('#administered_route').val(schedule.route);
        $('#administered_note').val('');
        $('#administer_store_id').val('');
        $('#administer_qty').val(1);
        $('#administer_external_qty').val(1);

        // Show scheduled/administered counts
        var counts = window._currentMedCounts || { totalScheduled: 0, totalAdministered: 0 };
        $('#administer-counts-info').html(
            '<i class="mdi mdi-calendar-clock"></i> ' + counts.totalScheduled + ' scheduled &nbsp;|&nbsp; ' +
            '<i class="mdi mdi-check-circle"></i> ' + counts.totalAdministered + ' administered'
        );

        // Explicitly show modal (data-bs-toggle is used for tooltip, not modal)
        $('#administerModal').modal('show');
    }
});

// Load stores into the administer modal's ward stock store select
// Uses the session-resolved store — the same one displayed at the workbench banner.
function loadAdministerStores(productId) {
    @if($resolvedStore)
    var $select = $('#administer_store_id');
    $select.empty();
    $select.append('<option value="{{ $resolvedStore->id }}" selected>{{ $resolvedStore->store_name }}</option>');
    $select.trigger('change');
    @endif
}

// Store selection change in administer modal - show stock for selected store
$(document).on('change', '#administer_store_id', function() {
    const storeId = $(this).val();
    const productId = $('#administer_product_id').val();
    const $stockInfo = $('#administer-stock-info');
    const $stockQty = $('#administer-stock-qty');
    const $stockWarning = $('#administer-stock-warning');

    if (!storeId) {
        $stockInfo.addClass('d-none');
        $stockWarning.addClass('d-none');
        return;
    }

    $stockInfo.removeClass('d-none');
    $stockQty.removeClass('bg-success bg-warning bg-danger').addClass('bg-secondary').text('Loading...');

    // Fetch stock for this product/store combination
    if (productId && storeId) {
        $.ajax({
            url: '{{ url('/nursing-workbench/product-batches') }}',
            method: 'GET',
            data: { product_id: productId, store_id: storeId },
            success: function(response) {
                if (response.success) {
                    const totalAvailable = response.total_available || 0;
                    $stockQty.text(totalAvailable + ' units');
                    if (totalAvailable <= 0) {
                        $stockQty.removeClass('bg-secondary bg-success bg-warning').addClass('bg-danger');
                        $stockWarning.removeClass('d-none');
                        $('#administer-stock-warning-text').text('No stock available in this store!');
                    } else if (totalAvailable < 5) {
                        $stockQty.removeClass('bg-secondary bg-success bg-danger').addClass('bg-warning');
                        $stockWarning.addClass('d-none');
                    } else {
                        $stockQty.removeClass('bg-secondary bg-warning bg-danger').addClass('bg-success');
                        $stockWarning.addClass('d-none');
                    }
                    administerStockData = { stores: [{ store_id: storeId, quantity: totalAvailable }] };
                } else {
                    $stockQty.text('0 units').removeClass('bg-secondary bg-success bg-warning').addClass('bg-danger');
                    $stockWarning.removeClass('d-none');
                    $('#administer-stock-warning-text').text('Failed to check stock');
                }
            },
            error: function() {
                $stockQty.text('Error').removeClass('bg-secondary bg-success bg-warning').addClass('bg-danger');
                $stockWarning.removeClass('d-none');
                $('#administer-stock-warning-text').text('Failed to check stock availability');
            }
        });
    }
});

// Administer form submit — routes to correct endpoint based on drug source
$(document).on('submit', '#administerForm', function(e) {
    e.preventDefault();

    const drugSource = $('#administer_drug_source').val() || 'pharmacy_dispensed';
    const isDirect = (drugSource === 'ward_stock' || drugSource === 'patient_own');

    // ── Direct entry (ward_stock / patient_own): submit to administerDirect ──
    if (isDirect) {
        // Validate required fields per source
        if (drugSource === 'ward_stock') {
            var storeId = $('#administer_store_id').val();
            if (!storeId) {
                toastr.error('Please select a dispensing store');
                $('#administer_store_id').focus();
                return;
            }
            var qty = parseInt($('#administer_qty').val()) || 0;
            if (qty < 1) {
                toastr.error('Quantity must be at least 1');
                $('#administer_qty').focus();
                return;
            }
        } else if (drugSource === 'patient_own') {
            var extQty = parseFloat($('#administer_external_qty').val()) || 0;
            if (extQty <= 0) {
                toastr.error('Quantity must be greater than 0');
                $('#administer_external_qty').focus();
                return;
            }
        }

        var $btn = $('#administerSubmitBtn');
        var originalBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Administering...');

        var directUrl = medicationChartAdministerDirectRoute.replace(':patient', PATIENT_ID);

        // Build form data — collect what administerDirect expects
        var formData = {
            _token: CSRF_TOKEN,
            drug_source: drugSource,
            schedule_id: $('#administer_schedule_id').val(),
            administered_at: $('#administered_at').val(),
            administered_dose: $('#administered_dose').val(),
            route: $('#administered_route').val(),
            note: $('#administered_note').val() || ''
        };

        if (drugSource === 'ward_stock') {
            formData.product_id = $('#administer_product_id').val();
            formData.store_id = $('#administer_store_id').val();
            formData.qty = $('#administer_qty').val();
        } else {
            formData.external_drug_name = $('#administer_external_drug_name').val();
            formData.external_qty = $('#administer_external_qty').val();
        }

        $.ajax({
            url: directUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                if (response.success) {
                    toastr.success(response.message || 'Medication administered successfully.');
                    try { bootstrap.Modal.getOrCreateInstance('#administerModal').hide(); } catch(e) {}
                    if (selectedMedication) {
                        loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                    }
                } else {
                    toastr.error(response.message || 'Failed to administer.');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                var msg = xhr.responseJSON?.message || 'Failed to administer.';
                if (xhr.responseJSON?.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                }
                toastr.error(msg);
            }
        });
        return; // Don't fall through to pharmacy_dispensed flow
    }

    // ── Pharmacy dispensed: standard flow (no store needed — stock from dispensed POSR) ──
    var $btn = $('#administerSubmitBtn');
    var originalBtnHtml = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Administering...');

    // Validate pharmacy qty
    var pharmacyQty = parseFloat($('#administer_pharmacy_qty').val()) || 0;
    if (pharmacyQty <= 0) {
        toastr.error('Quantity must be greater than 0');
        $btn.prop('disabled', false).html(originalBtnHtml);
        $('#administer_pharmacy_qty').focus();
        return;
    }

    $.ajax({
        url: medicationChartAdministerRoute,
        type: 'POST',
        data: $('#administerForm').serialize() + '&qty=' + pharmacyQty + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            if (response.success) {
                toastr.success('Medication administered successfully.');
                try { bootstrap.Modal.getOrCreateInstance('#administerModal').hide(); } catch(e) {}
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to administer.');
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            var msg = xhr.responseJSON?.message || 'Failed to administer.';
            if (xhr.responseJSON?.errors) {
                msg = Object.values(xhr.responseJSON.errors).flat().join(', ');
            }
            toastr.error(msg);
        }
    });
});

// Administration Details Modal - show details when clicking administered badge
$(document).on('click', '[data-bs-target="#adminDetailsModal"]', function() {
    const adminId = $(this).data('admin-id');
    const admin = currentAdministrations.find(a => a.id == adminId);

    if (admin) {
        // Get medication name from dropdown option data (works for both POSR and direct entries)
        let productName = 'Medication';
        const $detailsSelectedOpt = $('#drug-select').find('option:selected');
        const detailsDirectEntry = $detailsSelectedOpt.data('direct-entry');
        if (detailsDirectEntry) {
            productName = detailsDirectEntry.product_name || detailsDirectEntry.external_drug_name || 'Direct Entry';
        } else {
            const detailsRx = $detailsSelectedOpt.data('rx');
            if (detailsRx) {
                productName = detailsRx.product_name || 'Medication';
            }
        }
        const adminTime = new Date(admin.administered_at);

        let detailsHtml = `
            <div class="mb-3">
                <h6 class="text-primary fw-bold"><i class="mdi mdi-pill"></i> ${productName}</h6>
                <small class="text-muted">
                    <i class="mdi mdi-calendar-clock"></i> ${(window._currentMedCounts || {}).totalScheduled || 0} scheduled
                    &nbsp;|&nbsp;
                    <i class="mdi mdi-check-circle"></i> ${(window._currentMedCounts || {}).totalAdministered || 0} administered
                </small>
            </div>
            <table class="table table-sm table-borderless">
                <tr>
                    <td class="text-muted" width="40%"><i class="mdi mdi-clock-outline"></i> Administered At:</td>
                    <td class="fw-bold">${formatDateTime(adminTime)}</td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="mdi mdi-medical-bag"></i> Dose:</td>
                    <td class="fw-bold">${admin.dose || '-'}</td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="mdi mdi-pill"></i> Qty:</td>
                    <td class="fw-bold">${admin.qty || admin.external_qty || 1}</td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="mdi mdi-routes"></i> Route:</td>
                    <td class="fw-bold">${admin.route || '-'}</td>
                </tr>
                <tr>
                    <td class="text-muted"><i class="mdi mdi-account"></i> Administered By:</td>
                    <td class="fw-bold">${admin.administered_by_name || admin.administeredBy?.name || 'Unknown'}</td>
                </tr>`;

        if (admin.store_name) {
            detailsHtml += `
                <tr>
                    <td class="text-muted"><i class="mdi mdi-store"></i> Dispensed From:</td>
                    <td class="fw-bold">${admin.store_name}</td>
                </tr>`;
        }

        if (admin.drug_source && admin.drug_source !== 'pharmacy_dispensed') {
            var sourceBadge = admin.drug_source === 'ward_stock'
                ? '<span class="badge bg-warning text-dark">Ward Stock</span>'
                : '<span class="badge bg-info">Patient\'s Own</span>';
            detailsHtml += `
                <tr>
                    <td class="text-muted"><i class="mdi mdi-tag"></i> Source:</td>
                    <td>${sourceBadge}</td>
                </tr>`;
        }

        if (admin.comment) {
            detailsHtml += `
                <tr>
                    <td class="text-muted"><i class="mdi mdi-note-text"></i> Notes:</td>
                    <td>${admin.comment}</td>
                </tr>`;
        }

        if (admin.edited_at) {
            detailsHtml += `
                <tr class="table-info">
                    <td class="text-muted"><i class="mdi mdi-pencil"></i> Edited At:</td>
                    <td>${formatDateTime(new Date(admin.edited_at))}</td>
                </tr>
                <tr class="table-info">
                    <td class="text-muted"><i class="mdi mdi-account-edit"></i> Edited By:</td>
                    <td>${admin.edited_by_name || admin.editedBy?.name || '-'}</td>
                </tr>`;
        }

        if (admin.deleted_at) {
            detailsHtml += `
                <tr class="table-danger">
                    <td class="text-muted"><i class="mdi mdi-delete"></i> Deleted At:</td>
                    <td>${formatDateTime(new Date(admin.deleted_at))}</td>
                </tr>
                <tr class="table-danger">
                    <td class="text-muted"><i class="mdi mdi-account-remove"></i> Deleted By:</td>
                    <td>${admin.deleted_by_name || admin.deletedBy?.name || '-'}</td>
                </tr>`;
        }

        detailsHtml += '</table>';

        $('#admin-details-content').html(detailsHtml);

        // Store admin id for edit/delete buttons
        $('#edit-admin-btn').data('admin-id', adminId);
        $('#delete-admin-btn').data('admin-id', adminId);

        // Show/hide edit/delete buttons based on status
        if (admin.deleted_at) {
            $('#edit-admin-btn, #delete-admin-btn').hide();
        } else {
            $('#edit-admin-btn, #delete-admin-btn').show();
        }

        // Explicitly show modal (data-bs-toggle is used for tooltip, not modal)
        $('#adminDetailsModal').modal('show');
    } else {
        $('#admin-details-content').html('<div class="alert alert-warning">Administration details not found.</div>');
    }
});

// Edit administration button handler
$(document).on('click', '#edit-admin-btn', function() {
    const adminId = $(this).data('admin-id');
    const admin = currentAdministrations.find(a => a.id == adminId);

    if (admin) {
        // Close details modal and open edit modal
        $('#adminDetailsModal').modal('hide');

        // Populate edit form
        $('#edit_admin_id').val(adminId);
        $('#edit_administered_at').val(admin.administered_at.replace(' ', 'T').slice(0, 16));
        $('#edit_dose').val(admin.dose);
        $('#edit_route').val(admin.route);
        $('#edit_comment').val(admin.comment || '');
        $('#edit_reason').val('');

        $('#editAdminModal').modal('show');
    }
});

// Delete administration button handler
$(document).on('click', '#delete-admin-btn', function() {
    const adminId = $(this).data('admin-id');

    if (adminId) {
        // Close details modal and open delete modal
        $('#adminDetailsModal').modal('hide');

        $('#delete_admin_id').val(adminId);
        $('#delete_reason').val('');

        $('#deleteAdminModal').modal('show');
    }
});

// Edit Administration Form Submit
$(document).on('submit', '#editAdminForm', function(e) {
    e.preventDefault();

    const $btn = $('#editAdminSubmitBtn');
    const originalBtnHtml = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');

    $.ajax({
        url: medicationChartEditRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            if (response.success) {
                toastr.success('Administration updated successfully.');
                $('#editAdminModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to update administration.');
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            toastr.error(xhr.responseJSON?.message || 'Failed to update administration.');
        }
    });
});

// Delete Administration Form Submit
$(document).on('submit', '#deleteAdminForm', function(e) {
    e.preventDefault();

    const $btn = $('#deleteAdminSubmitBtn');
    const originalBtnHtml = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Deleting...');

    $.ajax({
        url: medicationChartDeleteRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            if (response.success) {
                toastr.success('Administration deleted successfully.');
                $('#deleteAdminModal').modal('hide');
                if (selectedMedication) {
                    loadMedicationCalendarWithDateRange(selectedMedication, $('#med-start-date').val(), $('#med-end-date').val());
                }
            } else {
                toastr.error(response.message || 'Failed to delete administration.');
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalBtnHtml);
            toastr.error(xhr.responseJSON?.message || 'Failed to delete administration.');
        }
    });
});

// Repeat type toggle for days selector
$(document).on('change', 'input[name="repeat_type"]', function() {
    if ($(this).val() === 'selected') {
        $('#days-selector').show();
    } else {
        $('#days-selector').hide();
    }
});

// =============================================
// §6.2–6.4: WARD STOCK & PATIENT'S OWN — DIRECT ADMINISTRATION
// =============================================

// ── Button click handlers ───────────────────────────────────
$('#btn-add-patient-own').on('click', function() {
    // Reset form
    $('#patientOwnForm')[0].reset();
    setCurrentDateTime('po_administered_at');
    var modal = new bootstrap.Modal(document.getElementById('patientOwnModal'));
    modal.show();
});

$('#btn-add-ward-stock').on('click', function() {
    // Reset form
    $('#wardStockForm')[0].reset();
    $('#ws_product_id').val('');
    $('#ws_product_info').hide();
    $('#ws_product_results').hide();
    $('#ws_product_search').val('');
    setCurrentDateTime('ws_administered_at');

    // Load stores
    loadWardStores();

    var modal = new bootstrap.Modal(document.getElementById('wardStockModal'));
    modal.show();
});

// ── Patient's Own Modal Submit ──────────────────────────────
$('#patientOwnForm').on('submit', function(e) {
    e.preventDefault();

    var $btn = $('#patientOwnSubmitBtn');
    var $spinner = $btn.find('.spinner-border');
    $btn.prop('disabled', true);
    $spinner.removeClass('d-none');

    var url = medicationChartAdministerDirectRoute.replace(':patient', PATIENT_ID);
    var formData = {
        drug_source: 'patient_own',
        external_drug_name: $('#po_drug_name').val(),
        external_qty: $('#po_qty').val(),
        external_batch_number: $('#po_batch').val(),
        external_expiry_date: $('#po_expiry').val(),
        external_source_note: $('#po_source_note').val(),
        administered_dose: $('#po_dose').val(),
        route: $('#po_route').val(),
        administered_at: $('#po_administered_at').val(),
        note: $('#po_comment').val()
    };

    $.ajax({
        url: url,
        type: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        data: formData,
        success: function(resp) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            toastr.success(resp.message || 'Patient\'s own drug administered successfully');
            try {
                var modalEl = document.getElementById('patientOwnModal');
                var modalInst = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
                modalInst.hide();
            } catch(e) { $('#patientOwnModal').modal('hide'); }
            // Reload medication list to show the new entry
            if (typeof loadMedicationsList === 'function') loadMedicationsList();
        },
        error: function(xhr) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            var msg = 'Failed to administer';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    msg = Object.values(errors).flat().join('<br>');
                } else if (xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
            }
            toastr.error(msg);
        }
    });
});

// ── Ward Stock: Load stores ─────────────────────────────────
function loadWardStores() {
    $.ajax({
        url: "{{ url('pharmacy-workbench/stores') }}",
        type: 'GET',
        success: function(stores) {
            var $select = $('#ws_store');
            $select.find('option:not(:first)').remove();
            stores.forEach(function(store) {
                $select.append('<option value="' + store.id + '">' + store.store_name + (store.location ? ' (' + store.location + ')' : '') + '</option>');
            });
        },
        error: function() {
            toastr.error('Failed to load stores');
        }
    });
}

// ── Ward Stock: Product search ──────────────────────────────
var wsSearchTimeout;
$('#ws_product_search').on('input', function() {
    var query = $(this).val();
    clearTimeout(wsSearchTimeout);

    if (query.length < 2) {
        $('#ws_product_results').hide();
        return;
    }

    wsSearchTimeout = setTimeout(function() {
        $.ajax({
            url: "{{ url('live-search-products') }}",
            method: 'GET',
            dataType: 'json',
            data: { term: query, patient_id: PATIENT_ID },
            success: function(data) {
                var $results = $('#ws_product_results');
                $results.html('');

                if (!data || data.length === 0) {
                    $results.html('<li class="list-group-item text-muted">No products found</li>').show();
                    return;
                }

                data.forEach(function(item) {
                    var name = item.product_name || 'Unknown';
                    var code = item.product_code || '';
                    var qty = (item.stock && item.stock.current_quantity !== undefined) ? item.stock.current_quantity : 0;
                    var price = (item.price && item.price.current_sale_price !== undefined) ? item.price.current_sale_price : 0;
                    var qtyClass = qty> 0 ? 'text-success' : 'text-danger';

                    var li = '<li class="list-group-item list-group-item-action" style="cursor:pointer;" ' +
                        'data-id="' + item.id + '" ' +
                        'data-name="' + name + '" ' +
                        'data-code="' + code + '" ' +
                        'data-qty="' + qty + '" ' +
                        'data-price="' + price + '">' +
                        '<div class="d-flex justify-content-between">' +
                        '<div><strong>' + name + '</strong> <small class="text-muted">[' + code + ']</small></div>' +
                        '<div class="text-end"><span class="' + qtyClass + '"><strong>' + qty + '</strong> avail.</span><br><small>₦' + Number(price).toLocaleString() + '</small></div>' +
                        '</div></li>';
                    $results.append(li);
                });
                $results.show();
            },
            error: function() {
                $('#ws_product_results').html('<li class="list-group-item text-danger">Search failed</li>').show();
            }
        });
    }, 300);
});

// ── Ward Stock: Select product from search results ──────────
$(document).on('click', '#ws_product_results li[data-id]', function() {
    var id = $(this).data('id');
    var name = $(this).data('name');
    var code = $(this).data('code');
    var qty = $(this).data('qty');
    var price = $(this).data('price');

    $('#ws_product_id').val(id);
    $('#ws_product_search').val(name);
    $('#ws_product_name').text(name);
    $('#ws_product_code').text('[' + code + ']');
    $('#ws_product_price').text('₦' + Number(price).toLocaleString());
    $('#ws_product_results').hide();

    // Show stock for the selected store
    var storeId = $('#ws_store').val();
    if (storeId) {
        updateWsStockDisplay(id, storeId);
    } else {
        $('#ws_available_stock').text(qty + ' global stock').removeClass('bg-success bg-danger').addClass('bg-info');
    }

    $('#ws_product_info').slideDown(200);
});

// ── Ward Stock: Update stock when store changes ─────────────
$('#ws_store').on('change', function() {
    var productId = $('#ws_product_id').val();
    var storeId = $(this).val();
    if (productId && storeId) {
        updateWsStockDisplay(productId, storeId);
    }
});

function updateWsStockDisplay(productId, storeId) {
    $.ajax({
        url: '{{ url('/pharmacy-workbench/product') }}/' + productId + '/stock',
        method: 'GET',
        success: function(resp) {
            var storeStock = (resp.stores || []).find(function(s) { return s.store_id == storeId; });
            var available = storeStock ? storeStock.quantity : 0;
            var badge = $('#ws_available_stock');
            badge.text(available + ' in store');
            if (available> 0) {
                badge.removeClass('bg-danger bg-info').addClass('bg-success');
            } else {
                badge.removeClass('bg-success bg-info').addClass('bg-danger');
            }
        },
        error: function() {
            $('#ws_available_stock').text('? stock').removeClass('bg-success bg-danger').addClass('bg-warning');
        }
    });
}

// Hide product results when clicking outside
$(document).on('click', function(e) {
    if (!$(e.target).closest('#ws_product_search, #ws_product_results').length) {
        $('#ws_product_results').hide();
    }
});

// ── Ward Stock Modal Submit ─────────────────────────────────
$('#wardStockForm').on('submit', function(e) {
    e.preventDefault();

    var productId = $('#ws_product_id').val();
    if (!productId) {
        toastr.warning('Please search and select a product');
        $('#ws_product_search').focus();
        return;
    }

    var storeId = $('#ws_store').val();
    if (!storeId) {
        toastr.warning('Please select a ward/store');
        $('#ws_store').focus();
        return;
    }

    var $btn = $('#wardStockSubmitBtn');
    var $spinner = $btn.find('.spinner-border');
    $btn.prop('disabled', true);
    $spinner.removeClass('d-none');

    var url = medicationChartAdministerDirectRoute.replace(':patient', PATIENT_ID);
    var formData = {
        drug_source: 'ward_stock',
        product_id: productId,
        store_id: storeId,
        qty: $('#ws_qty').val(),
        administered_dose: $('#ws_dose').val(),
        route: $('#ws_route').val(),
        administered_at: $('#ws_administered_at').val(),
        note: $('#ws_comment').val(),
        bill_patient: $('#ws_bill_patient').is(':checked') ? 1 : 0
    };

    $.ajax({
        url: url,
        type: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        data: formData,
        success: function(resp) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            var msg = resp.message || 'Ward stock drug administered successfully';
            if (formData.bill_patient) {
                msg += ' (billed)';
            }
            toastr.success(msg);
            try {
                var modalEl = document.getElementById('wardStockModal');
                var modalInst = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
                modalInst.hide();
            } catch(e) { $('#wardStockModal').modal('hide'); }
            // Reload to show new entry
            if (typeof loadMedicationsList === 'function') loadMedicationsList();
        },
        error: function(xhr) {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            var msg = 'Failed to administer';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    msg = Object.values(errors).flat().join('<br>');
                } else if (xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
            }
            toastr.error(msg);
        }
    });
});

// =============================================
// INTAKE & OUTPUT CHART FUNCTIONS
// =============================================

function loadFluidPeriods() {
    console.log('loadFluidPeriods called, PATIENT_ID:', PATIENT_ID);
    if (!PATIENT_ID) {
        console.log('No PATIENT_ID, returning');
        return;
    }

    const url = intakeOutputChartIndexRoute.replace(':patient', PATIENT_ID);
    const startDate = $('#fluid_start_date').val();
    const endDate = $('#fluid_end_date').val();
    console.log('Fetching from:', url, 'with dates:', startDate, endDate);

    $.ajax({
        url: url,
        type: 'GET',
        data: { type: 'fluid', start_date: startDate, end_date: endDate },
        success: function(data) {
            console.log('loadFluidPeriods response:', data);
            fluidPeriods = data.fluidPeriods || [];
            console.log('fluidPeriods array:', fluidPeriods);
            renderFluidPeriods();
        },
        error: function(xhr) {
            console.error('loadFluidPeriods error:', xhr);
            $('#fluid-periods-list').html('<p class="text-danger">Failed to load fluid data.</p>');
        }
    });
}

function loadSolidPeriods() {
    if (!PATIENT_ID) return;

    const url = intakeOutputChartIndexRoute.replace(':patient', PATIENT_ID);
    const startDate = $('#solid_start_date').val();
    const endDate = $('#solid_end_date').val();

    $.ajax({
        url: url,
        type: 'GET',
        data: { type: 'solid', start_date: startDate, end_date: endDate },
        success: function(data) {
            solidPeriods = data.solidPeriods || [];
            renderSolidPeriods();
        },
        error: function() {
            $('#solid-periods-list').html('<p class="text-danger">Failed to load solid data.</p>');
        }
    });
}

function renderFluidPeriods() {
    console.log('renderFluidPeriods called, fluidPeriods:', fluidPeriods);
    if (fluidPeriods.length === 0) {
        console.log('No periods, showing empty message');
        $('#fluid-periods-list').html('<p class="text-muted">No fluid intake/output periods found. Click "Start New Period" to begin.</p>');
        return;
    }

    let html = '';
    fluidPeriods.forEach(period => {
        console.log('Rendering period:', period);
        const isActive = !period.ended_at;
        const statusBadge = isActive ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Ended</span>';
        const totalIntake = period.total_intake || 0;
        const totalOutput = period.total_output || 0;
        const balance = totalIntake - totalOutput;

        // Build records table
        let recordsHtml = '';
        if (period.records && period.records.length> 0) {
            recordsHtml = `
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Nurse</th>
                            </tr>
                        </thead>
                        <tbody>`;
            period.records.forEach(record => {
                const recordTime = new Date(record.recorded_at);
                const typeBadge = record.type === 'intake'
                    ? '<span class="badge bg-primary">Intake</span>'
                    : '<span class="badge bg-warning text-dark">Output</span>';
                const deleteBtn = record.can_delete
                    ? `<button class="btn btn-sm btn-outline-danger delete-io-record-btn" data-record-id="${record.id}" data-type="fluid" title="Delete record"><i class="mdi mdi-delete"></i></button>`
                    : '';
                recordsHtml += `
                    <tr>
                        <td><small>${formatDateTime(recordTime)}</small></td>
                        <td>${typeBadge}</td>
                        <td>${record.amount} ml</td>
                        <td>${record.description || '-'}</td>
                        <td><small>${record.nurse_name || 'Unknown'}</small></td>
                        <td class="text-end">${deleteBtn}</td>
                    </tr>`;
            });
            recordsHtml += '</tbody></table></div>';
        } else {
            recordsHtml = '<div class="text-muted small mt-2"><em>No records yet. Click "Add Record" to add intake/output.</em></div>';
        }

        html += `
            <div class="card-modern period-card mb-3 ${isActive ? 'border-success' : ''}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><strong>Period:</strong> ${formatDateTime(new Date(period.started_at))} ${statusBadge}</span>
                    <div>
                        ${isActive ? `<button class="btn btn-sm btn-primary add-fluid-record-btn" data-period-id="${period.id}"><i class="mdi mdi-plus"></i> Add Record</button>
                        <button class="btn btn-sm btn-warning end-fluid-period-btn" data-period-id="${period.id}"><i class="mdi mdi-stop"></i> End Period</button>` : ''}
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-primary mb-0"><i class="mdi mdi-water"></i> Intake: ${totalIntake} ml</h6>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-warning mb-0"><i class="mdi mdi-water-off"></i> Output: ${totalOutput} ml</h6>
                        </div>
                        <div class="col-md-4">
                            <h6 class="mb-0"><strong>Balance:</strong> <span class="${balance>= 0 ? 'text-success' : 'text-danger'}">${balance} ml</span></h6>
                        </div>
                    </div>
                    ${recordsHtml}
                </div>
            </div>`;
    });

    $('#fluid-periods-list').html(html);
}

function renderSolidPeriods() {
    if (solidPeriods.length === 0) {
        $('#solid-periods-list').html('<p class="text-muted">No solid intake/output periods found. Click "Start New Period" to begin.</p>');
        return;
    }

    let html = '';
    solidPeriods.forEach(period => {
        const isActive = !period.ended_at;
        const statusBadge = isActive ? '<span class="badge bg-info">Active</span>' : '<span class="badge bg-secondary">Ended</span>';
        const totalIntake = period.total_intake || 0;
        const totalOutput = period.total_output || 0;
        const balance = totalIntake - totalOutput;

        // Build records table
        let recordsHtml = '';
        if (period.records && period.records.length> 0) {
            recordsHtml = `
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Nurse</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>`;
            period.records.forEach(record => {
                const recordTime = new Date(record.recorded_at);
                const typeBadge = record.type === 'intake'
                    ? '<span class="badge bg-success">Intake</span>'
                    : '<span class="badge bg-danger">Output</span>';
                const deleteBtn = record.can_delete
                    ? `<button class="btn btn-sm btn-outline-danger delete-io-record-btn" data-record-id="${record.id}" data-type="solid" title="Delete record"><i class="mdi mdi-delete"></i></button>`
                    : '';
                recordsHtml += `
                    <tr>
                        <td><small>${formatDateTime(recordTime)}</small></td>
                        <td>${typeBadge}</td>
                        <td>${record.amount} g</td>
                        <td>${record.description || '-'}</td>
                        <td><small>${record.nurse_name || 'Unknown'}</small></td>
                        <td class="text-end">${deleteBtn}</td>
                    </tr>`;
            });
            recordsHtml += '</tbody></table></div>';
        } else {
            recordsHtml = '<div class="text-muted small mt-2"><em>No records yet. Click "Add Record" to add intake/output.</em></div>';
        }

        html += `
            <div class="card-modern period-card mb-3 ${isActive ? 'border-info' : ''}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><strong>Period:</strong> ${formatDateTime(new Date(period.started_at))} ${statusBadge}</span>
                    <div>
                        ${isActive ? `<button class="btn btn-sm btn-success add-solid-record-btn" data-period-id="${period.id}"><i class="mdi mdi-plus"></i> Add Record</button>
                        <button class="btn btn-sm btn-warning end-solid-period-btn" data-period-id="${period.id}"><i class="mdi mdi-stop"></i> End Period</button>` : ''}
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-success mb-0"><i class="mdi mdi-food-apple"></i> Intake: ${totalIntake} g</h6>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-danger mb-0"><i class="mdi mdi-delete-empty"></i> Output: ${totalOutput} g</h6>
                        </div>
                        <div class="col-md-4">
                            <h6 class="mb-0"><strong>Balance:</strong> <span class="${balance>= 0 ? 'text-success' : 'text-danger'}">${balance} g</span></h6>
                        </div>
                    </div>
                    ${recordsHtml}
                </div>
            </div>`;
    });

    $('#solid-periods-list').html(html);
}

// Fluid filter buttons
$(document).on('click', '#fluid_apply_filter_btn', function() {
    loadFluidPeriods();
});

$(document).on('click', '#fluid_reset_filter_btn', function() {
    const today = new Date();
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    $('#fluid_start_date').val(weekAgo.toISOString().split('T')[0]);
    $('#fluid_end_date').val(today.toISOString().split('T')[0]);
    loadFluidPeriods();
});

// Solid filter buttons
$(document).on('click', '#solid_apply_filter_btn', function() {
    loadSolidPeriods();
});

$(document).on('click', '#solid_reset_filter_btn', function() {
    const today = new Date();
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    $('#solid_start_date').val(weekAgo.toISOString().split('T')[0]);
    $('#solid_end_date').val(today.toISOString().split('T')[0]);
    loadSolidPeriods();
});

// Start fluid period
$(document).on('click', '#startFluidPeriodBtn', function() {
    console.log('startFluidPeriodBtn clicked, PATIENT_ID:', PATIENT_ID);
    if (!PATIENT_ID) {
        toastr.warning('Please select a patient first.');
        return;
    }

    $.ajax({
        url: intakeOutputChartStartRoute,
        type: 'POST',
        data: { patient_id: PATIENT_ID, type: 'fluid', _token: CSRF_TOKEN },
        success: function(response) {
            console.log('Start period response:', response);
            if (response.success) {
                toastr.success('Fluid period started.');
                console.log('Calling loadFluidPeriods...');
                loadFluidPeriods();
            } else {
                toastr.error(response.message || 'Failed to start period.');
            }
        },
        error: function(xhr) {
            console.error('Start period error:', xhr);
            toastr.error(xhr.responseJSON?.message || 'Failed to start period.');
        }
    });
});

// Start solid period
$(document).on('click', '#startSolidPeriodBtn', function() {
    if (!PATIENT_ID) {
        toastr.warning('Please select a patient first.');
        return;
    }

    $.ajax({
        url: intakeOutputChartStartRoute,
        type: 'POST',
        data: { patient_id: PATIENT_ID, type: 'solid', _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Solid period started.');
                loadSolidPeriods();
            } else {
                toastr.error(response.message || 'Failed to start period.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to start period.');
        }
    });
});

// End fluid period
$(document).on('click', '.end-fluid-period-btn', function() {
    const periodId = $(this).data('period-id');
    if (!confirm('End this fluid period?')) return;

    $.ajax({
        url: intakeOutputChartEndRoute,
        type: 'POST',
        data: { period_id: periodId, _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Fluid period ended.');
                loadFluidPeriods();
            } else {
                toastr.error(response.message || 'Failed to end period.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to end period.');
        }
    });
});

// End solid period
$(document).on('click', '.end-solid-period-btn', function() {
    const periodId = $(this).data('period-id');
    if (!confirm('End this solid period?')) return;

    $.ajax({
        url: intakeOutputChartEndRoute,
        type: 'POST',
        data: { period_id: periodId, _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Solid period ended.');
                loadSolidPeriods();
            } else {
                toastr.error(response.message || 'Failed to end period.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to end period.');
        }
    });
});

// Add fluid record
$(document).on('click', '.add-fluid-record-btn', function() {
    currentFluidPeriodId = $(this).data('period-id');
    $('#fluid_period_id').val(currentFluidPeriodId);
    $('#fluidRecordModal').modal('show');
});

// Add solid record
$(document).on('click', '.add-solid-record-btn', function() {
    currentSolidPeriodId = $(this).data('period-id');
    $('#solid_period_id').val(currentSolidPeriodId);
    $('#solidRecordModal').modal('show');
});

// Delete I/O record handler
$(document).on('click', '.delete-io-record-btn', function() {
    const recordId = $(this).data('record-id');
    const type = $(this).data('type');
    if (!recordId) return;
    if (!confirm('Delete this record? This cannot be undone.')) return;
    const url = intakeOutputChartDeleteRecordRoute.replace(':record', recordId);
    $.ajax({
        url: url,
        type: 'DELETE',
        data: { _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Record deleted.');
                if (type === 'fluid') {
                    loadFluidPeriods();
                } else {
                    loadSolidPeriods();
                }
            } else {
                toastr.error(response.message || 'Failed to delete record.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to delete record.');
        }
    });
});

// Fluid record form submit
$(document).on('submit', '#fluidRecordForm', function(e) {
    e.preventDefault();

    $.ajax({
        url: intakeOutputChartRecordRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Fluid record added.');
                $('#fluidRecordModal').modal('hide');
                $('#fluidRecordForm')[0].reset();
                loadFluidPeriods();
            } else {
                toastr.error(response.message || 'Failed to add record.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to add record.');
        }
    });
});

// Solid record form submit
$(document).on('submit', '#solidRecordForm', function(e) {
    e.preventDefault();

    $.ajax({
        url: intakeOutputChartRecordRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Solid record added.');
                $('#solidRecordModal').modal('hide');
                $('#solidRecordForm')[0].reset();
                loadSolidPeriods();
            } else {
                toastr.error(response.message || 'Failed to add record.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to add record.');
        }
    });
});

// Edit Note Logic
let editNoteEditor;

function openEditNoteModal(btn) {
    const noteId = $(btn).data('id');
    // Get content from data attribute on the button (set by server)
    const content = $(btn).data('content') || $(btn).closest('.nursing-note-card').find('.note-content').html() || '';

    $('#edit-note-id').val(noteId);

    // Initialize Editor if not exists
    if (!editNoteEditor) {
        ClassicEditor
            .create(document.querySelector('#edit-note-editor'), {
                toolbar: ['heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo']
            })
            .then(editor => {
                editNoteEditor = editor;
                editNoteEditor.setData(content);
                $('#editNoteModal').modal('show');
            })
            .catch(error => {
                console.error(error);
            });
    } else {
        editNoteEditor.setData(content);
        $('#editNoteModal').modal('show');
    }
}

function updatedNote() {
    const noteId = $('#edit-note-id').val();
    const content = editNoteEditor.getData();

    if (!content.trim()) {
        showNotification('error', 'Note content cannot be empty');
        return;
    }

    $.ajax({
        url: `{{ url('/nursing-workbench/nursing-note/${noteId}') }}`,
        type: 'PUT',
        data: {
            note: content,
            _token: CSRF_TOKEN
        },
        success: function(response) {
            showNotification('success', 'Note updated successfully');
            $('#editNoteModal').modal('hide');
            loadNotesHistory(currentPatient); // Reload table
        },
        error: function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to update note');
        }
    });
}

// Edit Vital Logic
function openEditVitalModal(btn) {
    const vitalData = JSON.parse($(btn).attr('data-vital'));

    $('#edit-vital-id').val(vitalData.id);
    $('#edit-blood-pressure').val(vitalData.blood_pressure || '');
    $('#edit-temp').val(vitalData.temp || '');
    $('#edit-heart-rate').val(vitalData.heart_rate || '');
    $('#edit-resp-rate').val(vitalData.resp_rate || '');
    $('#edit-weight').val(vitalData.weight || '');
    $('#edit-height').val(vitalData.height || '');
    $('#edit-spo2').val(vitalData.spo2 || '');
    $('#edit-blood-sugar').val(vitalData.blood_sugar || '');
    $('#edit-other-notes').val(vitalData.other_notes || '');

    $('#editVitalModal').modal('show');
}

function updateVital() {
    const vitalId = $('#edit-vital-id').val();

    const data = {
        blood_pressure: $('#edit-blood-pressure').val(),
        temp: $('#edit-temp').val(),
        heart_rate: $('#edit-heart-rate').val(),
        resp_rate: $('#edit-resp-rate').val(),
        weight: $('#edit-weight').val(),
        height: $('#edit-height').val(),
        spo2: $('#edit-spo2').val(),
        blood_sugar: $('#edit-blood-sugar').val(),
        other_notes: $('#edit-other-notes').val(),
        _token: CSRF_TOKEN
    };

    $.ajax({
        url: `{{ url('/nursing-workbench/vitals/${vitalId}') }}`,
        type: 'PUT',
        data: data,
        success: function(response) {
            if (response.success) {
                showNotification('success', response.message || 'Vitals updated successfully');
                $('#editVitalModal').modal('hide');
                // Reload unified vitals history DataTable (if present)
                $('.unified-vitals-history-table').each(function() {
                    if ($.fn.DataTable.isDataTable(this)) {
                        $(this).DataTable().ajax.reload(null, false);
                    }
                });
                // Reload nursing workbench vitals table (if present)
                if ($.fn.DataTable.isDataTable('#vitals-table')) {
                    $('#vitals-table').DataTable().ajax.reload(null, false);
                }
            } else {
                showNotification('error', response.message || 'Failed to update vitals');
            }
        },
        error: function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to update vitals');
        }
    });
}

// =============================================
// SHIFT MANAGEMENT MODULE
// =============================================

const ShiftManager = {
    // State
    activeShift: null,
    shiftTimer: null,
    acknowledgedHandovers: [],
    currentHandoverDetail: null,
    forceEndShift: false,

    // Handover cards state
    handoverCurrentPage: 1,
    handoverPerPage: 12,
    handoverViewMode: 'cards', // 'cards' or 'list'

    // Routes
    routes: {
        check: '/nursing-workbench/shift/check',
        start: '/nursing-workbench/shift/start',
        end: '/nursing-workbench/shift/end',
        preview: '/nursing-workbench/shift/preview',
        pendingHandovers: '/nursing-workbench/shift/pending-handovers',
        wards: '/nursing-workbench/shift/wards',
        actions: '/nursing-workbench/shift/actions',
        handovers: '/nursing-workbench/handovers',
        handoverDetail: '/nursing-workbench/handover',
        acknowledge: '/nursing-workbench/handover/{id}/acknowledge',
        acknowledgeMultiple: '/nursing-workbench/handovers/acknowledge-multiple'
    },

    // Initialize
    init: function() {
        this.bindEvents();
        this.checkShiftStatus();
        this.loadWards();
        this.makeFabDraggable();
    },

    // Bind event handlers
    bindEvents: function() {
        const self = this;

        // Start shift button (on lock overlay)
        $('#start-shift-btn').on('click', function() {
            self.showStartShiftModal();
        });

        // Confirm start shift
        $('#confirm-start-shift-btn').on('click', function() {
            self.startShift();
        });

        // Ward select change - load handovers for that ward
        $('#shift-ward-select').on('change', function() {
            // Reset handovers when ward changes
            $('#shift-handovers-step').hide();
            self.acknowledgedHandovers = [];
        });

        // Check for handovers button
        $('#load-ward-handovers-btn').on('click', function() {
            self.loadHandoversForWard();
        });

        // FAB main button toggle
        $('#shift-fab-btn').on('click', function() {
            self.toggleFabActions();
        });

        // End shift button
        $('#end-shift-btn').on('click', function() {
            self.showEndShiftModal();
        });

        // Confirm end shift
        $('#confirm-end-shift-btn').on('click', function() {
            self.endShift();
        });

        // Load shift preview
        $('#load-shift-preview-btn').on('click', function() {
            self.loadShiftPreview();
        });

        // View shift summary
        $('#view-shift-summary').on('click', function() {
            self.showShiftSummary();
        });

        // View handovers button
        $('#view-handovers-btn').on('click', function() {
            self.showHandoversList();
        });

        // Quick action button for handovers
        $(document).on('click', '#quick-shift-handover', function() {
            self.showHandoversList();
        });

        // Add pending task
        $('#add-pending-task-btn').on('click', function() {
            self.addPendingTaskRow();
        });

        // Remove pending task
        $(document).on('click', '.remove-pending-task', function() {
            $(this).closest('.pending-task-row').remove();
        });

        // Apply handover filters
        $('#apply-handover-filters').on('click', function() {
            self.reloadHandoversCards();
        });

        // Clear handover filters
        $('#clear-handover-filters').on('click', function() {
            self.clearHandoverFilters();
        });

        // Per page change
        $('#handover-per-page').on('change', function() {
            self.handoverPerPage = parseInt($(this).val());
            self.handoverCurrentPage = 1;
            self.loadHandoversCards();
        });

        // Pagination click
        $(document).on('click', '#handover-pagination-list .page-link', function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            if (page && !$(this).parent().hasClass('disabled') && !$(this).parent().hasClass('active')) {
                self.handoverCurrentPage = page;
                self.loadHandoversCards();
            }
        });

        // View toggle - Cards
        $('#handover-view-cards').on('click', function() {
            self.handoverViewMode = 'cards';
            $(this).addClass('active');
            $('#handover-view-list').removeClass('active');
            self.loadHandoversCards();
        });

        // View toggle - List
        $('#handover-view-list').on('click', function() {
            self.handoverViewMode = 'list';
            $(this).addClass('active');
            $('#handover-view-cards').removeClass('active');
            self.loadHandoversCards();
        });

        // Search input enter key
        $('#handover-filter-search').on('keypress', function(e) {
            if (e.which === 13) {
                self.reloadHandoversCards();
            }
        });

        // View handover detail
        $(document).on('click', '.view-handover', function() {
            const id = $(this).data('id');
            self.showHandoverDetail(id);
        });

        // Acknowledge handover from cards/list
        $(document).on('click', '.acknowledge-handover', function() {
            const id = $(this).data('id');
            self.acknowledgeHandover(id);
        });

        // Acknowledge handover from detail modal
        $('#acknowledge-handover-detail-btn').on('click', function() {
            if (self.currentHandoverDetail) {
                self.acknowledgeHandover(self.currentHandoverDetail.id, true);
            }
        });

        // Load more handovers in start shift modal
        $('#load-more-handovers-btn').on('click', function() {
            self.loadPendingHandovers(48); // Load 48 hours
        });

        // Handover acknowledgment checkboxes
        $(document).on('change', '.handover-ack-checkbox', function() {
            const id = $(this).data('id');
            if ($(this).is(':checked')) {
                if (!self.acknowledgedHandovers.includes(id)) {
                    self.acknowledgedHandovers.push(id);
                }
            } else {
                self.acknowledgedHandovers = self.acknowledgedHandovers.filter(h => h !== id);
            }
        });

        // Restore overlay when start shift modal is closed without starting
        $('#startShiftModal').on('hidden.bs.modal', function() {
            if (!self.activeShift) {
                $('#shift-lock-overlay').removeClass('modal-open-hidden');
            }
        });
    },

    // Check shift status on load
    checkShiftStatus: function() {
        const self = this;

        $.ajax({
            url: this.routes.check,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    if (response.has_active_shift) {
                        self.activeShift = response.shift;

                        // Check if shift is older than 12 hours (43200 seconds)
                        const elapsedSeconds = response.shift.elapsed_seconds || 0;
                        const twelveHoursInSeconds = 12 * 60 * 60; // 43200

                        if (elapsedSeconds> twelveHoursInSeconds) {
                            // Shift is overdue - force end shift
                            self.showWorkbench();
                            self.startShiftTimer();
                            self.forceEndShiftModal(elapsedSeconds);
                        } else {
                            self.showWorkbench();
                            self.startShiftTimer();
                        }
                    } else {
                        self.showLockOverlay();
                    }
                }
            },
            error: function() {
                // If check fails, show workbench anyway (graceful degradation)
                self.showWorkbench();
            }
        });
    },

    // Force end shift modal for overdue shifts (>12 hours)
    forceEndShiftModal: function(elapsedSeconds) {
        const self = this;
        const hours = Math.floor(elapsedSeconds / 3600);
        const minutes = Math.floor((elapsedSeconds % 3600) / 60);
        const overdueHours = hours - 12;

        // Set flag to prevent modal dismissal
        this.forceEndShift = true;

        // Show the end shift modal with forced mode
        this.showEndShiftModal(true);

        // Add overdue warning to the modal
        const warningHtml = `
            <div id="overdue-shift-warning" class="alert alert-danger mb-4">
                <h5 class="alert-heading">
                    <i class="mdi mdi-alert-octagon"></i> Shift Overdue - Action Required
                </h5>
                <hr>
                <p class="mb-2">
                    <strong>Your shift has been running for ${hours} hours and ${minutes} minutes.</strong>
                </p>
                <p class="mb-2">
                    Standard shifts are 8-12 hours. Your shift is now <strong class="text-danger">${overdueHours> 0 ? overdueHours + ' hour(s)' : 'more than 12 hours'}</strong> overdue.
                </p>
                <p class="mb-0">
                    <i class="mdi mdi-information-outline"></i>
                    <strong>You must end this shift to continue.</strong> This ensures proper handover documentation and accurate shift records.
                    Please review your activities, add any critical notes, and end your shift.
                </p>
            </div>
        `;

        // Insert warning at the top of modal body if not already there
        if ($('#overdue-shift-warning').length === 0) {
            $('#endShiftModal .modal-body').prepend(warningHtml);
        }

        // Change modal header to indicate forced mode
        $('#endShiftModalLabel').html('<i class="mdi mdi-alert-octagon"></i> End Overdue Shift (Required)');

        // Hide cancel button and prevent modal dismiss
        $('#endShiftModal .btn-secondary[data-bs-dismiss="modal"]').hide();
        $('#endShiftModal .btn-close').hide();

        // Make modal static (cannot dismiss by clicking outside)
        $('#endShiftModal').attr('data-bs-backdrop', 'static');
        $('#endShiftModal').attr('data-bs-keyboard', 'false');

        // Update end shift button text
        $('#confirm-end-shift-btn').html('<i class="mdi mdi-stop-circle"></i> End Overdue Shift');

        toastr.warning('Your shift is overdue. Please end your shift and create a handover document.', 'Shift Overdue', {
            timeOut: 10000,
            closeButton: true
        });
    },

    // Reset end shift modal to normal mode
    resetEndShiftModal: function() {
        this.forceEndShift = false;

        // Remove overdue warning
        $('#overdue-shift-warning').remove();

        // Restore modal header
        $('#endShiftModalLabel').html('<i class="mdi mdi-stop-circle"></i> End Your Shift');

        // Show cancel button and close button
        $('#endShiftModal .btn-secondary[data-bs-dismiss="modal"]').show();
        $('#endShiftModal .btn-close').show();

        // Remove static backdrop
        $('#endShiftModal').removeAttr('data-bs-backdrop');
        $('#endShiftModal').removeAttr('data-bs-keyboard');

        // Reset end shift button text
        $('#confirm-end-shift-btn').html('<i class="mdi mdi-stop-circle"></i> End Shift');
    },

    // Show lock overlay
    showLockOverlay: function() {
        $('#shift-lock-overlay').show();
        $('#shift-control-fab').hide();
        // Show a simple count of handovers (user will see details after selecting ward in modal)
        this.loadPendingHandoversCount();
    },

    // Show workbench (unlock)
    showWorkbench: function() {
        $('#shift-lock-overlay').hide();
        $('#shift-control-fab').show();
        this.updateFabDisplay();
    },

    // Load pending handovers count for lock overlay (just shows count, not details)
    loadPendingHandoversCount: function() {
        $.ajax({
            url: this.routes.pendingHandovers,
            type: 'GET',
            data: { hours: 24 },
            success: function(response) {
                if (response.success && response.total_pending> 0) {
                    $('#pending-handovers-count').text(response.total_pending);
                    $('#pending-handovers-preview').show();
                    // Show simplified list
                    let html = '';
                    response.handovers.slice(0, 3).forEach(function(h) {
                        html += `
                            <div class="pending-handover-item ${h.has_critical_notes ? 'has-critical' : ''}">
                                <div>
                                    <strong>${h.created_by_name}</strong>
                                    <span class="text-muted">· ${h.created_at_ago}</span>
                                    ${h.has_critical_notes ? '<span class="badge badge-danger ml-2">Critical</span>' : ''}
                                </div>
                                <span>${h.shift_type_badge}</span>
                            </div>
                        `;
                    });
                    if (response.total_pending> 3) {
                        html += `<p class="text-center text-muted mt-2 mb-0">+${response.total_pending - 3} more</p>`;
                    }
                    $('#pending-handovers-list').html(html);
                } else {
                    $('#pending-handovers-preview').hide();
                }
            }
        });
    },

    // Load pending handovers preview for lock overlay (DEPRECATED - use loadPendingHandoversCount)
    loadPendingHandoversPreview: function() {
        const self = this;

        $.ajax({
            url: this.routes.pendingHandovers,
            type: 'GET',
            data: { hours: 24 },
            success: function(response) {
                if (response.success && response.total_pending> 0) {
                    $('#pending-handovers-count').text(response.total_pending);

                    let html = '';
                    response.handovers.forEach(function(h) {
                        html += `
                            <div class="pending-handover-item ${h.has_critical_notes ? 'has-critical' : ''}">
                                <div>
                                    <strong>${h.created_by_name}</strong>
                                    <span class="text-muted">· ${h.created_at_ago}</span>
                                    ${h.has_critical_notes ? '<span class="badge badge-danger ml-2">Critical</span>' : ''}
                                </div>
                                <span>${h.shift_type_badge}</span>
                            </div>
                        `;
                    });
                    $('#pending-handovers-list').html(html);
                    $('#pending-handovers-preview').show();
                } else {
                    $('#pending-handovers-preview').hide();
                }
            }
        });
    },

    // Load wards for select
    loadWards: function() {
        $.ajax({
            url: this.routes.wards,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    let options = '<option value="">All Wards (Floating)</option>';
                    response.wards.forEach(function(ward) {
                        options += `<option value="${ward.id}">${ward.name}</option>`;
                    });
                    $('#shift-ward-select, #handover-filter-ward').html(options);
                }
            }
        });
    },

    // Show start shift modal
    showStartShiftModal: function() {
        const self = this;
        this.acknowledgedHandovers = [];

        // Show modal with config step first, hide handovers until ward is selected
        $('#shift-config-step').show();
        $('#shift-handovers-step').hide();
        $('#start-shift-handovers-list').html('');

        // Temporarily hide overlay so modal is visible
        $('#shift-lock-overlay').addClass('modal-open-hidden');
        $('#startShiftModal').modal('show');
    },

    // Load handovers for selected ward (called when ward changes)
    loadHandoversForWard: function() {
        const self = this;
        const wardId = $('#shift-ward-select').val();

        $.ajax({
            url: this.routes.pendingHandovers,
            type: 'GET',
            data: {
                ward_id: wardId || null,
                hours: 24
            },
            success: function(response) {
                if (response.success && response.handovers.length> 0) {
                    self.renderPendingHandovers(response.handovers);
                    $('#shift-handovers-step').show();
                } else {
                    $('#shift-handovers-step').hide();
                    $('#start-shift-handovers-list').html('<p class="text-muted text-center py-3">No pending handovers for this ward</p>');
                }
            },
            error: function() {
                $('#shift-handovers-step').hide();
            }
        });
    },

    // Render pending handovers in modal
    renderPendingHandovers: function(handovers) {
        const self = this;
        let html = '';
        handovers.forEach(function(h) {
            const isAcked = self.acknowledgedHandovers.includes(h.id);
            html += `
                <div class="handover-ack-item ${h.has_critical_notes ? 'critical' : ''}">
                    <div class="handover-ack-header">
                        <div>
                            ${h.shift_type_badge}
                            <span class="ml-2 text-muted">${h.ward_name}</span>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input handover-ack-checkbox"
                                data-id="${h.id}" ${isAcked ? 'checked' : ''}>
                            <label class="form-check-label">Acknowledged</label>
                        </div>
                    </div>
                    <div class="handover-ack-meta">
                        <strong>${h.created_by_name}</strong> · ${h.created_at_ago}
                    </div>
                    <div class="handover-ack-content mt-2">
                        ${h.summary_preview}
                        ${h.has_critical_notes ? '<div class="text-danger mt-1"><i class="mdi mdi-alert"></i> Contains critical notes</div>' : ''}
                    </div>
                    <div class="handover-ack-footer">
                        <span class="text-muted">${h.pending_tasks_count} pending task(s)</span>
                        <button class="btn btn-sm btn-outline-info view-handover" data-id="${h.id}">
                            <i class="mdi mdi-eye"></i> View Full
                        </button>
                    </div>
                </div>
            `;
        });
        $('#start-shift-handovers-list').html(html);
    },

    // Load pending handovers for acknowledgment
    loadPendingHandovers: function(hours = 24) {
        const self = this;
        const wardId = $('#shift-ward-select').val();

        $.ajax({
            url: this.routes.pendingHandovers,
            type: 'GET',
            data: {
                ward_id: wardId,
                hours: hours
            },
            success: function(response) {
                if (response.success && response.handovers.length> 0) {
                    self.renderPendingHandovers(response.handovers);
                    $('#shift-handovers-step').show();
                } else {
                    $('#shift-handovers-step').hide();
                }
            }
        });
    },

    // Start shift
    startShift: function() {
        const self = this;
        const wardId = $('#shift-ward-select').val();
        const shiftType = $('#shift-type-select').val();

        // Check if there are critical handovers that need acknowledgment
        const criticalHandovers = $('.handover-ack-item.critical');
        const unacknowledgedCritical = criticalHandovers.filter(function() {
            return !$(this).find('.handover-ack-checkbox').is(':checked');
        });

        if (unacknowledgedCritical.length> 0) {
            // Highlight unacknowledged critical handovers
            unacknowledgedCritical.addClass('shake-highlight');
            setTimeout(() => unacknowledgedCritical.removeClass('shake-highlight'), 500);

            toastr.warning('Please acknowledge all critical handovers (marked in red) before starting your shift');

            // Scroll to first unacknowledged
            const firstUnacked = unacknowledgedCritical.first();
            if (firstUnacked.length) {
                firstUnacked[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        const btn = $('#confirm-start-shift-btn');
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Starting...');

        $.ajax({
            url: this.routes.start,
            type: 'POST',
            data: {
                ward_id: wardId || null,
                shift_type: shiftType || null,
                acknowledged_handovers: this.acknowledgedHandovers,
                _token: CSRF_TOKEN
            },
            success: function(response) {
                if (response.success) {
                    self.activeShift = response.shift;
                    $('#startShiftModal').modal('hide');
                    self.showWorkbench();
                    self.startShiftTimer();
                    toastr.success(response.message || 'Shift started successfully');
                } else if (response.requires_acknowledgment) {
                    toastr.warning(response.message);
                    // Highlight unacknowledged critical handovers
                    btn.prop('disabled', false).html('<i class="mdi mdi-play-circle"></i> Start Shift');
                } else {
                    toastr.error(response.message || 'Failed to start shift');
                    btn.prop('disabled', false).html('<i class="mdi mdi-play-circle"></i> Start Shift');
                }
            },
            error: function(xhr) {
                const resp = xhr.responseJSON || {};
                if (resp.requires_acknowledgment) {
                    toastr.warning(resp.message || 'Please acknowledge critical handovers first');
                    // Highlight unacknowledged critical handovers
                    $('.handover-ack-item.critical').each(function() {
                        if (!$(this).find('.handover-ack-checkbox').is(':checked')) {
                            $(this).addClass('shake-highlight');
                            setTimeout(() => $(this).removeClass('shake-highlight'), 500);
                        }
                    });
                } else {
                    toastr.error(resp.message || 'Failed to start shift');
                }
                btn.prop('disabled', false).html('<i class="mdi mdi-play-circle"></i> Start Shift');
            }
        });
    },

    // Show end shift modal
    showEndShiftModal: function(forced = false) {
        if (!this.activeShift) return;

        // Reset modal to normal mode if not forced
        if (!forced) {
            this.resetEndShiftModal();
        }

        // Populate summary
        $('#end-shift-duration').text(this.formatElapsedTime(this.activeShift.elapsed_seconds || 0));
        $('#end-shift-vitals').text(this.activeShift.counters?.vitals || 0);
        $('#end-shift-medications').text(this.activeShift.counters?.medications || 0);
        $('#end-shift-notes').text(this.activeShift.counters?.notes || 0);
        $('#end-shift-total').text(this.activeShift.total_actions || 0);

        // Clear form
        $('#end-shift-critical-notes').val('');
        $('#end-shift-concluding-notes').val('');
        $('#pending-tasks-container').html(`
            <div class="pending-task-row mb-2">
                <div class="input-group">
                    <select class="form-control form-control-sm pending-task-priority" style="max-width: 100px;">
                        <option value="normal">Normal</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                    <input type="text" class="form-control pending-task-desc" placeholder="Describe pending task...">
                    <div class="input-group-append">
                        <button class="btn btn-outline-danger remove-pending-task" type="button">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                </div>
            </div>
        `);
        $('#create-handover-checkbox').prop('checked', true);

        // Reset preview section and show loading state
        $('#shift-activity-preview').html(`
            <div class="text-center text-muted py-3">
                <i class="mdi mdi-loading mdi-spin"></i> Loading activity preview...
            </div>
        `);

        $('#endShiftModal').modal('show');

        // Auto-load preview after modal is shown
        this.loadShiftPreview();
    },

    // Load shift preview with audit-based activities
    loadShiftPreview: function() {
        const self = this;
        const btn = $('#load-shift-preview-btn');
        const container = $('#shift-activity-preview');

        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Loading...');

        $.ajax({
            url: this.routes.preview,
            type: 'GET',
            success: function(response) {
                if (response.success && response.preview) {
                    self.renderShiftPreview(response.preview);
                } else {
                    container.html(`
                        <div class="alert alert-warning mb-0">
                            <i class="mdi mdi-alert"></i> No activity data found for this shift
                        </div>
                    `);
                }
                btn.prop('disabled', false).html('<i class="mdi mdi-refresh"></i> Refresh Preview');
            },
            error: function(xhr) {
                container.html(`
                    <div class="alert alert-danger mb-0">
                        <i class="mdi mdi-alert-circle"></i> Failed to load preview
                    </div>
                `);
                btn.prop('disabled', false).html('<i class="mdi mdi-refresh"></i> Load Preview');
            }
        });
    },

    // Render shift preview HTML
    renderShiftPreview: function(preview) {
        let html = '';

        // Summary stats
        html += `
            <div class="d-flex justify-content-around text-center mb-3 pb-3 border-bottom">
                <div>
                    <div class="h5 mb-0 text-primary">${preview.total_events || 0}</div>
                    <small class="text-muted">Total Events</small>
                </div>
                <div>
                    <div class="h5 mb-0 text-info">${preview.total_patients || 0}</div>
                    <small class="text-muted">Patients</small>
                </div>
                <div>
                    <div class="h5 mb-0 text-secondary">${preview.elapsed_time || '--'}</div>
                    <small class="text-muted">Duration</small>
                </div>
            </div>
        `;

        // Activity breakdown
        if (preview.activity_summary && preview.activity_summary.length> 0) {
            html += '<h6 class="mb-2"><i class="mdi mdi-chart-bar"></i> Activity Breakdown</h6>';
            html += '<div class="row">';
            preview.activity_summary.forEach(function(activity) {
                html += `
                    <div class="col-6 col-md-4 mb-2">
                        <div class="d-flex align-items-center p-2 border rounded bg-white">
                            <i class="mdi ${activity.icon || 'mdi-circle'} text-${activity.color || 'secondary'} mr-2" style="font-size: 1.5rem;"></i>
                            <div class="flex-grow-1">
                                <div class="font-weight-bold">${activity.count}</div>
                                <small class="text-muted">${activity.label}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        }

        // Patient highlights (collapsible)
        if (preview.patient_highlights && preview.patient_highlights.length> 0) {
            html += `
                <h6 class="mb-2 mt-3"><i class="mdi mdi-account-group"></i> Patient Highlights</h6>
                <div class="patient-highlights-preview">
            `;
            preview.patient_highlights.slice(0, 5).forEach(function(patient, idx) {
                html += `
                    <div class="d-flex justify-content-between align-items-center p-2 border-bottom bg-white">
                        <div>
                            <i class="mdi mdi-account text-primary mr-1"></i>
                            <strong>${patient.patient_name}</strong>
                            <small class="text-muted ml-1">(${patient.patient_no || 'N/A'})</small>
                        </div>
                        <span class="badge badge-primary badge-pill">${patient.total_events} events</span>
                    </div>
                `;
            });
            if (preview.patient_highlights.length> 5) {
                html += `<div class="text-center py-2 text-muted small">... and ${preview.patient_highlights.length - 5} more patients</div>`;
            }
            html += '</div>';
        }

        // Auto-generated summary preview
        if (preview.detailed_summary) {
            html += `
                <div class="mt-3 pt-3 border-top">
                    <h6 class="mb-2"><i class="mdi mdi-clipboard-text"></i> Auto-Generated Summary</h6>
                    <div class="bg-white p-2 border rounded small" style="max-height: 150px; overflow-y: auto;">
                        ${preview.detailed_summary.replace(/\n/g, '<br>')}
                    </div>
                </div>
            `;
        }

        $('#shift-activity-preview').html(html || '<div class="text-center text-muted">No activities recorded yet</div>');
    },

    // End shift
    endShift: function() {
        const self = this;
        const btn = $('#confirm-end-shift-btn');
        const wasForced = this.forceEndShift;

        // Gather pending tasks
        const pendingTasks = [];
        $('.pending-task-row').each(function() {
            const desc = $(this).find('.pending-task-desc').val().trim();
            if (desc) {
                pendingTasks.push({
                    description: desc,
                    priority: $(this).find('.pending-task-priority').val()
                });
            }
        });

        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Ending...');

        $.ajax({
            url: this.routes.end,
            type: 'POST',
            data: {
                critical_notes: $('#end-shift-critical-notes').val(),
                concluding_notes: $('#end-shift-concluding-notes').val(),
                pending_tasks: pendingTasks,
                create_handover: $('#create-handover-checkbox').is(':checked'),
                _token: CSRF_TOKEN
            },
            success: function(response) {
                if (response.success) {
                    // Reset forced mode before hiding modal
                    self.resetEndShiftModal();

                    $('#endShiftModal').modal('hide');
                    self.activeShift = null;
                    self.forceEndShift = false;
                    self.stopShiftTimer();

                    if (wasForced) {
                        toastr.success('Overdue shift ended successfully. Thank you for completing the handover.', 'Shift Ended');
                    } else {
                        toastr.success(response.message || 'Shift ended successfully');
                    }

                    if (response.handover_created) {
                        toastr.info('Handover document created for incoming nurse');
                    }

                    // Show summary
                    setTimeout(function() {
                        self.showLockOverlay();
                    }, 1000);
                } else {
                    toastr.error(response.message || 'Failed to end shift');
                    btn.prop('disabled', false).html('<i class="mdi mdi-stop-circle"></i> End Shift');
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to end shift');
                btn.prop('disabled', false).html('<i class="mdi mdi-stop-circle"></i> End Shift');
            }
        });
    },

    // Add pending task row
    addPendingTaskRow: function() {
        const html = `
            <div class="pending-task-row mb-2">
                <div class="input-group">
                    <select class="form-control form-control-sm pending-task-priority" style="max-width: 100px;">
                        <option value="normal">Normal</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                    <input type="text" class="form-control pending-task-desc" placeholder="Describe pending task...">
                    <div class="input-group-append">
                        <button class="btn btn-outline-danger remove-pending-task" type="button">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#pending-tasks-container').append(html);
    },

    // Show handovers list (Cards-based modal)
    showHandoversList: function() {
        const self = this;
        // Reset pagination and load first page
        this.handoverCurrentPage = 1;
        this.handoverPerPage = parseInt($('#handover-per-page').val()) || 12;
        this.handoverViewMode = 'cards';

        // Set default view toggle
        $('#handover-view-cards').addClass('active');
        $('#handover-view-list').removeClass('active');

        // Set default dates (last 2 days)
        const today = new Date();
        const twoDaysAgo = new Date(today);
        twoDaysAgo.setDate(today.getDate() - 2);

        const formatDate = (date) => date.toISOString().split('T')[0];
        $('#handover-filter-from').val(formatDate(twoDaysAgo));
        $('#handover-filter-to').val(formatDate(today));

        // Populate wards if not already done
        if ($('#handover-filter-ward option').length <= 1) {
            this.populateHandoverWards();
        }

        this.loadHandoversCards();
        $('#handoversListModal').modal('show');
    },

    // Populate ward filter options
    populateHandoverWards: function() {
        const select = $('#handover-filter-ward');
        $.ajax({
            url: this.routes.wards || '/wards',
            type: 'GET',
            success: function(response) {
                const wards = response.wards || response.data || response;
                if (Array.isArray(wards)) {
                    wards.forEach(function(ward) {
                        select.append(`<option value="${ward.id}">${ward.name}</option>`);
                    });
                }
            }
        });
    },

    // Load handovers cards with backend processing
    loadHandoversCards: function() {
        const self = this;
        const container = $('#handover-cards-grid');
        const loadingEl = $('#handover-loading');
        const emptyEl = $('#handover-empty');

        // Show loading state
        container.html('');
        loadingEl.show();
        emptyEl.hide();

        // Build filter params
        const params = {
            page: this.handoverCurrentPage,
            per_page: this.handoverPerPage,
            ward_id: $('#handover-filter-ward').val(),
            shift_type: $('#handover-filter-shift').val(),
            status: $('#handover-filter-status').val(),
            priority: $('#handover-filter-priority').val(),
            date_from: $('#handover-filter-from').val(),
            date_to: $('#handover-filter-to').val(),
            search: $('#handover-filter-search').val(),
            sort: $('#handover-filter-sort').val(),
            format: 'cards'
        };

        $.ajax({
            url: this.routes.handovers,
            type: 'GET',
            data: params,
            success: function(response) {
                loadingEl.hide();

                if (response.success && response.data && response.data.length> 0) {
                    self.renderHandoversCards(response.data);
                    self.updateHandoverStats(response.stats);
                    self.renderHandoverPagination(response.pagination);
                } else {
                    emptyEl.show();
                    self.updateHandoverStats({ total: 0, pending: 0, critical: 0 });
                    self.renderHandoverPagination(null);
                }
            },
            error: function() {
                loadingEl.hide();
                container.html(`
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="mdi mdi-alert-circle"></i> Failed to load handovers. Please try again.
                        </div>
                    </div>
                `);
            }
        });
    },

    // Render handover cards
    renderHandoversCards: function(handovers) {
        const self = this;
        const container = $('#handover-cards-grid');
        const viewMode = this.handoverViewMode;

        if (viewMode === 'list') {
            this.renderHandoversList(handovers);
            return;
        }

        let html = '';
        handovers.forEach(function(h) {
            const isCritical = h.has_critical_notes;
            const isPending = !h.is_acknowledged;
            const cardClasses = [
                'handover-card',
                isCritical ? 'critical' : '',
                isPending ? 'pending' : 'acknowledged'
            ].filter(Boolean).join(' ');

            const shiftBadgeClass = {
                'morning': 'morning',
                'afternoon': 'afternoon',
                'night': 'night'
            }[h.shift_type] || 'secondary';

            const shiftIcon = {
                'morning': '🌅',
                'afternoon': '☀️',
                'night': '🌙'
            }[h.shift_type] || '🔲';

            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="${cardClasses}" data-handover-id="${h.id}">
                        <div class="handover-card-header">
                            <span class="handover-card-shift-badge ${shiftBadgeClass}">
                                ${shiftIcon} ${h.shift_type_label || h.shift_type}
                            </span>
                            <span class="handover-card-time" title="${h.created_at_full}">
                                ${h.created_at_ago}
                            </span>
                        </div>
                        <div class="handover-card-body">
                            <div class="handover-card-meta">
                                <span class="handover-card-nurse">
                                    <i class="mdi mdi-account-nurse"></i> ${h.created_by_name}
                                </span>
                            </div>
                            <div class="handover-card-ward">
                                <i class="mdi mdi-hospital-building"></i> ${h.ward_name}
                            </div>
                            <div class="handover-card-summary">
                                ${h.summary_preview || '<span class="text-muted">No summary provided</span>'}
                            </div>
                            ${isCritical ? `
                                <div class="handover-card-critical-preview">
                                    <i class="mdi mdi-alert-circle"></i>
                                    <strong>Critical:</strong> ${h.critical_notes_preview || 'See details'}
                                </div>
                            ` : ''}
                            <div class="handover-card-stats">
                                ${isCritical ? '<span class="handover-card-stat danger"><i class="mdi mdi-alert"></i> Critical</span>' : ''}
                                ${h.pending_tasks_count> 0 ? `<span class="handover-card-stat warning"><i class="mdi mdi-clipboard-list"></i> ${h.pending_tasks_count} tasks</span>` : ''}
                                ${h.action_count ? `<span class="handover-card-stat"><i class="mdi mdi-chart-bar"></i> ${h.action_count} actions</span>` : ''}
                            </div>
                        </div>
                        <div class="handover-card-footer">
                            <span class="handover-card-status ${isPending ? 'pending' : 'acknowledged'}">
                                ${isPending ? '<i class="mdi mdi-clock-alert"></i> Pending' : '<i class="mdi mdi-check-circle"></i> Acknowledged'}
                            </span>
                            <div class="handover-card-actions">
                                <button class="btn btn-sm btn-outline-primary view-handover" data-id="${h.id}" title="View Details">
                                    <i class="mdi mdi-eye"></i>
                                </button>
                                ${isPending ? `
                                    <button class="btn btn-sm btn-success acknowledge-handover" data-id="${h.id}" title="Acknowledge">
                                        <i class="mdi mdi-check"></i>
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        container.html(html);
    },

    // Render handovers as list
    renderHandoversList: function(handovers) {
        const container = $('#handover-cards-grid');
        let html = '<div class="col-12">';

        handovers.forEach(function(h) {
            const isCritical = h.has_critical_notes;
            const isPending = !h.is_acknowledged;

            html += `
                <div class="handover-list-item ${isCritical ? 'critical' : ''}" data-handover-id="${h.id}">
                    <div class="handover-list-shift">
                        <span class="badge badge-${h.shift_type === 'morning' ? 'warning' : h.shift_type === 'afternoon' ? 'info' : 'dark'}">
                            ${h.shift_type_label || h.shift_type}
                        </span>
                    </div>
                    <div class="handover-list-info">
                        <div class="handover-list-meta">
                            <strong>${h.created_by_name}</strong>
                            <span class="text-muted">•</span>
                            <span class="text-muted">${h.ward_name}</span>
                            <span class="text-muted">•</span>
                            <small class="text-muted">${h.created_at_ago}</small>
                            ${isCritical ? '<span class="badge badge-danger ms-2">Critical</span>' : ''}
                        </div>
                        <div class="handover-list-summary">
                            ${h.summary_preview || 'No summary'}
                        </div>
                    </div>
                    <div class="handover-list-status">
                        ${isPending
                            ? '<span class="badge badge-warning">Pending</span>'
                            : '<span class="badge badge-success">Acknowledged</span>'}
                    </div>
                    <div class="handover-list-actions">
                        <button class="btn btn-sm btn-outline-primary view-handover" data-id="${h.id}">
                            <i class="mdi mdi-eye"></i>
                        </button>
                        ${isPending ? `
                            <button class="btn btn-sm btn-success acknowledge-handover ms-1" data-id="${h.id}">
                                <i class="mdi mdi-check"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.html(html);
    },

    // Update handover stats display
    updateHandoverStats: function(stats) {
        $('#handover-total-count span').text(stats.total || 0);
        $('#handover-pending-count span').text(stats.pending || 0);
        $('#handover-critical-count span').text(stats.critical || 0);
    },

    // Render pagination
    renderHandoverPagination: function(pagination) {
        const self = this;
        const container = $('#handover-pagination-list');
        const pageInfo = $('#handover-page-info');

        if (!pagination || pagination.total_pages <= 1) {
            container.html('');
            pageInfo.text('');
            return;
        }

        pageInfo.text(`Page ${pagination.current_page} of ${pagination.total_pages}`);

        let html = '';

        // Previous button
        html += `
            <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.current_page - 1}">
                    <i class="mdi mdi-chevron-left"></i>
                </a>
            </li>
        `;

        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

        if (startPage> 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
            if (startPage> 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `
                <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        }

        if (endPage < pagination.total_pages) {
            if (endPage < pagination.total_pages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${pagination.total_pages}">${pagination.total_pages}</a></li>`;
        }

        // Next button
        html += `
            <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.current_page + 1}">
                    <i class="mdi mdi-chevron-right"></i>
                </a>
            </li>
        `;

        container.html(html);
    },

    // Reload handovers with current filters
    reloadHandoversCards: function() {
        this.handoverCurrentPage = 1;
        this.loadHandoversCards();
    },

    // Clear all handover filters
    clearHandoverFilters: function() {
        $('#handover-filter-ward').val('');
        $('#handover-filter-shift').val('');
        $('#handover-filter-status').val('');
        $('#handover-filter-priority').val('');
        $('#handover-filter-from').val('');
        $('#handover-filter-to').val('');
        $('#handover-filter-search').val('');
        $('#handover-filter-sort').val('newest');
        this.reloadHandoversCards();
    },

    // Show handover detail
    showHandoverDetail: function(id) {
        const self = this;

        $.ajax({
            url: this.routes.handoverDetail + '/' + id,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    self.currentHandoverDetail = response.handover;
                    self.renderHandoverDetail(response.handover);

                    // Show/hide acknowledge button
                    if (!response.handover.is_acknowledged) {
                        $('#acknowledge-handover-detail-btn').show();
                    } else {
                        $('#acknowledge-handover-detail-btn').hide();
                    }

                    $('#handoverDetailModal').modal('show');
                } else {
                    toastr.error('Failed to load handover details');
                }
            },
            error: function() {
                toastr.error('Failed to load handover details');
            }
        });
    },

    // Render handover detail content
    renderHandoverDetail: function(h) {
        let pendingTasksHtml = '';
        if (h.pending_tasks && h.pending_tasks.length> 0) {
            pendingTasksHtml = '<ul class="list-group list-group-flush">';
            h.pending_tasks.forEach(function(task) {
                const priorityColors = { low: 'secondary', normal: 'primary', high: 'warning', urgent: 'danger' };
                pendingTasksHtml += `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        ${task.description}
                        <span class="badge badge-${priorityColors[task.priority] || 'secondary'}">${task.priority || 'normal'}</span>
                    </li>
                `;
            });
            pendingTasksHtml += '</ul>';
        } else {
            pendingTasksHtml = '<p class="text-muted">No pending tasks</p>';
        }

        // Build action summary HTML with icons and colors
        let actionSummaryHtml = '';
        if (h.action_summary && Object.keys(h.action_summary).length> 0) {
            actionSummaryHtml = '<div class="row text-center mt-3">';
            for (const [key, value] of Object.entries(h.action_summary)) {
                const icon = value.icon || 'mdi-checkbox-blank-circle';
                const color = value.color || 'secondary';
                const count = value.count || 0;
                const label = value.label || key;
                actionSummaryHtml += `
                    <div class="col-4 col-md-3 mb-2">
                        <div class="stat-box p-2 border rounded">
                            <i class="mdi ${icon} text-${color}" style="font-size: 1.5rem;"></i>
                            <div class="stat-value h5 mb-0">${count}</div>
                            <div class="stat-label small text-muted">${label}</div>
                        </div>
                    </div>
                `;
            }
            actionSummaryHtml += '</div>';
        }

        // Build patient highlights HTML
        let patientHighlightsHtml = '';
        if (h.patient_highlights && h.patient_highlights.length> 0) {
            patientHighlightsHtml = `
                <div class="mt-4">
                    <h6><i class="mdi mdi-account-group"></i> Patient Activity Summary</h6>
                    <div class="accordion" id="patientHighlightsAccordion">
            `;

            h.patient_highlights.forEach(function(patient, idx) {
                const collapseId = `patientCollapse${idx}`;
                patientHighlightsHtml += `
                    <div class="card-modern mb-2">
                        <div class="card-header p-2" id="heading${idx}">
                            <h6 class="mb-0">
                                <button class="btn btn-link btn-sm w-100 text-left d-flex justify-content-between align-items-center" type="button" data-toggle="collapse" data-target="#${collapseId}">
                                    <span>
                                        <i class="mdi mdi-account"></i> ${patient.patient_name}
                                        <span class="text-muted ml-2">(${patient.patient_no || 'N/A'})</span>
                                    </span>
                                    <span class="badge badge-primary badge-pill">${patient.total_events} events</span>
                                </button>
                            </h6>
                        </div>
                        <div id="${collapseId}" class="collapse${idx === 0 ? ' show' : ''}" data-parent="#patientHighlightsAccordion">
                            <div class="card-body p-2">
                                <ul class="list-unstyled mb-0">
                `;

                if (patient.activities && patient.activities.length> 0) {
                    patient.activities.forEach(function(activity) {
                        patientHighlightsHtml += `
                            <li class="mb-1">
                                <i class="mdi ${activity.icon || 'mdi-circle'} text-${activity.color || 'secondary'} mr-1"></i>
                                <span class="text-muted">${activity.label}:</span>
                                <strong>${activity.count}</strong>
                                ${activity.events && activity.events.length> 0 ?
                                    `<span class="text-muted small">(${activity.events.slice(0, 3).join(', ')}${activity.events.length> 3 ? '...' : ''})</span>`
                                    : ''
                                }
                            </li>
                        `;
                    });
                }

                patientHighlightsHtml += `
                                </ul>
                            </div>
                        </div>
                    </div>
                `;
            });

            patientHighlightsHtml += '</div></div>';
        }

        // Build audit details HTML (detailed changes)
        let auditDetailsHtml = '';
        if (h.audit_details && h.audit_details.length> 0) {
            auditDetailsHtml = `
                <div class="mt-4">
                    <h6><i class="mdi mdi-history"></i> Detailed Activity Log <small class="text-muted">(${h.audit_details.length} changes)</small></h6>
                    <div class="audit-details-list" style="max-height: 400px; overflow-y: auto;">
            `;

            // Group by patient
            const byPatient = {};
            h.audit_details.forEach(function(detail) {
                const patientKey = detail.patient_name || 'General';
                if (!byPatient[patientKey]) {
                    byPatient[patientKey] = [];
                }
                byPatient[patientKey].push(detail);
            });

            for (const [patient, details] of Object.entries(byPatient)) {
                auditDetailsHtml += `
                    <div class="audit-patient-group mb-3">
                        <h6 class="text-primary mb-2">
                            <i class="mdi mdi-account"></i> ${patient}
                        </h6>
                        <div class="audit-items pl-3 border-left">
                `;

                details.forEach(function(detail) {
                    const eventBadge = detail.event === 'created'
                        ? '<span class="badge badge-success badge-sm">New</span>'
                        : detail.event === 'updated'
                        ? '<span class="badge badge-warning badge-sm">Updated</span>'
                        : '<span class="badge badge-danger badge-sm">Deleted</span>';

                    let changesHtml = '<ul class="list-unstyled mb-0 pl-3 small">';
                    if (detail.changes && detail.changes.length> 0) {
                        detail.changes.forEach(function(change) {
                            if (change.type === 'created') {
                                changesHtml += '<li><span class="text-muted">' + change.label + ':</span> <strong>' + change.value + '</strong></li>';
                            } else if (change.type === 'changed') {
                                changesHtml += '<li><span class="text-muted">' + change.label + ':</span> <del class="text-danger">' + change.old + '</del> → <strong class="text-success">' + change.new + '</strong></li>';
                            } else if (change.type === 'deleted') {
                                changesHtml += '<li><span class="text-muted">' + change.label + ':</span> <del class="text-danger">' + change.value + '</del></li>';
                            }
                        });
                    }
                    changesHtml += '</ul>';

                    auditDetailsHtml += `
                        <div class="audit-item mb-2 p-2 bg-light rounded">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <i class="mdi ${detail.icon} text-${detail.color}"></i>
                                    <strong class="ml-1">${detail.category}</strong>
                                    ${eventBadge}
                                </div>
                                <small class="text-muted">${detail.time}</small>
                            </div>
                            ${changesHtml}
                        </div>
                    `;
                });

                auditDetailsHtml += '</div></div>';
            }

            auditDetailsHtml += '</div></div>';
        }

        const html = `
            <div class="handover-detail">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        ${h.shift_type_badge}
                        <span class="ml-2">${h.ward_name}</span>
                    </div>
                    ${h.status_badge}
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <small class="text-muted">Created By</small>
                        <div><strong>${h.created_by.name}</strong></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Date/Time</small>
                        <div>${h.created_at} <span class="text-muted">(${h.created_at_ago})</span></div>
                    </div>
                </div>

                ${h.shift_duration ? `<div class="mb-3"><small class="text-muted">Shift Duration</small><div>${h.shift_duration}</div></div>` : ''}

                ${actionSummaryHtml ? `
                    <div class="mt-3">
                        <h6><i class="mdi mdi-chart-bar"></i> Activity Summary</h6>
                        ${actionSummaryHtml}
                    </div>
                ` : ''}

                ${h.critical_notes ? `
                    <div class="alert alert-danger mt-4">
                        <h6 class="alert-heading"><i class="mdi mdi-alert"></i> Critical Notes</h6>
                        <div>${h.critical_notes}</div>
                    </div>
                ` : ''}

                <div class="mt-4">
                    <h6><i class="mdi mdi-clipboard-text"></i> Summary</h6>
                    <div class="bg-light p-3 rounded">${h.summary || '<em>No summary provided</em>'}</div>
                </div>

                ${h.concluding_notes ? `
                    <div class="mt-4">
                        <h6><i class="mdi mdi-note-text"></i> Concluding Notes</h6>
                        <div class="bg-light p-3 rounded">${h.concluding_notes}</div>
                    </div>
                ` : ''}

                ${patientHighlightsHtml}

                ${auditDetailsHtml}

                <div class="mt-4">
                    <h6><i class="mdi mdi-format-list-checks"></i> Pending Tasks</h6>
                    ${pendingTasksHtml}
                </div>

                ${h.is_acknowledged ? `
                    <div class="mt-4 alert alert-success">
                        <i class="mdi mdi-check-circle"></i> Acknowledged by <strong>${h.acknowledged_by_name}</strong> on ${h.acknowledged_at}
                    </div>
                ` : ''}
            </div>
        `;

        $('#handover-detail-content').html(html);
    },

    // Acknowledge handover
    acknowledgeHandover: function(id, fromDetail = false) {
        const self = this;

        $.ajax({
            url: this.routes.acknowledge.replace('{id}', id),
            type: 'POST',
            data: { _token: CSRF_TOKEN },
            success: function(response) {
                if (response.success) {
                    toastr.success('Handover acknowledged');

                    if (fromDetail) {
                        $('#acknowledge-handover-detail-btn').hide();
                        self.currentHandoverDetail.is_acknowledged = true;
                        self.currentHandoverDetail.acknowledged_at = response.acknowledged_at;
                    }

                    // Reload cards list
                    self.loadHandoversCards();
                } else {
                    toastr.error(response.message || 'Failed to acknowledge');
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to acknowledge');
            }
        });
    },

    // Show shift summary
    showShiftSummary: function() {
        if (!this.activeShift) return;

        const self = this;

        // Load actions for current shift
        $.ajax({
            url: this.routes.actions,
            type: 'GET',
            success: function(response) {
                self.renderShiftSummary(response);
                $('#shiftSummaryModal').modal('show');
            },
            error: function() {
                // Still show basic summary
                self.renderShiftSummary({ actions: {}, total: 0 });
                $('#shiftSummaryModal').modal('show');
            }
        });
    },

    // Render shift summary
    renderShiftSummary: function(data) {
        const shift = this.activeShift;
        const counters = shift.counters || {};

        let actionsHtml = '';
        if (data.actions && Object.keys(data.actions).length> 0) {
            actionsHtml = '<div class="mt-4"><h6>Actions by Type</h6><div class="list-group">';
            for (const [type, info] of Object.entries(data.actions)) {
                actionsHtml += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="mdi ${info.config.icon} text-${info.config.color}"></i> ${info.config.label}</span>
                        <span class="badge badge-${info.config.color}">${info.count}</span>
                    </div>
                `;
            }
            actionsHtml += '</div></div>';
        }

        const html = `
            <div class="shift-summary-content">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-clock-outline text-primary mr-2" style="font-size: 2rem;"></i>
                            <div>
                                <div class="text-muted small">Shift Started</div>
                                <strong>${shift.started_at_full}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-timer-outline text-info mr-2" style="font-size: 2rem;"></i>
                            <div>
                                <div class="text-muted small">Elapsed Time</div>
                                <strong id="summary-elapsed-time">${shift.elapsed_time}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="text-muted small">Shift Type</div>
                        <span class="badge badge-info">${shift.shift_type_label}</span>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Ward</div>
                        <strong>${shift.ward_name}</strong>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Scheduled End</div>
                        <strong>${shift.scheduled_end || 'N/A'}</strong>
                    </div>
                </div>

                <h6 class="text-muted mb-3">Activity Summary</h6>
                <div class="row text-center">
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-danger">${counters.vitals || 0}</div>
                            <div class="stat-label">Vitals</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-warning">${counters.medications || 0}</div>
                            <div class="stat-label">Medications</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-info">${counters.injections || 0}</div>
                            <div class="stat-label">Injections</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-success">${counters.immunizations || 0}</div>
                            <div class="stat-label">Immunizations</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-primary">${counters.notes || 0}</div>
                            <div class="stat-label">Notes</div>
                        </div>
                    </div>
                </div>

                ${actionsHtml}

                <div class="mt-4 text-center">
                    <div class="h4 text-primary">${shift.total_actions || 0}</div>
                    <div class="text-muted">Total Actions This Shift</div>
                </div>
            </div>
        `;

        $('#shift-summary-content').html(html);
    },

    // Start shift timer
    startShiftTimer: function() {
        const self = this;

        if (this.shiftTimer) {
            clearInterval(this.shiftTimer);
        }

        this.updateFabDisplay();

        this.shiftTimer = setInterval(function() {
            if (self.activeShift) {
                self.activeShift.elapsed_seconds = (self.activeShift.elapsed_seconds || 0) + 1;
                self.updateFabDisplay();

                // Check for overdue
                if (self.activeShift.remaining_seconds !== null) {
                    self.activeShift.remaining_seconds = Math.max(0, (self.activeShift.remaining_seconds || 0) - 1);
                }
            }
        }, 1000);
    },

    // Stop shift timer
    stopShiftTimer: function() {
        if (this.shiftTimer) {
            clearInterval(this.shiftTimer);
            this.shiftTimer = null;
        }
    },

    // Update FAB display
    updateFabDisplay: function() {
        if (!this.activeShift) return;

        const elapsed = this.activeShift.elapsed_seconds || 0;
        $('#shift-elapsed-time').text(this.formatElapsedTime(elapsed));

        // Check if overdue (past max shift duration)
        if (this.activeShift.is_overdue || elapsed> 12 * 3600) {
            $('.shift-fab-timer').addClass('overdue');
        } else {
            $('.shift-fab-timer').removeClass('overdue');
        }

        // Update FAB button state
        $('#shift-fab-btn').addClass('active');
    },

    // Format elapsed time
    formatElapsedTime: function(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours> 0) {
            return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
        return `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    },

    // Toggle FAB actions
    toggleFabActions: function() {
        const actions = $('.shift-fab-actions');
        if (actions.is(':visible')) {
            actions.slideUp(200);
        } else {
            actions.slideDown(200);
        }
    },

    // Make FAB draggable
    makeFabDraggable: function() {
        const fab = document.getElementById('shift-control-fab');
        if (!fab) return;

        let isDragging = false;
        let startX, startY, startLeft, startBottom;

        fab.addEventListener('mousedown', startDrag);
        fab.addEventListener('touchstart', startDrag, { passive: false });

        function startDrag(e) {
            if (e.target.tagName === 'BUTTON') return; // Don't drag when clicking buttons

            isDragging = true;
            const rect = fab.getBoundingClientRect();

            if (e.type === 'touchstart') {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            } else {
                startX = e.clientX;
                startY = e.clientY;
            }

            startLeft = rect.left;
            startBottom = window.innerHeight - rect.bottom;

            document.addEventListener('mousemove', drag);
            document.addEventListener('touchmove', drag, { passive: false });
            document.addEventListener('mouseup', stopDrag);
            document.addEventListener('touchend', stopDrag);
        }

        function drag(e) {
            if (!isDragging) return;
            e.preventDefault();

            let clientX, clientY;
            if (e.type === 'touchmove') {
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            } else {
                clientX = e.clientX;
                clientY = e.clientY;
            }

            const deltaX = clientX - startX;
            const deltaY = startY - clientY;

            const newRight = window.innerWidth - (startLeft + fab.offsetWidth + deltaX);
            const newBottom = startBottom + deltaY;

            // Keep within bounds
            fab.style.right = Math.max(10, Math.min(window.innerWidth - fab.offsetWidth - 10, newRight)) + 'px';
            fab.style.bottom = Math.max(10, Math.min(window.innerHeight - fab.offsetHeight - 10, newBottom)) + 'px';
        }

        function stopDrag() {
            isDragging = false;
            document.removeEventListener('mousemove', drag);
            document.removeEventListener('touchmove', drag);
            document.removeEventListener('mouseup', stopDrag);
            document.removeEventListener('touchend', stopDrag);
        }
    }
};

// Initialize Shift Manager on document ready
$(document).ready(function() {
    ShiftManager.init();
});
</script>

{{-- Transfer to Ward Modal --}}
<div class="modal fade" id="transferWardModal" tabindex="-1" aria-labelledby="transferWardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title" id="transferWardModalLabel">
                    <i class="mdi mdi-swap-horizontal"></i> Transfer to Ward
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="transfer-ward-admission-id">
                <div class="alert alert-info py-2 mb-3">
                    <i class="mdi mdi-account"></i> Transferring: <strong id="transfer-ward-patient-name"></strong>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Target Bed <span class="text-danger">*</span></label>
                    <select class="form-select" id="transfer-ward-bed-select">
                        <option value="">-- Loading... --</option>
                    </select>
                    <small class="text-muted">Patient will be moved from their current emergency bed to the selected bed.</small>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="transfer-ward-submit-btn" onclick="submitWardTransfer()">
                    <i class="mdi mdi-check"></i> Transfer Patient
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Emergency Intake Modal (replaced by unified patient-form-modal emergency mode) --}}
{{-- @include('admin.partials.emergency-intake-modal') --}}

{{-- ================================================================
     Clinical Request Delete Confirmation Modal (for history tabs)
     ================================================================ --}}
<div class="modal fade" id="crDeleteConfirmModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fa fa-trash"></i> Delete Clinical Request</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fa fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action will soft-delete the request. It can be restored later.
                </div>
                <div class="mb-3" id="crDeleteItemInfo"></div>
                <div class="form-group">
                    <label>Reason for Deletion <span class="text-danger">*</span></label>
                    <select class="form-control mb-2" id="crDeletionReasonSelect">
                        <option value="">-- Select a reason --</option>
                        <option value="Duplicate request">Duplicate request</option>
                        <option value="Entered in error">Entered in error</option>
                        <option value="Patient refused">Patient refused</option>
                        <option value="No longer needed">No longer needed</option>
                        <option value="Doctor changed order">Doctor changed order</option>
                        <option value="Other">Other (specify below)</option>
                    </select>
                    <textarea class="form-control d-none" id="crDeletionReasonOther" rows="2"
                              placeholder="Please specify the reason..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" id="crConfirmDeleteBtn">
                    <i class="fa fa-trash-alt"></i> Delete Request
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    var crCurrentDeleteItem = null;

    // Toggle "Other" textarea when "Other" is selected
    $('#crDeletionReasonSelect').on('change', function() {
        if ($(this).val() === 'Other') {
            $('#crDeletionReasonOther').removeClass('d-none');
        } else {
            $('#crDeletionReasonOther').addClass('d-none').val('');
        }
    });

    /**
     * Delete a nurse-created clinical request from history.
     * Uses the nursing-workbench DELETE routes.
     */
    window.deleteNurseClinicalRequest = function(type, id, name) {
        var pathMap = {
            lab:          'labs',
            imaging:      'imaging',
            prescription: 'prescriptions',
            procedure:    'procedures'
        };
        ClinicalOrdersKit.showDeleteConfirmation({
            type: type,
            itemName: name,
            onConfirm: function (reason, callback) {
                $.ajax({
                    url: '{{ url('/nursing-workbench/clinical-requests') }}/' + pathMap[type] + '/' + id,
                    type: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { reason: reason },
                    success: function(response) {
                        callback(true);
                        if (response.success) {
                            toastr.success(response.message || 'Request deleted successfully');
                            var tableMap = {
                                lab:          '#cr_lab_history_list',
                                imaging:      '#cr_imaging_history_list',
                                prescription: '#cr_presc_history_list',
                                procedure:    '#cr_proc_history_list'
                            };
                            var tableId = tableMap[type];
                            if (tableId && $.fn.DataTable.isDataTable(tableId)) {
                                $(tableId).DataTable().ajax.reload();
                            }
                        }
                    },
                    error: function(xhr) {
                        callback(false);
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to delete request';
                        toastr.error(msg);
                    }
                });
            }
        });
    };
})();
</script>

<!-- Store Context Override Modal -->
<div class="modal fade" id="storeContextOverrideModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0"><i class="fas fa-exchange-alt me-1"></i> Change Active Store</h6>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body pb-2">
                <label class="form-label small fw-bold mb-1">Select Store</label>
                <select id="ctx-store-select" class="form-select form-select-sm">
                    <option value="">-- Select --</option>
                    @foreach($stores as $ctxStore)
                    <option value="{{ $ctxStore->id }}" {{ $resolvedStore && $resolvedStore->id === $ctxStore->id ? 'selected' : '' }}>
                        {{ $ctxStore->store_name }}
                    </option>
                    @endforeach
                </select>
                <div id="ctx-override-error" class="text-danger small mt-1 d-none"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="ctx-override-btn" class="btn btn-primary btn-sm" onclick="confirmStoreContextOverride()">
                    <i class="fas fa-check me-1"></i> Apply
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openStoreContextOverride() {
    $('#storeContextOverrideModal').modal('show');
}
function confirmStoreContextOverride() {
    const storeId = $('#ctx-store-select').val();
    if (!storeId) {
        $('#ctx-override-error').text('Please select a store.').removeClass('d-none');
        return;
    }
    $('#ctx-override-error').addClass('d-none');
    $('#ctx-override-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Applying...');
    $.ajax({
        url: '{{ route("store-context.set") }}',
        method: 'POST',
        data: { store_id: storeId, context: 'ward', _token: '{{ csrf_token() }}' },
        success: function () { window.location.reload(); },
        error: function (xhr) {
            const msg = xhr.responseJSON?.message ?? 'Failed to update store context.';
            $('#ctx-override-error').text(msg).removeClass('d-none');
            $('#ctx-override-btn').prop('disabled', false).html('<i class="fas fa-check me-1"></i> Apply');
        }
    });
}
</script>

@include('admin.partials.bundle_view_modal')
@include('admin.partials.bundle_remove_modal')
<script>
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
                        if ($.fn.DataTable.isDataTable('#cr_presc_history_list')) { $('#cr_presc_history_list').DataTable().ajax.reload(null, false); }
                        if ($.fn.DataTable.isDataTable('#cr_lab_history_list')) { $('#cr_lab_history_list').DataTable().ajax.reload(null, false); }
                        if ($.fn.DataTable.isDataTable('#cr_imaging_history_list')) { $('#cr_imaging_history_list').DataTable().ajax.reload(null, false); }
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
</script>

@include('admin.partials.clinical_alerts_modal')
@include('admin.partials.patient_summary_overlay')
@include('admin.partials.ai_quick_actions')
@include('admin.partials.hospital_contacts_modal')
@include('admin.partials.price_list_modal')
<script src="{{ asset('js/clinical-alerts-shared.js') }}"></script>
<script src="{{ asset('js/patient-summary.js') }}"></script>

@endsection


