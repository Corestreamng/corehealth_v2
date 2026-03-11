@extends('admin.layouts.app')
@section('title', 'HMO Workbench')
@section('page_name', 'HMO Management')
@section('subpage_name', 'HMO Workbench')
@section('content')

<style>
    :root {
        --hospital-primary: {{ appsettings('hos_color', '#007bff') }};
    }

    /* Modern Card Styling */
    .stat-card-modern {
        border-radius: 12px;
        border: none;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .stat-card-modern .card-body {
        padding: 1.25rem;
    }

    .stat-card-modern h6 {
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
        opacity: 0.8;
    }

    .stat-card-modern h2 {
        font-weight: 700;
        margin-bottom: 0;
    }

    .stat-card-modern .stat-icon {
        font-size: 3rem;
        opacity: 0.3;
    }

    /* Queue Card Styling */
    .queue-card-modern {
        border-radius: 10px;
        border: none;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }

    .queue-card-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }

    .queue-card-modern .card-body {
        padding: 1rem;
    }

    .queue-card-modern h6 {
        font-size: 0.75rem;
        margin-bottom: 0.25rem;
        opacity: 0.9;
    }

    .queue-card-modern h3 {
        font-weight: 700;
        margin-bottom: 0;
    }

    .queue-card-modern .queue-icon {
        font-size: 2rem;
        opacity: 0.4;
    }

    /* Filter Card Modern */
    .filter-card-modern {
        border-radius: 12px;
        border: none;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .filter-card-modern .card-header {
        border-radius: 12px 12px 0 0;
        background: linear-gradient(135deg, var(--hospital-primary) 0%, #5a67d8 100%);
        padding: 0.75rem 1.25rem;
    }

    /* Tabs Modern */
    .workbench-tabs-modern .nav-link {
        border-radius: 8px 8px 0 0;
        font-weight: 500;
        padding: 0.75rem 1.25rem;
        transition: all 0.2s;
    }

    .workbench-tabs-modern .nav-link.active {
        background: var(--hospital-primary);
        color: white;
        border-color: var(--hospital-primary);
    }

    /* Page Header */
    .workbench-header {
        margin-bottom: 1.5rem;
    }

    .workbench-title {
        font-weight: 700;
        color: var(--hospital-primary);
        margin-bottom: 0.25rem;
    }

    .workbench-subtitle {
        color: #6c757d;
        margin-bottom: 0;
    }

    .workbench-date {
        color: #6c757d;
        font-size: 0.9rem;
    }

    /* Action Link Style */
    .stat-action-link {
        font-size: 0.8rem;
        margin-top: 0.5rem;
        display: inline-block;
    }

    .stat-action-link:hover {
        opacity: 1;
    }

    /* Clinical Context Modal Styling */
    #clinical-context-modal .modal-dialog {
        max-width: 90vw;
    }

    #clinical-context-modal .modal-body {
        padding: 0;
        max-height: 80vh;
        overflow-y: auto;
    }

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
        color: var(--hospital-primary);
        border-bottom: 2px solid var(--hospital-primary);
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

    /* Note Entry Cards */
    .note-entry {
        background: white;
        border: 1px solid #e9ecef;
        border-left: 4px solid var(--hospital-primary);
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
        color: var(--hospital-primary);
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

    /* Cursor pointer for cards */
    .cursor-pointer {
        cursor: pointer;
    }

    .cursor-pointer:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.2s;
    }
</style>

