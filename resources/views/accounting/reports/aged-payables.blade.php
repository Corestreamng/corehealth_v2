@extends('admin.layouts.app')

@section('page_name', 'Accounting')
@section('subpage_name', 'Aged Payables')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Reports', 'url' => route('accounting.reports.index'), 'icon' => 'mdi-file-chart'],
    ['label' => 'Aged Payables', 'url' => '#', 'icon' => 'mdi-account-clock']
]])

<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    .card-modern { border: none; box-shadow: 0 0 15px rgba(0,0,0,0.08); border-radius: 12px; }
    .card-modern .card-header { background-color: transparent; border-bottom: 1px solid #eee; padding: 1rem 1.25rem; }
    .border-left-primary { border-left: 4px solid #007bff !important; }
    .border-left-success { border-left: 4px solid #28a745 !important; }
    .border-left-info { border-left: 4px solid #17a2b8 !important; }
    .border-left-warning { border-left: 4px solid #ffc107 !important; }
    .border-left-danger { border-left: 4px solid #dc3545 !important; }
    .border-left-dark { border-left: 4px solid #343a40 !important; }
    .border-left-orange { border-left: 4px solid #fd7e14 !important; }
    .border-left-teal { border-left: 4px solid #20c997 !important; }

    .stat-value { font-size: 1.5rem; font-weight: 700; }
    .stat-label { font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }

    .nav-tabs .nav-link { color: #495057; font-weight: 500; border: none; padding: 0.75rem 1.25rem; }
    .nav-tabs .nav-link.active { color: #dc3545; border-bottom: 3px solid #dc3545; background: transparent; }
    .nav-tabs .nav-link:hover { border-bottom: 3px solid #dee2e6; }

    .category-card { background: #f8f9fa; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
    .category-card h6 { color: #495057; margin-bottom: 0.5rem; }

    .expandable-row { cursor: pointer; }
    .expandable-row:hover { background-color: #f8f9fa; }
    .detail-row { background-color: #fafbfc; }
    .detail-row td { padding-left: 2rem !important; }

    .aging-badge { font-size: 0.7rem; padding: 0.25rem 0.5rem; border-radius: 4px; }
    .aging-current { background: #d4edda; color: #155724; }
    .aging-1-30 { background: #cce5ff; color: #004085; }
    .aging-31-60 { background: #fff3cd; color: #856404; }
    .aging-61-90 { background: #f8d7da; color: #721c24; }
    .aging-over-90 { background: #721c24; color: #fff; }

    .filter-card { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
    .filter-card .form-label { color: rgba(255,255,255,0.9); font-size: 0.8rem; }
    .filter-card .form-control, .filter-card .form-select { background: rgba(255,255,255,0.95); border: none; }

    .priority-high { background: #f8d7da; }
    .priority-medium { background: #fff3cd; }
    .priority-low { background: #d4edda; }

    .po-status-badge { font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 3px; }
    .po-unpaid { background: #f8d7da; color: #721c24; }
    .po-partial { background: #fff3cd; color: #856404; }
</style>

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="mdi mdi-cash-minus text-danger mr-2"></i>Aged Payables Report</h4>
            <p class="text-muted mb-0">Comprehensive view of all amounts owed by the hospital</p>
        </div>
        <div>
            <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-secondary mr-2">
                <i class="mdi mdi-arrow-left mr-1"></i> Back
            </a>
            <div class="btn-group">
                <a href="{{ route('accounting.reports.aged-payables', array_merge(request()->all(), ['export' => 'pdf'])) }}" class="btn btn-danger">
                    <i class="mdi mdi-file-pdf-box mr-1"></i> PDF
                </a>
                <a href="{{ route('accounting.reports.aged-payables', array_merge(request()->all(), ['export' => 'excel'])) }}" class="btn btn-success">
                    <i class="mdi mdi-file-excel mr-1"></i> Excel
                </a>
            </div>
        </div>
    </div>

    {{-- Filters Card --}}
    <div class="card card-modern filter-card mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('accounting.reports.aged-payables') }}" id="filterForm">
                <div class="row align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">As of Date</label>
                        <input type="date" name="as_of_date" class="form-control" value="{{ $asOfDate->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Payable Type</label>
                        <select name="payable_type" class="form-select">
                            <option value="all">All Types</option>
                            <option value="supplier_payables" {{ ($filters['payable_type'] ?? '') == 'supplier_payables' ? 'selected' : '' }}>Supplier POs</option>
                            <option value="patient_deposits" {{ ($filters['payable_type'] ?? '') == 'patient_deposits' ? 'selected' : '' }}>Patient Deposits</option>
                            <option value="supplier_credits" {{ ($filters['payable_type'] ?? '') == 'supplier_credits' ? 'selected' : '' }}>Supplier Credits</option>
                            <option value="gl_payables" {{ ($filters['payable_type'] ?? '') == 'gl_payables' ? 'selected' : '' }}>GL Payables</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-select">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers ?? [] as $supplier)
                            <option value="{{ $supplier->id }}" {{ ($filters['supplier_id'] ?? '') == $supplier->id ? 'selected' : '' }}>{{ $supplier->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Minimum Amount</label>
                        <input type="number" name="min_amount" class="form-control" placeholder="₦0.00" value="{{ $filters['min_amount'] ?? '' }}" step="0.01">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-light w-100">
                            <i class="mdi mdi-filter mr-1"></i> Apply Filters
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ route('accounting.reports.aged-payables') }}" class="btn btn-outline-light w-100">
                            <i class="mdi mdi-refresh mr-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary Cards --}}
    @php
        $totals = $report['totals'] ?? [];
        $summary = $report['summary'] ?? [];
        $categories = $report['categories'] ?? [];
        $priorities = $report['priorities'] ?? [];
    @endphp

    {{-- Grand Total & Aging Summary --}}
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card card-modern h-100 border-left-danger">
                <div class="card-body py-3 text-center">
                    <div class="stat-label">Total Payables</div>
                    <div class="stat-value text-danger">₦{{ number_format($report['total'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card card-modern h-100 border-left-success">
                <div class="card-body py-3 text-center">
                    <div class="stat-label">Current</div>
                    <div class="stat-value text-success">₦{{ number_format($totals['current'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card card-modern h-100 border-left-info">
                <div class="card-body py-3 text-center">
                    <div class="stat-label">1-30 Days</div>
                    <div class="stat-value text-info">₦{{ number_format($totals['1_30'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card card-modern h-100 border-left-warning">
                <div class="card-body py-3 text-center">
                    <div class="stat-label">31-60 Days</div>
                    <div class="stat-value text-warning">₦{{ number_format($totals['31_60'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card card-modern h-100 border-left-danger">
                <div class="card-body py-3 text-center">
                    <div class="stat-label">61-90 Days</div>
                    <div class="stat-value text-danger">₦{{ number_format($totals['61_90'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card card-modern h-100 border-left-dark">
                <div class="card-body py-3 text-center">
                    <div class="stat-label">Over 90 Days</div>
                    <div class="stat-value text-dark">₦{{ number_format($totals['over_90'] ?? 0, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Category Summary Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="category-card border-left-orange">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6><i class="mdi mdi-truck mr-1"></i> Supplier POs</h6>
                        <small class="text-muted">{{ $categories['supplier_payables']['count'] ?? 0 }} suppliers with outstanding POs</small>
                    </div>
                    <div class="text-right">
                        <h5 class="mb-0" style="color: #fd7e14;">₦{{ number_format($summary['supplier_payables'] ?? 0, 2) }}</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="category-card border-left-teal">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6><i class="mdi mdi-account-cash mr-1"></i> Patient Deposits</h6>
                        <small class="text-muted">{{ $categories['patient_deposits']['count'] ?? 0 }} patients with deposits</small>
                    </div>
                    <div class="text-right">
                        <h5 class="mb-0" style="color: #20c997;">₦{{ number_format($summary['patient_deposits'] ?? 0, 2) }}</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="category-card border-left-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6><i class="mdi mdi-credit-card mr-1"></i> Supplier Credits</h6>
                        <small class="text-muted">{{ $categories['supplier_credits']['count'] ?? 0 }} suppliers with credit</small>
                    </div>
                    <div class="text-right">
                        <h5 class="mb-0 text-warning">₦{{ number_format($summary['supplier_credits'] ?? 0, 2) }}</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="category-card border-left-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6><i class="mdi mdi-book-open-page-variant mr-1"></i> GL Payables</h6>
                        <small class="text-muted">{{ $categories['gl_payables']['count'] ?? 0 }} accounts</small>
                    </div>
                    <div class="text-right">
                        <h5 class="mb-0 text-info">₦{{ number_format($summary['gl_payables'] ?? 0, 2) }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Payment Priority Alert --}}
    @if(count($priorities) > 0)
    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
        <i class="mdi mdi-alert-circle mdi-24px mr-3"></i>
        <div>
            <strong>Payment Priority Alert:</strong> You have <strong>{{ count($priorities) }}</strong> outstanding payments that require attention.
            The oldest or largest amounts should be prioritized.
        </div>
    </div>
    @endif

    {{-- Tabbed Content --}}
    <div class="card card-modern">
        <div class="card-header p-0 border-0">
            <ul class="nav nav-tabs" id="payablesTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="supplier-tab" data-bs-toggle="tab" href="#supplierPayables" role="tab">
                        <i class="mdi mdi-truck mr-1"></i> Supplier POs
                        <span class="badge bg-warning ms-1" style="background: #fd7e14 !important;">{{ $categories['supplier_payables']['count'] ?? 0 }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="deposit-tab" data-bs-toggle="tab" href="#patientDeposits" role="tab">
                        <i class="mdi mdi-account-cash mr-1"></i> Patient Deposits
                        <span class="badge bg-teal ms-1" style="background: #20c997 !important;">{{ $categories['patient_deposits']['count'] ?? 0 }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="credit-tab" data-bs-toggle="tab" href="#supplierCredits" role="tab">
                        <i class="mdi mdi-credit-card mr-1"></i> Supplier Credits
                        <span class="badge bg-warning ms-1">{{ $categories['supplier_credits']['count'] ?? 0 }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="gl-tab" data-bs-toggle="tab" href="#glPayables" role="tab">
                        <i class="mdi mdi-book-open-page-variant mr-1"></i> GL Accounts
                        <span class="badge bg-info ms-1">{{ $categories['gl_payables']['count'] ?? 0 }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="priority-tab" data-bs-toggle="tab" href="#paymentPriority" role="tab">
                        <i class="mdi mdi-alert-circle mr-1 text-danger"></i> Payment Priority
                        <span class="badge bg-danger ms-1">{{ count($priorities) }}</span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="payablesTabContent">
                {{-- Supplier POs Tab --}}
                <div class="tab-pane fade show active" id="supplierPayables" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="text-muted mb-0">
                            <i class="mdi mdi-information-outline mr-1"></i>
                            {{ $categories['supplier_payables']['description'] ?? 'Purchase orders received but not fully paid' }}
                        </p>
                        <span class="badge bg-light text-dark">Total: ₦{{ number_format($categories['supplier_payables']['total'] ?? 0, 2) }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="supplierTable">
                            <thead class="table-light">
                                <tr>
                                    <th></th>
                                    <th>Supplier Name</th>
                                    <th>Contact</th>
                                    <th>PO Count</th>
                                    <th>Aging</th>
                                    <th class="text-right">Outstanding Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories['supplier_payables']['details'] ?? [] as $supplierItem)
                                <tr class="expandable-row" data-bs-toggle="collapse" data-bs-target="#supplier-pos-{{ $supplierItem['supplier_id'] }}">
                                    <td width="30">
                                        <i class="mdi mdi-chevron-right expand-icon"></i>
                                    </td>
                                    <td>
                                        <strong><i class="mdi mdi-truck mr-1"></i> {{ $supplierItem['supplier_name'] }}</strong>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $supplierItem['contact_person'] ?? '-' }}<br>
                                            <i class="mdi mdi-phone"></i> {{ $supplierItem['phone'] ?? '-' }}
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $supplierItem['po_count'] }} POs</span>
                                    </td>
                                    <td>
                                        @php
                                            $agingClass = match($supplierItem['aging_bucket'] ?? 'current') {
                                                'current' => 'aging-current',
                                                '1_30' => 'aging-1-30',
                                                '31_60' => 'aging-31-60',
                                                '61_90' => 'aging-61-90',
                                                'over_90' => 'aging-over-90',
                                                default => 'aging-current'
                                            };
                                            $agingLabel = match($supplierItem['aging_bucket'] ?? 'current') {
                                                'current' => 'Current',
                                                '1_30' => '1-30 Days',
                                                '31_60' => '31-60 Days',
                                                '61_90' => '61-90 Days',
                                                'over_90' => '90+ Days',
                                                default => 'Current'
                                            };
                                        @endphp
                                        <span class="aging-badge {{ $agingClass }}">{{ $agingLabel }}</span>
                                    </td>
                                    <td class="text-right">
                                        <strong style="color: #fd7e14;">₦{{ number_format($supplierItem['outstanding_amount'], 2) }}</strong>
                                    </td>
                                </tr>
                                <tr class="collapse" id="supplier-pos-{{ $supplierItem['supplier_id'] }}">
                                    <td colspan="6" class="p-0">
                                        <table class="table table-sm mb-0 bg-light">
                                            <thead>
                                                <tr>
                                                    <th>PO Number</th>
                                                    <th>PO Date</th>
                                                    <th>Expected Date</th>
                                                    <th>Status</th>
                                                    <th class="text-right">Total Amount</th>
                                                    <th class="text-right">Paid</th>
                                                    <th class="text-right">Outstanding</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($supplierItem['purchase_orders'] ?? [] as $po)
                                                <tr class="detail-row">
                                                    <td><code>{{ $po['po_number'] }}</code></td>
                                                    <td>{{ $po['po_date'] }}</td>
                                                    <td>{{ $po['expected_date'] ?? '-' }}</td>
                                                    <td>
                                                        @php
                                                            $statusClass = $po['payment_status'] == 'unpaid' ? 'po-unpaid' : 'po-partial';
                                                        @endphp
                                                        <span class="po-status-badge {{ $statusClass }}">{{ ucfirst($po['payment_status']) }}</span>
                                                    </td>
                                                    <td class="text-right">₦{{ number_format($po['total_amount'], 2) }}</td>
                                                    <td class="text-right text-success">₦{{ number_format($po['amount_paid'], 2) }}</td>
                                                    <td class="text-right text-danger">₦{{ number_format($po['outstanding'], 2) }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="mdi mdi-check-circle mdi-48px text-success"></i>
                                        <p class="mb-0 mt-2">No outstanding supplier POs found</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Patient Deposits Tab --}}
                <div class="tab-pane fade" id="patientDeposits" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="text-muted mb-0">
                            <i class="mdi mdi-information-outline mr-1"></i>
                            {{ $categories['patient_deposits']['description'] ?? 'Unused patient deposits (hospital liability)' }}
                        </p>
                        <span class="badge bg-light text-dark">Total: ₦{{ number_format($categories['patient_deposits']['total'] ?? 0, 2) }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="depositTable">
                            <thead class="table-light">
                                <tr>
                                    <th>File No.</th>
                                    <th>Patient Name</th>
                                    <th>Phone</th>
                                    <th>HMO</th>
                                    <th>Last Activity</th>
                                    <th>Aging</th>
                                    <th class="text-right">Deposit Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories['patient_deposits']['details'] ?? [] as $item)
                                <tr>
                                    <td><code>{{ $item['patient_file_no'] }}</code></td>
                                    <td>
                                        <strong>{{ $item['patient_name'] }}</strong>
                                    </td>
                                    <td>{{ $item['patient_phone'] }}</td>
                                    <td><span class="badge bg-light text-dark">{{ $item['hmo_name'] }}</span></td>
                                    <td>{{ $item['last_activity'] }}</td>
                                    <td>
                                        @php
                                            $agingClass = match($item['aging_bucket']) {
                                                'current' => 'aging-current',
                                                '1_30' => 'aging-1-30',
                                                '31_60' => 'aging-31-60',
                                                '61_90' => 'aging-61-90',
                                                'over_90' => 'aging-over-90',
                                                default => 'aging-current'
                                            };
                                            $agingLabel = match($item['aging_bucket']) {
                                                'current' => 'Current',
                                                '1_30' => '1-30 Days',
                                                '31_60' => '31-60 Days',
                                                '61_90' => '61-90 Days',
                                                'over_90' => '90+ Days',
                                                default => 'Current'
                                            };
                                        @endphp
                                        <span class="aging-badge {{ $agingClass }}">{{ $agingLabel }}</span>
                                    </td>
                                    <td class="text-right">
                                        <strong style="color: #20c997;">₦{{ number_format($item['amount'], 2) }}</strong>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="mdi mdi-check-circle mdi-48px text-success"></i>
                                        <p class="mb-0 mt-2">No patient deposits found</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Supplier Credits Tab --}}
                <div class="tab-pane fade" id="supplierCredits" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="text-muted mb-0">
                            <i class="mdi mdi-information-outline mr-1"></i>
                            {{ $categories['supplier_credits']['description'] ?? 'Credit balances owed to suppliers' }}
                        </p>
                        <span class="badge bg-light text-dark">Total: ₦{{ number_format($categories['supplier_credits']['total'] ?? 0, 2) }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="creditTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Supplier Name</th>
                                    <th>Contact Person</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th class="text-right">Credit Limit</th>
                                    <th class="text-right">Credit Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories['supplier_credits']['details'] ?? [] as $item)
                                <tr>
                                    <td><strong>{{ $item['supplier_name'] }}</strong></td>
                                    <td>{{ $item['contact_person'] ?? '-' }}</td>
                                    <td>{{ $item['phone'] ?? '-' }}</td>
                                    <td>{{ $item['email'] ?? '-' }}</td>
                                    <td class="text-right">₦{{ number_format($item['credit_limit'], 2) }}</td>
                                    <td class="text-right">
                                        <strong class="text-warning">₦{{ number_format($item['credit_amount'], 2) }}</strong>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="mdi mdi-check-circle mdi-48px text-success"></i>
                                        <p class="mb-0 mt-2">No supplier credits found</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- GL Payables Tab --}}
                <div class="tab-pane fade" id="glPayables" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="text-muted mb-0">
                            <i class="mdi mdi-information-outline mr-1"></i>
                            {{ $categories['gl_payables']['description'] ?? 'General ledger payables accounts' }}
                        </p>
                        <span class="badge bg-light text-dark">Total: ₦{{ number_format($categories['gl_payables']['total'] ?? 0, 2) }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="glTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Account Code</th>
                                    <th>Account Name</th>
                                    <th class="text-right">Balance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories['gl_payables']['details'] ?? [] as $item)
                                <tr>
                                    <td><code>{{ $item['account_code'] }}</code></td>
                                    <td>{{ $item['account_name'] }}</td>
                                    <td class="text-right">
                                        <strong class="text-info">₦{{ number_format($item['balance'], 2) }}</strong>
                                    </td>
                                    <td>
                                        <a href="{{ route('accounting.reports.account-activity', ['account_id' => $item['account_id']]) }}" class="btn btn-sm btn-outline-info" title="View Account Activity">
                                            <i class="mdi mdi-file-document-outline"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="mdi mdi-check-circle mdi-48px text-success"></i>
                                        <p class="mb-0 mt-2">No GL payables found</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Payment Priority Tab --}}
                <div class="tab-pane fade" id="paymentPriority" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="text-muted mb-0">
                            <i class="mdi mdi-alert-circle text-danger mr-1"></i>
                            Priority list based on aging and amount. Pay the top items first!
                        </p>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="priorityTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Type</th>
                                    <th>Vendor/Reference</th>
                                    <th>Reference No.</th>
                                    <th>Date</th>
                                    <th>Days Overdue</th>
                                    <th class="text-right">Amount</th>
                                    <th>Priority</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($priorities as $index => $priority)
                                @php
                                    $priorityClass = $priority['days_overdue'] >= 60 ? 'priority-high' : ($priority['days_overdue'] >= 30 ? 'priority-medium' : 'priority-low');
                                @endphp
                                <tr class="{{ $priorityClass }}">
                                    <td><strong>{{ $index + 1 }}</strong></td>
                                    <td>
                                        @if($priority['type'] == 'supplier_po')
                                        <span class="badge bg-warning text-dark">Supplier PO</span>
                                        @else
                                        <span class="badge bg-secondary">{{ ucfirst($priority['type']) }}</span>
                                        @endif
                                    </td>
                                    <td><strong>{{ $priority['vendor_name'] }}</strong></td>
                                    <td><code>{{ $priority['reference'] }}</code></td>
                                    <td>{{ $priority['date'] }}</td>
                                    <td>
                                        <span class="aging-badge {{ $priority['days_overdue'] >= 60 ? 'aging-over-90' : ($priority['days_overdue'] >= 30 ? 'aging-31-60' : 'aging-1-30') }}">
                                            {{ $priority['days_overdue'] }}+ days
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <strong class="text-danger">₦{{ number_format($priority['amount'], 2) }}</strong>
                                    </td>
                                    <td>
                                        @if($priority['days_overdue'] >= 60)
                                        <span class="badge bg-danger">HIGH</span>
                                        @elseif($priority['days_overdue'] >= 30)
                                        <span class="badge bg-warning text-dark">MEDIUM</span>
                                        @else
                                        <span class="badge bg-success">LOW</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="mdi mdi-check-circle mdi-48px text-success"></i>
                                        <p class="mb-0 mt-2">No priority payments found. All payables are current!</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(document).ready(function() {
    // Initialize DataTables only if there's actual data (not just the empty state row)
    var tableConfigs = {
        '#depositTable': { orderCol: 6, emptyMsg: 'No patient deposits found' },
        '#creditTable': { orderCol: 5, emptyMsg: 'No supplier credits found' },
        '#glTable': { orderCol: 2, emptyMsg: 'No GL payables found' },
        '#priorityTable': { orderCol: 6, emptyMsg: 'No priority payments found' }
    };

    Object.keys(tableConfigs).forEach(function(tableId) {
        var config = tableConfigs[tableId];
        var rows = $(tableId + ' tbody tr:not(:has(.text-center))');
        if (rows.length > 0) {
            $(tableId).DataTable({
                dom: 'Bfrtip',
                pageLength: 25,
                order: [[config.orderCol, 'desc']],
                language: {
                    emptyTable: config.emptyMsg
                }
            });
        }
    });

    // Toggle expand icon on row click
    $('.expandable-row').on('click', function() {
        var $icon = $(this).find('.expand-icon');
        $icon.toggleClass('mdi-chevron-right mdi-chevron-down');
    });
});
</script>
@endpush
