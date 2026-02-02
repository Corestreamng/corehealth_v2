@extends('accounting.reports.pdf.layout')

@section('title', 'Cost Centers Report')
@section('report_title', 'Cost Centers Report')
@section('report_subtitle', 'As of ' . now()->format('F d, Y'))

@section('styles')
<style>
    .summary-grid {
        display: table;
        width: 100%;
        margin-bottom: 15px;
    }
    .summary-cell {
        display: table-cell;
        text-align: center;
        padding: 10px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }
    .summary-cell.primary { background: #007bff; color: white; }
    .summary-cell.success { background: #28a745; color: white; }
    .summary-cell.info { background: #17a2b8; color: white; }
    .summary-cell.warning { background: #ffc107; color: #212529; }
    .summary-label { font-size: 9px; text-transform: uppercase; }
    .summary-value { font-size: 14px; font-weight: bold; }

    .status-badge {
        font-size: 8px;
        padding: 2px 6px;
        border-radius: 3px;
        font-weight: bold;
    }
    .status-active { background: #d4edda; color: #155724; }
    .status-inactive { background: #e2e3e5; color: #383d41; }

    .positive { color: #28a745; }
    .negative { color: #dc3545; }
</style>
@endsection

@section('content')
    @if(isset($stats))
    <div class="summary-grid">
        <div class="summary-cell primary">
            <div class="summary-label">Total Cost Centers</div>
            <div class="summary-value">{{ $stats['total'] ?? 0 }}</div>
        </div>
        <div class="summary-cell success">
            <div class="summary-label">Active</div>
            <div class="summary-value">{{ $stats['active'] ?? 0 }}</div>
        </div>
        <div class="summary-cell info">
            <div class="summary-label">Total Budget</div>
            <div class="summary-value">₦{{ number_format($stats['total_budget'] ?? 0, 0) }}</div>
        </div>
        <div class="summary-cell warning">
            <div class="summary-label">Total Actual</div>
            <div class="summary-value">₦{{ number_format($stats['total_actual'] ?? 0, 0) }}</div>
        </div>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Type</th>
                <th>Department</th>
                <th>Manager</th>
                <th class="text-right">Budget</th>
                <th class="text-right">Actual</th>
                <th class="text-right">Variance</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($costCenters as $cc)
            <tr>
                <td>{{ $cc->code }}</td>
                <td>{{ $cc->name }}</td>
                <td>{{ ucfirst($cc->type ?? 'general') }}</td>
                <td>{{ $cc->department->name ?? '-' }}</td>
                <td>{{ $cc->manager->name ?? '-' }}</td>
                <td class="text-right">₦{{ number_format($cc->budget_amount ?? 0, 2) }}</td>
                <td class="text-right">₦{{ number_format($cc->actual_amount ?? 0, 2) }}</td>
                <td class="text-right">
                    @php
                        $variance = ($cc->budget_amount ?? 0) - ($cc->actual_amount ?? 0);
                        $varianceClass = $variance >= 0 ? 'positive' : 'negative';
                    @endphp
                    <span class="{{ $varianceClass }}">₦{{ number_format(abs($variance), 2) }}</span>
                </td>
                <td class="text-center">
                    @php
                        $isActive = $cc->is_active ?? true;
                    @endphp
                    <span class="status-badge {{ $isActive ? 'status-active' : 'status-inactive' }}">
                        {{ $isActive ? 'Active' : 'Inactive' }}
                    </span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center">No cost centers found</td>
            </tr>
            @endforelse
        </tbody>
        @if($costCenters->count() > 0)
        <tfoot>
            <tr class="total-row">
                <th colspan="5">Totals ({{ $costCenters->where('is_active', true)->count() }} active)</th>
                <th class="text-right">₦{{ number_format($costCenters->sum('budget_amount'), 2) }}</th>
                <th class="text-right">₦{{ number_format($costCenters->sum('actual_amount'), 2) }}</th>
                <th class="text-right">
                    @php
                        $totalVariance = $costCenters->sum('budget_amount') - $costCenters->sum('actual_amount');
                    @endphp
                    <span class="{{ $totalVariance >= 0 ? 'positive' : 'negative' }}">₦{{ number_format(abs($totalVariance), 2) }}</span>
                </th>
                <th></th>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
