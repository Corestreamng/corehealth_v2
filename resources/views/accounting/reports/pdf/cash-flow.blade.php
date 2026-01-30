@extends('accounting.reports.pdf.layout')

@section('title', 'Cash Flow Statement')
@section('report_title', 'Cash Flow Statement')
@section('report_subtitle', $startDate->format('F d, Y') . ' to ' . $endDate->format('F d, Y'))

@section('styles')
<style>
    .section { margin-bottom: 25px; }
    .section-title {
        font-size: 14px;
        font-weight: bold;
        color: #2196f3;
        border-bottom: 2px solid #2196f3;
        padding-bottom: 5px;
        margin-bottom: 10px;
    }
    .indent { padding-left: 20px; }
    .subtotal-row {
        font-weight: bold;
        border-top: 1px solid #ddd;
        padding-top: 8px;
        background-color: #f8f9fa;
    }
    .summary-box {
        margin-top: 25px;
        padding: 15px;
        background-color: #333;
        color: white;
    }
    .summary-box table { color: white; }
    .summary-row td {
        padding: 5px 0;
        border-bottom: 1px solid #555;
    }
    .highlight-row {
        background-color: #f0f7ff;
        font-weight: bold;
    }
</style>
@endsection

@section('content')
<!-- Operating Activities -->
<div class="section">
    <div class="section-title">CASH FLOWS FROM OPERATING ACTIVITIES</div>
    <table>
        <tbody>
            @foreach($report['operating_activities'] ?? [] as $item)
                <tr>
                    <td class="indent">{{ $item['name'] ?? $item['description'] ?? 'Operating Activity' }}</td>
                    <td class="text-right" style="width: 120px;">₦{{ number_format($item['amount'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            <tr class="subtotal-row">
                <td><strong>Net Cash from Operating Activities</strong></td>
                <td class="text-right"><strong>₦{{ number_format($report['net_operating'] ?? 0, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Investing Activities -->
<div class="section">
    <div class="section-title" style="color: #ff9800; border-color: #ff9800;">CASH FLOWS FROM INVESTING ACTIVITIES</div>
    <table>
        <tbody>
            @foreach($report['investing_activities'] ?? [] as $item)
                <tr>
                    <td class="indent">{{ $item['name'] ?? $item['description'] ?? 'Investing Activity' }}</td>
                    <td class="text-right" style="width: 120px;">₦{{ number_format($item['amount'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            <tr class="subtotal-row">
                <td><strong>Net Cash from Investing Activities</strong></td>
                <td class="text-right"><strong>₦{{ number_format($report['net_investing'] ?? 0, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Financing Activities -->
<div class="section">
    <div class="section-title" style="color: #9c27b0; border-color: #9c27b0;">CASH FLOWS FROM FINANCING ACTIVITIES</div>
    <table>
        <tbody>
            @foreach($report['financing_activities'] ?? [] as $item)
                <tr>
                    <td class="indent">{{ $item['name'] ?? $item['description'] ?? 'Financing Activity' }}</td>
                    <td class="text-right" style="width: 120px;">₦{{ number_format($item['amount'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
            <tr class="subtotal-row">
                <td><strong>Net Cash from Financing Activities</strong></td>
                <td class="text-right"><strong>₦{{ number_format($report['net_financing'] ?? 0, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Summary -->
<div class="summary-box">
    <table>
        <tr class="summary-row">
            <td>Net Change in Cash</td>
            <td class="text-right" style="width: 150px;">₦{{ number_format($report['net_change_in_cash'] ?? 0, 2) }}</td>
        </tr>
        <tr class="summary-row">
            <td>Beginning Cash Balance</td>
            <td class="text-right">₦{{ number_format($report['beginning_cash'] ?? 0, 2) }}</td>
        </tr>
        <tr style="font-size: 16px; font-weight: bold;">
            <td>Ending Cash Balance</td>
            <td class="text-right" style="color: {{ ($report['ending_cash'] ?? 0) >= 0 ? '#4caf50' : '#f44336' }};">
                ₦{{ number_format($report['ending_cash'] ?? 0, 2) }}
            </td>
        </tr>
    </table>
</div>

@php
    $calculated = $report['calculated_ending'] ?? 0;
    $actual = $report['ending_cash'] ?? 0;
    $isBalanced = abs($calculated - $actual) < 0.01;
@endphp

@if(!$isBalanced)
<div class="balance-check unbalanced" style="margin-top: 15px;">
    <strong>⚠ Reconciliation Difference:</strong> ₦{{ number_format(abs($calculated - $actual), 2) }}
</div>
@endif
@endsection
