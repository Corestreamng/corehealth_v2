                <span>New Request</span>
            </button>
            <button class="quick-action-btn" id="btn-view-reports">
                <i class="mdi mdi-chart-box-outline"></i>
                <span>View Reports</span>
            </button>
            <button class="quick-action-btn" disabled style="opacity: 0.5;">
                <i class="mdi mdi-package-variant"></i>
                <span>Inventory (Coming Soon)</span>
            </button>
            @if(appsettings()->enable_ei_lab)
<button class="quick-action-btn" onclick="showEmergencyIntakeModal()">
                <i class="mdi mdi-ambulance text-danger"></i>
                <span>Emergency Intake</span>
            </button>
@endif
        </div>
    </div>

    <!-- Main Workspace -->
    <div class="main-workspace" id="main-workspace">
        <!-- Navigation Bar (Mobile Back Button + Actions) -->
        <div class="workspace-navbar" id="workspace-navbar">
            <button class="btn-back-to-search" id="btn-back-to-search">
                <i class="fa fa-arrow-left"></i> Back to Search
            </button>
            <div class="workspace-navbar-actions">
                <button class="btn-toggle-search" id="btn-toggle-search">
                    <i class="fa fa-bars"></i> Toggle Search
                </button>
                <button class="btn-clinical-context" id="btn-clinical-context">
                    <i class="fa fa-heartbeat"></i> Clinical Context
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <div class="empty-state" id="empty-state">
            <i class="fa fa-flask"></i>
            <h3>Select a patient to begin</h3>
            <p>Use the search box or view pending queue</p>
            <button class="btn btn-lg btn-primary" id="view-queue-btn">
                📋 View All Pending Requests
            </button>
        </div>

        <!-- Queue View -->
        <div class="queue-view" id="queue-view">
            <div class="queue-view-header">
                <h4 id="queue-view-title"><i class="mdi mdi-format-list-bulleted"></i> Lab Queue</h4>
                <button class="btn-close-queue" id="btn-close-queue">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content">
                <!-- Date Filter Section -->
                <div class="card-modern mb-3" style="border: none; background: #f8f9fa;">
                    <div class="card-body" style="padding: 1rem;">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <h6 class="mb-0" id="current-queue-title" style="color: #495057;">
                                    <i class="mdi mdi-filter"></i> Viewing: <span style="font-weight: 700; color: #667eea;">All Pending</span>
                                </h6>
                            </div>
                            <div class="col-md-9">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <label class="mb-0" style="font-size: 0.875rem; color: #6c757d;">Date Range:</label>
                                    </div>
                                    <div class="col-auto">
                                        <input type="date" id="queue-start-date" class="form-control form-control-sm" style="width: 150px;">
                                    </div>
                                    <div class="col-auto">
                                        <span style="color: #6c757d;">to</span>
                                    </div>
                                    <div class="col-auto">
                                        <input type="date" id="queue-end-date" class="form-control form-control-sm" style="width: 150px;">
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-sm btn-primary" id="apply-queue-filter">
                                            <i class="mdi mdi-filter-check"></i> Apply
                                        </button>
                                        <button class="btn btn-sm btn-secondary" id="reset-queue-filter">
                                            <i class="mdi mdi-filter-off"></i> Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Approval Queue Hint -->
                <div id="approval-queue-hint" class="alert alert-info" style="display: none; font-size: 0.9rem; margin-top: 1rem; border-left: 4px solid #0dcaf0;">
                    <i class="mdi mdi-information"></i> <strong>Note:</strong> Once approved, requests are moved to the <strong>Completed</strong> list. You can reverse your recent approvals from there.
                </div>
                <!-- DataTable -->
                <table class="table" id="queue-datatable" style="width: 100%">
                    <thead>
                        <tr>
                            <th>Queue Items</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

        <!-- Reports View (Full Screen - Global Access) -->
        <div class="queue-view" id="reports-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-chart-box"></i> Laboratory Reports & Analytics</h4>
                <button class="btn btn-secondary btn-close-queue" id="btn-close-reports">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content" style="padding: 1.5rem;">
                <!-- Filter Panel -->
                <div class="reports-filter-panel card-modern mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-filter"></i> Filters</h6>
                    </div>
                    <div class="card-body">
                        <form id="reports-filter-form">
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label for="report-date-from"><i class="mdi mdi-calendar"></i> Date From</label>
                                    <input type="date" class="form-control" id="report-date-from" name="date_from">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="report-date-to"><i class="mdi mdi-calendar"></i> Date To</label>
                                    <input type="date" class="form-control" id="report-date-to" name="date_to">
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="report-status-filter"><i class="mdi mdi-filter-variant"></i> Status</label>
                                    <select class="form-control" id="report-status-filter" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="1">Awaiting Billing</option>
                                        <option value="2">Awaiting Sample</option>
                                        <option value="3">Awaiting Results</option>
                                        <option value="4">Completed</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="report-service-filter"><i class="mdi mdi-test-tube"></i> Service</label>
                                    <select class="form-control" id="report-service-filter" name="service_id">
                                        <option value="">All Services</option>
                                        <!-- Services will be populated via JS -->
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="report-doctor-filter"><i class="mdi mdi-doctor"></i> Requesting Doctor</label>
                                    <select class="form-control" id="report-doctor-filter" name="doctor_id">
                                        <option value="">All Doctors</option>
                                        <!-- Doctors will be populated via JS -->
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="report-hmo-filter"><i class="mdi mdi-hospital-building"></i> HMO</label>
                                    <select class="form-control" id="report-hmo-filter" name="hmo_id">
                                        <option value="">All HMOs</option>
                                        <!-- HMOs will be populated via JS -->
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="report-patient-search"><i class="mdi mdi-account-search"></i> Patient Search</label>
                                    <input type="text" class="form-control" id="report-patient-search" name="patient_search" placeholder="File no or name...">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-md-12 text-right">
                                    <button type="button" class="btn btn-secondary" id="clear-report-filters">
                                        <i class="mdi mdi-refresh"></i> Clear
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="mdi mdi-filter"></i> Apply Filters
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sub Tabs -->
                <ul class="nav nav-tabs mb-3" id="reports-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="overview-tab" data-toggle="tab" href="#overview-content" role="tab" aria-controls="overview-content" aria-selected="true">
                            <i class="mdi mdi-view-dashboard"></i> Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="detailed-report-tab" data-toggle="tab" href="#detailed-report-content" role="tab" aria-controls="detailed-report-content" aria-selected="false">
                            <i class="mdi mdi-table"></i> Detailed Report
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="analytics-tab" data-toggle="tab" href="#analytics-content" role="tab" aria-controls="analytics-content" aria-selected="false">
                            <i class="mdi mdi-chart-line"></i> Analytics
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="reports-tab-content">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview-content" role="tabpanel" aria-labelledby="overview-tab">
                        <div class="reports-container">
                            <!-- Summary Statistics Cards -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                            <i class="mdi mdi-clipboard-list"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-total-requests">0</h3>
                                            <p>Total Requests</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                            <i class="mdi mdi-check-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-completed">0</h3>
                                            <p>Completed</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                                            <i class="mdi mdi-clock"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-pending">0</h3>
                                            <p>Pending</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                                            <i class="mdi mdi-timer"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-avg-tat">0</h3>
                                            <p>Avg TAT</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Charts and Top Services -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-chart-bar"></i> Requests by Status</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="status-chart" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-chart-line"></i> Monthly Trends</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="trends-chart" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Top Services -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="card-modern" id="top-services-card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-test-tube"></i> Top 10 Lab Services</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="top-services-list">
                                                <p class="text-muted">Loading...</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Report Tab -->
                    <div class="tab-pane fade" id="detailed-report-content" role="tabpanel" aria-labelledby="detailed-report-tab">
                        <div class="reports-container">
                            <!-- DataTable -->
                            <div class="card-modern">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-table"></i> Detailed Report</h6>
                                    <div>
                                        <button class="btn btn-sm btn-success" id="export-excel">
                                            <i class="mdi mdi-file-excel"></i> Excel
                                        </button>
                                        <button class="btn btn-sm btn-danger" id="export-pdf">
                                            <i class="mdi mdi-file-pdf"></i> PDF
                                        </button>
                                        <button class="btn btn-sm btn-info" id="print-report">
                                            <i class="mdi mdi-printer"></i> Print
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="reports-datatable" style="width: 100%">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>File No</th>
                                                    <th>Patient</th>
                                                    <th>Service</th>
                                                    <th>Doctor</th>
                                                    <th>HMO</th>
                                                    <th>Status</th>
                                                    <th>TAT</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Tab -->
                    <div class="tab-pane fade" id="analytics-content" role="tabpanel" aria-labelledby="analytics-tab">
                        <div class="reports-container">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card-modern">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-doctor"></i> Top Requesting Doctors</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="top-doctors-list">
                                                <p class="text-muted">Loading...</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patient Header -->
        <div class="patient-header" id="patient-header">
            <div class="patient-header-top">
                <div style="flex: 1;">
                    <div class="d-flex align-items-center mb-1">
                        <div class="patient-name mb-0 me-3" id="patient-name"></div>
                        <button class="btn btn-sm btn-danger btn-manage-alerts" id="btn-manage-alerts" style="display:none;">
                            <i class="mdi mdi-alert-octagon"></i> Alerts
                        </button>
                    </div>
                    <div class="patient-meta" id="patient-meta"></div>
                    <div class="sticky-header-alerts mt-2" style="max-height: 60px; overflow-y: auto;"></div>
                </div>
                <button class="btn-expand-patient mt-2" id="btn-expand-patient" title="Show more details">
                    <span class="btn-expand-text">more biodata</span>
                    <i class="mdi mdi-chevron-down"></i>
                </button>
            </div>
            <div class="patient-details-expanded" id="patient-details-expanded">
                <div class="patient-details-grid" id="patient-details-grid"></div>
            </div>
        </div>

        <!-- Workspace Content -->
        <div class="workspace-content" id="workspace-content">
            <div class="workspace-tabs">
                <button class="workspace-tab active" data-tab="pending">
                    <i class="mdi mdi-clipboard-list"></i>
                    <span>Pending</span>
                    <span class="workspace-tab-badge" id="pending-badge">0</span>
                </button>
                <button class="workspace-tab" data-tab="new-request">
                    <i class="mdi mdi-plus-circle"></i>
                    <span>New Request</span>
                </button>
                <button class="workspace-tab" data-tab="history">
                    <i class="mdi mdi-history"></i>
                    <span>History</span>
                </button>
                <button class="workspace-tab" data-tab="procedures">
                    <i class="mdi mdi-medical-bag"></i>
                    <span>Procedures</span>
                </button>
            </div>

            <div class="workspace-tab-content active" id="pending-tab">
                <div class="pending-subtabs">
                    <button class="pending-subtab active" data-status="all">
                        <i class="mdi mdi-format-list-bulleted"></i>
                        <span>All Pending</span>
                        <span class="subtab-badge" id="all-pending-badge">0</span>
                    </button>
                    <button class="pending-subtab" data-status="billing">
                        <i class="mdi mdi-cash-register"></i>
                        <span>Awaiting Billing</span>
                        <span class="subtab-badge" id="billing-subtab-badge">0</span>
                    </button>
                    <button class="pending-subtab" data-status="sample">
                        <i class="mdi mdi-test-tube"></i>
                        <span>Awaiting Sample</span>
                        <span class="subtab-badge" id="sample-subtab-badge">0</span>
                    </button>
                    <button class="pending-subtab" data-status="results">
                        <i class="mdi mdi-flask"></i>
                        <span>Awaiting Results</span>
                        <span class="subtab-badge" id="results-subtab-badge">0</span>
                    </button>
                    <button class="pending-subtab" data-status="freeform">
                        <i class="mdi mdi-file-document-edit"></i>
                        <span>Free-Form</span>
                        <span class="subtab-badge" id="freeform-subtab-badge" style="background: #6c757d;">0</span>
                    </button>
                    @if(($requiresApproval ?? false))
                    <button class="pending-subtab" data-status="approval">
                        <i class="mdi mdi-check-decagram"></i>
                        <span>Pending Approval</span>
                        <span class="subtab-badge" id="approval-subtab-badge" style="background: #6f42c1;">0</span>
                    </button>
                    @endif
                </div>
                <div class="pending-subtab-content" id="pending-subtab-container">
                    <h5>Loading...</h5>
                </div>
            </div>

            <div class="workspace-tab-content" id="new-request-tab">
                <div class="new-request-container">
                    <div class="new-request-header">
                        <h4><i class="mdi mdi-plus-circle"></i> Create New Lab Request</h4>
                        <p class="text-muted">Request investigation for <span id="new-request-patient-name"></span></p>
                    </div>
                    <form id="new-lab-request-form" class="new-request-form">
                        <div class="form-row">
                            <div class="form-group col-md-12" style="position: relative;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="service-search-input" class="mb-0"><i class="mdi mdi-magnify"></i> Search Laboratory Services *</label>
                                    <a href="javascript:void(0)" onclick="addFreeFormLabWorkbench()" class="text-primary small"><i class="mdi mdi-plus"></i> Not listed? Add free-form</a>
                                </div>
                                <input type="text" class="form-control" id="service-search-input" placeholder="Type to search for lab services..." autocomplete="off" onkeyup="searchLabServices(this.value)">
                                <ul class="list-group" id="service-search-results" style="display: none; position: absolute; z-index: 1000; max-height: 300px; overflow-y: auto; width: calc(100% - 30px);"></ul>
                            </div>
                        </div>

                        <div id="selected-services-container" class="mb-3">
                            <label><i class="mdi mdi-test-tube"></i> Selected Services</label>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped">
                                    <thead>
                                        <th>Name</th>
                                        <th>Price</th>
                                        <th>Notes</th>
                                        <th>*</th>
                                    </thead>
                                    <tbody id="selected-lab-services"></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="request-clinical-notes"><i class="mdi mdi-note-text"></i> Clinical Notes / Indication</label>
                            <textarea class="form-control" id="request-clinical-notes" name="clinical_notes" rows="3" placeholder="Enter clinical indication, symptoms, or relevant patient history..."></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="switchWorkspaceTab('pending')">
                                <i class="mdi mdi-close"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-check"></i> Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="workspace-tab-content" id="history-tab">
                <h4>Investigation History</h4>
                <div class="history-table">
                    <table class="table table-hover" style="width: 100%" id="investigation_history_list">
                        <thead class="table-light">
                            <th><i class="mdi mdi-test-tube"></i> Laboratory Requests</th>
                        </thead>
                    </table>
                </div>
            </div>

            <div class="workspace-tab-content" id="procedures-tab">
                <h4><i class="mdi mdi-medical-bag"></i> Patient Procedures</h4>
                <p class="text-muted mb-3">View all procedures for the selected patient</p>
                <div class="procedures-table-wrapper">
                    <table class="table table-hover" style="width: 100%" id="procedures_history_list">
                        <thead class="table-light">
                            <th><i class="mdi mdi-medical-bag"></i> Procedures</th>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.invest_res_view_modal', ['showLabNumber' => true])
