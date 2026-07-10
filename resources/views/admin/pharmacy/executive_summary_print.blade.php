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
            EXECUTIVE SUMMARY
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
        <div class="card">
            <div class="card-label">Total Stock Valuation</div>
            <div class="card-val">₦{{ number_format($stock_valuation, 2) }}</div>
        </div>
        <div class="card">
            <div class="card-label">Total Revenue Collected</div>
            @php
                $totalVal = 0;
                $totalItems = 0;
                foreach($collections_by_store as $s) {
                    $totalVal += $s['value'];
                    $totalItems += $s['count'];
                }
            @endphp
            <div class="card-val">₦{{ number_format($totalVal, 2) }}</div>
            <div style="font-size: 11px; color: var(--muted); margin-top: 4px;">{{ number_format($totalItems) }} items dispensed</div>
        </div>
        <div class="card">
            <div class="card-label">Patients Attended</div>
            @php
                $totalPat = array_sum($patients_attended_to);
            @endphp
            <div class="card-val">{{ number_format($totalPat) }}</div>
        </div>
    </div>

    <!-- Financial Performance Summary -->
    <h3 class="section-title">SUMMARY</h3>
    <table style="width: 60%; margin-bottom: 24px;">
        <tbody>
            <tr>
                <td style="width: 60%;"><strong>Opening stock</strong></td>
                <td class="text-right">₦{{ number_format($opening_stock, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Purchases (Expenditure)</strong></td>
                <td class="text-right">₦{{ number_format($total_expenditure, 2) }}</td>
            </tr>
            <tr style="background: rgba(10, 108, 242, 0.05);">
                <td><strong>Goods available</strong></td>
                <td class="text-right" style="color: var(--brand); font-weight: bold;">₦{{ number_format($opening_stock + $total_expenditure, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Closing Stock</strong></td>
                <td class="text-right">₦{{ number_format($stock_valuation, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Goods Used (Income/Sales)</strong></td>
                <td class="text-right">₦{{ number_format($total_goods_used, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Income by Scheme Summary -->
    <h3 class="section-title">INCOME – The total income generated by the Department was</h3>
    <table style="width: 100%; margin-bottom: 24px;">
        <thead>
            <tr>
                <th style="width: 40%; text-align: left;">Scheme</th>
                <th class="text-right" style="width: 20%;">Cash (₦)</th>
                <th class="text-right" style="width: 20%;">Claims (₦)</th>
                <th class="text-right" style="width: 20%;">Total (₦)</th>
            </tr>
        </thead>
        <tbody>
            @if(empty($income_by_scheme))
                <tr><td colspan="4" style="text-align: center; color: var(--muted);">No income data found</td></tr>
            @else
                @php
                    $sumCash = 0;
                    $sumClaims = 0;
                @endphp
                @foreach($income_by_scheme as $schemeName => $val)
                    @php
                        $cash = is_array($val) ? ($val['cash'] ?? 0) : 0;
                        $claims = is_array($val) ? ($val['claims'] ?? 0) : 0;
                        $total = is_array($val) ? ($val['total'] ?? $val) : $val;
                        $sumCash += $cash;
                        $sumClaims += $claims;
                    @endphp
                    <tr>
                        <td><strong>{{ strtoupper($schemeName) }}</strong></td>
                        <td class="text-right">{{ number_format($cash, 2) }}</td>
                        <td class="text-right">{{ number_format($claims, 2) }}</td>
                        <td class="text-right"><strong>{{ number_format($total, 2) }}</strong></td>
                    </tr>
                @endforeach
                <tr style="background: #fafafa; font-weight: bold;">
                    <td>TOTAL</td>
                    <td class="text-right">{{ number_format($sumCash, 2) }}</td>
                    <td class="text-right">{{ number_format($sumClaims, 2) }}</td>
                    <td class="text-right">₦{{ number_format($total_goods_used, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Total Patients by Scheme -->
    <h3 class="section-title">Total number of patients attended to</h3>
    <table style="width: 60%; margin-bottom: 24px;">
        <tbody>
            @if(empty($patients_by_scheme))
                <tr><td colspan="2" style="text-align: center; color: var(--muted);">No patient data found</td></tr>
            @else
                @foreach($patients_by_scheme as $schemeName => $val)
                    <tr>
                        <td style="width: 60%;"><strong>{{ strtoupper($schemeName) }}</strong></td>
                        <td class="text-right">{{ number_format($val) }}</td>
                    </tr>
                @endforeach
                <tr style="background: #fafafa;">
                    <td><strong>TOTAL</strong></td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format($totalPat) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Financial Breakdown Table -->
    <h3 class="section-title">Deep Financial Breakdown (Collections)</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 35%;">Entity</th>
                <th style="width: 15%; text-align: center;">Items</th>
                <th style="width: 15%; text-align: right;">Cash (₦)</th>
                <th style="width: 15%; text-align: right;">Claims (₦)</th>
                <th style="width: 20%; text-align: right;">Total Amount (₦)</th>
            </tr>
        </thead>
        <tbody>
            @if(empty($collections_by_store))
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--muted);">No financial data found</td>
                </tr>
            @else
                @foreach($collections_by_store as $store)
                    <tr class="row-store">
                        <td>🛒 {{ $store['store_name'] }}</td>
                        <td style="text-align: center;">{{ number_format($store['count']) }}</td>
                        <td class="text-right">{{ number_format($store['cash'], 2) }}</td>
                        <td class="text-right">{{ number_format($store['claims'], 2) }}</td>
                        <td class="text-right">{{ number_format($store['value'], 2) }}</td>
                    </tr>
                    @if(!empty($store['schemes']))
                        @foreach($store['schemes'] as $schemeName => $scheme)
                            <tr class="row-scheme">
                                <td class="indent-1">↳ {{ $schemeName }}</td>
                                <td style="text-align: center;">{{ number_format($scheme['count']) }}</td>
                                <td class="text-right">{{ number_format($scheme['cash'], 2) }}</td>
                                <td class="text-right">{{ number_format($scheme['claims'], 2) }}</td>
                                <td class="text-right">{{ number_format($scheme['value'], 2) }}</td>
                            </tr>
                            @if(!empty($scheme['hmos']))
                                @foreach($scheme['hmos'] as $hmoName => $hmo)
                                    <tr class="row-hmo">
                                        <td class="indent-2">- {{ $hmoName }}</td>
                                        <td style="text-align: center;">{{ number_format($hmo['count']) }}</td>
                                        <td class="text-right">{{ number_format($hmo['cash'], 2) }}</td>
                                        <td class="text-right">{{ number_format($hmo['claims'], 2) }}</td>
                                        <td class="text-right">{{ number_format($hmo['value'], 2) }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    @endif
                @endforeach
            @endif
        </tbody>
    </table>

    <div class="page-break"></div>
    
    <!-- Demographic Breakdown Macro Helper -->
    @php
        if (!function_exists('renderPrintDemoTable')) {
            function renderPrintDemoTable($title, $data) {
                echo '<h3 class="section-title">' . $title . '</h3>';
                echo '<table><thead><tr><th style="width: 80%;">Category / Scheme / HMO</th><th style="width: 20%; text-align: right;">Patients</th></tr></thead><tbody>';
                
                if (empty($data)) {
                    echo '<tr><td colspan="2" style="text-align: center; color: #5f6368;">No data found</td></tr>';
                } else {
                    foreach ($data as $catName => $cat) {
                        echo '<tr class="row-store">';
                        echo '<td><strong>' . htmlspecialchars($catName) . '</strong></td>';
                        echo '<td class="text-right"><strong>' . number_format($cat['count']) . '</strong></td>';
                        echo '</tr>';
                        
                        if (!empty($cat['schemes'])) {
                            foreach ($cat['schemes'] as $schemeName => $scheme) {
                                echo '<tr class="row-scheme">';
                                echo '<td class="indent-1">↳ ' . htmlspecialchars($schemeName) . '</td>';
                                echo '<td class="text-right">' . number_format($scheme['count']) . '</td>';
                                echo '</tr>';
                                
                                if (!empty($scheme['hmos'])) {
                                    foreach ($scheme['hmos'] as $hmoName => $count) {
                                        echo '<tr class="row-hmo">';
                                        echo '<td class="indent-2">- ' . htmlspecialchars($hmoName) . '</td>';
                                        echo '<td class="text-right">' . number_format($count) . '</td>';
                                        echo '</tr>';
                                    }
                                }
                            }
                        }
                    }
                }
                echo '</tbody></table>';
            }
        }
    @endphp

    <!-- Render Demographics -->
    {!! renderPrintDemoTable('Demographics: Gender Distribution', $gender_distribution) !!}
    {!! renderPrintDemoTable('Demographics: Age Distribution', $age_distribution) !!}
    {!! renderPrintDemoTable('Demographics: Visit Classifications', $patient_classifications) !!}

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
        {{ $appsettings->site_name ?? 'Hospital' }} - Pharmacy Workbench Executive Report
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
