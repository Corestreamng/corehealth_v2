@extends('admin.layouts.app')

@section('page_name', 'Accounting')
@section('subpage_name', 'Aged Receivables')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Reports', 'url' => route('accounting.reports.index'), 'icon' => 'mdi-file-chart'],
    ['label' => 'Aged Receivables', 'url' => '#', 'icon' => 'mdi-clock-alert']
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
    .border-left-purple { border-left: 4px solid #6f42c1 !important; }

    .stat-value { font-size: 1.5rem; font-weight: 700; }
    .stat-label { font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }

    .nav-tabs .nav-link { color: #495057; font-weight: 500; border: none; padding: 0.75rem 1.25rem; }
    .nav-tabs .nav-link.active { color: #007bff; border-bottom: 3px solid #007bff; background: transparent; }
    .nav-tabs .nav-link:hover { border-bottom: 3px solid #dee2e6; }

    .category-card { background: #f8f9fa; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
    .category-card h6 { color: #495057; margin-bottom: 0.5rem; }
    .category-card .badge { font-size: 0.7rem; }

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

    .filter-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    .filter-card .form-label { color: rgba(255,255,255,0.9); font-size: 0.8rem; }
    .filter-card .form-control, .filter-card .form-select { background: rgba(255,255,255,0.95); border: none; }

    .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
</style>

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="mdi mdi-account-cash text-primary mr-2"></i>Aged Receivables Report</h4>
            <p class="text-muted mb-0">Comprehensive view of all amounts owed to the hospital</p>
        </div>
        <div>
            <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-secondary mr-2">
                <i class="mdi mdi-arrow-left mr-1"></i> Back
            </a>
            <div class="btn-group">
                <a href="{{ route('accounting.reports.aged-receivables', array_merge(request()->all(), ['export' => 'pdf'])) }}" class="btn btn-danger">
                    <i class="mdi mdi-file-pdf-box mr-1"></i> PDF
                </a>
                <a href="{{ route('accounting.reports.aged-receivables', array_merge(request()->all(), ['export' => 'excel'])) }}" class="btn btn-success">
                    <i class="mdi mdi-file-excel mr-1"></i> Excel
                </a>
            </div>
        </div>
    </div>

    {{-- Filters Card --}}
    <div class="card card-modern filter-card mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('accounting.reports.aged-receivables') }}" id="filterForm">
                <div class="row align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">As of Date</label>
                        <input type="date" name="as_of_date" class="form-control" value="{{ $asOfDate->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Receivable Type</label>
                        <select name="receivable_type" class="form-select">
                            <option value="all">All Types</option>
                            <option value="patient_overdrafts" {{ ($filters['receivable_type'] ?? '') == 'patient_overdrafts' ? 'selected' : '' }}>Patient Overdrafts</option>
                            <option value="hmo_claims" {{ ($filters['receivable_type'] ?? '') == 'hmo_claims' ? 'selected' : '' }}>HMO Claims</option>
                            <option value="gl_receivables" {{ ($filters['receivable_type'] ?? '') == 'gl_receivables' ? 'selected' : '' }}>GL Receivables</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">HMO</label>
                        <select name="hmo_id" class="form-select">
                            <option value="">All HMOs</option>
                            @foreach($hmos ?? [] as $hmo)
                            <option value="{{ $hmo->id }}" {{ ($filters['hmo_id'] ?? '') == $hmo->id ? 'selected' : '' }}>{{ $hmo->name }}</option>
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
                        <a href="{{ route('accounting.reports.aged-receivables') }}" class="btn btn-outline-light w-100">
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
    @endphp

    {{-- Grand Total & Aging Summary --}}
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card card-modern h-100 border-left-primary">
                <div class="card-body py-3 text-center">
                    <div class="stat-label">Total Receivables</div>
                    <div class="stat-value text-primary">₦{{ number_format($report['total'] ?? 0, 2) }}</div>
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
        <div class="col-md-4">
            <div class="category-card border-left-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6><i class="mdi mdi-account-alert mr-1"></i> Patient Overdrafts</h6>
                        <small class="text-muted">{{ $categories['patient_overdrafts']['count'] ?? 0 }} patients owe money</small>
                    </div>
                    <div class="text-right">
                        <h5 class="mb-0 text-danger">₦{{ number_format($summary['patient_overdrafts'] ?? 0, 2) }}</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="category-card border-left-purple">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6><i class="mdi mdi-hospital-building mr-1"></i> HMO Claims Pending</h6>
                        <small class="text-muted">{{ $categories['hmo_claims']['count'] ?? 0 }} HMOs with pending claims</small>
                    </div>
                    <div class="text-right">
                        <h5 class="mb-0" style="color: #6f42c1;">₦{{ number_format($summary['hmo_claims'] ?? 0, 2) }}</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="category-card border-left-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6><i class="mdi mdi-book-open-page-variant mr-1"></i> GL Receivables</h6>
                        <small class="text-muted">{{ $categories['gl_receivables']['count'] ?? 0 }} accounts</small>
                    </div>
                    <div class="text-right">
                        <h5 class="mb-0 text-info">₦{{ number_format($summary['gl_receivables'] ?? 0, 2) }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabbed Content --}}
    <div class="card card-modern">
        <div class="card-header p-0 border-0">
            <ul class="nav nav-tabs" id="receivablesTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="patient-tab" data-bs-toggle="tab" href="#patientOverdrafts" role="tab">
                        <i class="mdi mdi-account-alert mr-1"></i> Patient Overdrafts
                        <span class="badge bg-danger ms-1">{{ $categories['patient_overdrafts']['count'] ?? 0 }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="hmo-tab" data-bs-toggle="tab" href="#hmoClaims" role="tab">
                        <i class="mdi mdi-hospital-building mr-1"></i> HMO Claims
                        <span class="badge bg-purple ms-1" style="background: #6f42c1;">{{ $categories['hmo_claims']['count'] ?? 0 }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="gl-tab" data-bs-toggle="tab" href="#glReceivables" role="tab">
                        <i class="mdi mdi-book-open-page-variant mr-1"></i> GL Accounts
                        <span class="badge bg-info ms-1">{{ $categories['gl_receivables']['count'] ?? 0 }}</span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="receivablesTabContent">
                {{-- Patient Overdrafts Tab --}}
                <div class="tab-pane fade show active" id="patientOverdrafts" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="text-muted mb-0">
                            <i class="mdi mdi-information-outline mr-1"></i>
                            {{ $categories['patient_overdrafts']['description'] ?? 'Patients with negative account balance' }}
                        </p>
                        <span class="badge bg-light text-dark">Total: ₦{{ number_format($categories['patient_overdrafts']['total'] ?? 0, 2) }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="patientTable">
                            <thead class="table-light">
                                <tr>
                                    <th>File No.</th>
                                    <th>Patient Name</th>
                                    <th>Phone</th>
                                    <th>HMO</th>
                                    <th>Last Activity</th>
                                    <th>Aging</th>
                                    <th class="text-right">Amount Owed</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories['patient_overdrafts']['details'] ?? [] as $item)
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
                                        <strong class="text-danger">₦{{ number_format($item['amount'], 2) }}</strong>
                                    </td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-outline-primary" title="View Patient">
                                            <i class="mdi mdi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="mdi mdi-check-circle mdi-48px text-success"></i>
                                        <p class="mb-0 mt-2">No patient overdrafts found</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- HMO Claims Tab --}}
                <div class="tab-pane fade" id="hmoClaims" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="text-muted mb-0">
                            <i class="mdi mdi-information-outline mr-1"></i>
                            {{ $categories['hmo_claims']['description'] ?? 'Validated HMO claims awaiting payment' }}
                        </p>
                        <span class="badge bg-light text-dark">Total: ₦{{ number_format($categories['hmo_claims']['total'] ?? 0, 2) }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="hmoTable">
                            <thead class="table-light">
                                <tr>
                                    <th></th>
                                    <th>HMO Name</th>
                                    <th>Claims Count</th>
                                    <th>Aging</th>
                                    <th class="text-right">Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories['hmo_claims']['details'] ?? [] as $hmoItem)
                                <tr class="expandable-row" data-bs-toggle="collapse" data-bs-target="#hmo-claims-{{ $hmoItem['hmo_id'] }}">
                                    <td width="30">
                                        <i class="mdi mdi-chevron-right expand-icon"></i>
                                    </td>
                                    <td>
                                        <strong><i class="mdi mdi-hospital-building mr-1"></i> {{ $hmoItem['hmo_name'] }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $hmoItem['claim_count'] }} claims</span>
                                    </td>
                                    <td>
                                        @php
                                            $agingClass = match($hmoItem['aging_bucket'] ?? 'current') {
                                                'current' => 'aging-current',
                                                '1_30' => 'aging-1-30',
                                                '31_60' => 'aging-31-60',
                                                '61_90' => 'aging-61-90',
                                                'over_90' => 'aging-over-90',
                                                default => 'aging-current'
                                            };
                                            $agingLabel = match($hmoItem['aging_bucket'] ?? 'current') {
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
                                        <strong style="color: #6f42c1;">₦{{ number_format($hmoItem['amount'], 2) }}</strong>
                                    </td>
                                </tr>
                                <tr class="collapse" id="hmo-claims-{{ $hmoItem['hmo_id'] }}">
                                    <td colspan="5" class="p-0">
                                        <table class="table table-sm mb-0 bg-light">
                                            <thead>
                                                <tr>
                                                    <th>Patient</th>
                                                    <th>Service/Product</th>
                                                    <th>Auth Code</th>
                                                    <th>Validated At</th>
                                                    <th class="text-right">Claim Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($hmoItem['claims'] ?? [] as $claim)
                                                <tr class="detail-row">
                                                    <td>{{ $claim['patient_name'] }}</td>
                                                    <td>{{ $claim['service_name'] }}</td>
                                                    <td><code>{{ $claim['auth_code'] }}</code></td>
                                                    <td>{{ $claim['validated_at'] }}</td>
                                                    <td class="text-right">₦{{ number_format($claim['claim_amount'], 2) }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="mdi mdi-check-circle mdi-48px text-success"></i>
                                        <p class="mb-0 mt-2">No pending HMO claims found</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- GL Receivables Tab --}}
                <div class="tab-pane fade" id="glReceivables" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="text-muted mb-0">
                            <i class="mdi mdi-information-outline mr-1"></i>
                            {{ $categories['gl_receivables']['description'] ?? 'General ledger receivables accounts' }}
                        </p>
                        <span class="badge bg-light text-dark">Total: ₦{{ number_format($categories['gl_receivables']['total'] ?? 0, 2) }}</span>
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
                                @forelse($categories['gl_receivables']['details'] ?? [] as $item)
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
                                        <p class="mb-0 mt-2">No GL receivables found</p>
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
    var patientRows = $('#patientTable tbody tr:not(:has(.text-center))');
    if (patientRows.length > 0) {
        $('#patientTable').DataTable({
            dom: 'Bfrtip',
            pageLength: 25,
            order: [[6, 'desc']], // Sort by amount descending
            columnDefs: [
                { orderable: false, targets: [7] }
            ],
            language: {
                emptyTable: "No patient overdrafts found"
            }
        });
    }

    var glRows = $('#glTable tbody tr:not(:has(.text-center))');
    if (glRows.length > 0) {
        $('#glTable').DataTable({
            dom: 'Bfrtip',
            pageLength: 25,
            order: [[2, 'desc']],
            language: {
                emptyTable: "No GL receivables found"
            }
        });
    }

    // Toggle expand icon on row click
    $('.expandable-row').on('click', function() {
        var $icon = $(this).find('.expand-icon');
        $icon.toggleClass('mdi-chevron-right mdi-chevron-down');
    });
});
</script>
@endpush
