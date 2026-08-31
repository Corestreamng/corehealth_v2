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

@include('admin.inventory.store-workbench.partials._tally_scripts')
