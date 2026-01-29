<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Balance Sheet</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        .header h2 {
            margin: 5px 0;
            font-size: 16px;
            font-weight: normal;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .columns {
            width: 100%;
        }
        .column {
            width: 48%;
            display: inline-block;
            vertical-align: top;
        }
        .column-left {
            border-right: 1px solid #ddd;
            padding-right: 15px;
        }
        .column-right {
            padding-left: 15px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #2196f3;
            border-bottom: 2px solid #2196f3;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .subsection {
            margin-bottom: 15px;
        }
        .subsection-title {
            font-weight: bold;
            color: #666;
            margin-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            padding: 3px 0;
        }
        .indent {
            padding-left: 15px;
        }
        .text-right {
            text-align: right;
        }
        .subtotal-row {
            font-weight: bold;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }
        .total-box {
            margin-top: 15px;
            padding: 10px;
            background-color: #2196f3;
            color: white;
        }
        .total-box table {
            color: white;
        }
        .balance-check {
            margin-top: 20px;
            padding: 10px;
            text-align: center;
            clear: both;
        }
        .balanced {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .unbalanced {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            width: 100%;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name', 'CoreHealth Hospital') }}</h1>
        <h2>Balance Sheet</h2>
        <p>As of {{ $asOfDate->format('F d, Y') }}</p>
    </div>

    <table class="columns">
        <tr>
            <!-- Assets Column -->
            <td class="column column-left">
                <div class="section-title">ASSETS</div>

                <!-- Current Assets -->
                <div class="subsection">
                    <div class="subsection-title">Current Assets</div>
                    <table>
                        @php $totalCurrentAssets = 0; @endphp
                        @foreach($data['current_assets'] ?? [] as $item)
                            <tr>
                                <td class="indent">{{ $item['name'] }}</td>
                                <td class="text-right" style="width: 100px;">{{ number_format($item['balance'], 2) }}</td>
                            </tr>
                            @php $totalCurrentAssets += $item['balance']; @endphp
                        @endforeach
                        <tr class="subtotal-row">
                            <td>Total Current Assets</td>
                            <td class="text-right">{{ number_format($totalCurrentAssets, 2) }}</td>
                        </tr>
                    </table>
                </div>

                <!-- Fixed Assets -->
                <div class="subsection">
                    <div class="subsection-title">Fixed Assets</div>
                    <table>
                        @php $totalFixedAssets = 0; @endphp
                        @foreach($data['fixed_assets'] ?? [] as $item)
                            <tr>
                                <td class="indent">{{ $item['name'] }}</td>
                                <td class="text-right" style="width: 100px;">{{ number_format($item['balance'], 2) }}</td>
                            </tr>
                            @php $totalFixedAssets += $item['balance']; @endphp
                        @endforeach
                        <tr class="subtotal-row">
                            <td>Total Fixed Assets</td>
                            <td class="text-right">{{ number_format($totalFixedAssets, 2) }}</td>
                        </tr>
                    </table>
                </div>

                @php
                    $totalOtherAssets = 0;
                    foreach($data['other_assets'] ?? [] as $item) {
                        $totalOtherAssets += $item['balance'];
                    }
                    $totalAssets = $totalCurrentAssets + $totalFixedAssets + $totalOtherAssets;
                @endphp

                <div class="total-box">
                    <table>
                        <tr>
                            <td><strong>TOTAL ASSETS</strong></td>
                            <td class="text-right" style="width: 100px;"><strong>₦ {{ number_format($totalAssets, 2) }}</strong></td>
                        </tr>
                    </table>
                </div>
            </td>

            <!-- Liabilities & Equity Column -->
            <td class="column column-right">
                <div class="section-title">LIABILITIES & EQUITY</div>

                <!-- Current Liabilities -->
                <div class="subsection">
                    <div class="subsection-title">Current Liabilities</div>
                    <table>
                        @php $totalCurrentLiabilities = 0; @endphp
                        @foreach($data['current_liabilities'] ?? [] as $item)
                            <tr>
                                <td class="indent">{{ $item['name'] }}</td>
                                <td class="text-right" style="width: 100px;">{{ number_format($item['balance'], 2) }}</td>
                            </tr>
                            @php $totalCurrentLiabilities += $item['balance']; @endphp
                        @endforeach
                        <tr class="subtotal-row">
                            <td>Total Current Liabilities</td>
                            <td class="text-right">{{ number_format($totalCurrentLiabilities, 2) }}</td>
                        </tr>
                    </table>
                </div>

                @php
                    $totalLongTermLiabilities = 0;
                    foreach($data['long_term_liabilities'] ?? [] as $item) {
                        $totalLongTermLiabilities += $item['balance'];
                    }
                    $totalLiabilities = $totalCurrentLiabilities + $totalLongTermLiabilities;
                @endphp

                <!-- Equity -->
                <div class="subsection">
                    <div class="subsection-title">Stockholders' Equity</div>
                    <table>
                        @php $totalEquity = 0; @endphp
                        @foreach($data['equity'] ?? [] as $item)
                            <tr>
                                <td class="indent">{{ $item['name'] }}</td>
                                <td class="text-right" style="width: 100px;">{{ number_format($item['balance'], 2) }}</td>
                            </tr>
                            @php $totalEquity += $item['balance']; @endphp
                        @endforeach
                        <tr>
                            <td class="indent">Current Period Net Income</td>
                            <td class="text-right" style="width: 100px;">{{ number_format($data['net_income'] ?? 0, 2) }}</td>
                        </tr>
                        @php $totalEquity += ($data['net_income'] ?? 0); @endphp
                        <tr class="subtotal-row">
                            <td>Total Equity</td>
                            <td class="text-right">{{ number_format($totalEquity, 2) }}</td>
                        </tr>
                    </table>
                </div>

                @php $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity; @endphp
                <div class="total-box">
                    <table>
                        <tr>
                            <td><strong>TOTAL LIAB. & EQUITY</strong></td>
                            <td class="text-right" style="width: 100px;"><strong>₦ {{ number_format($totalLiabilitiesAndEquity, 2) }}</strong></td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    @php $difference = $totalAssets - $totalLiabilitiesAndEquity; @endphp
    <div class="balance-check {{ abs($difference) < 0.01 ? 'balanced' : 'unbalanced' }}">
        @if(abs($difference) < 0.01)
            <strong>✓ Balance Sheet is BALANCED</strong>
        @else
            <strong>✗ Balance Sheet is OUT OF BALANCE</strong><br>
            Difference: ₦ {{ number_format(abs($difference), 2) }}
        @endif
    </div>

    <div class="footer">
        <span style="float: left;">Generated on: {{ now()->format('F d, Y H:i:s') }}</span>
        <span style="float: right;">Generated by: {{ auth()->user()->name ?? 'System' }}</span>
    </div>
</body>
</html>
