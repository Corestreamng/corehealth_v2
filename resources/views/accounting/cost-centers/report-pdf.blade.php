@extends('accounting.reports.pdf.layout')

@section('title', 'Cost Center Report - ' . $costCenter->code)
@section('report_title', 'Cost Center Report')
@section('report_subtitle', $costCenter->code . ' - ' . $costCenter->name . ' | Period: ' . \Carbon\Carbon::parse($fromDate)->format('M d, Y') . ' to ' . \Carbon\Carbon::parse($toDate)->format('M d, Y'))

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
        border: 1px solid #dee2e6;
        width: 25%;
    }
    .summary-cell.revenue { background: #d4edda; }
    .summary-cell.expense { background: #f8d7da; }
    .summary-cell.net { background: #d1ecf1; }
    .summary-cell.transactions { background: #f8f9fa; }
    .summary-label { font-size: 9px; text-transform: uppercase; margin-bottom: 5px; }
    .summary-value { font-size: 14px; font-weight: bold; }

    .section-title {
        font-size: 11px;
        font-weight: bold;
        margin-top: 15px;
        margin-bottom: 8px;
        padding-bottom: 4px;
        border-bottom: 2px solid #333;
    }

    .account-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }
    .account-table th {
        background: #f8f9fa;
        padding: 6px;
        border: 1px solid #dee2e6;
        font-size: 9px;
        text-align: left;
    }
    .account-table td {
        padding: 5px 6px;
        border: 1px solid #dee2e6;
        font-size: 9px;
    }
    .text-right { text-align: right; }
    .text-danger { color: #dc3545; }
    .text-success { color: #28a745; }
</style>
@endsection

@section('content')
<!-- Summary Stats -->
<div class="summary-grid">
    <div class="summary-cell revenue">
        <div class="summary-label">Total Revenue</div>
        <div class="summary-value text-success">₦{{ number_format($summary['total_revenue'], 2) }}</div>
    </div>
    <div class="summary-cell expense">
        <div class="summary-label">Total Expenses</div>
        <div class="summary-value text-danger">₦{{ number_format($summary['total_expenses'], 2) }}</div>
    </div>
    <div class="summary-cell net">
        @php
            $netAmount = $summary['total_revenue'] - $summary['total_expenses'];
        @endphp
        <div class="summary-label">{{ $netAmount >= 0 ? 'Net Surplus' : 'Net Deficit' }}</div>
        <div class="summary-value {{ $netAmount >= 0 ? 'text-success' : 'text-danger' }}">
            ₦{{ number_format(abs($netAmount), 2) }}
        </div>
    </div>
    <div class="summary-cell transactions">
        <div class="summary-label">Transactions</div>
        <div class="summary-value">{{ $summary['transaction_count'] }}</div>
    </div>
</div>

<!-- Budget Comparison -->
@if($budget)
<div class="section-title">Budget vs Actual</div>
<table class="account-table">
    <tr>
        <th style="width: 40%">Description</th>
        <th class="text-right" style="width: 30%">Amount</th>
        <th class="text-right" style="width: 30%">Utilization</th>
    </tr>
    @php
        $budgetAmount = $budget->budgeted_amount;
        $budgetUsed = $summary['total_expenses'];
        $utilization = $budgetAmount > 0 ? ($budgetUsed / $budgetAmount) * 100 : 0;
    @endphp
    <tr>
        <td>Budget Amount</td>
        <td class="text-right">₦{{ number_format($budgetAmount, 2) }}</td>
        <td class="text-right">100%</td>
    </tr>
    <tr>
        <td>Actual Spent</td>
        <td class="text-right">₦{{ number_format($budgetUsed, 2) }}</td>
        <td class="text-right">{{ number_format($utilization, 1) }}%</td>
    </tr>
    <tr>
        <td><strong>Variance</strong></td>
        <td class="text-right {{ ($budgetAmount - $budgetUsed) >= 0 ? 'text-success' : 'text-danger' }}">
            <strong>₦{{ number_format($budgetAmount - $budgetUsed, 2) }}</strong>
        </td>
        <td class="text-right">-</td>
    </tr>
</table>
@endif

<!-- Breakdown by Account -->
<div class="section-title">Breakdown by Account</div>
@if(count($expensesByAccount) > 0)
<table class="account-table">
    <thead>
        <tr>
            <th style="width: 20%">Code</th>
            <th style="width: 40%">Account Name</th>
            <th class="text-right" style="width: 20%">Debit</th>
            <th class="text-right" style="width: 20%">Credit</th>
        </tr>
    </thead>
    <tbody>
        @foreach($expensesByAccount as $item)
        <tr>
            <td>{{ $item->account_code }}</td>
            <td>{{ $item->account_name }}</td>
            <td class="text-right text-danger">
                {{ $item->total_debit > 0 ? '₦' . number_format($item->total_debit, 2) : '-' }}
            </td>
            <td class="text-right text-success">
                {{ $item->total_credit > 0 ? '₦' . number_format($item->total_credit, 2) : '-' }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<p style="text-align: center; color: #6c757d; padding: 15px;">No transactions for selected period</p>
@endif

<!-- Transaction Details -->
<div class="section-title">Transaction Details (Last 50)</div>
<table class="account-table">
    <thead>
        <tr>
            <th style="width: 12%">Date</th>
            <th style="width: 15%">JE #</th>
            <th style="width: 15%">Account</th>
            <th style="width: 33%">Description</th>
            <th class="text-right" style="width: 12.5%">Debit</th>
            <th class="text-right" style="width: 12.5%">Credit</th>
        </tr>
    </thead>
    <tbody>
        @forelse($transactions->take(50) as $txn)
        <tr>
            <td>{{ \Carbon\Carbon::parse($txn->journalEntry->entry_date)->format('M d, Y') }}</td>
            <td>{{ $txn->journalEntry->entry_number }}</td>
            <td>{{ $txn->account->code ?? 'N/A' }}</td>
            <td>{{ Str::limit($txn->description ?? $txn->journalEntry->description, 35) }}</td>
            <td class="text-right text-danger">{{ $txn->debit > 0 ? '₦' . number_format($txn->debit, 2) : '-' }}</td>
            <td class="text-right text-success">{{ $txn->credit > 0 ? '₦' . number_format($txn->credit, 2) : '-' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="6" style="text-align: center; color: #6c757d;">No transactions found</td>
        </tr>
        @endforelse
    </tbody>
</table>

@if($transactions->count() > 50)
<p style="font-size: 9px; color: #6c757d; text-align: center; margin-top: 10px;">
    Showing 50 of {{ $transactions->count() }} transactions. Export to Excel for full list.
</p>
@endif
@endsection
