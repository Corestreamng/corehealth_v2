<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>General Ledger</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.3;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 16px;
            color: #333;
        }
        .header h2 {
            margin: 5px 0;
            font-size: 14px;
            font-weight: normal;
        }
        .header p {
            margin: 3px 0;
            color: #666;
            font-size: 11px;
        }
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
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 5px;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f9f9f9;
            font-weight: bold;
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .no-transactions {
            text-align: center;
            color: #999;
            padding: 15px;
            font-style: italic;
        }
        .footer {
            position: fixed;
            bottom: 15px;
            width: 100%;
            font-size: 9px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name', 'CoreHealth Hospital') }}</h1>
        <h2>General Ledger</h2>
        <p>{{ $startDate->format('F d, Y') }} to {{ $endDate->format('F d, Y') }}</p>
    </div>

    @foreach($ledgerData as $index => $accountData)
        <div class="account-section">
            <div class="account-header">
                <div class="opening-balance">
                    <small>Opening Balance</small>
                    <strong>{{ number_format($accountData['opening_balance'], 2) }}</strong>
                </div>
                <h3>{{ $accountData['account']->code }} - {{ $accountData['account']->name }}</h3>
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
                    @if(count($accountData['transactions']) > 0)
                        @php $runningBalance = $accountData['opening_balance']; @endphp
                        @foreach($accountData['transactions'] as $transaction)
                            @php
                                $runningBalance += $transaction['debit'] - $transaction['credit'];
                            @endphp
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($transaction['date'])->format('M d, Y') }}</td>
                                <td>{{ $transaction['entry_number'] }}</td>
                                <td>{{ Str::limit($transaction['description'], 35) }}</td>
                                <td>{{ $transaction['reference'] ?? '-' }}</td>
                                <td class="text-right">{{ $transaction['debit'] > 0 ? number_format($transaction['debit'], 2) : '-' }}</td>
                                <td class="text-right">{{ $transaction['credit'] > 0 ? number_format($transaction['credit'], 2) : '-' }}</td>
                                <td class="text-right">{{ number_format($runningBalance, 2) }}</td>
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
                        <td colspan="4">Totals / Closing Balance</td>
                        <td class="text-right">{{ number_format($accountData['total_debit'], 2) }}</td>
                        <td class="text-right">{{ number_format($accountData['total_credit'], 2) }}</td>
                        <td class="text-right">{{ number_format($accountData['closing_balance'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if(!$loop->last && $loop->iteration % 3 == 0)
            <div class="page-break"></div>
        @endif
    @endforeach

    <div class="footer">
        <span style="float: left;">Generated on: {{ now()->format('F d, Y H:i:s') }}</span>
        <span style="float: right;">Generated by: {{ auth()->user()->name ?? 'System' }}</span>
    </div>
</body>
</html>