@include('admin.partials.invest_res_view_js')

<!-- Sample Collection Modal (Lab Number Assignment) -->
<div class="modal fade" id="sampleCollectionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="mdi mdi-test-tube"></i> Collect Sample</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Lab Number Input -->
                <div class="mb-3">
                    <label class="form-label fw-bold"><i class="mdi mdi-tag-text"></i> Lab Number</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="sample_lab_number" placeholder="Lab number..." readonly>
                        <button type="button" class="btn btn-outline-primary" id="btnAutoLabNumber" title="Auto-generate lab number">
                            <i class="mdi mdi-refresh"></i> Auto
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btnManualLabNumber" title="Enter manually">
                            <i class="mdi mdi-pencil"></i> Manual
                        </button>
                    </div>
                    <small class="text-muted" id="labNumberFormatHint"></small>
                    <div class="text-danger small mt-1 d-none" id="labNumberError"></div>
                    <div class="text-warning small mt-1 d-none" id="labNumberDuplicateWarning"></div>
                </div>

                <!-- Recent Lab Numbers -->
                <div class="mb-3 d-none" id="recentLabNumbersBox">
                    <label class="form-label text-muted small">Recent Lab Numbers:</label>
                    <div id="recentLabNumbersList" class="d-flex flex-wrap gap-1"></div>
                </div>

                <!-- Items Preview -->
                <div class="mb-3">
                    <label class="form-label fw-bold"><i class="mdi mdi-flask-outline"></i> Items to Collect (<span id="sampleItemCount">0</span>)</label>
                    <div id="sampleItemsList" class="border rounded p-2" style="max-height: 200px; overflow-y: auto;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
                <button type="button" class="btn btn-info text-white" id="btnConfirmCollectSample" disabled>
                    <i class="mdi mdi-test-tube"></i> Collect Sample
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Reason Modal -->
<div class="modal fade" id="deleteReasonModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fa fa-trash"></i> Delete Lab Request</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="deleteRequestForm">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action will soft delete the lab request. It can be restored from the trash later.
                    </div>
                    <div class="mb-3">
                        <p><strong>Service:</strong> <span id="delete_service_name"></span></p>
                        <p><strong>Request ID:</strong> <span id="delete_request_id"></span></p>
                    </div>
                    <div class="form-group">
                        <label for="delete_reason">Reason for Deletion <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="delete_reason" name="reason" rows="4"
                                  placeholder="Please provide a detailed reason for deleting this lab request (minimum 10 characters)"
                                  required minlength="10"></textarea>
                        <small class="form-text text-muted">This reason will be logged for audit purposes.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-trash"></i> Delete Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Dismiss Reason Modal -->
