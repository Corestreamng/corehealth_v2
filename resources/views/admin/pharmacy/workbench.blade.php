@extends('admin.layouts.app')

@section('title', 'Pharmacy Workbench')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pharmacy-workbench.css') }}?v={{ filemtime(public_path('css/pharmacy-workbench.css')) }}">
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
@endpush

@section('content')
@php
    $hosColor = appsettings()->hos_color ?? '#0066cc';
    $sett = appsettings();
@endphp


{{-- Store Context Banner — outside the flex container so it spans full width without compressing the panels --}}
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
        <strong>No store context resolved.</strong>
        <span class="ms-1">Dispensing is blocked until a store is assigned to your role or shift.</span>
        <a href="{{ route('inventory.config.store-governance.context-rules') }}" class="btn btn-danger btn-sm ms-auto py-0 px-2">
            <i class="fas fa-cog"></i> Configure
        </a>
    </div>
</div>
@elseif($contextFallbackAction === 'allow_manual')
<div class="px-3 pt-2 pb-0">
    <div class="alert alert-warning py-2 px-3 d-flex align-items-center gap-2 mb-1" id="store-context-banner">
        <i class="fas fa-question-circle me-1"></i>
        <strong>No store auto-resolved.</strong>
        <span class="ms-1">Please select a store manually before dispensing.</span>
    </div>
</div>
@endif

