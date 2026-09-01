@extends('admin.layouts.app')

@section('title', 'Reception Workbench')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/reception-workbench.css') }}?v={{ filemtime(public_path('css/reception-workbench.css')) }}">
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/fullcalendar/fullcalendar.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/queue-status.css') }}">
@endpush
@section('content')
@php
    $hosColor = appsettings()->hos_color ?? '#0066cc';
    $sett = appsettings();
@endphp


<div class="reception-workbench-container">
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
            <h6><i class="mdi mdi-format-list-bulleted"></i> DOCTOR QUEUE</h6>
            <div class="queue-item" data-filter="emergency" style="background: #fff5f5; border-left: 3px solid #dc3545;">
                <span class="queue-item-label"><i class="fa fa-bolt text-danger"></i> <strong class="text-danger">Emergency</strong></span>
                <span class="queue-count" id="queue-emergency-count" style="background: #dc3545; color: #fff;">0</span>
            </div>
            <div class="queue-item" data-filter="waiting">
                <span class="queue-item-label"><i class="mdi mdi-clock-outline text-warning"></i> Waiting</span>
                <span class="queue-count all-unpaid" id="queue-waiting-count">0</span>
            </div>
            <div class="queue-item" data-filter="vitals">
                <span class="queue-item-label"><i class="mdi mdi-heart-pulse text-info"></i> Vitals Pending</span>
                <span class="queue-count hmo-items" id="queue-vitals-count">0</span>
            </div>
            <div class="queue-item" data-filter="consultation">
                <span class="queue-item-label"><i class="mdi mdi-doctor text-success"></i> In Consultation</span>
                <span class="queue-count credit-accounts" id="queue-consultation-count">0</span>
            </div>
            <div class="queue-item" data-filter="admitted">
                <span class="queue-item-label"><i class="mdi mdi-bed text-danger"></i> Admitted</span>
                <span class="queue-count all-unpaid" id="queue-admitted-count">0</span>
            </div>
            <div class="queue-item" data-filter="appointments-today" style="border-left: 3px solid #6f42c1;">
                <span class="queue-item-label"><i class="mdi mdi-calendar-check" style="color:#6f42c1;"></i> <strong>Today's Appointments</strong></span>
                <span class="queue-count" id="queue-appointments-count" style="background: #6f42c1; color: #fff;">0</span>
            </div>
            <div class="queue-item" data-filter="referrals" style="border-left: 3px solid #b45309;">
                <span class="queue-item-label"><i class="mdi mdi-account-arrow-right" style="color:#b45309;"></i> <strong>Pending Referrals</strong></span>
                <span class="queue-count" id="queue-referrals-count" style="background: #b45309; color: #fff;">0</span>
            </div>
            <div class="queue-item" id="queue-hmo-pending-item" data-filter="hmo-pending-validation" style="border-left: 3px solid #0d6efd;">
                <span class="queue-item-label"><i class="mdi mdi-shield-check" style="color:#0d6efd;"></i> <strong>HMO Pending Validation</strong></span>
                <span class="queue-count" id="queue-hmo-pending-count" style="background: #0d6efd; color: #fff;">0</span>
            </div>
            <button class="btn-queue-all" id="show-all-queue-btn"><i class="mdi mdi-format-list-bulleted"></i> View Full Queue</button>
        </div>

        <div class="quick-actions">
            <h6><i class="mdi mdi-lightning-bolt"></i> QUICK ACTIONS</h6>
            <button class="quick-action-btn" id="btn-ward-dashboard">
                <i class="mdi mdi-hospital-building text-primary"></i>
                <span>Ward Dashboard</span>
            </button>
            <button class="quick-action-btn" id="btn-new-patient">
                <i class="mdi mdi-account-plus"></i>
                <span>New Patient</span>
            </button>
            <button class="quick-action-btn" id="btn-quick-register">
                <i class="mdi mdi-account-plus-outline text-success"></i>
                <span>Quick Register</span>
            </button>
            <button class="quick-action-btn" id="btn-today-stats">
                <i class="mdi mdi-chart-bar"></i>
                <span>Today's Stats</span>
            </button>
            <button class="quick-action-btn" id="btn-view-reports">
                <i class="mdi mdi-file-chart"></i>
                <span>Reports</span>
            </button>
            @if(appsettings()->enable_ei_reception)
<button class="quick-action-btn" onclick="showEmergencyIntakeModal()">
                <i class="mdi mdi-ambulance text-danger"></i>
                <span>Emergency Intake</span>
            </button>
