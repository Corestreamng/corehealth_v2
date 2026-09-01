@extends('admin.layouts.app')

@section('title', 'Maternity Workbench')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/maternity-workbench.css') }}?v={{ filemtime(public_path('css/maternity-workbench.css')) }}">
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/clinical-orders-shared.css') }}">
<link rel="stylesheet" href="{{ asset('css/billing-shared.css') }}">
@endpush

@section('content')
    @include('admin.partials.procedure_outcome_modal')
@php
$hosColor = appsettings()->hos_color ?? '#0066cc';
$sett = appsettings();
@endphp


<!-- ═══════════════════════════════════════════════════════════════
     HTML STRUCTURE (shared layout with nursing workbench)
     ═══════════════════════════════════════════════════════════════ -->

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
        <strong>No maternity store resolved.</strong>
        <span class="ms-1">Ward stock actions are blocked. Start a shift or contact admin.</span>
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

    <!-- ── Left Panel: Patient Search & Queue (SHARED PATTERN) ── -->
    <div class="left-panel" id="left-panel">
        <div class="panel-header">
            <h5><i class="mdi mdi-baby-carriage"></i> Maternity</h5>
            <button class="btn-view-work-pane" id="btn-view-work-pane" title="View Work Pane">
                <i class="fa fa-arrow-right"></i> Work Pane
            </button>
        </div>

        {{-- SHARED partial: patient search input + dropdown --}}
        @include('admin.partials.patient_search_html')

        <div class="queue-widget">
            <h6><i class="mdi mdi-clipboard-list"></i> MATERNITY QUEUES</h6>
            <div class="queue-item" data-filter="active-anc">
                <span class="queue-item-label"><i class="mdi mdi-mother-nurse"></i> Active ANC</span>
                <span class="queue-count anc" id="queue-active-anc-count">0</span>
            </div>
            <div class="queue-item" data-filter="due-visits">
                <span class="queue-item-label"><i class="mdi mdi-calendar-clock"></i> Due Visits</span>
                <span class="queue-count due-visit" id="queue-due-visits-count">0</span>
            </div>
            <div class="queue-item" data-filter="upcoming-edd">
                <span class="queue-item-label"><i class="mdi mdi-calendar-star"></i> Upcoming EDD</span>
                <span class="queue-count edd" id="queue-upcoming-edd-count">0</span>
            </div>
            <div class="queue-item" data-filter="postnatal">
                <span class="queue-item-label"><i class="mdi mdi-account-heart"></i> Postnatal</span>
                <span class="queue-count postnatal" id="queue-postnatal-count">0</span>
            </div>
            <div class="queue-item" data-filter="overdue-immunization">
                <span class="queue-item-label"><i class="mdi mdi-needle"></i> Overdue Immunizations</span>
                <span class="queue-count overdue" id="queue-overdue-imm-count">0</span>
            </div>
            <div class="queue-item" data-filter="high-risk" style="border-left: 3px solid #dc3545;">
                <span class="queue-item-label"><i class="mdi mdi-alert"></i> High Risk</span>
                <span class="queue-count high-risk" id="queue-high-risk-count">0</span>
            </div>
            <div class="queue-item" data-filter="bed-requests" style="border-left: 3px solid #17a2b8;">
                <span class="queue-item-label"><i class="mdi mdi-bed"></i> Bed Requests</span>
                <span class="queue-count" id="queue-bed-requests-count">0</span>
            </div>
            <div class="queue-item" data-filter="discharge-requests" style="border-left: 3px solid #ffc107;">
                <span class="queue-item-label"><i class="mdi mdi-exit-to-app"></i> Discharge Requests</span>
                <span class="queue-count" id="queue-discharge-requests-count">0</span>
            </div>
            <div class="queue-item" data-filter="admitted-patients" style="border-left: 3px solid #6f42c1;">
                <span class="queue-item-label"><i class="mdi mdi-hospital-building"></i> Admitted Patients</span>
                <span class="queue-count" id="queue-admitted-patients-count">0</span>
            </div>
            <button class="btn-queue-all" id="refresh-queues-btn">
                <i class="mdi mdi-refresh"></i> Refresh Queues
            </button>
        </div>

        <div class="quick-actions">
            <h6><i class="mdi mdi-lightning-bolt"></i> QUICK ACTIONS</h6>
            <button class="quick-action-btn" id="btn-enroll-patient">
                <i class="mdi mdi-clipboard-plus text-success"></i>
                <span>New Enrollment</span>
            </button>
            <button class="quick-action-btn" id="btn-quick-vitals" disabled title="Select a patient first">
                <i class="mdi mdi-heart-pulse text-danger"></i>
                <span>Quick Vitals</span>
            </button>
            <button class="quick-action-btn" id="btn-maternity-reports">
                <i class="mdi mdi-chart-box-outline text-info"></i>
                <span>Reports & Analytics</span>
            </button>
            <button class="quick-action-btn" id="btn-print-anc-card" disabled title="Enroll/select patient first">
                <i class="mdi mdi-printer text-primary"></i>
                <span>Print ANC Card</span>
            </button>
            <button class="quick-action-btn" id="btn-print-road-card" disabled title="Enroll/select patient first">
                <i class="mdi mdi-card-account-details-outline text-primary"></i>
                <span>Print Road to Health</span>
            </button>
            <button class="quick-action-btn" id="btn-maternity-audit" disabled title="Enroll/select patient first">
                <i class="mdi mdi-shield-search text-warning"></i>
                <span>Audit Trail</span>
            </button>
            <button class="quick-action-btn" id="btn-discharge-patient" disabled title="Discharge maternity enrollment" style="display:none;">
                <i class="mdi mdi-exit-run text-danger"></i>
                <span>Discharge</span>
            </button>
        </div>
    </div>

    <!-- ── Main Workspace (SHARED PATTERN) ── -->
    <div class="main-workspace" id="main-workspace">
        <!-- Navbar (mobile) -->
        <div class="workspace-navbar" id="workspace-navbar">
            <button class="btn-back-to-search" id="btn-back-to-search">
                <i class="fa fa-arrow-left"></i> Back
            </button>
            <div class="workspace-navbar-actions">
                <button class="btn-toggle-search" id="btn-toggle-search">
                    <i class="fa fa-bars"></i>
                </button>
                <button class="btn-clinical-context" id="btn-clinical-context" disabled title="Select a patient first">
                    <i class="fa fa-heartbeat"></i> Clinical Context
                </button>
            </div>
        </div>

        <!-- Empty State (SHARED PATTERN) -->
        <div class="empty-state" id="empty-state">
            <i class="mdi mdi-baby-carriage"></i>
            <h3>Select a patient to begin</h3>
            <p>Search for a female patient or pick from the maternity queues</p>
            <button class="btn btn-lg" id="view-queue-btn" style="background: var(--maternity-pink); color: white;">
                📋 View Active ANC Queue
            </button>
        </div>

        <!-- Queue View (SHARED PATTERN) -->
        <div class="queue-view" id="queue-view">
            <div class="queue-view-header">
                <h4 id="queue-view-title"><i class="mdi mdi-mother-nurse"></i> Active ANC</h4>
                <button class="btn-close-queue" id="btn-close-queue">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content" id="queue-view-content">
                <p class="text-muted text-center py-4">Loading...</p>
            </div>
        </div>

        <!-- Reports View -->
        <div class="queue-view" id="reports-view">
            <div class="queue-view-header">
                <h4><i class="mdi mdi-chart-box"></i> Maternity Reports & Analytics</h4>
                <button class="btn btn-secondary btn-close-queue" id="btn-close-reports">
                    <i class="mdi mdi-close"></i> Close
                </button>
            </div>
            <div class="queue-view-content" id="reports-content" style="padding: 1.5rem; overflow-y: auto;">
                
                <!-- Quick Date Presets & Filter -->
                <div class="date-presets-bar mb-3 d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted me-2">Global Date Filter:</span>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn active" data-preset="all">All Time</button>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn" data-preset="today">Today</button>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn" data-preset="week">This Week</button>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn" data-preset="month">This Month</button>
                    <button class="btn btn-sm btn-outline-primary date-preset-btn" data-preset="year">This Year</button>
                    
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <input type="date" class="form-control form-control-sm w-auto" id="mat-report-date-from" title="Date From">
                        <span class="text-muted">-</span>
                        <input type="date" class="form-control form-control-sm w-auto" id="mat-report-date-to" title="Date To">
                        <button class="btn btn-sm btn-primary" id="btn-apply-mat-dates">Apply</button>
                    </div>
                </div>

                <!-- Report Tabs -->
                <ul class="nav nav-tabs nav-fill mb-3" id="maternity-report-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="mat-overview-tab" data-bs-toggle="tab" data-bs-target="#mat-overview-content" type="button" role="tab">
                            <i class="mdi mdi-view-dashboard"></i> Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="mat-clinical-tab" data-bs-toggle="tab" data-bs-target="#mat-clinical-content" type="button" role="tab">
                            <i class="mdi mdi-stethoscope"></i> Clinical Quality
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="mat-ward-tab" data-bs-toggle="tab" data-bs-target="#mat-ward-content" type="button" role="tab">
                            <i class="mdi mdi-hospital-building"></i> Ward & Admissions
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="maternity-report-tab-content">
                    
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="mat-overview-content" role="tabpanel">
                        <div class="row" id="reports-summary-cards">
                            <div class="col-12 text-center p-4"><i class="mdi mdi-loading mdi-spin mdi-24px"></i> Loading Overview...</div>
                        </div>
                    </div>

                    <!-- Clinical Quality Tab -->
                    <div class="tab-pane fade" id="mat-clinical-content" role="tabpanel">
                        <div class="row" id="reports-clinical-cards">
                            <div class="col-12 text-center p-4"><i class="mdi mdi-loading mdi-spin mdi-24px"></i> Loading Clinical Data...</div>
                        </div>
                    </div>

                    <!-- Ward & Admissions Tab -->
                    <div class="tab-pane fade" id="mat-ward-content" role="tabpanel">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card-modern shadow-sm border-info h-100">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted text-uppercase mb-2">Total Admissions</h6>
                                        <h2 class="text-info fw-bold" id="admissions-total-kpi">0</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card-modern shadow-sm border-primary h-100">
                                    <div class="card-body text-center">
                                        <h6 class="text-muted text-uppercase mb-2">Average Length of Stay (ALOS)</h6>
                                        <h2 class="text-primary fw-bold" id="admissions-alos-kpi">0 Days</h2>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mx-auto">
                                <div class="card-modern shadow-sm">
                                    <div class="card-header bg-white py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-chart-pie"></i> Admissions by Class (Entry Point)</h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <canvas id="admissions-class-chart" width="400" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Patient Header (SHARED PATTERN) -->
        <div class="patient-header" id="patient-header">
            <div class="patient-header-top">
                <div style="flex: 1; min-width: 200px;">
                    <div class="patient-name" id="patient-name"></div>
                    <div class="patient-meta" id="patient-meta"></div>
                </div>

                {{-- Context Switching Buttons --}}
                <div id="context-switch-container" class="context-switch-container mx-3"></div>

                {{-- Header Actions --}}
                <div class="header-action-group">
                    <button class="btn btn-sm btn-info" id="btn-print-anc-card" title="Print ANC Card" style="display:none;">
                        <i class="mdi mdi-card-account-details"></i> ANC Card
                    </button>
                    <button class="btn btn-sm btn-success" id="btn-print-road-card" title="Print Road to Health Card" style="display:none;">
                        <i class="mdi mdi-baby-face-outline"></i> Road to Health
                    </button>
                    
                    <div id="admission-status-container" class="d-none align-items-center px-2 py-1 bg-light rounded border me-2" style="gap: 5px;">
                        <i class="fa fa-bed" style="color: var(--maternity-pink, #e91e63); font-size: 0.75rem;"></i>
                        <div class="d-flex flex-column lh-1 text-start" id="admission-status-text"></div>
                    </div>
                    <button class="btn btn-sm btn-primary" id="btn-admit-to-ward" title="Admit Patient" style="display:none;">
                        <i class="mdi mdi-bed"></i> Admit
                    </button>
                    <button class="btn btn-sm btn-warning" id="btn-discharge-from-ward" title="Request Ward Discharge" style="display:none;">
                        <i class="mdi mdi-exit-to-app"></i> Ward Discharge
                    </button>
                    <button class="btn btn-sm btn-danger" id="btn-discharge-patient" title="Discharge maternity enrollment" style="display:none;">
                        <i class="mdi mdi-exit-to-app"></i> Discharge
                    </button>
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

        <!-- Workspace Content (SHARED PATTERN) -->
        <div class="workspace-content" id="workspace-content">
            <div class="workspace-tabs">
                <button class="workspace-tab active" data-tab="overview">
                    <i class="mdi mdi-view-dashboard"></i>
                    <span>Overview</span>
                </button>
                <button class="workspace-tab" data-tab="clinical-story">
                    <i class="fa fa-history"></i>
                    <span>Clinical Story</span>
                </button>
                <button class="workspace-tab" data-tab="notes">
                    <i class="mdi mdi-note-text"></i>
                    <span>Notes</span>
                </button>
                <button class="workspace-tab" data-tab="enrollment">
                    <i class="mdi mdi-clipboard-plus"></i>
                    <span>Enrollment</span>
                </button>
                <button class="workspace-tab" data-tab="history">
                    <i class="mdi mdi-clipboard-text-clock"></i>
                    <span>Mother's History</span>
                </button>
                <button class="workspace-tab" data-tab="anc">
                    <i class="mdi mdi-stethoscope"></i>
                    <span>ANC Visits</span>
                </button>
                <button class="workspace-tab" data-tab="clinical-orders">
                    <i class="mdi mdi-flask"></i>
                    <span>Clinical Orders</span>
                </button>
                <button class="workspace-tab" data-tab="delivery">
                    <i class="mdi mdi-baby-carriage"></i>
                    <span>Delivery</span>
                </button>
                <button class="workspace-tab" data-tab="partograph">
                    <i class="mdi mdi-chart-timeline-variant"></i>
                    <span>Partograph</span>
                </button>
                <button class="workspace-tab" data-tab="baby">
                    <i class="mdi mdi-baby-face-outline"></i>
                    <span>Baby Records</span>
                </button>
                <button class="workspace-tab" data-tab="postnatal">
                    <i class="mdi mdi-account-heart"></i>
                    <span>Postnatal</span>
                </button>
                <button class="workspace-tab" data-tab="immunization">
                    <i class="mdi mdi-shield-check"></i>
                    <span>Immunization</span>
                </button>
                <button class="workspace-tab" data-tab="vitals">
                    <i class="mdi mdi-heart-pulse"></i>
                    <span>Vitals</span>
                </button>
                <button class="workspace-tab" data-tab="audit">
                    <i class="mdi mdi-shield-search"></i>
                    <span>Audit Trail</span>
                </button>
                <button class="workspace-tab" data-tab="billing">
                    <i class="mdi mdi-receipt"></i>
                    <span>Billing</span>
                </button>
            </div>

            <!-- ═══ OVERVIEW TAB ═══ -->
            <div class="workspace-tab-content active" id="overview-tab">
                <div class="p-3" id="overview-content">
                    <p class="text-muted text-center py-3">Select a patient to view overview</p>
                </div>
            </div>

            <!-- Clinical Story Tab -->
            <div class="workspace-tab-content" id="clinical-story-tab">
                <div class="clinical-story-container p-3">
                    @include('admin.partials.clinical_story')
                </div>
            </div>

            <!-- ═══ ENROLLMENT TAB ═══ -->
            <div class="workspace-tab-content" id="enrollment-tab">
                <div class="p-3">
                    <!-- Enrollment form (dynamic: show form if not enrolled, show details if enrolled) -->
                    <div id="enrollment-content">
                        <p class="text-muted text-center py-3">Select a patient first</p>
                    </div>
                </div>
            </div>

            <!-- ═══ MOTHER'S HISTORY TAB ═══ -->
            <div class="workspace-tab-content" id="history-tab">
                <div class="p-3">
                    <div id="history-content">
                        <p class="text-muted text-center py-3">Enroll patient to view history</p>
                    </div>
                </div>
            </div>

            <!-- ═══ ANC VISITS TAB ═══ -->
            <div class="workspace-tab-content" id="anc-tab">
                <div class="p-3">
                    <div id="anc-content">
                        <p class="text-muted text-center py-3">Enroll patient to record ANC visits</p>
                    </div>
                </div>
            </div>

            <!-- ═══ CLINICAL ORDERS TAB ═══ -->
            <div class="workspace-tab-content" id="clinical-orders-tab">
                <div class="p-3">
                    <div id="clinical-orders-content">
                        <p class="text-muted text-center py-3">Enroll patient to use clinical orders</p>
                    </div>
                </div>
            </div>

            <!-- ═══ DELIVERY TAB ═══ -->
            <div class="workspace-tab-content" id="delivery-tab">
                <div class="p-3">
                    <div id="delivery-content">
                        <p class="text-muted text-center py-3">Enroll patient to record delivery</p>
                    </div>
                </div>
            </div>

            <!-- ═══ PARTOGRAPH TAB ═══ -->
            <div class="workspace-tab-content" id="partograph-tab">
                <div class="p-3">
                    <div id="partograph-tab-content">
                        <p class="text-muted text-center py-3">Select a patient to view partograph</p>
                    </div>
                </div>
            </div>

            <!-- ═══ BABY RECORDS TAB ═══ -->
            <div class="workspace-tab-content" id="baby-tab">
                <div class="p-3">
                    <div id="baby-content">
                        <p class="text-muted text-center py-3">Delivery must be recorded first</p>
                    </div>
                </div>
            </div>

            <!-- ═══ POSTNATAL TAB ═══ -->
            <div class="workspace-tab-content" id="postnatal-tab">
                <div class="p-3">
                    <div id="postnatal-content">
                        <p class="text-muted text-center py-3">Delivery must be recorded first</p>
                    </div>
                </div>
            </div>

            <!-- ═══ IMMUNIZATION TAB ═══ -->
            <div class="workspace-tab-content" id="immunization-tab">
                <div class="p-3">
                    <div id="immunization-content">
                        <p class="text-muted text-center py-3">Register baby to view immunization schedule</p>
                    </div>
                </div>
            </div>

            <!-- ═══ VITALS TAB (uses shared partial) ═══ -->
            <div class="workspace-tab-content" id="vitals-tab">
                @include('admin.partials.unified_vitals')
            </div>

            <!-- ═══ NOTES TAB ═══ -->
            <div class="workspace-tab-content" id="notes-tab">
                @include('admin.partials.shared_workbench_nurse_notes', [
                    'prefix' => 'maternity',
                    'formId' => 'maternity-note-form',
                    'editorId' => 'maternity-note-editor',
                    'statusId' => 'maternity-note-autosave-status',
                    'refreshCallback' => 'loadNotesTab()'
                ])
            </div>

            <!-- ═══ AUDIT TAB ═══ -->
            <div class="workspace-tab-content" id="audit-tab">
                <div class="p-3">
                    <div id="audit-content">
                        <p class="text-muted text-center py-3">Enroll/select patient to view audit trail</p>
                    </div>
                </div>
            </div>

            <!-- ═══ BILLING TAB ═══ -->
            <div class="workspace-tab-content" id="billing-tab">
                <div id="billing-kit-root">
                    <p class="text-muted text-center py-5"><i class="mdi mdi-account-arrow-left mdi-36px"></i><br>Select a patient to view billing</p>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.administer_vaccine_modal')

{{-- Dose-mode toggle rendered as hidden HTML; moved into dynamic container via JS --}}
<div id="mco-dose-mode-toggle-source" style="display:none;">
    @include('admin.partials.dose-mode-toggle', ['prefix' => 'mco_'])
</div>

{{-- MATERNITY FORM MODALS --}}
@include('admin.maternity.partials._modals')

@endsection
@include('admin.maternity.partials._scripts')
