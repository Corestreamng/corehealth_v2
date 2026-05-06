@extends('admin.layouts.app')
@section('title', 'Tally Card')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Tally Card')

@php
    $hosColor = appsettings('hos_color') ?? '#0066cc';
@endphp

@section('content')
    <style>
        /* ===== Tally Card Styles ===== */
        .tally-page {
            font-family: 'Inter', -apple-system, sans-serif;
        }

        /* Filter bar */
        .tally-filter-bar {
            background: #fff;
            border-radius: 10px;
            padding: 14px 20px;
            margin-bottom: 18px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
        }

        .tally-filter-bar .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .tally-filter-bar label {
            font-size: 0.72rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 0;
        }

        /* Axis toggle */
        .axis-toggle {
            display: flex;
            background: #f0f2f5;
            border-radius: 8px;
            padding: 3px;
            gap: 2px;
        }

        .axis-toggle button {
            border: none;
            background: transparent;
            border-radius: 6px;
            padding: 5px 14px;
            font-size: 0.83rem;
            font-weight: 500;
            color: #6c757d;
            cursor: pointer;
            transition: all .15s;
        }

        .axis-toggle button.active {
            background: #fff;
            color: {{ $hosColor }};
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
            font-weight: 600;
        }

        /* Summary strip */
        .summary-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .summary-chip {
            background: #fff;
            border-radius: 8px;
            padding: 10px 18px;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.07);
            display: flex;
            flex-direction: column;
            min-width: 110px;
        }

        .summary-chip .chip-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .summary-chip .chip-value {
            font-size: 1.35rem;
            font-weight: 700;
        }

        .summary-chip.in .chip-value {
            color: #dc3545;
        }

        .summary-chip.out .chip-value {
            color: #0d6efd;
        }

        .summary-chip.net .chip-value {
            color: #0a6640;
        }

        .summary-chip.count .chip-value {
            color: #6c757d;
        }

        /* Tally table */
        .tally-table-wrapper {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .tally-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.875rem;
        }

        .tally-table thead th {
            background: #f8f9fa;
            font-size: 0.72rem;
            font-weight: 700;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 10px 14px;
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }

        .tally-table tbody tr {
            border-bottom: 1px solid #f0f2f5;
            transition: background .1s;
        }

        .tally-table tbody tr:hover {
            background: #fafbfc;
        }

        .tally-table td {
            padding: 9px 14px;
            vertical-align: middle;
        }

        /* Direction left border */
        .tally-table tr.dir-in td:first-child {
            border-left: 3px solid #dc3545;
        }

        .tally-table tr.dir-out td:first-child {
            border-left: 3px solid #0d6efd;
        }

        .tally-table tr.dir-transfer_in td:first-child {
            border-left: 3px solid #9d174d;
        }

        .tally-table tr.dir-transfer_out td:first-child {
            border-left: 3px solid #3730a3;
        }

        .tally-table tr.dir-return td:first-child {
            border-left: 3px solid #d97706;
        }

        .tally-table tr.dir-expired td:first-child {
            border-left: 3px solid #9ca3af;
        }

        .tally-table tr.dir-damaged td:first-child {
            border-left: 3px solid #6b7280;
        }

        .tally-table tr.dir-adjustment td:first-child {
            border-left: 3px solid #fd7e14;
        }

        /* Qty columns */
        .qty-in {
            color: #dc3545;
            font-weight: 700;
        }

        .qty-out {
            color: #0d6efd;
            font-weight: 700;
        }

        .balance-col {
            font-weight: 700;
            color: #212529;
        }

        /* Type badge */
        .type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .type-badge.in {
            background: #fee2e2;
            color: #b91c1c;
        }

        .type-badge.po_receipt {
            background: #fce7f3;
            color: #9d174d;
        }

        .type-badge.transfer_in {
            background: #ffe4f0;
            color: #9d174d;
        }

        .type-badge.return {
            background: #fef3c7;
            color: #92400e;
        }

        .type-badge.out {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .type-badge.transfer_out {
            background: #e0e7ff;
            color: #3730a3;
        }

        .type-badge.expired {
            background: #f3f4f6;
            color: #4b5563;
        }

        .type-badge.damaged {
            background: #fee2e2;
            color: #6b7280;
        }

        .type-badge.adjustment,
        .type-badge.adjustment_in,
        .type-badge.adjustment_out {
            background: #fef9c3;
            color: #78350f;
        }

        .type-badge.adjustment_in {
            background: #d1fae5;
            color: #065f46;
        }

        .type-badge.adjustment_out {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Empty state */
        .tally-empty {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        .tally-empty i {
            font-size: 3rem;
            display: block;
            margin-bottom: 12px;
            opacity: .4;
        }

        /* Product chips (store-axis) */
        .product-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 16px;
        }

        .product-chip {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 500;
            background: #f0f2f5;
            color: #374151;
            cursor: pointer;
            border: 1.5px solid transparent;
            transition: all .15s;
        }

        .product-chip:hover {
            background: #e5e7eb;
        }

        .product-chip.active {
            background: #dbeafe;
            color: #1d4ed8;
            border-color: #93c5fd;
            font-weight: 700;
        }

        .product-chip.all {
            background: {{ $hosColor }};
            color: #fff;
            border-color: {{ $hosColor }};
            font-weight: 700;
        }

        /* Pending panels */
        .pending-section {
            margin-top: 28px;
        }

        .pending-panel {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.08);
            margin-bottom: 18px;
            overflow: hidden;
        }

        .pending-panel-header {
            padding: 13px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f0f2f5;
        }

        .pending-panel-header h6 {
            margin: 0;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pending-panel-body {
            padding: 0;
        }

        .req-row,
        .po-row {
            padding: 10px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #f8f9fa;
            font-size: 0.84rem;
        }

        .req-row:last-child,
        .po-row:last-child {
            border-bottom: none;
        }

        .req-row .req-ref {
            font-weight: 700;
            font-size: 0.82rem;
            color: {{ $hosColor }};
        }

        .req-row .req-meta {
            color: #9ca3af;
            font-size: 0.75rem;
        }

        .status-badge {
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.partial,
        .status-badge.partially_received,
        .status-badge.partial_received {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Floating toolbar */
        .floating-toolbar {
            position: fixed;
            bottom: 28px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(30, 35, 45, 0.95);
            backdrop-filter: blur(8px);
            border-radius: 40px;
            padding: 10px 18px;
            display: flex;
            gap: 10px;
            align-items: center;
            box-shadow: 0 6px 28px rgba(0, 0, 0, 0.25);
            z-index: 1040;
        }

        .floating-toolbar .toolbar-btn {
            border: none;
            border-radius: 20px;
            padding: 7px 16px;
            font-size: 0.83rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .floating-toolbar .toolbar-btn:hover {
            transform: translateY(-1px);
        }

        .tb-req {
            background: #3b82f6;
            color: #fff;
        }

        .tb-batch {
            background: #10b981;
            color: #fff;
        }

        .tb-po {
            background: #8b5cf6;
            color: #fff;
        }

        /* Loading overlay */
        .tally-loading {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7);
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 10px;
        }

        .tally-loading.show {
            display: flex;
        }

        /* By-product summary table (store axis) */
        .by-product-table {
            font-size: 0.8rem;
        }

        .by-product-table td,
        .by-product-table th {
            padding: 6px 10px;
        }

        @media (max-width: 768px) {
            .tally-filter-bar {
                flex-direction: column;
            }

            .floating-toolbar {
                width: calc(100% - 32px);
                border-radius: 12px;
                justify-content: center;
                bottom: 16px;
            }
        }

        /* ===== Select2 z-index fix for all modals ===== */
        .select2-container--open { z-index: 9999 !important; }
        .modal .select2-container { width: 100% !important; }
        .modal .select2-container .select2-selection--single {
            height: calc(1.5em + 0.75rem + 2px);
            padding: 0.375rem 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
        }
        .modal .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            padding-left: 0;
        }
        .modal .select2-container .select2-selection--single .select2-selection__arrow {
            height: calc(1.5em + 0.75rem);
        }

        /* ===== Adjust Stock modal ===== */
        .adj-batch-card {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: border-color .15s, background .15s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .adj-batch-card:hover { border-color: #f59e0b; background: #fffbeb; }
        .adj-batch-card.selected { border-color: #f59e0b; background: #fffbeb; }
        .adj-batch-card .adj-batch-name { font-weight: 600; font-size: 0.9rem; }
        .adj-batch-card .adj-batch-meta { font-size: 0.78rem; color: #6b7280; margin-top: 2px; }
        .adj-batch-card .adj-batch-qty { font-size: 1.1rem; font-weight: 700; color: #10b981; white-space: nowrap; }
        .adj-batch-card .adj-expiry-warn { font-size: 0.75rem; color: #ef4444; font-weight: 600; }
        .adj-batch-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 14px 16px;
        }
        .adj-batch-info .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 0.88rem;
        }
        .adj-batch-info .info-row:last-child { margin-bottom: 0; }
        .adj-batch-info .info-label { color: #6c757d; }
        .adj-batch-info .info-value { font-weight: 600; }
        .adjustment-type-btn.selected { border-width: 2px !important; }
        .adjustment-type-btn.add.selected { background: #d4edda; border-color: #28a745 !important; }
        .adjustment-type-btn.subtract.selected { background: #f8d7da; border-color: #dc3545 !important; }
        .font-weight-600 { font-weight: 600; }
    </style>

    <div class="tally-page">

        {{-- ═══════════════ HEADER ═══════════════ --}}
        <div class="workbench-header-card"
            style="background: linear-gradient(135deg, {{ $hosColor }} 0%, #5a9fd4 100%); border-radius: 12px; padding: 22px 28px; color: #fff; margin-bottom: 22px; box-shadow: 0 4px 18px rgba(0,0,0,0.12);">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h2 class="mb-0" style="font-weight: 700; font-size: 1.5rem;">
                        <i class="mdi mdi-table-large mr-2"></i>Tally Card
                    </h2>
                    <p class="mb-0" style="opacity: .85; font-size: 0.9rem; margin-top: 4px;">
                        Live stock ledger — view all movements, manage requisitions, batches &amp; purchase orders
                    </p>
                </div>
                <a href="{{ route('inventory.store-workbench.index') }}{{ $selectedStore ? '?store_id=' . $selectedStore->id : '' }}"
                    class="btn btn-light btn-sm" style="border-radius: 8px;">
                    <i class="mdi mdi-arrow-left mr-1"></i> Workbench
                </a>
            </div>
        </div>

        {{-- ═══════════════ FILTER BAR ═══════════════ --}}
        <div class="tally-filter-bar">
            {{-- Axis toggle --}}
            <div class="filter-group">
                <label>View Axis</label>
                <div class="axis-toggle">
                    <button type="button" id="axis-product" class="axis-toggle-btn {{ ($axis ?? 'product') === 'product' ? 'active' : '' }}" data-axis="product">
                        <i class="mdi mdi-cube-outline"></i> Product
                    </button>
                    <button type="button" id="axis-store" class="axis-toggle-btn {{ ($axis ?? 'product') === 'store' ? 'active' : '' }}" data-axis="store">
                        <i class="mdi mdi-store"></i> Store
                    </button>
                </div>
            </div>

            {{-- Store selector --}}
            <div class="filter-group" style="min-width:200px;">
                <label for="filter-store">Store</label>
                <select id="filter-store" class="form-control form-control-sm">
                    <option value="">— Select Store —</option>
                    @foreach ($stores as $s)
                        <option value="{{ $s->id }}"
                            {{ $selectedStore && $selectedStore->id == $s->id ? 'selected' : '' }}>
                            {{ $s->store_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Product selector (product axis only) --}}
            <div class="filter-group" id="product-filter-group"
                style="min-width:220px; {{ ($axis ?? 'product') === 'store' ? 'display:none!important;' : '' }}">
                <label for="filter-product">Product</label>
                <select id="filter-product" class="form-control form-control-sm">
                    <option value="">— Select Product —</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}"
                            {{ $selectedProduct && $selectedProduct->id == $p->id ? 'selected' : '' }}>
                            {{ $p->product_name }}@if ($p->product_code)
                                ({{ $p->product_code }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Date range --}}
            <div class="filter-group">
                <label for="filter-date-from">From</label>
                <input type="date" id="filter-date-from" class="form-control form-control-sm"
                    value="{{ request('date_from', now()->startOfMonth()->format('Y-m-d')) }}">
            </div>
            <div class="filter-group">
                <label for="filter-date-to">To</label>
                <input type="date" id="filter-date-to" class="form-control form-control-sm"
                    value="{{ request('date_to', now()->format('Y-m-d')) }}">
            </div>

            <div class="filter-group" style="justify-content: flex-end;">
                <label>&nbsp;</label>
                <button id="btn-apply-filter" class="btn btn-sm" style="background: {{ $hosColor }}; color:#fff; border-radius:8px; font-weight:600;">
                    <i class="mdi mdi-magnify mr-1"></i>Apply
                </button>
            </div>
        </div>

        {{-- ═══════════════ SUMMARY STRIP ═══════════════ --}}
        <div class="summary-strip" id="summary-strip">
            <div class="summary-chip in">
                <span class="chip-label">Total In</span>
                <span class="chip-value" id="sum-in">—</span>
            </div>
            <div class="summary-chip out">
                <span class="chip-label">Total Out</span>
                <span class="chip-value" id="sum-out">—</span>
            </div>
            <div class="summary-chip net">
                <span class="chip-label">Net Movement</span>
                <span class="chip-value" id="sum-net">—</span>
            </div>
            <div class="summary-chip count" id="sum-balance-chip">
                <span class="chip-label">Balance</span>
                <span class="chip-value" id="sum-balance">—</span>
            </div>
            <div class="summary-chip count" id="sum-products-chip" style="display:none;">
                <span class="chip-label">Products</span>
                <span class="chip-value" id="sum-products">—</span>
            </div>
        </div>

        {{-- ═══════════════ PRODUCT CHIPS (store axis) ═══════════════ --}}
        <div id="product-chips-bar" class="product-chips" style="display:none;"></div>

        {{-- ═══════════════ TALLY TABLE ═══════════════ --}}
        <div class="tally-table-wrapper" style="position:relative;">
            <div class="tally-loading" id="tally-loading">
                <div class="spinner-border text-primary" role="status"><span class="sr-only">Loading…</span></div>
            </div>
            <div class="table-responsive">
                <table class="tally-table" id="tally-table">
                    <thead>
                        <tr id="tally-head-product">
                            <th>Date / Time</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Batch</th>
                            <th class="text-center" style="color:#dc3545;">IN</th>
                            <th class="text-center" style="color:#0d6efd;">OUT</th>
                            <th class="text-right">Balance</th>
                            <th>By</th>
                            <th>Notes</th>
                        </tr>
                        <tr id="tally-head-store" style="display:none;">
                            <th>Date / Time</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Batch</th>
                            <th class="text-center" style="color:#dc3545;">IN</th>
                            <th class="text-center" style="color:#0d6efd;">OUT</th>
                            <th class="text-right">Prod. Balance</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody id="tally-body">
                        <tr>
                            <td colspan="9" class="tally-empty">
                                <i class="mdi mdi-table-large"></i>
                                Select a store and apply filters to view the tally card
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══════════════ PENDING PANELS ═══════════════ --}}
        @if ($selectedStore)
            <div class="pending-section">
                <h5 style="font-weight:700; margin-bottom:16px;">
                    <i class="mdi mdi-clock-outline mr-1" style="color:{{ $hosColor }};"></i>
                    Pending Actions — {{ $selectedStore->store_name }}
                </h5>

                <div class="row">
                    {{-- Panel A: Incoming Requisitions (to fulfil) --}}
                    <div class="col-lg-4">
                        <div class="pending-panel" id="panel-incoming-reqs">
                            <div class="pending-panel-header">
                                <h6>
                                    <i class="mdi mdi-arrow-down-circle-outline" style="color:#dc3545;"></i>
                                    Incoming — To Fulfil
                                    <span class="badge badge-danger badge-pill">{{ $pendingIncomingReqs->count() }}</span>
                                </h6>
                                <button class="btn btn-sm btn-outline-secondary btn-refresh-panel" data-panel="incoming" style="border-radius:6px; font-size:0.75rem;">
                                    <i class="mdi mdi-refresh"></i>
                                </button>
                            </div>
                            <div class="pending-panel-body" id="incoming-list">
                                @forelse($pendingIncomingReqs as $req)
                                    <div class="req-row">
                                        <div>
                                            <div class="req-ref">{{ $req->requisition_number }}</div>
                                            <div class="req-meta">To: {{ $req->toStore->store_name ?? '—' }}</div>
                                            <div class="req-meta">
                                                @foreach ($req->items->take(2) as $item)
                                                    {{ $item->product->product_name ?? '—' }}
                                                    ×{{ $item->requested_qty }}
                                                    @if($item->product && $item->product->packagings->count() > 0 && ($pkg = $item->product->packagings->first()) && $pkg->base_unit_qty > 1)
                                                        <small class="text-muted">({{ round($item->requested_qty / $pkg->base_unit_qty, 2) }} {{ $pkg->name }})</small>
                                                    @endif
                                                    {{ !$loop->last ? ', ' : '' }}
                                                @endforeach
                                                @if ($req->items->count()> 2)
                                                    +{{ $req->items->count() - 2 }} more
                                                @endif
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span
                                                class="status-badge {{ $req->status }}">{{ ucfirst($req->status) }}</span>
                                            <a href="{{ route('inventory.requisitions.show', $req->id) }}#fulfill-panel"
                                                target="_blank"
                                                class="btn btn-sm btn-primary"
                                                style="border-radius:6px; font-size:0.75rem; padding:4px 10px;">
                                                <i class="mdi mdi-check"></i> Fulfil
                                            </a>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center text-muted py-4" style="font-size:0.84rem;">
                                        <i class="mdi mdi-check-circle-outline d-block mb-1"
                                            style="font-size:1.8rem; opacity:.4;"></i>
                                        No incoming requisitions to fulfil
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- Panel B: Outgoing Requisitions --}}
                    <div class="col-lg-4">
                        <div class="pending-panel" id="panel-outgoing-reqs">
                            <div class="pending-panel-header">
                                <h6>
                                    <i class="mdi mdi-arrow-up-circle-outline" style="color:#0d6efd;"></i>
                                    Outgoing Requisitions
                                    <span
                                        class="badge badge-primary badge-pill">{{ $pendingOutgoingReqs->count() }}</span>
                                </h6>
                                <button class="btn btn-sm btn-outline-secondary btn-refresh-panel" data-panel="outgoing" style="border-radius:6px; font-size:0.75rem;">
                                    <i class="mdi mdi-refresh"></i>
                                </button>
                            </div>
                            <div class="pending-panel-body" id="outgoing-list">
                                @forelse($pendingOutgoingReqs as $req)
                                    <div class="req-row">
                                        <div>
                                            <div class="req-ref">{{ $req->requisition_number }}</div>
                                            <div class="req-meta">From: {{ $req->fromStore->store_name ?? '—' }}</div>
                                            <div class="req-meta">
                                                @foreach ($req->items->take(2) as $item)
                                                    {{ $item->product->product_name ?? '—' }}
                                                    ×{{ $item->requested_qty }}
                                                    @if($item->product && $item->product->packagings->count() > 0 && ($pkg = $item->product->packagings->first()) && $pkg->base_unit_qty > 1)
                                                        <small class="text-muted">({{ round($item->requested_qty / $pkg->base_unit_qty, 2) }} {{ $pkg->name }})</small>
                                                    @endif
                                                    {{ !$loop->last ? ', ' : '' }}
                                                @endforeach
                                                @if ($req->items->count()> 2)
                                                    +{{ $req->items->count() - 2 }} more
                                                @endif
                                            </div>
                                        </div>
                                        <div>
                                            <span
                                                class="status-badge {{ $req->status }}">{{ ucfirst($req->status) }}</span>
                                            <a href="{{ route('inventory.requisitions.show', $req->id) }}"
                                                target="_blank"
                                                class="btn btn-sm btn-outline-secondary ml-1"
                                                style="border-radius:6px; font-size:0.75rem; padding:4px 10px;">
                                                <i class="mdi mdi-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center text-muted py-4" style="font-size:0.84rem;">
                                        <i class="mdi mdi-clipboard-check-outline d-block mb-1"
                                            style="font-size:1.8rem; opacity:.4;"></i>
                                        No pending outgoing requisitions
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- Panel C: Pending POs --}}
                    <div class="col-lg-4">
                        <div class="pending-panel" id="panel-pos">
                            <div class="pending-panel-header">
                                <h6>
                                    <i class="mdi mdi-cart-outline" style="color:#8b5cf6;"></i>
                                    Purchase Orders
                                    <span class="badge badge-pill"
                                        style="background:#8b5cf6;color:#fff;">{{ $pendingPOs->count() }}</span>
                                </h6>
                                <button class="btn btn-sm btn-outline-secondary btn-refresh-panel" data-panel="pos" style="border-radius:6px; font-size:0.75rem;">
                                    <i class="mdi mdi-refresh"></i>
                                </button>
                            </div>
                            <div class="pending-panel-body" id="po-list">
                                @forelse($pendingPOs as $po)
                                    <div class="po-row">
                                        <div>
                                            <div class="req-ref">{{ $po->po_number }}</div>
                                            <div class="req-meta">Supplier: {{ $po->supplier->company_name ?? '—' }}</div>
                                            <div class="req-meta">
                                                @foreach ($po->items->take(2) as $item)
                                                    {{ $item->product->product_name ?? '—' }}
                                                    ×{{ $item->ordered_qty }}
                                                    @if($item->product && $item->product->packagings->count() > 0 && ($pkg = $item->product->packagings->first()) && $pkg->base_unit_qty > 1)
                                                        <small class="text-muted">({{ round($item->ordered_qty / $pkg->base_unit_qty, 2) }} {{ $pkg->name }})</small>
                                                    @endif
                                                    {{ !$loop->last ? ', ' : '' }}
                                                @endforeach
                                                @if ($po->items->count()> 2)
                                                    +{{ $po->items->count() - 2 }} more
                                                @endif
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span
                                                class="status-badge {{ $po->status }}">{{ ucwords(str_replace('_', ' ', $po->status)) }}</span>
                                            <a href="{{ route('inventory.purchase-orders.receive', $po->id) }}"
                                                target="_blank"
                                                class="btn btn-sm btn-receive-po"
                                                style="background:#8b5cf6; color:#fff; border:none; border-radius:6px; font-size:0.75rem; padding:4px 10px;">
                                                <i class="mdi mdi-download"></i> Receive
                                            </a>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center text-muted py-4" style="font-size:0.84rem;">
                                        <i class="mdi mdi-cart-off d-block mb-1"
                                            style="font-size:1.8rem; opacity:.4;"></i>
                                        No purchase orders pending reception
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ═══════════════ FLOATING TOOLBAR ═══════════════ --}}
        <div class="floating-toolbar">
            <button class="toolbar-btn tb-req" id="tb-new-req">
                <i class="mdi mdi-plus"></i> New Requisition
            </button>
            <button class="toolbar-btn tb-batch" id="tb-add-batch">
                <i class="mdi mdi-package-variant-plus"></i> Add Batch
            </button>
            <button class="toolbar-btn tb-po" id="tb-new-po">
                <i class="mdi mdi-cart-plus"></i> New PO
            </button>
            <button class="toolbar-btn" id="tb-adjust-stock" style="background:#f59e0b; color:#fff;">
                <i class="mdi mdi-tune-vertical"></i> Adjust Stock
            </button>
        </div>
    </div>


    {{-- ═══════════════════════════════════════════════════════════
     MODAL 1 — New Requisition
═══════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modal-new-req" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="mdi mdi-swap-horizontal mr-2"></i>New Requisition</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
                </div>
                <form id="form-new-req">
                    @csrf
                    <input type="hidden" name="auto_approve" value="1">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Requesting From Store <span class="text-danger">*</span></label>
                                    <select name="from_store_id" id="req-from-store" class="form-control" required>
                                        <option value="">— Select Source Store —</option>
                                        @foreach ($allStores as $s)
                                            <option value="{{ $s->id }}">{{ $s->store_name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">The store that will supply the items</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Destination Store <span class="text-danger">*</span></label>
                                    <select name="to_store_id" id="req-to-store" class="form-control" required>
                                        <option value="">— Select Destination —</option>
                                        @foreach ($allStores as $s)
                                            <option value="{{ $s->id }}"
                                                {{ $selectedStore && $selectedStore->id == $s->id ? 'selected' : '' }}>
                                                {{ $s->store_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Request Notes</label>
                            <textarea name="request_notes" class="form-control" rows="2" maxlength="1000" placeholder="Optional notes…"></textarea>
                        </div>

                        {{-- Items --}}
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="mb-0 font-weight-bold">Items <span class="text-danger">*</span></label>
                            <button type="button" id="btn-add-req-item" class="btn btn-sm btn-outline-primary" style="border-radius:6px;">
                                <i class="mdi mdi-plus"></i> Add Item
                            </button>
                        </div>
                        <div id="req-items-container">
                            <div class="req-item-row" data-index="0">
                                <div class="row align-items-end mb-2">
                                    <div class="col-md-5">
                                        <select name="items[0][product_id]" class="form-control req-product-select"
                                            required onchange="loadProductPackaging(this, 0, 'req')">
                                            <option value="">— Select Product —</option>
                                            @foreach ($products as $p)
                                                <option value="{{ $p->id }}"
                                                    data-base-unit="{{ $p->base_unit_name ?? 'Piece' }}"
                                                    {{ $selectedProduct && $selectedProduct->id == $p->id ? 'selected' : '' }}>
                                                    {{ $p->product_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="items[0][packaging_id]" class="form-control req-packaging-select" onchange="calculateReqBaseQty(0)">
                                            <option value="">Base Unit</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" name="items[0][packaging_qty]" class="form-control req-qty-input"
                                            placeholder="Qty" min="1" required oninput="calculateReqBaseQty(0)">
                                        <small class="text-muted req-base-qty-hint" id="req-base-qty-hint-0"></small>
                                        <input type="hidden" name="items[0][requested_qty]" class="req-base-qty-hidden">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-req-item w-100" style="border-radius:6px;" disabled>
                                            <i class="mdi mdi-delete-outline"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="btn-submit-req">
                            <i class="mdi mdi-send mr-1"></i>Submit Requisition
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    {{-- ═══════════════════════════════════════════════════════════
     MODAL 2 — Add Batch
═══════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modal-add-batch" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content" style="border-radius:12px; border:none; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
                <div class="modal-header" style="background:#f8fafc; border-bottom:1px solid #e2e8f0; border-radius:12px 12px 0 0;">
                    <h5 class="modal-title" style="color:#1e293b; font-weight:700;">
                        <i class="mdi mdi-package-variant-plus mr-2" style="color:#10b981;"></i>Add Stock Batch
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="form-add-batch">
                    @csrf
                    <div class="modal-body" style="padding:1.5rem;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Store <span class="text-danger">*</span></label>
                                    <select name="store_id" id="batch-store" class="form-control" required style="border-radius:8px;">
                                        <option value="">— Select Store —</option>
                                        @foreach ($stores as $s)
                                            <option value="{{ $s->id }}"
                                                {{ $selectedStore && $selectedStore->id == $s->id ? 'selected' : '' }}>
                                                {{ $s->store_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Product <span class="text-danger">*</span></label>
                                    <select name="product_id" id="batch-product" class="form-control" required onchange="loadProductPackaging(this, null, 'batch')" style="border-radius:8px;">
                                        <option value="">— Select Product —</option>
                                        @foreach ($products as $p)
                                            <option value="{{ $p->id }}"
                                                data-base-unit="{{ $p->base_unit_name ?? 'Piece' }}"
                                                {{ $selectedProduct && $selectedProduct->id == $p->id ? 'selected' : '' }}>
                                                {{ $p->product_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Batch Number <span class="text-danger">*</span></label>
                                    <input type="text" name="batch_number" class="form-control" maxlength="100" required style="border-radius:8px;" placeholder="e.g. BTN-001">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Packaging Unit</label>
                                    <select name="packaging_id" id="batch-packaging" class="form-control" onchange="calculateBatchBaseQty()" style="border-radius:8px;">
                                        <option value="">Base Unit</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Quantity <span class="text-danger">*</span></label>
                                    <input type="number" name="packaging_qty" id="batch-qty" class="form-control" min="1" required oninput="calculateBatchBaseQty()" style="border-radius:8px;" placeholder="0">
                                    <small class="text-muted" id="batch-qty-hint" style="display:block; height:15px;"></small>
                                    <input type="hidden" name="quantity" id="batch-base-qty-hidden">
                                </div>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Batch Name</label>
                                    <input type="text" name="batch_name" class="form-control" maxlength="100" style="border-radius:8px;" placeholder="e.g. Initial Stock">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Cost Price</label>
                                    <input type="number" name="cost_price" class="form-control" step="0.01" min="0" style="border-radius:8px;" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Expiry Date</label>
                                    <input type="date" name="expiry_date" class="form-control" style="border-radius:8px;">
                                </div>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Supplier</label>
                                    <select name="supplier_id" class="form-control" style="border-radius:8px;">
                                        <option value="">— None —</option>
                                        @foreach ($suppliers as $sup)
                                            <option value="{{ $sup->id }}">{{ $sup->company_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="form-group">
                                    <label class="small font-weight-bold">Notes</label>
                                    <textarea name="notes" class="form-control" rows="1" maxlength="500" placeholder="Optional notes…" style="border-radius:8px;"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="background:#f8fafc; border-top:1px solid #e2e8f0; border-radius:0 0 12px 12px; padding:1rem 1.5rem;">
                        <button type="button" class="btn btn-light" data-dismiss="modal" style="border-radius:8px; font-weight:600; color:#64748b;">Cancel</button>
                        <button type="submit" class="btn" id="btn-submit-batch" style="background:#10b981; color:#fff; border-radius:8px; font-weight:600; padding:0.5rem 1.5rem;">
                            <i class="mdi mdi-check mr-1"></i>Create Batch
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    {{-- ═══════════════════════════════════════════════════════════
     MODAL 3 — Fulfill Requisition
═══════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modal-fulfill-req" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="mdi mdi-check-circle-outline mr-2"
                            style="color:#10b981;"></i>Fulfil Requisition <span id="fulfill-req-number"
                            class="text-muted"></span></h5>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
                </div>
                <form id="form-fulfill-req">
                    @csrf
                    <input type="hidden" id="fulfill-req-id" name="_req_id">
                    <div class="modal-body" id="fulfill-modal-body">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-muted">Loading requisition details…</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="btn-submit-fulfill">
                            <i class="mdi mdi-check mr-1"></i>Submit Fulfillment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    {{-- ═══════════════════════════════════════════════════════════
     MODAL 4 — New Purchase Order
═══════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modal-new-po" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="mdi mdi-cart-plus mr-2" style="color:#8b5cf6;"></i>New Purchase
                        Order</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
                </div>
                <form id="form-new-po">
                    @csrf
                    <input type="hidden" name="auto_approve" value="1">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Supplier <span class="text-danger">*</span></label>
                                    <select name="supplier_id" id="po-supplier" class="form-control" required>
                                        <option value="">— Select Supplier —</option>
                                        @foreach ($suppliers as $sup)
                                            <option value="{{ $sup->id }}">{{ $sup->company_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Target Store <span class="text-danger">*</span></label>
                                    <select name="target_store_id" id="po-store" class="form-control" required>
                                        <option value="">— Select Store —</option>
                                        @foreach ($stores as $s)
                                            <option value="{{ $s->id }}"
                                                {{ $selectedStore && $selectedStore->id == $s->id ? 'selected' : '' }}>
                                                {{ $s->store_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Expected Delivery</label>
                                    <input type="date" name="expected_date" class="form-control"
                                        min="{{ now()->format('Y-m-d') }}">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="2" maxlength="1000" placeholder="Optional notes…"></textarea>
                        </div>

                        {{-- PO Items --}}
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="mb-0 font-weight-bold">Items <span class="text-danger">*</span></label>
                            <button type="button" id="btn-add-po-item" class="btn btn-sm btn-outline-secondary" style="border-radius:6px;">
                                <i class="mdi mdi-plus"></i> Add Item
                            </button>
                        </div>
                        <div id="po-items-container">
                            <div class="po-item-row" data-index="0">
                                <div class="row align-items-end mb-2">
                                    <div class="col-md-4">
                                        <select name="items[0][product_id]" class="form-control po-product-select"
                                            required onchange="loadProductPackaging(this, 0, 'po')">
                                            <option value="">— Select Product —</option>
                                            @foreach ($products as $p)
                                                <option value="{{ $p->id }}"
                                                    data-base-unit="{{ $p->base_unit_name ?? 'Piece' }}"
                                                    {{ $selectedProduct && $selectedProduct->id == $p->id ? 'selected' : '' }}>
                                                    {{ $p->product_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="items[0][packaging_id]" class="form-control po-packaging-select" onchange="calculatePoBaseQty(0)">
                                            <option value="">Base Unit</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="items[0][packaging_qty]" class="form-control po-qty-input"
                                            placeholder="Qty" min="1" required oninput="calculatePoBaseQty(0)">
                                        <small class="text-muted po-base-qty-hint" id="po-base-qty-hint-0"></small>
                                        <input type="hidden" name="items[0][ordered_qty]" class="po-base-qty-hidden">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" name="items[0][unit_cost]" class="form-control po-cost-input"
                                            placeholder="Unit Cost" step="0.01" min="0" required oninput="calculatePoBaseQty(0)">
                                        <input type="hidden" name="items[0][base_unit_cost]" class="po-base-cost-hidden">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-po-item w-100" style="border-radius:6px;" disabled>
                                            <i class="mdi mdi-delete-outline"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="save" class="btn btn-outline-secondary" id="btn-save-po">
                            <i class="mdi mdi-content-save mr-1"></i>Save Draft
                        </button>
                        <button type="button" class="btn" style="background:#8b5cf6; color:#fff;" id="btn-submit-po">
                            <i class="mdi mdi-send mr-1"></i>Submit PO
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    {{-- ═══════════════════════════════════════════════════════════
     MODAL 5 — Receive PO
═══════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modal-receive-po" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="mdi mdi-download mr-2" style="color:#8b5cf6;"></i>Receive PO —
                        <span id="receive-po-number"></span>
                    </h5>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
                </div>
                <form id="form-receive-po">
                    @csrf
                    <input type="hidden" id="receive-po-id" name="_po_id">
                    <div class="modal-body" id="receive-modal-body">
                        <div class="text-center py-4">
                            <div class="spinner-border" role="status" style="color:#8b5cf6;"></div>
                            <p class="mt-2 text-muted">Loading PO details…</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" style="background:#8b5cf6; color:#fff;" id="btn-submit-receive">
                            <i class="mdi mdi-check mr-1"></i>Confirm Receipt
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
     MODAL 6 — Adjust Stock
═══════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modal-adjust-stock" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="mdi mdi-tune-vertical mr-2" style="color:#f59e0b;"></i>Adjust Stock</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    {{-- Step 1: Batch selector --}}
                    <div id="adj-step-1">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="font-weight-600 mb-1">Filter by Product <small class="text-muted">(optional)</small></label>
                                <select id="adj-product-filter" class="form-control">
                                    <option value="">— All Products —</option>
                                    @foreach ($products as $p)
                                        <option value="{{ $p->id }}">{{ $p->product_name }}{{ $p->product_code ? ' (' . $p->product_code . ')' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button id="adj-load-batches" class="btn btn-secondary btn-sm" style="border-radius:8px;">
                                    <i class="mdi mdi-refresh mr-1"></i>Load Batches
                                </button>
                            </div>
                        </div>
                        <div id="adj-batch-list">
                            <div class="text-center text-muted py-4" style="font-size:0.85rem;">
                                <i class="mdi mdi-package-variant d-block mb-1" style="font-size:2rem; opacity:.35;"></i>
                                Select a store and click "Load Batches"
                            </div>
                        </div>
                    </div>

                    {{-- Step 2: Adjustment form (hidden until batch selected) --}}
                    <div id="adj-step-2" style="display:none;">
                        <div class="adj-batch-info mb-3" id="adj-batch-info-panel"></div>

                        <form id="form-adjust-stock">
                            @csrf
                            <input type="hidden" id="adj-batch-id" name="batch_id">

                            {{-- Type picker --}}
                            <div class="form-group">
                                <label class="font-weight-600 mb-2">Adjustment Type <span class="text-danger">*</span></label>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="adjustment-type-btn add border text-center" id="adj-btn-add" onclick="adjSelectType('add')" style="cursor:pointer; padding:1rem; border-radius:8px; transition:all .2s;">
                                            <input type="radio" name="adjustment_type" value="add" class="d-none" id="adj-type-add">
                                            <i class="mdi mdi-plus-circle text-success" style="font-size:2rem;"></i>
                                            <div class="mt-1"><strong>Add Stock</strong></div>
                                            <small class="text-muted">Found / Returned items</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="adjustment-type-btn subtract border text-center" id="adj-btn-subtract" onclick="adjSelectType('subtract')" style="cursor:pointer; padding:1rem; border-radius:8px; transition:all .2s;">
                                            <input type="radio" name="adjustment_type" value="subtract" class="d-none" id="adj-type-subtract">
                                            <i class="mdi mdi-minus-circle text-danger" style="font-size:2rem;"></i>
                                            <div class="mt-1"><strong>Subtract Stock</strong></div>
                                            <small class="text-muted">Damaged / Lost items</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Quantity & Packaging --}}
                            <div class="row align-items-end mb-3">
                                <div class="col-md-5">
                                    <label class="font-weight-600 mb-1">Packaging Unit</label>
                                    <select name="packaging_id" id="adj-packaging" class="form-control" onchange="calculateAdjBaseQty()">
                                        <option value="">Base Unit</option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label for="adj-qty" class="font-weight-600 mb-1">Quantity <span class="text-danger">*</span></label>
                                    <input type="number" name="qty" id="adj-qty" class="form-control" min="1" value="1" required oninput="calculateAdjBaseQty()">
                                    <small class="text-muted" id="adj-qty-hint"></small>
                                    <input type="hidden" name="base_qty" id="adj-base-qty-hidden">
                                </div>
                            </div>

                            {{-- Reason --}}
                            <div class="form-group">
                                <label for="adj-reason">Reason <span class="text-danger">*</span></label>
                                <select name="reason" id="adj-reason" class="form-control" required>
                                    <option value="">— Select reason —</option>
                                    <optgroup label="Add Stock">
                                        <option value="Physical count correction (found)">Physical count correction (found)</option>
                                        <option value="Returned by patient">Returned by patient</option>
                                        <option value="Transfer from another location">Transfer from another location</option>
                                        <option value="Other - add">Other</option>
                                    </optgroup>
                                    <optgroup label="Subtract Stock">
                                        <option value="Physical count correction (loss)">Physical count correction (loss)</option>
                                        <option value="Damaged">Damaged</option>
                                        <option value="Expired">Expired</option>
                                        <option value="Theft/Loss">Theft/Loss</option>
                                        <option value="Sampling/Testing">Sampling/Testing</option>
                                        <option value="Other - subtract">Other</option>
                                    </optgroup>
                                </select>
                            </div>

                            {{-- Notes --}}
                            <div class="form-group">
                                <label for="adj-notes">Additional Notes</label>
                                <textarea name="notes" id="adj-notes" class="form-control" rows="2" placeholder="Optional details…"></textarea>
                            </div>
                        </form>

                        <button id="adj-back-btn" class="btn btn-link btn-sm text-muted px-0 mb-2">
                            <i class="mdi mdi-arrow-left mr-1"></i>Back to batch list
                        </button>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn" id="btn-apply-adjustment" style="background:#f59e0b; color:#fff; display:none;" disabled>
                        <i class="mdi mdi-check mr-1"></i>Apply Adjustment
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function() {
            'use strict';

            // ─── State ───────────────────────────────────────────────────────────────
            var currentAxis = $('#axis-store').hasClass('active') ? 'store' : 'product';
            var currentStoreId = $('#filter-store').val();
            var reqItemIndex = 0;
            var poItemIndex = 0;
            var productRegistry = {}; // id → name map (built from rendered rows)

            // ─── Helper: show toast ───────────────────────────────────────────────────
            function toast(msg, type) {
                type = type || 'success';
                var bg = type === 'success' ? '#10b981' : (type === 'warning' ? '#f59e0b' : '#ef4444');
                var $t = $('<div>')
                    .css({
                        position: 'fixed',
                        top: '20px',
                        right: '20px',
                        zIndex: 9999,
                        background: bg,
                        color: '#fff',
                        borderRadius: '8px',
                        padding: '12px 20px',
                        boxShadow: '0 4px 14px rgba(0,0,0,.2)',
                        fontSize: '0.875rem',
                        fontWeight: 600,
                        maxWidth: '360px'
                    })
                    .text(msg);
                $('body').append($t);
                setTimeout(function() {
                    $t.fadeOut(400, function() {
                        $t.remove();
                    });
                }, 3500);
            }

            // ─── Axis toggle ─────────────────────────────────────────────────────────
            $(document).on('click', '.axis-toggle-btn', function() {
                var axis = $(this).data('axis');
                currentAxis = axis;
                $('.axis-toggle-btn').removeClass('active');
                $(this).addClass('active');

                if (axis === 'store') {
                    $('#product-filter-group').hide();
                    $('#tally-head-product').hide();
                    $('#tally-head-store').show();
                    $('#sum-balance-chip').hide();
                    $('#sum-products-chip').show();
                } else {
                    $('#product-filter-group').show();
                    $('#tally-head-product').show();
                    $('#tally-head-store').hide();
                    $('#sum-balance-chip').show();
                    $('#sum-products-chip').hide();
                    $('#product-chips-bar').hide();
                }
            });

            function formatPackaging(qty, baseUnitName, packagings) {
                if (!packagings || packagings.length === 0 || qty === 0) return '';
                
                // For now, use the first packaging level as the reference
                var p = packagings[0];
                var factor = parseFloat(p.base_unit_qty) || 1;
                if (factor <= 1) return ''; // Skip if factor is 1 (redundant with base unit)
                
                var equiv = (Math.abs(qty) / factor).toFixed(2);
                // Clean up trailing zeros
                equiv = equiv.replace(/\.?0+$/, "");
                
                return ` <small class="text-muted" title="${equiv} ${p.name}">(${equiv} ${p.name})</small>`;
            }

            // ─── Load Pending Actions (AJAX) ──────────────────────────────────────────
            function loadPendingActions(storeId) {
                if (!storeId) return;

                $('.btn-refresh-panel').addClass('mdi-spin');

                $.get('{{ route('inventory.store-workbench.tally-card.pending-actions') }}', { store_id: storeId })
                    .done(function(res) {
                        if (!res.success) return;

                        // Update Counts
                        $('#panel-incoming-reqs h6 .badge').text(res.counts.incoming);
                        $('#panel-outgoing-reqs h6 .badge').text(res.counts.outgoing);
                        $('#panel-pos h6 .badge').text(res.counts.pos);

                        // Render Incoming
                        var incomingHtml = '';
                        if (res.incoming.length === 0) {
                            incomingHtml = '<div class="text-center text-muted py-4" style="font-size:0.84rem;"><i class="mdi mdi-check-circle-outline d-block mb-1" style="font-size:1.8rem; opacity:.4;"></i>No incoming requisitions to fulfil</div>';
                        } else {
                            $.each(res.incoming, function(_, req) {
                                var itemsHtml = '';
                                if (req.items) {
                                    $.each(req.items.slice(0, 2), function(i, item) {
                                        var pkgHint = formatPackaging(item.requested_qty, '', item.product ? item.product.packagings : []);
                                        itemsHtml += (item.product ? item.product.product_name : '—') + ' ×' + item.requested_qty + pkgHint + (i === 0 && req.items.length > 1 ? ', ' : '');
                                    });
                                    if (req.items.length > 2) itemsHtml += ' +' + (req.items.length - 2) + ' more';
                                }
                                incomingHtml += `
                                    <div class="req-row">
                                        <div>
                                            <div class="req-ref">${req.requisition_number}</div>
                                            <div class="req-meta">To: ${req.to_store ? req.to_store.store_name : '—'}</div>
                                            <div class="req-meta">${itemsHtml}</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="status-badge ${req.status}">${req.status.charAt(0).toUpperCase() + req.status.slice(1)}</span>
                                            <a href="{{ url('inventory/requisitions') }}/${req.id}#fulfill-panel" target="_blank" class="btn btn-sm btn-primary" style="border-radius:6px; font-size:0.75rem; padding:4px 10px;">
                                                <i class="mdi mdi-check"></i> Fulfil
                                            </a>
                                        </div>
                                    </div>`;
                            });
                        }
                        $('#incoming-list').html(incomingHtml);

                        // Render Outgoing
                        var outgoingHtml = '';
                        if (res.outgoing.length === 0) {
                            outgoingHtml = '<div class="text-center text-muted py-4" style="font-size:0.84rem;"><i class="mdi mdi-check-circle-outline d-block mb-1" style="font-size:1.8rem; opacity:.4;"></i>No pending outgoing requisitions</div>';
                        } else {
                            $.each(res.outgoing, function(_, req) {
                                var itemsHtml = '';
                                if (req.items) {
                                    $.each(req.items.slice(0, 2), function(i, item) {
                                        itemsHtml += (item.product ? item.product.product_name : '—') + ' ×' + item.requested_qty + (i === 0 && req.items.length > 1 ? ', ' : '');
                                    });
                                    if (req.items.length > 2) itemsHtml += ' +' + (req.items.length - 2) + ' more';
                                }
                                outgoingHtml += `
                                    <div class="req-row">
                                        <div>
                                            <div class="req-ref">${req.requisition_number}</div>
                                            <div class="req-meta">From: ${req.from_store ? req.from_store.store_name : '—'}</div>
                                            <div class="req-meta">${itemsHtml}</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="status-badge ${req.status}">${req.status.charAt(0).toUpperCase() + req.status.slice(1)}</span>
                                            <a href="{{ url('inventory/requisitions') }}/${req.id}" target="_blank" class="btn btn-sm btn-outline-primary" style="border-radius:6px; font-size:0.75rem; padding:4px 10px;">
                                                <i class="mdi mdi-eye"></i> View
                                            </a>
                                        </div>
                                    </div>`;
                            });
                        }
                        $('#outgoing-list').html(outgoingHtml);

                        // Render POs
                        var poHtml = '';
                        if (res.pos.length === 0) {
                            poHtml = '<div class="text-center text-muted py-4" style="font-size:0.84rem;"><i class="mdi mdi-cart-off d-block mb-1" style="font-size:1.8rem; opacity:.4;"></i>No purchase orders pending reception</div>';
                        } else {
                            $.each(res.pos, function(_, po) {
                                var itemsHtml = '';
                                if (po.items) {
                                    $.each(po.items.slice(0, 2), function(i, item) {
                                        var pkgHint = formatPackaging(item.ordered_qty, '', item.product ? item.product.packagings : []);
                                        itemsHtml += (item.product ? item.product.product_name : '—') + ' ×' + item.ordered_qty + pkgHint + (i === 0 && po.items.length > 1 ? ', ' : '');
                                    });
                                    if (po.items.length > 2) itemsHtml += ' +' + (po.items.length - 2) + ' more';
                                }
                                poHtml += `
                                    <div class="po-row">
                                        <div>
                                            <div class="req-ref">${po.po_number}</div>
                                            <div class="req-meta">Supplier: ${po.supplier ? po.supplier.company_name : '—'}</div>
                                            <div class="req-meta">${itemsHtml}</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="status-badge ${po.status}">${po.status.replace(/_/g, ' ')}</span>
                                            <a href="{{ url('inventory/purchase-orders') }}/${po.id}/receive" target="_blank" class="btn btn-sm btn-receive-po" style="background:#8b5cf6; color:#fff; border:none; border-radius:6px; font-size:0.75rem; padding:4px 10px;">
                                                <i class="mdi mdi-download"></i> Receive
                                            </a>
                                        </div>
                                    </div>`;
                            });
                        }
                        $('#po-list').html(poHtml);
                    })
                    .always(function() {
                        $('.btn-refresh-panel').removeClass('mdi-spin');
                    });
            }

            $('.btn-refresh-panel').on('click', function() {
                loadPendingActions(currentStoreId);
            });

            // ─── Load tally data ─────────────────────────────────────────────────────
            function loadTally() {
                var storeId = $('#filter-store').val();
                var productId = $('#filter-product').val();
                var dateFrom = $('#filter-date-from').val();
                var dateTo = $('#filter-date-to').val();

                if (!storeId) {
                    toast('Please select a store', 'error');
                    return;
                }
                if (currentAxis === 'product' && !productId) {
                    toast('Please select a product', 'error');
                    return;
                }

                currentStoreId = storeId;
                loadPendingActions(storeId);

                $('#tally-loading').addClass('show');
                $('#tally-body').html(
                    '<tr><td colspan="9" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary"></span></td></tr>'
                );
                $('#summary-strip .chip-value').text('—');
                $('#product-chips-bar').empty().hide();

                // Update URL params without reloading
                var params = new URLSearchParams(window.location.search);
                params.set('axis', currentAxis);
                params.set('store_id', storeId);
                if (productId) params.set('product_id', productId); else params.delete('product_id');
                params.set('date_from', dateFrom);
                params.set('date_to', dateTo);
                var newUrl = window.location.pathname + '?' + params.toString();
                window.history.pushState({path: newUrl}, '', newUrl);

                $.get('{{ route('inventory.store-workbench.tally-card.data') }}', {
                        axis: currentAxis,
                        store_id: storeId,
                        product_id: productId || null,
                        date_from: dateFrom || null,
                        date_to: dateTo || null,
                    })
                    .done(function(res) {
                        if (!res.success) {
                            toast(res.message || 'Error loading data', 'error');
                            return;
                        }
                        renderTally(res);
                    })
                    .fail(function(xhr) {
                        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message :
                            'Failed to load tally data';
                        toast(msg, 'error');
                        $('#tally-body').html(
                            '<tr><td colspan="9" class="tally-empty"><i class="mdi mdi-alert-circle-outline"></i>' +
                            msg + '</td></tr>');
                    })
                    .always(function() {
                        $('#tally-loading').removeClass('show');
                    });
            }

            // ─── Render tally table ───────────────────────────────────────────────────
            function renderTally(res) {
                var rows = res.transactions;
                var sum = res.summary;
                var axis = res.axis;

                // Summary strip
                if (axis === 'product') {
                    var inPkg = (rows.length > 0) ? formatPackaging(sum.total_in, rows[0].base_unit, rows[0].packaging) : '';
                    var outPkg = (rows.length > 0) ? formatPackaging(sum.total_out, rows[0].base_unit, rows[0].packaging) : '';
                    var pkgHint = (rows.length > 0) ? formatPackaging(sum.closing_balance, rows[0].base_unit, rows[0].packaging) : '';
                    
                    $('#sum-in').html(sum.total_in + inPkg);
                    $('#sum-out').html(sum.total_out + outPkg);
                    $('#sum-balance').html((sum.closing_balance !== null ? sum.closing_balance : '—') + pkgHint);
                    $('#sum-balance-chip').show();
                    $('#sum-products-chip').hide();
                } else {
                    $('#sum-in').text(sum.total_in);
                    $('#sum-out').text(sum.total_out);
                    $('#sum-products').text(sum.products_touched);
                    $('#sum-balance-chip').hide();
                    $('#sum-products-chip').show();
                }

                if (rows.length === 0) {
                    var colSpan = axis === 'store' ? 9 : 9;
                    $('#tally-body').html('<tr><td colspan="' + colSpan +
                        '" class="tally-empty"><i class="mdi mdi-table-search"></i>No transactions found for the selected filters</td></tr>'
                    );
                    return;
                }

                // Build product registry for chips
                productRegistry = {};
                $.each(rows, function(_, r) {
                    if (r.product_id && r.product_name) {
                        productRegistry[r.product_id] = r.product_name;
                    }
                });

                var html = '';
                $.each(rows, function(_, r) {
                    var dirClass = 'dir-' + r.direction;
                    var typeClass = 'type-badge ' + (r.badge_type || r.type);
                    var inCell = r.in_qty > 0 ? '<span class="qty-in">' + r.in_qty + '</span>' + formatPackaging(r.in_qty, r.base_unit, r.packaging) :
                        '<span style="color:#ccc;">—</span>';
                    var outCell = r.out_qty > 0 ? '<span class="qty-out">' + r.out_qty + '</span>' + formatPackaging(r.out_qty, r.base_unit, r.packaging) :
                        '<span style="color:#ccc;">—</span>';
                    var balanceCell = r.product_balance + formatPackaging(r.product_balance, r.base_unit, r.packaging);
                    var refCell = r.ref_url ?
                        '<a href="' + escHtml(r.ref_url) + '" target="_blank" rel="noopener">' + escHtml(r
                            .ref_label) + '</a>' :
                        escHtml(r.ref_label);
                    var notes = r.notes ? '<small class="text-muted" title="' + escHtml(r.notes) + '">' +
                        escHtml(r.notes.substring(0, 30)) + (r.notes.length> 30 ? '…' : '') + '</small>' : '—';

                    if (axis === 'product') {
                        html += '<tr class="' + dirClass + '">' +
                            '<td><div style="font-weight:600; font-size:0.82rem;">' + escHtml(r.date) +
                            '</div><div style="color:#9ca3af; font-size:0.72rem;">' + escHtml(r.time) +
                            '</div></td>' +
                            '<td><span class="' + typeClass + '">' + escHtml(r.type_label) + '</span></td>' +
                            '<td>' + refCell + '</td>' +
                            '<td><code style="font-size:0.78rem;">' + escHtml(r.batch_number) + '</code></td>' +
                            '<td class="text-center">' + inCell + '</td>' +
                            '<td class="text-center">' + outCell + '</td>' +
                            '<td class="text-right balance-col">' + balanceCell + '</td>' +
                            '<td><small>' + escHtml(r.performer) + '</small></td>' +
                            '<td>' + notes + '</td>' +
                            '</tr>';
                    } else {
                        html += '<tr class="' + dirClass + '" data-product-id="' + r.product_id + '">' +
                            '<td><div style="font-weight:600; font-size:0.82rem;">' + escHtml(r.date) +
                            '</div><div style="color:#9ca3af; font-size:0.72rem;">' + escHtml(r.time) +
                            '</div></td>' +
                            '<td><div style="font-weight:600; font-size:0.82rem;">' + escHtml(r.product_name) +
                            '</div>' +
                            (r.product_code ? '<div style="color:#9ca3af; font-size:0.72rem;">' + escHtml(r
                                .product_code) + '</div>' : '') +
                            '</td>' +
                            '<td><span class="' + typeClass + '">' + escHtml(r.type_label) + '</span></td>' +
                            '<td>' + refCell + '</td>' +
                            '<td><code style="font-size:0.78rem;">' + escHtml(r.batch_number) + '</code></td>' +
                            '<td class="text-center">' + inCell + '</td>' +
                            '<td class="text-center">' + outCell + '</td>' +
                            '<td class="text-right balance-col">' + balanceCell + '</td>' +
                            '<td><small>' + escHtml(r.performer) + '</small></td>' +
                            '</tr>';
                    }
                });

                $('#tally-body').html(html);

                // Product chips for store axis
                if (axis === 'store' && Object.keys(productRegistry).length> 0) {
                    var chipHtml = '<span class="product-chip all" data-pid="all">All Products</span>';
                    $.each(productRegistry, function(pid, name) {
                        chipHtml += '<span class="product-chip" data-pid="' + pid + '">' + escHtml(name) +
                            '</span>';
                    });
                    $('#product-chips-bar').html(chipHtml).show();
                }
            }

            // ─── Product chip filtering (client-side, store axis) ────────────────────
            $(document).on('click', '.product-chip', function() {
                $('.product-chip').removeClass('active');
                $(this).addClass('active');
                var pid = $(this).data('pid');
                if (pid === 'all') {
                    $('#tally-body tr').show();
                } else {
                    $('#tally-body tr').each(function() {
                        $(this).toggle($(this).data('product-id') == pid);
                    });
                }
            });

            // Apply filter
            $('#btn-apply-filter').on('click', loadTally);
            // Enter key on date fields
            $('#filter-date-from, #filter-date-to').on('keydown', function(e) {
                if (e.key === 'Enter') loadTally();
            });

            // Auto-load if store is pre-filled
            $(function() {
                if (currentStoreId) {
                    // Only load data if we have enough context for the axis
                    if (currentAxis === 'store' || (currentAxis === 'product' && $('#filter-product').val())) {
                        loadTally();
                    } else {
                        // Still load pending actions if store is selected
                        loadPendingActions(currentStoreId);
                    }
                }
            });

            // ─── Floating toolbar → open modals ──────────────────────────────────────
            $('#tb-new-req').on('click', function() {
                if (currentStoreId) $('#req-to-store').val(currentStoreId);
                $('#modal-new-req').modal('show');
            });
            $('#tb-add-batch').on('click', function() {
                if (currentStoreId) $('#batch-store').val(currentStoreId);
                $('#modal-add-batch').modal('show');
            });
            $('#tb-new-po').on('click', function() {
                if (currentStoreId) $('#po-store').val(currentStoreId);
                $('#modal-new-po').modal('show');
            });

            // ─── MODAL 1: New Requisition ─────────────────────────────────────────────
            // Prevent same source + destination
            $('#req-from-store, #req-to-store').on('change', function() {
                var from = $('#req-from-store').val();
                var to = $('#req-to-store').val();
                if (from && to && from === to) {
                    toast('Source and destination store cannot be the same', 'error');
                }
            });

            // Add item row
            $('#btn-add-req-item').on('click', function() {
                reqItemIndex++;
                var row = `<div class="req-item-row" data-index="${reqItemIndex}">
            <div class="row align-items-end mb-2">
                <div class="col-md-5">
                    <select name="items[${reqItemIndex}][product_id]" class="form-control req-product-select" required onchange="loadProductPackaging(this, ${reqItemIndex}, 'req')">
                        <option value="">— Select Product —</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}" data-base-unit="{{ $p->base_unit_name ?? 'Piece' }}">{{ $p->product_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="items[${reqItemIndex}][packaging_id]" class="form-control req-packaging-select" onchange="calculateReqBaseQty(${reqItemIndex})">
                        <option value="">Base Unit</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="number" name="items[${reqItemIndex}][packaging_qty]" class="form-control req-qty-input" placeholder="Qty" min="1" required oninput="calculateReqBaseQty(${reqItemIndex})">
                    <small class="text-muted req-base-qty-hint" id="req-base-qty-hint-${reqItemIndex}"></small>
                    <input type="hidden" name="items[${reqItemIndex}][requested_qty]" class="req-base-qty-hidden">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-req-item w-100" style="border-radius:6px;">
                        <i class="mdi mdi-delete-outline"></i>
                    </button>
                </div>
            </div>
        </div>`;
                $('#req-items-container').append(row);
                
                // Initialize Select2 on all selects in the new row
                if ($.fn.select2) {
                    var $newRow = $(`.req-item-row[data-index="${reqItemIndex}"]`);
                    $newRow.find('select').each(function() {
                        var $sel = $(this);
                        $sel.select2({
                            dropdownParent: $('#modal-new-req'),
                            width: '100%',
                            placeholder: $sel.find('option:first').text() || 'Select…'
                        });
                    });
                }

                updateRemoveReqButtons();
            });

            $(document).on('click', '.btn-remove-req-item', function() {
                $(this).closest('.req-item-row').remove();
                updateRemoveReqButtons();
            });

            function updateRemoveReqButtons() {
                var rows = $('.req-item-row');
                rows.find('.btn-remove-req-item').prop('disabled', rows.length <= 1);
            }

            // Submit requisition
            $('#form-new-req').on('submit', function(e) {
                e.preventDefault();
                var btn = $('#btn-submit-req').prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm"></span> Submitting…');

                $.ajax({
                        url: '{{ route('inventory.requisitions.store') }}',
                        method: 'POST',
                        data: $(this).serialize().replace(/requested_qty=/g, 'ignore_qty=').replace(/base_requested_qty=/g, 'requested_qty='),
                        dataType: 'json',
                    })
                    .done(function(res) {
                        if (res.success) {
                            toast('Requisition submitted successfully');
                            $('#modal-new-req').modal('hide');
                            $('#form-new-req')[0].reset();
                            reqItemIndex = 0;
                            updateRemoveReqButtons();
                            loadTally();
                        } else {
                            toast(res.message || 'Error submitting requisition', 'error');
                        }
                    })
                    .fail(function(xhr) {
                        var errors = xhr.responseJSON && xhr.responseJSON.errors ?
                            Object.values(xhr.responseJSON.errors).flat().join(' ') :
                            (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message :
                                'Request failed');
                        toast(errors, 'error');
                    })
                    .always(function() {
                        $('#btn-submit-req').prop('disabled', false).html(
                            '<i class="mdi mdi-send mr-1"></i>Submit Requisition');
                    });
            });

            // ─── MODAL 2: Add Batch ───────────────────────────────────────────────────
            $('#form-add-batch').on('submit', function(e) {
                e.preventDefault();
                var btn = $('#btn-submit-batch').prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm"></span> Creating…');

                $.ajax({
                        url: '{{ route('inventory.store-workbench.create-manual-batch') }}',
                        method: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json',
                    })
                    .done(function(res) {
                        if (res.success) {
                            toast(res.message || 'Batch created successfully');
                            $('#modal-add-batch').modal('hide');
                            $('#form-add-batch')[0].reset();
                            loadTally();
                        } else {
                            toast(res.message || 'Error creating batch', 'error');
                        }
                    })
                    .fail(function(xhr) {
                        var errors = xhr.responseJSON && xhr.responseJSON.errors ?
                            Object.values(xhr.responseJSON.errors).flat().join(' ') :
                            (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message :
                                'Request failed');
                        toast(errors, 'error');
                    })
                    .always(function() {
                        $('#btn-submit-batch').prop('disabled', false).html(
                            '<i class="mdi mdi-check mr-1"></i>Create Batch');
                    });
            });

            // ─── MODAL 3: Fulfill Requisition ─────────────────────────────────────────
            $(document).on('click', '.btn-fulfill-req', function() {
                var reqId = $(this).data('req-id');
                var reqNum = $(this).data('req-number');
                $('#fulfill-req-id').val(reqId);
                $('#fulfill-req-number').text('#' + reqNum);
                $('#modal-fulfill-req').modal('show');
                loadFulfillDetails(reqId);
            });

            function loadFulfillDetails(reqId) {
                $('#fulfill-modal-body').html(
                    '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading…</p></div>'
                );

                $.get('{{ url('inventory/requisitions') }}/' + reqId + '/available-batches')
                    .done(function(res) {
                        if (!res.success) {
                            $('#fulfill-modal-body').html('<div class="alert alert-danger">' + escHtml(res
                                .message || 'Error') + '</div>');
                            return;
                        }
                        renderFulfillForm(res);
                    })
                    .fail(function(xhr) {
                        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message :
                            'Failed to load details';
                        $('#fulfill-modal-body').html('<div class="alert alert-danger">' + escHtml(msg) + '</div>');
                    });
            }

            function renderFulfillForm(res) {
                var items = res.items || [];
                var html = '';

                if (items.length === 0) {
                    $('#fulfill-modal-body').html(
                        '<div class="alert alert-warning">No items found for this requisition.</div>');
                    return;
                }

                $.each(items, function(i, item) {
                    var pkgHint = '';
                    if (item.packaging && item.packaging.length > 0) {
                        var p = item.packaging[0];
                        var packs = (item.requested_qty / p.base_unit_qty).toFixed(1);
                        pkgHint = ` <small class="text-muted">(${packs} ${p.name})</small>`;
                    }

                    html += `<div class="card mb-3 fulfill-item-row" style="border-left:3px solid {{ $hosColor }};" data-index="${i}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>${escHtml(item.product_name)}</strong>
                        <span class="badge badge-info">Requested: ${item.requested_qty}${pkgHint} | Fulfilled: ${item.fulfilled_qty || 0}</span>
                    </div>
                    <input type="hidden" name="items[${i}][requisition_item_id]" value="${item.id}">
                    <input type="hidden" name="items[${i}][product_id]" value="${item.product_id}">`;

                    if (item.available_batches && item.available_batches.length > 0) {
                        html += `<div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Batch</th><th>Available</th><th>Expiry</th><th>Fulfil Qty</th></tr></thead><tbody>`;
                        $.each(item.available_batches, function(_, batch) {
                            var batchPkgHint = '';
                            if (item.packaging && item.packaging.length > 0) {
                                var p = item.packaging[0];
                                if (batch.current_qty >= p.base_unit_qty) {
                                    var bPacks = (batch.current_qty / p.base_unit_qty).toFixed(1);
                                    batchPkgHint = `<div class="small text-muted">≈ ${bPacks} ${p.name}</div>`;
                                }
                            }

                            html += `<tr>
                        <td><code>${escHtml(batch.batch_number)}</code></td>
                        <td>${batch.current_qty}${batchPkgHint}</td>
                        <td>${batch.expiry_date || '—'}</td>
                        <td><input type="number" name="items[${i}][batches][${batch.id}]" class="form-control form-control-sm" min="0" max="${batch.current_qty}" value="0" style="width:80px;"></td>
                    </tr>`;
                        });
                        html += '</tbody></table></div>';
                    } else {
                        html +=
                            '<div class="alert alert-warning py-2 mb-0">No stock available in source store</div>';
                    }

                    html += '</div></div>';
                });

                $('#fulfill-modal-body').html(html);
            }

            $('#form-fulfill-req').on('submit', function(e) {
                e.preventDefault();
                var reqId = $('#fulfill-req-id').val();
                if (!reqId) return;
                var btn = $('#btn-submit-fulfill').prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm"></span> Fulfilling…');

                $.ajax({
                        url: '{{ url('inventory/requisitions') }}/' + reqId + '/fulfill',
                        method: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json',
                    })
                    .done(function(res) {
                        if (res.success) {
                            toast('Requisition fulfilled successfully');
                            $('#modal-fulfill-req').modal('hide');
                            loadTally();
                            location.reload(); // refresh pending panels
                        } else {
                            toast(res.message || 'Error fulfilling requisition', 'error');
                        }
                    })
                    .fail(function(xhr) {
                        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message :
                            'Request failed';
                        toast(msg, 'error');
                    })
                    .always(function() {
                        $('#btn-submit-fulfill').prop('disabled', false).html(
                            '<i class="mdi mdi-check mr-1"></i>Submit Fulfillment');
                    });
            });

            // ─── MODAL 4: New PO ──────────────────────────────────────────────────────
            $('#btn-add-po-item').on('click', function() {
                poItemIndex++;
                var row = `<div class="po-item-row" data-index="${poItemIndex}">
            <div class="row align-items-end mb-2">
                <div class="col-md-4">
                    <select name="items[${poItemIndex}][product_id]" class="form-control po-product-select" required onchange="loadProductPackaging(this, ${poItemIndex}, 'po')">
                        <option value="">— Select Product —</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}" data-base-unit="{{ $p->base_unit_name ?? 'Piece' }}">{{ $p->product_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="items[${poItemIndex}][packaging_id]" class="form-control po-packaging-select" onchange="calculatePoBaseQty(${poItemIndex})">
                        <option value="">Base Unit</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" name="items[${poItemIndex}][packaging_qty]" class="form-control po-qty-input" placeholder="Qty" min="1" required oninput="calculatePoBaseQty(${poItemIndex})">
                    <small class="text-muted po-base-qty-hint" id="po-base-qty-hint-${poItemIndex}"></small>
                    <input type="hidden" name="items[${poItemIndex}][ordered_qty]" class="po-base-qty-hidden">
                </div>
                <div class="col-md-2">
                    <input type="number" name="items[${poItemIndex}][unit_cost]" class="form-control po-cost-input" placeholder="Unit Cost" step="0.01" min="0" required oninput="calculatePoBaseQty(${poItemIndex})">
                    <input type="hidden" name="items[${poItemIndex}][base_unit_cost]" class="po-base-cost-hidden">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-po-item w-100" style="border-radius:6px;">
                        <i class="mdi mdi-delete-outline"></i>
                    </button>
                </div>
            </div>
        </div>`;
                $('#po-items-container').append(row);

                // Initialize Select2 on all selects in the new row
                if ($.fn.select2) {
                    var $newRow = $(`.po-item-row[data-index="${poItemIndex}"]`);
                    $newRow.find('select').each(function() {
                        var $sel = $(this);
                        $sel.select2({
                            dropdownParent: $('#modal-new-po'),
                            width: '100%',
                            placeholder: $sel.find('option:first').text() || 'Select…'
                        });
                    });
                }

                updateRemovePoButtons();
            });

            $(document).on('click', '.btn-remove-po-item', function() {
                $(this).closest('.po-item-row').remove();
                updateRemovePoButtons();
            });

            function updateRemovePoButtons() {
                var rows = $('.po-item-row');
                rows.find('.btn-remove-po-item').prop('disabled', rows.length <= 1);
            }

            function submitPo(action) {
                var formData = $('#form-new-po').serialize() + '&action=' + action;
                var btn = action === 'submit' ? $('#btn-submit-po') : $('#btn-save-po');
                btn.prop('disabled', true).prepend('<span class="spinner-border spinner-border-sm mr-1"></span>');

                $.ajax({
                        url: '{{ route('inventory.purchase-orders.store') }}',
                        method: 'POST',
                        data: formData.replace(/ordered_qty=/g, 'ignore_qty=').replace(/base_ordered_qty=/g, 'ordered_qty=').replace(/unit_cost=/g, 'ignore_cost=').replace(/base_unit_cost=/g, 'unit_cost='),
                        dataType: 'json',
                    })
                    .done(function(res) {
                        if (res.success) {
                            toast(res.message || 'Purchase order created');
                            $('#modal-new-po').modal('hide');
                            $('#form-new-po')[0].reset();
                            poItemIndex = 0;
                            updateRemovePoButtons();
                            loadTally();
                        } else {
                            toast(res.message || 'Error creating PO', 'error');
                        }
                    })
                    .fail(function(xhr) {
                        var errors = xhr.responseJSON && xhr.responseJSON.errors ?
                            Object.values(xhr.responseJSON.errors).flat().join(' ') :
                            (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message :
                                'Request failed');
                        toast(errors, 'error');
                    })
                    .always(function() {
                        btn.prop('disabled', false);
                        $('#btn-save-po').html('<i class="mdi mdi-content-save mr-1"></i>Save Draft');
                        $('#btn-submit-po').html('<i class="mdi mdi-send mr-1"></i>Submit PO');
                    });
            }

            $('#btn-save-po').on('click', function(e) {
                e.preventDefault();
                submitPo('save');
            });
            $('#btn-submit-po').on('click', function() {
                submitPo('submit');
            });

            // ─── MODAL 5: Receive PO ──────────────────────────────────────────────────
            $(document).on('click', '.btn-receive-po', function() {
                var poId = $(this).data('po-id');
                var poNum = $(this).data('po-number');
                $('#receive-po-id').val(poId);
                $('#receive-po-number').text(poNum);
                $('#modal-receive-po').modal('show');
                loadReceiveDetails(poId);
            });

            function loadReceiveDetails(poId) {
                $('#receive-modal-body').html(
                    '<div class="text-center py-4"><div class="spinner-border" role="status" style="color:#8b5cf6;"></div><p class="mt-2 text-muted">Loading PO details…</p></div>'
                );

                $.get('{{ url('inventory/purchase-orders') }}/' + poId)
                    .done(function(res) {
                        // For PO show, we need to build a form from items
                        // Fetch PO data via the receive page endpoint
                    })
                    .fail(function() {});

                // Fetch from receive page endpoint (server renders items)
                $.get('{{ url('inventory/purchase-orders') }}/' + poId + '/receive')
                    .done(function(html) {
                        // Extract items table from the page
                        var $page = $($.parseHTML(html));
                        var items = [];

                        // Try to find PO items from JSON in page or build our own form
                        // We build a simple receive form using data attributes
                        $.get('{{ url('inventory/purchase-orders') }}/' + poId, {
                                _accept: 'json'
                            }, null, 'json')
                            .done(function(poRes) {})
                            .fail(function() {});

                        // Build the receive form directly
                        buildReceiveForm(poId);
                    })
                    .fail(function(xhr) {
                        buildReceiveForm(poId);
                    });
            }

            function buildReceiveForm(poId) {
                // We fetch the PO via the existing JSON endpoint
                // Since there's no dedicated PO JSON endpoint, we call the show page and parse
                // Instead, use an inline AJAX approach by re-fetching PO data from our tally data context
                // and building form fields manually per the known PO items

                // Simple approach: show a message and link to the full receive page
                $('#receive-modal-body').html(
                    '<div class="alert alert-info mb-3">For complex receives with batch numbers, use the full receive page.</div>' +
                    '<div id="receive-items-container"></div>'
                );

                // Fetch the PO's pending items from the server
                $.ajax({
                        url: '{{ url('inventory/purchase-orders') }}/' + poId,
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                    })
                    .done(function(res) {
                        if (res && res.items) {
                            renderReceiveItems(res.items);
                        } else {
                            $('#receive-modal-body').html(
                                '<div class="alert alert-warning">Could not load PO items inline. ' +
                                '<a href="{{ url('inventory/purchase-orders') }}/' + poId +
                                '/receive" target="_blank" class="alert-link">Open receive page <i class="mdi mdi-open-in-new"></i></a></div>'
                            );
                        }
                    })
                    .fail(function() {
                        $('#receive-modal-body').html(
                            '<div class="alert alert-warning mb-0">Could not load PO items inline. ' +
                            '<a href="{{ url('inventory/purchase-orders') }}/' + poId +
                            '/receive" target="_blank" class="alert-link">Open full receive page <i class="mdi mdi-open-in-new"></i></a></div>'
                        );
                    });
            }

            function renderReceiveItems(items) {
                var html = '<div class="mb-2"><strong>' + items.length + ' item(s) to receive</strong></div>';
                $.each(items, function(i, item) {
                    var remaining = (item.ordered_qty || 0) - (item.received_qty || 0);
                    var productId = item.product_id;
                    html += `<div class="card mb-3 receive-item-row" style="border-left:3px solid #8b5cf6;" data-index="${i}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>${escHtml(item.product ? item.product.product_name : 'Product #' + item.product_id)}</strong>
                        <span class="badge badge-secondary">Remaining: ${remaining} base units</span>
                    </div>
                    <input type="hidden" name="items[${i}][item_id]" value="${item.id}">
                    <div class="row align-items-end">
                        <div class="col-md-2">
                            <label class="small font-weight-bold">Packaging</label>
                            <select name="items[${i}][packaging_id]" class="form-control form-control-sm rec-packaging-select" onchange="updateRecBaseQty(${i}, ${remaining})">
                                <option value="">Base Unit</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small font-weight-bold">Qty <span class="text-danger">*</span></label>
                            <input type="number" name="items[${i}][qty]" class="form-control form-control-sm rec-qty-input" min="1" value="${remaining}" required oninput="updateRecBaseQty(${i}, ${remaining})">
                            <small class="text-muted rec-base-qty-hint"></small>
                            <input type="hidden" name="items[${i}][base_qty]" class="rec-base-qty-hidden" value="${remaining}">
                        </div>
                        <div class="col-md-3">
                            <label class="small font-weight-bold">Batch <span class="text-danger">*</span></label>
                            <input type="text" name="items[${i}][batch_number]" class="form-control form-control-sm" maxlength="100" required>
                        </div>
                        <div class="col-md-2">
                            <label class="small font-weight-bold">Expiry</label>
                            <input type="date" name="items[${i}][expiry_date]" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="small font-weight-bold">Cost (per Unit)</label>
                            <input type="number" name="items[${i}][actual_cost]" class="form-control form-control-sm rec-cost-input" step="0.01" min="0" value="${item.unit_cost || ''}" oninput="updateRecBaseQty(${i}, ${remaining})">
                            <input type="hidden" name="items[${i}][base_actual_cost]" class="rec-base-cost-hidden" value="${item.unit_cost || ''}">
                        </div>
                    </div>
                </div>
            </div>`;
                    
                    // Fetch packaging for this product
                    $.get('{{ route("products.packagings", ":id") }}'.replace(':id', productId))
                        .done(function(res) {
                            if (res.packagings) {
                                var $sel = $(`.receive-item-row[data-index="${i}"] .rec-packaging-select`);
                                $sel.data('prev-factor', 1);
                                $.each(res.packagings, function(_, p) {
                                    $sel.append(`<option value="${p.id}" data-qty="${p.base_unit_qty}">${p.name} (${p.base_unit_qty})</option>`);
                                });
                            }
                        });
                });

                html += `<div class="row mt-2">
            <div class="col-md-6">
                <label class="small font-weight-bold">Invoice Number</label>
                <input type="text" name="invoice_number" class="form-control form-control-sm" maxlength="100">
            </div>
            <div class="col-md-6">
                <label class="small font-weight-bold">Receiving Notes</label>
                <textarea name="receiving_notes" class="form-control form-control-sm" rows="2"></textarea>
            </div>
        </div>`;

                $('#receive-modal-body').html(html);
            }

            $('#form-receive-po').on('submit', function(e) {
                e.preventDefault();
                var poId = $('#receive-po-id').val();
                if (!poId) return;
                var btn = $('#btn-submit-receive').prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm"></span> Processing…');

                $.ajax({
                        url: '{{ url('inventory/purchase-orders') }}/' + poId + '/receive',
                        method: 'POST',
                        data: $(this).serialize().replace(/qty=/g, 'ignore_qty=').replace(/base_qty=/g, 'qty=').replace(/actual_cost=/g, 'ignore_cost=').replace(/base_actual_cost=/g, 'actual_cost='),
                        dataType: 'json',
                    })
                    .done(function(res) {
                        if (res.success) {
                            toast(res.message || 'Items received successfully');
                            $('#modal-receive-po').modal('hide');
                            loadTally();
                            location.reload();
                        } else {
                            toast(res.message || 'Error processing receipt', 'error');
                        }
                    })
                    .fail(function(xhr) {
                        var errors = xhr.responseJSON && xhr.responseJSON.errors ?
                            Object.values(xhr.responseJSON.errors).flat().join(' ') :
                            (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message :
                                'Request failed');
                        toast(errors, 'error');
                    })
                    .always(function() {
                        $('#btn-submit-receive').prop('disabled', false).html(
                            '<i class="mdi mdi-check mr-1"></i>Confirm Receipt');
                    });
            });

            // ─── Security helper: HTML escaping ──────────────────────────────────────
            function escHtml(str) {
                if (str === null || str === undefined) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            // ─── Packaging Helpers ───────────────────────────────────────────────────
            window.calculateReqBaseQty = function(index) {
                var row = $(`.req-item-row[data-index="${index}"]`);
                var qty = parseInt(row.find('.req-qty-input').val()) || 0;
                var $pkg = row.find('.req-packaging-select option:selected');
                var factor = parseFloat($pkg.data('qty')) || 1;
                var baseQty = qty * factor;
                row.find('.req-base-qty-hidden').val(baseQty);
                
                var baseUnit = row.find('.req-product-select option:selected').data('base-unit') || 'units';
                if (factor > 1 && qty > 0) {
                    row.find('.req-base-qty-hint').text(`= ${baseQty} ${baseUnit}`);
                } else {
                    row.find('.req-base-qty-hint').text('');
                }
            };

            window.calculateBatchBaseQty = function() {
                var qty = parseInt($('#batch-qty').val()) || 0;
                var $pkg = $('#batch-packaging option:selected');
                var factor = parseFloat($pkg.data('qty')) || 1;
                var baseQty = qty * factor;
                $('#batch-base-qty-hidden').val(baseQty);
                
                var baseUnit = $('#batch-product option:selected').data('base-unit') || 'units';
                if (factor > 1 && qty > 0) {
                    $('#batch-qty-hint').text(`= ${baseQty} ${baseUnit}`);
                } else {
                    $('#batch-qty-hint').text('');
                }
            };

            window.loadProductPackaging = function(select, index, type) {
                var productId = $(select).val();
                var $pkgSelect;
                
                if (type === 'batch') {
                    $pkgSelect = $('#batch-packaging');
                } else {
                    var $row = $(select).closest('.' + type + '-item-row');
                    $pkgSelect = $row.find('.' + type + '-packaging-select');
                }
                
                $pkgSelect.html('<option value="" data-qty="1">Base Unit</option>');
                $pkgSelect.data('prev-factor', 1);
                if (!productId) return;

                $.get('{{ route("products.packagings", ":id") }}'.replace(':id', productId))
                    .done(function(res) {
                        if (res.packagings) {
                            $.each(res.packagings, function(_, p) {
                                $pkgSelect.append(`<option value="${p.id}" data-qty="${p.base_unit_qty}">${p.name} (${p.base_unit_qty})</option>`);
                            });
                        }
                        if ($pkgSelect.hasClass('select2-hidden-accessible')) {
                            $pkgSelect.trigger('change.select2');
                        }
                    });
            }

            window.calculatePoBaseQty = function(index) {
                var $row = $(`.po-item-row[data-index="${index}"]`);
                var $pkgSelect = $row.find('.po-packaging-select');
                var qty = parseFloat($row.find('.po-qty-input').val()) || 0;
                var $costInput = $row.find('.po-cost-input');
                var cost = parseFloat($costInput.val()) || 0;
                var $pkgOpt = $pkgSelect.find('option:selected');
                var factor = parseFloat($pkgOpt.data('qty')) || 1;
                var prevFactor = parseFloat($pkgSelect.data('prev-factor')) || 1;
                var baseUnit = $row.find('.po-product-select option:selected').data('base-unit') || 'Piece';

                // Scale cost if factor changed
                if (window.event && window.event.type === 'change' && $(window.event.target).hasClass('po-packaging-select')) {
                    if (cost > 0) {
                        cost = (cost / prevFactor) * factor;
                        $costInput.val(parseFloat(cost.toFixed(4)));
                    }
                }
                $pkgSelect.data('prev-factor', factor);

                var baseQty = qty * factor;
                var baseCost = factor > 0 ? (cost / factor) : cost;

                $row.find('.po-base-qty-hidden').val(baseQty);
                $row.find('.po-base-cost-hidden').val(baseCost);
                
                if (factor > 1) {
                    $row.find('.po-base-qty-hint').text(`= ${baseQty} ${baseUnit}`);
                } else {
                    $row.find('.po-base-qty-hint').text('');
                }
            }

            window.calculateReqBaseQty = function(index) {
                var $row = $(`.req-item-row[data-index="${index}"]`);
                var qty = parseFloat($row.find('.req-qty-input').val()) || 0;
                var $pkgOpt = $row.find('.req-packaging-select option:selected');
                var factor = parseFloat($pkgOpt.data('qty')) || 1;
                
                var baseQty = qty * factor;
                $row.find('.req-base-qty-hidden').val(baseQty);
                
                if (factor > 1) {
                    $row.find('.req-base-qty-hint').text(`= ${baseQty} base units`);
                } else {
                    $row.find('.req-base-qty-hint').text('');
                }
            }

            window.calculateAdjBaseQty = function() {
                var qty = parseFloat($('#adj-qty').val()) || 0;
                var $pkgOpt = $('#adj-packaging option:selected');
                var factor = parseFloat($pkgOpt.data('qty')) || 1;
                var currentStock = adjSelectedBatch ? adjSelectedBatch.current_qty : 0;
                
                var baseQty = qty * factor;
                $('#adj-base-qty-hidden').val(baseQty);
                
                if (adjSelectedType === 'subtract' && baseQty > currentStock) {
                    $('#adj-qty-hint').html(`<span class="text-danger">Exceeds available stock (${currentStock} pieces)</span>`);
                    $('#btn-apply-adjustment').prop('disabled', true);
                } else {
                    if (factor > 1) {
                        $('#adj-qty-hint').text(`= ${baseQty} pieces`);
                    } else {
                        $('#adj-qty-hint').text('');
                    }
                    if (adjSelectedType && qty > 0) $('#btn-apply-adjustment').prop('disabled', false);
                }
            }

            window.updateRecBaseQty = function(index, remaining) {
                var $row = $(`.receive-item-row[data-index="${index}"]`);
                var $pkgSelect = $row.find('.rec-packaging-select');
                var qty = parseFloat($row.find('.rec-qty-input').val()) || 0;
                var $costInput = $row.find('.rec-cost-input');
                var cost = parseFloat($costInput.val()) || 0;
                var $pkgOpt = $pkgSelect.find('option:selected');
                var factor = parseFloat($pkgOpt.data('qty')) || 1;
                var prevFactor = parseFloat($pkgSelect.data('prev-factor')) || 1;

                // Scale cost if factor changed
                if (window.event && window.event.type === 'change' && $(window.event.target).hasClass('rec-packaging-select')) {
                    if (cost > 0) {
                        cost = (cost / prevFactor) * factor;
                        $costInput.val(parseFloat(cost.toFixed(4)));
                    }
                }
                $pkgSelect.data('prev-factor', factor);
                
                var baseQty = qty * factor;
                var baseCost = factor > 0 ? (cost / factor) : cost;

                $row.find('.rec-base-qty-hidden').val(baseQty);
                $row.find('.rec-base-cost-hidden').val(baseCost);
                
                if (baseQty > remaining) {
                    $row.find('.rec-base-qty-hint').html(`<span class="text-danger">Exceeds remaining (${remaining})</span>`);
                    $('#btn-submit-receive').prop('disabled', true);
                } else {
                    $row.find('.rec-base-qty-hint').text(factor > 1 ? `= ${baseQty} pieces` : '');
                    $('#btn-submit-receive').prop('disabled', false);
                }
            }

            // ─── CSRF for all AJAX ────────────────────────────────────────────────────
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // ─── Select2 initialization ───────────────────────────────────────────────
            function initSelect2InModal(modalId) {
                var $modal = $('#' + modalId);
                $modal.find('select').each(function() {
                    var $sel = $(this);
                    if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
                    $sel.select2({
                        dropdownParent: $modal,
                        width: '100%',
                        placeholder: $sel.find('option:first').text() || 'Select…',
                    });
                });
            }

            // Init select2 on all page-level selects (filter bar)
            (function initPageSelects() {
                if (!$.fn.select2) return;
                $('#filter-store, #filter-product').each(function() {
                    var $sel = $(this);
                    if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
                    $sel.select2({
                        width: '100%',
                        placeholder: $sel.find('option:first').text() || 'Select…',
                    });
                    // Bind change event after select2 init (select2 fires 'change' too)
                    $sel.on('change.select2', function() {
                        if (this.id === 'filter-store') {
                            currentStoreId = $(this).val();
                        }
                    });
                });
            }());

            // Re-init select2 when modals open
            $('#modal-new-req').on('shown.bs.modal', function() { initSelect2InModal('modal-new-req'); });
            $('#modal-add-batch').on('shown.bs.modal', function() { initSelect2InModal('modal-add-batch'); });
            $('#modal-new-po').on('shown.bs.modal', function() { initSelect2InModal('modal-new-po'); });
            $('#modal-adjust-stock').on('shown.bs.modal', function() {
                initSelect2InModal('modal-adjust-stock');
            });

            // ─── MODAL 6: Adjust Stock ────────────────────────────────────────────────
            var adjSelectedBatch = null;
            var adjSelectedType  = null;

            // Open modal from toolbar button
            $('#tb-adjust-stock').on('click', function() {
                if (!currentStoreId) {
                    toast('Please select a store first', 'warning');
                    return;
                }
                // Reset modal state
                adjSelectedBatch = null;
                adjSelectedType  = null;
                $('#adj-step-1').show();
                $('#adj-step-2').hide();
                $('#btn-apply-adjustment').hide().prop('disabled', true);
                $('#adj-batch-list').html('<div class="text-center text-muted py-4" style="font-size:.85rem;"><i class="mdi mdi-package-variant d-block mb-1" style="font-size:2rem;opacity:.35;"></i>Click "Load Batches" to view available batches</div>');

                // Pre-fill product if product axis is active and one is selected
                var productAxis = (currentAxis === 'product');
                var selectedProduct = $('#filter-product').val();
                if (productAxis && selectedProduct) {
                    $('#adj-product-filter').val(selectedProduct);
                } else {
                    $('#adj-product-filter').val('');
                }
                $('#modal-adjust-stock').modal('show');
            });

            // Load batches button
            $('#adj-load-batches').on('click', function() {
                if (!currentStoreId) {
                    toast('No store selected', 'warning');
                    return;
                }
                var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Loading…');
                var productId = $('#adj-product-filter').val();

                var params = { store_id: currentStoreId };
                if (productId) params.product_id = productId;

                $.get('{{ route('inventory.store-workbench.store-batches') }}', params)
                    .done(function(res) {
                        if (!res.success || !res.batches.length) {
                            $('#adj-batch-list').html('<div class="alert alert-info mb-0">No active batches found for this store.</div>');
                            return;
                        }
                        renderAdjBatchList(res.batches);
                    })
                    .fail(function() {
                        $('#adj-batch-list').html('<div class="alert alert-danger mb-0">Failed to load batches.</div>');
                    })
                    .always(function() {
                        $btn.prop('disabled', false).html('<i class="mdi mdi-refresh mr-1"></i>Load Batches');
                    });
            });

            function renderAdjBatchList(batches) {
                var html = '';
                $.each(batches, function(i, b) {
                    var expiryHtml = '';
                    if (b.is_expired) {
                        expiryHtml = '<span class="adj-expiry-warn ml-2"><i class="mdi mdi-alert"></i> Expired</span>';
                    } else if (b.is_expiring_soon) {
                        expiryHtml = '<span class="adj-expiry-warn ml-2" style="color:#f59e0b;"><i class="mdi mdi-clock-alert"></i> Expiring soon</span>';
                    }
                    var expiryLabel = b.expiry_date ? b.expiry_label : 'No expiry';
                    var productCode = b.product_code ? ' <span class="text-muted">(' + escHtml(b.product_code) + ')</span>' : '';
                    html += '<div class="adj-batch-card" data-batch-id="' + b.id + '" data-batch=\'' + JSON.stringify(b).replace(/'/g, '&#39;') + '\'>' +
                        '<div>' +
                            '<div class="adj-batch-name">' + escHtml(b.product_name) + productCode + '</div>' +
                            '<div class="adj-batch-meta">Batch: <strong>' + escHtml(b.batch_number) + '</strong> · Expiry: ' + escHtml(expiryLabel) + expiryHtml + '</div>' +
                        '</div>' +
                        '<div class="text-right">' +
                            '<div class="adj-batch-qty">' + b.current_qty + '</div>' +
                            '<div class="adj-batch-meta">in stock</div>' +
                        '</div>' +
                    '</div>';
                });
                $('#adj-batch-list').html(html);
            }

            // Batch card click → go to step 2
            $(document).on('click', '.adj-batch-card', function() {
                var batchData = $(this).data('batch');
                if (typeof batchData === 'string') {
                    try { batchData = JSON.parse(batchData); } catch(e) { return; }
                }
                adjSelectedBatch = batchData;
                adjSelectedType  = null;

                // Reset adjustment form
                $('#adj-type-add').prop('checked', false);
                $('#adj-type-subtract').prop('checked', false);
                $('.adjustment-type-btn').removeClass('selected');
                $('#adj-qty').val(1).attr('max', 99999);
                $('#adj-qty-hint').text('');
                $('#adj-reason').val('');
                $('#adj-notes').val('');
                $('#btn-apply-adjustment').prop('disabled', true);

                // Load packaging for this product
                var $pkgSelect = $('#adj-packaging').html('<option value="">Base Unit</option>');
                $.get('{{ route("products.packagings", ":id") }}'.replace(':id', batchData.product_id))
                    .done(function(res) {
                        if (res.packagings) {
                            $.each(res.packagings, function(_, p) {
                                $pkgSelect.append(`<option value="${p.id}" data-qty="${p.base_unit_qty}">${p.name} (${p.base_unit_qty})</option>`);
                            });
                        }
                    });

                // Set hidden batch id
                $('#adj-batch-id').val(batchData.id);

                // Render batch info panel
                var expiryLine = batchData.expiry_date ? batchData.expiry_label : 'No expiry';
                $('#adj-batch-info-panel').html(
                    '<div class="adj-batch-info">' +
                        '<div class="info-row"><span class="info-label">Product</span><span class="info-value">' + escHtml(batchData.product_name) + '</span></div>' +
                        '<div class="info-row"><span class="info-label">Batch #</span><span class="info-value">' + escHtml(batchData.batch_number) + '</span></div>' +
                        '<div class="info-row"><span class="info-label">Current Stock</span><span class="info-value text-success">' + batchData.current_qty + '</span></div>' +
                        '<div class="info-row"><span class="info-label">Expiry</span><span class="info-value">' + escHtml(expiryLine) + '</span></div>' +
                    '</div>'
                );

                $('#adj-qty-hint').text('Max for subtract: ' + batchData.current_qty);
                $('#adj-step-1').hide();
                $('#adj-step-2').show();
                $('#btn-apply-adjustment').show().prop('disabled', true);

                // Re-init select2 for reason select inside modal
                var $reason = $('#adj-reason');
                if ($.fn.select2) {
                    if ($reason.hasClass('select2-hidden-accessible')) $reason.select2('destroy');
                    $reason.select2({ dropdownParent: $('#modal-adjust-stock'), width: '100%', placeholder: '— Select reason —' });
                }
            });

            // Back button → return to batch list
            $('#adj-back-btn').on('click', function() {
                adjSelectedBatch = null;
                $('#adj-step-2').hide();
                $('#adj-step-1').show();
                $('#btn-apply-adjustment').hide().prop('disabled', true);
            });

            // Adjustment type buttons
            window.adjSelectType = function(type) {
                adjSelectedType = type;
                $('.adjustment-type-btn').removeClass('selected');
                $('#adj-btn-' + type).addClass('selected');
                $('#adj-type-' + type).prop('checked', true);

                if (type === 'subtract' && adjSelectedBatch) {
                    $('#adj-qty').attr('max', adjSelectedBatch.current_qty);
                } else {
                    $('#adj-qty').attr('max', 99999);
                }
                calculateAdjBaseQty();
            };

            // Enable submit when reason is filled
            $('#adj-reason').on('change', checkAdjFormReady);
            $('#adj-qty').on('input', checkAdjFormReady);

            function checkAdjFormReady() {
                var typeOk = !!adjSelectedType;
                var qtyOk  = parseInt($('#adj-qty').val()) > 0;
                var reasonOk = !!$('#adj-reason').val();
                
                // Also check if subtract exceeds stock (already handled in calculateAdjBaseQty but good to be sure)
                var factor = parseFloat($('#adj-packaging option:selected').data('qty')) || 1;
                var baseQty = (parseInt($('#adj-qty').val()) || 0) * factor;
                var stockOk = true;
                if (adjSelectedType === 'subtract' && adjSelectedBatch && baseQty > adjSelectedBatch.current_qty) {
                    stockOk = false;
                }

                $('#btn-apply-adjustment').prop('disabled', !(typeOk && qtyOk && reasonOk && stockOk));
            }

            // Submit adjustment
            $('#btn-apply-adjustment').on('click', function() {
                if (!adjSelectedBatch) return;
                var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Applying…');

                $.ajax({
                    url: '{{ url('inventory/store-workbench/batch') }}/' + adjSelectedBatch.id + '/adjust',
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        adjustment_type: adjSelectedType,
                        qty: $('#adj-base-qty-hidden').val(),
                        reason: $('#adj-reason').val(),
                        notes: $('#adj-notes').val(),
                    },
                    dataType: 'json',
                })
                .done(function(res) {
                    if (res.success) {
                        toast(res.message || 'Stock adjusted successfully');
                        $('#modal-adjust-stock').modal('hide');
                        loadTally();
                    } else {
                        toast(res.message || 'Adjustment failed', 'error');
                        $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i>Apply Adjustment');
                    }
                })
                .fail(function(xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Request failed';
                    toast(msg, 'error');
                    $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i>Apply Adjustment');
                });
            });

            // ─── Re-enable apply btn label when modal hides ───────────────────────────
            $('#modal-adjust-stock').on('hidden.bs.modal', function() {
                $('#btn-apply-adjustment').html('<i class="mdi mdi-check mr-1"></i>Apply Adjustment');
            });

            window.calculateAdjBaseQty = function() {
                var qty = parseInt($('#adj-qty').val()) || 0;
                var $pkg = $('#adj-packaging option:selected');
                var factor = parseFloat($pkg.data('qty')) || 1;
                var baseQty = qty * factor;
                $('#adj-base-qty-hidden').val(baseQty);
                
                var baseUnit = adjSelectedBatch ? adjSelectedBatch.base_unit_name : 'units';
                if (factor > 1 && qty > 0) {
                    $('#adj-qty-hint').text(`= ${baseQty} ${baseUnit}`);
                } else {
                    $('#adj-qty-hint').text('');
                }
                checkAdjFormReady();
            };

        })();
    </script>
@endsection
