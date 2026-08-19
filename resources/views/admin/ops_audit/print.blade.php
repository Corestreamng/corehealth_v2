{{-- Generic Ops Audit Print View --}}
<!DOCTYPE html>
<html>
<head>
    <title>Ops Audit - {{ $tab_name }} - {{ $print_date }}</title>
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
            max-width: 100%;
            width: 100%;
            margin: 0 auto;
            min-height: 1100px;
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
            flex-wrap: wrap;
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

        .summary-cards {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .card-modern {
            flex: 1;
            min-width: 150px;
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
            font-size: 12px;
        }

        th {
            background: #f1f3f5;
            font-weight: 700;
            color: var(--ink);
            text-align: left;
        }

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

        /* Clean badges for print */
        .badge {
            padding: 2px 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: #f8f9fa;
            color: #000 !important;
            font-size: 10px;
            display: inline-block;
        }

        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            .report-page { background: #fff; padding: 0; }
            .sheet { box-shadow: none; border: none; margin: 0; width: 100%; max-width: none; padding: 0; }
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
            OPS AUDIT - {{ $tab_name }}
            <small>Printed: {{ $print_date }}</small>
            <small>By: {{ $printer }}</small>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-box">
        <div class="filter-item">
            <span class="filter-label">Date Range</span>
            <span class="filter-val">{{ $filters['date_from'] }} - {{ $filters['date_to'] }}</span>
        </div>
        @if(request()->filled('shift_start') && request()->filled('shift_end'))
        <div class="filter-item">
            <span class="filter-label">Shift</span>
            <span class="filter-val">{{ request('shift_start') }} to {{ request('shift_end') }}</span>
        </div>
        @endif
    </div>

    <!-- Top Level KPI Cards -->
    @if(!empty($kpis))
    <div class="summary-cards">
        @foreach($kpis as $kpi)
            <div class="card-modern">
                <div class="card-label">{{ $kpi['label'] }}</div>
                <div class="card-val" style="color: {{ $kpi['color'] ?? 'var(--brand)' }}">{{ $kpi['value'] }}</div>
            </div>
        @endforeach
    </div>
    @endif

    <!-- Data Table -->
    <table style="width: 100%; margin-bottom: 24px;">
        <thead>
            <tr>
                @php
                    $keys = count($data) > 0 ? array_keys((array)$data[0]) : [];
                    $hiddenCols = ['id', 'action', 'checkbox', 'dt_row'];
                @endphp
                @foreach($keys as $key)
                    @if(!in_array(strtolower($key), $hiddenCols))
                        <th>{{ ucwords(str_replace('_', ' ', $key)) }}</th>
                    @endif
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if(count($data) === 0)
                <tr><td colspan="20" style="text-align: center; color: var(--muted);">No records found for the selected filters</td></tr>
            @else
                @foreach($data as $row)
                    <tr>
                        @foreach((array)$row as $key => $value)
                            @if(!in_array(strtolower($key), $hiddenCols))
                                <td>{!! $value !!}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <!-- Signatures -->
    <div class="signatures">
        <div class="sig-box">
            <div class="sig-line"></div>
            <strong>{{ $printer }}</strong><br>
            <small>Auditor / Operations</small>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <small>Authorized Signature</small>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        {{ $appsettings->site_name ?? 'Hospital' }} - Operations Audit Report
    </div>

</div>
</div>
<script>
    // Trigger print automatically when loaded
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500);
    };
</script>
</body>
</html>
