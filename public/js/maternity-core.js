    window.BILLING_KIT_CONFIG = {
        csrf: window.WORKBENCH_CONFIG.csrf,
        addServiceRoute: window.WORKBENCH_CONFIG.addServiceRoute,
        addLabRoute: window.WORKBENCH_CONFIG.addLabRoute,
        addImagingRoute: window.WORKBENCH_CONFIG.addImagingRoute,
        addConsumableRoute: window.WORKBENCH_CONFIG.addConsumableRoute,
        removeBillBase: '/nursing-workbench/remove-bill',
        pendingBillsBase: '/nursing-workbench/patient',
        serviceRequestsBase: '/nursing-workbench/patient',
        searchServicesRoute: window.WORKBENCH_CONFIG.searchServicesRoute,
        searchProductsRoute: window.WORKBENCH_CONFIG.searchProductsRoute,
        productBatchesRoute: window.WORKBENCH_CONFIG.productBatchesRoute,
        investigationCategoryId: window.WORKBENCH_CONFIG.investigationCategoryId,
        imagingCategoryId: 6,
        resolvedStoreId: window.WORKBENCH_CONFIG.resolvedStoreId,
        resolvedStoreName: window.WORKBENCH_CONFIG.resolvedStoreName,
        showMedicationOption: true,
    };
    // ═══════════════════════════════════════════════════════════════
    // GLOBAL STATE (mirrors nursing workbench pattern)
    // ═══════════════════════════════════════════════════════════════
    let currentPatient = null;
    let currentPatientData = null;
    let currentEnrollment = null;
    let currentEnrollmentId = null;
    Object.defineProperty(window, 'maternityEnrollmentId', {
        get: function() { return currentEnrollmentId; }
    });
    const CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

    // ═══════════════════════════════════════════════════════════════
    // PATIENT FORM CONFIG (ANC Registration)
    // ═══════════════════════════════════════════════════════════════
    window.patientFormConfig = {
        nextFileNumberUrl: '/reception/patient/next-file-number',
        checkFileNumberUrl: '/reception/patient/check-file-number',
        updateUrl: '/reception/patient/__ID__/update',
        registerUrl: '/reception/patient/quick-register',
        hmos: window.WORKBENCH_CONFIG?.hmos || [],
        onSuccess: function(patientId, mode) {
            toastr.success('ANC patient registered successfully');
            $('#patientFormModal').modal('hide');
            // Load the newly created patient in the workbench
            if (typeof loadPatient === 'function') {
                loadPatient(patientId);
            }
        },
        onSelectExisting: function(patientId) {
            toastr.info('Loading existing patient...');
            if (typeof loadPatient === 'function') {
                loadPatient(patientId);
            }
        }
    };

    function openAncPatientRegistration() {
        // Show modal in create mode
        showPatientFormModal('create');

        // After modal is shown, switch to ANC file number mode
        $('#patientFormModal').one('shown.bs.modal', function() {
            // Hide auto/manual toggle — ANC mode uses prefix hint instead
            $('.file-no-btn-group').hide();
            $('#pf-file-no-info').hide();

            // Set file number field as editable with ANC prefix
            var $input = $('#pf-file-no');
            $input.prop('readonly', false);

            // Generate ANC file number
            generateAncFileNumber();

            // Update modal title
            $('#patient-form-title').html('<i class="mdi mdi-clipboard-plus"></i> New ANC Patient Registration');

            // Pre-select Female gender
            $('#pf-gender').val('Female');
        });
    }

    function generateAncFileNumber() {
        $.ajax({
            url: wbUrl('/reception/patient/next-file-number'),
            method: 'GET',
            data: {
                prefix: 'ANC-'
            },
            success: function(response) {
                var nextFileNo = response.file_no;
                $('#pf-file-no').val(nextFileNo).addClass('status-valid');
                $('#pf-next-file-no').text(nextFileNo);
                $('#pf-duplicate-warning').hide();

                // Show last 2 ANC numbers as hint
                var recent = response.recent_file_nos || [];
                var lastTwo = recent.slice(0, 2);
                if (lastTwo.length> 0) {
                    $('#pf-file-no-hint').html('<small class="text-muted">Recent: ' + lastTwo.join(', ') + '</small>').show();
                } else {
                    $('#pf-file-no-hint').html('').hide();
                }
            },
            error: function() {
                $('#pf-file-no').val('ANC-001');
                $('#pf-next-file-no').text('ANC-001');
            }
        });
    }

    // Edit mode state tracking (shared pattern for modal reuse)
    let _editMode = null; // null = create, 'anc'|'postnatal'|'baby'|'note'|'history'|'pregnancy' = edit
    let _editId = null; // ID of record being edited

    // Data caches for edit pre-fill
    let _ancVisitsCache = [];
    let _postnatalVisitsCache = [];
    let _notesCache = [];

    // ═══════════════════════════════════════════════════════════════
    // RICH TEXT EDITOR HELPER (CKEditor5)
    // ═══════════════════════════════════════════════════════════════
    const MaternityEditors = {};

    function initMaternityEditor(selector, key) {
        const el = document.querySelector(selector);
        if (!el || MaternityEditors[key]) return Promise.resolve(MaternityEditors[key]);
        return ClassicEditor.create(el, {
            toolbar: {
                items: ['heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', '|', 'outdent', 'indent', '|', 'blockQuote', 'insertTable', 'undo', 'redo']
            }
        }).then(editor => {
            MaternityEditors[key] = editor;
            return editor;
        }).catch(err => console.error('CKEditor init error:', err));
    }

    function destroyMaternityEditor(key) {
        if (MaternityEditors[key]) {
            MaternityEditors[key].destroy().catch(() => {});
            delete MaternityEditors[key];
        }
    }

    function getEditorData(key, fallbackSelector) {
        if (MaternityEditors[key]) return MaternityEditors[key].getData();
        return $(fallbackSelector).val() || '';
    }

    // ═══════════════════════════════════════════════════════════════
    // VIEW MANAGEMENT (SHARED with nursing workbench — identical)
    // ═══════════════════════════════════════════════════════════════
    function hideAllViews() {
        $('#empty-state').hide();
        $('#queue-view').removeClass('active').hide();
        $('#reports-view').removeClass('active').hide();
        $('#patient-header').removeClass('active');
        $('#workspace-content').removeClass('active').hide();
    }

    function showQueue(filter) {
        hideAllViews();
        const titles = {
            'active-anc': '<i class="mdi mdi-mother-nurse"></i> Active ANC Patients',
            'due-visits': '<i class="mdi mdi-calendar-clock"></i> Due Visits',
            'upcoming-edd': '<i class="mdi mdi-calendar-star"></i> Upcoming EDD (Next 4 Weeks)',
            'postnatal': '<i class="mdi mdi-account-heart"></i> Postnatal Patients',
            'overdue-immunization': '<i class="mdi mdi-needle"></i> Overdue Immunizations',
            'high-risk': '<i class="mdi mdi-alert"></i> High Risk Patients',
        };
        $('#queue-view-title').html(titles[filter] || titles['active-anc']);
        $('.queue-item').removeClass('active');
        $(`.queue-item[data-filter="${filter}"]`).addClass('active');
        $('#queue-view').addClass('active').css('display', 'flex');
        if (window.innerWidth < 768) {
            $('#left-panel').addClass('hidden');
            $('#main-workspace').addClass('active');
        }
        loadQueueData(filter);
    }

    function hideQueue() {
        $('#queue-view').removeClass('active').css('display', 'none');
        $('.queue-item').removeClass('active');
        if (currentPatient) {
            $('#patient-header').addClass('active');
            $('#workspace-content').show().addClass('active');
        } else {
            $('#empty-state').show();
        }
        if (window.innerWidth < 768) {
            $('#main-workspace').removeClass('active');
            $('#left-panel').removeClass('hidden');
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // QUEUE DATA (maternity-specific endpoints, shared card pattern)
    // ═══════════════════════════════════════════════════════════════
    function loadQueueData(filter) {
        const $container = $('#queue-view-content');
        $container.html('<div class="text-center p-4"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading...</div>');

        const urls = {
            'active-anc': wbRoute('maternity-workbench.queue.active-anc', '/maternity-workbench/queue/active-anc'),
            'due-visits': wbRoute('maternity-workbench.queue.due-visits', '/maternity-workbench/queue/due-visits'),
            'upcoming-edd': wbRoute('maternity-workbench.queue.upcoming-edd', '/maternity-workbench/queue/upcoming-edd'),
            'postnatal': wbRoute('maternity-workbench.queue.postnatal', '/maternity-workbench/queue/postnatal'),
            'overdue-immunization': wbRoute('maternity-workbench.queue.overdue-immunization', '/maternity-workbench/queue/overdue-immunization'),
            'high-risk': wbRoute('maternity-workbench.queue.high-risk', '/maternity-workbench/queue/high-risk'),
            'bed-requests': wbRoute('maternity-workbench.queue.bed-requests', '/maternity-workbench/queue/bed-requests'),
            'discharge-requests': wbRoute('maternity-workbench.queue.discharge-requests', '/maternity-workbench/queue/discharge-requests'),
            'admitted-patients': wbRoute('maternity-workbench.queue.admitted-patients', '/maternity-workbench/queue/admitted-patients'),
        };

        $.ajax({
            url: urls[filter] || urls['active-anc'],
            method: 'GET',
            success: function(data) {
                const items = Array.isArray(data) ? data : (data.data || []);
                if (items.length === 0) {
                    $container.html('<div class="text-center p-4 text-muted"><i class="mdi mdi-account-off" style="font-size: 3rem;"></i><br>No patients in this queue</div>');
                    return;
                }
                renderQueueCards(items, filter);
            },
            error: function() {
                $container.html('<div class="text-center p-4 text-danger"><i class="mdi mdi-alert-circle" style="font-size: 3rem;"></i><br>Failed to load queue</div>');
            }
        });
    }

    function renderQueueCards(items, filter) {
        const $container = $('#queue-view-content');
        let html = '';
        items.forEach(function(item) {
            let badge = '';
            let detail = '';
            const pid = item.patient_id || item.baby_id;

            if (filter === 'active-anc') {
                badge = `<span class="risk-indicator ${item.risk_level}">${item.risk_level}</span>`;
                detail = `<span><i class="mdi mdi-calendar"></i> EDD: ${item.edd}</span>
                      <span><i class="mdi mdi-stethoscope"></i> GA: ${item.gestational_age || 'N/A'}</span>
                      <span><i class="mdi mdi-counter"></i> Visits: ${item.anc_visits || 0}</span>`;
            } else if (filter === 'due-visits') {
                badge = `<span class="badge bg-warning text-dark">${item.days_overdue}d overdue</span>`;
                detail = `<span><i class="mdi mdi-calendar-clock"></i> Due: ${item.next_appointment}</span>`;
            } else if (filter === 'upcoming-edd') {
                badge = `<span class="badge bg-info">${item.days_to_edd}d to EDD</span>`;
                detail = `<span><i class="mdi mdi-calendar-star"></i> EDD: ${item.edd}</span>
                      <span class="risk-indicator ${item.risk_level}">${item.risk_level}</span>`;
            } else if (filter === 'postnatal') {
                badge = `<span class="enrollment-badge ${item.status}">${item.status}</span>`;
                detail = `<span><i class="mdi mdi-calendar"></i> Delivered: ${item.delivery_date}</span>
                      <span><i class="mdi mdi-baby-face"></i> Babies: ${item.baby_count || 0}</span>
                      <span><i class="mdi mdi-clock"></i> ${item.days_postpartum || 0}d postpartum</span>`;
            } else if (filter === 'overdue-immunization') {
                detail = `<span><i class="mdi mdi-baby-face"></i> ${item.baby_name}</span>
                      <span><i class="mdi mdi-mother-nurse"></i> Mother: ${item.mother_name}</span>
                      <span><i class="mdi mdi-clock"></i> Age: ${item.age}</span>`;
            } else if (filter === 'high-risk') {
                badge = `<span class="enrollment-badge ${item.status}">${item.status}</span>`;
                const risks = item.risk_factors ? (Array.isArray(item.risk_factors) ? item.risk_factors.join(', ') : item.risk_factors) : 'N/A';
                detail = `<span><i class="mdi mdi-alert"></i> ${risks}</span>
                      <span><i class="mdi mdi-calendar"></i> EDD: ${item.edd}</span>`;
            } else if (filter === 'bed-requests') {
                const patientName = (item.name || item.baby_name || 'Unknown').replace(/'/g, "\\'");
                const fileNo = (item.file_no || '').replace(/'/g, "\\'");
                badge = `<span class="badge ${item.priority === 'emergency' ? 'bg-danger' : (item.priority === 'urgent' ? 'bg-warning text-dark' : 'bg-info')}">${item.priority || 'routine'}</span>`;
                detail = `<span><i class="mdi mdi-doctor"></i> Doctor: ${item.doctor_name || 'N/A'}</span>
                      <span><i class="mdi mdi-hospital-building"></i> Ward: ${item.ward_name}</span>
                      <span><i class="mdi mdi-clock"></i> Requested: ${item.requested_at}</span>
                      <div class="mt-2 text-end w-100">
                          <button class="btn btn-sm btn-info" onclick="event.stopPropagation(); WardDashboard.openBedAssignment(${item.id}, '${patientName}', '${fileNo}');">
                              <i class="mdi mdi-clipboard-check"></i> Process Admission
                          </button>
                      </div>`;
            } else if (filter === 'discharge-requests') {
                const patientName = (item.name || item.baby_name || 'Unknown').replace(/'/g, "\\'");
                const fileNo = (item.file_no || '').replace(/'/g, "\\'");
                const bedName = (item.ward_bed || 'No bed').replace(/'/g, "\\'");
                badge = `<span class="badge bg-warning text-dark">Discharge</span>`;
                detail = `<span><i class="mdi mdi-doctor"></i> Doctor: ${item.doctor_name || 'N/A'}</span>
                      <span><i class="mdi mdi-bed"></i> Bed: ${item.ward_bed}</span>
                      <span><i class="mdi mdi-clock"></i> Requested: ${item.requested_at}</span>
                      <div class="mt-2 text-end w-100">
                          <button class="btn btn-sm btn-warning" onclick="event.stopPropagation(); WardDashboard.openDischarge(${item.id}, '${patientName}', '${fileNo}', '${bedName}');">
                              <i class="mdi mdi-clipboard-check"></i> Process Discharge
                          </button>
                      </div>`;
            } else if (filter === 'admitted-patients') {
                badge = `<span class="badge bg-primary">${item.days_admitted}d Admitted</span>`;
                detail = `<span><i class="mdi mdi-doctor"></i> Doctor: ${item.doctor_name || 'N/A'}</span>
                      <span><i class="mdi mdi-hospital-building"></i> Ward: ${item.ward_name}</span>
                      <span><i class="mdi mdi-bed"></i> Bed: ${item.ward_bed}</span>
                      <span><i class="mdi mdi-calendar"></i> Admitted: ${item.admitted_date}</span>`;
            }

            html += `<div class="queue-card" onclick="loadPatient(${pid})">
            <div class="queue-card-header">
                <div>
                    <div class="queue-card-patient-name">${item.name || item.baby_name || 'Unknown'}</div>
                    <div class="queue-card-patient-meta d-flex flex-wrap gap-2">
                        <span class="queue-card-patient-meta-item"><i class="mdi mdi-folder"></i> ${item.file_no || 'N/A'}</span>
                        ${detail}
                    </div>
                </div>
                ${badge}
            </div>
        </div>`;
        });
        $container.html(html);
    }

    function loadQueueCounts() {
        $.ajax({
            url: wbRoute('maternity-workbench.queue.counts', '/maternity-workbench/queue/counts'),
            method: 'GET',
            success: function(data) {
                $('#queue-active-anc-count').text(data.active_anc || 0);
                $('#queue-due-visits-count').text(data.due_visits || 0);
                $('#queue-upcoming-edd-count').text(data.upcoming_edd || 0);
                $('#queue-postnatal-count').text(data.postnatal || 0);
                $('#queue-overdue-imm-count').text(data.overdue_immunization || 0);
                $('#queue-high-risk-count').text(data.high_risk || 0);
                $('#queue-bed-requests-count').text(data.bed_requests || 0);
                $('#queue-discharge-requests-count').text(data.discharge_requests || 0);
                $('#queue-admitted-patients-count').text(data.admitted_patients || 0);
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // PATIENT LOADING (SHARED pattern with nursing workbench)
    // ═══════════════════════════════════════════════════════════════
    // ═══════════════════════════════════════════════════════════════
    // CONTEXT HELPERS
    // ═══════════════════════════════════════════════════════════════
    function isBabyContext() {
        return window.currentPatientIsBaby === true;
    }

    function updateTabLabels() {
        const isBaby = isBabyContext();

        const labels = {
            enrollment: isBaby ? "Mother's Enrollment" : "Enrollment",
            history: "Mother's History",
            anc: isBaby ? "Mother's ANC Visits" : "ANC Visits",
            delivery: isBaby ? "Mother's Delivery" : "Delivery",
            baby: isBaby ? "Growth & Birth Details" : "Baby Records",
            postnatal: isBaby ? "Mother's Postnatal" : "Postnatal",
            vitals: isBaby ? "Baby Vitals / Growth" : "Vitals",
            notes: isBaby ? "Nursing/Clinical Notes" : "Notes"
        };

        Object.keys(labels).forEach(tab => {
            $(`.workspace-tab[data-tab="${tab}"] span`).text(labels[tab]);
        });
    }

    function loadPatient(patientId) {
        currentPatient = patientId;
        window.currentPatientId = patientId;

        if (window.loadUnviewedCounts) {
            window.loadUnviewedCounts(patientId);
        }

        // Initialize patient summary manager for LLM integration
        if (typeof PatientSummaryManager !== 'undefined') {
            window.patientSummary = new PatientSummaryManager({
                patientId: patientId,
                encounterId: null,
                autoOpen: false
            });
        }


        hideAllViews();

        $('#workspace-content').show().addClass('active');
        $('#patient-header').addClass('active');
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');

        // Enable quick actions
        $('#btn-enroll-patient').prop('disabled', false);
        $('#btn-quick-vitals').prop('disabled', false);
        $('#btn-print-anc-card').prop('disabled', false);
        $('#btn-print-road-card').prop('disabled', false);
        $('#btn-maternity-audit').prop('disabled', false);
        $('#btn-clinical-context').prop('disabled', false).attr('title', 'View clinical context for patient');

        $.ajax({
            url: wbUrl(`/maternity-workbench/patient/${patientId}/details`),
            method: 'GET',
            success: function(data) {
                currentPatientData = data;

                // Detect baby/mother context
                window.currentPatientIsBaby = data.is_baby || false;
                window.linkedMother = data.mother || null;
                window.linkedBabies = data.babies || [];

                updateTabLabels();

                // SHARED function: display patient header (same as nursing)
                displayPatientInfo(data);

                // Store enrollment
                currentEnrollment = data.enrollment;
                currentEnrollmentId = data.enrollment ? data.enrollment.id : null;

                // Show/hide print buttons based on enrollment
                if (currentEnrollmentId) {
                    $('#btn-print-anc-card').show();
                    $('#btn-print-road-card').show();
                    // Show discharge button for active enrollments
                    if (data.enrollment && !['completed', 'transferred', 'deceased'].includes(data.enrollment.status)) {
                        $('#btn-discharge-patient').show().prop('disabled', false);
                    } else {
                        $('#btn-discharge-patient').hide();
                    }
                    
                    // Show Admit or Ward Discharge button
                    const $admContainer = $('#admission-status-container');
                    const $admText = $('#admission-status-text');
                    
                    $admContainer.removeClass('d-flex').addClass('d-none');
                    $('#btn-admit-to-ward').hide();
                    $('#btn-discharge-from-ward').hide();

                    if (data.admission_request) {
                        $admContainer.removeClass('d-none').addClass('d-flex');
                        let statusHtml = '';
                        const req = data.admission_request;
                        
                        if (req.status === 'discharge_requested' || req.status === 'discharge_checklist') {
                            statusHtml = '<span class="fw-bold text-warning" style="font-size: 0.68rem;">Discharge Req</span><small class="text-muted" style="font-size: 0.58rem;">Awaiting Nursing</small>';
                        } else if (req.status === 'pending_checklist' || req.status === 'checklist_pending') {
                            statusHtml = '<span class="fw-bold text-info" style="font-size: 0.68rem;">Admission Req</span><small class="text-muted" style="font-size: 0.58rem;">Pending Checklist</small>';
                        } else if (req.status === 'requested') {
                            statusHtml = '<span class="fw-bold text-primary" style="font-size: 0.68rem;">Pending Admission</span><small class="text-muted" style="font-size: 0.58rem;">Requested</small>';
                        } else if (req.discharged) {
                            statusHtml = '<span class="fw-bold text-secondary" style="font-size: 0.68rem;">Discharged</span>';
                        } else {
                            statusHtml = '<span class="fw-bold text-dark" style="font-size: 0.68rem;">Admitted</span><small class="text-muted" style="font-size: 0.58rem;">' + (req.bed ? req.bed : 'Pending Bed') + '</small>';
                        }
                        
                        $admText.html(statusHtml);

                        if (!req.discharged && req.status !== 'discharge_requested' && req.status !== 'discharge_checklist' && req.status !== 'requested' && req.status !== 'pending_checklist' && req.status !== 'checklist_pending') {
                            $('#btn-discharge-from-ward').show().prop('disabled', false);
                        }
                    } else {
                        $('#btn-admit-to-ward').show().prop('disabled', false);
                    }
                } else {
                    $('#btn-print-anc-card').hide();
                    $('#btn-print-road-card').hide();
                    $('#btn-discharge-patient').hide();
                    $('#btn-admit-to-ward').hide();
                    $('#btn-discharge-from-ward').hide();
                    $('#admission-status-container').removeClass('d-flex').addClass('d-none');
                }

                // Load overview
                populateOverviewTab(data);

                // Initialize shared vitals partial
                if (typeof window.initUnifiedVitals === 'function') {
                    window.initUnifiedVitals(patientId, null, data.clinic_name, data.vitals_template, data.dynamic_ranges);
                }

                // Load enrollment tab content
                loadEnrollmentTab();

                if (window.BillingKit) {
                    BillingKit.setPatient(patientId);
                }

                switchWorkspaceTab('overview');
            },
            error: function(xhr) {
                console.error('Failed to load patient:', xhr);
                toastr.error('Failed to load patient data');
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // DISPLAY PATIENT INFO (SHARED with nursing workbench — same pattern)
    // ═══════════════════════════════════════════════════════════════
    function displayPatientInfo(patient) {
        // Build name with enrollment badge
        let nameSuffix = '';
        if (patient.enrollment) {
            nameSuffix = ` <span class="enrollment-badge ${patient.enrollment.status}">${patient.enrollment.status.toUpperCase()}</span>`;
            if (patient.enrollment.risk_level === 'high') {
                nameSuffix += ` <span class="risk-indicator high">HIGH RISK</span>`;
            }
        }
        $('#patient-name').html(`${patient.name} (#${patient.file_no})${nameSuffix}`);

        let metaHtml = `
        <div class="patient-meta-item"><i class="mdi mdi-account"></i><span>${patient.age} ${patient.gender}</span></div>
        <div class="patient-meta-item"><i class="mdi mdi-water"></i><span>${patient.blood_group} ${patient.genotype !== 'N/A' ? '(' + patient.genotype + ')' : ''}</span></div>
        <div class="patient-meta-item"><i class="mdi mdi-phone"></i><span>${patient.phone}</span></div>
    `;

        if (patient.enrollment) {
            const e = patient.enrollment;
            if (e.gestational_age) {
                metaHtml += `<div class="patient-meta-item"><span class="ga-pill"><i class="mdi mdi-baby-carriage"></i> GA: ${e.gestational_age}</span></div>`;
            }
            if (e.edd) {
                metaHtml += `<div class="patient-meta-item"><i class="mdi mdi-calendar-star"></i><span>EDD: ${e.edd}</span></div>`;
            }
            metaHtml += `<div class="patient-meta-item"><i class="mdi mdi-human-pregnant"></i><span>G${e.gravida || '?'}P${e.parity || '?'}</span></div>`;
        }
        $('#patient-meta').html(metaHtml);

        // Render Context Switch Buttons
        let contextHtml = '';
        if (window.currentPatientIsBaby && window.linkedMother) {
            contextHtml = `
            <a href="javascript:void(0)" class="context-switch-btn" onclick="loadPatient(${window.linkedMother.id})">
                <span class="linked-label"><i class="mdi mdi-arrow-left-circle"></i> Mother</span>
                <span class="linked-name">${window.linkedMother.name}</span>
                <span class="linked-file">#${window.linkedMother.file_no}</span>
            </a>`;
        } else if (!window.currentPatientIsBaby && window.linkedBabies && window.linkedBabies.length > 0) {
            window.linkedBabies.forEach(baby => {
                contextHtml += `
                <a href="javascript:void(0)" class="context-switch-btn" onclick="loadPatient(${baby.id})">
                    <span class="linked-label"><i class="mdi mdi-baby-face-outline"></i> Baby</span>
                    <span class="linked-name">${baby.name}</span>
                    <span class="linked-file">#${baby.file_no}</span>
                </a>`;
            });
        }
        $('#context-switch-container').html(contextHtml);

        // Build expanded details grid (SHARED pattern)
        let detailsHtml = '';
        const fields = [{
                icon: 'mdi-calendar-clock',
                label: 'Age',
                value: patient.age
            },
            {
                icon: 'mdi-gender-female',
                label: 'Gender',
                value: patient.gender
            },
            {
                icon: 'mdi-water',
                label: 'Blood Group',
                value: patient.blood_group
            },
            {
                icon: 'mdi-dna',
                label: 'Genotype',
                value: patient.genotype
            },
            {
                icon: 'mdi-phone',
                label: 'Phone',
                value: patient.phone
            },
            {
                icon: 'mdi-map-marker',
                label: 'Address',
                value: patient.address
            },
            {
                icon: 'mdi-hospital-building',
                label: 'HMO',
                value: patient.hmo
            },
            {
                icon: 'mdi-card-account-details',
                label: 'HMO No',
                value: patient.hmo_no
            },
        ];
        fields.forEach(function(f) {
            detailsHtml += `<div class="patient-detail-item"><div class="patient-detail-label"><i class="mdi ${f.icon}"></i> ${f.label}</div><div class="patient-detail-value">${f.value || 'N/A'}</div></div>`;
        });

        // Enrollment-specific details
        if (patient.enrollment) {
            const e = patient.enrollment;
            const enrollFields = [{
                    icon: 'mdi-clipboard-plus',
                    label: 'Booking Date',
                    value: e.booking_date
                },
                {
                    icon: 'mdi-calendar',
                    label: 'LMP',
                    value: e.lmp
                },
                {
                    icon: 'mdi-calendar-star',
                    label: 'EDD',
                    value: e.edd
                },
                {
                    icon: 'mdi-baby-carriage',
                    label: 'Gestational Age',
                    value: e.gestational_age
                },
                {
                    icon: 'mdi-human-pregnant',
                    label: 'Gravida/Parity',
                    value: `G${e.gravida || '?'} P${e.parity || '?'}`
                },
                {
                    icon: 'mdi-scale',
                    label: 'Booking Weight',
                    value: e.booking_weight_kg ? e.booking_weight_kg + ' kg' : 'N/A'
                },
                {
                    icon: 'mdi-arrow-up-down',
                    label: 'Height',
                    value: e.height_cm ? e.height_cm + ' cm' : 'N/A'
                },
                {
                    icon: 'mdi-gauge',
                    label: 'Booking BP',
                    value: e.booking_bp || 'N/A'
                },
                {
                    icon: 'mdi-clock-outline',
                    label: 'Remaining Days',
                    value: e.remaining_days !== null ? e.remaining_days + ' days' : 'N/A'
                },
            ];
            enrollFields.forEach(function(f) {
                detailsHtml += `<div class="patient-detail-item"><div class="patient-detail-label"><i class="mdi ${f.icon}"></i> ${f.label}</div><div class="patient-detail-value">${f.value || 'N/A'}</div></div>`;
            });
        }

        // Allergies (SHARED pattern from nursing workbench)
        let allergiesArray = [];
        if (patient.allergies) {
            if (Array.isArray(patient.allergies)) {
                allergiesArray = patient.allergies;
            } else if (typeof patient.allergies === 'string') {
                try {
                    const p = JSON.parse(patient.allergies);
                    allergiesArray = Array.isArray(p) ? p : [p];
                } catch (e) {
                    allergiesArray = patient.allergies.split(',').map(a => a.trim()).filter(a => a);
                }
            } else if (typeof patient.allergies === 'object') {
                allergiesArray = Object.values(patient.allergies).filter(a => a);
            }
        }
        if (allergiesArray.length> 0) {
            detailsHtml += `<div class="patient-detail-item full-width"><div class="patient-detail-label"><i class="mdi mdi-alert-circle"></i> Allergies</div><div class="patient-detail-value"><div class="allergies-list">${allergiesArray.map(a => `<span class="allergy-tag"><i class="mdi mdi-alert"></i> ${a}</span>`).join('')}</div></div></div>`;
        }

        // Risk factors
        if (patient.enrollment && patient.enrollment.risk_factors && patient.enrollment.risk_factors.length> 0) {
            const risks = patient.enrollment.risk_factors;
            const riskHtml = (Array.isArray(risks) ? risks : [risks]).map(r => `<span class="allergy-tag" style="background: rgba(220,53,69,0.15); border-color: rgba(220,53,69,0.4);"><i class="mdi mdi-alert"></i> ${r}</span>`).join('');
            detailsHtml += `<div class="patient-detail-item full-width"><div class="patient-detail-label"><i class="mdi mdi-alert-octagon"></i> Risk Factors</div><div class="patient-detail-value"><div class="allergies-list">${riskHtml}</div></div></div>`;
        }

        $('#patient-details-grid').html(detailsHtml);

        // SHARED: Toggle expand/collapse (identical to nursing)
        $('#btn-expand-patient').off('click').on('click', function() {
            $(this).toggleClass('expanded');
            $('#patient-details-expanded').toggleClass('show');
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // TAB SWITCHING (SHARED pattern with nursing workbench)
    // ═══════════════════════════════════════════════════════════════
    function switchWorkspaceTab(tab) {
        $('.workspace-tab').removeClass('active');
        $('.workspace-tab-content').removeClass('active');
        $(`.workspace-tab[data-tab="${tab}"]`).addClass('active');
        $(`#${tab}-tab`).addClass('active');

        if (!currentPatient) return;

        switch (tab) {
            case 'overview':
                populateOverviewTab(currentPatientData);
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
            case 'enrollment':
                loadEnrollmentTab();
                break;
            case 'history':
                loadHistoryTab();
                break;
            case 'anc':
                loadAncTab();
                break;
            case 'clinical-orders':
                loadClinicalOrdersTab();
                break;
            case 'delivery':
                loadDeliveryTab();
                break;
            case 'partograph':
                loadMatPartographTab();
                break;
            case 'baby':
                loadBabyTab();
                break;
            case 'postnatal':
                loadPostnatalTab();
                break;
            case 'immunization':
                loadImmunizationTab();
                break;
            case 'notes':
                loadNotesTab();
                break;
            case 'audit':
                loadAuditTab();
                break;
            case 'billing':
                if (window.BillingKit) {
                    BillingKit.setPatient(currentPatient);
                }
                break;
            case 'vitals':
                if (typeof window.initUnifiedVitals === 'function') {
                    const vitalsData = currentPatientData || {};
                    window.initUnifiedVitals(currentPatient, null, vitalsData.clinic_name, vitalsData.vitals_template);
                }
                break;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // OVERVIEW TAB
    // ═══════════════════════════════════════════════════════════════
    function populateOverviewTab(data) {
        const e = data.enrollment;
        const v = data.last_vitals;
        const isBaby = data.is_baby || false;

        let html = '';

        if (isBaby) {
            // ── Baby Overview Section ──────────────────────────────
            html += `
            <div class="row">
                <div class="col-md-12 mb-3">
                    <div class="card-modern" style="border-left: 4px solid var(--maternity-pink);">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3"><i class="mdi mdi-baby-face-outline"></i> Birth & Neonatal Details</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="small text-muted">Birth Weight</div>
                                    <div class="fw-bold" style="font-size:1.1rem;">${currentPatientData.birth_weight_kg || 'N/A'} kg</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small text-muted">APGAR (1/5/10 min)</div>
                                    <div class="fw-bold" style="font-size:1.1rem;">${currentPatientData.apgar_1_min || '-'}/${currentPatientData.apgar_5_min || '-'}/${currentPatientData.apgar_10_min || '-'}</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small text-muted">Delivery Date</div>
                                    <div class="fw-bold" style="font-size:1.1rem;">${e && e.delivery_date ? e.delivery_date : 'N/A'}</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small text-muted">Current Age</div>
                                    <div class="fw-bold" style="font-size:1.1rem;">${currentPatientData.age}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
        }

        // ── Stage-Aware Progress Section ──────────────────────────────
        if (e && e.status === 'completed') {
            // Discharged — show completed summary
            html += `
        <div class="card-modern mb-3" style="border-left: 4px solid #6c757d;">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold small"><i class="mdi mdi-check-circle text-secondary"></i> Maternity Episode Completed</span>
                    <span class="badge bg-secondary">DISCHARGED</span>
                </div>
                ${e.completed_at ? '<div class="small text-muted mt-1"><i class="mdi mdi-calendar-check"></i> Discharged: ' + e.completed_at + '</div>' : ''}
                ${e.outcome_summary ? '<div class="small mt-1"><i class="mdi mdi-text-box-outline"></i> ' + e.outcome_summary + '</div>' : ''}
            </div>
        </div>`;
        } else if (e && e.status === 'postnatal' && e.delivery_date) {
            // Postnatal — show postpartum days + postnatal progress
            const deliveryDate = new Date(e.delivery_date);
            const today = new Date();
            const postpartumDays = Math.floor((today - deliveryDate) / (1000 * 60 * 60 * 24));
            const postpartumWeeks = Math.floor(postpartumDays / 7);
            const postpartumRemDays = postpartumDays % 7;
            // Standard postnatal period is 6 weeks (42 days)
            const pnProgressPct = Math.min(100, Math.max(0, (postpartumDays / 42) * 100));
            const pnColor = postpartumDays> 42 ? '#6c757d' : '#2196f3';

            html += `
        <div class="card-modern mb-3" style="border-left: 4px solid #2196f3;">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold small"><i class="mdi mdi-account-heart text-primary"></i> Postnatal Progress</span>
                    <span class="small">${postpartumWeeks}w ${postpartumRemDays}d postpartum <span class="badge bg-primary">${e.postnatal_visit_count || 0} visits</span></span>
                </div>
                <div class="position-relative" style="height: 22px; background: #f0f0f0; border-radius: 11px; overflow: hidden;">
                    <div style="position:absolute; left:0; top:0; height:100%; width:${pnProgressPct}%; background: ${pnColor}; border-radius:11px; transition: width 0.5s;"></div>
                    <span style="position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); font-size:0.7rem; font-weight:600; color:#333; text-shadow: 0 0 3px #fff;">${postpartumDays}d / 42d (6 weeks)</span>
                </div>
                <div class="d-flex justify-content-between mt-1" style="font-size:0.65rem; color:#999;">
                    <span>Delivered: ${e.delivery_date}</span>
                    <span>6 weeks postpartum</span>
                </div>
            </div>
        </div>`;
        } else if (e && e.lmp && e.edd) {
            // Active (ANC) — pregnancy progress bar
            const gaText = e.gestational_age || 'N/A';
            const gaMatch = gaText.match(/(\d+)\s*weeks?/i);
            const gaWeeks = gaMatch ? parseInt(gaMatch[1]) : 0;
            const gaDayMatch = gaText.match(/(\d+)\s*days?/i);
            const gaDays = gaDayMatch ? parseInt(gaDayMatch[1]) : 0;
            const totalGaDays = gaWeeks * 7 + gaDays;
            const totalDays = 280; // 40 weeks
            const progressPct = Math.min(100, Math.max(0, (totalGaDays / totalDays) * 100));
            const trimester = gaWeeks < 13 ? 1 : (gaWeeks < 28 ? 2 : 3);
            const trimesterLabel = ['', '1st Trimester', '2nd Trimester', '3rd Trimester'][trimester];
            const progressColor = gaWeeks> 41 ? '#dc3545' : (gaWeeks>= 37 ? '#ffc107' : '#28a745');
            const postDates = e.remaining_days !== null && e.remaining_days < 0;

            html += `
        <div class="card-modern mb-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold small"><i class="mdi mdi-baby-carriage"></i> Pregnancy Progress — ${trimesterLabel}</span>
                    <span class="small">${gaText} ${postDates ? '<span class="badge bg-danger">POST-DATES</span>' : ''}</span>
                </div>
                <div class="position-relative" style="height: 22px; background: #f0f0f0; border-radius: 11px; overflow: hidden;">
                    <div style="position:absolute; left:0; top:0; height:100%; width:${progressPct}%; background: ${progressColor}; border-radius:11px; transition: width 0.5s;"></div>
                    <div style="position:absolute; left:${(12/40)*100}%; top:0; height:100%; width:1px; background: rgba(0,0,0,0.15);" title="End of 1st Trimester (12w)"></div>
                    <div style="position:absolute; left:${(28/40)*100}%; top:0; height:100%; width:1px; background: rgba(0,0,0,0.15);" title="End of 2nd Trimester (28w)"></div>
                    <span style="position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); font-size:0.7rem; font-weight:600; color:#333; text-shadow: 0 0 3px #fff;">${gaWeeks}w${gaDays}d / 40w</span>
                </div>
                <div class="d-flex justify-content-between mt-1" style="font-size:0.65rem; color:#999;">
                    <span>LMP: ${e.lmp}</span>
                    <span style="left:${(12/40)*100}%; position:relative;">12w</span>
                    <span style="left:${(28/40)*100}%; position:relative;">28w</span>
                    <span>EDD: ${e.edd}</span>
                </div>
            </div>
        </div>`;
        }

        // ── Stat Cards Row ─────────────────────────────────────────
        html += '<div class="row">';
        if (e) {
            // ANC Visits
            html += `
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="mat-stat-card mat-stat-pink" style="cursor:pointer;" onclick="switchWorkspaceTab('anc')">
                <div class="mat-stat-icon"><i class="mdi mdi-stethoscope" style="font-size:1.5rem;"></i></div>
                <div><div class="mat-stat-value">${e.anc_visit_count || 0}</div><div class="mat-stat-label">ANC Visits</div></div>
            </div>
        </div>`;
            // Babies
            html += `
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="mat-stat-card mat-stat-green" style="cursor:pointer;" onclick="switchWorkspaceTab('baby')">
                <div class="mat-stat-icon"><i class="mdi mdi-baby-face" style="font-size:1.5rem;"></i></div>
                <div><div class="mat-stat-value">${e.baby_count || 0}</div><div class="mat-stat-label">Babies</div></div>
            </div>
        </div>`;
            // Postnatal
            html += `
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="mat-stat-card mat-stat-blue" style="cursor:pointer;" onclick="switchWorkspaceTab('postnatal')">
                <div class="mat-stat-icon"><i class="mdi mdi-account-heart" style="font-size:1.5rem;"></i></div>
                <div><div class="mat-stat-value">${e.postnatal_visit_count || 0}</div><div class="mat-stat-label">Postnatal</div></div>
            </div>
        </div>`;
            // Days to EDD
            const eddBg = (e.remaining_days !== null && e.remaining_days < 0) ? 'mat-stat-red' : 'mat-stat-orange';
            html += `
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="mat-stat-card ${eddBg}">
                <div class="mat-stat-icon"><i class="mdi mdi-clock-outline" style="font-size:1.5rem;"></i></div>
                <div><div class="mat-stat-value">${e.remaining_days !== null ? (e.remaining_days < 0 ? Math.abs(e.remaining_days) + 'd over' : e.remaining_days + 'd') : 'N/A'}</div><div class="mat-stat-label">To EDD</div></div>
            </div>
        </div>`;
            // Risk Level
            const riskColors = {
                low: '#28a745',
                moderate: '#ffc107',
                high: '#fd7e14',
                very_high: '#dc3545'
            };
            const riskBg = riskColors[e.risk_level] || '#6c757d';
            html += `
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="mat-stat-card" style="border-left: 4px solid ${riskBg}; cursor:pointer;" onclick="switchWorkspaceTab('enrollment')">
                <div class="mat-stat-icon"><i class="mdi mdi-shield-alert" style="font-size:1.5rem; color:${riskBg};"></i></div>
                <div><div class="mat-stat-value" style="color:${riskBg}; text-transform:capitalize;">${(e.risk_level || 'low').replace('_', ' ')}</div><div class="mat-stat-label">Risk Level</div></div>
            </div>
        </div>`;
            // BMI
            const bmi = (e.booking_weight_kg && e.height_cm) ? (e.booking_weight_kg / ((e.height_cm / 100) ** 2)).toFixed(1) : null;
            const bmiColor = bmi ? (bmi < 18.5 ? '#17a2b8' : (bmi < 25 ? '#28a745' : (bmi < 30 ? '#ffc107' : '#dc3545'))) : '#6c757d';
            const bmiLabel = bmi ? (bmi < 18.5 ? 'Underweight' : (bmi < 25 ? 'Normal' : (bmi < 30 ? 'Overweight' : 'Obese'))) : '';
            html += `
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="mat-stat-card" style="border-left: 4px solid ${bmiColor};">
                <div class="mat-stat-icon"><i class="mdi mdi-weight" style="font-size:1.5rem; color:${bmiColor};"></i></div>
                <div><div class="mat-stat-value" style="color:${bmiColor};">${bmi || 'N/A'}</div><div class="mat-stat-label">BMI ${bmiLabel ? '(' + bmiLabel + ')' : ''}</div></div>
            </div>
        </div>`;
        }
        html += '</div>';

        // ── Alerts Panel ────────────────────────────────────────────
        if (e) {
            const alerts = [];
            // Post-dates alert
            if (e.remaining_days !== null && e.remaining_days < 0) {
                alerts.push({
                    type: 'danger',
                    icon: 'mdi-alert-circle',
                    text: `Post-dates by ${Math.abs(e.remaining_days)} days — consider induction assessment`,
                    tab: 'delivery'
                });
            }
            // High risk alert
            if (e.risk_level === 'high' || e.risk_level === 'very_high') {
                const riskDesc = e.risk_factors ? ': ' + e.risk_factors : '';
                alerts.push({
                    type: 'warning',
                    icon: 'mdi-shield-alert',
                    text: `High-risk pregnancy${riskDesc}`,
                    tab: 'enrollment'
                });
            }
            // Near term
            if (e.remaining_days !== null && e.remaining_days>= 0 && e.remaining_days <= 14) {
                alerts.push({
                    type: 'info',
                    icon: 'mdi-calendar-clock',
                    text: `Near term — EDD in ${e.remaining_days} days (${e.edd})`,
                    tab: null
                });
            }
            // Low ANC attendance
            const gaMatch2 = (e.gestational_age || '').match(/(\d+)\s*weeks?/i);
            const gaW = gaMatch2 ? parseInt(gaMatch2[1]) : 0;
            const expectedVisits = gaW < 16 ? 1 : (gaW < 28 ? 2 : (gaW < 36 ? 3 : 4));
            if (gaW>= 16 && (e.anc_visit_count || 0) < expectedVisits) {
                alerts.push({
                    type: 'warning',
                    icon: 'mdi-stethoscope',
                    text: `ANC visits below schedule: ${e.anc_visit_count}/${expectedVisits} expected by ${gaW} weeks`,
                    tab: 'anc'
                });
            }
            // Abnormal BP from last vitals
            if (v && v.bp && v.bp !== 'N/A') {
                const bpParts = v.bp.split('/');
                if (bpParts.length === 2) {
                    const sys = parseInt(bpParts[0]);
                    const dia = parseInt(bpParts[1]);
                    if (sys>= 140 || dia>= 90) {
                        alerts.push({
                            type: 'danger',
                            icon: 'mdi-heart-pulse',
                            text: `Elevated BP: ${v.bp} mmHg — screen for pre-eclampsia`,
                            tab: 'vitals'
                        });
                    }
                }
            }
            // Obese BMI
            const bmiVal = (e.booking_weight_kg && e.height_cm) ? (e.booking_weight_kg / ((e.height_cm / 100) ** 2)) : null;
            if (bmiVal && bmiVal>= 30) {
                alerts.push({
                    type: 'warning',
                    icon: 'mdi-weight',
                    text: `Booking BMI ${bmiVal.toFixed(1)} — increased risk for GDM, pre-eclampsia`,
                    tab: 'enrollment'
                });
            }

            if (alerts.length> 0) {
                html += '<div class="mb-3">';
                html += '<h6 class="small fw-bold text-muted mb-2"><i class="mdi mdi-bell-alert"></i> Clinical Alerts</h6>';
                alerts.forEach(a => {
                    const clickAttr = a.tab ? `style="cursor:pointer;" onclick="switchWorkspaceTab('${a.tab}')"` : '';
                    html += `<div class="alert alert-${a.type} py-1 px-2 mb-1 d-flex align-items-center small" ${clickAttr}>
                    <i class="mdi ${a.icon} me-2" style="font-size:1.1rem;"></i> ${a.text}
                    ${a.tab ? '<i class="mdi mdi-chevron-right ms-auto"></i>' : ''}
                </div>`;
                });
                html += '</div>';
            }
        }

        // ── Cards Row ───────────────────────────────────────────────
        html += '<div class="row">';

        // Enrollment Summary
        html += '<div class="col-lg-4 col-md-6 mb-3"><div class="card-modern h-100"><div class="card-header text-white py-2" style="background: var(--maternity-pink);"><h6 class="mb-0"><i class="mdi mdi-clipboard-plus"></i> Enrollment</h6></div><div class="card-body p-2">';
        if (e) {
            html += `<table class="table table-sm table-borderless mb-0">
            <tr><td class="text-muted" style="width:40%;">Status</td><td><span class="enrollment-badge ${e.status}">${e.status}</span></td></tr>
            <tr><td class="text-muted">Entry Point</td><td>${(e.entry_point || '').toUpperCase()}</td></tr>
            <tr><td class="text-muted">Booking</td><td>${e.booking_date || 'N/A'}</td></tr>
            <tr><td class="text-muted">LMP</td><td>${e.lmp || 'N/A'}</td></tr>
            <tr><td class="text-muted">EDD</td><td>${e.edd || 'N/A'}</td></tr>
            <tr><td class="text-muted">GA</td><td>${e.gestational_age || 'N/A'}</td></tr>
            <tr><td class="text-muted">G/P/A</td><td>G${e.gravida || '?'} P${e.parity || '?'}</td></tr>
            <tr><td class="text-muted">Blood Grp</td><td>${e.blood_group || 'N/A'} &nbsp; <span class="text-muted">Geno:</span> ${e.genotype || 'N/A'}</td></tr>
            <tr><td class="text-muted">Height</td><td>${e.height_cm ? e.height_cm + ' cm' : 'N/A'} &nbsp; <span class="text-muted">Wt:</span> ${e.booking_weight_kg ? e.booking_weight_kg + ' kg' : 'N/A'}</td></tr>
        </table>`;
        } else {
            html += '<p class="text-muted text-center py-3 mb-0">Not enrolled — <a href="javascript:void(0)" onclick="switchWorkspaceTab(\'enrollment\')">Enroll now</a></p>';
        }
        html += '</div></div></div>';

        // Latest Vitals
        html += '<div class="col-lg-4 col-md-6 mb-3"><div class="card-modern h-100"><div class="card-header bg-success text-white py-2"><h6 class="mb-0"><i class="mdi mdi-heart-pulse"></i> Latest Vitals</h6></div><div class="card-body p-2">';
        if (v) {
            // Highlight abnormal BP
            let bpClass = '';
            if (v.bp && v.bp !== 'N/A') {
                const bpSplit = v.bp.split('/');
                if (bpSplit.length === 2 && (parseInt(bpSplit[0])>= 140 || parseInt(bpSplit[1])>= 90)) bpClass = 'text-danger fw-bold';
            }
            html += `<table class="table table-sm table-borderless mb-0">
            <tr><td class="text-muted" style="width:40%;"><i class="mdi mdi-heart-pulse text-danger"></i> BP</td><td class="${bpClass}">${v.bp || 'N/A'} mmHg</td></tr>
            <tr><td class="text-muted"><i class="mdi mdi-thermometer text-warning"></i> Temp</td><td>${v.temp || 'N/A'} °C</td></tr>
            <tr><td class="text-muted"><i class="mdi mdi-heart text-danger"></i> Heart Rate</td><td>${v.heart_rate || 'N/A'} bpm</td></tr>
            <tr><td class="text-muted"><i class="mdi mdi-lungs text-info"></i> Resp Rate</td><td>${v.resp_rate || 'N/A'}/min</td></tr>
            <tr><td class="text-muted"><i class="mdi mdi-weight text-primary"></i> Weight</td><td>${v.weight || 'N/A'} kg</td></tr>
            <tr><td class="text-muted"><i class="mdi mdi-water-percent text-info"></i> SpO2</td><td>${v.spo2 || 'N/A'} %</td></tr>
            <tr><td class="text-muted"><i class="mdi mdi-clock text-secondary"></i> Recorded</td><td class="small">${v.time || 'N/A'}</td></tr>
        </table>`;
        } else {
            html += '<p class="text-muted text-center py-3 mb-0">No vitals recorded — <a href="javascript:void(0)" onclick="switchWorkspaceTab(\'vitals\')">Record now</a></p>';
        }
        html += '</div></div></div>';

        // Timeline
        html += '<div class="col-lg-4 col-md-12 mb-3"><div class="card-modern h-100"><div class="card-header bg-info text-white py-2"><h6 class="mb-0"><i class="mdi mdi-timeline"></i> Timeline</h6></div><div class="card-body p-2" style="max-height:280px; overflow-y:auto;" id="overview-timeline">';
        html += '<p class="text-muted text-center py-3 mb-0"><i class="mdi mdi-loading mdi-spin"></i> Loading timeline...</p>';
        html += '</div></div></div>';

        html += '</div>';
        $('#overview-content').html(html);

        // Load timeline with icons & color coding
        if (currentEnrollmentId) {
            $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}/timeline`, function(resp) {
                if (resp.success && resp.timeline.length> 0) {
                    const typeIcons = {
                        enrollment: 'mdi-clipboard-plus',
                        anc: 'mdi-stethoscope',
                        delivery: 'mdi-baby-carriage',
                        baby: 'mdi-baby-face',
                        postnatal: 'mdi-account-heart',
                        immunization: 'mdi-needle',
                        vitals: 'mdi-heart-pulse',
                        lab: 'mdi-test-tube',
                        note: 'mdi-note-text',
                        default: 'mdi-circle-small'
                    };
                    const typeColors = {
                        enrollment: 'var(--maternity-pink)',
                        anc: '#e91e63',
                        delivery: '#4caf50',
                        baby: '#8bc34a',
                        postnatal: '#2196f3',
                        immunization: '#ff9800',
                        vitals: '#f44336',
                        lab: '#9c27b0',
                        note: '#607d8b',
                        default: '#999'
                    };
                    const tabMap = {
                        anc: 'anc',
                        delivery: 'delivery',
                        baby: 'baby',
                        postnatal: 'postnatal',
                        immunization: 'immunization',
                        vitals: 'vitals',
                        lab: 'clinical-orders',
                        note: 'notes'
                    };

                    let tHtml = '<div class="timeline-container">';
                    resp.timeline.forEach(function(item) {
                        const icon = typeIcons[item.type] || typeIcons.default;
                        const color = typeColors[item.type] || typeColors.default;
                        const clickTab = tabMap[item.type];
                        const clickAttr = clickTab ? `style="cursor:pointer;" onclick="switchWorkspaceTab('${clickTab}')"` : '';
                        tHtml += `<div class="timeline-item ${item.type}" ${clickAttr}>
                        <div class="timeline-icon" style="color:${color};"><i class="mdi ${icon}"></i></div>
                        <div class="timeline-date">${item.date || ''}</div>
                        <div class="timeline-title">${item.title}</div>
                        <div class="timeline-detail">${item.detail || ''}</div>
                    </div>`;
                    });
                    tHtml += '</div>';
                    $('#overview-timeline').html(tHtml);
                } else {
                    $('#overview-timeline').html('<p class="text-muted text-center py-2 mb-0">No timeline events</p>');
                }
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // ENROLLMENT TAB
    // ═══════════════════════════════════════════════════════════════
    function loadEnrollmentTab() {
        if (window.currentPatientIsBaby) {
            if (currentEnrollment) {
                renderEnrollmentDetails(true); // true = show notice
            } else {
                $('#enrollment-content').html(`
                    <div class="alert alert-info d-flex align-items-center gap-3">
                        <i class="mdi mdi-information" style="font-size:2rem;"></i>
                        <div>
                            <strong>Baby Patient:</strong> Enrollment is managed via the mother's record.
                            ${window.linkedMother ? `<br><a href="javascript:void(0)" onclick="loadPatient(${window.linkedMother.id})" class="btn btn-sm btn-info mt-2">Go to Mother: ${window.linkedMother.name}</a>` : ''}
                        </div>
                    </div>
                `);
            }
            return;
        }

        if (currentEnrollment) {
            renderEnrollmentDetails();
        } else {
            renderEnrollmentForm();
        }
    }

    function renderEnrollmentForm() {
        const html = `
    <div class="card-modern">
        <div class="card-header text-white" style="background: var(--maternity-pink);">
            <h6 class="mb-0"><i class="mdi mdi-clipboard-plus"></i> New Maternity Enrollment</h6>
        </div>
        <div class="card-body">
            <form id="enrollment-form">
                <input type="hidden" name="patient_id" value="${currentPatient}">
                <div class="form-section">
                    <div class="form-section-title"><i class="mdi mdi-door-open"></i> Entry Point</div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Entry Point <span class="text-danger">*</span></label>
                            <select name="entry_point" class="form-select" required>
                                <option value="anc" selected>ANC (Antenatal Care)</option>
                                <option value="delivery">Delivery (Labour Ward)</option>
                                <option value="postnatal">Postnatal</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-section-title"><i class="mdi mdi-calendar"></i> Dates & Obstetric Formula</div>
                    <div class="mat-form-help mb-2"><i class="mdi mdi-information"></i> Obstetric formula: G = total pregnancies including current, P = deliveries ≥20 weeks, A = living children, Ab = abortions/miscarriages</div>
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">LMP <span class="text-danger">*</span> <span class="mat-tooltip-icon" title="Last Menstrual Period — first day of last normal menstrual cycle. Used to calculate gestational age and EDD."><i class="mdi mdi-help-circle"></i></span></label><input type="date" name="lmp" class="form-control" id="enroll-lmp" required></div>
                        <div class="col-md-3 mb-3"><label class="form-label">EDD <span class="mat-tooltip-icon" title="Estimated Date of Delivery — auto-calculated as LMP + 280 days (Naegele's rule)"><i class="mdi mdi-help-circle"></i></span> <small class="text-muted">(auto-calculated)</small></label><input type="date" name="edd" class="form-control" id="enroll-edd"></div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">GA at Booking</label>
                            <div class="form-control bg-light" id="enroll-ga-display" style="font-weight:600; color:#555;">— enter LMP —</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Days to EDD</label>
                            <div class="form-control bg-light" id="enroll-edd-countdown" style="font-weight:600; color:#555;">—</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-2 mb-3"><label class="form-label">Gravida <span class="text-danger">*</span> <span class="mat-tooltip-icon" title="Total number of pregnancies including current one (G1 = first pregnancy)"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="gravida" class="form-control" min="1" placeholder="e.g. 2" required></div>
                        <div class="col-md-2 mb-3"><label class="form-label">Parity <span class="mat-tooltip-icon" title="Number of pregnancies carried to ≥20 weeks (regardless of outcome)"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="parity" class="form-control" min="0" value="0" placeholder="e.g. 1"></div>
                        <div class="col-md-2 mb-3"><label class="form-label">Alive <span class="mat-tooltip-icon" title="Number of living children from previous pregnancies"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="alive" class="form-control" min="0" value="0" placeholder="e.g. 1"></div>
                        <div class="col-md-2 mb-3"><label class="form-label">Abortion / Miscarriage <span class="mat-tooltip-icon" title="Number of pregnancy losses before 20 weeks gestation"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="abortion_miscarriage" class="form-control" min="0" value="0" placeholder="e.g. 0"></div>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-section-title"><i class="mdi mdi-human-pregnant"></i> Booking Measurements</div>
                    <div class="mat-form-help mb-2"><i class="mdi mdi-information"></i> Initial baseline measurements recorded at first antenatal visit (booking visit)</div>
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">Blood Group <span class="mat-tooltip-icon" title="ABO and Rhesus type — Rh-negative mothers need anti-D prophylaxis"><i class="mdi mdi-help-circle"></i></span></label><select name="blood_group" class="form-select"><option value="">-- Select --</option><option>A+</option><option>A-</option><option>B+</option><option>B-</option><option>AB+</option><option>AB-</option><option>O+</option><option>O-</option></select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Genotype <span class="mat-tooltip-icon" title="Haemoglobin genotype — SS/SC = Sickle cell disease, AS = carrier trait"><i class="mdi mdi-help-circle"></i></span></label><select name="genotype" class="form-select"><option value="">-- Select --</option><option>AA</option><option>AS</option><option>SS</option><option>AC</option><option>SC</option><option>CC</option></select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Weight (kg)</label><input type="number" name="booking_weight_kg" id="enroll-weight" class="form-control" step="0.1" placeholder="e.g. 65.0"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Height (cm)</label><input type="number" name="height_cm" id="enroll-height" class="form-control" step="0.1" placeholder="e.g. 160.0"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">Booking BP <span class="mat-tooltip-icon" title="First blood pressure reading in pregnancy. Format: systolic/diastolic (e.g. 120/80)"><i class="mdi mdi-help-circle"></i></span></label><input type="text" name="booking_bp" class="form-control" placeholder="e.g. 120/80"></div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">BMI <small class="text-muted">(auto-calculated)</small></label>
                            <div class="form-control bg-light" id="enroll-bmi-display" style="font-weight:600;">—</div>
                        </div>
                        <div class="col-md-3 mb-3"><label class="form-label">Risk Level <span class="mat-tooltip-icon" title="Low: uncomplicated pregnancy. Moderate: age>35, prior C-section, mild anaemia. High: pre-eclampsia, multiple gestation. Very High: eclampsia, placenta praevia"><i class="mdi mdi-help-circle"></i></span></label><select name="risk_level" class="form-select"><option value="low">Low</option><option value="moderate">Moderate</option><option value="high">High</option><option value="very_high">Very High</option></select></div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Risk Factors <span class="mat-tooltip-icon" title="List specific risk factors: e.g. previous stillbirth, pre-eclampsia history, sickle cell disease, multiple gestation, age>40"><i class="mdi mdi-help-circle"></i></span></label>
                            <textarea name="risk_factors" class="form-control" rows="2" placeholder="List risk factors separated by commas (e.g. previous C-section, anaemia, age>35)"></textarea>
                        </div>
                    </div>
                </div>
                <div class="p-3 text-end">
                    <button type="submit" class="btn btn-lg text-white" style="background: var(--maternity-pink);"><i class="mdi mdi-check"></i> Enroll Patient</button>
                </div>
            </form>
        </div>
    </div>`;
        $('#enrollment-content').html(html);

        // Auto-calculate EDD, GA display, countdown from LMP
        function updateLmpCalculations() {
            const lmpVal = $('#enroll-lmp').val();
            if (!lmpVal) {
                $('#enroll-edd').val('');
                $('#enroll-ga-display').html('— enter LMP —');
                $('#enroll-edd-countdown').html('—');
                return;
            }
            const lmp = new Date(lmpVal);
            if (isNaN(lmp)) return;

            // EDD = LMP + 280 days (Naegele's rule)
            const edd = new Date(lmp);
            edd.setDate(edd.getDate() + 280);
            $('#enroll-edd').val(edd.toISOString().split('T')[0]);

            // GA at booking (from LMP to today)
            const today = new Date();
            const diffDays = Math.floor((today - lmp) / (1000 * 60 * 60 * 24));
            if (diffDays>= 0) {
                const weeks = Math.floor(diffDays / 7);
                const days = diffDays % 7;
                const trimester = weeks < 13 ? '1st' : (weeks < 28 ? '2nd' : '3rd');
                $('#enroll-ga-display').html(`<span style="color:#333;">${weeks}w ${days}d</span> <span class="badge bg-secondary" style="font-size:0.65rem;">${trimester} trimester</span>`);
            } else {
                $('#enroll-ga-display').html('<span class="text-warning">Future date?</span>');
            }

            // Days to EDD countdown
            const daysToEdd = Math.floor((edd - today) / (1000 * 60 * 60 * 24));
            if (daysToEdd> 0) {
                const countdownColor = daysToEdd <= 14 ? '#ffc107' : (daysToEdd <= 42 ? '#17a2b8' : '#28a745');
                $('#enroll-edd-countdown').html(`<span style="color:${countdownColor};">${daysToEdd} days</span>`);
            } else if (daysToEdd === 0) {
                $('#enroll-edd-countdown').html('<span class="text-danger fw-bold">DUE TODAY</span>');
            } else {
                $('#enroll-edd-countdown').html(`<span class="text-danger fw-bold">${Math.abs(daysToEdd)} days overdue</span>`);
            }
        }
        $('#enroll-lmp').on('change', updateLmpCalculations);

        // Auto-calculate BMI from weight and height
        function updateBmiCalc() {
            const wt = parseFloat($('#enroll-weight').val());
            const ht = parseFloat($('#enroll-height').val());
            if (wt> 0 && ht> 0) {
                const bmi = (wt / ((ht / 100) ** 2)).toFixed(1);
                const category = bmi < 18.5 ? 'Underweight' : (bmi < 25 ? 'Normal' : (bmi < 30 ? 'Overweight' : 'Obese'));
                const color = bmi < 18.5 ? '#17a2b8' : (bmi < 25 ? '#28a745' : (bmi < 30 ? '#ffc107' : '#dc3545'));
                $('#enroll-bmi-display').html(`<span style="color:${color};">${bmi}</span> <span class="badge" style="background:${color}; font-size:0.65rem;">${category}</span>`);
            } else {
                $('#enroll-bmi-display').html('—');
            }
        }
        $('#enroll-weight, #enroll-height').on('input', updateBmiCalc);

        // Handle enrollment form submit
        $('#enrollment-form').on('submit', function(e) {
            e.preventDefault();
            const formData = {};
            $(this).serializeArray().forEach(f => formData[f.name] = f.value);

            $.ajax({
                url: wbRoute('maternity-workbench.enroll', '/maternity-workbench/enroll'),
                method: 'POST',
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                success: function(resp) {
                    if (resp.success) {
                        toastr.success(resp.message);
                        currentEnrollment = resp.enrollment;
                        currentEnrollmentId = resp.enrollment_id;
                        loadPatient(currentPatient); // Reload
                    } else {
                        toastr.error(resp.message || 'Enrollment failed');
                    }
                },
                error: function(xhr) {
                    const errors = xhr.responseJSON?.errors;
                    if (errors) {
                        Object.values(errors).flat().forEach(e => toastr.error(e));
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Enrollment failed');
                    }
                }
            });
        });
    }

    function renderEnrollmentDetails(isBabyNotice = false) {
        const e = currentEnrollment;

        let babyNoticeHtml = '';
        if (isBabyNotice) {
            babyNoticeHtml = `
            <div class="alert alert-info d-flex align-items-center gap-3 mb-3">
                <i class="mdi mdi-information" style="font-size:1.5rem;"></i>
                <div>
                    <strong>Mother's Enrollment:</strong> You are viewing the maternity enrollment record of this baby's mother.
                </div>
            </div>`;
        }

        // Status transition bar — 3-step: Active → Postnatal → Discharged
        const statuses = ['active', 'postnatal', 'completed'];
        const statusLabels = ['Active (ANC)', 'Postnatal', 'Discharged'];
        const statusColors = ['#e91e63', '#2196f3', '#6c757d'];
        const statusMap = {
            active: 0,
            postnatal: 1,
            completed: 2,
            transferred: 2,
            deceased: 2
        };
        const currentIdx = statusMap[e.status] !== undefined ? statusMap[e.status] : -1;

        let statusBarHtml = '<div class="d-flex align-items-center mb-3" style="gap:0;">';
        statuses.forEach((s, i) => {
            const isActive = i <= currentIdx;
            const isCurrent = i === currentIdx;
            const bg = isActive ? statusColors[i] : '#e0e0e0';
            const textColor = isActive ? '#fff' : '#999';
            // For terminal statuses that aren't 'completed', show actual label
            let label = statusLabels[i];
            if (i === 2 && isCurrent && e.status === 'transferred') label = 'Transferred';
            if (i === 2 && isCurrent && e.status === 'deceased') label = 'Deceased';
            statusBarHtml += `<div class="text-center px-2 py-1 flex-fill" style="background:${bg}; color:${textColor}; font-size:0.72rem; font-weight:${isCurrent ? '700' : '400'}; ${i === 0 ? 'border-radius:6px 0 0 6px;' : ''} ${i === 2 ? 'border-radius:0 6px 6px 0;' : ''}">
            ${isCurrent ? '<i class="mdi mdi-chevron-right"></i> ' : ''}${label}
        </div>`;
        });
        statusBarHtml += '</div>';

        // BMI display
        const bmi = (e.booking_weight_kg && e.height_cm) ? (e.booking_weight_kg / ((e.height_cm / 100) ** 2)).toFixed(1) : null;
        const bmiCategory = bmi ? (bmi < 18.5 ? 'Underweight' : (bmi < 25 ? 'Normal' : (bmi < 30 ? 'Overweight' : 'Obese'))) : '';
        const bmiColor = bmi ? (bmi < 18.5 ? '#17a2b8' : (bmi < 25 ? '#28a745' : (bmi < 30 ? '#ffc107' : '#dc3545'))) : '#999';

        const html = `
    <div class="card-modern">
        <div class="card-header text-white d-flex justify-content-between align-items-center" style="background: var(--maternity-pink);">
            <h6 class="mb-0"><i class="mdi mdi-clipboard-check"></i> Enrollment Details</h6>
            <div class="d-flex align-items-center" style="gap:6px;">
                ${!isBabyNotice ? `
                <button class="btn btn-sm btn-outline-light" onclick="editEnrollment()" title="Edit enrollment"><i class="mdi mdi-pencil"></i> Edit</button>
                ${e.status !== 'completed' && e.status !== 'transferred' && e.status !== 'deceased' ? '<button class="btn btn-sm btn-outline-light" onclick="showDischargeModal()" title="Discharge patient"><i class="mdi mdi-exit-run"></i> Discharge</button>' : ''}
                ` : ''}
                <span class="enrollment-badge ${e.status}" style="background: rgba(255,255,255,0.2); color: white;">${e.status.toUpperCase()}</span>
            </div>
        </div>
        <div class="card-body">
            ${babyNoticeHtml}
            ${statusBarHtml}
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr><td class="text-muted" style="width:40%;">Entry Point</td><td class="fw-bold">${(e.entry_point || '').toUpperCase()}</td></tr>
                        <tr><td class="text-muted">Booking Date</td><td>${e.booking_date || 'N/A'}</td></tr>
                        <tr><td class="text-muted">LMP</td><td>${e.lmp || 'N/A'}</td></tr>
                        <tr><td class="text-muted">EDD</td><td>${e.edd || 'N/A'} ${e.remaining_days !== null ? (e.remaining_days < 0 ? '<span class="badge bg-danger ms-1">' + Math.abs(e.remaining_days) + 'd overdue</span>' : '<span class="badge bg-secondary ms-1">' + e.remaining_days + 'd remaining</span>') : ''}</td></tr>
                        <tr><td class="text-muted">Gestational Age</td><td><span class="ga-pill">${e.gestational_age || 'N/A'}</span></td></tr>
                        <tr><td class="text-muted">Obstetric Formula</td><td class="fw-bold">G${e.gravida || '?'} P${e.parity || '?'}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr><td class="text-muted" style="width:40%;">Blood Group</td><td>${e.blood_group || 'N/A'}</td></tr>
                        <tr><td class="text-muted">Genotype</td><td>${e.genotype || 'N/A'}</td></tr>
                        <tr><td class="text-muted">Weight</td><td>${e.booking_weight_kg ? e.booking_weight_kg + ' kg' : 'N/A'}</td></tr>
                        <tr><td class="text-muted">Height</td><td>${e.height_cm ? e.height_cm + ' cm' : 'N/A'}</td></tr>
                        <tr><td class="text-muted">BMI</td><td>${bmi ? '<span style="color:' + bmiColor + '; font-weight:600;">' + bmi + '</span> <span class="badge" style="background:' + bmiColor + '; font-size:0.65rem;">' + bmiCategory + '</span>' : 'N/A'}</td></tr>
                        <tr><td class="text-muted">BP</td><td>${e.booking_bp || 'N/A'}</td></tr>
                        <tr><td class="text-muted">Risk Level</td><td><span class="risk-indicator ${e.risk_level}">${(e.risk_level || 'low').replace('_', ' ')}</span></td></tr>
                        ${e.risk_factors ? '<tr><td class="text-muted">Risk Factors</td><td class="small">' + e.risk_factors + '</td></tr>' : ''}
                    </table>
                </div>
            </div>
        </div>
    </div>`;
        $('#enrollment-content').html(html);
    }

    function editEnrollment() {
        const e = currentEnrollment;
        if (!e) return;

        // Helper to format date for input[type=date]
        function toDateInput(val) {
            if (!val) return '';
            const d = new Date(val);
            if (isNaN(d)) return '';
            return d.toISOString().split('T')[0];
        }

        function optionSelected(val, option) {
            return val === option ? 'selected' : '';
        }

        const html = `
    <div class="card-modern">
        <div class="card-header text-white d-flex justify-content-between align-items-center" style="background: var(--maternity-pink);">
            <h6 class="mb-0"><i class="mdi mdi-pencil"></i> Edit Enrollment</h6>
            <button class="btn btn-sm btn-outline-light" onclick="renderEnrollmentDetails()"><i class="mdi mdi-close"></i> Cancel</button>
        </div>
        <div class="card-body">
            <form id="edit-enrollment-form">
                <div class="form-section">
                    <div class="form-section-title"><i class="mdi mdi-calendar"></i> Dates & Obstetric Formula</div>
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">LMP <span class="text-danger">*</span></label><input type="date" name="lmp" class="form-control" id="edit-enroll-lmp" value="${toDateInput(e.lmp)}" required></div>
                        <div class="col-md-3 mb-3"><label class="form-label">EDD <small class="text-muted">(auto-calculated)</small></label><input type="date" name="edd" class="form-control" id="edit-enroll-edd" value="${toDateInput(e.edd)}"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">GA</label><div class="form-control bg-light" id="edit-enroll-ga-display" style="font-weight:600; color:#555;">${e.gestational_age || '—'}</div></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Days to EDD</label><div class="form-control bg-light" id="edit-enroll-edd-countdown" style="font-weight:600; color:#555;">${e.remaining_days !== null ? e.remaining_days + ' days' : '—'}</div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-2 mb-3"><label class="form-label">Gravida <span class="text-danger">*</span></label><input type="number" name="gravida" class="form-control" min="1" value="${e.gravida || ''}" required></div>
                        <div class="col-md-2 mb-3"><label class="form-label">Parity</label><input type="number" name="parity" class="form-control" min="0" value="${e.parity || 0}"></div>
                        <div class="col-md-2 mb-3"><label class="form-label">Alive</label><input type="number" name="alive" class="form-control" min="0" value="${e.alive || 0}"></div>
                        <div class="col-md-2 mb-3"><label class="form-label">Abortion / Miscarriage</label><input type="number" name="abortion_miscarriage" class="form-control" min="0" value="${e.abortion_miscarriage || 0}"></div>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-section-title"><i class="mdi mdi-human-pregnant"></i> Booking Measurements</div>
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">Blood Group</label><select name="blood_group" class="form-select"><option value="">-- Select --</option><option ${optionSelected(e.blood_group,'A+')}>A+</option><option ${optionSelected(e.blood_group,'A-')}>A-</option><option ${optionSelected(e.blood_group,'B+')}>B+</option><option ${optionSelected(e.blood_group,'B-')}>B-</option><option ${optionSelected(e.blood_group,'AB+')}>AB+</option><option ${optionSelected(e.blood_group,'AB-')}>AB-</option><option ${optionSelected(e.blood_group,'O+')}>O+</option><option ${optionSelected(e.blood_group,'O-')}>O-</option></select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Genotype</label><select name="genotype" class="form-select"><option value="">-- Select --</option><option ${optionSelected(e.genotype,'AA')}>AA</option><option ${optionSelected(e.genotype,'AS')}>AS</option><option ${optionSelected(e.genotype,'SS')}>SS</option><option ${optionSelected(e.genotype,'AC')}>AC</option><option ${optionSelected(e.genotype,'SC')}>SC</option><option ${optionSelected(e.genotype,'CC')}>CC</option></select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Weight (kg)</label><input type="number" name="booking_weight_kg" id="edit-enroll-weight" class="form-control" step="0.1" value="${e.booking_weight_kg || ''}"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Height (cm)</label><input type="number" name="height_cm" id="edit-enroll-height" class="form-control" step="0.1" value="${e.height_cm || ''}"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3"><label class="form-label">Booking BP</label><input type="text" name="booking_bp" class="form-control" value="${e.booking_bp || ''}" placeholder="e.g. 120/80"></div>
                        <div class="col-md-3 mb-3"><label class="form-label">BMI <small class="text-muted">(auto)</small></label><div class="form-control bg-light" id="edit-enroll-bmi-display" style="font-weight:600;">—</div></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Risk Level</label><select name="risk_level" class="form-select"><option value="low" ${optionSelected(e.risk_level,'low')}>Low</option><option value="moderate" ${optionSelected(e.risk_level,'moderate')}>Moderate</option><option value="high" ${optionSelected(e.risk_level,'high')}>High</option><option value="very_high" ${optionSelected(e.risk_level,'very_high')}>Very High</option></select></div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3"><label class="form-label">Risk Factors</label><textarea name="risk_factors" class="form-control" rows="2">${e.risk_factors || ''}</textarea></div>
                    </div>
                </div>
                <div class="p-3 text-end">
                    <button type="button" class="btn btn-secondary me-2" onclick="renderEnrollmentDetails()"><i class="mdi mdi-close"></i> Cancel</button>
                    <button type="submit" class="btn btn-lg text-white" style="background: var(--maternity-pink);"><i class="mdi mdi-check"></i> Update Enrollment</button>
                </div>
            </form>
        </div>
    </div>`;
        $('#enrollment-content').html(html);

        // Auto-calculate EDD from LMP
        function editUpdateLmpCalcs() {
            const lmpVal = $('#edit-enroll-lmp').val();
            if (!lmpVal) return;
            const lmp = new Date(lmpVal);
            if (isNaN(lmp)) return;
            const edd = new Date(lmp);
            edd.setDate(edd.getDate() + 280);
            $('#edit-enroll-edd').val(edd.toISOString().split('T')[0]);
            const today = new Date();
            const diffDays = Math.floor((today - lmp) / (1000 * 60 * 60 * 24));
            if (diffDays>= 0) {
                const weeks = Math.floor(diffDays / 7),
                    days = diffDays % 7;
                const tri = weeks < 13 ? '1st' : (weeks < 28 ? '2nd' : '3rd');
                $('#edit-enroll-ga-display').html(`<span>${weeks}w ${days}d</span> <span class="badge bg-secondary" style="font-size:0.65rem;">${tri} trimester</span>`);
            }
            const daysToEdd = Math.floor((edd - today) / (1000 * 60 * 60 * 24));
            $('#edit-enroll-edd-countdown').html(daysToEdd> 0 ? daysToEdd + ' days' : (daysToEdd === 0 ? '<span class="text-danger">DUE TODAY</span>' : `<span class="text-danger">${Math.abs(daysToEdd)}d overdue</span>`));
        }
        $('#edit-enroll-lmp').on('change', editUpdateLmpCalcs);

        // Auto-calculate BMI
        function editUpdateBmi() {
            const wt = parseFloat($('#edit-enroll-weight').val()),
                ht = parseFloat($('#edit-enroll-height').val());
            if (wt> 0 && ht> 0) {
                const bmi = (wt / ((ht / 100) ** 2)).toFixed(1);
                const cat = bmi < 18.5 ? 'Underweight' : (bmi < 25 ? 'Normal' : (bmi < 30 ? 'Overweight' : 'Obese'));
                const clr = bmi < 18.5 ? '#17a2b8' : (bmi < 25 ? '#28a745' : (bmi < 30 ? '#ffc107' : '#dc3545'));
                $('#edit-enroll-bmi-display').html(`<span style="color:${clr};">${bmi}</span> <span class="badge" style="background:${clr}; font-size:0.65rem;">${cat}</span>`);
            }
        }
        $('#edit-enroll-weight, #edit-enroll-height').on('input', editUpdateBmi);
        editUpdateBmi(); // initial calc

        // Submit edit
        $('#edit-enrollment-form').on('submit', function(ev) {
            ev.preventDefault();
            const formData = {};
            $(this).serializeArray().forEach(f => formData[f.name] = f.value);
            $.ajax({
                url: wbUrl(`/maternity-workbench/enrollment/${currentEnrollmentId}`),
                method: 'PUT',
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                success: function(resp) {
                    if (resp.success) {
                        toastr.success(resp.message);
                        currentEnrollment = resp.enrollment;
                        currentPatientData.enrollment = resp.enrollment;
                        loadPatient(currentPatient);
                    } else {
                        toastr.error(resp.message || 'Update failed');
                    }
                },
                error: function(xhr) {
                    const errors = xhr.responseJSON?.errors;
                    if (errors) {
                        Object.values(errors).flat().forEach(e => toastr.error(e));
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Update failed');
                    }
                }
            });
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // HISTORY TAB
    // ═══════════════════════════════════════════════════════════════
    function loadHistoryTab() {
        if (!currentEnrollmentId) {
            $('#history-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>');
            return;
        }

        const isBaby = isBabyContext();

        $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}`, function(resp) {
            if (!resp.success) return;
            const enrollment = resp.enrollment;
            window._medicalHistoryCache = enrollment.medical_history || [];
            window._prevPregnanciesCache = enrollment.previous_pregnancies || [];
            let html = '';

            if (isBaby) {
                html += `
                <div class="alert alert-info d-flex align-items-center gap-3 mb-3">
                    <i class="mdi mdi-information" style="font-size:1.5rem;"></i>
                    <div>
                        <strong>Mother's History:</strong> You are viewing the obstetric and medical history of this baby's mother. Changes are disabled in this view.
                    </div>
                </div>`;
            }

            // Medical History
            html += '<div class="card-modern mb-3"><div class="card-header" style="background: #f8f9fa;"><h6 class="mb-0"><i class="mdi mdi-clipboard-text"></i> Medical / Surgical History</h6></div><div class="card-body">';
            if (enrollment.medical_history && enrollment.medical_history.length > 0) {
                html += `<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Category</th><th>Description</th><th>Year</th><th>Notes</th><th>Added By</th>${!isBaby ? '<th style="width:80px">Actions</th>' : ''}</tr></thead><tbody>`;
                enrollment.medical_history.forEach(h => {
                    const creatorName = h.creator ? h.creator.name : '-';
                    let actions = '';
                    if (!isBaby) {
                        actions = `<td><button class="btn btn-sm btn-outline-primary py-0 px-1" onclick="editMedicalHistory(${h.id})" title="Edit"><i class="mdi mdi-pencil"></i></button> <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="confirmDeleteMedicalHistory(${h.id})" title="Delete"><i class="mdi mdi-delete"></i></button></td>`;
                    }
                    html += `<tr><td><span class="badge bg-secondary">${h.category}</span></td><td>${h.description}</td><td>${h.year || '-'}</td><td>${h.notes || '-'}</td><td><small>${creatorName}</small></td>${actions}</tr>`;
                });
                html += '</tbody></table></div>';
            } else {
                html += '<p class="text-muted mb-0">No medical history recorded</p>';
            }
            if (!isBaby) {
                html += `<button class="btn btn-sm btn-outline-primary mt-2" onclick="showAddHistoryForm()"><i class="mdi mdi-plus"></i> Add History</button>`;
            }
            html += `</div></div>`;

            // Previous Pregnancies
            html += '<div class="card-modern mb-3"><div class="card-header" style="background: #f8f9fa;"><h6 class="mb-0"><i class="mdi mdi-human-pregnant"></i> Previous Pregnancies</h6></div><div class="card-body">';
            if (enrollment.previous_pregnancies && enrollment.previous_pregnancies.length > 0) {
                html += `<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Year</th><th>Duration</th><th>Place</th><th>Outcome</th><th>Sex</th><th>Weight</th><th>Notes</th>${!isBaby ? '<th style="width:60px">Edit</th>' : ''}</tr></thead><tbody>`;
                enrollment.previous_pregnancies.forEach(p => {
                    const outcome = p.baby_alive ? '✅ Alive' : (p.baby_dead ? '❌ Dead' : (p.baby_stillbirth ? '💔 Stillbirth' : '-'));
                    let actions = '';
                    if (!isBaby) {
                        actions = `<td><button class="btn btn-sm btn-outline-primary py-0 px-1" onclick="editPreviousPregnancy(${p.id})" title="Edit"><i class="mdi mdi-pencil"></i></button> <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="confirmDeletePreviousPregnancy(${p.id})" title="Delete"><i class="mdi mdi-delete"></i></button></td>`;
                    }
                    html += `<tr><td>${p.year || '-'}</td><td>${p.duration_weeks ? p.duration_weeks + 'w' : '-'}</td><td>${p.place_of_delivery || '-'}</td><td>${outcome}</td><td>${p.baby_sex || '-'}</td><td>${p.birth_weight_kg ? p.birth_weight_kg + 'kg' : '-'}</td><td>${p.notes || '-'}</td>${actions}</tr>`;
                });
                html += '</tbody></table></div>';
            } else {
                html += '<p class="text-muted mb-0">No previous pregnancies recorded</p>';
            }
            if (!isBaby) {
                html += `<button class="btn btn-sm btn-outline-primary mt-2" onclick="showAddPregnancyForm()"><i class="mdi mdi-plus"></i> Add Previous Pregnancy</button>`;
            }
            html += `</div></div>`;

            $('#history-content').html(html);
        });
    }

    function showAddHistoryForm() {
        _editMode = null;
        _editId = null;
        const form = $('#addHistoryModal #add-history-form')[0];
        if (form) form.reset();
        $('#addHistoryModalLabel').html('<i class="mdi mdi-clipboard-text-clock"></i> Add Medical History');
        $('#btn-save-history').html('<i class="mdi mdi-check"></i> Save');
        $('#addHistoryModal input[name="year"]').attr('max', new Date().getFullYear());
        $('#addHistoryModal').modal('show');
    }

    function editMedicalHistory(id) {
        const h = (window._medicalHistoryCache || []).find(x => x.id === id);
        if (!h) {
            toastr.error('Record not found');
            return;
        }
        _editMode = 'history';
        _editId = id;
        const form = $('#addHistoryModal #add-history-form')[0];
        if (form) form.reset();
        $('#addHistoryModalLabel').html('<i class="mdi mdi-pencil"></i> Edit Medical History');
        $('#btn-save-history').html('<i class="mdi mdi-check"></i> Update');
        $('#addHistoryModal select[name="category"]').val(h.category || 'medical');
        $('#addHistoryModal input[name="year"]').val(h.year || '').attr('max', new Date().getFullYear());
        $('#addHistoryModal input[name="description"]').val(h.description || '');
        $('#addHistoryModal input[name="notes"]').val(h.notes || '');
        $('#addHistoryModal').modal('show');
    }

    let _itemToDeleteId = null;
    let _itemToDeleteType = null;

    function confirmDeleteMedicalHistory(id) {
        _itemToDeleteId = id;
        _itemToDeleteType = 'history';
        $('#deleteConfirmModal .modal-body').text('Are you sure you want to delete this medical history entry?');
        $('#deleteConfirmModal').modal('show');
    }

    function confirmDeletePreviousPregnancy(id) {
        _itemToDeleteId = id;
        _itemToDeleteType = 'pregnancy';
        $('#deleteConfirmModal .modal-body').text('Are you sure you want to delete this pregnancy record?');
        $('#deleteConfirmModal').modal('show');
    }

    function confirmDeleteAncVisit(id) {
        _itemToDeleteId = id;
        _itemToDeleteType = 'anc';
        $('#deleteConfirmModal .modal-body').text('Are you sure you want to delete this ANC visit? This will also remove any trend data associated with it.');
        $('#deleteConfirmModal').modal('show');
    }

    function confirmDeleteDeliveryRecord(id) {
        _itemToDeleteId = id;
        _itemToDeleteType = 'delivery';
        $('#deleteConfirmModal .modal-body').html('<div class="alert alert-warning"><i class="mdi mdi-alert"></i> <strong>CRITICAL ACTION:</strong> Deleting a delivery record will also remove all associated <strong>Partograph entries</strong> and <strong>Baby records</strong> for this delivery. This cannot be undone.</div><p>Are you absolutely sure you want to proceed?</p>');
        $('#deleteConfirmModal').modal('show');
    }

    function confirmDeletePostnatalVisit(id) {
        _itemToDeleteId = id;
        _itemToDeleteType = 'postnatal';
        $('#deleteConfirmModal .modal-body').text('Are you sure you want to delete this postnatal visit?');
        $('#deleteConfirmModal').modal('show');
    }

    function confirmDeleteBaby(id) {
        _itemToDeleteId = id;
        _itemToDeleteType = 'baby';
        $('#deleteConfirmModal .modal-body').text('Are you sure you want to remove this baby record?');
        $('#deleteConfirmModal').modal('show');
    }

    $(document).on('click', '#btn-confirm-delete', function() {
        if (!_itemToDeleteId) return;
        const btn = $(this);
        const originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Deleting...');

        let url = '';
        let reloadFn = loadHistoryTab;

        if (_itemToDeleteType === 'history') {
            url = `/maternity-workbench/medical-history/${_itemToDeleteId}`;
        } else if (_itemToDeleteType === 'pregnancy') {
            url = `/maternity-workbench/prev-pregnancy/${_itemToDeleteId}`;
        } else if (_itemToDeleteType === 'anc') {
            url = `/maternity-workbench/anc-visit/${_itemToDeleteId}`;
            reloadFn = loadAncTab;
        } else if (_itemToDeleteType === 'delivery') {
            url = `/maternity-workbench/delivery/${_itemToDeleteId}`;
            reloadFn = loadDeliveryTab;
        } else if (_itemToDeleteType === 'postnatal') {
            url = `/maternity-workbench/postnatal/${_itemToDeleteId}`;
            reloadFn = loadPostnatalTab;
        } else if (_itemToDeleteType === 'baby') {
            url = `/maternity-workbench/baby/${_itemToDeleteId}`;
            reloadFn = loadBabyTab;
        }

        $.ajax({
            url: url,
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            success: function(r) {
                btn.prop('disabled', false).html(originalHtml);
                $('#deleteConfirmModal').modal('hide');
                if (r.success) {
                    toastr.success(r.message);
                    reloadFn();
                    if (_itemToDeleteType === 'delivery') {
                         // Full reload context if delivery deleted
                         loadPatient(currentPatient);
                    }
                } else toastr.error(r.message);
                _itemToDeleteId = null;
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(originalHtml);
                toastr.error(xhr.responseJSON?.message || 'Failed to delete');
                _itemToDeleteId = null;
            }
        });
    });

    // Medical History modal save handler
    $(document).on('click', '#btn-save-history', function() {
        const form = $('#addHistoryModal #add-history-form');
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');
        const isEdit = _editMode === 'history' && _editId;
        if (isEdit) {
            // Single record update via PUT
            const data = {};
            form.serializeArray().forEach(f => data[f.name] = f.value);
            $.ajax({
                url: wbUrl(`/maternity-workbench/medical-history/${_editId}`),
                method: 'PUT',
                data: data,
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                success: function(r) {
                    btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save');
                    if (r.success) {
                        _editMode = null;
                        _editId = null;
                        $('#addHistoryModal').modal('hide');
                        toastr.success(r.message);
                        loadHistoryTab();
                    } else toastr.error(r.message);
                },
                error: function() {
                    btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save');
                    toastr.error('Failed to update');
                }
            });
        } else {
            // Create via POST (existing pattern)
            const data = {
                items: [{}]
            };
            form.serializeArray().forEach(f => data.items[0][f.name] = f.value);
            $.ajax({
                url: wbUrl(`/maternity-workbench/enrollment/${currentEnrollmentId}/medical-history`),
                method: 'POST',
                data: data,
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                success: function(r) {
                    btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save');
                    if (r.success) {
                        $('#addHistoryModal').modal('hide');
                        toastr.success(r.message);
                        loadHistoryTab();
                    } else toastr.error(r.message);
                },
                error: function() {
                    btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save');
                    toastr.error('Failed to save');
                }
            });
        }
    });

    function showAddPregnancyForm() {
        _editMode = null;
        _editId = null;
        const form = $('#addPregnancyModal #add-pregnancy-form')[0];
        if (form) form.reset();
        $('#addPregnancyModalLabel').html('<i class="mdi mdi-baby-carriage"></i> Add Previous Pregnancy');
        $('#btn-save-pregnancy').html('<i class="mdi mdi-check"></i> Save');
        $('#addPregnancyModal input[name="year"]').attr('max', new Date().getFullYear());
        $('#addPregnancyModal').modal('show');
    }

    function editPreviousPregnancy(id) {
        const p = (window._prevPregnanciesCache || []).find(x => x.id === id);
        if (!p) {
            toastr.error('Record not found');
            return;
        }
        _editMode = 'pregnancy';
        _editId = id;
        const form = $('#addPregnancyModal #add-pregnancy-form')[0];
        if (form) form.reset();
        $('#addPregnancyModalLabel').html('<i class="mdi mdi-pencil"></i> Edit Previous Pregnancy');
        $('#btn-save-pregnancy').html('<i class="mdi mdi-check"></i> Update');
        const m = $('#addPregnancyModal');
        m.find('input[name="year"]').val(p.year || '').attr('max', new Date().getFullYear());
        m.find('input[name="duration_weeks"]').val(p.duration_weeks || '');
        m.find('input[name="place_of_delivery"]').val(p.place_of_delivery || '');
        m.find('select[name="baby_sex"]').val(p.baby_sex || '');
        m.find('input[name="birth_weight_kg"]').val(p.birth_weight_kg || '');
        // Determine outcome from booleans
        const outcome = p.baby_alive ? 'alive' : (p.baby_dead ? 'dead' : (p.baby_stillbirth ? 'stillbirth' : 'alive'));
        m.find('select[name="outcome"]').val(outcome);
        m.find('input[name="complications"]').val(p.complications || '');
        m.find('input[name="notes"]').val(p.notes || '');
        m.modal('show');
    }

    // Previous Pregnancy modal save handler
    $(document).on('click', '#btn-save-pregnancy', function() {
        const form = $('#addPregnancyModal #add-pregnancy-form');
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        const data = {};
        form.serializeArray().forEach(f => data[f.name] = f.value);
        data.baby_alive = data.outcome === 'alive';
        data.baby_dead = data.outcome === 'dead';
        data.baby_stillbirth = data.outcome === 'stillbirth';
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');
        const isEdit = _editMode === 'pregnancy' && _editId;
        const url = isEdit ? `/maternity-workbench/prev-pregnancy/${_editId}` : `/maternity-workbench/enrollment/${currentEnrollmentId}/prev-pregnancy`;
        const method = isEdit ? 'PUT' : 'POST';
        $.ajax({
            url: url,
            method: method,
            data: data,
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            success: function(r) {
                btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save');
                if (r.success) {
                    _editMode = null;
                    _editId = null;
                    $('#addPregnancyModal').modal('hide');
                    toastr.success(r.message);
                    loadHistoryTab();
                } else toastr.error(r.message);
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save');
                toastr.error('Failed to save');
            }
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // ANC VISITS TAB
    // ═══════════════════════════════════════════════════════════════
    function loadAncTab() {
        if (!currentEnrollmentId) {
            $('#anc-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>');
            return;
        }

        const isBaby = isBabyContext();

        $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}/anc-visits`, function(resp) {
            if (!resp.success) return;
            _ancVisitsCache = resp.visits; // cache for edit pre-fill

            let html = '';
            if (isBaby) {
                html += `
                <div class="alert alert-info d-flex align-items-center gap-3 mb-3">
                    <i class="mdi mdi-information" style="font-size:1.5rem;"></i>
                    <div>
                        <strong>Mother's ANC Visits:</strong> You are viewing the antenatal records of the pregnancy that resulted in this baby.
                    </div>
                </div>`;
            }

            html += `<div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="mdi mdi-stethoscope"></i> ANC Visits (${resp.visits.length})</h5>
            ${!isBaby ? `<button class="btn text-white" style="background: var(--maternity-pink);" onclick="showAncVisitForm()"><i class="mdi mdi-plus"></i> New ANC Visit</button>` : ''}
        </div>`;

            if (resp.visits.length === 0) {
                html += '<p class="text-muted text-center py-4">No ANC visits recorded yet</p>';
            } else {
                // Trend charts panel (show when ≥2 visits with data)
                html += `<div class="card-modern mb-3">
                <div class="card-header d-flex justify-content-between align-items-center" style="cursor:pointer;" onclick="$('#anc-trends-body').slideToggle(200); $(this).find('.mdi-chevron-down, .mdi-chevron-up').toggleClass('mdi-chevron-down mdi-chevron-up');">
                    <h6 class="mb-0"><i class="mdi mdi-chart-line"></i> ANC Trend Charts</h6>
                    <i class="mdi mdi-chevron-down"></i>
                </div>
                <div class="card-body" id="anc-trends-body" style="display:none;">
                    <div class="small text-muted mb-2"><i class="mdi mdi-information"></i> Trends are plotted from ANC visit data. Reference lines indicate clinically significant thresholds.</div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><div class="border rounded p-2"><div class="small fw-bold text-center mb-1">Blood Pressure Trend</div><div style="position:relative; height:200px;"><canvas id="anc-chart-bp"></canvas></div></div></div>
                        <div class="col-md-6 mb-3"><div class="border rounded p-2"><div class="small fw-bold text-center mb-1">Weight Gain Trend</div><div style="position:relative; height:200px;"><canvas id="anc-chart-weight"></canvas></div></div></div>
                        <div class="col-md-6 mb-3"><div class="border rounded p-2"><div class="small fw-bold text-center mb-1">Fundal Height vs Gestational Age</div><div style="position:relative; height:200px;"><canvas id="anc-chart-fundal"></canvas></div></div></div>
                        <div class="col-md-6 mb-3"><div class="border rounded p-2"><div class="small fw-bold text-center mb-1">Haemoglobin Trend</div><div style="position:relative; height:200px;"><canvas id="anc-chart-hb"></canvas></div></div></div>
                    </div>
                </div>
            </div>`;

                resp.visits.forEach(function(v) {
                    let actions = '';
                    if (!isBaby) {
                        actions = `<button class="btn btn-sm btn-outline-primary py-0 px-1" onclick="editAncVisit(${v.id})" title="Edit visit"><i class="mdi mdi-pencil"></i></button>
                                   <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="confirmDeleteAncVisit(${v.id})" title="Delete visit"><i class="mdi mdi-delete"></i></button>`;
                    }

                    html += `<div class="anc-visit-card shadow-sm border-0 mb-3" style="border-left: 4px solid var(--maternity-pink) !important; border-radius: 8px;">
                    <div class="d-flex justify-content-between mb-2">
                        <div><span class="visit-number" style="color: var(--maternity-pink); font-weight: bold;">Visit #${v.visit_number}</span> <span class="badge bg-secondary ms-1">${v.visit_type || ''}</span></div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="visit-date text-muted"><i class="mdi mdi-calendar"></i> ${v.visit_date || ''}</span>
                            ${actions}
                        </div>
                    </div>
                    
                    <div class="row small mb-2 bg-light p-2 rounded mx-0">
                        <div class="col-md-3 mb-1"><strong>GA:</strong> ${v.gestational_age || '-'}</div>
                        <div class="col-md-3 mb-1"><strong>Weight:</strong> ${v.weight_kg ? v.weight_kg + ' kg' : '-'}</div>
                        <div class="col-md-3 mb-1"><strong>BP:</strong> ${v.bp || '-'}</div>
                        <div class="col-md-3 mb-1"><strong>Next Appt:</strong> ${v.next_appointment || '-'}</div>
                    </div>

                    <div class="row small mb-2 mx-0 px-2">
                        <div class="col-md-3 mb-1"><span class="text-muted">Fundal Ht:</span> <span class="fw-medium">${v.fundal_height ? v.fundal_height + ' cm' : '-'}</span></div>
                        <div class="col-md-3 mb-1"><span class="text-muted">FHR:</span> <span class="fw-medium">${v.fhr || '-'}</span></div>
                        <div class="col-md-3 mb-1"><span class="text-muted">Presentation:</span> <span class="fw-medium">${v.presentation || '-'}</span></div>
                        <div class="col-md-3 mb-1"><span class="text-muted">Foetal Mvt:</span> <span class="fw-medium">${v.foetal_movement || '-'}</span></div>
                    </div>

                    <div class="row small mb-2 mx-0 px-2">
                        <div class="col-md-3 mb-1"><span class="text-muted">Hb:</span> <span class="fw-medium">${v.haemoglobin ? v.haemoglobin + ' g/dL' : '-'}</span></div>
                        <div class="col-md-3 mb-1"><span class="text-muted">Oedema:</span> <span class="fw-medium">${v.oedema || '-'}</span></div>
                        <div class="col-md-3 mb-1"><span class="text-muted">Urine Prot:</span> <span class="fw-medium">${v.urine_protein || '-'}</span></div>
                        <div class="col-md-3 mb-1"><span class="text-muted">Urine Gluc:</span> <span class="fw-medium">${v.urine_glucose || '-'}</span></div>
                    </div>

                    ${v.clinical_notes ? '<div class="mt-2 small text-muted px-2 border-top pt-2"><i class="mdi mdi-note-text-outline text-info"></i> <strong>Notes:</strong> ' + v.clinical_notes + '</div>' : ''}
                    <div class="mt-1 small text-muted px-2 pb-2"><em>Seen by: ${v.seen_by}</em></div>
                </div>`;
                });
            }
            $('#anc-content').html(html);
            if (resp.visits.length>= 2) {
                renderAncTrendCharts(resp.visits);
            }
        });
    }

    function showAncVisitForm() {
        _editMode = null;
        _editId = null; // reset to create mode
        destroyMaternityEditor('anc_notes');
        const form = $('#ancVisitModal #anc-visit-form')[0];
        if (form) form.reset();
        $('#ancVisitModalLabel').html('<i class="mdi mdi-stethoscope"></i> Record ANC Visit');
        $('#btn-save-anc-visit').html('<i class="mdi mdi-check"></i> Save Visit');
        // Set dynamic defaults
        $('#ancVisitModal input[name="visit_date"]').val(new Date().toISOString().split('T')[0]);
        const gaWeeks = currentEnrollment && currentEnrollment.gestational_age ? parseInt(currentEnrollment.gestational_age) : '';
        $('#ancVisitModal input[name="gestational_age_weeks"]').val(gaWeeks);

        // Init CKEditor after modal is fully visible
        $('#ancVisitModal').off('shown.bs.modal.ancEditor').on('shown.bs.modal.ancEditor', function() {
            initMaternityEditor('#mat-anc-notes-editor-modal', 'anc_notes');
        });
        // Destroy CKEditor when modal hides
        $('#ancVisitModal').off('hidden.bs.modal.ancEditor').on('hidden.bs.modal.ancEditor', function() {
            destroyMaternityEditor('anc_notes');
        });

        $('#ancVisitModal').modal('show');
    }

    function editAncVisit(id) {
        const v = _ancVisitsCache.find(x => x.id === id);
        if (!v) {
            toastr.error('Visit data not found');
            return;
        }
        _editMode = 'anc';
        _editId = id;
        destroyMaternityEditor('anc_notes');
        const form = $('#ancVisitModal #anc-visit-form')[0];
        if (form) form.reset();
        $('#ancVisitModalLabel').html('<i class="mdi mdi-pencil"></i> Edit ANC Visit #' + v.visit_number);
        $('#btn-save-anc-visit').html('<i class="mdi mdi-check"></i> Update Visit');
        // Pre-fill form fields
        $('#ancVisitModal input[name="visit_date"]').val(v.visit_date_raw || '');
        $('#ancVisitModal input[name="gestational_age_weeks"]').val(v.gestational_age_weeks || '');
        $('#ancVisitModal select[name="visit_type"]').val(v.visit_type || '');
        $('#ancVisitModal input[name="next_appointment"]').val(v.next_appointment_raw || '');
        $('#ancVisitModal input[name="weight_kg"]').val(v.weight_kg || '');
        $('#ancVisitModal input[name="blood_pressure_systolic"]').val(v.blood_pressure_systolic || '');
        $('#ancVisitModal input[name="blood_pressure_diastolic"]').val(v.blood_pressure_diastolic || '');
        $('#ancVisitModal input[name="haemoglobin"]').val(v.haemoglobin || '');
        $('#ancVisitModal input[name="fundal_height_cm"]').val(v.fundal_height_cm || '');
        $('#ancVisitModal input[name="fetal_heart_rate"]').val(v.fetal_heart_rate || '');
        $('#ancVisitModal select[name="presentation"]').val(v.presentation || '');
        $('#ancVisitModal select[name="oedema"]').val(v.oedema || '');
        $('#ancVisitModal select[name="foetal_movement"]').val(v.foetal_movement || '');
        $('#ancVisitModal select[name="urine_protein"]').val(v.urine_protein || '');
        $('#ancVisitModal select[name="urine_glucose"]').val(v.urine_glucose || '');
        // CKEditor: init after modal is visible, then set data
        $('#ancVisitModal').off('shown.bs.modal.ancEditor').on('shown.bs.modal.ancEditor', function() {
            initMaternityEditor('#mat-anc-notes-editor-modal', 'anc_notes').then(function(editor) {
                if (editor && v.clinical_notes) editor.setData(v.clinical_notes);
            });
        });
        $('#ancVisitModal').off('hidden.bs.modal.ancEditor').on('hidden.bs.modal.ancEditor', function() {
            destroyMaternityEditor('anc_notes');
        });
        $('#ancVisitModal').modal('show');
    }

    // ANC Visit modal save handler
    $(document).on('click', '#btn-save-anc-visit', function() {
        const form = $('#ancVisitModal #anc-visit-form');
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        const data = {};
        form.serializeArray().forEach(f => data[f.name] = f.value);
        data.clinical_notes = getEditorData('anc_notes', '#mat-anc-notes-editor-modal');
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');
        const isEdit = _editMode === 'anc' && _editId;
        const url = isEdit ? `/maternity-workbench/anc-visit/${_editId}` : `/maternity-workbench/enrollment/${currentEnrollmentId}/anc-visit`;
        const method = isEdit ? 'PUT' : 'POST';
        $.ajax({
            url: url,
            method: method,
            data: data,
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            success: function(r) {
                btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save Visit');
                if (r.success) {
                    _editMode = null;
                    _editId = null;
                    destroyMaternityEditor('anc_notes');
                    $('#ancVisitModal').modal('hide');
                    toastr.success(r.message);
                    loadAncTab();
                } else toastr.error(r.message);
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save Visit');
                const e = xhr.responseJSON?.errors;
                if (e) Object.values(e).flat().forEach(m => toastr.error(m));
                else toastr.error('Failed to save');
            }
        });
    });

    // ─── ANC Trend Charts (Phase 4) ───────────────────────────────
    function renderAncTrendCharts(visits) {
        if (typeof Chart === 'undefined' || !visits || visits.length < 2) return;

        // Destroy previous chart instances
        ['_ancBpChart', '_ancWeightChart', '_ancFundalChart', '_ancHbChart'].forEach(k => {
            if (window[k]) {
                window[k].destroy();
                window[k] = null;
            }
        });

        const sorted = [...visits].sort((a, b) => {
            const da = a.visit_date_raw || a.visit_date || '';
            const db = b.visit_date_raw || b.visit_date || '';
            return da.localeCompare(db);
        });
        const labels = sorted.map(v => v.visit_date || `V#${v.visit_number}`);
        const toNum = v => {
            if (v === null || v === undefined || v === '') return null;
            const n = Number(v);
            return isNaN(n) ? null : n;
        };

        // ── 1. Blood Pressure Chart ──
        const bpCanvas = document.getElementById('anc-chart-bp');
        if (bpCanvas) {
            const sys = sorted.map(v => toNum(v.blood_pressure_systolic));
            const dia = sorted.map(v => toNum(v.blood_pressure_diastolic));
            window._ancBpChart = new Chart(bpCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Systolic',
                            data: sys,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220,53,69,0.1)',
                            tension: 0.3,
                            pointRadius: 4,
                            spanGaps: true
                        },
                        {
                            label: 'Diastolic',
                            data: dia,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13,110,253,0.1)',
                            tension: 0.3,
                            pointRadius: 4,
                            spanGaps: true
                        },
                        {
                            label: 'Pre-eclampsia threshold (140/90)',
                            data: new Array(labels.length).fill(140),
                            borderColor: '#fd7e14',
                            borderDash: [5, 3],
                            pointRadius: 0,
                            borderWidth: 1.5,
                            fill: false
                        },
                        {
                            label: '',
                            data: new Array(labels.length).fill(90),
                            borderColor: '#fd7e14',
                            borderDash: [5, 3],
                            pointRadius: 0,
                            borderWidth: 1.5,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                usePointStyle: true,
                                padding: 8,
                                font: {
                                    size: 10
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            title: {
                                display: true,
                                text: 'mmHg'
                            },
                            min: 40,
                            max: 200
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 9
                                },
                                maxRotation: 45
                            }
                        }
                    }
                }
            });
        }

        // ── 2. Weight Gain Chart ──
        const wtCanvas = document.getElementById('anc-chart-weight');
        if (wtCanvas) {
            const wts = sorted.map(v => toNum(v.weight_kg));
            const ppw = currentEnrollment && currentEnrollment.pre_pregnancy_weight ? Number(currentEnrollment.pre_pregnancy_weight) : null;
            const datasets = [{
                label: 'Weight (kg)',
                data: wts,
                borderColor: '#198754',
                backgroundColor: 'rgba(25,135,84,0.1)',
                tension: 0.3,
                pointRadius: 4,
                spanGaps: true,
                fill: true
            }];
            if (ppw) {
                datasets.push({
                    label: 'Pre-pregnancy weight',
                    data: new Array(labels.length).fill(ppw),
                    borderColor: '#6c757d',
                    borderDash: [5, 3],
                    pointRadius: 0,
                    borderWidth: 1.5,
                    fill: false
                });
            }
            window._ancWeightChart = new Chart(wtCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                usePointStyle: true,
                                padding: 8,
                                font: {
                                    size: 10
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            title: {
                                display: true,
                                text: 'kg'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 9
                                },
                                maxRotation: 45
                            }
                        }
                    }
                }
            });
        }

        // ── 3. Fundal Height vs Gestational Age Chart ──
        const fhCanvas = document.getElementById('anc-chart-fundal');
        if (fhCanvas) {
            const gaWeeks = sorted.map(v => toNum(v.gestational_age_weeks));
            const fhCm = sorted.map(v => toNum(v.fundal_height_cm));
            // McDonald's rule reference: fundal height ≈ gestational age ± 2cm
            const refGa = [];
            const refUpper = [];
            const refLower = [];
            for (let w = 12; w <= 42; w++) {
                refGa.push(w);
                refUpper.push(w + 2);
                refLower.push(Math.max(0, w - 2));
            }

            const ptData = [];
            sorted.forEach(v => {
                const ga = toNum(v.gestational_age_weeks);
                const fh = toNum(v.fundal_height_cm);
                if (ga !== null && fh !== null) ptData.push({
                    x: ga,
                    y: fh
                });
            });

            window._ancFundalChart = new Chart(fhCanvas.getContext('2d'), {
                type: 'scatter',
                data: {
                    datasets: [{
                            label: 'Fundal Height',
                            data: ptData,
                            borderColor: '#6f42c1',
                            backgroundColor: '#6f42c1',
                            pointRadius: 5,
                            showLine: true,
                            tension: 0.2
                        },
                        {
                            label: 'Expected (GA ± 2cm)',
                            data: refGa.map((g, i) => ({
                                x: g,
                                y: g
                            })),
                            borderColor: '#198754',
                            borderDash: [4, 2],
                            pointRadius: 0,
                            showLine: true,
                            fill: false,
                            borderWidth: 1.5
                        },
                        {
                            label: 'Upper limit (+2cm)',
                            data: refGa.map((g, i) => ({
                                x: g,
                                y: refUpper[i]
                            })),
                            borderColor: 'rgba(25,135,84,0.3)',
                            borderDash: [2, 2],
                            pointRadius: 0,
                            showLine: true,
                            fill: false,
                            borderWidth: 1
                        },
                        {
                            label: 'Lower limit (−2cm)',
                            data: refGa.map((g, i) => ({
                                x: g,
                                y: refLower[i]
                            })),
                            borderColor: 'rgba(25,135,84,0.3)',
                            borderDash: [2, 2],
                            pointRadius: 0,
                            showLine: true,
                            fill: false,
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                usePointStyle: true,
                                padding: 8,
                                font: {
                                    size: 10
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Gestational Age (weeks)'
                            },
                            min: 12,
                            max: 42
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Fundal Height (cm)'
                            },
                            min: 10,
                            max: 44
                        }
                    }
                }
            });
        }

        // ── 4. Haemoglobin Trend Chart ──
        const hbCanvas = document.getElementById('anc-chart-hb');
        if (hbCanvas) {
            const hbs = sorted.map(v => toNum(v.haemoglobin));
            window._ancHbChart = new Chart(hbCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Hb (g/dL)',
                            data: hbs,
                            borderColor: '#d63384',
                            backgroundColor: 'rgba(214,51,132,0.1)',
                            tension: 0.3,
                            pointRadius: 4,
                            spanGaps: true,
                            fill: true
                        },
                        {
                            label: 'Normal threshold (11 g/dL)',
                            data: new Array(labels.length).fill(11),
                            borderColor: '#198754',
                            borderDash: [5, 3],
                            pointRadius: 0,
                            borderWidth: 1.5,
                            fill: false
                        },
                        {
                            label: 'Severe anaemia (7 g/dL)',
                            data: new Array(labels.length).fill(7),
                            borderColor: '#dc3545',
                            borderDash: [5, 3],
                            pointRadius: 0,
                            borderWidth: 1.5,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                usePointStyle: true,
                                padding: 8,
                                font: {
                                    size: 10
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            title: {
                                display: true,
                                text: 'g/dL'
                            },
                            min: 4,
                            max: 16
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 9
                                },
                                maxRotation: 45
                            }
                        }
                    }
                }
            });
        }
    }

