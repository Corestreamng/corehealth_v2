@extends('accounting.reports.pdf.layout')

@section('title', 'Profit & Loss Statement')
@section('report_title', 'Profit & Loss Statement')
@section('report_subtitle', 'For the period ' . $startDate->format('F d, Y') . ' to ' . $endDate->format('F d, Y'))

@section('styles')
<style>
    .section { margin-bottom: 25px; }
    .section-title {
        font-size: 14px;
        font-weight: bold;
        border-bottom: 1px solid #999;
        padding-bottom: 5px;
        margin-bottom: 10px;
    }
    .revenue { color: #28a745; }
    .expense { color: #dc3545; }
    .cost { color: #ffc107; }
    .indent { padding-left: 20px; }
    .subtotal-row {
        font-weight: bold;
        border-top: 1px solid #ddd;
        padding-top: 8px;
    }
    .total-box {
        margin-top: 20px;
        padding: 15px;
        background-color: #333;
        color: white;
    }
    .total-box table { color: white; }
    .total-box .label { font-size: 14px; font-weight: bold; }
    .total-box .amount { font-size: 18px; font-weight: bold; }
    .gross-profit-box {
        margin: 20px 0;
        padding: 10px;
        background-color: #e3f2fd;
        border-left: 4px solid #2196f3;
    }
</style>
@endsection

@section('content')
@php
    // Data structure from ReportService:
    // $report['income']['groups'] = [{name, accounts: [{id, code, name, balance}], total}]
    // $report['income']['total'] = total income
    // $report['expenses']['groups'] = same structure
    // $report['total_income'], $report['total_expenses'], $report['net_income']

    $incomeGroups = $report['income']['groups'] ?? [];
    $incomeTotal = $report['total_income'] ?? $report['income']['total'] ?? 0;
    $expenseGroups = $report['expenses']['groups'] ?? [];
    $expenseTotal = $report['total_expenses'] ?? $report['expenses']['total'] ?? 0;
    $netIncome = $report['net_income'] ?? ($incomeTotal - $expenseTotal);
@endphp

<!-- Income/Revenue Section -->
<div class="section">
    <div class="section-title revenue">INCOME / REVENUE</div>
    <table>
        <tbody>
            @php $totalIncome = 0; @endphp
            @if(is_array($incomeGroups) && count($incomeGroups) > 0)
                @foreach($incomeGroups as $group)
                    <tr>
                        <td colspan="2" style="font-weight: bold; padding-top: 10px;">{{ $group['name'] ?? 'Income' }}</td>
                    </tr>
                    @foreach($group['accounts'] ?? [] as $account)
                        <tr>
                            <td class="indent">{{ $account['code'] ?? '' }} - {{ $account['name'] ?? '' }}</td>
                            <td class="text-right" style="width: 120px;">₦{{ number_format($account['balance'] ?? 0, 2) }}</td>
                        </tr>
                        @php $totalIncome += ($account['balance'] ?? 0); @endphp
                    @endforeach
                    <tr style="font-style: italic; color: #666;">
                        <td class="indent">Subtotal: {{ $group['name'] ?? '' }}</td>
                        <td class="text-right" style="width: 120px;">₦{{ number_format($group['total'] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="2" class="text-center" style="color: #999;">No income recorded for this period</td>
                </tr>
            @endif
            <tr class="subtotal-row">
                <td><strong>Total Income</strong></td>
                <td class="text-right"><strong>₦{{ number_format($incomeTotal ?: $totalIncome, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Expenses Section -->
<div class="section">
    <div class="section-title expense">OPERATING EXPENSES</div>
    <table>
        <tbody>
            @php $totalExpenses = 0; @endphp
            @if(is_array($expenseGroups) && count($expenseGroups) > 0)
                @foreach($expenseGroups as $group)
                    <tr>
                        <td colspan="2" style="font-weight: bold; padding-top: 10px;">{{ $group['name'] ?? 'Expenses' }}</td>
                    </tr>
                    @foreach($group['accounts'] ?? [] as $account)
                        <tr>
                            <td class="indent">{{ $account['code'] ?? '' }} - {{ $account['name'] ?? '' }}</td>
                            <td class="text-right" style="width: 120px;">₦{{ number_format($account['balance'] ?? 0, 2) }}</td>
                        </tr>
                        @php $totalExpenses += ($account['balance'] ?? 0); @endphp
                    @endforeach
                    <tr style="font-style: italic; color: #666;">
                        <td class="indent">Subtotal: {{ $group['name'] ?? '' }}</td>
                        <td class="text-right" style="width: 120px;">₦{{ number_format($group['total'] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="2" class="text-center" style="color: #999;">No expenses recorded for this period</td>
                </tr>
            @endif
            <tr class="subtotal-row">
                <td><strong>Total Expenses</strong></td>
                <td class="text-right"><strong>₦{{ number_format($expenseTotal ?: $totalExpenses, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Net Income -->
<div class="total-box">
    <table>
        <tr>
            <td class="label">NET INCOME (LOSS)</td>
            <td class="text-right amount" style="width: 150px; color: {{ $netIncome >= 0 ? '#28a745' : '#dc3545' }};">
                ₦{{ number_format($netIncome, 2) }}
            </td>
        </tr>
    </table>
</div>
@endsection
