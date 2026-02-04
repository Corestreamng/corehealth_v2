@extends('accounting.reports.pdf.layout')

@section('title', 'Payment Schedule - ' . $lease->lease_number)
@section('report_title', 'Lease Payment Schedule')
@section('report_subtitle', 'Lease # ' . $lease->lease_number . ' | Generated ' . now()->format('F d, Y'))

@section('styles')
<style>
    .lease-summary-box {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 12px;
        margin-bottom: 15px;
        display: table;
        width: 100%;
    }
    .lease-summary-cell {
        display: table-cell;
        vertical-align: top;
        padding: 0 15px;
        border-right: 1px solid #dee2e6;
    }
    .lease-summary-cell:first-child { padding-left: 0; }
    .lease-summary-cell:last-child { border-right: none; padding-right: 0; }

    .info-label {
        font-size: 8px;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: 2px;
    }
    .info-value {
        font-size: 10px;
        font-weight: 600;
        color: #212529;
    }

    .ifrs-note {
        background: #e7f3ff;
        border-left: 3px solid #007bff;
        padding: 10px 12px;
        margin-bottom: 15px;
        font-size: 9px;
    }
    .ifrs-note strong {
        color: #007bff;
    }

    .totals-grid {
        display: table;
        width: 100%;
        margin-bottom: 15px;
    }
    .totals-cell {
        display: table-cell;
        text-align: center;
        padding: 10px;
        border: 1px solid #dee2e6;
    }
    .totals-cell.primary { background: #007bff; color: white; }
    .totals-cell.success { background: #28a745; color: white; }
    .totals-cell.warning { background: #ffc107; color: #212529; }
    .totals-cell.info { background: #17a2b8; color: white; }
    .totals-cell.danger { background: #dc3545; color: white; }
    .totals-label {
        font-size: 8px;
        text-transform: uppercase;
    }
    .totals-value {
        font-size: 12px;
        font-weight: bold;
        margin-top: 3px;
    }

    .schedule-table {
        margin-bottom: 15px;
        font-size: 8px;
    }
    .schedule-table th {
        background: #495057;
        color: white;
        font-size: 7px;
        padding: 5px 4px;
        text-align: center;
        text-transform: uppercase;
    }
    .schedule-table td {
        font-size: 7px;
        padding: 4px;
        text-align: center;
        border-bottom: 1px solid #dee2e6;
    }
    .schedule-table tr:nth-child(even) {
        background: #f9f9f9;
    }
    .schedule-table .paid-row {
        background: #d4edda !important;
    }
    .schedule-table .paid-row td {
        color: #155724;
    }
    .schedule-table .overdue-row {
        background: #f8d7da !important;
    }
    .schedule-table .overdue-row td {
        color: #721c24;
    }
    .schedule-table .current-row {
        background: #fff3cd !important;
        font-weight: bold;
    }

    .status-badge {
        font-size: 6px;
        padding: 1px 4px;
        border-radius: 2px;
    }
    .status-paid { background: #155724; color: white; }
    .status-overdue { background: #721c24; color: white; }
    .status-due { background: #856404; color: white; }
    .status-scheduled { background: #6c757d; color: white; }

    .year-separator {
        background: #343a40 !important;
        color: white !important;
    }
    .year-separator td {
        font-weight: bold;
        text-align: left !important;
        padding: 6px !important;
        border: none !important;
    }

    .page-break { page-break-after: always; }

    .legend-box {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        padding: 10px;
        margin-top: 15px;
        font-size: 8px;
    }
    .legend-item {
        display: inline-block;
        margin-right: 15px;
    }
    .legend-color {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 2px;
        vertical-align: middle;
        margin-right: 4px;
    }
    .legend-color.paid { background: #d4edda; }
    .legend-color.overdue { background: #f8d7da; }
    .legend-color.current { background: #fff3cd; }
    .legend-color.scheduled { background: #f9f9f9; }
</style>
@endsection

@section('content')
@php
    $totalPayments = $schedule->sum('payment_amount');
    $totalPrincipal = $schedule->sum('principal_portion');
    $totalInterest = $schedule->sum('interest_portion');
    $totalDepreciation = $schedule->sum('rou_depreciation');
    $paidPayments = $schedule->whereNotNull('payment_date');
    $paidCount = $paidPayments->count();
    $paidAmount = $paidPayments->sum('actual_payment') ?: $paidPayments->sum('payment_amount');
    $remainingPayments = $schedule->whereNull('payment_date');
    $remainingAmount = $remainingPayments->sum('payment_amount');
    $overduePayments = $remainingPayments->filter(fn($p) => \Carbon\Carbon::parse($p->due_date)->lt(now()));
    $overdueAmount = $overduePayments->sum('payment_amount');
@endphp

<!-- Lease Summary -->
<div class="lease-summary-box">
    <div class="lease-summary-cell">
        <div class="info-label">Leased Item</div>
        <div class="info-value">{{ $lease->leased_item }}</div>
    </div>
    <div class="lease-summary-cell">
        <div class="info-label">Lessor</div>
        <div class="info-value">{{ $lease->supplier_name ?? $lease->lessor_name ?? '-' }}</div>
    </div>
    <div class="lease-summary-cell">
        <div class="info-label">Lease Term</div>
        <div class="info-value">
            {{ \Carbon\Carbon::parse($lease->commencement_date)->format('M Y') }} -
            {{ \Carbon\Carbon::parse($lease->end_date)->format('M Y') }}
        </div>
    </div>
    <div class="lease-summary-cell">
        <div class="info-label">Monthly Payment</div>
        <div class="info-value">₦{{ number_format($lease->monthly_payment, 2) }}</div>
    </div>
    <div class="lease-summary-cell">
        <div class="info-label">IBR</div>
        <div class="info-value">{{ number_format($lease->incremental_borrowing_rate, 2) }}%</div>
    </div>
</div>

<!-- IFRS 16 Note -->
<div class="ifrs-note">
    <strong>IFRS 16 Amortization Schedule:</strong> This schedule shows the month-by-month breakdown of lease payments
    into principal (liability reduction) and interest (expense) components using the effective interest method.
    The ROU asset is depreciated on a straight-line basis over the lease term.
</div>

<!-- Totals Summary -->
<div class="totals-grid">
    <div class="totals-cell primary">
        <div class="totals-label">Total Payments</div>
        <div class="totals-value">{{ $schedule->count() }}</div>
    </div>
    <div class="totals-cell info">
        <div class="totals-label">Total Amount</div>
        <div class="totals-value">₦{{ number_format($totalPayments, 2) }}</div>
    </div>
    <div class="totals-cell success">
        <div class="totals-label">Total Principal</div>
        <div class="totals-value">₦{{ number_format($totalPrincipal, 2) }}</div>
    </div>
    <div class="totals-cell warning">
        <div class="totals-label">Total Interest</div>
        <div class="totals-value">₦{{ number_format($totalInterest, 2) }}</div>
    </div>
    <div class="totals-cell danger">
        <div class="totals-label">Total Depreciation</div>
        <div class="totals-value">₦{{ number_format($totalDepreciation, 2) }}</div>
    </div>
</div>

<div class="totals-grid">
    <div class="totals-cell" style="background: #d4edda;">
        <div class="totals-label">Paid Payments</div>
        <div class="totals-value" style="color: #155724;">{{ $paidCount }} (₦{{ number_format($paidAmount, 2) }})</div>
    </div>
    <div class="totals-cell" style="background: #f8f9fa;">
        <div class="totals-label">Remaining Payments</div>
        <div class="totals-value">{{ $remainingPayments->count() }} (₦{{ number_format($remainingAmount, 2) }})</div>
    </div>
    <div class="totals-cell" style="background: #f8d7da;">
        <div class="totals-label">Overdue Payments</div>
        <div class="totals-value" style="color: #721c24;">{{ $overduePayments->count() }} (₦{{ number_format($overdueAmount, 2) }})</div>
    </div>
</div>

<!-- Full Payment Schedule -->
<table class="schedule-table">
    <thead>
        <tr>
            <th style="width: 4%;">#</th>
            <th style="width: 10%;">Due Date</th>
            <th style="width: 10%;">Payment</th>
            <th style="width: 10%;">Principal</th>
            <th style="width: 10%;">Interest</th>
            <th style="width: 11%;">Open Liab.</th>
            <th style="width: 11%;">Close Liab.</th>
            <th style="width: 10%;">ROU Depr.</th>
            <th style="width: 11%;">Open ROU</th>
            <th style="width: 11%;">Close ROU</th>
            <th style="width: 7%;">Status</th>
        </tr>
    </thead>
    <tbody>
        @php $currentYear = null; @endphp
        @foreach($schedule as $payment)
        @php
            $paymentYear = \Carbon\Carbon::parse($payment->due_date)->year;
            $isPaid = !is_null($payment->payment_date);
            $isOverdue = !$isPaid && \Carbon\Carbon::parse($payment->due_date)->lt(now());
            $isCurrent = !$isPaid && \Carbon\Carbon::parse($payment->due_date)->isSameMonth(now());
            $rowClass = $isPaid ? 'paid-row' : ($isOverdue ? 'overdue-row' : ($isCurrent ? 'current-row' : ''));

            $statusClass = $isPaid ? 'status-paid' : ($isOverdue ? 'status-overdue' : ($isCurrent ? 'status-due' : 'status-scheduled'));
            $statusText = $isPaid ? 'PAID' : ($isOverdue ? 'OVERDUE' : ($isCurrent ? 'DUE' : 'SCHED'));
        @endphp

        @if($currentYear !== $paymentYear)
            @php $currentYear = $paymentYear; @endphp
            <tr class="year-separator">
                <td colspan="11">📅 YEAR {{ $paymentYear }}</td>
            </tr>
        @endif

        <tr class="{{ $rowClass }}">
            <td>{{ $payment->payment_number }}</td>
            <td>{{ \Carbon\Carbon::parse($payment->due_date)->format('M d, Y') }}</td>
            <td>₦{{ number_format($payment->payment_amount, 2) }}</td>
            <td>₦{{ number_format($payment->principal_portion, 2) }}</td>
            <td>₦{{ number_format($payment->interest_portion, 2) }}</td>
            <td>₦{{ number_format($payment->opening_liability, 2) }}</td>
            <td>₦{{ number_format($payment->closing_liability, 2) }}</td>
            <td>₦{{ number_format($payment->rou_depreciation, 2) }}</td>
            <td>₦{{ number_format($payment->opening_rou_value, 2) }}</td>
            <td>₦{{ number_format($payment->closing_rou_value, 2) }}</td>
            <td><span class="status-badge {{ $statusClass }}">{{ $statusText }}</span></td>
        </tr>
        @endforeach

        <!-- Totals Row -->
        <tr style="background: #212529; color: white; font-weight: bold;">
            <td colspan="2" style="text-align: left; padding-left: 10px;">TOTALS</td>
            <td>₦{{ number_format($totalPayments, 2) }}</td>
            <td>₦{{ number_format($totalPrincipal, 2) }}</td>
            <td>₦{{ number_format($totalInterest, 2) }}</td>
            <td>-</td>
            <td>₦0.00</td>
            <td>₦{{ number_format($totalDepreciation, 2) }}</td>
            <td>-</td>
            <td>₦0.00</td>
            <td></td>
        </tr>
    </tbody>
</table>

<!-- Legend -->
<div class="legend-box">
    <strong>Legend:</strong>
    <div class="legend-item"><span class="legend-color paid"></span> Paid</div>
    <div class="legend-item"><span class="legend-color overdue"></span> Overdue</div>
    <div class="legend-item"><span class="legend-color current"></span> Current Month Due</div>
    <div class="legend-item"><span class="legend-color scheduled"></span> Scheduled</div>
</div>

<!-- IFRS 16 Calculation Notes -->
<div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border: 1px solid #b8daff; font-size: 8px;">
    <strong>IFRS 16 Calculation Methodology:</strong><br>
    • <strong>Opening Liability:</strong> Present value of remaining lease payments at commencement<br>
    • <strong>Interest:</strong> Opening Liability × Monthly Rate (IBR/12) - recognized as expense<br>
    • <strong>Principal:</strong> Payment Amount - Interest - reduces the lease liability<br>
    • <strong>ROU Depreciation:</strong> Initial ROU Asset ÷ Lease Term (months) - straight-line method<br>
    • <strong>IBR Used:</strong> {{ number_format($lease->incremental_borrowing_rate, 2) }}% per annum ({{ number_format($lease->incremental_borrowing_rate/12, 4) }}% monthly)
</div>
@endsection