@endif
            <button class="quick-action-btn" onclick="showMorgueAdmissionModal()">
                <i class="mdi mdi-emoticon-dead text-dark"></i>
                <span>Morgue Admission (BID)</span>
            </button>
            <button class="quick-action-btn" id="btn-hmo-validation" onclick="showHmoValidationPanel()">
                <i class="mdi mdi-shield-check text-primary"></i>
                <span>HMO Validation</span>
            </button>
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
            <i class="mdi mdi-account-search"></i>
            <h3>No Patient Selected</h3>
            <p>Search and select a patient to manage their visit</p>
            <button class="btn btn-lg btn-primary" id="view-queue-btn">
                <i class="mdi mdi-format-list-bulleted"></i> View Today's Queue
            </button>
        </div>

        <!-- Queue View -->
        <div class="queue-view" id="queue-view">
            <div class="queue-view-header">
                <h4 id="queue-view-title"><i class="mdi mdi-format-list-bulleted"></i> Doctor Queue</h4>
                <button class="btn-close-queue" id="btn-close-queue">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content">
                <div class="mb-3">
                    <select class="form-control" id="queue-clinic-filter" style="max-width: 300px;">
                        <option value="">All Clinics</option>
                    </select>
                </div>
                <table class="table" id="queue-datatable" style="width: 100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>File No</th>
                            <th>HMO</th>
                            <th>Clinic</th>
                            <th>Doctor</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

        {{-- ─── HMO Pending Validation Panel ───────────────────────────────── --}}
        <div class="queue-view" id="hmo-validation-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-shield-check" style="color:#0d6efd;"></i> HMO Pending Validation</h4>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-primary" id="btn-hmo-batch-validate" disabled>
                        <i class="mdi mdi-check-all"></i> Validate Selected (<span id="hmo-batch-count">0</span>)
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" id="btn-hmo-refresh-validation">
                        <i class="mdi mdi-refresh"></i>
                    </button>
                    <button class="btn-close-queue" id="btn-close-hmo-validation">
                        <i class="mdi mdi-close"></i> Close
                    </button>
                </div>
            </div>
            <div class="queue-view-content">
                <div class="mb-2 d-flex align-items-center gap-2">
                    <input type="text" class="form-control form-control-sm" id="hmo-validation-search"
                           placeholder="Search patient, file no..." style="max-width: 280px;">
                    <small class="text-muted" id="hmo-validation-total">0 pending</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover" id="hmo-validation-table" style="width:100%;">
                        <thead>
                            <tr>
                                <th style="width:30px;"><input type="checkbox" id="hmo-select-all" style="transform:scale(1.3);cursor:pointer;"></th>
                                <th>Patient</th>
                                <th>HMO</th>
                                <th>Item</th>
                                <th>Coverage</th>
                                <th>HMO Amt</th>
                                <th>Pending</th>
                                <th style="width:140px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="hmo-validation-body">
                            <tr><td colspan="8" class="text-center text-muted py-4"><i class="mdi mdi-loading mdi-spin"></i> Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ─── HMO Validation Confirm Modal ──────────────────────────────── --}}
        <div class="modal fade" id="hmoValidateConfirmModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content" style="border-radius:12px;border:none;">
                    <div class="modal-header py-2" style="background:linear-gradient(135deg,#0d6efd 0%,#0a58ca 100%);color:#fff;border-radius:12px 12px 0 0;">
                        <h5 class="modal-title"><i class="mdi mdi-shield-check"></i> <span id="hvc-title">Confirm Validation</span></h5>
                        <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        {{-- Single request details --}}
                        <div id="hvc-single-details">
                            <table class="table table-sm table-borderless mb-2" style="font-size:0.88rem;">
                                <tr><td class="text-muted" style="width:110px;">Patient</td><td><strong id="hvc-patient"></strong></td></tr>
                                <tr><td class="text-muted">File No</td><td id="hvc-file-no"></td></tr>
                                <tr><td class="text-muted">HMO</td><td id="hvc-hmo"></td></tr>
                                <tr><td class="text-muted">Item</td><td id="hvc-item"></td></tr>
                                <tr><td class="text-muted">Coverage</td><td id="hvc-coverage"></td></tr>
                                <tr><td class="text-muted">HMO Amount</td><td><strong id="hvc-amount" class="text-success"></strong></td></tr>
                            </table>
                            <div class="alert alert-info py-2 mb-2" style="font-size:0.82rem;border-radius:8px;" id="hvc-outcome-info"></div>
                        </div>
                        {{-- Batch summary --}}
                        <div id="hvc-batch-details" style="display:none;">
                            <div class="alert alert-primary py-2 mb-2" style="font-size:0.88rem;border-radius:8px;">
                                <i class="mdi mdi-check-all"></i> You are about to validate <strong id="hvc-batch-total">0</strong> HMO request(s).
                            </div>
                            <div class="mb-2" style="font-size:0.82rem;">
                                <span class="text-muted">Primary requests → </span><span class="badge badge-success">Approved</span><br>
                                <span class="text-muted">Secondary requests → </span><span class="badge" style="background:#7c4dff;color:#fff;">Awaiting Code</span>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label class="small font-weight-bold text-muted">Notes <span class="font-weight-normal">(optional)</span></label>
                            <textarea class="form-control form-control-sm" id="hvc-notes" rows="2" maxlength="500" placeholder="Add a note..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-sm btn-primary" id="hvc-confirm-btn"><i class="mdi mdi-check"></i> Confirm Validation</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─── Appointments Calendar / Table View ─────────────────────────── --}}
        <div class="queue-view" id="appointments-calendar-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-calendar-clock" style="color:#6f42c1;"></i> Appointment Schedule</h4>
                <div class="d-flex align-items-center gap-2">
                    {{-- Calendar/Table toggle --}}
                    <div class="btn-group btn-group-sm mr-3" role="group" id="appt-view-toggle">
                        <button type="button" class="btn btn-outline-purple active" data-view="calendar"><i class="mdi mdi-calendar"></i> Calendar</button>
                        <button type="button" class="btn btn-outline-purple" data-view="table"><i class="mdi mdi-table"></i> Table</button>
                    </div>
                    <button class="btn-close-queue" id="btn-close-appointments-view"><i class="mdi mdi-close"></i> Close</button>
                </div>
            </div>
            <div class="queue-view-content" style="padding: 1rem; overflow-y: auto;">
                {{-- Status legend --}}
                <div class="d-flex flex-wrap gap-2 mb-3 appt-legend">
                    <span><span class="legend-dot" style="background:#6f42c1;"></span> Scheduled</span>
                    <span><span class="legend-dot" style="background:#17a2b8;"></span> Waiting</span>
                    <span><span class="legend-dot" style="background:#ffc107;"></span> Vitals Pending</span>
                    <span><span class="legend-dot" style="background:#28a745;"></span> Ready</span>
                    <span><span class="legend-dot" style="background:#0d6efd;"></span> In Consultation</span>
                    <span><span class="legend-dot" style="background:#198754;"></span> Completed</span>
                    <span><span class="legend-dot" style="background:#dc3545;"></span> Cancelled</span>
                    <span><span class="legend-dot" style="background:#6c757d;"></span> No-Show</span>
                </div>
                {{-- Filters --}}
                <div class="row mb-3">
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="appt-cal-clinic-filter">
                            <option value="">All Clinics</option>
                            @if(isset($clinics))
                                @foreach($clinics as $clinic)
                                    <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="appt-cal-doctor-filter">
                            <option value="">All Doctors</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="appt-cal-status-filter">
                            <option value="">All Statuses</option>
                            <option value="6">Scheduled</option>
                            <option value="1">Waiting</option>
                            <option value="2">Vitals Pending</option>
                            <option value="3">Ready</option>
                            <option value="4">In Consultation</option>
                            <option value="5">Completed</option>
                            <option value="0">Cancelled</option>
                            <option value="7">No-Show</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" id="appt-cal-search" placeholder="Search patient name / file no...">
                    </div>
                </div>

                {{-- Calendar container --}}
                <div id="appointments-calendar-container">
                    <div id="appointments-fullcalendar"></div>
                </div>

                {{-- Table container (hidden by default) --}}
                <div id="appointments-table-container" style="display: none;">
                    <table class="table table-hover table-sm" id="appointments-global-datatable" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Patient</th>
                                <th>File No</th>
                                <th>HMO</th>
                                <th>Clinic</th>
                                <th>Doctor</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Delivery</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

        <!-- Ward Dashboard View -->
        <div class="queue-view" id="ward-dashboard-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-hospital-building"></i> Ward Dashboard</h4>
                <button class="btn btn-secondary btn-close-queue" id="btn-close-ward-dashboard">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content" style="padding: 1rem; overflow-y: auto;">
                @include('admin.partials.ward_dashboard')
            </div>
        </div>

        <!-- Reports View (Full Screen - Global Access) -->
        <div class="queue-view" id="reports-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-chart-box"></i> Reception Reports & Analytics</h4>
                <button class="btn btn-secondary btn-close-queue" id="btn-close-reports">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content" style="padding: 1.5rem; overflow-y: auto;">
                <!-- Filter Panel -->
                <div class="reports-filter-panel card-modern mb-4">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="mdi mdi-filter"></i> Filters</h6>
                    </div>
                    <div class="card-body py-3">
                        <form id="reports-filter-form">
                            <div class="row">
                                <div class="form-group col-md-2">
                                    <label for="report-date-from" class="small mb-1">Date From</label>
                                    <input type="date" class="form-control form-control-sm" id="report-date-from" name="date_from">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="report-date-to" class="small mb-1">Date To</label>
                                    <input type="date" class="form-control form-control-sm" id="report-date-to" name="date_to">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="report-type-filter" class="small mb-1">Report Type</label>
                                    <select class="form-control form-control-sm" id="report-type-filter" name="report_type">
                                        <option value="">All Activity</option>
                                        <option value="registrations">New Registrations</option>
                                        <option value="queue">Queue Entries</option>
                                        <option value="visits">Completed Visits</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="report-clinic-filter" class="small mb-1">Clinic</label>
                                    <select class="form-control form-control-sm" id="report-clinic-filter" name="clinic_id">
                                        <option value="">All Clinics</option>
                                        @foreach(\App\Models\Clinic::where('status', 1)->orderBy('name')->get() as $clinic)
                                            <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="report-hmo-filter" class="small mb-1">HMO</label>
                                    <select class="form-control form-control-sm" id="report-hmo-filter" name="hmo_id">
                                        <option value="">All HMOs</option>
                                        @foreach(\App\Models\Hmo::where('status', 1)->orderBy('name')->get() as $hmo)
                                            <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="report-patient-search" class="small mb-1">Patient Search</label>
                                    <input type="text" class="form-control form-control-sm" id="report-patient-search" name="patient_search" placeholder="File no or name...">
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-12 text-right">
                                    <button type="button" class="btn btn-sm btn-secondary" id="clear-report-filters">
                                        <i class="mdi mdi-refresh"></i> Clear
                                    </button>
                                    <button type="submit" class="btn btn-sm btn-primary">
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
                        <a class="nav-link active" id="overview-tab" data-toggle="tab" href="#overview-content" role="tab">
                            <i class="mdi mdi-view-dashboard"></i> Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="registrations-tab" data-toggle="tab" href="#registrations-content" role="tab">
                            <i class="mdi mdi-account-plus"></i> Registrations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="queue-report-tab" data-toggle="tab" href="#queue-report-content" role="tab">
                            <i class="mdi mdi-format-list-bulleted"></i> Queue Report
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="visits-tab" data-toggle="tab" href="#visits-content" role="tab">
                            <i class="mdi mdi-calendar-check"></i> Visits
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="clinical-reports-tab" data-toggle="tab" href="#clinical-reports-content" role="tab">
                            <i class="mdi mdi-chart-timeline-variant"></i> Clinical Reports
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="reports-tab-content">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview-content" role="tabpanel">
                        <div class="reports-container">
                            <!-- Summary Statistics Cards -->
                            <div class="row mb-4">
                                <div class="col-md-2 col-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                            <i class="mdi mdi-account-plus"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-new-registrations">0</h3>
                                            <p>New Registrations</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                            <i class="mdi mdi-format-list-bulleted"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-total-queued">0</h3>
                                            <p>Total Queued</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                            <i class="mdi mdi-check-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-completed-visits">0</h3>
                                            <p>Completed Visits</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                                            <i class="mdi mdi-clock-outline"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-pending-queue">0</h3>
                                            <p>Pending in Queue</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                                            <i class="mdi mdi-timer"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-avg-wait-time">0m</h3>
                                            <p>Avg Wait Time</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="stat-card">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                            <i class="mdi mdi-refresh"></i>
                                        </div>
                                        <div class="stat-content">
                                            <h3 id="stat-return-rate">0%</h3>
                                            <p>Return Visits</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Charts -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header py-2">
                                            <h6 class="mb-0"><i class="mdi mdi-chart-bar"></i> Registrations Trend</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="registrations-chart" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header py-2">
                                            <h6 class="mb-0"><i class="mdi mdi-chart-pie"></i> HMO Distribution</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="hmo-distribution-chart" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Top Clinics & Peak Hours -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header py-2">
                                            <h6 class="mb-0"><i class="mdi mdi-hospital-building"></i> Top Clinics</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0" id="top-clinics-table">
                                                    <thead class="bg-light">
                                                        <tr>
                                                            <th>Clinic</th>
                                                            <th class="text-center">Visits</th>
                                                            <th class="text-right">%</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="top-clinics-body">
                                                        <tr><td colspan="3" class="text-center text-muted">Loading...</td></tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header py-2">
                                            <h6 class="mb-0"><i class="mdi mdi-clock"></i> Peak Hours</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="peak-hours-chart" height="180"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Registrations Tab -->
                    <div class="tab-pane fade" id="registrations-content" role="tabpanel">
                        <div class="card-modern">
                            <div class="card-header d-flex justify-content-between align-items-center py-2">
                                <h6 class="mb-0"><i class="mdi mdi-account-plus"></i> Patient Registrations</h6>
                                <div>
                                    <button class="btn btn-sm btn-success" id="export-registrations-excel">
                                        <i class="mdi mdi-file-excel"></i> Excel
                                    </button>
                                    <button class="btn btn-sm btn-info" id="print-registrations">
                                        <i class="mdi mdi-printer"></i> Print
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm" id="registrations-datatable" style="width: 100%">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>File No</th>
                                                <th>Patient Name</th>
                                                <th>Gender</th>
                                                <th>Age</th>
                                                <th>Phone</th>
                                                <th>HMO</th>
                                                <th>Registered By</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Queue Report Tab -->
                    <div class="tab-pane fade" id="queue-report-content" role="tabpanel">
                        <div class="card-modern">
                            <div class="card-header d-flex justify-content-between align-items-center py-2">
                                <h6 class="mb-0"><i class="mdi mdi-format-list-bulleted"></i> Queue Entries</h6>
                                <div>
                                    <button class="btn btn-sm btn-success" id="export-queue-excel">
                                        <i class="mdi mdi-file-excel"></i> Excel
                                    </button>
                                    <button class="btn btn-sm btn-info" id="print-queue">
                                        <i class="mdi mdi-printer"></i> Print
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm" id="queue-report-datatable" style="width: 100%">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>File No</th>
                                                <th>Patient</th>
                                                <th>Clinic</th>
                                                <th>Doctor</th>
                                                <th>Service</th>
                                                <th>Status</th>
                                                <th>Wait Time</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Visits Tab -->
                    <div class="tab-pane fade" id="visits-content" role="tabpanel">
                        <div class="card-modern">
                            <div class="card-header d-flex justify-content-between align-items-center py-2">
                                <h6 class="mb-0"><i class="mdi mdi-calendar-check"></i> Visit History</h6>
                                <div>
                                    <button class="btn btn-sm btn-success" id="export-visits-excel">
                                        <i class="mdi mdi-file-excel"></i> Excel
                                    </button>
                                    <button class="btn btn-sm btn-info" id="print-visits">
                                        <i class="mdi mdi-printer"></i> Print
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-sm" id="visits-datatable" style="width: 100%">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>File No</th>
                                                <th>Patient</th>
                                                <th>Clinic</th>
                                                <th>Doctor</th>
                                                <th>Reason</th>
                                                <th>HMO</th>
                                                <th>Type</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    @include('admin.partials.clinical-reports-panel')

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
                    <div class="patient-allergies" id="patient-allergies" style="display: none;">
                        <span class="allergy-alert-badge"><i class="mdi mdi-alert"></i> Allergies: <span id="allergy-list"></span></span>
                    </div>
                </div>
                <div class="d-flex flex-column align-items-end gap-2">
                    <div class="patient-account-balance" id="patient-header-balance" style="display: none;">
                        <div class="balance-label">Account Balance</div>
                        <div class="balance-value" id="header-balance-amount">₦0.00</div>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-light" id="btn-edit-patient" title="Edit Patient">
                            <i class="mdi mdi-pencil"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-info" id="btn-print-card" title="Print Hospital Card">
                            <i class="mdi mdi-card-account-details"></i> Print Card
                        </button>
                        <button class="btn btn-sm btn-outline-dark" id="btn-medical-reports" title="Medical Reports" style="display:none;">
                            <i class="mdi mdi-file-document-multiple"></i> Medical Reports
                        </button>
                        <button class="btn-expand-patient d-inline-flex" id="btn-expand-patient" title="Show more details">
                            <span class="btn-expand-text">more biodata</span>
                            <i class="mdi mdi-chevron-down ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="patient-details-expanded" id="patient-details-expanded">
                <div class="patient-details-grid" id="patient-details-grid"></div>
            </div>
        </div>

        <!-- Workspace Content -->
        <div class="workspace-content" id="workspace-content">
            <div class="workspace-tabs">
                <button class="workspace-tab active" data-tab="profile">
                    <i class="mdi mdi-account-card-details"></i>
                    <span>Profile</span>
                </button>
                <button class="workspace-tab" data-tab="booking">
                    <i class="mdi mdi-calendar-plus"></i>
                    <span>Book Service</span>
                </button>
                <button class="workspace-tab" data-tab="walkin">
                    <i class="mdi mdi-cart-plus"></i>
                    <span>Walk-in Sales</span>
                </button>
                <button class="workspace-tab" data-tab="history">
                    <i class="mdi mdi-history"></i>
                    <span>Visit History</span>
                </button>
                <button class="workspace-tab" data-tab="requests">
                    <i class="mdi mdi-clipboard-list"></i>
                    <span>Service Requests</span>
                </button>
                <button class="workspace-tab" data-tab="appointments">
                    <i class="mdi mdi-calendar-clock" style="color:#6f42c1;"></i>
                    <span>Appointments</span>
                </button>
                <button class="workspace-tab" data-tab="admissions">
                    <i class="mdi mdi-hospital-building"></i>
                    <span>Admissions</span>
                    <span class="badge badge-dark ml-1" id="reception-admissions-badge" style="display:none;">0</span>
                </button>
            </div>

            <!-- Profile Tab -->
            <div class="workspace-tab-content active" id="profile-tab">
                <div class="profile-tab-content p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card-modern">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="mdi mdi-account"></i> Patient Information</h5>
                                </div>
                                <div class="card-body" id="profile-info-card">
                                    <table class="table table-sm table-borderless">
                                        <tbody id="profile-info-table"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card-modern mb-3">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="mdi mdi-hospital-building"></i> HMO / Insurance</h5>
                                </div>
                                <div class="card-body" id="profile-hmo-card">
                                    <table class="table table-sm table-borderless">
                                        <tbody id="profile-hmo-table"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-modern">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="mdi mdi-calendar-clock"></i> Current Queue</h5>
                                </div>
                                <div class="card-body" id="profile-queue-card">
                                    <div id="current-queue-entries">
                                        <p class="text-muted">No active queue entries</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Book Service Tab -->
            <div class="workspace-tab-content" id="booking-tab">
                <div class="booking-tab-content p-4">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="card-modern">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="mdi mdi-calendar-plus"></i> Book Consultation</h5>
                                </div>
                                <div class="card-body">
                                    <form id="booking-form">
                                        <div id="family-booking-container" class="mb-4 row">
                                            <!-- Dynamically populated family members grid will go here -->
                                        </div>

                                        <!-- Appointment Type Toggle -->
                                        <div class="form-group mb-3 mt-4 border-top pt-3">
                                            <label class="d-block mb-2"><i class="mdi mdi-calendar-clock"></i> Appointment Type (Applies to all selected)</label>
                                            <div class="btn-group w-100" role="group">
                                                <input type="radio" class="btn-check" name="booking_type" id="booking-type-walkin" value="walkin" checked autocomplete="off">
                                                <label class="btn btn-outline-primary" for="booking-type-walkin"><i class="mdi mdi-walk"></i> Walk-in (Now)</label>
                                                <input type="radio" class="btn-check" name="booking_type" id="booking-type-schedule" value="schedule" autocomplete="off">
                                                <label class="btn btn-outline-purple" for="booking-type-schedule"><i class="mdi mdi-calendar-plus"></i> Schedule Future</label>
                                            </div>
                                        </div>

                                        <!-- Schedule Fields (hidden by default) -->
                                        <div id="schedule-fields" style="display:none;">
                                            <div class="card-modern border-purple mb-3 p-3">
                                                <div class="row">
                                                    <div class="col-md-6 mb-2">
                                                        <label><i class="mdi mdi-calendar"></i> Date <span class="text-danger">*</span></label>
                                                        <input type="date" class="form-control" id="booking-appointment-date" min="{{ date('Y-m-d') }}">
                                                    </div>
                                                    <div class="col-md-6 mb-2">
                                                        <label><i class="mdi mdi-clock-outline"></i> Time Slot <span class="text-danger">*</span></label>
                                                        <select class="form-control" id="booking-appointment-time" style="display:block;">
                                                            <option value="">-- Select date first --</option>
                                                        </select>
                                                        <input type="time" class="form-control mt-1" id="booking-appointment-time-manual" style="display:none;" placeholder="HH:MM">
                                                        <div class="form-check mt-1">
                                                            <input class="form-check-input" type="checkbox" id="booking-custom-time-toggle">
                                                            <label class="form-check-label text-muted small" for="booking-custom-time-toggle">Enter custom time</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group mb-2">
                                                    <label><i class="mdi mdi-priority-high"></i> Priority</label>
                                                    <select class="form-control" id="booking-priority">
                                                        <option value="routine">Routine</option>
                                                        <option value="urgent">Urgent</option>
                                                        <option value="emergency">Emergency</option>
                                                    </select>
                                                </div>
                                                <div class="form-group mb-0">
                                                    <label><i class="mdi mdi-note-text-outline"></i> Notes</label>
                                                    <textarea class="form-control" id="booking-appointment-notes" rows="2" placeholder="Optional notes for the doctor or scheduler"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-check mb-3">
                                            <input type="checkbox" class="form-check-input" id="force_rebill">
                                            <label class="form-check-label" for="force_rebill">
                                                Force Re-bill Consultation (ignore cycle duration limits for all selected)
                                            </label>
                                        </div>
                                        @hasanyrole('SUPERADMIN|ADMIN|ACCOUNTS|BILLER')
                                        <button type="submit" class="btn btn-success w-100" id="btn-book-consultation">
                                            <i class="mdi mdi-cart-check"></i> Checkout & Book
                                        </button>
                                        @else
                                        <button type="submit" class="btn btn-primary w-100" id="btn-book-consultation">
                                            <i class="mdi mdi-check-circle"></i> Book Service
                                        </button>
                                        @endhasanyrole
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="card-modern" id="tariff-preview-card" style="display: none;">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="mdi mdi-cart"></i> Booking Cart</h5>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Patient</th>
                                                <th>Service</th>
                                                <th class="text-right">Price</th>
                                            </tr>
                                        </thead>
                                        <tbody id="cart-preview-body">
                                            <!-- Dynamically filled by JS -->
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-primary">
                                                <td colspan="2"><strong>Total Payable:</strong></td>
                                                <td class="text-right"><strong id="cart-preview-total">₦0</strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="alert alert-info" id="tariff-validation-alert" style="display: none;">
                                <i class="mdi mdi-information"></i> <span id="tariff-validation-message"></span>
                            </div>
                            <div class="card-modern mt-3">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0"><i class="mdi mdi-queue-first-in-last-out"></i> Current Queue</h5>
                                </div>
                                <div class="card-body">
                                    <div id="booking-current-queue">
                                        <p class="text-muted">No active queue entries</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Walk-in Sales Tab -->
            <div class="workspace-tab-content" id="walkin-tab">
                <div class="walkin-tab-content p-4">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="card-modern">
                                <div class="card-header">
                                    <ul class="nav nav-pills card-header-pills" id="walkin-subtabs">
                                        <li class="nav-item">
                                            <a class="nav-link active" href="#walkin-lab" data-toggle="pill" data-type="lab">
                                                <i class="mdi mdi-test-tube"></i> Lab
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" href="#walkin-imaging" data-toggle="pill" data-type="imaging">
                                                <i class="mdi mdi-x-ray"></i> Imaging
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" href="#walkin-product" data-toggle="pill" data-type="product">
                                                <i class="mdi mdi-pill"></i> Products
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-body">
                                    <div class="form-group mb-3">
                                        <input type="text" class="form-control" id="walkin-search"
                                            placeholder="🔍 Search services/products...">
                                    </div>
                                    <div class="walkin-search-results" id="walkin-search-results" style="max-height: 300px; overflow-y: auto;">
                                        <p class="text-muted text-center">Type to search...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="card-modern">
                                <!-- Cart Sub-Tabs -->
                                <div class="card-header p-0">
                                    <ul class="nav nav-tabs nav-fill" id="walkin-cart-tabs">
                                        <li class="nav-item">
                                            <a class="nav-link active" data-toggle="tab" href="#walkin-cart-pane">
                                                <i class="mdi mdi-cart"></i> Cart
                                                <span class="badge badge-primary ml-1" id="cart-count-badge">0</span>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#walkin-recent-pane">
                                                <i class="mdi mdi-clock-outline"></i> Recent (24h)
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="tab-content">
                                    <!-- Cart Tab Pane -->
                                    <div class="tab-pane fade show active" id="walkin-cart-pane">
                                        <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                                            <table class="table table-sm mb-0" id="walkin-cart-table">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th>Item</th>
                                                        <th class="text-right">Price</th>
                                                        <th class="text-right text-success">HMO Covers</th>
                                                        <th class="text-right text-primary">You Pay</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="walkin-cart-body">
                                                    <tr id="walkin-cart-empty">
                                                        <td colspan="5" class="text-center text-muted py-4">
                                                            <i class="mdi mdi-cart-outline" style="font-size: 2rem;"></i>
                                                            <p class="mb-0 mt-2">No items selected</p>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <!-- Payment Summary -->
                                        <div class="border-top">
                                            <table class="table table-sm mb-0" id="walkin-summary-table">
                                                <tbody>
                                                    <tr class="bg-light">
                                                        <td colspan="2"><strong>Subtotal (Original Prices):</strong></td>
                                                        <td class="text-right" colspan="3"><strong id="walkin-subtotal">₦0</strong></td>
                                                    </tr>
                                                    <tr id="walkin-hmo-row" style="display: none;" class="bg-success-light">
                                                        <td colspan="2">
                                                            <span class="text-success">
                                                                <i class="mdi mdi-shield-check"></i> <strong>Total HMO Coverage</strong>
                                                            </span>
                                                            <small class="d-block" id="walkin-hmo-name"></small>
                                                        </td>
                                                        <td class="text-right text-success" colspan="3"><strong id="walkin-hmo-amount">-₦0</strong></td>
                                                    </tr>
                                                    <tr class="table-primary">
                                                        <td colspan="2"><strong style="font-size: 1.1rem;">Patient Pays:</strong></td>
                                                        <td class="text-right" colspan="3"><strong style="font-size: 1.1rem;" id="walkin-cart-total">₦0</strong></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <div class="p-3">
                                                <button class="btn btn-success btn-lg w-100" id="btn-submit-walkin" disabled>
                                                    <i class="mdi mdi-send"></i> Create Request (Awaiting Billing)
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Recent Requests Tab Pane -->
                                    <div class="tab-pane fade" id="walkin-recent-pane">
                                        <div class="card-body p-2" style="max-height: 400px; overflow-y: auto;">
                                            <div class="alert alert-info py-2 px-3 mb-2">
                                                <small><i class="mdi mdi-information"></i> Requests created in the last 24 hours for this patient</small>
                                            </div>
                                            <div id="recent-requests-container">
                                                <div class="text-center text-muted py-4">
                                                    <i class="mdi mdi-clock-outline" style="font-size: 2rem;"></i>
                                                    <p class="mb-0 mt-2">No recent requests</p>
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

            <!-- Visit History Tab -->
            <div class="workspace-tab-content" id="history-tab">
                <div class="history-tab-content p-4">
                    <div class="card-modern">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-history"></i> Visit History</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-hover" id="visit-history-table" style="width: 100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Doctor</th>
                                        <th>Service</th>
                                        <th>Reason</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Requests Tab -->
            <div class="workspace-tab-content" id="requests-tab">
                <div class="requests-tab-content p-4">
                    <!-- Summary Stats -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-6">
                            <div class="stat-card-modern">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <i class="mdi mdi-clipboard-list text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="stat-value" id="req-total-requests">0</div>
                                        <div class="stat-label">Total Requests</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card-modern">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                        <i class="mdi mdi-shield-check text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="stat-value" id="req-hmo-covered">₦0</div>
                                        <div class="stat-label">HMO Covered</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card-modern">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                        <i class="mdi mdi-cash text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="stat-value" id="req-patient-payable">₦0</div>
                                        <div class="stat-label">Patient Payable</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-card-modern">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                        <i class="mdi mdi-check-circle text-white"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="stat-value" id="req-completed-count">0</div>
                                        <div class="stat-label">Completed</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card-modern mb-4">
                        <div class="card-header py-2">
                            <h6 class="mb-0"><i class="mdi mdi-filter"></i> Filters</h6>
                        </div>
                        <div class="card-body py-3">
                            <form id="service-requests-filter-form">
                                <div class="row">
                                    <div class="form-group col-md-2">
                                        <label class="small mb-1">Date From</label>
                                        <input type="date" class="form-control form-control-sm" id="req-date-from">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label class="small mb-1">Date To</label>
                                        <input type="date" class="form-control form-control-sm" id="req-date-to">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label class="small mb-1">Request Type</label>
                                        <select class="form-control form-control-sm" id="req-type-filter">
                                            <option value="">All Types</option>
                                            <option value="consultation">Consultation</option>
                                            <option value="lab">Lab Test</option>
                                            <option value="imaging">Imaging</option>
                                            <option value="product">Product/Drug</option>
                                            <option value="procedure">Procedure</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label class="small mb-1">Billing Status</label>
                                        <select class="form-control form-control-sm" id="req-billing-filter">
                                            <option value="">All Status</option>
                                            <option value="pending">Pending Billing</option>
                                            <option value="billed">Billed</option>
                                            <option value="paid">Paid</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label class="small mb-1">Delivery Status</label>
                                        <select class="form-control form-control-sm" id="req-delivery-filter">
                                            <option value="">All Status</option>
                                            <option value="pending">Pending</option>
                                            <option value="in_progress">In Progress</option>
                                            <option value="completed">Completed</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-2 d-flex align-items-end">
                                        <button type="button" class="btn btn-sm btn-secondary mr-2" id="clear-req-filters">
                                            <i class="mdi mdi-refresh"></i>
                                        </button>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="mdi mdi-filter"></i> Apply
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Requests DataTable -->
                    <div class="card-modern">
                        <div class="card-header d-flex justify-content-between align-items-center py-2">
                            <h6 class="mb-0"><i class="mdi mdi-clipboard-list"></i> Service Requests</h6>
                            <div>
                                <button class="btn btn-sm btn-success" id="export-requests-excel">
                                    <i class="mdi mdi-file-excel"></i> Excel
                                </button>
                                <button class="btn btn-sm btn-danger" id="export-requests-pdf">
                                    <i class="mdi mdi-file-pdf"></i> PDF
                                </button>
                                <button class="btn btn-sm btn-info" id="print-requests">
                                    <i class="mdi mdi-printer"></i> Print
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm" id="service-requests-datatable" style="width: 100%">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Request #</th>
                                            <th>Type</th>
                                            <th>Service/Item</th>
                                            <th class="text-right">Price</th>
                                            <th class="text-right">HMO Covers</th>
                                            <th class="text-right">Payable</th>
                                            <th>Billing</th>
                                            <th>Delivery</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Appointments Tab -->
            <div class="workspace-tab-content" id="appointments-tab">
                <div class="appointments-tab-content p-4">
                    <div class="card-modern">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="mdi mdi-calendar-clock" style="color:#6f42c1;"></i> Patient Appointments</h5>
                            <button class="btn btn-sm btn-purple" id="btn-new-appointment-from-tab">
                                <i class="mdi mdi-calendar-plus"></i> New Appointment
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm" id="patient-appointments-datatable" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Clinic</th>
                                            <th>Doctor</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Delivery</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admissions Tab (Reusable Module) -->
            <div class="workspace-tab-content" id="admissions-tab">
                @include('admin.partials.admissions-module')
            </div>
        </div>
    </div>
</div>

@include("admin.reception.partials._modals")
@endsection

@include("admin.reception.partials._scripts")
