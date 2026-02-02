@extends('admin.layouts.app')

@section('title', 'Payroll Batches')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-cash-register mr-2"></i>Payroll Batches
                    </h3>
                    <p class="text-muted mb-0">Process and manage monthly payroll</p>
                </div>
                <button type="button" class="btn btn-primary" id="createBatchBtn" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                    <i class="mdi mdi-plus mr-1"></i> Create Payroll Batch
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <!-- Draft -->
                <div class="col-lg col-md-4 col-sm-6 mb-3 mb-lg-0">
                    <div class="card-modern border-0 h-100" style="border-radius: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body text-white py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50" style="font-size: 0.75rem;">Draft</h6>
                                    <h3 class="mb-0" id="draftCount">0</h3>
                                </div>
                                <i class="mdi mdi-file-document-edit-outline" style="font-size: 2rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Pending Approval -->
                <div class="col-lg col-md-4 col-sm-6 mb-3 mb-lg-0">
                    <div class="card-modern border-0 h-100" style="border-radius: 10px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="card-body text-white py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50" style="font-size: 0.75rem;">Pending Approval</h6>
                                    <h3 class="mb-0" id="pendingCount">0</h3>
                                </div>
                                <i class="mdi mdi-clock-outline" style="font-size: 2rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Approved (Awaiting Payment) -->
                <div class="col-lg col-md-4 col-sm-6 mb-3 mb-lg-0">
                    <div class="card-modern border-0 h-100" style="border-radius: 10px; background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);">
                        <div class="card-body text-white py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50" style="font-size: 0.75rem;">Awaiting Payment</h6>
                                    <h3 class="mb-0" id="approvedCount">0</h3>
                                </div>
                                <i class="mdi mdi-clock-check-outline" style="font-size: 2rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Paid -->
                <div class="col-lg col-md-6 col-sm-6 mb-3 mb-lg-0">
                    <div class="card-modern border-0 h-100" style="border-radius: 10px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                        <div class="card-body text-white py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50" style="font-size: 0.75rem;">Paid</h6>
                                    <h3 class="mb-0" id="paidCount">0</h3>
                                </div>
                                <i class="mdi mdi-check-circle-outline" style="font-size: 2rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- This Month Total -->
                <div class="col-lg col-md-6 col-sm-12">
                    <div class="card-modern border-0 h-100" style="border-radius: 10px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="card-body text-white py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50" style="font-size: 0.75rem;">This Month Total</h6>
                                    <h4 class="mb-0" id="monthTotal">₦0</h4>
                                </div>
                                <i class="mdi mdi-currency-ngn" style="font-size: 2rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payroll Batches Table Card -->
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                    <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-format-list-bulleted mr-2" style="color: var(--primary-color);"></i>
                        Payroll Batches
                    </h5>
                    <select id="statusFilter" class="form-control" style="border-radius: 8px; width: 150px;">
                        <option value="">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table id="payrollBatchesTable" class="table table-hover" style="width: 100%;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="font-weight: 600; color: #495057;">SN</th>
                                    <th style="font-weight: 600; color: #495057;">Batch Number</th>
                                    <th style="font-weight: 600; color: #495057;">Period</th>
                                    <th style="font-weight: 600; color: #495057;">Staff Count</th>
                                    <th style="font-weight: 600; color: #495057;">Total Amount</th>
                                    <th style="font-weight: 600; color: #495057;">Status</th>
                                    <th style="font-weight: 600; color: #495057;">Created</th>
                                    <th style="font-weight: 600; color: #495057;">Actions</th>
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

<!-- Create Batch Modal (Advanced with Tiered Selection) -->
<div class="modal fade" id="createBatchModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-cash-register mr-2" style="color: var(--primary-color);"></i>
                    Create Payroll Batch
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="createBatchForm">
                @csrf
                <div class="modal-body" style="padding: 1.5rem; max-height: 75vh; overflow-y: auto;">
                    <!-- Step 1: Basic Info -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #495057;">Payroll Month *</label>
                                <input type="month" class="form-control" name="pay_period" id="pay_period" required
                                       style="border-radius: 8px; padding: 0.75rem;" value="{{ date('Y-m') }}">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #495057;">Description</label>
                                <input type="text" class="form-control" name="description" id="description"
                                       style="border-radius: 8px; padding: 0.75rem;" placeholder="e.g., January 2026 Salary">
                            </div>
                        </div>
                    </div>

                    <!-- Days Worked Range -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #495057;">Work Period Start *</label>
                                <input type="date" class="form-control" name="work_period_start" id="work_period_start" required
                                       style="border-radius: 8px; padding: 0.75rem;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #495057;">Work Period End *</label>
                                <input type="date" class="form-control" name="work_period_end" id="work_period_end" required
                                       style="border-radius: 8px; padding: 0.75rem;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #495057;">Days Summary</label>
                                <div class="card-modern bg-light border-0" style="border-radius: 8px;">
                                    <div class="card-body py-2 px-3">
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Days in Month:</span>
                                            <strong id="daysInMonthDisplay">--</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Days Worked:</span>
                                            <strong id="daysWorkedDisplay" class="text-primary">--</strong>
                                        </div>
                                        <div class="d-flex justify-content-between border-top pt-1 mt-1">
                                            <span class="text-muted">Pro-rata Factor:</span>
                                            <strong id="proRataFactor" class="text-success">--</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Selection Mode -->
                    <div class="mb-4">
                        <label class="form-label" style="font-weight: 600; color: #495057;">
                            <i class="mdi mdi-account-check mr-1"></i> Staff Selection Mode
                        </label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card-modern selection-mode-card active" data-mode="all" style="cursor: pointer; border-radius: 8px; border: 2px solid var(--primary-color);">
                                    <div class="card-body py-3 text-center">
                                        <i class="mdi mdi-account-group text-primary" style="font-size: 2rem;"></i>
                                        <h6 class="mb-1 mt-2">All Staff</h6>
                                        <small class="text-muted" id="allStaffCount">Loading...</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card-modern selection-mode-card" data-mode="department" style="cursor: pointer; border-radius: 8px; border: 2px solid #dee2e6;">
                                    <div class="card-body py-3 text-center">
                                        <i class="mdi mdi-office-building text-info" style="font-size: 2rem;"></i>
                                        <h6 class="mb-1 mt-2">By Department</h6>
                                        <small class="text-muted">Select specific departments</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card-modern selection-mode-card" data-mode="custom" style="cursor: pointer; border-radius: 8px; border: 2px solid #dee2e6;">
                                    <div class="card-body py-3 text-center">
                                        <i class="mdi mdi-account-search text-warning" style="font-size: 2rem;"></i>
                                        <h6 class="mb-1 mt-2">Custom Selection</h6>
                                        <small class="text-muted">Pick individual staff</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="selection_mode" id="selection_mode" value="all">
                    </div>

                    <!-- Department Selection Panel (hidden by default) -->
                    <div id="departmentSelectionPanel" style="display: none;" class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0" style="font-weight: 600;">
                                <i class="mdi mdi-office-building mr-1"></i> Select Departments
                            </h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary mr-2" id="selectAllDepts">
                                    <i class="mdi mdi-checkbox-multiple-marked"></i> Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllDepts">
                                    <i class="mdi mdi-checkbox-multiple-blank-outline"></i> Clear All
                                </button>
                            </div>
                        </div>
                        <div id="departmentList" class="row">
                            <div class="col-12 text-center py-3">
                                <div class="spinner-border spinner-border-sm text-primary"></div>
                                <span class="text-muted ml-2">Loading departments...</span>
                            </div>
                        </div>

                        <!-- Employment Type Filter -->
                        <h6 class="mb-3 mt-4" style="font-weight: 600;">
                            <i class="mdi mdi-briefcase mr-1"></i> Filter by Employment Type (Optional)
                        </h6>
                        <div id="employmentTypeCheckboxes" class="row">
                            <div class="col-12 text-center py-3">
                                <div class="spinner-border spinner-border-sm text-primary"></div>
                                <span class="text-muted ml-2">Loading...</span>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Selection Panel (hidden by default) -->
                    <div id="customSelectionPanel" style="display: none;" class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0" style="font-weight: 600;">
                                <i class="mdi mdi-account-search mr-1"></i> Select Individual Staff
                            </h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary mr-2" id="selectAllVisible">
                                    <i class="mdi mdi-checkbox-multiple-marked"></i> Select Visible
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllCustom">
                                    <i class="mdi mdi-checkbox-multiple-blank-outline"></i> Clear All
                                </button>
                            </div>
                        </div>
                        <!-- Search/Filter -->
                        <div class="row mb-3">
                            <div class="col-md-5">
                                <input type="text" class="form-control" id="customStaffSearch" placeholder="Search by name or employee ID..." style="border-radius: 8px;">
                            </div>
                            <div class="col-md-4">
                                <select class="form-control" id="customDeptFilter" style="border-radius: 8px;">
                                    <option value="">All Departments</option>
                                </select>
                            </div>
                            <div class="col-md-3 text-right">
                                <span class="text-muted small"><span id="customSelectedCount">0</span> selected</span>
                            </div>
                        </div>
                        <!-- Staff Table with Virtual Scroll -->
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;" id="customStaffTableContainer">
                            <table class="table table-sm table-hover" id="customStaffTable">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th class="text-right">Gross</th>
                                        <th class="text-right">Net Salary</th>
                                    </tr>
                                </thead>
                                <tbody id="customStaffTableBody">
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-2" id="customPagination">
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card-modern bg-light border-0 text-center" style="border-radius: 8px;">
                                <div class="card-body py-3">
                                    <small class="text-muted d-block">Selected Staff</small>
                                    <h4 class="mb-0 text-primary" id="selectedStaffCount">0</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card-modern bg-light border-0 text-center" style="border-radius: 8px;">
                                <div class="card-body py-3">
                                    <small class="text-muted d-block">Total Gross</small>
                                    <h5 class="mb-0 text-success" id="selectedTotalGross">₦0.00</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card-modern bg-light border-0 text-center" style="border-radius: 8px;">
                                <div class="card-body py-3">
                                    <small class="text-muted d-block">Total Deductions</small>
                                    <h5 class="mb-0 text-danger" id="selectedTotalDeductions">₦0.00</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card-modern bg-primary text-white border-0 text-center" style="border-radius: 8px;">
                                <div class="card-body py-3">
                                    <small class="d-block" style="opacity: 0.8;">Est. Net Payable</small>
                                    <h5 class="mb-0" id="selectedTotalNet">₦0.00</h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-3 mb-0" style="border-radius: 8px; display: none;" id="noProfilesAlert">
                        <i class="mdi mdi-alert-outline mr-1"></i>
                        <small>Some active staff members don't have salary profiles. <a href="{{ route('hr.salary-profiles.index') }}">Configure them here</a>.</small>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                    <div class="mr-auto">
                        <span class="text-muted" id="selectionSummary">0 staff selected</span>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="createBatchSubmitBtn" style="border-radius: 8px;" disabled>
                        <i class="mdi mdi-check mr-1"></i> Create Payroll Batch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Batch Modal - Enhanced with Timeline -->
