@php
    $sett = appsettings();
    $hosColor = $sett->hos_color ?? '#0066cc';

    // Compute age from patient.dob
    $ageText = 'N/A';
    if ($req->patient && $req->patient->dob) {
        $dob = \Carbon\Carbon::parse($req->patient->dob);
        $now = \Carbon\Carbon::now();
        $years = $dob->diffInYears($now);
        $months = $dob->copy()->addYears($years)->diffInMonths($now);
        $days = $dob->copy()->addYears($years)->addMonths($months)->diffInDays($now);
        $ageParts = [];
        if ($years > 0) $ageParts[] = $years . 'y';
        if ($months > 0) $ageParts[] = $months . 'm';
        if ($days > 0 || empty($ageParts)) $ageParts[] = $days . 'd';
        $ageText = implode(' ', $ageParts);
    }
    $dobText = ($req->patient && $req->patient->dob) ? \Carbon\Carbon::parse($req->patient->dob)->format('d/m/Y') : null;

    $patientGender = ($req->patient && $req->patient->gender) ? ucfirst($req->patient->gender) : 'N/A';
    $patientName = ($req->patient && $req->patient->user) ? userfullname($req->patient->user->id) : 'N/A';
    $patientFileNo = $req->patient->file_no ?? 'N/A';
    $patientHmo = ($req->patient && $req->patient->hmo) ? $req->patient->hmo->name : 'Private';
    $patientPhone = $req->patient->phone_no ?? ($req->patient && $req->patient->user ? $req->patient->user->phone : null);

    // Doctor fallback: productOrServiceRequest.user_id → doctor relation → encounter.doctor
    $doctorName = 'N/A';
    if ($req->productOrServiceRequest && $req->productOrServiceRequest->user_id) {
        $doctorName = userfullname($req->productOrServiceRequest->user_id);
    } elseif ($req->doctor) {
        $doctorName = userfullname($req->doctor->id);
    } elseif ($req->encounter && $req->encounter->doctor_id) {
        $doctorName = userfullname($req->encounter->doctor_id);
    }

    $reqNumber = str_pad($req->id, 7, '0', STR_PAD_LEFT);
    $resultByName = $req->resultBy ? userfullname($req->resultBy->id) : null;
    $approverName = ($req->approver) ? userfullname($req->approver->id) : null;
    $resultDate = $req->result_date ? \Carbon\Carbon::parse($req->result_date)->format('d M Y, h:i A') : null;
    $approvedDate = ($req->approved_at) ? \Carbon\Carbon::parse($req->approved_at)->format('d M Y, h:i A') : null;
    $serviceName = $req->service->service_name ?? 'Imaging Study';
    $priority = $req->priority ?? null;
    $note = $req->note ?? null;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Imaging Report - {{ $serviceName }} - {{ $patientName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 11px; padding: 10px; background: #f0f0f0; color: #222; line-height: 1.35; }
        .page { max-width: 800px; margin: 0 auto; background: #fff; }

        /* ─── Header ─── */
        .rpt-header { padding: 18px 24px 14px; border-bottom: 3px solid {{ $hosColor }}; }
        .rpt-header-top { display: flex; align-items: center; }
        .rpt-header .logo { width: 62px; height: 62px; margin-right: 16px; object-fit: contain; }
        .rpt-header .hos-info { flex: 1; }
        .rpt-header .hos-name { font-size: 20px; font-weight: 700; color: {{ $hosColor }}; text-transform: uppercase; letter-spacing: 1.5px; }
        .rpt-header .hos-tagline { font-size: 9.5px; color: #666; margin-top: 1px; font-style: italic; letter-spacing: 0.5px; }
        .rpt-header .hos-contact { font-size: 10px; color: #555; margin-top: 4px; line-height: 1.4; }
        .rpt-header .report-label { text-align: right; }
        .rpt-header .report-label .tag { display: inline-block; background: {{ $hosColor }}; color: #fff; font-size: 12px; font-weight: 700; padding: 5px 16px; letter-spacing: 3px; text-transform: uppercase; border-radius: 2px; }
        .rpt-header .report-label .req-no { font-size: 10.5px; color: #444; margin-top: 4px; font-weight: 600; }
        .rpt-header .report-label .rpt-date { font-size: 9.5px; color: #777; margin-top: 1px; }

        /* ─── Patient Banner ─── */
        .pt-banner { display: flex; flex-wrap: wrap; background: #f7f8fa; border-bottom: 1px solid #ddd; padding: 8px 24px; gap: 0; }
        .pt-banner .pt-col { flex: 1 1 50%; display: flex; flex-wrap: wrap; }
        .pt-field { padding: 2px 0; margin-right: 18px; white-space: nowrap; }
        .pt-field .lbl { font-weight: 600; color: #555; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
        .pt-field .val { font-weight: 600; color: #111; margin-left: 3px; }

        /* ─── Meta Row ─── */
        .meta-row { display: flex; flex-wrap: wrap; padding: 6px 24px; border-bottom: 1px solid #eee; background: #fff; gap: 0; }
        .meta-item { margin-right: 24px; padding: 2px 0; font-size: 10px; }
        .meta-item .lbl { font-weight: 600; color: #777; text-transform: uppercase; letter-spacing: 0.3px; }
        .meta-item .val { color: #222; margin-left: 3px; }
        .priority-urgent { color: #dc3545; font-weight: 700; }
        .priority-stat { color: #e67e00; font-weight: 700; }

        /* ─── Results ─── */
        .results-section { padding: 10px 24px 6px; }
        .results-heading { font-size: 12px; font-weight: 700; color: {{ $hosColor }}; text-transform: uppercase; letter-spacing: 1px; padding-bottom: 4px; border-bottom: 1.5px solid {{ $hosColor }}; margin-bottom: 6px; }
        .results-table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        .results-table thead { background: {{ $hosColor }}; color: #fff; }
        .results-table th { padding: 5px 8px; text-align: left; font-weight: 600; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .results-table td { padding: 4px 8px; border-bottom: 1px solid #e8e8e8; }
        .results-table tbody tr:nth-child(even) { background: #fafbfc; }
        .results-table .grp-header td { background: #eef1f5; font-weight: 700; color: #333; font-size: 10.5px; padding: 4px 8px; border-bottom: 1px solid #ccc; }
        .results-table .param-name { color: #333; padding-left: 12px; }
        .results-table .param-value { font-weight: 700; color: #111; }
        .results-table .ref-range { color: #666; font-size: 10px; }
        .results-table .status-flag { font-size: 9px; font-weight: 700; padding: 1px 6px; border-radius: 3px; display: inline-block; }
        .flag-normal { background: #d4edda; color: #155724; }
        .flag-high, .flag-low { background: #f8d7da; color: #721c24; }
        .flag-abnormal { background: #fff3cd; color: #856404; }
        .flag-critical { background: #721c24; color: #fff; }

        /* ─── V1 results ─── */
        .v1-results { padding: 8px 24px; line-height: 1.5; font-size: 11px; }
        .v1-results table { width: 100%; border-collapse: collapse; }
        .v1-results table td, .v1-results table th { padding: 4px 6px; border: 1px solid #ddd; font-size: 10.5px; }

        /* ─── Clinical Note ─── */
        .clinical-note { padding: 6px 24px 4px; }
        .clinical-note .note-label { font-size: 10px; font-weight: 700; color: #555; text-transform: uppercase; }
        .clinical-note .note-text { font-size: 10px; color: #333; margin-top: 2px; font-style: italic; border-left: 2px solid {{ $hosColor }}; padding-left: 8px; }

        /* ─── Attachments ─── */
        .attachments-section { padding: 6px 24px; }
        .attachments-section .att-title { font-size: 10px; font-weight: 700; color: #555; text-transform: uppercase; margin-bottom: 3px; }
        .att-list { font-size: 10px; color: #444; }
        .att-list span { margin-right: 16px; }

        /* ─── Signatures ─── */
        .sig-section { display: flex; justify-content: space-between; padding: 20px 24px 8px; }
        .sig-box { text-align: center; min-width: 160px; }
        .sig-line { border-top: 1.5px solid #333; margin-top: 30px; padding-top: 4px; }
        .sig-name { font-size: 10.5px; font-weight: 700; color: #111; }
        .sig-role { font-size: 9px; color: #666; }

        /* ─── Footer ─── */
        .rpt-footer { padding: 8px 24px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #ddd; }
        .rpt-footer p { margin: 1px 0; }

        /* ─── Print ─── */
        @media print {
            body { background: #fff; padding: 0; font-size: 10px; }
            .page { box-shadow: none; max-width: 100%; }
            .no-print { display: none !important; }
            .results-table th, .results-table td { padding: 3px 6px; }
        }
        .print-btn { position: fixed; top: 12px; right: 12px; background: {{ $hosColor }}; color: #fff; border: none; padding: 8px 18px; border-radius: 3px; cursor: pointer; font-size: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.2); z-index: 1000; }
        .print-btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨 Print</button>

    <div class="page">
        {{-- ═══ Header ═══ --}}
        <div class="rpt-header">
            <div class="rpt-header-top">
                @if($sett->logo)
                    <img src="data:image/jpeg;base64,{{ $sett->logo }}" alt="" class="logo">
                @endif
                <div class="hos-info">
                    <div class="hos-name">{{ $sett->site_name ?? 'Hospital' }}</div>
                    @if($sett->hos_tagline ?? false)
                        <div class="hos-tagline">{{ $sett->hos_tagline }}</div>
                    @endif
                    <div class="hos-contact">
                        @if($sett->contact_address){{ $sett->contact_address }}@endif
                        @if($sett->contact_phones)<br>Tel: {{ $sett->contact_phones }}@endif
                        @if($sett->contact_emails) &bull; {{ $sett->contact_emails }}@endif
                    </div>
                </div>
                <div class="report-label">
                    <div class="tag">Imaging Report</div>
                    <div class="req-no">Req # {{ $reqNumber }}</div>
                    <div class="rpt-date">{{ $resultDate ?? now()->format('d M Y') }}</div>
                </div>
            </div>
        </div>

        {{-- ═══ Patient Banner ═══ --}}
        <div class="pt-banner">
            <div class="pt-col">
                <div class="pt-field"><span class="lbl">Patient:</span> <span class="val">{{ $patientName }}</span></div>
                <div class="pt-field"><span class="lbl">File #:</span> <span class="val">{{ $patientFileNo }}</span></div>
                <div class="pt-field"><span class="lbl">Age:</span> <span class="val">{{ $ageText }}@if($dobText) <span style="font-weight:400;color:#666;">({{ $dobText }})</span>@endif</span></div>
                <div class="pt-field"><span class="lbl">Sex:</span> <span class="val">{{ $patientGender }}</span></div>
            </div>
            <div class="pt-col">
                <div class="pt-field"><span class="lbl">HMO:</span> <span class="val">{{ $patientHmo }}</span></div>
                @if($patientPhone)
                    <div class="pt-field"><span class="lbl">Phone:</span> <span class="val">{{ $patientPhone }}</span></div>
                @endif
                @if($req->patient && $req->patient->blood_group)
                    <div class="pt-field"><span class="lbl">Blood Grp:</span> <span class="val">{{ $req->patient->blood_group }}</span></div>
                @endif
            </div>
        </div>

        {{-- ═══ Meta Row ═══ --}}
        <div class="meta-row">
            <div class="meta-item"><span class="lbl">Requested by:</span> <span class="val">{{ $doctorName }}</span></div>
            @if($resultDate)
                <div class="meta-item"><span class="lbl">Result Date:</span> <span class="val">{{ $resultDate }}</span></div>
            @endif
            @if($priority && strtolower($priority) !== 'normal')
                <div class="meta-item">
                    <span class="lbl">Priority:</span>
                    <span class="val {{ strtolower($priority) === 'urgent' ? 'priority-urgent' : (strtolower($priority) === 'stat' ? 'priority-stat' : '') }}">{{ strtoupper($priority) }}</span>
                </div>
            @endif
            @if($approverName)
                <div class="meta-item"><span class="lbl">Approved by:</span> <span class="val">{{ $approverName }}</span></div>
            @endif
            @if($approvedDate)
                <div class="meta-item"><span class="lbl">Approved:</span> <span class="val">{{ $approvedDate }}</span></div>
            @endif
        </div>

        {{-- ═══ Results ═══ --}}
        <div class="results-section">
            <div class="results-heading">{{ $serviceName }}</div>

            @if($req->result_data && is_array($req->result_data) && $req->service && $req->service->result_template_v2)
                {{-- V2 Structured Results --}}
                @php
                    $template = $req->service->result_template_v2;
                    $parameters = $template['parameters'] ?? [];
                    $groupedParams = [];
                    foreach ($parameters as $param) {
                        $category = $param['category'] ?? $template['template_name'] ?? 'Results';
                        $groupedParams[$category][] = $param;
                    }
                @endphp

                <table class="results-table">
                    <thead>
                        <tr>
                            <th style="width:35%;">Parameter</th>
                            <th style="width:20%;">Result</th>
                            <th style="width:10%;">Unit</th>
                            <th style="width:25%;">Reference Range</th>
                            <th style="width:10%;">Flag</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($groupedParams as $category => $params)
                            @if(count($groupedParams) > 1)
                                <tr class="grp-header"><td colspan="5">{{ $category }}</td></tr>
                            @endif
                            @foreach($params as $param)
                                @if(isset($req->result_data[$param['id']]))
                                    @php
                                        $paramData = $req->result_data[$param['id']];
                                        $rawValue = $paramData['value'] ?? $paramData;
                                        $status = $paramData['status'] ?? 'normal';

                                        if ($param['type'] === 'boolean') {
                                            $displayValue = ($rawValue === true || $rawValue === 'true') ? 'Positive' : 'Negative';
                                        } elseif ($param['type'] === 'float' && is_numeric($rawValue)) {
                                            $displayValue = number_format((float)$rawValue, 2);
                                        } else {
                                            $displayValue = $rawValue;
                                        }

                                        $unit = $param['unit'] ?? '';

                                        $refRange = '';
                                        if (isset($param['reference_range'])) {
                                            $ref = $param['reference_range'];
                                            if (isset($ref['min']) && isset($ref['max'])) {
                                                $refRange = $ref['min'] . ' – ' . $ref['max'];
                                                if ($unit) $refRange .= ' ' . $unit;
                                            } elseif (isset($ref['reference_value'])) {
                                                $refRange = $param['type'] === 'boolean'
                                                    ? ($ref['reference_value'] ? 'Positive' : 'Negative')
                                                    : $ref['reference_value'];
                                            } elseif (isset($ref['text'])) {
                                                $refRange = $ref['text'];
                                            }
                                        }

                                        $flagClass = 'flag-normal';
                                        $flagLabel = '';
                                        if ($status === 'high') { $flagClass = 'flag-high'; $flagLabel = 'H'; }
                                        elseif ($status === 'low') { $flagClass = 'flag-low'; $flagLabel = 'L'; }
                                        elseif ($status === 'abnormal') { $flagClass = 'flag-abnormal'; $flagLabel = 'ABN'; }
                                        elseif ($status === 'critical_high' || $status === 'critical_low') { $flagClass = 'flag-critical'; $flagLabel = 'CRIT'; }
                                    @endphp
                                    <tr>
                                        <td class="param-name">{{ $param['name'] }}</td>
                                        <td class="param-value">{{ $displayValue }}</td>
                                        <td>{{ $unit }}</td>
                                        <td class="ref-range">{{ $refRange }}</td>
                                        <td>
                                            @if($flagLabel)
                                                <span class="status-flag {{ $flagClass }}">{{ $flagLabel }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @else
                {{-- V1 Legacy HTML Results --}}
                <div class="v1-results">
                    {!! $req->result ?? '<p style="color:#999;">No results available.</p>' !!}
                </div>
            @endif
        </div>

        {{-- ═══ Clinical Note ═══ --}}
        @if($note)
            <div class="clinical-note">
                <div class="note-label">Clinical Note</div>
                <div class="note-text">{{ $note }}</div>
            </div>
        @endif

        {{-- ═══ Attachments ═══ --}}
        @if($req->attachments)
            @php
                $attachments = is_string($req->attachments) ? json_decode($req->attachments, true) : $req->attachments;
            @endphp
            @if(!empty($attachments))
                <div class="attachments-section">
                    <div class="att-title">Attachments ({{ count($attachments) }})</div>
                    <div class="att-list">
                        @foreach($attachments as $att)
                            <span>📎 {{ $att['name'] }} <small style="color:#999;">({{ number_format(($att['size'] ?? 0) / 1024, 1) }} KB)</small></span>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif

        {{-- ═══ Signatures ═══ --}}
        <div class="sig-section">
            @if($resultByName)
                <div class="sig-box">
                    <div class="sig-line">
                        <div class="sig-name">{{ $resultByName }}</div>
                        <div class="sig-role">Radiologist / Imaging Technician</div>
                    </div>
                </div>
            @endif
            @if($approverName)
                <div class="sig-box">
                    <div class="sig-line">
                        <div class="sig-name">{{ $approverName }}</div>
                        <div class="sig-role">Approved &bull; {{ $approvedDate }}</div>
                    </div>
                </div>
            @endif
            <div class="sig-box">
                <div class="sig-line">
                    <div class="sig-name">Authorized Signature</div>
                    <div class="sig-role">{{ $resultDate ?? now()->format('d M Y') }}</div>
                </div>
            </div>
        </div>

        {{-- ═══ Footer ═══ --}}
        <div class="rpt-footer">
            <p>This is a computer-generated imaging report. Results should be interpreted in the context of clinical findings.</p>
            <p>{{ $sett->contact_phones ?? '' }} &bull; {{ $sett->contact_emails ?? '' }}</p>
            <p>Printed: {{ date('d M Y H:i') }}</p>
        </div>
    </div>
</body>
</html>
