// =============================================
// RECEPTION WORKBENCH JAVASCRIPT
// =============================================

// Global state
let currentPatient = null;
let currentPatientData = null;
let queueRefreshInterval = null;
let queueDataTable = null;
let visitHistoryTable = null;

// Cached reference data
let cachedClinics = [];
let cachedServices = { consultation: [], lab: [], imaging: [] };
let cachedProducts = [];
let cachedHmos = [];

// Patient Form Modal Config (shared partial)
window.patientFormConfig = {
    nextFileNumberUrl: '/reception/patient/next-file-number',
    checkFileNumberUrl: '/reception/patient/check-file-number',
    updateUrl: '/reception/patient/__ID__/update',
    registerUrl: wbRoute('reception.patient.quick-register', '/reception/patient/quick-register'),
    hmos: (window.WORKBENCH_CONFIG?.hmos || []),
    onSuccess: function(patientId, mode) {
        var newPatientId = patientId;
        if (newPatientId && typeof loadPatient === 'function') {
            loadPatient(newPatientId);
        }
    },
    onSelectExisting: function(patientId) {
        if (patientId && typeof loadPatient === 'function') {
            loadPatient(patientId);
        }
    }
};

$(document).ready(function() {
    // Initialize
    loadQueueCounts();
    startQueueRefresh();
    initializeEventListeners();
    loadReferenceData();

    // Auto-select patient from URL query parameter (e.g., from Patient list workbench button)
    const urlParams = new URLSearchParams(window.location.search);
    const patientId = urlParams.get('patient_id');
    if (patientId) {
        loadPatient(patientId);
    }

    // Auto-open queue from URL parameter (e.g., from dashboard queue widget click)
    const queueFilter = urlParams.get('queue_filter');
    if (queueFilter && ['waiting', 'vitals', 'consultation', 'admitted'].includes(queueFilter)) {
        setTimeout(function() { showQueue(queueFilter); }, 500);
    }

    // Auto-trigger action from URL parameter (e.g., from dashboard quick action click)
    const action = urlParams.get('action');
    if (action === 'new-patient' && typeof showPatientFormModal === 'function') {
        setTimeout(function() { showPatientFormModal('create'); }, 800);
    } else if (action === 'quick-register' && typeof showQuickRegisterModal === 'function') {
        setTimeout(function() { showQuickRegisterModal(); }, 800);
    }
});

