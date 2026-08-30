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

// =============================================
// WALK-IN SALES
// =============================================
function initializeWalkinSales() {
    // Add to cart
    $(document).on('click', '.add-to-cart', function() {
        const serviceId = $(this).data('id');
        const serviceType = $(this).data('type');
        const serviceName = $(this).data('name');
        const servicePrice = $(this).data('price');
        addToWalkinCart(serviceId, serviceType, serviceName, servicePrice);
    });

    // Remove from cart
    $(document).on('click', '.remove-from-cart', function() {
        const index = $(this).data('index');
        removeFromWalkinCart(index);
    });

    // Submit walk-in
    $('#btn-submit-walkin').on('click', function() {
        submitWalkinServices();
    });
}

let walkinCart = [];

function searchWalkinServices(query, type) {
    const $container = $('#walkin-search-results');
    $container.empty();

    let services = [];
    if (type === 'lab') {
        services = (cachedServices.lab || []).map(s => ({ ...s, type: 'lab' }));
    } else if (type === 'imaging') {
        services = (cachedServices.imaging || []).map(s => ({ ...s, type: 'imaging' }));
    } else if (type === 'product') {
        services = (cachedProducts || []).map(p => ({ ...p, type: 'product' }));
    }

    // Filter by query if provided
    if (query) {
        services = services.filter(s => s.name.toLowerCase().includes(query));
    }

    if (services.length === 0) {
        $container.html('<p class="text-muted text-center py-3">No services found</p>');
        return;
    }

    services.slice(0, 20).forEach(service => {
        const price = parseFloat(service.price || 0);
        const typeLabel = type === 'lab' ? 'Lab' : (type === 'imaging' ? 'Imaging' : 'Product');
        const typeClass = type === 'lab' ? 'info' : (type === 'imaging' ? 'warning' : 'success');

        $container.append(`
            <div class="walkin-service-item d-flex justify-content-between align-items-center p-2 border-bottom">
                <div>
                    <span class="badge badge-${typeClass}">${typeLabel}</span>
                    <span class="ml-2">${service.name}</span>
                </div>
                <div>
                    <span class="text-muted mr-2">₦${price.toLocaleString()}</span>
                    <button class="btn btn-sm btn-primary add-to-cart" data-id="${service.id}" data-type="${type}" data-name="${service.name}" data-price="${price}">
                        <i class="mdi mdi-plus"></i>
                    </button>
                </div>
            </div>
        `);
    });
}

function addToWalkinCart(id, type, name, price) {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    // Check if item already in cart
    const existingIndex = walkinCart.findIndex(item => item.id == id && item.type == type);
    if (existingIndex>= 0) {
        toastr.info('Item already in cart');
        return;
    }

    // Fetch tariff preview with HMO calculations
    const isProduct = type === 'product';
    $.ajax({
        url: wbRoute('reception.tariff-preview', '/reception/tariff-preview'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            patient_id: currentPatient,
            service_id: isProduct ? null : id,
            product_id: isProduct ? id : null,
            qty: 1
        },
        success: function(data) {
            walkinCart.push({
                id: id,
                type: type,
                name: name,
                base_price: parseFloat(data.base_price || price),
                payable_amount: parseFloat(data.payable_amount || price),
                claims_amount: parseFloat(data.claims_amount || 0),
                coverage_mode: data.coverage_mode || null,
                hmo_name: data.hmo_name || 'Private',
                quantity: 1
            });
            updateWalkinCartUI();
        },
        error: function() {
            // Fallback without HMO
            walkinCart.push({
                id: id,
                type: type,
                name: name,
                base_price: parseFloat(price),
                payable_amount: parseFloat(price),
                claims_amount: 0,
                coverage_mode: null,
                hmo_name: 'Private',
                quantity: 1
            });
            updateWalkinCartUI();
        }
    });
}

function removeFromWalkinCart(index) {
    walkinCart.splice(index, 1);
    updateWalkinCartUI();
}

function updateWalkinCartUI() {
    const $container = $('#walkin-cart-body');
    $container.empty();

    // Update cart count badge
    $('#cart-count-badge').text(walkinCart.length);

    if (walkinCart.length === 0) {
        $container.html(`
            <tr id="walkin-cart-empty">
                <td colspan="5" class="text-center text-muted py-4">
                    <i class="mdi mdi-cart-outline" style="font-size: 2rem;"></i>
                    <p class="mb-0 mt-2">No items selected</p>
                </td>
            </tr>
        `);
        $('#walkin-subtotal').text('₦0');
        $('#walkin-cart-total').text('₦0');
        $('#walkin-hmo-row').hide();
        $('#btn-submit-walkin').prop('disabled', true);
        return;
    }

    let subtotal = 0;
    let totalPayable = 0;
    let totalClaims = 0;
    let hmoName = 'Private';

    walkinCart.forEach((item, index) => {
        const itemSubtotal = item.base_price * item.quantity;
        const itemPayable = item.payable_amount * item.quantity;
        const itemClaims = item.claims_amount * item.quantity;

        subtotal += itemSubtotal;
        totalPayable += itemPayable;
        totalClaims += itemClaims;

        if (item.hmo_name && item.hmo_name !== 'Private') {
            hmoName = item.hmo_name;
        }

        // Coverage info
        const hasHmoCoverage = itemClaims> 0;
        const coverageLabel = item.coverage_mode ? item.coverage_mode.charAt(0).toUpperCase() + item.coverage_mode.slice(1) : '';
        const coverageBadge = hasHmoCoverage
            ? `<span class="badge badge-success" style="font-size: 0.7rem;">${coverageLabel}</span>`
            : '';

        $container.append(`
            <tr>
                <td>
                    <strong>${item.name}</strong>
                    <br>
                    <small class="text-muted">${item.type}</small>
                    ${coverageBadge}
                </td>
                <td class="text-right">
                    <span>₦${itemSubtotal.toLocaleString()}</span>
                </td>
                <td class="text-right text-success">
                    ${hasHmoCoverage ? `<span>-₦${itemClaims.toLocaleString()}</span>` : '<span class="text-muted">-</span>'}
                </td>
                <td class="text-right text-primary">
                    <strong>₦${itemPayable.toLocaleString()}</strong>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-danger remove-from-cart" data-index="${index}" title="Remove">
                        <i class="mdi mdi-close"></i>
                    </button>
                </td>
            </tr>
        `);
    });

    // Update summary
    $('#walkin-subtotal').text(`₦${subtotal.toLocaleString()}`);

    if (totalClaims> 0) {
        $('#walkin-hmo-row').show();
        $('#walkin-hmo-name').text(hmoName);
        $('#walkin-hmo-amount').text(`-₦${totalClaims.toLocaleString()}`);
    } else {
        $('#walkin-hmo-row').hide();
    }

    $('#walkin-cart-total').text(`₦${totalPayable.toLocaleString()}`);
    $('#btn-submit-walkin').prop('disabled', false);
}

