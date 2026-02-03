@extends('accounting.reports.pdf.layout')

@section('title', 'Cash Flow Forecast - ' . $forecast->forecast_name)

@section('styles')
@php
    $sett = function_exists('appsettings') ? appsettings() : null;
    $hosColor = $sett->hos_color ?? config('app.hospital_color', '#0066cc');

    function adjustColor($color, $percent) {
        $color = ltrim($color, '#');
        $rgb = array_map('hexdec', str_split($color, 2));
        foreach ($rgb as &$value) {
            $value = max(0, min(255, $value + $percent));
        }
        return '#' . implode('', array_map(function($v) { return str_pad(dechex($v), 2, '0', STR_PAD_LEFT); }, $rgb));
    }
@endphp
<style>
    .forecast-header-section {
        margin-bottom: 20px;
        padding: 10px;
        background: #f8f9fa;
        border-left: 4px solid {{ $hosColor }};
    }
    .forecast-info-grid {
        display: table;
        width: 100%;
        margin-top: 8px;
    }
    .forecast-info-row {
        display: table-row;
    }
    .forecast-info-label {
        display: table-cell;
        width: 35%;
        font-weight: 600;
        padding: 4px 8px;
        color: #222;
    }
    .forecast-info-value {
        display: table-cell;
        padding: 4px 8px;
        color: #000;
    }
    .periods-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        margin-bottom: 25px;
    }
    .periods-table thead th {
        background: linear-gradient(to bottom, {{ $hosColor }}, {{ adjustColor($hosColor, -15) }});
        color: white;
        padding: 8px 6px;
        text-align: left;
        font-size: 10px;
        font-weight: 600;
        border: 1px solid {{ adjustColor($hosColor, -20) }};
    }
    .periods-table tbody td {
        padding: 7px 6px;
        border: 1px solid #ddd;
        font-size: 10px;
    }
    .periods-table tbody tr:nth-child(even) {
        background: #f9f9f9;
    }
    .periods-table tbody tr.period-current {
        background: #fffbea;
        font-weight: 600;
    }
    .amount-cell {
        text-align: right;
        font-family: 'Courier New', monospace;
        font-size: 9px;
    }
    .variance-positive {
        color: #28a745;
        font-weight: 600;
    }
    .variance-negative {
        color: #dc3545;
        font-weight: 600;
    }
    .period-items-section {
        margin-top: 25px;
        page-break-inside: avoid;
    }
    .period-header {
        background: linear-gradient(to bottom, {{ $hosColor }}, {{ adjustColor($hosColor, -15) }});
        color: white;
        padding: 8px 10px;
        font-weight: 600;
        font-size: 11px;
        margin-top: 15px;
        border-radius: 3px 3px 0 0;
    }
    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
        page-break-inside: avoid;
    }
    .items-table thead th {
        background: #e9ecef;
        padding: 6px 8px;
        text-align: left;
        font-size: 9px;
        font-weight: 600;
        border: 1px solid #ddd;
    }
    .items-table tbody td {
        padding: 5px 8px;
        border: 1px solid #ddd;
        font-size: 9px;
    }
    .items-table tbody tr.category-separator {
        background: #f1f3f5;
        font-weight: 600;
    }
    .summary-box {
        background: #f8f9fa;
        border: 2px solid #dee2e6;
        padding: 10px;
        margin-top: 10px;
        page-break-inside: avoid;
    }
    .summary-row {
        display: table;
        width: 100%;
        padding: 3px 0;
    }
    .summary-label {
        display: table-cell;
        width: 70%;
        font-weight: 600;
        font-size: 10px;
        color: #000;
    }
    .summary-value {
        display: table-cell;
        text-align: right;
        font-family: 'Courier New', monospace;
        font-size: 10px;
        color: #000;
    }
    .summary-total {
        border-top: 2px solid {{ $hosColor }};
        padding-top: 8px;
        margin-top: 5px;
    }
    .summary-total .summary-label,
    .summary-total .summary-value {
        font-size: 11px;
        font-weight: 700;
        color: #000;
    }
    .no-items-message {
        padding: 15px;
        text-align: center;
        color: #666;
        font-style: italic;
        background: #f9f9f9;
        border: 1px dashed #ddd;
    }
</style>
@endsection

