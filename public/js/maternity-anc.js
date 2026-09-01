    // ═══════════════════════════════════════════════════════════════
    // CLINICAL ORDERS TAB (Nursing-parity — auto-save per item)
    // ═══════════════════════════════════════════════════════════════
    function loadClinicalOrdersTab() {
        if (!currentEnrollmentId || !currentPatient) {
            $('#clinical-orders-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>');
            return;
        }

        const html = `
    <div class="clinical-requests-container p-3">
        <div class="mat-info-banner mb-3"><i class="mdi mdi-auto-fix"></i> <strong>Auto-save enabled:</strong> Items are saved automatically when selected from search results. Use the search boxes below to find and add prescriptions, lab tests, imaging, or procedures.</div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="mdi mdi-clipboard-pulse"></i> Clinical Orders</h4>
            <div class="d-flex gap-2">
                ${isBabyContext() ? '<span class="badge bg-info"><i class="mdi mdi-baby-face"></i> BABY CONTEXT</span>' : '<span class="badge bg-secondary">MOTHER CONTEXT</span>'}
                <span class="badge bg-primary" id="mco-patient-badge">${currentPatientData.name} (#${currentPatientData.file_no})</span>
            </div>
        </div>

        <ul class="nav nav-tabs service-tabs mb-3" id="mco-sub-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mco-prescriptions" type="button" role="tab">
                    <i class="mdi mdi-pill"></i> Drug Prescription
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-non-pharm" type="button" role="tab">
                    <i class="fa fa-heartbeat"></i> Care Plan / Non-Pharm
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-lab" type="button" role="tab">
                    <i class="mdi mdi-flask"></i> Lab Requests
                    <span class="badge bg-danger rounded-pill ms-1 lab-unviewed-badge" id="mco-lab-unviewed-badge" style="display: none; font-size: 0.7rem; padding: 0.25em 0.6em;"></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-imaging" type="button" role="tab">
                    <i class="mdi mdi-radioactive"></i> Imaging
                    <span class="badge bg-danger rounded-pill ms-1 imaging-unviewed-badge" id="mco-imaging-unviewed-badge" style="display: none; font-size: 0.7rem; padding: 0.25em 0.6em;"></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-procedures" type="button" role="tab">
                    <i class="mdi mdi-medical-bag"></i> Procedures
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- ══ PRESCRIPTIONS ══ -->
            <div class="tab-pane fade show active" id="mco-prescriptions" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                            <div class="btn-group">

                                <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                    <i class="fa fa-save"></i> Save as Template
                                </button>
                            </div>
                            <div class="dropdown" id="mco-rp-encounter-dropdown">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <i class="fa fa-redo"></i> Re-prescribe from Encounter
                                </button>
                                <ul class="dropdown-menu rp-encounter-menu" style="min-width: 320px; max-height: 300px; overflow-y: auto;">
                                    <li class="dropdown-item text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</li>
                                </ul>
                            </div>
                        </div>
                        <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mco-presc-history" type="button"><i class="fa fa-history"></i> Drug History</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-presc-new" type="button"><i class="fa fa-plus-circle"></i> Add Prescription</button></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="mco-presc-history" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" style="width:100%" id="mco_presc_history_list">
                                        <thead class="table-light"><tr><th style="width:100%"><i class="mdi mdi-pill"></i> Prescriptions</th></tr></thead>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="mco-presc-new" role="tabpanel">
                                <div id="mco_presc_message" class="mb-2"></div>
                                <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Prescription</h6>
                                <div id="mco_dose_mode_container"></div>
                                <div class="form-group">
                                    <label>Search drugs/products</label>
                                    <input type="text" class="form-control" id="mco_presc_search" placeholder="Type to search products..." autocomplete="off">
                                    <ul class="list-group co-search-dropdown" id="mco_presc_results"></ul>
                                </div>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm table-bordered table-striped">
                                        <thead><tr><th>Drug / Product</th><th>Price</th><th>Dose / Frequency</th><th style="width:40px;"><i class="fa fa-trash-alt text-muted" title="Remove"></i></th></tr></thead>
                                        <tbody id="mco-selected-products"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ NON-PHARM / CARE PLAN ══ -->
            <div class="tab-pane fade" id="mco-non-pharm" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">
                        <div id="mco-non-pharm-container"></div>
                    </div>
                </div>
            </div>

            <!-- ══ LAB REQUESTS ══ -->
            <div class="tab-pane fade" id="mco-lab" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                            <div class="btn-group">

                                <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                    <i class="fa fa-save"></i> Save as Template
                                </button>
                            </div>
                        </div>
                        <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mco-lab-history" type="button"><i class="fa fa-history"></i> Lab History</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-lab-new" type="button"><i class="fa fa-plus-circle"></i> New Lab Request</button></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="mco-lab-history" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" style="width:100%" id="mco_lab_history_list">
                                        <thead class="table-light"><tr><th style="width:100%"><i class="mdi mdi-flask"></i> Lab Requests</th></tr></thead>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="mco-lab-new" role="tabpanel">
                                <div id="mco_lab_message" class="mb-2"></div>
                                <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Lab Request</h6>
                                <div class="form-group">
                                    <label>Search lab services</label>
                                    <input type="text" class="form-control" id="mco_lab_search" placeholder="Type to search lab services..." autocomplete="off">
                                    <ul class="list-group co-search-dropdown" id="mco_lab_results"></ul>
                                </div>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm table-bordered table-striped">
                                        <thead><tr><th>Lab Test</th><th>Price</th><th>Clinical Notes</th><th style="width:40px;"><i class="fa fa-trash-alt text-muted" title="Remove"></i></th></tr></thead>
                                        <tbody id="mco-selected-labs"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ IMAGING ══ -->
            <div class="tab-pane fade" id="mco-imaging" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                            <div class="btn-group">

                                <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                    <i class="fa fa-save"></i> Save as Template
                                </button>
                            </div>
                        </div>
                        <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mco-imaging-history" type="button"><i class="fa fa-history"></i> Imaging History</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-imaging-new" type="button"><i class="fa fa-plus-circle"></i> New Imaging Request</button></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="mco-imaging-history" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" style="width:100%" id="mco_imaging_history_list">
                                        <thead class="table-light"><tr><th style="width:100%"><i class="mdi mdi-radioactive"></i> Imaging Requests</th></tr></thead>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="mco-imaging-new" role="tabpanel">
                                <div id="mco_imaging_message" class="mb-2"></div>
                                <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Imaging Request</h6>
                                <div class="form-group">
                                    <label>Search imaging services</label>
                                    <input type="text" class="form-control" id="mco_imaging_search" placeholder="Type to search imaging services..." autocomplete="off">
                                    <ul class="list-group co-search-dropdown" id="mco_imaging_results"></ul>
                                </div>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm table-bordered table-striped">
                                        <thead><tr><th>Imaging Study</th><th>Price</th><th>Clinical Notes</th><th style="width:40px;"><i class="fa fa-trash-alt text-muted" title="Remove"></i></th></tr></thead>
                                        <tbody id="mco-selected-imaging"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══ PROCEDURES ══ -->
            <div class="tab-pane fade" id="mco-procedures" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                            <div class="btn-group">

                                <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                    <i class="fa fa-save"></i> Save as Template
                                </button>
                            </div>
                        </div>
                        <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mco-proc-history" type="button"><i class="fa fa-history"></i> Procedure History</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mco-proc-new" type="button"><i class="fa fa-plus-circle"></i> Request Procedure</button></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="mco-proc-history" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" style="width:100%" id="mco_proc_history_list">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 100%;"><i class="mdi mdi-medical-bag"></i> Procedure Requests</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="mco-proc-new" role="tabpanel">
                                <div id="mco_proc_message" class="mb-2"></div>
                                <h6 class="mb-3"><i class="fa fa-plus-circle"></i> Request New Procedure</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label><i class="fa fa-search"></i> Search Procedure</label>
                                            <input type="text" class="form-control" id="mco_proc_search" placeholder="Type procedure name or code..." autocomplete="off">
                                            <ul class="list-group co-search-dropdown" id="mco_proc_results"></ul>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mb-3">
                                            <label><i class="fa fa-exclamation-triangle"></i> Priority <span class="mat-tooltip-icon" title="Routine: scheduled normally. Urgent: needs attention soon. Emergency: immediate intervention required"><i class="mdi mdi-help-circle"></i></span></label>
                                            <select class="form-control" id="mco_proc_priority">
                                                <option value="routine">Routine</option>
                                                <option value="urgent">Urgent</option>
                                                <option value="emergency">Emergency</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mb-3">
                                            <label><i class="fa fa-calendar"></i> Scheduled Date</label>
                                            <input type="date" class="form-control" id="mco_proc_scheduled_date">
                                            <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Leave blank for today; set future date for elective procedures</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group mb-3">
                                    <label><i class="fa fa-sticky-note"></i> Pre-op / Clinical Notes</label>
                                    <textarea class="form-control" id="mco_proc_notes" rows="2" placeholder="Clinical indications, relevant history, patient consent status..."></textarea>
                                    <div class="mat-form-help"><i class="mdi mdi-help-circle"></i> Document clinical indications, relevant history, and any special instructions for the procedure team</div>
                                </div>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm table-bordered table-striped">
                                        <thead><tr><th>Procedure</th><th>Price</th><th>Priority</th><th style="width:40px;"><i class="fa fa-trash-alt text-muted" title="Remove"></i></th></tr></thead>
                                        <tbody id="mco-selected-procedures"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>`;

        $('#clinical-orders-content').html(html);

        // Inject the dose-mode toggle from the hidden source into the dynamic container
        $('#mco_dose_mode_container').html($('#mco-dose-mode-toggle-source').html());

        MaternityClinicalOrders.init(currentPatient, currentEnrollmentId);
    }

    const MaternityClinicalOrders = (function() {
        let patientId = null;
        let enrollmentId = null;
        let mcoDoseStructuredMode = true;
        const investigationCategoryId = window.WORKBENCH_CONFIG?.investigationCategoryId || '';
        const procedureCategoryId = '';

        function init(pid, eid) {
            patientId = pid;
            enrollmentId = eid;

            // Clear selection tables
            $('#mco-selected-products').empty();
            $('#mco-selected-labs').empty();
            $('#mco-selected-imaging').empty();
            $('#mco-selected-procedures').empty();

            // Initialize Non-Pharmacological Bedside Care Orders
            window.NonPharmManager.init({
                patientId: pid,
                maternityEnrollmentId: eid,
                encounterId: null,
                containerId: '#mco-non-pharm-container',
                isNurseView: true
            });

            // Init history DataTables
            initPrescHistory();
            initLabHistory();
            initImagingHistory();
            initProcHistory();

            // Clear ClinicalOrdersKit duplicate tracking
            if (typeof ClinicalOrdersKit !== 'undefined') {
                ClinicalOrdersKit.clearAddedIds();
            }

            // One-time initialization
            if (!MaternityClinicalOrders._advancedInit && typeof ClinicalOrdersKit !== 'undefined') {

                // Dose mode toggle
                var mcoDoseState = ClinicalOrdersKit.initDoseModeToggle({
                    prefix: 'mco_',
                    cssPrefix: 'mco-',
                    tableSelector: '#mco-selected-products',
                    idInputName: 'mco_presc_id[]',
                    doseInputName: 'mco_presc_dose[]',
                    onchange: 'ClinicalOrdersKit.updateDoseValue(this, "mco-")',
                    onToggle: function(isStructured) {
                        mcoDoseStructuredMode = isStructured;
                    }
                });
                mcoDoseStructuredMode = mcoDoseState.isStructured;

                ClinicalOrdersKit.onDoseUpdate('mco-', function(recordId, doseValue, flashEl) {
                    ClinicalOrdersKit.debouncedUpdate({
                        url: wbUrl('/maternity-workbench/enrollment/' + enrollmentId + '/prescriptions/' + recordId + '/dose'),
                        payload: {
                            dose: doseValue
                        },
                        csrfToken: CSRF_TOKEN,
                        flashTarget: flashEl,
                        onSuccess: function() {
                            initPrescHistory();
                        }
                    });
                });

                // Treatment Plans
                ClinicalOrdersKit.initTreatmentPlans({
                    applyUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/apply-treatment-plan',
                    csrfToken: CSRF_TOKEN,
                    extraPayload: {
                        enrollment_id: enrollmentId
                    },
                    onApplySuccess: function() {
                        initPrescHistory();
                        initLabHistory();
                        initImagingHistory();
                        initProcHistory();
                    },
                    currentItemsGatherer: function() {
                        var items = [];
                        $('#mco-selected-labs tr[data-record-id]').each(function() {
                            items.push({
                                item_type: 'lab',
                                reference_id: parseInt($(this).data('service-id')),
                                display_name: $(this).find('td:first').text().trim(),
                                note: $(this).find('input[name="mco_lab_note[]"]').val() || ''
                            });
                        });
                        $('#mco-selected-imaging tr[data-record-id]').each(function() {
                            items.push({
                                item_type: 'imaging',
                                reference_id: parseInt($(this).data('service-id')),
                                display_name: $(this).find('td:first').text().trim(),
                                note: $(this).find('input[name="mco_imaging_note[]"]').val() || ''
                            });
                        });
                        $('#mco-selected-products tr[data-record-id]').each(function() {
                            items.push({
                                item_type: 'medication',
                                reference_id: parseInt($(this).data('service-id')),
                                display_name: $(this).find('td:first').text().trim(),
                                dose: $(this).find('input[name="mco_presc_dose[]"]').val() || ''
                            });
                        });
                        $('#mco-selected-procedures tr[data-record-id]').each(function() {
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

                // Re-prescribe from Encounter
                ClinicalOrdersKit.initRePrescribeFromEncounter({
                    recentUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/recent-encounters',
                    encounterItemsUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/encounter-items/{id}',
                    rePrescribeUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/re-prescribe',
                    csrfToken: CSRF_TOKEN,
                    extraPayload: {
                        enrollment_id: enrollmentId
                    },
                    dropdownSelector: '#mco-rp-encounter-dropdown',
                    onRePrescribed: function() {
                        initPrescHistory();
                        initLabHistory();
                        initImagingHistory();
                        initProcHistory();
                    }
                });

                MaternityClinicalOrders._advancedInit = true;
            }

            // Update configs on every enrollment switch
            if (typeof ClinicalOrdersKit !== 'undefined') {
                ClinicalOrdersKit.updateTreatmentPlanConfig({
                    applyUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/apply-treatment-plan',
                    extraPayload: {
                        enrollment_id: enrollmentId
                    }
                });
                ClinicalOrdersKit.updateRePrescribeConfig({
                    recentUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/recent-encounters',
                    encounterItemsUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/encounter-items/{id}',
                    rePrescribeUrl: '/maternity-workbench/enrollment/' + enrollmentId + '/re-prescribe',
                    extraPayload: {
                        enrollment_id: enrollmentId
                    }
                });
            }

            // Setup search + re-order handlers (only once)
            if (!MaternityClinicalOrders._searchBound) {
                bindSearchHandlers();
                MaternityClinicalOrders._searchBound = true;
            }
        }

        function bindSearchHandlers() {
            let searchTimeout;

            // Initialize floating dropdowns (escape overflow:hidden ancestors)
            ClinicalOrdersKit.initSearchDropdown('#mco_presc_search', '#mco_presc_results');
            ClinicalOrdersKit.initSearchDropdown('#mco_lab_search', '#mco_lab_results');
            ClinicalOrdersKit.initSearchDropdown('#mco_imaging_search', '#mco_imaging_results');
            ClinicalOrdersKit.initSearchDropdown('#mco_proc_search', '#mco_proc_results');

            // Drug search
            $('#mco_presc_search').on('keyup', function() {
                const q = $(this).val();
                clearTimeout(searchTimeout);
                if (q.length < 2) {
                    $('#mco_presc_results').hide();
                    return;
                }
                ClinicalOrdersKit.positionDropdown('#mco_presc_search', '#mco_presc_results');
                ClinicalOrdersKit.showSearchLoading('#mco_presc_results');
                searchTimeout = setTimeout(() => searchProducts(q), 300);
            });

            // Lab search
            $('#mco_lab_search').on('keyup', function() {
                const q = $(this).val();
                clearTimeout(searchTimeout);
                if (q.length < 2) {
                    $('#mco_lab_results').hide();
                    return;
                }
                ClinicalOrdersKit.positionDropdown('#mco_lab_search', '#mco_lab_results');
                ClinicalOrdersKit.showSearchLoading('#mco_lab_results');
                searchTimeout = setTimeout(() => searchLabServices(q), 300);
            });

            // Imaging search
            $('#mco_imaging_search').on('keyup', function() {
                const q = $(this).val();
                clearTimeout(searchTimeout);
                if (q.length < 2) {
                    $('#mco_imaging_results').hide();
                    return;
                }
                ClinicalOrdersKit.positionDropdown('#mco_imaging_search', '#mco_imaging_results');
                ClinicalOrdersKit.showSearchLoading('#mco_imaging_results');
                searchTimeout = setTimeout(() => searchImagingServices(q), 300);
            });

            // Procedure search
            $('#mco_proc_search').on('keyup', function() {
                const q = $(this).val();
                clearTimeout(searchTimeout);
                if (q.length < 2) {
                    $('#mco_proc_results').hide();
                    return;
                }
                ClinicalOrdersKit.positionDropdown('#mco_proc_search', '#mco_proc_results');
                ClinicalOrdersKit.showSearchLoading('#mco_proc_results');
                searchTimeout = setTimeout(() => searchProcedureServices(q), 300);
            });

            // Re-order from history (nursing parity — Plan §5.2)
            $(document).off('click.mcoreorder').on('click.mcoreorder', '.re-order-btn', function() {
                var $btn = $(this);
                if ($btn.prop('disabled')) return;

                var type = $btn.data('type');
                var name = $btn.data('name');
                var price = $btn.data('price') || 0;
                var coverageMode = $btn.data('coverage-mode') || null;
                var claims = $btn.data('claims') || null;
                var payable = $btn.data('payable') || null;
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
                    addProductService(name, productId, price, coverageMode, claims, payable);
                }

                $btn.prop('disabled', true).html('<i class="fa fa-check text-success"></i> Added');
            });
        }

        // ===== HISTORY DATATABLES =====
        function initPrescHistory() {
            if ($.fn.DataTable.isDataTable('#mco_presc_history_list')) {
                $('#mco_presc_history_list').DataTable().destroy();
            }
            $('#mco_presc_history_list').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: wbUrl('/prescHistoryList/' + patientId),
                    type: 'GET'
                },
                columns: [{
                    data: 'info',
                    name: 'info',
                    orderable: false
                }],
                order: [
                    [0, 'desc']
                ],
                pageLength: 10,
                language: {
                    emptyTable: 'No prescription history',
                    processing: '<i class="fa fa-spinner fa-spin"></i> Loading...'
                }
            });
        }

        function initLabHistory() {
            if ($.fn.DataTable.isDataTable('#mco_lab_history_list')) {
                $('#mco_lab_history_list').DataTable().destroy();
            }
            $('#mco_lab_history_list').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: wbUrl('/investigationHistoryList/' + patientId),
                    type: 'GET'
                },
                columns: [{
                    data: 'info',
                    name: 'info',
                    orderable: false
                }],
                order: [
                    [0, 'desc']
                ],
                pageLength: 10,
                language: {
                    emptyTable: 'No lab history',
                    processing: '<i class="fa fa-spinner fa-spin"></i> Loading...'
                }
            });
        }

        function initImagingHistory() {
            if ($.fn.DataTable.isDataTable('#mco_imaging_history_list')) {
                $('#mco_imaging_history_list').DataTable().destroy();
            }
            $('#mco_imaging_history_list').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: wbUrl('/imagingHistoryList/' + patientId),
                    type: 'GET'
                },
                columns: [{
                    data: 'info',
                    name: 'info',
                    orderable: false
                }],
                order: [
                    [0, 'desc']
                ],
                pageLength: 10,
                language: {
                    emptyTable: 'No imaging history',
                    processing: '<i class="fa fa-spinner fa-spin"></i> Loading...'
                }
            });
        }

        function initProcHistory() {
            if ($.fn.DataTable.isDataTable('#mco_proc_history_list')) {
                $('#mco_proc_history_list').DataTable().destroy();
            }
            $('#mco_proc_history_list').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: wbUrl('/procedureHistoryList/' + patientId),
                    type: 'GET'
                },
                columns: [
                    {
                        data: 'info',
                        name: 'info',
                        orderable: false,
                        searchable: false
                    }
                ],
                order: [],
                pageLength: 10,
                language: {
                    emptyTable: 'No procedure history',
                    processing: '<i class="fa fa-spinner fa-spin"></i> Loading...'
                }
            });
        }

        // ===== SEARCH FUNCTIONS =====
        function searchLabServices(q) {
            const data = {
                term: q,
                patient_id: patientId
            };
            if (investigationCategoryId) data.category_id = investigationCategoryId;

            $.get('/live-search-services', data, function(results) {
                const $res = $('#mco_lab_results').empty();
                ClinicalOrdersKit.appendFreeFormLink($res, q, 'Add Free-Form Lab Test', 'Enter lab test name:', '#mco_lab_search', function(val) {
                    MaternityClinicalOrders.addLabService(val + ' [Free-form]', 'FF_' + val, 0, 'cash', 0, 0);
                });
                if (!results.length) {
                    ClinicalOrdersKit.showSearchEmpty('#mco_lab_results', 'lab services');
                    return;
                } else {
                    results.forEach(item => {
                        const name = item.service_name || 'Unknown';
                        const code = item.service_code || '';
                        const price = item.price?.sale_price ?? 0;
                        const display = name + '[' + code + ']';
                        const isCombo = item.is_combo || false;
                        const bundleItems = item.bundle_items || [];
                        if (isCombo) { window.comboDataMap = window.comboDataMap || {}; window.comboDataMap[item.id] = item; }
                        const alreadyAdded = false; // combos can't be "added" twice
                        const mode = item.coverage_mode || null;
                        const payable = item.payable_amount ?? price;
                        const claims = item.claims_amount ?? 0;
                        const onClick = isCombo 
                            ? `MaternityClinicalOrders.applyLabCombo(${item.id}, ${enrollmentId}, '${name.replace(/'/g, "\\'")}')`
                            : `MaternityClinicalOrders.addLabService('${display.replace(/'/g, "\\'") }', ${item.id}, ${price}, '${mode || ''}', ${claims}, ${payable})`;
                        
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
            });
        }

        let mcoSearchProdTimeout = null;
        let mcoSearchProdRequest = null;

        function searchProducts(q) {
            if (mcoSearchProdRequest) mcoSearchProdRequest.abort();
            clearTimeout(mcoSearchProdTimeout);

            const $res = $('#mco_presc_results');
            if (q.length < 2) { $res.empty().hide(); return; }

            $res.html('<li class="list-group-item text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</li>').show();

            mcoSearchProdTimeout = setTimeout(() => {
                mcoSearchProdRequest = $.ajax({
                    url: wbUrl('/live-search-products'),
                    method: 'GET',
                    dataType: 'json',
                    data: { term: q, patient_id: patientId },
                    success: (results) => {
                        $res.empty();
                        ClinicalOrdersKit.appendFreeFormLink($res, q, 'Add Free-Form Medication', 'Enter medication name:', '#mco_presc_search', function(val) {
                            MaternityClinicalOrders.addProductService(val + ' [Free-form]', 'FF_' + val, 0, 'cash', 0, 0);
                        });
                        if (!results.length) {
                            ClinicalOrdersKit.showSearchEmpty('#mco_presc_results', 'products');
                            return;
                        } else {
                            results.forEach(item => {
                                const name = item.product_name || 'Unknown';
                                const code = item.product_code || '';
                                const qty = item.stock?.current_quantity ?? 0;
                                const price = item.price?.initial_sale_price ?? 0;
                                const display = name + '[' + code + '](' + qty + ' avail.)';
                                const isCombo = item.is_combo || false;
                                const bundleItems = item.bundle_items || [];
                                if (isCombo) { window.comboDataMap = window.comboDataMap || {}; window.comboDataMap[item.id] = item; }
                                const alreadyAdded = isCombo ? false : ClinicalOrdersKit.isAlreadyAdded('meds', parseInt(item.id));
                                const mode = item.coverage_mode || null;
                                const payable = item.payable_amount ?? price;
                                const claims = item.claims_amount ?? 0;
                                const onClick = alreadyAdded ? '' : (isCombo 
                                    ? `MaternityClinicalOrders.applyProductCombo(${item.id}, ${enrollmentId}, '${name.replace(/'/g, "\\'")}')`
                                    : 'MaternityClinicalOrders.addProductService(\'' + display.replace(/'/g, "\\'") + '\', ' + item.id + ', ' + price + ', \'' + (mode || '') + '\', ' + claims + ', ' + payable + ')');
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

        function searchImagingServices(q) {
            $.get('/live-search-services', {
                term: q,
                category_id: 6,
                patient_id: patientId
            }, function(results) {
                const $res = $('#mco_imaging_results').empty();
                ClinicalOrdersKit.appendFreeFormLink($res, q, 'Add Free-Form Imaging Request', 'Enter imaging request name:', '#mco_imaging_search', function(val) {
                    MaternityClinicalOrders.addImagingService(val + ' [Free-form]', 'FF_' + val, 0, 'cash', 0, 0);
                });
                if (!results.length) {
                    ClinicalOrdersKit.showSearchEmpty('#mco_imaging_results', 'imaging services');
                    return;
                } else {
                    results.forEach(item => {
                        const name = item.service_name || 'Unknown';
                        const code = item.service_code || '';
                        const price = item.price?.sale_price ?? 0;
                        const display = name + '[' + code + ']';
                        const isCombo = item.is_combo || false;
                        const bundleItems = item.bundle_items || [];
                        if (isCombo) { window.comboDataMap = window.comboDataMap || {}; window.comboDataMap[item.id] = item; }
                        const alreadyAdded = false; // combos can't be "added" twice
                        const mode = item.coverage_mode || null;
                        const payable = item.payable_amount ?? price;
                        const claims = item.claims_amount ?? 0;
                        const onClick = isCombo 
                            ? `MaternityClinicalOrders.applyImagingCombo(${item.id}, ${enrollmentId}, '${name.replace(/'/g, "\\'")}')`
                            : `MaternityClinicalOrders.addImagingService('${display.replace(/'/g, "\\'") }', ${item.id}, ${price}, '${mode || ''}', ${claims}, ${payable})`;
                        
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
            $.get('/live-search-services', {
                term: q,
                category_id: procedureCategoryId,
                patient_id: patientId
            }, function(results) {
                const $res = $('#mco_proc_results').empty();
                ClinicalOrdersKit.appendFreeFormLink($res, q, 'Add Free-Form Procedure', 'Enter procedure name:', '#mco_proc_search', function(val) {
                    MaternityClinicalOrders.addProcedureService(val + ' [Free-form]', 'FF_' + val, 0);
                });
                if (!results.length) {
                    ClinicalOrdersKit.showSearchEmpty('#mco_proc_results', 'procedures');
                    return;
                } else {
                    results.forEach(item => {
                        const name = item.service_name || 'Unknown';
                        const code = item.service_code || '';
                        const price = item.price?.sale_price ?? 0;
                        const display = name + '[' + code + ']';
                        const alreadyAdded = ClinicalOrdersKit.isAlreadyAdded('procedures', parseInt(item.id));
                        const payable = item.payable_amount ?? price;
                        const onClick = alreadyAdded ? '' : 'MaternityClinicalOrders.addProcedureService(\'' + display.replace(/'/g, "\\'") + '\', ' + item.id + ', ' + payable + ')';
                        $res.append(ClinicalOrdersKit.renderSearchResultItem({
                            id: item.id,
                            category: item.category?.category_name || 'Procedure',
                            name: name,
                            code: code,
                            price: price,
                            payable: payable,
                            claims: item.claims_amount ?? 0,
                            mode: item.coverage_mode || null,
                            alreadyAdded: alreadyAdded,
                            alreadyLabel: 'Already Added',
                            onClick: onClick
                        }));
                    });
                }
                $res.show();
            });
        }

        // ===== AUTO-SAVE ADD FUNCTIONS (via ClinicalOrdersKit.addItem) =====

        function addProductService(name, id, price, mode, claims, payable) {
            var rowId = 'mco_rx_' + Date.now() + '_' + id;
            var coverageBadge = ClinicalOrdersKit.renderCoverageBadge(
                mode && mode !== 'null' ? mode : null, payable ?? price, claims ?? 0
            );

            ClinicalOrdersKit.addItem({
                url: wbUrl('/maternity-workbench/enrollment/' + enrollmentId + '/add-prescription'),
                payload: {
                    product_id: id,
                    dose: ''
                },
                csrfToken: CSRF_TOKEN,
                tableSelector: '#mco-selected-products',
                type: 'meds',
                referenceId: parseInt(id),
                buildRowHtml: function(resp) {
                    var recordId = resp.id;
                    var doseOnchange = "ClinicalOrdersKit.updateDoseValue(this, 'mco-'); ";

                    var doseCell;
                    if (mcoDoseStructuredMode) {
                        doseCell = '<td>' + ClinicalOrdersKit.buildStructuredDoseHtml({
                            cssPrefix: 'mco-',
                            hiddenName: 'mco_presc_dose[]',
                            onchange: doseOnchange,
                            drugName: name,
                            rowId: rowId
                        }) + '<input type="hidden" name="mco_presc_id[]" value="' + id + '"></td>';
                    } else {
                        var simpleDoseCmd = "ClinicalOrdersKit.updateDoseValue(this, 'mco-');";
                        doseCell = '<td><input type="text" class="form-control form-control-sm" name="mco_presc_dose[]" ' +
                            'placeholder="e.g. 500mg BD x 5days" ' +
                            'onfocus="ClinicalOrdersKit.startPeriodicSave(this)" ' +
                            'onblur="ClinicalOrdersKit.stopPeriodicSave(this); ClinicalOrdersKit.cancelIdleTimer(this); ' + simpleDoseCmd + '" ' +
                            'oninput="ClinicalOrdersKit.scheduleIdleUpdate(this, function(){ ' + simpleDoseCmd + ' }, 3000)" required>' +
                            '<input type="hidden" name="mco_presc_id[]" value="' + id + '"></td>';
                    }

                    return '<tr data-record-id="' + recordId + '" data-record-type="prescription" data-service-id="' + id + '" data-drug-name="' + name.replace(/"/g, '&quot;') + '" data-row-id="' + rowId + '">' +
                        '<td>' + name + coverageBadge + '</td>' +
                        '<td>' + (payable ?? price) + '</td>' +
                        doseCell +
                        '<td><button class="btn btn-sm btn-danger" onclick="MaternityClinicalOrders.removeAutoSavedRow(this,\'prescription\',' + recordId + ',' + id + ')"><span class="co-remove-btn"><i class="fa fa-times"></i></span></button></td>' +
                        '</tr>';
                },
                onSuccess: function(resp) {
                    initPrescHistory();
                }
            });
            $('#mco_presc_search').val('');
            $('#mco_presc_results').hide();
        }

        function addLabService(name, id, price, mode, claims, payable) {
            ClinicalOrdersKit.addItem({
                url: wbUrl('/maternity-workbench/enrollment/' + enrollmentId + '/add-lab'),
                payload: {
                    service_id: id,
                    note: ''
                },
                csrfToken: CSRF_TOKEN,
                tableSelector: '#mco-selected-labs',
                type: 'labs',
                referenceId: parseInt(id),
                buildRowHtml: function(response) {
                    var coverageBadge = mode && mode !== 'null' ? '<div class="small mt-1"><span class="badge bg-info">' + (mode || '').toUpperCase() + '</span> <span class="text-danger">Pay: ' + payable + '</span> <span class="text-success">Claims: ' + claims + '</span></div>' : '';
                    return '<tr data-record-id="' + response.id + '" data-record-type="lab" data-service-id="' + id + '">' +
                        '<td>' + name + coverageBadge + '</td>' +
                        '<td>' + (payable ?? price) + '</td>' +
                        '<td><input type="text" class="form-control form-control-sm" name="mco_lab_note[]" placeholder="e.g. Fasting, urgent, repeat in 2wks" ' +
                        'onblur="ClinicalOrdersKit.cancelIdleTimer(this); ClinicalOrdersKit.debouncedUpdate({url:\'/maternity-workbench/enrollment/' + enrollmentId + '/labs/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + CSRF_TOKEN + '\',flashTarget:this.closest(\'td\')})" ' +
                        'oninput="ClinicalOrdersKit.scheduleIdleUpdate(this, function(){ ClinicalOrdersKit.debouncedUpdate({url:\'/maternity-workbench/enrollment/' + enrollmentId + '/labs/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + CSRF_TOKEN + '\',flashTarget:this.closest(\'td\')}) }, 3000)">' +
                        '<input type="hidden" name="mco_lab_id[]" value="' + id + '"></td>' +
                        '<td><button class="btn btn-sm btn-danger" onclick="MaternityClinicalOrders.removeAutoSavedRow(this,\'lab\',' + response.id + ',' + id + ')"><span class="co-remove-btn"><i class="fa fa-times"></i></span></button></td>' +
                        '</tr>';
                },
                onSuccess: function(resp) {
                    initLabHistory();
                }
            });
            $('#mco_lab_search').val('');
            $('#mco_lab_results').hide();
        }

        function addImagingService(name, id, price, mode, claims, payable) {
            ClinicalOrdersKit.addItem({
                url: wbUrl('/maternity-workbench/enrollment/' + enrollmentId + '/add-imaging'),
                payload: {
                    service_id: id,
                    note: ''
                },
                csrfToken: CSRF_TOKEN,
                tableSelector: '#mco-selected-imaging',
                type: 'imaging',
                referenceId: parseInt(id),
                buildRowHtml: function(response) {
                    var coverageBadge = mode && mode !== 'null' ? '<div class="small mt-1"><span class="badge bg-info">' + (mode || '').toUpperCase() + '</span> <span class="text-danger">Pay: ' + payable + '</span> <span class="text-success">Claims: ' + claims + '</span></div>' : '';
                    return '<tr data-record-id="' + response.id + '" data-record-type="imaging" data-service-id="' + id + '">' +
                        '<td>' + name + coverageBadge + '</td>' +
                        '<td>' + (payable ?? price) + '</td>' +
                        '<td><input type="text" class="form-control form-control-sm" name="mco_imaging_note[]" placeholder="e.g. R/O fracture, contrast required" ' +
                        'onblur="ClinicalOrdersKit.cancelIdleTimer(this); ClinicalOrdersKit.debouncedUpdate({url:\'/maternity-workbench/enrollment/' + enrollmentId + '/imaging/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + CSRF_TOKEN + '\',flashTarget:this.closest(\'td\')})" ' +
                        'oninput="ClinicalOrdersKit.scheduleIdleUpdate(this, function(){ ClinicalOrdersKit.debouncedUpdate({url:\'/maternity-workbench/enrollment/' + enrollmentId + '/imaging/' + response.id + '/note\',payload:{note:this.value},csrfToken:\'' + CSRF_TOKEN + '\',flashTarget:this.closest(\'td\')}) }, 3000)">' +
                        '<input type="hidden" name="mco_imaging_id[]" value="' + id + '"></td>' +
                        '<td><button class="btn btn-sm btn-danger" onclick="MaternityClinicalOrders.removeAutoSavedRow(this,\'imaging\',' + response.id + ',' + id + ')"><span class="co-remove-btn"><i class="fa fa-times"></i></span></button></td>' +
                        '</tr>';
                },
                onSuccess: function(resp) {
                    initImagingHistory();
                }
            });
            $('#mco_imaging_search').val('');
            $('#mco_imaging_results').hide();
        }

        function addProcedureService(name, id, price) {
            if (ClinicalOrdersKit.isAlreadyAdded('procedures', parseInt(id))) {
                toastr.warning('Procedure already added');
                return;
            }
            var priority = $('#mco_proc_priority').val() || 'routine';
            var scheduledDate = $('#mco_proc_scheduled_date').val() || '';
            var preNotes = $('#mco_proc_notes').val() || '';
            var priorityClass = {
                routine: 'bg-success',
                urgent: 'bg-warning text-dark',
                emergency: 'bg-danger'
            } [priority] || 'bg-secondary';
            var priorityLabel = priority.charAt(0).toUpperCase() + priority.slice(1);

            ClinicalOrdersKit.addItem({
                url: wbUrl('/maternity-workbench/enrollment/' + enrollmentId + '/add-procedure'),
                payload: {
                    service_id: id,
                    priority: priority,
                    scheduled_date: scheduledDate,
                    pre_notes: preNotes
                },
                csrfToken: CSRF_TOKEN,
                tableSelector: '#mco-selected-procedures',
                type: 'procedures',
                referenceId: parseInt(id),
                buildRowHtml: function(resp) {
                    var isFreeForm = String(id).startsWith('FF_');
                    var nameHtml = isFreeForm ? '<h6 class="mb-0"><span class="badge bg-info text-dark">' + name.replace(' [Free-form]', '') + '</span></h6>' : '<strong>' + name + '</strong>';
                    var priceHtml = isFreeForm ? '<span class="text-muted">N/A</span>' : 'NGN ' + price;
                    return '<tr data-record-id="' + resp.id + '" data-record-type="procedure" data-service-id="' + id + '">' +
                        '<td>' + nameHtml +
                        (preNotes ? '<br><small class="text-info"><i class="fa fa-sticky-note"></i> ' + preNotes.substring(0, 60) + '</small>' : '') + '</td>' +
                        '<td>' + priceHtml + '</td>' +
                        '<td><span class="badge ' + priorityClass + '">' + priorityLabel + '</span>' +
                        (scheduledDate ? '<br><small>' + scheduledDate + '</small>' : '') + '</td>' +
                        '<td><button class="btn btn-sm btn-danger" onclick="MaternityClinicalOrders.removeAutoSavedRow(this,\'procedure\',' + resp.id + ',' + id + ')"><span class="co-remove-btn"><i class="fa fa-times"></i></span></button></td>' +
                        '</tr>';
                },
                onSuccess: function() {
                    initProcHistory();
                }
            });
            $('#mco_proc_search').val('');
            $('#mco_proc_results').hide();
        }

        // ===== AUTO-SAVE REMOVE (via ClinicalOrdersKit.removeItem) =====
        function removeAutoSavedRow(btn, type, recordId, serviceId) {
            var deleteUrl, tableSelector;

            if (type === 'lab') {
                deleteUrl = '/maternity-workbench/enrollment/' + enrollmentId + '/labs/' + recordId;
                tableSelector = '#mco-selected-labs';
            } else if (type === 'imaging') {
                deleteUrl = '/maternity-workbench/enrollment/' + enrollmentId + '/imaging/' + recordId;
                tableSelector = '#mco-selected-imaging';
            } else if (type === 'prescription') {
                deleteUrl = '/maternity-workbench/enrollment/' + enrollmentId + '/prescriptions/' + recordId;
                tableSelector = '#mco-selected-products';
            } else if (type === 'procedure') {
                deleteUrl = '/maternity-workbench/enrollment/' + enrollmentId + '/procedures/' + recordId;
                tableSelector = '#mco-selected-procedures';
            }

            var idsType = {
                lab: 'labs',
                imaging: 'imaging',
                prescription: 'meds',
                procedure: 'procedures'
            } [type] || type;

            ClinicalOrdersKit.removeItem({
                url: deleteUrl,
                csrfToken: CSRF_TOKEN,
                rowSelector: $(btn).closest('tr'),
                type: idsType,
                referenceId: serviceId ? parseInt(serviceId) : null,
                tableSelector: tableSelector
            });
        }

        function showMessage(containerId, msg, type) {
            var alertType = type === 'error' ? 'danger' : type;
            $('#' + containerId).html('<div class="alert alert-' + alertType + ' alert-dismissible fade show">' + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
            document.getElementById(containerId).scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
            setTimeout(function() {
                $('#' + containerId + ' .alert').alert('close');
            }, 5000);
        }

        function applyProductCombo(comboId, enrollmentId, comboName) {
            var comboData = (window.comboDataMap || {})[comboId] || {};
            var name = comboName || comboData.product_name || comboData.service_name || 'Combo';

            $('#mco_presc_search').val('');
            $('#mco_presc_results').hide();

            ComboConfirmModal.show({
                name        : name,
                bundleItems : comboData.bundle_items || [],
                price       : parseFloat(comboData.base_price || 0),
                payable     : parseFloat(comboData.payable_amount != null ? comboData.payable_amount : (comboData.base_price || 0)),
                claims      : parseFloat(comboData.claims_amount || 0),
                mode        : comboData.coverage_mode || null,
                onConfirm   : function() {
                    $.ajax({
                        url         : '/maternity-workbench/enrollment/' + enrollmentId + '/apply-combo',
                        method      : 'POST',
                        headers     : { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data        : JSON.stringify({ service_id: comboId, note: '' }),
                        contentType : 'application/json',
                        dataType    : 'json',
                        success     : function(response) {
                            if (response.success) {
                                toastr.success(response.message, 'Combo Applied');
                                if (typeof loadClinicalOrdersTab === 'function') { loadClinicalOrdersTab(); }
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

        function applyLabCombo(comboId, enrollmentId, comboName) {
            var comboData = (window.comboDataMap || {})[comboId] || {};
            var name = comboName || comboData.service_name || 'Combo';

            $('#mco_lab_search').val('');
            $('#mco_lab_results').hide();

            ComboConfirmModal.show({
                name        : name,
                bundleItems : comboData.bundle_items || [],
                price       : parseFloat(comboData.base_price || 0),
                payable     : parseFloat(comboData.payable_amount != null ? comboData.payable_amount : (comboData.base_price || 0)),
                claims      : parseFloat(comboData.claims_amount || 0),
                mode        : comboData.coverage_mode || null,
                onConfirm   : function() {
                    $.ajax({
                        url         : '/maternity-workbench/enrollment/' + enrollmentId + '/apply-combo',
                        method      : 'POST',
                        headers     : { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data        : JSON.stringify({ service_id: comboId, note: '' }),
                        contentType : 'application/json',
                        dataType    : 'json',
                        success     : function(response) {
                            if (response.success) {
                                toastr.success(response.message, 'Combo Applied');
                                if (typeof initMaternityLabsHistory === 'function') { initMaternityLabsHistory(); }
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

        function applyImagingCombo(comboId, enrollmentId, comboName) {
            var comboData = (window.comboDataMap || {})[comboId] || {};
            var name = comboName || comboData.service_name || 'Combo';

            $('#mco_imaging_search').val('');
            $('#mco_imaging_results').hide();

            ComboConfirmModal.show({
                name        : name,
                bundleItems : comboData.bundle_items || [],
                price       : parseFloat(comboData.base_price || 0),
                payable     : parseFloat(comboData.payable_amount != null ? comboData.payable_amount : (comboData.base_price || 0)),
                claims      : parseFloat(comboData.claims_amount || 0),
                mode        : comboData.coverage_mode || null,
                onConfirm   : function() {
                    $.ajax({
                        url         : '/maternity-workbench/enrollment/' + enrollmentId + '/apply-combo',
                        method      : 'POST',
                        headers     : { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data        : JSON.stringify({ service_id: comboId, note: '' }),
                        contentType : 'application/json',
                        dataType    : 'json',
                        success     : function(response) {
                            if (response.success) {
                                toastr.success(response.message, 'Combo Applied');
                                if (typeof initMaternityImagingHistory === 'function') { initMaternityImagingHistory(); }
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
            addProductService: addProductService,
            addLabService: addLabService,
            addImagingService: addImagingService,
            applyProductCombo: applyProductCombo,
            applyLabCombo: applyLabCombo,
            applyImagingCombo: applyImagingCombo,
            addProcedureService: addProcedureService,
            removeAutoSavedRow: removeAutoSavedRow,
            _searchBound: false
        };
    })();

    // ═══════════════════════════════════════════════════════════════
    // DELIVERY TAB
    // ═══════════════════════════════════════════════════════════════
    function loadDeliveryTab() {
        if (!currentEnrollmentId) {
            $('#delivery-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>');
            return;
        }

        const isBaby = isBabyContext();

        // Check if delivery record exists
        if (currentEnrollment && currentEnrollment.has_delivery) {
            // Load existing delivery
            $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}`, function(resp) {
                if (!resp.success || !resp.enrollment.delivery_record) {
                    if (isBaby) {
                        $('#delivery-content').html('<p class="text-muted text-center py-4">Delivery record not found for this enrollment</p>');
                    } else {
                        renderDeliveryForm();
                    }
                    return;
                }
                renderDeliveryDetails(resp.enrollment.delivery_record);
            });
        } else {
            if (isBaby) {
                $('#delivery-content').html('<p class="text-muted text-center py-4">Delivery record has not been recorded yet for this enrollment.</p>');
            } else {
                renderDeliveryForm();
            }
        }
    }

    function renderDeliveryForm() {
        destroyMaternityEditor('delivery_notes');
        destroyMaternityEditor('delivery_complications');
        
        // Define toggle helper globally
        window.toggleInductionMethod = function(elem) {
            const val = $(elem).val();
            if (val === '1') {
                $('#induction_method_container').slideDown(200);
            } else {
                $('#induction_method_container').slideUp(200);
                $('input[name="induction_method"]').val('');
            }
        };

        const html = `<div class="card-modern"><div class="card-header text-white" style="background: var(--success);"><h6 class="mb-0"><i class="mdi mdi-baby-carriage"></i> Record Delivery</h6></div><div class="card-body">
        <div class="mat-info-banner"><i class="mdi mdi-information"></i><div>Record the delivery outcome. All fields contribute to the patient\'s permanent delivery record. Fields marked <span class="text-danger">*</span> are required. After saving, register each baby separately in the Baby Records tab.</div></div>
        <form id="delivery-form">
            <div class="mat-form-section">
                <div class="mat-form-section-title"><i class="mdi mdi-clock"></i> Timing & Location</div>
                <div class="row">
                    <div class="col-md-3 mb-2"><label class="form-label">Delivery Date <span class="text-danger">*</span></label><input type="date" name="delivery_date" class="form-control" value="${new Date().toISOString().split('T')[0]}" required></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Delivery Time</label><input type="time" name="delivery_time" class="form-control"></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Place of Delivery <span class="mat-tooltip-icon" title="Where did the delivery occur?"><i class="mdi mdi-help-circle"></i></span></label><input type="text" name="place_of_delivery" class="form-control" placeholder="e.g. This Facility, Home, Transit"></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Number of Babies <span class="text-danger">*</span></label><input type="number" name="number_of_babies" class="form-control" value="1" min="1" max="8" placeholder="1" required></div>
                </div>
            </div>
            <div class="mat-form-section">
                <div class="mat-form-section-title"><i class="mdi mdi-medical-bag"></i> Labour Details</div>
                <div class="row">
                    <div class="col-md-3 mb-2"><label class="form-label">Type of Delivery <span class="text-danger">*</span> <span class="mat-tooltip-icon" title="SVD: Spontaneous Vaginal Delivery. CS: Caesarean Section. Vacuum/Forceps: Assisted vaginal delivery"><i class="mdi mdi-help-circle"></i></span></label><select name="type_of_delivery" class="form-select" required><option value="svd">SVD (Spontaneous Vaginal)</option><option value="assisted_vaginal">Assisted Vaginal</option><option value="elective_cs">Elective CS</option><option value="emergency_cs">Emergency CS</option><option value="vacuum">Vacuum Extraction</option><option value="forceps">Forceps Delivery</option></select></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Duration of Labour (hrs) <span class="mat-tooltip-icon" title="Total active labour duration in hours. Prolonged labour:>12hrs primigravida,>8hrs multigravida"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="duration_of_labour_hours" class="form-control" step="0.5" placeholder="e.g. 8.5"></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Estimated Blood Loss (ml) <span class="mat-tooltip-icon" title="Normal: SVD \u2264500ml, CS \u22641000ml.>500ml SVD or>1000ml CS = postpartum haemorrhage"><i class="mdi mdi-help-circle"></i></span></label><input type="number" name="blood_loss_ml" class="form-control" placeholder="e.g. 300"></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Oxytocin Given <span class="mat-tooltip-icon" title="Active management of third stage: Oxytocin 10 IU IM within 1 minute of delivery"><i class="mdi mdi-help-circle"></i></span></label><select name="oxytocin_given" class="form-select"><option value="1">Yes</option><option value="0">No</option></select></div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-3 mb-2"><label class="form-label">Induction</label><select name="induction" class="form-select" onchange="toggleInductionMethod(this)"><option value="0">No</option><option value="1">Yes</option></select></div>
                    <div class="col-md-3 mb-2" id="induction_method_container" style="display:none;"><label class="form-label">Induction Method</label><input type="text" name="induction_method" class="form-control" placeholder="e.g. Oxytocin, ARM"></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Augmentation</label><select name="augmentation" class="form-select"><option value="0">No</option><option value="1">Yes</option></select></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Anaesthesia Type</label><select name="anaesthesia_type" class="form-select"><option value="">None</option><option value="Local">Local</option><option value="Epidural">Epidural</option><option value="Spinal">Spinal</option><option value="General">General</option><option value="Other">Other</option></select></div>
                </div>
            </div>
            <div class="mat-form-section">
                <div class="mat-form-section-title"><i class="mdi mdi-clipboard-check"></i> Outcomes & Assessment</div>
                <div class="row">
                    <div class="col-md-3 mb-2"><label class="form-label">Placenta <span class="mat-tooltip-icon" title="Complete: all cotyledons and membranes accounted for. Incomplete: retained products — requires manual removal"><i class="mdi mdi-help-circle"></i></span></label><select name="placenta_complete" class="form-select"><option value="1">Complete</option><option value="0">Incomplete</option></select></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Placenta Notes</label><input type="text" name="placenta_notes" class="form-control" placeholder="e.g. membranes ragged"></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Perineal Tear <span class="mat-tooltip-icon" title="1st: mucosa only. 2nd: perineal muscles. 3rd: anal sphincter involved. 4th: rectal mucosa torn"><i class="mdi mdi-help-circle"></i></span></label><select name="perineal_tear_degree" class="form-select"><option value="">None</option><option value="1st">1st degree</option><option value="2nd">2nd degree</option><option value="3rd">3rd degree</option><option value="4th">4th degree</option></select></div>
                    <div class="col-md-3 mb-2"><label class="form-label">Episiotomy <span class="mat-tooltip-icon" title="Surgical incision of perineum to widen vaginal opening during delivery"><i class="mdi mdi-help-circle"></i></span></label><select name="episiotomy" class="form-select"><option value="none">None</option><option value="mediolateral">Yes, mediolateral</option><option value="median">Yes, midline</option></select></div>
                </div>
            </div>
            <div class="mat-form-section p-3 bg-light rounded border-start border-4 border-danger mt-4">
                <div class="d-flex align-items-center mb-2">
                    <i class="mdi mdi-alert-circle fs-4 text-danger me-2"></i>
                    <h5 class="mb-0 text-dark fw-bold">Complications</h5>
                </div>
                <div id="mat-delivery-complications-editor"></div>
                <div class="mt-2 text-muted small"><i class="mdi mdi-lightbulb-on-outline text-warning"></i> <b>Hint:</b> Document any complications: PPH, shoulder dystocia, cord prolapse, fetal distress, etc.</div>
            </div>
            <div class="mat-form-section p-3 bg-light rounded border-start border-4 border-info mt-3">
                <div class="d-flex align-items-center mb-2">
                    <i class="mdi mdi-clipboard-text fs-4 text-info me-2"></i>
                    <h5 class="mb-0 text-dark fw-bold">Delivery Notes (Global Sync)</h5>
                </div>
                <div class="alert alert-warning py-2 px-3 small mb-3">
                    <i class="mdi mdi-information-outline me-1"></i> <b>Critical Documentation:</b> These notes will be permanently synced to the patient's global encounter timeline.
                </div>
                <div id="mat-delivery-notes-editor"></div>
                <div class="mt-2 text-muted small"><i class="mdi mdi-lightbulb-on-outline text-warning"></i> <b>Hint:</b> Birth narrative, personnel present, interventions performed.</div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success btn-lg"><i class="mdi mdi-check"></i> Save Delivery Record</button>
            </div>
        </form></div></div>`;
        $('#delivery-content').html(html);
        initMaternityEditor('#mat-delivery-notes-editor', 'delivery_notes');
        initMaternityEditor('#mat-delivery-complications-editor', 'delivery_complications');

        $('#delivery-form').on('submit', function(e) {
            e.preventDefault();
            const data = {};
            $(this).serializeArray().forEach(f => data[f.name] = f.value);
            data.notes = getEditorData('delivery_notes', '#mat-delivery-notes-editor');
            data.complications = getEditorData('delivery_complications', '#mat-delivery-complications-editor');
            $.ajax({
                url: wbUrl(`/maternity-workbench/enrollment/${currentEnrollmentId}/delivery`),
                method: 'POST',
                data: data,
                headers: {
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                success: function(r) {
                    if (r.success) {
                        destroyMaternityEditor('delivery_notes');
                        destroyMaternityEditor('delivery_complications');
                        toastr.success(r.message);
                        currentEnrollment.has_delivery = true;
                        currentEnrollment.status = 'postnatal';
                        currentEnrollment.delivery_date = data.delivery_date || null;
                        loadDeliveryTab();
                        renderEnrollmentDetails();
                        populateOverviewTab(currentPatientData);
                        loadQueueCounts();
                    } else toastr.error(r.message);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to save');
                }
            });
        });
    }

    function renderDeliveryDetails(d) {
        const isBaby = isBabyContext();
        const actionsHtml = isBaby ? '' : `<div><button class="btn btn-sm btn-outline-light me-1" onclick="editDeliveryRecord(${d.id})" title="Edit"><i class="mdi mdi-pencil"></i> Edit</button><button class="btn btn-sm btn-outline-light me-1" onclick="confirmDeleteDeliveryRecord(${d.id})" title="Delete Delivery"><i class="mdi mdi-delete"></i> Delete</button></div>`;

        let html = '';
        if (isBaby) {
            html += `
            <div class="alert alert-info d-flex align-items-center gap-3 mb-3">
                <i class="mdi mdi-information" style="font-size:1.5rem;"></i>
                <div>
                    <strong>Mother's Delivery Record:</strong> You are viewing the delivery record of this baby's mother.
                </div>
            </div>`;
        }

        let episiotomyLabel = 'None';
        if (d.episiotomy === 'mediolateral') episiotomyLabel = 'Yes, mediolateral';
        else if (d.episiotomy === 'median') episiotomyLabel = 'Yes, midline';
        else if (d.episiotomy && d.episiotomy !== 'none') episiotomyLabel = d.episiotomy;

        html += `<div class="card-modern"><div class="card-header text-white d-flex justify-content-between" style="background: var(--success);"><h6 class="mb-0"><i class="mdi mdi-baby-carriage"></i> Delivery Record</h6><div>${actionsHtml}<span class="badge bg-light text-dark">${(d.type_of_delivery || '').toUpperCase()}</span></div></div><div class="card-body">
        <div class="row"><div class="col-md-6"><table class="table table-sm">
            <tr><td class="text-muted">Date</td><td>${d.delivery_date || 'N/A'}</td></tr>
            <tr><td class="text-muted">Time</td><td>${d.delivery_time || 'N/A'}</td></tr>
            <tr><td class="text-muted">Type</td><td class="fw-bold">${(d.type_of_delivery || '').toUpperCase()}</td></tr>
            <tr><td class="text-muted">Babies</td><td>${d.number_of_babies || 0}</td></tr>
            <tr><td class="text-muted">Duration</td><td>${d.duration_of_labour_hours ? d.duration_of_labour_hours + ' hrs' : 'N/A'}</td></tr>
            <tr><td class="text-muted">Induction</td><td>${d.induction ? 'Yes (' + (d.induction_method || 'N/A') + ')' : 'No'}</td></tr>
            <tr><td class="text-muted">Augmentation</td><td>${d.augmentation ? 'Yes' : 'No'}</td></tr>
        </table></div><div class="col-md-6"><table class="table table-sm">
            <tr><td class="text-muted">Blood Loss</td><td>${d.blood_loss_ml ? d.blood_loss_ml + ' ml' : 'N/A'}</td></tr>
            <tr><td class="text-muted">Placenta</td><td>${d.placenta_complete ? 'Complete' : 'Incomplete'} ${d.placenta_notes ? '(' + d.placenta_notes + ')' : ''}</td></tr>
            <tr><td class="text-muted">Perineal Tear</td><td>${d.perineal_tear_degree || 'None'}</td></tr>
            <tr><td class="text-muted">Episiotomy</td><td>${episiotomyLabel}</td></tr>
            <tr><td class="text-muted">Anaesthesia</td><td>${d.anaesthesia_type || 'None'}</td></tr>
            <tr><td class="text-muted">Place of Delivery</td><td>${d.place_of_delivery || 'N/A'}</td></tr>
            <tr><td class="text-muted">Complications</td><td>${d.complications || 'None'}</td></tr>
            <tr><td class="text-muted">Delivered By</td><td>${d.delivered_by_name || 'N/A'}</td></tr>
        </table></div></div>
        ${d.notes ? '<div class="alert alert-info mt-2 mb-0"><strong>Notes:</strong> ' + d.notes + '</div>' : ''}
    </div></div>
    <div class="card-modern mt-3">
        <div class="card-body py-2 d-flex align-items-center gap-2">
            <i class="mdi mdi-chart-timeline-variant text-success fs-5"></i>
            <span class="text-muted small">Partograph entries are recorded in the</span>
            <button class="btn btn-sm btn-outline-success py-0 px-2" onclick="switchWorkspaceTab('partograph')">
                <i class="mdi mdi-chart-timeline-variant"></i> Partograph Tab
            </button>
        </div>
    </div>`;
        window._deliveryRecordCache = d;
        $('#delivery-content').html(html);
    }

    function editDeliveryRecord(id) {
        const d = window._deliveryRecordCache;
        if (!d) {
            toastr.error('Delivery data not found');
            return;
        }
        // Re-render the delivery form pre-filled
        renderDeliveryForm();
        // Wait a tick for the form to render and editors to init, then pre-fill
        setTimeout(function() {
            const f = $('#delivery-form');
            // Parse dates from Eloquent serialization (ISO string or Y-m-d)
            const delivDate = d.delivery_date ? d.delivery_date.substring(0, 10) : '';
            const delivTime = d.delivery_time ? (d.delivery_time.length > 10 ? d.delivery_time.substring(11, 16) : d.delivery_time) : '';
            f.find('input[name="delivery_date"]').val(delivDate);
            f.find('input[name="delivery_time"]').val(delivTime);
            f.find('select[name="type_of_delivery"]').val(d.type_of_delivery || 'svd');
            f.find('input[name="number_of_babies"]').val(d.number_of_babies || 1);
            f.find('input[name="duration_of_labour_hours"]').val(d.duration_of_labour_hours || '');
            f.find('input[name="blood_loss_ml"]').val(d.blood_loss_ml || '');
            f.find('select[name="oxytocin_given"]').val(d.oxytocin_given ? '1' : '0');
            f.find('input[name="place_of_delivery"]').val(d.place_of_delivery || '');
            f.find('input[name="placenta_notes"]').val(d.placenta_notes || '');
            
            // Prefill new fields
            f.find('select[name="induction"]').val(d.induction ? '1' : '0');
            if (d.induction) {
                $('#induction_method_container').show();
                f.find('input[name="induction_method"]').val(d.induction_method || '');
            } else {
                $('#induction_method_container').hide();
                f.find('input[name="induction_method"]').val('');
            }
            f.find('select[name="augmentation"]').val(d.augmentation ? '1' : '0');
            f.find('select[name="anaesthesia_type"]').val(d.anaesthesia_type || '');

            f.find('select[name="placenta_complete"]').val(d.placenta_complete ? '1' : '0');
            f.find('select[name="perineal_tear_degree"]').val(d.perineal_tear_degree || '');
            f.find('select[name="episiotomy"]').val(d.episiotomy || 'none');
            // Set CKEditor data after init
            setTimeout(function() {
                if (MaternityEditors['delivery_complications'] && d.complications) MaternityEditors['delivery_complications'].setData(d.complications);
                if (MaternityEditors['delivery_notes'] && d.notes) MaternityEditors['delivery_notes'].setData(d.notes);
            }, 500);
            // Change submit button text
            f.find('button[type="submit"]').html('<i class="mdi mdi-check"></i> Update Delivery Record');
            // Override form submit to use PUT
            f.off('submit').on('submit', function(e) {
                e.preventDefault();
                const data = {};
                $(this).serializeArray().forEach(fd => data[fd.name] = fd.value);
                data.notes = getEditorData('delivery_notes', '#mat-delivery-notes-editor');
                data.complications = getEditorData('delivery_complications', '#mat-delivery-complications-editor');
                $.ajax({
                    url: wbUrl(`/maternity-workbench/delivery/${id}`),
                    method: 'PUT',
                    data: data,
                    headers: {
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    success: function(r) {
                        if (r.success) {
                            destroyMaternityEditor('delivery_notes');
                            destroyMaternityEditor('delivery_complications');
                            toastr.success(r.message || 'Delivery record updated');
                            loadDeliveryTab();
                        } else toastr.error(r.message);
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Failed to update');
                    }
                });
            });
        }, 300);
    }

    // ═══════════════════════════════════════════════════════════════
    // BABY RECORDS TAB
    // ═══════════════════════════════════════════════════════════════
    function loadBabyTab() {
        if (!currentEnrollmentId) {
            $('#baby-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>');
            return;
        }
        if (!currentEnrollment || !currentEnrollment.has_delivery) {
            $('#baby-content').html('<p class="text-muted text-center py-3">Record delivery first</p>');
            return;
        }

        const isBaby = isBabyContext();

        $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}`, function(resp) {
            if (!resp.success) return;
            const babies = resp.enrollment.babies || [];
            window._babiesCache = babies; // cache for edit

            let html = `<div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="mdi mdi-baby-face-outline"></i> ${isBaby ? 'Growth & Birth Details' : 'Baby Records (' + babies.length + ')'}</h5>
            ${!isBaby ? `<button class="btn text-white" style="background: var(--maternity-pink);" onclick="showRegisterBabyForm()"><i class="mdi mdi-plus"></i> Register Baby</button>` : ''}
        </div>`;

            if (babies.length === 0) {
                html += '<p class="text-muted text-center py-4">No babies registered yet</p>';
            } else {
                babies.forEach(function(b) {
                    // In baby context, we only show THIS specific baby
                    if (isBaby && b.patient_id != currentPatient) return;

                    const patientName = b.patient && b.patient.user ? (b.patient.user.surname + ' ' + b.patient.user.firstname) : 'Baby';
                    const isStillBirth = b.is_still_birth ? '<span class="badge bg-danger ms-2">Still Birth</span>' : '';
                    const deathBtn = b.status === 'alive' ? `<button class="btn btn-sm btn-outline-danger" onclick="showMarkDeceasedModal(${b.id})"><i class="mdi mdi-account-remove"></i> Mark Deceased</button>` : '';

                    html += `<div class="baby-card ${b.status === 'deceased' ? 'bg-light' : ''}">
                    <div class="baby-header">
                        <div class="baby-name">${patientName} ${isStillBirth}</div>
                        <div class="d-flex align-items-center gap-2">
                            ${!isBaby ? `<button class="btn btn-sm btn-outline-primary py-0 px-1" onclick="editBaby(${b.id})" title="Edit baby record"><i class="mdi mdi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="confirmDeleteBaby(${b.id})" title="Delete baby record"><i class="mdi mdi-delete"></i></button>` : ''}
                            <span class="baby-sex ${b.sex}">${b.sex === 'male' ? '♂ Male' : (b.sex === 'female' ? '♀ Female' : '? Ambiguous')}</span>
                        </div>
                    </div>
                    <div class="baby-metrics">
                        <div><div class="baby-metric-label">Birth Weight</div><div class="baby-metric-value">${b.birth_weight_kg ? b.birth_weight_kg + ' kg' : '-'}</div></div>
                        <div><div class="baby-metric-label">APGAR 1/5/10</div><div class="baby-metric-value">${b.apgar_1_min ?? '-'}/${b.apgar_5_min ?? '-'}/${b.apgar_10_min ?? '-'}</div></div>
                        <div><div class="baby-metric-label">Status</div><div class="baby-metric-value"><span class="badge badge-${b.status === 'alive' ? 'success' : (b.status === 'deceased' ? 'danger' : 'warning')}">${b.status || '-'}</span></div></div>
                        ${b.deceased_at ? `<div class="col-12 mt-1 small text-danger"><b>Deceased:</b> ${b.deceased_at} - ${b.cause_of_death || 'Unknown'}</div>` : ''}
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="loadGrowthChart(${b.id})"><i class="mdi mdi-chart-line"></i> Growth Chart</button>
                        <button class="btn btn-sm btn-outline-success" onclick="showGrowthRecordForm(${b.id})"><i class="mdi mdi-plus"></i> Add Growth</button>
                        ${!isBaby ? deathBtn : ''}
                    </div>
                    <div id="growth-chart-${b.id}" class="mt-2" ${isBaby ? 'style="display:block;"' : ''}></div>
                </div>`;
                });
            }
            $('#baby-content').html(html);

            // Auto-load growth chart in baby context
            if (isBaby) {
                const currentBaby = babies.find(b => b.patient_id == currentPatient);
                if (currentBaby) {
                    setTimeout(() => loadGrowthChart(currentBaby.id), 100);
                }
            }
        });
    }

    function showMarkDeceasedModal(id) {
        $('#deceased-baby-id').val(id);
        $('#mark-baby-deceased-form')[0].reset();
        $('#markBabyDeceasedModal').modal('show');
    }

    $('#btn-confirm-baby-death').on('click', function() {
        const id = $('#deceased-baby-id').val();
        const data = $('#mark-baby-deceased-form').serialize();
        $.post(`/maternity-workbench/baby/${id}/mark-deceased`, data, function(resp) {
            if (resp.success) {
                toastr.success(resp.message);
                $('#markBabyDeceasedModal').modal('hide');
                loadBabyTab();
            } else toastr.error(resp.message);
        });
    });

    function showRegisterBabyForm() {
        _editMode = null;
        _editId = null;
        const form = $('#registerBabyModal #register-baby-form')[0];
        if (form) form.reset();
        // Uncheck all checkboxes
        $('#registerBabyModal input[type="checkbox"]').prop('checked', false);
        $('#registerBabyModalLabel').html('<i class="mdi mdi-baby-face-outline"></i> Register Baby');
        $('#btn-save-baby').html('<i class="mdi mdi-check"></i> Register Baby');
        $('#registerBabyModal').modal('show');
    }

    function editBaby(id) {
        const b = (window._babiesCache || []).find(x => x.id === id);
        if (!b) {
            toastr.error('Baby data not found');
            return;
        }
        _editMode = 'baby';
        _editId = id;
        const form = $('#registerBabyModal #register-baby-form')[0];
        if (form) form.reset();
        $('#registerBabyModalLabel').html('<i class="mdi mdi-pencil"></i> Edit Baby Record');
        $('#btn-save-baby').html('<i class="mdi mdi-check"></i> Update Baby');
        // Pre-fill
        const m = $('#registerBabyModal');
        const u = b.patient && b.patient.user ? b.patient.user : {};
        m.find('input[name="baby_surname"]').val(u.surname || '');
        m.find('input[name="baby_firstname"]').val(u.firstname || '');
        m.find('select[name="sex"]').val(b.sex || '');
        m.find('input[name="is_still_birth"]').prop('checked', b.is_still_birth ? true : false);
        m.find('input[name="birth_weight_kg"]').val(b.birth_weight_kg || '');
        m.find('input[name="length_cm"]').val(b.length_cm || '');
        m.find('input[name="head_circumference_cm"]').val(b.head_circumference_cm || '');
        m.find('input[name="apgar_1_min"]').val(b.apgar_1_min ?? '');
        m.find('input[name="apgar_5_min"]').val(b.apgar_5_min ?? '');
        m.find('input[name="apgar_10_min"]').val(b.apgar_10_min ?? '');
        m.find('select[name="feeding_method"]').val(b.feeding_method || 'exclusive_breastfeeding');
        ['bcg_given', 'opv0_given', 'hbv0_given', 'vitamin_k_given', 'eye_prophylaxis'].forEach(cb => {
            m.find('input[name="' + cb + '"]').prop('checked', !!b[cb]);
        });
        m.modal('show');
    }

    // Register Baby modal save handler
    $(document).on('click', '#btn-save-baby', function() {
        const form = $('#registerBabyModal #register-baby-form');
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        const data = {};
        form.serializeArray().forEach(f => data[f.name] = f.value);
        ['bcg_given', 'opv0_given', 'hbv0_given', 'vitamin_k_given', 'eye_prophylaxis', 'is_still_birth'].forEach(cb => {
            data[cb] = $('#registerBabyModal input[name="' + cb + '"]').is(':checked') ? 1 : 0;
        });
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');
        const isEdit = _editMode === 'baby' && _editId;
        const url = isEdit ? `/maternity-workbench/baby/${_editId}` : `/maternity-workbench/enrollment/${currentEnrollmentId}/baby`;
        const method = isEdit ? 'PUT' : 'POST';
        $.ajax({
            url: url,
            method: method,
            data: data,
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            success: function(r) {
                btn.prop('disabled', false).html(isEdit ? '<i class="mdi mdi-check"></i> Update Baby' : '<i class="mdi mdi-check"></i> Register Baby');
                if (r.success) {
                    _editMode = null;
                    _editId = null;
                    $('#registerBabyModal').modal('hide');
                    toastr.success(r.message);
                    loadBabyTab();
                } else toastr.error(r.message);
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Register Baby');
                toastr.error(xhr.responseJSON?.message || 'Failed to register');
            }
        });
    });

    function showGrowthRecordForm(babyId) {
        const form = $('#addGrowthModal #growth-record-form')[0];
        if (form) form.reset();
        $('#growth-baby-id').val(babyId);
        $('#addGrowthModal input[name="record_date"]').val(new Date().toISOString().split('T')[0]);
        $('#addGrowthModal').modal('show');
    }

    // Growth Record modal save handler
    $(document).on('click', '#btn-save-growth', function() {
        const form = $('#addGrowthModal #growth-record-form');
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        const bid = $('#growth-baby-id').val();
        const data = {};
        form.serializeArray().forEach(f => {
            if (f.name !== 'baby_id') data[f.name] = f.value;
        });
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');
        $.ajax({
            url: wbUrl(`/maternity-workbench/baby/${bid}/growth`),
            method: 'POST',
            data: data,
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            success: function(r) {
                btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save');
                if (r.success) {
                    $('#addGrowthModal').modal('hide');
                    toastr.success(r.message);
                    loadBabyTab();
                } else toastr.error(r.message);
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save');
                toastr.error('Failed to save');
            }
        });
    });

    function loadGrowthChart(babyId) {
        const container = $(`#growth-chart-${babyId}`);
        container.html('<div class="text-center text-muted py-2"><i class="mdi mdi-loading mdi-spin"></i> Loading growth charts...</div>');

        $.get(`/maternity-workbench/baby/${babyId}/growth-chart`, function(resp) {
            if (!resp.success || !resp.data || resp.data.length === 0) {
                container.html('<p class="text-muted small py-2"><i class="mdi mdi-information"></i> No growth records yet. Add a growth record to see WHO growth curves.</p>');
                return;
            }

            const sex = resp.sex;
            const sexLabel = sex === 'F' ? 'Girls' : 'Boys';
            const sexColor = sex === 'F' ? '#d63384' : '#0d6efd';
            const data = resp.data;
            const who = resp.who_reference;

            // Build tabbed chart view
            container.html(`
            <ul class="nav nav-tabs nav-tabs-sm mt-2" id="growth-tabs-${babyId}">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#gc-weight-${babyId}" style="font-size:0.78rem; padding: 4px 10px;">Weight-for-Age</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#gc-length-${babyId}" style="font-size:0.78rem; padding: 4px 10px;">Length-for-Age</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#gc-hc-${babyId}" style="font-size:0.78rem; padding: 4px 10px;">Head Circumference</a></li>
            </ul>
            <div class="tab-content border border-top-0 rounded-bottom p-2">
                <div class="tab-pane active" id="gc-weight-${babyId}">
                    <div class="small text-muted mb-1"><i class="mdi mdi-information"></i> WHO Weight-for-Age (${sexLabel}) — Shaded zones: <span class="text-danger">severe</span>, <span class="text-warning">moderate</span>, <span class="text-success">normal</span></div>
                    <div style="position:relative; height:280px;"><canvas id="gc-wfa-canvas-${babyId}"></canvas></div>
                </div>
                <div class="tab-pane" id="gc-length-${babyId}">
                    <div class="small text-muted mb-1"><i class="mdi mdi-information"></i> WHO Length/Height-for-Age (${sexLabel})</div>
                    <div style="position:relative; height:280px;"><canvas id="gc-lfa-canvas-${babyId}"></canvas></div>
                </div>
                <div class="tab-pane" id="gc-hc-${babyId}">
                    <div class="small text-muted mb-1"><i class="mdi mdi-information"></i> WHO Head Circumference-for-Age (${sexLabel})</div>
                    <div style="position:relative; height:280px;"><canvas id="gc-hc-canvas-${babyId}"></canvas></div>
                </div>
            </div>
            <div class="table-responsive mt-2">
                <table class="table table-sm table-bordered" style="font-size:0.8rem;">
                    <thead class="table-light"><tr><th>Date</th><th>Age (mo)</th><th>Weight (kg)</th><th>WAZ</th><th>Length (cm)</th><th>LAZ</th><th>Head (cm)</th><th>Status</th></tr></thead>
                    <tbody>${data.map(r => {
                        const statusBadge = r.nutritional_status === 'normal' ? 'bg-success' :
                            (r.nutritional_status && r.nutritional_status.includes('severe') ? 'bg-danger' : 'bg-warning text-dark');
                        return '<tr>' +
                            '<td>' + (r.record_date || '-') + '</td>' +
                            '<td>' + (r.age_months ? parseFloat(r.age_months).toFixed(1) : '-') + '</td>' +
                            '<td>' + (r.weight_kg || '-') + '</td>' +
                            '<td>' + (r.weight_for_age_z ? parseFloat(r.weight_for_age_z).toFixed(2) : '-') + '</td>' +
                            '<td>' + (r.length_height_cm || '-') + '</td>' +
                            '<td>' + (r.length_for_age_z ? parseFloat(r.length_for_age_z).toFixed(2) : '-') + '</td>' +
                            '<td>' + (r.head_circumference_cm || '-') + '</td>' +
                            '<td><span class="badge ' + statusBadge + '">' + (r.nutritional_status || '-').replace(/_/g, ' ') + '</span></td>' +
                        '</tr>';
                    }).join('')}</tbody>
                </table>
            </div>
        `);

            // Render each chart
            if (typeof Chart !== 'undefined') {
                renderWhoGrowthChart(`gc-wfa-canvas-${babyId}`, who.weight_for_age, data, 'weight_kg', 'Weight (kg)', sexColor, babyId + '-wfa');
                renderWhoGrowthChart(`gc-lfa-canvas-${babyId}`, who.length_for_age, data, 'length_height_cm', 'Length/Height (cm)', sexColor, babyId + '-lfa');
                renderWhoGrowthChart(`gc-hc-canvas-${babyId}`, who.head_circumference, data, 'head_circumference_cm', 'Head Circ (cm)', sexColor, babyId + '-hc');

                // Lazy-render on tab switch (Chart.js needs visible canvas)
                $(`#growth-tabs-${babyId} a[data-bs-toggle="tab"]`).on('shown.bs.tab', function() {
                    const target = $(this).attr('href');
                    if (target.includes('length') && window['_gcChart_' + babyId + '-lfa']) window['_gcChart_' + babyId + '-lfa'].resize();
                    if (target.includes('hc') && window['_gcChart_' + babyId + '-hc']) window['_gcChart_' + babyId + '-hc'].resize();
                });
            }
        }).fail(function() {
            container.html('<div class="alert alert-danger small mb-0">Failed to load growth chart data.</div>');
        });
    }

    function renderWhoGrowthChart(canvasId, whoData, childData, measureField, yLabel, childColor, cacheKey) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !whoData || whoData.length === 0) return;

        if (window['_gcChart_' + cacheKey]) window['_gcChart_' + cacheKey].destroy();

        // WHO reference bands
        const months = whoData.map(w => w.month);
        const sd3n = whoData.map(w => w.sd_neg3);
        const sd2n = whoData.map(w => w.sd_neg2);
        const sd1n = whoData.map(w => w.sd_neg1);
        const median = whoData.map(w => w.median);
        const sd1p = whoData.map(w => w.sd_pos1);
        const sd2p = whoData.map(w => w.sd_pos2);
        const sd3p = whoData.map(w => w.sd_pos3);

        // Child data points
        const childPts = [];
        childData.forEach(record => {
            const age = record.age_months ? parseFloat(record.age_months) : null;
            const val = record[measureField] ? parseFloat(record[measureField]) : null;
            if (age !== null && val !== null) childPts.push({
                x: age,
                y: val
            });
        });

        const datasets = [
            // WHO reference bands (filled)
            {
                label: '-3 SD',
                data: sd3n,
                borderColor: 'rgba(220,53,69,0.4)',
                borderWidth: 1,
                pointRadius: 0,
                fill: false,
                borderDash: [2, 2]
            },
            {
                label: '-2 SD',
                data: sd2n,
                borderColor: 'rgba(255,152,0,0.5)',
                borderWidth: 1,
                pointRadius: 0,
                fill: {
                    target: 0,
                    above: 'rgba(255,152,0,0.08)'
                }
            },
            {
                label: '-1 SD',
                data: sd1n,
                borderColor: 'rgba(76,175,80,0.4)',
                borderWidth: 1,
                pointRadius: 0,
                fill: {
                    target: 1,
                    above: 'rgba(255,193,7,0.06)'
                }
            },
            {
                label: 'Median',
                data: median,
                borderColor: '#198754',
                borderWidth: 2,
                pointRadius: 0,
                fill: {
                    target: 2,
                    above: 'rgba(76,175,80,0.08)'
                }
            },
            {
                label: '+1 SD',
                data: sd1p,
                borderColor: 'rgba(76,175,80,0.4)',
                borderWidth: 1,
                pointRadius: 0,
                fill: {
                    target: 3,
                    above: 'rgba(76,175,80,0.08)'
                }
            },
            {
                label: '+2 SD',
                data: sd2p,
                borderColor: 'rgba(255,152,0,0.5)',
                borderWidth: 1,
                pointRadius: 0,
                fill: {
                    target: 4,
                    above: 'rgba(255,193,7,0.06)'
                }
            },
            {
                label: '+3 SD',
                data: sd3p,
                borderColor: 'rgba(220,53,69,0.4)',
                borderWidth: 1,
                pointRadius: 0,
                fill: {
                    target: 5,
                    above: 'rgba(255,152,0,0.08)'
                }
            },
        ];

        // Child measurement line (scatter + line)
        if (childPts.length> 0) {
            datasets.push({
                label: 'Child',
                data: childPts,
                borderColor: childColor,
                backgroundColor: childColor,
                pointRadius: 6,
                pointBackgroundColor: childColor,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                borderWidth: 2.5,
                showLine: true,
                tension: 0.2,
                type: 'scatter',
                order: 0
            });
        }

        window['_gcChart_' + cacheKey] = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: months,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            usePointStyle: true,
                            padding: 6,
                            font: {
                                size: 9
                            },
                            filter: item => ['Child', 'Median', '-2 SD', '+2 SD', '-3 SD', '+3 SD'].includes(item.text)
                        }
                    },
                    tooltip: {
                        mode: 'nearest',
                        intersect: true
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Age (months)'
                        },
                        min: 0,
                        max: 60,
                        ticks: {
                            stepSize: 6
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: yLabel
                        }
                    }
                }
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // POSTNATAL TAB
    // ═══════════════════════════════════════════════════════════════
    function loadPostnatalTab() {
        if (!currentEnrollmentId) {
            $('#postnatal-content').html('<p class="text-muted text-center py-3">Patient not enrolled</p>');
            return;
        }

        const isBaby = isBabyContext();

        $.get(`/maternity-workbench/enrollment/${currentEnrollmentId}/postnatal`, function(resp) {
            if (!resp.success) return;
            _postnatalVisitsCache = resp.visits; // cache for edit

            let html = '';
            if (isBaby) {
                html += `
                <div class="alert alert-info d-flex align-items-center gap-3 mb-3">
                    <i class="mdi mdi-information" style="font-size:1.5rem;"></i>
                    <div>
                        <strong>Mother's Postnatal Visits:</strong> You are viewing the postnatal records of this baby's mother.
                    </div>
                </div>`;
            }

            html += `<div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="mdi mdi-account-heart"></i> Postnatal Visits (${resp.visits.length})</h5>
            ${!isBaby ? `<button class="btn text-white" style="background: var(--maternity-pink);" onclick="showPostnatalForm()"><i class="mdi mdi-plus"></i> New Visit</button>` : ''}
        </div>`;

            if (resp.visits.length === 0) {
                html += '<p class="text-muted text-center py-4">No postnatal visits recorded</p>';
            } else {
                resp.visits.forEach(function(v) {
                    let actions = '';
                    if (!isBaby) {
                        actions = `<button class="btn btn-sm btn-outline-info py-0 px-1" onclick="editPostnatalVisit(${v.id})" title="Edit visit"><i class="mdi mdi-pencil"></i></button>
                                   <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="confirmDeletePostnatalVisit(${v.id})" title="Delete visit"><i class="mdi mdi-delete"></i></button>`;
                    }

                    html += `<div class="anc-visit-card shadow-sm border-0 mb-3" style="border-left: 4px solid var(--info) !important; border-radius: 8px;">
                    <div class="d-flex justify-content-between mb-2">
                        <div><span class="visit-number" style="color: var(--info); font-weight: bold;">${v.visit_type_label}</span></div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="visit-date text-muted"><i class="mdi mdi-calendar"></i> ${v.visit_date} (${v.days_postpartum || '?'}d postpartum)</span>
                            ${actions}
                        </div>
                    </div>

                    <div class="row small mb-2 bg-light p-2 rounded mx-0">
                        <div class="col-md-3 mb-1"><strong>Condition:</strong> ${v.general_condition || '-'}</div>
                        <div class="col-md-3 mb-1"><strong>BP:</strong> ${v.blood_pressure || '-'}</div>
                        <div class="col-md-3 mb-1"><strong>Temp:</strong> ${v.temperature_c ? v.temperature_c + ' °C' : '-'}</div>
                        <div class="col-md-3 mb-1"><strong>Lochia:</strong> ${v.lochia || '-'}</div>
                    </div>

                    <div class="row small mb-2 mx-0 px-2">
                        <div class="col-md-3 mb-1"><span class="text-muted">Uterus:</span> <span class="fw-medium">${v.uterus_assessment || '-'}</span></div>
                        <div class="col-md-3 mb-1"><span class="text-muted">Wound:</span> <span class="fw-medium">${v.wound_assessment || '-'}</span></div>
                        <div class="col-md-3 mb-1"><span class="text-muted">Breast:</span> <span class="fw-medium">${v.breast_assessment || '-'}</span></div>
                        <div class="col-md-3 mb-1"><span class="text-muted">Wellbeing:</span> <span class="fw-medium">${v.emotional_wellbeing ? v.emotional_wellbeing.replace('_', ' ') : '-'}</span></div>
                    </div>

                    <div class="row small mb-2 mx-0 px-2">
                        <div class="col-md-3 mb-1"><span class="text-muted">Baby Wt:</span> <span class="fw-medium">${v.baby_weight_kg ? v.baby_weight_kg + ' kg' : '-'}</span></div>
                        <div class="col-md-3 mb-1"><span class="text-muted">Feeding:</span> <span class="fw-medium">${v.baby_feeding || '-'}</span></div>
                        <div class="col-md-3 mb-1"><span class="text-muted">Cord:</span> <span class="fw-medium">${v.cord_status || '-'}</span></div>
                        <div class="col-md-3 mb-1"><span class="text-muted">Jaundice:</span> <span class="fw-medium">${v.jaundice ? 'Yes' : 'No'}</span></div>
                    </div>

                    <div class="row small mb-2 mx-0 px-2">
                        <div class="col-md-6 mb-1"><span class="text-muted">FP Counselled:</span> <span class="fw-medium">${v.family_planning_counselled ? 'Yes' : 'No'} (${v.family_planning_method || '-'})</span></div>
                        <div class="col-md-6 mb-1"><span class="text-muted">Next Appt:</span> <span class="fw-medium">${v.next_appointment || '-'}</span></div>
                    </div>

                    ${v.clinical_notes ? '<div class="mt-2 small text-muted px-2 border-top pt-2"><i class="mdi mdi-note-text-outline text-info"></i> <strong>Notes:</strong> ' + v.clinical_notes + '</div>' : ''}
                    <div class="mt-1 small text-muted px-2 pb-2"><em>Seen by: ${v.seen_by}</em></div>
                </div>`;
                });
            }
            $('#postnatal-content').html(html);
        });
    }

    function showPostnatalForm() {
        _editMode = null;
        _editId = null;
        destroyMaternityEditor('postnatal_notes');
        const form = $('#postnatalModal #postnatal-form')[0];
        if (form) form.reset();
        $('#postnatalModalLabel').html('<i class="mdi mdi-account-heart"></i> Record Postnatal Visit');
        $('#btn-save-postnatal').html('<i class="mdi mdi-check"></i> Save Visit');
        $('#postnatalModal input[name="visit_date"]').val(new Date().toISOString().split('T')[0]);

        // Init CKEditor after modal is fully visible
        $('#postnatalModal').off('shown.bs.modal.pnEditor').on('shown.bs.modal.pnEditor', function() {
            initMaternityEditor('#mat-postnatal-notes-editor-modal', 'postnatal_notes');
        });
        // Destroy CKEditor when modal hides
        $('#postnatalModal').off('hidden.bs.modal.pnEditor').on('hidden.bs.modal.pnEditor', function() {
            destroyMaternityEditor('postnatal_notes');
        });

        $('#postnatalModal').modal('show');
    }

    function editPostnatalVisit(id) {
        const v = _postnatalVisitsCache.find(x => x.id === id);
        if (!v) {
            toastr.error('Visit data not found');
            return;
        }
        _editMode = 'postnatal';
        _editId = id;
        destroyMaternityEditor('postnatal_notes');
        const form = $('#postnatalModal #postnatal-form')[0];
        if (form) form.reset();
        $('#postnatalModalLabel').html('<i class="mdi mdi-pencil"></i> Edit Postnatal Visit');
        $('#btn-save-postnatal').html('<i class="mdi mdi-check"></i> Update Visit');
        // Pre-fill form fields
        const m = $('#postnatalModal');
        m.find('select[name="visit_type"]').val(v.visit_type || '');
        m.find('input[name="visit_date"]').val(v.visit_date_raw || '');
        m.find('input[name="next_appointment"]').val(v.next_appointment_raw || '');
        m.find('select[name="general_condition"]').val(v.general_condition || '');
        m.find('input[name="blood_pressure"]').val(v.blood_pressure || '');
        m.find('input[name="temperature_c"]').val(v.temperature_c || '');
        m.find('select[name="lochia"]').val(v.lochia || '');
        m.find('input[name="uterus_assessment"]').val(v.uterus_assessment || '');
        m.find('input[name="wound_assessment"]').val(v.wound_assessment || '');
        m.find('input[name="breast_assessment"]').val(v.breast_assessment || '');
        m.find('select[name="emotional_wellbeing"]').val(v.emotional_wellbeing || '');
        m.find('input[name="emotional_notes"]').val(v.emotional_notes || '');
        m.find('input[name="baby_general_condition"]').val(v.baby_general_condition || '');
        m.find('input[name="baby_weight_kg"]').val(v.baby_weight_kg || '');
        m.find('select[name="baby_feeding"]').val(v.baby_feeding || '');
        m.find('select[name="breastfeeding_support"]').val(v.breastfeeding_support || '');
        m.find('select[name="cord_status"]').val(v.cord_status || '');
        m.find('select[name="jaundice"]').val(v.jaundice ? '1' : '0');
        m.find('input[name="baby_notes"]').val(v.baby_notes || '');
        m.find('select[name="family_planning_counselled"]').val(v.family_planning_counselled ? '1' : '0');
        m.find('input[name="family_planning_method"]').val(v.family_planning_method || '');
        // CKEditor
        m.off('shown.bs.modal.pnEditor').on('shown.bs.modal.pnEditor', function() {
            initMaternityEditor('#mat-postnatal-notes-editor-modal', 'postnatal_notes').then(function(editor) {
                if (editor && v.clinical_notes) editor.setData(v.clinical_notes);
            });
        });
        m.off('hidden.bs.modal.pnEditor').on('hidden.bs.modal.pnEditor', function() {
            destroyMaternityEditor('postnatal_notes');
        });
        m.modal('show');
    }

    // Postnatal modal save handler
    $(document).on('click', '#btn-save-postnatal', function() {
        const form = $('#postnatalModal #postnatal-form');
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        const data = {};
        form.serializeArray().forEach(f => data[f.name] = f.value);
        data.clinical_notes = getEditorData('postnatal_notes', '#mat-postnatal-notes-editor-modal');
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');
        const isEdit = _editMode === 'postnatal' && _editId;
        const url = isEdit ? `/maternity-workbench/postnatal/${_editId}` : `/maternity-workbench/enrollment/${currentEnrollmentId}/postnatal`;
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
                    destroyMaternityEditor('postnatal_notes');
                    $('#postnatalModal').modal('hide');
                    toastr.success(r.message);
                    loadPostnatalTab();
                } else toastr.error(r.message);
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Save Visit');
                toastr.error(xhr.responseJSON?.message || 'Failed to save');
            }
        });
    });