function submitWalkinServices() {
    if (!currentPatient) {
        toastr.warning('Please select a patient first');
        return;
    }

    if (walkinCart.length === 0) {
        toastr.warning('Cart is empty');
        return;
    }

    const $btn = $('#btn-submit-walkin');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

    $.ajax({
        url: wbRoute('reception.book-walkin', '/reception/book-walkin'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            patient_id: currentPatient,
            items: walkinCart
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Services created successfully');
                walkinCart = [];
                updateWalkinCartUI();
                // Refresh recent requests
                loadRecentRequests();
                // Switch to recent tab to show the new request
                $('#walkin-cart-tabs a[href="#walkin-recent-pane"]').tab('show');
            } else {
                toastr.error(response.message || 'Failed to create services');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to create services');
        },
        complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
}

// =============================================
// RECENT REQUESTS (Last 24 hours)
// =============================================
function loadRecentRequests() {
    if (!currentPatient) return;

    const $container = $('#recent-requests-container');
    $container.html('<div class="text-center py-3"><i class="mdi mdi-loading mdi-spin"></i> Loading...</div>');

    $.ajax({
        url: wbUrl(`reception/patient/${currentPatient}/recent-requests`),
        method: 'GET',
        success: function(response) {
            if (response.success && response.requests && response.requests.length> 0) {
                let html = '';
                response.requests.forEach(req => {
                    const typeClass = getTypeClass(req.type);
                    const billingClass = getBillingStatusClass(req.billing_status);
                    const deliveryClass = getDeliveryStatusClass(req.delivery_status);
                    const coverageBadge = req.coverage_mode ? `<span class="badge badge-outline-success ml-1">${req.coverage_mode}</span>` : '';
                    const createdAt = new Date(req.created_at).toLocaleString('en-GB', {day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'});

                    html += `
                        <div class="recent-request-item">
                            <div class="recent-request-header">
                                <div>
                                    <span class="recent-request-name">${req.name}</span>
                                    <span class="badge badge-${typeClass} recent-request-type ml-2">${req.type_label}</span>
                                    ${coverageBadge}
                                </div>
                                <small class="text-muted">${createdAt}</small>
                            </div>
                            <div class="recent-request-details">
                                <div class="recent-request-pricing">
                                    <span>
                                        <span class="price-label">Price</span>
                                        <span class="price-value">₦${parseFloat(req.price || 0).toLocaleString()}</span>
                                    </span>
                                    <span>
                                        <span class="price-label">HMO</span>
                                        <span class="price-value text-success">${req.hmo_covers> 0 ? '-₦' + parseFloat(req.hmo_covers).toLocaleString() : '-'}</span>
                                    </span>
                                    <span>
                                        <span class="price-label">Payable</span>
                                        <span class="price-value text-primary">₦${parseFloat(req.payable || 0).toLocaleString()}</span>
                                    </span>
                                </div>
                                <div class="recent-request-status">
                                    <span class="billing-badge ${billingClass}">${req.billing_status || 'Pending'}</span>
                                    <span class="delivery-badge ${deliveryClass}">${req.delivery_status || 'Pending'}</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
                $container.html(html);
            } else {
                $container.html(`
                    <div class="text-center text-muted py-4">
                        <i class="mdi mdi-clock-outline" style="font-size: 2rem;"></i>
                        <p class="mb-0 mt-2">No recent requests</p>
                    </div>
                `);
            }
        },
        error: function() {
            $container.html(`
                <div class="text-center text-muted py-4">
                    <i class="mdi mdi-alert-circle" style="font-size: 2rem;"></i>
                    <p class="mb-0 mt-2">Failed to load recent requests</p>
                </div>
            `);
        }
    });
}

function getTypeClass(type) {
    const classes = {
        'lab': 'info',
        'imaging': 'warning',
        'product': 'success',
        'consultation': 'primary',
        'procedure': 'secondary'
    };
    return classes[type?.toLowerCase()] || 'secondary';
}

function getBillingStatusClass(status) {
    if (!status) return 'billing-pending';
    const statusLower = status.toLowerCase();
    if (statusLower.includes('paid')) return 'billing-paid';
    if (statusLower.includes('billed')) return 'billing-billed';
    return 'billing-pending';
}

function getDeliveryStatusClass(status) {
    if (!status) return 'delivery-pending';
    const statusLower = status.toLowerCase();
    if (statusLower.includes('completed') || statusLower.includes('dispensed')) return 'delivery-completed';
    if (statusLower.includes('progress') || statusLower.includes('sample') || statusLower.includes('awaiting')) return 'delivery-progress';
    return 'delivery-pending';
}

// =============================================
// SERVICE REQUESTS TAB
// =============================================
let serviceRequestsTable = null;

function initializeServiceRequestsTable(patientId) {
    if (serviceRequestsTable) {
        serviceRequestsTable.destroy();
    }

    // Set default date range to this month
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    if (!$('#req-date-from').val()) {
        $('#req-date-from').val(firstDay.toISOString().split('T')[0]);
    }
    if (!$('#req-date-to').val()) {
        $('#req-date-to').val(lastDay.toISOString().split('T')[0]);
    }

    serviceRequestsTable = $('#service-requests-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbUrl(`reception/patient/${patientId}/service-requests`),
            type: 'GET',
            data: function(d) {
                d.date_from = $('#req-date-from').val();
                d.date_to = $('#req-date-to').val();
                d.type_filter = $('#req-type-filter').val();
                d.billing_filter = $('#req-billing-filter').val();
                d.delivery_filter = $('#req-delivery-filter').val();
            }
        },
        columns: [
            { data: 'date_formatted', name: 'created_at' },
            { data: 'request_no', name: 'request_no' },
            { data: 'type_badge', name: 'type' },
            { data: 'name', name: 'name' },
            { data: 'price_formatted', name: 'price', className: 'text-right' },
            { data: 'hmo_covers_formatted', name: 'hmo_covers', className: 'text-right text-success' },
            { data: 'payable_formatted', name: 'payable', className: 'text-right text-primary font-weight-bold' },
            { data: 'billing_badge', name: 'billing_status' },
            { data: 'delivery_badge', name: 'delivery_status' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rtip',
        language: {
            emptyTable: 'No service requests found',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        },
        drawCallback: function() {
            // Update summary stats after table loads
            loadServiceRequestsStats(patientId);
        }
    });
}

function loadServiceRequestsStats(patientId) {
    $.ajax({
        url: wbUrl(`reception/patient/${patientId}/service-requests-stats`),
        method: 'GET',
        data: {
            date_from: $('#req-date-from').val(),
            date_to: $('#req-date-to').val(),
            type_filter: $('#req-type-filter').val(),
            billing_filter: $('#req-billing-filter').val(),
            delivery_filter: $('#req-delivery-filter').val()
        },
        success: function(response) {
            if (response.success && response.stats) {
                $('#req-total-requests').text(response.stats.total_requests || 0);
                $('#req-hmo-covered').text(response.stats.hmo_covered || '₦0');
                $('#req-patient-payable').text(response.stats.patient_payable || '₦0');
                $('#req-completed-count').text(response.stats.completed || 0);
            }
        }
    });
}

function reloadServiceRequestsData() {
    if (serviceRequestsTable) {
        serviceRequestsTable.ajax.reload();
    }
}

// Event handlers for service requests
$(document).on('submit', '#service-requests-filter-form', function(e) {
    e.preventDefault();
    reloadServiceRequestsData();
});

$(document).on('click', '#clear-req-filters', function() {
    // Reset to this month defaults
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    $('#req-date-from').val(firstDay.toISOString().split('T')[0]);
    $('#req-date-to').val(lastDay.toISOString().split('T')[0]);
    $('#req-type-filter, #req-billing-filter, #req-delivery-filter').val('');
    reloadServiceRequestsData();
});

// Export handlers
$(document).on('click', '#export-requests-excel', function() {
    if (!currentPatient) return;
    const params = new URLSearchParams({
        date_from: $('#req-date-from').val(),
        date_to: $('#req-date-to').val(),
        type: $('#req-type-filter').val(),
        billing_status: $('#req-billing-filter').val(),
        delivery_status: $('#req-delivery-filter').val(),
        format: 'excel'
    });
    window.location.href = wbUrl(`reception/patient/${currentPatient}/service-requests/export?${params}`);
});

$(document).on('click', '#export-requests-pdf', function() {
    if (!currentPatient) return;
    const params = new URLSearchParams({
        date_from: $('#req-date-from').val(),
        date_to: $('#req-date-to').val(),
        type: $('#req-type-filter').val(),
        billing_status: $('#req-billing-filter').val(),
        delivery_status: $('#req-delivery-filter').val(),
        format: 'pdf'
    });
    window.location.href = wbUrl(`reception/patient/${currentPatient}/service-requests/export?${params}`);
});

$(document).on('click', '#print-requests', function() {
    if (!currentPatient) return;
    const params = new URLSearchParams({
        date_from: $('#req-date-from').val(),
        date_to: $('#req-date-to').val(),
        type: $('#req-type-filter').val(),
        billing_status: $('#req-billing-filter').val(),
        delivery_status: $('#req-delivery-filter').val()
    });
    window.open(wbUrl(`reception/patient/${currentPatient}/service-requests/print?${params}`), '_blank');
});

// View Request Details Handler
$(document).on('click', '.view-request-btn', function() {
    const type = $(this).data('type');
    const id = $(this).data('id');

    showRequestDetails(type, id);
});

// Discard Request Handler
let discardRequestType = null;
let discardRequestId = null;

$(document).on('click', '.discard-request-btn', function() {
    discardRequestType = $(this).data('type');
    discardRequestId = $(this).data('id');
    const serviceName = $(this).data('name');
    const requestNo = $(this).data('request-no');

    $('#discard_service_name').text(serviceName);
    $('#discard_request_no').text(requestNo);
    $('#discard_reason').val('');
    $('#discardRequestModal').modal('show');
});

$(document).on('click', '.btn-print-routing', function(e) {
    e.preventDefault();
    const queueId = $(this).data('queue-id');
    
    toastr.info('Generating routing slip...');
    
    $.ajax({
        url: wbUrl(`reception/queue/${queueId}/routing-slip`),
        method: 'GET',
        success: function(response) {
            if (response.success && response.html) {
                const printWindow = window.open('', '', 'height=600,width=800');
                printWindow.document.write(response.html);
                printWindow.document.close();
                printWindow.print();
            } else {
                toastr.error('Failed to generate routing slip');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to print routing slip');
        }
    });
});

$(document).on('click', '.btn-delete-queue', function(e) {
    e.preventDefault();
    const queueId = $(this).data('queue-id');
    const serviceRequestId = $(this).data('service-request-id');
    const serviceName = $(this).closest('.card-body').find('h6').text().trim();
    
    discardRequestType = 'service';
    discardRequestId = serviceRequestId; // Note: if serviceRequestId is null (skipped billing), this might need handling
    
    // If no service request (e.g. skipped billing), we might need to delete the queue directly
    if (!serviceRequestId) {
        // Fallback for cycle-duration skipped billings
        discardRequestType = 'queue';
        discardRequestId = queueId;
    }

    $('#discard_service_name').text(serviceName + ' (Queue Booking)');
    $('#discard_request_no').text('Q-' + queueId);
    $('#discard_reason').val('');
    $('#discardRequestModal').modal('show');
});

$(document).on('click', '.btn-print-routing', function(e) {
    e.preventDefault();
    const queueId = $(this).data('queue-id');
    
    toastr.info('Generating routing slip...');
    
    $.ajax({
        url: wbUrl(`reception/queue/${queueId}/routing-slip`),
        method: 'GET',
        success: function(response) {
            if (response.success && response.html) {
                const printWindow = window.open('', '', 'height=600,width=800');
                printWindow.document.write(response.html);
                printWindow.document.close();
                printWindow.print();
            } else {
                toastr.error('Failed to generate routing slip');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to print routing slip');
        }
    });
});

$('#discardRequestForm').on('submit', function(e) {
    e.preventDefault();

    const reason = $('#discard_reason').val();

    if (reason.length < 10) {
        toastr.warning('Please provide a detailed reason (minimum 10 characters)');
        return;
    }

    $('#confirmDiscardBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Discarding...');

    $.ajax({
        url: wbUrl(`reception/request/${discardRequestType}/${discardRequestId}/discard`),
        method: 'DELETE',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            reason: reason
        },
        success: function(response) {
            $('#discardRequestModal').modal('hide');
            toastr.success(response.message || 'Request discarded successfully');

            // Reload the service requests table
            reloadServiceRequestsData();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to discard request');
        },
        complete: function() {
            $('#confirmDiscardBtn').prop('disabled', false).html('<i class="mdi mdi-delete"></i> Discard Request');
        }
    });
});

// =============================================
// VISIT HISTORY
// =============================================
function initializeVisitHistoryTable(patientId) {
    if (visitHistoryTable) {
        visitHistoryTable.destroy();
    }

    visitHistoryTable = $('#visit-history-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbUrl(`reception/patient/${patientId}/visits`),
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'date', name: 'created_at' },
            { data: 'doctor_name', name: 'doctor_name' },
            { data: 'service_name', name: 'service_name' },
            { data: 'reason', name: 'reason' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'desc']],
        pageLength: 10,
        language: {
            emptyTable: 'No visit history found',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        }
    });
}

// =============================================
// QUICK REGISTRATION
// =============================================
function showQuickRegisterModal() {
    $('#quickRegisterModal').modal('show');
    // Reset mode buttons
    $('#qr-mode-auto').addClass('active');
    $('#qr-mode-manual').removeClass('active');
    $('#quick-register-file-no').prop('readonly', true).removeClass('status-valid status-checking status-duplicate');
    $('#qr-duplicate-warning').hide();
    $('#qr-file-no-hint').removeClass('manual-mode');
    // Generate new file number from server
    generateFileNumber();
}

function generateFileNumber() {
    const $input = $('#quick-register-file-no');
    $input.removeClass('status-valid status-checking status-duplicate');

    $.ajax({
        url: wbUrl('/reception/patient/next-file-number'),
        method: 'GET',
        success: function(response) {
            $input.val(response.file_no).addClass('status-valid');
            $('#qr-next-file-no').text(response.file_no);

            // Update format pattern display
            if (response.format_pattern) {
                $('#qr-format-pattern').text(response.format_pattern);
            } else {
                $('#qr-format-pattern').text('Sequential');
            }

            // Populate recent file numbers
            const $recentList = $('#qr-recent-file-nos');
            $recentList.empty();
            if (response.recent_file_nos && response.recent_file_nos.length> 0) {
                response.recent_file_nos.forEach(fileNo => {
                    $recentList.append(`<span class="file-no-recent-item qr-recent-item" data-file-no="${fileNo}">${fileNo}</span>`);
                });
            }

            $('#qr-duplicate-warning').hide();
        },
        error: function() {
            // Fallback: use timestamp-based number if server fails
            const now = new Date();
            const fallbackNo = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(Math.floor(Math.random() * 10000)).padStart(4, '0')}`;
            $('#quick-register-file-no').val(fallbackNo);
            $('#qr-next-file-no').text(fallbackNo);
            $('#qr-format-pattern').text('Auto-generated');
            $('#qr-recent-file-nos').empty();
            toastr.warning('Could not fetch next file number, using auto-generated');
        }
    });
}

// Toggle file number mode for quick register (Auto/Manual buttons)
function toggleQRFileNumberMode(mode) {
    const $input = $('#quick-register-file-no');
    const $hint = $('#qr-file-no-hint');

    // Update button states
    $('#qr-mode-auto, #qr-mode-manual').removeClass('active');
    if (mode === 'manual') {
        $('#qr-mode-manual').addClass('active');
    } else {
        $('#qr-mode-auto').addClass('active');
    }

    if (mode === 'manual') {
        // Manual mode - allow editing
        $input.prop('readonly', false).attr('placeholder', 'Enter file number');
        $hint.addClass('manual-mode');
        $input.focus().select();

        // Check current value for duplicates
        if ($input.val()) {
            checkQRFileNumberDuplicate($input.val());
        }
    } else {
        // Auto mode - readonly with generated number
        $input.prop('readonly', true).attr('placeholder', 'Auto-generated');
        $hint.removeClass('manual-mode');
        generateFileNumber();
    }
}

// Click handlers for mode buttons
$('#qr-mode-auto').on('click', function() {
    toggleQRFileNumberMode('auto');
});

$('#qr-mode-manual').on('click', function() {
    toggleQRFileNumberMode('manual');
});

// Quick register refresh button
$('#qr-file-no-refresh').on('click', function() {
    toggleQRFileNumberMode('auto');
    toastr.info('File number regenerated');
});

// Click handler for recent file numbers in quick register (copy to input)
$(document).on('click', '.qr-recent-item', function() {
    const fileNo = $(this).data('file-no');
    const $input = $('#quick-register-file-no');

    // Switch to manual mode
    toggleQRFileNumberMode('manual');

    // Set the value
    $input.val(fileNo);

    // Check for duplicates
    checkQRFileNumberDuplicate(fileNo);

    toastr.info(`Copied "${fileNo}" - you can edit it now`);
});

// Debounced file number duplicate check for quick register
let quickRegisterCheckTimeout = null;
function checkQRFileNumberDuplicate(fileNo) {
    const $input = $('#quick-register-file-no');

    // Clear previous timeout
    if (quickRegisterCheckTimeout) {
        clearTimeout(quickRegisterCheckTimeout);
    }

    if (!fileNo || fileNo.trim() === '') {
        $input.removeClass('status-valid status-checking status-duplicate');
        $('#qr-duplicate-warning').hide();
        return;
    }

    // Show checking state
    $input.removeClass('status-valid status-duplicate').addClass('status-checking');

    // Debounce the AJAX call
    quickRegisterCheckTimeout = setTimeout(function() {
        $.ajax({
            url: wbUrl('/reception/patient/check-file-number'),
            method: 'POST',
            data: {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                file_no: fileNo
            },
            success: function(response) {
                $input.removeClass('status-checking');

                if (response.exists) {
                    // Show warning (not blocking, just informative)
                    $input.addClass('status-duplicate');
                    const $warning = $('#qr-duplicate-warning');
                    const $patients = $('#qr-duplicate-patients');

                    let html = '';
                    response.patients.forEach(p => {
                        html += `<div class="duplicate-patient"><i class="mdi mdi-account"></i> ${p.name} (${p.file_no})</div>`;
                    });
                    if (response.count> 3) {
                        html += `<div class="duplicate-patient text-muted">...and ${response.count - 3} more</div>`;
                    }
                    $patients.html(html);
                    $warning.show();
                } else {
                    // File number is unique
                    $input.addClass('status-valid');
                    $('#qr-duplicate-warning').hide();
                }
            },
            error: function() {
                $input.removeClass('status-checking');
            }
        });
    }, 400); // 400ms debounce
}

// Quick register file number input change (for duplicate check)
$('#quick-register-file-no').on('input', function() {
    const $input = $(this);
    if (!$input.prop('readonly')) {
        checkQRFileNumberDuplicate($input.val());
    }
});

function submitQuickRegister() {
    const $form = $('#quick-register-form');
    const $btn = $form.find('button[type="submit"]');
    const originalHtml = $btn.html();

    // Basic validation
    const firstName = $('#quick-register-firstname').val().trim();
    const lastName = $('#quick-register-lastname').val().trim();
    const phone = $('#quick-register-phone').val().trim();
    const gender = $('#quick-register-gender').val();

    if (!firstName || !lastName) {
        toastr.warning('First name and last name are required');
        return;
    }

    if (!gender) {
        toastr.warning('Please select gender');
        return;
    }

    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Registering...');

    $.ajax({
        url: wbRoute('reception.patient.quick-register', '/reception/patient/quick-register'),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            surname: lastName,
            firstname: firstName,
            phone_no: phone,
            gender: gender,
            dob: $('#quick-register-dob').val(),
            hmo_id: $('#quick-register-hmo').val(),
            hmo_no: $('#quick-register-hmo-no').val()
        },
        success: function(response) {
            if (response.success) {
                toastr.success('Patient registered successfully');
                $('#quickRegisterModal').modal('hide');
                $form[0].reset();

                // Load the newly registered patient
                if (response.patient && response.patient.id) {
                    loadPatient(response.patient.id);
                }
            } else {
                toastr.error(response.message || 'Registration failed');
            }
        },
        error: function(xhr) {
            const errors = xhr.responseJSON?.errors;
            if (errors) {
                Object.values(errors).forEach(err => {
                    toastr.error(err[0]);
                });
            } else {
                toastr.error(xhr.responseJSON?.message || 'Registration failed');
            }
        },
        complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
}

// =============================================
// TODAY'S STATS
// =============================================
function showTodayStats() {
    $.ajax({
        url: wbRoute('reception.today-stats', '/reception/today-stats'),
        method: 'GET',
        success: function(data) {
            displayTodayStats(data);
        },
        error: function() {
            toastr.error('Failed to load statistics');
        }
    });
}

function displayTodayStats(data) {
    const html = `
        <div class="modal fade" id="todayStatsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="mdi mdi-chart-bar"></i> Today's Statistics</h5>
                        <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-3 col-6">
                                <div class="stat-card bg-primary text-white p-3 rounded mb-3">
                                    <h3 class="mb-0">${data.total_queued || 0}</h3>
                                    <small>Total Queued Today</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stat-card bg-success text-white p-3 rounded mb-3">
                                    <h3 class="mb-0">${data.new_registrations || 0}</h3>
                                    <small>New Registrations</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stat-card bg-info text-white p-3 rounded mb-3">
                                    <h3 class="mb-0">${data.consultations_done || 0}</h3>
                                    <small>Consultations Done</small>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="stat-card bg-warning text-white p-3 rounded mb-3">
                                    <h3 class="mb-0">${data.pending_services || 0}</h3>
                                    <small>Pending Services</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    $('#todayStatsModal').remove();

    // Add and show modal
    $('body').append(html);
    $('#todayStatsModal').modal('show');

    // Clean up on close
    $('#todayStatsModal').on('hidden.bs.modal', function() {
        $(this).remove();
    });
}

// =============================================
// UTILITY FUNCTIONS
// =============================================
function updateSyncIndicator() {
    const $indicator = $('#sync-indicator');
    if ($indicator.length) {
        $indicator.html(`
            <i class="mdi mdi-check-circle text-success"></i>
            <small class="text-muted">Synced</small>
        `);
    }
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
           ' ' + date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

// Cleanup on page unload
$(window).on('beforeunload', function() {
    if (queueRefreshInterval) {
        clearInterval(queueRefreshInterval);
    }
});

// =============================================
// HOSPITAL CARD FUNCTIONS
// =============================================
function showHospitalCard(patientData) {
    const defaultAvatar = wbUrl('assets/images/default-avatar.png');

    // Populate FRONT card data
    $('#card-patient-photo').attr('src', patientData.photo || defaultAvatar);
    $('#card-patient-name').text(patientData.name || 'Patient Name');
    $('#card-patient-id').text(patientData.file_no || 'N/A');
    $('#card-dob').text(patientData.dob || 'N/A');
    $('#card-blood-type').text(patientData.blood_group || 'N/A');
    $('#card-genotype').text(patientData.genotype || 'N/A');
    $('#card-barcode-number').text(patientData.file_no || '');

    // Populate BACK card data
    $('#card-gender').text(patientData.gender || 'N/A');
    $('#card-phone').text(patientData.phone_no || 'N/A');
    $('#card-address').text(patientData.address || 'Not provided');

    // Handle allergies (may be array or string)
    let allergiesText = 'None known';
    if (patientData.allergies) {
        if (Array.isArray(patientData.allergies)) {
            allergiesText = patientData.allergies.length> 0 ? patientData.allergies.join(', ') : 'None known';
        } else {
            allergiesText = patientData.allergies;
        }
    }
    $('#card-allergies').text(allergiesText);

    $('#card-nok-name').text(patientData.next_of_kin_name || 'Not provided');
    $('#card-nok-phone').text(patientData.next_of_kin_phone || 'N/A');

    // Generate barcode using JsBarcode if available, otherwise use simple display
    if (typeof JsBarcode !== 'undefined' && patientData.file_no) {
        try {
            JsBarcode('#card-barcode', patientData.file_no, {
                format: 'CODE128',
                width: 1.5,
                height: 30,
                displayValue: false,
                margin: 0,
                background: 'transparent'
            });
        } catch (e) {
            console.error('Barcode generation failed:', e);
            // Fallback: show text-based barcode
            generateTextBarcode(patientData.file_no);
        }
    } else {
        // Fallback: generate text-based barcode representation
        generateTextBarcode(patientData.file_no);
    }

    // Show modal
    $('#hospitalCardModal').modal('show');
}

function generateTextBarcode(code) {
    // Create a simple CSS-based barcode representation
    if (!code) return;

    const svg = document.getElementById('card-barcode');
    const width = 200;
    const height = 30;

    // Create simple bars based on character codes
    let bars = '';
    const barWidth = width / (code.length * 11 + 2);
    let x = barWidth;

    for (let i = 0; i < code.length; i++) {
        const charCode = code.charCodeAt(i);
        // Generate pattern based on character
        const pattern = charCode.toString(2).padStart(8, '0');

        for (let j = 0; j < pattern.length; j++) {
            if (pattern[j] === '1') {
                bars += `<rect x="${x}" y="0" width="${barWidth}" height="${height}" fill="#000"/>`;
            }
            x += barWidth;
        }
        x += barWidth; // Space between characters
    }

    svg.innerHTML = bars;
    svg.setAttribute('width', width);
    svg.setAttribute('height', height);
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
}

function printHospitalCard() {
    var activeTab = $('#cardViewTabs .nav-link.active').data('card-tab') || 'combined';
    var cardContent = '';
    if (activeTab === 'front') {
        cardContent = document.getElementById('hospital-card-preview').outerHTML;
    } else if (activeTab === 'back') {
        cardContent = document.getElementById('hospital-card-back').outerHTML;
    } else {
        cardContent = document.getElementById('hospital-card-container').innerHTML;
    }
    var pageHeight = (activeTab === 'combined') ? '120mm' : '60mm';
    const hosColor = '';
    const printWindow = window.open('', '_blank', 'width=500,height=700');

    // Use mm-based sizing for consistent print output (ISO ID-1 card: 85.6mm × 54mm)
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Hospital Patient Card</title>
            <style>
                @page {
                    size: 90mm ${pageHeight};
                    margin: 2mm;
                }
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    margin: 0;
                    padding: 0;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                /* === CARD BASE === */
                .hospital-card {
                    width: 85.6mm;
                    height: 54mm;
                    background: #fff;
                    border-radius: 2.5mm;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    border: 0.3mm solid #ccc;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .hospital-card-back {
                    margin-top: 3mm;
                    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                }

                /* === FRONT HEADER === */
                .card-header-section {
                    background: ${hosColor} !important;
                    color: white;
                    padding: 2mm 2.5mm;
                    display: flex;
                    align-items: center;
                    gap: 2mm;
                    flex-shrink: 0;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .hospital-logo-section { width: 9mm; height: 9mm; flex-shrink: 0; }
                .hospital-logo { width: 9mm; height: 9mm; object-fit: contain; background: white; border-radius: 1mm; padding: 0.5mm; }
                .hospital-logo-placeholder { width: 9mm; height: 9mm; background: rgba(255,255,255,0.2); border-radius: 1mm; display: flex; align-items: center; justify-content: center; font-size: 5mm; }
                .hospital-info-section { flex: 1; line-height: 1.2; }
                .hospital-name-text { font-size: 3mm; font-weight: 800; text-transform: uppercase; }
                .hospital-address-text { font-size: 2.2mm; font-weight: 600; opacity: 0.9; }
                .hospital-phone-text { font-size: 2.2mm; font-weight: 600; opacity: 0.9; }

                /* === FRONT BODY === */
                .card-body-section {
                    display: flex;
                    padding: 2mm 2.5mm;
                    gap: 2.5mm;
                    flex: 1;
                    min-height: 0;
                }
                .patient-photo-section { flex-shrink: 0; }
                .patient-photo {
                    width: 16mm;
                    height: 20mm;
                    object-fit: cover;
                    border-radius: 1.5mm;
                    border: 0.5mm solid ${hosColor};
                    background: #f0f0f0;
                }
                .patient-info-section { flex: 1; text-align: left; overflow: visible; }
                .patient-name-text {
                    font-size: 3.5mm;
                    font-weight: 800;
                    color: #222;
                    margin-bottom: 0.8mm;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    border-bottom: 0.2mm solid #ddd;
                    padding-bottom: 1mm;
                }
                .patient-details-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 1mm 2mm;
                }
                .detail-item { display: flex; flex-direction: column; }
                .detail-label { font-size: 2mm; font-weight: 700; color: #666; text-transform: uppercase; }
                .detail-value { font-size: 2.8mm; font-weight: 700; color: #222; white-space: nowrap; overflow: visible; }

                /* === BARCODE === */
                .card-barcode-section {
                    padding: 0.5mm 2.5mm;
                    text-align: center;
                    flex-shrink: 0;
                    background: #fff;
                }
                .card-barcode-section svg { height: 5mm; width: auto; max-width: 100%; }
                .barcode-number {
                    font-size: 2.5mm;
                    font-family: 'Courier New', monospace;
                    font-weight: 700;
                    color: #222;
                    letter-spacing: 0.5mm;
                }

                /* === FRONT FOOTER === */
                .card-footer-section {
                    background: ${hosColor} !important;
                    color: white;
                    padding: 1mm 2.5mm;
                    flex-shrink: 0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .card-type-badge { font-size: 2.2mm; font-weight: 800; letter-spacing: 0.3mm; }
                .powered-by { font-size: 1.8mm; font-weight: 600; opacity: 0.8; }

                /* === BACK CARD === */
                .card-back-header {
                    background: ${hosColor} !important;
                    color: white;
                    padding: 1.5mm 2.5mm;
                    text-align: center;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .back-title { font-size: 3mm; font-weight: 800; letter-spacing: 0.3mm; }
                .card-back-body { padding: 2mm 2.5mm; flex: 1; }
                .back-info-row { display: flex; gap: 1.5mm; margin-bottom: 1mm; font-size: 2.5mm; }
                .back-info-row.full-width { flex-direction: column; gap: 0.3mm; }
                .back-label { font-weight: 700; color: #444; min-width: 13mm; }
                .back-value { color: #333; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .back-info-row.full-width .back-value { white-space: normal; font-size: 2.2mm; font-weight: 600; line-height: 1.3; }
                .back-divider { border-top: 0.2mm dashed #ccc; margin: 1.5mm 0; }
                .back-section-title {
                    font-size: 2.5mm;
                    font-weight: 800;
                    color: ${hosColor};
                    margin-bottom: 1mm;
                    text-transform: uppercase;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .card-back-footer {
                    background: ${hosColor} !important;
                    color: white;
                    padding: 1mm 2.5mm;
                    text-align: center;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                .emergency-note { font-size: 2.2mm; font-weight: 600; opacity: 0.9; }
                .powered-by-back { font-size: 1.8mm; font-weight: 600; opacity: 0.7; margin-top: 0.5mm; }
            </style>
        </head>
        <body>
            ${cardContent}
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                    }, 500);
                };
            <\/script>
        </body>
        </html>
    `);

    printWindow.document.close();
}

// =============================================
// APPOINTMENT SCHEDULING ENHANCEMENTS
// =============================================

// Toggle schedule fields visibility
$('input[name="booking_type"]').on('change', function() {
    var isSchedule = $(this).val() === 'schedule';
    $('#schedule-fields').toggle(isSchedule);
    var $btn = $('#btn-book-consultation');
    if (isSchedule) {
        $btn.html('<i class="mdi mdi-calendar-plus"></i> Schedule Appointment');
        $btn.removeClass('btn-primary').addClass('btn-purple');
    } else {
        $btn.html('<i class="mdi mdi-send"></i> Send to Queue');
        $btn.removeClass('btn-purple').addClass('btn-primary');
    }
});

// Load available slots when date/clinic/doctor changes
$('#booking-appointment-date').on('change', function() {
    loadAvailableSlots();
});

// Custom time toggle
$('#booking-custom-time-toggle').on('change', function() {
    if ($(this).is(':checked')) {
        $('#booking-appointment-time').hide();
        $('#booking-appointment-time-manual').show().val('');
        $('#custom-time-hint').remove();
    } else {
        $('#booking-appointment-time-manual').hide().val('');
        $('#booking-appointment-time').show();
        $('#custom-time-hint').remove();
    }
});

function getBookingTime() {
    if ($('#booking-custom-time-toggle').is(':checked')) {
        return $('#booking-appointment-time-manual').val();
    }
    return $('#booking-appointment-time').val();
}

function loadAvailableSlots() {
    var date = $('#booking-appointment-date').val();
    var clinicId = $('#booking-clinic').val();
    var doctorId = $('#booking-doctor').val();

    if (!date || !clinicId) {
        $('#booking-appointment-time').empty().append('<option value="">-- Select date & clinic first --</option>');
        return;
    }

    // Remove any previous hint
    $('#custom-time-hint').remove();

    $.get(wbRoute('appointments.available-slots', '/appointments/available-slots'), {
        date: date,
        clinic_id: clinicId,
        doctor_id: doctorId
    }, function(response) {
        var $select = $('#booking-appointment-time');
        $select.empty().append('<option value="">-- Select Time --</option>');

        var availableCount = 0;
        if (response.success && response.slots && response.slots.length> 0) {
            response.slots.forEach(function(slot) {
                if (slot.available) {
                    $select.append('<option value="' + slot.time + '">' + slot.time + '</option>');
                    availableCount++;
                } else {
                    // Show booked slots as disabled to give full schedule visibility
                    $select.append('<option value="' + slot.time + '" disabled class="text-muted">' + slot.time + ' (booked)</option>');
                }
            });
        }

        // Auto-suggest custom time when no slots are available
        if (availableCount === 0) {
            $select.empty().append('<option value="" disabled>No preset slots available</option>');

            // Auto-enable custom time
            $('#booking-custom-time-toggle').prop('checked', true).trigger('change');

            // Show helpful hint
            var hintHtml = '<div id="custom-time-hint" class="alert alert-info py-1 px-2 mt-1 mb-0 small">' +
                '<i class="mdi mdi-information-outline"></i> ' +
                'No preset slots available for this date. Enter a custom time below.' +
                '</div>';
            $('#booking-appointment-time-manual').after(hintHtml);
        } else {
            // If custom time was auto-enabled, reset back to dropdown
            // (only if user didn't manually check it)
            if ($('#booking-custom-time-toggle').data('auto-enabled')) {
                $('#booking-custom-time-toggle').prop('checked', false).trigger('change');
                $('#booking-custom-time-toggle').removeData('auto-enabled');
            }
        }

        // Track that it was auto-enabled
        if (availableCount === 0) {
            $('#booking-custom-time-toggle').data('auto-enabled', true);
        }

    }).fail(function() {
        $('#booking-appointment-time').empty().append('<option value="">Error loading slots</option>');
    });
}

// ─── Queue filter handlers for new items ─────────────
$(document).on('click', '.queue-item[data-filter="appointments-today"]', function() {
    showAppointmentsCalendarView();
});

$(document).on('click', '.queue-item[data-filter="referrals"]', function() {
    showReferralsQueueView();
});

// HMO Pending Validation queue item click
$(document).on('click', '.queue-item[data-filter="hmo-pending-validation"]', function() {
    showHmoValidationPanel();
});

// Close HMO validation panel
$('#btn-close-hmo-validation').on('click', function() {
    $('#hmo-validation-view').removeClass('active').hide();
    if (currentPatient) {
        $('#workspace-content').addClass('active').show();
        $('#patient-header').addClass('active');
    } else {
        $('#empty-state').show();
    }
});

// Refresh HMO validation panel
$('#btn-hmo-refresh-validation').on('click', function() {
    loadHmoValidationList();
});

// Search filter
$('#hmo-validation-search').on('keyup', _.debounce(function() {
    loadHmoValidationList();
}, 300));

// Select all checkbox
$('#hmo-select-all').on('change', function() {
    var checked = $(this).prop('checked');
    $('#hmo-validation-body .hmo-row-check').prop('checked', checked);
    updateHmoBatchCount();
});

// Row checkbox change
$(document).on('change', '.hmo-row-check', function() {
    updateHmoBatchCount();
});

// ── HMO Validate Confirm Modal helpers ──────────────────────────
var _hvcMode = null;   // 'single' | 'batch'
var _hvcId = null;     // single request id
var _hvcRow = null;    // single row jQuery element
var _hvcIds = [];      // batch ids

// Single validate button → open confirm modal
$(document).on('click', '.hmo-validate-btn', function() {
    var btn = $(this);
    _hvcMode = 'single';
    _hvcId = btn.data('id');
    _hvcRow = btn.closest('tr');
    _hvcIds = [];

    // Populate modal
    $('#hvc-title').text('Confirm Validation');
    $('#hvc-patient').text(btn.data('patient'));
    $('#hvc-file-no').text(btn.data('fileno'));
    $('#hvc-hmo').text(btn.data('hmo'));
    $('#hvc-item').text(btn.data('item'));
    var coverage = btn.data('coverage');
    $('#hvc-coverage').html(coverage === 'primary'
        ? '<span class="badge badge-warning">PRIMARY</span>'
        : '<span class="badge badge-danger">SECONDARY</span>');
    $('#hvc-amount').text('₦' + Number(btn.data('amount')).toLocaleString());
    $('#hvc-outcome-info').html(coverage === 'secondary'
        ? '<i class="mdi mdi-information-outline"></i> Secondary coverage — will be set to <strong>Awaiting Auth Code</strong>.'
        : '<i class="mdi mdi-information-outline"></i> Primary coverage — will be <strong>Approved</strong> immediately.');

    $('#hvc-single-details').show();
    $('#hvc-batch-details').hide();
    $('#hvc-notes').val('');
    $('#hvc-confirm-btn').prop('disabled', false).html('<i class="mdi mdi-check"></i> Confirm Validation');
    $('#hmoValidateConfirmModal').modal('show');
});

// Batch validate → open confirm modal
$('#btn-hmo-batch-validate').on('click', function() {
    _hvcIds = [];
    $('#hmo-validation-body .hmo-row-check:checked').each(function() {
        _hvcIds.push($(this).data('id'));
    });
    if (_hvcIds.length === 0) return;

    _hvcMode = 'batch';
    _hvcId = null;
    _hvcRow = null;

    $('#hvc-title').text('Confirm Batch Validation');
    $('#hvc-batch-total').text(_hvcIds.length);
    $('#hvc-single-details').hide();
    $('#hvc-batch-details').show();
    $('#hvc-notes').val('');
    $('#hvc-confirm-btn').prop('disabled', false).html('<i class="mdi mdi-check-all"></i> Confirm Validation');
    $('#hmoValidateConfirmModal').modal('show');
});

// Confirm button inside modal → execute
$('#hvc-confirm-btn').on('click', function() {
    var confirmBtn = $(this);
    var notes = $('#hvc-notes').val();
    confirmBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

    if (_hvcMode === 'single' && _hvcId) {
        $.ajax({
        url: wbUrl('reception/hmo-validate/' + _hvcId),
            method: 'POST',
            data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')), notes: notes },
            success: function(resp) {
                $('#hmoValidateConfirmModal').modal('hide');
                if (resp.success) {
                    if (_hvcRow) _hvcRow.fadeOut(400, function() { $(this).remove(); });
                    toastr.success(resp.message);
                    loadQueueCounts();
                    updateHmoBatchCount();
                } else {
                    toastr.warning(resp.message);
                }
            },
            error: function(xhr) {
                $('#hmoValidateConfirmModal').modal('hide');
                toastr.error(xhr.responseJSON?.message || 'Validation failed');
            }
        });
    } else if (_hvcMode === 'batch' && _hvcIds.length) {
        $.ajax({
            url: wbRoute('reception.hmo-batch-validate', '/reception/hmo-batch-validate'),
            method: 'POST',
            data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')), ids: _hvcIds, notes: notes },
            success: function(resp) {
                $('#hmoValidateConfirmModal').modal('hide');
                if (resp.success) {
                    toastr.success(resp.message);
                    loadHmoValidationList();
                    loadQueueCounts();
                } else {
                    toastr.warning(resp.message);
                }
            },
            error: function(xhr) {
                $('#hmoValidateConfirmModal').modal('hide');
                toastr.error(xhr.responseJSON?.message || 'Batch validation failed');
            }
        });
    }
});

function showHmoValidationPanel() {
    hideAllViews();
    $('#hmo-validation-view').show().addClass('active');
    $('.queue-item').removeClass('active');
    $('.queue-item[data-filter="hmo-pending-validation"]').addClass('active');
    loadHmoValidationList();
}

function loadHmoValidationList() {
    var search = $('#hmo-validation-search').val() || '';
    $('#hmo-validation-body').html('<tr><td colspan="8" class="text-center text-muted py-4"><i class="mdi mdi-loading mdi-spin"></i> Loading...</td></tr>');

    $.get(wbRoute('reception.hmo-pending-validation', '/reception/hmo-pending-validation'), { search: search }, function(resp) {
        var data = resp.data || [];
        $('#hmo-validation-total').text(data.length + ' pending');
        $('#hmo-select-all').prop('checked', false);

        if (data.length === 0) {
            $('#hmo-validation-body').html('<tr><td colspan="8" class="text-center text-muted py-4"><i class="mdi mdi-check-circle text-success"></i> No pending HMO requests</td></tr>');
            return;
        }

        var html = '';
        data.forEach(function(r) {
            var coverageBadge = r.coverage_mode === 'primary'
                ? '<span class="badge badge-warning">PRIMARY</span>'
                : '<span class="badge badge-danger">SECONDARY</span>';
            var hours = r.hours_pending || 0;
            var slaBadge = hours < 2
                ? '<span class="badge badge-success">' + hours + 'h</span>'
                : (hours < 4 ? '<span class="badge badge-warning">' + hours + 'h</span>' : '<span class="badge badge-danger">' + hours + 'h</span>');

            html += '<tr>';
            html += '<td><input type="checkbox" class="hmo-row-check" data-id="' + r.id + '" style="transform:scale(1.3);cursor:pointer;"></td>';
            html += '<td><strong>' + r.patient_name + '</strong><br><small class="text-muted">' + r.file_no + '</small></td>';
            html += '<td><small>' + r.hmo_name + '</small>' + (r.hmo_no ? '<br><small class="text-info">HMO#: ' + r.hmo_no + '</small>' : '') + '</td>';
            html += '<td><span class="badge badge-' + (r.item_type === 'Product' ? 'success' : 'info') + '">' + r.item_type + '</span><br><small>' + r.item_name + '</small></td>';
            html += '<td>' + coverageBadge + '</td>';
            html += '<td><strong>₦' + Number(r.claims_amount).toLocaleString() + '</strong></td>';
            html += '<td>' + slaBadge + '<br><small class="text-muted">' + (r.created_at || '') + '</small></td>';
            html += '<td><button class="btn btn-sm btn-success hmo-validate-btn" data-id="' + r.id + '"' + ' data-patient="' + (r.patient_name || '').replace(/"/g,'&quot;') + '"' + ' data-fileno="' + (r.file_no || '') + '"' + ' data-hmo="' + (r.hmo_name || '').replace(/"/g,'&quot;') + '"' + ' data-item="' + (r.item_name || '').replace(/"/g,'&quot;') + '"' + ' data-coverage="' + (r.coverage_mode || '') + '"' + ' data-amount="' + (r.claims_amount || 0) + '"' + '><i class="mdi mdi-check"></i> Validate</button></td>';
            html += '</tr>';
        });

        $('#hmo-validation-body').html(html);
    }).fail(function() {
        $('#hmo-validation-body').html('<tr><td colspan="8" class="text-center text-danger py-4">Failed to load requests</td></tr>');
    });
}

function updateHmoBatchCount() {
    var count = $('#hmo-validation-body .hmo-row-check:checked').length;
    $('#hmo-batch-count').text(count);
    $('#btn-hmo-batch-validate').prop('disabled', count === 0);
}

var appointmentsDataTable = null;
var appointmentsGlobalDataTable = null;
var referralsDataTable = null;
var appointmentsCalendar = null;
var currentApptCalendarView = 'calendar'; // 'calendar' or 'table'

// Close appointments view
$('#btn-close-appointments-view').on('click', function() {
    $('#appointments-calendar-view').removeClass('active');
});

// Calendar / Table toggle
$('#appt-view-toggle .btn').on('click', function() {
    var view = $(this).data('view');
    currentApptCalendarView = view;
    $('#appt-view-toggle .btn').removeClass('active');
    $(this).addClass('active');
    if (view === 'calendar') {
        $('#appointments-calendar-container').show();
        $('#appointments-table-container').hide();
        if (appointmentsCalendar) {
            $('#appointments-fullcalendar').fullCalendar('rerenderEvents');
        }
    } else {
        $('#appointments-calendar-container').hide();
        $('#appointments-table-container').show();
        initAppointmentsGlobalDataTable();
    }
});

// Calendar filter changes → when filters change we need a full refetch (data set changes completely)
$('#appt-cal-clinic-filter, #appt-cal-doctor-filter, #appt-cal-status-filter').on('change', function() {
    if (appointmentsCalendar) {
        // Filter change = entirely new data set, must use refetch
        $('#appointments-fullcalendar').fullCalendar('refetchEvents');
        window._apptCalLastEvents = {}; // reset fingerprints
    }
    if (appointmentsGlobalDataTable) {
        appointmentsGlobalDataTable.ajax.reload(null, false);
    }
});

// Populate doctor filter when clinic changes
$('#appt-cal-clinic-filter').on('change', function() {
    var clinicId = $(this).val();
    var $docFilter = $('#appt-cal-doctor-filter');
    $docFilter.empty().append('<option value="">All Doctors</option>');
    if (clinicId) {
        $.get(wbUrl("reception/clinics" + '/' + clinicId + '/doctors'), function(data) {
            var doctors = Array.isArray(data) ? data : (data.doctors || []);
            doctors.forEach(function(doc) {
                $docFilter.append('<option value="' + doc.id + '">' + doc.name + '</option>');
            });
        });
    }
});

var apptCalSearchTimer = null;
$('#appt-cal-search').on('keyup', function() {
    clearTimeout(apptCalSearchTimer);
    apptCalSearchTimer = setTimeout(function() {
        if (appointmentsGlobalDataTable) {
            appointmentsGlobalDataTable.search($('#appt-cal-search').val()).draw();
        }
    }, 300);
});

function showAppointmentsCalendarView() {
    hideAllViews();
    $('#appointments-calendar-view').show().addClass('active');

    if (!appointmentsCalendar) {
        initAppointmentsCalendar();
    } else {
        smoothRefreshApptCal();
    }
}

function initAppointmentsCalendar() {
    appointmentsCalendar = true;

    // ── Smooth refresh: diff-based update to avoid blink ────────────
    window._apptCalLastEvents = {};  // id → JSON fingerprint

    function apptCalFetchParams() {
        return {
            start: $('#appointments-fullcalendar').fullCalendar('getView').start.format('YYYY-MM-DD'),
            end: $('#appointments-fullcalendar').fullCalendar('getView').end.format('YYYY-MM-DD'),
            clinic_id: $('#appt-cal-clinic-filter').val(),
            doctor_id: $('#appt-cal-doctor-filter').val(),
            status: $('#appt-cal-status-filter').val(),
            include_queue: 1
        };
    }

    window.smoothRefreshApptCal = function() {
        if (!appointmentsCalendar) return;
        $.get(wbRoute('appointments.calendar-events', '/appointments/calendar-events'), apptCalFetchParams(), function(newEvents) {
            var cal = $('#appointments-fullcalendar');
            var newMap = {};
            (newEvents || []).forEach(function(e) {
                var fp = JSON.stringify([e.id, e.start, e.end, e.color, e.status, e.title, e.can_deliver, e.doctor_id, e.clinic_id]);
                newMap[e.id] = { data: e, fingerprint: fp };
            });

            // Remove events that are gone or changed
            var existingEvents = cal.fullCalendar('clientEvents');
            existingEvents.forEach(function(existing) {
                var newEntry = newMap[existing.id];
                if (!newEntry) {
                    // Event no longer exists — remove it
                    cal.fullCalendar('removeEvents', existing.id);
                } else if (newEntry.fingerprint !== window._apptCalLastEvents[existing.id]) {
                    // Event changed — remove so we can re-add with new data
                    cal.fullCalendar('removeEvents', existing.id);
                } else {
                    // Unchanged — keep it, mark as seen
                    delete newMap[existing.id];
                }
            });

            // Add new or changed events
            Object.keys(newMap).forEach(function(id) {
                cal.fullCalendar('renderEvent', newMap[id].data, true);
            });

            // Store fingerprints for next diff
            window._apptCalLastEvents = {};
            (newEvents || []).forEach(function(e) {
                window._apptCalLastEvents[e.id] = JSON.stringify([e.id, e.start, e.end, e.color, e.status, e.title, e.can_deliver, e.doctor_id, e.clinic_id]);
            });
        });
    };

    $('#appointments-fullcalendar').fullCalendar({
        header: {
            left: 'prev,today,next',
            center: 'title',
            right: 'agendaWeek,agendaDay,month'
        },
        defaultView: 'agendaWeek',
        editable: false,
        allDaySlot: false,
        slotDuration: '00:15:00',
        minTime: '07:00:00',
        maxTime: '20:00:00',
        slotEventOverlap: false,
        height: 'auto',
        contentHeight: 600,
        events: function(start, end, timezone, callback) {
            $.get(wbRoute('appointments.calendar-events', '/appointments/calendar-events'), {
                start: start.format('YYYY-MM-DD'),
                end: end.format('YYYY-MM-DD'),
                clinic_id: $('#appt-cal-clinic-filter').val(),
                doctor_id: $('#appt-cal-doctor-filter').val(),
                status: $('#appt-cal-status-filter').val(),
                include_queue: 1
            }, function(events) {
                // Store fingerprints so first smooth refresh can diff
                window._apptCalLastEvents = {};
                (events || []).forEach(function(e) {
                    window._apptCalLastEvents[e.id] = JSON.stringify([e.id, e.start, e.end, e.color, e.status, e.title, e.can_deliver, e.doctor_id, e.clinic_id]);
                });
                callback(events);
            }).fail(function() {
                callback([]);
                toastr.error('Failed to load calendar events');
            });
        },
        eventRender: function(event, element) {
            // Rich tooltip with delivery status + next step
            var tipContent = '<strong>' + event.patient_name + '</strong>';
            if (event.file_no) tipContent += '<br>File: ' + event.file_no;
            if (event.phone) tipContent += '<br>Phone: ' + event.phone;
            tipContent += '<br>Doctor: ' + (event.doctor || '');
            tipContent += '<br>Status: ' + event.status_label;
            if (event.is_follow_up) tipContent += '<br><span class="badge bg-info">Follow-Up</span>';
            if (event.event_type === 'queue') tipContent += '<br><span class="badge bg-secondary"><i class="mdi mdi-walk"></i> Walk-in Queue</span>';

            // Delivery status
            if (event.can_deliver === false) {
                tipContent += '<br><span style="color:#dc3545;"><i class="mdi mdi-alert-circle"></i> ' + (event.delivery_reason || 'Blocked') + '</span>';
            } else if (event.status === 1 || event.status === 2 || event.status === 3 || event.status === 4) {
                tipContent += '<br><span style="color:#198754;"><i class="mdi mdi-check-circle"></i> Payment OK</span>';
            }

            // Next step guidance
            if (event.next_step) {
                tipContent += '<br><em style="color:#0d6efd;font-size:0.85em;"><i class="mdi mdi-arrow-right-circle"></i> ' + event.next_step + '</em>';
            }

            element.attr('title', '');
            element.tooltip({ title: tipContent, html: true, container: 'body', placement: 'top' });

            // Delivery blocked visual (striped pattern)
            if (event.can_deliver === false) {
                element.css({
                    'background': 'repeating-linear-gradient(45deg, ' + event.color + ', ' + event.color + ' 10px, rgba(255,255,255,0.15) 10px, rgba(255,255,255,0.15) 12px)',
                    'border-right': '3px solid #dc3545'
                });
            }

            // Add file no to event display
            element.find('.fc-title').append(
                '<br><small style="opacity:0.85;">' + (event.file_no || '') +
                (event.phone ? ', ' + event.phone : '') + '</small>'
            );

            // Priority indicator
            if (event.priority === 'emergency') {
                element.css('border-left', '4px solid #dc3545');
            } else if (event.priority === 'urgent') {
                element.css('border-left', '4px solid #ffc107');
            }
        },
        eventClick: function(event, jsEvent, view) {
            jsEvent.preventDefault();
            jsEvent.stopPropagation(); // Prevent doc click handler removing the new menu immediately
            showAppointmentContextMenu(event, jsEvent);
        },
        dayClick: function(date, jsEvent, view) {
            // Could open new appointment form pre-filled with this date
        }
    });
}

// Context menu for calendar events (matching LinkHMS reference)
function showAppointmentContextMenu(event, jsEvent) {
    // Remove existing context menu
    $('.appt-context-menu').remove();

    var status = event.status;
    var isActive = status === 1 || status === 2 || status === 3 || status === 4;
    var isTerminal = status === 0 || status === 5 || status === 7;
    var menuItems = '';

    // Terminal state info line
    if (isTerminal) {
        var termText = status === 0 ? 'Cancelled' : (status === 5 ? 'Completed' : 'No-Show');
        var termIcon = status === 0 ? 'mdi-cancel text-danger' : (status === 5 ? 'mdi-check-circle text-success' : 'mdi-account-remove text-secondary');
        menuItems += '<a class="context-item" style="cursor:default;opacity:0.7;font-weight:600;" data-action="none"><i class="mdi ' + termIcon + '"></i> ' + termText + '</a>';
    }

    // Start Visit - for WAITING or READY
    if (status === 1 || status === 3) {
        if (event.can_deliver === false) {
            menuItems += '<a class="context-item" style="color:#dc3545;cursor:default;opacity:0.85;" data-action="none"><i class="mdi mdi-alert-circle"></i> ' + (event.delivery_reason || 'Payment Pending') + '</a>';
            menuItems += '<a class="context-item" style="color:#0d6efd;cursor:default;opacity:0.85;font-style:italic;" data-action="none"><i class="mdi mdi-arrow-right-circle"></i> Direct patient to billing/cashier</a>';
        } else {
            menuItems += '<a class="context-item text-success" style="cursor:default;" data-action="none"><i class="mdi mdi-check-circle"></i> Payment OK — In doctor\'s queue</a>';
        }
    }
    // Vitals Pending hint
    if (status === 2) {
        if (event.can_deliver === false) {
            menuItems += '<a class="context-item" style="color:#dc3545;cursor:default;opacity:0.85;" data-action="none"><i class="mdi mdi-alert-circle"></i> ' + (event.delivery_reason || 'Payment Pending') + '</a>';
        }
        menuItems += '<a class="context-item" style="color:#17a2b8;cursor:default;opacity:0.85;font-style:italic;" data-action="none"><i class="mdi mdi-needle"></i> Vitals in progress — waiting for nurse</a>';
    }
    // In Consultation hint
    if (status === 4) {
        menuItems += '<a class="context-item" style="color:#198754;cursor:default;opacity:0.85;font-style:italic;" data-action="none"><i class="mdi mdi-stethoscope"></i> Consultation in progress</a>';
    }
    // Check In - for SCHEDULED
    if (status === 6) {
        menuItems += '<a class="context-item text-info" data-action="checkin"><i class="mdi mdi-account-check"></i> Patient Arrived / Check-In</a>';
    }
    // Reschedule - for SCHEDULED and NO-SHOW
    if (status === 6 || status === 7) {
        menuItems += '<a class="context-item text-warning" data-action="reschedule"><i class="mdi mdi-calendar-edit"></i> Reschedule</a>';
    }
    // Change Doctor - for SCHEDULED
    if (status === 6) {
        menuItems += '<a class="context-item" style="color:#6f42c1;" data-action="reassign"><i class="mdi mdi-account-switch"></i> Change Doctor</a>';
    }
    // Cancel - for SCHEDULED, WAITING, VITALS_PENDING, READY
    if ([6, 1, 2, 3].indexOf(status) !== -1) {
        menuItems += '<a class="context-item text-danger" data-action="cancel"><i class="mdi mdi-close-circle"></i> Cancel</a>';
    }
    // No-Show - for SCHEDULED
    if (status === 6) {
        menuItems += '<a class="context-item text-muted" data-action="noshow"><i class="mdi mdi-account-off"></i> Mark No-Show</a>';
    }
    // View History
    menuItems += '<a class="context-item text-secondary" data-action="history"><i class="mdi mdi-link-variant"></i> View History</a>';

    // Next-step hint for non-obvious states
    if (event.next_step && (status === 1 || status === 2 || status === 3 || status === 6)) {
        menuItems += '<a class="context-item text-muted" style="cursor:default;opacity:0.7;font-size:0.78rem;" data-action="none"><i class="mdi mdi-lightbulb-outline text-warning"></i> ' + event.next_step + '</a>';
    }

    var menu = $('<div class="appt-context-menu" data-appt-id="' + (event.appointment_id || event.record_id || '') +
        '" data-event-type="' + (event.event_type || 'appointment') +
        '" data-queue-id="' + (event.queue_id || '') +
        '" data-clinic="' + (event.clinic_id || '') +
        '" data-doctor="' + (event.doctor_id || '') +
        '" data-date="' + (event.start ? moment(event.start).format('YYYY-MM-DD') : '') +
        '" data-patient="' + (event.patient_name || '') +
        '" data-reschedule-count="' + (event.reschedule_count || 0) +
        '">' + menuItems + '</div>');

    menu.css({ top: jsEvent.pageY, left: jsEvent.pageX });
    $('body').append(menu);

    // Auto-close on click outside
    setTimeout(function() {
        $(document).one('click', function() { $('.appt-context-menu').remove(); });
    }, 10);
}

// Context menu action handlers
$(document).on('click', '.appt-context-menu .context-item', function(e) {
    e.stopPropagation();
    var action = $(this).data('action');
    var $menu = $(this).closest('.appt-context-menu');
    var apptId = $menu.data('appt-id');

    $('.appt-context-menu').remove();

    switch (action) {
        case 'checkin':
            if (!confirm('Check in this appointment?')) return;
            $.post(wbRoute('appointments.check-in', '/appointments/check-in').replace('__AID__', apptId), { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) }, function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Checked in successfully.');
                    // Show actionable next-step guidance
                    toastr.info('<i class="mdi mdi-arrow-right-circle"></i> Patient is now <b>Waiting</b>. If HMO/payment is pending, direct them to <b>billing/cashier</b> before the doctor can start.', 'Next Step', { timeOut: 8000, extendedTimeOut: 4000, enableHtml: true });
                    refreshAppointmentViews();
                }
                else toastr.error(res.message);
            }).fail(function(xhr) { toastr.error(xhr.responseJSON?.message || 'Check-in failed'); });
            break;
        case 'cancel':
            var reason = prompt('Cancellation reason (optional):');
            if (reason === null) return;
            $.post(wbRoute('appointments.cancel', '/appointments/cancel').replace('__AID__', apptId), { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')), reason: reason }, function(res) {
                if (res.success) { toastr.success(res.message); refreshAppointmentViews(); }
                else toastr.error(res.message);
            }).fail(function(xhr) { toastr.error(xhr.responseJSON?.message || 'Cancel failed'); });
            break;
        case 'noshow':
            if (!confirm('Mark this appointment as No-Show?')) return;
            $.post(wbRoute('appointments.no-show', '/appointments/no-show').replace('__AID__', apptId), { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) }, function(res) {
                if (res.success) { toastr.success(res.message); refreshAppointmentViews(); }
                else toastr.error(res.message);
            }).fail(function(xhr) { toastr.error(xhr.responseJSON?.message || 'Failed'); });
            break;
        case 'reschedule':
            // Trigger the reschedule modal using data from context menu
            $('#reschedule-appt-id').val(apptId);
            $('#reschedule-patient-name').text($menu.data('patient'));
            $('#reschedule-original-date').text($menu.data('date'));
            $('#reschedule-count-info').text('Reschedule #' + (parseInt($menu.data('reschedule-count') || 0) + 1));
            var ctxReschClinic = $menu.data('clinic');
            $('#reschedule-clinic').val(ctxReschClinic);
            $('#reschedule-date').val('');
            $('#reschedule-time').empty().append('<option value="">-- Select date first --</option>').removeClass('d-none');
            $('#reschedule-custom-time-input').addClass('d-none').val('');
            $('#reschedule-custom-time-toggle').prop('checked', false);
            $('#reschedule-doctor').empty().append('<option value="">Same Doctor</option>');
            $('#reschedule-reason').val('');
            loadRescheduleModalDoctors(ctxReschClinic);
            $('#rescheduleAppointmentModal').modal('show');
            break;
        case 'reassign':
            // Directly open reassign modal (delegated handlers won't fire on detached elements)
            var ctxReassignDoctorId = $menu.data('doctor');
            $('#reassign-appt-id').val(apptId);
            $('#reassign-patient-name').text($menu.data('patient'));
            $('#reassign-current-doctor').text('Loading...');
            $('#reassign-doctor').empty().append('<option value="">Loading doctors...</option>');
            $('#reassign-reason').val('');
            $('#reassignDoctorModal').modal('show');
            $.get(wbRoute('appointments.available-doctors', '/appointments/available-doctors').replace('__AID__', apptId), function(res) {
                var $sel = $('#reassign-doctor');
                $sel.empty().append('<option value="">-- Select Doctor --</option>');
                if (res.success && res.doctors) {
                    var currentName = '';
                    res.doctors.forEach(function(doc) {
                        var isCurrent = doc.id == ctxReassignDoctorId;
                        if (isCurrent) currentName = doc.name;
                        $sel.append('<option value="' + doc.id + '"' + (isCurrent ? ' disabled' : '') + '>' + doc.name + (isCurrent ? ' (current)' : '') + '</option>');
                    });
                    $('#reassign-current-doctor').text(currentName || 'Unknown');
                }
            }).fail(function() {
                $('#reassign-doctor').empty().append('<option value="">Error loading doctors</option>');
            });
            break;
        case 'history':
            // Directly open chain modal (delegated events won't fire on detached elements)
            $('#chain-body').html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px"></i></div>');
            $('#appointmentChainModal').modal('show');
            $.get(wbRoute('appointments.chain', '/appointments/chain').replace('__AID__', apptId), function(res) {
                if (res.success && res.chain) {
                    var html = '<div class="appointment-chain-timeline">';
                    res.chain.forEach(function(item, idx) {
                        var isActive = item.id == apptId;
                        var statusBadge = item.status_badge || ('<span class="badge bg-secondary">' + (item.status_label || item.status) + '</span>');
                        html += '<div class="chain-item' + (isActive ? ' chain-item-active' : '') + '">';
                        html += '<div class="chain-marker"><span class="chain-dot' + (isActive ? ' active' : '') + '">' + (idx + 1) + '</span></div>';
                        html += '<div class="chain-content">';
                        html += '<div class="d-flex justify-content-between align-items-center mb-1">';
                        html += '<strong>' + (item.appointment_date || '') + '</strong> ' + statusBadge;
                        html += '</div>';
                        html += '<div class="text-muted small">';
                        html += (item.start_time || '') + ' - ' + (item.end_time || '') + ' &bull; Dr. ' + (item.doctor_name || 'Any');
                        html += '</div>';
                        if (item.appointment_type === 'follow_up') html += '<span class="badge bg-info badge-sm">Follow-Up</span> ';
                        if (item.rescheduled_from_id) html += '<span class="badge bg-warning badge-sm">Rescheduled</span> ';
                        if (item.reassignment_reason) html += '<span class="badge bg-purple badge-sm">Reassigned</span> ';
                        if (item.cancellation_reason) html += '<div class="text-muted small mt-1"><em>' + item.cancellation_reason + '</em></div>';
                        html += '</div></div>';
                    });
                    html += '</div>';
                    $('#chain-body').html(html);
                } else {
                    $('#chain-body').html('<div class="alert alert-warning">No chain data found.</div>');
                }
            }).fail(function() {
                $('#chain-body').html('<div class="alert alert-danger">Failed to load history.</div>');
            });
            break;
        case 'start-visit':
            // No longer used — replaced by status-aware hints
            break;
        case 'none':
            // Informational items — do nothing
            break;
    }
});

function refreshAppointmentViews() {
    loadQueueCounts();
    if (appointmentsCalendar) {
        smoothRefreshApptCal();
    }
    if (appointmentsDataTable) {
        appointmentsDataTable.ajax.reload(null, false);
    }
    if (appointmentsGlobalDataTable) {
        appointmentsGlobalDataTable.ajax.reload(null, false);
    }
    if (typeof patientAppointmentsDataTable !== 'undefined' && patientAppointmentsDataTable) {
        patientAppointmentsDataTable.ajax.reload(null, false);
    }
}

function initAppointmentsGlobalDataTable() {
    if (appointmentsGlobalDataTable) {
        appointmentsGlobalDataTable.ajax.reload(null, false);
        return;
    }

    appointmentsGlobalDataTable = $('#appointments-global-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbRoute('appointments.list', '/appointments/list'),
            data: function(d) {
                d.clinic_id = $('#appt-cal-clinic-filter').val();
                d.doctor_id = $('#appt-cal-doctor-filter').val();
                d.status = $('#appt-cal-status-filter').val();
                // Don't filter by date — show all upcoming
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'patient_name', name: 'patient_name' },
            { data: 'patient_file_no', name: 'patient_file_no' },
            { data: 'patient_hmo', name: 'patient_hmo' },
            { data: 'clinic_name', name: 'clinic_name' },
            { data: 'doctor_name', name: 'doctor_name' },
            { data: 'appointment_date', name: 'appointment_date' },
            { data: 'time_slot', name: 'start_time' },
            { data: 'type_badge', name: 'appointment_type', orderable: false },
            { data: 'status_badge', name: 'status', orderable: false },
            { data: 'delivery_info', name: 'delivery_info', orderable: false, searchable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[6, 'asc'], [7, 'asc']],
        pageLength: 20,
        language: {
            emptyTable: 'No appointments found',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        }
    });
}

// Keep the old function as alias for backward compatibility
function showAppointmentsQueueView() {
    showAppointmentsCalendarView();
}

function showReferralsQueueView() {
    referralViewMode = 'pending';
    _loadReferralsTable('pending', 'Pending Referrals');
}

// ─── Appointment actions from queue view ─────────────
$(document).on('click', '.btn-check-in-appointment', function() {
    var apptId = $(this).data('id');
    if (!confirm('Check in this appointment?')) return;
    $.post(wbRoute('appointments.check-in', '/appointments/check-in').replace('__AID__', apptId), { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            refreshAppointmentViews();
        } else {
            toastr.error(res.message);
        }
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Check-in failed');
    });
});

$(document).on('click', '.btn-cancel-appointment', function() {
    var apptId = $(this).data('id');
    var reason = prompt('Cancellation reason (optional):');
    if (reason === null) return;
    $.post(wbRoute('appointments.cancel', '/appointments/cancel').replace('__AID__', apptId), { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')), reason: reason }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            refreshAppointmentViews();
        } else {
            toastr.error(res.message);
        }
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Cancel failed');
    });
});

$(document).on('click', '.btn-noshow-appointment', function() {
    var apptId = $(this).data('id');
    if (!confirm('Mark this appointment as No-Show?')) return;
    $.post(wbRoute('appointments.no-show', '/appointments/no-show').replace('__AID__', apptId), { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            refreshAppointmentViews();
        } else {
            toastr.error(res.message);
        }
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Failed');
    });
});

