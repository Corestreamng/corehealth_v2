@extends('admin.layouts.app')

@section('title', 'Nursing Workbench')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/nursing-workbench.css') }}?v={{ filemtime(public_path('css/nursing-workbench.css')) }}">
    
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/clinical-orders-shared.css') }}">
<link rel="stylesheet" href="{{ asset('css/queue-status.css') }}">
<link rel="stylesheet" href="{{ asset('css/billing-shared.css') }}">
@endpush

@section('content')
    @include('admin.partials.procedure_outcome_modal')
@php
    $hosColor = appsettings()->hos_color ?? '#0066cc';
    $sett = appsettings();
@endphp


{{-- Store Context Banner — outside flex container so it spans full width --}}
@if($resolvedStore)
<div class="px-3 pt-2 pb-0">
    <div class="alert alert-info py-1 px-3 d-flex align-items-center gap-2 mb-1" id="store-context-badge">
        <i class="fas fa-store-alt me-1"></i>
        <strong>Active Store:</strong>
        <span class="ms-1">{{ $resolvedStore->store_name }}</span>
        <span class="badge bg-secondary ms-1 text-uppercase" style="font-size:0.7rem;">
            {{ $resolvedStore->distributionRoleLabel() }}
        </span>
        @can('store-context.change-manual')
        <button class="btn btn-outline-secondary btn-sm ms-auto py-0 px-2" onclick="openStoreContextOverride()" title="Change active store context">
            <i class="fas fa-exchange-alt"></i> Change
        </button>
        @endcan
    </div>
</div>
@elseif($contextFallbackAction === 'block')
<div class="px-3 pt-2 pb-0">
    <div class="alert alert-danger py-2 px-3 d-flex align-items-center gap-2 mb-1" id="store-context-banner">
        <i class="fas fa-exclamation-triangle me-1"></i>
        <strong>No ward store resolved.</strong>
        <span class="ms-1">Administration from ward stock is blocked. Start a shift or contact admin.</span>
    </div>
</div>
@elseif($contextFallbackAction === 'allow_manual')
<div class="px-3 pt-2 pb-0">
    <div class="alert alert-warning py-2 px-3 d-flex align-items-center gap-2 mb-1" id="store-context-banner">
        <i class="fas fa-question-circle me-1"></i>
        <strong>No store auto-resolved.</strong>
        <span class="ms-1">Select a store manually for ward stock actions.</span>
    </div>
</div>
@endif

