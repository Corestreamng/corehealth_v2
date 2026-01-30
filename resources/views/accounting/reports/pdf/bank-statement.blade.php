@extends('accounting.reports.pdf.layout')

@section('title', 'Bank Statement')
@section('report_title', 'Bank Statement Report')
@section('report_subtitle', $startDate->format('F d, Y') . ' to ' . $endDate->format('F d, Y'))

@section('styles')
<style>
    body { font-size: 10px; }

    .account-info-box {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        padding: 10px 15px;
        margin-bottom: 15px;
        border-radius: 4px;
    }

    .account-info-box .info-row {
        display: table;
        width: 100%;
        margin-bottom: 5px;
    }

    .account-info-box .info-row:last-child {
        margin-bottom: 0;
    }

    .account-info-box .label {
        display: table-cell;
        width: 40%;
        font-weight: bold;
        color: #495057;
    }

    .account-info-box .value {
        display: table-cell;
        width: 60%;
        color: #212529;
    }

    .summary-boxes {
        display: table;
        width: 100%;
        margin-bottom: 20px;
    }

    .summary-box {
        display: table-cell;
        width: 25%;
        padding: 10px;
        text-align: center;
        border: 1px solid #dee2e6;
        background-color: #f8f9fa;
    }

    .summary-box .label {
        font-size: 9px;
        color: #6c757d;
        text-transform: uppercase;
        margin-bottom: 5px;
        font-weight: 600;
    }

    .summary-box .amount {
        font-size: 13px;
        font-weight: bold;
        color: #212529;
    }

    .summary-box.deposits .amount {
        color: #28a745;
    }

    .summary-box.withdrawals .amount {
        color: #dc3545;
    }

    .summary-box.closing .amount {
        color: #0066cc;
    }

    .transactions-table {
        margin-top: 15px;
    }

    .transactions-table thead th {
        background-color: #e9ecef;
        font-weight: bold;
        text-align: left;
        padding: 8px 5px;
        border-bottom: 2px solid #dee2e6;
    }

    .transactions-table tbody td {
        padding: 6px 5px;
        border-bottom: 1px solid #e9ecef;
    }

    .transactions-table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .text-success {
        color: #28a745 !important;
    }

    .text-danger {
        color: #dc3545 !important;
    }

    .text-right {
        text-align: right;
    }

    .text-center {
        text-align: center;
    }

    .balance-cell {
        font-weight: 600;
    }

    .no-transactions {
        text-align: center;
        color: #999;
        padding: 30px;
        font-style: italic;
    }

    tfoot {
        border-top: 3px double #495057;
    }

    tfoot td {
        padding: 10px 5px;
        font-weight: bold;
        font-size: 11px;
    }

    .page-break {
        page-break-after: always;
    }
</style>
@endsection

@section('content')
@php
    $account = is_array($exportData['account']) ? (object) $exportData['account'] : $exportData['account'];
    $accountCode = $account->code ?? $account->account_code ?? '';
    $accountName = $account->name ?? $account->account_name ?? '';
    $accountGroup = is_object($account) && isset($account->accountGroup) ? $account->accountGroup->name : ($account->account_group_name ?? 'Bank Account');

    // Bank information
    $bank = null;
    if (is_object($account) && isset($account->bank)) {
        $bank = $account->bank;
    } elseif (isset($exportData['bank'])) {
        $bank = is_array($exportData['bank']) ? (object) $exportData['bank'] : $exportData['bank'];
    }
@endphp

{{-- Account Information Box --}}
<div class="account-info-box">
    @if($bank)
    <div class="info-row">
        <div class="label">Bank:</div>
        <div class="value"><strong>{{ $bank->name ?? '' }}</strong></div>
    </div>
    @if(isset($bank->account_number))
    <div class="info-row">
        <div class="label">Bank Account Number:</div>
        <div class="value">{{ $bank->account_number }}</div>
    </div>
    @endif
    @if(isset($bank->account_name))
    <div class="info-row">
        <div class="label">Bank Account Name:</div>
        <div class="value">{{ $bank->account_name }}</div>
    </div>
    @endif
    @if(isset($bank->bank_code))
    <div class="info-row">
        <div class="label">Bank Code:</div>
        <div class="value">{{ $bank->bank_code }}</div>
    </div>
    @endif
    <div class="info-row" style="border-bottom: 1px solid #dee2e6; margin-bottom: 8px; padding-bottom: 8px;"></div>
    @endif
    <div class="info-row">
        <div class="label">Ledger Account:</div>
        <div class="value">{{ $accountCode }} - {{ $accountName }}</div>
    </div>
    <div class="info-row">
        <div class="label">Account Type:</div>
        <div class="value">{{ $accountGroup }}</div>
    </div>
    <div class="info-row">
        <div class="label">Period:</div>
        <div class="value">{{ $startDate->format('M d, Y') }} to {{ $endDate->format('M d, Y') }}</div>
    </div>
    <div class="info-row">
        <div class="label">Report Date:</div>
        <div class="value">{{ now()->format('M d, Y h:i A') }}</div>
    </div>
