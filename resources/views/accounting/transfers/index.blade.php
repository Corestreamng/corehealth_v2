{{--
    Inter-Account Transfers Index - Revamped
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 2
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Inter-Account Transfers')
@section('page_name', 'Accounting')
@section('subpage_name', 'Bank Transfers')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
<style>
/* ============================================
   STATUS CARDS - Clickable with hover effects
   ============================================ */
.status-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}
.status-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}
.status-card.active {
    border-color: currentColor;
}
.status-card .card-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 12px;
}
.status-card .card-count {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 4px;
}
.status-card .card-amount {
    font-size: 0.9rem;
    opacity: 0.8;
    margin-bottom: 8px;
}
.status-card .card-label {
    font-size: 0.85rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.status-card .card-trend {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 20px;
    font-weight: 600;
}
.status-card .card-trend.up { background: rgba(40,167,69,0.15); color: #28a745; }
.status-card .card-trend.down { background: rgba(220,53,69,0.15); color: #dc3545; }

/* Card color variants */
.status-card.pending { color: #f59e0b; }
.status-card.pending .card-icon { background: rgba(245,158,11,0.15); }
.status-card.awaiting { color: #3b82f6; }
.status-card.awaiting .card-icon { background: rgba(59,130,246,0.15); }
.status-card.transit { color: #8b5cf6; }
.status-card.transit .card-icon { background: rgba(139,92,246,0.15); }
.status-card.cleared { color: #10b981; }
.status-card.cleared .card-icon { background: rgba(16,185,129,0.15); }
.status-card.failed { color: #ef4444; }
.status-card.failed .card-icon { background: rgba(239,68,68,0.15); }

/* ============================================
   VOLUME SUMMARY CARDS
   ============================================ */
.volume-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 20px;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.volume-card.today { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.volume-card.week { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.volume-card.month { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.volume-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}
.volume-card .vol-label {
    font-size: 0.85rem;
    opacity: 0.9;
    margin-bottom: 5px;
}
.volume-card .vol-amount {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 3px;
}
.volume-card .vol-count {
    font-size: 0.85rem;
    opacity: 0.85;
}
.volume-card .vol-trend {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8rem;
    margin-top: 8px;
    padding: 3px 10px;
    background: rgba(255,255,255,0.2);
    border-radius: 20px;
}

/* ============================================
   QUICK FILTERS (Pills)
   ============================================ */
.quick-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 15px;
}
.filter-pill {
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.85rem;
    font-weight: 500;
    border: 2px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
}
.filter-pill:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}
.filter-pill.active {
    background: #4e73df;
    border-color: #4e73df;
    color: #fff;
}
.filter-pill .pill-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    margin-left: 6px;
    background: rgba(0,0,0,0.1);
    border-radius: 10px;
    font-size: 0.75rem;
}
.filter-pill.active .pill-count {
    background: rgba(255,255,255,0.25);
}

/* Date presets */
.date-presets {
    display: flex;
    gap: 8px;
    margin-bottom: 15px;
}
.date-preset {
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
    background: #e2e8f0;
    color: #475569;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
}
.date-preset:hover { background: #cbd5e1; }
.date-preset.active { background: #1e40af; color: #fff; }

/* ============================================
   ADVANCED FILTERS (Collapsible)
   ============================================ */
.advanced-filters-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: #f1f5f9;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    color: #475569;
    margin-bottom: 15px;
    transition: all 0.2s ease;
}
.advanced-filters-toggle:hover { background: #e2e8f0; }
.advanced-filters-toggle .toggle-icon {
    transition: transform 0.3s ease;
}
.advanced-filters-toggle.collapsed .toggle-icon {
    transform: rotate(-90deg);
}

.advanced-filters-panel {
    background: #f8fafc;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #e2e8f0;
}

/* ============================================
   SELECT2 MODERN STYLING
   ============================================ */
.select2-container--bootstrap4 .select2-selection--single {
    height: 42px !important;
    border: 2px solid #e2e8f0 !important;
    border-radius: 8px !important;
    background: #fff !important;
    transition: all 0.2s ease !important;
    padding: 6px 12px !important;
}
.select2-container--bootstrap4 .select2-selection--single:focus,
.select2-container--bootstrap4.select2-container--focus .select2-selection--single,
.select2-container--bootstrap4.select2-container--open .select2-selection--single {
    border-color: #4e73df !important;
    box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.15) !important;
}
.select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
    line-height: 28px !important;
    padding-left: 0 !important;
    color: #1e293b !important;
}
.select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
    height: 40px !important;
}
.select2-container--bootstrap4 .select2-selection--single .select2-selection__placeholder {
    color: #94a3b8 !important;
}
.select2-container--bootstrap4 .select2-dropdown {
    border: 2px solid #e2e8f0 !important;
    border-radius: 8px !important;
    box-shadow: 0 10px 40px rgba(0,0,0,0.12) !important;
    margin-top: 4px !important;
}
.select2-container--bootstrap4 .select2-search--dropdown .select2-search__field {
    border: 2px solid #e2e8f0 !important;
    border-radius: 6px !important;
    padding: 8px 12px !important;
}
.select2-container--bootstrap4 .select2-results__option {
    padding: 10px 14px !important;
}
.select2-container--bootstrap4 .select2-results__option--highlighted[aria-selected] {
    background: #4e73df !important;
}
.select2-container--bootstrap4 .select2-results__option[aria-selected=true] {
    background: #eef2ff !important;
    color: #4e73df !important;
}

/* Bank option with balance */
.bank-option {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.bank-option .bank-name { font-weight: 500; }
.bank-option .bank-balance {
    font-size: 0.85em;
    color: #10b981;
    font-weight: 600;
}

/* ============================================
   FILTER SECTION STYLING
   ============================================ */
.filter-section {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    margin-bottom: 20px;
}
.filter-section-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}
.filter-group {
    margin-bottom: 15px;
}
.filter-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 500;
    color: #475569;
    margin-bottom: 6px;
}

/* ============================================
   ACTION BUTTONS
   ============================================ */
.filter-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
    margin-top: 15px;
}
.btn-filter {
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.2s ease;
}
.btn-filter.primary {
    background: #4e73df;
    color: #fff;
    border: none;
}
.btn-filter.primary:hover {
    background: #3a5bc7;
    transform: translateY(-1px);
}
.btn-filter.secondary {
    background: #f1f5f9;
    color: #475569;
    border: none;
}
.btn-filter.secondary:hover { background: #e2e8f0; }

/* Export dropdown */
.export-dropdown .dropdown-toggle {
    padding: 10px 20px;
    border-radius: 8px;
    background: #f1f5f9;
    border: none;
    font-weight: 500;
}
.export-dropdown .dropdown-menu {
    border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.12);
    border: 1px solid #e2e8f0;
}
.export-dropdown .dropdown-item {
    padding: 10px 16px;
    font-weight: 500;
}
.export-dropdown .dropdown-item:hover { background: #f8fafc; }
.export-dropdown .dropdown-item i { width: 20px; }

/* ============================================
   TABLE IMPROVEMENTS
   ============================================ */
.transfers-table-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    overflow: hidden;
}
.transfers-table-card .card-header {
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.transfers-table-card .card-header h5 {
    margin: 0;
    font-weight: 600;
    color: #1e293b;
}

/* Method badges with icons */
.method-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* DataTable styling overrides */
#transfers-table {
    border-collapse: separate;
    border-spacing: 0;
}
#transfers-table thead th {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    font-weight: 600;
    color: #475569;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    padding: 14px 16px;
}
#transfers-table tbody td {
    padding: 14px 16px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}
#transfers-table tbody tr:hover {
    background: #f8fafc;
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 768px) {
    .status-card { margin-bottom: 15px; }
    .quick-filters { overflow-x: auto; flex-wrap: nowrap; padding-bottom: 10px; }
    .filter-pill { white-space: nowrap; }
}
</style>
@endpush

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Inter-Account Transfers', 'url' => route('accounting.transfers.index'), 'icon' => 'mdi-bank-transfer']
    ]
])

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="mdi mdi-bank-transfer text-primary mr-2"></i>Inter-Account Transfers</h4>
            <p class="text-muted mb-0">Manage bank-to-bank transfers and track clearance status</p>
        </div>
        <a href="{{ route('accounting.transfers.create') }}" class="btn btn-primary btn-lg">
            <i class="mdi mdi-plus mr-1"></i> New Transfer
        </a>
    </div>

    <!-- Status Cards Row -->
    <div class="row mb-4">
        <div class="col-lg col-md-4 col-6 mb-3">
            <div class="status-card pending" data-filter="pending_approval">
                <div class="card-trend {{ $stats['pending_trend'] >= 0 ? 'up' : 'down' }}">
                    <i class="mdi mdi-arrow-{{ $stats['pending_trend'] >= 0 ? 'up' : 'down' }}"></i>
                    {{ abs($stats['pending_trend']) }}%
                </div>
                <div class="card-icon"><i class="mdi mdi-clock-alert-outline"></i></div>
                <div class="card-count">{{ $stats['pending_approval'] }}</div>
                <div class="card-amount">₦{{ number_format($stats['pending_amount'], 0) }}</div>
                <div class="card-label">Pending Approval</div>
            </div>
        </div>
        <div class="col-lg col-md-4 col-6 mb-3">
            <div class="status-card awaiting" data-filter="approved">
                <div class="card-icon"><i class="mdi mdi-check-decagram-outline"></i></div>
                <div class="card-count">{{ $stats['approved_count'] }}</div>
                <div class="card-amount">₦{{ number_format($stats['approved_amount'], 0) }}</div>
                <div class="card-label">Awaiting Clearance</div>
            </div>
        </div>
        <div class="col-lg col-md-4 col-6 mb-3">
            <div class="status-card transit" data-filter="in_transit">
                <div class="card-icon"><i class="mdi mdi-truck-delivery-outline"></i></div>
                <div class="card-count">{{ $stats['in_transit'] }}</div>
                <div class="card-amount">₦{{ number_format($stats['transit_amount'], 0) }}</div>
                <div class="card-label">In Transit</div>
            </div>
        </div>
        <div class="col-lg col-md-4 col-6 mb-3">
            <div class="status-card cleared" data-filter="cleared">
                <div class="card-trend up">
                    <i class="mdi mdi-arrow-up"></i>
                    {{ $stats['cleared_today'] }} today
                </div>
                <div class="card-icon"><i class="mdi mdi-check-circle-outline"></i></div>
                <div class="card-count">{{ $stats['cleared_month'] }}</div>
                <div class="card-amount">₦{{ number_format($stats['cleared_month_amount'], 0) }}</div>
                <div class="card-label">Cleared (Month)</div>
            </div>
        </div>
        <div class="col-lg col-md-4 col-6 mb-3">
            <div class="status-card failed" data-filter="failed">
                <div class="card-icon"><i class="mdi mdi-alert-circle-outline"></i></div>
                <div class="card-count">{{ $stats['failed_count'] }}</div>
                <div class="card-amount">₦{{ number_format($stats['failed_amount'], 0) }}</div>
                <div class="card-label">Failed</div>
            </div>
        </div>
    </div>

    <!-- Volume Summary -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="volume-card today">
                <div class="vol-label"><i class="mdi mdi-calendar-today mr-1"></i> Today</div>
                <div class="vol-amount">₦{{ number_format($stats['today_amount'], 2) }}</div>
                <div class="vol-count">{{ $stats['today_count'] }} transfers</div>
                @if($stats['today_vs_yesterday'] != 0)
                <div class="vol-trend">
                    <i class="mdi mdi-arrow-{{ $stats['today_vs_yesterday'] >= 0 ? 'up' : 'down' }}"></i>
                    {{ abs($stats['today_vs_yesterday']) }}% vs yesterday
                </div>
                @endif
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="volume-card week">
                <div class="vol-label"><i class="mdi mdi-calendar-week mr-1"></i> This Week</div>
                <div class="vol-amount">₦{{ number_format($stats['week_amount'], 2) }}</div>
                <div class="vol-count">{{ $stats['week_count'] }} transfers</div>
                @if($stats['week_vs_last'] != 0)
                <div class="vol-trend">
                    <i class="mdi mdi-arrow-{{ $stats['week_vs_last'] >= 0 ? 'up' : 'down' }}"></i>
                    {{ abs($stats['week_vs_last']) }}% vs last week
                </div>
                @endif
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="volume-card month">
                <div class="vol-label"><i class="mdi mdi-calendar-month mr-1"></i> This Month</div>
                <div class="vol-amount">₦{{ number_format($stats['month_amount'], 2) }}</div>
                <div class="vol-count">{{ $stats['month_count'] }} transfers</div>
                @if($stats['month_vs_last'] != 0)
                <div class="vol-trend">
                    <i class="mdi mdi-arrow-{{ $stats['month_vs_last'] >= 0 ? 'up' : 'down' }}"></i>
                    {{ abs($stats['month_vs_last']) }}% vs last month
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <!-- Quick Status Filters -->
        <div class="filter-section-title">Quick Filters</div>
        <div class="quick-filters">
            <button class="filter-pill active" data-status="">
                All <span class="pill-count">{{ $stats['total_transfers'] }}</span>
            </button>
            <button class="filter-pill" data-status="pending_approval">
                <i class="mdi mdi-clock-outline"></i> Pending
                <span class="pill-count">{{ $stats['pending_approval'] }}</span>
            </button>
            <button class="filter-pill" data-status="approved">
                <i class="mdi mdi-check"></i> Approved
                <span class="pill-count">{{ $stats['approved_count'] }}</span>
            </button>
            <button class="filter-pill" data-status="in_transit">
                <i class="mdi mdi-truck"></i> In Transit
                <span class="pill-count">{{ $stats['in_transit'] }}</span>
            </button>
            <button class="filter-pill" data-status="cleared">
                <i class="mdi mdi-check-all"></i> Cleared
            </button>
            <button class="filter-pill" data-status="failed">
                <i class="mdi mdi-alert"></i> Failed
                <span class="pill-count">{{ $stats['failed_count'] }}</span>
            </button>
            <button class="filter-pill" data-status="cancelled">
                <i class="mdi mdi-cancel"></i> Cancelled
            </button>
        </div>

        <!-- Date Presets -->
        <div class="filter-section-title">Date Range</div>
        <div class="date-presets">
            <button class="date-preset" data-range="today">Today</button>
            <button class="date-preset" data-range="yesterday">Yesterday</button>
            <button class="date-preset" data-range="week">This Week</button>
            <button class="date-preset" data-range="month">This Month</button>
            <button class="date-preset" data-range="quarter">This Quarter</button>
            <button class="date-preset" data-range="year">This Year</button>
            <button class="date-preset" data-range="custom">Custom...</button>
        </div>

        <!-- Advanced Filters Toggle -->
        <div class="advanced-filters-toggle collapsed" data-toggle="collapse" data-target="#advancedFilters">
            <i class="mdi mdi-tune toggle-icon"></i>
            <span>Advanced Filters</span>
            <i class="mdi mdi-chevron-down ml-auto"></i>
        </div>

        <!-- Advanced Filters Panel -->
        <div class="collapse" id="advancedFilters">
            <div class="advanced-filters-panel">
                <div class="row">
                    <div class="col-md-3">
                        <div class="filter-group">
                            <label>From Bank</label>
                            <select id="filter_from_bank" class="form-control select2-bank" data-placeholder="All Source Banks">
                                <option value=""></option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}" data-balance="{{ $bank->current_balance ?? 0 }}">
                                        {{ $bank->bank_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="filter-group">
                            <label>To Bank</label>
                            <select id="filter_to_bank" class="form-control select2-bank" data-placeholder="All Destination Banks">
                                <option value=""></option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank->id }}" data-balance="{{ $bank->current_balance ?? 0 }}">
                                        {{ $bank->bank_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="filter-group">
                            <label>Transfer Method</label>
                            <select id="filter_method" class="form-control select2-basic" data-placeholder="All Methods">
                                <option value=""></option>
                                <option value="internal">🏦 Internal (Same Bank)</option>
                                <option value="wire">📡 Wire Transfer</option>
                                <option value="eft">💳 EFT</option>
                                <option value="cheque">📝 Cheque</option>
                                <option value="rtgs">⚡ RTGS</option>
                                <option value="neft">🔄 NEFT</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="filter-group">
                            <label>Initiated By</label>
                            <select id="filter_initiator" class="form-control select2-basic" data-placeholder="All Users">
                                <option value=""></option>
                                @foreach($initiators as $user)
                                    <option value="{{ $user->id }}">{{ $user->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="filter-group">
                            <label>Amount Range (Min)</label>
                            <input type="number" id="filter_amount_min" class="form-control" placeholder="₦0.00" step="0.01">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="filter-group">
                            <label>Amount Range (Max)</label>
                            <input type="number" id="filter_amount_max" class="form-control" placeholder="No limit" step="0.01">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="filter-group">
                            <label>Date From</label>
                            <input type="date" id="filter_date_from" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="filter-group">
                            <label>Date To</label>
                            <input type="date" id="filter_date_to" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Actions -->
        <div class="filter-actions">
            <div>
                <button id="btn_filter" class="btn-filter primary">
                    <i class="mdi mdi-magnify mr-1"></i> Apply Filters
                </button>
                <button id="btn_reset" class="btn-filter secondary ml-2">
                    <i class="mdi mdi-refresh mr-1"></i> Reset
                </button>
            </div>
            <div class="export-dropdown dropdown">
                <button class="dropdown-toggle" type="button" data-toggle="dropdown">
                    <i class="mdi mdi-download mr-1"></i> Export
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="#" id="btn_export_excel">
                        <i class="mdi mdi-file-excel text-success"></i> Export to Excel
                    </a>
                    <a class="dropdown-item" href="#" id="btn_export_pdf">
                        <i class="mdi mdi-file-pdf text-danger"></i> Export to PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Transfers DataTable -->
    <div class="transfers-table-card">
        <div class="card-header">
            <h5><i class="mdi mdi-table mr-2"></i>Transfer Records</h5>
            <div class="d-flex align-items-center gap-3">
                <!-- Method Legend -->
                <div class="d-none d-lg-flex gap-2" style="gap: 8px;">
                    <span class="badge badge-info">INT</span>
                    <span class="badge badge-primary">WIRE</span>
                    <span class="badge badge-success">EFT</span>
                    <span class="badge badge-warning text-dark">CHQ</span>
                    <span class="badge badge-dark">RTGS</span>
                    <span class="badge badge-secondary">NEFT</span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="transfers-table" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Transfer #</th>
                            <th>Date</th>
                            <th>From → To</th>
                            <th class="text-right">Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Initiated By</th>
                            <th width="140">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-check-circle text-success mr-2"></i>Approve Transfer</h5>
                <button type="button" class="close" data-bs-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this transfer?</p>
                <p class="text-muted small">The transfer will be marked as approved and ready for clearance confirmation.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirm-approve">
                    <i class="mdi mdi-check mr-1"></i> Approve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-close-circle text-danger mr-2"></i>Reject Transfer</h5>
                <button type="button" class="close" data-bs-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Rejection Reason <span class="text-danger">*</span></label>
                    <textarea id="rejection_reason" class="form-control" rows="3" required placeholder="Enter reason for rejection..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-reject">Reject Transfer</button>
            </div>
        </div>
    </div>
</div>

<!-- Clearance Modal -->
<div class="modal fade" id="clearanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-bank-check text-success mr-2"></i>Confirm Clearance</h5>
                <button type="button" class="close" data-bs-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    <i class="mdi mdi-information mr-1"></i>
                    Confirming clearance will create the journal entry and complete the transfer.
                </div>
                <div class="form-group">
                    <label>Clearance Date</label>
                    <input type="date" id="clearance_date" class="form-control" value="{{ date('Y-m-d') }}">
                </div>
                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea id="clearance_notes" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirm-clearance">
                    <i class="mdi mdi-check-all mr-1"></i> Confirm Clearance
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-cancel text-dark mr-2"></i>Cancel Transfer</h5>
                <button type="button" class="close" data-bs-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this transfer?</p>
                <p class="text-danger small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep It</button>
                <button type="button" class="btn btn-dark" id="confirm-cancel">Yes, Cancel Transfer</button>
            </div>
        </div>
    </div>
</div>

<!-- Mark Failed Modal -->
<div class="modal fade" id="markFailedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-alert text-warning mr-2"></i>Mark Transfer as Failed</h5>
                <button type="button" class="close" data-bs-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Failure Reason <span class="text-danger">*</span></label>
                    <textarea id="failure_reason" class="form-control" rows="3" required placeholder="Enter reason for failure..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirm-mark-failed">Mark as Failed</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('/plugins/dataT/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
<script>
$(document).ready(function() {
    // ============================================
    // SELECT2 INITIALIZATION
    // ============================================
    function formatBankOption(bank) {
        if (!bank.id) return bank.text;
        var balance = $(bank.element).data('balance') || 0;
        return $('<div class="bank-option">' +
            '<span class="bank-name">' + bank.text + '</span>' +
            '<span class="bank-balance">₦' + Number(balance).toLocaleString() + '</span>' +
        '</div>');
    }

    $('.select2-bank').select2({
        theme: 'bootstrap4',
        allowClear: true,
        placeholder: $(this).data('placeholder'),
        templateResult: formatBankOption,
        width: '100%'
    });

    $('.select2-basic').select2({
        theme: 'bootstrap4',
        allowClear: true,
        placeholder: $(this).data('placeholder'),
        width: '100%'
    });

    // ============================================
    // DATATABLE INITIALIZATION
    // ============================================
    var table = $('#transfers-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('accounting.transfers.datatable') }}',
            data: function(d) {
                d.status = $('#filter_status').val();
                d.from_bank_id = $('#filter_from_bank').val();
                d.to_bank_id = $('#filter_to_bank').val();
                d.transfer_method = $('#filter_method').val();
                d.initiated_by = $('#filter_initiator').val();
                d.amount_min = $('#filter_amount_min').val();
                d.amount_max = $('#filter_amount_max').val();
                d.date_from = $('#filter_date_from').val();
                d.date_to = $('#filter_date_to').val();
            }
        },
        columns: [
            { data: 'transfer_number', name: 'transfer_number' },
            { data: 'transfer_date_formatted', name: 'transfer_date' },
            { data: 'bank_flow', name: 'from_bank.name' },
            { data: 'amount_formatted', name: 'amount', className: 'text-right font-weight-bold' },
            { data: 'method_badge', name: 'transfer_method' },
            { data: 'status_badge', name: 'status' },
            { data: 'initiator_name', name: 'initiator.name' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'desc']],
        pageLength: 25,
        language: {
            processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>',
            emptyTable: '<div class="text-center py-4"><i class="mdi mdi-bank-transfer-out mdi-48px text-muted"></i><p class="mt-2 text-muted">No transfers found</p></div>'
        },
        dom: '<"top"l>rt<"bottom d-flex justify-content-between align-items-center"ip>',
    });

    // Hidden status filter input
    $('<input>').attr({
        type: 'hidden',
        id: 'filter_status',
        value: ''
    }).appendTo('body');

    // ============================================
    // QUICK FILTERS (Status Pills)
    // ============================================
    $('.filter-pill').click(function() {
        $('.filter-pill').removeClass('active');
        $(this).addClass('active');
        $('#filter_status').val($(this).data('status'));
        table.ajax.reload();
    });

    // ============================================
    // STATUS CARD CLICK (Filter by status)
    // ============================================
    $('.status-card').click(function() {
        var status = $(this).data('filter');
        $('.filter-pill').removeClass('active');
        $('.filter-pill[data-status="' + status + '"]').addClass('active');
        $('#filter_status').val(status);
        table.ajax.reload();
    });

    // ============================================
    // DATE PRESETS
    // ============================================
    $('.date-preset').click(function() {
        $('.date-preset').removeClass('active');
        $(this).addClass('active');

        var range = $(this).data('range');
        var today = new Date();
        var from, to;

        switch(range) {
            case 'today':
                from = to = formatDate(today);
                break;
            case 'yesterday':
                var yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                from = to = formatDate(yesterday);
                break;
            case 'week':
                var weekStart = new Date(today);
                weekStart.setDate(today.getDate() - today.getDay());
                from = formatDate(weekStart);
                to = formatDate(today);
                break;
            case 'month':
                from = formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
                to = formatDate(today);
                break;
            case 'quarter':
                var quarter = Math.floor(today.getMonth() / 3);
                from = formatDate(new Date(today.getFullYear(), quarter * 3, 1));
                to = formatDate(today);
                break;
            case 'year':
                from = formatDate(new Date(today.getFullYear(), 0, 1));
                to = formatDate(today);
                break;
            case 'custom':
                $('#advancedFilters').collapse('show');
                $('.advanced-filters-toggle').removeClass('collapsed');
                $('#filter_date_from').focus();
                return;
        }

        $('#filter_date_from').val(from);
        $('#filter_date_to').val(to);
        table.ajax.reload();
    });

    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    // ============================================
    // ADVANCED FILTERS TOGGLE
    // ============================================
    $('.advanced-filters-toggle').click(function() {
        $(this).toggleClass('collapsed');
    });

    // ============================================
    // FILTER & RESET BUTTONS
    // ============================================
    $('#btn_filter').click(function() {
        table.ajax.reload();
    });

    $('#btn_reset').click(function() {
        // Reset all filters
        $('.filter-pill').removeClass('active').first().addClass('active');
        $('.date-preset').removeClass('active');
        $('#filter_status').val('');
        $('#filter_from_bank, #filter_to_bank, #filter_method, #filter_initiator').val('').trigger('change');
        $('#filter_amount_min, #filter_amount_max, #filter_date_from, #filter_date_to').val('');
        table.ajax.reload();
    });

    // ============================================
    // ACTION BUTTONS
    // ============================================
    var currentId = null;

    $(document).on('click', '.approve-btn', function() {
        currentId = $(this).data('id');
        $('#approveModal').modal('show');
    });

    $(document).on('click', '.reject-btn', function() {
        currentId = $(this).data('id');
        $('#rejection_reason').val('');
        $('#rejectModal').modal('show');
    });

    $(document).on('click', '.clearance-btn', function() {
        currentId = $(this).data('id');
        $('#clearance_date').val('{{ date('Y-m-d') }}');
        $('#clearance_notes').val('');
        $('#clearanceModal').modal('show');
    });

    $(document).on('click', '.cancel-btn', function() {
        currentId = $(this).data('id');
        $('#cancelModal').modal('show');
    });

    $(document).on('click', '.mark-failed-btn', function() {
        currentId = $(this).data('id');
        $('#failure_reason').val('');
        $('#markFailedModal').modal('show');
    });

    // ============================================
    // CONFIRM ACTIONS
    // ============================================
    $('#confirm-approve').click(function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');
        $.ajax({
            url: '/accounting/transfers/' + currentId + '/approve',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                $('#approveModal').modal('hide');
                toastr.success(res.message);
                table.ajax.reload(null, false);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Approve');
            }
        });
    });

    $('#confirm-reject').click(function() {
        var reason = $('#rejection_reason').val().trim();
        if (!reason) {
            toastr.error('Please provide a rejection reason');
            return;
        }
        var btn = $(this);
        btn.prop('disabled', true);
        $.ajax({
            url: '/accounting/transfers/' + currentId + '/reject',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', rejection_reason: reason },
            success: function(res) {
                $('#rejectModal').modal('hide');
                toastr.success(res.message);
                table.ajax.reload(null, false);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });

    $('#confirm-clearance').click(function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');
        $.ajax({
            url: '/accounting/transfers/' + currentId + '/confirm-clearance',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                clearance_date: $('#clearance_date').val(),
                notes: $('#clearance_notes').val()
            },
            success: function(res) {
                $('#clearanceModal').modal('hide');
                toastr.success(res.message);
                table.ajax.reload(null, false);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="mdi mdi-check-all mr-1"></i> Confirm Clearance');
            }
        });
    });

    $('#confirm-cancel').click(function() {
        var btn = $(this);
        btn.prop('disabled', true);
        $.ajax({
            url: '/accounting/transfers/' + currentId + '/cancel',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                $('#cancelModal').modal('hide');
                toastr.success(res.message);
                table.ajax.reload(null, false);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });

    $('#confirm-mark-failed').click(function() {
        var reason = $('#failure_reason').val().trim();
        if (!reason) {
            toastr.error('Please provide a failure reason');
            return;
        }
        var btn = $(this);
        btn.prop('disabled', true);
        $.ajax({
            url: '/accounting/transfers/' + currentId + '/mark-failed',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', failure_reason: reason },
            success: function(res) {
                $('#markFailedModal').modal('hide');
                toastr.success(res.message);
                table.ajax.reload(null, false);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });

    // ============================================
    // EXPORT BUTTONS
    // ============================================
    function buildQueryString() {
        return $.param({
            status: $('#filter_status').val(),
            from_bank_id: $('#filter_from_bank').val(),
            to_bank_id: $('#filter_to_bank').val(),
            transfer_method: $('#filter_method').val(),
            initiated_by: $('#filter_initiator').val(),
            amount_min: $('#filter_amount_min').val(),
            amount_max: $('#filter_amount_max').val(),
            date_from: $('#filter_date_from').val(),
            date_to: $('#filter_date_to').val()
        });
    }

    $('#btn_export_pdf').click(function(e) {
        e.preventDefault();
        window.location.href = '{{ route('accounting.transfers.export.pdf') }}?' + buildQueryString();
    });

    $('#btn_export_excel').click(function(e) {
        e.preventDefault();
        window.location.href = '{{ route('accounting.transfers.export.excel') }}?' + buildQueryString();
    });
});
</script>
@endpush