<section class="content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center workbench-header">
            <div>
                <h3 class="workbench-title">
                    <i class="mdi mdi-shield-check mr-2"></i>HMO Executive Workbench
                </h3>
                <p class="workbench-subtitle">Claims Validation & Management Dashboard</p>
            </div>
            <div class="workbench-date">
                <button class="btn btn-danger btn-sm me-2" data-bs-toggle="modal" data-bs-target="#emergencyIntakeModal">
                    <i class="mdi mdi-ambulance"></i> Emergency Intake
                </button>
                <i class="mdi mdi-calendar mr-1"></i>{{ date('l, F j, Y') }}
            </div>
        </div>

        <!-- Financial Summary Cards Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card-modern text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Pending Claims Value</h6>
                                <h2 id="pending_claims_total">₦0</h2>
                            </div>
                            <i class="mdi mdi-cash-multiple stat-icon"></i>
                        </div>
                        <a href="javascript:void(0)" class="text-white-50 stat-action-link preset-card" data-preset="">
                            <i class="mdi mdi-arrow-right mr-1"></i>View Pending
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-modern text-white" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Approved Today Value</h6>
                                <h2 id="approved_today_total">₦0</h2>
                            </div>
                            <i class="mdi mdi-cash-check stat-icon"></i>
                        </div>
                        <a href="javascript:void(0)" class="text-white-50 stat-action-link preset-card" data-preset="today_approved">
                            <i class="mdi mdi-arrow-right mr-1"></i>View Approved
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-modern text-white" style="background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Rejected Today Value</h6>
                                <h2 id="rejected_today_total">₦0</h2>
                            </div>
                            <i class="mdi mdi-cash-remove stat-icon"></i>
                        </div>
                        <a href="javascript:void(0)" class="text-white-50 stat-action-link preset-card" data-preset="today_rejected">
                            <i class="mdi mdi-arrow-right mr-1"></i>View Rejected
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card-modern text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Monthly Claims Total</h6>
                                <h2 id="monthly_claims_total">₦0</h2>
                            </div>
                            <i class="mdi mdi-calendar-month stat-icon"></i>
                        </div>
                        <small class="text-white-50">{{ date('F Y') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Queue Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="queue-card-modern text-white" data-tab-target="pending" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Pending Validation</h6>
                                <h3 id="pending_count">0</h3>
                            </div>
                            <i class="mdi mdi-clock-alert queue-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="queue-card-modern text-white" data-tab-target="express" style="background: linear-gradient(135deg, #38ef7d 0%, #11998e 100%); cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Express (Auto)</h6>
                                <h3 id="express_count">0</h3>
                            </div>
                            <i class="mdi mdi-flash queue-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="queue-card-modern text-white preset-card" data-preset="today_approved" style="background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Approved Today</h6>
                                <h3 id="approved_today_count">0</h3>
                            </div>
                            <i class="mdi mdi-thumb-up queue-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="queue-card-modern text-white preset-card" data-preset="today_rejected" style="background: linear-gradient(135deg, #f45c43 0%, #eb3349 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Rejected Today</h6>
                                <h3 id="rejected_today_count">0</h3>
                            </div>
                            <i class="mdi mdi-thumb-down queue-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="queue-card-modern text-white preset-card" data-preset="overdue" style="background: linear-gradient(135deg, #434343 0%, #000000 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Overdue (>4h)</h6>
                                <h3 id="overdue_count">0</h3>
                            </div>
                            <i class="mdi mdi-alert-circle queue-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="queue-card-modern text-white" id="emergency-hmo-card" style="background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); display: none;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Emergency Patients</h6>
                                <h3 id="emergency_count">0</h3>
                            </div>
                            <i class="fa fa-bolt queue-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="queue-card-modern text-white preset-card" data-preset="high_value" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                    <div class="card-body text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted">High Value (>50k)</h6>
                                <h3>🎯</h3>
                            </div>
                            <i class="mdi mdi-currency-ngn queue-icon" style="opacity: 0.2;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="filter-card-modern mb-4">
            <div class="card-header text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0" style="font-weight: 600;">
                    <i class="mdi mdi-filter-variant mr-2"></i>Filters & Search
                </h6>
                <a href="{{ route('hmo.export-claims') }}" class="btn btn-sm btn-light" id="exportBtn" style="border-radius: 6px;">
                    <i class="mdi mdi-download mr-1"></i>Export Claims
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">Search</label>
                            <input type="text" class="form-control form-control-sm" id="search_input" placeholder="Patient name, file no, HMO no, request ID..." style="border-radius: 6px;">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">HMO</label>
                            <select class="form-control form-control-sm" id="filter_hmo" style="border-radius: 6px;">
                                <option value="">All HMOs</option>
                                @foreach($hmos as $hmo)
                                    <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">Coverage Mode</label>
                            <select class="form-control form-control-sm" id="filter_coverage" style="border-radius: 6px;">
                                <option value="">All Modes</option>
                                <option value="express">Express</option>
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">Service Type</label>
                            <select class="form-control form-control-sm" id="filter_service_type" style="border-radius: 6px;">
                                <option value="">All Types</option>
                                <option value="product">Products</option>
                                <option value="service">Services</option>
                                <option value="procedure">Procedures</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">Date From</label>
                            <input type="date" class="form-control form-control-sm" id="filter_date_from" style="border-radius: 6px;">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">Date To</label>
                            <input type="date" class="form-control form-control-sm" id="filter_date_to" style="border-radius: 6px;">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold text-muted">Validated By</label>
                            <select class="form-control form-control-sm select2" id="filter_validated_by" style="border-radius: 6px;">
                                <option value="">All Validators</option>
                                @foreach($validators as $v)
                                    <option value="{{ $v->id }}">{{ $v->firstname }} {{ $v->surname }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="form-group mb-2">
                            <label class="small">&nbsp;</label>
                            <button type="button" class="btn btn-primary btn-block btn-sm" id="applyFilters" style="border-radius: 6px; background: var(--hospital-primary); border-color: var(--hospital-primary);">
                                <i class="mdi mdi-magnify"></i> Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Batch Actions Bar -->
        <div class="card-modern border-0 mb-3" id="batchActionsBar" style="display:none; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div class="card-body py-2" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="mr-3 font-weight-bold"><span id="selectedCount" class="badge badge-primary" style="border-radius: 6px; font-size: 0.9rem;">0</span> items selected</span>
                        <button type="button" class="btn btn-sm btn-success" id="batchApproveBtn" style="border-radius: 6px;">
                            <i class="mdi mdi-check-all mr-1"></i>Batch Approve
                        </button>
                        <button type="button" class="btn btn-sm btn-danger ml-2" id="batchRejectBtn" style="border-radius: 6px;">
                            <i class="mdi mdi-close-circle-multiple mr-1"></i>Batch Reject
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearSelectionBtn" style="border-radius: 6px;">
                            <i class="mdi mdi-close mr-1"></i>Clear Selection
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs and DataTable -->
        <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div class="card-header bg-white" style="border-radius: 12px 12px 0 0; border-bottom: 1px solid #e9ecef;">
                <ul class="nav nav-tabs card-header-tabs workbench-tabs-modern" id="workbenchTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="pending-tab" data-toggle="tab" href="#pending" role="tab">
                            <i class="mdi mdi-clock-alert mr-1"></i>Pending <span class="badge badge-warning ml-1" id="pending_badge" style="border-radius: 6px;">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="express-tab" data-toggle="tab" href="#express" role="tab">
                            <i class="mdi mdi-flash mr-1"></i>Express <span class="badge badge-success ml-1" id="express_badge" style="border-radius: 6px;">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="approved-tab" data-toggle="tab" href="#approved" role="tab">
                            <i class="mdi mdi-check-circle mr-1"></i>Approved <span class="badge badge-info ml-1" id="approved_badge" style="border-radius: 6px;">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="rejected-tab" data-toggle="tab" href="#rejected" role="tab">
                            <i class="mdi mdi-close-circle mr-1"></i>Rejected <span class="badge badge-danger ml-1" id="rejected_badge" style="border-radius: 6px;">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="claims-tab" data-toggle="tab" href="#claims" role="tab">
                            <i class="mdi mdi-cash mr-1"></i>Claims <span class="badge badge-primary ml-1" id="claims_badge" style="border-radius: 6px;">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="all-tab" data-toggle="tab" href="#all" role="tab">
                            <i class="mdi mdi-view-list mr-1"></i>All <span class="badge badge-secondary ml-1" id="all_badge" style="border-radius: 6px;">0</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="table-responsive">
                        <table id="requestsTable" class="table table-sm table-bordered table-striped table-hover display">
                            <thead>
                                <tr>
                                    <th width="30"><input type="checkbox" id="selectAllCheckbox" title="Select All"></th>
                                    <th>Patient & Actions</th>
                                    <th>Request Info</th>
                                    <th>Item Details</th>
                                    <th>Pricing</th>
                                    <th>Coverage & Payment</th>
                                    <th>Status & Validation</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Include Clinical Context Modal -->
@include('admin.partials.clinical_context_modal')

<!-- View Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header py-2 text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h6 class="modal-title mb-0"><i class="mdi mdi-file-document-outline mr-1"></i>Request <span id="detail_request_id" class="font-weight-bold"></span></h6>
                <div class="ml-auto d-flex align-items-center">
                    <span id="detail_validation_status" class="mr-3"></span>
                    <span id="detail_coverage_mode" class="mr-3"></span>
                    <button type="button" class="close text-white ml-2" data-bs-dismiss="modal"><span>&times;</span></button>
                </div>
            </div>
            <div class="modal-body p-3" style="font-size: 0.88rem;">
                {{-- Row 1: Patient + HMO + Request context --}}
                <div class="row mb-2">
                    {{-- Patient card --}}
                    <div class="col-md-4">
                        <div class="card-modern mb-0 h-100">
                            <div class="card-header py-1 px-2">
                                <strong class="text-primary"><i class="mdi mdi-account mr-1"></i>Patient</strong>
                            </div>
                            <div class="card-body p-2">
                                <p class="mb-1 font-weight-bold" id="detail_patient_name" style="font-size:1rem;"></p>
                                <div class="d-flex flex-wrap" style="gap:4px 12px; font-size:0.82rem;">
                                    <span><i class="mdi mdi-folder-account text-muted"></i> <span id="detail_file_no"></span></span>
                                    <span><i class="mdi mdi-card-account-details text-muted"></i> <span id="detail_hmo_no"></span></span>
                                    <span><i class="mdi mdi-gender-male-female text-muted"></i> <span id="detail_gender"></span></span>
                                    <span><i class="mdi mdi-cake-variant text-muted"></i> <span id="detail_age"></span></span>
                                    <span><i class="mdi mdi-phone text-muted"></i> <span id="detail_phone"></span></span>
                                </div>
                                <div id="detail_allergies_row" class="mt-1" style="display:none;">
                                    <small class="text-danger"><i class="mdi mdi-alert-circle"></i> Allergies: <span id="detail_allergies"></span></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- HMO card --}}
                    <div class="col-md-4">
                        <div class="card-modern mb-0 h-100">
                            <div class="card-header py-1 px-2">
                                <strong class="text-primary"><i class="mdi mdi-hospital-building mr-1"></i>HMO / Scheme</strong>
                            </div>
                            <div class="card-body p-2">
                                <p class="mb-1 font-weight-bold" id="detail_hmo_name"></p>
                                <div style="font-size:0.82rem;">
                                    <span id="detail_scheme_row"><i class="mdi mdi-tag-outline text-muted"></i> Scheme: <span id="detail_hmo_scheme" class="font-weight-bold"></span> <small class="text-muted" id="detail_hmo_scheme_code"></small></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Context card --}}
                    <div class="col-md-4">
                        <div class="card-modern mb-0 h-100">
                            <div class="card-header py-1 px-2">
                                <strong class="text-primary"><i class="mdi mdi-clipboard-text mr-1"></i>Request Context</strong>
                            </div>
                            <div class="card-body p-2" style="font-size:0.82rem;">
                                <div><i class="mdi mdi-calendar text-muted"></i> <span id="detail_created_at"></span></div>
                                <div><i class="mdi mdi-account-tie text-muted"></i> Requested by: <strong id="detail_requested_by"></strong></div>
                                <div id="detail_doctor_row"><i class="mdi mdi-doctor text-muted"></i> Doctor: <strong id="detail_encounter_doctor"></strong></div>
                                <div id="detail_admission_row" style="display:none;"><i class="mdi mdi-bed text-muted"></i> Admitted — <span id="detail_admission_status" class="badge badge-info"></span></div>
                                <div id="detail_procedure_row" style="display:none;"><i class="mdi mdi-medical-bag text-muted"></i> Procedure: <span id="detail_procedure_name"></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Row 2: Item + Pricing --}}
                <div class="card-modern mb-2">
                    <div class="card-header py-1 px-2 d-flex align-items-center">
                        <strong class="text-primary"><i class="mdi mdi-cash-multiple mr-1"></i>Item & Pricing</strong>
                    </div>
                    <div class="card-body p-2">
                        <div class="row mb-2" style="font-size:0.85rem;">
                            <div class="col-md-6">
                                <span class="badge badge-secondary" id="detail_item_type"></span>
                                <span id="detail_category" class="text-muted ml-1" style="font-size:0.8rem;"></span>
                                <div class="font-weight-bold mt-1" id="detail_item_name" style="font-size:0.95rem;"></div>
                                <small class="text-muted" id="detail_item_code"></small>
                            </div>
                            <div class="col-md-6 text-right">
                                <span class="text-muted">Qty:</span> <strong id="detail_qty"></strong>
                                <span class="ml-2 text-muted">Unit Price:</span> <strong>₦<span id="detail_unit_price"></span></strong>
                                <span class="ml-2 text-muted">Total:</span> <strong>₦<span id="detail_total_price"></span></strong>
                            </div>
                        </div>
                        <table class="table table-sm table-bordered mb-0" style="font-size:0.85rem;">
                            <thead class="bg-light">
                                <tr>
                                    <th></th>
                                    <th class="text-center">Per Unit</th>
                                    <th class="text-center">Total (×<span id="detail_qty2"></span>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><i class="mdi mdi-hospital text-info"></i> <strong>HMO Covers (Claims)</strong></td>
                                    <td class="text-center">₦<span id="detail_unit_claims"></span></td>
                                    <td class="text-center font-weight-bold text-info">₦<span id="detail_claims_amount"></span></td>
                                </tr>
                                <tr>
                                    <td><i class="mdi mdi-account-cash text-warning"></i> <strong>Patient Pays</strong></td>
                                    <td class="text-center">₦<span id="detail_unit_payable"></span></td>
                                    <td class="text-center font-weight-bold text-warning">₦<span id="detail_payable_amount"></span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Row 3: Validation + Submission --}}
                <div class="row">
                    <div class="col-md-6">
                        <div class="card-modern mb-0 h-100">
                            <div class="card-header py-1 px-2">
                                <strong class="text-primary"><i class="mdi mdi-check-decagram mr-1"></i>Validation</strong>
                            </div>
                            <div class="card-body p-2" style="font-size:0.85rem;">
                                <div class="mb-1"><strong>Auth Code:</strong> <span id="detail_auth_code">-</span></div>
                                <div class="mb-1"><strong>Validated By:</strong> <span id="detail_validated_by">-</span></div>
                                <div class="mb-1"><strong>Validated At:</strong> <span id="detail_validated_at">-</span></div>
                                <div><strong>Notes:</strong> <span id="detail_validation_notes" class="text-muted">-</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card-modern mb-0 h-100">
                            <div class="card-header py-1 px-2">
                                <strong class="text-primary"><i class="mdi mdi-send mr-1"></i>Submission / Billing</strong>
                            </div>
                            <div class="card-body p-2" style="font-size:0.85rem;">
                                <div class="mb-1"><strong>Payment ID:</strong> <span id="detail_payment_id">-</span></div>
                                <div class="mb-1"><strong>Submitted to HMO:</strong> <span id="detail_submitted_at">-</span></div>
                                <div><strong>Batch:</strong> <span id="detail_batch">-</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Audit trail (collapsible) --}}
                <div class="mt-2">
                    <a data-toggle="collapse" href="#auditTrailCollapse" class="text-muted" style="font-size:0.82rem;">
                        <i class="mdi mdi-history"></i> Audit Trail <i class="mdi mdi-chevron-down"></i>
                    </a>
                    <div class="collapse" id="auditTrailCollapse">
                        <table class="table table-sm table-striped mt-1 mb-0" style="font-size:0.8rem;">
                            <thead><tr><th>Event</th><th>By</th><th>Date</th><th>Changes</th></tr></thead>
                            <tbody id="detail_audit_tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-1" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <h5 class="modal-title"><i class="mdi mdi-check-circle mr-2"></i>Approve Request</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="approveForm">
                @csrf
                <input type="hidden" id="approve_request_id">
                <input type="hidden" id="approve_coverage_mode">
                <div class="modal-body">
                    <div class="alert alert-success" style="border-radius: 8px; border-left: 4px solid #28a745;">
                        <i class="mdi mdi-information mr-1"></i>
                        <strong>Confirm Approval:</strong> You are about to approve this HMO request.
                    </div>
                    <!-- Tariff Edit Section -->
                    <div class="tariff-edit-section mb-3">
                        <div class="card mb-0" style="border-radius: 8px; border: 1px dashed #adb5bd;">
                            <div class="card-header px-3 py-2 tariff-toggle cursor-pointer" style="background: #f8f9fa; border-radius: 8px;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span style="font-size: 0.85rem;">
                                        <i class="mdi mdi-tune-vertical mr-1 text-info"></i>
                                        <strong>Tariff Settings</strong>
                                        <span class="tariff-summary-text text-muted small ml-1"></span>
                                    </span>
                                    <i class="mdi mdi-chevron-down tariff-chevron" style="transition: transform 0.3s;"></i>
                                </div>
                            </div>
                            <div class="tariff-panel" style="display: none;">
                                <div class="card-body px-3 pt-2 pb-3" style="border-top: 1px solid #dee2e6;">
                                    <div class="tariff-loading text-center py-3">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        <span class="ml-2 small text-muted">Loading tariff details...</span>
                                    </div>
                                    <div class="tariff-fields" style="display:none;">
                                        <div class="form-group mb-2">
                                            <label class="small font-weight-bold mb-1">Display Name</label>
                                            <input type="text" class="form-control form-control-sm tariff-display-name" style="border-radius: 6px;">
                                            <small class="form-text text-muted" style="font-size: 0.7rem;">Overrides item name in claims/reports. Leave blank for original.</small>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Coverage Mode</label>
                                                <select class="form-control form-control-sm tariff-coverage-mode" style="border-radius: 6px;">
                                                    <option value="express">Express</option>
                                                    <option value="primary">Primary</option>
                                                    <option value="secondary">Secondary</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Claims Amount</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm tariff-claims-amount" style="border-radius: 6px;">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Payable Amount</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm tariff-payable-amount" style="border-radius: 6px;">
                                            </div>
                                        </div>
                                        <div class="tariff-scheme-option mt-1" style="display:none;">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input tariff-apply-scheme" id="approve_apply_scheme">
                                                <label class="custom-control-label small" for="approve_apply_scheme">
                                                    Apply to all HMOs under <strong class="tariff-scheme-name"></strong>
                                                    (<span class="tariff-scheme-count">0</span> HMOs)
                                                </label>
                                            </div>
                                        </div>
                                        <div class="tariff-current-info mt-2 p-2 small" style="border-radius: 6px; font-size: 0.75rem; background: #eef2ff;">
                                            <i class="mdi mdi-information-outline mr-1 text-info"></i>
                                            <strong>This request:</strong>
                                            Qty: <span class="tariff-current-qty">-</span> |
                                            Claims: ₦<span class="tariff-current-claims">-</span> |
                                            Payable: ₦<span class="tariff-current-payable">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" id="auth_code_div" style="display:none;">
                        <label class="font-weight-bold">Authorization Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="auth_code" name="auth_code" placeholder="Enter HMO auth code" style="border-radius: 6px;">
                        <small class="form-text text-danger" id="error_auth_code"></small>
                        <small class="form-text text-muted">Required for secondary coverage</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Validation Notes</label>
                        <textarea class="form-control" id="approve_notes" name="validation_notes" rows="3" placeholder="Optional notes..." style="border-radius: 6px;"></textarea>
                        <small class="form-text text-danger" id="error_validation_notes"></small>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" style="border-radius: 6px;">
                        <i class="mdi mdi-check mr-1"></i>Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
                <h5 class="modal-title"><i class="mdi mdi-close-circle mr-2"></i>Reject Request</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="rejectForm">
                @csrf
                <input type="hidden" id="reject_request_id">
                <div class="modal-body">
                    <div class="alert alert-warning" style="border-radius: 8px; border-left: 4px solid #ffc107;">
                        <i class="mdi mdi-alert mr-1"></i>
                        <strong>Confirm Rejection:</strong> You are about to reject this HMO request.
                    </div>
                    <!-- Tariff Edit Section -->
                    <div class="tariff-edit-section mb-3">
                        <div class="card mb-0" style="border-radius: 8px; border: 1px dashed #adb5bd;">
                            <div class="card-header px-3 py-2 tariff-toggle cursor-pointer" style="background: #f8f9fa; border-radius: 8px;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span style="font-size: 0.85rem;">
                                        <i class="mdi mdi-tune-vertical mr-1 text-info"></i>
                                        <strong>Tariff Settings</strong>
                                        <span class="tariff-summary-text text-muted small ml-1"></span>
                                    </span>
                                    <i class="mdi mdi-chevron-down tariff-chevron" style="transition: transform 0.3s;"></i>
                                </div>
                            </div>
                            <div class="tariff-panel" style="display: none;">
                                <div class="card-body px-3 pt-2 pb-3" style="border-top: 1px solid #dee2e6;">
                                    <div class="tariff-loading text-center py-3">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        <span class="ml-2 small text-muted">Loading tariff details...</span>
                                    </div>
                                    <div class="tariff-fields" style="display:none;">
                                        <div class="form-group mb-2">
                                            <label class="small font-weight-bold mb-1">Display Name</label>
                                            <input type="text" class="form-control form-control-sm tariff-display-name" style="border-radius: 6px;">
                                            <small class="form-text text-muted" style="font-size: 0.7rem;">Overrides item name in claims/reports. Leave blank for original.</small>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Coverage Mode</label>
                                                <select class="form-control form-control-sm tariff-coverage-mode" style="border-radius: 6px;">
                                                    <option value="express">Express</option>
                                                    <option value="primary">Primary</option>
                                                    <option value="secondary">Secondary</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Claims Amount</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm tariff-claims-amount" style="border-radius: 6px;">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Payable Amount</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm tariff-payable-amount" style="border-radius: 6px;">
                                            </div>
                                        </div>
                                        <div class="tariff-scheme-option mt-1" style="display:none;">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input tariff-apply-scheme" id="reject_apply_scheme">
                                                <label class="custom-control-label small" for="reject_apply_scheme">
                                                    Apply to all HMOs under <strong class="tariff-scheme-name"></strong>
                                                    (<span class="tariff-scheme-count">0</span> HMOs)
                                                </label>
                                            </div>
                                        </div>
                                        <div class="tariff-current-info mt-2 p-2 small" style="border-radius: 6px; font-size: 0.75rem; background: #eef2ff;">
                                            <i class="mdi mdi-information-outline mr-1 text-info"></i>
                                            <strong>This request:</strong>
                                            Qty: <span class="tariff-current-qty">-</span> |
                                            Claims: ₦<span class="tariff-current-claims">-</span> |
                                            Payable: ₦<span class="tariff-current-payable">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Rejection Reason <span class="text-danger">*</span></label>
                        <select class="form-control" id="rejection_reason" name="rejection_reason" required style="border-radius: 6px;">
                            <option value="">-- Select Reason --</option>
                            @foreach($rejectionReasons as $key => $reason)
                                <option value="{{ $key }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-danger" id="error_rejection_reason"></small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Additional Notes</label>
                        <textarea class="form-control" id="reject_notes" name="validation_notes" rows="3" placeholder="Optional additional notes..." style="border-radius: 6px;"></textarea>
                        <small class="form-text text-danger" id="error_reject_notes"></small>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="border-radius: 6px;">
                        <i class="mdi mdi-close mr-1"></i>Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reverse Approval Modal -->
