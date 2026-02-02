@extends('accounting.reports.pdf.layout')

@section('title', 'Patient Deposits Report')
@section('report_title', 'Patient Deposits Report')
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
    .status-applied { background: #d1ecf1; color: #0c5460; }
    .status-refunded { background: #fff3cd; color: #856404; }
    .status-expired { background: #f8d7da; color: #721c24; }
    .status-default { background: #e2e3e5; color: #383d41; }
</style>
@endsection

@section('content')
    @if(isset($stats))
    <div class="summary-grid">
        <div class="summary-cell primary">
            <div class="summary-label">Total Deposits</div>
            <div class="summary-value">{{ $stats['total'] ?? 0 }}</div>
        </div>
        <div class="summary-cell success">
            <div class="summary-label">Active</div>
            <div class="summary-value">{{ $stats['active'] ?? 0 }}</div>
        </div>
        <div class="summary-cell info">
            <div class="summary-label">Total Amount</div>
            <div class="summary-value">₦{{ number_format($stats['total_deposits'] ?? 0, 0) }}</div>
        </div>
        <div class="summary-cell warning">
            <div class="summary-label">Applied</div>
            <div class="summary-value">₦{{ number_format($stats['total_applied'] ?? 0, 0) }}</div>
        </div>
        <div class="summary-cell success">
            <div class="summary-label">Balance</div>
            <div class="summary-value">₦{{ number_format($stats['total_balance'] ?? 0, 0) }}</div>
        </div>
        <div class="summary-cell danger">
            <div class="summary-label">Refunded</div>
            <div class="summary-value">₦{{ number_format($stats['total_refunded'] ?? 0, 0) }}</div>
        </div>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Reference</th>
                <th>Patient</th>
                <th>Type</th>
                <th>Payment Method</th>
                <th>Date</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Applied</th>
                <th class="text-right">Balance</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($deposits as $deposit)
            <tr>
                <td>{{ $deposit->reference_number ?? 'N/A' }}</td>
                <td>{{ $deposit->patient->full_name ?? ($deposit->patient->firstname ?? '') . ' ' . ($deposit->patient->lastname ?? '') }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $deposit->deposit_type ?? 'general')) }}</td>
                <td>{{ $deposit->paymentMethod->name ?? $deposit->payment_method ?? '-' }}</td>
                <td>{{ $deposit->deposit_date ? \Carbon\Carbon::parse($deposit->deposit_date)->format('M d, Y') : '-' }}</td>
                <td class="text-right">₦{{ number_format($deposit->amount, 2) }}</td>
                <td class="text-right">₦{{ number_format($deposit->applied_amount ?? 0, 2) }}</td>
                <td class="text-right">₦{{ number_format($deposit->balance ?? ($deposit->amount - ($deposit->applied_amount ?? 0)), 2) }}</td>
                <td class="text-center">
                    @php
                        $statusClass = match($deposit->status) {
                            'active' => 'status-active',
                            'fully_applied' => 'status-applied',
                            'refunded' => 'status-refunded',
                            'expired' => 'status-expired',
                            default => 'status-default'
                        };
                    @endphp
                    <span class="status-badge {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $deposit->status)) }}</span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center">No patient deposits found</td>
            </tr>
            @endforelse
        </tbody>
        @if($deposits->count() > 0)
        <tfoot>
            <tr class="total-row">
                <th colspan="5">Totals</th>
                <th class="text-right">₦{{ number_format($deposits->sum('amount'), 2) }}</th>
                <th class="text-right">₦{{ number_format($deposits->sum('applied_amount'), 2) }}</th>
                <th class="text-right">₦{{ number_format($deposits->sum('balance'), 2) }}</th>
                <th></th>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