// =============================================
// EVENT LISTENERS
// =============================================
function initializeEventListeners() {
    // Patient search (shared module with barcode support)
    PatientSearch.init();

    // Workspace tabs
    $('.workspace-tab').on('click', function() {
        const tab = $(this).data('tab');
        switchWorkspaceTab(tab);
    });

    // Navigation buttons (mobile)
    $('#btn-back-to-search').on('click', function() {
        $('#main-workspace').removeClass('active');
        $('#left-panel').removeClass('hidden');
    });

    $('#btn-view-work-pane').on('click', function() {
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');
    });

    $('#btn-toggle-search').on('click', function() {
        $('#left-panel').toggleClass('hidden');
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

    // Queue clinic filter
    $('#queue-clinic-filter').on('change', function() {
        if (queueDataTable) {
            queueDataTable.ajax.reload();
        }
    });

    // Quick actions
    $('#btn-new-patient').on('click', function() {
        showPatientFormModal('create');
    });

    $('#btn-quick-register').on('click', function() {
        showQuickRegisterModal();
    });

    $('#btn-today-stats').on('click', function() {
        showTodayStats();
    });

    // Ward Dashboard quick action
    $('#btn-ward-dashboard').on('click', function() {
        showWardDashboard();
    });

    $('#btn-close-ward-dashboard').on('click', function() {
        hideWardDashboard();
    });

    // Reports quick action
    $('#btn-view-reports').on('click', function() {
        showReports();
    });

    $('#btn-close-reports').on('click', function() {
        hideReports();
    });

    // Reports filter form
    $('#reports-filter-form').on('submit', function(e) {
        e.preventDefault();
        reloadReportsData();
    });

    $('#clear-report-filters').on('click', function() {
        $('#reports-filter-form')[0].reset();
        // Reset to default dates (this month)
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        $('#report-date-from').val(firstDay.toISOString().split('T')[0]);
        $('#report-date-to').val(today.toISOString().split('T')[0]);
        reloadReportsData();
    });

    // View patient from reports tables
    $(document).on('click', '.view-patient-btn', function() {
        const patientId = $(this).data('id');
        if (patientId) {
            hideReports();
            selectPatient(patientId);
        }
    });

    // Edit patient button - open edit modal
    $('#btn-edit-patient').on('click', function() {
        if (currentPatient && currentPatientData) {
            showPatientFormModal('edit', currentPatientData);
        }
    });

    // Print Hospital Card button
    $('#btn-print-card').on('click', function() {
        if (currentPatient && currentPatientData) {
            showHospitalCard(currentPatientData);
        }
    });

    // Print card button in modal
    $('#btn-print-card-now').on('click', function() {
        printHospitalCard();
    });

    // Card view tab switching (Front / Back / Combined)
    $(document).on('click', '#cardViewTabs .nav-link', function(e) {
        e.preventDefault();
        $('#cardViewTabs .nav-link').removeClass('active');
        $(this).addClass('active');
        var tab = $(this).data('card-tab');
        var $front = $('#hospital-card-preview');
        var $back = $('#hospital-card-back');
        if (tab === 'front') {
            $front.show();
            $back.hide();
        } else if (tab === 'back') {
            $front.hide();
            $back.show();
        } else {
            $front.show();
            $back.show();
        }
    });

    // Default to combined view when modal opens
    $('#hospitalCardModal').on('show.bs.modal', function() {
        $('#cardViewTabs .nav-link').removeClass('active');
        $('#cardViewTabs .nav-link[data-card-tab="combined"]').addClass('active');
        $('#hospital-card-preview, #hospital-card-back').show();
    });

    // Expand patient details button
    $('#btn-expand-patient').on('click', function() {
        $(this).toggleClass('expanded');
        $('#patient-details-expanded').toggleClass('show');
        const $text = $(this).find('.btn-expand-text');
        if ($(this).hasClass('expanded')) {
            $text.text('less biodata');
        } else {
            $text.text('more biodata');
        }
    });

    // Book Service tab - Clinic selection
    $('#booking-clinic').on('change', function() {
        const clinicId = $(this).val();
        loadDoctorsByClinic(clinicId);
        // Don't reset services when clinic changes - services are not clinic-specific
        // updateServicesByClinic(clinicId);
    });

    // Book Service - Service type selection
    $('input[name="service-type"]').on('change', function() {
        const type = $(this).val();
        updateServiceTypeUI(type);
    });

    // Book Service - Service selection for tariff preview
    $('#booking-service, #booking-doctor').on('change', function() {
        updateTariffPreview();
    });

    // Book Consultation form submit
    $('#booking-form').on('submit', function(e) {
        e.preventDefault();
        bookConsultation();
    });

    // Walk-in Sales tab - Search and selection
    initializeWalkinSales();

    // Walk-in search input
    $('#walkin-search').on('input', function() {
        const query = $(this).val().toLowerCase();
        const activeType = $('#walkin-subtabs .nav-link.active').data('type') || 'lab';
        searchWalkinServices(query, activeType);
    });

    // Walk-in subtab change
    $('#walkin-subtabs .nav-link').on('click', function(e) {
        e.preventDefault();
        const type = $(this).data('type');
        $('#walkin-subtabs .nav-link').removeClass('active');
        $(this).addClass('active');
        const query = $('#walkin-search').val().toLowerCase();
        searchWalkinServices(query, type);
    });

    // Quick Register form
    $('#quick-register-form').on('submit', function(e) {
        e.preventDefault();
        submitQuickRegister();
    });

    // HMO selection - show/hide HMO number field
    $('#quick-register-hmo').on('change', function() {
        const hmoId = $(this).val();
        if (hmoId) {
            $('#hmo-no-row').show();
        } else {
            $('#hmo-no-row').hide();
            $('#quick-register-hmo-no').val('');
        }
    });
}

// =============================================
// LOAD REFERENCE DATA
// =============================================
function loadReferenceData() {
    // Load clinics
    $.get(wbRoute('reception.clinics', '/reception/clinics'), function(data) {
        cachedClinics = Array.isArray(data) ? data : (data.clinics || []);
        populateClinicDropdowns();
    });

    // Load HMOs
    $.get(wbRoute('reception.hmos', '/reception/hmos'), function(data) {
        cachedHmos = Array.isArray(data) ? data : (data.hmos || []);
        populateHmoDropdown();
        // Keep shared patient form modal config in sync
        if (window.patientFormConfig) {
            window.patientFormConfig.hmos = cachedHmos;
        }
    });

    // Load consultation services
    $.get(wbRoute('reception.services.consultation', '/reception/services/consultation'), function(data) {
        cachedServices.consultation = Array.isArray(data) ? data : (data.services || []);
        populateConsultationServices();
    });

    // Load lab services
    $.get(wbRoute('reception.services.lab', '/reception/services/lab'), function(data) {
        cachedServices.lab = Array.isArray(data) ? data : (data.services || []);
    });

    // Load imaging services
    $.get(wbRoute('reception.services.imaging', '/reception/services/imaging'), function(data) {
        cachedServices.imaging = Array.isArray(data) ? data : (data.services || []);
    });

    // Load products
    $.get(wbRoute('reception.products', '/reception/products'), function(data) {
        cachedProducts = Array.isArray(data) ? data : (data.products || []);
    });
}

function populateClinicDropdowns() {
    const $bookClinic = $('#booking-clinic');
    const $queueClinic = $('#queue-clinic-filter');

    $bookClinic.empty().append('<option value="">Select Clinic</option>');
    $queueClinic.empty().append('<option value="">All Clinics</option>');

    cachedClinics.forEach(clinic => {
        const option = `<option value="${clinic.id}">${clinic.name}</option>`;
        $bookClinic.append(option);
        $queueClinic.append(option);
    });
}

function populateHmoDropdown() {
    // Group HMOs by scheme
    const hmosByScheme = {};
    cachedHmos.forEach(hmo => {
        const scheme = hmo.scheme || 'General';
        if (!hmosByScheme[scheme]) {
            hmosByScheme[scheme] = [];
        }
        hmosByScheme[scheme].push(hmo);
    });

    // Populate quick register HMO dropdown with optgroups
    const $hmoSelect = $('#quick-register-hmo');
    if ($hmoSelect.length) {
        $hmoSelect.empty().append('<option value="">No HMO (Private)</option>');

        Object.keys(hmosByScheme).sort().forEach(scheme => {
            const $optgroup = $(`<optgroup label="${scheme}"></optgroup>`);
            hmosByScheme[scheme].forEach(hmo => {
                $optgroup.append(`<option value="${hmo.id}">${hmo.name}</option>`);
            });
            $hmoSelect.append($optgroup);
        });
    }

    // Populate report HMO filter dropdown with optgroups
    const $reportHmoSelect = $('#report-hmo-filter');
    if ($reportHmoSelect.length) {
        $reportHmoSelect.empty().append('<option value="">All HMOs</option>');

        Object.keys(hmosByScheme).sort().forEach(scheme => {
            const $optgroup = $(`<optgroup label="${scheme}"></optgroup>`);
            hmosByScheme[scheme].forEach(hmo => {
                $optgroup.append(`<option value="${hmo.id}">${hmo.name}</option>`);
            });
            $reportHmoSelect.append($optgroup);
        });
    }
}

function loadDoctorsByClinic(clinicId) {
    const $doctorSelect = $('#booking-doctor');
    $doctorSelect.empty().append('<option value="">Select Doctor</option>');

    if (!clinicId) return;

    $.get(wbUrl(`reception/clinics/${clinicId}/doctors`), function(data) {
        const doctors = Array.isArray(data) ? data : (data.doctors || []);
        doctors.forEach(doctor => {
            $doctorSelect.append(`<option value="${doctor.id}">${doctor.name}</option>`);
        });
    });
}

function updateServicesByClinic(clinicId) {
    const $serviceSelect = $('#booking-service');

    $serviceSelect.empty().append('<option value="">Select Service</option>');

    // Always use consultation services for booking tab
    let services = cachedServices.consultation || [];

    services.forEach(service => {
        const price = service.price ? ` - ₦${parseFloat(service.price).toLocaleString()}` : '';
        $serviceSelect.append(`<option value="${service.id}" data-price="${service.price || 0}">${service.name}${price}</option>`);
    });
}

function populateConsultationServices() {
    const $serviceSelect = $('#booking-service');
    $serviceSelect.empty().append('<option value="">Select Service</option>');

    let services = cachedServices.consultation || [];

    services.forEach(service => {
        const price = service.price ? ` - ₦${parseFloat(service.price).toLocaleString()}` : '';
        $serviceSelect.append(`<option value="${service.id}" data-price="${service.price || 0}">${service.name}${price}</option>`);
    });
}

function updateServiceTypeUI(type) {
    // Update service dropdown based on type
    updateServicesByClinic($('#booking-clinic').val());

    // Show/hide doctor selection (only for consultation)
    if (type === 'consultation') {
        $('#doctor-selection-group').show();
    } else {
        $('#doctor-selection-group').hide();
    }

    // Update button text
    const buttonTexts = {
        consultation: 'Book Consultation',
        lab: 'Book Lab Test',
        imaging: 'Book Imaging'
    };
    $('#btn-book-consultation').html(`<i class="mdi mdi-check-circle"></i> ${buttonTexts[type] || 'Book Service'}`);

    updateTariffPreview();
}

// =============================================
// PATIENT SEARCH & LOAD
// =============================================
function loadPatient(patientId) {
    currentPatient = patientId;

    // Hide all views to prevent stacking
    hideAllViews();

    // Show patient workspace
    $('#workspace-content').show().addClass('active');
    $('#patient-header').addClass('active');

    // Show loading indicator
    $('#patient-name').html('<i class="mdi mdi-loading mdi-spin"></i> Loading...');
    $('#patient-meta').html('');

    // Mobile: Switch to work pane
    $('#left-panel').addClass('hidden');
    $('#main-workspace').addClass('active');

    // Load patient data
    $.ajax({
        url: wbUrl(`reception/patient/${patientId}`),
        method: 'GET',
        success: function(data) {
            currentPatientData = data.patient;
            displayPatientInfo(data.patient);

            // Initialize Clinical Alerts
            try { 
                $('#btn-manage-alerts').show();
                if(typeof ClinicalAlerts !== 'undefined') {
                    ClinicalAlerts.init(patientId, 'records_reception');
                }
            } catch(e) { console.error('ClinicalAlerts init error:', e); }

            // Display upcoming appointments / follow-up alerts
            displayUpcomingAppointments(data.upcoming_appointments || []);

            // Switch to profile tab by default
            switchWorkspaceTab('profile');

            // Initialize visit history DataTable
            initializeVisitHistoryTable(patientId);

            // Initialize service requests DataTable
            initializeServiceRequestsTable(patientId);

            // Load recent requests for walk-in cart
            loadRecentRequests();

            // Show Medical Reports button
            $('#btn-medical-reports').show().off('click').on('click', function() {
                openMedicalReportHistory(
                    patientId,
                    data.patient.name || '',
                    data.patient.file_no || ''
                );
            });
        },
        error: function(xhr) {
            console.error('Error loading patient:', xhr);
            toastr.error('Failed to load patient data');
        }
    });
}

function displayPatientInfo(patient) {
    $('#patient-name').text(patient.name);

    // Parse allergies
    let allergiesHtml = '';
    if (patient.allergies) {
        let allergies = patient.allergies;
        if (typeof allergies === 'string') {
            try {
                allergies = JSON.parse(allergies);
            } catch(e) {
                allergies = [];
            }
        }
        if (Array.isArray(allergies) && allergies.length> 0) {
            allergiesHtml = `
                <div class="patient-allergies">
                    <i class="mdi mdi-alert-circle text-danger"></i>
                    <span class="text-danger">Allergies: ${allergies.join(', ')}</span>
                </div>
            `;
        }
    }

    const metaHtml = `
        <div class="patient-meta-item">
            <i class="mdi mdi-card-account-details"></i>
            <span>File: ${patient.file_no}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-calendar"></i>
            <span>${patient.age || 'N/A'}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-gender-${patient.gender === 'Male' ? 'male' : 'female'}"></i>
            <span>${patient.gender}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-water"></i>
            <span>${patient.blood_group || 'N/A'} ${patient.genotype && patient.genotype !== 'N/A' ? '(' + patient.genotype + ')' : ''}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-phone"></i>
            <span>${patient.phone || 'N/A'}</span>
        </div>
        ${patient.hmo_name ? `
        <div class="patient-meta-item">
            <i class="mdi mdi-hospital-building"></i>
            <span>${patient.hmo_name} ${patient.hmo_category && patient.hmo_category !== 'N/A' ? '[' + patient.hmo_category + ']' : ''} ${patient.hmo_no ? '(' + patient.hmo_no + ')' : ''}</span>
        </div>
        ` : ''}
        ${allergiesHtml}
    `;

    $('#patient-meta').html(metaHtml);

    // Populate expanded patient details
    const expandedDetailsHtml = `
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-water"></i> Blood Group</div>
            <div class="patient-detail-value">${patient.blood_group || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-dna"></i> Genotype</div>
            <div class="patient-detail-value">${patient.genotype || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-phone"></i> Phone</div>
            <div class="patient-detail-value">${patient.phone || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-map-marker"></i> Address</div>
            <div class="patient-detail-value">${patient.address || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-flag"></i> Nationality</div>
            <div class="patient-detail-value">${patient.nationality || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-account-group"></i> Ethnicity</div>
            <div class="patient-detail-value">${patient.ethnicity || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-wheelchair-accessibility"></i> Disability</div>
            <div class="patient-detail-value">${patient.disability || 'No'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-hospital-building"></i> HMO</div>
            <div class="patient-detail-value">${patient.hmo_name || 'Private'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-tag"></i> HMO Category</div>
            <div class="patient-detail-value">${patient.hmo_category || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-card-account-details"></i> HMO Number</div>
            <div class="patient-detail-value">${patient.hmo_no || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-shield-account"></i> Insurance Scheme</div>
            <div class="patient-detail-value">${patient.insurance_scheme || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-account-heart"></i> Next of Kin</div>
            <div class="patient-detail-value">${patient.next_of_kin_name || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-phone-outline"></i> NOK Phone</div>
            <div class="patient-detail-value">${patient.next_of_kin_phone || 'N/A'}</div>
        </div>
        <div class="patient-detail-item">
            <div class="patient-detail-label"><i class="mdi mdi-map-marker-outline"></i> NOK Address</div>
            <div class="patient-detail-value">${patient.next_of_kin_address || 'N/A'}</div>
        </div>
        ${patient.medical_history ? `
        <div class="patient-detail-item full-width">
            <div class="patient-detail-label"><i class="mdi mdi-clipboard-text"></i> Medical History</div>
            <div class="patient-detail-value text-content">${patient.medical_history}</div>
        </div>
        ` : ''}
        ${patient.misc ? `
        <div class="patient-detail-item full-width">
            <div class="patient-detail-label"><i class="mdi mdi-note-text"></i> Additional Notes</div>
            <div class="patient-detail-value text-content">${patient.misc}</div>
        </div>
        ` : ''}
    `;
    $('#patient-details-grid').html(expandedDetailsHtml);

    // Update profile tab with patient details
    updateProfileTab(patient);
    // Render Family Booking Grid
    renderFamilyBookingGrid(patient);
}

/**
 * Display upcoming appointments & follow-up alerts for the loaded patient
 */
function displayUpcomingAppointments(appointments) {
    // Remove any previous alert
    $('#upcoming-appointments-alert').remove();

    if (!appointments || appointments.length === 0) return;

    // Filter today's appointments
    const todayAppts = appointments.filter(a => a.is_today);
    const futureAppts = appointments.filter(a => !a.is_today);
    const prepaidFollowUps = todayAppts.filter(a => a.is_prepaid_followup);

    let alertHtml = '';

    // Prepaid follow-up — highlight prominently
    if (prepaidFollowUps.length> 0) {
        alertHtml += `<div class="alert alert-success border-success mb-2 py-2 px-3 d-flex align-items-center gap-2" style="border-left: 4px solid #198754;">
            <i class="mdi mdi-calendar-check mdi-24px text-success"></i>
            <div class="flex-grow-1">
                <strong>Pre-paid Follow-up Today</strong><br>
                <small>${prepaidFollowUps.map(a => `${a.time} — ${a.clinic} (Dr. ${a.doctor})`).join('<br>')}</small>
            </div>
            <button class="btn btn-success btn-sm" onclick="quickCheckInFollowUp(${prepaidFollowUps[0].id})" title="Check-in without billing">
                <i class="mdi mdi-login-variant"></i> Check-In (No Billing)
            </button>
        </div>`;
    }

    // Other today appointments
    const otherToday = todayAppts.filter(a => !a.is_prepaid_followup);
    if (otherToday.length> 0) {
        alertHtml += `<div class="alert alert-info border-info mb-2 py-2 px-3" style="border-left: 4px solid #0dcaf0;">
            <i class="mdi mdi-calendar-today text-info"></i>
            <strong>Scheduled Today:</strong>
            <small>${otherToday.map(a => {
                let badge = a.is_follow_up ? '<span class="badge bg-info-subtle text-info ms-1">Follow-up</span>' : '';
                return `${a.time} — ${a.clinic} (Dr. ${a.doctor})${badge}`;
            }).join('<br>')}</small>
        </div>`;
    }

    // Future appointments (compact)
    if (futureAppts.length> 0) {
        alertHtml += `<div class="alert alert-light border mb-2 py-2 px-3" style="border-left: 4px solid #6c757d;">
            <i class="mdi mdi-calendar-range text-muted"></i>
            <strong>Upcoming:</strong>
            <small>${futureAppts.slice(0, 3).map(a => {
                let badge = a.is_follow_up ? '<span class="badge bg-info-subtle text-info ms-1">Follow-up</span>' : '';
                return `${a.date} ${a.time} — ${a.clinic}${badge}`;
            }).join('<br>')}${futureAppts.length> 3 ? '<br><em>+' + (futureAppts.length - 3) + ' more</em>' : ''}</small>
        </div>`;
    }

    if (alertHtml) {
        $('#patient-meta').after('<div id="upcoming-appointments-alert" class="mt-2">' + alertHtml + '</div>');
    }
}

/**
 * Quick check-in for a pre-paid follow-up appointment (no billing needed)
 */
function quickCheckInFollowUp(appointmentId) {
    if (!confirm('This is a pre-paid follow-up. Check in without billing?')) return;
    $.ajax({
        url: wbRoute('appointments.check-in', '/appointments/check-in').replace('__AID__', appointmentId),
        type: 'POST',
        data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) },
        success: function(res) {
            if (res.success) {
                toastr.success(res.message || 'Follow-up checked in successfully.');
                $('#upcoming-appointments-alert').remove();
                if (typeof loadQueueEntries === 'function') loadQueueEntries();
                if (typeof loadTodayQueueList === 'function') loadTodayQueueList();
            } else {
                toastr.error(res.message || 'Check-in failed.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Check-in failed.');
        }
    });
}

function updateProfileTab(patient) {
    // Populate Patient Information table
    let allergies = patient.allergies || [];
    if (typeof allergies === 'string') {
        try {
            allergies = JSON.parse(allergies);
        } catch(e) {
            allergies = [];
        }
    }

    const allergiesBadges = allergies.length> 0
        ? allergies.map(a => `<span class="badge badge-danger mr-1">${a}</span>`).join(' ')
        : '<span class="text-muted">No known allergies</span>';

    const profileInfoHtml = `
        <tr>
            <td class="text-muted" width="35%">File No:</td>
            <td><strong>${patient.file_no || 'N/A'}</strong></td>
        </tr>
        <tr>
            <td class="text-muted">Full Name:</td>
            <td>${patient.name || 'N/A'}</td>
        </tr>
        <tr>
            <td class="text-muted">Phone:</td>
            <td>${patient.phone || 'N/A'}</td>
        </tr>
        <tr>
            <td class="text-muted">Email:</td>
            <td>${patient.email || 'N/A'}</td>
        </tr>
        <tr>
            <td class="text-muted">Gender:</td>
            <td>${patient.gender || 'N/A'}</td>
        </tr>
        <tr>
            <td class="text-muted">Date of Birth:</td>
            <td>${patient.dob || 'N/A'}</td>
        </tr>
        <tr>
            <td class="text-muted">Age:</td>
            <td>${patient.age ? `${patient.age} years` : 'N/A'}</td>
        </tr>
        <tr>
            <td class="text-muted">Address:</td>
            <td>${patient.address || 'N/A'}</td>
        </tr>
        <tr>
            <td class="text-muted">Allergies:</td>
            <td>${allergiesBadges}</td>
        </tr>
    `;
    $('#profile-info-table').html(profileInfoHtml);

    // Populate HMO Information table
    const hmoInfoHtml = `
        <tr>
            <td class="text-muted" width="35%">HMO/Insurance:</td>
            <td><strong>${patient.hmo_name || '<span class="text-warning">Private (No HMO)</span>'}</strong></td>
        </tr>
        ${patient.hmo_no ? `
        <tr>
            <td class="text-muted">HMO Number:</td>
            <td>${patient.hmo_no}</td>
        </tr>
        ` : ''}
        ${patient.hmo_plan ? `
        <tr>
            <td class="text-muted">Plan:</td>
            <td>${patient.hmo_plan}</td>
        </tr>
        ` : ''}
        ${patient.company ? `
        <tr>
            <td class="text-muted">Company:</td>
            <td>${patient.company}</td>
        </tr>
        ` : ''}
    `;
    $('#profile-hmo-table').html(hmoInfoHtml);

    // Load current queue entries for this patient (both overview and booking tabs)
    loadPatientQueueEntries(patient.id);
    loadBookingQueueEntries(patient.id);
}

function renderQueueCards(entries) {
    if (!entries || entries.length === 0) {
        return '<p class="text-muted">No active queue entries</p>';
    }

    let html = '<div class="queue-entries-list" style="display:flex; flex-direction:column; gap:12px;">';
    entries.forEach(entry => {
        const statusClass = {
            1: 'badge-warning',
            2: 'badge-info',
            3: 'badge-primary',
            4: 'badge-success'
        }[entry.status] || 'badge-secondary';

        const statusText = {
            1: 'Waiting',
            2: 'Vitals Pending',
            3: 'In Consultation',
            4: 'Completed'
        }[entry.status] || 'Unknown';
        
        // Format initials
        const names = (entry.patient_name || '').split(' ');
        let initials = '?';
        if (names.length > 0 && names[0]) {
            initials = names.length > 1 && names[1] ? (names[0][0] + names[1][0]).toUpperCase() : names[0][0].toUpperCase();
        }
        
        // Background for initials
        const initialBg = entry.is_family ? '#6c757d' : 'var(--hospital-primary)';
        
        // Wait time string
        let waitTimeStr = entry.wait_time_mins > 60 ? Math.floor(entry.wait_time_mins / 60) + 'h ' + (entry.wait_time_mins % 60) + 'm' : entry.wait_time_mins + ' mins';

        // Payment / HMO Details
        let paymentBadge = '';
        let hmoDetails = '';
        
        if (entry.coverage_mode) {
            paymentBadge = `<span class="badge badge-info"><i class="mdi mdi-shield-check"></i> HMO Covered</span>`;
            hmoDetails = `<div class="mt-1" style="font-size:0.8rem; background:#f0f9ff; padding:4px 8px; border-radius:4px; border-left:3px solid #17a2b8;">
                <strong>Mode:</strong> ${entry.coverage_mode} 
                <span class="mx-1">|</span> 
                <strong>Claim:</strong> ₦${parseFloat(entry.claims_amount).toLocaleString()}
                ${entry.validation_status ? `<span class="mx-1">|</span> <strong>Auth:</strong> ${entry.validation_status}` : ''}
            </div>`;
        } else {
            paymentBadge = entry.is_paid 
                ? `<span class="badge badge-success"><i class="mdi mdi-check-circle"></i> Paid</span>` 
                : `<span class="badge badge-warning text-dark"><i class="mdi mdi-clock-outline"></i> Pending Payment</span>`;
        }
        
        const familyBadge = entry.is_family ? `<span class="badge badge-secondary ml-2" style="font-size:0.7rem;">Family Member</span>` : '';
        const serviceName = entry.service_name || 'Consultation';

        html += `
            <div class="card-modern shadow-sm border-0 mb-0" style="border-radius: 8px;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex">
                            <div class="mr-3 mt-1 d-flex justify-content-center align-items-center text-white font-weight-bold" 
                                style="width:40px; height:40px; border-radius:8px; background:${initialBg}; font-size:1.1rem;">
                                ${initials}
                            </div>
                            <div>
                                <h6 class="mb-0 font-weight-bold" style="font-size:1rem;">${entry.patient_name} ${familyBadge}</h6>
                                <div class="text-muted" style="font-size:0.85rem;">
                                    <i class="mdi mdi-identifier"></i> #${entry.patient_file_no} &bull; ${entry.patient_gender}, ${entry.patient_age} yrs
                                </div>
                                <div class="mt-2 text-dark font-weight-bold" style="font-size:0.9rem;">
                                    ${serviceName} 
                                    <span class="text-muted font-weight-normal ml-1">at ${entry.clinic_name}</span>
                                </div>
                                ${entry.doctor_name ? `<div class="text-muted" style="font-size:0.85rem;"><i class="mdi mdi-doctor"></i> Dr. ${entry.doctor_name}</div>` : ''}
                                ${hmoDetails}
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <div class="mb-2">
                                <h5 class="text-primary mb-0 font-weight-bold">Q-${entry.queue_no || 'N/A'}</h5>
                                <div class="text-muted" style="font-size:0.75rem;">${entry.appointment_type} &bull; ${entry.created_at}</div>
                            </div>
                            <div>
                                ${paymentBadge}
                            </div>
                            <div class="mt-1">
                                <span class="badge ${statusClass}">${statusText}</span>
                            </div>
                            <div class="mt-1 text-muted" style="font-size:0.75rem;">
                                <i class="mdi mdi-timer-sand"></i> Waiting ${waitTimeStr}
                            </div>
                            
                            <div class="mt-2 d-flex justify-content-end align-items-center">
                                <button class="btn btn-sm btn-outline-primary btn-print-routing mr-2" data-queue-id="${entry.id}" title="Print Routing Slip">
                                    <i class="mdi mdi-printer"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-delete-queue" data-queue-id="${entry.id}" data-service-request-id="${entry.service_request_id || ''}" title="Delete Booking">
                                    <i class="mdi mdi-trash-can"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    return html;
}

function loadPatientQueueEntries(patientId) {
    $.get(wbUrl(`reception/patient/${patientId}/queue`), function(data) {
        const $container = $('#current-queue-entries');
        const entries = Array.isArray(data) ? data : (data.entries || []);
        $container.html(renderQueueCards(entries));
    }).fail(function() {
        $('#current-queue-entries').html('<p class="text-muted text-danger">Failed to load queue entries</p>');
    });
}

// Load queue entries for the booking tab
function loadBookingQueueEntries(patientId) {
    $.get(wbUrl(`reception/patient/${patientId}/queue`), function(data) {
        const $container = $('#booking-current-queue');
        const entries = Array.isArray(data) ? data : (data.entries || []);
        $container.html(renderQueueCards(entries));
    }).fail(function() {
        $('#booking-current-queue').html('<p class="text-muted text-danger">Failed to load queue entries</p>');
    });
}



// =============================================
// WORKSPACE TABS
// =============================================
function switchWorkspaceTab(tab) {
    // Update tab buttons
    $('.workspace-tab').removeClass('active');
    $(`.workspace-tab[data-tab="${tab}"]`).addClass('active');

    // Update tab content
    $('.workspace-tab-content').removeClass('active');
    $(`#${tab}-tab`).addClass('active');

    // Tab-specific actions
    if (tab === 'history' && currentPatient) {
        if (visitHistoryTable) {
            visitHistoryTable.ajax.reload();
        }
    }

    if (tab === 'appointments' && currentPatient) {
        loadPatientAppointments(currentPatient);
    }

    if (tab === 'admissions' && currentPatient) {
        AdmissionModule.init(currentPatient, {
            container: '#admissions-tab',
            printTarget: 'self',
            onBadgeUpdate: function(count) {
                var badge = $('#reception-admissions-badge');
                badge.text(count);
                if (count> 0) badge.show(); else badge.hide();
            }
        });
    }
}

// =============================================
// QUEUE MANAGEMENT
// =============================================
function loadQueueCounts() {
    $.get(wbRoute('reception.queue-counts', '/reception/queue-counts'), function(counts) {
        $('#queue-waiting-count').text(counts.waiting || 0);
        $('#queue-vitals-count').text(counts.vitals_pending || 0);
        $('#queue-consultation-count').text(counts.in_consultation || 0);
        $('#queue-admitted-count').text(counts.admitted || 0);
        var emergencyCount = counts.emergency || 0;
        $('#queue-emergency-count').text(emergencyCount);
        $('#queue-appointments-count').text(counts.scheduled || 0);
        // Pulse animation when emergency patients exist
        if (emergencyCount> 0) {
            $('#queue-emergency-count').closest('.queue-item').addClass('emergency-pulse');
        } else {
            $('#queue-emergency-count').closest('.queue-item').removeClass('emergency-pulse');
        }
        updateSyncIndicator();
    }).fail(function() {
        console.error('Failed to load queue counts');
    });

    // Load referrals count separately
    $.get(wbRoute('referrals.pending-count', '/referrals/pending-count'), function(data) {
        $('#queue-referrals-count').text(data.count || 0);
    }).fail(function() {
        console.error('Failed to load referral count');
    });

    // Load HMO pending validation count
    $.get(wbRoute('reception.hmo-pending-count', '/reception/hmo-pending-count'), function(data) {
        var count = data.count || 0;
        $('#queue-hmo-pending-count').text(count);
        if (count> 0) {
            $('#queue-hmo-pending-item').addClass('emergency-pulse');
        } else {
            $('#queue-hmo-pending-item').removeClass('emergency-pulse');
        }
    }).fail(function() {
        console.error('Failed to load HMO pending count');
    });
}

function startQueueRefresh() {
    queueRefreshInterval = setInterval(function() {
        loadQueueCounts();

        // Refresh queue DataTable if visible
        if ($('#queue-view').hasClass('active') && queueDataTable) {
            queueDataTable.ajax.reload(null, false);
        }
    }, 30000); // 30 seconds
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
    $('#ward-dashboard-view').removeClass('active').hide();
    $('#appointments-calendar-view').removeClass('active').hide();
    $('#hmo-validation-view').removeClass('active').hide();
    $('#patient-header').removeClass('active');
    $('#workspace-content').removeClass('active').hide();
}

function showQueue(filter) {
    hideAllViews();
    $('#queue-view').show().addClass('active');

    // Update filter buttons
    $('.queue-item').removeClass('active');
    $(`.queue-item[data-filter="${filter}"]`).addClass('active');

    initializeQueueDataTable(filter);
}

function hideQueue() {
    $('#queue-view').removeClass('active');

    if (currentPatient) {
        $('#workspace-content').addClass('active');
        $('#patient-header').addClass('active');
    } else {
        $('#empty-state').show();
    }
}

// =============================================
// WARD DASHBOARD FUNCTIONS
// =============================================
function showWardDashboard() {
    // Hide all views to prevent stacking
    hideAllViews();

    // Show ward dashboard
    $('#ward-dashboard-view').show().addClass('active');

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
    $('#ward-dashboard-view').hide().removeClass('active');

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

// =============================================
// REPORTS FUNCTIONS
// =============================================
let registrationsDataTable = null;
let queueReportDataTable = null;
let visitsDataTable = null;
let registrationsChart = null;
let hmoDistributionChart = null;
let peakHoursChart = null;

function showReports() {
    // Hide all views to prevent stacking
    hideAllViews();

    // Show reports view
    $('#reports-view').show().addClass('active');

    // Set default date range (this month)
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    $('#report-date-from').val(firstDay.toISOString().split('T')[0]);
    $('#report-date-to').val(today.toISOString().split('T')[0]);

    // Load data
    loadReportsStatistics();
    loadChartData();
    initReportsDataTables();

    // On mobile, show main workspace
    if (window.innerWidth < 768) {
        $('#main-workspace').addClass('active');
        $('#left-panel').addClass('hidden');
    }
}

function hideReports() {
    $('#reports-view').hide().removeClass('active');

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

function getReportFilters() {
    return {
        date_from: $('#report-date-from').val(),
        date_to: $('#report-date-to').val(),
        report_type: $('#report-type-filter').val(),
        clinic_id: $('#report-clinic-filter').val(),
        hmo_id: $('#report-hmo-filter').val(),
        patient_search: $('#report-patient-search').val()
    };
}

function loadReportsStatistics() {
    const filters = getReportFilters();

    $.ajax({
        url: wbRoute('reception.reports.statistics', '/reception/reports/statistics'),
        method: 'GET',
        data: filters,
        success: function(data) {
            $('#stat-new-registrations').text(data.new_registrations || 0);
            $('#stat-total-queued').text(data.total_queued || 0);
            $('#stat-completed-visits').text(data.completed_visits || 0);
            $('#stat-pending-queue').text(data.pending_queue || 0);
            $('#stat-avg-wait-time').text((data.avg_wait_time || 0) + 'm');
            $('#stat-return-rate').text((data.return_rate || 0) + '%');

            // Update top clinics table
            let clinicsHtml = '';
            if (data.top_clinics && data.top_clinics.length> 0) {
                data.top_clinics.forEach(function(clinic) {
                    clinicsHtml += `
                        <tr>
                            <td>${clinic.name}</td>
                            <td class="text-center">${clinic.visits}</td>
                            <td class="text-right">${clinic.percentage}%</td>
                        </tr>
                    `;
                });
            } else {
                clinicsHtml = '<tr><td colspan="3" class="text-center text-muted">No data</td></tr>';
            }
            $('#top-clinics-body').html(clinicsHtml);
        },
        error: function() {
            toastr.error('Failed to load statistics');
        }
    });
}

function loadChartData() {
    const filters = getReportFilters();

    $.ajax({
        url: wbRoute('reception.reports.chart-data', '/reception/reports/chart-data'),
        method: 'GET',
        data: filters,
        success: function(data) {
            renderRegistrationsChart(data.registration_trends);
            renderHmoDistributionChart(data.hmo_distribution);
            renderPeakHoursChart(data.peak_hours);
        },
        error: function() {
            console.error('Failed to load chart data');
        }
    });
}

function renderRegistrationsChart(data) {
    const ctx = document.getElementById('registrations-chart');
    if (!ctx) return;

    if (registrationsChart) {
        registrationsChart.destroy();
    }

    registrationsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Registrations',
                data: data.data,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

function renderHmoDistributionChart(data) {
    const ctx = document.getElementById('hmo-distribution-chart');
    if (!ctx) return;

    if (hmoDistributionChart) {
        hmoDistributionChart.destroy();
    }

    const colors = ['#667eea', '#f093fb', '#ffecd2', '#a8edea', '#11998e', '#4facfe'];

    hmoDistributionChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.data,
                backgroundColor: colors
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { boxWidth: 12 }
                }
            }
        }
    });
}

function renderPeakHoursChart(data) {
    const ctx = document.getElementById('peak-hours-chart');
    if (!ctx) return;

    if (peakHoursChart) {
        peakHoursChart.destroy();
    }

    peakHoursChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Queue Entries',
                data: data.data,
                backgroundColor: '#11998e'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

function initReportsDataTables() {
    // Initialize Registrations DataTable
    if (registrationsDataTable) {
        registrationsDataTable.destroy();
    }

    registrationsDataTable = $('#registrations-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbRoute('reception.reports.registrations', '/reception/reports/registrations'),
            data: function(d) {
                const filters = getReportFilters();
                Object.assign(d, filters);
            }
        },
        columns: [
            { data: 'date', name: 'created_at' },
            { data: 'file_no', name: 'file_no' },
            { data: 'patient_name', name: 'patient_name', orderable: false },
            { data: 'gender', name: 'gender' },
            { data: 'age', name: 'age', orderable: false },
            { data: 'phone', name: 'phone_no' },
            { data: 'hmo', name: 'hmo', orderable: false },
            { data: 'registered_by', name: 'registered_by', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 15,
        language: {
            emptyTable: 'No registrations found for selected period'
        }
    });

    // Initialize Queue Report DataTable
    if (queueReportDataTable) {
        queueReportDataTable.destroy();
    }

    queueReportDataTable = $('#queue-report-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbRoute('reception.reports.queue', '/reception/reports/queue'),
            data: function(d) {
                const filters = getReportFilters();
                Object.assign(d, filters);
            }
        },
        columns: [
            { data: 'datetime', name: 'created_at' },
            { data: 'file_no', name: 'file_no', orderable: false },
            { data: 'patient_name', name: 'patient_name', orderable: false },
            { data: 'clinic', name: 'clinic', orderable: false },
            { data: 'doctor', name: 'doctor', orderable: false },
            { data: 'service', name: 'service', orderable: false },
            { data: 'status', name: 'status' },
            { data: 'wait_time', name: 'wait_time', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 15,
        language: {
            emptyTable: 'No queue entries found for selected period'
        }
    });

    // Initialize Visits DataTable
    if (visitsDataTable) {
        visitsDataTable.destroy();
    }

    visitsDataTable = $('#visits-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbRoute('reception.reports.visits', '/reception/reports/visits'),
            data: function(d) {
                const filters = getReportFilters();
                Object.assign(d, filters);
            }
        },
        columns: [
            { data: 'date', name: 'created_at' },
            { data: 'file_no', name: 'file_no', orderable: false },
            { data: 'patient_name', name: 'patient_name', orderable: false },
            { data: 'clinic', name: 'clinic', orderable: false },
            { data: 'doctor', name: 'doctor', orderable: false },
            { data: 'reason', name: 'reason', orderable: false },
            { data: 'hmo', name: 'hmo', orderable: false },
            { data: 'type', name: 'type', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 15,
        language: {
            emptyTable: 'No visits found for selected period'
        }
    });
}

function reloadReportsData() {
    loadReportsStatistics();
    loadChartData();

    if (registrationsDataTable) {
        registrationsDataTable.ajax.reload();
    }
    if (queueReportDataTable) {
        queueReportDataTable.ajax.reload();
    }
    if (visitsDataTable) {
        visitsDataTable.ajax.reload();
    }
}

function initializeQueueDataTable(filter) {
    if (queueDataTable) {
        queueDataTable.destroy();
        queueDataTable = null;
    }
    if (referralsDataTable) {
        referralsDataTable.destroy();
        referralsDataTable = null;
    }

    // Rebuild table headers for queue
    $('#queue-datatable').empty().html(
        '<thead><tr>' +
        '<th>#</th><th>Patient</th><th>File No</th><th>HMO</th>' +
        '<th>Clinic</th><th>Doctor</th><th>Service</th><th>Status</th>' +
        '<th>Time</th><th>Action</th>' +
        '</tr></thead>'
    );

    queueDataTable = $('#queue-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbRoute('reception.queue-list', '/reception/queue-list'),
            data: function(d) {
                d.filter = filter;
                d.clinic_id = $('#queue-clinic-filter').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'patient_name', name: 'patient_name' },
            { data: 'patient_file_no', name: 'patient_file_no' },
            { data: 'patient_hmo', name: 'patient_hmo' },
            { data: 'clinic_name', name: 'clinic_name' },
            { data: 'doctor_name', name: 'doctor_name' },
            { data: 'service_name', name: 'service_name' },
            { data: 'status_badge', name: 'status', orderable: false },
            { data: 'time', name: 'created_at' },
            {
                data: 'actions',
                name: 'actions',
                orderable: false,
                render: function(data, type, row) {
                    return `
                        <button class="btn btn-sm btn-primary select-queue-patient" data-patient-id="${row.patient_id}">
                            <i class="mdi mdi-account-search"></i> Select
                        </button>
                    `;
                }
            }
        ],
        order: [[0, 'asc']],
        pageLength: 15,
        language: {
            emptyTable: 'No patients in queue',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        },
        drawCallback: function() {
            // Bind click handler for select buttons
            $('.select-queue-patient').off('click').on('click', function() {
                const patientId = $(this).data('patient-id');
                loadPatient(patientId);
                hideQueue();
            });
        }
    });
}

