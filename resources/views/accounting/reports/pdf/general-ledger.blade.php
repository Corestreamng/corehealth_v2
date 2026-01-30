@extends('accounting.reports.pdf.layout')

@section('title', 'General Ledger')
@section('report_title', 'General Ledger')
@section('report_subtitle', $startDate->format('F d, Y') . ' to ' . $endDate->format('F d, Y'))

@section('styles')
<style>
    body { font-size: 10px; }
    .account-section {
        margin-bottom: 25px;
        page-break-inside: avoid;
    }
    .account-header {
        background-color: #f5f5f5;
        padding: 8px;
        border: 1px solid #ddd;
        margin-bottom: 5px;
    }
    .account-header h3 {
        margin: 0;
        font-size: 12px;
    }
    .account-header .opening-balance {
        float: right;
        text-align: right;
    }
    .account-header .opening-balance small {
        display: block;
        color: #666;
    }
    .no-transactions {
        text-align: center;
        color: #999;
        padding: 15px;
        font-style: italic;
    }
    .page-break {
        page-break-after: always;
    }
</style>
@endsection

@section('content')
@foreach($ledgerData as $index => $accountData)
    <div class="account-section">
        <div class="account-header">
            <div class="opening-balance">
                <small>Opening Balance</small>
                <strong>₦{{ number_format($accountData['opening_balance'] ?? 0, 2) }}</strong>
            </div>
            <h3>{{ $accountData['account']->code ?? $accountData['account']->account_code ?? '' }} - {{ $accountData['account']->name ?? $accountData['account']->account_name ?? '' }}</h3>
            <small style="color: #666;">{{ $accountData['account']->accountGroup->name ?? '' }}</small>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 12%;">Date</th>
                    <th style="width: 12%;">Entry #</th>
                    <th style="width: 30%;">Description</th>
                    <th style="width: 12%;">Reference</th>
                    <th style="width: 12%;" class="text-right">Debit</th>
                    <th style="width: 12%;" class="text-right">Credit</th>
                    <th style="width: 12%;" class="text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                @if(count($accountData['transactions'] ?? []) > 0)
                    @php $runningBalance = $accountData['opening_balance'] ?? 0; @endphp
                    @foreach($accountData['transactions'] as $transaction)
                        @php
                            $runningBalance += ($transaction['debit'] ?? 0) - ($transaction['credit'] ?? 0);
                        @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($transaction['date'])->format('M d, Y') }}</td>
                            <td>{{ $transaction['entry_number'] ?? '-' }}</td>
                            <td>{{ Str::limit($transaction['description'] ?? '', 35) }}</td>
                            <td>{{ $transaction['reference'] ?? '-' }}</td>
                            <td class="text-right">{{ ($transaction['debit'] ?? 0) > 0 ? '₦' . number_format($transaction['debit'], 2) : '-' }}</td>
                            <td class="text-right">{{ ($transaction['credit'] ?? 0) > 0 ? '₦' . number_format($transaction['credit'], 2) : '-' }}</td>
                            <td class="text-right">₦{{ number_format($runningBalance, 2) }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="7" class="no-transactions">No transactions in this period</td>
                    </tr>
                @endif
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="4"><strong>Totals / Closing Balance</strong></td>
                    <td class="text-right"><strong>₦{{ number_format($accountData['total_debit'] ?? 0, 2) }}</strong></td>
                    <td class="text-right"><strong>₦{{ number_format($accountData['total_credit'] ?? 0, 2) }}</strong></td>
                    <td class="text-right"><strong>₦{{ number_format($accountData['closing_balance'] ?? 0, 2) }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if(!$loop->last && $loop->iteration % 3 == 0)
        <div class="page-break"></div>
    @endif
@endforeach
@endsection