@section('content')
    @php
        // Fetch periods once to avoid repetition
        $periods = $forecast->periods()->orderBy('period_start_date')->get();
    @endphp

    {{-- Forecast Information Section --}}
    <div class="forecast-header-section">
        <h3 style="margin-bottom: 8px; color: {{ $hosColor }}; font-size: 13px;">Cash Flow Forecast Details</h3>
        <div class="forecast-info-grid">
            <div class="forecast-info-row">
                <div class="forecast-info-label">Forecast Name:</div>
                <div class="forecast-info-value">{{ $forecast->forecast_name }}</div>
            </div>
            <div class="forecast-info-row">
                <div class="forecast-info-label">Forecast Period:</div>
                <div class="forecast-info-value">
                    {{ optional($forecast->forecast_start_date)->format('M d, Y') }} -
                    {{ optional($forecast->forecast_end_date)->format('M d, Y') }}
                </div>
            </div>
            <div class="forecast-info-row">
                <div class="forecast-info-label">Forecast Type:</div>
                <div class="forecast-info-value">{{ ucwords(str_replace('_', ' ', $forecast->forecast_type)) }}</div>
            </div>
            <div class="forecast-info-row">
                <div class="forecast-info-label">Frequency:</div>
                <div class="forecast-info-value">{{ ucfirst($forecast->frequency) }}</div>
            </div>
            <div class="forecast-info-row">
                <div class="forecast-info-label">Opening Balance:</div>
                <div class="forecast-info-value">₦{{ number_format($currentCash, 2) }}</div>
            </div>
            @if($forecast->approved_by)
            <div class="forecast-info-row">
                <div class="forecast-info-label">Approved By:</div>
                <div class="forecast-info-value">
                    {{ optional($forecast->approver)->name ?? 'Unknown' }} on
                    {{ optional($forecast->approved_at)->format('M d, Y') }}
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Periods Summary Table --}}
    <h4 style="color: {{ $hosColor }}; font-size: 12px; margin-top: 20px; margin-bottom: 10px; border-bottom: 2px solid {{ $hosColor }}; padding-bottom: 5px;">
        Period Summary
    </h4>
    <table class="periods-table">
        <thead>
            <tr>
                <th style="width: 15%;">Period</th>
                <th style="width: 15%;">Start Date</th>
                <th style="width: 15%;">End Date</th>
                <th style="width: 13%;">Opening Balance</th>
                <th style="width: 13%;">Forecasted Inflows</th>
                <th style="width: 13%;">Forecasted Outflows</th>
                <th style="width: 13%;">Net Cash Flow</th>
                <th style="width: 13%;">Closing Balance</th>
            </tr>
        </thead>
        <tbody>
            @php
                $runningBalance = $currentCash;
            @endphp
            @foreach($periods as $period)
                @php
                    $netFlow = $period->forecasted_inflows - $period->forecasted_outflows;
                    $periodClosing = $runningBalance + $netFlow;
                    $isCurrent = $period->isCurrent();
                @endphp
                <tr class="{{ $isCurrent ? 'period-current' : '' }}">
                    <td>{{ $period->period_name }}</td>
                    <td>{{ optional($period->period_start_date)->format('M d, Y') }}</td>
                    <td>{{ optional($period->period_end_date)->format('M d, Y') }}</td>
                    <td class="amount-cell">₦{{ number_format($runningBalance, 2) }}</td>
                    <td class="amount-cell">₦{{ number_format($period->forecasted_inflows, 2) }}</td>
                    <td class="amount-cell">₦{{ number_format($period->forecasted_outflows, 2) }}</td>
                    <td class="amount-cell">₦{{ number_format($netFlow, 2) }}</td>
                    <td class="amount-cell">₦{{ number_format($periodClosing, 2) }}</td>
                </tr>
                @php
                    $runningBalance = $periodClosing;
                @endphp
            @endforeach
        </tbody>
    </table>

    {{-- Detailed Period Items Breakdown --}}
    <div style="page-break-before: always;"></div>
    <h4 style="color: {{ $hosColor }}; font-size: 12px; margin-top: 20px; margin-bottom: 10px; border-bottom: 2px solid {{ $hosColor }}; padding-bottom: 5px;">
        Period-by-Period Breakdown
    </h4>

    @foreach($periods as $period)
        <div class="period-items-section">
            <div class="period-header">
                {{ $period->period_name }} ({{ optional($period->period_start_date)->format('M d, Y') }} - {{ optional($period->period_end_date)->format('M d, Y') }})
                @if($period->isCurrent())
                    <span style="float: right; background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 9px;">CURRENT PERIOD</span>
                @endif
            </div>

            @php
                $items = $period->items()->orderBy('cash_flow_category')->orderBy('item_description')->get();
                $inflows = $items->whereIn('cash_flow_category', ['operating_inflow', 'investing_inflow', 'financing_inflow']);
                $outflows = $items->whereIn('cash_flow_category', ['operating_outflow', 'investing_outflow', 'financing_outflow']);
            @endphp

            @if($items->isEmpty())
                <div class="no-items-message">
                    No line items defined for this period
                </div>
            @else
                {{-- Inflows Table --}}
                @if($inflows->isNotEmpty())
                    <h5 style="color: #28a745; font-size: 11px; margin-top: 12px; margin-bottom: 5px;">Cash Inflows</h5>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Category</th>
                                <th style="width: 45%;">Description</th>
                                <th style="width: 15%;">Source</th>
                                <th style="width: 15%;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $currentCategory = null;
                                $categoryTotal = 0;
                                $grandTotal = 0;
                            @endphp
                            @foreach($inflows as $item)
                                @if($currentCategory !== $item->cash_flow_category)
                                    @if($currentCategory !== null)
                                        <tr class="category-separator">
                                            <td colspan="3" style="text-align: right;">{{ ucwords(str_replace('_', ' ', $currentCategory)) }} Subtotal:</td>
                                            <td class="amount-cell">₦{{ number_format($categoryTotal, 2) }}</td>
                                        </tr>
                                        @php $categoryTotal = 0; @endphp
                                    @endif
                                    @php $currentCategory = $item->cash_flow_category; @endphp
                                @endif
                                <tr>
                                    <td>{{ ucwords(str_replace('_', ' ', $item->cash_flow_category)) }}</td>
                                    <td>{{ $item->item_description }}</td>
                                    <td>{{ ucwords(str_replace('_', ' ', $item->source_type)) }}</td>
                                    <td class="amount-cell">₦{{ number_format($item->forecasted_amount, 2) }}</td>
                                </tr>
                                @php
                                    $categoryTotal += $item->forecasted_amount;
                                    $grandTotal += $item->forecasted_amount;
                                @endphp
                            @endforeach
                            {{-- Last category subtotal --}}
                            <tr class="category-separator">
                                <td colspan="3" style="text-align: right;">{{ ucwords(str_replace('_', ' ', $currentCategory)) }} Subtotal:</td>
                                <td class="amount-cell">₦{{ number_format($categoryTotal, 2) }}</td>
                            </tr>
                            <tr style="background: #d4edda; font-weight: 700;">
                                <td colspan="3" style="text-align: right; padding: 8px;">Total Inflows:</td>
                                <td class="amount-cell" style="padding: 8px;">₦{{ number_format($grandTotal, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif

                {{-- Outflows Table --}}
                @if($outflows->isNotEmpty())
                    <h5 style="color: #dc3545; font-size: 11px; margin-top: 12px; margin-bottom: 5px;">Cash Outflows</h5>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Category</th>
                                <th style="width: 45%;">Description</th>
                                <th style="width: 15%;">Source</th>
                                <th style="width: 15%;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $currentCategory = null;
                                $categoryTotal = 0;
                                $grandTotal = 0;
                            @endphp
                            @foreach($outflows as $item)
                                @if($currentCategory !== $item->cash_flow_category)
                                    @if($currentCategory !== null)
                                        <tr class="category-separator">
                                            <td colspan="3" style="text-align: right;">{{ ucwords(str_replace('_', ' ', $currentCategory)) }} Subtotal:</td>
                                            <td class="amount-cell">₦{{ number_format($categoryTotal, 2) }}</td>
                                        </tr>
                                        @php $categoryTotal = 0; @endphp
                                    @endif
                                    @php $currentCategory = $item->cash_flow_category; @endphp
                                @endif
                                <tr>
                                    <td>{{ ucwords(str_replace('_', ' ', $item->cash_flow_category)) }}</td>
                                    <td>{{ $item->item_description }}</td>
                                    <td>{{ ucwords(str_replace('_', ' ', $item->source_type)) }}</td>
                                    <td class="amount-cell">₦{{ number_format($item->forecasted_amount, 2) }}</td>
                                </tr>
                                @php
                                    $categoryTotal += $item->forecasted_amount;
                                    $grandTotal += $item->forecasted_amount;
                                @endphp
                            @endforeach
                            {{-- Last category subtotal --}}
                            <tr class="category-separator">
                                <td colspan="3" style="text-align: right;">{{ ucwords(str_replace('_', ' ', $currentCategory)) }} Subtotal:</td>
                                <td class="amount-cell">₦{{ number_format($categoryTotal, 2) }}</td>
                            </tr>
                            <tr style="background: #f8d7da; font-weight: 700;">
                                <td colspan="3" style="text-align: right; padding: 8px;">Total Outflows:</td>
                                <td class="amount-cell" style="padding: 8px;">₦{{ number_format($grandTotal, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif

                {{-- Period Summary Box --}}
                <div class="summary-box">
                    <div class="summary-row">
                        <div class="summary-label">Total Inflows:</div>
                        <div class="summary-value">₦{{ number_format($period->forecasted_inflows, 2) }}</div>
                    </div>
                    <div class="summary-row">
                        <div class="summary-label">Total Outflows:</div>
                        <div class="summary-value">₦{{ number_format($period->forecasted_outflows, 2) }}</div>
                    </div>
                    <div class="summary-row summary-total">
                        <div class="summary-label">Net Cash Flow:</div>
                        <div class="summary-value">₦{{ number_format($period->forecasted_inflows - $period->forecasted_outflows, 2) }}</div>
                    </div>
                    @if($period->actual_closing_balance !== null)
                        <div class="summary-row" style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #dee2e6;">
                            <div class="summary-label">Actual Closing Balance:</div>
                            <div class="summary-value">₦{{ number_format($period->actual_closing_balance, 2) }}</div>
                        </div>
                        <div class="summary-row">
                            <div class="summary-label">Variance:</div>
                            <div class="summary-value {{ $period->variance >= 0 ? 'variance-positive' : 'variance-negative' }}">
                                ₦{{ number_format($period->variance, 2) }}
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endforeach
@endsection