<div class="modal fade" id="viewBatchModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 1100px;" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-eye mr-2" style="color: var(--primary-color);"></i>
                    Payroll Batch Details - <span id="viewBatchNumber"></span>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem; max-height: 80vh; overflow-y: auto;">
                <div class="row">
                    <!-- Left Column: Batch Info & Timeline -->
                    <div class="col-lg-5">
                        <!-- Batch Summary Card -->
                        <div class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-information-outline mr-1"></i> Batch Information
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0" style="font-size: 0.9rem;">
                                    <tbody id="batchInfoTable">
                                        <!-- Populated by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Financials Card -->
                        <div class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-currency-ngn mr-1"></i> Financial Summary
                                </h6>
                            </div>
                            <div class="card-body py-2 px-3">
                                <div class="row text-center">
                                    <div class="col-6 border-right">
                                        <small class="text-muted d-block">Total Gross</small>
                                        <strong class="text-dark" id="viewTotalGross">₦0.00</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Total Deductions</small>
                                        <strong class="text-danger" id="viewTotalDeductions">₦0.00</strong>
                                    </div>
                                </div>
                                <hr class="my-2">
                                <div class="text-center">
                                    <small class="text-muted d-block">Total Net Payable</small>
                                    <h4 class="text-success mb-0" id="viewTotalNet">₦0.00</h4>
                                </div>
                            </div>
                        </div>

                        <!-- Workflow Timeline -->
                        <div class="card-modern" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-timeline-text-outline mr-1"></i> Workflow Timeline
                                </h6>
                            </div>
                            <div class="card-body py-2 px-3">
                                <div id="workflowTimeline" class="timeline-vertical">
                                    <!-- Populated by JS -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Payroll Items -->
                    <div class="col-lg-7">
                        <div class="card-modern" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-account-group mr-1"></i> Payroll Items
                                    <span class="badge badge-primary ml-1" id="itemsCount">0</span>
                                </h6>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="printPayslipsBtn" title="Print Payslips">
                                        <i class="mdi mdi-printer"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                    <table id="batchItemsTable" class="table table-sm table-hover mb-0">
                                        <thead class="bg-light sticky-top">
                                            <tr>
                                                <th>Staff</th>
                                                <th class="text-right">Basic</th>
                                                <th class="text-right">Gross</th>
                                                <th class="text-right">Net</th>
                                                <th class="text-center" style="width: 80px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Populated by JS -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Expense Link (if applicable) -->
                        <div id="expenseInfoCard" class="card-modern mt-3" style="border-radius: 10px; border: 1px solid #e9ecef; display: none;">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="mdi mdi-receipt text-primary mr-2"></i>
                                        <strong>Linked Expense:</strong>
                                        <span id="expenseReference">-</span>
                                    </div>
                                    <span class="badge" id="expenseStatus">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between" style="border-top: 1px solid #e9ecef;">
                <div id="batchActionButtons">
                    <!-- Dynamic action buttons -->
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Timeline CSS -->
<style>
.timeline-vertical {
    position: relative;
    padding-left: 30px;
}
.timeline-vertical::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}
.timeline-item {
    position: relative;
    padding-bottom: 15px;
    margin-bottom: 5px;
}
.timeline-item:last-child {
    padding-bottom: 0;
    margin-bottom: 0;
}
.timeline-item .timeline-dot {
    position: absolute;
    left: -24px;
    top: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: white;
}
.timeline-item .timeline-dot.completed {
    background: #28a745;
}
.timeline-item .timeline-dot.pending {
    background: #e9ecef;
    border: 2px solid #adb5bd;
}
.timeline-item .timeline-dot.pending i {
    color: #adb5bd;
}
.timeline-item .timeline-dot.rejected {
    background: #dc3545;
}
.timeline-item .timeline-dot.current {
    background: #007bff;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(0, 123, 255, 0); }
    100% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0); }
}
.timeline-item .timeline-content {
    padding-left: 5px;
}
.timeline-item .timeline-title {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 2px;
}
.timeline-item .timeline-meta {
    font-size: 0.8rem;
    color: #6c757d;
}
.timeline-item .timeline-comment {
    font-size: 0.8rem;
    background: #f8f9fa;
    border-radius: 6px;
    padding: 6px 10px;
    margin-top: 6px;
    border-left: 3px solid #007bff;
}
.timeline-item .timeline-comment.rejection {
    border-left-color: #dc3545;
    background: #fff5f5;
}

/* Duplicate Staff Modal Styles */
#duplicateStaffTable tbody tr {
    transition: background-color 0.2s ease;
}
#duplicateStaffTable tbody tr:hover {
    background-color: #f8f9fa;
}
#duplicateStaffTable tbody tr.selected-for-replace {
    background-color: #fff3e0 !important;
}
.duplicate-staff-checkbox:checked + label,
.duplicate-staff-checkbox:checked {
    accent-color: #ff9800;
}
#duplicateHandlingModal .form-check-input:checked {
    background-color: #ff9800;
    border-color: #ff9800;
}
#duplicateHandlingModal .form-check-input:focus {
    box-shadow: 0 0 0 0.2rem rgba(255, 152, 0, 0.25);
}
</style>

