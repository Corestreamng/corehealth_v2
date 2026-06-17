@extends('admin.layouts.app')
@section('title', 'Requisition - ' . $requisition->requisition_number)
@section('page_name', 'Inventory Management')
@section('subpage_name', 'View Requisition')

@section('content')
<style>
    .req-header {
        background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
        color: white;
        padding: 2rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    .status-badge {
        font-size: 0.85rem;
        padding: 0.5em 1em;
        border-radius: 20px;
    }
    .status-pending { background-color: rgba(255,255,255,0.2); }
    .status-approved { background-color: #17a2b8; }
    .status-partial { background-color: #ffc107; color: #212529; }
    .status-fulfilled { background-color: #28a745; }
    .status-rejected { background-color: #dc3545; }
    .status-cancelled { background-color: #6c757d; }
    .status-returned { background-color: #343a40; }
    .returned-banner {
        background: linear-gradient(135deg, #343a40 0%, #495057 100%);
        color: white;
        border-radius: 8px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }
    .returned-banner .icon { font-size: 2.5rem; opacity: 0.85; }
    .returned-banner .text-block h5 { margin-bottom: 0.25rem; font-weight: 700; }
    .returned-banner .text-block p { margin: 0; opacity: 0.85; font-size: 0.9rem; }

    .priority-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
    }
    .priority-low { background: #e9ecef; color: #495057; }
    .priority-normal { background: #cfe2ff; color: #084298; }
    .priority-high { background: #fff3cd; color: #664d03; }
    .priority-urgent { background: #f8d7da; color: #842029; }

    .detail-card {
        background: #fff;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .detail-card h5 {
        border-bottom: 2px solid #6f42c1;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
    }
    .detail-label {
        font-weight: 500;
        color: #6c757d;
        font-size: 0.85rem;
    }
    .detail-value {
        font-weight: 600;
        color: #212529;
    }
    .items-table th {
        background: #f8f9fa;
    }
    .transfer-flow {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        padding: 1rem;
        background: rgba(255,255,255,0.1);
        border-radius: 8px;
        margin-top: 1rem;
    }
    .store-box {
        background: rgba(255,255,255,0.2);
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
    }
    .fulfill-input {
        width: 80px;
        text-align: center;
    }

    /* Approval Panel Styles */
    .approval-panel {
        background: linear-gradient(135deg, #fff9e6 0%, #fff 100%);
        border: 2px solid #ffc107;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .approval-panel h5 {
        color: #856404;
        margin-bottom: 1rem;
        border-bottom: none;
        padding-bottom: 0;
    }
    .approval-item {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    .approval-item:last-child { margin-bottom: 0; }
    .approval-item .product-name {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }
    .approval-item .product-code {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .stock-comparison {
        display: flex;
        gap: 1rem;
        margin: 0.75rem 0;
        flex-wrap: wrap;
    }
    .stock-box {
        flex: 1;
        min-width: 120px;
        padding: 0.75rem;
        border-radius: 6px;
        text-align: center;
    }
    .stock-box.source {
        background: #d4edda;
        border: 1px solid #28a745;
    }
    .stock-box.destination {
        background: #cce5ff;
        border: 1px solid #007bff;
    }
    .stock-box .label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }
    .stock-box .value {
        font-size: 1.25rem;
        font-weight: 700;
    }
    .stock-box.source .value { color: #28a745; }
    .stock-box.destination .value { color: #007bff; }
    .qty-adjustment {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }
    .qty-adjustment label {
        font-weight: 500;
        margin: 0;
        white-space: nowrap;
    }
    .qty-input {
        width: 80px;
        text-align: center;
        font-weight: 600;
        border: 2px solid #dee2e6;
        border-radius: 6px;
        padding: 0.5rem;
    }
    .qty-input:focus {
        border-color: #6f42c1;
        outline: none;
    }
    .qty-input.warning { border-color: #ffc107; background: #fff9e6; }
    .qty-input.danger { border-color: #dc3545; background: #f8d7da; }
    .requested-badge {
        background: #e9ecef;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.85rem;
    }
    .stock-warning {
        font-size: 0.8rem;
        color: #dc3545;
        margin-top: 0.25rem;
    }
    .approval-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #dee2e6;
    }

    /* Item Cards Styles */
    .item-card {
        border: 1px solid #e9ecef;
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
    }
    .item-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }
    .item-number {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #6f42c1;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.85rem;
    }
    .item-product {
        flex: 1;
    }
    .item-product .product-name {
        font-weight: 600;
        font-size: 1rem;
    }
    .item-product .product-code {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .item-body {
        padding: 1rem;
    }
    .qty-flow {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    .qty-stage {
        text-align: center;
        padding: 0.75rem 1rem;
        background: #f8f9fa;
        border-radius: 8px;
        min-width: 80px;
        flex: 1;
    }
    .qty-stage.adjusted {
        background: #fff3cd;
        border: 1px solid #ffc107;
    }
    .qty-stage.complete {
        background: #d4edda;
        border: 1px solid #28a745;
    }
    .qty-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }
    .qty-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: #212529;
    }
    .qty-arrow {
        color: #adb5bd;
        font-size: 1.25rem;
    }
    .progress-section {
        margin-bottom: 0.75rem;
    }
    .pending-info {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 0.5rem;
    }
    .badge-outline-warning {
        background: transparent;
        border: 1px solid #ffc107;
        color: #856404;
    }
    .badge-outline-success {
        background: transparent;
        border: 1px solid #28a745;
        color: #155724;
    }
    .badge-outline-danger {
        background: transparent;
        border: 1px solid #dc3545;
        color: #721c24;
    }
    .item-notes {
        padding-top: 0.5rem;
        border-top: 1px dashed #dee2e6;
    }

    /* Fulfill Panel Styles */
    .fulfill-panel {
        background: linear-gradient(135deg, #e3f2fd 0%, #fff 100%);
        border: 2px solid #2196f3;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    /* Screen-only elements (hidden when printing) */
    .screen-only { display: block; }
    .fulfill-panel h5 {
        color: #1565c0;
        margin-bottom: 1rem;
    }
    .fulfill-item {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    .fulfill-item:last-child { margin-bottom: 0; }
    .fulfill-item-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #e9ecef;
    }
    .fulfill-progress-bar {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    .fulfill-progress-bar .progress {
        flex: 1;
        height: 10px;
    }
    .batch-selector {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 1rem;
    }
    .batch-option {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .batch-option:hover {
        border-color: #007bff;
        background: #e7f1ff;
    }
    .batch-option.selected {
        border-color: #007bff;
        background: #cce5ff;
    }
    .batch-info {
        flex: 1;
    }
    .batch-qty-input {
        width: 80px;
        text-align: center;
    }

    /* Print-only elements */
    .print-only { display: none; }
    .print-header { display: none; }
    .print-footer { display: none; }
    .print-signature-section { display: none; }

    /* Print Styles */
    @media print {
        /* Hide non-printable elements */
        .sidebar, .navbar, .topbar, #sidebar, #sidebarMenu,
        .breadcrumb, .btn, button, .approval-panel, .fulfill-panel,
        .no-print, form, .modal, .toast, .alert-dismissible,
        nav, footer, .pagination, .dropdown-menu, .tooltip,
        .card-footer, .action-buttons, [class*="btn-"] {
            display: none !important;
        }

        /* Show print-only elements */
        .print-only { display: block !important; }
        .print-header { display: block !important; }
        .print-footer { display: block !important; }
        .print-signature-section { display: block !important; }

        /* Hide screen-only elements */\n        .screen-only { display: none !important; }

        /* Hide item cards (use summary table instead) */
        .item-card { display: none !important; }
        .detail-card .item-card { display: none !important; }

        /* Hide the colored header on print (info is in summary table) */
        .req-header { display: none !important; }

        /* Hide detail cards sidebar on print */
        .col-md-4.detail-sidebar { display: none !important; }

        /* Make main content full width on print */
        .col-md-8 { width: 100% !important; max-width: 100% !important; flex: 0 0 100% !important; }

        /* Hide the detail-card container on print (only show items table) */
        .detail-card { display: none !important; }

        /* Reset page layout */
        body {
            background: white !important;
            font-size: 11pt !important;
            color: black !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        #content-wrapper, .container-fluid, .content-wrapper {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Print Header Styling */
        .print-header {
            text-align: center;
            padding: 15px 0;
            border-bottom: 2px solid #333;
            margin-bottom: 20px;
        }
        .print-header .company-name {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .print-header .document-title {
            font-size: 14pt;
            font-weight: 600;
            color: #333;
            margin-top: 10px;
        }
        .print-header .company-address {
            font-size: 9pt;
            color: #666;
        }

        /* Requisition header */
        .req-header {
            background: none !important;
            color: black !important;
            padding: 10px !important;
            border: 2px solid #333 !important;
            border-radius: 0 !important;
            margin-bottom: 15px !important;
            page-break-inside: avoid;
        }
        .req-header h2 {
            font-size: 14pt !important;
            margin-bottom: 10px !important;
        }
        .status-badge, .priority-badge {
            border: 1px solid #333 !important;
            background: none !important;
            color: black !important;
            padding: 2px 8px !important;
            font-size: 9pt !important;
        }
        .transfer-flow {
            background: none !important;
            padding: 10px 0 !important;
        }
        .store-box {
            background: #f5f5f5 !important;
            border: 1px solid #333 !important;
            padding: 8px 15px !important;
        }

        /* Detail cards */
        .detail-card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
            page-break-inside: avoid;
            margin-bottom: 10px !important;
            padding: 10px !important;
        }
        .detail-card h5 {
            font-size: 11pt !important;
            border-bottom: 1px solid #333 !important;
            padding-bottom: 5px !important;
            margin-bottom: 10px !important;
        }

        /* Item cards */
        .item-card {
            border: 1px solid #333 !important;
            page-break-inside: avoid;
            margin-bottom: 10px !important;
        }
        .item-header {
            background: #f5f5f5 !important;
            border-bottom: 1px solid #333 !important;
            padding: 8px !important;
        }
        .item-number {
            background: #333 !important;
            width: 22px !important;
            height: 22px !important;
            font-size: 10pt !important;
        }
        .item-body {
            padding: 10px !important;
        }

        /* Quantity flow */
        .qty-flow {
            display: flex !important;
            justify-content: space-around !important;
        }
        .qty-stage {
            border: 1px solid #999 !important;
            background: #f9f9f9 !important;
            padding: 5px 10px !important;
            min-width: 70px !important;
        }
        .qty-stage.complete {
            background: #e8f5e9 !important;
            border-color: #4caf50 !important;
        }
        .qty-label {
            font-size: 8pt !important;
        }
        .qty-value {
            font-size: 12pt !important;
        }
        .qty-arrow {
            font-size: 12pt !important;
        }

        /* Progress bars - show as text */
        .progress {
            display: none !important;
        }

        /* Table styling */
        table {
            width: 100% !important;
            border-collapse: collapse !important;
        }
        table th, table td {
            border: 1px solid #333 !important;
            padding: 6px 8px !important;
            font-size: 10pt !important;
        }
        table th {
            background: #f0f0f0 !important;
            font-weight: bold !important;
        }

        /* Signature section */
        .print-signature-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .signature-grid {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }
        .signature-box {
            flex: 1;
            text-align: center;
            padding: 10px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
        }
        .signature-label {
            font-size: 10pt;
            font-weight: 600;
        }
        .signature-name {
            font-size: 9pt;
            color: #666;
            margin-top: 3px;
        }
        .signature-date {
            font-size: 8pt;
            color: #999;
            margin-top: 3px;
        }

        /* Print footer */
        .print-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #666;
            padding: 10px;
            border-top: 1px solid #ddd;
        }

        /* Page settings */
        @page {
            size: A4;
            margin: 15mm 10mm 20mm 10mm;
        }

        /* Avoid breaking inside important elements */
        .row { page-break-inside: avoid; }
        h1, h2, h3, h4, h5, h6 { page-break-after: avoid; }

        /* Compact print mode */
        .printing-compact .print-compact-hide { display: none !important; }
    }
</style>

<!-- Print Header (hidden on screen) -->
<div class="print-only print-header">
    @if(appsettings('logo'))
    <div class="company-logo" style="margin-bottom: 10px;">
        <img src="data:image/png;base64,{{ appsettings('logo') }}" alt="Logo" style="max-height: 60px; max-width: 200px;">
    </div>
    @endif
    <div class="company-name">{{ appsettings('site_name') ?: config('app.name', 'CoreHealth') }}</div>
    <div class="company-address">{{ appsettings('contact_address') ?: 'Healthcare Management System' }}</div>
    @if(appsettings('contact_phones'))
    <div class="company-contact" style="font-size: 9pt; color: #666;">Tel: {{ appsettings('contact_phones') }}</div>
    @endif
    <div class="document-title">STOCK REQUISITION FORM</div>
</div>

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="req-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <h2 class="mb-0">{{ $requisition->requisition_number }}</h2>
                        <span class="status-badge status-{{ $requisition->status }}">
                            {{ ucfirst(str_replace('_', ' ', $requisition->status)) }}
                        </span>
                        <span class="priority-badge priority-{{ $requisition->priority }}">
                            {{ ucfirst($requisition->priority) }} Priority
                        </span>
                    </div>
                    <div class="transfer-flow">
                        <div class="store-box">
                            <small class="d-block opacity-75">From</small>
                            <strong>{{ $requisition->fromStore->store_name }}</strong>
                        </div>
                        <i class="mdi mdi-arrow-right-bold" style="font-size: 1.5rem;"></i>
                        <div class="store-box">
                            <small class="d-block opacity-75">To</small>
                            <strong>{{ $requisition->toStore->store_name }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-right">
                    <div class="mb-1">Request Date: {{ $requisition->created_at->format('M d, Y') }}</div>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ route('inventory.requisitions.index') }}" class="btn btn-secondary btn-sm">
                <i class="mdi mdi-arrow-left"></i> Back
            </a>

            @if($requisition->canEditHeader())
                @if(auth()->user()->hasAnyRole(['ADMIN','SUPERADMIN','super-admin','store','Store']) || auth()->id() == $requisition->requested_by)
                <a href="{{ route('inventory.requisitions.edit', $requisition) }}" class="btn btn-warning btn-sm">
                    <i class="mdi mdi-pencil"></i> Edit Requisition
                </a>
                @endif
            @endif

            @if($requisition->status === 'pending')
                @if(auth()->id() == $requisition->requested_by)
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="cancelRequisition()">
                    <i class="mdi mdi-cancel"></i> Cancel Request
                </button>
                @endif
            @endif

            @if(in_array($requisition->status, ['approved', 'partial']))
                @if(auth()->user()->can('requisitions.fulfill') || auth()->user()->hasAnyRole(['ADMIN', 'SUPERADMIN', 'STORE']))
                <a href="#fulfill-panel" class="btn btn-primary btn-sm">
                    <i class="mdi mdi-package"></i> Fulfill Items
                </a>
                @endif
            @endif

            @if($requisition->status !== 'pending')
                @if($requisition->canApprove())
                    @can('requisitions.approve')
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="approveRequisitionGlobal()">
                        <i class="mdi mdi-check-circle"></i> Approve All
                    </button>
                    @endcan
                @endif
                @if($requisition->canReject())
                    @can('requisitions.approve')
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="rejectRequisition()">
                        <i class="mdi mdi-close-circle"></i> Reject All
                    </button>
                    @endcan
                @endif
            @endif

            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printRequisition()">
                <i class="mdi mdi-printer"></i> Print
            </button>
            <div class="btn-group no-print">
                <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown">
                    <i class="mdi mdi-download"></i> Export
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="#" onclick="printRequisition(); return false;">
                        <i class="mdi mdi-printer"></i> Print Document
                    </a>
                    <a class="dropdown-item" href="#" onclick="printRequisition('compact'); return false;">
                        <i class="mdi mdi-file-document-outline"></i> Print Compact
                    </a>
                </div>
            </div>
        </div>

        {{-- Edit History Banner --}}
        @if($requisition->isEdited())
        <div class="alert alert-warning border-left border-warning py-2 px-3 mb-3 d-flex align-items-center" style="border-left-width:4px !important; border-radius:6px;">
            <i class="mdi mdi-pencil-circle text-warning mr-2" style="font-size:1.4rem;"></i>
            <div>
                <strong>This requisition has been edited {{ $requisition->edit_count }} time(s).</strong>
                Last edited by <strong>{{ $requisition->editor->name ?? 'Unknown' }}</strong>
                on {{ $requisition->edited_at ? $requisition->edited_at->format('d M Y \a\t H:i') : '—' }}.
                <small class="d-block text-muted">Full change history is available in the audit log.</small>
            </div>
        </div>
        @endif

        {{-- Fully Returned Banner --}}
        @if($requisition->isFullyReturned())
        <div class="returned-banner mb-3">
            <div class="icon"><i class="mdi mdi-keyboard-return"></i></div>
            <div class="text-block">
                <h5><i class="mdi mdi-check-circle mr-1"></i> All Items Returned</h5>
                <p>
                    All items in this requisition have been returned to the source store.
                    This requisition is effectively <strong>closed</strong>. No further editing or fulfillment is possible.
                    @php $returnCount = \App\Models\StoreRequisitionReturn::where('store_requisition_id', $requisition->id)->count(); @endphp
                    @if($returnCount > 0)
                        <a href="{{ route('inventory.requisition-returns.index') }}?requisition_id={{ $requisition->id }}" class="text-white ml-2">
                            <u>View {{ $returnCount }} return record(s) &rarr;</u>
                        </a>
                    @endif
                </p>
            </div>
        </div>
        @endif

        @if($requisition->status === 'pending')
        @can('requisitions.approve')
        <!-- Approval Panel -->
        <div class="approval-panel">
            <h5><i class="mdi mdi-clipboard-check"></i> Review & Approve Requisition</h5>
            <p class="text-muted mb-3">Review the requested quantities and adjust if needed. You can approve partial quantities based on available stock.</p>

            <form id="approval-form">
                <div class="list-group mb-3">
                    @foreach($requisition->items as $item)
                        @if($item->status === 'approved')
                            @continue
                        @endif
                    @php
                        $sourceStock = \App\Models\StockBatch::where('product_id', $item->product_id)
                            ->where('store_id', $requisition->from_store_id)
                            ->where('current_qty', '>', 0)
                            ->sum('current_qty');
                        $destStock = \App\Models\StockBatch::where('product_id', $item->product_id)
                            ->where('store_id', $requisition->to_store_id)
                            ->where('current_qty', '>', 0)
                            ->sum('current_qty');
                        $canFulfill = $sourceStock>= $item->requested_qty;
                    @endphp
                    <div class="list-group-item approval-item py-3 {{ $item->status === 'rejected' ? 'bg-light text-muted' : '' }}">
                        <div class="row align-items-center">
                            <!-- 1. Product Info & Stock -->
                            <div class="col-12 col-xl-5 mb-3 mb-xl-0">
                                <div class="d-flex align-items-start">
                                    <div class="mr-3 mt-1">
                                        @if($item->status === 'rejected')
                                            <i class="mdi mdi-close-circle text-danger" style="font-size: 1.5rem;"></i>
                                        @elseif($item->status === 'approved')
                                            <i class="mdi mdi-check-circle text-success" style="font-size: 1.5rem;"></i>
                                        @else
                                            <i class="mdi mdi-circle-outline text-muted" style="font-size: 1.5rem;"></i>
                                        @endif
                                    </div>
                                    <div class="w-100">
                                        <h6 class="mb-1 text-primary font-weight-bold" style="{{ $item->status === 'rejected' ? 'text-decoration: line-through;' : '' }}">
                                            {{ $item->product->product_name }}
                                        </h6>
                                        <div class="text-muted small mb-2">{{ $item->product->product_code }}</div>
                                        
                                        <div class="bg-light rounded p-2 border mt-2">
                                            <div class="row text-center small">
                                                <div class="col-4 border-right">
                                                    <div class="text-muted text-uppercase font-weight-bold" style="font-size: 0.65rem;">Requested</div>
                                                    @php
                                                        $baseUnitName = $item->product->base_unit_name ?? 'Units';
                                                        $reqUnitStr = $item->packaging ? $item->packaging->name : $baseUnitName;
                                                        $factor = $item->packaging ? $item->packaging->base_unit_qty : 1;
                                                        $reqDisplay = $item->packaging ? (float)$item->packaging_qty : $item->requested_qty;
                                                    @endphp
                                                    <strong class="text-dark d-block mt-1" style="font-size: 0.85rem;">{{ $reqDisplay }} {{ $reqUnitStr }}</strong>
                                                    @if($factor > 1)
                                                        <div style="font-size: 0.65rem;" class="text-muted mt-1">{{ $item->requested_qty }} {{ $baseUnitName }}</div>
                                                    @endif
                                                </div>
                                                <div class="col-4 border-right">
                                                    <div class="text-muted text-uppercase font-weight-bold" style="font-size: 0.55rem;">From (Source)</div>
                                                    <div class="text-dark mb-1" style="font-size: 0.75rem;" title="{{ $requisition->fromStore->store_name }}"><i class="mdi mdi-store"></i> {{ $requisition->fromStore->store_name }}</div>
                                                    @php
                                                        $sourceDisplay = $factor > 0 ? round($sourceStock / $factor, 1) : 0;
                                                        $sourceColor = $sourceStock >= $item->requested_qty ? 'text-success' : ($sourceStock > 0 ? 'text-warning' : 'text-danger');
                                                    @endphp
                                                    <strong class="{{ $sourceColor }} d-block mt-1" style="font-size: 0.85rem;">{{ $sourceDisplay }} {{ $reqUnitStr }}</strong>
                                                    @if($factor > 1)
                                                        <div style="font-size: 0.65rem;" class="text-muted mt-1">Avail: {{ $sourceStock }} {{ $baseUnitName }}</div>
                                                    @endif
                                                </div>
                                                <div class="col-4">
                                                    <div class="text-muted text-uppercase font-weight-bold" style="font-size: 0.55rem;">To (Destination)</div>
                                                    <div class="text-dark mb-1" style="font-size: 0.75rem;" title="{{ $requisition->toStore->store_name }}"><i class="mdi mdi-store"></i> {{ $requisition->toStore->store_name }}</div>
                                                    @php
                                                        $destDisplay = $factor > 0 ? round($destStock / $factor, 1) : 0;
                                                    @endphp
                                                    <strong class="text-info d-block mt-1" style="font-size: 0.85rem;">{{ $destDisplay }} {{ $reqUnitStr }}</strong>
                                                    @if($factor > 1)
                                                        <div style="font-size: 0.65rem;" class="text-muted mt-1">Stock: {{ $destStock }} {{ $baseUnitName }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Inputs & Actions -->
                            <div class="col-12 col-xl-7">
                                <div class="d-flex flex-column flex-sm-row justify-content-lg-end align-items-sm-center gap-3">
                                    @if($item->status === 'rejected')
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="text-danger small"><strong>Rejected:</strong> {{ $item->notes }}</div>
                                            @if($item->canReverse())
                                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 ml-2" onclick="reverseItem({{ $item->id }})" title="Reverse Rejection"><i class="mdi mdi-undo"></i> Reverse</button>
                                            @endif
                                        </div>
                                    @elseif($item->status === 'approved')
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="text-success small"><strong>Approved:</strong> {{ $item->approved_qty }} {{ $item->product->base_unit_name ?? 'Units' }}</div>
                                            @if($item->canReverse())
                                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 ml-2" onclick="reverseItem({{ $item->id }})" title="Reverse Approval"><i class="mdi mdi-undo"></i> Reverse</button>
                                            @endif
                                        </div>
                                    @else
                                        <div class="d-flex align-items-center gap-2 flex-grow-1 flex-sm-grow-0">
                                            @php
                                                $defaultFactor = $item->packaging ? $item->packaging->base_unit_qty : 1;
                                                $defaultDisplayVal = $defaultFactor > 0 ? round(min($item->requested_qty, $sourceStock) / $defaultFactor, 1) : 0;
                                            @endphp
                                            <input type="number"
                                                   class="form-control form-control-sm approve-display-qty {{ !$canFulfill ? ($sourceStock> 0 ? 'border-warning' : 'border-danger') : '' }}"
                                                   value="{{ $defaultDisplayVal }}"
                                                   min="0" step="0.1"
                                                   style="min-width: 80px; width: 100px; font-weight: 600;"
                                                   placeholder="Qty">
                                            
                                            <select class="form-control form-control-sm approve-unit-select" style="min-width: 150px;">
                                                <option value="" data-factor="1" data-name="{{ $item->product->base_unit_name ?? 'Base Unit' }}" {{ !$item->packaging_id ? 'selected' : '' }}>{{ $item->product->base_unit_name ?? 'Base Unit' }}</option>
                                                @foreach($item->product->packagings as $pkg)
                                                    <option value="{{ $pkg->id }}" data-factor="{{ (float)$pkg->base_unit_qty }}" data-name="{{ $pkg->name }}" {{ $item->packaging_id == $pkg->id ? 'selected' : '' }}>
                                                        {{ $pkg->name }} ({{ (float)$pkg->base_unit_qty }} {{ $item->product->base_unit_name ?? 'units' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="text-center d-none d-sm-block px-3 py-1 bg-white border border-primary rounded shadow-sm mx-2" style="min-width: 100px;">
                                            <div style="font-size: 0.6rem;" class="text-uppercase text-muted font-weight-bold approve-unit-label">Total {{ $item->packaging ? $item->packaging->name : ($item->product->base_unit_name ?? 'Base Qty') }}</div>
                                            <div style="font-size: 1.15rem; line-height: 1.2;" class="font-weight-bold text-primary">
                                                <span class="approve-unit-fraction-numerator">{{ $defaultDisplayVal }}</span>
                                                <span class="text-muted" style="font-size: 0.8rem; font-weight: normal;">/ <span class="approve-unit-fraction-denominator">{{ $defaultFactor > 0 ? round($item->requested_qty / $defaultFactor, 1) : 0 }}</span></span>
                                            </div>
                                            <div style="font-size: 0.65rem;" class="text-muted border-top mt-1 pt-1">
                                                <span class="approve-base-display">{{ min($item->requested_qty, $sourceStock) }}</span> / {{ $item->requested_qty }} {{ $item->product->base_unit_name ?? 'Units' }}
                                            </div>
                                        </div>

                                        <input type="hidden"
                                               name="approved_qtys[{{ $item->id }}]"
                                               class="qty-input"
                                               value="{{ min($item->requested_qty, $sourceStock) }}"
                                               data-requested="{{ $item->requested_qty }}"
                                               data-available="{{ $sourceStock }}">
                                        
                                        <div class="btn-group flex-shrink-0">
                                            <button type="button" class="btn btn-sm btn-outline-success btn-approve-item" 
                                                data-item-id="{{ $item->id }}" 
                                                data-product="{{ $item->product->product_name }}"
                                                data-unit="{{ $item->product->base_unit_name ?? '' }}"
                                                data-pkg="{{ $item->packaging ? (float)$item->packaging_qty . ' ' . $item->packaging->name : '' }}">
                                                <i class="mdi mdi-check"></i> <span class="d-none d-xl-inline">Approve</span>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-reject-item" 
                                                data-item-id="{{ $item->id }}" 
                                                data-product="{{ $item->product->product_name }}">
                                                <i class="mdi mdi-close"></i> <span class="d-none d-xl-inline">Reject</span>
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                @if(!$canFulfill && $item->status !== 'rejected' && $item->status !== 'approved')
                                    <div class="text-lg-right mt-2 text-danger small">
                                        <i class="mdi mdi-alert"></i> 
                                        @if($sourceStock == 0) No stock at source!
                                        @else Only {{ $sourceStock }} available
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="form-group mt-3">
                    <label>Approval Notes (Optional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Add any notes about this approval..."></textarea>
                </div>

                <div class="approval-actions">
                    <button type="button" class="btn btn-success btn-lg" onclick="submitApproval()">
                        <i class="mdi mdi-check-circle"></i> Approve Requisition
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="rejectRequisition()">
                        <i class="mdi mdi-close-circle"></i> Reject
                    </button>
                    <div class="ml-auto text-muted">
                        <small><i class="mdi mdi-information"></i> Adjust quantities above before approving if needed</small>
                    </div>
                </div>
            </form>
        </div>
        @endcan
        @endif

        <!-- Print-Only Items Summary Table -->
        <div class="print-only" style="margin-bottom: 20px;">
            <!-- Requisition Details for Print -->
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd;">
                <div>
                    <strong>Requisition #:</strong> {{ $requisition->requisition_number }}<br>
                    <strong>Date:</strong> {{ $requisition->created_at->format('M d, Y') }}<br>
                    <strong>Priority:</strong> {{ ucfirst($requisition->priority ?? 'normal') }}
                </div>
                <div style="text-align: center;">
                    <strong>From:</strong> {{ $requisition->fromStore->store_name }}<br>
                    <span style="font-size: 16px;">→</span><br>
                    <strong>To:</strong> {{ $requisition->toStore->store_name }}
                </div>
                <div style="text-align: right;">
                    <strong>Status:</strong> {{ ucfirst($requisition->status) }}<br>
                    <strong>Requested By:</strong> {{ $requisition->requester->name ?? 'N/A' }}<br>
                    @if($requisition->approved_at)
                    <strong>Approved:</strong> {{ $requisition->approved_at->format('M d, Y') }}
                    @endif
                </div>
            </div>

            <h5 style="border-bottom: 2px solid #333; padding-bottom: 5px; margin-bottom: 15px;">Items Summary</h5>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f0f0f0;">
                        <th style="border: 1px solid #333; padding: 8px; text-align: center; width: 30px;">#</th>
                        <th style="border: 1px solid #333; padding: 8px; text-align: left;">Product</th>
                        <th style="border: 1px solid #333; padding: 8px; text-align: center;">Unit</th>
                        <th style="border: 1px solid #333; padding: 8px; text-align: center;">Requested</th>
                        <th style="border: 1px solid #333; padding: 8px; text-align: center;">Approved</th>
                        <th style="border: 1px solid #333; padding: 8px; text-align: center;">Fulfilled</th>
                        <th style="border: 1px solid #333; padding: 8px; text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requisition->items as $index => $item)
                    @php $isRejected = ($item->status ?? 'pending') === 'rejected'; @endphp
                    <tr style="{{ $isRejected ? 'opacity: 0.6;' : '' }}">
                        <td style="border: 1px solid #333; padding: 8px; text-align: center;">{{ $index + 1 }}</td>
                        <td style="border: 1px solid #333; padding: 8px;">
                            <strong style="{{ $isRejected ? 'text-decoration: line-through;' : '' }}">{{ $item->product->product_name }}</strong><br>
                            <small style="color: #666;">{{ $item->product->product_code }}</small>
                            @if($item->packaging)
                                <br><small style="color: #17a2b8;">({{ (float)$item->packaging_qty }} {{ $item->packaging->name }})</small>
                            @endif
                        </td>
                        <td style="border: 1px solid #333; padding: 8px; text-align: center;">{{ $item->product->base_unit_name ?? $item->product->unit_of_measure ?? 'Unit' }}</td>
                        <td style="border: 1px solid #333; padding: 8px; text-align: center;">{{ $item->requested_qty }}</td>
                        <td style="border: 1px solid #333; padding: 8px; text-align: center;">{{ $item->approved_qty ?? '-' }}</td>
                        <td style="border: 1px solid #333; padding: 8px; text-align: center;">{{ $item->fulfilled_qty ?? 0 }}</td>
                        <td style="border: 1px solid #333; padding: 8px; text-align: center;">{{ ucfirst($item->status ?? 'pending') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background: #f5f5f5; font-weight: bold;">
                        <td colspan="3" style="border: 1px solid #333; padding: 8px; text-align: right;">Totals:</td>
                        <td style="border: 1px solid #333; padding: 8px; text-align: center;">{{ $requisition->items->sum('requested_qty') }}</td>
                        <td style="border: 1px solid #333; padding: 8px; text-align: center;">{{ $requisition->items->sum('approved_qty') ?: '-' }}</td>
                        <td style="border: 1px solid #333; padding: 8px; text-align: center;">{{ $requisition->items->sum('fulfilled_qty') }}</td>
                        <td style="border: 1px solid #333; padding: 8px; text-align: center;">-</td>
                    </tr>
                </tfoot>
            </table>

            @if($requisition->request_notes)
            <div style="margin-top: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
                <strong>Notes:</strong> {{ $requisition->request_notes }}
            </div>
            @endif
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-md-8">
            <!-- Requested Items - Enhanced Table -->
        @if(auth()->user()->can('requisitions.fulfill') || auth()->user()->hasAnyRole(['ADMIN', 'SUPERADMIN', 'STORE']))
            <form id="fulfill-form" method="POST" action="{{ route('inventory.requisitions.fulfill', $requisition) }}">
            @csrf
            <div class="fulfill-panel" id="fulfill-panel">
                <div class="detail-card">
                    <h5><i class="mdi mdi-package-variant"></i> Requisition Items</h5>
                    
                    {{-- Plan §7.3, §R11 — FIFO Override Warning --}}
                    <div id="fifo-override-banner" class="alert alert-warning mb-3" style="display:none;">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fas fa-exclamation-triangle fa-lg mt-1 text-warning"></i>
                            <div>
                                <strong>FIFO/FEFO Order Override Detected</strong>
                                <div id="fifo-override-detail" class="mt-1 small"></div>
                                @can('store-policy.override-fifo')
                                <button type="button" class="btn btn-sm btn-outline-warning mt-2" id="btn-dismiss-fifo-warning">
                                    <i class="fas fa-unlock-alt me-1"></i> I understand — proceed with out-of-order batch
                                </button>
                                @else
                                <div class="mt-2 small text-danger">
                                    <i class="fas fa-lock me-1"></i> You do not have permission to override FIFO order. Please use batches in the displayed order.
                                </div>
                                @endcan
                            </div>
                        </div>
                    </div>

                    @foreach($requisition->items as $index => $item)
                    @php
                        $sourceStock = \App\Models\StockBatch::where('product_id', $item->product_id)
                            ->where('store_id', $requisition->from_store_id)
                            ->where('current_qty', '>', 0)
                            ->sum('current_qty');
                        $pending = ($item->approved_qty ?? $item->requested_qty) - ($item->fulfilled_qty ?? 0);
                        $fulfillmentPercent = $item->approved_qty > 0
                            ? round((($item->fulfilled_qty ?? 0) / $item->approved_qty) * 100)
                            : 0;
                            
                        $displayStatus = $item->status ?? $requisition->status;
                        if (in_array($displayStatus, ['approved', 'partial']) && $item->approved_qty === 0 && $item->approved_qty !== null) {
                            $displayStatus = 'rejected';
                        }
                    @endphp
                    <div class="item-card mb-3 {{ $displayStatus === 'rejected' ? 'opacity-50' : '' }}" data-allow-decimal="{{ $item->product->allow_decimal_qty ? '1' : '0' }}">
                        <div class="item-header">
                            <div class="item-number">{{ $index + 1 }}</div>
                            <div class="item-product" style="{{ $displayStatus === 'rejected' ? 'text-decoration: line-through;' : '' }}">
                                <div class="product-name">{{ $item->product->product_name }}</div>
                                <div class="product-code">{{ $item->product->product_code }}</div>
                            </div>
                            <div class="item-status">
                                @php
                                    $statusColors = [
                                        'pending' => 'secondary',
                                        'approved' => 'info',
                                        'partial' => 'warning',
                                        'fulfilled' => 'success',
                                        'rejected' => 'danger',
                                        'cancelled' => 'dark'
                                    ];
                                @endphp
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge badge-{{ $statusColors[$displayStatus] ?? 'secondary' }}">
                                        {{ ucfirst($displayStatus) }}
                                    </span>
                                    @if($item->canReverse())
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="reverseItem({{ $item->id }})" title="Reverse {{ ucfirst($displayStatus) }}"><i class="mdi mdi-undo"></i> Reverse</button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="item-body">
                            <div class="qty-flow">
                                <div class="qty-stage">
                                    <div class="qty-label">Requested</div>
                                    <div class="qty-value">{{ $item->requested_qty }}</div>
                                    @if($item->packaging)
                                        <div class="text-muted" style="font-size:0.7rem;">({{ (float)$item->packaging_qty }} {{ $item->packaging->name }})</div>
                                    @elseif($item->product && $item->product->base_unit_name)
                                        <div class="text-muted" style="font-size:0.7rem;">{{ $item->product->base_unit_name }}</div>
                                    @endif
                                </div>
                                <div class="qty-arrow"><i class="mdi mdi-arrow-right"></i></div>
                                <div class="qty-stage {{ $item->approved_qty !== null && $item->approved_qty < $item->requested_qty ? 'adjusted' : '' }}">
                                    <div class="qty-label">Approved</div>
                                    <div class="qty-value">
                                        @if($item->approved_qty !== null)
                                            {{ $item->approved_qty }}
                                            @if($item->approved_qty < $item->requested_qty)
                                                <small class="text-warning">({{ $item->requested_qty - $item->approved_qty }} reduced)</small>
                                            @endif
                                        @else
                                            <span class="text-muted">--</span>
                                        @endif
                                    </div>
                                    @if($item->approved_qty !== null)
                                        @if($item->packaging && $item->packaging->base_unit_qty > 0)
                                            <div class="text-muted" style="font-size:0.7rem;">({{ round($item->approved_qty / $item->packaging->base_unit_qty, 1) }} {{ $item->packaging->name }})</div>
                                        @elseif($item->product && $item->product->base_unit_name)
                                            <div class="text-muted" style="font-size:0.7rem;">{{ $item->product->base_unit_name }}</div>
                                        @endif
                                    @endif
                                </div>
                                <div class="qty-arrow"><i class="mdi mdi-arrow-right"></i></div>
                                <div class="qty-stage {{ ($item->fulfilled_qty ?? 0)>= ($item->approved_qty ?? 0) && $item->approved_qty> 0 ? 'complete' : '' }}">
                                    <div class="qty-label">Fulfilled</div>
                                    <div class="qty-value">{{ $item->fulfilled_qty ?? 0 }}</div>
                                    @if($item->packaging && $item->packaging->base_unit_qty > 0)
                                        <div class="text-muted" style="font-size:0.7rem;">({{ round(($item->fulfilled_qty ?? 0) / $item->packaging->base_unit_qty, 1) }} {{ $item->packaging->name }})</div>
                                    @elseif($item->product && $item->product->base_unit_name)
                                        <div class="text-muted" style="font-size:0.7rem;">{{ $item->product->base_unit_name }}</div>
                                    @endif
                                </div>
                            </div>

                            @if($item->approved_qty> 0)
                            <div class="progress-section">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-{{ $fulfillmentPercent>= 100 ? 'success' : ($fulfillmentPercent> 0 ? 'warning' : 'secondary') }}"
                                         style="width: {{ $fulfillmentPercent }}%"></div>
                                </div>
                                <small class="text-muted">{{ $fulfillmentPercent }}% fulfilled</small>
                            </div>
                            @endif

                            @if(in_array($requisition->status, ['approved', 'partial']) && $pending> 0)
                            <div class="pending-info">
                                <span class="badge badge-outline-warning">
                                    <i class="mdi mdi-clock-outline"></i> {{ $pending }} pending fulfillment
                                </span>
                                <span class="badge badge-outline-{{ $sourceStock>= $pending ? 'success' : 'danger' }}">
                                    <i class="mdi mdi-store"></i> {{ $sourceStock }} available at source
                                </span>
                            </div>
                            @endif

                            @if($item->notes)
                            <div class="item-notes">
                                <small class="text-muted"><i class="mdi mdi-note"></i> {{ $item->notes }}</small>
                            </div>
                            @endif

                            @if(auth()->user()->can('requisitions.fulfill') || auth()->user()->hasAnyRole(['ADMIN', 'SUPERADMIN', 'STORE']))
                            @if(in_array($requisition->status, ['approved', 'partial']) && $pending > 0 && ($item->status ?? 'pending') !== 'rejected')
                            @php
                                $availableBatches = \App\Models\StockBatch::where('product_id', $item->product_id)
                                    ->where('store_id', $requisition->from_store_id)
                                    ->where('current_qty', '>', 0)
                                    ->where('is_active', true)
                                    ->orderBy('expiry_date', 'asc')
                                    ->orderBy('created_at', 'asc')
                                    ->get();
                                $totalAvailable = $availableBatches->sum('current_qty');
                            @endphp
                            <div class="fulfillment-section bg-light p-3 rounded mt-3 border">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong class="text-primary"><i class="mdi mdi-package-variant-closed"></i> Fulfill from Stock</strong>
                                </div>
                                <input type="hidden" name="items[{{ $item->id }}][requisition_item_id]" value="{{ $item->id }}">
                                <input type="hidden" name="items[{{ $item->id }}][product_id]" value="{{ $item->product_id }}">

                                @if($availableBatches->isEmpty())
                                <div class="alert alert-warning mb-0">
                                    <i class="mdi mdi-alert"></i> No stock available at source store
                                </div>
                                @else
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0 bg-white">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Batch</th>
                                                <th class="text-center">Available</th>
                                                <th class="text-center">Expiry</th>
                                                <th class="text-center" style="width: 120px;">Unit</th>
                                                <th class="text-center" style="width: 100px;">Transfer Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($availableBatches as $batchIndex => $batch)
                                            <tr>
                                                <td>
                                                    <strong>{{ $batch->batch_name ?: 'Batch #' . $batch->id }}</strong>
                                                    @if($batch->batch_number)
                                                    <br><small class="text-muted">{{ $batch->batch_number }}</small>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge badge-{{ $batch->current_qty > 10 ? 'success' : ($batch->current_qty > 0 ? 'warning' : 'danger') }}">
                                                        {{ $batch->current_qty }} {{ $item->product->base_unit_name ?? 'pcs' }}
                                                    </span>
                                                    @if($item->packaging)
                                                        <br><small class="text-muted">≈ {{ round($batch->current_qty / $item->packaging->base_unit_qty, 1) }} {{ $item->packaging->name }}</small>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if($batch->expiry_date)
                                                        @php $isExpiringSoon = $batch->expiry_date->diffInDays(now()) < 30; @endphp
                                                        <span class="{{ $isExpiringSoon ? 'text-danger' : '' }}">
                                                            {{ $batch->expiry_date->format('M d, Y') }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">N/A</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <select class="form-control form-control-sm batch-unit-select" 
                                                            data-batch-id="{{ $batch->id }}" 
                                                            data-item-id="{{ $item->id }}"
                                                            data-base-unit="{{ $item->product->base_unit_name ?? 'units' }}"
                                                            style="min-width: 140px;">
                                                        <option value="" data-factor="1">{{ $item->product->base_unit_name ?? 'Base Unit' }}</option>
                                                        @foreach($item->product->packagings as $pkg)
                                                            <option value="{{ $pkg->id }}" data-factor="{{ $pkg->base_unit_qty }}">
                                                                {{ $pkg->name }} ({{ $pkg->base_unit_qty }} {{ $item->product->base_unit_name ?? 'units' }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="text-center">
                                                    <input type="number"
                                                           class="form-control form-control-sm batch-display-qty"
                                                           value="0"
                                                           min="0"
                                                           step="any"
                                                           data-batch-id="{{ $batch->id }}"
                                                           data-item-id="{{ $item->id }}"
                                                           style="font-weight: 600; min-width: 100px;">
                                                    
                                                    <input type="hidden"
                                                           name="items[{{ $item->id }}][batches][{{ $batch->id }}]"
                                                           class="batch-qty-input"
                                                           value="0"
                                                           data-batch-id="{{ $batch->id }}"
                                                           data-available="{{ $batch->current_qty }}"
                                                           data-item-id="{{ $item->id }}"
                                                           data-pending="{{ $pending }}">
                                                    
                                                    <small class="text-muted batch-base-hint" style="display: none; font-size: 0.7rem;"></small>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-light">
                                                <td colspan="3" class="text-right"><strong>Total to Transfer:</strong></td>
                                                <td class="text-center">
                                                    <strong class="item-transfer-total text-primary" data-item-id="{{ $item->id }}">0</strong>
                                                    <span class="text-muted">/ {{ $pending }} {{ $item->product->base_unit_name ?? 'units' }}</span>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                @endif
                            </div>
                            @endif
                            @endif

                        </div>
                    </div>
                    @endforeach
                </div>
                
                @if(auth()->user()->can('requisitions.fulfill') || auth()->user()->hasAnyRole(['ADMIN', 'SUPERADMIN', 'STORE']))
                @if(in_array($requisition->status, ['approved', 'partial']))
                <div class="detail-card sticky-bottom shadow-lg border-top border-primary p-3" style="bottom: 0; z-index: 1000; position: sticky;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted">Total Items to transfer: </span>
                            <strong class="text-primary h4 mb-0" id="total-transfer-qty">0</strong>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg" id="btn-process-transfer">
                            <i class="mdi mdi-truck-delivery"></i> Process Transfer
                        </button>
                    </div>
                </div>
                @endif
                @endif
                </form>

                @if($requisition->request_notes)
                <div class="detail-card">
                    <h5><i class="mdi mdi-note-text"></i> Notes</h5>
                    <p class="mb-0">{{ $requisition->request_notes }}</p>
                </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="col-md-4 detail-sidebar">
                <!-- Details -->
                <div class="detail-card">
                    <h5><i class="mdi mdi-information"></i> Details</h5>
                    <div class="mb-3">
                        <div class="detail-label">Requested By</div>
                        <div class="detail-value">{{ $requisition->requester->name ?? 'N/A' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="detail-label">Created At</div>
                        <div class="detail-value">{{ $requisition->created_at->format('M d, Y H:i') }}</div>
                    </div>
                    @if($requisition->approved_at)
                    <div class="mb-3">
                        <div class="detail-label">Approved By</div>
                        <div class="detail-value">{{ $requisition->approver->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $requisition->approved_at->format('M d, Y H:i') }}</small>
                    </div>
                    @endif
                    @if($requisition->fulfilled_at)
                    <div class="mb-3">
                        <div class="detail-label">Fulfilled By</div>
                        <div class="detail-value">{{ $requisition->fulfiller->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $requisition->fulfilled_at->format('M d, Y H:i') }}</small>
                    </div>
                    @endif
                    @if($requisition->rejected_at)
                    <div class="mb-3">
                        <div class="detail-label text-danger">Rejected</div>
                        <div class="detail-value">{{ $requisition->rejected_at->format('M d, Y H:i') }}</div>
                        @if($requisition->rejection_reason)
                        <small class="text-muted">Reason: {{ $requisition->rejection_reason }}</small>
                        @endif
                    </div>
                    @endif
                </div>

                <!-- Stock Availability -->
                @if(in_array($requisition->status, ['approved', 'partial']))
                <div class="detail-card">
                    <h5><i class="mdi mdi-cube"></i> Quick Stock Summary</h5>
                    <div class="row">
                        @php
                            $totalApproved = $requisition->items->sum('approved_qty');
                            $totalFulfilled = $requisition->items->sum('fulfilled_qty') ?? 0;
                            $totalPending = $totalApproved - $totalFulfilled;
                        @endphp
                        <div class="col-4 text-center">
                            <div class="h4 text-info mb-0">{{ $totalApproved }}</div>
                            <small class="text-muted">Approved</small>
                        </div>
                        <div class="col-4 text-center">
                            <div class="h4 text-success mb-0">{{ $totalFulfilled }}</div>
                            <small class="text-muted">Fulfilled</small>
                        </div>
                        <div class="col-4 text-center">
                            <div class="h4 text-warning mb-0">{{ $totalPending }}</div>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>


<!-- Print Signature Section (hidden on screen) -->
<div class="print-only print-signature-section">
    <div class="signature-grid">
        <div class="signature-box">
            <div class="signature-line">
                <div class="signature-label">Requested By</div>
                <div class="signature-name">{{ $requisition->requester->name ?? 'N/A' }}</div>
                <div class="signature-date">{{ $requisition->created_at->format('M d, Y') }}</div>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                <div class="signature-label">Approved By</div>
                <div class="signature-name">{{ $requisition->approver->name ?? '________________' }}</div>
                <div class="signature-date">{{ $requisition->approved_at ? $requisition->approved_at->format('M d, Y') : 'Date: ____________' }}</div>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                <div class="signature-label">Fulfilled By</div>
                <div class="signature-name">{{ $requisition->fulfiller->name ?? '________________' }}</div>
                <div class="signature-date">{{ $requisition->fulfilled_at ? $requisition->fulfilled_at->format('M d, Y') : 'Date: ____________' }}</div>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                <div class="signature-label">Received By</div>
                <div class="signature-name">________________</div>
                <div class="signature-date">Date: ____________</div>
            </div>
        </div>
    </div>
</div>

<!-- Print Footer (hidden on screen) -->
<div class="print-only print-footer">
    <div>{{ appsettings('site_abbreviation') ?: 'HMS' }} | Requisition #{{ $requisition->requisition_number }} | Printed on {{ now()->format('M d, Y h:i A') }}</div>
    @if(appsettings('footer_text'))
    <div style="font-size: 7pt; margin-top: 3px;">{{ appsettings('footer_text') }}</div>
    @endif
</div>
<!-- Reject Item Modal -->
<div class="modal fade" id="rejectItemModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-close-circle"></i> Reject Item</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove <strong id="rejectItemProductName"></strong> from this requisition?</p>
                <div class="form-group">
                    <label>Reason for Rejection <span class="text-danger">*</span></label>
                    <textarea id="rejectItemNotes" class="form-control" rows="3" placeholder="Enter the reason..."></textarea>
                </div>
                <div class="form-group">
                    <label class="text-muted small">Quick Suggestions:</label><br>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-reason mb-1">Out of Stock</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-reason mb-1">Incorrect Item Requested</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-reason mb-1">Use Substitute Product</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-quick-reason mb-1">Expired Batch Only</button>
                </div>
                <input type="hidden" id="rejectItemId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btnConfirmRejectItem">Confirm Rejection</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
@if(in_array($requisition->status, ['approved', 'partial']))
$(function() {
    // Calculate totals when batch quantities change
    $('.batch-display-qty, .batch-unit-select').on('input change', function() {
        var $row = $(this).closest('tr');
        var $displayInput = $row.find('.batch-display-qty');
        var $unitSelect = $row.find('.batch-unit-select');
        var $hiddenInput = $row.find('.batch-qty-input');
        var $hint = $row.find('.batch-base-hint');
        var $itemContainer = $(this).closest('.item-card');
        var allowDecimal = $itemContainer.data('allow-decimal') == '1';

        var displayVal = parseFloat($displayInput.val()) || 0;
        var factor = parseFloat($unitSelect.find('option:selected').data('factor')) || 1;
        var baseVal = displayVal * factor;
        
        if (!allowDecimal) {
            baseVal = Math.round(baseVal);
        }
        
        var available = parseFloat($hiddenInput.data('available'));
        var itemId = $hiddenInput.data('item-id');
        var pending = parseFloat($hiddenInput.data('pending'));

        // Limit base quantity to available stock
        if (baseVal > available) {
            baseVal = available;
            displayVal = baseVal / factor;
            $displayInput.val(Number.isInteger(displayVal) ? displayVal : displayVal.toFixed(2));
            toastr.warning('Limited to available stock (' + available + ' base units)');
        }

        // Calculate total for this item across other batches first
        var otherBatchesTotal = 0;
        $('.batch-qty-input[data-item-id="' + itemId + '"]').not($hiddenInput).each(function() {
            otherBatchesTotal += parseInt($(this).val()) || 0;
        });

        // Check if current batch baseVal + otherBatchesTotal exceeds pending
        if (baseVal + otherBatchesTotal > pending) {
            baseVal = pending - otherBatchesTotal;
            displayVal = baseVal / factor;
            $displayInput.val(Number.isInteger(displayVal) ? displayVal : displayVal.toFixed(2));
            toastr.warning('Total cannot exceed pending quantity (' + pending + ')');
        }

        // Update hidden input with the final base quantity
        $hiddenInput.val(baseVal);

        // Update hint if factor > 1
        if (factor > 1 && baseVal > 0) {
            var baseUnitName = $unitSelect.data('base-unit') || 'units';
            $hint.text('= ' + baseVal + ' ' + baseUnitName).show();
        } else {
            $hint.hide();
        }

        // Update item total display
        var itemTotal = otherBatchesTotal + baseVal;
        $('.item-transfer-total[data-item-id="' + itemId + '"]').text(itemTotal);

        // ── Plan §7.3, §R11 — FIFO Override Warning ──────────────────────────
        // If this input is for a non-first batch and baseVal > 0,
        // check if an earlier batch for the same item still has untouched stock.
        if (baseVal > 0) {
            var $allBatchInputs = $('.batch-qty-input[data-item-id="' + itemId + '"]');
            var thisIndex = $allBatchInputs.index($hiddenInput);
            var fifoViolation = false;
            var firstBatchName = '';
            $allBatchInputs.each(function(idx) {
                if (idx < thisIndex) {
                    var earlierUsed = parseInt($(this).val()) || 0;
                    var earlierAvail = parseInt($(this).data('available'));
                    if (earlierUsed < earlierAvail) {
                        fifoViolation = true;
                        firstBatchName = $(this).closest('tr').find('td:first strong').text() || ('Batch index ' + idx);
                        return false; // break
                    }
                }
            });
            if (fifoViolation) {
                $('#fifo-override-detail').html(
                    'You are transferring from a later batch while <strong>' + firstBatchName +
                    '</strong> still has remaining stock. FIFO/FEFO order should be observed (Plan §R11).'
                );
                $('#fifo-override-banner').slideDown(200);
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        // Update grand total
        updateGrandTotal();
    });

    $('#btn-dismiss-fifo-warning').on('click', function() {
        $('#fifo-override-banner').slideUp(200);
    });

    function updateGrandTotal() {
        var grandTotal = 0;
        $('.item-transfer-total').each(function() {
            grandTotal += parseInt($(this).text()) || 0;
        });
        $('#total-transfer-qty').text(grandTotal);
    }

    // Form submission validation
    $('#fulfill-form').on('submit', function(e) {
        e.preventDefault();

        var totalTransfer = parseInt($('#total-transfer-qty').text()) || 0;

        if (totalTransfer === 0) {
            toastr.error('Please enter quantities to transfer');
            return false;
        }

        if (!confirm('Transfer ' + totalTransfer + ' items? This action cannot be undone.')) {
            return false;
        }

        // Show loading state
        var btn = $('#btn-process-transfer');
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message || 'Transfer completed successfully');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    toastr.error(response.message || 'Transfer failed');
                    btn.prop('disabled', false).html('<i class="mdi mdi-truck-delivery"></i> Process Transfer');
                }
            },
            error: function(xhr) {
                var message = 'An error occurred';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                toastr.error(message);
                btn.prop('disabled', false).html('<i class="mdi mdi-truck-delivery"></i> Process Transfer');
            }
        });

        return false;
    });
});
@endif

@if($requisition->status === 'pending')
// Approval quantity validation
$(function() {
    $('.qty-input').on('input', function() {
        var val = parseInt($(this).val()) || 0;
        var requested = parseInt($(this).data('requested'));
        var available = parseInt($(this).data('available'));

        // Reset classes
        $(this).removeClass('warning danger');

        if (val> available) {
            $(this).addClass('danger');
            $(this).val(available);
            toastr.warning('Adjusted to available stock (' + available + ')');
        } else if (val < requested && val> 0) {
            $(this).addClass('warning');
        }
    });
});

function submitApproval() {
    var hasItems = false;
    var formData = $('#approval-form').serialize();

    // Check if at least one item has quantity> 0
    $('.qty-input').each(function() {
        if (parseInt($(this).val())> 0) {
            hasItems = true;
        }
    });

    if (!hasItems) {
        toastr.error('At least one item must have a quantity greater than 0');
        return;
    }

    if (!confirm('Approve this requisition with the specified quantities?')) {
        return;
    }

    $.ajax({
        url: '{{ route("inventory.requisitions.approve", $requisition) }}',
        method: 'POST',
        data: formData + '&_token={{ csrf_token() }}',
        success: function(response) {
            toastr.success(response.message || 'Requisition approved successfully');
            setTimeout(function() {
                location.reload();
            }, 1000);
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to approve requisition');
        }
    });
}
@endif

function rejectRequisition() {
    var reason = prompt('Please enter rejection reason:');
    if (reason) {
        $.post('{{ route("inventory.requisitions.reject", $requisition) }}', {
            _token: '{{ csrf_token() }}',
            reason: reason
        })
            .done(function(response) {
                toastr.success(response.message || 'Requisition rejected');
                location.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to reject');
            });
    }
}

function approveRequisitionGlobal() {
    if (!confirm('Are you sure you want to approve this entire requisition?')) {
        return;
    }

    $.ajax({
        url: '{{ route("inventory.requisitions.approve", $requisition) }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            toastr.success(response.message || 'Requisition approved');
            setTimeout(function() {
                location.reload();
            }, 1000);
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to approve requisition');
        }
    });
}

function approveRequisitionGlobal() {
    if (!confirm('Are you sure you want to approve this entire requisition?')) {
        return;
    }

    $.ajax({
        url: '{{ route("inventory.requisitions.approve", $requisition) }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            toastr.success(response.message || 'Requisition approved');
            setTimeout(function() {
                location.reload();
            }, 1000);
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to approve requisition');
        }
    });
}

function reverseItem(itemId) {
    Swal.fire({
        title: 'Reverse Item',
        text: 'Are you sure you want to reverse this item back to pending?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="mdi mdi-undo"></i> Yes, Reverse it'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/inventory/requisitions/{{ $requisition->id }}/items/${itemId}/reverse`,
                type: 'PATCH',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    toastr.success(response.message || 'Item reversed');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to reverse item');
                }
            });
        }
    });
}

// Calculate approval base quantity
$(document).on('input change', '.approve-display-qty, .approve-unit-select', function() {
    var wrapper = $(this).closest('.approval-item');
    var displayQty = parseFloat(wrapper.find('.approve-display-qty').val()) || 0;
    var select = wrapper.find('.approve-unit-select option:selected');
    var factor = parseFloat(select.data('factor')) || 1;
    
    var baseQty = Math.round(displayQty * factor * 10) / 10;
    wrapper.find('.qty-input').val(baseQty);
    
    // Update labels and fractions
    var pkgName = select.data('name');
    wrapper.find('.approve-unit-label').text('Total ' + pkgName);
    wrapper.find('.approve-unit-fraction-numerator').text(displayQty);
    
    var reqBase = parseInt(wrapper.find('.qty-input').data('requested')) || 0;
    var reqUnit = factor > 0 ? Math.round((reqBase / factor) * 10) / 10 : 0;
    wrapper.find('.approve-unit-fraction-denominator').text(reqUnit);
    
    wrapper.find('.approve-base-display').text(baseQty);
    
    // Update data attributes on the approve button
    var btn = wrapper.find('.btn-approve-item');
    btn.data('pkg-name', select.data('name'));
    btn.data('display-qty', displayQty);
});

// Approve Item Logic
$(document).on('click', '.btn-approve-item', function() {
    var btn = $(this);
    var itemId = btn.data('item-id');
    var productName = btn.data('product');
    
    var card = btn.closest('.approval-item');
    var qtyInput = card.find('.qty-input');
    var baseQty = qtyInput.val();
    var baseUnitName = btn.data('unit') || 'Units';
    
    var displayQty = btn.data('display-qty');
    if (displayQty === undefined) {
        displayQty = card.find('.approve-display-qty').val();
    }
    
    var pkgName = btn.data('pkg-name');
    if (pkgName === undefined) {
        pkgName = card.find('.approve-unit-select option:selected').data('name');
    }
    
    if (!baseQty || parseFloat(baseQty) < 0) {
        toastr.error('Please specify a valid approval quantity.');
        return;
    }
    
    var unitStr = pkgName ? `(${pkgName})` : `(${baseUnitName})`;
    
    Swal.fire({
        title: 'Approve Item',
        html: `
            <div class="text-left">
                <p>You are about to approve <strong>${displayQty} ${unitStr}</strong> of <br><strong class="text-primary">${productName}</strong>.</p>
                <div class="alert alert-info py-2"><i class="mdi mdi-information-outline"></i> This will immediately mark this specific item as approved (Total: ${baseQty} ${baseUnitName}).</div>
                <div class="form-group mt-3">
                    <label>Approval Notes (Optional):</label>
                    <textarea id="swal-approve-notes" class="form-control" rows="2" placeholder="Add any notes..."></textarea>
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="mdi mdi-check-circle"></i> Yes, Approve Item'
    }).then((result) => {
        if (result.isConfirmed) {
            var notes = document.getElementById('swal-approve-notes').value;
            
            btn.prop('disabled', true).html('<i class="mdi mdi-spin mdi-loading"></i>');
            card.find('.btn-reject-item').prop('disabled', true);
            
            $.ajax({
                url: '{{ url("inventory/requisitions") }}/{{ $requisition->id }}/items/' + itemId + '/approve',
                type: 'PATCH',
                data: {
                    _token: '{{ csrf_token() }}',
                    approved_qty: baseQty,
                    notes: notes
                },
                success: function(res) {
                    if(res.success) {
                        toastr.success(res.message);
                        if(res.auto_closed) {
                            Swal.fire('Requisition Processed', 'All items have been processed, so the requisition status has been automatically updated.', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            qtyInput.prop('readonly', true).removeClass('warning danger').addClass('is-valid');
                            card.find('.btn-group').html('<span class="badge badge-success p-2" style="font-size:0.85rem;"><i class="mdi mdi-check-all"></i> Approved</span>');
                            card.css('background-color', '#f8fff9');
                        }
                    } else {
                        toastr.error(res.message);
                        btn.prop('disabled', false).html('<i class="mdi mdi-check-circle"></i> Approve');
                        card.find('.btn-reject-item').prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    var msg = 'An error occurred.';
                    if(xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    toastr.error(msg);
                    btn.prop('disabled', false).html('<i class="mdi mdi-check-circle"></i> Approve');
                    card.find('.btn-reject-item').prop('disabled', false);
                }
            });
        }
    });
});

// Reject Item Logic
$(document).on('click', '.btn-reject-item', function() {
    var itemId = $(this).data('item-id');
    var productName = $(this).data('product');
    
    $('#rejectItemId').val(itemId);
    $('#rejectItemProductName').text(productName);
    $('#rejectItemNotes').val('');
    $('#rejectItemModal').modal('show');
});

$(document).on('click', '.btn-quick-reason', function() {
    var reason = $(this).text();
    $('#rejectItemNotes').val(reason);
});

$('#btnConfirmRejectItem').on('click', function() {
    var itemId = $('#rejectItemId').val();
    var notes = $('#rejectItemNotes').val().trim();
    
    if (!notes) {
        toastr.error('Please enter a reason for rejection.');
        return;
    }
    
    var btn = $(this);
    btn.prop('disabled', true).html('<i class="mdi mdi-spin mdi-loading"></i> Processing...');
    
    $.ajax({
        url: '{{ url("inventory/requisitions") }}/{{ $requisition->id }}/items/' + itemId + '/reject',
        type: 'PATCH',
        data: {
            _token: '{{ csrf_token() }}',
            notes: notes
        },
        success: function(res) {
            if(res.success) {
                toastr.success(res.message);
                if(res.auto_closed) {
                    Swal.fire('Requisition Rejected', 'All items have been rejected, so the requisition has been automatically rejected.', 'info').then(() => {
                        location.reload();
                    });
                } else {
                    location.reload();
                }
            } else {
                toastr.error(res.message || 'Failed to reject item.');
                btn.prop('disabled', false).text('Confirm Rejection');
            }
        },
        error: function(xhr) {
            var msg = xhr.responseJSON?.message || 'An error occurred while rejecting the item.';
            toastr.error(msg);
            btn.prop('disabled', false).text('Confirm Rejection');
        }
    });
});

function cancelRequisition() {
    if (confirm('Cancel this requisition?')) {
        $.post('{{ route("inventory.requisitions.cancel", $requisition) }}', { _token: '{{ csrf_token() }}' })
            .done(function(response) {
                toastr.success(response.message || 'Requisition cancelled');
                location.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to cancel');
            });
    }
}

// Print function with options
function printRequisition(mode) {
    mode = mode || 'full';

    // Add print mode class to body
    $('body').addClass('printing-' + mode);

    // Store original title
    var originalTitle = document.title;
    document.title = 'Requisition_{{ $requisition->requisition_number }}_{{ now()->format("Ymd") }}';

    // If compact mode, hide item details
    if (mode === 'compact') {
        $('.item-body .qty-flow').addClass('print-compact-hide');
        $('.item-body .progress-section').addClass('print-compact-hide');
    }

    // Trigger print
    window.print();

    // Cleanup after print
    setTimeout(function() {
        document.title = originalTitle;
        $('body').removeClass('printing-' + mode);
        $('.print-compact-hide').removeClass('print-compact-hide');
    }, 500);
}

// Handle print events for better PDF filename
window.addEventListener('beforeprint', function() {
    console.log('Printing requisition: {{ $requisition->requisition_number }}');
});

window.addEventListener('afterprint', function() {
    console.log('Print completed or cancelled');
});
</script>
@endsection
