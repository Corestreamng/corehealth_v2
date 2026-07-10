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
        .modal-filter-pills {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .filter-pill {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            color: #6b7280;
            transition: all .15s;
        }
        .filter-pill:hover { background: #f3f4f6; }
        .filter-pill.active {
            background: {{ $hosColor }};
            color: #fff;
            border-color: {{ $hosColor }};
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
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
                @hasanyrole('SUPERADMIN|ADMIN|STORE')
                <a href="{{ route('inventory.store-workbench.index') }}{{ $selectedStore ? '?store_id=' . $selectedStore->id : '' }}"
                    class="btn btn-light btn-sm" style="border-radius: 8px;">
                    <i class="mdi mdi-arrow-left mr-1"></i> Workbench
                </a>
                @else
                <a href="javascript:history.back()"
                    class="btn btn-light btn-sm" style="border-radius: 8px;">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back
                </a>
                @endhasanyrole
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
            <div class="summary-chip count" id="sum-opening-chip" style="display:none;">
                <span class="chip-label">Opening (B/F)</span>
                <span class="chip-value" id="sum-opening">—</span>
            </div>
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
                            <th style="width:100px;">Date / Time</th>
                            <th style="width:120px;">Action</th>
                            <th style="width:140px;">Batch Details</th>
                            <th class="text-right" style="width:120px; background:#f8f9fa;">Bal B/F</th>
                            <th class="text-center" style="width:120px;">Change (+/-)</th>
                            <th class="text-right" style="width:120px; background:#f1f5f9;">Bal A/F</th>
                            <th style="width:120px;">By</th>
                            <th>Notes</th>
                            <th class="text-right" style="width:80px;">Actions</th>
                        </tr>
                        <tr id="tally-head-store" style="display:none;">
                            <th style="width:100px;">Date / Time</th>
                            <th>Product</th>
                            <th style="width:120px;">Action</th>
                            <th style="width:140px;">Batch Details</th>
                            <th class="text-right" style="width:110px; background:#f8f9fa;">Bal B/F</th>
                            <th class="text-center" style="width:100px;">Change</th>
                            <th class="text-right" style="width:110px; background:#f1f5f9;">Bal A/F</th>
                            <th style="width:100px;">By</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tally-body">
                        <tr>
                            <td colspan="10" class="tally-empty">
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

                    {{-- Panel D: Pending Damages --}}
                    <div class="col-lg-4 mt-3">
                        <div class="pending-panel" id="panel-damages">
                            <div class="pending-panel-header">
                                <h6>
                                    <i class="mdi mdi-alert-circle-outline" style="color:#dc2626;"></i>
                                    Pending Damages
                                    <span class="badge badge-pill" id="badge-damages"
                                        style="background:#dc2626;color:#fff;">0</span>
                                </h6>
                                <button class="btn btn-sm btn-outline-secondary btn-refresh-panel" data-panel="damages" style="border-radius:6px; font-size:0.75rem;">
                                    <i class="mdi mdi-refresh"></i>
                                </button>
                            </div>
                            <div class="pending-panel-body" id="damages-list">
                                <div class="text-center text-muted py-4" style="font-size:0.84rem;">
                                    <i class="mdi mdi-loading mdi-spin d-block mb-1" style="font-size:1.8rem; opacity:.4;"></i>
                                    Loading...
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Panel E: Pending Requisition Returns --}}
                    <div class="col-lg-4 mt-3">
                        <div class="pending-panel" id="panel-req-returns">
                            <div class="pending-panel-header">
                                <h6>
                                    <i class="mdi mdi-undo-variant" style="color:#0d9488;"></i>
                                    Req Returns
                                    <span class="badge badge-pill" id="badge-req-returns"
                                        style="background:#0d9488;color:#fff;">0</span>
                                </h6>
                                <button class="btn btn-sm btn-outline-secondary btn-refresh-panel" data-panel="req_returns" style="border-radius:6px; font-size:0.75rem;">
                                    <i class="mdi mdi-refresh"></i>
                                </button>
                            </div>
                            <div class="pending-panel-body" id="req-returns-list">
                                <div class="text-center text-muted py-4" style="font-size:0.84rem;">
                                    <i class="mdi mdi-loading mdi-spin d-block mb-1" style="font-size:1.8rem; opacity:.4;"></i>
                                    Loading...
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Panel F: Pending PO Returns --}}
                    <div class="col-lg-4 mt-3">
                        <div class="pending-panel" id="panel-po-returns">
                            <div class="pending-panel-header">
                                <h6>
                                    <i class="mdi mdi-receipt" style="color:#6366f1;"></i>
                                    PO Returns
                                    <span class="badge badge-pill" id="badge-po-returns"
                                        style="background:#6366f1;color:#fff;">0</span>
                                </h6>
                                <button class="btn btn-sm btn-outline-secondary btn-refresh-panel" data-panel="po_returns" style="border-radius:6px; font-size:0.75rem;">
                                    <i class="mdi mdi-refresh"></i>
                                </button>
                            </div>
                            <div class="pending-panel-body" id="po-returns-list">
                                <div class="text-center text-muted py-4" style="font-size:0.84rem;">
                                    <i class="mdi mdi-loading mdi-spin d-block mb-1" style="font-size:1.8rem; opacity:.4;"></i>
                                    Loading...
                                </div>
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
            <button class="toolbar-btn" id="tb-record-damage" style="background:#dc2626; color:#fff;">
                <i class="mdi mdi-alert-circle-outline"></i> Record Damage
            </button>
            <button class="toolbar-btn" id="tb-return-req" style="background:#0d9488; color:#fff;">
                <i class="mdi mdi-undo-variant"></i> Return Req Items
            </button>
            <button class="toolbar-btn" id="tb-return-po" style="background:#6366f1; color:#fff;">
                <i class="mdi mdi-truck-delivery-outline"></i> Return PO Items
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
                                            required onchange="loadProductPackaging($(this).val(), $(this).find('option:selected').data('base-unit'), $(this).closest('.req-item-row').find('.req-packaging-select'))">
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                    <select name="product_id" id="batch-product" class="form-control" required onchange="loadProductPackaging($(this).val(), $(this).find('option:selected').data('base-unit'), $('#batch-packaging'))" style="border-radius:8px;">
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
                                    <label class="small font-weight-bold" id="batch-cost-price-label">Cost Price (₦) <span class="text-danger">*</span></label>
                                    <input type="number" name="cost_price" id="batch-cost-price" class="form-control" step="0.01" min="0" required style="border-radius:8px;" placeholder="0.00 — enter 0 for donations" oninput="updateBatchCostPreview()">
                                    <div class="custom-control custom-checkbox mt-2">
                                        <input type="checkbox" class="custom-control-input" name="skip_cost_price" id="tally_skip_cost_price" value="1" onchange="toggleTallyCostRequirement(this)">
                                        <label class="custom-control-label" for="tally_skip_cost_price" style="font-size: 0.8rem;">Skip Cost Price (Not Recommended)</label>
                                    </div>
                                    <small class="text-muted" style="display:block; margin-top: 5px;">Enter cost per <strong>selected packaging unit</strong>. The system converts this to a base-unit cost for storage.</small>
                                    <div id="batch-cost-preview" class="mt-1" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:6px 10px; font-size:0.78rem;">
                                        <i class="mdi mdi-calculator text-success mr-1"></i>
                                        <span id="batch-cost-preview-text"></span>
                                    </div>
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
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px; font-weight:600; color:#64748b;">Cancel</button>
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
                                            required onchange="loadProductPackaging($(this).val(), $(this).find('option:selected').data('base-unit'), $(this).closest('.po-item-row').find('.po-packaging-select'))">
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
                                        <option value="{{ $p->id }}" data-base-unit="{{ $p->base_unit_name ?? 'Piece' }}">{{ $p->product_name }}{{ $p->product_code ? ' (' . $p->product_code . ')' : '' }}</option>
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
                                    <input type="number" id="adj-qty" class="form-control" min="1" value="1" required oninput="calculateAdjBaseQty()">
                                    <small class="text-muted" id="adj-qty-hint"></small>
                                    <input type="hidden" name="qty" id="adj-base-qty-hidden">
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

    {{-- ═══════════════════════════════════════════════════════════
     MODAL 7 — Record Store Damage
═══════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modal-record-damage" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="mdi mdi-alert-circle-outline mr-2" style="color:#dc2626;"></i>Record Store Damage</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{-- Step 1: Batch selector --}}
                    <div id="dmg-step-1">
                        <div class="mb-3">
                            <label class="font-weight-600 mb-1" style="font-size:0.75rem; color:#6b7280; text-transform:uppercase;">Batch Filter</label>
                            <div class="modal-filter-pills" id="dmg-filter-status">
                                <div class="filter-pill active" data-value="recent">Recently Received</div>
                                <div class="filter-pill" data-value="near-expiry">Near Expiry</div>
                                <div class="filter-pill" data-value="low-stock">Low Stock</div>
                                <div class="filter-pill" data-value="all">All Batches</div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="font-weight-600 mb-1">Filter by Product</label>
                                <select id="dmg-product-filter" class="form-control">
                                    <option value="">— Search product —</option>
                                    @foreach ($products as $p)
                                        <option value="{{ $p->id }}" data-base-unit="{{ $p->base_unit_name ?? 'Piece' }}">{{ $p->product_name }}{{ $p->product_code ? ' (' . $p->product_code . ')' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button id="dmg-load-batches" class="btn btn-secondary btn-sm" style="border-radius:8px;">
                                    <i class="mdi mdi-refresh mr-1"></i>Load Batches
                                </button>
                            </div>
                        </div>
                        <div id="dmg-batch-list">
                            <div class="text-center text-muted py-4" style="font-size:0.85rem;">
                                <i class="mdi mdi-package-variant d-block mb-1" style="font-size:2rem; opacity:.35;"></i>
                                Select a product and click "Load Batches"
                            </div>
                        </div>
                    </div>

                    {{-- Step 2: Damage form --}}
                    <div id="dmg-step-2" style="display:none;">
                        <div class="adj-batch-info mb-3" id="dmg-batch-info-panel"></div>

                        <form id="form-record-damage">
                            @csrf
                            <input type="hidden" name="store_id" id="damage-store-id">
                            <input type="hidden" name="batch_id" id="damage-batch-id">
                            <input type="hidden" name="product_id" id="damage-product-id">

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Damage Type <span class="text-danger">*</span></label>
                                        <select name="damage_type" id="damage-type" class="form-control" required>
                                            <option value="">— Select —</option>
                                            <option value="expired">Expired</option>
                                            <option value="broken">Broken</option>
                                            <option value="contaminated">Contaminated</option>
                                            <option value="spoiled">Spoiled</option>
                                            <option value="theft">Theft</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Packaging</label>
                                        <select id="dmg-packaging" name="packaging_id" class="form-control" onchange="calculateDmgBaseQty()">
                                            <option value="">Base Unit</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Qty Damaged <span class="text-danger">*</span></label>
                                        <input type="number" id="damage-qty" class="form-control" min="1" required oninput="calculateDmgBaseQty()">
                                        <small class="text-muted" id="dmg-qty-hint"></small>
                                        <input type="hidden" name="qty_damaged" id="dmg-base-qty-hidden">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Unit Cost <span class="text-danger">*</span></label>
                                        <input type="number" name="unit_cost" id="damage-unit-cost" class="form-control" step="0.01" min="0" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Date Discovered <span class="text-danger">*</span></label>
                                        <input type="date" name="discovered_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Damage Reason <span class="text-danger">*</span></label>
                                        <input type="text" name="damage_reason" class="form-control" placeholder="Describe the damage…" required>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <button id="dmg-back-btn" class="btn btn-link btn-sm text-muted px-0 mb-2">
                            <i class="mdi mdi-arrow-left mr-1"></i>Back to batch list
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn" id="btn-submit-damage" style="background:#dc2626; color:#fff; display:none;" disabled>
                        <i class="mdi mdi-check mr-1"></i>Record Damage
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
     MODAL 8 — Return Requisition Items
═══════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modal-return-req" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="mdi mdi-undo-variant mr-2" style="color:#0d9488;"></i>Return Requisition Items</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{-- Step 1: Requisition Selector --}}
                    <div id="ret-step-1">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="mb-2">
                                    <label class="font-weight-600 mb-1" style="font-size:0.75rem; color:#6b7280; text-transform:uppercase;">Involvement</label>
                                    <div class="modal-filter-pills" id="ret-filter-involvement">
                                        <div class="filter-pill active" data-value="received">Received</div>
                                        <div class="filter-pill" data-value="sent">Sent</div>
                                        <div class="filter-pill" data-value="all">All Requisitions</div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="font-weight-600 mb-1" style="font-size:0.75rem; color:#6b7280; text-transform:uppercase;">Timeframe</label>
                                    <div class="modal-filter-pills" id="ret-filter-date">
                                        <div class="filter-pill" data-value="7">Last 7 Days</div>
                                        <div class="filter-pill active" data-value="30">Last 30 Days</div>
                                        <div class="filter-pill" data-value="all">All Time</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label class="font-weight-600 mb-1">Search Requisition</label>
                                <div class="input-group">
                                    <input type="text" id="ret-search-input" class="form-control" placeholder="REQ-0001 or ID">
                                    <button id="ret-btn-search" class="btn btn-secondary" type="button">
                                        <i class="mdi mdi-magnify mr-1"></i>Search
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="ret-requisition-results">
                             <div class="text-center text-muted py-4" style="font-size:0.85rem;">
                                <i class="mdi mdi-file-search-outline d-block mb-1" style="font-size:2rem; opacity:.35;"></i>
                                Search for a fulfilled requisition to return items
                            </div>
                        </div>
                    </div>

                    {{-- Step 2: Item Selection --}}
                    <div id="ret-step-2" style="display:none;">
                         <div class="adj-batch-info mb-3" id="ret-req-info-panel"></div>
                         <label class="font-weight-600 mb-2">Select Item to Return</label>
                         <div id="ret-item-list"></div>
                         <button id="ret-back-to-search" class="btn btn-link btn-sm text-muted px-0 mt-2">
                            <i class="mdi mdi-arrow-left mr-1"></i>Back to search
                        </button>
                    </div>

                    {{-- Step 3: Return Form --}}
                    <div id="ret-step-3" style="display:none;">
                        <div class="adj-batch-info mb-3" id="ret-item-info-panel"></div>
                        <form id="form-return-req">
                            @csrf
                            <input type="hidden" name="store_requisition_id" id="ret-requisition-id-hidden">
                            <input type="hidden" name="store_requisition_item_id" id="ret-item-id-hidden">
                            <input type="hidden" name="product_id" id="ret-product-id-hidden">
                            <input type="hidden" name="source_store_id" id="ret-source-store-id">
                            <input type="hidden" name="destination_store_id" id="ret-dest-store-id">
                            <div id="ret-direction-hint" class="alert alert-soft-info py-2 mb-3" style="font-size:0.8rem; border-radius:8px;"></div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Batch <small class="text-muted">(Optional)</small></label>
                                        <select name="batch_id" id="ret-batch-select" class="form-control">
                                            <option value="">— Auto / FIFO —</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Qty to Return <span class="text-danger">*</span></label>
                                        <input type="number" name="qty_returned" id="ret-qty" class="form-control" min="1" required>
                                        <small id="ret-max-qty-hint" class="form-text text-muted"></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Return Condition <span class="text-danger">*</span></label>
                                        <select name="return_condition" class="form-control" required>
                                            <option value="good">Good Condition</option>
                                            <option value="damaged">Damaged</option>
                                            <option value="expired">Expired</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Notes / Reason <span class="text-danger">*</span></label>
                                <textarea name="return_reason" class="form-control" rows="2" required placeholder="Why are these items being returned?"></textarea>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="restock" value="1" id="ret-restock" checked>
                                <label class="form-check-label" for="ret-restock">
                                    Restock items at origin store
                                </label>
                            </div>
                        </form>
                        <button id="ret-back-to-items" class="btn btn-link btn-sm text-muted px-0 mb-2">
                            <i class="mdi mdi-arrow-left mr-1"></i>Back to item list
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn" id="btn-submit-ret-req" style="background:#0d9488; color:#fff; display:none;" disabled>
                        <i class="mdi mdi-check mr-1"></i>Submit Return
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
     MODAL 9 — Return PO Items
═══════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="modal-return-po" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="mdi mdi-truck-delivery-outline mr-2" style="color:#6366f1;"></i>Return PO Items</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{-- Step 1: PO Selector --}}
                    <div id="ret-po-step-1">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="font-weight-600 mb-1" style="font-size:0.75rem; color:#6b7280; text-transform:uppercase;">Timeframe</label>
                                    <div class="modal-filter-pills" id="ret-po-filter-date">
                                        <div class="filter-pill" data-value="7">Last 7 Days</div>
                                        <div class="filter-pill active" data-value="30">Last 30 Days</div>
                                        <div class="filter-pill" data-value="all">All Time</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label class="font-weight-600 mb-1">Search Purchase Order</label>
                                <div class="input-group">
                                    <input type="text" id="ret-po-search-input" class="form-control" placeholder="PO-0001 or ID">
                                    <button id="ret-po-btn-search" class="btn btn-secondary" type="button">
                                        <i class="mdi mdi-magnify mr-1"></i>Search
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="ret-po-results">
                             <div class="text-center text-muted py-4" style="font-size:0.85rem;">
                                <i class="mdi mdi-file-search-outline d-block mb-1" style="font-size:2rem; opacity:.35;"></i>
                                Search for a received PO to return items
                            </div>
                        </div>
                    </div>

                    {{-- Step 2: Item Selection --}}
                    <div id="ret-po-step-2" style="display:none;">
                         <div class="adj-batch-info mb-3" id="ret-po-info-panel"></div>
                         <label class="font-weight-600 mb-2">Select Item to Return</label>
                         <div id="ret-po-item-list"></div>
                         <button id="ret-po-back-to-search" class="btn btn-link btn-sm text-muted px-0 mt-2">
                            <i class="mdi mdi-arrow-left mr-1"></i>Back to search
                        </button>
                    </div>

                    {{-- Step 3: Return Form --}}
                    <div id="ret-po-step-3" style="display:none;">
                        <div class="adj-batch-info mb-3" id="ret-po-item-info-panel"></div>
                        <form id="form-return-po">
                            @csrf
                            <input type="hidden" name="purchase_order_id" id="ret-po-id-hidden">
                            <input type="hidden" name="purchase_order_item_id" id="ret-po-item-id-hidden">
                            <input type="hidden" name="product_id" id="ret-po-product-id-hidden">
                            <input type="hidden" name="unit_cost" id="ret-po-unit-cost-hidden">

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Batch <small class="text-muted">(Optional)</small></label>
                                        <select name="batch_id" id="ret-po-batch-select" class="form-control">
                                            <option value="">— Auto / FIFO —</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Qty to Return <span class="text-danger">*</span></label>
                                        <input type="number" name="qty_returned" id="ret-po-qty" class="form-control" min="1" required>
                                        <small id="ret-po-max-qty-hint" class="form-text text-muted"></small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Return Reason <span class="text-danger">*</span></label>
                                        <select name="return_reason" class="form-control" required>
                                            <option value="wrong_item">Wrong Item</option>
                                            <option value="damaged">Damaged</option>
                                            <option value="excess">Excess Quantity</option>
                                            <option value="quality_issue">Quality Issue</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="return_notes" class="form-control" rows="2" placeholder="Reason for return…"></textarea>
                            </div>
                        </form>
                        <button id="ret-po-back-to-items" class="btn btn-link btn-sm text-muted px-0 mb-2">
                            <i class="mdi mdi-arrow-left mr-1"></i>Back to item list
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn" id="btn-submit-ret-po" style="background:#6366f1; color:#fff; display:none;" disabled>
                        <i class="mdi mdi-check mr-1"></i>Submit Return
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
            window.toast = function(msg, type) {
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
                    $('#filter-product').val('').trigger('change');
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
                                            <button type="button" class="btn btn-sm btn-primary btn-fulfill-req" data-req-id="${req.id}" data-req-number="${req.requisition_number}" style="border-radius:6px; font-size:0.75rem; padding:4px 10px;">
                                                <i class="mdi mdi-check"></i> Fulfil
                                            </button>
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
                                            <button type="button" class="btn btn-sm btn-receive-po" data-po-id="${po.id}" data-po-number="${po.po_number}" style="background:#8b5cf6; color:#fff; border:none; border-radius:6px; font-size:0.75rem; padding:4px 10px;">
                                                <i class="mdi mdi-download"></i> Receive
                                            </button>
                                        </div>
                                    </div>`;
                            });
                        }
                        $('#po-list').html(poHtml);

                        // Fire event so new panels (D & E) can update
                        $(document).trigger('pendingActionsLoaded', [res]);
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
                $('#sum-opening-chip').hide();
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

                    // Opening balance chip (product axis only)
                    var openingBal = (sum.opening_balance !== null && sum.opening_balance !== undefined) ? sum.opening_balance : 0;
                    var openingPkg = (rows.length > 0) ? formatPackaging(openingBal, rows[0].base_unit, rows[0].packaging) : '';
                    $('#sum-opening').html(openingBal + openingPkg);
                    $('#sum-opening-chip').show();

                    $('#sum-in').html(sum.total_in + inPkg);
                    $('#sum-out').html(sum.total_out + outPkg);
                    $('#sum-balance').html((sum.closing_balance !== null ? sum.closing_balance : '—') + pkgHint);
                    $('#sum-balance-chip').show();
                    $('#sum-products-chip').hide();
                } else {
                    $('#sum-opening-chip').hide();
                    $('#sum-in').text(sum.total_in);
                    $('#sum-out').text(sum.total_out);
                    $('#sum-products').text(sum.products_touched);
                    $('#sum-balance-chip').hide();
                    $('#sum-products-chip').show();
                }

                if (rows.length === 0) {
                    var colSpan = 10;
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

                // Opening balances map from backend (per product_id)
                var openingBalances = (res.summary && res.summary.opening_balances) ? res.summary.opening_balances : {};

                // Add Opening Balance row if in product axis (single product)
                if (axis === 'product' && rows.length > 0) {
                    var first = rows[0];
                    var openingBal = (res.summary.opening_balance !== null && res.summary.opening_balance !== undefined)
                        ? res.summary.opening_balance
                        : first.bal_before;
                    html += '<tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">' +
                        '<td colspan="3" class="text-right" style="font-weight:700; color:#64748b; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.025em;">Opening Balance (B/F)</td>' +
                        '<td class="text-right" style="background:#f1f5f9;">' +
                            '<div style="font-weight:700; color:#475569;">' + openingBal + '</div>' +
                            '<div style="font-size:0.7rem; color:#64748b;">' + formatPackaging(openingBal, first.base_unit, first.packaging) + '</div>' +
                        '</td>' +
                        '<td colspan="5"></td>' +
                        '</tr>';
                }

                // Track first occurrence of each product (for store axis B/F rows)
                var seenProducts = {};

                $.each(rows, function(idx, r) {
                    var dirClass = 'dir-' + r.direction;
                    var typeClass = 'type-badge ' + (r.badge_type || r.type);

                    var changeQty = r.in_qty > 0 ? r.in_qty : -r.out_qty;
                    var changeClass = changeQty > 0 ? 'qty-in' : 'qty-out';
                    var changeSign = changeQty > 0 ? '+' : '';

                    var changeCell = '<div class="' + changeClass + '" style="font-weight:700;">' + changeSign + changeQty + '</div>' +
                                     '<div style="font-size:0.7rem; opacity:0.8;">' + formatPackaging(changeQty, r.base_unit, r.packaging) + '</div>';

                    var balBeforeCell = '<div style="font-weight:600; color:#64748b;">' + r.bal_before + '</div>' +
                                        '<div style="font-size:0.7rem; color:#94a3b8;">' + formatPackaging(r.bal_before, r.base_unit, r.packaging) + '</div>';

                    var balAfterCell = '<div style="font-weight:700; color:#1e293b;">' + r.bal_after + '</div>' +
                                       '<div style="font-size:0.7rem; color:#475569;">' + formatPackaging(r.bal_after, r.base_unit, r.packaging) + '</div>';

                    var batchCell = '<div><code style="font-size:0.82rem; color:#0f172a;">' + escHtml(r.batch_number) + '</code></div>' +
                                    '<div style="font-size:0.72rem; color:#64748b;">Exp: ' + escHtml(r.expiry_date) + '</div>' +
                                    '<div style="font-size:0.72rem; color:#059669; font-weight:600;">Cost: ₦' + parseFloat(r.cost_price).toLocaleString(undefined, {minimumFractionDigits:2}) + '</div>';

                    var actionCell = '<span class="' + typeClass + '">' + escHtml(r.type_label) + '</span>' +
                                     (r.ref_url ? '<div style="margin-top:2px;"><a href="' + escHtml(r.ref_url) + '" target="_blank" style="font-size:0.72rem; text-decoration:underline;">' + escHtml(r.ref_label) + '</a></div>' :
                                     '<div style="font-size:0.72rem; color:#94a3b8;">' + escHtml(r.ref_label) + '</div>');

                    var notes = r.notes ? '<small class="text-muted" title="' + escHtml(r.notes) + '">' +
                        escHtml(r.notes.substring(0, 40)) + (r.notes.length > 40 ? '…' : '') + '</small>' : '—';

                    var actions = `<div class="text-right btn-group">
                        <button class="btn btn-link btn-xs p-0 text-danger btn-row-damage" title="Record Damage" data-pid="${r.product_id || ''}" data-pname="${escHtml(r.product_name) || ''}" data-bid="${r.batch_id || ''}" data-bnum="${escHtml(r.batch_number) || ''}">
                            <i class="mdi mdi-alert-octagon" style="font-size:1.1rem;"></i>
                        </button></div>`;

                    if (axis === 'product') {
                        html += '<tr class="' + dirClass + '">' +
                            '<td style="font-size:0.78rem;"><div>' + escHtml(r.datetime.split(' ')[0] + ' ' + r.datetime.split(' ')[1]) + '</div>' +
                            '<div style="color:#94a3b8; font-size:0.7rem;">' + escHtml(r.datetime.split(' ')[2]) + '</div></td>' +
                            '<td>' + actionCell + '</td>' +
                            '<td>' + batchCell + '</td>' +
                            '<td class="text-right">' + balBeforeCell + '</td>' +
                            '<td class="text-center">' + changeCell + '</td>' +
                            '<td class="text-right">' + balAfterCell + '</td>' +
                            '<td><small>' + escHtml(r.performer) + '</small></td>' +
                            '<td>' + notes + '</td>' +
                            '<td class="text-right">' + actions + '</td>' +
                            '</tr>';
                    } else {
                        // Store axis: inject per-product Opening Balance (B/F) row on first occurrence
                        if (!seenProducts[r.product_id]) {
                            seenProducts[r.product_id] = true;
                            var openingQty = openingBalances[r.product_id] !== undefined ? openingBalances[r.product_id] : 0;
                            var openingPkgHint = formatPackaging(openingQty, r.base_unit, r.packaging);
                            html += '<tr class="tally-bf-row" data-product-id="' + r.product_id + '" style="background:#f0f9ff; border-top:2px solid #bae6fd; border-bottom:1px solid #e0f2fe;">' +
                                '<td colspan="4" style="font-weight:700; color:#0369a1; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.02em; padding:6px 10px;">' +
                                    '<i class="mdi mdi-package-variant-closed mr-1"></i>' + escHtml(r.product_name) + ' — Opening Balance (B/F)' +
                                '</td>' +
                                '<td class="text-right" style="background:#e0f2fe; padding:6px 10px;">' +
                                    '<div style="font-weight:700; color:#0369a1;">' + openingQty + '</div>' +
                                    (openingPkgHint ? '<div style="font-size:0.7rem; color:#0284c7;">' + openingPkgHint + '</div>' : '') +
                                '</td>' +
                                '<td colspan="4"></td>' +
                                '</tr>';
                        }

                        html += '<tr class="' + dirClass + '" data-product-id="' + r.product_id + '">' +
                            '<td style="font-size:0.78rem;"><div>' + escHtml(r.datetime.split(' ')[0] + ' ' + r.datetime.split(' ')[1]) + '</div>' +
                            '<div style="color:#94a3b8; font-size:0.7rem;">' + escHtml(r.datetime.split(' ')[2]) + '</div></td>' +
                            '<td><div style="font-weight:600; font-size:0.82rem;">' + escHtml(r.product_name) + '</div></td>' +
                            '<td>' + actionCell + '</td>' +
                            '<td>' + batchCell + '</td>' +
                            '<td class="text-right">' + balBeforeCell + '</td>' +
                            '<td class="text-center">' + changeCell + '</td>' +
                            '<td class="text-right">' + balAfterCell + '</td>' +
                            '<td><small>' + escHtml(r.performer) + '</small></td>' +
                            '<td class="text-right">' + actions + '</td>' +
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
                    <select name="items[${reqItemIndex}][product_id]" class="form-control req-product-select" required onchange="loadProductPackaging($(this).val(), $(this).find('option:selected').data('base-unit'), $(this).closest('.req-item-row').find('.req-packaging-select'))">
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
                    $newRow.find('select').not('.req-packaging-select').each(function() {
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
                    <thead><tr><th>Batch</th><th>Available</th><th>Expiry</th><th>Cost (₦)</th><th>Fulfil Qty</th></tr></thead><tbody>`;
                        $.each(item.available_batches, function(_, batch) {
                            var batchPkgHint = '';
                            if (item.packaging && item.packaging.length > 0) {
                                var p = item.packaging[0];
                                if (batch.current_qty >= p.base_unit_qty) {
                                    var bPacks = (batch.current_qty / p.base_unit_qty).toFixed(1);
                                    batchPkgHint = `<div class="small text-muted">≈ ${bPacks} ${p.name}</div>`;
                                }
                            }

                            var costFmt = parseFloat(batch.cost_price || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            var costCell = (parseFloat(batch.cost_price || 0) === 0)
                                ? `<span class="text-muted small">Donation</span>`
                                : `<span style="color:#059669; font-weight:600;">₦${costFmt}</span>`;

                            html += `<tr>
                        <td><code>${escHtml(batch.batch_number)}</code></td>
                        <td>${batch.current_qty}${batchPkgHint}</td>
                        <td>${batch.expiry_date || '—'}</td>
                        <td>${costCell}</td>
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
                    <select name="items[${poItemIndex}][product_id]" class="form-control po-product-select" required onchange="loadProductPackaging($(this).val(), $(this).find('option:selected').data('base-unit'), $(this).closest('.po-item-row').find('.po-packaging-select'))">
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
                    $newRow.find('select').not('.po-packaging-select').each(function() {
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
                            <label class="small font-weight-bold">Cost (per Unit) <span class="text-danger">*</span></label>
                            <input type="number" name="items[${i}][actual_cost]" class="form-control form-control-sm rec-cost-input" step="0.01" min="0" value="${item.unit_cost || ''}" required oninput="updateRecBaseQty(${i}, ${remaining})">
                            <input type="hidden" name="items[${i}][base_actual_cost]" class="rec-base-cost-hidden" value="${item.unit_cost || ''}">
                        </div>
                    </div>
                </div>
            </div>`;

                    // Fetch packaging for this product
                    if (productId) {
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
                    }
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
            window.escHtml = function(str) {
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
                
                var pkgText = $pkg.text().trim() || 'packaging unit';
                var rawPkgName = pkgText.split('(')[0].trim();
                var labelStr = rawPkgName;
                if (factor > 1) {
                    labelStr = rawPkgName + ' ~ ' + factor + ' ' + baseUnit;
                } else if (rawPkgName === 'Base Unit' || rawPkgName === 'packaging unit') {
                    labelStr = baseUnit;
                }

                $('#batch-cost-price-label').html('Cost Price (per ' + labelStr + ') <span class="text-danger">*</span>');
                if ($('#tally_skip_cost_price').is(':checked')) {
                    $('#batch-cost-price-label span.text-danger').hide();
                }
                if (factor > 1 && qty > 0) {
                    $('#batch-qty-hint').text(`= ${baseQty} ${baseUnit}`);
                } else {
                    $('#batch-qty-hint').text('');
                }
                updateBatchCostPreview();
            };

            /**
             * Shows a real-time preview of how the entered cost per packaging unit
             * will be converted to a base-unit cost for database storage.
             * Formula: base_unit_cost = cost_entered / packaging.base_unit_qty
             */
            window.updateBatchCostPreview = function() {
                var costEntered = parseFloat($('#batch-cost-price').val()) || 0;
                var $pkg = $('#batch-packaging option:selected');
                var factor = parseFloat($pkg.data('qty')) || 1;
                var pkgName = $pkg.text().trim() || 'Base Unit';
                var baseUnit = $('#batch-product option:selected').data('base-unit') || 'unit';
                var $preview = $('#batch-cost-preview');
                var $previewText = $('#batch-cost-preview-text');

                if (costEntered > 0 && factor > 1) {
                    var baseUnitCost = (costEntered / factor).toFixed(4);
                    $previewText.html(
                        '₦' + parseFloat(costEntered).toLocaleString() + ' per <strong>' + pkgName + '</strong> (' + factor + ' ' + baseUnit + 's) → ' +
                        '<strong>₦' + parseFloat(baseUnitCost).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 4}) + '</strong> per ' + baseUnit + ' (saved in DB)'
                    );
                    $preview.show();
                } else if (costEntered > 0) {
                    $previewText.html('₦' + parseFloat(costEntered).toLocaleString(undefined, {minimumFractionDigits: 2}) + ' per ' + baseUnit + ' — stored as-is (base unit selected)');
                    $preview.show();
                } else {
                    $preview.hide();
                }
            };

            window.loadProductPackaging = function(productId, baseUnit, $pkgSelect) {
                if (!$pkgSelect || $pkgSelect.length === 0) return;

                $pkgSelect.empty();

                // Add the Base Unit option first
                var baseLabel = `Base Unit (${baseUnit || 'Piece'})`;
                var $baseOpt = $('<option>', {
                    value: '',
                    text: baseLabel
                }).attr('data-qty', 1).prop('selected', true);

                $pkgSelect.append($baseOpt);
                $pkgSelect.trigger('change');

                if (!productId || productId === 'undefined' || productId === 'null' || isNaN(productId)) return;

                $.get('{{ route("products.packagings", ":id") }}'.replace(':id', productId))
                    .done(function(res) {
                        if (res.packagings && res.packagings.length) {
                            $.each(res.packagings, function(_, p) {
                                var $opt = $('<option>', {
                                    value: p.id,
                                    text: `${p.name} (${p.base_unit_qty})`
                                }).attr('data-qty', p.base_unit_qty);
                                $pkgSelect.append($opt);
                            });
                            $pkgSelect.trigger('change');
                        }

                        // Populate Cost Price default if field exists
                        if (res.price) {
                            // Prefer pr_buy_price (purchase cost) → fall back to current_sale_price
                            var defaultPrice = parseFloat(res.price.pr_buy_price || res.price.current_sale_price || 0);

                            // Check if we are in Manual Batch modal
                            var $manualCost = $('#batch-cost-price');
                            if ($manualCost.length && $pkgSelect.closest('#form-add-batch').length) {
                                $manualCost.val(defaultPrice.toFixed(2));
                            }

                            // Check if we are in PO row
                            var $poRow = $pkgSelect.closest('.po-item-row');
                            if ($poRow.length) {
                                var $costInput = $poRow.find('.po-cost-input');
                                if ($costInput.length && !$costInput.val()) {
                                    $costInput.val(defaultPrice.toFixed(2));
                                }
                            }
                        }
                    });
            };

            window.calculateDmgBaseQty = function() {
                var qty = parseInt($('#damage-qty').val()) || 0;
                var $pkg = $('#dmg-packaging option:selected');
                var factor = parseFloat($pkg.data('qty')) || 1;
                var baseQty = qty * factor;
                $('#dmg-base-qty-hidden').val(baseQty);

                var baseUnit = dmgSelectedBatch ? dmgSelectedBatch.base_unit_name : 'Piece';
                if (factor > 1 && qty > 0) {
                    $('#dmg-qty-hint').text(`= ${baseQty} ${baseUnit}`);
                } else {
                    $('#dmg-qty-hint').text('');
                }
            };

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
                var baseUnit = adjSelectedBatch ? adjSelectedBatch.base_unit_name : 'units';

                var baseQty = qty * factor;
                $('#adj-base-qty-hidden').val(baseQty);

                if (adjSelectedType === 'subtract' && baseQty > currentStock) {
                    $('#adj-qty-hint').html(`<span class="text-danger">Exceeds available stock (${currentStock} ${baseUnit})</span>`);
                    $('#btn-apply-adjustment').prop('disabled', true);
                } else {
                    if (factor > 1 && qty > 0) {
                        $('#adj-qty-hint').text(`= ${baseQty} ${baseUnit}`);
                    } else {
                        $('#adj-qty-hint').text('');
                    }
                    if (adjSelectedType && qty > 0) $('#btn-apply-adjustment').prop('disabled', false);
                }
                if (typeof checkAdjFormReady === 'function') checkAdjFormReady();
            };

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
                $modal.find('select').not('.req-packaging-select, .po-packaging-select, .rec-packaging-select, #batch-packaging, #adj-packaging, #dmg-packaging').each(function() {
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
                if (!batchData) return; // Ignore if not a real batch card (e.g. return item cards)

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
                if (batchData && batchData.product_id) {
                    loadProductPackaging(batchData.product_id, batchData.base_unit_name, $('#adj-packaging'));
                }

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





        // Keep in sync when the store filter changes
        $('#filter-store').on('change', function() {
            currentStoreId = parseInt($(this).val(), 10) || null;
        });

        // ─── Toolbar triggers ────────────────────────────────────────────────────
        $('#tb-record-damage').on('click', function() {
            $('#damage-store-id').val(currentStoreId);
            $('#form-record-damage')[0].reset();
            $('#dmg-step-1').show();
            $('#dmg-step-2').hide();
            $('#btn-submit-damage').hide();
            $('#modal-record-damage').modal('show');
        });

        $('#tb-return-req').on('click', function() {
            $('#form-return-req')[0].reset();
            $('#ret-step-1').show();
            $('#ret-step-2, #ret-step-3').hide();
            $('#btn-submit-ret-req').hide();
            $('#modal-return-req').modal('show');
        });

        $('#tb-return-po').on('click', function() {
            $('#form-return-po')[0].reset();
            $('#ret-po-step-1').show();
            $('#ret-po-step-2, #ret-po-step-3').hide();
            $('#btn-submit-ret-po').hide();
            $('#modal-return-po').modal('show');
        });

        // ─── Tally row action triggers ──────────────────────────────────────────
        $(document).on('click', '.btn-row-damage', function(e) {
            e.preventDefault();
            var pid = $(this).data('pid');
            var pname = $(this).data('pname');
            var bid = $(this).data('bid');
            var bnum = $(this).data('bnum');

            $('#damage-store-id').val(currentStoreId);
            $('#form-record-damage')[0].reset();

            // Set step 1 search value
            if ($('#dmg-product-filter').hasClass('select2-hidden-accessible')) {
                $('#dmg-product-filter').val(pid).trigger('change');
            } else {
                $('#dmg-product-filter').val(pid);
            }

            $('#dmg-step-1').show();
            $('#dmg-step-2').hide();
            $('#btn-submit-damage').hide();
            $('#modal-record-damage').modal('show');

            // Auto-load batches and try to find the specific one
            if (pid) {
                $('#dmg-load-batches').trigger('click');
                // We'll need a delay or a callback to select the specific batch card
                var checkInterval = setInterval(function() {
                    var $card = $('.dmg-batch-card').filter(function() {
                        var b = $(this).data('batch');
                        return b && b.id == bid;
                    });
                    if ($card.length > 0) {
                        $card.trigger('click');
                        clearInterval(checkInterval);
                    }
                }, 100);
                setTimeout(function(){ clearInterval(checkInterval); }, 2000);
            }
        });

        $(document).on('click', '.btn-row-return', function(e) {
            e.preventDefault();
            var reqId = $(this).data('req-id');
            var reqNum = $(this).data('req-num');
            var pid = $(this).data('pid');
            var bid = $(this).data('bid');

            $('#form-return-req')[0].reset();
            $('#ret-step-1').show();
            $('#ret-step-2, #ret-step-3').hide();
            $('#btn-submit-ret-req').hide();
            $('#modal-return-req').modal('show');

            // Search for the specific requisition
            var q = reqNum ? reqNum.replace('Requisition #', '').trim() : '';
            $('#ret-search-input').val(q);
            $('#ret-btn-search').trigger('click');

            // Auto-select logic...
            var checkInterval = setInterval(function() {
                var $row = $('.ret-req-choice').filter(function() {
                    var r = $(this).data('req');
                    return r && r.id == reqId;
                });
                if ($row.length > 0) {
                    $row.trigger('click');
                    clearInterval(checkInterval);

                    var itemInterval = setInterval(function() {
                        var $item = $('.ret-item-card').filter(function() {
                            var it = $(this).data('item');
                            return it && it.product_id == pid;
                        });
                        if ($item.length > 0) {
                            $item.trigger('click');
                            clearInterval(itemInterval);

                            var batchInterval = setInterval(function() {
                                if ($('#ret-batch-select option[value="' + bid + '"]').length > 0) {
                                    $('#ret-batch-select').val(bid).trigger('change');
                                    clearInterval(batchInterval);
                                }
                            }, 100);
                            setTimeout(function(){ clearInterval(batchInterval); }, 2000);
                        }
                    }, 100);
                    setTimeout(function(){ clearInterval(itemInterval); }, 2000);
                }
            }, 100);
            setTimeout(function(){ clearInterval(checkInterval); }, 2000);
        });

        $(document).on('click', '.btn-row-po-return', function(e) {
            e.preventDefault();
            var poId = $(this).data('po-id');
            var poNum = $(this).data('po-num');
            var pid = $(this).data('pid');
            var bid = $(this).data('bid');

            $('#form-return-po')[0].reset();
            $('#ret-po-step-1').show();
            $('#ret-po-step-2, #ret-po-step-3').hide();
            $('#btn-submit-ret-po').hide();
            $('#modal-return-po').modal('show');

            $('#ret-po-search-input').val(poNum || '');
            $('#ret-po-btn-search').trigger('click');

            var checkInterval = setInterval(function() {
                var $row = $('.ret-po-choice').filter(function() {
                    var r = $(this).data('po');
                    return r && r.id == poId;
                });
                if ($row.length > 0) {
                    $row.trigger('click');
                    clearInterval(checkInterval);

                    var itemInterval = setInterval(function() {
                        var $item = $('.ret-po-item-card').filter(function() {
                            var it = $(this).data('item');
                            return it && it.product_id == pid;
                        });
                        if ($item.length > 0) {
                            $item.trigger('click');
                            clearInterval(itemInterval);

                            var batchInterval = setInterval(function() {
                                if ($('#ret-po-batch-select option[value="' + bid + '"]').length > 0) {
                                    $('#ret-po-batch-select').val(bid).trigger('change');
                                    clearInterval(batchInterval);
                                }
                            }, 100);
                            setTimeout(function(){ clearInterval(batchInterval); }, 2000);
                        }
                    }, 100);
                    setTimeout(function(){ clearInterval(itemInterval); }, 2000);
                }
            }, 100);
            setTimeout(function(){ clearInterval(checkInterval); }, 2000);
        });

        // ─── Modal Filters ──────────────────────────────────────────────────────
        $(document).on('click', '.filter-pill', function() {
            var $p = $(this);
            $p.siblings().removeClass('active');
            $p.addClass('active');

            // Find parent and trigger related search
            var parentId = $p.parent().attr('id');
            if (parentId && parentId.indexOf('ret-filter') === 0) {
                $('#ret-btn-search').trigger('click');
            } else if (parentId && parentId.indexOf('ret-po-filter') === 0) {
                $('#ret-po-btn-search').trigger('click');
            } else if (parentId && parentId.indexOf('dmg-filter') === 0) {
                $('#dmg-load-batches').trigger('click');
            }
        });

        // ─── Modal 7: Record Store Damage ────────────────────────────────────────

        var dmgSelectedBatch = null;

        // Step 1: Search & Load Batches
        if ($.fn.select2) {
            $('#dmg-product-filter').select2({
                dropdownParent: $('#modal-record-damage'),
                width: '100%',
                placeholder: '— Search product —'
            });
        }

        function renderDmgBatches(batches, showProductName = false) {
            var $container = $('#dmg-batch-list');
            if (!batches || !batches.length) {
                $container.html('<div class="alert alert-info py-2" style="font-size:0.8rem;"><i class="mdi mdi-information-outline mr-1"></i>No active batches found.</div>');
                return;
            }

            var html = '<div class="row g-2">';
            batches.forEach(function(b) {
                var expiry = b.expiry_date ? b.expiry_date : 'No Expiry';
                html += `
                    <div class="col-md-6">
                        <div class="adj-batch-card dmg-batch-card" data-batch='${JSON.stringify(b)}'>
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="batch-num">#${escHtml(b.batch_number || 'N/A')}</span>
                                <span class="badge ${b.current_qty > 0 ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'}">
                                    ${b.current_qty} in stock
                                </span>
                            </div>
                            ${showProductName ? `<div class="font-weight-600 small mb-1 text-truncate" title="${escHtml(b.product_name)}">${escHtml(b.product_name)}</div>` : ''}
                            <div class="batch-meta">Exp: ${escHtml(expiry)}</div>
                            <div class="batch-meta">Cost: ₦${parseFloat(b.unit_cost || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                        </div>
                    </div>`;
            });
            html += '</div>';
            $container.html(html);
        }

        function loadRecentDmgBatches() {
            var status = $('#dmg-filter-status .filter-pill.active').data('value') || 'recent';
            $('#dmg-batch-list').html('<div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span> Loading recent activity…</div>');
            $.getJSON('{{ route("inventory.store-damages.get-recent-batches") }}', {
                store_id: currentStoreId,
                status: status
            }, function(res) {
                renderDmgBatches(res.batches, true);
            });
        }

        $('#dmg-load-batches').on('click', function() {
            var pid = $('#dmg-product-filter').val();
            if (!pid) return loadRecentDmgBatches();

            $('#dmg-batch-list').html('<div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span> Loading…</div>');
            $.getJSON('{{ route("inventory.store-damages.get-batches") }}', {
                product_id: pid,
                store_id: currentStoreId,
            }, function(r) {
                renderDmgBatches(r.batches, false);
            });
        });

        $('#modal-record-damage').on('shown.bs.modal', function() {
            $('#dmg-product-filter').select2('open');
            var currentProductId = (currentAxis === 'product') ? $('#filter-product').val() : null;
            var $dmgProductFilter = $('#dmg-product-filter');

            if (currentProductId && !$dmgProductFilter.val()) {
                $dmgProductFilter.val(currentProductId).trigger('change');
                setTimeout(function() {
                    $('#dmg-load-batches').trigger('click');
                }, 100);
            } else if (!$dmgProductFilter.val()) {
                loadRecentDmgBatches();
            }
        });

        $(document).on('click', '.dmg-batch-card', function() {
            var b = $(this).data('batch');
            if (typeof b === 'string') b = JSON.parse(b);
            dmgSelectedBatch = b;

            $('#damage-batch-id').val(b.id);
            $('#damage-product-id').val(b.product_id);
            $('#damage-unit-cost').val(b.unit_cost || 0);
            $('#damage-qty').attr('max', b.current_qty).val(1);

            loadProductPackaging(b.product_id, b.base_unit_name, $('#dmg-packaging'));
            setTimeout(calculateDmgBaseQty, 200);

            $('#dmg-batch-info-panel').html(`
                <div class="info-row"><span class="info-label">Product</span><span class="info-value">${escHtml(b.product_name)}</span></div>
                <div class="info-row"><span class="info-label">Batch</span><span class="info-value">#${escHtml(b.batch_number)}</span></div>
                <div class="info-row"><span class="info-label">Available</span><span class="info-value text-success">${b.current_qty}</span></div>
            `);

            $('#dmg-step-1').hide();
            $('#dmg-step-2').fadeIn();
            $('#btn-submit-damage').show().prop('disabled', false);
        });

        $('#dmg-back-btn').on('click', function() {
            $('#dmg-step-2').hide();
            $('#dmg-step-1').fadeIn();
            $('#btn-submit-damage').hide();
        });

        $('#btn-submit-damage').on('click', function() {
            $('#form-record-damage').submit();
        });

        $('#form-record-damage').on('submit', function(e) {
            e.preventDefault();
            var $btn = $('#btn-submit-damage').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving…');

            var data = $(this).serializeArray();
            // Replace qty_damaged with the base quantity (the system expects base units)
            for (var i = 0; i < data.length; i++) {
                if (data[i].name === 'qty_damaged') {
                    data[i].value = $('#dmg-base-qty-hidden').val();
                }
            }

            $.ajax({
                url: '{{ route("inventory.store-damages.store") }}',
                method: 'POST',
                data: data,
                dataType: 'json',
            })
            .done(function(r) {
                if (r.success) {
                    toastSuccess(r.message || 'Damage recorded');
                    $('#modal-record-damage').modal('hide');
                    loadTally();
                } else {
                    toastError(r.message || 'Failed to record damage');
                    $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i>Record Damage');
                }
            })
            .fail(function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Request failed';
                toastError(msg);
                $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i>Record Damage');
            });
        });

        // ─── Modal 8: Return Requisition Items ───────────────────────────────────

        var retSelectedReq = null;
        var retSelectedItem = null;
        var retSearchTimer;

        $('#ret-search-input').on('input', function() {
            clearTimeout(retSearchTimer);
            var q = $(this).val();
            if (q.length >= 2) {
                retSearchTimer = setTimeout(function() {
                    $('#ret-btn-search').trigger('click');
                }, 400);
            }
        });

        $('#ret-btn-search').on('click', function() {
            var q = $('#ret-search-input').val();
            var involvement = $('#ret-filter-involvement .filter-pill.active').data('value') || 'received';
            var days = $('#ret-filter-date .filter-pill.active').data('value') || 30;
            var currentPid = (currentAxis === 'product') ? $('#filter-product').val() : null;

            var $res = $('#ret-requisition-results').html('<div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span> Loading…</div>');

            $.getJSON('{{ route("inventory.requisition-returns.search-requisitions") }}', {
                q: q,
                store_id: currentStoreId,
                product_id: currentPid,
                involvement: involvement,
                days: days
            }, function(r) {
                var html = '';
                if (r.is_fallback) {
                    html += '<div class="alert alert-info py-2" style="font-size:0.75rem;"><i class="mdi mdi-information-outline mr-1"></i>No requisitions found for this product. Showing all recent activity:</div>';
                }

                if (!r.requisitions || r.requisitions.length === 0) {
                    $res.html('<div class="alert alert-warning py-2" style="font-size:0.8rem;">No requisitions found matching those filters.</div>');
                    return;
                }

                html += '<div class="list-group list-group-flush border rounded">';
                r.requisitions.forEach(function(req) {
                    html += `
                        <a href="javascript:void(0)" class="list-group-item list-group-item-action ret-req-choice" data-req='${JSON.stringify(req)}'>
                            <div class="d-flex justify-content-between">
                                <span class="font-weight-600">${escHtml(req.requisition_number)}</span>
                                <small class="text-muted">${req.fulfilled_at_label}</small>
                            </div>
                            <div class="small text-muted">From: ${escHtml(req.from_store_name)} &rarr; To: ${escHtml(req.to_store_name)} | ${req.items_count} items</div>
                        </a>`;
                });
                html += '</div>';
                $res.html(html);
            });
        });

        $('#modal-return-req').on('shown.bs.modal', function() {
            $('#ret-search-input').focus();
            if (!$('#ret-search-input').val()) {
                $('#ret-btn-search').trigger('click');
            }
        });

        $(document).on('click', '.ret-req-choice', function() {
            var req = $(this).data('req');
            if (typeof req === 'string') req = JSON.parse(req);
            retSelectedReq = req;

            $('#ret-requisition-id-hidden').val(req.id);
            $('#ret-source-store-id').val(req.to_store_id);
            $('#ret-dest-store-id').val(req.from_store_id);

            var hint = `Source: <strong>${escHtml(req.to_store_name)}</strong> (Returning) &rarr; Dest: <strong>${escHtml(req.from_store_name)}</strong> (Receiving)`;
            $('#ret-direction-hint').html(hint);

            // Load items
            var $list = $('#ret-item-list').html('<div class="spinner-border spinner-border-sm"></div>');
            $.getJSON('{{ route("inventory.requisition-returns.req-items") }}', { requisition_id: req.id }, function(res) {
                var html = '<div class="row g-2">';
                (res.items || []).forEach(function(it) {
                    html += `
                        <div class="col-md-6">
                            <div class="adj-batch-card ret-item-card" data-item='${JSON.stringify(it)}'>
                                <div class="font-weight-600 mb-1">${escHtml(it.product_name)}</div>
                                <div class="d-flex justify-content-between small">
                                    <span>Fulfilled: ${it.fulfilled_qty}</span>
                                    <span class="text-info">Returnable: ${it.returnable_qty}</span>
                                </div>
                            </div>
                        </div>`;
                });
                html += '</div>';
                $list.html(html);
            });

            $('#ret-step-1').hide();
            $('#ret-step-2').fadeIn();
        });

        $(document).on('click', '.ret-item-card', function() {
            var it = $(this).data('item');
            if (typeof it === 'string') it = JSON.parse(it);
            retSelectedItem = it;

            $('#ret-item-id-hidden').val(it.id);
            $('#ret-product-id-hidden').val(it.product_id);
            $('#ret-qty').attr('max', it.returnable_qty).val(1);
            $('#ret-max-qty-hint').text(`Max returnable: ${it.returnable_qty}`);

            $('#ret-item-info-panel').html(`
                <div class="info-row"><span class="info-label">Product</span><span class="info-value">${escHtml(it.product_name)}</span></div>
                <div class="info-row"><span class="info-label">Returnable</span><span class="info-value text-info">${it.returnable_qty}</span></div>
            `);

            // Load batches for this product in the returning store
            var $b = $('#ret-batch-select').html('<option value="">— Auto / FIFO —</option>');
            $.getJSON('{{ route("inventory.requisition-returns.batches-for-product") }}', {
                product_id: it.product_id,
                store_id: retSelectedReq.to_store_id, // The store returning the items
            }, function(r) {
                (r.batches || []).forEach(function(batch) {
                    var costFmt = parseFloat(batch.unit_cost || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    var costTxt = parseFloat(batch.unit_cost || 0) === 0 ? 'Donation' : '₦' + costFmt;
                    var expTxt  = batch.expiry_date || 'No Expiry';
                    $b.append(`<option value="${batch.id}">${escHtml(batch.batch_number)} | Qty: ${batch.current_qty} | Cost: ${costTxt} | Exp: ${expTxt}</option>`);
                });
            });

            $('#ret-step-2').hide();
            $('#ret-step-3').fadeIn();
            $('#btn-submit-ret-req').show().prop('disabled', false);
        });

        $('#ret-back-to-search').on('click', function() {
            $('#ret-step-2').hide();
            $('#ret-step-1').fadeIn();
        });

        $('#ret-back-to-items').on('click', function() {
            $('#ret-step-3').hide();
            $('#ret-step-2').fadeIn();
            $('#btn-submit-ret-req').hide();
        });

        $('#btn-submit-ret-req').on('click', function() {
            var $form = $('#form-return-req');
            if (!$form[0].checkValidity()) return $form[0].reportValidity();

            var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing…');

            $.ajax({
                url: '{{ route("inventory.requisition-returns.store") }}',
                method: 'POST',
                data: $form.serialize(),
                dataType: 'json',
            })
            .done(function(r) {
                if (r.success) {
                    toastSuccess(r.message || 'Return submitted');
                    $('#modal-return-req').modal('hide');
                    loadTally();
                } else {
                    toastError(r.message || 'Failed to submit return');
                    $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i>Submit Return');
                }
            });
        });

        // ─── Modal 9: Return PO Items ────────────────────────────────────────────

        var retSelectedPo = null;
        var retSelectedPoItem = null;
        var retPoSearchTimer;

        $('#ret-po-search-input').on('input', function() {
            clearTimeout(retPoSearchTimer);
            var q = $(this).val();
            if (q.length >= 2) {
                retPoSearchTimer = setTimeout(function() {
                    $('#ret-po-btn-search').trigger('click');
                }, 400);
            }
        });

        $('#ret-po-btn-search').on('click', function() {
            var q = $('#ret-po-search-input').val();
            var days = $('#ret-po-filter-date .filter-pill.active').data('value') || 30;
            var currentPid = (currentAxis === 'product') ? $('#filter-product').val() : null;

            var $res = $('#ret-po-results').html('<div class="text-center py-4"><span class="spinner-border spinner-border-sm"></span> Loading…</div>');

            $.getJSON('{{ route("inventory.po-returns.search-pos") }}', {
                q: q,
                store_id: currentStoreId,
                product_id: currentPid,
                days: days
            }, function(r) {
                var html = '';
                if (r.is_fallback) {
                    html += '<div class="alert alert-info py-2" style="font-size:0.75rem;"><i class="mdi mdi-information-outline mr-1"></i>No POs found for this product. Showing all recent receipts:</div>';
                }

                if (!r.pos || r.pos.length === 0) {
                    $res.html('<div class="alert alert-warning py-2" style="font-size:0.8rem;">No received purchase orders found.</div>');
                    return;
                }

                html += '<div class="list-group list-group-flush border rounded">';
                r.pos.forEach(function(po) {
                    html += `
                        <a href="javascript:void(0)" class="list-group-item list-group-item-action ret-po-choice" data-po='${JSON.stringify(po)}'>
                            <div class="d-flex justify-content-between">
                                <span class="font-weight-600">${escHtml(po.po_number)}</span>
                                <small class="text-muted">${po.received_at_label}</small>
                            </div>
                            <div class="small text-muted">Supplier: ${escHtml(po.supplier_name)} | ${po.items_count} items</div>
                        </a>`;
                });
                html += '</div>';
                $res.html(html);
            });
        });

        $('#modal-return-po').on('shown.bs.modal', function() {
            $('#ret-po-search-input').focus();
            if (!$('#ret-po-search-input').val()) {
                $('#ret-po-btn-search').trigger('click');
            }
        });

        $(document).on('click', '.ret-po-choice', function() {
            var po = $(this).data('po');
            if (typeof po === 'string') po = JSON.parse(po);
            retSelectedPo = po;

            $('#ret-po-id-hidden').val(po.id);
            $('#ret-po-info-panel').html(`
                <div class="info-row"><span class="info-label">PO Number</span><span class="info-value">${escHtml(po.po_number)}</span></div>
                <div class="info-row"><span class="info-label">Supplier</span><span class="info-value">${escHtml(po.supplier_name)}</span></div>
            `);

            // Load items
            var $list = $('#ret-po-item-list').html('<div class="spinner-border spinner-border-sm"></div>');
            $.getJSON('{{ route("inventory.po-returns.po-items") }}', { purchase_order_id: po.id }, function(res) {
                var html = '<div class="row g-2">';
                (res.items || []).forEach(function(it) {
                    html += `
                        <div class="col-md-6">
                            <div class="adj-batch-card ret-po-item-card" data-item='${JSON.stringify(it)}'>
                                <div class="font-weight-600 mb-1">${escHtml(it.product_name)}</div>
                                <div class="d-flex justify-content-between small">
                                    <span>Received: ${it.received_qty}</span>
                                    <span class="text-danger">Returnable: ${it.returnable_qty}</span>
                                </div>
                            </div>
                        </div>`;
                });
                html += '</div>';
                $list.html(html);
            });

            $('#ret-po-step-1').hide();
            $('#ret-po-step-2').fadeIn();
        });

        $(document).on('click', '.ret-po-item-card', function() {
            var it = $(this).data('item');
            if (typeof it === 'string') it = JSON.parse(it);
            retSelectedPoItem = it;

            $('#ret-po-item-id-hidden').val(it.id);
            $('#ret-po-product-id-hidden').val(it.product_id);
            $('#ret-po-unit-cost-hidden').val(it.unit_cost);
            $('#ret-po-qty').attr('max', it.returnable_qty).val(1);
            $('#ret-po-max-qty-hint').text(`Max returnable: ${it.returnable_qty}`);

            $('#ret-po-item-info-panel').html(`
                <div class="info-row"><span class="info-label">Product</span><span class="info-value">${escHtml(it.product_name)}</span></div>
                <div class="info-row"><span class="info-label">Received Qty</span><span class="info-value">${it.received_qty}</span></div>
            `);

            // Load batches for this product
            var $b = $('#ret-po-batch-select').html('<option value="">— Auto / FIFO —</option>');
            $.getJSON('{{ route("inventory.requisition-returns.batches-for-product") }}', {
                product_id: it.product_id,
                store_id: currentStoreId,
            }, function(r) {
                (r.batches || []).forEach(function(batch) {
                    var costFmt = parseFloat(batch.unit_cost || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    var costTxt = parseFloat(batch.unit_cost || 0) === 0 ? 'Donation' : '₦' + costFmt;
                    var expTxt  = batch.expiry_date || 'No Expiry';
                    $b.append(`<option value="${batch.id}">${escHtml(batch.batch_number)} | Qty: ${batch.current_qty} | Cost: ${costTxt} | Exp: ${expTxt}</option>`);
                });
            });

            $('#ret-po-step-2').hide();
            $('#ret-po-step-3').fadeIn();
            $('#btn-submit-ret-po').show().prop('disabled', false);
        });

        $('#ret-po-back-to-search').on('click', function() {
            $('#ret-po-step-2').hide();
            $('#ret-po-step-1').fadeIn();
        });

        $('#ret-po-back-to-items').on('click', function() {
            $('#ret-po-step-3').hide();
            $('#ret-po-step-2').fadeIn();
            $('#btn-submit-ret-po').hide();
        });

        $('#btn-submit-ret-po').on('click', function() {
            var $form = $('#form-return-po');
            if (!$form[0].checkValidity()) return $form[0].reportValidity();

            var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing…');

            $.ajax({
                url: '{{ route("inventory.po-returns.store") }}',
                method: 'POST',
                data: $form.serialize(),
                dataType: 'json',
            })
            .done(function(r) {
                if (r.success) {
                    toastSuccess(r.message || 'PO Return submitted');
                    $('#modal-return-po').modal('hide');
                    loadTally();
                } else {
                    toastError(r.message || 'Failed to submit PO return');
                    $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i>Submit Return');
                }
            })
            .fail(function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Request failed';
                toastError(msg);
                $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i>Submit Return');
            });
        });

        // ─── Pending Panels D & E (damages / req-returns) ───────────────────────

        function renderDamagesPanel(items) {
            var $list = $('#damages-list').empty();
            if (!items || !items.length) {
                $list.html('<div class="text-center text-muted py-4" style="font-size:0.84rem;"><i class="mdi mdi-check-circle-outline d-block mb-1" style="font-size:1.8rem; opacity:.4;"></i>No pending damages</div>');
                return;
            }
            items.forEach(function(d) {
                $list.append(
                    '<div class="po-row">' +
                      '<div>' +
                        '<div class="req-ref">' + (d.product ? d.product.product_name : 'Product #'+d.product_id) + '</div>' +
                        '<div class="req-meta">Type: ' + d.damage_type + ' | Qty: ' + d.qty_damaged + '</div>' +
                      '</div>' +
                      '<div class="d-flex align-items-center gap-2">' +
                        '<span class="status-badge pending">Pending</span>' +
                        '<a href="{{ url('inventory/store-damages') }}" target="_blank" class="btn btn-sm btn-outline-primary" style="border-radius:6px; font-size:0.75rem; padding:4px 10px;">' +
                          '<i class="mdi mdi-check"></i>' +
                        '</a>' +
                      '</div>' +
                    '</div>'
                );
            });
        }

        function renderReqReturnsPanel(items) {
            var $list = $('#req-returns-list').empty();
            if (!items || !items.length) {
                $list.html('<div class="text-center text-muted py-4" style="font-size:0.84rem;"><i class="mdi mdi-check-circle-outline d-block mb-1" style="font-size:1.8rem; opacity:.4;"></i>No pending req returns</div>');
                return;
            }
            items.forEach(function(r) {
                $list.append(
                    '<div class="po-row">' +
                      '<div>' +
                        '<div class="req-ref">Return #' + r.id + '</div>' +
                        '<div class="req-meta">Qty: ' + r.qty_returned + ' | ' + (r.return_condition||'') + '</div>' +
                      '</div>' +
                      '<div class="d-flex align-items-center gap-2">' +
                        '<span class="status-badge pending">Pending</span>' +
                        '<a href="{{ url('inventory/requisition-returns') }}" target="_blank" class="btn btn-sm btn-outline-primary" style="border-radius:6px; font-size:0.75rem; padding:4px 10px;">' +
                          '<i class="mdi mdi-check"></i>' +
                        '</a>' +
                      '</div>' +
                    '</div>'
                );
            });
        }

        // Extend the existing loadPendingActions to also populate panels D & E
        var _origPendingCb = window.__pendingActionsCb;
        $(document).on('pendingActionsLoaded', function(e, data) {
            if (data && data.counts) {
                $('#badge-damages').text(data.counts.damages || 0);
                $('#badge-req-returns').text(data.counts.req_returns || 0);
                $('#badge-po-returns').text(data.counts.po_returns || 0);
            }
            renderDamagesPanel(data.damages || []);
            renderReqReturnsPanel(data.req_returns || []);
            renderPoReturnsPanel(data.po_returns || []);
        });

        function renderPoReturnsPanel(items) {
            var html = '';
            if (!items || items.length === 0) {
                html = '<div class="text-center text-muted py-4" style="font-size:0.84rem;"><i class="mdi mdi-check-circle-outline d-block mb-1" style="font-size:1.8rem; opacity:.4;"></i>No pending PO returns</div>';
            } else {
                items.forEach(function(it) {
                    html += `
                        <div class="req-row">
                            <div>
                                <div class="req-ref">${escHtml(it.purchase_order ? it.purchase_order.po_number : 'PO#'+it.purchase_order_id)}</div>
                                <div class="req-meta">${escHtml(it.product ? it.product.product_name : 'Item')} ×${it.quantity}</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="status-badge ${it.status}">${it.status}</span>
                                <a href="{{ url('inventory/purchase-order-returns') }}" target="_blank" class="btn btn-sm btn-outline-primary" style="border-radius:6px; font-size:0.75rem; padding:4px 10px;">
                                    <i class="mdi mdi-check"></i>
                                </a>
                            </div>
                        </div>`;
                });
            }
            $('#po-returns-list').html(html);
        }

        // Helper toast aliases (in case named differently in outer scope)
        function toastSuccess(msg) {
            if (typeof toast === 'function') toast(msg, 'success');
            else alert(msg);
        }
        function toastError(msg) {
            if (typeof toast === 'function') toast(msg, 'error');
            else alert('Error: ' + msg);
        }

        window.toggleTallyCostRequirement = function(checkbox) {
            var costInput = $('#batch-cost-price');
            var labelSpan = $('#batch-cost-price-label span.text-danger');
            if (checkbox.checked) {
                costInput.prop('required', false);
                costInput.prop('disabled', true);
                costInput.val('');
                labelSpan.hide();
                $('#batch-cost-preview').hide();
            } else {
                costInput.prop('required', true);
                costInput.prop('disabled', false);
                labelSpan.show();
            }
        };

    })();
    </script>
@endsection
