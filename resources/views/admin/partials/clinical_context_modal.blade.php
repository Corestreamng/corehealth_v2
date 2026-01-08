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
                        <button class="nav-link" id="notes-tab-btn" data-bs-toggle="tab" data-bs-target="#notes-tab" type="button" role="tab">
                            <i class="mdi mdi-note-text"></i> Clinical Notes
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

                    {{-- Clinical Notes Tab --}}
                    <div class="tab-pane fade" id="notes-tab" role="tabpanel">
                        <div class="clinical-tab-header">
                            <h6><i class="mdi mdi-note-text"></i> Clinical Notes</h6>
                            <button class="btn btn-sm btn-outline-primary refresh-clinical-btn" data-panel="notes">
                                <i class="mdi mdi-refresh"></i> Refresh
                            </button>
                        </div>
                        <div class="clinical-tab-body" id="notes-panel-body">
                            <div class="table-responsive">
                                <table class="table" id="notes-table" style="width: 100%">
                                    <thead>
                                        <tr>
                                            <th>Doctor Notes</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Medications Tab --}}
                    <div class="tab-pane fade" id="meds-tab" role="tabpanel">
                        <div class="clinical-tab-header">
                            <h6><i class="mdi mdi-pill"></i> Active Medications</h6>
                            <button class="btn btn-sm btn-outline-primary refresh-clinical-btn" data-panel="medications">
                                <i class="mdi mdi-refresh"></i> Refresh
                            </button>
                        </div>
                        <div class="clinical-tab-body" id="medications-panel-body">
                            <div id="medications-list-container"></div>
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
        overflow-y: auto;
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
