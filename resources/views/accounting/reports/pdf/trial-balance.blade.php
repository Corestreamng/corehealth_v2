@extends('accounting.reports.pdf.layout')

@section('title', 'Trial Balance')
@section('report_title', 'Trial Balance')
@section('report_subtitle', 'As of ' . $asOfDate->format('F d, Y'))

@section('content')
<table>
    <thead>
        <tr>
            <th style="width: 15%;">Account Code</th>
            <th style="width: 45%;">Account Name</th>
            <th style="width: 20%;" class="text-right">Debit</th>
            <th style="width: 20%;" class="text-right">Credit</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report['accounts'] ?? [] as $account)
        <tr>
            <td>{{ $account['account_code'] ?? $account['code'] ?? '' }}</td>
            <td>{{ $account['account_name'] ?? $account['name'] ?? '' }}</td>
            <td class="text-right">
                @if(($account['debit'] ?? 0) > 0)
                    {{ number_format($account['debit'], 2) }}
                @else
                    -
                @endif
            </td>
            <td class="text-right">
                @if(($account['credit'] ?? 0) > 0)
                    {{ number_format($account['credit'], 2) }}
                @else
                    -
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td colspan="2"><strong>TOTAL</strong></td>
            <td class="text-right"><strong>₦{{ number_format($report['total_debit'] ?? $report['total_debits'] ?? 0, 2) }}</strong></td>
            <td class="text-right"><strong>₦{{ number_format($report['total_credit'] ?? $report['total_credits'] ?? 0, 2) }}</strong></td>
        </tr>
    </tfoot>
</table>

@php
    $totalDebit = $report['total_debit'] ?? $report['total_debits'] ?? 0;
    $totalCredit = $report['total_credit'] ?? $report['total_credits'] ?? 0;
    $isBalanced = abs($totalDebit - $totalCredit) < 0.01;
@endphp

<div class="balance-check {{ $isBalanced ? 'balanced' : 'unbalanced' }}">
    @if($isBalanced)
        <strong>✓ BALANCED</strong> - Debits equal Credits
    @else
        <strong>✗ NOT BALANCED</strong> - Difference: ₦{{ number_format(abs($totalDebit - $totalCredit), 2) }}
    @endif
</div>
@endsection