<div class="modal fade" id="dismissReasonModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fa fa-ban"></i> Dismiss Lab Request</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
            </div>
            <form id="dismissRequestForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <strong>Info:</strong> Dismissed requests can be restored later from the trash panel.
                    </div>
                    <div class="mb-3">
                        <p><strong>Service:</strong> <span id="dismiss_service_name"></span></p>
                        <p><strong>Request ID:</strong> <span id="dismiss_request_id"></span></p>
                    </div>
                    <div class="form-group">
                        <label for="dismiss_reason">Reason for Dismissal <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="dismiss_reason" name="reason" rows="4"
                                  placeholder="Please provide a reason for dismissing this lab request (minimum 10 characters)"
                                  required minlength="10"></textarea>
                        <small class="form-text text-muted">This reason will be logged for audit purposes.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fa fa-ban"></i> Dismiss Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Trash Aside Panel -->
<div class="trash-panel" id="trashPanel" style="display: none;">
    <div class="trash-panel-header">
        <h5><i class="fa fa-trash"></i> Trash & Dismissed</h5>
        <button class="close-panel-btn" id="closeTrashPanel">&times;</button>
    </div>
    <div class="trash-panel-tabs">
        <button class="trash-tab active" data-trash-tab="dismissed">
            <i class="fa fa-ban"></i> Dismissed
            <span class="badge badge-warning" id="dismissed-count">0</span>
        </button>
        <button class="trash-tab" data-trash-tab="deleted">
            <i class="fa fa-trash"></i> Deleted
            <span class="badge badge-danger" id="deleted-count">0</span>
        </button>
    </div>
    <div class="trash-panel-content">
        <div class="trash-tab-content active" id="dismissed-content">
            <div class="table-responsive">
                <table class="table table-sm table-hover" id="dismissed-table" style="width: 100%">
                    <thead>
                        <tr>
                            <th>Details</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
        <div class="trash-tab-content" id="deleted-content">
            <div class="table-responsive">
                <table class="table table-sm table-hover" id="deleted-table" style="width: 100%">
                    <thead>
                        <tr>
                            <th>Details</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Audit Log Modal -->