// ─── Referral actions from queue view ─────────────

// Book referral — open modal
$(document).on('click', '.btn-book-referral', function() {
    $('#book-ref-id').val($(this).data('id'));
    $('#book-ref-clinic').val($(this).data('clinic'));
    $('#book-ref-doctor').val($(this).data('doctor'));
    $('#book-ref-patient').val($(this).data('patient'));
    $('#book-ref-date').val('');
    $('#book-ref-time').val('09:00');
    $('#book-ref-clinic-override').val('');
    $('#book-ref-doctor-override').val('');

    // Show target info
    var targetClinic = $(this).data('clinic-name') || '';
    var targetDoctor = $(this).data('doctor-name') || '';
    var info = [];
    if (targetClinic) info.push('Clinic: ' + targetClinic);
    if (targetDoctor) info.push('Doctor: ' + targetDoctor);
    if (info.length) {
        $('#book-ref-target-text').text(info.join(' — '));
        $('#book-ref-target-info').show();
    } else {
        $('#book-ref-target-info').hide();
    }

    // Pre-select existing values in override dropdowns
    if ($(this).data('clinic')) {
        $('#book-ref-clinic-override').val($(this).data('clinic'));
    }
    if ($(this).data('doctor')) {
        $('#book-ref-doctor-override').val($(this).data('doctor'));
    }

    $('#bookReferralModal').modal('show');
});

