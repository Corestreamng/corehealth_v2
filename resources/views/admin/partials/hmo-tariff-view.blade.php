{{--
    HMO Tariff Viewer / Editor
    ─────────────────────────────
    Required variables:
      $itemName       — string: product or service name
      $itemType       — 'product' or 'service'
      $itemId         — int: the product_id or service_id
      $salePrice      — float: current sale/issue price
      $schemeSummary  — array of scheme data with HMOs and tariff stats
      $standaloneData — array of standalone HMOs (no scheme)
      $totalHmoCount  — int: total active HMOs
      $backUrl        — string: URL to go back to
--}}
@extends('admin.layouts.app')
@section('title', 'HMO Tariffs — ' . $itemName)
@section('page_name', ucfirst($itemType) . 's')
@section('subpage_name', 'HMO Tariffs')
@section('style')
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
    <style>
        .tariff-page .stat-card {
            border-radius: 10px; padding: 14px 18px;
            border: 1px solid #e2e8f0; background: #fff;
            transition: box-shadow 0.2s;
        }
        .tariff-page .stat-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .tariff-page .stat-card .stat-value { font-size: 1.3rem; font-weight: 700; }
        .tariff-page .stat-card .stat-label { font-size: 0.78rem; color: #6c757d; }

        .view-toggle .btn { border-radius: 8px; font-size: 0.82rem; }
        .view-toggle .btn.active { background: #007bff; color: #fff; border-color: #007bff; }

        .scheme-group { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 16px; overflow: hidden; }
        .scheme-group-header {
            background: #f8f9fa; padding: 10px 16px; cursor: pointer;
            display: flex; align-items: center; justify-content: between;
            user-select: none; border-bottom: 1px solid #e2e8f0;
        }
        .scheme-group-header:hover { background: #edf2f7; }
        .scheme-group-body { display: none; max-height: 600px; overflow-y: auto; }
        .scheme-group.open .scheme-group-body { display: block; }
        .scheme-group .chevron { transition: transform 0.2s; }
        .scheme-group.open .chevron { transform: rotate(180deg); }

        .tariff-table { margin: 0; }
        .tariff-table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; background: #fafbfc; }
        .tariff-table td { vertical-align: middle; font-size: 0.85rem; }
        .tariff-table .tariff-input {
            width: 110px; padding: 4px 8px; font-size: 0.82rem;
            border: 1px solid #dee2e6; border-radius: 6px; text-align: right;
            transition: border-color 0.2s;
        }
        .tariff-table .tariff-input:focus { border-color: #80bdff; outline: none; box-shadow: 0 0 0 2px rgba(0,123,255,0.15); }
        .tariff-table .tariff-input.changed { border-color: #ffc107; background: #fffdf0; }
        .tariff-table .tariff-input.saved { border-color: #28a745; background: #f0fff4; }

        .badge-scheme { background: #e8eaf6; color: #283593; border-radius: 12px; font-size: 0.72rem; padding: 3px 10px; }
        .badge-no-tariff { background: #fff3cd; color: #856404; border-radius: 12px; font-size: 0.65rem; padding: 2px 8px; }
        .badge-standalone { background: #e0f2f1; color: #00695c; border-radius: 12px; font-size: 0.72rem; padding: 3px 10px; }

        .save-row-btn {
            padding: 2px 10px; font-size: 0.75rem; border-radius: 6px;
            opacity: 0; transition: opacity 0.2s;
        }
        .tariff-row.dirty .save-row-btn { opacity: 1; }

        .bulk-bar {
            background: #f0f7ff; border: 1px solid #b8d4f0; border-radius: 10px;
            padding: 12px 16px; margin-bottom: 16px; display: none;
        }
        .bulk-bar.active { display: block; }

        .search-box { max-width: 280px; }
        .info-callout { border-left: 4px solid #17a2b8; background: #f0faff; border-radius: 0 8px 8px 0; padding: 10px 14px; font-size: 0.85rem; }

        .standalone-search { max-width: 260px; margin: 8px 16px; }
        .load-more-bar {
            text-align: center; padding: 8px; background: #f8f9fa;
            border-top: 1px solid #e2e8f0; cursor: pointer; font-size: 0.82rem;
            color: #007bff; user-select: none;
        }
        .load-more-bar:hover { background: #edf2f7; }
        .standalone-counter { font-size: 0.72rem; color: #6c757d; padding: 4px 16px; background: #fafbfc; border-bottom: 1px solid #e2e8f0; }

        .coverage-badge {
            font-size: 0.68rem; padding: 2px 8px; border-radius: 10px;
            font-weight: 600; letter-spacing: 0.3px; display: inline-block;
        }
        .coverage-badge.express { background: #e8f5e9; color: #2e7d32; }
        .coverage-badge.primary { background: #e3f2fd; color: #1565c0; }
        .coverage-badge.secondary { background: #fff3e0; color: #e65100; }
        .coverage-select {
            font-size: 0.78rem; padding: 2px 6px; border-radius: 6px;
            border: 1px solid #dee2e6; background: #fff; width: 110px;
            display: none;
        }
        .coverage-select:focus { border-color: #80bdff; outline: none; box-shadow: 0 0 0 2px rgba(0,123,255,0.15); }
        .coverage-cell .edit-coverage-check { margin-right: 4px; cursor: pointer; }
        .coverage-cell .edit-coverage-check:checked ~ .coverage-select { display: inline-block; }
        .coverage-cell .edit-coverage-check:checked ~ .coverage-badge { display: none; }
    </style>
@endsection
@section('content')
<div class="container-fluid tariff-page">
    {{-- Header --}}
    <div class="card-modern mb-3">
        <div class="card-header-modern d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2 class="mb-1 font-weight-bold text-dark">
                    <i class="mdi mdi-shield-check text-info"></i>
                    HMO Tariffs
                </h2>
                <p class="text-muted mb-0">
                    <strong>{{ $itemName }}</strong>
                    <span class="mx-1">·</span>
                    Current price: <strong>&#8358;{{ number_format($salePrice, 2) }}</strong>
                    <span class="mx-1">·</span>
                    {{ $totalHmoCount }} active HMOs
                </p>
            </div>
            <div>
                <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm mr-1">
                    <i class="mdi mdi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    {{-- Stats row --}}
    <div class="row mb-3">
        @php
            $allHmos = collect();
            foreach ($schemeSummary as $s) { foreach ($s['hmos'] as $h) { $allHmos->push($h); } }
            foreach ($standaloneData as $h) { $allHmos->push($h); }
            $withTariff = $allHmos->where('has_tariff', true)->count();
            $withoutTariff = $allHmos->where('has_tariff', false)->count();
            $avgPayable = $allHmos->where('has_tariff', true)->avg('payable_amount') ?? 0;
            $avgClaims = $allHmos->where('has_tariff', true)->avg('claims_amount') ?? 0;
        @endphp
        <div class="col-6 col-md-3 mb-2">
            <div class="stat-card">
                <div class="stat-value text-primary">{{ $totalHmoCount }}</div>
                <div class="stat-label">Total Active HMOs</div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="stat-card">
                <div class="stat-value text-success">{{ $withTariff }}</div>
                <div class="stat-label">With Tariff</div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="stat-card">
                <div class="stat-value text-warning">{{ $withoutTariff }}</div>
                <div class="stat-label">No Tariff</div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="stat-card">
                <div class="stat-value text-info">&#8358;{{ number_format($avgPayable, 2) }}</div>
                <div class="stat-label">Avg Payable</div>
            </div>
        </div>
    </div>

    {{-- Explanation callout --}}
    <div class="info-callout mb-3">
        <strong><i class="mdi mdi-information-outline mr-1"></i> How tariffs work:</strong>
        <span class="ml-1"><strong>Payable</strong> = amount the patient pays out-of-pocket.</span>
        <span class="mx-1">·</span>
        <span><strong>Claims</strong> = amount the HMO reimburses the hospital.</span>
        <span class="mx-1">·</span>
        <span>100% coverage means payable is &#8358;0 (patient pays nothing).</span>
        <span class="mx-1">·</span>
        <span><strong>Coverage Mode:</strong>
            <span class="coverage-badge express">Express</span> auto-approved,
            <span class="coverage-badge primary">Primary</span> requires validation,
            <span class="coverage-badge secondary">Secondary</span> requires validation + auth code.
        </span>
    </div>

    {{-- Toolbar --}}
    <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
        <div class="d-flex align-items-center mb-2">
            <div class="input-group search-box mr-3">
                <div class="input-group-prepend">
                    <span class="input-group-text" style="border-radius: 8px 0 0 8px; background: #f8f9fa;">
                        <i class="mdi mdi-magnify"></i>
                    </span>
                </div>
                <input type="text" class="form-control form-control-sm" id="tariffSearch"
                    placeholder="Search HMOs..." style="border-radius: 0 8px 8px 0;">
            </div>
            <div class="btn-group view-toggle mr-2">
                <button class="btn btn-sm btn-outline-secondary active" data-view="scheme" title="Group by Scheme">
                    <i class="mdi mdi-group"></i> By Scheme
                </button>
                <button class="btn btn-sm btn-outline-secondary" data-view="all" title="Flat List">
                    <i class="mdi mdi-format-list-bulleted"></i> All
                </button>
            </div>
        </div>
        <div class="mb-2">
            <button class="btn btn-sm btn-outline-primary" id="bulkEditToggle">
                <i class="mdi mdi-pencil-box-multiple-outline mr-1"></i> Bulk Update
            </button>
            <button class="btn btn-sm btn-success d-none" id="saveAllBtn">
                <i class="mdi mdi-content-save-all mr-1"></i> Save All Changes
                <span class="badge badge-light ml-1" id="changeCount">0</span>
            </button>
        </div>
    </div>

    {{-- Bulk update bar --}}
    <div class="bulk-bar" id="bulkBar">
        <form id="bulkForm" class="d-flex align-items-end flex-wrap gap-2">
            <input type="hidden" name="item_type" value="{{ $itemType }}">
            <input type="hidden" name="item_id" value="{{ $itemId }}">
            <div class="mr-3">
                <label class="small font-weight-bold mb-1">Apply Payable (&#8358;)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="bulk_payable"
                    id="bulkPayable" placeholder="Leave blank to skip" style="width: 150px;">
            </div>
            <div class="mr-3">
                <label class="small font-weight-bold mb-1">Apply Claims (&#8358;)</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="bulk_claims"
                    id="bulkClaims" placeholder="Leave blank to skip" style="width: 150px;">
            </div>
            <div class="mr-3">
                <label class="small font-weight-bold mb-1">Apply To</label>
                <select class="form-control form-control-sm" id="bulkScope" style="width: 180px;">
                    <option value="visible">Visible HMOs</option>
                    <option value="selected">Selected Schemes</option>
                    <option value="no-tariff">Without Tariff Only</option>
                    <option value="all">All HMOs</option>
                </select>
            </div>
            <button type="button" class="btn btn-sm btn-primary" id="applyBulkBtn">
                <i class="mdi mdi-check mr-1"></i> Apply
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary ml-1" id="cancelBulkBtn">
                Cancel
            </button>
        </form>
    </div>

    {{-- ═══ Scheme View ═══ --}}
    <div id="schemeView">
        @foreach ($schemeSummary as $idx => $scheme)
        <div class="scheme-group{{ $idx === 0 ? ' open' : '' }}" data-scheme-id="{{ $scheme['id'] }}">
            <div class="scheme-group-header" onclick="$(this).closest('.scheme-group').toggleClass('open')">
                <div class="flex-grow-1 d-flex align-items-center">
                    <i class="mdi mdi-chevron-down chevron mr-2"></i>
                    <strong>{{ $scheme['name'] }}</strong>
                    <span class="badge-scheme ml-2">{{ $scheme['hmo_count'] }} HMOs</span>
                    @if ($scheme['manual_count']> 0)
                        <span class="ml-2" style="font-size: 0.72rem; color: #c62828;">
                            <i class="mdi mdi-account-edit"></i> {{ $scheme['manual_count'] }} manual
                        </span>
                    @endif
                </div>
                <div class="text-right text-muted" style="font-size: 0.78rem;">
                    Payable: &#8358;{{ number_format($scheme['payable_min'], 2) }}–{{ number_format($scheme['payable_max'], 2) }}
                    <span class="mx-1">·</span>
                    Claims: &#8358;{{ number_format($scheme['claims_min'], 2) }}–{{ number_format($scheme['claims_max'], 2) }}
                </div>
            </div>
            <div class="scheme-group-body">
                <table class="table table-sm tariff-table">
                    <thead>
                        <tr>
                            <th style="width: 28%;">HMO Name</th>
                            <th style="width: 15%;">Payable (&#8358;)</th>
                            <th style="width: 15%;">Claims (&#8358;)</th>
                            <th style="width: 14%;">Coverage</th>
                            <th style="width: 13%;">Status</th>
                            <th style="width: 15%;" class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($scheme['hmos'] as $hmo)
                        <tr class="tariff-row hmo-row" data-hmo-id="{{ $hmo['id'] }}" data-name="{{ strtolower($hmo['name']) }}"
                            data-orig-payable="{{ $hmo['payable_amount'] }}" data-orig-claims="{{ $hmo['claims_amount'] }}"
                            data-orig-coverage="{{ $hmo['coverage_mode'] }}">
                            <td>
                                <i class="mdi mdi-shield-outline text-muted mr-1"></i>
                                {{ $hmo['name'] }}
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0"
                                    class="tariff-input payable-input"
                                    value="{{ number_format($hmo['payable_amount'], 2, '.', '') }}"
                                    data-field="payable">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0"
                                    class="tariff-input claims-input"
                                    value="{{ number_format($hmo['claims_amount'], 2, '.', '') }}"
                                    data-field="claims">
                            </td>
                            <td class="coverage-cell">
                                <input type="checkbox" class="edit-coverage-check" title="Check to change coverage mode">
                                <span class="coverage-badge {{ $hmo['coverage_mode'] }}">{{ ucfirst($hmo['coverage_mode']) }}</span>
                                <select class="coverage-select">
                                    <option value="express" {{ $hmo['coverage_mode'] === 'express' ? 'selected' : '' }}>Express</option>
                                    <option value="primary" {{ $hmo['coverage_mode'] === 'primary' ? 'selected' : '' }}>Primary</option>
                                    <option value="secondary" {{ $hmo['coverage_mode'] === 'secondary' ? 'selected' : '' }}>Secondary</option>
                                </select>
                            </td>
                            <td>
                                @if (!$hmo['has_tariff'])
                                    <span class="badge-no-tariff">No tariff</span>
                                @elseif ($hmo['is_manual'])
                                    <span style="font-size: 0.72rem; color: #c62828;"><i class="mdi mdi-account-edit mr-1"></i>Manual</span>
                                @else
                                    <span style="font-size: 0.72rem; color: #1565c0;"><i class="mdi mdi-sync mr-1"></i>Auto</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <button type="button" class="btn btn-sm btn-success save-row-btn" title="Save">
                                    <i class="mdi mdi-check"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach

        {{-- Standalone HMOs --}}
        @if (count($standaloneData)> 0)
        @php
            $standaloneWithTariff = collect($standaloneData)->where('has_tariff', true)->count();
            $standaloneTotal = count($standaloneData);
            $standalonePageSize = 20;
        @endphp
        <div class="scheme-group" data-scheme-id="standalone" id="standaloneGroup">
            <div class="scheme-group-header" onclick="$(this).closest('.scheme-group').toggleClass('open')">
                <div class="flex-grow-1 d-flex align-items-center">
                    <i class="mdi mdi-chevron-down chevron mr-2"></i>
                    <strong>Standalone HMOs</strong>
                    <span class="badge-standalone ml-2">{{ $standaloneTotal }} HMOs</span>
                    <span class="ml-2" style="font-size: 0.72rem; color: #6c757d;">
                        · {{ $standaloneWithTariff }} with tariff · {{ $standaloneTotal - $standaloneWithTariff }} without
                    </span>
                </div>
            </div>
            <div class="scheme-group-body">
                <div class="d-flex align-items-center justify-content-between">
                    <input type="text" class="form-control form-control-sm standalone-search"
                        id="standaloneSearch" placeholder="Filter standalone HMOs...">
                    <div class="standalone-counter" id="standaloneCounter">
                        Showing <span id="standaloneShown">{{ min($standalonePageSize, $standaloneTotal) }}</span> of {{ $standaloneTotal }}
                    </div>
                </div>
                <table class="table table-sm tariff-table">
                    <thead>
                        <tr>
                            <th style="width: 28%;">HMO Name</th>
                            <th style="width: 15%;">Payable (&#8358;)</th>
                            <th style="width: 15%;">Claims (&#8358;)</th>
                            <th style="width: 14%;">Coverage</th>
                            <th style="width: 13%;">Status</th>
                            <th style="width: 15%;" class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="standaloneBody">
                        @foreach ($standaloneData as $si => $hmo)
                        <tr class="tariff-row hmo-row standalone-row" data-hmo-id="{{ $hmo['id'] }}" data-name="{{ strtolower($hmo['name']) }}"
                            data-orig-payable="{{ $hmo['payable_amount'] }}" data-orig-claims="{{ $hmo['claims_amount'] }}"
                            data-orig-coverage="{{ $hmo['coverage_mode'] }}"
                            {!! $si>= $standalonePageSize ? 'style="display:none" data-paged="1"' : '' !!}>
                            <td>
                                <i class="mdi mdi-shield-outline text-muted mr-1"></i>
                                {{ $hmo['name'] }}
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0"
                                    class="tariff-input payable-input"
                                    value="{{ number_format($hmo['payable_amount'], 2, '.', '') }}"
                                    data-field="payable">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0"
                                    class="tariff-input claims-input"
                                    value="{{ number_format($hmo['claims_amount'], 2, '.', '') }}"
                                    data-field="claims">
                            </td>
                            <td class="coverage-cell">
                                <input type="checkbox" class="edit-coverage-check" title="Check to change coverage mode">
                                <span class="coverage-badge {{ $hmo['coverage_mode'] }}">{{ ucfirst($hmo['coverage_mode']) }}</span>
                                <select class="coverage-select">
                                    <option value="express" {{ $hmo['coverage_mode'] === 'express' ? 'selected' : '' }}>Express</option>
                                    <option value="primary" {{ $hmo['coverage_mode'] === 'primary' ? 'selected' : '' }}>Primary</option>
                                    <option value="secondary" {{ $hmo['coverage_mode'] === 'secondary' ? 'selected' : '' }}>Secondary</option>
                                </select>
                            </td>
                            <td>
                                @if (!$hmo['has_tariff'])
                                    <span class="badge-no-tariff">No tariff</span>
                                @elseif ($hmo['is_manual'])
                                    <span style="font-size: 0.72rem; color: #c62828;"><i class="mdi mdi-account-edit mr-1"></i>Manual</span>
                                @else
                                    <span style="font-size: 0.72rem; color: #1565c0;"><i class="mdi mdi-sync mr-1"></i>Auto</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <button type="button" class="btn btn-sm btn-success save-row-btn" title="Save">
                                    <i class="mdi mdi-check"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($standaloneTotal> $standalonePageSize)
                <div class="load-more-bar" id="standaloneLoadMore">
                    <i class="mdi mdi-chevron-down mr-1"></i>
                    Show {{ min($standalonePageSize, $standaloneTotal - $standalonePageSize) }} more
                    <span class="text-muted ml-1">({{ $standaloneTotal - $standalonePageSize }} remaining)</span>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if (count($schemeSummary) === 0 && count($standaloneData) === 0)
            <div class="info-callout text-center py-4">
                <i class="mdi mdi-information-outline mr-1"></i> No active HMOs found. Configure HMOs in the HMO management section first.
            </div>
        @endif
    </div>

    {{-- ═══ Flat View (hidden by default) ═══ --}}
    <div id="flatView" style="display: none;">
        <div class="scheme-group open">
            <div class="d-flex align-items-center justify-content-between px-3 py-2" style="border-bottom: 1px solid #e2e8f0;">
                <input type="text" class="form-control form-control-sm standalone-search"
                    id="flatSearch" placeholder="Filter all HMOs...">
                <div class="standalone-counter" id="flatCounter">
                    Showing <span id="flatShown">0</span> of <span id="flatTotal">0</span>
                </div>
            </div>
            <table class="table table-sm tariff-table">
                <thead>
                    <tr>
                        <th style="width: 22%;">HMO Name</th>
                        <th style="width: 12%;">Scheme</th>
                        <th style="width: 13%;">Payable (&#8358;)</th>
                        <th style="width: 13%;">Claims (&#8358;)</th>
                        <th style="width: 14%;">Coverage</th>
                        <th style="width: 11%;">Status</th>
                        <th style="width: 15%;" class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody id="flatBody">
                    @php
                        $flatList = collect();
                        foreach ($schemeSummary as $scheme) {
                            foreach ($scheme['hmos'] as $hmo) {
                                $flatList->push(array_merge($hmo, ['scheme_name' => $scheme['name']]));
                            }
                        }
                        foreach ($standaloneData as $hmo) {
                            $flatList->push(array_merge($hmo, ['scheme_name' => '—']));
                        }
                        $flatList = $flatList->sortBy('name')->values();
                        $flatPageSize = 30;
                    @endphp
                    @foreach ($flatList as $fi => $hmo)
                    <tr class="tariff-row hmo-row flat-row" data-hmo-id="{{ $hmo['id'] }}" data-name="{{ strtolower($hmo['name']) }}"
                        data-orig-payable="{{ $hmo['payable_amount'] }}" data-orig-claims="{{ $hmo['claims_amount'] }}"
                        data-orig-coverage="{{ $hmo['coverage_mode'] }}"
                        {!! $fi>= $flatPageSize ? 'style="display:none" data-paged="1"' : '' !!}>
                        <td>
                            <i class="mdi mdi-shield-outline text-muted mr-1"></i>
                            {{ $hmo['name'] }}
                        </td>
                        <td><span class="badge-scheme">{{ $hmo['scheme_name'] }}</span></td>
                        <td>
                            <input type="number" step="0.01" min="0"
                                class="tariff-input payable-input"
                                value="{{ number_format($hmo['payable_amount'], 2, '.', '') }}"
                                data-field="payable">
                        </td>
                        <td>
                            <input type="number" step="0.01" min="0"
                                class="tariff-input claims-input"
                                value="{{ number_format($hmo['claims_amount'], 2, '.', '') }}"
                                data-field="claims">
                        </td>
                        <td class="coverage-cell">
                            <input type="checkbox" class="edit-coverage-check" title="Check to change coverage mode">
                            <span class="coverage-badge {{ $hmo['coverage_mode'] }}">{{ ucfirst($hmo['coverage_mode']) }}</span>
                            <select class="coverage-select">
                                <option value="express" {{ $hmo['coverage_mode'] === 'express' ? 'selected' : '' }}>Express</option>
                                <option value="primary" {{ $hmo['coverage_mode'] === 'primary' ? 'selected' : '' }}>Primary</option>
                                <option value="secondary" {{ $hmo['coverage_mode'] === 'secondary' ? 'selected' : '' }}>Secondary</option>
                            </select>
                        </td>
                        <td>
                            @if (!$hmo['has_tariff'])
                                <span class="badge-no-tariff">No tariff</span>
                            @elseif ($hmo['is_manual'])
                                <span style="font-size: 0.72rem; color: #c62828;"><i class="mdi mdi-account-edit mr-1"></i>Manual</span>
                            @else
                                <span style="font-size: 0.72rem; color: #1565c0;"><i class="mdi mdi-sync mr-1"></i>Auto</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <button type="button" class="btn btn-sm btn-success save-row-btn" title="Save">
                                <i class="mdi mdi-check"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($flatList->count()> $flatPageSize)
            <div class="load-more-bar" id="flatLoadMore">
                <i class="mdi mdi-chevron-down mr-1"></i>
                Show more <span class="text-muted ml-1" id="flatRemaining">({{ $flatList->count() - $flatPageSize }} remaining)</span>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    const ITEM_TYPE = '{{ $itemType }}';
    const ITEM_ID = {{ $itemId }};
    const CSRF = '{{ csrf_token() }}';
    const SAVE_URL = '{{ route("tariff.update") }}';

    // ── Page sizes ──
    const STANDALONE_PAGE = {{ $standaloneData ? (isset($standalonePageSize) ? $standalonePageSize : 20) : 20 }};
    const FLAT_PAGE = {{ isset($flatPageSize) ? $flatPageSize : 30 }};

    // ── Global search ──
    $('#tariffSearch').on('input', function() {
        let term = $(this).val().toLowerCase();
        $('.hmo-row').each(function() {
            let match = $(this).data('name').indexOf(term) !== -1;
            // Don't show paged rows unless actively searching
            if (term === '' && $(this).data('paged')) {
                $(this).hide();
            } else {
                $(this).toggle(match);
            }
        });
        updateStandaloneCounter();
        updateFlatCounter();
    });

    // ── Standalone search ──
    $('#standaloneSearch').on('input', function() {
        let term = $(this).val().toLowerCase();
        $('#standaloneBody .standalone-row').each(function() {
            if (term === '') {
                // Restore paged state
                $(this).toggle(!$(this).data('paged'));
            } else {
                $(this).toggle($(this).data('name').indexOf(term) !== -1);
            }
        });
        updateStandaloneCounter();
        // Hide/show load-more when searching
        $('#standaloneLoadMore').toggle(term === '' && $('#standaloneBody .standalone-row[data-paged]').length> 0);
    });

    // ── Flat search ──
    $('#flatSearch').on('input', function() {
        let term = $(this).val().toLowerCase();
        $('#flatBody .flat-row').each(function() {
            if (term === '') {
                $(this).toggle(!$(this).data('paged'));
            } else {
                $(this).toggle($(this).data('name').indexOf(term) !== -1);
            }
        });
        updateFlatCounter();
        $('#flatLoadMore').toggle(term === '' && $('#flatBody .flat-row[data-paged]').length> 0);
    });

    // ── Load more: Standalone ──
    $('#standaloneLoadMore').on('click', function() {
        let hidden = $('#standaloneBody .standalone-row[data-paged]');
        hidden.slice(0, STANDALONE_PAGE).each(function() {
            $(this).show().removeAttr('data-paged').removeData('paged');
        });
        let remaining = $('#standaloneBody .standalone-row[data-paged]').length;
        if (remaining === 0) {
            $(this).hide();
        } else {
            $(this).find('span').text('(' + remaining + ' remaining)');
        }
        updateStandaloneCounter();
    });

    // ── Load more: Flat ──
    $('#flatLoadMore').on('click', function() {
        let hidden = $('#flatBody .flat-row[data-paged]');
        hidden.slice(0, FLAT_PAGE).each(function() {
            $(this).show().removeAttr('data-paged').removeData('paged');
        });
        let remaining = $('#flatBody .flat-row[data-paged]').length;
        if (remaining === 0) {
            $(this).hide();
        } else {
            $(this).find('#flatRemaining').text('(' + remaining + ' remaining)');
        }
        updateFlatCounter();
    });

    function updateStandaloneCounter() {
        let visible = $('#standaloneBody .standalone-row:visible').length;
        let total = $('#standaloneBody .standalone-row').length;
        $('#standaloneShown').text(visible);
    }

    function updateFlatCounter() {
        let visible = $('#flatBody .flat-row:visible').length;
        let total = $('#flatBody .flat-row').length;
        $('#flatShown').text(visible);
        $('#flatTotal').text(total);
    }

    // Init counters
    updateFlatCounter();

    // ── View toggle ──
    $('.view-toggle .btn').on('click', function() {
        $('.view-toggle .btn').removeClass('active');
        $(this).addClass('active');
        let view = $(this).data('view');
        $('#schemeView').toggle(view === 'scheme');
        $('#flatView').toggle(view === 'all');
    });

    // ── Dirty tracking ──
    $(document).on('input', '.tariff-input', function() {
        checkRowDirty($(this).closest('.tariff-row'));
    });

    // Coverage checkbox toggle
    $(document).on('change', '.edit-coverage-check', function() {
        let $row = $(this).closest('.tariff-row');
        if (!this.checked) {
            // Reset select to original value
            let orig = $row.data('orig-coverage') || 'primary';
            $row.find('.coverage-select').val(orig);
        }
        checkRowDirty($row);
    });

    // Coverage select change
    $(document).on('change', '.coverage-select', function() {
        checkRowDirty($(this).closest('.tariff-row'));
    });

    function checkRowDirty($row) {
        let $payable = $row.find('.payable-input');
        let $claims = $row.find('.claims-input');
        let origP = parseFloat($row.data('orig-payable')) || 0;
        let origC = parseFloat($row.data('orig-claims')) || 0;
        let curP = parseFloat($payable.val()) || 0;
        let curC = parseFloat($claims.val()) || 0;

        let origCov = $row.data('orig-coverage') || 'primary';
        let $check = $row.find('.edit-coverage-check');
        let curCov = $check.is(':checked') ? $row.find('.coverage-select').val() : origCov;
        let covDirty = curCov !== origCov;

        let dirty = (curP.toFixed(2) !== origP.toFixed(2)) || (curC.toFixed(2) !== origC.toFixed(2)) || covDirty;

        $row.toggleClass('dirty', dirty);
        $payable.toggleClass('changed', curP.toFixed(2) !== origP.toFixed(2));
        $claims.toggleClass('changed', curC.toFixed(2) !== origC.toFixed(2));

        updateChangeCount();
    }

    function updateChangeCount() {
        // Count unique dirty hmo IDs (may appear in both views)
        let dirtyIds = new Set();
        $('.tariff-row.dirty').each(function() { dirtyIds.add($(this).data('hmo-id')); });
        let count = dirtyIds.size;
        $('#changeCount').text(count);
        $('#saveAllBtn').toggleClass('d-none', count === 0);
    }

    // ── Save single row ──
    $(document).on('click', '.save-row-btn', function() {
        let $row = $(this).closest('.tariff-row');
        saveTariffRow($row);
    });

    function saveTariffRow($row) {
        let hmoId = $row.data('hmo-id');
        let payable = parseFloat($row.find('.payable-input').val()) || 0;
        let claims = parseFloat($row.find('.claims-input').val()) || 0;
        let $check = $row.find('.edit-coverage-check');
        let coverage = $check.is(':checked') ? $row.find('.coverage-select').val() : null;
        let $btn = $row.find('.save-row-btn');

        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i>');

        let postData = {
            _token: CSRF,
            item_type: ITEM_TYPE,
            item_id: ITEM_ID,
            hmo_id: hmoId,
            payable_amount: payable,
            claims_amount: claims
        };
        if (coverage) postData.coverage_mode = coverage;

        $.ajax({
            url: SAVE_URL,
            method: 'POST',
            data: postData,
            success: function(res) {
                let savedCov = coverage || ($row.data('orig-coverage') || 'primary');
                // Update orig values
                $row.data('orig-payable', payable);
                $row.data('orig-claims', claims);
                $row.data('orig-coverage', savedCov);
                $row.removeClass('dirty');
                $row.find('.tariff-input').removeClass('changed').addClass('saved');
                // Reset coverage checkbox
                $row.find('.edit-coverage-check').prop('checked', false);
                $row.find('.coverage-badge').attr('class', 'coverage-badge ' + savedCov).text(savedCov.charAt(0).toUpperCase() + savedCov.slice(1));
                $row.find('.coverage-select').val(savedCov);
                // Also sync the row in the other view
                syncRow(hmoId, payable, claims, savedCov);
                setTimeout(() => $row.find('.tariff-input').removeClass('saved'), 1500);

                // Update status cell
                let statusHtml = payable> 0
                    ? '<span style="font-size:0.72rem;color:#c62828;"><i class="mdi mdi-account-edit mr-1"></i>Manual</span>'
                    : '<span style="font-size:0.72rem;color:#1565c0;"><i class="mdi mdi-sync mr-1"></i>Auto</span>';
                // Update status in all matching rows
                $('[data-hmo-id="' + hmoId + '"]').each(function() {
                    $(this).find('td:nth-child(' + ($(this).children().length - 1) + ')').html(statusHtml);
                });

                $btn.prop('disabled', false).html('<i class="mdi mdi-check"></i>');
                updateChangeCount();
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="mdi mdi-check"></i>');
                let msg = xhr.responseJSON?.message || 'Save failed';
                alert(msg);
            }
        });
    }

    // Keep both views in sync
    function syncRow(hmoId, payable, claims, coverage) {
        $('[data-hmo-id="' + hmoId + '"]').each(function() {
            $(this).data('orig-payable', payable);
            $(this).data('orig-claims', claims);
            $(this).data('orig-coverage', coverage);
            $(this).find('.payable-input').val(payable.toFixed(2));
            $(this).find('.claims-input').val(claims.toFixed(2));
            $(this).find('.edit-coverage-check').prop('checked', false);
            $(this).find('.coverage-badge').attr('class', 'coverage-badge ' + coverage).text(coverage.charAt(0).toUpperCase() + coverage.slice(1));
            $(this).find('.coverage-select').val(coverage);
            $(this).removeClass('dirty');
            $(this).find('.tariff-input').removeClass('changed');
        });
    }

    // ── Save all dirty ──
    $('#saveAllBtn').on('click', function() {
        let $btn = $(this);
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');

        // Collect unique dirty rows (prefer scheme view)
        let seen = new Set();
        let rows = [];
        $('#schemeView .tariff-row.dirty, #flatView .tariff-row.dirty').each(function() {
            let hmoId = $(this).data('hmo-id');
            if (!seen.has(hmoId)) {
                seen.add(hmoId);
                let $check = $(this).find('.edit-coverage-check');
                let covMode = $check.is(':checked') ? $(this).find('.coverage-select').val() : null;
                let rowData = {
                    hmo_id: hmoId,
                    payable_amount: parseFloat($(this).find('.payable-input').val()) || 0,
                    claims_amount: parseFloat($(this).find('.claims-input').val()) || 0
                };
                if (covMode) rowData.coverage_mode = covMode;
                rows.push(rowData);
            }
        });

        $.ajax({
            url: SAVE_URL,
            method: 'POST',
            data: {
                _token: CSRF,
                item_type: ITEM_TYPE,
                item_id: ITEM_ID,
                bulk: JSON.stringify(rows)
            },
            success: function(res) {
                rows.forEach(function(r) {
                    let cov = r.coverage_mode || ($('[data-hmo-id="' + r.hmo_id + '"]').first().data('orig-coverage') || 'primary');
                    syncRow(r.hmo_id, r.payable_amount, r.claims_amount, cov);
                });
                $('.tariff-input').addClass('saved');
                setTimeout(() => $('.tariff-input').removeClass('saved'), 1500);
                updateChangeCount();
                $btn.prop('disabled', false).html('<i class="mdi mdi-content-save-all mr-1"></i> Save All Changes <span class="badge badge-light ml-1" id="changeCount">0</span>');
            },
            error: function(xhr) {
                let msg = xhr.responseJSON?.message || 'Bulk save failed';
                alert(msg);
                $btn.prop('disabled', false).html('<i class="mdi mdi-content-save-all mr-1"></i> Save All Changes <span class="badge badge-light ml-1" id="changeCount">' + seen.size + '</span>');
            }
        });
    });

    // ── Bulk edit bar ──
    $('#bulkEditToggle').on('click', function() {
        $('#bulkBar').toggleClass('active');
    });
    $('#cancelBulkBtn').on('click', function() {
        $('#bulkBar').removeClass('active');
    });

    // Apply bulk values
    $('#applyBulkBtn').on('click', function() {
        let payable = $('#bulkPayable').val();
        let claims = $('#bulkClaims').val();
        let scope = $('#bulkScope').val();

        if (payable === '' && claims === '') {
            alert('Enter at least one value to apply.');
            return;
        }

        let $rows;
        if (scope === 'visible') {
            $rows = $('.hmo-row:visible');
        } else if (scope === 'selected') {
            let schemeIds = [];
            $('.scheme-group.open').each(function() {
                let sid = $(this).data('scheme-id');
                if (sid !== 'standalone') schemeIds.push(sid);
            });
            $rows = $('.hmo-row:visible');
        } else if (scope === 'no-tariff') {
            $rows = $('.hmo-row').filter(function() {
                return parseFloat($(this).data('orig-payable')) === 0 && parseFloat($(this).data('orig-claims')) === 0;
            });
        } else {
            $rows = $('.hmo-row');
        }

        $rows.each(function() {
            if (payable !== '') {
                $(this).find('.payable-input').val(parseFloat(payable).toFixed(2)).trigger('input');
            }
            if (claims !== '') {
                $(this).find('.claims-input').val(parseFloat(claims).toFixed(2)).trigger('input');
            }
        });
    });
});
</script>
@endpush