<div class="modal fade" id="auditLogModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fa fa-clipboard-list"></i> Audit Trail</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Action Type</label>
                        <select class="form-control" id="audit_action_filter">
                            <option value="">All Actions</option>
                            <option value="view">View</option>
                            <option value="edit">Edit</option>
                            <option value="delete">Delete</option>
                            <option value="restore">Restore</option>
                            <option value="dismiss">Dismiss</option>
                            <option value="undismiss">Undismiss</option>
                            <option value="billing">Billing</option>
                            <option value="sample_collection">Sample Collection</option>
                            <option value="result_entry">Result Entry</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" class="form-control" id="audit_from_date">
                    </div>
                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" class="form-control" id="audit_to_date">
                    </div>
                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary btn-block" id="applyAuditFilter">
                            <i class="fa fa-filter"></i> Apply Filter
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="audit-log-table" style="width: 100%">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="exportAuditLog">
                    <i class="fa fa-download"></i> Export to Excel
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Trash Panel Styles */
.trash-panel {
    position: fixed;
    right: 0;
    top: 0;
    bottom: 0;
    width: 450px;
    background: white;
    box-shadow: -4px 0 15px rgba(0, 0, 0, 0.2);
    z-index: 2000;
    display: flex;
    flex-direction: column;
}

