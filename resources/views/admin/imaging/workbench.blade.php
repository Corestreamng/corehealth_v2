@extends('admin.layouts.app')

@section('title', 'Imaging Workbench')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/imaging-workbench.css') }}?v={{ filemtime(public_path('css/imaging-workbench.css')) }}">
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
@endpush

@section('content')
@php
    $hosColor = appsettings()->hos_color ?? '#0066cc';
    $sett = appsettings();
@endphp


<div class="imaging-workbench-container">
    <!-- Left Panel: Patient Search & Queue -->
    <div class="left-panel" id="left-panel">
        <div class="panel-header">
            <h5><i class="fa fa-search"></i> Patient Search</h5>
            <button class="btn-view-work-pane" id="btn-view-work-pane" title="View Work Pane">
                <i class="fa fa-arrow-right"></i> Work Pane
            </button>
        </div>

        @include('admin.partials.patient_search_html')

        <div class="queue-widget">
            <h6>📊 PENDING QUEUE</h6>
            <div class="queue-item" data-filter="emergency" style="background: #fff5f5; border-left: 3px solid #dc3545;">
                <span class="queue-item-label">🚨 <strong class="text-danger">Emergency</strong></span>
                <span class="queue-count" id="queue-emergency-count" style="background: #dc3545; color: #fff;">0</span>
            </div>
            <div class="queue-item" data-filter="billing">
                <span class="queue-item-label">🟡 Awaiting Billing</span>
                <span class="queue-count billing" id="queue-billing-count">0</span>
            </div>
            <!-- No sample collection stage for imaging -->
            <div class="queue-item" data-filter="results">
                <span class="queue-item-label">🔴 Result Entry</span>
                <span class="queue-count results" id="queue-results-count">0</span>
            </div>
            <div class="queue-item" data-filter="freeform">
                <span class="queue-item-label">⚪ Free-Form / External</span>
                <span class="queue-count" id="queue-freeform-count" style="background: #6c757d; color: white;">0</span>
            </div>
            @if(($isApprover ?? false) && ($requiresApproval ?? false))
            <div class="queue-item" data-filter="approval" style="background: #f3f0ff; border-left: 3px solid #6f42c1;">
                <span class="queue-item-label">🟣 <strong class="text-purple">Awaiting Approval</strong></span>
                <span class="queue-count" id="queue-approval-count" style="background: #6f42c1; color: #fff;">0</span>
            </div>
            @endif
            <button class="btn-queue-all" id="show-all-queue-btn">
                📋 Show All Queue →
            </button>
        </div>

        <div class="quick-actions">
            <h6>⚡ QUICK ACTIONS</h6>
            <button class="quick-action-btn" id="btn-register-walkin">
                <i class="mdi mdi-account-plus text-success"></i>
                <span>Register Walk-in</span>
            </button>
            <button class="quick-action-btn" id="btn-new-request" style="display: none;">
                <i class="mdi mdi-plus-circle"></i>
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
            @if(appsettings()->enable_ei_imaging)
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
                <!-- Approval Queue Hint -->
                <div id="approval-queue-hint" class="alert alert-info" style="display: none; font-size: 0.9rem; margin: 1rem 1.5rem; border-left: 4px solid #0dcaf0;">
                    <i class="mdi mdi-information"></i> <strong>Note:</strong> Once approved, requests are moved to the <strong>Completed</strong> list. You can reverse your recent approvals from there.
                </div>
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
                    <!-- No sample collection stage for imaging -->
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
                        <h4><i class="mdi mdi-plus-circle"></i> Create New Imaging Request</h4>
                        <p class="text-muted">Request imaging for <span id="new-request-patient-name"></span></p>
                    </div>
                    <form id="new-imaging-request-form" class="new-request-form">
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="service-search-input" class="mb-0"><i class="mdi mdi-magnify"></i> Search Imaging Services *</label>
                                    <a href="javascript:void(0)" onclick="addFreeFormImagingWorkbench()" class="text-primary small"><i class="mdi mdi-plus"></i> Not listed? Add free-form</a>
                                </div>
                                <input type="text" class="form-control" id="service-search-input" placeholder="Type to search for imaging services..." autocomplete="off" onkeyup="searchImagingServices(this.value)">
                                <ul class="list-group" id="service-search-results" style="display: none; position: absolute; z-index: 1000; max-height: 300px; overflow-y: auto; width: calc(100% - 30px);"></ul>
                            </div>
                        </div>

                        <div id="selected-services-container" class="mb-3">
                            <label><i class="mdi mdi-radioactive"></i> Selected Services</label>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped">
                                    <thead>
                                        <th>Name</th>
                                        <th>Price</th>
                                        <th>Notes</th>
                                        <th>*</th>
                                    </thead>
                                    <tbody id="selected-imaging-services"></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="request-urgency"><i class="mdi mdi-clock-alert"></i> Urgency Level</label>
                                <select class="form-control" id="request-urgency" name="urgency">
                                    <option value="routine">Routine</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="stat">STAT (Immediate)</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="request-priority"><i class="mdi mdi-flag"></i> Priority</label>
                                <select class="form-control" id="request-priority" name="priority">
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="request-clinical-notes"><i class="mdi mdi-note-text"></i> Clinical Notes / Indication</label>
                            <textarea class="form-control" id="request-clinical-notes" name="clinical_notes" rows="4" placeholder="Enter clinical indication, symptoms, or relevant patient history..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="request-special-instructions"><i class="mdi mdi-information"></i> Special Instructions</label>
                            <textarea class="form-control" id="request-special-instructions" name="special_instructions" rows="2" placeholder="Any special handling or processing instructions..."></textarea>
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
                <h4>Imaging History</h4>
                <div class="history-table">
                    <table class="table table-hover" style="width: 100%" id="investigation_history_list">
                        <thead class="table-light">
                            <th><i class="mdi mdi-radioactive"></i> Imaging Requests</th>
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

@include('admin.partials.invest_res_view_modal', ['resultViewTitle' => 'Imaging Results'])
@include('admin.partials.invest_res_view_js')

@include('admin.partials.invest_res_view_imaging_modal')
@include('admin.partials.invest_res_view_imaging_js')

@include('admin.partials.invest_res_modal', ['save_route' => 'imaging.saveResult'])

<!-- Include Bulk Result Entry Modal -->
@include('admin.partials.bulk_result_entry_modal')

{{-- Result Approval Review Modal --}}
@if(($isApprover ?? false) && ($requiresApproval ?? false))
@include('admin.imaging.partials._modals')
@endif

@endsection

@include('admin.imaging.partials._scripts')
