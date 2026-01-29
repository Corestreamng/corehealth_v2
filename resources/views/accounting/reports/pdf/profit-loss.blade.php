<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Profit & Loss Statement</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
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
        .section {
            margin-bottom: 25px;
        }
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
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            padding: 5px 0;
        }
        .indent {
            padding-left: 20px;
        }
        .text-right {
            text-align: right;
        }
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
        .total-box table {
            color: white;
        }
        .total-box .label {
            font-size: 14px;
            font-weight: bold;
        }
        .total-box .amount {
            font-size: 18px;
            font-weight: bold;
        }
        .gross-profit-box {
            margin: 20px 0;
            padding: 10px;
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
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
        <h2>Profit & Loss Statement</h2>
        <p>For the period {{ $startDate->format('F d, Y') }} to {{ $endDate->format('F d, Y') }}</p>
    </div>

    <!-- Revenue Section -->
    <div class="section">
        <div class="section-title revenue">REVENUE</div>
        <table>
            <tbody>
                @php $totalRevenue = 0; @endphp
                @foreach($data['revenue'] ?? [] as $item)
                    <tr>
                        <td class="indent">{{ $item['code'] }} - {{ $item['name'] }}</td>
                        <td class="text-right" style="width: 120px;">{{ number_format($item['balance'], 2) }}</td>
                    </tr>
                    @php $totalRevenue += $item['balance']; @endphp
                @endforeach
                <tr class="subtotal-row">
                    <td>Total Revenue</td>
                    <td class="text-right">{{ number_format($totalRevenue, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Cost of Services Section -->
    <div class="section">
        <div class="section-title cost">COST OF SERVICES</div>
        <table>
            <tbody>
                @php $totalCOS = 0; @endphp
                @foreach($data['cost_of_services'] ?? [] as $item)
                    <tr>
                        <td class="indent">{{ $item['code'] }} - {{ $item['name'] }}</td>
                        <td class="text-right" style="width: 120px;">{{ number_format($item['balance'], 2) }}</td>
                    </tr>
                    @php $totalCOS += $item['balance']; @endphp
                @endforeach
                <tr class="subtotal-row">
                    <td>Total Cost of Services</td>
                    <td class="text-right">{{ number_format($totalCOS, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Gross Profit -->
    @php $grossProfit = $totalRevenue - $totalCOS; @endphp
    <div class="gross-profit-box">
        <table>
            <tr>
                <td><strong>GROSS PROFIT</strong></td>
                <td class="text-right" style="width: 120px;"><strong>{{ number_format($grossProfit, 2) }}</strong></td>
            </tr>
        </table>
    </div>

    <!-- Operating Expenses Section -->
    <div class="section">
        <div class="section-title expense">OPERATING EXPENSES</div>
        <table>
            <tbody>
                @php $totalExpenses = 0; @endphp
                @foreach($data['expenses'] ?? [] as $item)
                    <tr>
                        <td class="indent">{{ $item['code'] }} - {{ $item['name'] }}</td>
                        <td class="text-right" style="width: 120px;">{{ number_format($item['balance'], 2) }}</td>
                    </tr>
                    @php $totalExpenses += $item['balance']; @endphp
                @endforeach
                <tr class="subtotal-row">
                    <td>Total Operating Expenses</td>
                    <td class="text-right">{{ number_format($totalExpenses, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Other Income Section -->
    @if(!empty($data['other_income']))
        <div class="section">
            <div class="section-title" style="color: #17a2b8;">OTHER INCOME</div>
            <table>
                <tbody>
                    @php $totalOtherIncome = 0; @endphp
                    @foreach($data['other_income'] ?? [] as $item)
                        <tr>
                            <td class="indent">{{ $item['code'] }} - {{ $item['name'] }}</td>
                            <td class="text-right" style="width: 120px;">{{ number_format($item['balance'], 2) }}</td>
                        </tr>
                        @php $totalOtherIncome += $item['balance']; @endphp
                    @endforeach
                    <tr class="subtotal-row">
                        <td>Total Other Income</td>
                        <td class="text-right">{{ number_format($totalOtherIncome, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @else
        @php $totalOtherIncome = 0; @endphp
    @endif

    <!-- Net Income -->
    @php $netIncome = $grossProfit - $totalExpenses + $totalOtherIncome; @endphp
    <div class="total-box">
        <table>
            <tr>
                <td class="label">NET INCOME (LOSS)</td>
                <td class="text-right amount" style="width: 150px; color: {{ $netIncome >= 0 ? '#28a745' : '#dc3545' }};">
                    ₦ {{ number_format($netIncome, 2) }}
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <span style="float: left;">Generated on: {{ now()->format('F d, Y H:i:s') }}</span>
        <span style="float: right;">Generated by: {{ auth()->user()->name ?? 'System' }}</span>
    </div>
</body>
</html>