.trash-panel-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 3px solid #5a67d8;
}

.trash-panel-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.25rem;
}

.trash-panel-tabs {
    display: flex;
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.trash-tab {
    flex: 1;
    padding: 1rem;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 600;
    color: #6c757d;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.trash-tab:hover {
    background: rgba(0, 123, 255, 0.05);
    color: #007bff;
}

.trash-tab.active {
    color: #007bff;
    border-bottom-color: #007bff;
    background: white;
}

.trash-panel-content {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
}

.trash-tab-content {
    display: none;
}

.trash-tab-content.active {
    display: block;
}

/* Floating Trash Button */
.floating-trash-btn {
    position: fixed;
    right: 30px;
    bottom: 100px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.3s;
    z-index: 1000;
}

.floating-trash-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

.floating-trash-btn .badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
}

/* Audit Log Button */
.floating-audit-btn {
    position: fixed;
    right: 30px;
    bottom: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 15px rgba(240, 147, 251, 0.4);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.3s;
    z-index: 1000;
}

.floating-audit-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(240, 147, 251, 0.6);
}
</style>

<!-- Floating Action Buttons -->
<button class="floating-trash-btn" id="openTrashPanel" title="View Trash & Dismissed Requests">
    <i class="fa fa-trash"></i>
    <span class="badge" id="trash-total-count">0</span>