<!-- Submit/Approve/Reject/Mark Paid Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header" id="actionModalHeader" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title text-white" id="actionModalTitle"></h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="actionForm">
                @csrf
                <input type="hidden" id="actionBatchId">
                <input type="hidden" id="actionType">
                <div class="modal-body" style="padding: 1.5rem;">
                    <p id="actionDescription"></p>

                    <!-- Payment Source Section (shown only for mark-paid) -->
                    <div id="paymentSourceSection" style="display: none;">
                        <div class="card-modern bg-light mb-3">
                            <div class="card-body py-3">
                                <h6 class="mb-3"><i class="mdi mdi-cash-multiple mr-2"></i>Payment Source <span class="text-danger">*</span></h6>

                                <div class="form-group mb-3">
                                    <label style="font-weight: 600; color: #495057;">Payment Method</label>
                                    <select name="payment_method" id="actionPaymentMethod" class="form-control" style="border-radius: 8px;">
                                        <option value="">-- Select Source --</option>
                                        <option value="cash">Cash (from Cash in Hand)</option>
                                        <option value="bank_transfer" selected>Bank Transfer</option>
                                    </select>
                                    <small class="form-text text-muted">
                                        Select where the salary payments will be disbursed from.
                                    </small>
                                </div>

                                <div class="form-group mb-0" id="actionBankSelectionGroup">
                                    <label style="font-weight: 600; color: #495057;">Select Bank <span class="text-danger">*</span></label>
                                    <select name="bank_id" id="actionBankId" class="form-control" style="border-radius: 8px;">
                                        <option value="">-- Select Bank --</option>
                                        @foreach(\App\Models\Bank::whereNotNull('account_id')->orderBy('name')->get() as $bank)
                                            <option value="{{ $bank->id }}">{{ $bank->name }} ({{ $bank->account_number }})</option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">
                                        <i class="mdi mdi-information-outline"></i>
                                        The selected bank's GL account will be credited.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Comments</label>
                        <textarea class="form-control" name="comments" id="actionComments" rows="3"
                                  style="border-radius: 8px; padding: 0.75rem;" placeholder="Add comments (optional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn" id="actionSubmitBtn" style="border-radius: 8px;"></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Duplicate Staff Handling Modal -->