// Confirm book referral
$(document).on('click', '#confirm-book-referral', function() {
    var refId = $('#book-ref-id').val();
    var date = $('#book-ref-date').val();
    var time = $('#book-ref-time').val();
    if (!date) { toastr.warning('Please select an appointment date'); return; }
    if (!time) { toastr.warning('Please select a start time'); return; }

    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Booking...');

    $.post(wbRoute('referrals.book', '/referrals/book').replace('__RID__', refId), {
        _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
        appointment_date: date,
        start_time: time,
        clinic_id: $('#book-ref-clinic-override').val() || $('#book-ref-clinic').val(),
        doctor_id: $('#book-ref-doctor-override').val() || $('#book-ref-doctor').val()
    }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            $('#bookReferralModal').modal('hide');
            loadQueueCounts();
            if (referralsDataTable) referralsDataTable.ajax.reload(null, false);
        } else {
            toastr.error(res.message);
        }
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Booking failed');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="mdi mdi-calendar-check me-1"></i> Confirm Booking');
    });
});

// Refer out — open modal
$(document).on('click', '.btn-refer-out', function() {
    $('#refer-out-ref-id').val($(this).data('id'));
    $('#refer-out-notes').val('');
    $('#referOutModal').modal('show');
});

// Confirm refer out
$(document).on('click', '#confirm-refer-out', function() {
    var refId = $('#refer-out-ref-id').val();
    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

    $.post(wbRoute('referrals.refer-out', '/referrals/refer-out').replace('__RID__', refId), {
        _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
        action_notes: $('#refer-out-notes').val()
    }, function(res) {
        if (res.success) {
            toastr.success(res.message + ' — You can view & print the referral letter from the history.');
            $('#referOutModal').modal('hide');
            loadQueueCounts();
            // Switch to all view so user can see the referred-out entry
            showReferralsAllView();
        }
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Refer out failed');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="mdi mdi-check me-1"></i> Confirm Referred Out');
    });
});

