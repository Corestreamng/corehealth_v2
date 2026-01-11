{{-- Clinical Context Modal - Shared between Lab and Imaging Workbenches --}}
<div class="modal fade" id="clinical-context-modal" tabindex="-1" role="dialog" aria-labelledby="clinicalContextModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: {{ appsettings('hos_color', '#007bff') }}; color: white;">
                <h5 class="modal-title" id="clinicalContextModalLabel">
                    <i class="fa fa-heartbeat"></i> Clinical Context
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                {{-- Tabs for different clinical data --}}
                <ul class="nav nav-tabs" id="clinical-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="vitals-tab-btn" data-bs-toggle="tab" data-bs-target="#vitals-tab" type="button" role="tab">
                            <i class="mdi mdi-heart-pulse"></i> Vitals
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="enc-notes-tab-btn" data-bs-toggle="tab" data-bs-target="#enc-notes-tab" type="button" role="tab">
                            <i class="mdi mdi-note-text"></i> Encounter Notes
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="meds-tab-btn" data-bs-toggle="tab" data-bs-target="#meds-tab" type="button" role="tab">
                            <i class="mdi mdi-pill"></i> Medications
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="allergies-tab-btn" data-bs-toggle="tab" data-bs-target="#allergies-tab" type="button" role="tab">
                            <i class="mdi mdi-alert-circle"></i> Allergies
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="inj-imm-tab-btn" data-bs-toggle="tab" data-bs-target="#inj-imm-tab" type="button" role="tab">
                            <i class="mdi mdi-needle"></i> Inj/Imm History
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="clinical-tab-content">
                    {{-- Vitals Tab --}}
                    <div class="tab-pane fade show active" id="vitals-tab" role="tabpanel">
                        <div class="clinical-tab-header">
                            <h6><i class="mdi mdi-heart-pulse"></i> Recent Vital Signs</h6>
                            <button class="btn btn-sm btn-outline-primary refresh-clinical-btn" data-panel="vitals">
                                <i class="mdi mdi-refresh"></i> Refresh
                            </button>
                        </div>
                        <div class="clinical-tab-body" id="vitals-panel-body">
                            <div class="table-responsive">
                                <table class="table" id="vitals-table" style="width: 100%">
                                    <thead>
                                        <tr>
                                            <th>Vital Signs History</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Encounter Notes Tab --}}
                    <div class="tab-pane fade" id="enc-notes-tab" role="tabpanel">
                        <div class="clinical-tab-header">
                            <h6><i class="mdi mdi-note-text"></i> Encounter Notes</h6>
                            <button class="btn btn-sm btn-outline-primary refresh-clinical-btn" data-panel="enc-notes">
                                <i class="mdi mdi-refresh"></i> Refresh
                            </button>
                        </div>
                        <div class="clinical-tab-body" id="enc-notes-panel-body">
                            <div id="clinical-enc-notes-container">
                                <div class="text-center py-4">
                                    <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                                    <p class="text-muted mt-2">Loading encounter notes...</p>
                                </div>
                            </div>
                            <div id="clinical-enc-notes-show-all" class="text-center mt-3"></div>
                        </div>
                    </div>

                    {{-- Medications Tab --}}
                    <div class="tab-pane fade" id="meds-tab" role="tabpanel">
                        <div class="clinical-tab-header">
                            <h6><i class="mdi mdi-pill"></i> Prescription History</h6>
                            <button class="btn btn-sm btn-outline-primary refresh-clinical-btn" data-panel="medications">
                                <i class="mdi mdi-refresh"></i> Refresh
                            </button>
                        </div>
                        <div class="clinical-tab-body" id="medications-panel-body">
                            <div id="clinical-meds-container">
                                <div class="text-center py-4">
                                    <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                                    <p class="text-muted mt-2">Loading prescriptions...</p>
                                </div>
                            </div>
                            <div id="clinical-meds-show-all" class="text-center mt-3"></div>
                        </div>
                    </div>

                    {{-- Allergies Tab --}}
                    <div class="tab-pane fade" id="allergies-tab" role="tabpanel">
                        <div class="clinical-tab-header">
                            <h6><i class="mdi mdi-alert-circle"></i> Known Allergies</h6>
                            <button class="btn btn-sm btn-outline-primary refresh-clinical-btn" data-panel="allergies">
                                <i class="mdi mdi-refresh"></i> Refresh
                            </button>
                        </div>
                        <div class="clinical-tab-body" id="allergies-panel-body">
                            <div id="allergies-list"></div>
                        </div>
                    </div>

                    {{-- Injection/Immunization History Tab --}}
                    <div class="tab-pane fade" id="inj-imm-tab" role="tabpanel">
                        <div class="clinical-tab-body p-3" id="inj-imm-panel-body">
                            <div id="clinical-inj-imm-container">
                                {{-- Content loaded dynamically --}}
                                <div class="text-center py-4">
                                    <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                                    <p class="text-muted mt-2">Loading injection & immunization history...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #clinical-tabs {
        border-bottom: 1px solid #dee2e6;
        background: #f8f9fa;
        padding: 0.5rem 1rem 0 1rem;
    }

    #clinical-tabs .nav-link {
        border: none;
        color: #6c757d;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        border-radius: 0.5rem 0.5rem 0 0;
        transition: all 0.2s;
    }

    #clinical-tabs .nav-link:hover {
        background: #e9ecef;
        color: #495057;
    }

    #clinical-tabs .nav-link.active {
        background: white;
        color: {{ appsettings('hos_color', '#007bff') }};
        border-bottom: 2px solid {{ appsettings('hos_color', '#007bff') }};
    }

    .clinical-tab-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }

    .clinical-tab-header h6 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: #212529;
    }

    .clinical-tab-body {
        padding: 1rem;
        max-height: 60vh;
        min-height: 200px;
        overflow-y: auto;
    }

    #clinical-tab-content .tab-pane {
        min-height: 200px;
    }

    #clinical-notes-container {
        min-height: 150px;
    }

    .refresh-clinical-btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Vital Entry Cards */
    .vital-entry {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .vital-entry-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #e9ecef;
    }

    .vital-date {
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: 500;
    }

    .vital-entry-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1rem;
    }

    .vital-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 0.5rem;
        transition: all 0.2s;
        cursor: help;
    }

    .vital-item:hover {
        background: #e9ecef;
        transform: translateY(-2px);
    }

    .vital-item i {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: #6c757d;
    }

    .vital-value {
        font-size: 1.25rem;
        font-weight: 600;
        color: #212529;
    }

    .vital-label {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }

    .vital-normal {
        border-left: 4px solid #28a745;
    }

    .vital-warning {
        border-left: 4px solid #ffc107;
        background: #fff3cd;
    }

    .vital-critical {
        border-left: 4px solid #dc3545;
        background: #f8d7da;
    }

    /* Note Entry Cards */
    .note-entry {
        background: white;
        border: 1px solid #e9ecef;
        border-left: 4px solid {{ appsettings('hos_color', '#007bff') }};
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .note-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .note-doctor {
        font-weight: 600;
        color: #212529;
    }

    .note-date {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .note-diagnosis {
        margin-bottom: 0.75rem;
    }

    .diagnosis-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: #e7f3ff;
        color: {{ appsettings('hos_color', '#007bff') }};
        border-radius: 1rem;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .note-content {
        color: #495057;
        line-height: 1.6;
    }

    .specialty-tag {
        display: inline-block;
        padding: 0.15rem 0.5rem;
        background: #6c757d;
        color: white;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        margin-left: 0.5rem;
    }

    /* Medication Cards */
    .medication-card {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .medication-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 0.75rem;
    }

    .medication-name {
        font-weight: 600;
        color: #212529;
        font-size: 1rem;
    }

    .medication-status {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .status-active {
        background: #d4edda;
        color: #155724;
    }

    .status-completed {
        background: #d1ecf1;
        color: #0c5460;
    }

    .medication-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.5rem;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid #e9ecef;
    }

    .medication-detail-item {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .medication-detail-item strong {
        color: #495057;
    }

    /* Allergy Cards */
    .allergy-card {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-left: 4px solid #ffc107;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .allergy-card.severe {
        background: #f8d7da;
        border-color: #dc3545;
        border-left-color: #dc3545;
    }

    .allergy-name {
        font-weight: 600;
        color: #856404;
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }

    .allergy-card.severe .allergy-name {
        color: #721c24;
    }

    .allergy-reaction {
        font-size: 0.85rem;
        color: #856404;
        margin-bottom: 0.5rem;
    }

    .allergy-card.severe .allergy-reaction {
        color: #721c24;
    }

    .allergy-severity {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .severity-mild {
        background: #d4edda;
        color: #155724;
    }

    .severity-moderate {
        background: #fff3cd;
        color: #856404;
    }

    .severity-severe {
        background: #f8d7da;
        color: #721c24;
    }
</style>

<script>
(function initClinicalEncounterNotesLoader() {
    // Wait for jQuery to be available
    if (typeof jQuery === 'undefined') {
        setTimeout(initClinicalEncounterNotesLoader, 100);
        return;
    }

    const $ = jQuery;
    let clinicalEncounterNotesLoaded = false;

    // Load when tab is shown (using multiple event bindings for reliability)
    $(document).on('shown.bs.tab', '#enc-notes-tab-btn', function() {
        if (!clinicalEncounterNotesLoaded) {
            loadClinicalEncounterNotes();
        }
    });

    // Also bind click event as fallback
    $(document).on('click', '#enc-notes-tab-btn', function() {
        setTimeout(function() {
            if (!clinicalEncounterNotesLoaded && $('#enc-notes-tab').hasClass('show')) {
                loadClinicalEncounterNotes();
            }
        }, 150);
    });

    // Refresh button handler
    $(document).on('click', '.refresh-clinical-btn[data-panel="enc-notes"]', function() {
        clinicalEncounterNotesLoaded = false;
        loadClinicalEncounterNotes();
    });

    function loadClinicalEncounterNotes() {
        // Get patient ID from various sources
        let patientId = null;

        if (typeof currentPatient !== 'undefined' && currentPatient) {
            patientId = currentPatient;
        } else if (typeof selectedPatientId !== 'undefined' && selectedPatientId) {
            patientId = selectedPatientId;
        } else if ($('#clinical-context-modal').data('patient-id')) {
            patientId = $('#clinical-context-modal').data('patient-id');
        }

        if (!patientId) {
            $('#clinical-enc-notes-container').html(`
                <div class="alert alert-warning">
                    <i class="mdi mdi-alert"></i> No patient selected. Please select a patient first.
                </div>
            `);
            return;
        }

        $('#clinical-enc-notes-container').html(`
            <div class="text-center py-4">
                <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                <p class="text-muted mt-2">Loading encounter notes...</p>
            </div>
        `);

        $.ajax({
            url: `/EncounterHistoryList/${patientId}`,
            method: 'GET',
            data: { length: 10, start: 0, draw: 1 },
            success: function(response) {
                if (!response.data || response.data.length === 0) {
                    $('#clinical-enc-notes-container').html(`
                        <div class="text-center py-4">
                            <i class="mdi mdi-note-off mdi-48px text-muted"></i>
                            <p class="text-muted mt-2">No encounter notes found for this patient</p>
                        </div>
                    `);
                    $('#clinical-enc-notes-show-all').html('');
                    return;
                }

                let html = '';
                response.data.forEach(function(item) {
                    // The server returns pre-formatted HTML in 'info' column
                    // We render it directly without extra wrapping
                    html += item.info || `<div class="alert alert-light">Note ID: ${item.DT_RowId || 'N/A'}</div>`;
                });

                $('#clinical-enc-notes-container').html(html);
                clinicalEncounterNotesLoaded = true;

                // Add show all link
                $('#clinical-enc-notes-show-all').html(`
                    <a href="/patients/show/${patientId}?section=encountersCardBody" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="mdi mdi-open-in-new"></i> Show All Notes
                    </a>
                `);
            },
            error: function() {
                $('#clinical-enc-notes-container').html(`
                    <div class="alert alert-danger">
                        <i class="mdi mdi-alert-circle"></i> Failed to load encounter notes. Please try again.
                    </div>
                `);
            }
        });
    }

    // Reset when modal is hidden
    $(document).on('hidden.bs.modal', '#clinical-context-modal', function() {
        clinicalEncounterNotesLoaded = false;
        $('#clinical-enc-notes-container').html(`
            <div class="text-center py-4">
                <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                <p class="text-muted mt-2">Loading encounter notes...</p>
            </div>
        `);
        $('#clinical-enc-notes-show-all').html('');
    });
})();
</script>

<script>
(function initClinicalInjImmHistory() {
    // Wait for jQuery to be available
    if (typeof jQuery === 'undefined') {
        setTimeout(initClinicalInjImmHistory, 100);
        return;
    }

    const $ = jQuery;
    let clinicalInjImmLoaded = false;
    let clinicalInjectionTableInit = false;
    let clinicalImmunizationTableInit = false;

    // Load when tab is shown
    $(document).on('shown.bs.tab', '#inj-imm-tab-btn', function() {
        if (!clinicalInjImmLoaded) {
            loadClinicalInjImmContent();
        }
    });

    function loadClinicalInjImmContent() {
        // Get patient ID from the modal or global variable
        let patientId = null;

        // Try different sources for patient ID
        if (typeof currentPatient !== 'undefined' && currentPatient) {
            patientId = currentPatient;
        } else if (typeof selectedPatientId !== 'undefined' && selectedPatientId) {
            patientId = selectedPatientId;
        } else if ($('#clinical-context-modal').data('patient-id')) {
            patientId = $('#clinical-context-modal').data('patient-id');
        }

        if (!patientId) {
            $('#clinical-inj-imm-container').html(`
                <div class="alert alert-warning">
                    <i class="mdi mdi-alert"></i> No patient selected. Please select a patient first.
                </div>
            `);
            return;
        }

        // Build the content with tabs
        const uniqueId = 'clinical_inj_imm_' + Date.now();

        let html = `
            <ul class="nav nav-tabs mb-3" id="${uniqueId}-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="${uniqueId}-injection-tab" data-bs-toggle="tab" href="#${uniqueId}-injection" role="tab">
                        <i class="mdi mdi-needle me-1"></i> Injection History
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="${uniqueId}-immunization-tab" data-bs-toggle="tab" href="#${uniqueId}-immunization" role="tab">
                        <i class="mdi mdi-medical-bag me-1"></i> Immunization History
                    </a>
                </li>
            </ul>
            <div class="tab-content" id="${uniqueId}-content">
                <div class="tab-pane fade show active" id="${uniqueId}-injection" role="tabpanel">
                    <div class="card">
                        <div class="card-header py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="mdi mdi-history"></i> Injection History</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadClinicalInjectionTable('${uniqueId}', ${patientId})">
                                    <i class="mdi mdi-refresh"></i> Refresh
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover" id="${uniqueId}-injection-table" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>Drug</th>
                                            <th>Dose</th>
                                            <th>Route</th>
                                            <th>Site</th>
                                            <th>Nurse</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="${uniqueId}-immunization" role="tabpanel">
                    <div class="card">
                        <div class="card-header py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="mdi mdi-history"></i> Immunization History</h6>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary active clinical-imm-view-btn" data-uid="${uniqueId}" data-view="timeline" data-patient="${patientId}">
                                        <i class="mdi mdi-chart-timeline-variant"></i> Timeline
                                    </button>
                                    <button type="button" class="btn btn-outline-primary clinical-imm-view-btn" data-uid="${uniqueId}" data-view="table" data-patient="${patientId}">
                                        <i class="mdi mdi-table"></i> Table
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="${uniqueId}-timeline-view">
                                <div class="text-center py-4">
                                    <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                                    <p class="text-muted mt-2">Loading timeline...</p>
                                </div>
                            </div>
                            <div id="${uniqueId}-table-view" class="d-none">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover" id="${uniqueId}-immunization-table" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Vaccine</th>
                                                <th>Dose #</th>
                                                <th>Dose Amount</th>
                                                <th>Batch</th>
                                                <th>Site</th>
                                                <th>Nurse</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('#clinical-inj-imm-container').html(html);
        clinicalInjImmLoaded = true;

        // Load injection table
        loadClinicalInjectionTable(uniqueId, patientId);

        // Tab switch handler for immunization
        $(`#${uniqueId}-tabs a[data-bs-toggle="tab"]`).on('shown.bs.tab', function(e) {
            if ($(e.target).attr('href') === `#${uniqueId}-immunization`) {
                loadClinicalImmunizationTimeline(uniqueId, patientId);
            }
        });
    }

    // Make functions globally available
    window.loadClinicalInjectionTable = function(uid, patientId) {
        const tableId = `#${uid}-injection-table`;

        if (typeof $.fn.DataTable === 'undefined') {
            setTimeout(function() { loadClinicalInjectionTable(uid, patientId); }, 100);
            return;
        }

        if ($.fn.DataTable.isDataTable(tableId)) {
            $(tableId).DataTable().destroy();
        }

        $(tableId).DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: `/nursing-workbench/patient/${patientId}/injections`,
                dataSrc: ''
            },
            columns: [
                { data: 'administered_at', defaultContent: 'N/A' },
                { data: 'product_name', defaultContent: 'N/A' },
                { data: 'dose', defaultContent: 'N/A' },
                { data: 'route', defaultContent: 'N/A' },
                { data: 'site', defaultContent: 'N/A' },
                { data: 'administered_by', defaultContent: 'N/A' }
            ],
            order: [[0, 'desc']],
            pageLength: 10,
            language: {
                emptyTable: "No injection history found",
                processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
            }
        });
    };

    window.loadClinicalImmunizationTimeline = function(uid, patientId) {
        const container = $(`#${uid}-timeline-view`);
        container.html(`
            <div class="text-center py-4">
                <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                <p class="text-muted mt-2">Loading timeline...</p>
            </div>
        `);

        $.ajax({
            url: `/nursing-workbench/patient/${patientId}/immunization-history`,
            method: 'GET',
            success: function(response) {
                if (!response.records || response.records.length === 0) {
                    container.html(`
                        <div class="text-center py-4">
                            <i class="mdi mdi-calendar-blank mdi-48px text-muted"></i>
                            <p class="text-muted mt-2">No immunization records found</p>
                        </div>
                    `);
                    return;
                }

                let html = '<div class="timeline-container" style="position: relative; padding-left: 30px; border-left: 3px solid #dee2e6;">';
                response.records.forEach(record => {
                    html += `
                        <div class="timeline-item mb-3" style="position: relative;">
                            <div class="timeline-marker" style="position: absolute; left: -40px; width: 20px; height: 20px; border-radius: 50%; background: #28a745; border: 3px solid white; box-shadow: 0 0 0 3px #28a745;"></div>
                            <div class="card">
                                <div class="card-body py-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>${record.vaccine_name}</strong>
                                            <span class="badge bg-success ms-2">${record.dose_number || 'N/A'}</span>
                                            <br>
                                            <small class="text-muted">
                                                <i class="mdi mdi-calendar"></i> ${record.administered_date}
                                                ${record.site ? `| <i class="mdi mdi-map-marker"></i> ${record.site}` : ''}
                                                ${record.batch_number ? `| <i class="mdi mdi-barcode"></i> ${record.batch_number}` : ''}
                                            </small>
                                        </div>
                                        <small class="text-muted">${record.administered_by || ''}</small>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                });
                html += '</div>';
                container.html(html);
            },
            error: function() {
                container.html('<div class="alert alert-danger"><i class="mdi mdi-alert-circle"></i> Failed to load timeline</div>');
            }
        });
    };

    window.loadClinicalImmunizationTable = function(uid, patientId) {
        const tableId = `#${uid}-immunization-table`;

        if (typeof $.fn.DataTable === 'undefined') {
            setTimeout(function() { loadClinicalImmunizationTable(uid, patientId); }, 100);
            return;
        }

        if ($.fn.DataTable.isDataTable(tableId)) {
            $(tableId).DataTable().destroy();
        }

        $(tableId).DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: `/nursing-workbench/patient/${patientId}/immunization-history`,
                dataSrc: 'records'
            },
            columns: [
                { data: 'administered_date', defaultContent: 'N/A' },
                { data: 'vaccine_name', defaultContent: 'N/A' },
                { data: 'dose_number', render: function(d) { return d ? `Dose ${d}` : 'N/A'; } },
                { data: 'dose', defaultContent: 'N/A' },
                { data: 'batch_number', defaultContent: 'N/A' },
                { data: 'site', defaultContent: 'N/A' },
                { data: 'administered_by', defaultContent: 'N/A' }
            ],
            order: [[0, 'desc']],
            pageLength: 10,
            language: {
                emptyTable: "No immunization records found",
                processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
            }
        });
    };

    // View toggle for immunization
    $(document).on('click', '.clinical-imm-view-btn', function() {
        const uid = $(this).data('uid');
        const view = $(this).data('view');
        const patientId = $(this).data('patient');

        // Update button states
        $(`.clinical-imm-view-btn[data-uid="${uid}"]`).removeClass('active');
        $(this).addClass('active');

        // Show/hide views
        $(`#${uid}-timeline-view, #${uid}-table-view`).addClass('d-none');
        $(`#${uid}-${view}-view`).removeClass('d-none');

        // Load the appropriate view
        if (view === 'timeline') {
            loadClinicalImmunizationTimeline(uid, patientId);
        } else if (view === 'table') {
            loadClinicalImmunizationTable(uid, patientId);
        }
    });

    // Reset when modal is hidden
    $(document).on('hidden.bs.modal', '#clinical-context-modal', function() {
        clinicalInjImmLoaded = false;
        $('#clinical-inj-imm-container').html(`
            <div class="text-center py-4">
                <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                <p class="text-muted mt-2">Loading injection & immunization history...</p>
            </div>
        `);
    });
})();
</script>

<script>
(function initClinicalMedicationsLoader() {
    // Wait for jQuery to be available
    if (typeof jQuery === 'undefined') {
        setTimeout(initClinicalMedicationsLoader, 100);
        return;
    }

    const $ = jQuery;
    let clinicalMedicationsLoaded = false;

    // Load when tab is shown
    $(document).on('shown.bs.tab', '#meds-tab-btn', function() {
        if (!clinicalMedicationsLoaded) {
            loadClinicalMedications();
        }
    });

    // Also bind click event as fallback
    $(document).on('click', '#meds-tab-btn', function() {
        setTimeout(function() {
            if (!clinicalMedicationsLoaded && $('#meds-tab').hasClass('show')) {
                loadClinicalMedications();
            }
        }, 150);
    });

    // Refresh button handler
    $(document).on('click', '.refresh-clinical-btn[data-panel="medications"]', function() {
        clinicalMedicationsLoaded = false;
        loadClinicalMedications();
    });

    function loadClinicalMedications() {
        let patientId = null;

        if (typeof currentPatient !== 'undefined' && currentPatient) {
            patientId = currentPatient;
        } else if (typeof selectedPatientId !== 'undefined' && selectedPatientId) {
            patientId = selectedPatientId;
        } else if ($('#clinical-context-modal').data('patient-id')) {
            patientId = $('#clinical-context-modal').data('patient-id');
        }

        if (!patientId) {
            $('#clinical-meds-container').html(`
                <div class="alert alert-warning">
                    <i class="mdi mdi-alert"></i> No patient selected. Please select a patient first.
                </div>
            `);
            return;
        }

        $('#clinical-meds-container').html(`
            <div class="text-center py-4">
                <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                <p class="text-muted mt-2">Loading prescriptions...</p>
            </div>
        `);

        // AJAX request to fetch prescription history
        // Using length=20 as requested
        $.ajax({
            url: `/prescHistoryList/${patientId}`,
            method: 'GET',
            data: { length: 20, start: 0, draw: 1 },
            success: function(response) {
                if (!response.data || response.data.length === 0) {
                    $('#clinical-meds-container').html(`
                        <div class="text-center py-4">
                            <i class="mdi mdi-pill mdi-48px text-muted"></i>
                            <p class="text-muted mt-2">No prescription history found</p>
                        </div>
                    `);
                    $('#clinical-meds-show-all').html('');
                    return;
                }

                let html = '';
                response.data.forEach(function(item) {
                     // The 'info' column contains the pre-rendered HTML card
                    html += item.info || `<div class="alert alert-light">Item: ${item.id}</div>`;
                });

                $('#clinical-meds-container').html(html);
                clinicalMedicationsLoaded = true;

                // Add see more link
                $('#clinical-meds-show-all').html(`
                    <a href="/patients/show/${patientId}?section=prescriptionsNotesCardBody" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="mdi mdi-open-in-new"></i> See More Prescriptions
                    </a>
                `);
            },
            error: function() {
                $('#clinical-meds-container').html(`
                    <div class="alert alert-danger">
                        <i class="mdi mdi-alert-circle"></i> Failed to load prescriptions.
                    </div>
                `);
            }
        });
    }

    // Reset when modal is hidden
    $(document).on('hidden.bs.modal', '#clinical-context-modal', function() {
        clinicalMedicationsLoaded = false;
        $('#clinical-meds-container').html(`
            <div class="text-center py-4">
                <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                <p class="text-muted mt-2">Loading prescriptions...</p>
            </div>
        `);
        $('#clinical-meds-show-all').html('');
    });
})();
</script>