<div class="modal fade" id="duplicateHandlingModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); border-radius: 12px 12px 0 0;">
                <h5 class="modal-title text-white">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Duplicate Staff Found
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 0;">
                <!-- Info Banner -->
                <div style="background: #fff3e0; padding: 1rem 1.5rem; border-bottom: 1px solid #ffe0b2;">
                    <p class="mb-1" style="color: #e65100; font-size: 0.9rem;">
                        <i class="fas fa-info-circle mr-2"></i>
                        The following staff already have payroll records for this period.
                    </p>
                    <p class="mb-0" style="color: #795548; font-size: 0.85rem;">
                        <strong>✓ Checked:</strong> Replace existing payroll (only from Draft batches)<br>
                        <strong>☐ Unchecked:</strong> Skip and keep existing payroll
                    </p>
                </div>

                <!-- Selection Actions -->
                <div style="padding: 1rem 1.5rem; background: #fafafa; border-bottom: 1px solid #e9ecef;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="selectAllDuplicates" style="width: 18px; height: 18px;">
                            <label class="form-check-label" for="selectAllDuplicates" style="font-weight: 600; color: #495057;">
                                Select All for Replacement
                            </label>
                        </div>
                        <div id="duplicateSelectionCount" style="font-size: 0.875rem; color: #6c757d;">
                            <span id="selectedDuplicateCount">0</span> of <span id="totalDuplicateCount">0</span> selected for replacement
                        </div>
                    </div>
                </div>

                <!-- Staff List -->
                <div style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-hover mb-0" id="duplicateStaffTable">
                        <thead style="background: #f8f9fa; position: sticky; top: 0; z-index: 10;">
                            <tr>
                                <th style="width: 50px; padding: 0.75rem 1rem;">
                                    <span class="text-muted" style="font-size: 0.75rem;">Replace</span>
                                </th>
                                <th style="padding: 0.75rem 1rem;">Staff</th>
                                <th style="padding: 0.75rem 1rem;">Existing Batch</th>
                                <th style="padding: 0.75rem 1rem; text-align: right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="duplicateStaffList">
                            <!-- Populated by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Summary -->
                <div id="duplicateSummary" style="padding: 1rem 1.5rem; background: #f8f9fa; border-top: 1px solid #e9ecef;">
                    <div class="row text-center">
                        <div class="col-4">
                            <div style="font-size: 1.25rem; font-weight: 700; color: #dc3545;" id="toReplaceCount">0</div>
                            <div style="font-size: 0.75rem; color: #6c757d;">Will Replace</div>
                        </div>
                        <div class="col-4">
                            <div style="font-size: 1.25rem; font-weight: 700; color: #28a745;" id="toSkipCount">0</div>
                            <div style="font-size: 0.75rem; color: #6c757d;">Will Skip</div>
                        </div>
                        <div class="col-4">
                            <div style="font-size: 1.25rem; font-weight: 700; color: #1976d2;" id="newStaffCount">0</div>
                            <div style="font-size: 0.75rem; color: #6c757d;">New Staff</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">
                    <i class="fas fa-times mr-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-warning" id="confirmDuplicateAction" style="border-radius: 8px;">
                    <i class="fas fa-check mr-1"></i>Continue with Selection
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(function() {
    // ========== CURRENCY FORMATTER ==========
    const formatCurrency = (amount) => '₦' + parseFloat(amount || 0).toLocaleString('en-NG', {minimumFractionDigits: 2});

    // ========== DATE/DAYS CALCULATION HELPERS ==========
    function updateWorkPeriodDefaults() {
        const payPeriod = $('#pay_period').val();
        if (payPeriod) {
            const [year, month] = payPeriod.split('-');
            const startDate = `${year}-${month}-01`;
            const lastDay = new Date(year, month, 0).getDate();
            const endDate = `${year}-${month}-${String(lastDay).padStart(2, '0')}`;

            $('#work_period_start').val(startDate);
            $('#work_period_end').val(endDate);
            $('#work_period_start').attr('min', startDate).attr('max', endDate);
            $('#work_period_end').attr('min', startDate).attr('max', endDate);

            updateDaysDisplay();
        }
    }

    function updateDaysDisplay() {
        const payPeriod = $('#pay_period').val();
        const workStart = $('#work_period_start').val();
        const workEnd = $('#work_period_end').val();

        if (payPeriod && workStart && workEnd) {
            const [year, month] = payPeriod.split('-');
            const daysInMonth = new Date(year, month, 0).getDate();
            const startDate = new Date(workStart);
            const endDate = new Date(workEnd);
            const daysWorked = Math.floor((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
            const proRataFactor = (daysWorked / daysInMonth * 100).toFixed(1);

            $('#daysInMonthDisplay').text(daysInMonth);
            $('#daysWorkedDisplay').text(daysWorked);
            $('#proRataFactor').text(proRataFactor + '%');

            // Update totals with pro-rata
            updateSelectionTotals();
        }
    }

    $('#pay_period').change(function() {
        updateWorkPeriodDefaults();
    });

    $('#work_period_start, #work_period_end').change(function() {
        updateDaysDisplay();
    });

    // ========== DATATABLE ==========
    const table = $('#payrollBatchesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('hr.payroll.index') }}",
            data: function(d) {
                d.status = $('#statusFilter').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'batch_number', name: 'batch_number' },
            { data: 'pay_period_formatted', name: 'pay_period' },
            { data: 'staff_count', name: 'staff_count', orderable: false },
            { data: 'total_amount_formatted', name: 'total_net_amount' },
            { data: 'status_badge', name: 'status' },
            { data: 'created_at', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[6, 'desc']],
        language: {
            emptyTable: "No payroll batches found",
            processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>'
        }
    });

    // Load stats
    function loadStats() {
        $.get("{{ route('hr.payroll.index') }}", { stats: true }, function(data) {
            $('#draftCount').text(data.draft || 0);
            $('#pendingCount').text(data.pending || 0);
            $('#approvedCount').text(data.approved || 0);
            $('#paidCount').text(data.paid || 0);
            $('#monthTotal').text(formatCurrency(data.month_total || 0));
        });
    }
    loadStats();

    $('#statusFilter').change(function() {
        table.ajax.reload();
    });

    // ========== TIERED BATCH CREATION ==========
    let staffSummary = null;
    let customStaffList = [];
    let customStaffPage = 1;
    let customStaffTotal = 0;
    let selectedDepartments = [];
    let selectedEmploymentTypes = [];
    let customSelectedIds = [];

    function getSelectionMode() {
        return $('#selection_mode').val() || 'all';
    }

    // Create batch button - load summary
    $('#createBatchBtn').click(function() {
        $('#createBatchForm')[0].reset();
        $('#pay_period').val('{{ date("Y-m") }}');
        updateWorkPeriodDefaults();

        // Reset selection state
        staffSummary = null;
        customStaffList = [];
        customSelectedIds = [];
        selectedDepartments = [];
        selectedEmploymentTypes = [];

        // Show loading in summary section
        $('#staffSummaryContent').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mb-0 mt-2">Loading staff summary...</p>
            </div>
        `);

        // Reset panels
        $('#departmentSelectionPanel').hide();
        $('#customSelectionPanel').hide();

        // Reset selection mode cards - activate "All Staff" card
        $('.selection-mode-card').removeClass('active').css('border-color', '#dee2e6');
        $('.selection-mode-card[data-mode="all"]').addClass('active').css('border-color', 'var(--primary-color)');
        $('#selection_mode').val('all');
        $('#allStaffCount').text('Loading...');

        $('#createBatchModal').modal('show');

        // Load staff summary
        loadStaffSummary();
    });

    function loadStaffSummary() {
        $.ajax({
            url: "{{ route('hr.payroll.staff-summary') }}",
            method: 'GET',
            success: function(data) {
                staffSummary = data;
                renderStaffSummary();
                updateSelectionTotals();
            },
            error: function(xhr) {
                $('#staffSummaryContent').html(`
                    <div class="alert alert-danger mb-0">
                        <i class="mdi mdi-alert-circle-outline mr-1"></i>
                        Failed to load staff summary. <a href="#" onclick="loadStaffSummary();return false;">Retry</a>
                    </div>
                `);
            }
        });
    }

    function renderStaffSummary() {
        if (!staffSummary) return;

        let html = `
            <div class="row mb-3">
                <div class="col-12">
                    <div class="d-flex flex-wrap">
        `;

        // Department summary cards
        staffSummary.by_department.forEach(dept => {
            html += `
                <div class="summary-card mr-2 mb-2" style="min-width: 140px; padding: 8px 12px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
                    <strong style="font-size: 0.85rem;">${dept.department || 'Unassigned'}</strong>
                    <div class="d-flex justify-content-between mt-1" style="font-size: 0.8rem;">
                        <span class="text-muted">${dept.count} staff</span>
                        <span class="text-primary">${formatCurrency(dept.total_net)}</span>
                    </div>
                </div>
            `;
        });

        html += `
                    </div>
                </div>
            </div>
            <div class="border-top pt-3">
                <div class="row text-center">
                    <div class="col-4">
                        <h4 class="mb-0 text-primary">${staffSummary.total_count}</h4>
                        <small class="text-muted">Total Staff</small>
                    </div>
                    <div class="col-4">
                        <h4 class="mb-0 text-success">${formatCurrency(staffSummary.total_gross)}</h4>
                        <small class="text-muted">Total Gross</small>
                    </div>
                    <div class="col-4">
                        <h4 class="mb-0 text-dark">${formatCurrency(staffSummary.total_net)}</h4>
                        <small class="text-muted">Total Net</small>
                    </div>
                </div>
            </div>
        `;

        $('#staffSummaryContent').html(html);

        // Populate department checkboxes
        let deptHtml = '';
        staffSummary.by_department.forEach(dept => {
            deptHtml += `
                <div class="col-md-6 mb-2">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input dept-checkbox"
                               id="dept_${dept.department || 'unassigned'}"
                               data-department="${dept.department || ''}"
                               data-count="${dept.count}"
                               data-gross="${dept.total_gross}"
                               data-deductions="${dept.total_deductions || 0}"
                               data-net="${dept.total_net}">
                        <label class="custom-control-label" for="dept_${dept.department || 'unassigned'}">
                            ${dept.department || 'Unassigned'}
                            <span class="badge badge-secondary ml-1">${dept.count}</span>
                            <small class="text-muted d-block">${formatCurrency(dept.total_net)}</small>
                        </label>
                    </div>
                </div>
            `;
        });
        $('#departmentList').html(deptHtml);

        // Populate employment type checkboxes
        let empTypeHtml = '';
        staffSummary.by_employment_type.forEach(et => {
            empTypeHtml += `
                <div class="col-md-4 mb-2">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input emp-type-checkbox"
                               id="emptype_${et.employment_type || 'unspecified'}"
                               data-type="${et.employment_type || ''}"
                               data-count="${et.count}"
                               data-gross="${et.total_gross}"
                               data-net="${et.total_net}">
                        <label class="custom-control-label" for="emptype_${et.employment_type || 'unspecified'}">
                            ${et.employment_type || 'Unspecified'}
                            <span class="badge badge-secondary ml-1">${et.count}</span>
                        </label>
                    </div>
                </div>
            `;
        });
        $('#employmentTypeCheckboxes').html(empTypeHtml);

        // Also populate custom department filter dropdown
        let customDeptOpts = '<option value="">All Departments</option>';
        staffSummary.by_department.forEach(dept => {
            customDeptOpts += `<option value="${dept.department || ''}">${dept.department || 'Unassigned'}</option>`;
        });
        $('#customDeptFilter').html(customDeptOpts);

        // Update the All Staff count display in the selection card
        $('#allStaffCount').text(staffSummary.total_count + ' staff');
    }

    // Selection mode card click handler
    $(document).on('click', '.selection-mode-card', function() {
        const mode = $(this).data('mode');

        // Update visual state
        $('.selection-mode-card').removeClass('active').css('border-color', '#dee2e6');
        $(this).addClass('active').css('border-color', 'var(--primary-color)');

        // Update hidden input
        $('#selection_mode').val(mode);

        // Show/hide appropriate panels
        if (mode === 'all') {
            $('#departmentSelectionPanel').hide();
            $('#customSelectionPanel').hide();
        } else if (mode === 'department') {
            $('#departmentSelectionPanel').show();
            $('#customSelectionPanel').hide();
        } else if (mode === 'custom') {
            $('#departmentSelectionPanel').hide();
            $('#customSelectionPanel').show();
            loadCustomStaffList();
        }

        updateSelectionTotals();
    });

    // Selection mode change (for radio buttons if used)
    $('input[name="selection_mode"]').change(function() {
        const mode = $(this).val();

        if (mode === 'all') {
            $('#departmentSelectionPanel').hide();
            $('#customSelectionPanel').hide();
        } else if (mode === 'department') {
            $('#departmentSelectionPanel').show();
            $('#customSelectionPanel').hide();
        } else if (mode === 'custom') {
            $('#departmentSelectionPanel').hide();
            $('#customSelectionPanel').show();
            loadCustomStaffList();
        }

        updateSelectionTotals();
    });

    // Department/employment type checkbox change
    $(document).on('change', '.dept-checkbox, .emp-type-checkbox', function() {
        updateSelectionTotals();
    });

    // Select all departments
    $('#selectAllDepts').click(function() {
        $('.dept-checkbox').prop('checked', true);
        updateSelectionTotals();
    });

    $('#deselectAllDepts').click(function() {
        $('.dept-checkbox').prop('checked', false);
        updateSelectionTotals();
    });

    function updateSelectionTotals() {
        const mode = getSelectionMode();
        let staffCount = 0, totalGross = 0, totalDeductions = 0, totalNet = 0;

        if (mode === 'all' && staffSummary) {
            staffCount = staffSummary.total_count;
            totalGross = staffSummary.total_gross;
            totalDeductions = staffSummary.total_deductions || 0;
            totalNet = staffSummary.total_net;
        } else if (mode === 'department') {
            $('.dept-checkbox:checked').each(function() {
                staffCount += parseInt($(this).data('count')) || 0;
                totalGross += parseFloat($(this).data('gross')) || 0;
                totalDeductions += parseFloat($(this).data('deductions')) || 0;
                totalNet += parseFloat($(this).data('net')) || 0;
            });
        } else if (mode === 'custom') {
            staffCount = customSelectedIds.length;
            customStaffList.filter(s => customSelectedIds.includes(s.id)).forEach(s => {
                totalGross += parseFloat(s.gross_salary) || 0;
                totalDeductions += parseFloat(s.total_deductions) || 0;
                totalNet += parseFloat(s.net_salary) || 0;
            });
        }

        // If deductions not directly available, calculate from gross - net
        if (totalDeductions === 0 && totalGross > 0 && totalNet > 0) {
            totalDeductions = totalGross - totalNet;
        }

        // Apply pro-rata if applicable
        const payPeriod = $('#pay_period').val();
        const workStart = $('#work_period_start').val();
        const workEnd = $('#work_period_end').val();

        if (payPeriod && workStart && workEnd) {
            const [year, month] = payPeriod.split('-');
            const daysInMonth = new Date(year, month, 0).getDate();
            const startDate = new Date(workStart);
            const endDate = new Date(workEnd);
            const daysWorked = Math.floor((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;

            if (daysWorked < daysInMonth) {
                totalGross = (totalGross / daysInMonth) * daysWorked;
                totalDeductions = (totalDeductions / daysInMonth) * daysWorked;
                totalNet = (totalNet / daysInMonth) * daysWorked;
            }
        }

        $('#selectedStaffCount').text(staffCount);
        $('#selectedTotalGross').text(formatCurrency(totalGross));
        $('#selectedTotalDeductions').text(formatCurrency(totalDeductions));
        $('#selectedTotalNet').text(formatCurrency(totalNet));

        // Enable/disable submit
        $('#createBatchSubmitBtn').prop('disabled', staffCount === 0);
    }

    // ========== CUSTOM STAFF SELECTION (PAGINATED) ==========
    function loadCustomStaffList(page = 1) {
        customStaffPage = page;
        const search = $('#customStaffSearch').val() || '';
        const dept = $('#customDeptFilter').val() || '';

        $('#customStaffTableBody').html(`
            <tr><td colspan="5" class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary"></div> Loading...
            </td></tr>
        `);

        $.ajax({
            url: "{{ route('hr.payroll.staff-by-criteria') }}",
            method: 'GET',
            data: { page: page, search: search, department: dept, per_page: 20 },
            success: function(response) {
                customStaffList = response.data || [];
                customStaffTotal = response.total || 0;
                renderCustomStaffTable();
                renderCustomPagination(response);
            },
            error: function() {
                $('#customStaffTableBody').html(`
                    <tr><td colspan="5" class="text-center text-danger py-3">
                        <i class="mdi mdi-alert-circle"></i> Failed to load staff
                    </td></tr>
                `);
            }
        });
    }

    function renderCustomStaffTable() {
        if (customStaffList.length === 0) {
            $('#customStaffTableBody').html(`
                <tr><td colspan="5" class="text-center text-muted py-3">No staff found</td></tr>
            `);
            return;
        }

        let html = '';
        customStaffList.forEach(staff => {
            const isSelected = customSelectedIds.includes(staff.id);
            html += `
                <tr class="${isSelected ? 'table-active' : ''}">
                    <td>
                        <input type="checkbox" class="custom-staff-checkbox"
                               data-id="${staff.id}"
                               data-gross="${staff.gross_salary}"
                               data-net="${staff.net_salary}"
                               ${isSelected ? 'checked' : ''}>
                    </td>
                    <td>
                        <strong>${staff.name}</strong>
                        <br><small class="text-muted">${staff.employee_id || 'N/A'}</small>
                    </td>
                    <td>${staff.department || 'N/A'}</td>
                    <td class="text-right">${formatCurrency(staff.gross_salary)}</td>
                    <td class="text-right font-weight-bold">${formatCurrency(staff.net_salary)}</td>
                </tr>
            `;
        });
        $('#customStaffTableBody').html(html);
    }

    function renderCustomPagination(response) {
        const totalPages = response.last_page || 1;
        const currentPage = response.current_page || 1;

        let html = `<small class="text-muted">Showing ${response.from || 0}-${response.to || 0} of ${response.total || 0}</small>`;

        if (totalPages > 1) {
            html += '<div class="btn-group btn-group-sm ml-2">';
            html += `<button type="button" class="btn btn-outline-secondary custom-page-btn" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>&laquo;</button>`;

            for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
                html += `<button type="button" class="btn ${i === currentPage ? 'btn-primary' : 'btn-outline-secondary'} custom-page-btn" data-page="${i}">${i}</button>`;
            }

            html += `<button type="button" class="btn btn-outline-secondary custom-page-btn" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}>&raquo;</button>`;
            html += '</div>';
        }

        $('#customPagination').html(html);
    }

    // Custom staff checkbox change
    $(document).on('change', '.custom-staff-checkbox', function() {
        const id = parseInt($(this).data('id'));
        if ($(this).is(':checked')) {
            if (!customSelectedIds.includes(id)) customSelectedIds.push(id);
        } else {
            customSelectedIds = customSelectedIds.filter(i => i !== id);
        }
        $(this).closest('tr').toggleClass('table-active', $(this).is(':checked'));
        $('#customSelectedCount').text(customSelectedIds.length);
        updateSelectionTotals();
    });

    // Pagination click
    $(document).on('click', '.custom-page-btn:not([disabled])', function() {
        loadCustomStaffList($(this).data('page'));
    });

    // Custom search/filter
    $('#customStaffSearch').on('input', debounce(function() {
        loadCustomStaffList(1);
    }, 300));

    $('#customDeptFilter').change(function() {
        loadCustomStaffList(1);
    });

    // Debounce helper
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // ========== CHECK FOR DUPLICATES ==========
    function checkForDuplicates(staffIds) {
        console.log('Checking duplicates for staffIds:', staffIds);
        return $.ajax({
            url: "{{ route('hr.payroll.check-duplicates') }}",
            method: 'POST',
            dataType: 'json',
            data: {
                _token: '{{ csrf_token() }}',
                pay_period: $('#pay_period').val(),
                staff_ids: staffIds
            }
        });
    }

    // ========== FORM SUBMISSION ==========
    $('#createBatchForm').submit(function(e) {
        e.preventDefault();

        // First gather the data
        const mode = getSelectionMode();
        let postData = {
            _token: '{{ csrf_token() }}',
            pay_period: $('#pay_period').val(),
            work_period_start: $('#work_period_start').val(),
            work_period_end: $('#work_period_end').val(),
            description: $('#description').val(),
            selection_mode: mode,
            duplicate_action: 'skip' // Default to skip
        };

        let staffIds = null;
        let totalSelectedCount = 0;

        if (mode === 'department') {
            const depts = [];
            const empTypes = [];
            $('.dept-checkbox:checked').each(function() {
                depts.push($(this).data('department'));
            });
            $('.emp-type-checkbox:checked').each(function() {
                empTypes.push($(this).data('type'));
            });
            if (depts.length === 0) {
                toastr.warning('Please select at least one department');
                return;
            }
            postData.departments = depts;
            postData.employment_types = empTypes;
            totalSelectedCount = parseInt($('#selectedCount').text()) || 0;
        } else if (mode === 'custom') {
            if (customSelectedIds.length === 0) {
                toastr.warning('Please select at least one staff member');
                return;
            }
            postData.staff_ids = customSelectedIds;
            staffIds = customSelectedIds;
            totalSelectedCount = customSelectedIds.length;
        }

        $('#createBatchSubmitBtn').prop('disabled', true)
            .html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Checking...');

        // Check for duplicates first
        checkForDuplicates(staffIds).then(function(response) {
            console.log('Duplicate check response:', response);
            if (response.has_duplicates) {
                console.log('Showing duplicate warning modal');
                // Show duplicate warning modal with total count
                showDuplicateWarning(response.duplicates, postData, totalSelectedCount);
            } else {
                console.log('No duplicates found, proceeding with batch creation');
                // No duplicates, proceed with batch creation
                submitBatchCreation(postData);
            }
        }).catch(function(xhr) {
            console.error('Duplicate check failed:', xhr);
            $('#createBatchSubmitBtn').prop('disabled', false)
                .html('<i class="mdi mdi-check mr-1"></i> Create Payroll Batch');
            toastr.error('Failed to check for duplicates');
        });
    });

    // ========== DUPLICATE HANDLING MODAL ==========
    let duplicateStaffData = [];
    let pendingBatchData = null;
    let newStaffCountForBatch = 0;

    function showDuplicateWarning(duplicates, postData, totalSelectedCount) {
        duplicateStaffData = duplicates;
        pendingBatchData = postData;
        newStaffCountForBatch = totalSelectedCount - duplicates.length;

        // Populate the duplicate staff table
        renderDuplicateStaffTable();
        updateDuplicateSummary();

        // Show the modal
        $('#duplicateHandlingModal').modal('show');

        // Reset button state
        $('#createBatchSubmitBtn').prop('disabled', false)
            .html('<i class="mdi mdi-check mr-1"></i> Create Payroll Batch');
    }

    function renderDuplicateStaffTable() {
        const tbody = $('#duplicateStaffList');
        tbody.empty();

        $('#totalDuplicateCount').text(duplicateStaffData.length);

        duplicateStaffData.forEach((staff, index) => {
            const canReplace = staff.existing_batch_status === 'draft';
            const disabledAttr = canReplace ? '' : 'disabled';
            const rowClass = canReplace ? '' : 'bg-light';
            const lockedIcon = canReplace ? '' : '<i class="fas fa-lock text-muted ml-1" title="Cannot replace - batch is not in Draft status"></i>';

            const row = `
                <tr style="transition: background-color 0.2s;" class="${rowClass}">
                    <td style="padding: 0.75rem 1rem; vertical-align: middle;">
                        <div class="form-check d-flex justify-content-center">
                            <input type="checkbox" class="form-check-input duplicate-staff-checkbox"
                                   data-staff-id="${staff.staff_id}"
                                   data-index="${index}"
                                   data-can-replace="${canReplace}"
                                   style="width: 20px; height: 20px; cursor: ${canReplace ? 'pointer' : 'not-allowed'};"
                                   ${disabledAttr}>
                        </div>
                    </td>
                    <td style="padding: 0.75rem 1rem;">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle mr-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <span style="color: white; font-weight: 600; font-size: 0.875rem;">${getInitials(staff.staff_name)}</span>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: #212529;">${staff.staff_name}</div>
                                <div style="font-size: 0.8rem; color: #6c757d;">${staff.employee_id} • ${staff.department || 'N/A'}</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding: 0.75rem 1rem; vertical-align: middle;">
                        <span class="badge badge-${getStatusBadgeClass(staff.existing_batch_status)}" style="padding: 0.5rem 0.75rem; font-size: 0.8rem;">
                            ${staff.existing_batch}
                        </span>${lockedIcon}
                        <div style="font-size: 0.75rem; color: ${canReplace ? '#6c757d' : '#dc3545'}; margin-top: 2px;">
                            ${canReplace ? 'Can be replaced' : 'Cannot replace - ' + staff.existing_batch_status.charAt(0).toUpperCase() + staff.existing_batch_status.slice(1)}
                        </div>
                    </td>
                    <td style="padding: 0.75rem 1rem; text-align: right; vertical-align: middle;">
                        <div style="font-weight: 600; color: #212529;">${formatCurrency(staff.net_salary || 0)}</div>
                        <div style="font-size: 0.75rem; color: #6c757d;">Existing</div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    function getStatusBadgeClass(status) {
        const classes = {
            'draft': 'secondary',
            'submitted': 'info',
            'approved': 'primary',
            'paid': 'success',
            'rejected': 'danger'
        };
        return classes[status] || 'secondary';
    }

    function getInitials(name) {
        if (!name) return '?';
        const parts = name.split(' ');
        if (parts.length >= 2) {
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    }

    function updateDuplicateSummary() {
        const selectedCount = $('.duplicate-staff-checkbox:checked').length;
        const totalCount = duplicateStaffData.length;
        const replaceableCount = $('.duplicate-staff-checkbox:not(:disabled)').length;
        const lockedCount = totalCount - replaceableCount;

        $('#selectedDuplicateCount').text(selectedCount);
        $('#totalDuplicateCount').text(replaceableCount > 0 ? replaceableCount + ' replaceable' : totalCount);
        $('#toReplaceCount').text(selectedCount);
        $('#toSkipCount').text(totalCount - selectedCount);
        $('#newStaffCount').text(newStaffCountForBatch);

        // Update select all checkbox state (based on replaceable items only)
        if (replaceableCount === 0) {
            $('#selectAllDuplicates').prop('checked', false).prop('indeterminate', false).prop('disabled', true);
        } else if (selectedCount === 0) {
            $('#selectAllDuplicates').prop('checked', false).prop('indeterminate', false).prop('disabled', false);
        } else if (selectedCount === replaceableCount) {
            $('#selectAllDuplicates').prop('checked', true).prop('indeterminate', false).prop('disabled', false);
        } else {
            $('#selectAllDuplicates').prop('indeterminate', true).prop('disabled', false);
        }
    }

    // Select all duplicates checkbox (only selects replaceable items)
    $('#selectAllDuplicates').change(function() {
        const isChecked = $(this).prop('checked');
        $('.duplicate-staff-checkbox:not(:disabled)').prop('checked', isChecked);
        $('.duplicate-staff-checkbox:not(:disabled)').each(function() {
            $(this).closest('tr').toggleClass('selected-for-replace', isChecked);
        });
        updateDuplicateSummary();
    });

    // Individual duplicate checkbox change
    $(document).on('change', '.duplicate-staff-checkbox', function() {
        $(this).closest('tr').toggleClass('selected-for-replace', $(this).prop('checked'));
        updateDuplicateSummary();
    });

    // Confirm duplicate action
    $('#confirmDuplicateAction').click(function() {
        const selectedForReplacement = [];
        const totalDuplicates = duplicateStaffData.length;
        let replaceableCount = 0;

        $('.duplicate-staff-checkbox').each(function() {
            const staffId = $(this).data('staff-id');
            const canReplace = $(this).data('can-replace');

            if (canReplace) {
                replaceableCount++;
            }

            if ($(this).prop('checked')) {
                selectedForReplacement.push(staffId);
            }
        });

        // Determine action based on selection
        if (selectedForReplacement.length === replaceableCount && replaceableCount > 0 && replaceableCount === totalDuplicates) {
            // All replaceable items selected and all are replaceable - use overwrite action
            pendingBatchData.duplicate_action = 'overwrite';
        } else if (selectedForReplacement.length === 0) {
            // None selected - use skip action (all duplicates will be skipped)
            pendingBatchData.duplicate_action = 'skip';
        } else {
            // Mixed selection - use selective action
            pendingBatchData.duplicate_action = 'selective';
            pendingBatchData.replace_staff_ids = selectedForReplacement;
        }

        // Hide modal and proceed
        $('#duplicateHandlingModal').modal('hide');
        submitBatchCreation(pendingBatchData);
    });

    // Reset modal when closed
    $('#duplicateHandlingModal').on('hidden.bs.modal', function() {
        duplicateStaffData = [];
        pendingBatchData = null;
        newStaffCountForBatch = 0;
        $('#selectAllDuplicates').prop('checked', false).prop('indeterminate', false);
    });

    function submitBatchCreation(postData) {
        $('#createBatchSubmitBtn').prop('disabled', true)
            .html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Creating Batch...');

        $.ajax({
            url: "{{ route('hr.payroll.store') }}",
            method: 'POST',
            data: postData,
            success: function(response) {
                $('#createBatchModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Payroll batch created successfully');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to create batch');
            },
            complete: function() {
                $('#createBatchSubmitBtn').prop('disabled', false)
                    .html('<i class="mdi mdi-check mr-1"></i> Create Payroll Batch');
            }
        });
    }

    // ========== ENHANCED VIEW BATCH ==========
    $(document).on('click', '.view-batch, .view-btn', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        if (!id) return;

        // Show loading
        $('#batchInfoTable').html('<tr><td colspan="2" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>');
        $('#workflowTimeline').html('<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>');
        $('#batchItemsTable tbody').html('<tr><td colspan="5" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>');
        $('#batchActionButtons').html('');
        $('#expenseInfoCard').hide();
        $('#viewBatchModal').modal('show');

        $.ajax({
            url: "{{ route('hr.payroll.show', ':id') }}".replace(':id', id),
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                renderBatchDetails(data);
                renderTimeline(data.timeline);
                renderBatchItems(data.items, data.id);
                renderBatchActions(data);
                renderExpenseInfo(data);
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Failed to load batch details';
                $('#batchInfoTable').html(`<tr><td colspan="2" class="text-danger">${msg}</td></tr>`);
                toastr.error(msg);
            }
        });
    });

    function renderBatchDetails(data) {
        $('#viewBatchNumber').text(data.batch_number || 'N/A');

        let infoHtml = `
            <tr><th style="width: 40%;">Pay Period</th><td>${data.pay_period_formatted || '-'}</td></tr>
            <tr><th>Work Period</th><td>${data.work_period_start || '-'} to ${data.work_period_end || '-'}</td></tr>
            <tr><th>Days Worked</th><td>${data.days_worked || '-'} / ${data.days_in_month || '-'} days</td></tr>
            <tr><th>Status</th><td>${data.status_badge || data.status}</td></tr>
            <tr><th>Staff Count</th><td>${data.items?.length || 0}</td></tr>
        `;
        if (data.description) {
            infoHtml += `<tr><th>Description</th><td>${data.description}</td></tr>`;
        }
        $('#batchInfoTable').html(infoHtml);

        // Financial summary
        $('#viewTotalGross').text(formatCurrency(data.total_gross_amount));
        $('#viewTotalDeductions').text(formatCurrency(data.total_deductions));
        $('#viewTotalNet').text(formatCurrency(data.total_net_amount));
        $('#itemsCount').text(data.items?.length || 0);
    }

    function renderTimeline(timeline) {
        if (!timeline || !timeline.length) {
            $('#workflowTimeline').html('<p class="text-muted text-center">No timeline data</p>');
            return;
        }

        let html = '';
        timeline.forEach(event => {
            let dotClass = 'pending';
            let icon = 'mdi-circle-outline';

            if (event.completed) {
                if (event.status === 'rejected') {
                    dotClass = 'rejected';
                    icon = 'mdi-close';
                } else {
                    dotClass = 'completed';
                    icon = 'mdi-check';
                }
            } else if (event.current) {
                dotClass = 'current';
                icon = 'mdi-dots-horizontal';
            }

            html += `
                <div class="timeline-item">
                    <div class="timeline-dot ${dotClass}">
                        <i class="mdi ${icon}"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">${event.title}</div>
            `;

            if (event.completed && event.user) {
                html += `<div class="timeline-meta">By ${event.user} on ${event.date} at ${event.time}</div>`;
            } else if (!event.completed) {
                html += `<div class="timeline-meta text-muted">Pending</div>`;
            }

            if (event.comments) {
                const commentClass = event.status === 'rejected' ? 'rejection' : '';
                html += `<div class="timeline-comment ${commentClass}"><i class="mdi mdi-comment-quote-outline mr-1"></i>${event.comments}</div>`;
            }

            html += `
                    </div>
                </div>
            `;
        });

        $('#workflowTimeline').html(html);
    }

    function renderBatchItems(items, batchId) {
        if (!items || !items.length) {
            $('#batchItemsTable tbody').html('<tr><td colspan="5" class="text-center text-muted">No items</td></tr>');
            return;
        }

        let html = '';
        items.forEach(item => {
            html += `
                <tr>
                    <td>
                        <strong>${item.staff_name || '-'}</strong>
                        <br><small class="text-muted">${item.employee_id || ''}</small>
                    </td>
                    <td class="text-right">${formatCurrency(item.basic_salary)}</td>
                    <td class="text-right">${formatCurrency(item.gross_salary)}</td>
                    <td class="text-right font-weight-bold">${formatCurrency(item.net_salary)}</td>
                    <td class="text-center">
                        <a href="{{ url('/hr/payroll') }}/${batchId}/payslip/${item.id}/print" target="_blank" class="btn btn-sm btn-outline-primary" title="View Payslip">
                            <i class="mdi mdi-file-document"></i>
                        </a>
                    </td>
                </tr>
            `;
        });
        $('#batchItemsTable tbody').html(html);
    }

    function renderExpenseInfo(data) {
        if (data.expense) {
            $('#expenseReference').text(data.expense.reference || data.expense_reference || 'N/A');
            let statusClass = 'badge-secondary';
            if (data.expense.status === 'approved') statusClass = 'badge-success';
            else if (data.expense.status === 'pending') statusClass = 'badge-warning';
            else if (data.expense.status === 'rejected') statusClass = 'badge-danger';
            $('#expenseStatus').attr('class', 'badge ' + statusClass).text(data.expense.status || 'N/A');
            $('#expenseInfoCard').show();
        } else {
            $('#expenseInfoCard').hide();
        }
    }

    function renderBatchActions(data) {
        const perms = data.permissions || {};
        let actions = '';
        const hasItems = data.items && data.items.length > 0;

        if (data.status === 'draft') {
            if (!hasItems) {
                if (perms.can_create) {
                    actions += '<button class="btn btn-info mr-2 generate-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-cog mr-1"></i> Generate Items</button>';
                }
            } else {
                if (perms.can_create) {
                    actions += '<button class="btn btn-outline-secondary mr-2 generate-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-refresh mr-1"></i> Regenerate</button>';
                }
                if (perms.can_submit) {
                    actions += '<button class="btn btn-primary mr-2 submit-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-send mr-1"></i> Submit</button>';
                }
            }
            // Delete button for draft batches
            if (perms.can_delete || perms.can_create) {
                actions += '<button class="btn btn-outline-danger delete-batch-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-delete mr-1"></i> Delete</button>';
            }
        } else if (data.status === 'submitted') {
            if (perms.can_approve) {
                actions += '<button class="btn btn-success mr-2 approve-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-check mr-1"></i> Approve</button>';
            }
            if (perms.can_reject) {
                actions += '<button class="btn btn-danger reject-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-close mr-1"></i> Reject</button>';
            }
            if (!perms.can_approve && !perms.can_reject) {
                actions += '<span class="badge badge-warning"><i class="mdi mdi-clock-outline mr-1"></i> Awaiting Approval</span>';
            }
        } else if (data.status === 'approved') {
            if (perms.can_mark_paid) {
                actions += '<button class="btn btn-success mr-2 mark-paid-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-cash-check mr-1"></i> Mark as Paid</button>';
            }
            actions += '<button class="btn btn-outline-success mr-2 export-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-download mr-1"></i> Export</button>';
            actions += '<a href="{{ url("/hr/payroll") }}/' + data.id + '/payslips" class="btn btn-outline-info" style="border-radius: 8px;"><i class="mdi mdi-file-document-multiple mr-1"></i> Payslips</a>';
        } else if (data.status === 'rejected') {
            if (perms.can_edit) {
                actions += '<button class="btn btn-warning revert-draft-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-undo mr-1"></i> Revert to Draft</button>';
            }
        } else if (data.status === 'paid') {
            actions += '<span class="badge badge-success mr-2"><i class="mdi mdi-check-circle mr-1"></i> Paid</span>';
            actions += '<button class="btn btn-outline-success mr-2 export-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-download mr-1"></i> Export</button>';
            actions += '<a href="{{ url("/hr/payroll") }}/' + data.id + '/payslips" class="btn btn-outline-info" style="border-radius: 8px;"><i class="mdi mdi-file-document-multiple mr-1"></i> Payslips</a>';
        }

        $('#batchActionButtons').html(actions);
    }

    // ========== ACTION HANDLERS ==========
    // Generate items
    $(document).on('click', '.generate-btn', function() {
        const id = $(this).data('id');
        const btn = $(this);

        if (!confirm('Generate payroll items for all selected staff?')) return;

        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Generating...');

        $.ajax({
            url: "{{ route('hr.payroll.generate', ':id') }}".replace(':id', id),
            method: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                toastr.success(response.message || 'Items generated');
                $('.view-batch[data-id="' + id + '"], .view-btn[data-id="' + id + '"]').first().trigger('click');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to generate items');
                btn.prop('disabled', false).html('<i class="mdi mdi-cog mr-1"></i> Generate Items');
            }
        });
    });

    // Submit for approval
    $(document).on('click', '.submit-btn', function() {
        const id = $(this).data('id');
        $('#actionBatchId').val(id);
        $('#actionType').val('submit');
        $('#actionModalHeader').removeClass('bg-success bg-danger').addClass('bg-primary');
        $('#actionModalTitle').html('<i class="mdi mdi-send mr-2"></i>Submit for Approval');
        $('#actionDescription').text('Submit this payroll batch for approval?');
        $('#actionSubmitBtn').removeClass('btn-success btn-danger').addClass('btn-primary').html('<i class="mdi mdi-send mr-1"></i> Submit');
        $('#actionComments').val('');
        $('#paymentSourceSection').hide();
        $('#viewBatchModal').modal('hide');
        $('#actionModal').modal('show');
    });

    // Approve
    $(document).on('click', '.approve-btn', function() {
        const id = $(this).data('id');
        $('#actionBatchId').val(id);
        $('#actionType').val('approve');
        $('#actionModalHeader').removeClass('bg-primary bg-danger').addClass('bg-success');
        $('#actionModalTitle').html('<i class="mdi mdi-check-circle mr-2"></i>Approve Payroll');
        $('#actionDescription').html('<strong class="text-warning">Warning:</strong> Approving will create an Expense entry for the total payroll amount.');
        $('#actionSubmitBtn').removeClass('btn-primary btn-danger').addClass('btn-success').html('<i class="mdi mdi-check mr-1"></i> Approve');
        $('#actionComments').val('');
        $('#paymentSourceSection').hide();
        $('#viewBatchModal').modal('hide');
        $('#actionModal').modal('show');
    });

    // Reject
    $(document).on('click', '.reject-btn', function() {
        const id = $(this).data('id');
        $('#actionBatchId').val(id);
        $('#actionType').val('reject');
        $('#actionModalHeader').removeClass('bg-primary bg-success').addClass('bg-danger');
        $('#actionModalTitle').html('<i class="mdi mdi-close-circle mr-2"></i>Reject Payroll');
        $('#actionDescription').text('Reject this payroll batch? Please provide a reason.');
        $('#actionSubmitBtn').removeClass('btn-primary btn-success').addClass('btn-danger').html('<i class="mdi mdi-close mr-1"></i> Reject');
        $('#actionComments').val('');
        $('#paymentSourceSection').hide();
        $('#viewBatchModal').modal('hide');
        $('#actionModal').modal('show');
    });

    // Mark as Paid
    $(document).on('click', '.mark-paid-btn', function() {
        const id = $(this).data('id');
        $('#actionBatchId').val(id);
        $('#actionType').val('mark-paid');
        $('#actionModalHeader').removeClass('bg-primary bg-danger').addClass('bg-success');
        $('#actionModalTitle').html('<i class="mdi mdi-cash-check mr-2"></i>Mark as Paid');
        $('#actionDescription').html('<p>Mark this payroll batch as paid?</p><div class="alert alert-info mb-0" style="font-size: 0.9rem;"><i class="mdi mdi-information-outline mr-1"></i><strong>Note:</strong> The linked expense will be set to <em>Pending</em> status. The Accountant will need to approve it in the Expenses module to complete the payment cycle.</div>');
        $('#actionSubmitBtn').removeClass('btn-primary btn-danger').addClass('btn-success').html('<i class="mdi mdi-cash-check mr-1"></i> Mark as Paid');
        $('#actionComments').val('');
        // Show payment source section for mark-paid action
        $('#paymentSourceSection').show();
        $('#actionPaymentMethod').val('bank_transfer').trigger('change');
        $('#actionBankId').val('');
        $('#viewBatchModal').modal('hide');
        $('#actionModal').modal('show');
    });

    // Toggle bank selection based on payment method
    $('#actionPaymentMethod').on('change', function() {
        var method = $(this).val();
        if (method === 'bank_transfer') {
            $('#actionBankSelectionGroup').slideDown();
            $('#actionBankId').prop('required', true);
        } else {
            $('#actionBankSelectionGroup').slideUp();
            $('#actionBankId').prop('required', false).val('');
        }
    });

    // Delete batch (from view modal)
    $(document).on('click', '.delete-batch-btn', function() {
        const id = $(this).data('id');
        const btn = $(this);

        Swal.fire({
            title: '<i class="mdi mdi-alert-circle text-danger"></i> Delete Batch?',
            html: '<p>Are you sure you want to delete this payroll batch?</p><p class="text-danger mb-0"><strong>This action cannot be undone.</strong></p>',
            icon: null,
            showCancelButton: true,
            confirmButtonText: '<i class="mdi mdi-delete"></i> Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
        }).then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Deleting...');

                $.ajax({
                    url: "{{ route('hr.payroll.destroy', ':id') }}".replace(':id', id),
                    method: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        $('#viewBatchModal').modal('hide');
                        table.ajax.reload();
                        loadStats();
                        toastr.success(response.message || 'Batch deleted successfully');
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Failed to delete batch');
                        btn.prop('disabled', false).html('<i class="mdi mdi-delete mr-1"></i> Delete');
                    }
                });
            }
        });
    });

    // Submit action form
    $('#actionForm').submit(function(e) {
        e.preventDefault();

        const id = $('#actionBatchId').val();
        const action = $('#actionType').val();

        if (!id || !action) {
            toastr.error('Missing parameters');
            return;
        }

        const originalBtnText = $('#actionSubmitBtn').html();
        $('#actionSubmitBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...');

        let actionUrl;
        if (action === 'submit') {
            actionUrl = "{{ route('hr.payroll.submit', ':id') }}".replace(':id', id);
        } else if (action === 'approve') {
            actionUrl = "{{ route('hr.payroll.approve', ':id') }}".replace(':id', id);
        } else if (action === 'reject') {
            actionUrl = "{{ route('hr.payroll.reject', ':id') }}".replace(':id', id);
        } else if (action === 'mark-paid') {
            actionUrl = "{{ route('hr.payroll.mark-paid', ':id') }}".replace(':id', id);
        } else {
            toastr.error('Unknown action');
            $('#actionSubmitBtn').prop('disabled', false).html(originalBtnText);
            return;
        }

        // Build request data
        let requestData = {
            _token: '{{ csrf_token() }}',
            comments: $('#actionComments').val()
        };

        // Add payment source data for mark-paid action
        if (action === 'mark-paid') {
            const paymentMethod = $('#paymentMethodSelect').val();
            if (!paymentMethod) {
                toastr.error('Please select a payment source');
                $('#actionSubmitBtn').prop('disabled', false).html(originalBtnText);
                return;
            }
            requestData.payment_method = paymentMethod;

            if (paymentMethod === 'bank_transfer') {
                const bankId = $('#bankIdSelect').val();
                if (!bankId) {
                    toastr.error('Please select a bank account');
                    $('#actionSubmitBtn').prop('disabled', false).html(originalBtnText);
                    return;
                }
                requestData.bank_id = bankId;
            }
        }

        $.ajax({
            url: actionUrl,
            method: 'POST',
            data: requestData,
            success: function(response) {
                $('#actionModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Action completed');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Action failed');
            },
            complete: function() {
                $('#actionSubmitBtn').prop('disabled', false).html(originalBtnText);
            }
        });
    });

    // Export
    $(document).on('click', '.export-btn', function() {
        window.location.href = "{{ url('/hr/payroll') }}/" + $(this).data('id') + "/export";
    });

    // Print payslips from view modal
    $('#printPayslipsBtn').click(function() {
        const batchNumber = $('#viewBatchNumber').text();
        // Get batch ID from any button in the modal
        const id = $('#batchActionButtons').find('[data-id]').first().data('id');
        if (id) {
            window.open("{{ url('/hr/payroll') }}/" + id + "/payslips", '_blank');
        }
    });

    // Revert to Draft
    $(document).on('click', '.revert-draft-btn', function() {
        const id = $(this).data('id');
        const btn = $(this);

        if (!confirm('Revert this batch to draft status?')) return;

        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Reverting...');

        $.ajax({
            url: "{{ route('hr.payroll.revert-draft', ':id') }}".replace(':id', id),
            method: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                $('#viewBatchModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Batch reverted to draft');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to revert batch');
                btn.prop('disabled', false).html('<i class="mdi mdi-undo mr-1"></i> Revert to Draft');
            }
        });
    });
});
</script>
@endsection
