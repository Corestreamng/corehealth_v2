@extends('accounting.reports.pdf.layout')

@section('title', 'Liabilities Register')
@section('report_title', 'Liabilities Register')
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
    .summary-cell.danger { background: #dc3545; color: white; }
    .summary-label { font-size: 9px; text-transform: uppercase; }
    .summary-value { font-size: 14px; font-weight: bold; }

    .status-badge {
        font-size: 8px;
        padding: 2px 6px;
        border-radius: 3px;
        font-weight: bold;
    }
    .status-active { background: #d4edda; color: #155724; }
    .status-paid { background: #d1ecf1; color: #0c5460; }
    .status-defaulted { background: #f8d7da; color: #721c24; }
    .status-default { background: #e2e3e5; color: #383d41; }
</style>
@endsection

@section('content')
    @if(isset($stats))
    <div class="summary-grid">
        <div class="summary-cell primary">
            <div class="summary-label">Total Liabilities</div>
            <div class="summary-value">{{ $stats['total'] ?? 0 }}</div>
        </div>
        <div class="summary-cell success">
            <div class="summary-label">Active</div>
            <div class="summary-value">{{ $stats['active'] ?? 0 }}</div>
        </div>
        <div class="summary-cell info">
            <div class="summary-label">Total Principal</div>
            <div class="summary-value">₦{{ number_format($stats['total_principal'] ?? 0, 0) }}</div>
        </div>
        <div class="summary-cell danger">
            <div class="summary-label">Current Balance</div>
            <div class="summary-value">₦{{ number_format($stats['total_balance'] ?? 0, 0) }}</div>
        </div>
        <div class="summary-cell warning">
            <div class="summary-label">Current Portion</div>
            <div class="summary-value">₦{{ number_format($stats['current_portion'] ?? 0, 0) }}</div>
        </div>
        <div class="summary-cell info">
            <div class="summary-label">Non-Current</div>
            <div class="summary-value">₦{{ number_format($stats['non_current'] ?? 0, 0) }}</div>
        </div>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Reference</th>
                <th>Creditor</th>
                <th>Type</th>
                <th class="text-right">Principal</th>
                <th class="text-right">Rate</th>
                <th class="text-right">Balance</th>
                <th class="text-right">Current</th>
                <th class="text-right">Non-Current</th>
                <th>Maturity</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($liabilities as $liability)
            <tr>
                <td>{{ $liability->reference_number ?? 'N/A' }}</td>
                <td>{{ $liability->vendor_name ?? $liability->creditor_name ?? '-' }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $liability->liability_type ?? '-')) }}</td>
                <td class="text-right">₦{{ number_format($liability->principal_amount, 2) }}</td>
                <td class="text-right">{{ number_format($liability->interest_rate ?? 0, 2) }}%</td>
                <td class="text-right">₦{{ number_format($liability->current_balance, 2) }}</td>
                <td class="text-right">₦{{ number_format($liability->current_portion ?? 0, 2) }}</td>
                <td class="text-right">₦{{ number_format($liability->non_current_portion ?? 0, 2) }}</td>
                <td>{{ $liability->maturity_date ? \Carbon\Carbon::parse($liability->maturity_date)->format('M d, Y') : '-' }}</td>
                <td class="text-center">
                    @php
                        $statusClass = match($liability->status) {
                            'active' => 'status-active',
                            'paid_off' => 'status-paid',
                            'defaulted' => 'status-defaulted',
                            default => 'status-default'
                        };
                    @endphp
                    <span class="status-badge {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $liability->status)) }}</span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="text-center">No liabilities found</td>
            </tr>
            @endforelse
        </tbody>
        @if(count($liabilities) > 0)
        <tfoot>
            <tr class="total-row">
                <th colspan="3">Totals</th>
                <th class="text-right">₦{{ number_format(collect($liabilities)->sum('principal_amount'), 2) }}</th>
                <th></th>
                <th class="text-right">₦{{ number_format(collect($liabilities)->sum('current_balance'), 2) }}</th>
                <th class="text-right">₦{{ number_format(collect($liabilities)->sum('current_portion'), 2) }}</th>
                <th class="text-right">₦{{ number_format(collect($liabilities)->sum('non_current_portion'), 2) }}</th>
                <th colspan="2"></th>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
