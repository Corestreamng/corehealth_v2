{{-- Executive Summary Detailed Print --}}
<!DOCTYPE html>
<html>
<head>
    <title>Executive Summary Detailed - {{ $print_date }}</title>
    <style>
        :root {
            --brand: {{ $appsettings->hos_color ?? '#0a6cf2' }};
            --ink: #1d1d1f;
            --muted: #5f6368;
            --border: #d7d9dc;
            --bg: #f7f9fb;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        .report-page {
            padding: 24px;
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 13px;
            color: var(--ink);
            background: var(--bg);
        }

        .sheet {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.04);
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
            min-height: 1100px; /* A4 rough min-height */
        }

        .brand-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid var(--brand);
            padding-bottom: 16px;
            margin-bottom: 24px;
        }

        .brand-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .brand-left img {
            max-width: 80px;
            height: auto;
        }

        .brand-name {
            font-size: 22px;
            font-weight: 800;
            color: var(--brand);
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .brand-meta {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.5;
        }

        .doc-title {
            text-align: right;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--ink);
            text-transform: uppercase;
        }

        .doc-title small {
            display: block;
            font-size: 12px;
            color: var(--muted);
            font-weight: 500;
            margin-top: 4px;
            text-transform: none;
        }

        .filters-box {
            background: #f8f9fa;
            border: 1px solid var(--border);
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 24px;
            display: flex;
            gap: 32px;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--muted);
            letter-spacing: 0.5px;
        }

        .filter-val {
            font-size: 14px;
            font-weight: 600;
            color: var(--brand);
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            border-bottom: 2px solid var(--border);
            padding-bottom: 6px;
            margin: 24px 0 16px 0;
            color: var(--ink);
        }

        .summary-cards {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }

        .card {
            flex: 1;
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 6px;
            text-align: center;
        }

        .card-label {
            font-size: 11px;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .card-val {
            font-size: 20px;
            font-weight: 700;
            color: var(--brand);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        th, td {
            border: 1px solid var(--border);
            padding: 8px 12px;
            font-size: 13px;
        }

        th {
            background: #f1f3f5;
            font-weight: 700;
            color: var(--ink);
            text-align: left;
        }

        .row-store {
            background: rgba(10, 108, 242, 0.05);
            font-weight: 700;
        }

        .row-scheme {
            background: #fafafa;
            font-weight: 600;
        }

        .row-hmo {
            background: #fff;
        }

        .indent-1 { padding-left: 24px; }
        .indent-2 { padding-left: 48px; }

        .text-right { text-align: right; }

        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 11px;
            color: var(--muted);
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }

        .sig-box {
            width: 250px;
            text-align: center;
        }

        .sig-line {
            border-bottom: 1px solid var(--ink);
            height: 30px;
            margin-bottom: 8px;
        }

        @media print {
            @page { size: A4; margin: 10mm; }
            .report-page { background: #fff; padding: 0; }
            .sheet { box-shadow: none; border: none; margin: 0; width: 100%; max-width: none; padding: 0; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body>
<div class="report-page">
<div class="sheet">
    
    <!-- Header -->
    <div class="brand-bar">
        <div class="brand-left">
            @if(!empty($appsettings->logo))
                <img src="data:image/png;base64,{{ $appsettings->logo }}" alt="Logo">
            @endif
            <div>
                <div class="brand-name">{{ $appsettings->site_name ?? 'HOSPITAL' }}</div>
                <div class="brand-meta">
                    {{ $appsettings->contact_address ?? '' }}<br>
                    @if(!empty($appsettings->contact_phones) || !empty($appsettings->contact_emails))
                        Phone: {{ $appsettings->contact_phones ?? 'N/A' }} | Email: {{ $appsettings->contact_emails ?? 'N/A' }}
                    @endif
                </div>
            </div>
        </div>
        <div class="doc-title">
            INVENTORY SUMMARY
            <small>Printed: {{ $print_date }}</small>
            <small>By: {{ $pharmacist }}</small>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-box">
        <div class="filter-item">
            <span class="filter-label">Date Range</span>
            <span class="filter-val">{{ $filters['date_from'] }} - {{ $filters['date_to'] }}</span>
        </div>
        <div class="filter-item">
            <span class="filter-label">Store Selection</span>
            <span class="filter-val">{{ $filters['store'] }}</span>
        </div>
    </div>

    <!-- Top Level KPI Cards -->
    <div class="summary-cards">
        @php
            $grandQty = 0;
            $grandVal = 0;
            $grandSales = 0;
            $grandProfit = 0;
            foreach($data as $row) {
                $grandQty += $row['total_qty'];
                $grandVal += $row['total_value'];
                $totalSales = ($row['potential_revenue'] ?? 0) + ($row['cash_revenue'] ?? 0) + ($row['claims_revenue'] ?? 0);
                $grandSales += $totalSales;
                $grandProfit += ($row['profit'] ?? 0);
            }
        @endphp
        <div class="card-modern">
            <div class="card-label">Total Volume (Qty)</div>
            <div class="card-val">{{ number_format($grandQty) }}</div>
        </div>
        <div class="card-modern">
            <div class="card-label">Total Cost Value</div>
            <div class="card-val">₦{{ number_format($grandVal, 2) }}</div>
        </div>
        <div class="card-modern">
            <div class="card-label">Total Expected Revenue</div>
            <div class="card-val">₦{{ number_format($grandSales, 2) }}</div>
        </div>
        <div class="card-modern">
            <div class="card-label">Total Profit/Loss</div>
            <div class="card-val" style="color: {{ $grandProfit > 0 ? '#28a745' : ($grandProfit < 0 ? '#dc3545' : 'inherit') }}">₦{{ number_format($grandProfit, 2) }}</div>
        </div>
    </div>

    <!-- Data Table -->
    <h3 class="section-title">
        BREAKDOWN BY 
        @if($group_by === 'category') DRUG/PRODUCT CATEGORY
        @elseif($group_by === 'product') PRODUCT NAME
        @else DESTINATION UNIT/DEPARTMENT @endif
    </h3>
    <table style="width: 100%; margin-bottom: 24px;">
        <thead>
            <tr>
                <th style="width: 30%; text-align: left;">Category/Group</th>
                <th class="text-right">Volume (Qty)</th>
                <th class="text-right">Cost (₦)</th>
                <th class="text-right">Sales / Potential (₦)</th>
                <th class="text-right">Cash (₦)</th>
                <th class="text-right">Claims (₦)</th>
                <th class="text-right">Profit/Loss (₦)</th>
            </tr>
        </thead>
        <tbody>
            @if(empty($data))
                <tr><td colspan="7" style="text-align: center; color: var(--muted);">No records found for the selected period</td></tr>
            @else
                @foreach($data as $row)
                    @php
                        $sales = ($row['potential_revenue'] ?? 0) + ($row['cash_revenue'] ?? 0) + ($row['claims_revenue'] ?? 0);
                        $profit = $row['profit'] ?? 0;
                        $profitColor = $profit > 0 ? '#28a745' : ($profit < 0 ? '#dc3545' : 'inherit');
                    @endphp
                    <tr>
                        <td><strong>{{ $row['grouping_key'] }}</strong></td>
                        <td class="text-right">{{ number_format($row['total_qty']) }}</td>
                        <td class="text-right">{{ number_format($row['total_value'], 2) }}</td>
                        <td class="text-right">{{ number_format($sales, 2) }}</td>
                        <td class="text-right">{{ number_format($row['cash_revenue'] ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($row['claims_revenue'] ?? 0, 2) }}</td>
                        <td class="text-right" style="color: {{ $profitColor }}; font-weight: bold;">{{ number_format($profit, 2) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <!-- Signatures -->
    <div class="signatures">
        <div class="sig-box">
            <div class="sig-line"></div>
            <strong>{{ $pharmacist }}</strong><br>
            <small>Attending Pharmacist</small>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <small>Authorized Signature</small>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        {{ $appsettings->site_name ?? 'Hospital' }} - Inventory Summary Report
    </div>

</div>
</div>
<script>
    // Trigger print automatically when loaded in iframe/new window
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500);
    };
</script>
</body>
</html>