</button>

<button class="floating-audit-btn" id="openAuditLog" title="View Audit Trail">
    <i class="fa fa-clipboard-list"></i>
</button>

@if(($isApprover ?? false) && ($requiresApproval ?? false))
<button class="floating-approval-btn" id="openApprovalQueue" title="View Results Pending Approval">
    <i class="mdi mdi-check-decagram"></i>
    <span class="badge" id="approval-float-count">0</span>
</button>
@endif

<!-- Floating Cart Button -->
<div class="floating-cart" id="floating-cart">
    <button class="floating-cart-btn" onclick="openCartReviewModal()">
        <i class="mdi mdi-cart-outline"></i>
        <span class="cart-badge" id="cart-item-count">0</span>
        <span>selected</span>
        <span class="cart-total" id="cart-total-display" style="display:none; margin-left:4px; font-weight:600;"></span>
        <i class="mdi mdi-chevron-up"></i>
    </button>
</div>

<!-- Cart Review Modal -->
<div class="modal fade" id="cartReviewModal" tabindex="-1" role="dialog" aria-labelledby="cartReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cartReviewModalLabel">
                    <i class="mdi mdi-cart-check"></i> Selected Items
                    <span class="badge bg-light text-primary ms-2" id="modal-cart-count">0</span>
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="cart-review-body">
                <!-- Populated by JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Include Investigation Result Entry Modal -->