// =============================================
// BOOK SERVICE FUNCTIONALITY
// =============================================
function updateBookingCart() {
    const cartBody = $('#cart-preview-body');
    const cartTotal = $('#cart-preview-total');
    let totalAmount = 0;
    
    cartBody.empty();
    
    let hasItems = false;
    
    $('.family-booking-checkbox:checked').each(function() {
        const idx = $(this).data('idx');
        const id = $(this).data('patient-id');
        const patientName = $(this).siblings('label').text().replace('Active', '').trim();

        const serviceSelect = $(`#booking-service-${idx}`);
        const selectedOption = serviceSelect.find('option:selected');
        const serviceId = selectedOption.val();
        
        if (serviceId) {
            hasItems = true;
            const serviceName = selectedOption.text().split(' - ₦')[0]; // Extract name without price
            const priceStr = selectedOption.attr('data-price') || '0';
            const price = parseFloat(priceStr);
            totalAmount += price;
            
            cartBody.append(`
                <tr>
                    <td><small>${patientName}</small></td>
                    <td><small>${serviceName}</small></td>
                    <td class="text-right">₦${price.toLocaleString()}</td>
                </tr>
            `);
        }
    });
    
    if (hasItems) {
        $('#tariff-preview-card').show();
        cartTotal.text(`₦${totalAmount.toLocaleString()}`);
    } else {
        $('#tariff-preview-card').hide();
        cartTotal.text('₦0');
    }
}

