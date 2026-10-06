@extends('admin.layouts.app')

@section('title', 'Billing Workbench')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('css/billing-workbench.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
@endpush

@section('content')
@php
$hosColor = appsettings()->hos_color ?? '#0066cc';
$sett = appsettings();
@endphp


<!-- Workbench Lock Overlay (shown when no active shift) -->
<div id="shift-lock-overlay" class="shift-lock-overlay" style="display: none;">
    <div class="shift-lock-content">
        <div class="shift-lock-icon">
            <i class="mdi mdi-clock-alert-outline"></i>
        </div>
        <h3>Start Your Shift</h3>
        <p class="text-muted">Please start your billing shift to access the workbench and process payments.</p>
        <button class="btn btn-lg btn-success" id="start-shift-btn">
            <i class="mdi mdi-play-circle"></i> Start Shift
        </button>
        <div class="shift-lock-nav-buttons mt-4">
            <a href="{{ route('home') }}" class="btn btn-outline-primary btn-lg me-2">
                <i class="mdi mdi-home"></i> Home
            </a>
            <a href="{{ route('logout') }}"
                onclick="event.preventDefault(); document.getElementById('shift-overlay-logout-form').submit();"
                class="btn btn-outline-danger btn-lg">
                <i class="mdi mdi-logout"></i> Logout
            </a>
            <form id="shift-overlay-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                @csrf
            </form>
        </div>
    </div>
</div>

<!-- Start Shift Modal -->
<div class="modal fade" id="startShiftModal" tabindex="-1" aria-labelledby="startShiftModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="startShiftModalLabel">
                    <i class="mdi mdi-play-circle"></i> Start Billing Shift
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label for="shift-type-select">Shift Type</label>
                    <select class="form-control" id="shift-type-select">
                        <option value="morning">Morning (8AM - 2PM)</option>
                        <option value="afternoon">Afternoon (2PM - 8PM)</option>
                        <option value="night">Night (8PM - 8AM)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirm-start-shift-btn">
                    <i class="mdi mdi-play-circle"></i> Start Shift
                </button>
            </div>
        </div>
    </div>
</div>

<!-- End Shift Modal -->
<div class="modal fade" id="endShiftModal" tabindex="-1" aria-labelledby="endShiftModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="endShiftModalLabel">
                    <i class="mdi mdi-stop-circle"></i> End Billing Shift
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to end your current billing shift?</p>
                <div id="shift-summary-preview"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-end-shift-btn">
                    <i class="mdi mdi-stop-circle"></i> End Shift
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Floating Shift Control Button -->
<div id="shift-control-fab" class="shift-control-fab" style="display: none;">
    <div class="shift-fab-timer">
        <span id="shift-elapsed-time">00:00</span>
    </div>
    <div class="shift-fab-main">
        <button class="btn-shift-control" id="shift-fab-btn" title="End Shift">
            <i class="mdi mdi-stop-circle"></i>
        </button>
    </div>
</div>

<div class="billing-workbench-container">
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
            <h6>📊 PAYMENT QUEUE</h6>
            <div class="queue-item" data-filter="emergency" style="background: #fff5f5; border-left: 3px solid #dc3545;">
                <span class="queue-item-label">🚨 <strong class="text-danger">Emergency</strong></span>
                <span class="queue-count" id="queue-emergency-count" style="background: #dc3545; color: #fff;">0</span>
            </div>
            <div class="queue-item" data-filter="all">
                <span class="queue-item-label">🟡 All Unpaid</span>
                <span class="queue-count all-unpaid" id="queue-all-count">0</span>
            </div>
            <div class="queue-item" data-filter="hmo">
                <span class="queue-item-label">🟢 HMO Items</span>
                <span class="queue-count hmo-items" id="queue-hmo-count">0</span>
            </div>
            <div class="queue-item" data-filter="credit">
                <span class="queue-item-label">🟠 Credit Accounts</span>
                <span class="queue-count credit-accounts" id="queue-credit-count">0</span>
            </div>
            <button class="btn-queue-all" id="show-all-queue-btn">
                📋 Show All Queue →
            </button>
        </div>

        <div class="quick-actions">
            <h6>⚡ QUICK ACTIONS</h6>
            <button class="quick-action-btn" id="btn-my-transactions">
                <i class="mdi mdi-receipt"></i>
                <span>My Transactions</span>
            </button>
            <button class="quick-action-btn" disabled style="opacity: 0.5;">
                <i class="mdi mdi-file-invoice-dollar"></i>
                <span>Generate Invoice (Coming Soon)</span>
            </button>
            <button class="quick-action-btn" disabled style="opacity: 0.5;">
                <i class="mdi mdi-wallet"></i>
                <span>Credit Management (Coming Soon)</span>
            </button>
            @if(appsettings()->enable_ei_billing)
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
                <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#hospital-contacts-modal" title="Hospital Contacts">
                    <i class="mdi mdi-contacts"></i> Contacts
                </button>
                <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#workbench-price-list-modal" title="Price List">
                    <i class="mdi mdi-currency-usd"></i> Price List
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <div class="empty-state" id="empty-state">
            <i class="mdi mdi-account-cash"></i>
            <h3>No Patient Selected</h3>
            <p>Search and select a patient from the queue to begin billing</p>
            <button class="btn btn-lg btn-primary" id="view-queue-btn">
                💰 View Payment Queue
            </button>
        </div>

        <!-- Queue View -->
        <div class="queue-view" id="queue-view">
            <div class="queue-view-header">
                <h4 id="queue-view-title"><i class="mdi mdi-format-list-bulleted"></i> Payment Queue</h4>
                <button class="btn-close-queue" id="btn-close-queue">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content">
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
                <div class="d-flex flex-column align-items-end gap-2">
                    <div class="patient-account-balance" id="patient-header-balance" style="display: none;">
                        <div class="balance-label">Account Balance</div>
                        <div class="balance-value" id="header-balance-amount">₦0.00</div>
                    </div>
                    <button class="btn-expand-patient" id="btn-expand-patient" title="Show more details">
                        <span class="btn-expand-text">more biodata</span>
                        <i class="mdi mdi-chevron-down"></i>
                    </button>
                </div>
            </div>
            <div class="patient-details-expanded" id="patient-details-expanded">
                <div class="patient-details-grid" id="patient-details-grid"></div>
            </div>
        </div>

        <!-- Workspace Content -->
        <div class="workspace-content" id="workspace-content">
            <div class="workspace-tabs">
                <button class="workspace-tab active" data-tab="billing">
                    <i class="mdi mdi-cash-register"></i>
                    <span>Billing</span>
                    <span class="workspace-tab-badge" id="billing-badge">0</span>
                </button>
                <button class="workspace-tab" data-tab="receipts">
                    <i class="mdi mdi-receipt"></i>
                    <span>Receipts</span>
                </button>
                <button class="workspace-tab" data-tab="admissions">
                    <i class="mdi mdi-hospital-building"></i>
                    <span>Admissions</span>
                    <span class="workspace-tab-badge" id="admissions-badge" style="display: none;">0</span>
                </button>
                <button class="workspace-tab" data-tab="account">
                    <i class="mdi mdi-wallet"></i>
                    <span>Account</span>
                </button>
            </div>

            <div class="workspace-tab-content active" id="billing-tab">
                <div class="billing-tab-header">
                    <h4><i class="mdi mdi-cash-register"></i> Patient Billing Items</h4>
                    <div class="billing-toolbar">
                        <button class="btn btn-sm btn-secondary" id="refresh-billing-items">
                            <i class="mdi mdi-refresh"></i> Refresh
                        </button>
                        <button class="btn btn-sm btn-warning" id="print-invoice-btn" disabled>
                            <i class="mdi mdi-file-document-outline"></i> Print Invoice
                        </button>
                        <button class="btn btn-sm btn-success" id="process-payment-btn" disabled>
                            <i class="mdi mdi-cash"></i> Process Payment
                        </button>
                    </div>
                </div>

                <!-- Family Tabs Container -->
                <div id="billing-family-tabs" class="workspace-tabs" style="display:none; margin: 0; padding: 0 1rem; border-bottom: 1px solid #dee2e6;"></div>

                <!-- Billing Items Filter/Search Bar -->
                <div class="billing-filter-bar">
                    <div class="billing-filter-row">
                        <div class="billing-filter-search">
                            <i class="mdi mdi-magnify"></i>
                            <input type="text" id="billing-search-input" class="form-control form-control-sm" placeholder="Search items...">
                        </div>
                        <div class="billing-filter-category">
                            <select id="billing-category-filter" class="form-control form-control-sm">
                                <option value="">All Categories</option>
                            </select>
                        </div>
                        <div class="billing-filter-date">
                            <input type="date" id="billing-date-from" class="form-control form-control-sm" placeholder="From">
                            <span class="date-separator">to</span>
                            <input type="date" id="billing-date-to" class="form-control form-control-sm" placeholder="To">
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" id="clear-billing-filters">
                            <i class="mdi mdi-filter-remove"></i> Clear
                        </button>
                    </div>
                    <div class="billing-filter-stats">
                        <span class="filter-stat">
                            <span id="billing-items-visible">0</span> of <span id="billing-items-total">0</span> items
                        </span>
                        <span class="filter-stat-divider">|</span>
                        <span class="filter-stat">
                            <input type="checkbox" id="select-visible-only" style="margin-right: 4px;">
                            <label for="select-visible-only" style="margin-bottom: 0; cursor: pointer;">Select visible only</label>
                        </span>
                        <span class="filter-stat-divider">|</span>
                        <span class="filter-stat total-visible-amount">
                            Visible Total: <strong id="billing-visible-total">₦0.00</strong>
                        </span>
                    </div>
                </div>

                <div class="billing-items-container">
                    <table class="table table-hover" id="billing-items-table">
                        <thead>
                            <tr>
                                <th width="40"><input type="checkbox" id="select-all-billing-items"></th>
                                <th>Date/Time</th>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th width="80">Qty</th>
                                <th width="80">Discount %</th>
                                <th>HMO Coverage</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="billing-items-tbody">
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="mdi mdi-information-outline" style="font-size: 3rem;"></i>
                                    <p>No unpaid items for this patient</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Floating Cart Button (appears when items selected) -->
                <div class="floating-cart" id="floating-cart">
                    <button class="floating-cart-btn" data-bs-toggle="modal" data-bs-target="#paymentModal">
                        <i class="mdi mdi-cart-outline"></i>
                        <span class="cart-badge" id="cart-item-count">0</span>
                        <span class="cart-total" id="cart-total-display">₦0.00</span>
                        <i class="mdi mdi-chevron-up"></i>
                    </button>
                </div>
            </div>

            <!-- Receipts Tab -->
            <div class="workspace-tab-content" id="receipts-tab">
                <div class="receipts-tab-header">
                    <h4><i class="mdi mdi-receipt"></i> Payment Receipts & Transactions</h4>
                    <div class="receipts-toolbar">
                        <button class="btn btn-sm btn-secondary" id="refresh-receipts">
                            <i class="mdi mdi-refresh"></i> Refresh
                        </button>
                        <button class="btn btn-sm btn-primary" id="print-selected-receipts" disabled>
                            <i class="mdi mdi-printer"></i> Print Selected
                        </button>
                        <button class="btn btn-sm btn-info" id="export-receipts">
                            <i class="mdi mdi-download"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Filter Panel -->
                <div class="transactions-filter-panel">
                    <div class="row">
                        <div class="col-md-3">
                            <label>From Date</label>
                            <input type="date" class="form-control" id="receipts-from-date">
                        </div>
                        <div class="col-md-3">
                            <label>To Date</label>
                            <input type="date" class="form-control" id="receipts-to-date">
                        </div>
                        <div class="col-md-3">
                            <label>Payment Type</label>
                            <select class="form-control" id="receipts-payment-type">
                                <option value="">All Types</option>
                                <option value="CASH">Cash</option>
                                <option value="POS">POS/Card</option>
                                <option value="TRANSFER">Bank Transfer</option>
                                <option value="MOBILE">Mobile Money</option>
                                <option value="ACCOUNT">Account Balance</option>
                                <option value="ACC_DEPOSIT">Account Deposit</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>&nbsp;</label>
                            <button class="btn btn-primary btn-block" id="filter-receipts">
                                <i class="mdi mdi-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="transactions-summary" id="receipts-summary" style="display: none;">
                    <div class="stat-card">
                        <div class="stat-value" id="receipts-total-count">0</div>
                        <div class="stat-label">Total Transactions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="receipts-total-amount">₦0.00</div>
                        <div class="stat-label">Total Amount</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="receipts-total-discounts">₦0.00</div>
                        <div class="stat-label">Total Discounts</div>
                    </div>
                </div>

                <div class="receipts-container">
                    <table class="table table-hover" id="receipts-table">
                        <thead>
                            <tr>
                                <th width="40"><input type="checkbox" id="select-all-receipts"></th>
                                <th>Receipt No</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Discount</th>
                                <th>Method</th>
                                <th>Cashier</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="receipts-tbody">
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="mdi mdi-receipt" style="font-size: 3rem;"></i>
                                    <p>No receipts found for this patient</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Admissions Tab (Reusable Module) -->
            <div class="workspace-tab-content" id="admissions-tab">
                @include('admin.partials.admissions-module')
            </div>

            <!-- Account Tab -->
            <div class="workspace-tab-content" id="account-tab">
                <!-- Hero Balance Section -->
                <div class="account-hero-section" id="account-hero-section">
                    <div class="account-hero-balance" id="account-hero-balance">
                        <div class="hero-balance-icon">
                            <i class="mdi mdi-wallet"></i>
                        </div>
                        <div class="hero-balance-content">
                            <span class="hero-balance-label">Current Balance</span>
                            <span class="hero-balance-amount" id="hero-balance-amount">₦0.00</span>
                            <span class="hero-balance-status" id="hero-balance-status">Balanced</span>
                        </div>
                        <div class="hero-balance-actions">
                            <div class="action-btn-group">
                                <button class="btn btn-light btn-sm" id="quick-deposit-btn" title="Make Deposit">
                                    <i class="mdi mdi-plus-circle text-success"></i> Deposit
                                </button>
                                <button class="btn btn-outline-light btn-sm" id="quick-withdraw-btn" title="Withdraw">
                                    <i class="mdi mdi-minus-circle text-danger"></i> Withdraw
                                </button>
                                <button class="btn btn-outline-light btn-sm" id="quick-adjust-btn" title="Adjustment">
                                    <i class="mdi mdi-swap-horizontal text-info"></i> Adjust
                                </button>
                            </div>
                            <div class="action-btn-group mt-2">
                                <button class="btn btn-warning btn-sm" id="print-statement-btn" title="Print Account Statement">
                                    <i class="mdi mdi-file-document-outline"></i> Print Statement
                                </button>
                                <button class="btn btn-outline-light btn-sm" id="refresh-account-data" title="Refresh">
                                    <i class="mdi mdi-refresh"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Account Stats Dashboard -->
                    <div class="account-stats-grid">
                        <div class="account-stat-card deposits">
                            <div class="stat-icon"><i class="mdi mdi-arrow-down-bold-circle"></i></div>
                            <div class="stat-info">
                                <span class="stat-value" id="total-deposits-stat">₦0</span>
                                <span class="stat-label">Total Deposits</span>
                            </div>
                        </div>
                        <div class="account-stat-card withdrawals">
                            <div class="stat-icon"><i class="mdi mdi-arrow-up-bold-circle"></i></div>
                            <div class="stat-info">
                                <span class="stat-value" id="total-withdrawals-stat">₦0</span>
                                <span class="stat-label">Total Withdrawals</span>
                            </div>
                        </div>
                        <div class="account-stat-card pending">
                            <div class="stat-icon"><i class="mdi mdi-clock-outline"></i></div>
                            <div class="stat-info">
                                <span class="stat-value" id="pending-bills-stat">₦0</span>
                                <span class="stat-label">Pending Bills</span>
                            </div>
                        </div>
                        <div class="account-stat-card transactions">
                            <div class="stat-icon"><i class="mdi mdi-swap-horizontal"></i></div>
                            <div class="stat-info">
                                <span class="stat-value" id="tx-count-stat">0</span>
                                <span class="stat-label">Transactions</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Account State -->
                <div class="account-no-account-state" id="no-account-state" style="display: none;">
                    <div class="no-account-content">
                        <div class="no-account-icon">
                            <i class="mdi mdi-wallet-outline"></i>
                        </div>
                        <h4>No Account Found</h4>
                        <p>This patient doesn't have an account yet. Create one to start tracking deposits and payments.</p>
                        <button class="btn btn-primary btn-lg" id="create-account-btn">
                            <i class="mdi mdi-plus-circle"></i> Create Account
                        </button>
                    </div>
                </div>

                <!-- Account Transaction Panel (Deposit/Withdraw/Adjust) -->
                <div class="account-transaction-panel" id="account-transaction-panel" style="display: none;">
                    <div class="transaction-panel-header" id="transaction-panel-header">
                        <h5><i class="mdi mdi-cash-plus" id="transaction-panel-icon"></i> <span id="transaction-panel-title">Make Deposit</span></h5>
                        <button class="btn btn-sm btn-link" id="close-transaction-panel">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                    <div class="transaction-panel-body">
                        <form id="account-transaction-form" class="transaction-form-inline">
                            <input type="hidden" id="transaction-type" value="deposit">
                            <div class="form-group">
                                <label>Amount</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control form-control-lg" id="transaction-amount" placeholder="0.00" required>
                                </div>
                                <small class="form-text text-muted" id="transaction-amount-help">Enter amount to deposit</small>
                            </div>
                            <div class="form-group" id="transaction-payment-method-group">
                                <label>Payment Method</label>
                                <select class="form-control" id="transaction-payment-method">
                                    <option value="CASH">Cash</option>
                                    <option value="POS">POS/Card</option>
                                    <option value="TRANSFER">Bank Transfer</option>
                                    <option value="MOBILE">Mobile Money</option>
                                </select>
                            </div>
                            <div class="form-group" id="transaction-bank-group" style="display: none;">
                                <label>Select Bank</label>
                                <select class="form-control" id="transaction-bank">
                                    <option value="">-- Select Bank --</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Description <small class="text-muted">(Required for adjustments)</small></label>
                                <input type="text" class="form-control" id="transaction-description" placeholder="e.g., Cash deposit, Refund, Correction, etc.">
                            </div>
                            <div class="transaction-actions">
                                <button type="submit" class="btn btn-block" id="transaction-submit-btn">
                                    <i class="mdi mdi-check"></i> <span id="transaction-submit-text">Confirm Deposit</span>
                                </button>
                            </div>
                        </form>

                        <!-- Balance Preview -->
                        <div class="balance-preview" id="balance-preview">
                            <div class="balance-preview-row">
                                <span>Current Balance:</span>
                                <span id="preview-current-balance">₦0.00</span>
                            </div>
                            <div class="balance-preview-row">
                                <span id="preview-change-label">After Deposit:</span>
                                <span id="preview-new-balance">₦0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaction History Section -->
                <div class="account-transactions-section" id="account-transactions-section">
                    <div class="transactions-section-header">
                        <h5><i class="mdi mdi-history"></i> Account Transactions</h5>
                        <div class="transactions-filters">
                            <div class="filter-group">
                                <input type="date" class="form-control form-control-sm" id="account-tx-from-date">
                            </div>
                            <div class="filter-group">
                                <input type="date" class="form-control form-control-sm" id="account-tx-to-date">
                            </div>
                            <div class="filter-group">
                                <select class="form-control form-control-sm" id="account-tx-type-filter">
                                    <option value="">All Types</option>
                                    <option value="ACC_DEPOSIT">Deposits</option>
                                    <option value="ACC_WITHDRAW">Withdrawals/Payments</option>
                                    <option value="ACC_ADJUSTMENT">Adjustments</option>
                                </select>
                            </div>
                            <button class="btn btn-sm btn-primary" id="filter-account-tx">
                                <i class="mdi mdi-filter"></i> Filter
                            </button>
                        </div>
                    </div>

                    <!-- Transaction Timeline -->
                    <div class="transaction-timeline" id="transaction-timeline">
                        <div class="timeline-empty-state">
                            <i class="mdi mdi-swap-horizontal"></i>
                            <p>No account transactions yet</p>
                            <small>Deposits and withdrawals will appear here</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.payment_modal')
@include('admin.billing.partials._modals')
@endsection

@include('admin.billing.partials._scripts')
