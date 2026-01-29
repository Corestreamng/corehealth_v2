@extends('admin.layouts.app')

@section('page_name', 'Accounting')
@section('subpage_name', 'Aged Payables')

@section('content')
<div class="container-fluid">
    {{-- Header with Title --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Aged Payables Report</h4>
            <p class="text-muted mb-0">Outstanding amounts owed to suppliers and vendors</p>
        </div>
        <div>
            <a href="{{ route('accounting.reports.index') }}" class="btn btn-outline-secondary mr-2">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Reports
            </a>
            <a href="{{ route('accounting.reports.aged-payables', ['export' => 'pdf', 'as_of_date' => $asOfDate->format('Y-m-d')]) }}" class="btn btn-danger">
                <i class="mdi mdi-file-pdf-box mr-1"></i> Export PDF
            </a>
        </div>
    </div>

    {{-- Filters Card --}}
    <div class="card card-modern mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="mdi mdi-filter-outline mr-2"></i>Report Parameters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('accounting.reports.aged-payables') }}">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">As of Date</label>
                        <input type="date" name="as_of_date" class="form-control" value="{{ $asOfDate->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-refresh mr-1"></i> Generate Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card card-modern h-100 border-left-primary">
                <div class="card-body py-3">
                    <h6 class="text-muted small mb-1">Total Payable</h6>
                    <h4 class="mb-0 text-primary">₦{{ number_format($report['totals']['total'] ?? 0, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card card-modern h-100 border-left-success">
                <div class="card-body py-3">
                    <h6 class="text-muted small mb-1">Current (0-30)</h6>
                    <h4 class="mb-0 text-success">₦{{ number_format($report['totals']['current'] ?? 0, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card card-modern h-100 border-left-info">
                <div class="card-body py-3">
                    <h6 class="text-muted small mb-1">31-60 Days</h6>
                    <h4 class="mb-0 text-info">₦{{ number_format($report['totals']['days_31_60'] ?? 0, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card card-modern h-100 border-left-warning">
                <div class="card-body py-3">
                    <h6 class="text-muted small mb-1">61-90 Days</h6>
                    <h4 class="mb-0 text-warning">₦{{ number_format($report['totals']['days_61_90'] ?? 0, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card card-modern h-100 border-left-danger">
                <div class="card-body py-3">
                    <h6 class="text-muted small mb-1">91-120 Days</h6>
                    <h4 class="mb-0 text-danger">₦{{ number_format($report['totals']['days_91_120'] ?? 0, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 mb-3">
            <div class="card card-modern h-100 border-left-dark">
                <div class="card-body py-3">
                    <h6 class="text-muted small mb-1">Over 120 Days</h6>
                    <h4 class="mb-0 text-dark">₦{{ number_format($report['totals']['over_120'] ?? 0, 2) }}</h4>
                </div>
            </div>
        </div>
    </div>

    {{-- Report Table --}}
    <div class="card card-modern">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="mdi mdi-table mr-2"></i>Payables Detail</h5>
            <span class="text-muted">As of {{ $asOfDate->format('M d, Y') }}</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th>Vendor/Supplier</th>
                            <th>Reference</th>
                            <th class="text-right">Current</th>
                            <th class="text-right">31-60</th>
                            <th class="text-right">61-90</th>
                            <th class="text-right">91-120</th>
                            <th class="text-right">Over 120</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['items'] ?? [] as $item)
                        <tr>
                            <td>
                                <strong>{{ $item['vendor_name'] ?? 'Unknown' }}</strong>
                                @if(isset($item['vendor_type']))
                                    <br><small class="text-muted">{{ ucfirst($item['vendor_type']) }}</small>
                                @endif
                            </td>
                            <td>{{ $item['reference'] ?? '-' }}</td>
                            <td class="text-right">{{ $item['current'] > 0 ? number_format($item['current'], 2) : '-' }}</td>
                            <td class="text-right">{{ $item['days_31_60'] > 0 ? number_format($item['days_31_60'], 2) : '-' }}</td>
                            <td class="text-right">{{ $item['days_61_90'] > 0 ? number_format($item['days_61_90'], 2) : '-' }}</td>
                            <td class="text-right {{ $item['days_91_120'] > 0 ? 'text-danger' : '' }}">{{ $item['days_91_120'] > 0 ? number_format($item['days_91_120'], 2) : '-' }}</td>
                            <td class="text-right {{ $item['over_120'] > 0 ? 'text-danger font-weight-bold' : '' }}">{{ $item['over_120'] > 0 ? number_format($item['over_120'], 2) : '-' }}</td>
                            <td class="text-right font-weight-bold">{{ number_format($item['total'], 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="mdi mdi-check-circle mdi-48px text-success"></i>
                                <p class="mb-0 mt-2">No outstanding payables found</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if(count($report['items'] ?? []) > 0)
                    <tfoot class="font-weight-bold bg-light">
                        <tr>
                            <td colspan="2">Total</td>
                            <td class="text-right">{{ number_format($report['totals']['current'] ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($report['totals']['days_31_60'] ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($report['totals']['days_61_90'] ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($report['totals']['days_91_120'] ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($report['totals']['over_120'] ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($report['totals']['total'] ?? 0, 2) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Payment Priority Section --}}
    @if(isset($report['priorities']) && count($report['priorities']) > 0)
    <div class="card card-modern mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="mdi mdi-alert-circle mr-2 text-danger"></i>Payment Priority</h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">The following payables are overdue and should be prioritized for payment:</p>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Invoice</th>
                            <th>Due Date</th>
                            <th>Days Overdue</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['priorities'] as $priority)
                        <tr class="{{ $priority['days_overdue'] > 90 ? 'table-danger' : ($priority['days_overdue'] > 60 ? 'table-warning' : '') }}">
                            <td>{{ $priority['vendor_name'] }}</td>
                            <td>{{ $priority['reference'] }}</td>
                            <td>{{ $priority['due_date'] }}</td>
                            <td>
                                <span class="badge badge-{{ $priority['days_overdue'] > 90 ? 'danger' : ($priority['days_overdue'] > 60 ? 'warning' : 'info') }}">
                                    {{ $priority['days_overdue'] }} days
                                </span>
                            </td>
                            <td class="text-right font-weight-bold">₦{{ number_format($priority['amount'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('styles')
<style>
    .card-modern {
        border: none;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
        border-radius: 10px;
    }
    .card-modern .card-header {
        background-color: transparent;
        border-bottom: 1px solid #eee;
    }

    .border-left-primary { border-left: 4px solid #007bff !important; }
    .border-left-success { border-left: 4px solid #28a745 !important; }
    .border-left-info { border-left: 4px solid #17a2b8 !important; }
    .border-left-warning { border-left: 4px solid #ffc107 !important; }
    .border-left-danger { border-left: 4px solid #dc3545 !important; }
    .border-left-dark { border-left: 4px solid #343a40 !important; }
</style>
@endpush
