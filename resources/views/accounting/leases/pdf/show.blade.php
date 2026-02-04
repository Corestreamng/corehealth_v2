@extends('accounting.reports.pdf.layout')

@section('title', 'Lease Detail - ' . $lease->lease_number)
@section('report_title', 'Lease Agreement Details')
@section('report_subtitle', 'Lease # ' . $lease->lease_number . ' | As of ' . now()->format('F d, Y'))

@section('styles')
<style>
    .lease-header {
        display: table;
        width: 100%;
        margin-bottom: 15px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }
    .lease-header-cell {
        display: table-cell;
        padding: 12px;
        vertical-align: top;
    }
    .lease-header-cell.left { width: 50%; }
    .lease-header-cell.right { width: 50%; border-left: 1px solid #dee2e6; }

    .info-label {
        font-size: 8px;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: 2px;
    }
    .info-value {
        font-size: 11px;
        font-weight: 600;
        color: #212529;
        margin-bottom: 8px;
    }

    .type-badge {
        font-size: 9px;
        padding: 3px 8px;
        border-radius: 3px;
        text-transform: uppercase;
        font-weight: bold;
    }
    .type-operating { background: #6c757d; color: white; }
    .type-finance { background: #007bff; color: white; }
    .type-short_term { background: #17a2b8; color: white; }
    .type-low_value { background: #e9ecef; color: #212529; }

    .status-badge {
        font-size: 9px;
        padding: 3px 8px;
        border-radius: 3px;
        text-transform: uppercase;
        font-weight: bold;
    }
    .status-active { background: #d4edda; color: #155724; }
    .status-draft { background: #e9ecef; color: #6c757d; }
    .status-expired { background: #343a40; color: white; }
    .status-terminated { background: #f8d7da; color: #721c24; }

    .section-title {
        background: #495057;
        color: white;
        padding: 8px 12px;
        margin: 15px 0 10px 0;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
    }

    .ifrs-box {
        background: #e7f3ff;
        border: 1px solid #b8daff;
        border-radius: 4px;
        padding: 12px;
        margin-bottom: 15px;
    }
    .ifrs-box-title {
        font-size: 10px;
        font-weight: bold;
        color: #004085;
        margin-bottom: 8px;
        text-transform: uppercase;
    }
    .ifrs-grid {
        display: table;
        width: 100%;
    }
    .ifrs-item {
        display: table-cell;
        padding: 8px;
        text-align: center;
        border-right: 1px solid #b8daff;
    }
    .ifrs-item:last-child { border-right: none; }
    .ifrs-label {
        font-size: 8px;
        color: #004085;
        text-transform: uppercase;
    }
    .ifrs-value {
        font-size: 13px;
        font-weight: bold;
        color: #004085;
        margin-top: 3px;
    }
    .ifrs-value.rou { color: #28a745; }
    .ifrs-value.liability { color: #dc3545; }
    .ifrs-value.depreciation { color: #6c757d; }

    .account-table {
        margin-bottom: 15px;
    }
    .account-table th {
        background: #f5f5f5;
        font-size: 8px;
        padding: 6px;
    }
    .account-table td {
        font-size: 9px;
        padding: 5px;
    }
    .account-table code {
        background: #e9ecef;
        padding: 2px 4px;
        border-radius: 2px;
        font-size: 8px;
    }

    .schedule-table {
        margin-bottom: 15px;
    }
    .schedule-table th {
        background: #495057;
        color: white;
        font-size: 8px;
        padding: 5px;
        text-align: center;
    }
    .schedule-table td {
        font-size: 8px;
        padding: 4px 5px;
        text-align: center;
    }
    .schedule-table tr:nth-child(even) {
        background: #f9f9f9;
    }
    .schedule-table .paid-row {
        background: #d4edda !important;
    }
    .schedule-table .overdue-row {
        background: #f8d7da !important;
    }

    .payment-summary-grid {
        display: table;
        width: 100%;
        margin-bottom: 15px;
    }
    .payment-summary-cell {
        display: table-cell;
        text-align: center;
        padding: 10px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }
    .payment-summary-cell.success { background: #d4edda; }
    .payment-summary-cell.info { background: #d1ecf1; }
    .payment-summary-cell.warning { background: #fff3cd; }

    .je-table {
        margin-bottom: 15px;
    }
    .je-table th {
        background: #f5f5f5;
        font-size: 8px;
        padding: 5px;
    }
    .je-table td {
        font-size: 8px;
        padding: 4px 5px;
    }

    .notes-box {
        background: #fff8e1;
        border: 1px solid #ffecb5;
        border-radius: 4px;
        padding: 10px;
        margin-top: 15px;
    }
    .notes-title {
        font-size: 9px;
        font-weight: bold;
        color: #856404;
        margin-bottom: 5px;
    }
    .notes-content {
        font-size: 9px;
        color: #856404;
        white-space: pre-wrap;
    }
</style>
@endsection

@section('content')
@php
    $isFinanceLease = in_array($lease->lease_type, ['operating', 'finance']);
@endphp

<!-- Lease Header Info -->
<div class="lease-header">
    <div class="lease-header-cell left">
        <div class="info-label">Leased Item</div>
        <div class="info-value">{{ $lease->leased_item }}</div>

        <div class="info-label">Lease Type</div>
        <div class="info-value">
            <span class="type-badge type-{{ $lease->lease_type }}">
                {{ ucfirst(str_replace('_', ' ', $lease->lease_type)) }}
            </span>
        </div>

        <div class="info-label">Status</div>
        <div class="info-value">
            <span class="status-badge status-{{ $lease->status }}">
                {{ ucfirst($lease->status) }}
            </span>
        </div>

        <div class="info-label">Department</div>
        <div class="info-value">{{ $lease->department_name ?? 'N/A' }}</div>
    </div>
    <div class="lease-header-cell right">
        <div class="info-label">Lessor</div>
        <div class="info-value">{{ $lease->supplier_name ?? $lease->lessor_name ?? '-' }}</div>

        <div class="info-label">Lease Term</div>
        <div class="info-value">
            {{ \Carbon\Carbon::parse($lease->commencement_date)->format('M d, Y') }} -
            {{ \Carbon\Carbon::parse($lease->end_date)->format('M d, Y') }}
            ({{ $lease->lease_term_months }} months)
        </div>

        <div class="info-label">Monthly Payment</div>
        <div class="info-value">₦{{ number_format($lease->monthly_payment, 2) }}</div>

        <div class="info-label">Incremental Borrowing Rate</div>
        <div class="info-value">{{ number_format($lease->incremental_borrowing_rate, 2) }}%</div>
    </div>
</div>

<!-- IFRS 16 Values -->
@if($isFinanceLease)
<div class="ifrs-box">
    <div class="ifrs-box-title">IFRS 16 Recognition Values</div>
    <div class="ifrs-grid">
        <div class="ifrs-item">
            <div class="ifrs-label">Initial ROU Asset</div>
            <div class="ifrs-value rou">₦{{ number_format($lease->initial_rou_asset_value, 2) }}</div>
        </div>
        <div class="ifrs-item">
            <div class="ifrs-label">Current ROU Asset</div>
            <div class="ifrs-value rou">₦{{ number_format($lease->current_rou_asset_value, 2) }}</div>
        </div>
        <div class="ifrs-item">
            <div class="ifrs-label">Initial Liability</div>
            <div class="ifrs-value liability">₦{{ number_format($lease->initial_lease_liability, 2) }}</div>
        </div>
        <div class="ifrs-item">
            <div class="ifrs-label">Current Liability</div>
            <div class="ifrs-value liability">₦{{ number_format($lease->current_lease_liability, 2) }}</div>
        </div>
        <div class="ifrs-item">
            <div class="ifrs-label">Accumulated Depreciation</div>
            <div class="ifrs-value depreciation">₦{{ number_format($lease->accumulated_rou_depreciation, 2) }}</div>
        </div>
    </div>
</div>
@endif

<!-- Account Mapping -->
<div class="section-title">ACCOUNT MAPPING</div>
<table class="account-table">
    <thead>
        <tr>
            <th style="width: 25%;">Purpose</th>
            <th style="width: 15%;">Account Code</th>
            <th style="width: 60%;">Account Name</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>ROU Asset</td>
            <td><code>{{ $lease->rou_account_code ?? '1460' }}</code></td>
            <td>{{ $lease->rou_account_name ?? 'Right-of-Use Asset' }}</td>
        </tr>
        <tr>
            <td>Lease Liability</td>
            <td><code>{{ $lease->liability_account_code ?? '2310' }}</code></td>
            <td>{{ $lease->liability_account_name ?? 'Lease Liability' }}</td>
        </tr>
        <tr>
            <td>Depreciation Expense</td>
            <td><code>{{ $lease->depreciation_account_code ?? '6260' }}</code></td>
            <td>{{ $lease->depreciation_account_name ?? 'Depreciation Expense' }}</td>
        </tr>
        <tr>
            <td>Interest Expense</td>
            <td><code>{{ $lease->interest_account_code ?? '6300' }}</code></td>
            <td>{{ $lease->interest_account_name ?? 'Interest Expense' }}</td>
        </tr>
    </tbody>
</table>

<!-- Payment Summary -->
@if(isset($paymentSummary))
<div class="section-title">PAYMENT SUMMARY</div>
<div class="payment-summary-grid">
    <div class="payment-summary-cell success">
        <div class="info-label">Payments Made</div>
        <div class="info-value">{{ $paymentSummary->paid_count ?? 0 }} / {{ $paymentSummary->total_payments ?? 0 }}</div>
    </div>
    <div class="payment-summary-cell success">
        <div class="info-label">Total Paid</div>
        <div class="info-value">₦{{ number_format($paymentSummary->total_paid ?? 0, 2) }}</div>
    </div>
    <div class="payment-summary-cell info">
        <div class="info-label">Total Scheduled</div>
        <div class="info-value">₦{{ number_format($paymentSummary->total_scheduled ?? 0, 2) }}</div>
    </div>
    <div class="payment-summary-cell warning">
        <div class="info-label">Remaining</div>
        <div class="info-value">₦{{ number_format(($paymentSummary->total_scheduled ?? 0) - ($paymentSummary->total_paid ?? 0), 2) }}</div>
    </div>
</div>
@endif

<!-- Payment Schedule (First 12) -->
@if(isset($schedule) && $schedule->count() > 0)
<div class="section-title">PAYMENT SCHEDULE @if($schedule->count() >= 12)(Showing First 12)@endif</div>
<table class="schedule-table">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 12%;">Due Date</th>
            <th style="width: 12%;">Payment</th>
            <th style="width: 12%;">Principal</th>
            <th style="width: 12%;">Interest</th>
            <th style="width: 12%;">Opening Liab.</th>
            <th style="width: 12%;">Closing Liab.</th>
            <th style="width: 12%;">ROU Depr.</th>
            <th style="width: 11%;">Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($schedule as $payment)
        @php
            $isPaid = !is_null($payment->payment_date);
            $isOverdue = !$isPaid && \Carbon\Carbon::parse($payment->due_date)->lt(now());
            $rowClass = $isPaid ? 'paid-row' : ($isOverdue ? 'overdue-row' : '');
        @endphp
        <tr class="{{ $rowClass }}">
            <td>{{ $payment->payment_number }}</td>
            <td>{{ \Carbon\Carbon::parse($payment->due_date)->format('M d, Y') }}</td>
            <td>₦{{ number_format($payment->payment_amount, 2) }}</td>
            <td>₦{{ number_format($payment->principal_portion, 2) }}</td>
            <td>₦{{ number_format($payment->interest_portion, 2) }}</td>
            <td>₦{{ number_format($payment->opening_liability, 2) }}</td>
            <td>₦{{ number_format($payment->closing_liability, 2) }}</td>
            <td>₦{{ number_format($payment->rou_depreciation, 2) }}</td>
            <td>
                @if($isPaid)
                    <span style="color: #155724;">✓ Paid</span>
                @elseif($isOverdue)
                    <span style="color: #721c24;">⚠ Overdue</span>
                @else
                    Scheduled
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<!-- Journal Entries -->
@if(isset($journalEntries) && $journalEntries->count() > 0)
<div class="section-title">RELATED JOURNAL ENTRIES</div>
<table class="je-table">
    <thead>
        <tr>
            <th style="width: 20%;">Entry #</th>
            <th style="width: 15%;">Date</th>
            <th style="width: 45%;">Description</th>
            <th style="width: 20%;" class="text-right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($journalEntries as $je)
        <tr>
            <td><code>{{ $je->entry_number }}</code></td>
            <td>{{ \Carbon\Carbon::parse($je->entry_date)->format('M d, Y') }}</td>
            <td>{{ Str::limit($je->description, 50) }}</td>
            <td class="text-right">₦{{ number_format($je->total_debit, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<!-- Notes -->
@if(!empty($lease->notes))
<div class="notes-box">
    <div class="notes-title">Notes</div>
    <div class="notes-content">{{ $lease->notes }}</div>
</div>
@endif

<!-- IFRS 16 Footer Note -->
<div style="margin-top: 20px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; font-size: 8px; color: #6c757d;">
    <strong>IFRS 16 Compliance Note:</strong> Under IFRS 16, lessees recognize a right-of-use asset and lease liability
    for all leases (with exemptions for short-term leases ≤12 months and low-value assets). The lease liability is initially
    measured at the present value of lease payments discounted using the incremental borrowing rate. The ROU asset equals
    the initial lease liability plus any initial direct costs, prepayments, and less any lease incentives received.
</div>
@endsection