<div class="modal fade" id="reverseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-dark" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                <h5 class="modal-title"><i class="mdi mdi-undo mr-2"></i>Reverse Approval</h5>
                <button type="button" class="close"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="reverseForm">
                @csrf
                <input type="hidden" id="reverse_request_id">
                <div class="modal-body">
                    <div class="alert alert-warning" style="border-radius: 8px; border-left: 4px solid #ffc107;">
                        <i class="mdi mdi-alert-circle mr-1"></i>
                        <strong>⚠️ Warning:</strong> You are about to reverse this approval and set the request back to pending.
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Reason for Reversal <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reverse_reason" name="reason" rows="3" placeholder="Please provide reason for reversing this approval..." required style="border-radius: 6px;"></textarea>
                        <small class="form-text text-danger" id="error_reverse_reason"></small>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" style="border-radius: 6px;">
                        <i class="mdi mdi-undo mr-1"></i>Reverse to Pending
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Re-approve Modal -->
<div class="modal fade" id="reapproveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <h5 class="modal-title"><i class="mdi mdi-check-decagram mr-2"></i>Re-approve Request</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="reapproveForm">
                @csrf
                <input type="hidden" id="reapprove_request_id">
                <input type="hidden" id="reapprove_coverage_mode">
                <div class="modal-body">
                    <div class="alert alert-success" style="border-radius: 8px; border-left: 4px solid #28a745;">
                        <i class="mdi mdi-information mr-1"></i>
                        <strong>Re-approve:</strong> You are about to re-approve a previously rejected request.
                    </div>
                    <!-- Tariff Edit Section -->
                    <div class="tariff-edit-section mb-3">
                        <div class="card mb-0" style="border-radius: 8px; border: 1px dashed #adb5bd;">
                            <div class="card-header px-3 py-2 tariff-toggle cursor-pointer" style="background: #f8f9fa; border-radius: 8px;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span style="font-size: 0.85rem;">
                                        <i class="mdi mdi-tune-vertical mr-1 text-info"></i>
                                        <strong>Tariff Settings</strong>
                                        <span class="tariff-summary-text text-muted small ml-1"></span>
                                    </span>
                                    <i class="mdi mdi-chevron-down tariff-chevron" style="transition: transform 0.3s;"></i>
                                </div>
                            </div>
                            <div class="tariff-panel" style="display: none;">
                                <div class="card-body px-3 pt-2 pb-3" style="border-top: 1px solid #dee2e6;">
                                    <div class="tariff-loading text-center py-3">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        <span class="ml-2 small text-muted">Loading tariff details...</span>
                                    </div>
                                    <div class="tariff-fields" style="display:none;">
                                        <div class="form-group mb-2">
                                            <label class="small font-weight-bold mb-1">Display Name</label>
                                            <input type="text" class="form-control form-control-sm tariff-display-name" style="border-radius: 6px;">
                                            <small class="form-text text-muted" style="font-size: 0.7rem;">Overrides item name in claims/reports. Leave blank for original.</small>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Coverage Mode</label>
                                                <select class="form-control form-control-sm tariff-coverage-mode" style="border-radius: 6px;">
                                                    <option value="express">Express</option>
                                                    <option value="primary">Primary</option>
                                                    <option value="secondary">Secondary</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Claims Amount</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm tariff-claims-amount" style="border-radius: 6px;">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="small font-weight-bold mb-1">Payable Amount</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm tariff-payable-amount" style="border-radius: 6px;">
                                            </div>
                                        </div>
                                        <div class="tariff-scheme-option mt-1" style="display:none;">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input tariff-apply-scheme" id="reapprove_apply_scheme">
                                                <label class="custom-control-label small" for="reapprove_apply_scheme">
                                                    Apply to all HMOs under <strong class="tariff-scheme-name"></strong>
                                                    (<span class="tariff-scheme-count">0</span> HMOs)
                                                </label>
                                            </div>
                                        </div>
                                        <div class="tariff-current-info mt-2 p-2 small" style="border-radius: 6px; font-size: 0.75rem; background: #eef2ff;">
                                            <i class="mdi mdi-information-outline mr-1 text-info"></i>
                                            <strong>This request:</strong>
                                            Qty: <span class="tariff-current-qty">-</span> |
                                            Claims: ₦<span class="tariff-current-claims">-</span> |
                                            Payable: ₦<span class="tariff-current-payable">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" id="reapprove_auth_code_div" style="display:none;">
                        <label class="font-weight-bold">Authorization Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="reapprove_auth_code" name="auth_code" placeholder="Enter HMO auth code" style="border-radius: 6px;">
                        <small class="form-text text-danger" id="error_reapprove_auth_code"></small>
                        <small class="form-text text-muted">Required for secondary coverage</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Validation Notes</label>
                        <textarea class="form-control" id="reapprove_notes" name="validation_notes" rows="3" placeholder="Optional notes for re-approval..." style="border-radius: 6px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" style="border-radius: 6px;">
                        <i class="mdi mdi-check mr-1"></i>Re-approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Batch Approve Modal -->