<div class="nursing-workbench-container">

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
            <h6><i class="mdi mdi-clipboard-list"></i> PATIENT QUEUES</h6>
            <div class="queue-item" data-filter="admitted">
                <span class="queue-item-label"><i class="mdi mdi-bed"></i> Admitted Patients</span>
                <span class="queue-count billing" id="queue-admitted-count">0</span>
            </div>
            <div class="queue-item" data-filter="vitals">
                <span class="queue-item-label"><i class="mdi mdi-heart-pulse"></i> Vitals Queue</span>
                <span class="queue-count sample" id="queue-vitals-count">0</span>
            </div>
            <div class="queue-item" data-filter="bed-requests">
                <span class="queue-item-label"><i class="mdi mdi-bed-empty"></i> Bed Requests</span>
                <span class="queue-count info" id="queue-bed-count">0</span>
            </div>
            <div class="queue-item" data-filter="discharge-requests">
                <span class="queue-item-label"><i class="mdi mdi-account-minus"></i> Discharge Requests</span>
                <span class="queue-count results" id="queue-discharge-count">0</span>
            </div>
            <div class="queue-item" data-filter="medication-due">
                <span class="queue-item-label"><i class="mdi mdi-pill"></i> Medication Due</span>
                <span class="queue-count results" id="queue-medication-count">0</span>
            </div>
            <div class="queue-item" data-filter="emergency" style="border-left: 3px solid #dc3545;">
                <span class="queue-item-label"><i class="mdi mdi-ambulance"></i> Emergency Queue</span>
                <span class="queue-count" id="queue-emergency-count" style="background: #dc3545; color: #fff;">0</span>
            </div>
            <div class="queue-item" data-filter="deceased" style="border-left: 3px solid #6c757d;">
                <span class="queue-item-label"><i class="mdi mdi-emoticon-dead-outline"></i> Deceased (Last Office)</span>
                <span class="queue-count" id="queue-deceased-count" style="background: #6c757d; color: #fff;">0</span>
            </div>
            <button class="btn-queue-all" id="refresh-queues-btn">
                <i class="mdi mdi-refresh"></i> Refresh Queues
            </button>
        </div>

        <div class="quick-actions">
            <h6><i class="mdi mdi-lightning-bolt"></i> QUICK ACTIONS</h6>

            <!-- Ward & Bed Management -->
            <button class="quick-action-btn" id="btn-ward-dashboard">
                <i class="mdi mdi-hospital-building text-primary"></i>
                <span>Ward Dashboard</span>
            </button>

            <!-- Quick Vitals (opens modal for fast entry) -->
            <button class="quick-action-btn" id="btn-quick-vitals" disabled title="Select a patient first">
                <i class="mdi mdi-heart-pulse text-danger"></i>
                <span>Quick Vitals</span>
            </button>

            <!-- Medication Rounds -->
            <button class="quick-action-btn" data-filter="medication-due">
                <i class="mdi mdi-pill text-warning"></i>
                <span>Medication Round</span>
                <span class="badge bg-danger ms-auto" id="med-round-badge" style="display: none;">0</span>
            </button>

            <!-- Shift Handover -->
            <button class="quick-action-btn" id="btn-shift-handover">
                <i class="mdi mdi-clipboard-text text-info"></i>
                <span>Shift Handover</span>
            </button>

            <!-- Nursing Reports -->
            <button class="quick-action-btn" id="btn-nursing-reports">
                <i class="mdi mdi-chart-box-outline text-success"></i>
                <span>Nursing Reports</span>
            </button>

            <!-- Admission/Discharge Summary -->
            <button class="quick-action-btn" id="btn-admission-summary">
                <i class="mdi mdi-account-switch text-secondary"></i>
                <span>Admissions Today</span>
            </button>
            @if(appsettings()->enable_ei_nursing)
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
                <button class="btn-clinical-context" id="btn-clinical-context" disabled title="Select a patient first">
                    <i class="fa fa-heartbeat"></i> Clinical Context
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <div class="empty-state" id="empty-state">
            <i class="fa fa-user-nurse"></i>
            <h3>Select a patient to begin</h3>
            <p>Use the search box or select from patient queues</p>
            <button class="btn btn-lg btn-primary" id="view-queue-btn">
                📋 View All Pending Requests
            </button>
        </div>

        <!-- Queue View -->
        @include('admin.nursing.partials.views._queue_view')

        <!-- Reports View (Full Screen - Global Access) -->
        @include('admin.nursing.partials.views._reports_view')

        <!-- Nursing Reports View -->
        @include('admin.nursing.partials.views._nursing_reports_view')

        <!-- Ward Dashboard View -->
        @include('admin.nursing.partials.views._ward_dashboard_view')

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
                <button class="workspace-tab active" data-tab="overview">
                    <i class="mdi mdi-account-details"></i>
                    <span>Overview</span>
                </button>
                <button class="workspace-tab" data-tab="clinical-story">
                    <i class="fa fa-history"></i>
                    <span>Clinical Story</span>
                </button>
                <button class="workspace-tab" data-tab="notes">
                    <i class="mdi mdi-note-text"></i>
                    <span>Nursing Notes</span>
                </button>
                <button class="workspace-tab" data-tab="vitals">
                    <i class="mdi mdi-heart-pulse"></i>
                    <span>Vitals</span>
                </button>
                <button class="workspace-tab" data-tab="medication">
                    <i class="mdi mdi-pill"></i>
                    <span>Medication Chart</span>
                </button>
                <button class="workspace-tab" data-tab="intake-output">
                    <i class="mdi mdi-water"></i>
                    <span>I/O Chart</span>
                </button>
                <button class="workspace-tab" data-tab="injection">
                    <i class="mdi mdi-needle"></i>
                    <span>Injections</span>
                </button>
                <button class="workspace-tab" data-tab="immunization">
                    <i class="mdi mdi-shield-check"></i>
                    <span>Immunization</span>
                </button>
                <button class="workspace-tab" data-tab="procedures">
                    <i class="mdi mdi-medical-bag"></i>
                    <span>Procedures</span>
                </button>
                <button class="workspace-tab" data-tab="clinical-requests">
                    <i class="mdi mdi-clipboard-pulse"></i>
                    <span>Clinical Requests</span>
                </button>
                <button class="workspace-tab" data-tab="billing">
                    <i class="mdi mdi-cash-register"></i>
                    <span>Billing</span>
                </button>
            </div>

            <!-- Overview Tab -->
            @include('admin.nursing.partials.tabs._overview_tab')

            <!-- Clinical Story Tab -->
            @include('admin.nursing.partials.tabs._clinical_story_tab')

            <!-- Vitals Tab -->
            <div class="workspace-tab-content" id="vitals-tab">
                <div class="vitals-container p-3">
                    @include('admin.partials.unified_vitals', ['patient' => $currentPatient ?? null])
                </div>
            </div>

            <!-- Medication Chart Tab -->
            <div class="workspace-tab-content" id="medication-tab">
                <div class="medication-container p-3">
                    <div id="medication-chart-content">
                        @include('admin.patients.partials.nurse_chart_medication_enhanced', ['patient' => $currentPatient ?? null])
                    </div>
                </div>
            </div>

            <!-- I/O Chart Tab -->
            <div class="workspace-tab-content" id="intake-output-tab">
                <div class="io-container p-3">
                    <div id="io-chart-content">
                        @include('admin.patients.partials.nurse_chart_intake_output', ['patient' => $currentPatient ?? null])
                    </div>
                </div>
            </div>

            <!-- Injection Service Tab -->
            <div class="workspace-tab-content" id="injection-tab">
                <div class="injection-container p-3">
                    <!-- Sub-tabs for Injection -->
                    <ul class="nav nav-tabs mb-3" id="injection-sub-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="injection-administer-tab" data-toggle="tab" href="#injection-administer" role="tab">
                                <i class="mdi mdi-plus-circle"></i> Administer
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="injection-history-tab" data-toggle="tab" href="#injection-history" role="tab">
                                <i class="mdi mdi-history"></i> History
                            </a>
                        </li>
                    </ul>

                    @include('admin.nursing.partials.tabs._injection_sub_content')
                </div>
            </div>

            <!-- Immunization Tab -->
            <div class="workspace-tab-content" id="immunization-tab">
                <div class="immunization-container p-3">
                    <!-- Sub-tabs for Immunization - Redesigned UX -->
                    <ul class="nav nav-tabs mb-3" id="immunization-sub-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="immunization-schedule-tab" data-toggle="tab" href="#immunization-schedule" role="tab">
                                <i class="mdi mdi-calendar-check"></i> Schedules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="immunization-history-tab" data-toggle="tab" href="#immunization-history" role="tab">
                                <i class="mdi mdi-history"></i> History & Timeline
                            </a>
                        </li>
                    </ul>

                    @include('admin.nursing.partials.tabs._immunization_sub_content')
                </div>
            </div>

            <!-- Administer Vaccine Modal -->
            @include('admin.partials.administer_vaccine_modal')

            <!-- Procedures Tab -->
            @include('admin.nursing.partials.tabs._procedures_tab')

            <!-- Clinical Requests Tab -->
            <div class="workspace-tab-content" id="clinical-requests-tab">
                <div class="clinical-requests-container p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0"><i class="mdi mdi-clipboard-pulse"></i> Clinical Requests</h4>
                        <span class="badge bg-info" id="cr-patient-badge">No patient selected</span>
                    </div>

                    <!-- Sub-tabs -->
                    <ul class="nav nav-tabs service-tabs mb-3" id="clinical-requests-sub-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="cr-prescriptions-tab" data-bs-toggle="tab" data-bs-target="#cr-prescriptions" type="button" role="tab">
                                <i class="mdi mdi-pill"></i> Drug Prescription
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cr-non-pharm-tab" data-bs-toggle="tab" data-bs-target="#cr-non-pharm" type="button" role="tab">
                                <i class="fa fa-heartbeat"></i> Care Plan / Non-Pharm
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cr-lab-tab" data-bs-toggle="tab" data-bs-target="#cr-lab" type="button" role="tab">
                                <i class="mdi mdi-flask"></i> Lab Requests
                                <span class="badge bg-danger rounded-pill ms-1 lab-unviewed-badge" id="cr-lab-unviewed-badge" style="display: none; font-size: 0.7rem; padding: 0.25em 0.6em;"></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cr-imaging-tab" data-bs-toggle="tab" data-bs-target="#cr-imaging" type="button" role="tab">
                                <i class="mdi mdi-radioactive"></i> Imaging
                                <span class="badge bg-danger rounded-pill ms-1 imaging-unviewed-badge" id="cr-imaging-unviewed-badge" style="display: none; font-size: 0.7rem; padding: 0.25em 0.6em;"></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cr-procedures-tab" data-bs-toggle="tab" data-bs-target="#cr-procedures" type="button" role="tab">
                                <i class="mdi mdi-medical-bag"></i> Procedures
                            </button>
                        </li>
                    </ul>

                    @include('admin.nursing.partials.tabs._clinical_requests_sub_content')
                </div>
            </div>

            <!-- Billing Tab -->
            <div class="workspace-tab-content" id="billing-tab">
                <div id="billing-kit-root">
                    <p class="text-muted text-center py-5"><i class="mdi mdi-account-arrow-left mdi-36px"></i><br>Select a patient to view billing</p>
                </div>
            </div>
            <!-- Nursing Notes Tab -->
            <div class="workspace-tab-content" id="notes-tab">
                @include('admin.partials.shared_workbench_nurse_notes', [
                    'prefix' => 'nursing',
                    'formId' => 'nursing-note-form',
                    'editorId' => 'nursing-note-editor',
                    'statusId' => 'note-autosave-status',
                    'tableId' => 'nursing-notes-table',
                    'refreshCallback' => 'loadNotesHistory(currentPatient)'
                ])
            </div>
        </div>
    </div>
</div>

<!-- Medication Logs Modal -->
@include('admin.nursing.partials._modals')
@endsection

@include('admin.nursing.partials._scripts')
