// Global state
let currentPatient = null;
let currentPatientData = null; // Store full patient data including allergies
let queueRefreshInterval = null;
let vitalTooltip = null;
let queueDataTable = null;
let currentQueueFilter = 'admitted';
var medicationChartPrescribedRoute = wbUrl('/nursing-workbench/medication/prescribed-drugs/' + patientId);
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
            url = wbRoute('nursing-workbench_admitted-patients', '/nursing-workbench/admitted-patients');
            handler = displayAdmittedPatientsQueue;
            break;
        case 'vitals':
            url = wbRoute('nursing-workbench_vitals-queue', '/nursing-workbench/vitals-queue');
            handler = displayVitalsQueue;
            break;
        case 'bed-requests':
            url = wbRoute('nursing-workbench_bed-requests-queue', '/nursing-workbench/bed-requests-queue');
            handler = displayBedRequestsQueue;
            break;
        case 'discharge-requests':
            url = wbRoute('nursing-workbench_discharge-queue', '/nursing-workbench/discharge-queue');
            handler = displayDischargeRequestsQueue;
            break;
        case 'medication-due':
            url = wbRoute('nursing-workbench_medication-due', '/nursing-workbench/medication-due');
            handler = displayMedicationDueQueue;
            break;
        case 'emergency':
            url = wbRoute('emergency_queue', '/emergency/queue');
            handler = displayEmergencyQueue;
            break;
        case 'deceased':
            url = '/nursing-workbench/deceased-queue';
            handler = displayDeceasedQueue;
            break;
        default:
            url = wbRoute('nursing-workbench_admitted-patients', '/nursing-workbench/admitted-patients');
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
        $.get(wbRoute('nursing-workbench_clinics', '/nursing-workbench/clinics'), function(data) {
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

            $.get(wbRoute('nursing-workbench_vitals-queue', '/nursing-workbench/vitals-queue'), { clinic_id: vitalsClinicFilter }, function(data) {
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
    $.get(wbRoute('nursing-workbench_vitals-queue', '/nursing-workbench/vitals-queue'), { clinic_id: vitalsClinicFilter }, function(data) {
        vitalsQueueData = data;
        renderVitalsQueueFull();
        toastr.success('Queue refreshed');
    });
}

/**
 * Load currently consulting patients with mini-timers
 */
function loadConsultingQueue() {
    $.get(wbRoute('nursing-workbench_consulting-queue', '/nursing-workbench/consulting-queue'), { clinic_id: vitalsClinicFilter }, function(data) {
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
        url: '${wbUrl('/nursing-workbench/deceased')}/' + recordId + '/last-office',
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
    $.get(wbRoute('nursing-workbench_ward-dashboard_available-beds', '/nursing-workbench/ward-dashboard/available-beds'), function(beds) {
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
        url: '${wbUrl("nursing-workbench")}/admission/' + admissionId + '/transfer-ward',
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
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
        url: wbUrl(`/nursing-workbench/patient/${patientId}/details`),
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

// =====================================
// CLINICAL REQUESTS MODULE
// =====================================
const ClinicalRequests = (function() {
    let patientId = null;
    let selectedProcedures = [];
    let crDoseStructuredMode = true; // Plan §2.2: structured is now the default
    const CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
    const procedureCategoryId = '';
    const investigationCategoryId = '';

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
                        url: '${wbUrl('/nursing-workbench/clinical-requests/prescriptions')}/' + recordId + '/dose',
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
            ajax: { url: '${wbUrl('/prescHistoryList')}/' + patientId, type: 'GET' },
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
            ajax: { url: '${wbUrl('/investigationHistoryList')}/' + patientId, type: 'GET' },
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
            ajax: { url: '${wbUrl('/imagingHistoryList')}/' + patientId, type: 'GET' },
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
            ajax: { url: '${wbUrl('/procedureHistoryList')}/' + patientId, type: 'GET' },
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
                url: wbUrl('/live-search-products'),
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
                inputVal: q, minLength: 2, url: wbUrl('/live-search-services'), data: reqData,
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
            url: wbUrl('/nursing-workbench/clinical-requests/add-prescription'),
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
            url: wbUrl('/nursing-workbench/clinical-requests/add-lab'),
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
            url: wbUrl('/nursing-workbench/clinical-requests/add-imaging'),
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
            url: wbUrl('/nursing-workbench/clinical-requests/add-procedure'),
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
            url: wbUrl('/nursing-workbench/clinical-requests/prescriptions'),
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
            url: wbUrl('/nursing-workbench/clinical-requests/labs'),
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
            url: wbUrl('/nursing-workbench/clinical-requests/imaging'),
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
            url: wbUrl('/nursing-workbench/clinical-requests/procedures'),
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

/* LAB-SPECIFIC - DISABLED
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
    $.get(wbRoute('nursing-workbench_queue-counts', '/nursing-workbench/queue-counts'), function(counts) {
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
        wbRoute('lab_saveResult', '/lab/saveResult')
    );
}

// Lab result edit (called from investigation history DataTable "Edit" button)
function editLabResult(obj) {
    const requestId = $(obj).data('id');
    InvestResultEntry.editResult(
        requestId,
        `/lab-workbench/lab-service-requests/${requestId}`,
        `/lab-workbench/lab-service-requests/${requestId}/attachments`,
        wbRoute('lab_saveResult', '/lab/saveResult')
    );
}

// Imaging result entry (called from imaging history DataTable "Enter Result" button)
function enterImagingResult(requestId) {
    window._investResultContext = { type: 'imaging', id: requestId };
    InvestResultEntry.enterResult(
        requestId,
        `/imaging-workbench/imaging-service-requests/${requestId}`,
        `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
        wbRoute('imaging_saveResult', '/imaging/saveResult')
    );
}

// Imaging result edit (called from imaging history DataTable "Edit" button)
function editImagingResult(obj) {
    const requestId = $(obj).data('id');
    InvestResultEntry.editResult(
        requestId,
        `/imaging-workbench/imaging-service-requests/${requestId}`,
        `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
        wbRoute('imaging_saveResult', '/imaging/saveResult')
    );
}

var _PI_LAB_REQ_APPROVAL = true;
var _PI_IMG_REQ_APPROVAL = true;
var _PI_DR_SELF_LAB      = true;
var _PI_NR_SELF_LAB      = true;
var _PI_DR_SELF_IMG      = true;
var _PI_NR_SELF_IMG      = true;

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
        url: wbRoute('lab_filterDoctors', '/lab/filterDoctors'),
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
        url: wbRoute('lab_filterHmos', '/lab/filterHmos'),
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
        url: wbRoute('lab_filterServices', '/lab/filterServices'),
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
        url: wbRoute('lab_statistics', '/lab/statistics'),
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
            url: wbRoute('lab_reports', '/lab/reports'),
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
        url: wbRoute('lab_statistics', '/lab/statistics'),
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
        url: wbUrl(`/nursing-workbench/patient/${patientId}/details`),
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
        url: wbUrl(`/patients/${patientId}/nurse-chart/medication`),
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
        url: wbRoute('nursing-workbench_admitted-patients', '/nursing-workbench/admitted-patients'),
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
        ajax: wbRoute('nursing-workbench_vitals-queue', '/nursing-workbench/vitals-queue'),
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
        ajax: wbRoute('nursing-workbench_bed-requests-queue', '/nursing-workbench/bed-requests-queue'),
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
            url: wbUrl('live-search-products'),
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
        url: wbRoute('nursing-workbench_product-batches', '/nursing-workbench/product-batches'),
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
        url: wbRoute('nursing-workbench_product-batches', '/nursing-workbench/product-batches'),
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
        url: wbUrl(`/pharmacy-workbench/product/${productId}/stock`),
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
                url: wbRoute('nursing-workbench_injection_administer', '/nursing-workbench/injection/administer'),
                method: 'POST',
                data: data,
                headers: {'X-CSRF-TOKEN': (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content'))},
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
            url: wbUrl(`/nursing-workbench/patient/${patientId}/injections`),
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
            url: wbUrl('live-search-products'),
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
        url: wbRoute('nursing-workbench_immunization_administer', '/nursing-workbench/immunization/administer'),
        method: 'POST',
        data: data,
        headers: {'X-CSRF-TOKEN': (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content'))},
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
        csrfToken: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
        currentPatientId: patientId,
        onScheduleReload: function() { loadImmunizationSchedule(patientId); },
        onHistoryReload: function() { loadImmunizationHistory(patientId); },
        storesHtml: $('#ctx-store-select').html() || '', // Or whatever provides store HTML if needed, but it's handled by modal
        productSearchUrl: wbRoute('nursing-workbench_search-products', '/nursing-workbench/search-products'),
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
        csrfToken: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
        getSaveUrl: function(patientId) {
            return wbRoute('nursing-workbench_notes_store', '/nursing-workbench/notes/store');
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