// Decline referral — open modal
$(document).on('click', '.btn-decline-referral', function() {
    $('#decline-ref-id').val($(this).data('id'));
    $('#decline-reason').val('');
    $('#declineReferralModal').modal('show');
});

// Confirm decline
$(document).on('click', '#confirm-decline-referral', function() {
    var refId = $('#decline-ref-id').val();
    var reason = $('#decline-reason').val();
    if (!reason || reason.trim().length < 3) {
        toastr.warning('Please provide a reason for declining');
        return;
    }
    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Declining...');

    $.post(wbRoute('referrals.decline', '/referrals/decline').replace('__RID__', refId), {
        _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
        reason: reason
    }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            $('#declineReferralModal').modal('hide');
            if (referralsDataTable) referralsDataTable.ajax.reload(null, false);
        }
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Decline failed');
    }).always(function() {
        $btn.prop('disabled', false).html('<i class="mdi mdi-close-circle me-1"></i> Decline Referral');
    });
});

// Cancel referral
$(document).on('click', '.btn-cancel-referral', function() {
    var refId = $(this).data('id');
    if (!confirm('Cancel this referral? This cannot be undone.')) return;
    var $btn = $(this);
    $btn.prop('disabled', true);
    $.post(wbRoute('referrals.cancel', '/referrals/cancel').replace('__RID__', refId), {
        _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content'))
    }, function(res) {
        if (res.success) {
            toastr.success(res.message);
            loadQueueCounts();
            if (referralsDataTable) referralsDataTable.ajax.reload(null, false);
        }
    }).fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Cancel referral failed');
        $btn.prop('disabled', false);
    });
});

