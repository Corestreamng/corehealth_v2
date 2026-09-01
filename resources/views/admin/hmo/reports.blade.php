@extends('admin.layouts.app')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/hmo-reports.css') }}">
@endsection

@section('content')
<section class="content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center reports-header">
            <div>
                <h3 class="reports-title">
                    <i class="mdi mdi-file-chart mr-2"></i>HMO Reports & Claims
                </h3>
                <p class="reports-subtitle">Analytics, Claims Submission & Remittance Management</p>
            </div>
            <div class="d-flex align-items-center">
                <a href="{{ route('hmo.workbench') }}" class="btn btn-outline-primary mr-3" style="border-radius: 6px;">
                    <i class="mdi mdi-arrow-left mr-1"></i>Back to Workbench
                </a>
                <span class="reports-date">
                    <i class="mdi mdi-calendar mr-1"></i>{{ date('l, F j, Y') }}
                </span>
            </div>
        </div>

        <!-- Report Type Selection - Row 1 -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="report-card-modern active" data-report="claims">
                    <div class="card-body text-center py-4">
                        <div class="icon-wrapper text-white mx-auto mb-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="mdi mdi-file-document-outline"></i>
                        </div>
                        <h6 class="mb-1 font-weight-bold">Claims Report</h6>
                        <small class="text-muted">Submission-ready claims</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card-modern" data-report="outstanding">
                    <div class="card-body text-center py-4">
                        <div class="icon-wrapper text-white mx-auto mb-3" style="background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
                            <i class="mdi mdi-cash-multiple"></i>
                        </div>
                        <h6 class="mb-1 font-weight-bold">Outstanding Claims</h6>
                        <small class="text-muted">What HMOs owe</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card-modern" data-report="remittances">
                    <div class="card-body text-center py-4">
                        <div class="icon-wrapper text-white mx-auto mb-3" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                            <i class="mdi mdi-bank-transfer-in"></i>
                        </div>
                        <h6 class="mb-1 font-weight-bold">HMO Remittances</h6>
                        <small class="text-muted">Payments received</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card-modern" data-report="monthly">
                    <div class="card-body text-center py-4">
                        <div class="icon-wrapper text-white mx-auto mb-3" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="mdi mdi-chart-bar"></i>
                        </div>
                        <h6 class="mb-1 font-weight-bold">Monthly Summary</h6>
                        <small class="text-muted">Analytics & trends</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Type Selection - Row 2 -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="report-card-modern" data-report="patient">
                    <div class="card-body text-center py-4">
                        <div class="icon-wrapper text-white mx-auto mb-3" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="mdi mdi-account-card-details"></i>
                        </div>
                        <h6 class="mb-1 font-weight-bold">Patient History</h6>
                        <small class="text-muted">Per-patient claims</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card-modern" data-report="utilization">
                    <div class="card-body text-center py-4">
                        <div class="icon-wrapper text-white mx-auto mb-3" style="background: linear-gradient(135deg, #a8edea 0%, #20c997 100%);">
                            <i class="mdi mdi-chart-pie"></i>
                        </div>
                        <h6 class="mb-1 font-weight-bold">Service Utilization</h6>
                        <small class="text-muted">Top services & analytics</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card-modern" data-report="authcodes">
                    <div class="card-body text-center py-4">
                        <div class="icon-wrapper text-white mx-auto mb-3" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                            <i class="mdi mdi-key-variant"></i>
                        </div>
                        <h6 class="mb-1 font-weight-bold">Auth Code Tracker</h6>
                        <small class="text-muted">Authorization codes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <!-- Placeholder for future reports -->
            </div>
        </div>

        <!-- Report Content Area -->
        <div id="reportContent">
            <!-- Claims Report Section -->
            <div id="claimsReportSection" class="report-section">
                <div class="card-section-modern">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="mdi mdi-file-document-outline text-primary mr-2"></i>Claims Submission Report</h5>
                    </div>
                    <div class="card-body">
                        <!-- Filters Row 1 -->
                        <div class="filter-section-modern">
                            <div class="row mb-3">
                                <div class="col-md-2">
                                    <label>HMO Provider</label>
                                    <select class="form-control form-control-sm" id="filter_hmo" style="border-radius: 6px;">
                                        <option value="">All HMOs</option>
                                        @foreach($hmos as $hmo)
                                            <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>Status</label>
                                    <select class="form-control form-control-sm" id="filter_status" style="border-radius: 6px;">
                                        <option value="">All Status</option>
                                        <option value="approved">Approved</option>
                                        <option value="pending">Pending</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>From Date</label>
                                    <input type="date" class="form-control form-control-sm" id="filter_date_from" value="{{ date('Y-m-01') }}" style="border-radius: 6px;">
                                </div>
                                <div class="col-md-2">
                                    <label>To Date</label>
                                    <input type="date" class="form-control form-control-sm" id="filter_date_to" value="{{ date('Y-m-d') }}" style="border-radius: 6px;">
                                </div>
                                <div class="col-md-2">
                                    <label>Submission</label>
                                    <select class="form-control form-control-sm" id="filter_submission" style="border-radius: 6px;">
                                        <option value="">All</option>
                                        <option value="submitted">Submitted</option>
                                        <option value="not_submitted">Not Submitted</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>Payment</label>
                                    <select class="form-control form-control-sm" id="filter_payment" style="border-radius: 6px;">
                                        <option value="">All</option>
                                        <option value="paid">Paid</option>
                                        <option value="unpaid">Unpaid</option>
                                    </select>
                                </div>
                            </div>
                            <!-- Filters Row 2 -->
                            <div class="row">
                                <div class="col-md-2">
                                    <label>Service Category</label>
                                    <select class="form-control form-control-sm" id="filter_service_category" style="border-radius: 6px;">
                                        <option value="">All Categories</option>
                                        @foreach($serviceCategories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->category_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>Product Category</label>
                                    <select class="form-control form-control-sm" id="filter_product_category" style="border-radius: 6px;">
                                        <option value="">All Categories</option>
                                        @foreach($productCategories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->category_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>Type</label>
                                    <select class="form-control form-control-sm" id="filter_type" style="border-radius: 6px;">
                                        <option value="">All Types</option>
                                        <option value="service">Services Only</option>
                                        <option value="product">Products Only</option>
                                    </select>
                                </div>
                                <div class="col-md-6 text-right">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button class="btn btn-primary btn-sm" id="applyFilters" style="border-radius: 6px;">
                                            <i class="mdi mdi-filter mr-1"></i>Apply Filters
                                        </button>
                                        <button class="btn btn-secondary btn-sm" id="clearFilters" style="border-radius: 6px;">
                                            <i class="mdi mdi-close mr-1"></i>Clear
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Bar -->
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <div class="btn-group">
                                    <button class="btn btn-success btn-sm" id="printReportBtn" style="border-radius: 6px 0 0 6px;">
                                        <i class="mdi mdi-printer mr-1"></i>Print
                                    </button>
                                    <button class="btn btn-info btn-sm" id="exportExcelBtn">
                                        <i class="mdi mdi-microsoft-excel mr-1"></i>Excel
                                    </button>
                                    <button class="btn btn-danger btn-sm" id="exportPdfBtn">
                                        <i class="mdi mdi-file-pdf-box mr-1"></i>PDF
                                    </button>
                                    <button class="btn btn-warning btn-sm" id="markSubmittedBtn" disabled style="border-radius: 0 6px 6px 0;">
                                        <i class="mdi mdi-check mr-1"></i>Mark Submitted
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4 text-right">
                                <span class="text-muted" id="selectedClaimsInfo">0 claims selected</span>
                            </div>
                        </div>

                        <!-- Claims Table -->
                        <div class="table-responsive">
                            <table id="claimsTable" class="table table-sm table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="30"><input type="checkbox" id="selectAllClaims"></th>
                                        <th>S/N</th>
                                        <th>Patient</th>
                                        <th>File No</th>
                                        <th>HMO No</th>
                                        <th>HMO</th>
                                        <th>Date</th>
                                        <th>Item</th>
                                        <th>Auth Code</th>
                                        <th>Qty</th>
                                        <th>Amount (₦)</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>Paid</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outstanding Claims Section -->
            <div id="outstandingReportSection" class="report-section" style="display:none;">
                <div class="card-section-modern">
                    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px 12px 0 0;">
                        <h5 class="card-title mb-0"><i class="mdi mdi-cash-multiple mr-2"></i>Outstanding Claims by HMO</h5>
                    </div>
                    <div class="card-body">
                        <!-- Summary Cards -->
                        <div class="row mb-4" id="outstandingSummary">
                            <div class="col-md-3">
                                <div class="summary-card-modern" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <div class="summary-icon">
                                        <i class="mdi mdi-file-document-multiple-outline"></i>
                                    </div>
                                    <h6 class="text-white-50 mb-1">Total Claims</h6>
                                    <h4 class="mb-0 text-white font-weight-bold" id="summaryTotalClaims">₦0.00</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="summary-card-modern" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                    <div class="summary-icon">
                                        <i class="mdi mdi-check-circle-outline"></i>
                                    </div>
                                    <h6 class="text-white-50 mb-1">Total Paid</h6>
                                    <h4 class="mb-0 text-white font-weight-bold" id="summaryTotalPaid">₦0.00</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="summary-card-modern" style="background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
                                    <div class="summary-icon">
                                        <i class="mdi mdi-alert-circle-outline"></i>
                                    </div>
                                    <h6 class="text-white-50 mb-1">Outstanding</h6>
                                    <h4 class="mb-0 text-white font-weight-bold" id="summaryOutstanding">₦0.00</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="summary-card-modern" style="background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);">
                                    <div class="summary-icon">
                                        <i class="mdi mdi-clock-alert-outline"></i>
                                    </div>
                                    <h6 class="text-white-50 mb-1">Over 90 Days</h6>
                                    <h4 class="mb-0 text-white font-weight-bold" id="summaryOverdue">₦0.00</h4>
                                </div>
                            </div>
                        </div>

                        <!-- Aging Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered" id="outstandingTable">
                                <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                    <tr>
                                        <th>HMO Provider</th>
                                        <th>Total Claims</th>
                                        <th>Paid</th>
                                        <th>Outstanding</th>
                                        <th>0-30 Days</th>
                                        <th>31-60 Days</th>
                                        <th>61-90 Days</th>
                                        <th>90+ Days</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="outstandingTableBody">
                                    <tr><td colspan="9" class="text-center">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-right mt-3">
                            <button class="btn btn-success" id="printOutstandingBtn" style="border-radius: 6px;">
                                <i class="mdi mdi-printer mr-1"></i> Print Outstanding Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Remittances Section -->
            <div id="remittancesSection" class="report-section" style="display:none;">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card-section-modern">
                            <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; border-radius: 12px 12px 0 0;">
                                <h5 class="card-title mb-0"><i class="mdi mdi-bank-transfer-in mr-2"></i>HMO Remittances</h5>
                                <button class="btn btn-light btn-sm" id="addRemittanceBtn" style="border-radius: 6px;">
                                    <i class="mdi mdi-plus mr-1"></i>Record Remittance
                                </button>
                            </div>
                            <div class="card-body">
                                <!-- Filters -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <select class="form-control form-control-sm" id="remittance_filter_hmo" style="border-radius: 6px;">
                                            <option value="">All HMOs</option>
                                            @foreach($hmos as $hmo)
                                                <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="date" class="form-control form-control-sm" id="remittance_filter_from" placeholder="From" value="{{ date('Y-m-01') }}" style="border-radius: 6px;">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="date" class="form-control form-control-sm" id="remittance_filter_to" placeholder="To" value="{{ date('Y-m-d') }}" style="border-radius: 6px;">
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-primary btn-sm btn-block" id="filterRemittances" style="border-radius: 6px;">Filter</button>
                                    </div>
                                </div>

                                <!-- Remittances Table -->
                                <div class="table-responsive">
                                    <table id="remittancesTable" class="table table-sm table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>HMO</th>
                                                <th>Amount</th>
                                                <th>Payment Date</th>
                                                <th>Reference</th>
                                                <th>Period</th>
                                                <th>Recorded By</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-section-modern">
                            <div class="card-header text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 12px 12px 0 0;">
                                <h5 class="card-title mb-0"><i class="mdi mdi-calculator mr-2"></i>Quick Summary</h5>
                            </div>
                            <div class="card-body">
                                <div id="remittanceSummary">
                                    <div class="text-center py-4">
                                        <i class="mdi mdi-loading mdi-spin mdi-36px"></i>
                                        <p>Loading summary...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Summary Section -->
            <div id="monthlySummarySection" class="report-section" style="display:none;">
                <div class="card-section-modern">
                    <div class="card-header" style="background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); color: white; border-radius: 12px 12px 0 0;">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="card-title mb-0"><i class="mdi mdi-chart-bar mr-2"></i>Monthly Summary</h5>
                            </div>
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-md-5">
                                        <select class="form-control form-control-sm" id="monthlyMonth" style="border-radius: 6px;">
                                            @for($m = 1; $m <= 12; $m++)
                                                <option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>
                                                    {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-control form-control-sm" id="monthlyYear" style="border-radius: 6px;">
                                            @for($y = date('Y'); $y>= date('Y') - 5; $y--)
                                                <option value="{{ $y }}">{{ $y }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-light btn-sm btn-block" id="loadMonthlySummary" style="border-radius: 6px;">Load</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="monthlySummaryContent">
                            <div class="text-center py-5">
                                <i class="mdi mdi-chart-bar mdi-48px text-muted"></i>
                                <p class="text-muted">Select month and year, then click Load</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Patient History Section -->
            <div id="patientHistorySection" class="report-section" style="display:none;">
                <div class="card-section-modern">
                    <div class="card-header" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; border-radius: 12px 12px 0 0;">
                        <h5 class="card-title mb-0"><i class="mdi mdi-account-card-details mr-2"></i>Patient Claims History</h5>
                    </div>
                    <div class="card-body">
                        <!-- Patient Search -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="font-weight-bold">Search Patient</label>
                                <select class="form-control" id="patientSearchSelect" style="width: 100%; border-radius: 6px;">
                                    <option value="">Type patient name, file no, or HMO no...</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div id="selectedPatientInfo" class="alert alert-info" style="display:none; border-radius: 6px;">
                                    <strong>Selected:</strong> <span id="patientInfoText"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Patient Claims Display -->
                        <div id="patientClaimsContent">
                            <div class="text-center py-5">
                                <i class="mdi mdi-account-search mdi-48px text-muted"></i>
                                <p class="text-muted">Search for a patient to view their claims history</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Utilization Section -->
            <div id="utilizationSection" class="report-section" style="display:none;">
                <div class="card-section-modern">
                    <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border-radius: 12px 12px 0 0;">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="card-title mb-0"><i class="mdi mdi-chart-pie mr-2"></i>Service Utilization Report</h5>
                            </div>
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-md-4">
                                        <input type="date" class="form-control form-control-sm" id="util_date_from" value="{{ date('Y-m-01') }}" style="border-radius: 6px;">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="date" class="form-control form-control-sm" id="util_date_to" value="{{ date('Y-m-d') }}" style="border-radius: 6px;">
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-light btn-sm btn-block" id="loadUtilization" style="border-radius: 6px;">
                                            <i class="mdi mdi-chart-bar mr-1"></i>Load
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="utilizationContent">
                            <div class="text-center py-5">
                                <i class="mdi mdi-chart-pie mdi-48px text-muted"></i>
                                <p class="text-muted">Select date range and click Load to view utilization data</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Auth Code Tracker Section -->
            <div id="authCodesSection" class="report-section" style="display:none;">
                <div class="card-section-modern">
                    <div class="card-header" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; border-radius: 12px 12px 0 0;">
                        <h5 class="card-title mb-0"><i class="mdi mdi-key-variant mr-2"></i>Authorization Code Tracker</h5>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="filter-section mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <label>HMO Provider</label>
                                    <select class="form-control form-control-sm" id="auth_filter_hmo">
                                        <option value="">All HMOs</option>
                                        @foreach($hmos as $hmo)
                                            <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>Auth Status</label>
                                    <select class="form-control form-control-sm" id="auth_filter_status">
                                        <option value="">All</option>
                                        <option value="with_code">With Code</option>
                                        <option value="without_code">Without Code</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>From Date</label>
                                    <input type="date" class="form-control form-control-sm" id="auth_filter_from" value="{{ date('Y-m-01') }}">
                                </div>
                                <div class="col-md-2">
                                    <label>To Date</label>
                                    <input type="date" class="form-control form-control-sm" id="auth_filter_to" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="col-md-3">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button class="btn btn-primary btn-sm" id="applyAuthFilters">
                                            <i class="fa fa-filter"></i> Filter
                                        </button>
                                        <button class="btn btn-success btn-sm" id="printAuthReport">
                                            <i class="fa fa-print"></i> Print
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Auth Codes Stats -->
                        <div class="row mb-3" id="authCodeStats">
                            <div class="col-md-3">
                                <div class="card-modern bg-success text-white">
                                    <div class="card-body py-2 text-center">
                                        <h5 class="mb-0" id="authWithCode">0</h5>
                                        <small>With Auth Code</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card-modern bg-warning text-dark">
                                    <div class="card-body py-2 text-center">
                                        <h5 class="mb-0" id="authWithoutCode">0</h5>
                                        <small>Without Auth Code</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card-modern bg-info text-white">
                                    <div class="card-body py-2 text-center">
                                        <h5 class="mb-0" id="authTotalClaims">₦0</h5>
                                        <small>Claims with Code</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card-modern bg-secondary text-white">
                                    <div class="card-body py-2 text-center">
                                        <h5 class="mb-0" id="authPendingClaims">₦0</h5>
                                        <small>Claims without Code</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Auth Codes Table -->
                        <div class="table-responsive">
                            <table id="authCodesTable" class="table table-sm table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>S/N</th>
                                        <th>Patient</th>
                                        <th>HMO No</th>
                                        <th>HMO</th>
                                        <th>Date</th>
                                        <th>Item</th>
                                        <th>Auth Code</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Add/Edit Remittance Modal -->
<div class="modal fade" id="remittanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <h5 class="modal-title"><i class="mdi mdi-cash-plus mr-2"></i><span id="remittanceModalTitle">Record HMO Remittance</span></h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="remittanceForm">
                <div class="modal-body">
                    <input type="hidden" id="remittance_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">HMO Provider <span class="text-danger">*</span></label>
                                <select class="form-control" id="remittance_hmo_id" name="hmo_id" required style="border-radius: 6px;">
                                    <option value="">Select HMO</option>
                                    @foreach($hmos as $hmo)
                                        <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Amount (₦) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="remittance_amount" name="amount" step="0.01" min="0" required style="border-radius: 6px;">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-bold">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="remittance_payment_date" name="payment_date" value="{{ date('Y-m-d') }}" required style="border-radius: 6px;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-bold">Reference Number</label>
                                <input type="text" class="form-control" id="remittance_reference" name="reference_number" style="border-radius: 6px;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="font-weight-bold">Payment Method</label>
                                <select class="form-control" id="remittance_method" name="payment_method" style="border-radius: 6px;">
                                    <option value="">Select Method</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Receiving Bank Account <span class="text-danger">*</span></label>
                                <select class="form-control" id="remittance_bank_id" name="bank_id" required style="border-radius: 6px;">
                                    <option value="">Select Bank Account</option>
                                    @foreach($banks ?? [] as $bank)
                                        <option value="{{ $bank->id }}">{{ $bank->name }} - {{ $bank->account_number }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Bank account where payment was received</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">HMO's Bank Name</label>
                                <input type="text" class="form-control" id="remittance_bank" name="bank_name" placeholder="Bank where HMO paid from" style="border-radius: 6px;">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Period From</label>
                                <input type="date" class="form-control" id="remittance_period_from" name="period_from" value="{{ date('Y-m-01') }}" style="border-radius: 6px;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Period To</label>
                                <input type="date" class="form-control" id="remittance_period_to" name="period_to" value="{{ date('Y-m-d') }}" style="border-radius: 6px;">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Notes</label>
                        <textarea class="form-control" id="remittance_notes" name="notes" rows="2" style="border-radius: 6px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 6px;">Cancel</button>
                    <button type="submit" class="btn btn-success" style="border-radius: 6px;">
                        <i class="mdi mdi-content-save mr-1"></i>Save Remittance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Remittance Details Modal -->
<div class="modal fade" id="viewRemittanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header text-white" style="border-radius: 12px 12px 0 0; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h5 class="modal-title"><i class="mdi mdi-eye mr-2"></i>Remittance Details</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewRemittanceContent">
                <div class="text-center py-4">
                    <i class="mdi mdi-loading mdi-spin mdi-36px"></i>
                </div>
            </div>
            <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 6px;">Close</button>
                <button type="button" class="btn btn-primary" id="printRemittanceBtn" style="border-radius: 6px;">
                    <i class="mdi mdi-printer mr-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Print Preview Modal -->
<div class="modal fade" id="printPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title"><i class="mdi mdi-printer mr-2"></i>Print Preview</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="printPreviewContent" class="print-preview">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer" style="border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 6px;">Close</button>
                <button type="button" class="btn btn-primary" id="doPrintBtn" style="border-radius: 6px;">
                    <i class="mdi mdi-printer mr-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<link href="{{ asset('assets/css/select2.min.css') }}" rel="stylesheet" />
<script src="{{ asset('assets/js/select2.min.js') }}"></script>
<script src="{{ asset('js/hmo-reports.js') }}"></script>
@endsection
