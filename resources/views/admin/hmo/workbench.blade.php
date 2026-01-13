@extends('admin.layouts.app')
@section('title', 'HMO Workbench')
@section('page_name', 'HMO Management')
@section('subpage_name', 'HMO Workbench')
@section('content')

<style>
    :root {
        --hospital-primary: {{ appsettings('hos_color', '#007bff') }};
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
        <!-- Financial Summary Cards Row -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card-modern text-white" style="background-color: #007bff;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Pending Claims Value</h6>
                                <h4 class="mb-0" id="pending_claims_total">₦0</h4>
                            </div>
                            <i class="mdi mdi-cash-multiple" style="font-size: 2.5rem; opacity: 0.8;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-modern text-white" style="background-color: #28a745;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Approved Today Value</h6>
                                <h4 class="mb-0" id="approved_today_total">₦0</h4>
                            </div>
                            <i class="mdi mdi-cash-check" style="font-size: 2.5rem; opacity: 0.8;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-modern text-white" style="background-color: #dc3545;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Rejected Today Value</h6>
                                <h4 class="mb-0" id="rejected_today_total">₦0</h4>
                            </div>
                            <i class="mdi mdi-cash-remove" style="font-size: 2.5rem; opacity: 0.8;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-modern text-white" style="background-color: #17a2b8;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Monthly Claims Total</h6>
                                <h4 class="mb-0" id="monthly_claims_total">₦0</h4>
                            </div>
                            <i class="mdi mdi-calendar-month" style="font-size: 2.5rem; opacity: 0.8;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Queue Stats Cards -->
        <div class="row mb-3">
            <div class="col-md-2">
                <div class="card-modern bg-warning text-white cursor-pointer preset-card" data-preset="">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 small">Pending Validation</h6>
                                <h3 class="mb-0" id="pending_count">0</h3>
                            </div>
                            <i class="mdi mdi-clock-alert" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card-modern bg-success text-white cursor-pointer preset-card" data-preset="">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 small">Express (Auto)</h6>
                                <h3 class="mb-0" id="express_count">0</h3>
                            </div>
                            <i class="mdi mdi-flash" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card-modern bg-info text-white cursor-pointer preset-card" data-preset="today_approved">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 small">Approved Today</h6>
                                <h3 class="mb-0" id="approved_today_count">0</h3>
                            </div>
                            <i class="mdi mdi-thumb-up" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card-modern bg-danger text-white cursor-pointer preset-card" data-preset="today_rejected">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 small">Rejected Today</h6>
                                <h3 class="mb-0" id="rejected_today_count">0</h3>
                            </div>
                            <i class="mdi mdi-thumb-down" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card-modern bg-dark text-white cursor-pointer preset-card" data-preset="overdue">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 small">Overdue (>4h)</h6>
                                <h3 class="mb-0" id="overdue_count">0</h3>
                            </div>
                            <i class="mdi mdi-alert-circle" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card-modern bg-secondary text-white cursor-pointer preset-card" data-preset="high_value">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 small">High Value (>50k)</h6>
                                <h3 class="mb-0">🎯</h3>
                            </div>
                            <i class="mdi mdi-currency-ngn" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card-modern">
            <div class="card-header bg-primary text-white">
                <h3 class="card-title"><i class="fa fa-filter"></i> Filters & Search</h3>
                <div class="card-tools">
                    <a href="{{ route('hmo.export-claims') }}" class="btn btn-sm btn-light" id="exportBtn">
                        <i class="fa fa-download"></i> Export Claims
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Search</label>
                            <input type="text" class="form-control form-control-sm" id="search_input" placeholder="Patient name, file no, HMO no, request ID...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>HMO</label>
                            <select class="form-control form-control-sm" id="filter_hmo">
                                <option value="">All HMOs</option>
                                @foreach($hmos as $hmo)
                                    <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Coverage Mode</label>
                            <select class="form-control form-control-sm" id="filter_coverage">
                                <option value="">All Modes</option>
                                <option value="express">Express</option>
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date From</label>
                            <input type="date" class="form-control form-control-sm" id="filter_date_from">
                        </div>
                    </div>
                        <div class="form-group">
                            <label>Date From</label>
                            <input type="date" class="form-control form-control-sm" id="filter_date_from">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date To</label>
                            <input type="date" class="form-control form-control-sm" id="filter_date_to">
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-primary btn-block btn-sm" id="applyFilters">
                                <i class="fa fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Batch Actions Bar -->
        <div class="card-modern" id="batchActionsBar" style="display:none;">
            <div class="card-body py-2 bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="mr-3"><strong><span id="selectedCount">0</span></strong> items selected</span>
                        <button type="button" class="btn btn-sm btn-success" id="batchApproveBtn">
                            <i class="fa fa-check"></i> Batch Approve
                        </button>
                        <button type="button" class="btn btn-sm btn-danger ml-2" id="batchRejectBtn">
                            <i class="fa fa-times"></i> Batch Reject
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-secondary" id="clearSelectionBtn">
                            <i class="fa fa-times-circle"></i> Clear Selection
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs and DataTable -->
        <div class="card-modern">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="workbenchTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="pending-tab" data-toggle="tab" href="#pending" role="tab">
                            <i class="mdi mdi-clock-alert"></i> Pending <span class="badge badge-warning" id="pending_badge">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="express-tab" data-toggle="tab" href="#express" role="tab">
                            <i class="mdi mdi-flash"></i> Express <span class="badge badge-success" id="express_badge">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="approved-tab" data-toggle="tab" href="#approved" role="tab">
                            <i class="mdi mdi-check"></i> Approved
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="rejected-tab" data-toggle="tab" href="#rejected" role="tab">
                            <i class="mdi mdi-close"></i> Rejected
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="claims-tab" data-toggle="tab" href="#claims" role="tab">
                            <i class="mdi mdi-cash"></i> Claims
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="all-tab" data-toggle="tab" href="#all" role="tab">
                            <i class="mdi mdi-view-list"></i> All
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
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Request Details</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Patient Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr><th width="40%">Name:</th><td id="detail_patient_name"></td></tr>
                            <tr><th>File No:</th><td id="detail_file_no"></td></tr>
                            <tr><th>HMO No:</th><td id="detail_hmo_no"></td></tr>
                            <tr><th>HMO:</th><td id="detail_hmo_name"></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Request Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr><th width="40%">Request ID:</th><td id="detail_request_id"></td></tr>
                            <tr><th>Date:</th><td id="detail_created_at"></td></tr>
                            <tr><th>Type:</th><td id="detail_item_type"></td></tr>
                            <tr><th>Item:</th><td id="detail_item_name"></td></tr>
                            <tr><th>Quantity:</th><td id="detail_qty"></td></tr>
                        </table>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-12">
                        <h6 class="text-primary">Pricing & Coverage</h6>
                        <table class="table table-sm table-bordered">
                            <tr>
                                <th>Original Price</th>
                                <th>HMO Covers (Claims)</th>
                                <th>Patient Pays</th>
                                <th>Coverage Mode</th>
                            </tr>
                            <tr>
                                <td>₦<span id="detail_original_price"></span></td>
                                <td>₦<span id="detail_claims_amount"></span></td>
                                <td>₦<span id="detail_payable_amount"></span></td>
                                <td><span id="detail_coverage_mode"></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-12">
                        <h6 class="text-primary">Validation Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr><th width="20%">Status:</th><td><span id="detail_validation_status"></span></td></tr>
                            <tr><th>Auth Code:</th><td id="detail_auth_code">-</td></tr>
                            <tr><th>Validated By:</th><td id="detail_validated_by">-</td></tr>
                            <tr><th>Validated At:</th><td id="detail_validated_at">-</td></tr>
                            <tr><th>Notes:</th><td id="detail_validation_notes">-</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Approve Request</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="approveForm">
                @csrf
                <input type="hidden" id="approve_request_id">
                <input type="hidden" id="approve_coverage_mode">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Confirm Approval:</strong> You are about to approve this HMO request.
                    </div>
                    <div class="form-group" id="auth_code_div" style="display:none;">
                        <label>Authorization Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="auth_code" name="auth_code" placeholder="Enter HMO auth code">
                        <small class="form-text text-danger" id="error_auth_code"></small>
                        <small class="form-text text-muted">Required for secondary coverage</small>
                    </div>
                    <div class="form-group">
                        <label>Validation Notes</label>
                        <textarea class="form-control" id="approve_notes" name="validation_notes" rows="3" placeholder="Optional notes..."></textarea>
                        <small class="form-text text-danger" id="error_validation_notes"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-check"></i> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Reject Request</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="rejectForm">
                @csrf
                <input type="hidden" id="reject_request_id">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Confirm Rejection:</strong> You are about to reject this HMO request.
                    </div>
                    <div class="form-group">
                        <label>Rejection Reason <span class="text-danger">*</span></label>
                        <select class="form-control" id="rejection_reason" name="rejection_reason" required>
                            <option value="">-- Select Reason --</option>
                            @foreach($rejectionReasons as $key => $reason)
                                <option value="{{ $key }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-danger" id="error_rejection_reason"></small>
                    </div>
                    <div class="form-group">
                        <label>Additional Notes</label>
                        <textarea class="form-control" id="reject_notes" name="validation_notes" rows="3" placeholder="Optional additional notes..."></textarea>
                        <small class="form-text text-danger" id="error_reject_notes"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-times"></i> Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reverse Approval Modal -->
<div class="modal fade" id="reverseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Reverse Approval</h5>
                <button type="button" class="close"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="reverseForm">
                @csrf
                <input type="hidden" id="reverse_request_id">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>⚠️ Warning:</strong> You are about to reverse this approval and set the request back to pending.
                    </div>
                    <div class="form-group">
                        <label>Reason for Reversal <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reverse_reason" name="reason" rows="3" placeholder="Please provide reason for reversing this approval..." required></textarea>
                        <small class="form-text text-danger" id="error_reverse_reason"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fa fa-undo"></i> Reverse to Pending
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Re-approve Modal -->
<div class="modal fade" id="reapproveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Re-approve Request</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="reapproveForm">
                @csrf
                <input type="hidden" id="reapprove_request_id">
                <input type="hidden" id="reapprove_coverage_mode">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Re-approve:</strong> You are about to re-approve a previously rejected request.
                    </div>
                    <div class="form-group" id="reapprove_auth_code_div" style="display:none;">
                        <label>Authorization Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="reapprove_auth_code" name="auth_code" placeholder="Enter HMO auth code">
                        <small class="form-text text-danger" id="error_reapprove_auth_code"></small>
                        <small class="form-text text-muted">Required for secondary coverage</small>
                    </div>
                    <div class="form-group">
                        <label>Validation Notes</label>
                        <textarea class="form-control" id="reapprove_notes" name="validation_notes" rows="3" placeholder="Optional notes for re-approval..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-check"></i> Re-approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Batch Approve Modal -->
<div class="modal fade" id="batchApproveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Batch Approve Requests</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="batchApproveForm">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Batch Approve:</strong> You are about to approve <strong><span id="batchApproveCount">0</span></strong> requests.
                        <br><small class="text-warning">Note: Secondary coverage requests will be skipped (require individual auth codes).</small>
                    </div>
                    <div class="form-group">
                        <label>Validation Notes (applied to all)</label>
                        <textarea class="form-control" name="validation_notes" rows="3" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-check"></i> Approve All
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Batch Reject Modal -->
<div class="modal fade" id="batchRejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Batch Reject Requests</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="batchRejectForm">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Batch Reject:</strong> You are about to reject <strong><span id="batchRejectCount">0</span></strong> requests.
                    </div>
                    <div class="form-group">
                        <label>Rejection Reason <span class="text-danger">*</span></label>
                        <select class="form-control" name="rejection_reason" required>
                            <option value="">-- Select Reason --</option>
                            @foreach($rejectionReasons as $key => $reason)
                                <option value="{{ $key }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Additional Notes</label>
                        <textarea class="form-control" name="validation_notes" rows="3" placeholder="Optional additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-times"></i> Reject All
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Patient History Modal -->
<div class="modal fade" id="patientHistoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="fa fa-history"></i> Patient HMO History</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card-modern bg-info text-white">
                            <div class="card-body text-center">
                                <h5 class="mb-0">Total HMO Claims</h5>
                                <h3 class="mb-0" id="history_total_claims">₦0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-modern bg-primary text-white">
                            <div class="card-body text-center">
                                <h5 class="mb-0">This Month Claims</h5>
                                <h3 class="mb-0" id="history_month_claims">₦0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-modern bg-success text-white">
                            <div class="card-body text-center">
                                <h5 class="mb-0">Total HMO Visits</h5>
                                <h3 class="mb-0" id="history_total_visits">0</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="historyTable">
                        <thead class="thead-dark">
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
                            <tr><td colspan="8" class="text-center">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<script>
$(function() {
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
                    d.date_from = $('#filter_date_from').val();
                    d.date_to = $('#filter_date_to').val();
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
            $('#pending_count, #pending_badge').text(data.pending);
            $('#express_count, #express_badge').text(data.express);
            $('#approved_today_count').text(data.approved_today);
            $('#rejected_today_count').text(data.rejected_today);
            $('#overdue_count').text(data.overdue);
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
            let data = response.data;

            $('#detail_request_id').text(data.id);
            $('#detail_patient_name').text(data.patient_name);
            $('#detail_file_no').text(data.file_no);
            $('#detail_hmo_no').text(data.hmo_no || 'N/A');
            $('#detail_hmo_name').text(data.hmo_name);
            $('#detail_item_type').text(data.item_type);
            $('#detail_item_name').text(data.item_name);
            $('#detail_qty').text(data.qty);
            $('#detail_original_price').text(formatNumber(data.original_price || 0));
            $('#detail_claims_amount').text(formatNumber(data.claims_amount || 0));
            $('#detail_payable_amount').text(formatNumber(data.payable_amount || 0));

            let coverageBadge = data.coverage_mode === 'express' ? '<span class="badge badge-success">EXPRESS</span>' :
                               data.coverage_mode === 'primary' ? '<span class="badge badge-warning">PRIMARY</span>' :
                               '<span class="badge badge-danger">SECONDARY</span>';
            $('#detail_coverage_mode').html(coverageBadge);

            let statusBadge = data.validation_status === 'approved' ? '<span class="badge badge-success">APPROVED</span>' :
                             data.validation_status === 'rejected' ? '<span class="badge badge-danger">REJECTED</span>' :
                             '<span class="badge badge-warning">PENDING</span>';
            $('#detail_validation_status').html(statusBadge);

            $('#detail_auth_code').text(data.auth_code || '-');
            $('#detail_validated_by').text(data.validated_by_name || '-');
            $('#detail_validated_at').text(data.validated_at || '-');
            $('#detail_validation_notes').text(data.validation_notes || '-');
            $('#detail_created_at').text(data.created_at);

            $('#detailsModal').modal('show');
        });
    });

    // Clinical context button
    $(document).on('click', '.clinical-context-btn', function() {
        let patientId = $(this).data('patient-id');
        loadClinicalContext(patientId);
    });

    function loadClinicalContext(patientId) {
        // Store patient ID for see more buttons
        window.currentClinicalPatientId = patientId;

        // Show modal first with loading state
        $('#clinical-context-modal').modal('show');

        // Load vitals into vitals panel
        $('#vitals-panel-body').html('<div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading vitals...</p></div>');
        $.get("{{ url('hmo/patient') }}/" + patientId + "/vitals", function(vitals) {
            let vitalsHtml = '';
            if (vitals.length > 0) {
                vitals.forEach(function(v) {
                    vitalsHtml += `
                        <div class="vital-entry">
                            <div class="vital-entry-header">
                                <span class="vital-date"><i class="mdi mdi-calendar-clock"></i> ${new Date(v.created_at).toLocaleString()}</span>
                            </div>
                            <div class="vital-entry-grid">
                                <div class="vital-item" title="Blood Pressure">
                                    <i class="mdi mdi-heart-pulse"></i>
                                    <span class="vital-value">${v.blood_pressure || 'N/A'}</span>
                                    <span class="vital-label">Blood Pressure</span>
                                </div>
                                <div class="vital-item" title="Temperature">
                                    <i class="mdi mdi-thermometer"></i>
                                    <span class="vital-value">${v.temp || 'N/A'}°C</span>
                                    <span class="vital-label">Temperature</span>
                                </div>
                                <div class="vital-item" title="Heart Rate">
                                    <i class="mdi mdi-heart"></i>
                                    <span class="vital-value">${v.heart_rate || 'N/A'}</span>
                                    <span class="vital-label">Heart Rate</span>
                                </div>
                                <div class="vital-item" title="Respiratory Rate">
                                    <i class="mdi mdi-lungs"></i>
                                    <span class="vital-value">${v.resp_rate || 'N/A'}</span>
                                    <span class="vital-label">Resp. Rate</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
                vitalsHtml += `<div class="text-center mt-3"><a href="{{ url('patient') }}/${patientId}?section=vitalsCardBody" class="btn btn-primary btn-sm" target="_blank"><i class="fa fa-external-link"></i> See More in Patient Profile</a></div>`;
            } else {
                vitalsHtml = '<div class="alert alert-info text-center"><i class="mdi mdi-information"></i> No vitals recorded for this patient</div>';
            }
            $('#vitals-panel-body').html(vitalsHtml);
        }).fail(function() {
            $('#vitals-panel-body').html('<div class="alert alert-danger">Failed to load vitals</div>');
        });

        // Load clinical notes
        $('#notes-panel-body').html('<div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading notes...</p></div>');
        $.get("{{ url('hmo/patient') }}/" + patientId + "/notes", function(notes) {
            let notesHtml = '';
            if (notes.length > 0) {
                notes.forEach(function(n) {
                    notesHtml += `
                        <div class="note-entry">
                            <div class="note-header">
                                <div>
                                    <span class="note-doctor">${n.doctor}</span>
                                    <span class="specialty-tag">${n.specialty}</span>
                                </div>
                                <span class="note-date">${n.date_formatted}</span>
                            </div>
                            ${n.reasons_for_encounter ? `<div class="note-diagnosis"><span class="diagnosis-badge">${n.reasons_for_encounter}</span></div>` : ''}
                            <div class="note-content">${n.notes}</div>
                        </div>
                    `;
                });
                notesHtml += `<div class="text-center mt-3"><a href="{{ url('patient') }}/${patientId}?section=encountersCardBody" class="btn btn-primary btn-sm" target="_blank"><i class="fa fa-external-link"></i> See More in Patient Profile</a></div>`;
            } else {
                notesHtml = '<div class="alert alert-info text-center"><i class="mdi mdi-information"></i> No clinical notes found for this patient</div>';
            }
            $('#notes-panel-body').html(notesHtml);
        }).fail(function() {
            $('#notes-panel-body').html('<div class="alert alert-danger">Failed to load notes</div>');
        });

        // Load medications
        $('#medications-panel-body').html('<div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading medications...</p></div>');
        $.get("{{ url('hmo/patient') }}/" + patientId + "/medications", function(meds) {
            let medsHtml = '';
            if (meds.length > 0) {
                meds.forEach(function(m) {
                    let statusClass = m.status === 'dispensed' ? 'status-completed' : 'status-active';
                    medsHtml += `
                        <div class="medication-card">
                            <div class="medication-header">
                                <span class="medication-name"><i class="mdi mdi-pill"></i> ${m.drug_name}</span>
                                <span class="medication-status ${statusClass}">${m.status.toUpperCase()}</span>
                            </div>
                            <div class="medication-details">
                                <div class="medication-detail-item"><strong>Dose:</strong> ${m.dose}</div>
                                <div class="medication-detail-item"><strong>Frequency:</strong> ${m.freq}</div>
                                <div class="medication-detail-item"><strong>Duration:</strong> ${m.duration}</div>
                                <div class="medication-detail-item"><strong>Prescribed:</strong> ${m.requested_date}</div>
                                <div class="medication-detail-item"><strong>By:</strong> ${m.doctor}</div>
                            </div>
                        </div>
                    `;
                });
                medsHtml += `<div class="text-center mt-3"><a href="{{ url('patient') }}/${patientId}?section=prescriptionsNotesCardBody" class="btn btn-primary btn-sm" target="_blank"><i class="fa fa-external-link"></i> See More in Patient Profile</a></div>`;
            } else {
                medsHtml = '<div class="alert alert-info text-center"><i class="mdi mdi-information"></i> No medications found for this patient</div>';
            }
            $('#medications-panel-body').html(medsHtml);
        }).fail(function() {
            $('#medications-panel-body').html('<div class="alert alert-danger">Failed to load medications</div>');
        });

        // Allergies placeholder (can be extended later)
        $('#allergies-panel-body').html('<div class="alert alert-info text-center"><i class="mdi mdi-information"></i> Allergy information not available</div>');
    }

    // Refresh clinical panel
    $(document).on('click', '.refresh-clinical-btn', function() {
        // Refresh current patient's data
        if (window.currentClinicalPatientId) {
            loadClinicalContext(window.currentClinicalPatientId);
        } else {
            swal('Info', 'Click the Clinical button on a patient row to refresh data', 'info');
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

        $('#approveModal').modal('show');
    });

    // Submit approve form
    $('#approveForm').submit(function(e) {
        e.preventDefault();
        $('.text-danger').text('');

        let id = $('#approve_request_id').val();

        $.ajax({
            url: "{{ url('hmo/requests') }}/" + id + "/approve",
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                $('#approveModal').modal('hide');
                table.ajax.reload();
                loadQueueCounts();
                loadFinancialSummary();
                swal('Success', response.message, 'success');
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    let errors = xhr.responseJSON.errors;
                    $.each(errors, function(key, value) {
                        $('#error_' + key).text(value[0]);
                    });
                } else {
                    swal('Error', xhr.responseJSON.message || 'An error occurred', 'error');
                }
            }
        });
    });

    // Show reject modal
    $(document).on('click', '.reject-btn', function() {
        let id = $(this).data('id');

        $('#reject_request_id').val(id);
        $('#rejection_reason').val('');
        $('#reject_notes').val('');
        $('.text-danger').text('');

        $('#rejectModal').modal('show');
    });

    // Submit reject form
    $('#rejectForm').submit(function(e) {
        e.preventDefault();
        $('.text-danger').text('');

        let id = $('#reject_request_id').val();

        $.ajax({
            url: "{{ url('hmo/requests') }}/" + id + "/reject",
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                $('#rejectModal').modal('hide');
                table.ajax.reload();
                loadQueueCounts();
                loadFinancialSummary();
                swal('Success', response.message, 'success');
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    $('#error_rejection_reason').text(xhr.responseJSON.message);
                } else {
                    swal('Error', xhr.responseJSON.message || 'An error occurred', 'error');
                }
            }
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
                swal('Success', response.message, 'success');
            },
            error: function(xhr) {
                swal('Error', xhr.responseJSON.message || 'An error occurred', 'error');
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

        $('#reapproveModal').modal('show');
    });

    // Submit re-approve form
    $('#reapproveForm').submit(function(e) {
        e.preventDefault();
        let id = $('#reapprove_request_id').val();

        $.ajax({
            url: "{{ url('hmo/requests') }}/" + id + "/reapprove",
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                $('#reapproveModal').modal('hide');
                table.ajax.reload();
                loadQueueCounts();
                loadFinancialSummary();
                swal('Success', response.message, 'success');
            },
            error: function(xhr) {
                swal('Error', xhr.responseJSON.message || 'An error occurred', 'error');
            }
        });
    });

    // Batch approve button
    $('#batchApproveBtn').on('click', function() {
        if (selectedIds.length === 0) {
            swal('Warning', 'Please select at least one request', 'warning');
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
                swal('Success', response.message, 'success');
            },
            error: function(xhr) {
                swal('Error', xhr.responseJSON.message || 'An error occurred', 'error');
            }
        });
    });

    // Batch reject button
    $('#batchRejectBtn').on('click', function() {
        if (selectedIds.length === 0) {
            swal('Warning', 'Please select at least one request', 'warning');
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
                swal('Success', response.message, 'success');
            },
            error: function(xhr) {
                swal('Error', xhr.responseJSON.message || 'An error occurred', 'error');
            }
        });
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
</style>
@endsection
