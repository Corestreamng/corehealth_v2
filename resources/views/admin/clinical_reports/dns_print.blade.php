<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS Clinical Report & Census - {{ $report['period']['formatted'] ?? '' }}</title>
    <style>
        :root {
            --brand: {{ appsettings('hos_color', '#011b33') }};
            --ink: #0f172a;
            --muted: #475569;
            --border: #cbd5e1;
            --bg-light: #f8fafc;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 8.5pt;
            color: var(--ink);
            background: #fff;
            padding: 18px;
        }
        .no-print-bar {
            background: var(--bg-light);
            padding: 10px 15px;
            border-bottom: 2px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .no-print-bar button {
            padding: 6px 14px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 8.5pt;
        }
        .btn-print { background: var(--brand); color: #fff; }
        .btn-close-window { background: #64748b; color: #fff; margin-left: 8px; }
        @media print {
            .no-print-bar { display: none !important; }
            body { padding: 0; }
            .page-break { page-break-before: always; }
        }
        .header-box {
            border-bottom: 2px solid var(--brand);
            padding-bottom: 10px;
            margin-bottom: 12px;
            text-align: center;
        }
        .header-box h1 {
            font-size: 14pt;
            font-weight: 800;
            color: var(--brand);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-box h2 {
            font-size: 11pt;
            font-weight: 700;
            color: #1e293b;
            margin-top: 2px;
            text-transform: uppercase;
        }
        .header-box h3 {
            font-size: 9.5pt;
            font-weight: 600;
            color: #334155;
            margin-top: 3px;
        }
        .header-meta {
            font-size: 8pt;
            color: var(--muted);
            margin-top: 5px;
            display: flex;
            justify-content: space-between;
            border-top: 1px dashed var(--border);
            padding-top: 4px;
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
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-bottom: 6px;
        }
        th, td {
            border: 1px solid var(--border);
            padding: 4px 6px;
            vertical-align: middle;
        }
        th {
            background-color: var(--bg-light);
            font-weight: 700;
            color: #1e293b;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .font-bold { font-weight: 700; }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }
        .kpi-card {
            border: 1px solid var(--border);
            border-left: 4px solid var(--brand);
            padding: 6px 10px;
            background: var(--bg-light);
            border-radius: 3px;
        }
        .kpi-title { font-size: 7.5pt; color: var(--muted); text-transform: uppercase; font-weight: 600; }
        .kpi-val { font-size: 13pt; font-weight: 800; color: #0f172a; margin-top: 2px; }
        .ward-strip-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 6px;
            margin-bottom: 10px;
        }
        .ward-mini-card {
            border: 1px solid var(--border);
            padding: 5px 8px;
            background: var(--bg-light);
            border-radius: 3px;
        }
        .ward-mini-code { font-weight: 800; font-size: 8pt; color: var(--brand); }
        .ward-mini-val { font-size: 10pt; font-weight: 700; margin-top: 1px; }
        .signatures {
            margin-top: 24px;
            page-break-inside: avoid;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }
        .sig-block {
            border-top: 1px solid #475569;
            padding-top: 4px;
            text-align: center;
            font-size: 7.5pt;
        }
        .sig-title { font-weight: 700; color: #1e293b; }
    </style>
</head>
<body>
    <div class="no-print-bar">
        <div>
            <strong>DNS Office Clinical Report & Ward Census</strong> — {{ $report['period']['formatted'] ?? '' }}
        </div>
        <div>
            <button class="btn-print" onclick="window.print()">Print Official Report</button>
            <button class="btn-close-window" onclick="window.close()">Close Window</button>
        </div>
    </div>

    {{-- Official Header --}}
    <div class="header-box">
        <h1>{{ $report['hospital']['name'] ?? 'HOSPITAL MANAGEMENT SYSTEM' }}</h1>
        @if(!empty($report['hospital']['address']))
            <p style="font-size: 8pt; color: var(--muted);">{{ $report['hospital']['address'] }} @if(!empty($report['hospital']['phone'])) | Tel: {{ $report['hospital']['phone'] }} @endif</p>
        @endif
        <h2>DIRECTOR OF NURSING SERVICES (DNS) OFFICE</h2>
        <h3>DAILY SHIFT / PERIODIC CLINICAL & WARD OCCUPANCY STATISTICS</h3>
        <div class="header-meta">
            <div><strong>Report Period:</strong> {{ $report['period']['formatted'] ?? 'N/A' }}</div>
            <div><strong>Generated At:</strong> {{ now()->format('d M Y, H:i') }} | <strong>System:</strong> CoreHealth v2</div>
        </div>
    </div>

    {{-- 6 Hero KPIs --}}
    <div class="kpi-grid">
        <div class="kpi-card" style="border-left-color: #2563eb;">
            <div class="kpi-title">GOPD Visits</div>
            <div class="kpi-val font-mono">{{ number_format($report['kpis']['gopd'] ?? 0) }}</div>
            <div style="font-size: 7pt; color: var(--muted);">General Outpatient</div>
        </div>
        <div class="kpi-card" style="border-left-color: #f59e0b;">
            <div class="kpi-title">POPD Visits</div>
            <div class="kpi-val font-mono">{{ number_format($report['kpis']['popd'] ?? 0) }}</div>
            <div style="font-size: 7pt; color: var(--muted);">Private Outpatient</div>
        </div>
        <div class="kpi-card" style="border-left-color: #475569;">
            <div class="kpi-title">Outpatient Total</div>
            <div class="kpi-val font-mono">{{ number_format($report['kpis']['total_outpatient'] ?? 0) }}</div>
            <div style="font-size: 7pt; color: var(--muted);">All active clinics</div>
        </div>
        <div class="kpi-card" style="border-left-color: #dc2626;">
            <div class="kpi-title">Active Inpatients</div>
            <div class="kpi-val font-mono">{{ number_format($report['kpis']['total_inpatients'] ?? 0) }}</div>
            <div style="font-size: 7pt; color: var(--muted);">Occupied beds across wards</div>
        </div>
        <div class="kpi-card" style="border-left-color: #16a34a;">
            <div class="kpi-title">Empty Beds Available</div>
            <div class="kpi-val font-mono">{{ number_format($report['kpis']['empty_beds'] ?? 0) }}</div>
            <div style="font-size: 7pt; color: var(--muted);">Ready for admission</div>
        </div>
        <div class="kpi-card" style="border-left-color: #0284c7;">
            <div class="kpi-title">Day Care / Emergency</div>
            <div class="kpi-val font-mono">{{ number_format($report['kpis']['day_care'] ?? 0) }}</div>
            <div style="font-size: 7pt; color: var(--muted);">A&E and same-day obs</div>
        </div>
    </div>

    {{-- Section 1: Key Indicators Summary Table --}}
    <div class="section-box">
        <div class="section-header">1. Operational Clinical & Departmental Indicators</div>
        <table>
            <thead>
                <tr>
                    <th class="text-left" style="width: 25%;">Clinical Indicator</th>
                    <th class="text-center" style="width: 15%;">Census / Count</th>
                    <th class="text-left" style="width: 35%;">Standard Definition / Reference</th>
                    <th class="text-left" style="width: 25%;">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="font-bold text-left">GOPD Consultations</td>
                    <td class="text-center font-mono font-bold">{{ number_format($report['kpis']['gopd'] ?? 0) }}</td>
                    <td>General Outpatient Department consultations</td>
                    <td>Non-private & general clinics</td>
                </tr>
                <tr>
                    <td class="font-bold text-left">POPD Consultations</td>
                    <td class="text-center font-mono font-bold">{{ number_format($report['kpis']['popd'] ?? 0) }}</td>
                    <td>Private Outpatient Department consultations</td>
                    <td>Private retainers & self-paying suites</td>
                </tr>
                <tr>
                    <td class="font-bold text-left">Inpatient Admissions</td>
                    <td class="text-center font-mono font-bold">{{ number_format($report['kpis']['total_admissions'] ?? 0) }}</td>
                    <td>New patients admitted into hospital wards</td>
                    <td>During reported period</td>
                </tr>
                <tr>
                    <td class="font-bold text-left">Inpatient Discharges</td>
                    <td class="text-center font-mono font-bold">{{ number_format($report['kpis']['total_discharges'] ?? 0) }}</td>
                    <td>Formal medical discharges from wards</td>
                    <td>Routine & specialized</td>
                </tr>
                <tr>
                    <td class="font-bold text-left">Signed Against Medical Advice (SAMA)</td>
                    <td class="text-center font-mono font-bold text-danger">{{ number_format($report['kpis']['sama'] ?? 0) }}</td>
                    <td>Discharges initiated by patient / relatives (AMA)</td>
                    <td>Legal discharge document signed</td>
                </tr>
                <tr>
                    <td class="font-bold text-left">Absconsion (Left Without Notice)</td>
                    <td class="text-center font-mono font-bold text-danger">{{ number_format($report['kpis']['absconsion'] ?? 0) }}</td>
                    <td>Patient eloped or departed ward unauthorized</td>
                    <td>Reported to security & DNS</td>
                </tr>
                <tr>
                    <td class="font-bold text-left">Referrals & Outward Transfers</td>
                    <td class="text-center font-mono font-bold">{{ number_format($report['kpis']['referrals'] ?? 0) }}</td>
                    <td>Referred to tertiary / external medical facilities</td>
                    <td>Continuity of care</td>
                </tr>
                <tr>
                    <td class="font-bold text-left">Normal Deliveries (SVD / Vaginal)</td>
                    <td class="text-center font-mono font-bold">{{ number_format($report['kpis']['normal_delivery'] ?? 0) }}</td>
                    <td>Spontaneous vertex & assisted vaginal deliveries</td>
                    <td>Maternity / Labour Ward</td>
                </tr>
                <tr>
                    <td class="font-bold text-left">Caesarean Sections (CS)</td>
                    <td class="text-center font-mono font-bold">{{ number_format($report['kpis']['cs_delivery'] ?? 0) }}</td>
                    <td>Elective & emergency caesarean deliveries</td>
                    <td>CS Rate: {{ $report['kpis']['cs_rate'] ?? 0 }}% of total deliveries</td>
                </tr>
                <tr>
                    <td class="font-bold text-left">Completed Surgical Operations</td>
                    <td class="text-center font-mono font-bold">{{ number_format($report['kpis']['total_surgeries'] ?? 0) }}</td>
                    <td>Major and minor surgical interventions</td>
                    <td>Operating Theatres</td>
                </tr>
                <tr>
                    <td class="font-bold text-left">Total Mortalities / Deaths</td>
                    <td class="text-center font-mono font-bold">{{ number_format($report['kpis']['total_deaths'] ?? 0) }}</td>
                    <td>Clinical deaths recorded in wards & emergency</td>
                    <td>Death certificates issued</td>
                </tr>
                <tr>
                    <td class="font-bold text-left">Morgue Received (Corpses)</td>
                    <td class="text-center font-mono font-bold">{{ number_format($report['kpis']['corpses'] ?? 0) }}</td>
                    <td>Corpses received into facility mortuary</td>
                    <td>Mortuary admissions</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Section 2: Inpatient Ward Bed & Occupancy Census --}}
    <div class="section-box">
        <div class="section-header">2. Inpatient Ward Bed & Occupancy Census</div>
        
        {{-- Ward Summary Strip for All Active Wards --}}
        <div class="ward-strip-grid" style="margin-top: 6px;">
            @foreach($report['ward_census']['wards'] ?? [] as $w)
                <div class="ward-mini-card">
                    <div class="d-flex justify-content-between">
                        <span class="ward-mini-code">{{ $w['code'] }}</span>
                        <span style="font-size: 7.5pt; font-weight: 700; color: #475569;">{{ $w['occupancy_rate'] }}</span>
                    </div>
                    <div class="ward-mini-val font-mono">{{ $w['occupied'] }} <small style="font-size: 7pt; font-weight: 400; color: var(--muted);">/ {{ $w['total_beds'] }}</small></div>
                    <div style="font-size: 6.5pt; color: #16a34a;">{{ $w['available'] }} empty</div>
                </div>
            @endforeach
        </div>

        <table>
            <thead>
                <tr>
                    <th class="text-left" style="width: 25%;">Ward Name</th>
                    <th class="text-left" style="width: 25%;">Specialty / Description</th>
                    <th class="text-center" style="width: 12%;">Total Bed Capacity</th>
                    <th class="text-center" style="width: 12%;">Active Inpatients</th>
                    <th class="text-center" style="width: 12%;">Available Empty Beds</th>
                    <th class="text-center" style="width: 14%;">Bed Occupancy Rate</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['ward_census']['wards'] ?? [] as $w)
                    <tr>
                        <td class="text-left font-bold">{{ $w['ward_name'] }}</td>
                        <td class="text-left text-muted">{{ $w['specialty'] ?? 'General Ward' }}</td>
                        <td class="text-center font-mono">{{ $w['total_beds'] }}</td>
                        <td class="text-center font-mono font-bold">{{ $w['occupied'] }}</td>
                        <td class="text-center font-mono font-bold">{{ $w['available'] }}</td>
                        <td class="text-center font-mono font-bold">{{ $w['occupancy_rate'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No active inpatient wards found.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="background: #f1f5f9; font-weight: 700;">
                    <td colspan="2" class="text-left font-bold">HOSPITAL-WIDE SUMMARY / TOTAL</td>
                    <td class="text-center font-mono">{{ $report['ward_census']['total_beds'] ?? 0 }}</td>
                    <td class="text-center font-mono font-bold">{{ $report['ward_census']['occupied'] ?? 0 }}</td>
                    <td class="text-center font-mono font-bold">{{ $report['ward_census']['available'] ?? 0 }}</td>
                    <td class="text-center font-mono font-bold">{{ $report['ward_census']['occupancy_rate'] ?? '0%' }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Section 3: All-Clinics Outpatient Throughput --}}
    <div class="section-box">
        <div class="section-header">3. Outpatient Consultation Volume by Clinic</div>
        <table>
            <thead>
                <tr>
                    <th class="text-left" style="width: 50%;">Clinic Name</th>
                    <th class="text-center" style="width: 25%;">Attended Encounters</th>
                    <th class="text-center" style="width: 25%;">Proportion of Total Outpatient Volume</th>
                </tr>
            </thead>
            <tbody>
                @php $totalClinicsCount = 0; @endphp
                @forelse($report['clinics'] ?? [] as $c)
                    @php $totalClinicsCount += $c['total']; @endphp
                    <tr>
                        <td class="text-left font-bold">{{ $c['name'] }}</td>
                        <td class="text-center font-mono font-bold">{{ number_format($c['total']) }}</td>
                        <td class="text-center font-mono">{{ $c['percentage'] }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center">No outpatient clinics configured.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr style="background: #f1f5f9; font-weight: 700;">
                    <td class="text-left font-bold">TOTAL OUTPATIENT CONSULTATIONS</td>
                    <td class="text-center font-mono font-bold">{{ number_format($totalClinicsCount) }}</td>
                    <td class="text-center font-mono font-bold">100.0%</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Section 4: Day Care & Emergency Intake Analysis --}}
    <div class="section-box">
        <div class="section-header">4. Day Care & Emergency Intake Census</div>
        <table>
            <thead>
                <tr>
                    <th class="text-left" style="width: 50%;">Intake Category</th>
                    <th class="text-center" style="width: 25%;">Patient Volume</th>
                    <th class="text-left" style="width: 25%;">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-left">Emergency Intake Queue Consultations (A&E)</td>
                    <td class="text-center font-mono font-bold">{{ number_format($report['kpis']['emergency_intakes'] ?? 0) }}</td>
                    <td>Walk-ins and urgent triage cases</td>
                </tr>
                <tr>
                    <td class="text-left">Emergency Priority Inpatient Admissions</td>
                    <td class="text-center font-mono font-bold">{{ number_format($report['kpis']['emergency_admissions'] ?? 0) }}</td>
                    <td>Admitted directly via emergency route</td>
                </tr>
                <tr>
                    <td class="text-left">Same-Day Observation Discharges</td>
                    <td class="text-center font-mono font-bold">{{ number_format($report['kpis']['same_day_observations'] ?? 0) }}</td>
                    <td>Short-stay observations admitted & discharged same day</td>
                </tr>
                <tr style="background: #f1f5f9; font-weight: 700;">
                    <td class="text-left font-bold">COMBINED DAY CARE VOLUME</td>
                    <td class="text-center font-mono font-bold">{{ number_format($report['kpis']['day_care'] ?? 0) }}</td>
                    <td>Total day care throughput</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Official Authentication Sign-off --}}
    <div class="signatures">
        <div class="sig-block">
            <div style="height: 35px;"></div>
            <div class="sig-title">NURSING SHIFT SUPERVISOR</div>
            <div style="color: var(--muted);">Name, Signature & Date</div>
        </div>
        <div class="sig-block">
            <div style="height: 35px;"></div>
            <div class="sig-title">DIRECTOR OF NURSING SERVICES (DNS)</div>
            <div style="color: var(--muted);">Office Stamp, Signature & Date</div>
        </div>
        <div class="sig-block">
            <div style="height: 35px;"></div>
            <div class="sig-title">CHIEF MEDICAL DIRECTOR / ADMINISTRATOR</div>
            <div style="color: var(--muted);">Approval Signature & Date</div>
        </div>
    </div>
</body>
</html>
