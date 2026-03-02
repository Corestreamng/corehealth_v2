<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Road to Health Card - Enrollment {{ $enrollment->id }}</title>
    <style>
        @page { size: A4 landscape; margin: 6mm 8mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Times New Roman', Times, serif;
            color: #333;
            font-size: 10px;
            line-height: 1.3;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .page {
            width: 100%;
            page-break-after: always;
            position: relative;
            min-height: 180mm;
        }
        .page:last-child { page-break-after: avoid; }

        /* ── Hospital Header ── */
        .hospital-header {
            text-align: center;
            border-bottom: 3px double #006400;
            padding-bottom: 5px;
            margin-bottom: 6px;
        }
        .hospital-name {
            font-size: 20px;
            font-weight: 700;
            text-transform: uppercase;
            color: #006400;
            letter-spacing: 2px;
        }
        .hospital-sub { font-size: 10px; color: #666; margin-top: 1px; }
        .card-title {
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            color: #006400;
            letter-spacing: 1px;
            margin-top: 3px;
        }

        /* ── Layout ── */
        .cols-2 { display: flex; gap: 8px; }
        .cols-2 > div { flex: 1; }
        .cols-3 { display: flex; gap: 6px; }
        .cols-3 > div { flex: 1; }

        /* ── Panels ── */
        .section {
            border: 1.5px solid #006400;
            margin-bottom: 5px;
            page-break-inside: avoid;
        }
        .section-head {
            background: #006400;
            color: #fff;
            padding: 3px 7px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-body { padding: 4px 5px; }

        /* ── Info Grid ── */
        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td {
            padding: 2px 4px;
            vertical-align: top;
            border-bottom: 1px dotted #ccc;
        }
        .info-grid .lbl {
            font-weight: 700;
            width: 38%;
            color: #444;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }
        .info-grid .val { font-weight: 600; color: #222; }

        /* ── Data Tables ── */
        .data-table { width: 100%; border-collapse: collapse; font-size: 9.5px; }
        .data-table th {
            background: #e8f5e9;
            border: 1px solid #006400;
            padding: 3px 4px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8.5px;
            color: #006400;
            text-align: left;
        }
        .data-table td {
            border: 1px solid #bbb;
            padding: 2.5px 4px;
            vertical-align: top;
        }
        .data-table tr:nth-child(even) td { background: #f7fdf7; }
        .data-table .empty-row td { height: 14px; }

        /* ── Vaccination Table ── */
        .vax-table { width: 100%; border-collapse: collapse; font-size: 9px; }
        .vax-table th {
            background: #006400;
            color: #fff;
            border: 1px solid #006400;
            padding: 3px 4px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8px;
            text-align: center;
        }
        .vax-table td {
            border: 1px solid #999;
            padding: 2px 3px;
            text-align: center;
            font-size: 9px;
        }
        .vax-table .vax-name {
            text-align: left;
            font-weight: 700;
            background: #f0f7f0;
            width: 120px;
        }
        .vax-table tr:nth-child(even) td:not(.vax-name) { background: #fafff9; }
        .vax-group-header td {
            background: #d4edda !important;
            font-weight: 700;
            color: #006400;
            text-align: left !important;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ── Growth SVG ── */
        .growth-chart-container {
            border: 1.5px solid #006400;
            padding: 4px;
            margin-bottom: 5px;
        }
        .chart-panel-title {
            font-size: 11px;
            font-weight: 700;
            color: #006400;
            text-align: center;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        /* ── Footer ── */
        .page-footer {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 2px;
        }
        .highlight { color: #006400; font-weight: 700; }
        .muted { color: #888; font-style: italic; }
        .text-center { text-align: center; }
        .mb-4 { margin-bottom: 4px; }

        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

@php
    $firstBaby = $babies->first();
    $babySex = $firstBaby ? strtoupper(substr($firstBaby['model']->sex ?? 'M', 0, 1)) : 'M';
    $sexLabel = $babySex === 'F' ? 'Girls' : 'Boys';
    $whoData = $firstBaby ? ($firstBaby['whoWfa'] ?? []) : [];
    $fullGrowth = $firstBaby ? collect($firstBaby['growth'])->sortBy('age_months')->values() : collect();
    $vaxRows = $firstBaby ? collect($firstBaby['immunizations']) : collect();

    // Nigerian NPI Vaccination Schedule
    $npiSchedule = [
        ['group' => 'At Birth', 'vaccines' => [
            ['name' => 'B.C.G', 'key' => 'bcg'],
            ['name' => 'O.P.V. 0', 'key' => 'opv_0'],
            ['name' => 'H.B.V. 0', 'key' => 'hbv_0'],
        ]],
        ['group' => '6 Weeks', 'vaccines' => [
            ['name' => 'Penta 1', 'key' => 'penta_1'],
            ['name' => 'P.C.V. 1', 'key' => 'pcv_1'],
            ['name' => 'O.P.V. 1', 'key' => 'opv_1'],
            ['name' => 'Rota 1', 'key' => 'rota_1'],
        ]],
        ['group' => '10 Weeks', 'vaccines' => [
            ['name' => 'Penta 2', 'key' => 'penta_2'],
            ['name' => 'P.C.V. 2', 'key' => 'pcv_2'],
            ['name' => 'O.P.V. 2', 'key' => 'opv_2'],
            ['name' => 'Rota 2', 'key' => 'rota_2'],
        ]],
        ['group' => '14 Weeks', 'vaccines' => [
            ['name' => 'Penta 3', 'key' => 'penta_3'],
            ['name' => 'P.C.V. 3', 'key' => 'pcv_3'],
            ['name' => 'O.P.V. 3', 'key' => 'opv_3'],
            ['name' => 'Rota 3', 'key' => 'rota_3'],
            ['name' => 'I.P.V. 1', 'key' => 'ipv_1'],
        ]],
        ['group' => '6 Months', 'vaccines' => [
            ['name' => 'Vitamin A (1st)', 'key' => 'vita_1'],
            ['name' => 'I.P.V. 2', 'key' => 'ipv_2'],
        ]],
        ['group' => '9 Months', 'vaccines' => [
            ['name' => 'Measles 1', 'key' => 'measles_1'],
            ['name' => 'Yellow Fever', 'key' => 'yellow_fever'],
        ]],
        ['group' => '12 Months', 'vaccines' => [
            ['name' => 'Meningitis', 'key' => 'meningitis'],
            ['name' => 'Vitamin A (2nd)', 'key' => 'vita_2'],
        ]],
        ['group' => '15 Months', 'vaccines' => [
            ['name' => 'Measles 2', 'key' => 'measles_2'],
        ]],
    ];

    // Match immunization records to schedule
    $vaxMap = [];
    foreach ($vaxRows as $v) {
        $vName = strtolower(trim($v->vaccine_name ?? ''));
        $vaxMap[$vName] = $v;
    }

    // Chart dimension helpers
    function svgX($month, $monthMin, $monthMax, $chartW) {
        return round(40 + (($month - $monthMin) / ($monthMax - $monthMin)) * ($chartW - 50), 2);
    }
    function svgY($weight, $weightMin, $weightMax, $chartH) {
        return round($chartH - 20 - (($weight - $weightMin) / ($weightMax - $weightMin)) * ($chartH - 30), 2);
    }
@endphp

{{-- ═══════════════════════════════════════════════════════════════
     PAGE 1: INSIDE LEFT — Growth Curves (Birth to 3 Years)
     WHO Weight-for-Age with SVG curves + child data points
     ═══════════════════════════════════════════════════════════════ --}}
<div class="page">
    <div style="text-align:center; margin-bottom:4px;">
        <span class="card-title" style="font-size:13px;">Weight-for-Age Growth Chart ({{ $sexLabel }}) — Birth to 3 Years</span>
        <span style="font-size:9px; color:#888; margin-left:8px;">WHO Child Growth Standards</span>
    </div>

    {{-- ── Reasons for Special Care ── --}}
    <div class="section" style="margin-bottom:5px;">
        <div class="section-head" style="font-size:9px; padding:2px 6px;">Reasons for Special Care</div>
        <div class="section-body" style="padding:3px 5px; font-size:9px;">
            @if($firstBaby && ($firstBaby['model']->birth_weight_kg ?? 0) < 2.5)
                <span style="color:#dc3545; font-weight:700;">Low Birth Weight ({{ $firstBaby['model']->birth_weight_kg }} kg)</span> &nbsp;|&nbsp;
            @endif
            @if($firstBaby && ($firstBaby['model']->condition_at_birth ?? '') !== 'Normal' && !empty($firstBaby['model']->condition_at_birth))
                <span style="color:#dc3545;">Condition: {{ $firstBaby['model']->condition_at_birth }}</span> &nbsp;|&nbsp;
            @endif
            @if($enrollment->risk_level === 'high' || $enrollment->risk_level === 'very_high')
                <span style="color:#dc3545;">High Risk Pregnancy</span> &nbsp;|&nbsp;
            @endif
            <span class="muted">____________________________________________</span>
        </div>
    </div>

    {{-- ── Three SVG Growth Chart Panels ── --}}
    <div class="cols-3">
        @php
            $panels = [
                ['title' => 'Birth — 1 Year', 'monthMin' => 0, 'monthMax' => 12, 'weightMin' => 1, 'weightMax' => 14],
                ['title' => '1 — 2 Years', 'monthMin' => 12, 'monthMax' => 24, 'weightMin' => 6, 'weightMax' => 18],
                ['title' => '2 — 3 Years', 'monthMin' => 24, 'monthMax' => 36, 'weightMin' => 8, 'weightMax' => 22],
            ];
        @endphp

        @foreach($panels as $pi => $panel)
        <div class="growth-chart-container">
            <div class="chart-panel-title" style="font-size:9.5px;">{{ $panel['title'] }}</div>
            @php
                $chartW = 340;
                $chartH = 230;
                $mMin = $panel['monthMin'];
                $mMax = $panel['monthMax'];
                $wMin = $panel['weightMin'];
                $wMax = $panel['weightMax'];

                // Filter WHO data for this panel range
                $panelWho = array_filter($whoData, function($d) use ($mMin, $mMax) {
                    return $d['month'] >= $mMin && $d['month'] <= $mMax;
                });
                $panelWho = array_values($panelWho);

                // Build SVG polyline points for each SD line
                $sdKeys = ['sd_neg3', 'sd_neg2', 'sd_neg1', 'median', 'sd_pos1', 'sd_pos2', 'sd_pos3'];
                $sdColors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#ffc107', '#fd7e14', '#dc3545'];
                $sdWidths = [0.8, 1.0, 0.8, 1.8, 0.8, 1.0, 0.8];
                $sdDash = ['3,2', '2,1', '1,1', '', '1,1', '2,1', '3,2'];

                $polylines = [];
                foreach ($sdKeys as $si => $key) {
                    $pts = [];
                    foreach ($panelWho as $d) {
                        $x = svgX($d['month'], $mMin, $mMax, $chartW);
                        $y = svgY($d[$key], $wMin, $wMax, $chartH);
                        $pts[] = "$x,$y";
                    }
                    $polylines[$key] = implode(' ', $pts);
                }

                // Child data points in this range
                $childPts = $fullGrowth->filter(function($g) use ($mMin, $mMax) {
                    return ($g->age_months >= $mMin && $g->age_months <= $mMax && !empty($g->weight_kg));
                });
            @endphp
            <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" width="100%" height="auto" style="display:block;">
                {{-- Background zones --}}
                @if(count($panelWho) >= 2)
                    {{-- Severe underweight zone (below -3) --}}
                    <polygon points="{{ $polylines['sd_neg3'] }} {{ svgX($panelWho[count($panelWho)-1]['month'], $mMin, $mMax, $chartW) }},{{ $chartH - 20 }} {{ svgX($panelWho[0]['month'], $mMin, $mMax, $chartW) }},{{ $chartH - 20 }}" fill="#f8d7da" opacity="0.3" />
                    {{-- Normal zone (-2 to +2) --}}
                    @php
                        $normalTop = '';
                        $normalBottom = '';
                        foreach ($panelWho as $d) {
                            $normalTop .= svgX($d['month'], $mMin, $mMax, $chartW) . ',' . svgY($d['sd_pos2'], $wMin, $wMax, $chartH) . ' ';
                        }
                        foreach (array_reverse($panelWho) as $d) {
                            $normalBottom .= svgX($d['month'], $mMin, $mMax, $chartW) . ',' . svgY($d['sd_neg2'], $wMin, $wMax, $chartH) . ' ';
                        }
                    @endphp
                    <polygon points="{{ $normalTop }}{{ $normalBottom }}" fill="#d4edda" opacity="0.25" />
                @endif

                {{-- Grid lines --}}
                @for($m = $mMin; $m <= $mMax; $m += ($mMax - $mMin <= 12 ? 1 : 2))
                    @php $gx = svgX($m, $mMin, $mMax, $chartW); @endphp
                    <line x1="{{ $gx }}" y1="10" x2="{{ $gx }}" y2="{{ $chartH - 20 }}" stroke="#eee" stroke-width="0.5" />
                    @if($m % (($mMax - $mMin <= 12) ? 2 : 3) === 0)
                        <text x="{{ $gx }}" y="{{ $chartH - 8 }}" text-anchor="middle" font-size="7" fill="#666">{{ $m }}m</text>
                    @endif
                @endfor
                @for($w = $wMin; $w <= $wMax; $w += 2)
                    @php $gy = svgY($w, $wMin, $wMax, $chartH); @endphp
                    <line x1="40" y1="{{ $gy }}" x2="{{ $chartW - 10 }}" y2="{{ $gy }}" stroke="#eee" stroke-width="0.5" />
                    <text x="36" y="{{ $gy + 3 }}" text-anchor="end" font-size="7" fill="#666">{{ $w }}</text>
                @endfor

                {{-- Y-axis label --}}
                <text x="8" y="{{ $chartH / 2 }}" text-anchor="middle" font-size="8" fill="#006400" transform="rotate(-90, 8, {{ $chartH / 2 }})">Weight (kg)</text>

                {{-- SD reference lines --}}
                @foreach($sdKeys as $si => $key)
                    @if(!empty($polylines[$key]))
                        <polyline points="{{ $polylines[$key] }}" fill="none" stroke="{{ $sdColors[$si] }}" stroke-width="{{ $sdWidths[$si] }}" {!! $sdDash[$si] ? 'stroke-dasharray="' . $sdDash[$si] . '"' : '' !!} />
                    @endif
                @endforeach

                {{-- SD labels on the right edge --}}
                @if(count($panelWho) > 0)
                    @php $lastD = end($panelWho); @endphp
                    @foreach(['sd_neg3' => '-3', 'sd_neg2' => '-2', 'median' => 'M', 'sd_pos2' => '+2', 'sd_pos3' => '+3'] as $sdKey => $sdLbl)
                        <text x="{{ $chartW - 2 }}" y="{{ svgY($lastD[$sdKey], $wMin, $wMax, $chartH) + 3 }}" font-size="6" fill="{{ $sdLbl === 'M' ? '#28a745' : '#888' }}" font-weight="{{ $sdLbl === 'M' ? '700' : '400' }}">{{ $sdLbl }}</text>
                    @endforeach
                @endif

                {{-- Child data points --}}
                @foreach($childPts as $g)
                    @php
                        $cx = svgX($g->age_months, $mMin, $mMax, $chartW);
                        $cy = svgY($g->weight_kg, $wMin, $wMax, $chartH);
                        $ptColor = ($babySex === 'F') ? '#e91e63' : '#1976d2';
                    @endphp
                    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="3.5" fill="{{ $ptColor }}" stroke="#fff" stroke-width="0.8" />
                    <text x="{{ $cx + 5 }}" y="{{ $cy - 3 }}" font-size="6.5" fill="{{ $ptColor }}" font-weight="700">{{ $g->weight_kg }}</text>
                @endforeach

                {{-- Connect child data points with line --}}
                @if($childPts->count() > 1)
                    @php
                        $childLine = '';
                        foreach ($childPts as $g) {
                            $childLine .= svgX($g->age_months, $mMin, $mMax, $chartW) . ',' . svgY($g->weight_kg, $wMin, $wMax, $chartH) . ' ';
                        }
                    @endphp
                    <polyline points="{{ $childLine }}" fill="none" stroke="{{ $babySex === 'F' ? '#e91e63' : '#1976d2' }}" stroke-width="1.5" stroke-dasharray="4,2" />
                @endif
            </svg>
        </div>
        @endforeach
    </div>

    {{-- ── Food / Malaria tracking rows (like physical card) ── --}}
    <div style="display:flex; gap:4px; margin-top:4px;">
        <div style="flex:1; border:1px solid #006400; padding:3px 5px;">
            <strong style="font-size:9px; color:#006400;">FOOD:</strong>
            <span style="font-size:8.5px; color:#666;"> Breast only _____ | Mixed _____ | Complementary started (month) _____ | Weaned _____ </span>
        </div>
        <div style="flex:0.5; border:1px solid #006400; padding:3px 5px;">
            <strong style="font-size:9px; color:#006400;">MALARIA FILL:</strong>
            <span style="font-size:8.5px; color:#666;"> ITN: Yes / No | Prophylaxis: _____ </span>
        </div>
    </div>

    <div class="page-footer">
        <div>{{ appsettings('hos_name', 'O.L.A Hospital Jos') }} — Road to Health Card</div>
        <div>Baby: {{ $firstBaby && $firstBaby['user'] ? trim(($firstBaby['user']->surname ?? '') . ' ' . ($firstBaby['user']->firstname ?? '')) : 'N/A' }}</div>
        <div>Page 1 of 2 | Printed: {{ now()->format('d M Y H:i') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     PAGE 2: INSIDE RIGHT — Child Details, Vaccinations, Delivery
     Matches physical Nigerian Road to Health Card right-side panel
     ═══════════════════════════════════════════════════════════════ --}}
<div class="page">
    <div class="hospital-header">
        <div class="hospital-name">{{ appsettings('hos_name', 'O.L.A Hospital — Jos') }}</div>
        <div class="hospital-sub">{{ appsettings('hos_address', '') }}</div>
        <div class="card-title">Road to Health Card</div>
    </div>

    <div class="cols-2">
        {{-- ── Left Side: Child Details, Mother's Other Children, Delivery Records ── --}}
        <div>
            <div class="section">
                <div class="section-head">Child's Particulars</div>
                <div class="section-body">
                    <table class="info-grid">
                        <tr><td class="lbl">Child's No.</td><td class="val">{{ $firstBaby && $firstBaby['patient'] ? ($firstBaby['patient']->file_no ?? 'N/A') : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Child's Name</td><td class="val highlight">{{ $firstBaby && $firstBaby['user'] ? trim(($firstBaby['user']->surname ?? '') . ' ' . ($firstBaby['user']->firstname ?? '') . ' ' . ($firstBaby['user']->othername ?? '')) : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Date of Birth</td><td class="val">{{ $firstBaby && $firstBaby['patient'] && $firstBaby['patient']->dob ? \Carbon\Carbon::parse($firstBaby['patient']->dob)->format('d/m/Y') : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Time of Birth</td><td class="val">{{ $delivery && ($delivery->delivery_time ?? $delivery->delivery_date) ? \Carbon\Carbon::parse($delivery->delivery_time ?? $delivery->delivery_date)->format('H:i') : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Sex</td><td class="val">{{ ucfirst($firstBaby['model']->sex ?? 'N/A') }}</td></tr>
                        <tr><td class="lbl">Mother's Name</td><td class="val">{{ $motherUser ? trim(($motherUser->surname ?? '') . ' ' . ($motherUser->firstname ?? '')) : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Mother's Phone</td><td class="val">{{ $mother->phone_no ?? 'N/A' }}</td></tr>
                        <tr><td class="lbl">Father's Name</td><td class="val">{{ $mother->next_of_kin_name ?? 'N/A' }}</td></tr>
                        <tr><td class="lbl">Address</td><td class="val">{{ $mother->address ?? 'N/A' }}</td></tr>
                    </table>
                </div>
            </div>

            {{-- Mother's Other Children --}}
            <div class="section">
                <div class="section-head">Mother's Other Children</div>
                <div class="section-body">
                    <table class="data-table">
                        <thead><tr><th>Name</th><th>Sex</th><th>Age</th><th>Alive/Dead</th></tr></thead>
                        <tbody>
                        @php
                            $otherBabies = $enrollment->babies->filter(function($b) use ($firstBaby) {
                                return $firstBaby && $b->id !== $firstBaby['model']->id;
                            });
                        @endphp
                        @forelse($otherBabies as $ob)
                            <tr>
                                <td>{{ $ob->patient && $ob->patient->user ? trim(($ob->patient->user->surname ?? '') . ' ' . ($ob->patient->user->firstname ?? '')) : ('Baby #' . ($ob->birth_order ?? '?')) }}</td>
                                <td>{{ ucfirst($ob->sex ?? '-') }}</td>
                                <td>{{ $ob->patient && $ob->patient->dob ? \Carbon\Carbon::parse($ob->patient->dob)->diffForHumans(null, true) : '-' }}</td>
                                <td>ALIVE</td>
                            </tr>
                        @empty
                            @for($i = 0; $i < 4; $i++)
                                <tr class="empty-row"><td></td><td></td><td></td><td></td></tr>
                            @endfor
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Ante-Natal & Delivery Records --}}
            <div class="section">
                <div class="section-head">Ante-Natal / Delivery Records</div>
                <div class="section-body">
                    <table class="info-grid">
                        <tr><td class="lbl">Birth Weight</td><td class="val highlight">{{ $firstBaby && $firstBaby['model']->birth_weight_kg ? $firstBaby['model']->birth_weight_kg . ' kg' : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Apgar Score</td><td class="val">{{ $firstBaby ? (($firstBaby['model']->apgar_1_min ?? '-') . '/' . ($firstBaby['model']->apgar_5_min ?? '-') . '/' . ($firstBaby['model']->apgar_10_min ?? '-') . ' (1/5/10 min)') : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Head Circumference</td><td class="val">{{ $firstBaby && $firstBaby['model']->head_circumference_cm ? $firstBaby['model']->head_circumference_cm . ' cm' : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Chest Circumference</td><td class="val">{{ $firstBaby && $firstBaby['model']->chest_circumference_cm ? $firstBaby['model']->chest_circumference_cm . ' cm' : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Length</td><td class="val">{{ $firstBaby && $firstBaby['model']->length_cm ? $firstBaby['model']->length_cm . ' cm' : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Condition at Birth</td><td class="val">{{ $firstBaby ? ($firstBaby['model']->condition_at_birth ?? 'Normal') : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Type of Delivery</td><td class="val">{{ $delivery ? strtoupper($delivery->type_of_delivery ?? 'N/A') : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Date First Seen</td><td class="val">{{ $firstBaby && $firstBaby['model']->date_first_seen ? \Carbon\Carbon::parse($firstBaby['model']->date_first_seen)->format('d/m/Y') : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Other Remarks</td><td class="val">{{ $firstBaby ? ($firstBaby['model']->resuscitation ?? ($firstBaby['model']->remarks ?? 'None')) : 'N/A' }}</td></tr>
                    </table>
                </div>
            </div>

            {{-- Growth Data Table --}}
            <div class="section">
                <div class="section-head">Growth Monitoring Records</div>
                <div class="section-body">
                    <table class="data-table">
                        <thead><tr><th>Age (mo)</th><th>Wt (kg)</th><th>Ht (cm)</th><th>HC (cm)</th><th>WAZ</th><th>Status</th><th>Feeding</th></tr></thead>
                        <tbody>
                        @forelse($fullGrowth->take(12) as $g)
                            <tr>
                                <td>{{ $g->age_months ?? '-' }}</td>
                                <td>{{ $g->weight_kg ?? '-' }}</td>
                                <td>{{ $g->length_height_cm ?? '-' }}</td>
                                <td>{{ $g->head_circumference_cm ?? '-' }}</td>
                                <td style="{{ ($g->weight_for_age_z ?? 0) < -2 ? 'color:#dc3545;font-weight:700;' : '' }}">{{ $g->weight_for_age_z ?? '-' }}</td>
                                <td>{{ strtoupper(str_replace('_', ' ', $g->nutritional_status ?? '-')) }}</td>
                                <td>{{ str_replace('_', ' ', $g->feeding_method ?? '-') }}</td>
                            </tr>
                        @empty
                            @for($i = 0; $i < 6; $i++)
                                <tr class="empty-row"><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                            @endfor
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── Right Side: Vaccination Schedule ── --}}
        <div>
            <div class="section">
                <div class="section-head" style="font-size:11px; padding:4px 8px;">Nigerian NPI Vaccination Schedule</div>
                <div class="section-body" style="padding:2px;">
                    <table class="vax-table">
                        <thead>
                            <tr>
                                <th style="width:120px;">Vaccine</th>
                                <th style="width:80px;">Date</th>
                                <th>Remarks</th>
                                <th style="width:60px;">Sig.</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($npiSchedule as $group)
                            {{-- Group header row --}}
                            <tr class="vax-group-header">
                                <td colspan="4">{{ $group['group'] }}</td>
                            </tr>
                            @foreach($group['vaccines'] as $vax)
                                @php
                                    // Try to match immunization record by name similarity
                                    $matched = null;
                                    $searchKey = strtolower(str_replace(['.', ' '], '', $vax['name']));
                                    foreach ($vaxRows as $vr) {
                                        $vrName = strtolower(str_replace(['.', ' ', '_', '-'], '', $vr->vaccine_name ?? ''));
                                        if (str_contains($vrName, $searchKey) || str_contains($searchKey, $vrName) || $vrName === str_replace('_', '', $vax['key'])) {
                                            $matched = $vr;
                                            break;
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td class="vax-name">{{ $vax['name'] }}</td>
                                    <td>{!! $matched ? '<span style="color:#006400;font-weight:700;">' . \Carbon\Carbon::parse($matched->date_of_vaccination)->format('d/m/Y') . '</span>' : '' !!}</td>
                                    <td style="text-align:left; font-size:8.5px;">{{ $matched ? ($matched->notes ?? '') : '' }}</td>
                                    <td>{{ $matched && $matched->created_by ? substr(userfullname($matched->created_by), 0, 15) : '' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                        {{-- Other vaccines section --}}
                        <tr class="vax-group-header">
                            <td colspan="4">Others</td>
                        </tr>
                        @php
                            // Find any immunizations not matched by the NPI schedule
                            $npiNames = collect($npiSchedule)->pluck('vaccines')->flatten(1)->pluck('key')->map(function($k) {
                                return str_replace('_', '', $k);
                            })->toArray();
                            $otherVax = $vaxRows->filter(function($v) use ($npiNames) {
                                $vn = strtolower(str_replace(['.', ' ', '_', '-'], '', $v->vaccine_name ?? ''));
                                foreach ($npiNames as $nk) {
                                    if (str_contains($vn, $nk) || str_contains($nk, $vn)) return false;
                                }
                                return true;
                            });
                        @endphp
                        @forelse($otherVax as $ov)
                            <tr>
                                <td class="vax-name">{{ $ov->vaccine_name }}</td>
                                <td>{{ $ov->date_of_vaccination ? \Carbon\Carbon::parse($ov->date_of_vaccination)->format('d/m/Y') : '' }}</td>
                                <td style="text-align:left; font-size:8.5px;">{{ $ov->notes ?? '' }}</td>
                                <td>{{ $ov->created_by ? substr(userfullname($ov->created_by), 0, 15) : '' }}</td>
                            </tr>
                        @empty
                            @for($i = 0; $i < 3; $i++)
                                <tr class="empty-row"><td></td><td></td><td></td><td></td></tr>
                            @endfor
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Developmental Milestones --}}
            <div class="section">
                <div class="section-head">Developmental Milestones</div>
                <div class="section-body">
                    <table class="data-table">
                        <thead><tr><th>Milestone</th><th>Expected</th><th>Achieved</th></tr></thead>
                        <tbody>
                        @php
                            $milestones = [
                                ['Social smile', '6 weeks', ''],
                                ['Head control', '3 months', ''],
                                ['Turns to sound', '4 months', ''],
                                ['Sits without support', '6 months', ''],
                                ['Stands with support', '9 months', ''],
                                ['Walks alone', '12 months', ''],
                                ['Speaks single words', '12 months', ''],
                                ['Speaks sentences', '24 months', ''],
                            ];
                        @endphp
                        @foreach($milestones as $ms)
                            <tr>
                                <td style="font-weight:600;">{{ $ms[0] }}</td>
                                <td class="text-center">{{ $ms[1] }}</td>
                                <td></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Clinic Visit Summary --}}
            <div class="section">
                <div class="section-head">Clinic Attendance Summary</div>
                <div class="section-body" style="font-size:9px;">
                    <table class="info-grid">
                        <tr><td class="lbl">Total ANC Visits</td><td class="val">{{ $enrollment->ancVisits ? $enrollment->ancVisits->count() : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Delivery Date</td><td class="val">{{ $delivery && ($delivery->delivery_date ?? $delivery->date_of_delivery) ? \Carbon\Carbon::parse($delivery->delivery_date ?? $delivery->date_of_delivery)->format('d/m/Y') : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Growth Records</td><td class="val">{{ $fullGrowth->count() }} entries</td></tr>
                        <tr><td class="lbl">Immunizations</td><td class="val">{{ $vaxRows->count() }} given</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="page-footer">
        <div>{{ appsettings('hos_name', 'O.L.A Hospital Jos') }} — Road to Health Card</div>
        <div>Enrollment #{{ $enrollment->id }} | Mother: {{ $motherUser ? trim(($motherUser->surname ?? '') . ' ' . ($motherUser->firstname ?? '')) : 'N/A' }}</div>
        <div>Page 2 of 2 | Generated by CoreHealth Maternity Workbench</div>
    </div>
</div>

<script>
    window.onload = function () { window.print(); };
</script>
</body>
</html>
