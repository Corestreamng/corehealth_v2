<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ANC Card - {{ $motherUser ? trim(($motherUser->surname ?? '') . ' ' . ($motherUser->firstname ?? '')) : 'Patient' }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm 8mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Times New Roman', Times, serif;
            color: #333;
            font-size: 10.5px;
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
            border-bottom: 3px double #8B0000;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .hospital-name {
            font-size: 22px;
            font-weight: 700;
            text-transform: uppercase;
            color: #8B0000;
            letter-spacing: 2px;
        }
        .hospital-sub {
            font-size: 11px;
            color: #666;
            margin-top: 2px;
        }
        .card-title {
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            color: #8B0000;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        .file-no {
            font-size: 12px;
            font-weight: 700;
            margin-top: 2px;
        }

        /* ── Layout ── */
        .cols-3 {
            display: flex;
            gap: 8px;
        }
        .cols-3 > div { flex: 1; }
        .cols-2 {
            display: flex;
            gap: 8px;
        }
        .cols-2 > div { flex: 1; }

        /* ── Panels & Section Headers ── */
        .section {
            border: 1.5px solid #8B0000;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }
        .section-head {
            background: #8B0000;
            color: #fff;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-body {
            padding: 5px 6px;
        }

        /* ── Info Grid (label:value) ── */
        .info-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .info-grid td {
            padding: 2.5px 4px;
            vertical-align: top;
            border-bottom: 1px dotted #ccc;
        }
        .info-grid .lbl {
            font-weight: 700;
            width: 42%;
            color: #555;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .info-grid .val {
            font-weight: 600;
            color: #222;
        }

        /* ── Data Tables ── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .data-table th {
            background: #f5e6e6;
            border: 1px solid #8B0000;
            padding: 4px 5px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.3px;
            color: #8B0000;
            text-align: left;
        }
        .data-table td {
            border: 1px solid #bbb;
            padding: 3px 4px;
            vertical-align: top;
        }
        .data-table tr:nth-child(even) td {
            background: #fdf9f9;
        }
        .data-table .empty-row td {
            height: 16px;
        }

        /* ── ANC Visit Table (Big) ── */
        .anc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .anc-table th {
            background: #8B0000;
            color: #fff;
            border: 1px solid #8B0000;
            padding: 5px 4px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.3px;
            text-align: center;
            vertical-align: bottom;
        }
        .anc-table td {
            border: 1px solid #999;
            padding: 3px 4px;
            text-align: center;
            vertical-align: top;
            min-height: 20px;
        }
        .anc-table tr:nth-child(even) td {
            background: #fdf5f5;
        }
        .anc-table .col-date { width: 70px; }
        .anc-table .col-fundus { width: 72px; }
        .anc-table .col-pres { width: 100px; }
        .anc-table .col-fh { width: 65px; }
        .anc-table .col-oed { width: 55px; }
        .anc-table .col-urine { width: 60px; }
        .anc-table .col-wt { width: 55px; }
        .anc-table .col-hb { width: 45px; }
        .anc-table .col-bp { width: 60px; }
        .anc-table .col-treatment { min-width: 140px; }

        /* ── Risk Badge ── */
        .risk-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 3px;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
        }
        .risk-low { background: #d4edda; color: #155724; }
        .risk-moderate { background: #fff3cd; color: #856404; }
        .risk-high { background: #f8d7da; color: #721c24; }
        .risk-very_high { background: #721c24; color: #fff; }

        /* ── Footer ── */
        .page-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-between;
            font-size: 8.5px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 3px;
        }
        .highlight { color: #8B0000; font-weight: 700; }
        .muted { color: #888; font-style: italic; }
        .text-center { text-align: center; }
        .text-left { text-align: left !important; }
        .mb-4 { margin-bottom: 4px; }
        .mt-6 { margin-top: 6px; }

        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════════════
     PAGE 1: COVER — Demographics, Pregnancy Details, History
     Matches physical Nigerian ANC Card front page layout
     ═══════════════════════════════════════════════════════════════ --}}
<div class="page">
    <div class="hospital-header">
        <div class="hospital-name">{{ appsettings('hos_name', 'O.L.A Maternity — Jos') }}</div>
        <div class="hospital-sub">{{ appsettings('hos_address', '') }}</div>
        <div class="card-title">Ante-Natal Clinic Card</div>
        <div class="file-no">File No: {{ $mother->file_no ?? '________' }}</div>
    </div>

    <div class="cols-3">
        {{-- ── Left Column: Ante-Natal Clinic Demographics ── --}}
        <div>
            <div class="section">
                <div class="section-head">Ante-Natal Clinic</div>
                <div class="section-body">
                    <table class="info-grid">
                        <tr><td class="lbl">Name</td><td class="val">{{ $motherUser ? trim(($motherUser->surname ?? '') . ' ' . ($motherUser->firstname ?? '') . ' ' . ($motherUser->othername ?? '')) : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Address</td><td class="val">{{ $mother->address ?? 'N/A' }}</td></tr>
                        <tr><td class="lbl">Age</td><td class="val">{{ $mother && $mother->dob ? \Carbon\Carbon::parse($mother->dob)->age . ' years' : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Phone</td><td class="val">{{ $mother->phone_no ?? 'N/A' }}</td></tr>
                        <tr><td class="lbl">Next of Kin</td><td class="val">{{ $mother->next_of_kin_name ?? 'N/A' }}</td></tr>
                        <tr><td class="lbl">NOK Phone</td><td class="val">{{ $mother->next_of_kin_phone ?? 'N/A' }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="section">
                <div class="section-head">Obstetric Formula</div>
                <div class="section-body">
                    <table class="info-grid">
                        <tr><td class="lbl">L.M.P.</td><td class="val highlight">{{ $enrollment->lmp ? \Carbon\Carbon::parse($enrollment->lmp)->format('d/m/Y') : 'N/A' }}</td></tr>
                        <tr><td class="lbl">E.D.D.</td><td class="val highlight">{{ $enrollment->edd ? \Carbon\Carbon::parse($enrollment->edd)->format('d/m/Y') : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Gravida</td><td class="val">G{{ $enrollment->gravida ?? '?' }} P{{ $enrollment->parity ?? '?' }} A{{ $enrollment->alive ?? '?' }} Ab{{ $enrollment->abortion_miscarriage ?? '?' }}</td></tr>
                        <tr><td class="lbl">Entry Point</td><td class="val">{{ strtoupper($enrollment->entry_point ?? 'ANC') }}</td></tr>
                        <tr><td class="lbl">Booking Date</td><td class="val">{{ $enrollment->booking_date ? \Carbon\Carbon::parse($enrollment->booking_date)->format('d/m/Y') : now()->format('d/m/Y') }}</td></tr>
                        <tr><td class="lbl">Status</td><td class="val">{{ strtoupper($enrollment->status ?? 'ACTIVE') }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="section">
                <div class="section-head">Present Pregnancy</div>
                <div class="section-body">
                    <table class="info-grid">
                        <tr><td class="lbl">Blood Group</td><td class="val">{{ $enrollment->blood_group ?? 'N/A' }}</td></tr>
                        <tr><td class="lbl">Genotype</td><td class="val">{{ $enrollment->genotype ?? 'N/A' }}</td></tr>
                        <tr><td class="lbl">Height</td><td class="val">{{ $enrollment->height_cm ? $enrollment->height_cm . ' cm' : 'N/A' }}</td></tr>
                        <tr><td class="lbl">Weight</td><td class="val">{{ $enrollment->booking_weight_kg ? $enrollment->booking_weight_kg . ' kg' : 'N/A' }}</td></tr>
                        <tr><td class="lbl">BMI</td><td class="val">{{ $enrollment->booking_bmi ?? 'N/A' }}</td></tr>
                        <tr><td class="lbl">BP (Booking)</td><td class="val">{{ $enrollment->booking_bp ?? 'N/A' }}</td></tr>

                        <tr><td class="lbl">Risk Level</td><td class="val"><span class="risk-badge risk-{{ $enrollment->risk_level ?? 'low' }}">{{ strtoupper(str_replace('_', ' ', $enrollment->risk_level ?? 'LOW')) }}</span></td></tr>
                        <tr><td class="lbl">Risk Factors</td><td class="val">{{ is_array($enrollment->risk_factors) ? implode(', ', $enrollment->risk_factors) : ($enrollment->risk_factors ?? 'None identified') }}</td></tr>
                        <tr><td class="lbl">Preferred Place</td><td class="val">{{ $enrollment->preferred_delivery_place ?? 'N/A' }}</td></tr>
                        <tr><td class="lbl">Birth Plan</td><td class="val">{{ $enrollment->birth_plan_notes ?? 'N/A' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── Middle Column: Summary of Delivery, Postnatal, Record of Baby ── --}}
        <div>
            <div class="section">
                <div class="section-head">Summary of Delivery</div>
                <div class="section-body">
                    @if($delivery)
                        <table class="info-grid">
                            <tr><td class="lbl">Date of Delivery</td><td class="val">{{ $delivery->delivery_date ? \Carbon\Carbon::parse($delivery->delivery_date)->format('d/m/Y H:i') : 'N/A' }}</td></tr>
                            <tr><td class="lbl">Place</td><td class="val">{{ $delivery->place_of_delivery ?? 'N/A' }}</td></tr>
                            <tr><td class="lbl">Duration of Labour</td><td class="val">{{ $delivery->duration_of_labour_hours ? $delivery->duration_of_labour_hours . ' hrs' : 'N/A' }}</td></tr>
                            <tr><td class="lbl">Type of Delivery</td><td class="val highlight">{{ strtoupper($delivery->type_of_delivery ?? 'N/A') }}</td></tr>
                            <tr><td class="lbl">Episiotomy</td><td class="val">{{ $delivery->episiotomy ?? 'None' }}</td></tr>
                            <tr><td class="lbl">Complications</td><td class="val">{{ $delivery->complications ?? 'None' }}</td></tr>
                            <tr><td class="lbl">Blood Loss</td><td class="val">{{ $delivery->blood_loss_ml ? $delivery->blood_loss_ml . ' ml' : 'N/A' }}</td></tr>
                            <tr><td class="lbl">Perineal Tear</td><td class="val">{{ $delivery->perineal_tear_degree ?? 'None' }}</td></tr>
                            <tr><td class="lbl">Delivered By</td><td class="val">{{ $delivery->deliveredBy ? userfullname($delivery->deliveredBy->id) : 'N/A' }}</td></tr>
                        </table>
                    @else
                        <p class="muted" style="padding:8px 0;">Delivery not yet recorded.</p>
                    @endif
                </div>
            </div>

            <div class="section">
                <div class="section-head">Post Natal Check</div>
                <div class="section-body">
                    @if($postnatal->count())
                        <table class="data-table">
                            <thead><tr><th>Date</th><th>Type</th><th>Mother Condition</th><th>BP</th><th>Temp</th><th>Baby Condition</th><th>Feeding</th></tr></thead>
                            <tbody>
                            @foreach($postnatal as $pn)
                                <tr>
                                    <td>{{ $pn->visit_date ? \Carbon\Carbon::parse($pn->visit_date)->format('d/m/Y') : '-' }}</td>
                                    <td>{{ str_replace('_', ' ', $pn->visit_type ?? '-') }}</td>
                                    <td>{{ $pn->general_condition ?? '-' }}</td>
                                    <td>{{ $pn->blood_pressure ?? '-' }}</td>
                                    <td>{{ $pn->temperature_c ? $pn->temperature_c . '°C' : '-' }}</td>
                                    <td>{{ $pn->baby_general_condition ?? '-' }}</td>
                                    <td>{{ str_replace('_', ' ', $pn->baby_feeding ?? '-') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="muted" style="padding:4px 0;">No postnatal entries yet.</p>
                    @endif
                </div>
            </div>

            <div class="section">
                <div class="section-head">Record of Baby</div>
                <div class="section-body">
                    @forelse($enrollment->babies as $b)
                        <table class="info-grid mb-4">
                            <tr><td class="lbl">Baby</td><td class="val">{{ $b->patient && $b->patient->user ? trim(($b->patient->user->surname ?? '') . ' ' . ($b->patient->user->firstname ?? '')) : ('Baby #' . ($b->birth_order ?? 1)) }} — {{ ucfirst($b->sex ?? '?') }}</td></tr>
                            <tr><td class="lbl">Apgar Score</td><td class="val">{{ ($b->apgar_1_min ?? '-') }}/{{ ($b->apgar_5_min ?? '-') }}/{{ ($b->apgar_10_min ?? '-') }} <span style="color:#888;">(1/5/10 min)</span></td></tr>
                            <tr><td class="lbl">Birth Weight</td><td class="val highlight">{{ $b->birth_weight_kg ? $b->birth_weight_kg . ' kg' : 'N/A' }}</td></tr>
                            <tr><td class="lbl">Length</td><td class="val">{{ $b->length_cm ? $b->length_cm . ' cm' : 'N/A' }}</td></tr>
                            <tr><td class="lbl">Head Circumference</td><td class="val">{{ $b->head_circumference_cm ? $b->head_circumference_cm . ' cm' : 'N/A' }}</td></tr>
                            <tr><td class="lbl">Chest Circumference</td><td class="val">{{ $b->chest_circumference_cm ? $b->chest_circumference_cm . ' cm' : 'N/A' }}</td></tr>
                            <tr><td class="lbl">Status at Birth</td><td class="val">{{ ucfirst($b->status ?? 'alive') }}</td></tr>
                            <tr><td class="lbl">Special Care</td><td class="val">{{ $b->reasons_for_special_care ?? 'None' }}</td></tr>
                            <tr><td class="lbl">Resuscitation</td><td class="val">{{ $b->resuscitation ? 'Yes' . ($b->resuscitation_details ? ' — ' . $b->resuscitation_details : '') : 'No' }}</td></tr>
                        </table>
                        @if(!$loop->last)<hr style="border-top:1px dashed #ccc; margin:4px 0;">@endif
                    @empty
                        <p class="muted" style="padding:4px 0;">No baby records yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ── Right Column: Previous Pregnancies, Past Medical History ── --}}
        <div>
            <div class="section">
                <div class="section-head">Previous Pregnancies</div>
                <div class="section-body">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Year</th>
                            <th>Duration of Pregnancy</th>
                            <th>Ante-Natal Complications</th>
                            <th>Labour</th>
                            <th>Baby Alive/Dead</th>
                            <th>Sex</th>
                            <th>Age at Death</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($prevPregnancies as $p)
                            <tr>
                                <td>{{ $p->year ?? '-' }}</td>
                                <td>{{ $p->duration_weeks ? $p->duration_weeks . 'w' : ($p->duration_of_pregnancy ?? '-') }}</td>
                                <td>{{ $p->complications ?? $p->ante_natal_complications ?? '-' }}</td>
                                <td>{{ $p->type_of_labour ?? $p->labour_notes ?? '-' }}</td>
                                <td>
                                    @if(!empty($p->baby_alive_or_dead))
                                        {{ strtoupper($p->baby_alive_or_dead) }}
                                    @elseif(!empty($p->baby_alive))
                                        ALIVE
                                    @elseif(!empty($p->baby_dead))
                                        DEAD
                                    @elseif(!empty($p->baby_stillbirth))
                                        STILLBIRTH
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $p->sex ?? $p->baby_sex ?? '-' }}</td>
                                <td>{{ $p->age_at_death ?? '-' }}</td>
                            </tr>
                        @empty
                            {{-- Empty rows like the physical card --}}
                            @for($i = 0; $i < 8; $i++)
                                <tr class="empty-row"><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                            @endfor
                        @endforelse
                        @if($prevPregnancies->count() > 0 && $prevPregnancies->count() < 8)
                            @for($i = 0; $i < 8 - $prevPregnancies->count(); $i++)
                                <tr class="empty-row"><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                            @endfor
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section">
                <div class="section-head">Past Medical or Surgical History</div>
                <div class="section-body">
                    @if($medicalHistory && $medicalHistory->count())
                        <table class="data-table">
                            <thead><tr><th>Category</th><th>Description</th><th>Year</th><th>Notes</th></tr></thead>
                            <tbody>
                            @foreach($medicalHistory as $h)
                                <tr>
                                    <td>{{ strtoupper($h->category ?? '-') }}</td>
                                    <td>{{ $h->description ?? '-' }}</td>
                                    <td>{{ $h->year ?? '-' }}</td>
                                    <td>{{ $h->notes ?? '-' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="muted" style="padding:4px 0;">No past medical/surgical history recorded.</p>
                        <div style="height:60px; border-bottom:1px dotted #ccc;"></div>
                    @endif
                </div>
            </div>

            <div class="section">
                <div class="section-head">ANC Investigations</div>
                <div class="section-body">
                    @if($investigations && $investigations->count())
                        <table class="data-table">
                            <thead><tr><th>Investigation</th><th>Result</th><th>Routine</th></tr></thead>
                            <tbody>
                            @foreach($investigations as $inv)
                                <tr>
                                    <td>{{ $inv->investigation_name ?? '-' }}</td>
                                    <td>{{ $inv->result_summary ?? 'Pending' }}</td>
                                    <td>{{ $inv->is_routine ? 'Yes' : 'No' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="muted" style="padding:4px 0;">No investigations recorded.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="page-footer">
        <div>{{ appsettings('hos_name', 'O.L.A Hospital Jos') }} — ANC Card</div>
        <div>Enrollment #{{ $enrollment->id }} | File: {{ $mother->file_no ?? 'N/A' }}</div>
        <div>Page 1 of 2 | Printed: {{ now()->format('d M Y H:i') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     PAGE 2: ANC RECORDS TABLE — Matches physical card exactly
     Full ANC visit tracking table with all Nigerian card columns
     ═══════════════════════════════════════════════════════════════ --}}
<div class="page">
    <div style="text-align:center; margin-bottom:8px;">
        <span class="card-title" style="font-size:14px;">Ante-Natal Records — {{ $motherUser ? trim(($motherUser->surname ?? '') . ' ' . ($motherUser->firstname ?? '')) : 'Patient' }}</span>
        <span style="font-size:10px; color:#888; margin-left:12px;">File No: {{ $mother->file_no ?? 'N/A' }} | Enroll #{{ $enrollment->id }}</span>
    </div>

    <table class="anc-table">
        <thead>
        <tr>
            <th class="col-date">Date</th>
            <th class="col-fundus">Height of<br>Fundus</th>
            <th class="col-pres">Presentation<br>and Position</th>
            <th class="col-fh">Foetal<br>Heart</th>
            <th class="col-oed">Oedema</th>
            <th class="col-urine">Urine<br><span style="font-size:7.5px;font-weight:400;">(Album/Sugar)</span></th>
            <th class="col-wt">Weight</th>
            <th class="col-hb">H/B</th>
            <th class="col-bp">B.P</th>
            <th class="col-treatment">Treatment</th>
        </tr>
        </thead>
        <tbody>
        @forelse($ancVisits as $v)
            @php
                $bpVal = $v->blood_pressure ?? (($v->blood_pressure_systolic && $v->blood_pressure_diastolic) ? $v->blood_pressure_systolic . '/' . $v->blood_pressure_diastolic : '-');
                $bpParts = explode('/', $bpVal);
                $bpHighlight = (count($bpParts) == 2 && (intval($bpParts[0]) >= 140 || intval($bpParts[1]) >= 90));

                $urineStr = '';
                if (!empty($v->urine_protein) || !empty($v->urine_glucose)) {
                    $urineStr = ($v->urine_protein ?? 'nil') . '/' . ($v->urine_glucose ?? 'nil');
                } else {
                    $urineStr = '-';
                }
            @endphp
            <tr>
                <td>{{ $v->visit_date ? \Carbon\Carbon::parse($v->visit_date)->format('d/m/Y') : '-' }}</td>
                <td>{{ $v->fundal_height_cm ? $v->fundal_height_cm . ' cm' : ($v->height_of_fundus ?? '-') }}</td>
                <td class="text-left">{{ $v->presentation ?? ($v->presentation_and_position ?? '-') }}</td>
                <td>{!! $v->fetal_heart_rate ? (($v->fetal_heart_rate < 110 || $v->fetal_heart_rate > 160) ? '<span style="color:#dc3545;font-weight:700;">' . $v->fetal_heart_rate . '</span>' : $v->fetal_heart_rate) : '-' !!}</td>
                <td>{{ $v->oedema ?? '-' }}</td>
                <td>{{ $urineStr }}</td>
                <td>{{ $v->weight_kg ? $v->weight_kg . ' kg' : '-' }}</td>
                <td>{!! $v->haemoglobin ? (($v->haemoglobin < 11) ? '<span style="color:#dc3545;font-weight:700;">' . $v->haemoglobin . '</span>' : $v->haemoglobin) : '-' !!}</td>
                <td>{!! $bpHighlight ? '<span style="color:#dc3545;font-weight:700;">' . $bpVal . '</span>' : $bpVal !!}</td>
                <td class="text-left" style="font-size:9.5px;">{{ $v->treatment ?? $v->plan ?? '-' }}</td>
            </tr>
        @empty
            {{-- Empty rows like the physical card --}}
            @for($i = 0; $i < 20; $i++)
                <tr class="empty-row"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            @endfor
        @endforelse
        {{-- Pad remaining rows to fill the card --}}
        @if($ancVisits->count() > 0 && $ancVisits->count() < 20)
            @for($i = 0; $i < 20 - $ancVisits->count(); $i++)
                <tr class="empty-row"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            @endfor
        @endif
        </tbody>
    </table>

    <div class="page-footer">
        <div>{{ appsettings('hos_name', 'O.L.A Hospital Jos') }} — ANC Records</div>
        <div>Total ANC Visits: {{ $ancVisits->count() }}</div>
        <div>Page 2 of 2 | Generated by CoreHealth Maternity Workbench</div>
    </div>
</div>

<script>
    window.onload = function () { window.print(); };
</script>
</body>
</html>