function updateTariffPreview() {
    if (!currentPatient) return;

    const serviceId = $('#booking-service').val();
    const serviceType = $('input[name="service-type"]:checked').val() || 'consultation';

    if (!serviceId) {
        $('#tariff-preview-card').hide();
        return;
    }

    // Get service price from option data
    const servicePrice = parseFloat($('#booking-service option:selected').data('price')) || 0;

    $.ajax({
        url: wbRoute('reception.tariff-preview', '/reception/tariff-preview'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            patient_id: currentPatient,
            service_id: serviceId
        },
        success: function(data) {
            displayTariffPreview(data);
        },
        error: function() {
            $('#tariff-preview-card').hide();
        }
    });
}

function displayTariffPreview(data) {
    const $card = $('#tariff-preview-card');

    // Update tariff values
    $('#tariff-base-price').text(`₦${parseFloat(data.total_base_price || data.base_price || 0).toLocaleString()}`);
    $('#tariff-payable-amount').text(`₦${parseFloat(data.payable_amount || 0).toLocaleString()}`);

    if (data.hmo_name && data.claims_amount> 0) {
        $('#tariff-hmo-row').show();
        $('#tariff-coverage-mode').text(data.coverage_mode || 'N/A');
        $('#tariff-claims-amount').text(`₦${parseFloat(data.claims_amount || 0).toLocaleString()}`);

        if (data.validation_required) {
            $('#tariff-validation-alert').show();
            $('#tariff-validation-message').text('HMO validation required before service');
        } else {
            $('#tariff-validation-alert').hide();
        }
    } else {
        $('#tariff-hmo-row').hide();
        $('#tariff-validation-alert').hide();
    }

    $card.show();
}