</div>

{{-- Summary Boxes --}}
<div class="summary-boxes">
    <div class="summary-box opening">
        <div class="label">Opening Balance</div>
        <div class="amount">₦{{ number_format($exportData['opening_balance'], 2) }}</div>
    </div>
    <div class="summary-box deposits">
        <div class="label">Total Deposits</div>
        <div class="amount">+₦{{ number_format($exportData['total_deposits'], 2) }}</div>
    </div>
    <div class="summary-box withdrawals">
        <div class="label">Total Withdrawals</div>
        <div class="amount">-₦{{ number_format($exportData['total_withdrawals'], 2) }}</div>
    </div>
    <div class="summary-box closing">
        <div class="label">Closing Balance</div>
        <div class="amount">₦{{ number_format($exportData['closing_balance'], 2) }}</div>
    </div>
</div>

{{-- Transactions Table --}}
<table class="transactions-table">
    <thead>
        <tr>
            <th style="width: 10%;">Date</th>
            <th style="width: 10%;">Entry #</th>
            <th style="width: 32%;">Description</th>
            <th style="width: 12%;">Reference</th>
            <th style="width: 12%;" class="text-right">Deposits</th>
            <th style="width: 12%;" class="text-right">Withdrawals</th>
            <th style="width: 12%;" class="text-right">Balance</th>
        </tr>
    </thead>
    <tbody>
        @if(count($exportData['transactions']) > 0)
            @php
                $runningBalance = $exportData['opening_balance'];
            @endphp

            @foreach($exportData['transactions'] as $transaction)
                @php
                    $runningBalance += ($transaction['debit'] - $transaction['credit']);
                    $isDeposit = $transaction['debit'] > 0;
                @endphp
                <tr>
                    <td>{{ \Carbon\Carbon::parse($transaction['date'])->format('M d, Y') }}</td>
                    <td>{{ $transaction['entry_number'] ?? '-' }}</td>
                    <td>{{ Str::limit($transaction['description'] ?? '', 45) }}</td>
                    <td>{{ $transaction['reference'] ?? '-' }}</td>
                    <td class="text-right {{ $isDeposit ? 'text-success' : '' }}">
                        {{ $isDeposit ? '₦' . number_format($transaction['debit'], 2) : '-' }}
                    </td>
                    <td class="text-right {{ !$isDeposit ? 'text-danger' : '' }}">
                        {{ !$isDeposit ? '₦' . number_format($transaction['credit'], 2) : '-' }}
                    </td>
                    <td class="text-right balance-cell">₦{{ number_format($runningBalance, 2) }}</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="7" class="no-transactions">No transactions found for this period</td>
            </tr>
        @endif
    </tbody>
    @if(count($exportData['transactions']) > 0)
    <tfoot>
        <tr>
            <td colspan="4" class="text-right">Totals:</td>
            <td class="text-right text-success">₦{{ number_format($exportData['total_deposits'], 2) }}</td>
            <td class="text-right text-danger">₦{{ number_format($exportData['total_withdrawals'], 2) }}</td>
            <td class="text-right">₦{{ number_format($exportData['closing_balance'], 2) }}</td>
        </tr>
        <tr>
            <td colspan="6" class="text-right">Net Movement:</td>
            <td class="text-right" style="color: {{ ($exportData['closing_balance'] - $exportData['opening_balance']) >= 0 ? '#28a745' : '#dc3545' }};">
                {{ ($exportData['closing_balance'] - $exportData['opening_balance']) >= 0 ? '+' : '' }}₦{{ number_format($exportData['closing_balance'] - $exportData['opening_balance'], 2) }}
            </td>
        </tr>
    </tfoot>
    @endif
</table>

@if(count($exportData['transactions']) > 0)
<div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #dee2e6;">
    <p style="font-size: 9px; color: #6c757d; margin: 0;">
        <strong>Transaction Summary:</strong><br>
        This statement shows {{ count($exportData['transactions']) }} transaction(s)
        from {{ $startDate->format('M d, Y') }} to {{ $endDate->format('M d, Y') }}.<br>
        Opening Balance: ₦{{ number_format($exportData['opening_balance'], 2) }} |
        Closing Balance: ₦{{ number_format($exportData['closing_balance'], 2) }} |
        Net Change: {{ ($exportData['closing_balance'] - $exportData['opening_balance']) >= 0 ? '+' : '' }}₦{{ number_format($exportData['closing_balance'] - $exportData['opening_balance'], 2) }}
    </p>
</div>
@endif
@endsection