// View referral detail
$(document).on('click', '.btn-view-referral', function() {
    var refId = $(this).data('id');
    $('#referral-detail-loading').show();
    $('#referral-detail-content').hide();
    $('#btn-print-referral-letter').hide();
    $('#referralDetailModal').modal('show');

    $.get(wbRoute('referrals.detail', '/referrals/detail').replace('__RID__', refId), function(data) {
        if (!data.success) { toastr.error('Failed to load referral'); return; }

        var ref = data.referral;
        var urgencyClass = { 'emergency': 'text-danger', 'urgent': 'text-warning', 'routine': 'text-secondary' }[ref.urgency] || '';
        var statusBadge = {
            'pending': '<span class="badge bg-warning text-dark">Pending</span>',
            'booked': '<span class="badge bg-primary">Booked</span>',
            'completed': '<span class="badge bg-success">Completed</span>',
            'cancelled': '<span class="badge bg-danger">Cancelled</span>',
            'declined': '<span class="badge bg-dark">Declined</span>',
            'referred_out': '<span class="badge bg-purple text-white">Referred Out</span>'
        }[ref.status] || '<span class="badge bg-secondary">' + ref.status + '</span>';

        var html = '<div class="p-3">';
        html += '<div class="d-flex justify-content-between mb-3">';
        html += '<div>' + statusBadge + ' <span class="badge ' + (ref.referral_type === 'internal' ? 'bg-info' : 'bg-dark') + '">' + (ref.referral_type === 'internal' ? 'Internal' : 'External') + '</span></div>';
        html += '<span class="' + urgencyClass + ' fw-bold text-uppercase">' + ref.urgency + '</span>';
        html += '</div>';

        html += '<h6 class="border-bottom pb-1 mb-2"><i class="mdi mdi-account me-1"></i> Patient Information</h6>';
        html += '<div class="row mb-3">';
        html += '<div class="col-md-4"><small class="text-muted">Name</small><br><strong>' + ref.patient_name + '</strong></div>';
        html += '<div class="col-md-4"><small class="text-muted">File No</small><br><strong>' + ref.patient_file_no + '</strong></div>';
        html += '<div class="col-md-4"><small class="text-muted">HMO</small><br><strong>' + ref.patient_hmo + '</strong></div>';
        html += '</div>';

        html += '<h6 class="border-bottom pb-1 mb-2"><i class="mdi mdi-stethoscope me-1"></i> Referral Information</h6>';
        html += '<div class="row mb-3">';
        html += '<div class="col-md-6"><small class="text-muted">Referring Doctor</small><br><strong>' + ref.referring_doctor + '</strong></div>';
        html += '<div class="col-md-6"><small class="text-muted">Referring Clinic</small><br><strong>' + ref.referring_clinic + '</strong></div>';
        html += '</div>';

        if (ref.referral_type === 'internal') {
            html += '<div class="row mb-3">';
            html += '<div class="col-md-6"><small class="text-muted">Target Clinic</small><br><strong>' + (ref.target_clinic || 'Any') + '</strong></div>';
            html += '<div class="col-md-6"><small class="text-muted">Target Doctor</small><br><strong>' + (ref.target_doctor || 'Any Available') + '</strong></div>';
            html += '</div>';
        } else {
            html += '<div class="row mb-3">';
            html += '<div class="col-md-6"><small class="text-muted">External Facility</small><br><strong>' + (ref.external_facility_name || 'N/A') + '</strong></div>';
            html += '<div class="col-md-6"><small class="text-muted">External Doctor</small><br><strong>' + (ref.external_doctor_name || 'N/A') + '</strong></div>';
            html += '</div>';
            if (ref.external_facility_address || ref.external_facility_phone) {
                html += '<div class="row mb-3">';
                html += '<div class="col-md-8"><small class="text-muted">Address</small><br>' + (ref.external_facility_address || '—') + '</div>';
                html += '<div class="col-md-4"><small class="text-muted">Phone</small><br>' + (ref.external_facility_phone || '—') + '</div>';
                html += '</div>';
            }
        }

        html += '<h6 class="border-bottom pb-1 mb-2"><i class="mdi mdi-clipboard-pulse me-1"></i> Clinical Details</h6>';
        if (ref.provisional_diagnosis) html += '<div class="mb-2"><small class="text-muted">Provisional Diagnosis</small><br>' + ref.provisional_diagnosis + '</div>';
        if (ref.clinical_summary) html += '<div class="mb-2"><small class="text-muted">Clinical Summary</small><br>' + ref.clinical_summary + '</div>';
        html += '<div class="mb-2"><small class="text-muted">Reason for Referral</small><br>' + (ref.reason || 'N/A') + '</div>';

        if (ref.action_notes) {
            html += '<h6 class="border-bottom pb-1 mb-2 mt-3"><i class="mdi mdi-note-text me-1"></i> Action Notes</h6>';
            html += '<div class="mb-2">' + ref.action_notes + '</div>';
            if (ref.actioned_at) html += '<small class="text-muted">Actioned: ' + ref.actioned_at + '</small>';
        }

        html += '<div class="text-end mt-3"><small class="text-muted">Created: ' + ref.created_at + '</small></div>';
        html += '</div>';

        $('#referral-detail-content').html(html).show();
        $('#referral-detail-loading').hide();

        // Show print button for external referrals
        if (ref.referral_type === 'external') {
            $('#btn-print-referral-letter').data('ref-data', data).show();
        }

    }).fail(function() {
        $('#referral-detail-loading').hide();
        $('#referral-detail-content').html('<div class="alert alert-danger m-3">Failed to load referral details</div>').show();
    });
});

