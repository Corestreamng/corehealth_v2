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

        <!-- Action Buttons -->
        <div class="mb-4">
            <a href="{{ route('inventory.requisitions.index') }}" class="btn btn-secondary btn-sm">
                <i class="mdi mdi-arrow-left"></i> Back
            </a>

            @if($requisition->status === 'pending')
                @if(auth()->id() == $requisition->requested_by)
                <button type="button" class="btn btn-warning btn-sm" onclick="cancelRequisition()">
                    <i class="mdi mdi-cancel"></i> Cancel Request
                </button>
                @endif
            @endif

            @if(in_array($requisition->status, ['approved', 'partial']))
                @can('requisitions.fulfill')
                <a href="#fulfill-panel" class="btn btn-primary btn-sm">
                    <i class="mdi mdi-package"></i> Fulfill Items
                </a>
                @endcan
            @endif

            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printRequisition()">
                <i class="mdi mdi-printer"></i> Print
            </button>
            <div class="btn-group no-print">
                <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
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

        @if($requisition->status === 'pending')
        @can('requisitions.approve')
        <!-- Approval Panel -->
        <div class="approval-panel">
            <h5><i class="mdi mdi-clipboard-check"></i> Review & Approve Requisition</h5>
            <p class="text-muted mb-3">Review the requested quantities and adjust if needed. You can approve partial quantities based on available stock.</p>

            <form id="approval-form">
                @foreach($requisition->items as $item)
                @php
                    $sourceStock = \App\Models\StockBatch::where('product_id', $item->product_id)
                        ->where('store_id', $requisition->from_store_id)
                        ->where('current_qty', '>', 0)
                        ->sum('current_qty');
                    $destStock = \App\Models\StockBatch::where('product_id', $item->product_id)
                        ->where('store_id', $requisition->to_store_id)
                        ->where('current_qty', '>', 0)
                        ->sum('current_qty');
                    $canFulfill = $sourceStock >= $item->requested_qty;
                @endphp
                <div class="approval-item">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <div class="product-name">{{ $item->product->product_name }}</div>
                            <div class="product-code">{{ $item->product->product_code }}</div>
                            <div class="mt-2">
                                <span class="requested-badge">
                                    <i class="mdi mdi-cart"></i> Requested: <strong>{{ $item->requested_qty }}</strong>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stock-comparison">
                                <div class="stock-box source">
                                    <div class="label"><i class="mdi mdi-store"></i> {{ $requisition->fromStore->store_name }}</div>
                                    <div class="value">{{ $sourceStock }}</div>
                                    <div class="label">Available</div>
                                </div>
                                <div class="stock-box destination">
                                    <div class="label"><i class="mdi mdi-store"></i> {{ $requisition->toStore->store_name }}</div>
                                    <div class="value">{{ $destStock }}</div>
                                    <div class="label">Current Stock</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="qty-adjustment">
                                <label>Approve Qty:</label>
                                <input type="number"
                                       name="approved_qtys[{{ $item->id }}]"
                                       class="qty-input {{ !$canFulfill ? ($sourceStock > 0 ? 'warning' : 'danger') : '' }}"
                                       value="{{ min($item->requested_qty, $sourceStock) }}"
                                       min="0"
                                       max="{{ $item->requested_qty }}"
                                       data-requested="{{ $item->requested_qty }}"
                                       data-available="{{ $sourceStock }}">
                                <span class="text-muted">/ {{ $item->requested_qty }}</span>
                            </div>
                            @if(!$canFulfill)
                            <div class="stock-warning">
                                <i class="mdi mdi-alert"></i>
                                @if($sourceStock == 0)
                                    No stock at source store!
                                @else
                                    Only {{ $sourceStock }} available ({{ $item->requested_qty - $sourceStock }} short)
                                @endif
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach

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
                    <tr>
                        <td style="border: 1px solid #333; padding: 8px; text-align: center;">{{ $index + 1 }}</td>
                        <td style="border: 1px solid #333; padding: 8px;">
                            <strong>{{ $item->product->product_name }}</strong><br>
                            <small style="color: #666;">{{ $item->product->product_code }}</small>
                        </td>
                        <td style="border: 1px solid #333; padding: 8px; text-align: center;">{{ $item->product->unit_of_measure ?? 'Unit' }}</td>
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
                <div class="detail-card">
                    <h5><i class="mdi mdi-package-variant"></i> Requisition Items</h5>

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
                    @endphp
                    <div class="item-card mb-3">
                        <div class="item-header">
                            <div class="item-number">{{ $index + 1 }}</div>
                            <div class="item-product">
                                <div class="product-name">{{ $item->product->product_name }}</div>
                                <div class="product-code">{{ $item->product->product_code }}</div>
                            </div>
                            <div class="item-status">
                                @php
                                    $itemStatus = $item->status ?? $requisition->status;
                                    $statusColors = [
                                        'pending' => 'secondary',
                                        'approved' => 'info',
                                        'partial' => 'warning',
                                        'fulfilled' => 'success',
                                        'rejected' => 'danger',
                                        'cancelled' => 'dark'
                                    ];
                                @endphp
                                <span class="badge badge-{{ $statusColors[$itemStatus] ?? 'secondary' }}">
                                    {{ ucfirst($itemStatus) }}
                                </span>
                            </div>
                        </div>

                        <div class="item-body">
                            <div class="qty-flow">
                                <div class="qty-stage">
                                    <div class="qty-label">Requested</div>
                                    <div class="qty-value">{{ $item->requested_qty }}</div>
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
                                </div>
                                <div class="qty-arrow"><i class="mdi mdi-arrow-right"></i></div>
                                <div class="qty-stage {{ ($item->fulfilled_qty ?? 0) >= ($item->approved_qty ?? 0) && $item->approved_qty > 0 ? 'complete' : '' }}">
                                    <div class="qty-label">Fulfilled</div>
                                    <div class="qty-value">{{ $item->fulfilled_qty ?? 0 }}</div>
                                </div>
                            </div>

                            @if($item->approved_qty > 0)
                            <div class="progress-section">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-{{ $fulfillmentPercent >= 100 ? 'success' : ($fulfillmentPercent > 0 ? 'warning' : 'secondary') }}"
                                         style="width: {{ $fulfillmentPercent }}%"></div>
                                </div>
                                <small class="text-muted">{{ $fulfillmentPercent }}% fulfilled</small>
                            </div>
                            @endif

                            @if(in_array($requisition->status, ['approved', 'partial']) && $pending > 0)
                            <div class="pending-info">
                                <span class="badge badge-outline-warning">
                                    <i class="mdi mdi-clock-outline"></i> {{ $pending }} pending fulfillment
                                </span>
                                <span class="badge badge-outline-{{ $sourceStock >= $pending ? 'success' : 'danger' }}">
                                    <i class="mdi mdi-store"></i> {{ $sourceStock }} available at source
                                </span>
                            </div>
                            @endif

                            @if($item->notes)
                            <div class="item-notes">
                                <small class="text-muted"><i class="mdi mdi-note"></i> {{ $item->notes }}</small>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

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

