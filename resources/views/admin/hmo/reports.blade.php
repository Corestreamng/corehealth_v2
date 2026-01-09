@extends('admin.layouts.app')
@section('styles')
@php
    $settings = appsettings();
    $hosColor = $settings->hos_color ?? '#0066cc';
@endphp
<style>
    :root {
        --hospital-primary: {{ $hosColor }};
    }

    /* Report Cards */
    .report-card {
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }
    .report-card:hover {
        border-color: var(--hospital-primary);
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .report-card.active {
        border-color: var(--hospital-primary);
        background-color: rgba(0, 102, 204, 0.05);
    }
    .report-card .icon-wrapper {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    /* Tab styling */
    .nav-pills .nav-link.active {
        background-color: var(--hospital-primary);
    }

    /* Outstanding aging table */
    .aging-cell-current { background-color: #d4edda !important; }
    .aging-cell-warning { background-color: #fff3cd !important; }
    .aging-cell-danger { background-color: #f8d7da !important; }
    .aging-cell-critical { background-color: #dc3545 !important; color: white; }

    /* Summary cards */
    .summary-card {
        border-left: 4px solid var(--hospital-primary);
    }
    .summary-card.success { border-left-color: #28a745; }
    .summary-card.warning { border-left-color: #ffc107; }
    .summary-card.danger { border-left-color: #dc3545; }
    .summary-card.info { border-left-color: #17a2b8; }

    /* Print preview */
    .print-preview {
        background: white;
        border: 1px solid #ddd;
        padding: 20px;
        max-height: 600px;
        overflow-y: auto;
    }

    /* Filters */
    .filter-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }

    /* DataTable customization */
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px 10px;
    }

    /* Remittance form */
    .remittance-summary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px;
        padding: 20px;
    }
</style>
@endsection

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><i class="mdi mdi-file-chart"></i> HMO Reports & Claims</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('hmo.workbench') }}">HMO Workbench</a></li>
                    <li class="breadcrumb-item active">Reports</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <!-- Report Type Selection - Row 1 -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card report-card active" data-report="claims">
                    <div class="card-body text-center py-3">
                        <div class="icon-wrapper bg-primary text-white mx-auto mb-2">
                            <i class="mdi mdi-file-document-outline"></i>
                        </div>
                        <h6 class="mb-0">Claims Report</h6>
                        <small class="text-muted">Submission-ready claims</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card report-card" data-report="outstanding">
                    <div class="card-body text-center py-3">
                        <div class="icon-wrapper bg-danger text-white mx-auto mb-2">
                            <i class="mdi mdi-cash-multiple"></i>
                        </div>
                        <h6 class="mb-0">Outstanding Claims</h6>
                        <small class="text-muted">What HMOs owe</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card report-card" data-report="remittances">
                    <div class="card-body text-center py-3">
                        <div class="icon-wrapper bg-success text-white mx-auto mb-2">
                            <i class="mdi mdi-bank-transfer-in"></i>
                        </div>
                        <h6 class="mb-0">HMO Remittances</h6>
                        <small class="text-muted">Payments received</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card report-card" data-report="monthly">
                    <div class="card-body text-center py-3">
                        <div class="icon-wrapper bg-info text-white mx-auto mb-2">
                            <i class="mdi mdi-chart-bar"></i>
                        </div>
                        <h6 class="mb-0">Monthly Summary</h6>
                        <small class="text-muted">Analytics & trends</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Type Selection - Row 2 -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card report-card" data-report="patient">
                    <div class="card-body text-center py-3">
                        <div class="icon-wrapper bg-purple text-white mx-auto mb-2" style="background: #6f42c1;">
                            <i class="mdi mdi-account-card-details"></i>
                        </div>
                        <h6 class="mb-0">Patient History</h6>
                        <small class="text-muted">Per-patient claims</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card report-card" data-report="utilization">
                    <div class="card-body text-center py-3">
                        <div class="icon-wrapper bg-teal text-white mx-auto mb-2" style="background: #20c997;">
                            <i class="mdi mdi-chart-pie"></i>
                        </div>
                        <h6 class="mb-0">Service Utilization</h6>
                        <small class="text-muted">Top services & analytics</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card report-card" data-report="authcodes">
                    <div class="card-body text-center py-3">
                        <div class="icon-wrapper bg-orange text-white mx-auto mb-2" style="background: #fd7e14;">
                            <i class="mdi mdi-key-variant"></i>
                        </div>
                        <h6 class="mb-0">Auth Code Tracker</h6>
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
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="mdi mdi-file-document-outline"></i> Claims Submission Report</h5>
                    </div>
                    <div class="card-body">
                        <!-- Filters Row 1 -->
                        <div class="filter-section">
                            <div class="row mb-2">
                                <div class="col-md-2">
                                    <label>HMO Provider</label>
                                    <select class="form-control form-control-sm" id="filter_hmo">
                                        <option value="">All HMOs</option>
                                        @foreach($hmos as $hmo)
                                            <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>Status</label>
                                    <select class="form-control form-control-sm" id="filter_status">
                                        <option value="">All Status</option>
                                        <option value="approved">Approved</option>
                                        <option value="pending">Pending</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>From Date</label>
                                    <input type="date" class="form-control form-control-sm" id="filter_date_from" value="{{ date('Y-m-01') }}">
                                </div>
                                <div class="col-md-2">
                                    <label>To Date</label>
                                    <input type="date" class="form-control form-control-sm" id="filter_date_to" value="{{ date('Y-m-d') }}">
                                </div>
                                <div class="col-md-2">
                                    <label>Submission</label>
                                    <select class="form-control form-control-sm" id="filter_submission">
                                        <option value="">All</option>
                                        <option value="submitted">Submitted</option>
                                        <option value="not_submitted">Not Submitted</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>Payment</label>
                                    <select class="form-control form-control-sm" id="filter_payment">
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
                                    <select class="form-control form-control-sm" id="filter_service_category">
                                        <option value="">All Categories</option>
                                        @foreach($serviceCategories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->category_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>Product Category</label>
                                    <select class="form-control form-control-sm" id="filter_product_category">
                                        <option value="">All Categories</option>
                                        @foreach($productCategories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->category_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>Type</label>
                                    <select class="form-control form-control-sm" id="filter_type">
                                        <option value="">All Types</option>
                                        <option value="service">Services Only</option>
                                        <option value="product">Products Only</option>
                                    </select>
                                </div>
                                <div class="col-md-6 text-right">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button class="btn btn-primary btn-sm" id="applyFilters">
                                            <i class="fa fa-filter"></i> Apply Filters
                                        </button>
                                        <button class="btn btn-secondary btn-sm" id="clearFilters">
                                            <i class="fa fa-times"></i> Clear
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Bar -->
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <div class="btn-group">
                                    <button class="btn btn-success btn-sm" id="printReportBtn">
                                        <i class="fa fa-print"></i> Print
                                    </button>
                                    <button class="btn btn-info btn-sm" id="exportExcelBtn">
                                        <i class="fa fa-file-excel"></i> Excel
                                    </button>
                                    <button class="btn btn-danger btn-sm" id="exportPdfBtn">
                                        <i class="fa fa-file-pdf"></i> PDF
                                    </button>
                                    <button class="btn btn-warning btn-sm" id="markSubmittedBtn" disabled>
                                        <i class="fa fa-check"></i> Mark Submitted
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
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="mdi mdi-cash-multiple"></i> Outstanding Claims by HMO</h5>
                    </div>
                    <div class="card-body">
                        <!-- Summary Cards -->
                        <div class="row mb-4" id="outstandingSummary">
                            <div class="col-md-3">
                                <div class="card summary-card info">
                                    <div class="card-body py-3">
                                        <h6 class="text-muted mb-1">Total Claims</h6>
                                        <h4 class="mb-0" id="summaryTotalClaims">₦0.00</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card summary-card success">
                                    <div class="card-body py-3">
                                        <h6 class="text-muted mb-1">Total Paid</h6>
                                        <h4 class="mb-0 text-success" id="summaryTotalPaid">₦0.00</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card summary-card danger">
                                    <div class="card-body py-3">
                                        <h6 class="text-muted mb-1">Outstanding</h6>
                                        <h4 class="mb-0 text-danger" id="summaryOutstanding">₦0.00</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card summary-card warning">
                                    <div class="card-body py-3">
                                        <h6 class="text-muted mb-1">Over 90 Days</h6>
                                        <h4 class="mb-0 text-warning" id="summaryOverdue">₦0.00</h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Aging Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered" id="outstandingTable">
                                <thead class="thead-dark">
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
                            <button class="btn btn-success" id="printOutstandingBtn">
                                <i class="fa fa-print"></i> Print Outstanding Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Remittances Section -->
            <div id="remittancesSection" class="report-section" style="display:none;">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0"><i class="mdi mdi-bank-transfer-in"></i> HMO Remittances</h5>
                                <button class="btn btn-primary btn-sm" id="addRemittanceBtn">
                                    <i class="fa fa-plus"></i> Record Remittance
                                </button>
                            </div>
                            <div class="card-body">
                                <!-- Filters -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <select class="form-control form-control-sm" id="remittance_filter_hmo">
                                            <option value="">All HMOs</option>
                                            @foreach($hmos as $hmo)
                                                <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="date" class="form-control form-control-sm" id="remittance_filter_from" placeholder="From" value="{{ date('Y-m-01') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="date" class="form-control form-control-sm" id="remittance_filter_to" placeholder="To" value="{{ date('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-primary btn-sm btn-block" id="filterRemittances">Filter</button>
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
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="fa fa-calculator"></i> Quick Summary</h5>
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
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="card-title mb-0"><i class="mdi mdi-chart-bar"></i> Monthly Summary</h5>
                            </div>
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-md-5">
                                        <select class="form-control form-control-sm" id="monthlyMonth">
                                            @for($m = 1; $m <= 12; $m++)
                                                <option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>
                                                    {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-control form-control-sm" id="monthlyYear">
                                            @for($y = date('Y'); $y >= date('Y') - 5; $y--)
                                                <option value="{{ $y }}">{{ $y }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-primary btn-sm btn-block" id="loadMonthlySummary">Load</button>
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
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="mdi mdi-account-card-details"></i> Patient Claims History</h5>
                    </div>
                    <div class="card-body">
                        <!-- Patient Search -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label>Search Patient</label>
                                <select class="form-control" id="patientSearchSelect" style="width: 100%;">
                                    <option value="">Type patient name, file no, or HMO no...</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div id="selectedPatientInfo" class="alert alert-info" style="display:none;">
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
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="card-title mb-0"><i class="mdi mdi-chart-pie"></i> Service Utilization Report</h5>
                            </div>
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-md-4">
                                        <input type="date" class="form-control form-control-sm" id="util_date_from" value="{{ date('Y-m-01') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="date" class="form-control form-control-sm" id="util_date_to" value="{{ date('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-primary btn-sm btn-block" id="loadUtilization">
                                            <i class="fa fa-chart-bar"></i> Load
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
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="mdi mdi-key-variant"></i> Authorization Code Tracker</h5>
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
                                <div class="card bg-success text-white">
                                    <div class="card-body py-2 text-center">
                                        <h5 class="mb-0" id="authWithCode">0</h5>
                                        <small>With Auth Code</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-dark">
                                    <div class="card-body py-2 text-center">
                                        <h5 class="mb-0" id="authWithoutCode">0</h5>
                                        <small>Without Auth Code</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body py-2 text-center">
                                        <h5 class="mb-0" id="authTotalClaims">₦0</h5>
                                        <small>Claims with Code</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-secondary text-white">
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
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa fa-money-bill"></i> <span id="remittanceModalTitle">Record HMO Remittance</span></h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="remittanceForm">
                <div class="modal-body">
                    <input type="hidden" id="remittance_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>HMO Provider <span class="text-danger">*</span></label>
                                <select class="form-control" id="remittance_hmo_id" name="hmo_id" required>
                                    <option value="">Select HMO</option>
                                    @foreach($hmos as $hmo)
                                        <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Amount (₦) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="remittance_amount" name="amount" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Payment Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="remittance_payment_date" name="payment_date" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Reference Number</label>
                                <input type="text" class="form-control" id="remittance_reference" name="reference_number">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Payment Method</label>
                                <select class="form-control" id="remittance_method" name="payment_method">
                                    <option value="">Select Method</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Bank Name</label>
                                <input type="text" class="form-control" id="remittance_bank" name="bank_name">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Period From</label>
                                <input type="date" class="form-control" id="remittance_period_from" name="period_from" value="{{ date('Y-m-01') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Period To</label>
                                <input type="date" class="form-control" id="remittance_period_to" name="period_to" value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea class="form-control" id="remittance_notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Save Remittance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Remittance Details Modal -->
<div class="modal fade" id="viewRemittanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fa fa-eye"></i> Remittance Details</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="viewRemittanceContent">
                <div class="text-center py-4">
                    <i class="mdi mdi-loading mdi-spin mdi-36px"></i>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printRemittanceBtn">
                    <i class="fa fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Print Preview Modal -->
<div class="modal fade" id="printPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-print"></i> Print Preview</h5>
                <button type="button" class="close" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="printPreviewContent" class="print-preview">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="doPrintBtn">
                    <i class="fa fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function() {
    let claimsTable;
    let remittancesTable;
    let authCodesTable;
    let selectedClaimIds = [];
    let currentRemittanceId = null;
    let selectedPatientId = null;

    // App settings for branding
    const appSettings = {
        siteName: "{{ appsettings()->site_name ?? config('app.name') }}",
        logo: "{{ appsettings()->logo ?? '' }}",
        address: "{{ appsettings()->contact_address ?? '' }}",
        phones: "{{ appsettings()->contact_phones ?? '' }}",
        emails: "{{ appsettings()->contact_emails ?? '' }}",
        hosColor: "{{ appsettings()->hos_color ?? '#0066cc' }}"
    };

    // Initialize Claims DataTable
    function initClaimsTable() {
        if (claimsTable) {
            claimsTable.destroy();
        }

        claimsTable = $('#claimsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('hmo.reports.claims') }}",
                data: function(d) {
                    d.hmo_id = $('#filter_hmo').val();
                    d.status = $('#filter_status').val();
                    d.date_from = $('#filter_date_from').val();
                    d.date_to = $('#filter_date_to').val();
                    d.submission_status = $('#filter_submission').val();
                    d.payment_status = $('#filter_payment').val();
                    d.service_category_id = $('#filter_service_category').val();
                    d.product_category_id = $('#filter_product_category').val();
                    d.service_type = $('#filter_type').val();
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    render: function(data) {
                        return '<input type="checkbox" class="claim-checkbox" data-id="' + data.id + '">';
                    }
                },
                { data: 'DT_RowIndex', orderable: false },
                { data: 'patient_name' },
                { data: 'file_no' },
                { data: 'hmo_no' },
                { data: 'hmo_name' },
                { data: 'service_date' },
                { data: 'item_name' },
                { data: 'auth_code_display' },
                { data: 'qty_display' },
                { data: 'claim_amount' },
                { data: 'status_badge' },
                { data: 'submission_badge' },
                { data: 'payment_badge' }
            ],
            order: [[6, 'desc']],
            dom: 'Bfrtip',
            buttons: ['pageLength', 'copy', 'excel', 'pdf'],
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]]
        });
    }

    // Initialize Remittances DataTable
    function initRemittancesTable() {
        if (remittancesTable) {
            remittancesTable.destroy();
        }

        remittancesTable = $('#remittancesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('hmo.reports.remittances') }}",
                data: function(d) {
                    d.hmo_id = $('#remittance_filter_hmo').val();
                    d.date_from = $('#remittance_filter_from').val();
                    d.date_to = $('#remittance_filter_to').val();
                }
            },
            columns: [
                { data: 'DT_RowIndex', orderable: false },
                { data: 'hmo_name' },
                { data: 'amount_formatted' },
                { data: 'payment_date_formatted' },
                { data: 'reference_number', defaultContent: '-' },
                { data: 'period' },
                { data: 'created_by_name' },
                { data: 'actions', orderable: false }
            ],
            order: [[3, 'desc']]
        });
    }

    // Load Outstanding Report
    function loadOutstandingReport() {
        $.get("{{ route('hmo.reports.outstanding') }}", function(response) {
            // Update summary
            $('#summaryTotalClaims').text('₦' + formatNumber(response.summary.total_claims));
            $('#summaryTotalPaid').text('₦' + formatNumber(response.summary.total_paid));
            $('#summaryOutstanding').text('₦' + formatNumber(response.summary.total_outstanding));

            // Calculate overdue (90+ days)
            let overdue = response.data.reduce((sum, item) => sum + parseFloat(item.aging_over_90 || 0), 0);
            $('#summaryOverdue').text('₦' + formatNumber(overdue));

            // Build table
            let html = '';
            response.data.forEach(function(item) {
                html += `<tr>
                    <td><strong>${item.hmo_name}</strong></td>
                    <td>₦${formatNumber(item.total_claims)}</td>
                    <td class="text-success">₦${formatNumber(item.paid)}</td>
                    <td class="text-danger font-weight-bold">₦${formatNumber(item.outstanding)}</td>
                    <td class="aging-cell-current">₦${formatNumber(item.aging_current)}</td>
                    <td class="aging-cell-warning">₦${formatNumber(item.aging_31_60)}</td>
                    <td class="aging-cell-danger">₦${formatNumber(item.aging_61_90)}</td>
                    <td class="${item.aging_over_90 > 0 ? 'aging-cell-critical' : ''}">₦${formatNumber(item.aging_over_90)}</td>
                    <td>
                        <button class="btn btn-sm btn-info view-hmo-claims" data-hmo-id="${item.hmo_id}">
                            <i class="fa fa-eye"></i>
                        </button>
                    </td>
                </tr>`;
            });

            if (html === '') {
                html = '<tr><td colspan="9" class="text-center">No outstanding claims</td></tr>';
            }

            $('#outstandingTableBody').html(html);
        });
    }

    // Load Monthly Summary
    function loadMonthlySummary() {
        let month = $('#monthlyMonth').val();
        let year = $('#monthlyYear').val();

        $.get("{{ route('hmo.reports.monthly') }}", { month: month, year: year }, function(response) {
            let html = `
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h6>Total Claims</h6>
                                <h3>₦${response.summary.total_claims}</h3>
                                <small>${response.summary.claims_count} claims</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h6>Approved</h6>
                                <h3>₦${response.summary.approved_total}</h3>
                                <small>${response.summary.approved_count} claims</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h6>Rejected</h6>
                                <h3>₦${response.summary.rejected_total}</h3>
                                <small>${response.summary.rejected_count} claims</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h6>Remittances</h6>
                                <h3>₦${response.summary.total_remittances}</h3>
                                <small>Received</small>
                            </div>
                        </div>
                    </div>
                </div>
                <h6 class="mb-3">Claims by HMO</h6>
                <table class="table table-bordered table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th>HMO</th>
                            <th>Total</th>
                            <th>Approved</th>
                            <th>Rejected</th>
                            <th>Pending</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>`;

            response.by_hmo.forEach(function(hmo) {
                html += `<tr>
                    <td>${hmo.hmo_name}</td>
                    <td>₦${formatNumber(hmo.total_claims)}</td>
                    <td class="text-success">₦${formatNumber(hmo.approved)}</td>
                    <td class="text-danger">₦${formatNumber(hmo.rejected)}</td>
                    <td class="text-warning">₦${formatNumber(hmo.pending)}</td>
                    <td>${hmo.count}</td>
                </tr>`;
            });

            html += `</tbody></table>
                <div class="row mt-4">
                    <div class="col-md-6">
                        <h6>By Service Type</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Products</span>
                                <strong>₦${formatNumber(response.by_type.products)}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Services</span>
                                <strong>₦${formatNumber(response.by_type.services)}</strong>
                            </li>
                        </ul>
                    </div>
                </div>`;

            $('#monthlySummaryContent').html(html);
        });
    }

    // Format number helper
    function formatNumber(num) {
        return parseFloat(num || 0).toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    // Report card click handler
    $('.report-card').on('click', function() {
        let reportType = $(this).data('report');

        $('.report-card').removeClass('active');
        $(this).addClass('active');

        $('.report-section').hide();

        switch(reportType) {
            case 'claims':
                $('#claimsReportSection').show();
                if (!claimsTable) initClaimsTable();
                break;
            case 'outstanding':
                $('#outstandingReportSection').show();
                loadOutstandingReport();
                break;
            case 'remittances':
                $('#remittancesSection').show();
                if (!remittancesTable) initRemittancesTable();
                loadRemittanceSummary();
                break;
            case 'monthly':
                $('#monthlySummarySection').show();
                break;
            case 'patient':
                $('#patientHistorySection').show();
                initPatientSearch();
                break;
            case 'utilization':
                $('#utilizationSection').show();
                break;
            case 'authcodes':
                $('#authCodesSection').show();
                if (!authCodesTable) initAuthCodesTable();
                break;
        }
    });

    // Initialize default report
    initClaimsTable();

    // Filter handlers
    $('#applyFilters').on('click', function() {
        claimsTable.ajax.reload();
    });

    $('#clearFilters').on('click', function() {
        $('#filter_hmo, #filter_status, #filter_submission, #filter_payment, #filter_service_category, #filter_product_category, #filter_type').val('');
        $('#filter_date_from, #filter_date_to').val('');
        claimsTable.ajax.reload();
    });

    // Claim checkbox handlers
    $('#selectAllClaims').on('change', function() {
        let checked = $(this).prop('checked');
        $('.claim-checkbox').prop('checked', checked);
        updateSelectedClaims();
    });

    $(document).on('change', '.claim-checkbox', function() {
        updateSelectedClaims();
    });

    function updateSelectedClaims() {
        selectedClaimIds = [];
        $('.claim-checkbox:checked').each(function() {
            selectedClaimIds.push($(this).data('id'));
        });
        $('#selectedClaimsInfo').text(selectedClaimIds.length + ' claims selected');
        $('#markSubmittedBtn').prop('disabled', selectedClaimIds.length === 0);
    }

    // Mark as submitted
    $('#markSubmittedBtn').on('click', function() {
        if (selectedClaimIds.length === 0) return;

        if (!confirm('Mark ' + selectedClaimIds.length + ' claims as submitted to HMO?')) return;

        $.post("{{ route('hmo.reports.mark-submitted') }}", {
            _token: '{{ csrf_token() }}',
            claim_ids: selectedClaimIds
        }, function(response) {
            if (response.success) {
                toastr.success(response.message);
                claimsTable.ajax.reload();
                selectedClaimIds = [];
                updateSelectedClaims();
            }
        });
    });

    // Print Report
    $('#printReportBtn').on('click', function() {
        let params = $.param({
            hmo_id: $('#filter_hmo').val(),
            status: $('#filter_status').val(),
            date_from: $('#filter_date_from').val(),
            date_to: $('#filter_date_to').val()
        });

        $.get("{{ route('hmo.reports.print-data') }}?" + params, function(response) {
            let html = generatePrintHTML(response);
            $('#printPreviewContent').html(html);
            $('#printPreviewModal').modal('show');
        });
    });

    // Generate Print HTML
    function generatePrintHTML(data) {
        let logoHtml = data.hospital.logo
            ? `<img src="data:image/png;base64,${data.hospital.logo}" style="max-height: 80px;" alt="Logo">`
            : `<h2>${data.hospital.name}</h2>`;

        let html = `
            <div style="font-family: Arial, sans-serif;">
                <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid ${appSettings.hosColor}; padding-bottom: 15px;">
                    ${logoHtml}
                    <h3 style="margin: 10px 0 5px 0;">${data.hospital.name}</h3>
                    <p style="margin: 0; font-size: 12px;">${data.hospital.address}</p>
                    <p style="margin: 0; font-size: 12px;">Tel: ${data.hospital.phones} | Email: ${data.hospital.emails}</p>
                </div>

                <div style="text-align: center; margin-bottom: 20px;">
                    <h4 style="margin: 0; color: ${appSettings.hosColor};">${data.report.title}</h4>
                    <p style="margin: 5px 0;"><strong>HMO:</strong> ${data.report.hmo_name}</p>
                    <p style="margin: 5px 0;"><strong>Period:</strong> ${data.report.period}</p>
                </div>

                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background: ${appSettings.hosColor}; color: white;">
                            <th style="border: 1px solid #ddd; padding: 8px;">S/N</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">Patient</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">File No</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">HMO No</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">Date</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">Item</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">Auth Code</th>
                            <th style="border: 1px solid #ddd; padding: 8px;">Qty</th>
                            <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Amount (₦)</th>
                        </tr>
                    </thead>
                    <tbody>`;

        data.claims.forEach(function(claim) {
            html += `<tr>
                <td style="border: 1px solid #ddd; padding: 6px;">${claim.sn}</td>
                <td style="border: 1px solid #ddd; padding: 6px;">${claim.patient_name}</td>
                <td style="border: 1px solid #ddd; padding: 6px;">${claim.file_no}</td>
                <td style="border: 1px solid #ddd; padding: 6px;">${claim.hmo_no}</td>
                <td style="border: 1px solid #ddd; padding: 6px;">${claim.service_date}</td>
                <td style="border: 1px solid #ddd; padding: 6px;">${claim.item}</td>
                <td style="border: 1px solid #ddd; padding: 6px;">${claim.auth_code}</td>
                <td style="border: 1px solid #ddd; padding: 6px; text-align: center;">${claim.qty}</td>
                <td style="border: 1px solid #ddd; padding: 6px; text-align: right;">${claim.claim_amount}</td>
            </tr>`;
        });

        html += `</tbody>
                    <tfoot>
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td colspan="8" style="border: 1px solid #ddd; padding: 8px; text-align: right;">TOTAL CLAIMS:</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">₦${data.summary.total_claims}</td>
                        </tr>
                    </tfoot>
                </table>

                <div style="margin-top: 30px; font-size: 11px;">
                    <p><strong>Summary:</strong> ${data.summary.total_count} claims totaling ₦${data.summary.total_claims}</p>
                    <p><strong>Approved:</strong> ${data.summary.approved_count} claims (₦${data.summary.total_approved})</p>
                </div>

                <div style="margin-top: 50px; display: flex; justify-content: space-between;">
                    <div style="text-align: center; width: 30%;">
                        <div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">
                            Prepared By
                        </div>
                        <small>${data.report.generated_by}</small>
                    </div>
                    <div style="text-align: center; width: 30%;">
                        <div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">
                            Verified By
                        </div>
                    </div>
                    <div style="text-align: center; width: 30%;">
                        <div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">
                            Authorized Signature
                        </div>
                    </div>
                </div>

                <div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666;">
                    <p>Generated on ${data.report.generated_at}</p>
                </div>
            </div>`;

        return html;
    }

    // Do Print
    $('#doPrintBtn').on('click', function() {
        let content = $('#printPreviewContent').html();
        let printWindow = window.open('', '', 'height=800,width=1000');
        printWindow.document.write(`
            <html>
            <head>
                <title>HMO Claims Report</title>
                <link rel="stylesheet" href="{{ asset('plugins/bootstrap/css/bootstrap.min.css') }}">
                <style>
                    @media print {
                        body { padding: 20px; }
                        @page { margin: 1cm; }
                    }
                </style>
            </head>
            <body>${content}</body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => printWindow.print(), 500);
    });

    // Export Excel
    $('#exportExcelBtn').on('click', function() {
        let params = $.param({
            hmo_id: $('#filter_hmo').val(),
            status: $('#filter_status').val(),
            date_from: $('#filter_date_from').val(),
            date_to: $('#filter_date_to').val()
        });
        window.location.href = "{{ route('hmo.reports.export-excel') }}?" + params;
    });

    // Remittance handlers
    $('#addRemittanceBtn').on('click', function() {
        $('#remittanceModalTitle').text('Record HMO Remittance');
        $('#remittanceForm')[0].reset();
        $('#remittance_id').val('');
        $('#remittanceModal').modal('show');
    });

    $('#remittanceForm').on('submit', function(e) {
        e.preventDefault();

        let id = $('#remittance_id').val();
        let url = id ? "{{ url('hmo/reports/remittances') }}/" + id : "{{ route('hmo.reports.remittances.store') }}";
        let method = id ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            method: method,
            data: {
                _token: '{{ csrf_token() }}',
                hmo_id: $('#remittance_hmo_id').val(),
                amount: $('#remittance_amount').val(),
                payment_date: $('#remittance_payment_date').val(),
                reference_number: $('#remittance_reference').val(),
                payment_method: $('#remittance_method').val(),
                bank_name: $('#remittance_bank').val(),
                period_from: $('#remittance_period_from').val(),
                period_to: $('#remittance_period_to').val(),
                notes: $('#remittance_notes').val()
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#remittanceModal').modal('hide');
                    remittancesTable.ajax.reload();
                    loadRemittanceSummary();
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    // View remittance
    $(document).on('click', '.view-remittance-btn', function() {
        let id = $(this).data('id');
        currentRemittanceId = id;

        $.get("{{ url('hmo/reports/remittances') }}/" + id, function(response) {
            let rem = response.remittance;
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><th>HMO:</th><td>${rem.hmo_name}</td></tr>
                            <tr><th>Amount:</th><td><strong>₦${rem.amount}</strong></td></tr>
                            <tr><th>Payment Date:</th><td>${rem.payment_date}</td></tr>
                            <tr><th>Reference:</th><td>${rem.reference_number || '-'}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><th>Method:</th><td>${rem.payment_method || '-'}</td></tr>
                            <tr><th>Bank:</th><td>${rem.bank_name || '-'}</td></tr>
                            <tr><th>Period:</th><td>${rem.period_from || '-'} to ${rem.period_to || '-'}</td></tr>
                            <tr><th>Recorded By:</th><td>${rem.created_by}</td></tr>
                        </table>
                    </div>
                </div>
                ${rem.notes ? `<div class="alert alert-info"><strong>Notes:</strong> ${rem.notes}</div>` : ''}
                <h6 class="mt-3">Linked Claims (${response.claims.length})</h6>
                <table class="table table-sm table-bordered">
                    <thead><tr><th>Patient</th><th>Item</th><th>Amount</th></tr></thead>
                    <tbody>`;

            response.claims.forEach(function(claim) {
                html += `<tr><td>${claim.patient}</td><td>${claim.item}</td><td>₦${claim.amount}</td></tr>`;
            });

            html += `</tbody>
                <tfoot><tr><th colspan="2">Total:</th><th>₦${response.claims_total}</th></tr></tfoot>
                </table>`;

            $('#viewRemittanceContent').html(html);
            $('#viewRemittanceModal').modal('show');
        });
    });

    // Edit remittance
    $(document).on('click', '.edit-remittance-btn', function() {
        let id = $(this).data('id');

        $.get("{{ url('hmo/reports/remittances') }}/" + id, function(response) {
            let rem = response.remittance;
            $('#remittanceModalTitle').text('Edit Remittance');
            $('#remittance_id').val(rem.id);
            $('#remittance_hmo_id').val(rem.hmo_id);
            $('#remittance_amount').val(parseFloat(rem.amount.replace(/,/g, '')));
            $('#remittance_payment_date').val(rem.payment_date);
            $('#remittance_reference').val(rem.reference_number);
            $('#remittance_method').val(rem.payment_method);
            $('#remittance_bank').val(rem.bank_name);
            $('#remittance_period_from').val(rem.period_from);
            $('#remittance_period_to').val(rem.period_to);
            $('#remittance_notes').val(rem.notes);
            $('#remittanceModal').modal('show');
        });
    });

    // Delete remittance
    $(document).on('click', '.delete-remittance-btn', function() {
        let id = $(this).data('id');

        if (!confirm('Are you sure you want to delete this remittance? Claims will be unlinked.')) return;

        $.ajax({
            url: "{{ url('hmo/reports/remittances') }}/" + id,
            method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    remittancesTable.ajax.reload();
                    loadRemittanceSummary();
                }
            }
        });
    });

    // Filter remittances
    $('#filterRemittances').on('click', function() {
        remittancesTable.ajax.reload();
    });

    // Load remittance summary
    function loadRemittanceSummary() {
        $.get("{{ route('hmo.reports.outstanding') }}", function(response) {
            let html = `
                <div class="text-center mb-3">
                    <h3 class="text-danger">₦${formatNumber(response.summary.total_outstanding)}</h3>
                    <small class="text-muted">Total Outstanding</small>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Claims:</span>
                    <strong>₦${formatNumber(response.summary.total_claims)}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Paid:</span>
                    <strong class="text-success">₦${formatNumber(response.summary.total_paid)}</strong>
                </div>`;

            $('#remittanceSummary').html(html);
        });
    }

    // Load monthly summary
    $('#loadMonthlySummary').on('click', function() {
        loadMonthlySummary();
    });

    // Print outstanding report
    $('#printOutstandingBtn').on('click', function() {
        let content = $('#outstandingReportSection .card-body').html();
        let printWindow = window.open('', '', 'height=800,width=1000');
        printWindow.document.write(`
            <html>
            <head>
                <title>Outstanding Claims Report</title>
                <link rel="stylesheet" href="{{ asset('plugins/bootstrap/css/bootstrap.min.css') }}">
                <style>
                    body { padding: 20px; font-family: Arial, sans-serif; }
                    .aging-cell-current { background-color: #d4edda !important; }
                    .aging-cell-warning { background-color: #fff3cd !important; }
                    .aging-cell-danger { background-color: #f8d7da !important; }
                    .aging-cell-critical { background-color: #dc3545 !important; color: white; }
                    @media print { @page { margin: 1cm; } }
                </style>
            </head>
            <body>
                <div style="text-align: center; margin-bottom: 20px;">
                    <h3>${appSettings.siteName}</h3>
                    <h4>Outstanding HMO Claims Report</h4>
                    <p>Generated: ${new Date().toLocaleString()}</p>
                </div>
                ${content}
                <div style="margin-top: 50px;">
                    <div style="display: flex; justify-content: space-between;">
                        <div style="text-align: center; width: 30%;">
                            <div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Prepared By</div>
                        </div>
                        <div style="text-align: center; width: 30%;">
                            <div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Verified By</div>
                        </div>
                        <div style="text-align: center; width: 30%;">
                            <div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Authorized Signature</div>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => printWindow.print(), 500);
    });

    // View HMO specific claims from outstanding table
    $(document).on('click', '.view-hmo-claims', function() {
        let hmoId = $(this).data('hmo-id');
        $('#filter_hmo').val(hmoId);
        $('#filter_status').val('approved');
        $('.report-card[data-report="claims"]').click();
        setTimeout(() => claimsTable.ajax.reload(), 100);
    });

    // PDF Export
    $('#exportPdfBtn').on('click', function() {
        let params = $.param({
            hmo_id: $('#filter_hmo').val(),
            status: $('#filter_status').val(),
            date_from: $('#filter_date_from').val(),
            date_to: $('#filter_date_to').val()
        });
        window.location.href = "{{ route('hmo.reports.export-pdf') }}?" + params;
    });

    // =============================================
    // PATIENT HISTORY SECTION
    // =============================================
    let patientSearchInitialized = false;

    function initPatientSearch() {
        if (patientSearchInitialized) return;

        $('#patientSearchSelect').select2({
            placeholder: 'Type patient name, file no, or HMO no...',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: "{{ route('hmo.reports.search-patients') }}",
                dataType: 'json',
                delay: 300,
                data: function(params) {
                    return { q: params.term };
                },
                processResults: function(data) {
                    return { results: data };
                }
            }
        });

        patientSearchInitialized = true;
    }

    $('#patientSearchSelect').on('select2:select', function(e) {
        let data = e.params.data;
        selectedPatientId = data.id;
        $('#patientInfoText').html(`<strong>${data.text}</strong> | HMO: ${data.hmo_name}`);
        $('#selectedPatientInfo').show();
        loadPatientClaims(data.id);
    });

    function loadPatientClaims(patientId) {
        $('#patientClaimsContent').html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px"></i><p>Loading patient claims...</p></div>');

        $.get("{{ url('hmo/reports/patient') }}/" + patientId, function(response) {
            let html = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-left-primary">
                            <div class="card-body">
                                <h6 class="text-muted mb-2">Patient Details</h6>
                                <p class="mb-1"><strong>Name:</strong> ${response.patient.name}</p>
                                <p class="mb-1"><strong>File No:</strong> ${response.patient.file_no}</p>
                                <p class="mb-1"><strong>HMO No:</strong> ${response.patient.hmo_no}</p>
                                <p class="mb-0"><strong>HMO:</strong> ${response.patient.hmo_name}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center py-3">
                                        <h4 class="mb-0">₦${response.summary.total_claims}</h4>
                                        <small>Total Claims (Approved)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center py-3">
                                        <h4 class="mb-0">₦${response.summary.total_patient_paid}</h4>
                                        <small>Patient Paid</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4">
                                <div class="alert alert-success mb-0 py-2 text-center">
                                    <strong>${response.summary.approved_count}</strong><br><small>Approved</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-danger mb-0 py-2 text-center">
                                    <strong>${response.summary.rejected_count}</strong><br><small>Rejected</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-warning mb-0 py-2 text-center">
                                    <strong>${response.summary.pending_count}</strong><br><small>Pending</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between mb-3">
                    <h6><i class="fa fa-list"></i> Claims History</h6>
                    <button class="btn btn-success btn-sm" onclick="printPatientReport(${patientId})">
                        <i class="fa fa-print"></i> Print Report
                    </button>
                </div>

                <table class="table table-sm table-bordered table-striped">
                    <thead class="thead-dark">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Auth Code</th>
                            <th>Claim (₦)</th>
                            <th>Patient Pays (₦)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>`;

            response.claims.forEach(function(claim) {
                let statusClass = claim.status === 'Approved' ? 'success' : (claim.status === 'Rejected' ? 'danger' : 'warning');
                html += `<tr>
                    <td>${claim.date}</td>
                    <td>${claim.type}</td>
                    <td>${claim.item}</td>
                    <td>${claim.qty}</td>
                    <td>${claim.auth_code}</td>
                    <td class="text-right">${claim.claim_amount}</td>
                    <td class="text-right">${claim.patient_pays}</td>
                    <td><span class="badge badge-${statusClass}">${claim.status}</span></td>
                </tr>`;
            });

            html += '</tbody></table>';
            $('#patientClaimsContent').html(html);
        });
    }

    // Print patient report
    window.printPatientReport = function(patientId) {
        $.get("{{ url('hmo/reports/patient') }}/" + patientId + "/print", function(data) {
            let html = generatePatientPrintHTML(data);
            let printWindow = window.open('', '', 'height=800,width=1000');
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => printWindow.print(), 500);
        });
    }

    function generatePatientPrintHTML(data) {
        let html = `
            <html>
            <head>
                <title>Patient Claims Report</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; font-size: 12px; }
                    .header { text-align: center; border-bottom: 2px solid ${data.hospital.color}; padding-bottom: 15px; margin-bottom: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                    th { background: ${data.hospital.color}; color: white; padding: 8px; text-align: left; }
                    td { border: 1px solid #ddd; padding: 6px; }
                    .signatures { margin-top: 50px; display: flex; justify-content: space-between; }
                    .sig-box { width: 30%; text-align: center; }
                    .sig-line { border-top: 1px solid #000; margin-top: 50px; padding-top: 5px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>${data.hospital.name}</h2>
                    <p>${data.hospital.address}</p>
                    <p>Tel: ${data.hospital.phones} | Email: ${data.hospital.emails}</p>
                </div>

                <h3 style="text-align: center; color: ${data.hospital.color};">Patient Claims Report</h3>

                <table style="width: 60%; margin: 0 auto 20px; border: none;">
                    <tr><td style="border:none;"><strong>Patient:</strong></td><td style="border:none;">${data.patient.name}</td></tr>
                    <tr><td style="border:none;"><strong>File No:</strong></td><td style="border:none;">${data.patient.file_no}</td></tr>
                    <tr><td style="border:none;"><strong>HMO No:</strong></td><td style="border:none;">${data.patient.hmo_no}</td></tr>
                    <tr><td style="border:none;"><strong>HMO:</strong></td><td style="border:none;">${data.patient.hmo_name}</td></tr>
                </table>

                <table>
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Item</th>
                            <th>Auth Code</th>
                            <th>Claim (₦)</th>
                            <th>Patient Pays (₦)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>`;

        data.claims.forEach(function(claim) {
            html += `<tr>
                <td>${claim.sn}</td>
                <td>${claim.date}</td>
                <td>${claim.type}</td>
                <td>${claim.item}</td>
                <td>${claim.auth_code}</td>
                <td style="text-align:right;">${claim.claim_amount}</td>
                <td style="text-align:right;">${claim.patient_pays}</td>
                <td>${claim.status}</td>
            </tr>`;
        });

        html += `</tbody>
                <tfoot>
                    <tr style="background:#f0f0f0; font-weight:bold;">
                        <td colspan="5" style="text-align:right;">TOTALS:</td>
                        <td style="text-align:right;">₦${data.summary.total_claims}</td>
                        <td style="text-align:right;">₦${data.summary.total_patient_paid}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <div class="signatures">
                <div class="sig-box"><div class="sig-line">Prepared By</div></div>
                <div class="sig-box"><div class="sig-line">Verified By</div></div>
                <div class="sig-box"><div class="sig-line">Authorized Signature</div></div>
            </div>

            <p style="text-align:center; margin-top:30px; font-size:10px; color:#999;">
                Generated on ${data.generated_at} by ${data.generated_by}
            </p>
            </body></html>`;

        return html;
    }

    // =============================================
    // SERVICE UTILIZATION SECTION
    // =============================================
    $('#loadUtilization').on('click', function() {
        loadUtilizationReport();
    });

    function loadUtilizationReport() {
        let dateFrom = $('#util_date_from').val();
        let dateTo = $('#util_date_to').val();

        $('#utilizationContent').html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px"></i><p>Loading utilization data...</p></div>');

        $.get("{{ route('hmo.reports.utilization') }}", { date_from: dateFrom, date_to: dateTo }, function(response) {
            let html = `
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center py-3">
                                <h4 class="mb-0">₦${formatNumber(response.summary.total_claims)}</h4>
                                <small>Total HMO Claims</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center py-3">
                                <h4 class="mb-0">₦${formatNumber(response.summary.total_services)}</h4>
                                <small>Services Revenue</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center py-3">
                                <h4 class="mb-0">₦${formatNumber(response.summary.total_products)}</h4>
                                <small>Products Revenue</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center py-3">
                                <h4 class="mb-0">${response.summary.total_count}</h4>
                                <small>Total Transactions</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="fa fa-star"></i> Top 10 Services</h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Service</th><th>Category</th><th>Count</th><th class="text-right">Revenue</th></tr></thead>
                                    <tbody>`;

            response.top_services.forEach(function(item) {
                html += `<tr>
                    <td>${item.name}</td>
                    <td><small class="text-muted">${item.category}</small></td>
                    <td>${item.count}</td>
                    <td class="text-right">₦${formatNumber(item.revenue)}</td>
                </tr>`;
            });

            html += `</tbody></table></div></div></div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fa fa-star"></i> Top 10 Products</h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Product</th><th>Category</th><th>Count</th><th class="text-right">Revenue</th></tr></thead>
                                    <tbody>`;

            response.top_products.forEach(function(item) {
                html += `<tr>
                    <td>${item.name}</td>
                    <td><small class="text-muted">${item.category}</small></td>
                    <td>${item.count}</td>
                    <td class="text-right">₦${formatNumber(item.revenue)}</td>
                </tr>`;
            });

            html += `</tbody></table></div></div></div></div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h6 class="mb-0">Service Categories Breakdown</h6></div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Category</th><th>Count</th><th class="text-right">Total</th></tr></thead>
                                    <tbody>`;

            response.service_categories.forEach(function(cat) {
                html += `<tr><td>${cat.category_name}</td><td>${cat.count}</td><td class="text-right">₦${formatNumber(cat.total)}</td></tr>`;
            });

            html += `</tbody></table></div></div></div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h6 class="mb-0">Product Categories Breakdown</h6></div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Category</th><th>Count</th><th class="text-right">Total</th></tr></thead>
                                    <tbody>`;

            response.product_categories.forEach(function(cat) {
                html += `<tr><td>${cat.category_name}</td><td>${cat.count}</td><td class="text-right">₦${formatNumber(cat.total)}</td></tr>`;
            });

            html += `</tbody></table></div></div></div></div>

                <div class="text-right mt-3">
                    <button class="btn btn-success" onclick="printUtilizationReport()">
                        <i class="fa fa-print"></i> Print Utilization Report
                    </button>
                </div>`;

            $('#utilizationContent').html(html);
        });
    }

    window.printUtilizationReport = function() {
        let content = $('#utilizationContent').html();
        let printWindow = window.open('', '', 'height=800,width=1000');
        printWindow.document.write(`
            <html>
            <head>
                <title>Service Utilization Report</title>
                <link rel="stylesheet" href="{{ asset('plugins/bootstrap/css/bootstrap.min.css') }}">
                <style>
                    body { padding: 20px; font-family: Arial, sans-serif; }
                    @media print { @page { margin: 1cm; } }
                </style>
            </head>
            <body>
                <div style="text-align: center; margin-bottom: 20px;">
                    <h3>${appSettings.siteName}</h3>
                    <h4>Service Utilization Report</h4>
                    <p>Period: ${$('#util_date_from').val()} to ${$('#util_date_to').val()}</p>
                </div>
                ${content}
                <div style="margin-top: 50px; display: flex; justify-content: space-between;">
                    <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Prepared By</div></div>
                    <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Verified By</div></div>
                    <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Authorized Signature</div></div>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => printWindow.print(), 500);
    }

    // =============================================
    // AUTH CODE TRACKER SECTION
    // =============================================
    function initAuthCodesTable() {
        if (authCodesTable) {
            authCodesTable.destroy();
        }

        authCodesTable = $('#authCodesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('hmo.reports.auth-codes') }}",
                data: function(d) {
                    d.hmo_id = $('#auth_filter_hmo').val();
                    d.auth_status = $('#auth_filter_status').val();
                    d.date_from = $('#auth_filter_from').val();
                    d.date_to = $('#auth_filter_to').val();
                }
            },
            columns: [
                { data: 'DT_RowIndex', orderable: false },
                { data: 'patient_name' },
                { data: 'hmo_no' },
                { data: 'hmo_name' },
                { data: 'service_date' },
                { data: 'item_name' },
                { data: 'auth_code_display' },
                { data: 'claim_amount' },
                { data: 'status_badge' }
            ],
            order: [[4, 'desc']],
            drawCallback: function() {
                updateAuthStats();
            }
        });
    }

    function updateAuthStats() {
        // Get stats via info from table or separate AJAX call
        let withCode = 0, withoutCode = 0, withCodeAmount = 0, withoutCodeAmount = 0;
        // This is simplified - in production you might want a separate endpoint
    }

    $('#applyAuthFilters').on('click', function() {
        authCodesTable.ajax.reload();
    });

    $('#printAuthReport').on('click', function() {
        let content = $('#authCodesSection .card-body').html();
        let printWindow = window.open('', '', 'height=800,width=1000');
        printWindow.document.write(`
            <html>
            <head>
                <title>Auth Code Tracker Report</title>
                <link rel="stylesheet" href="{{ asset('plugins/bootstrap/css/bootstrap.min.css') }}">
                <style>
                    body { padding: 20px; font-family: Arial, sans-serif; }
                    @media print { @page { margin: 1cm; } }
                </style>
            </head>
            <body>
                <div style="text-align: center; margin-bottom: 20px;">
                    <h3>${appSettings.siteName}</h3>
                    <h4>Authorization Code Tracker Report</h4>
                    <p>Generated: ${new Date().toLocaleString()}</p>
                </div>
                ${content}
                <div style="margin-top: 50px; display: flex; justify-content: space-between;">
                    <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Prepared By</div></div>
                    <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Verified By</div></div>
                    <div style="text-align: center; width: 30%;"><div style="border-top: 1px solid #000; margin-top: 50px; padding-top: 5px;">Authorized Signature</div></div>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => printWindow.print(), 500);
    });
});
</script>
@endsection
