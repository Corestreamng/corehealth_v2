<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NHMIS Monthly Summary Form (MSF) - {{ $report->month_name }} {{ $report->year }}</title>
    <style>
        :root {
            --brand: {{ appsettings('hos_color', '#011b33') }};
            --ink: #0f172a;
            --muted: #64748b;
            --border: #cbd5e1;
            --bg: #ffffff;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 8.5pt;
            color: var(--ink);
            background: #fff;
            padding: 15px;
        }
        .header-box {
            border-bottom: 2px solid var(--brand);
            padding-bottom: 10px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-title h2 {
            font-size: 13pt;
            font-weight: 800;
            color: var(--brand);
            text-transform: uppercase;
        }
        .header-title h4 {
            font-size: 9.5pt;
            font-weight: 600;
            color: #334155;
            margin-top: 2px;
        }
        .header-meta {
            font-size: 8pt;
            color: #475569;
            margin-top: 4px;
        }
        .header-badge {
            text-align: right;
            font-size: 8pt;
        }
        .section-box {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .section-header {
            background-color: #f1f5f9;
            padding: 4px 8px;
            font-size: 9pt;
            font-weight: 700;
            color: var(--brand);
            border: 1px solid var(--border);
            border-bottom: none;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-bottom: 6px;
        }
        th, td {
            border: 1px solid var(--border);
            padding: 3px 5px;
            text-align: center;
            vertical-align: middle;
        }
        th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #1e293b;
        }
        td.cell-label {
            text-align: left;
            font-weight: 500;
            min-width: 220px;
        }
        td.cell-num {
            width: 32px;
            font-weight: 700;
            color: #64748b;
        }
        td.cell-val {
            font-weight: 600;
        }
        .is-overridden-text {
            color: #1d4ed8;
            font-style: italic;
        }
        .no-print-bar {
            background: #f8fafc;
            padding: 10px 15px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        @media print {
            .no-print-bar { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print-bar">
        <div>
            <strong>NHMIS Monthly Summary (Form NHMIS/HF/MSF 2019)</strong> - Period: {{ $report->month_name }} {{ $report->year }}
        </div>
        <div>
            <button onclick="window.print()" style="padding: 6px 14px; background: var(--brand); color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 600;">Print Report</button>
            <button onclick="window.close()" style="padding: 6px 14px; background: #64748b; color: #fff; border: none; border-radius: 4px; cursor: pointer; margin-left: 8px;">Close</button>
        </div>
    </div>

    <div class="header-box">
        <div class="header-title">
            <h2>FEDERAL MINISTRY OF HEALTH, NIGERIA</h2>
            <h4>NATIONAL HEALTH MANAGEMENT INFORMATION SYSTEM (NHMIS) HEALTH FACILITY MONTHLY SUMMARY FORM</h4>
            <div class="header-meta">
                <strong>Facility:</strong> {{ $report->facility_name ?? appsettings('hospitalname', 'CoreHealth Facility') }} |
                <strong>LGA:</strong> {{ $report->lga ?? 'N/A' }} |
                <strong>State:</strong> {{ $report->state ?? 'N/A' }} |
                <strong>Period:</strong> {{ $report->month_name }} {{ $report->year }}
            </div>
        </div>
        <div class="header-badge">
            <div><strong>Version:</strong> {{ strtoupper($report->form_version) }}</div>
            <div><strong>Status:</strong> {{ strtoupper($report->status) }}</div>
            @if ($report->compiled_at)
                <div><strong>Compiled:</strong> {{ $report->compiled_at->format('d/m/Y') }}</div>
            @endif
        </div>
    </div>

    @foreach ($schema->getPages() as $pIndex => $page)
        <div style="margin-top: 15px; margin-bottom: 8px; border-bottom: 1px dashed var(--brand); padding-bottom: 4px;">
            <strong style="color: var(--brand); font-size: 10pt;">PAGE {{ $pIndex + 1 }}: {{ strtoupper($page['title']) }}</strong>
        </div>

        @foreach ($page['sections'] as $section)
            @php
                $columns = $section['columns'] ?? ['total' => ['label' => 'Total', 'is_total' => true]];
                $rows = $section['rows'] ?? [];
                $groups = [];
                foreach ($columns as $cKey => $col) {
                    $grp = $col['group'] ?? '';
                    $groups[$grp][$cKey] = $col;
                }
                $hasGroups = count(array_filter(array_keys($groups))) > 0;
            @endphp

            <div class="section-box">
                <div class="section-header">{{ $section['title'] }}</div>
                <table>
                    <thead>
                        @if ($hasGroups)
                            <tr>
                                <th rowspan="2" style="width: 32px;">#</th>
                                <th rowspan="2" style="text-align: left;">Data Element / Parameter</th>
                                @foreach ($groups as $grpName => $cols)
                                    @if ($grpName !== '')
                                        <th colspan="{{ count($cols) }}">{{ $grpName }}</th>
                                    @else
                                        @foreach ($cols as $cKey => $col)
                                            <th rowspan="2">{{ $col['label'] }}</th>
                                        @endforeach
                                    @endif
                                @endforeach
                            </tr>
                            <tr>
                                @foreach ($groups as $grpName => $cols)
                                    @if ($grpName !== '')
                                        @foreach ($cols as $cKey => $col)
                                            <th>{{ $col['label'] }}</th>
                                        @endforeach
                                    @endif
                                @endforeach
                            </tr>
                        @else
                            <tr>
                                <th style="width: 32px;">#</th>
                                <th style="text-align: left;">Data Element / Parameter</th>
                                @foreach ($columns as $cKey => $col)
                                    <th>{{ $col['label'] }}</th>
                                @endforeach
                            </tr>
                        @endif
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @if ($row['is_header'] ?? false)
                                <tr style="background: #f8fafc; font-weight: bold;">
                                    <td colspan="{{ 2 + count($columns) }}" style="text-align: left; padding: 4px 8px;">
                                        {{ $row['label'] }}
                                    </td>
                                </tr>
                            @else
                                @php
                                    $rowNum = $row['number'] ?? '';
                                    $rowId = $row['id'] ?? ('row_' . $rowNum);
                                @endphp
                                <tr>
                                    <td class="cell-num">{{ $rowNum }}</td>
                                    <td class="cell-label">{{ $row['label'] }}</td>
                                    @foreach ($columns as $cKey => $col)
                                        @php
                                            $primaryKey = "{$rowId}:{$cKey}";
                                            $altKey = "{$rowNum}:{$cKey}";
                                            $valRec = $values[$primaryKey] ?? ($values[$altKey] ?? null);
                                            $finalVal = $valRec ? $valRec->final_value : 0;
                                        @endphp
                                        <td class="cell-val">{{ $finalVal }}</td>
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endforeach
</body>
</html>