<!-- Fulfillment Panel (Inline, not modal) -->
@if(in_array($requisition->status, ['approved', 'partial']))
@can('requisitions.fulfill')
@php
    $hasPendingItems = $requisition->items->contains(function($item) {
        return ($item->approved_qty ?? 0) - ($item->fulfilled_qty ?? 0) > 0;
    });
@endphp
@if($hasPendingItems)
<div class="container-fluid">
    <div class="fulfill-panel" id="fulfill-panel">
        <h5><i class="mdi mdi-package-variant-closed"></i> Fulfill Requisition Items</h5>
        <p class="text-muted mb-3">
            Transfer items from <strong class="text-success">{{ $requisition->fromStore->store_name }}</strong>
            to <strong class="text-info">{{ $requisition->toStore->store_name }}</strong>.
            Select batches and quantities for each item.
        </p>

        <form id="fulfill-form" method="POST" action="{{ route('inventory.requisitions.fulfill', $requisition) }}">
            @csrf
            @foreach($requisition->items as $item)
            @php
                $approvedQty = $item->approved_qty ?? 0;
                $fulfilledQty = $item->fulfilled_qty ?? 0;
                $pendingQty = $approvedQty - $fulfilledQty;
                $fulfillPercent = $approvedQty > 0 ? round(($fulfilledQty / $approvedQty) * 100) : 0;

                // Get available batches from source store
                $availableBatches = \App\Models\StockBatch::where('product_id', $item->product_id)
                    ->where('store_id', $requisition->from_store_id)
                    ->where('current_qty', '>', 0)
                    ->where('is_active', true)
                    ->orderBy('expiry_date', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->get();
                $totalAvailable = $availableBatches->sum('current_qty');
            @endphp
            @if($pendingQty > 0)
            <div class="fulfill-item">
                <div class="fulfill-item-header">
                    <div>
                        <div class="h6 mb-1">{{ $item->product->product_name }}</div>
                        <small class="text-muted">{{ $item->product->product_code }}</small>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-{{ $totalAvailable >= $pendingQty ? 'success' : 'warning' }}">
                            {{ $totalAvailable }} available
                        </span>
                    </div>
                </div>

                <!-- Progress visualization -->
                <div class="fulfill-progress-bar">
                    <small class="text-muted" style="min-width: 80px;">{{ $fulfilledQty }}/{{ $approvedQty }}</small>
                    <div class="progress">
                        <div class="progress-bar bg-success" style="width: {{ $fulfillPercent }}%"></div>
                        <div class="progress-bar bg-warning" style="width: {{ min(100 - $fulfillPercent, ($pendingQty/$approvedQty)*100) }}%; opacity: 0.5;"></div>
                    </div>
                    <small class="text-muted" style="min-width: 80px; text-align: right;">{{ $pendingQty }} pending</small>
                </div>

                <!-- Batch Selection -->
                <div class="batch-selector">
                    <div class="mb-2"><strong><i class="mdi mdi-package"></i> Select Batch(es) to Transfer:</strong></div>
                    <input type="hidden" name="items[{{ $item->id }}][requisition_item_id]" value="{{ $item->id }}">
                    <input type="hidden" name="items[{{ $item->id }}][product_id]" value="{{ $item->product_id }}">

                    @if($availableBatches->isEmpty())
                    <div class="alert alert-warning mb-0">
                        <i class="mdi mdi-alert"></i> No stock available at source store
                    </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Batch</th>
                                    <th class="text-center">Available</th>
                                    <th class="text-center">Expiry</th>
                                    <th class="text-center">Transfer Qty</th>
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
                                            {{ $batch->current_qty }}
                                        </span>
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
                                        <input type="number"
                                               name="items[{{ $item->id }}][batches][{{ $batch->id }}]"
                                               class="form-control form-control-sm batch-qty-input"
                                               value="0"
                                               min="0"
                                               max="{{ min($batch->current_qty, $pendingQty) }}"
                                               data-batch-id="{{ $batch->id }}"
                                               data-available="{{ $batch->current_qty }}"
                                               data-item-id="{{ $item->id }}"
                                               data-pending="{{ $pendingQty }}">
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="bg-light">
                                    <td colspan="3" class="text-right"><strong>Total to Transfer:</strong></td>
                                    <td class="text-center">
                                        <strong class="item-transfer-total text-primary" data-item-id="{{ $item->id }}">0</strong>
                                        <span class="text-muted">/ {{ $pendingQty }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
            @endif
            @endforeach

            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                <div>
                    <span class="text-muted">Items to transfer: </span>
                    <strong class="text-primary" id="total-transfer-qty">0</strong>
                </div>
                <button type="submit" class="btn btn-primary btn-lg" id="btn-process-transfer">
                    <i class="mdi mdi-truck-delivery"></i> Process Transfer
                </button>
            </div>
        </form>
    </div>
</div>
@endif
@endcan
@endif

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
@endsection

@section('scripts')
<script>
@if(in_array($requisition->status, ['approved', 'partial']))
$(function() {
    // Calculate totals when batch quantities change
    $('.batch-qty-input').on('input', function() {
        var $input = $(this);
        var val = parseInt($input.val()) || 0;
        var available = parseInt($input.data('available'));
        var itemId = $input.data('item-id');
        var pending = parseInt($input.data('pending'));

        // Limit to available quantity
        if (val > available) {
            $input.val(available);
            val = available;
            toastr.warning('Limited to available stock (' + available + ')');
        }

        // Calculate total for this item across all batches
        var itemTotal = 0;
        $('input[data-item-id="' + itemId + '"]').each(function() {
            itemTotal += parseInt($(this).val()) || 0;
        });

        // Check if exceeding pending
        if (itemTotal > pending) {
            var excess = itemTotal - pending;
            $input.val(Math.max(0, val - excess));
            toastr.warning('Total cannot exceed pending quantity (' + pending + ')');
            itemTotal = pending;
        }

        // Update item total display
        $('.item-transfer-total[data-item-id="' + itemId + '"]').text(itemTotal);

        // Update grand total
        updateGrandTotal();
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

        if (val > available) {
            $(this).addClass('danger');
            $(this).val(available);
            toastr.warning('Adjusted to available stock (' + available + ')');
        } else if (val < requested && val > 0) {
            $(this).addClass('warning');
        }
    });
});

function submitApproval() {
    var hasItems = false;
    var formData = $('#approval-form').serialize();

    // Check if at least one item has quantity > 0
    $('.qty-input').each(function() {
        if (parseInt($(this).val()) > 0) {
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