<div class="pharmacy-workbench-container">

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
            <h6>📊 PRESCRIPTION QUEUE</h6>
            <div class="queue-item" data-filter="emergency" style="background: #fff5f5; border-left: 3px solid #dc3545;">
                <span class="queue-item-label">🚨 <strong class="text-danger">Emergency</strong></span>
                <span class="queue-count" id="queue-emergency-count" style="background: #dc3545; color: #fff;">0</span>
            </div>
            <div class="queue-item" data-filter="all">
                <span class="queue-item-label">🟡 All Pending</span>
                <span class="queue-count all-unpaid" id="queue-all-count">0</span>
            </div>
            <div class="queue-item" data-filter="unbilled">
                <span class="queue-item-label">🟠 Unbilled</span>
                <span class="queue-count unbilled-items" id="queue-unbilled-count">0</span>
            </div>
            <div class="queue-item" data-filter="billed">
                <span class="queue-item-label">🟢 Ready to Dispense</span>
                <span class="queue-count ready-items" id="queue-ready-count">0</span>
            </div>
            <div class="queue-item" data-filter="hmo">
                <span class="queue-item-label">🔵 HMO Items</span>
                <span class="queue-count hmo-items" id="queue-hmo-count">0</span>
            </div>
            <div class="queue-item" data-filter="freeform">
                <span class="queue-item-label">⚪ Free-Form / External</span>
                <span class="queue-count" id="queue-freeform-count" style="background: #6c757d; color: white;">0</span>
            </div>
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
            <button class="quick-action-btn" id="btn-my-transactions">
                <i class="mdi mdi-receipt"></i>
                <span>My Transactions</span>
            </button>
            <button class="quick-action-btn" id="btn-pharmacy-reports">
                <i class="mdi mdi-chart-box-outline"></i>
                <span>Reports & Analytics</span>
            </button>

            <!-- Post-Transaction Quick Actions -->
            @hasanyrole('SUPERADMIN|ADMIN|PHARMACIST|STORE_MANAGER')
            <button class="quick-action-btn" id="btn-pharmacy-returns">
                <i class="mdi mdi-undo-variant"></i>
                <span>Process Returns</span>
            </button>
            <button class="quick-action-btn" id="btn-pharmacy-damages">
                <i class="mdi mdi-alert-octagon"></i>
                <span>Report Damages</span>
            </button>
            <button class="quick-action-btn" id="btn-pharmacy-stock-reports">
                <i class="mdi mdi-file-chart"></i>
                <span>Stock Reports</span>
            </button>
            @endhasanyrole

            <button class="quick-action-btn" disabled style="opacity: 0.5;">
                <i class="mdi mdi-file-invoice-dollar"></i>
                <span>Generate Invoice (Coming Soon)</span>
            </button>
            @if(appsettings()->enable_ei_pharmacy)
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
            <i class="mdi mdi-account-cash"></i>
            <h3>No Patient Selected</h3>
            <p>Search and select a patient from the queue to dispense medications</p>
            <button class="btn btn-lg btn-primary" id="view-queue-btn">
                💊 View Prescription Queue
            </button>
        </div>

        <!-- Queue View -->
        <div class="queue-view" id="queue-view">
            <div class="queue-view-header">
                <h4 id="queue-view-title"><i class="mdi mdi-format-list-bulleted"></i> Prescription Queue</h4>
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

        <!-- Pharmacy Reports View (Full Screen) -->
        @include('admin.pharmacy.partials.views._pharmacy_reports_view')

        {{-- Post-Transaction Action Panels --}}
        @include('admin.pharmacy.partials._returns')
        @include('admin.pharmacy.partials._damages')
        @include('admin.pharmacy.partials._stock_reports')

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
                <div class="patient-account-balance" id="patient-header-balance" style="display: none;">
                    <div class="balance-label">Account Balance</div>
                    <div class="balance-value" id="header-balance-amount">₦0.00</div>
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
                    <i class="mdi mdi-pill"></i>
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
                {{-- Use unified prescription component with sub-tabs --}}
                <div id="pharmacy-presc-container">
                    {{-- This will be populated dynamically when patient is loaded --}}
                    <div class="text-center text-muted py-5">
                        <i class="mdi mdi-pill" style="font-size: 3rem;"></i>
                        <p>Select a patient to view prescriptions</p>
                    </div>
                </div>
            </div>

            <div class="workspace-tab-content" id="history-tab">
                <div class="history-tab-header">
                    <h4><i class="mdi mdi-history"></i> Dispensing History</h4>
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
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="Transfer">Bank Transfer</option>
                                <option value="Mobile">Mobile Money</option>
                                <option value="Account">Account Balance</option>
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
                                <th>Date</th>
                                <th>Medication</th>
                                <th>Qty</th>
                                <th class="text-right">Default</th>
                                <th class="text-right">Patient Paid</th>
                                <th class="text-right">HMO Paid</th>
                                <th>Dispensed By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="receipts-tbody">
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="mdi mdi-receipt" style="font-size: 3rem;"></i>
                                    <p>No receipts found for this patient</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="workspace-tab-content" id="new-request-tab">
                <div class="new-request-container" style="max-width: 100%;">
                    <div class="new-request-header">
                        <h4><i class="mdi mdi-plus-circle"></i> Create New Prescription Request</h4>
                        <p class="text-muted">Request medication for <span id="new-request-patient-name"></span></p>
                    </div>
                    <form id="new-prescription-request-form" class="new-request-form">
                        <div class="form-group" style="position: relative; width: 100%;">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="product-search-input" class="mb-0"><i class="mdi mdi-magnify"></i> Search Medications/Products</label>
                                <a href="javascript:void(0)" onclick="addFreeFormProductWorkbench()" class="text-primary small"><i class="mdi mdi-plus"></i> Not listed? Add free-form</a>
                            </div>
                            <input type="text" class="form-control" id="product-search-input" placeholder="Type medication name or code..." autocomplete="off">
                            <ul class="list-group" id="product-search-results" style="display: none; position: absolute; top: 100%; left: 0; z-index: 1050; max-height: 300px; overflow-y: auto; width: 100%; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border: 1px solid #ddd; border-radius: 0 0 4px 4px;"></ul>
                        </div>

                        <hr class="my-3">

                        <div id="selected-products-container" style="display: none;">
                            <label><i class="mdi mdi-pill"></i> Selected Medications</label>
                            <div class="table-responsive" id="selected-products-list" class="mb-3">
                                <table class="table table-sm table-bordered table-hover" id="selected-products-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Medication</th>
                                            <th class="text-right">Price</th>
                                            <th class="text-center" style="width: 80px;">Qty</th>
                                            <th>Dose/Frequency *</th>
                                            <th class="text-center" style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="selected-products-tbody"></tbody>
                                    <tfoot>
                                        <tr class="table-light">
                                            <td class="text-right"><strong>Grand Total:</strong></td>
                                            <td class="text-right"><strong id="selected-products-total">₦0.00</strong></td>
                                            <td colspan="3"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="form-row" style="display: none;">
                            <div class="form-group col-md-6">
                                <label for="request-urgency"><i class="mdi mdi-clock-alert"></i> Urgency Level</label>
                                <select class="form-control" id="request-urgency" name="urgency">
                                    <option value="routine">Routine</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="stat">STAT (Immediate)</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="request-send-to-billing"><i class="mdi mdi-cash-register"></i> Send to Billing?</label>
                                <select class="form-control" id="request-send-to-billing" name="send_to_billing">
                                    <option value="1">Yes - Send to Billing Queue</option>
                                    <option value="0">No - Direct Request (e.g., Ward Stock)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="request-notes"><i class="mdi mdi-note-text"></i> Notes / Instructions</label>
                            <textarea class="form-control" id="request-notes" name="notes" rows="3" placeholder="Enter any special instructions or notes..."></textarea>
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



<!-- Dispense Summary Card (floating) -->
<div id="dispense-summary-card">
    <span><strong id="dispense-count">0</strong> items selected</span>
    <button class="btn btn-light btn-sm" id="print-selected-btn">
        <i class="mdi mdi-printer"></i> Print
    </button>
    <button class="btn btn-success btn-sm" id="dispense-selected-btn">
        <i class="mdi mdi-pill"></i> Dispense
    </button>
</div>



<!-- Floating Cart Button -->
<div class="floating-cart" id="floating-cart">
    <button class="floating-cart-btn" onclick="openCartReviewModal()">
        <i class="mdi mdi-cart-outline"></i>
        <span class="cart-badge" id="cart-item-count">0</span>
        <span class="cart-total" id="cart-total-display">₦0.00</span>
        <i class="mdi mdi-chevron-up"></i>
    </button>
</div>

<!-- Cart Review Modal -->
@include('admin.pharmacy.partials._modals')
@endsection

@include('admin.pharmacy.partials._scripts')