@include('admin.partials.invest_res_modal', ['save_route' => 'lab.saveResult'])

<!-- Include Bulk Result Entry Modal -->
@include('admin.partials.bulk_result_entry_modal')

<!-- Result Approval Review Modal -->
@if(($isApprover ?? false) && ($requiresApproval ?? false))
<div class="modal fade" id="approvalReviewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #6f42c1, #5a32a3); color: white;">
                <h5 class="modal-title"><i class="mdi mdi-check-decagram"></i> Result Approval Review</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="approval-review-body">
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Loading result details...</p>
                </div>
            </div>
            <div class="modal-footer" id="approval-review-footer" style="display: none;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="mdi mdi-close"></i> Close
                </button>
                <button type="button" class="btn btn-danger" id="btn-reject-result">
                    <i class="mdi mdi-close-circle"></i> Reject Result
                </button>
                <button type="button" class="btn btn-success" id="btn-approve-result">
                    <i class="mdi mdi-check-circle"></i> Approve Result
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Reason Modal -->
<div class="modal fade" id="rejectionReasonModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-close-circle"></i> Reject Result</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <form id="rejectionReasonForm">
                <div class="modal-body">
                    <p class="text-muted">Please provide a reason for rejecting this result. The technician will be able to see this feedback.</p>
                    <div class="form-group">
                        <label for="rejection_reason"><strong>Rejection Reason <span class="text-danger">*</span></strong></label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="4" required
                                  placeholder="E.g., Values appear inconsistent, please re-run test..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="btn-confirm-reject">
                        <i class="mdi mdi-close-circle"></i> Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Approve Confirmation Modal -->
<div class="modal fade" id="approveConfirmModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745, #20c997); color: #fff; border: none;">
                <h5 class="modal-title"><i class="mdi mdi-check-decagram"></i> Confirm Approval</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div style="font-size: 3rem; color: #28a745;"><i class="mdi mdi-clipboard-check-outline"></i></div>
                <h5 class="mt-2 mb-1">Approve this result?</h5>
                <p class="text-muted mb-0">This will make the result visible to doctors and patients. You can reverse this later if needed.</p>
            </div>
            <div class="modal-footer justify-content-center" style="border-top: 1px solid #eee;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-success" id="btn-confirm-approve">
                    <i class="mdi mdi-check-bold"></i> Yes, Approve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reverse Approval Confirmation Modal -->
<div class="modal fade" id="reverseApprovalModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #e67e22, #f39c12); color: #fff; border: none;">
                <h5 class="modal-title"><i class="mdi mdi-undo-variant"></i> Reverse Approval</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div style="font-size: 3rem; color: #e67e22;"><i class="mdi mdi-undo-variant"></i></div>
                <h5 class="mt-2 mb-1">Reverse this approval?</h5>
                <p class="text-muted mb-0">The result will be removed from the patient's record and sent back to the pending approval queue.</p>
            </div>
            <div class="modal-footer justify-content-center" style="border-top: 1px solid #eee;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="mdi mdi-close"></i> Cancel</button>
                <button type="button" class="btn btn-warning text-white" id="btn-confirm-reverse">
                    <i class="mdi mdi-undo-variant"></i> Yes, Reverse
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Include Clinical Context Modal -->
@include('admin.partials.clinical_context_modal')
@include('admin.partials.treatment-plan-viewer-modal')
@include('admin.partials.patient-form-modal')
@include('admin.partials.bundle_view_modal')
@include('admin.partials.bundle_remove_modal')