function renderFamilyBookingGrid(patient) {
    const $container = $('#family-booking-container');
    $container.empty();

    let family = patient.family_members || [];
    
    // Create an array with the primary patient first, then dependents
    let members = [
        {
            user_id: patient.user_id,
            id: patient.id,
            name: patient.name,
            file_no: patient.file_no,
            is_principal: patient.is_family_principal,
            is_primary: true
        }
    ];

    family.forEach(f => {
        if (f.user_id !== patient.user_id) {
            members.push({
                user_id: f.user_id,
                id: f.id,
                name: f.name,
                file_no: f.file_no,
                is_principal: f.is_principal,
                is_primary: false
            });
        }
    });

    let serviceOptions = '<option value="">-- Select Service --</option>';
    if (cachedServices.consultation && cachedServices.consultation.length > 0) {
        cachedServices.consultation.forEach(s => {
            const priceLabel = s.price ? ` - ₦${parseFloat(s.price).toLocaleString()}` : '';
            serviceOptions += `<option value="${s.id}" data-price="${s.price || 0}">${s.name}${priceLabel}</option>`;
        });
    }

    // Populate clinic options string
    let clinicOptions = '<option value="">-- Select Clinic --</option>';
    if (cachedClinics && cachedClinics.length > 0) {
        cachedClinics.forEach(c => {
            clinicOptions += `<option value="${c.id}">${c.name}</option>`;
        });
    }

    members.forEach((m, idx) => {
        const checkedStatus = m.is_primary ? 'checked' : '';
        const displayStatus = m.is_primary ? 'block' : 'none';

        const card = `
        <div class="col-md-6 mb-3">
            <div class="card border mb-0 ${m.is_primary ? 'border-primary' : ''}">
                <div class="card-header bg-light p-2 d-flex align-items-center">
                    <div class="form-check mb-0">
                        <input class="form-check-input family-booking-checkbox" type="checkbox" id="book-member-${idx}" data-idx="${idx}" data-patient-id="${m.id}" ${checkedStatus}>
                        <label class="form-check-label mb-0 fw-bold" for="book-member-${idx}">
                            ${m.name} ${m.is_primary ? '<span class="badge bg-primary text-white ms-1">Active</span>' : ''}
                        </label>
                    </div>
                </div>
                <div class="card-body p-2" id="family-booking-fields-${idx}" style="display: ${displayStatus};">
                    <div class="form-group mb-2">
                        <label class="small text-muted mb-0">Service <span class="text-danger">*</span></label>
                        <select class="form-control form-control-sm family-booking-service" id="booking-service-${idx}" data-idx="${idx}">
                            ${serviceOptions}
                        </select>
                    </div>
                    <div class="form-group mb-2">
                        <label class="small text-muted mb-0">Clinic <span class="text-danger">*</span></label>
                        <select class="form-control form-control-sm family-booking-clinic" id="booking-clinic-${idx}" data-idx="${idx}" onchange="fetchDoctorsForGrid(this.value, 'booking-doctor-${idx}')">
                            ${clinicOptions}
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label class="small text-muted mb-0">Doctor</label>
                        <select class="form-control form-control-sm family-booking-doctor" id="booking-doctor-${idx}">
                            <option value="">Any Available Doctor</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        `;
        $container.append(card);
    });

    // Toggle fields based on checkbox
    $('.family-booking-checkbox').on('change', function() {
        const idx = $(this).data('idx');
        if ($(this).is(':checked')) {
            $(`#family-booking-fields-${idx}`).slideDown();
            $(this).closest('.card').addClass('border-primary');
        } else {
            $(`#family-booking-fields-${idx}`).slideUp();
            $(this).closest('.card').removeClass('border-primary');
        }
    });

    // Bind events for live cart
    $('.family-booking-checkbox, .family-booking-service').on('change', updateBookingCart);
    updateBookingCart();
}