// Print referral letter — open via detail modal
$(document).on('click', '#btn-print-referral-letter', function() {
    var cachedData = $(this).data('ref-data');
    if (cachedData) {
        buildAndPrintReferralLetter(cachedData);
    }
});

// Print referral directly from table
$(document).on('click', '.btn-print-referral', function() {
    var refId = $(this).data('id');
    $.get(wbRoute('referrals.detail', '/referrals/detail').replace('__RID__', refId), function(data) {
        if (data.success) {
            buildAndPrintReferralLetter(data);
        } else {
            toastr.error('Failed to load referral for printing');
        }
    }).fail(function() {
        toastr.error('Failed to load referral');
    });
});

function buildAndPrintReferralLetter(data) {
    var ref = data.referral;
    var hosp = data.hospital;
    var today = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    var urgencyClass = 'urgency-' + (ref.urgency || 'routine');

    var html = '<div class="referral-letter">';

    // Hospital header
    html += '<div class="letter-header">';
    if (hosp.logo) html += '<img src="' + hosp.logo + '" alt="Logo">';
    html += '<h2>' + (hosp.name || 'Hospital') + '</h2>';
    if (hosp.address) html += '<p>' + hosp.address + '</p>';
    var contacts = [];
    if (hosp.phones) contacts.push(hosp.phones);
    if (hosp.email) contacts.push(hosp.email);
    if (contacts.length) html += '<p>' + contacts.join(' | ') + '</p>';
    html += '</div>';

    // Title
    html += '<div class="letter-title">Specialist Referral Letter</div>';

    // Meta row
    html += '<div class="letter-meta">';
    html += '<div><strong>Date:</strong> ' + today + '</div>';
    html += '<div><strong>Ref #:</strong> REF-' + String(ref.id).padStart(5, '0') + '</div>';
    html += '</div>';

    // Urgency stamp
    if (ref.urgency !== 'routine') {
        html += '<div style="text-align:right; margin-bottom:10px;"><span class="urgency-stamp ' + urgencyClass + '">' + ref.urgency + '</span></div>';
    }

    // To: section
    html += '<div class="letter-body">';
    html += '<p><strong>To:</strong><br>';
    if (ref.external_doctor_name) html += ref.external_doctor_name + '<br>';
    html += (ref.external_facility_name || 'The Receiving Doctor') + '<br>';
    if (ref.external_facility_address) html += ref.external_facility_address + '<br>';
    if (ref.external_facility_phone) html += 'Tel: ' + ref.external_facility_phone;
    html += '</p>';

    // Patient info table
    html += '<table class="patient-info-table">';
    html += '<tr><td class="label-cell">Patient Name</td><td>' + ref.patient_name + '</td><td class="label-cell">File No</td><td>' + ref.patient_file_no + '</td></tr>';
    var row2 = '<tr>';
    row2 += '<td class="label-cell">Gender</td><td>' + (ref.patient_gender || '—') + '</td>';
    row2 += '<td class="label-cell">Date of Birth</td><td>' + (ref.patient_dob || '—') + '</td>';
    row2 += '</tr>';
    html += row2;
    html += '<tr><td class="label-cell">HMO / Insurance</td><td colspan="3">' + (ref.patient_hmo || '—') + '</td></tr>';
    html += '</table>';

    // Dear Doctor
    html += '<p>Dear Colleague,</p>';

    html += '<p>I am writing to refer the above-named patient to your facility for specialist evaluation and management.</p>';

    // Diagnosis
    if (ref.provisional_diagnosis) {
        html += '<p><strong>Provisional Diagnosis:</strong> ' + ref.provisional_diagnosis + '</p>';
    }

    // Clinical summary
    if (ref.clinical_summary) {
        html += '<p><strong>Clinical Summary:</strong><br>' + ref.clinical_summary + '</p>';
    }

    // Reason
    html += '<p><strong>Reason for Referral:</strong><br>' + (ref.reason || 'N/A') + '</p>';

    html += '<p>Kindly evaluate and manage as appropriate. I would appreciate feedback on the patient\'s progress and management plan.</p>';

    html += '<p>Thank you for your kind attention.</p>';
    html += '</div>';

    // Signature
    html += '<div class="letter-signature">';
    html += '<p>Yours faithfully,</p>';
    html += '<div class="sig-line">';
    html += '<strong>' + ref.referring_doctor + '</strong><br>';
    html += ref.referring_clinic + '<br>';
    html += (hosp.name || '');
    html += '</div>';
    html += '</div>';

    // Footer
    html += '<div class="letter-footer">';
    html += 'This referral letter was generated electronically by ' + (hosp.name || 'the hospital') + ' on ' + today + '. Ref #REF-' + String(ref.id).padStart(5, '0');
    html += '</div>';

    html += '</div>';

    $('#referral-print-area').html(html);

    setTimeout(function() {
        window.print();
    }, 300);
}

// ─── Referral History Toggle ─────────────
var referralViewMode = 'pending'; // 'pending' or 'all'

function showReferralsAllView() {
    referralViewMode = 'all';
    _loadReferralsTable('all', 'All Referrals (History)');
}