<div class="modal fade" id="batchApproveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <h5 class="modal-title"><i class="mdi mdi-check-all mr-2"></i>Batch Approve Requests</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="batchApproveForm">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-success" style="border-radius: 8px; border-left: 4px solid #28a745;">
                        <i class="mdi mdi-information mr-1"></i>
                        <strong>Batch Approve:</strong> You are about to approve <strong><span id="batchApproveCount">0</span></strong> requests.
                        <br><small class="text-warning"><i class="mdi mdi-alert mr-1"></i>Secondary coverage requests will be skipped (require individual auth codes).</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Validation Notes (applied to all)</label>
                        <textarea class="form-control" name="validation_notes" rows="3" placeholder="Optional notes..." style="border-radius: 6px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" style="border-radius: 6px;">
                        <i class="mdi mdi-check-all mr-1"></i>Approve All
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Batch Reject Modal -->
<div class="modal fade" id="batchRejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
                <h5 class="modal-title"><i class="mdi mdi-close-circle-multiple mr-2"></i>Batch Reject Requests</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="batchRejectForm">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning" style="border-radius: 8px; border-left: 4px solid #ffc107;">
                        <i class="mdi mdi-alert mr-1"></i>
                        <strong>Batch Reject:</strong> You are about to reject <strong><span id="batchRejectCount">0</span></strong> requests.
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Rejection Reason <span class="text-danger">*</span></label>
                        <select class="form-control" name="rejection_reason" required style="border-radius: 6px;">
                            <option value="">-- Select Reason --</option>
                            @foreach($rejectionReasons as $key => $reason)
                                <option value="{{ $key }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Additional Notes</label>
                        <textarea class="form-control" name="validation_notes" rows="3" placeholder="Optional additional notes..." style="border-radius: 6px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="border-radius: 6px;">
                        <i class="mdi mdi-close mr-1"></i>Reject All
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Validate by Group Modal -->
<div class="modal fade" id="validateGroupModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div>
                    <h5 class="modal-title mb-0"><i class="mdi mdi-account-check-outline mr-2"></i>Validate Patient Requests — <span id="vg_patient_name"></span></h5>
                    <small id="vg_patient_subtitle" class="text-white-50"></small>
                </div>
                <button type="button" class="close text-white" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-3">
                {{-- Summary bar --}}
                <div class="card card-modern mb-3">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <div>
                                <span class="badge badge-warning mr-1" id="vg_primary_badge">0 Primary</span>
                                <span class="badge badge-danger mr-1" id="vg_secondary_badge">0 Secondary</span>
                                <span class="text-muted small ml-2">Total: <strong id="vg_total_count">0</strong> pending</span>
                            </div>
                            <div class="text-right">
                                <span class="small text-muted">Claims: </span><strong class="text-success" id="vg_total_claims">₦0</strong>
                                <span class="small text-muted ml-2">Payable: </span><strong class="text-info" id="vg_total_payable">₦0</strong>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Grouping toolbar --}}
                <div class="d-flex flex-wrap align-items-center mb-3 gap-2">
                    <span class="small font-weight-bold mr-2">Group By:</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary active" id="vg_group_encounter">
                            <i class="mdi mdi-stethoscope mr-1"></i>Encounter
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="vg_group_date">
                            <i class="mdi mdi-calendar-range mr-1"></i>Date Range
                        </button>
                    </div>
                    <div id="vg_date_range_controls" class="ml-2" style="display:none;">
                        <select class="form-control form-control-sm" id="vg_date_filter" style="width:160px; border-radius:6px;">
                            <option value="all">All Dates</option>
                            <option value="today">Today</option>
                            <option value="3days">Last 3 Days</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                        </select>
                    </div>
                    <div class="ml-auto">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="vg_select_all_btn">
                            <i class="mdi mdi-checkbox-multiple-marked-outline mr-1"></i>Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="vg_deselect_all_btn">
                            <i class="mdi mdi-checkbox-multiple-blank-outline mr-1"></i>Deselect All
                        </button>
                    </div>
                </div>

                {{-- Loading spinner --}}
                <div id="vg_loading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2 small">Loading patient requests...</p>
                </div>

                {{-- Empty state --}}
                <div id="vg_empty" class="text-center py-5" style="display:none;">
                    <i class="mdi mdi-check-circle-outline text-success" style="font-size:48px;"></i>
                    <p class="text-muted mt-2">No pending requests for this patient.</p>
                </div>

                {{-- Request groups container --}}
                <div id="vg_groups_container"></div>

                {{-- Auth code section --}}
                <div id="vg_auth_section" class="card card-modern mt-3" style="display:none;">
                    <div class="card-header py-2 px-3">
                        <strong class="small"><i class="mdi mdi-key-variant mr-1"></i>Authorization Codes</strong>
                        <span class="badge badge-danger ml-1" id="vg_secondary_selected_count">0</span>
                        <span class="small text-muted"> secondary items selected — auth code required</span>
                    </div>
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="custom-control custom-radio mr-3">
                                <input type="radio" class="custom-control-input" id="vg_auth_shared" name="vg_auth_mode" value="shared" checked>
                                <label class="custom-control-label small" for="vg_auth_shared">One code for all secondary items</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" class="custom-control-input" id="vg_auth_individual" name="vg_auth_mode" value="individual">
                                <label class="custom-control-label small" for="vg_auth_individual">Individual codes per item</label>
                            </div>
                        </div>
                        <div id="vg_shared_auth_input">
                            <input type="text" class="form-control form-control-sm" id="vg_shared_auth_code" placeholder="Enter shared authorization code" style="border-radius:6px; max-width:400px;">
                        </div>
                    </div>
                </div>

                {{-- Validation notes --}}
                <div class="form-group mt-3 mb-0">
                    <label class="small font-weight-bold">Validation Notes (Optional)</label>
                    <textarea class="form-control form-control-sm" id="vg_validation_notes" rows="2" placeholder="Applied to all approved/rejected items..." style="border-radius:6px;"></textarea>
                </div>

                {{-- Reject inline section (hidden) --}}
                <div id="vg_reject_section" class="card border-danger mt-3" style="display:none;">
                    <div class="card-header py-2 px-3 bg-danger text-white">
                        <strong class="small"><i class="mdi mdi-close-circle mr-1"></i>Reject Selected Items</strong>
                    </div>
                    <div class="card-body py-2 px-3">
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold">Rejection Reason <span class="text-danger">*</span></label>
                            <select class="form-control form-control-sm" id="vg_rejection_reason" style="border-radius:6px;">
                                <option value="">Select reason...</option>
                                @foreach($rejectionReasons as $key => $reason)
                                    <option value="{{ $key }}">{{ $reason }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold">Additional Notes</label>
                            <textarea class="form-control form-control-sm" id="vg_reject_notes" rows="2" placeholder="Optional..." style="border-radius:6px;"></textarea>
                        </div>
                        <div class="text-right">
                            <button type="button" class="btn btn-sm btn-outline-secondary mr-1" id="vg_cancel_reject_btn">Cancel</button>
                            <button type="button" class="btn btn-sm btn-danger" id="vg_confirm_reject_btn">
                                <i class="mdi mdi-close-circle mr-1"></i>Confirm Reject (<span class="vg-selected-count">0</span>)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                <div class="d-flex align-items-center mr-auto">
                    <span class="small text-muted">Selected: <strong class="vg-selected-count">0</strong> of <strong id="vg_footer_total">0</strong></span>
                    <span class="small text-muted ml-3">Claims: <strong class="text-success vg-selected-claims">₦0</strong></span>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" style="border-radius:6px;">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="vg_reject_btn" disabled style="border-radius:6px;">
                    <i class="mdi mdi-close-circle mr-1"></i>Reject (<span class="vg-selected-count">0</span>)
                </button>
                <button type="button" class="btn btn-success btn-sm" id="vg_approve_btn" disabled style="border-radius:6px;">
                    <i class="mdi mdi-check-all mr-1"></i>Approve (<span class="vg-selected-count">0</span>)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Patient History Modal -->
<div class="modal fade" id="patientHistoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="modal-title"><i class="mdi mdi-history mr-2"></i>Patient HMO History</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card-modern text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="card-body text-center py-3">
                                <h6 class="text-white-50 mb-1">Total HMO Claims</h6>
                                <h3 class="mb-0" id="history_total_claims" style="font-weight: 700;">₦0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-modern text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <div class="card-body text-center py-3">
                                <h6 class="text-white-50 mb-1">This Month Claims</h6>
                                <h3 class="mb-0" id="history_month_claims" style="font-weight: 700;">₦0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-modern text-white" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                            <div class="card-body text-center py-3">
                                <h6 class="text-white-50 mb-1">Total HMO Visits</h6>
                                <h3 class="mb-0" id="history_total_visits" style="font-weight: 700;">0</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="historyTable" style="border-radius: 8px;">
                        <thead class="bg-light">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Item</th>
                                <th>Coverage</th>
                                <th>Claims</th>
                                <th>Payable</th>
                                <th>Status</th>
                                <th>Validated By</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <tr><td colspan="8" class="text-center text-muted py-4"><i class="mdi mdi-loading mdi-spin mr-2"></i>Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary" style="border-radius: 6px;" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<script>
$(function() {
    // Toastr options
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: 'toast-top-right',
        timeOut: 4000,
        extendedTimeOut: 2000,
        showEasing: 'swing',
        hideEasing: 'linear',
        showMethod: 'fadeIn',
        hideMethod: 'fadeOut'
    };

    let currentTab = 'pending';
    let currentPreset = '';
    let table;
    let selectedIds = [];

    // Initialize DataTable
    function initDataTable() {
        if (table) {
            table.destroy();
        }

        table = $('#requestsTable').DataTable({
            "dom": 'Bfrtip',
            "iDisplayLength": 50,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "{{ route('hmo.requests') }}",
                "type": "GET",
                "data": function(d) {
                    d.tab = currentTab;
                    d.preset = currentPreset;
                    d.hmo_id = $('#filter_hmo').val();
                    d.coverage_mode = $('#filter_coverage').val();
                    d.service_type = $('#filter_service_type').val();
                    d.date_from = $('#filter_date_from').val();
                    d.date_to = $('#filter_date_to').val();
                    d.validated_by = $('#filter_validated_by').val();
                    d.search = $('#search_input').val();
                }
            },
            "columns": [
                { "data": "checkbox", "orderable": false, "searchable": false },
                { "data": "patient_info", "orderable": false, "searchable": false },
                { "data": "request_info", "orderable": false },
                { "data": "item_details", "orderable": false },
                { "data": "pricing_info", "orderable": false },
                { "data": "coverage_payment", "orderable": false },
                { "data": "status_validation", "orderable": false }
            ],
            "order": [[2, 'desc']],
            "drawCallback": function() {
                // Update checkbox states after redraw
                updateCheckboxStates();
            }
        });
    }

    // Load queue counts
    function loadQueueCounts() {
        $.get("{{ route('hmo.queue-counts') }}", function(data) {
            // Update stat cards
            $('#pending_count').text(data.pending || 0);
            $('#express_count').text(data.express || 0);
            $('#approved_today_count').text(data.approved_today || 0);
            $('#rejected_today_count').text(data.rejected_today || 0);
            $('#overdue_count').text(data.overdue || 0);

            // Emergency count
            var emergencyCount = data.emergency || 0;
            $('#emergency_count').text(emergencyCount);
            if (emergencyCount > 0) {
                $('#emergency-hmo-card').show();
            } else {
                $('#emergency-hmo-card').hide();
            }

            // Update tab badges
            $('#pending_badge').text(data.pending || 0);
            $('#express_badge').text(data.express || 0);
            $('#approved_badge').text(data.approved || 0);
            $('#rejected_badge').text(data.rejected || 0);
            $('#claims_badge').text(data.claims || 0);
            $('#all_badge').text(data.all || 0);
        }).fail(function(xhr) {
            console.error('Failed to load queue counts:', xhr);
        });
    }

    // Load financial summary
    function loadFinancialSummary() {
        $.get("{{ route('hmo.financial-summary') }}", function(data) {
            $('#pending_claims_total').text('₦' + formatNumber(data.pending_claims_total));
            $('#approved_today_total').text('₦' + formatNumber(data.approved_today_total));
            $('#rejected_today_total').text('₦' + formatNumber(data.rejected_today_total));
            $('#monthly_claims_total').text('₦' + formatNumber(data.monthly_claims_total));
        });
    }

    function formatNumber(num) {
        return parseFloat(num || 0).toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    // Update batch action bar
    function updateBatchActionBar() {
        $('#selectedCount').text(selectedIds.length);
        if (selectedIds.length > 0) {
            $('#batchActionsBar').slideDown();
        } else {
            $('#batchActionsBar').slideUp();
        }
    }

    function updateCheckboxStates() {
        $('.batch-select-checkbox').each(function() {
            let id = $(this).data('id');
            $(this).prop('checked', selectedIds.includes(id));
        });
    }

    // Initial load
    initDataTable();
    loadQueueCounts();
    loadFinancialSummary();

    // Auto-open queue/tab from URL parameter (e.g., from dashboard queue widget click)
    const urlParams = new URLSearchParams(window.location.search);
    const queueFilter = urlParams.get('queue_filter');
    if (queueFilter && ['pending', 'approved', 'rejected'].includes(queueFilter)) {
        currentTab = queueFilter;
        $('#workbenchTabs a.nav-link').removeClass('active');
        $('#' + queueFilter + '-tab').addClass('active');
        if (table) { table.ajax.reload(); }
    }

    // Tab change - using direct click handler for reliability
    $('#workbenchTabs a.nav-link').on('click', function (e) {
        e.preventDefault();

        // Update active state
        $('#workbenchTabs a.nav-link').removeClass('active');
        $(this).addClass('active');

        // Get tab name from href
        currentTab = $(this).attr('href').substring(1);
        currentPreset = '';
        selectedIds = [];
        updateBatchActionBar();

        console.log('Tab changed to:', currentTab); // Debug

        // Reload DataTable with new tab filter
        if (table) {
            table.ajax.reload();
        }
    });

    // Preset card click
    $('.preset-card').on('click', function() {
        currentPreset = $(this).data('preset');
        if (currentPreset) {
            $('.preset-card').removeClass('border border-dark border-3');
            $(this).addClass('border border-dark border-3');
        }
        table.ajax.reload();
    });

    // Stat card → switch to its corresponding tab
    $('[data-tab-target]').on('click', function() {
        var targetTab = $(this).data('tab-target');
        if (targetTab) {
            currentTab = targetTab;
            currentPreset = '';
            $('#workbenchTabs a.nav-link').removeClass('active');
            $('#' + targetTab + '-tab').addClass('active');
            if (table) { table.ajax.reload(); }
        }
    });

    // Apply filters
    $('#applyFilters, #search_input').on('click keyup', function(e) {
        if (e.type === 'click' || (e.type === 'keyup' && e.keyCode === 13)) {
            table.ajax.reload();
        }
    });

    // Select all checkbox
    $('#selectAllCheckbox').on('change', function() {
        let checked = $(this).prop('checked');
        $('.batch-select-checkbox').each(function() {
            let id = $(this).data('id');
            $(this).prop('checked', checked);
            if (checked && !selectedIds.includes(id)) {
                selectedIds.push(id);
            } else if (!checked) {
                selectedIds = selectedIds.filter(i => i !== id);
            }
        });
        updateBatchActionBar();
    });

    // Individual checkbox
    $(document).on('change', '.batch-select-checkbox', function() {
        let id = $(this).data('id');
        if ($(this).prop('checked')) {
            if (!selectedIds.includes(id)) {
                selectedIds.push(id);
            }
        } else {
            selectedIds = selectedIds.filter(i => i !== id);
        }
        updateBatchActionBar();
    });

    // Clear selection
    $('#clearSelectionBtn').on('click', function() {
        selectedIds = [];
        $('.batch-select-checkbox').prop('checked', false);
        $('#selectAllCheckbox').prop('checked', false);
        updateBatchActionBar();
    });

    // View details
    $(document).on('click', '.view-details-btn', function() {
        let id = $(this).data('id');

        $.get("{{ url('hmo/requests') }}/" + id, function(response) {
            let d = response.data;

            // Header
            $('#detail_request_id').text('#' + d.id);

            let statusBadge = d.validation_status === 'approved' ? '<span class="badge badge-success">APPROVED</span>' :
                             d.validation_status === 'rejected' ? '<span class="badge badge-danger">REJECTED</span>' :
                             '<span class="badge badge-warning">PENDING</span>';
            $('#detail_validation_status').html(statusBadge);

            let coverageBadge = d.coverage_mode === 'express' ? '<span class="badge badge-success">EXPRESS</span>' :
                               d.coverage_mode === 'primary' ? '<span class="badge badge-warning">PRIMARY</span>' :
                               '<span class="badge badge-danger">SECONDARY</span>';
            $('#detail_coverage_mode').html(coverageBadge);

            // Patient
            $('#detail_patient_name').text(d.patient_name);
            $('#detail_file_no').text(d.file_no);
            $('#detail_hmo_no').text(d.hmo_no || 'N/A');
            $('#detail_gender').text(d.gender || 'N/A');
            $('#detail_age').text(d.age || 'N/A');
            $('#detail_phone').text(d.phone || 'N/A');

            if (d.allergies && (Array.isArray(d.allergies) ? d.allergies.length : String(d.allergies).trim())) {
                $('#detail_allergies').text(Array.isArray(d.allergies) ? d.allergies.join(', ') : d.allergies);
                $('#detail_allergies_row').show();
            } else {
                $('#detail_allergies_row').hide();
            }

            // HMO
            $('#detail_hmo_name').text(d.hmo_name);
            if (d.hmo_scheme) {
                $('#detail_hmo_scheme').text(d.hmo_scheme);
                $('#detail_hmo_scheme_code').text(d.hmo_scheme_code ? '(' + d.hmo_scheme_code + ')' : '');
                $('#detail_scheme_row').show();
            } else {
                $('#detail_scheme_row').hide();
            }

            // Context
            $('#detail_created_at').text(d.created_at || 'N/A');
            $('#detail_requested_by').text(d.requested_by || 'N/A');
            if (d.encounter_doctor) {
                $('#detail_encounter_doctor').text(d.encounter_doctor);
                $('#detail_doctor_row').show();
            } else {
                $('#detail_doctor_row').hide();
            }
            if (d.is_admitted) {
                $('#detail_admission_status').text(d.admission_status || 'admitted');
                $('#detail_admission_row').show();
            } else {
                $('#detail_admission_row').hide();
            }
            if (d.procedure_name) {
                $('#detail_procedure_name').text(d.procedure_name);
                $('#detail_procedure_row').show();
            } else {
                $('#detail_procedure_row').hide();
            }

            // Item & Pricing
            $('#detail_item_type').text(d.item_type);
            $('#detail_item_name').text(d.item_name);
            $('#detail_item_code').text(d.item_code || '');
            $('#detail_category').text(d.category ? '/ ' + d.category : '');
            $('#detail_qty').text(d.qty);
            $('#detail_qty2').text(d.qty);
            $('#detail_unit_price').text(formatNumber(d.unit_price || 0));
            $('#detail_total_price').text(formatNumber(d.total_price || 0));
            $('#detail_unit_claims').text(formatNumber(d.unit_claims || 0));
            $('#detail_unit_payable').text(formatNumber(d.unit_payable || 0));
            $('#detail_claims_amount').text(formatNumber(d.claims_amount || 0));
            $('#detail_payable_amount').text(formatNumber(d.payable_amount || 0));

            // Validation
            $('#detail_auth_code').text(d.auth_code || '-');
            $('#detail_validated_by').text(d.validated_by_name || '-');
            $('#detail_validated_at').text(d.validated_at || '-');
            $('#detail_validation_notes').text(d.validation_notes || '-');

            // Submission
            $('#detail_payment_id').text(d.payment_id || '-');
            $('#detail_submitted_at').text(d.submitted_to_hmo_at || '-');
            $('#detail_batch').text(d.hmo_submission_batch || '-');

            // Audit trail
            let $tbody = $('#detail_audit_tbody').empty();
            if (d.audits && d.audits.length) {
                d.audits.forEach(function(a) {
                    let changes = [];
                    if (a.new_values) {
                        Object.keys(a.new_values).forEach(function(k) {
                            let oldVal = a.old_values && a.old_values[k] !== undefined ? a.old_values[k] : '—';
                            changes.push(k + ': ' + oldVal + ' → ' + a.new_values[k]);
                        });
                    }
                    $tbody.append('<tr><td>' + a.event + '</td><td>' + a.user + '</td><td>' + a.created_at + '</td><td><small>' + changes.join('<br>') + '</small></td></tr>');
                });
            } else {
                $tbody.append('<tr><td colspan="4" class="text-muted text-center">No audit records</td></tr>');
            }

            // Collapse audit trail by default
            $('#auditTrailCollapse').collapse('hide');

            $('#detailsModal').modal('show');
        });
    });

    // Clinical context button
    $(document).on('click', '.clinical-context-btn', function() {
        let patientId = $(this).data('patient-id');
        loadClinicalContext(patientId);
    });

    // Manual tab switching for clinical context modal (BS5 data-bs-toggle not functional with BS4 runtime)
    let clinicalTabsInitialized = false;
    function initClinicalModalTabs() {
        if (clinicalTabsInitialized) return;
        clinicalTabsInitialized = true;

        $('#clinical-context-modal').on('click', '#clinical-tabs .nav-link', function(e) {
            e.preventDefault();
            var $this = $(this);
            var target = $this.attr('data-bs-target') || $this.attr('href');
            if (!target) return;

            // Deactivate all tabs and panes
            $('#clinical-tabs .nav-link').removeClass('active').attr('aria-selected', 'false');
            $('#clinical-tab-content .tab-pane').removeClass('show active');

            // Activate clicked tab and target pane
            $this.addClass('active').attr('aria-selected', 'true');
            $(target).addClass('show active');

            // Fire shown.bs.tab so the shared modal IIFE data loaders trigger
            $this.trigger('shown.bs.tab');
        });

        // Also handle inner sub-tabs (e.g. injection/immunization sub-tabs) that use data-bs-toggle
        $('#clinical-context-modal').on('click', '.tab-content [data-bs-toggle="tab"]', function(e) {
            e.preventDefault();
            var $this = $(this);
            var target = $this.attr('data-bs-target') || $this.attr('href');
            if (!target) return;

            var $tabList = $this.closest('.nav-tabs');
            var $tabContent = $tabList.next('.tab-content');
            if (!$tabContent.length) $tabContent = $tabList.siblings('.tab-content');

            $tabList.find('.nav-link').removeClass('active');
            $tabContent.find('.tab-pane').removeClass('show active');

            $this.addClass('active');
            $(target).addClass('show active');

            $this.trigger('shown.bs.tab');
        });
    }

    function loadClinicalContext(patientId) {
        // Store patient ID for see more buttons and shared modal tab loaders
        window.currentClinicalPatientId = patientId;
        window.currentPatient = patientId;
        $('#clinical-context-modal').data('patient-id', patientId);

        // Initialize manual tab switching (once)
        initClinicalModalTabs();

        // Show modal first with loading state
        $('#clinical-context-modal').modal('show');

        // Load vitals using DataTable rendering (nursing workbench approach)
        $.get("{{ url('hmo/patient') }}/" + patientId + "/vitals", function(vitals) {
            hmoDisplayVitals(vitals, patientId);
        }).fail(function() {
            $('#vitals-panel-body').html('<div class="alert alert-danger">Failed to load vitals</div>');
        });

        // Load medications using card rendering (nursing workbench approach)
        $.get("{{ url('hmo/patient') }}/" + patientId + "/medications", function(meds) {
            hmoDisplayMedications(meds, patientId);
        }).fail(function() {
            $('#clinical-meds-container').html('<div class="alert alert-danger"><i class="mdi mdi-alert-circle"></i> Failed to load medications</div>');
        });

        // Encounter notes, Injection/Immunization, and Procedures are loaded
        // by the shared clinical_context_modal IIFE handlers when their tabs are clicked.

        // Load allergies (nursing workbench approach)
        $.get("{{ url('hmo/patient') }}/" + patientId + "/allergies", function(data) {
            hmoDisplayAllergies(data);
        }).fail(function() {
            $('#allergies-panel-body').html('<div class="alert alert-danger"><i class="mdi mdi-alert-circle"></i> Failed to load allergy information</div>');
        });
    }

    // Refresh handler for allergies tab
    $(document).on('click', '.refresh-clinical-btn[data-panel="allergies"]', function() {
        var patientId = window.currentPatient || window.currentClinicalPatientId;
        if (!patientId) return;
        $('#allergies-list').html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i></div>');
        $.get("{{ url('hmo/patient') }}/" + patientId + "/allergies", function(data) {
            hmoDisplayAllergies(data);
        }).fail(function() {
            $('#allergies-list').html('<div class="alert alert-danger"><i class="mdi mdi-alert-circle"></i> Failed to load allergy information</div>');
        });
    });

    // Vitals rendering using DataTable (nursing workbench approach)
    function hmoDisplayVitals(vitals, patientId) {
        if (typeof $.fn.DataTable === 'undefined') {
            $('#vitals-panel-body').html('<p class="text-danger">Error: DataTables library not loaded</p>');
            return;
        }

        // Restore the table structure if previously overwritten
        $('#vitals-panel-body').html('<div class="table-responsive"><table class="table" id="vitals-table" style="width: 100%"><thead><tr><th>Vital Signs History</th></tr></thead></table></div>');

        if ($.fn.DataTable.isDataTable('#vitals-table')) {
            $('#vitals-table').DataTable().destroy();
        }

        $('#vitals-table').DataTable({
            data: vitals,
            paging: false,
            searching: false,
            info: false,
            ordering: false,
            dom: 't',
            language: {
                emptyTable: '<p class="text-muted">No recent vitals recorded</p>'
            },
            columns: [{
                data: null,
                render: function(data, type, row) {
                    var vitalDate = hmoFormatDateTime(row.time_taken || row.created_at);
                    var temp = row.temp || 'N/A';
                    var heartRate = row.heart_rate || 'N/A';
                    var bp = row.blood_pressure || 'N/A';
                    var respRate = row.resp_rate || 'N/A';
                    var weight = row.weight || 'N/A';

                    return `
                        <div class="vital-entry">
                            <div class="vital-entry-header">
                                <span class="vital-date">${vitalDate}</span>
                            </div>
                            <div class="vital-entry-grid">
                                <div class="vital-item ${hmoGetTempClass(temp)}">
                                    <i class="mdi mdi-thermometer"></i>
                                    <span class="vital-value">${temp}°C</span>
                                    <span class="vital-label">Temp</span>
                                </div>
                                <div class="vital-item ${hmoGetHeartRateClass(heartRate)}">
                                    <i class="mdi mdi-heart-pulse"></i>
                                    <span class="vital-value">${heartRate}</span>
                                    <span class="vital-label">Heart Rate</span>
                                </div>
                                <div class="vital-item ${hmoGetBPClass(bp)}">
                                    <i class="mdi mdi-water"></i>
                                    <span class="vital-value">${bp}</span>
                                    <span class="vital-label">BP (mmHg)</span>
                                </div>
                                <div class="vital-item ${hmoGetRespRateClass(respRate)}">
                                    <i class="mdi mdi-lungs"></i>
                                    <span class="vital-value">${respRate}</span>
                                    <span class="vital-label">Resp Rate (BPM)</span>
                                </div>
                                <div class="vital-item">
                                    <i class="mdi mdi-weight-kilogram"></i>
                                    <span class="vital-value">${weight}</span>
                                    <span class="vital-label">Weight (Kg)</span>
                                </div>
                            </div>
                        </div>
                    `;
                }
            }],
            drawCallback: function() {
                var $wrapper = $('#vitals-table_wrapper');
                $wrapper.find('.show-all-link').remove();
                $wrapper.append(`
                    <a href="/patient/${patientId}?section=vitalsCardBody" target="_blank" class="show-all-link">
                        Show All Vitals →
                    </a>
                `);
            }
        });
    }

    // Medications rendering using cards (nursing workbench approach)
    function hmoDisplayMedications(meds, patientId) {
        if (!meds || meds.length === 0) {
            $('#clinical-meds-container').html(`
                <div class="text-center py-4">
                    <i class="mdi mdi-pill mdi-48px text-muted"></i>
                    <p class="text-muted mt-2">No medications found for this patient</p>
                </div>
            `);
            $('#clinical-meds-show-all').html('');
            return;
        }

        var html = '';
        meds.forEach(function(med) {
            var drugName = med.drug_name || 'N/A';
            var productCode = med.product_code || '';
            var dose = med.dose || 'N/A';
            var freq = med.freq || '';
            var duration = med.duration || '';
            var status = med.status || 'pending';
            var requestedDate = med.requested_date || 'N/A';
            var doctor = med.doctor || 'N/A';

            var statusBadge = '';
            if (status === 'dispensed') {
                statusBadge = "<span class='badge bg-info'>Dispensed</span>";
            } else if (status === 'billed') {
                statusBadge = "<span class='badge bg-primary'>Billed</span>";
            } else {
                statusBadge = "<span class='badge bg-secondary'>Pending</span>";
            }

            var doseInfo = dose;
            if (freq) doseInfo += ' | Freq: ' + freq;
            if (duration) doseInfo += ' | Duration: ' + duration;

            html += '<div class="card mb-2" style="border-left: 4px solid #0d6efd;">';
            html += '<div class="card-body p-3">';
            html += '<div class="d-flex justify-content-between align-items-start mb-3">';
            html += "<h6 class='mb-0'><span class='badge bg-success'>" + (productCode ? '[' + productCode + '] ' : '') + drugName + '</span></h6>';
            html += statusBadge;
            html += '</div>';
            html += '<div class="alert alert-light mb-3"><small><b><i class="mdi mdi-pill"></i> Dose/Frequency:</b><br>' + doseInfo + '</small></div>';
            html += '<div class="mb-2"><small>';
            html += '<div class="mb-1"><i class="mdi mdi-account-arrow-right text-primary"></i> <b>Requested by:</b> '
                + doctor + ' <span class="text-muted">(' + requestedDate + ')</span></div>';
            html += '</small></div>';
            html += '</div>';
            html += '</div>';
        });

        $('#clinical-meds-container').html(html);

        $('#clinical-meds-show-all').html(`
            <a href="/patient/${patientId}?section=prescriptionsNotesCardBody" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="mdi mdi-open-in-new"></i> See More Prescriptions
            </a>
        `);
    }

    // Allergies rendering (nursing workbench approach)
    function hmoDisplayAllergies(data) {
        let allergiesArray = [];

        if (data && data.allergies) {
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

        let html = '';

        if (allergiesArray.length > 0) {
            html += `
                <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
                    <i class="mdi mdi-alert-circle mdi-24px me-2"></i>
                    <strong>${allergiesArray.length} known allerg${allergiesArray.length === 1 ? 'y' : 'ies'} on record</strong>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
            `;

            allergiesArray.forEach(function(allergy) {
                let allergyName = allergy;
                let severity = '';
                let reaction = '';

                // Handle if allergy is an object with name/severity/reaction
                if (typeof allergy === 'object' && allergy !== null) {
                    allergyName = allergy.name || allergy.allergen || allergy.allergy || JSON.stringify(allergy);
                    severity = allergy.severity || '';
                    reaction = allergy.reaction || '';
                }

                let severityClass = 'allergy-card';
                let badgeClass = 'badge bg-warning text-dark';
                if (severity && severity.toLowerCase() === 'severe') {
                    severityClass += ' severe';
                    badgeClass = 'badge bg-danger';
                }

                html += `<div class="${severityClass}">`;
                html += `<div class="allergy-name"><i class="mdi mdi-alert"></i> ${allergyName}</div>`;

                if (reaction) {
                    html += `<div class="allergy-reaction"><strong>Reaction:</strong> ${reaction}</div>`;
                }

                if (severity) {
                    html += `<span class="allergy-severity ${severity.toLowerCase() === 'severe' ? 'severity-severe' : (severity.toLowerCase() === 'moderate' ? 'severity-moderate' : 'severity-mild')}">${severity}</span>`;
                }

                html += `</div>`;
            });

            html += `</div>`;
        } else {
            html = `
                <div class="text-center py-4">
                    <i class="mdi mdi-check-circle mdi-48px text-success"></i>
                    <p class="text-success mt-2 mb-0"><strong>No Known Allergies (NKA)</strong></p>
                    <small class="text-muted">No allergy information has been recorded for this patient</small>
                </div>
            `;
        }

        // Add medical history if available
        if (data && data.medical_history && data.medical_history !== 'N/A') {
            html += `
                <div class="mt-3 p-3 bg-light rounded">
                    <h6 class="mb-2"><i class="mdi mdi-clipboard-text"></i> Medical History</h6>
                    <p class="mb-0 text-muted">${data.medical_history}</p>
                </div>
            `;
        }

        $('#allergies-list').html(html);
    }

    // Vital sign classification helpers (nursing workbench approach)
    function hmoGetTempClass(temp) {
        if (temp === 'N/A') return '';
        var t = parseFloat(temp);
        if (t < 34 || t > 39) return 'vital-critical';
        if (t < 36.1 || t > 38.0) return 'vital-warning';
        return 'vital-normal';
    }

    function hmoGetHeartRateClass(heartRate) {
        if (heartRate === 'N/A') return '';
        var hr = parseInt(heartRate);
        if (hr < 50 || hr > 220) return 'vital-critical';
        if (hr < 60 || hr > 100) return 'vital-warning';
        return 'vital-normal';
    }

    function hmoGetRespRateClass(respRate) {
        if (respRate === 'N/A') return '';
        var rr = parseInt(respRate);
        if (rr < 10 || rr > 35) return 'vital-critical';
        if (rr < 12 || rr > 30) return 'vital-warning';
        return 'vital-normal';
    }

    function hmoGetBPClass(bp) {
        if (bp === 'N/A' || !bp.includes('/')) return '';
        var parts = bp.split('/').map(function(v) { return parseInt(v); });
        var systolic = parts[0], diastolic = parts[1];
        if (systolic > 180 || systolic < 80 || diastolic > 110 || diastolic < 50) return 'vital-critical';
        if (systolic > 140 || systolic < 90 || diastolic > 90 || diastolic < 60) return 'vital-warning';
        return 'vital-normal';
    }

    function hmoFormatDateTime(dateString) {
        var date = new Date(dateString);
        var dateOptions = { month: 'short', day: 'numeric' };
        var timeOptions = { hour: '2-digit', minute: '2-digit' };
        return date.toLocaleDateString('en-US', dateOptions) + ', ' + date.toLocaleTimeString('en-US', timeOptions);
    }

    // Refresh clinical panel
    $(document).on('click', '.refresh-clinical-btn', function() {
        // Refresh current patient's data
        if (window.currentClinicalPatientId) {
            loadClinicalContext(window.currentClinicalPatientId);
        } else {
            toastr.info('Click the Clinical button on a patient row to refresh data');
        }
    });

    // Patient history button
    $(document).on('click', '.history-btn', function() {
        let patientId = $(this).data('patient-id');

        $.get("{{ url('hmo/patient') }}/" + patientId + "/history", function(response) {
            $('#history_total_claims').text('₦' + formatNumber(response.summary.total_claims));
            $('#history_month_claims').text('₦' + formatNumber(response.summary.this_month_claims));
            $('#history_total_visits').text(response.summary.total_visits);

            let tbody = '';
            response.history.forEach(function(h) {
                let statusBadge = h.validation_status === 'approved' ? '<span class="badge badge-success">Approved</span>' :
                                 h.validation_status === 'rejected' ? '<span class="badge badge-danger">Rejected</span>' :
                                 '<span class="badge badge-warning">Pending</span>';
                tbody += `
                    <tr>
                        <td>${h.date}</td>
                        <td><span class="badge badge-${h.type === 'Product' ? 'success' : 'info'}">${h.type}</span></td>
                        <td>${h.item}</td>
                        <td><span class="badge badge-secondary">${h.coverage_mode}</span></td>
                        <td>₦${formatNumber(h.claims_amount)}</td>
                        <td>₦${formatNumber(h.payable_amount)}</td>
                        <td>${statusBadge}</td>
                        <td>${h.validated_by || '-'}</td>
                    </tr>
                `;
            });
            $('#historyTableBody').html(tbody || '<tr><td colspan="8" class="text-center">No history found</td></tr>');

            $('#patientHistoryModal').modal('show');
        });
    });

    // ════════════════════════════════════════════════════════════
    // Tariff inline-edit helpers (shared across approve / reject / re-approve)
    // ════════════════════════════════════════════════════════════

    // Toggle tariff panel expand / collapse
    $(document).on('click', '.tariff-toggle', function() {
        let $panel = $(this).closest('.tariff-edit-section').find('.tariff-panel');
        let $chevron = $(this).find('.tariff-chevron');
        $panel.slideToggle(200);
        let isOpen = $panel.is(':visible');
        $chevron.css('transform', isOpen ? 'rotate(180deg)' : 'rotate(0deg)');
    });

    // Load tariff details into a modal's tariff section
    function loadTariffForModal(modalSel, requestId) {
        let $modal   = $(modalSel);
        let $section = $modal.find('.tariff-edit-section');

        // Reset
        $section.find('.tariff-panel').hide();
        $section.find('.tariff-chevron').css('transform', 'rotate(0deg)');
        $section.find('.tariff-loading').show();
        $section.find('.tariff-fields').hide();
        $section.find('.tariff-summary-text').text('');
        $section.find('.tariff-apply-scheme').prop('checked', false);
        $section.data('request-id', requestId);
        $section.data('tariff-loaded', false);
        $section.data('original-values', null);

        $.ajax({
            url: "{{ url('hmo/requests') }}/" + requestId + "/tariff-details",
            type: 'GET',
            success: function(data) {
                let tariff  = data.tariff;
                let current = data.current;

                // Summary text on collapsed header
                let parts = [];
                if (data.original_name) parts.push(data.original_name);
                if (data.hmo_name)      parts.push(data.hmo_name);
                $section.find('.tariff-summary-text')
                    .text(parts.length ? '— ' + parts.join(' | ') : '');

                // Display name
                $section.find('.tariff-display-name')
                    .val(tariff ? tariff.display_name || '' : '')
                    .attr('placeholder', data.original_name || 'Original item name');

                // Coverage mode (tariff → current POSR fallback)
                let mode = tariff ? tariff.coverage_mode
                         : (current ? current.coverage_mode : 'primary');
                $section.find('.tariff-coverage-mode').val(mode);

                // Per-unit amounts
                let unitClaims  = tariff ? tariff.claims_amount  : 0;
                let unitPayable = tariff ? tariff.payable_amount : 0;
                if (!tariff && current) {
                    let qty = parseInt(current.qty) || 1;
                    unitClaims  = parseFloat(current.claims_amount)  / qty;
                    unitPayable = parseFloat(current.payable_amount) / qty;
                }
                $section.find('.tariff-claims-amount').val(unitClaims ? parseFloat(unitClaims).toFixed(2) : '');
                $section.find('.tariff-payable-amount').val(unitPayable ? parseFloat(unitPayable).toFixed(2) : '');

                // Scheme checkbox
                if (data.scheme) {
                    $section.find('.tariff-scheme-option').show();
                    $section.find('.tariff-scheme-name').text(data.scheme.name);
                    $section.find('.tariff-scheme-count').text(data.scheme.hmo_count);
                } else {
                    $section.find('.tariff-scheme-option').hide();
                }

                // Current POSR reference
                if (current) {
                    $section.find('.tariff-current-qty').text(current.qty || 1);
                    $section.find('.tariff-current-claims').text(
                        parseFloat(current.claims_amount || 0).toLocaleString('en-NG', {minimumFractionDigits: 2})
                    );
                    $section.find('.tariff-current-payable').text(
                        parseFloat(current.payable_amount || 0).toLocaleString('en-NG', {minimumFractionDigits: 2})
                    );
                }

                // Store originals for change detection
                $section.data('original-values', {
                    display_name:   tariff ? (tariff.display_name || '') : '',
                    coverage_mode:  mode,
                    claims_amount:  parseFloat(unitClaims)  || 0,
                    payable_amount: parseFloat(unitPayable) || 0
                });

                $section.data('tariff-loaded', true);
                $section.find('.tariff-loading').hide();
                $section.find('.tariff-fields').show();
            },
            error: function() {
                $section.find('.tariff-loading').html(
                    '<span class="text-danger small"><i class="mdi mdi-alert-circle mr-1"></i>Could not load tariff details</span>'
                );
            }
        });
    }

    // Detect if tariff was edited
    function hasTariffChanges(modalSel) {
        let $section = $(modalSel).find('.tariff-edit-section');
        let original = $section.data('original-values');
        if (!original || !$section.data('tariff-loaded')) return false;

        return ($section.find('.tariff-display-name').val() || '') !== original.display_name
            || $section.find('.tariff-coverage-mode').val()        !== original.coverage_mode
            || (parseFloat($section.find('.tariff-claims-amount').val())  || 0) !== original.claims_amount
            || (parseFloat($section.find('.tariff-payable-amount').val()) || 0) !== original.payable_amount
            || $section.find('.tariff-apply-scheme').is(':checked');
    }

    // Save tariff changes (returns jQuery Deferred / Promise)
    function saveTariffChanges(modalSel) {
        let deferred = $.Deferred();
        if (!hasTariffChanges(modalSel)) {
            deferred.resolve();
            return deferred.promise();
        }

        let $section  = $(modalSel).find('.tariff-edit-section');
        let requestId = $section.data('request-id');

        $.ajax({
            url: "{{ url('hmo/requests') }}/" + requestId + "/update-tariff",
            type: 'POST',
            data: {
                _token:          '{{ csrf_token() }}',
                coverage_mode:   $section.find('.tariff-coverage-mode').val(),
                claims_amount:   $section.find('.tariff-claims-amount').val(),
                payable_amount:  $section.find('.tariff-payable-amount').val(),
                display_name:    $section.find('.tariff-display-name').val() || '',
                apply_to_scheme: $section.find('.tariff-apply-scheme').is(':checked') ? 1 : 0
            },
            success: function(resp) { deferred.resolve(resp); },
            error: function(xhr) {
                let msg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to update tariff';
                toastr.error(msg, 'Tariff Update Error');
                deferred.reject(msg);
            }
        });
        return deferred.promise();
    }

    // When coverage mode changes in the tariff section, sync the modal's auth code visibility
    $(document).on('change', '.tariff-coverage-mode', function() {
        let $modal = $(this).closest('.modal');
        let newMode = $(this).val();

        // Approve modal
        if ($modal.attr('id') === 'approveModal') {
            $('#approve_coverage_mode').val(newMode);
            if (newMode === 'secondary') {
                $('#auth_code_div').show();
                $('#auth_code').prop('required', true);
            } else {
                $('#auth_code_div').hide();
                $('#auth_code').prop('required', false).val('');
            }
        }
        // Re-approve modal
        if ($modal.attr('id') === 'reapproveModal') {
            $('#reapprove_coverage_mode').val(newMode);
            if (newMode === 'secondary') {
                $('#reapprove_auth_code_div').show();
                $('#reapprove_auth_code').prop('required', true);
            } else {
                $('#reapprove_auth_code_div').hide();
                $('#reapprove_auth_code').prop('required', false).val('');
            }
        }
    });

    // ════════════════════════════════════════════════════════════

    // Show approve modal
    $(document).on('click', '.approve-btn', function() {
        let id = $(this).data('id');
        let mode = $(this).data('mode');

        $('#approve_request_id').val(id);
        $('#approve_coverage_mode').val(mode);
        $('#approve_notes').val('');
        $('#auth_code').val('');
        $('.text-danger').text('');

        if (mode === 'secondary') {
            $('#auth_code_div').show();
            $('#auth_code').prop('required', true);
        } else {
            $('#auth_code_div').hide();
            $('#auth_code').prop('required', false);
        }

        loadTariffForModal('#approveModal', id);
        $('#approveModal').modal('show');
    });

    // Submit approve form (save tariff first if changed)
    $('#approveForm').submit(function(e) {
        e.preventDefault();
        $('.text-danger').text('');

        let id    = $('#approve_request_id').val();
        let $form = $(this);
        let $btn  = $form.find('[type="submit"]').prop('disabled', true);

        saveTariffChanges('#approveModal').then(function() {
            $.ajax({
                url: "{{ url('hmo/requests') }}/" + id + "/approve",
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    $btn.prop('disabled', false);
                    $('#approveModal').modal('hide');
                    table.ajax.reload();
                    loadQueueCounts();
                    loadFinancialSummary();
                    toastr.success(response.message);
                },
                error: function(xhr) {
                    $btn.prop('disabled', false);
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) {
                            $('#error_' + key).text(value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON.message || 'An error occurred');
                    }
                }
            });
        }).fail(function() {
            $btn.prop('disabled', false);
        });
    });

    // Show reject modal
    $(document).on('click', '.reject-btn', function() {
        let id = $(this).data('id');

        $('#reject_request_id').val(id);
        $('#rejection_reason').val('');
        $('#reject_notes').val('');
        $('.text-danger').text('');

        loadTariffForModal('#rejectModal', id);
        $('#rejectModal').modal('show');
    });

    // Submit reject form (save tariff first if changed)
    $('#rejectForm').submit(function(e) {
        e.preventDefault();
        $('.text-danger').text('');

        let id    = $('#reject_request_id').val();
        let $form = $(this);
        let $btn  = $form.find('[type="submit"]').prop('disabled', true);

        saveTariffChanges('#rejectModal').then(function() {
            $.ajax({
                url: "{{ url('hmo/requests') }}/" + id + "/reject",
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    $btn.prop('disabled', false);
                    $('#rejectModal').modal('hide');
                    table.ajax.reload();
                    loadQueueCounts();
                    loadFinancialSummary();
                    toastr.success(response.message);
                },
                error: function(xhr) {
                    $btn.prop('disabled', false);
                    if (xhr.status === 422) {
                        $('#error_rejection_reason').text(xhr.responseJSON.message);
                    } else {
                        toastr.error(xhr.responseJSON.message || 'An error occurred');
                    }
                }
            });
        }).fail(function() {
            $btn.prop('disabled', false);
        });
    });

    // Show reverse modal
    $(document).on('click', '.reverse-btn', function() {
        let id = $(this).data('id');
        $('#reverse_request_id').val(id);
        $('#reverse_reason').val('');
        $('#reverseModal').modal('show');
    });

    // Submit reverse form
    $('#reverseForm').submit(function(e) {
        e.preventDefault();
        let id = $('#reverse_request_id').val();

        $.ajax({
            url: "{{ url('hmo/requests') }}/" + id + "/reverse",
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                $('#reverseModal').modal('hide');
                table.ajax.reload();
                loadQueueCounts();
                loadFinancialSummary();
                toastr.success(response.message);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON.message || 'An error occurred');
            }
        });
    });

    // Show re-approve modal
    $(document).on('click', '.reapprove-btn', function() {
        let id = $(this).data('id');
        let mode = $(this).data('mode');

        $('#reapprove_request_id').val(id);
        $('#reapprove_coverage_mode').val(mode);
        $('#reapprove_notes').val('');
        $('#reapprove_auth_code').val('');

        if (mode === 'secondary') {
            $('#reapprove_auth_code_div').show();
            $('#reapprove_auth_code').prop('required', true);
        } else {
            $('#reapprove_auth_code_div').hide();
            $('#reapprove_auth_code').prop('required', false);
        }

        loadTariffForModal('#reapproveModal', id);
        $('#reapproveModal').modal('show');
    });

    // Submit re-approve form (save tariff first if changed)
    $('#reapproveForm').submit(function(e) {
        e.preventDefault();

        let id    = $('#reapprove_request_id').val();
        let $form = $(this);
        let $btn  = $form.find('[type="submit"]').prop('disabled', true);

        saveTariffChanges('#reapproveModal').then(function() {
            $.ajax({
                url: "{{ url('hmo/requests') }}/" + id + "/reapprove",
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    $btn.prop('disabled', false);
                    $('#reapproveModal').modal('hide');
                    table.ajax.reload();
                    loadQueueCounts();
                    loadFinancialSummary();
                    toastr.success(response.message);
                },
                error: function(xhr) {
                    $btn.prop('disabled', false);
                    toastr.error(xhr.responseJSON.message || 'An error occurred');
                }
            });
        }).fail(function() {
            $btn.prop('disabled', false);
        });
    });

    // Batch approve button
    $('#batchApproveBtn').on('click', function() {
        if (selectedIds.length === 0) {
            toastr.warning('Please select at least one request');
            return;
        }
        $('#batchApproveCount').text(selectedIds.length);
        $('#batchApproveModal').modal('show');
    });

    // Submit batch approve
    $('#batchApproveForm').submit(function(e) {
        e.preventDefault();

        $.ajax({
            url: "{{ route('hmo.batch-approve') }}",
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                request_ids: selectedIds,
                validation_notes: $(this).find('[name="validation_notes"]').val()
            },
            success: function(response) {
                $('#batchApproveModal').modal('hide');
                selectedIds = [];
                updateBatchActionBar();
                table.ajax.reload();
                loadQueueCounts();
                loadFinancialSummary();
                toastr.success(response.message);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON.message || 'An error occurred');
            }
        });
    });

    // Batch reject button
    $('#batchRejectBtn').on('click', function() {
        if (selectedIds.length === 0) {
            toastr.warning('Please select at least one request');
            return;
        }
        $('#batchRejectCount').text(selectedIds.length);
        $('#batchRejectModal').modal('show');
    });

    // Submit batch reject
    $('#batchRejectForm').submit(function(e) {
        e.preventDefault();

        $.ajax({
            url: "{{ route('hmo.batch-reject') }}",
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                request_ids: selectedIds,
                rejection_reason: $(this).find('[name="rejection_reason"]').val(),
                validation_notes: $(this).find('[name="validation_notes"]').val()
            },
            success: function(response) {
                $('#batchRejectModal').modal('hide');
                selectedIds = [];
                updateBatchActionBar();
                table.ajax.reload();
                loadQueueCounts();
                loadFinancialSummary();
                toastr.success(response.message);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON.message || 'An error occurred');
            }
        });
    });

    // ═══════════════════════════════════════════════════════════
    // VALIDATE BY GROUP — Full JS logic
    // ═══════════════════════════════════════════════════════════
    let vgData = null;          // raw response from server
    let vgSelectedIds = [];     // currently checked request ids
    let vgGroupMode = 'encounter'; // 'encounter' or 'date'
    let vgIndividualCodes = {};  // {requestId: authCode} — persists across re-renders

    // Format currency
    function vgMoney(val) {
        return '₦' + parseFloat(val || 0).toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    // SLA color: green <2h, yellow <4h, red >4h
    function vgSlaColor(hoursAgo) {
        if (!hoursAgo) return 'secondary';
        if (hoursAgo < 2) return 'success';
        if (hoursAgo < 4) return 'warning';
        return 'danger';
    }

    // Open modal
    $(document).on('click', '.validate-group-btn', function() {
        let patientId = $(this).data('patient-id');
        let patientName = $(this).data('patient-name');

        // Reset state
        vgData = null;
        vgSelectedIds = [];
        vgGroupMode = 'encounter';
        $('#vg_patient_name').text(patientName);
        $('#vg_patient_subtitle').text('');
        $('#vg_groups_container').empty();
        $('#vg_loading').show();
        $('#vg_empty').hide();
        $('#vg_auth_section').hide();
        $('#vg_reject_section').hide();
        $('#vg_shared_auth_code').val('');
        $('#vg_validation_notes').val('');
        $('#vg_rejection_reason').val('');
        $('#vg_reject_notes').val('');
        vgIndividualCodes = {};
        $('input[name="vg_auth_mode"][value="shared"]').prop('checked', true);
        $('#vg_shared_auth_input').show();
        $('#vg_group_encounter').addClass('active');
        $('#vg_group_date').removeClass('active');
        $('#vg_date_range_controls').hide();
        $('#vg_date_filter').val('all');
        vgUpdateFooter();

        $('#validateGroupModal').modal('show');

        // Fetch data
        $.ajax({
            url: "{{ url('hmo/patient') }}/" + patientId + "/pending-requests",
            type: 'GET',
            success: function(resp) {
                $('#vg_loading').hide();
                if (!resp.success || resp.summary.total_count === 0) {
                    $('#vg_empty').show();
                    return;
                }
                vgData = resp;

                // Header info
                let p = resp.patient;
                let sub = 'HMO: ' + p.hmo_name;
                if (p.scheme_name) sub += ' • Scheme: ' + p.scheme_name + (p.scheme_code ? ' (' + p.scheme_code + ')' : '');
                sub += ' • File: ' + p.file_no;
                if (p.hmo_no) sub += ' • HMO#: ' + p.hmo_no;
                $('#vg_patient_subtitle').text(sub);

                // Summary badges
                let s = resp.summary;
                $('#vg_primary_badge').text(s.primary_count + ' Primary');
                $('#vg_secondary_badge').text(s.secondary_count + ' Secondary');
                $('#vg_total_count').text(s.total_count);
                $('#vg_total_claims').text(vgMoney(s.total_claims));
                $('#vg_total_payable').text(vgMoney(s.total_payable));

                // Select all by default
                vgSelectAllRequests();
                vgRenderGroups();
                vgUpdateFooter();
            },
            error: function() {
                $('#vg_loading').hide();
                toastr.error('Failed to load patient requests');
            }
        });
    });

    // Collect all request objects flat
    function vgAllRequests() {
        if (!vgData) return [];
        let all = [];
        vgData.encounters.forEach(function(enc) {
            enc.requests.forEach(function(r) { all.push(r); });
        });
        return all;
    }

    // Select/deselect all
    function vgSelectAllRequests() {
        vgSelectedIds = vgAllRequests().map(function(r) { return r.id; });
    }

    $('#vg_select_all_btn').on('click', function() {
        // Select all visible (respecting date filter)
        let visible = vgGetVisibleRequests();
        visible.forEach(function(r) {
            if (vgSelectedIds.indexOf(r.id) === -1) vgSelectedIds.push(r.id);
        });
        vgRefreshCheckboxes();
        vgUpdateGroupSubtotals();
        vgUpdateFooter();
    });

    $('#vg_deselect_all_btn').on('click', function() {
        // Only deselect visible items (if in date mode with filter)
        if (vgGroupMode === 'date' && $('#vg_date_filter').val() !== 'all') {
            let visible = vgGetVisibleRequests();
            let visibleIds = visible.map(function(r) { return r.id; });
            vgSelectedIds = vgSelectedIds.filter(function(id) { return visibleIds.indexOf(id) === -1; });
        } else {
            vgSelectedIds = [];
        }
        vgRefreshCheckboxes();
        vgUpdateGroupSubtotals();
        vgUpdateFooter();
    });

    // Grouping toggle
    $('#vg_group_encounter').on('click', function() {
        vgGroupMode = 'encounter';
        $(this).addClass('active');
        $('#vg_group_date').removeClass('active');
        $('#vg_date_range_controls').hide();
        vgRenderGroups();
    });

    $('#vg_group_date').on('click', function() {
        vgGroupMode = 'date';
        $(this).addClass('active');
        $('#vg_group_encounter').removeClass('active');
        $('#vg_date_range_controls').show();
        vgRenderGroups();
    });

    $('#vg_date_filter').on('change', function() {
        vgRenderGroups();
    });

    // Get visible requests based on date filter
    function vgGetVisibleRequests() {
        let all = vgAllRequests();
        if (vgGroupMode !== 'date') return all;

        let filter = $('#vg_date_filter').val();
        if (filter === 'all') return all;

        let now = new Date();
        let cutoff = new Date();
        if (filter === 'today') {
            cutoff.setHours(0, 0, 0, 0);
        } else if (filter === '3days') {
            cutoff.setDate(now.getDate() - 3);
        } else if (filter === 'week') {
            cutoff.setDate(now.getDate() - (now.getDay() || 7) + 1);
            cutoff.setHours(0, 0, 0, 0);
        } else if (filter === 'month') {
            cutoff = new Date(now.getFullYear(), now.getMonth(), 1);
        }

        return all.filter(function(r) {
            if (!r.created_at) return true;
            let d = new Date(r.created_at);
            if (isNaN(d.getTime())) return true;
            return d >= cutoff;
        });
    }

    // Render groups into the container
    function vgRenderGroups() {
        if (!vgData) return;
        let container = $('#vg_groups_container');
        container.empty();

        if (vgGroupMode === 'encounter') {
            vgData.encounters.forEach(function(enc, idx) {
                let encLabel = enc.encounter_id
                    ? 'Encounter #' + enc.encounter_id + (enc.doctor ? ' — ' + enc.doctor : '') + (enc.date ? ' — ' + enc.date : '')
                    : 'Ungrouped Requests (no encounter)';
                let card = vgBuildGroupCard(encLabel, enc.requests, 'enc_' + idx);
                container.append(card);
            });
        } else {
            let visible = vgGetVisibleRequests();
            let card = vgBuildGroupCard('All Pending Requests (' + visible.length + ')', visible, 'date_all');
            container.append(card);
        }
    }

    // Build a single group card with table
    function vgBuildGroupCard(title, requests, groupKey) {
        let groupIds = requests.map(function(r) { return r.id; });
        let allChecked = groupIds.every(function(id) { return vgSelectedIds.indexOf(id) !== -1; });

        let html = '<div class="card card-modern mb-2">';
        html += '<div class="card-header py-2 px-3 d-flex align-items-center justify-content-between cursor-pointer" data-toggle="collapse" data-target="#vg_collapse_' + groupKey + '">';
        html += '<div><i class="mdi mdi-chevron-down mr-1"></i><strong class="small">' + title + '</strong>';
        html += ' <span class="badge badge-light ml-1">' + requests.length + ' items</span></div>';
        html += '<div class="custom-control custom-checkbox" onclick="event.stopPropagation();">';
        html += '<input type="checkbox" class="custom-control-input vg-group-select" id="vg_grp_' + groupKey + '" data-group="' + groupKey + '" ' + (allChecked ? 'checked' : '') + '>';
        html += '<label class="custom-control-label small" for="vg_grp_' + groupKey + '">Select All</label>';
        html += '</div></div>';

        html += '<div class="collapse show" id="vg_collapse_' + groupKey + '">';
        html += '<div class="card-body p-0"><div class="table-responsive">';
        html += '<table class="table table-sm table-hover mb-0" style="font-size:0.82rem;">';
        html += '<thead class="thead-light"><tr>';
        html += '<th style="width:30px;"></th>';
        html += '<th>Type</th><th>Item</th><th class="text-center">Qty</th>';
        html += '<th class="text-right">Claims (₦)</th><th class="text-right">Payable (₦)</th>';
        html += '<th class="text-center">Coverage</th>';
        html += '<th>Auth Code</th>';
        html += '<th style="width:50px;"></th>';
        html += '</tr></thead><tbody>';

        requests.forEach(function(r) {
            let checked = vgSelectedIds.indexOf(r.id) !== -1 ? 'checked' : '';
            let typeBadge = r.type === 'Product'
                ? '<span class="badge badge-primary">Product</span>'
                : r.type === 'Procedure'
                    ? '<span class="badge badge-warning text-dark">Procedure</span>'
                    : '<span class="badge badge-info">Service</span>';
            let coverageBadge = r.coverage_mode === 'secondary'
                ? '<span class="badge badge-danger">SECONDARY</span>'
                : '<span class="badge badge-warning">PRIMARY</span>';
            let slaClass = vgSlaColor(r.hours_ago);
            let slaDot = '<span class="badge badge-' + slaClass + '" style="width:8px;height:8px;padding:0;border-radius:50%;display:inline-block;" title="' + (r.hours_ago ? r.hours_ago + 'h ago' : '') + '"></span>';

            // Auth code input (only for secondary)
            let authCol = '—';
            if (r.coverage_mode === 'secondary') {
                let savedCode = (vgIndividualCodes[r.id] || '').replace(/"/g, '&quot;');
                authCol = '<input type="text" class="form-control form-control-sm vg-individual-auth" data-id="' + r.id + '" value="' + savedCode + '" placeholder="Auth code" style="border-radius:4px;min-width:100px;font-size:0.78rem;" ' + ($('input[name="vg_auth_mode"]:checked').val() === 'shared' ? 'disabled' : '') + '>';
            }

            html += '<tr>';
            html += '<td><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input vg-item-check" id="vg_item_' + r.id + '" data-id="' + r.id + '" data-group="' + groupKey + '" data-claims="' + r.claims_amount + '" data-coverage="' + r.coverage_mode + '" ' + checked + '><label class="custom-control-label" for="vg_item_' + r.id + '"></label></div></td>';
            html += '<td>' + typeBadge + '</td>';
            html += '<td>' + r.name + (r.category ? '<br><small class="text-muted">' + r.category + '</small>' : '') + '</td>';
            html += '<td class="text-center">' + r.qty + '</td>';
            html += '<td class="text-right">' + vgMoney(r.claims_amount) + '</td>';
            html += '<td class="text-right">' + vgMoney(r.payable_amount) + '</td>';
            html += '<td class="text-center">' + coverageBadge + '</td>';
            html += '<td>' + authCol + '</td>';
            html += '<td>' + slaDot + '</td>';
            html += '</tr>';
        });

        html += '</tbody>';

        // Group subtotal row
        let groupSelected = requests.filter(function(r) { return vgSelectedIds.indexOf(r.id) !== -1; });
        let groupClaims = groupSelected.reduce(function(s, r) { return s + r.claims_amount; }, 0);
        let groupPayable = groupSelected.reduce(function(s, r) { return s + r.payable_amount; }, 0);
        html += '<tfoot><tr class="bg-light">';
        html += '<td colspan="4" class="text-right small text-muted">Selected: ' + groupSelected.length + '/' + requests.length + '</td>';
        html += '<td class="text-right small font-weight-bold">' + vgMoney(groupClaims) + '</td>';
        html += '<td class="text-right small font-weight-bold">' + vgMoney(groupPayable) + '</td>';
        html += '<td colspan="2"></td><td></td>';
        html += '</tr></tfoot>';

        html += '</table></div></div></div></div>';
        return html;
    }

    // Checkbox: individual item
    $(document).on('change', '.vg-item-check', function() {
        let id = parseInt($(this).data('id'));
        if ($(this).is(':checked')) {
            if (vgSelectedIds.indexOf(id) === -1) vgSelectedIds.push(id);
        } else {
            vgSelectedIds = vgSelectedIds.filter(function(x) { return x !== id; });
        }
        vgUpdateGroupCheckbox($(this).data('group'));
        vgUpdateGroupSubtotals();
        vgUpdateFooter();
    });

    // Checkbox: group select all
    $(document).on('change', '.vg-group-select', function() {
        let groupKey = $(this).data('group');
        let checked = $(this).is(':checked');
        $('#vg_collapse_' + groupKey + ' .vg-item-check').each(function() {
            let id = parseInt($(this).data('id'));
            $(this).prop('checked', checked);
            if (checked && vgSelectedIds.indexOf(id) === -1) {
                vgSelectedIds.push(id);
            } else if (!checked) {
                vgSelectedIds = vgSelectedIds.filter(function(x) { return x !== id; });
            }
        });
        vgUpdateGroupSubtotals();
        vgUpdateFooter();
    });

    function vgUpdateGroupCheckbox(groupKey) {
        let allInGroup = $('#vg_collapse_' + groupKey + ' .vg-item-check');
        let allChecked = allInGroup.length > 0 && allInGroup.filter(':checked').length === allInGroup.length;
        $('#vg_grp_' + groupKey).prop('checked', allChecked);
    }

    function vgRefreshCheckboxes() {
        $('.vg-item-check').each(function() {
            let id = parseInt($(this).data('id'));
            $(this).prop('checked', vgSelectedIds.indexOf(id) !== -1);
        });
        $('.vg-group-select').each(function() {
            vgUpdateGroupCheckbox($(this).data('group'));
        });
        vgUpdateGroupSubtotals();
    }

    // Update tfoot subtotals in each group card without full re-render
    function vgUpdateGroupSubtotals() {
        $('[id^="vg_collapse_"]').each(function() {
            let $items = $(this).find('.vg-item-check');
            let selectedCount = 0;
            let totalCount = $items.length;
            let groupClaims = 0;
            let groupPayable = 0;
            $items.each(function() {
                if ($(this).is(':checked')) {
                    selectedCount++;
                    let id = parseInt($(this).data('id'));
                    let req = vgFindRequest(id);
                    if (req) {
                        groupClaims += req.claims_amount;
                        groupPayable += req.payable_amount;
                    }
                }
            });
            let $tfoot = $(this).find('tfoot td');
            $tfoot.eq(0).html('Selected: ' + selectedCount + '/' + totalCount);
            $tfoot.eq(1).html(vgMoney(groupClaims));
            $tfoot.eq(2).html(vgMoney(groupPayable));
        });
    }

    // Find a request by ID in vgData
    function vgFindRequest(id) {
        if (!vgData) return null;
        for (let i = 0; i < vgData.encounters.length; i++) {
            for (let j = 0; j < vgData.encounters[i].requests.length; j++) {
                if (vgData.encounters[i].requests[j].id === id) return vgData.encounters[i].requests[j];
            }
        }
        return null;
    }

    // Auth mode toggle
    $('input[name="vg_auth_mode"]').on('change', function() {
        let mode = $(this).val();
        if (mode === 'shared') {
            $('#vg_shared_auth_input').show();
            $('.vg-individual-auth').prop('disabled', true);
        } else {
            $('#vg_shared_auth_input').hide();
            $('.vg-individual-auth').prop('disabled', false);
            // Pre-fill individual fields with shared code value
            let sharedVal = $('#vg_shared_auth_code').val();
            if (sharedVal) {
                // Pre-fill map for all secondary items that don't have codes yet
                vgAllRequests().forEach(function(r) {
                    if (r.coverage_mode === 'secondary' && !vgIndividualCodes[r.id]) {
                        vgIndividualCodes[r.id] = sharedVal;
                    }
                });
                $('.vg-individual-auth').each(function() {
                    if (!$(this).val()) $(this).val(sharedVal);
                });
            }
        }
    });

    // Update footer counts, amounts, button states, auth section visibility
    function vgUpdateFooter() {
        let allReqs = vgAllRequests();
        let selectedReqs = allReqs.filter(function(r) { return vgSelectedIds.indexOf(r.id) !== -1; });
        let selectedClaims = selectedReqs.reduce(function(s, r) { return s + r.claims_amount; }, 0);
        let secondarySelected = selectedReqs.filter(function(r) { return r.coverage_mode === 'secondary'; }).length;

        $('.vg-selected-count').text(selectedReqs.length);
        $('.vg-selected-claims').text(vgMoney(selectedClaims));
        $('#vg_footer_total').text(allReqs.length);
        $('#vg_secondary_selected_count').text(secondarySelected);

        // Show/hide auth section
        if (secondarySelected > 0) {
            $('#vg_auth_section').show();
        } else {
            $('#vg_auth_section').hide();
        }

        // Enable/disable buttons
        let hasSelection = selectedReqs.length > 0;
        $('#vg_approve_btn').prop('disabled', !hasSelection);
        $('#vg_reject_btn').prop('disabled', !hasSelection);
    }

    // APPROVE selected
    $('#vg_approve_btn').on('click', function() {
        if (vgSelectedIds.length === 0) return;

        let allReqs = vgAllRequests();
        let selectedReqs = allReqs.filter(function(r) { return vgSelectedIds.indexOf(r.id) !== -1; });
        let secondarySelected = selectedReqs.filter(function(r) { return r.coverage_mode === 'secondary'; });
        let authMode = $('input[name="vg_auth_mode"]:checked').val();

        // Validate auth codes for secondary items
        if (secondarySelected.length > 0) {
            if (authMode === 'shared') {
                let code = $('#vg_shared_auth_code').val().trim();
                if (!code) {
                    $('#vg_shared_auth_code').addClass('is-invalid').focus();
                    toastr.warning('Enter an authorization code for ' + secondarySelected.length + ' secondary item(s)', 'Auth Code Required');
                    return;
                }
            } else {
                let missing = [];
                secondarySelected.forEach(function(r) {
                    let val = vgIndividualCodes[r.id];
                    if (!val || !val.trim()) missing.push(r.name);
                });
                if (missing.length > 0) {
                    toastr.warning('Missing auth codes for: ' + missing.join(', '), 'Auth Code Required');
                    return;
                }
            }
        }

        // Build payload
        let payload = {
            _token: '{{ csrf_token() }}',
            request_ids: vgSelectedIds,
            auth_mode: authMode,
            validation_notes: $('#vg_validation_notes').val()
        };

        if (authMode === 'shared') {
            payload.shared_auth_code = $('#vg_shared_auth_code').val().trim();
        } else {
            let codes = {};
            secondarySelected.forEach(function(r) {
                codes[r.id] = (vgIndividualCodes[r.id] || '').trim();
            });
            payload.individual_auth_codes = codes;
        }

        let $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span>Approving...');

        $.ajax({
            url: "{{ route('hmo.group-approve') }}",
            type: 'POST',
            data: payload,
            success: function(resp) {
                if (resp.errors && resp.errors.length > 0) {
                    toastr.warning(resp.message + '<br>' + resp.errors.join('<br>'), 'Partial Approval');
                } else {
                    toastr.success(resp.message);
                }

                // Remove approved items from vgData and refresh
                if (resp.approved > 0) {
                    vgRemoveApprovedItems();
                }

                $btn.prop('disabled', false).html('<i class="mdi mdi-check-all mr-1"></i>Approve (<span class="vg-selected-count">0</span>)');

                // If nothing left, close modal and reload table
                if (vgAllRequests().length === 0) {
                    $('#validateGroupModal').modal('hide');
                }

                table.ajax.reload();
                loadQueueCounts();
                loadFinancialSummary();
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="mdi mdi-check-all mr-1"></i>Approve (<span class="vg-selected-count">' + vgSelectedIds.length + '</span>)');
                toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred');
            }
        });
    });

    // Remove approved/rejected items from local data
    function vgRemoveApprovedItems() {
        if (!vgData) return;
        vgData.encounters.forEach(function(enc) {
            enc.requests = enc.requests.filter(function(r) { return vgSelectedIds.indexOf(r.id) === -1; });
        });
        // Remove empty encounter groups
        vgData.encounters = vgData.encounters.filter(function(enc) { return enc.requests.length > 0; });
        // Update summary
        let allReqs = vgAllRequests();
        vgData.summary.total_count = allReqs.length;
        vgData.summary.primary_count = allReqs.filter(function(r) { return r.coverage_mode === 'primary'; }).length;
        vgData.summary.secondary_count = allReqs.filter(function(r) { return r.coverage_mode === 'secondary'; }).length;
        vgData.summary.total_claims = allReqs.reduce(function(s, r) { return s + r.claims_amount; }, 0);
        vgData.summary.total_payable = allReqs.reduce(function(s, r) { return s + r.payable_amount; }, 0);

        // Update UI
        vgSelectedIds = [];
        if (allReqs.length === 0) {
            $('#vg_groups_container').empty();
            $('#vg_empty').show();
        } else {
            // Update badges
            let s = vgData.summary;
            $('#vg_primary_badge').text(s.primary_count + ' Primary');
            $('#vg_secondary_badge').text(s.secondary_count + ' Secondary');
            $('#vg_total_count').text(s.total_count);
            $('#vg_total_claims').text(vgMoney(s.total_claims));
            $('#vg_total_payable').text(vgMoney(s.total_payable));

            // Re-select remaining and re-render
            vgSelectAllRequests();
            vgRenderGroups();
        }
        vgUpdateFooter();
    }

    // REJECT flow — show inline section
    $('#vg_reject_btn').on('click', function() {
        if (vgSelectedIds.length === 0) return;
        $('#vg_reject_section').slideDown(200);
        $('#vg_rejection_reason').focus();
    });

    $('#vg_cancel_reject_btn').on('click', function() {
        $('#vg_reject_section').slideUp(200);
    });

    $('#vg_confirm_reject_btn').on('click', function() {
        let reason = $('#vg_rejection_reason').val();
        if (!reason) {
            $('#vg_rejection_reason').addClass('is-invalid').focus();
            return;
        }

        let $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span>Rejecting...');

        $.ajax({
            url: "{{ route('hmo.group-reject') }}",
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                request_ids: vgSelectedIds,
                rejection_reason: reason,
                validation_notes: $('#vg_reject_notes').val()
            },
            success: function(resp) {
                toastr.success(resp.message);
                $('#vg_reject_section').slideUp(200);

                if (resp.rejected > 0) {
                    vgRemoveApprovedItems();
                }

                $btn.prop('disabled', false).html('<i class="mdi mdi-close-circle mr-1"></i>Confirm Reject (<span class="vg-selected-count">0</span>)');

                if (vgAllRequests().length === 0) {
                    $('#validateGroupModal').modal('hide');
                }

                table.ajax.reload();
                loadQueueCounts();
                loadFinancialSummary();
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="mdi mdi-close-circle mr-1"></i>Confirm Reject (<span class="vg-selected-count">' + vgSelectedIds.length + '</span>)');
                toastr.error(xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred');
            }
        });
    });

    // Clear invalid state on input/change
    $(document).on('input', '#vg_shared_auth_code', function() {
        $(this).removeClass('is-invalid');
    });
    $(document).on('input', '.vg-individual-auth', function() {
        vgIndividualCodes[parseInt($(this).data('id'))] = $(this).val();
    });
    $(document).on('change', '#vg_rejection_reason', function() {
        $(this).removeClass('is-invalid');
    });

    // Auto-refresh counts every 30 seconds
    setInterval(function() {
        loadQueueCounts();
        loadFinancialSummary();
    }, 30000);
});
</script>
<style>
    .cursor-pointer { cursor: pointer; }
    .preset-card:hover { opacity: 0.9; transform: scale(1.02); transition: all 0.2s; }
    .tariff-edit-section .card-header.tariff-toggle:hover { background: #edf2f7 !important; }
    .tariff-edit-section .tariff-chevron { transition: transform 0.3s ease; }
    .tariff-edit-section .tariff-fields .form-control-sm { font-size: 0.82rem; }
    #validateGroupModal .table th { font-size: 0.78rem; font-weight: 600; white-space: nowrap; }
    #validateGroupModal .card-modern { transition: none; }
    #validateGroupModal .card-modern:hover { transform: none; }
    #validateGroupModal .custom-checkbox .custom-control-label::before { transform: scale(1.15); }
</style>

{{-- Emergency Intake Modal --}}
@include('admin.partials.emergency-intake-modal')

@endsection