function fetchDoctorsForGrid(clinicId, doctorSelectId) {
    if (!clinicId) {
        $(`#${doctorSelectId}`).empty().append('<option value="">Any Available Doctor</option>');
        return;
    }
    $.ajax({
        type: 'GET',
        url: wbUrl(`get-doctors/${clinicId}`),
        success: function(data) {
            $(`#${doctorSelectId}`).empty().append('<option value="">Any Available Doctor</option>');
            data.forEach(d => {
                $(`#${doctorSelectId}`).append(`<option value="${d.id}">${d.user?.surname || ''}, ${d.user?.firstname || ''} - ${d.specialization?.name || ''}</option>`);
            });
        }
    });
}

function bookConsultation() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    const bookingType = $('input[name="booking_type"]:checked').val() || 'walkin';
    const forceRebill = $('#force_rebill').is(':checked') ? 1 : 0;
    
    // Collect batch booking data
    let bookings = [];
    let hasErrors = false;

    $('.family-booking-checkbox:checked').each(function() {
        const idx = $(this).data('idx');
        const patientId = $(this).data('patient-id');
        const serviceId = $(`#booking-service-${idx}`).val();
        const clinicId = $(`#booking-clinic-${idx}`).val();
        const doctorId = $(`#booking-doctor-${idx}`).val();

        if (!serviceId || !clinicId) {
            hasErrors = true;
            toastr.warning('Please select Service and Clinic for all checked family members.');
            return false; // break each loop
        }

        let bookingPayload = {
            patient_id: patientId,
            service_id: serviceId,
            clinic_id: clinicId,
            doctor_id: doctorId,
            force_rebill: forceRebill
        };

        if (bookingType === 'schedule') {
            bookingPayload.appointment_date = $('#booking-appointment-date').val();
            bookingPayload.start_time = getBookingTime();
            bookingPayload.priority = $('#booking-priority').val();
            bookingPayload.appointment_notes = $('#booking-appointment-notes').val();
            bookingPayload.appointment_type = 'scheduled';
            
            if (!bookingPayload.appointment_date || !bookingPayload.start_time) {
                hasErrors = true;
                toastr.warning('Please select Date and Time for scheduled appointments.');
                return false;
            }
        }

        bookings.push(bookingPayload);
    });

    if (hasErrors) return;

    if (bookings.length === 0) {
        toastr.warning('Please select at least one family member to book.');
        return;
    }

    const $btn = $('#btn-book-consultation');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Booking...');

    var requestUrl = wbRoute('reception.book-consultation', '/reception/book-consultation');
    
    var requestData = {
        _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
        bookings: bookings
    };

    $.ajax({
        url: requestUrl,
        method: 'POST',
        data: requestData,
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Service(s) booked successfully');
                
                // Trigger Checkout Flow if applicable
                if ($btn.hasClass('btn-success') && typeof processPayment === 'function' && response.batch_responses) {
                    let checkoutItems = [];
                    let actualTotal = 0;

                    response.batch_responses.forEach(r => {
                        if (r.service_request_id && r.is_new_bill) {
                            checkoutItems.push({
                                id: r.service_request_id,
                                qty: 1,
                                discount: 0
                            });
                            actualTotal += r.payable_amount || 0;
                        }
                    });

                    if (checkoutItems.length > 0) {
                        window.pendingPaymentItems = checkoutItems;
                        window.pendingPaymentPatientId = currentPatient;
                        
                        $('#summary-total').text('₦' + actualTotal.toLocaleString());
                        $('#summary-subtotal').text('₦' + actualTotal.toLocaleString());
                        $('#modal-item-count').text(checkoutItems.length);
                        
                        $('#paymentModal').modal('show');
                    }
                }
                
                // Reset form fields natively
                $('.family-booking-service').val('');
                $('.family-booking-clinic').val('');
                $('.family-booking-doctor').empty().append('<option value="">Any Available Doctor</option>');
                
                $('#booking-appointment-date').val('');
                $('#booking-appointment-time').empty().append('<option value="">-- Select date first --</option>');
                $('#booking-appointment-notes').val('');
                $('input[name="booking_type"][value="walkin"]').prop('checked', true).trigger('change');
                
                updateBookingCart();

                loadQueueCounts();

                if (currentPatient) {
                    loadPatientQueueEntries(currentPatient);
                    loadBookingQueueEntries(currentPatient);
                }
            } else {
                toastr.error(response.message || 'Failed to book service');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to book service');
        },
        complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
}