function _loadReferralsTable(statusFilter, titleText) {
    hideAllViews();
    $('#queue-view').show().addClass('active');
    $('#queue-view-title').html(titleText + ' <div class="btn-group btn-group-sm ms-3" role="group">' +
        '<button class="btn btn-' + (statusFilter === 'pending' ? '' : 'outline-') + 'primary btn-referral-filter" data-filter="pending">Pending</button>' +
        '<button class="btn btn-' + (statusFilter === 'all' ? '' : 'outline-') + 'primary btn-referral-filter" data-filter="all">All History</button>' +
        '</div>');

    if (queueDataTable) { queueDataTable.destroy(); queueDataTable = null; }
    if (referralsDataTable) { referralsDataTable.destroy(); referralsDataTable = null; }

    var showStatus = (statusFilter === 'all');

    // Rebuild headers — add Status column for "all" view
    var headerHtml = '<thead><tr><th>#</th><th>Patient</th><th>File No</th><th>Referring Doctor</th><th>Clinic</th><th>Target</th><th>Urgency</th><th>Type</th>';
    if (showStatus) headerHtml += '<th>Status</th>';
    headerHtml += '<th>Time</th><th>Actions</th></tr></thead>';
    $('#queue-datatable').empty().html(headerHtml);

    var columns = [
        { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
        { data: 'patient_name', name: 'patient_name' },
        { data: 'patient_file_no', name: 'patient_file_no' },
        { data: 'referring_doctor', name: 'referring_doctor' },
        { data: 'referring_clinic', name: 'referring_clinic' },
        { data: 'target_info', name: 'target_info' },
        { data: 'urgency_badge', name: 'urgency', orderable: false },
        { data: 'type_badge', name: 'referral_type', orderable: false },
    ];
    if (showStatus) columns.push({ data: 'status_badge', name: 'status', orderable: false });
    columns.push({ data: 'time', name: 'created_at' });
    columns.push({ data: 'actions', name: 'actions', orderable: false, searchable: false });

    referralsDataTable = $('#queue-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbRoute('referrals.pending', '/referrals/pending'),
            data: function(d) { d.status = statusFilter; }
        },
        columns: columns,
        order: [[showStatus ? 9 : 8, 'desc']],
        pageLength: 15,
        language: {
            emptyTable: statusFilter === 'pending' ? 'No pending referrals' : 'No referrals found',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        }
    });
}

// Filter toggle buttons
$(document).on('click', '.btn-referral-filter', function() {
    var filter = $(this).data('filter');
    referralViewMode = filter;
    if (filter === 'pending') {
        showReferralsQueueView();
    } else {
        showReferralsAllView();
    }
});

// ─── Reschedule Appointment ────────────────────────────────────────

/**
 * Populate the #reschedule-doctor select with doctors for the given clinic.
 * If selectedDoctorId is provided, that option will be pre-selected.
 */
function loadRescheduleModalDoctors(clinicId, selectedDoctorId) {
    var $sel = $('#reschedule-doctor');
    if (!clinicId) { $sel.empty().append('<option value="">Same Doctor</option>'); return; }
    $.get(wbUrl("reception/clinics" + '/' + clinicId + '/doctors'), function(doctors) {
        $sel.empty().append('<option value="">Same Doctor</option>');
        if (doctors && doctors.length) {
            doctors.forEach(function(doc) {
                var selected = (selectedDoctorId && doc.id == selectedDoctorId) ? ' selected' : '';
                $sel.append('<option value="' + doc.id + '"' + selected + '>' + doc.name + '</option>');
            });
        }
    }).fail(function() {
        $sel.empty().append('<option value="">Same Doctor</option>');
    });
}

$(document).on('click', '.btn-reschedule-appointment', function() {
    var $btn = $(this);
    var apptId = $btn.data('id');
    var clinicId = $btn.data('clinic');
    var doctorId = $btn.data('doctor');
    var origDate = $btn.data('date');
    var patientName = $btn.data('patient');
    var rescheduleCount = $btn.data('reschedule-count') || 0;

    $('#reschedule-appt-id').val(apptId);
    $('#reschedule-patient-name').text(patientName);
    $('#reschedule-original-date').text(origDate);
    $('#reschedule-count-info').text('Reschedule #' + (parseInt(rescheduleCount) + 1));
    $('#reschedule-clinic').val(clinicId);
    $('#reschedule-date').val('');
    $('#reschedule-time').empty().append('<option value="">-- Select date first --</option>').removeClass('d-none');
    $('#reschedule-custom-time-input').addClass('d-none').val('');
    $('#reschedule-custom-time-toggle').prop('checked', false);
    $('#reschedule-doctor').empty().append('<option value="">Same Doctor</option>');
    $('#reschedule-reason').val('');
    loadRescheduleModalDoctors(clinicId, doctorId);

    $('#rescheduleAppointmentModal').modal('show');
});

$(document).on('change', '#reschedule-date, #reschedule-clinic, #reschedule-doctor', function() {
    if ($('#reschedule-custom-time-toggle').is(':checked')) return; // skip if custom time active
    var date = $('#reschedule-date').val();
    var clinicId = $('#reschedule-clinic').val();
    var doctorId = $('#reschedule-doctor').val();
    if (!date || !clinicId) {
        $('#reschedule-time').empty().append('<option value="">-- Select date & clinic first --</option>');
        return;
    }
    $.get(wbRoute('appointments.available-slots', '/appointments/available-slots'), {
        date: date, clinic_id: clinicId, doctor_id: doctorId
    }, function(response) {
        var $sel = $('#reschedule-time');
        $sel.empty().append('<option value="">-- Select Time --</option>');
        if (response.success && response.slots && response.slots.length> 0) {
            var hasSlots = false;
            response.slots.forEach(function(slot) {
                if (slot.available) {
                    hasSlots = true;
                    $sel.append('<option value="' + slot.time + '">' + slot.time + '</option>');
                }
            });
            if (!hasSlots) {
                $sel.append('<option value="" disabled>No available slots — use custom time</option>');
            }
        } else {
            $sel.append('<option value="" disabled>No slots configured — use custom time</option>');
        }
    });
});

// Custom time toggle
$(document).on('change', '#reschedule-custom-time-toggle', function() {
    var isCustom = $(this).is(':checked');
    if (isCustom) {
        $('#reschedule-time').addClass('d-none');
        $('#reschedule-custom-time-input').removeClass('d-none');
    } else {
        $('#reschedule-custom-time-input').addClass('d-none').val('');
        $('#reschedule-time').removeClass('d-none');
        // Re-fetch slots if date set
        var date = $('#reschedule-date').val();
        if (date) $('#reschedule-date').trigger('change');
    }
});

$(document).on('submit', '#reschedule-form', function(e) {
    e.preventDefault();
    var apptId = $('#reschedule-appt-id').val();
    var $submitBtn = $(this).find('button[type="submit"]');
    $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Rescheduling...');

    var isCustomTime = $('#reschedule-custom-time-toggle').is(':checked');
    var startTime = isCustomTime ? $('#reschedule-custom-time-input').val() : $('#reschedule-time').val();
    var endTime = isCustomTime ? '' : ($('#reschedule-time option:selected').data('end') || '');

    if (!startTime) {
        $submitBtn.prop('disabled', false).html('<i class="mdi mdi-calendar-edit"></i> Reschedule');
        toastr.warning('Please select or enter a time.');
        return;
    }

    $.ajax({
        url: wbRoute('appointments.reschedule', '/appointments/reschedule').replace('__AID__', apptId),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            appointment_date: $('#reschedule-date').val(),
            start_time: startTime,
            end_time: endTime,
            custom_time: isCustomTime ? 1 : 0,
            doctor_id: $('#reschedule-doctor').val(),
            reason: $('#reschedule-reason').val()
        },
        success: function(res) {
            if (res.success) {
                toastr.success(res.message);
                $('#rescheduleAppointmentModal').modal('hide');
                refreshAppointmentViews();
                if (typeof patientAppointmentsDataTable !== 'undefined' && patientAppointmentsDataTable) {
                    patientAppointmentsDataTable.ajax.reload(null, false);
                }
            } else {
                toastr.error(res.message);
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Reschedule failed');
        },
        complete: function() {
            $submitBtn.prop('disabled', false).html('<i class="mdi mdi-calendar-edit"></i> Reschedule');
        }
    });
});

// ─── Reassign Doctor ───────────────────────────────────────────────
$(document).on('click', '.btn-reassign-appointment', function() {
    var $btn = $(this);
    var apptId = $btn.data('id');
    var clinicId = $btn.data('clinic');
    var currentDoctorId = $btn.data('doctor');
    var patientName = $btn.data('patient');

    $('#reassign-appt-id').val(apptId);
    $('#reassign-patient-name').text(patientName);
    $('#reassign-current-doctor').text('Loading...');
    $('#reassign-doctor').empty().append('<option value="">Loading doctors...</option>');
    $('#reassign-reason').val('');

    $('#reassignDoctorModal').modal('show');

    // Load available doctors for this appointment
    $.get(wbRoute('appointments.available-doctors', '/appointments/available-doctors').replace('__AID__', apptId), function(res) {
        var $sel = $('#reassign-doctor');
        $sel.empty().append('<option value="">-- Select Doctor --</option>');
        if (res.success && res.doctors) {
            var currentName = '';
            res.doctors.forEach(function(doc) {
                var isCurrent = doc.id == currentDoctorId;
                if (isCurrent) currentName = doc.name;
                $sel.append('<option value="' + doc.id + '"' + (isCurrent ? ' disabled' : '') + '>' + doc.name + (isCurrent ? ' (current)' : '') + '</option>');
            });
            $('#reassign-current-doctor').text(currentName || 'Unknown');
        }
    }).fail(function() {
        $('#reassign-doctor').empty().append('<option value="">Error loading doctors</option>');
    });
});

$(document).on('submit', '#reassign-form', function(e) {
    e.preventDefault();
    var apptId = $('#reassign-appt-id').val();
    var $submitBtn = $(this).find('button[type="submit"]');
    $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Reassigning...');

    $.ajax({
        url: wbRoute('appointments.reassign', '/appointments/reassign').replace('__AID__', apptId),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            doctor_id: $('#reassign-doctor').val(),
            reason: $('#reassign-reason').val()
        },
        success: function(res) {
            if (res.success) {
                toastr.success(res.message);
                $('#reassignDoctorModal').modal('hide');
                refreshAppointmentViews();
                if (typeof patientAppointmentsDataTable !== 'undefined' && patientAppointmentsDataTable) {
                    patientAppointmentsDataTable.ajax.reload(null, false);
                }
            } else {
                toastr.error(res.message);
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Reassignment failed');
        },
        complete: function() {
            $submitBtn.prop('disabled', false).html('<i class="mdi mdi-account-switch"></i> Reassign');
        }
    });
});

// ─── Appointment Chain / History ───────────────────────────────────
$(document).on('click', '.btn-view-chain', function() {
    var apptId = $(this).data('id');
    $('#chain-body').html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px"></i></div>');
    $('#appointmentChainModal').modal('show');

    $.get(wbRoute('appointments.chain', '/appointments/chain').replace('__AID__', apptId), function(res) {
        if (res.success && res.chain) {
            var html = '<div class="appointment-chain-timeline">';
            res.chain.forEach(function(item, idx) {
                var isActive = item.id == apptId;
                var statusBadge = item.status_badge || ('<span class="badge bg-secondary">' + (item.status_label || item.status) + '</span>');
                html += '<div class="chain-item' + (isActive ? ' chain-item-active' : '') + '">';
                html += '<div class="chain-marker"><span class="chain-dot' + (isActive ? ' active' : '') + '">' + (idx + 1) + '</span></div>';
                html += '<div class="chain-content">';
                html += '<div class="d-flex justify-content-between align-items-center mb-1">';
                html += '<strong>' + (item.appointment_date || '') + '</strong> ' + statusBadge;
                html += '</div>';
                html += '<div class="text-muted small">';
                html += (item.start_time || '') + ' - ' + (item.end_time || '') + ' &bull; Dr. ' + (item.doctor_name || 'Any');
                html += '</div>';
                if (item.appointment_type === 'follow_up') html += '<span class="badge bg-info badge-sm">Follow-Up</span> ';
                if (item.rescheduled_from_id) html += '<span class="badge bg-warning badge-sm">Rescheduled</span> ';
                if (item.reassignment_reason) html += '<span class="badge bg-purple badge-sm">Reassigned</span> ';
                if (item.cancellation_reason) html += '<div class="text-muted small mt-1"><em>' + item.cancellation_reason + '</em></div>';
                html += '</div></div>';
            });
            html += '</div>';
            $('#chain-body').html(html);
        } else {
            $('#chain-body').html('<p class="text-muted">No chain data available.</p>');
        }
    }).fail(function() {
        $('#chain-body').html('<p class="text-danger">Failed to load appointment history.</p>');
    });
});

// ─── Patient Appointments Tab DataTable ────────────────────────────
var patientAppointmentsDataTable = null;

function loadPatientAppointments(patientId) {
    if (!patientId) return;

    if (patientAppointmentsDataTable) {
        patientAppointmentsDataTable.destroy();
    }

    patientAppointmentsDataTable = $('#patient-appointments-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbRoute('appointments.list', '/appointments/list'),
            data: function(d) {
                d.patient_id = patientId;
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'appointment_date', name: 'appointment_date' },
            { data: 'time_slot', name: 'start_time' },
            { data: 'clinic_name', name: 'clinic_name' },
            { data: 'doctor_name', name: 'doctor_name' },
            { data: 'type_badge', name: 'appointment_type', orderable: false },
            { data: 'status_badge', name: 'status', orderable: false },
            { data: 'delivery_info', name: 'delivery_info', orderable: false, searchable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'desc']],
        pageLength: 10,
        language: {
            emptyTable: 'No appointments found for this patient',
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
        }
    });
}

