{{-- Prescription Slip - Clean A4 Style --}}
<!DOCTYPE html>
<html>
<head>
    <title>Prescription Slip - {{ $patient->file_no }}</title>
    <style>
        :root {
            --brand: {{ $appsettings->hos_color ?? '#0a6cf2' }};
            --ink: #1d1d1f;
            --muted: #5f6368;
            --border: #d7d9dc;
            --bg: #f7f9fb;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        .prescription-slip {
            padding: 24px;
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 14px;
            color: var(--ink);
            background: var(--bg);
        }

        .sheet {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.04);
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
        }

        .brand-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--brand);
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .brand-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-left img {
            width: 72px;
            height: auto;
        }

        .brand-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--brand);
            letter-spacing: 0.5px;
        }

        .brand-meta {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.4;
        }

        .doc-title {
            text-align: right;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--ink);
        }

        .doc-title small {
            display: block;
            font-size: 12px;
            color: var(--muted);
            font-weight: 500;
        }

        .details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 8px 16px;
            margin-bottom: 16px;
        }

        .details div {
            font-size: 13px;
            color: var(--ink);
        }

        .badge {
            display: inline-block;
            background: rgba(10,108,242,0.08);
            color: var(--brand);
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th, td {
            border: 1px solid var(--border);
            padding: 8px 10px;
            font-size: 13px;
        }

        thead th {
            background: rgba(10,108,242,0.06);
            color: var(--ink);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        tbody tr:nth-child(odd) {
            background: #fbfcfd;
        }

        tbody tr:nth-child(even) {
            background: #fff;
        }

        .dose-cell {
            color: var(--brand);
            font-weight: 500;
        }

        .hmo-badge {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            margin-top: 4px;
        }

        .instructions {
            margin-top: 16px;
            font-size: 12px;
            color: var(--muted);
            border-left: 3px solid var(--brand);
            padding-left: 10px;
        }

        .instructions ul {
            margin: 6px 0 0 16px;
            padding: 0;
        }

        .instructions li {
            margin-bottom: 2px;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 32px;
            gap: 40px;
        }

        .sig-box {
            flex: 1;
            text-align: center;
        }

        .sig-line {
            border-top: 1px solid var(--ink);
            margin-top: 40px;
            padding-top: 6px;
        }

        .sig-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--ink);
        }

        .sig-name {
            font-size: 11px;
            color: var(--muted);
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 11px;
            color: var(--muted);
            border-top: 1px solid var(--border);
            padding-top: 12px;
        }

        @media print {
            @page { size: A4; margin: 10mm; }
            .prescription-slip { background: #fff; padding: 0; }
            .sheet { box-shadow: none; border: none; margin: 0; width: 100%; max-width: none; padding: 0; }
        }
    </style>
</head>
<body>
<div class="prescription-slip">
<div class="sheet">
    <!-- Header -->
    <div class="brand-bar">
        <div class="brand-left">
            @if($appsettings->logo)
                <img src="data:image/png;base64,{{ $appsettings->logo }}" alt="Logo">
            @endif
            <div>
                <div class="brand-name">{{ $appsettings->site_name ?? 'HOSPITAL' }}</div>
                <div class="brand-meta">
                    {{ $appsettings->contact_address ?? '' }}<br>
                    @if($appsettings->contact_phones || $appsettings->contact_emails)
                        Phone: {{ $appsettings->contact_phones ?? 'N/A' }} | Email: {{ $appsettings->contact_emails ?? 'N/A' }}
                    @endif
                </div>
            </div>
        </div>
        <div class="doc-title">
            PRESCRIPTION
            <small>{{ $print_date }}</small>
        </div>
    </div>

    <!-- Patient Details -->
    <div class="details">
        <div><span class="badge">Patient</span><br>{{ userfullname($patient->user_id) }}</div>
        <div><span class="badge">File No.</span><br>{{ $patient->file_no }}</div>
        <div><span class="badge">Age / Gender</span><br>{{ $patient->dob ? \Carbon\Carbon::parse($patient->dob)->age . ' yrs' : 'N/A' }} / {{ $patient->gender ?? 'N/A' }}</div>
        <div><span class="badge">Date</span><br>{{ \Carbon\Carbon::now()->format('d M Y') }}</div>
        @if($patient->hmo)
            <div><span class="badge">HMO</span><br>{{ $patient->hmo->name }} ({{ $patient->hmo_no ?? 'N/A' }})</div>
        @endif
    </div>

    <!-- Prescriptions Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 30%;">Medication</th>
                <th style="width: 30%;">Dosage / Frequency</th>
                <th style="width: 8%;">Qty</th>
                <th style="width: 27%;">Prescribed By</th>
            </tr>
        </thead>
        <tbody>
            @foreach($prescriptions as $index => $prescription)
                @php
                    $posr = $prescription->productOrServiceRequest;
                    $hasHmo = $posr && $posr->coverage_mode && $posr->coverage_mode != 'none';
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $prescription->product->product_name }}</strong>
                        @if($prescription->product->product_code)
                            <br><small style="color: var(--muted);">[{{ $prescription->product->product_code }}]</small>
                        @endif
                        @if($hasHmo)
                            <br><span class="hmo-badge">HMO: ₦{{ number_format($posr->claims_amount ?? 0, 2) }} | Pay: ₦{{ number_format($posr->payable_amount ?? 0, 2) }}</span>
                        @endif
                    </td>
                    <td class="dose-cell">{{ $prescription->dose ?: 'As directed by physician' }}</td>
                    <td style="text-align: center; font-weight: 600;">{{ $prescription->qty ?? 1 }}</td>
                    <td>
                        {{ $prescription->doctor ? userfullname($prescription->doctor_id) : 'N/A' }}
                        @if($prescription->created_at)
                            <br><small style="color: var(--muted);">{{ \Carbon\Carbon::parse($prescription->created_at)->format('d M Y H:i') }}</small>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Instructions -->
    <div class="instructions">
        <strong>⚠️ Important Instructions:</strong>
        <ul>
            <li>Take medications exactly as prescribed by your doctor</li>
            <li>Complete the full course of treatment even if symptoms improve</li>
            <li>Consult your doctor if you experience any adverse reactions</li>
            <li>Keep medications out of reach of children</li>
        </ul>
    </div>

    <!-- Signatures -->
    <div class="signatures">
        <div class="sig-box">
            <div class="sig-line">
                <div class="sig-label">Attending Pharmacist</div>
                <div class="sig-name">{{ $pharmacist }}</div>
            </div>
        </div>
        <div class="sig-box">
            <div class="sig-line">
                <div class="sig-label">Patient / Guardian Signature</div>
                <div class="sig-name">_______________________</div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        {{ $appsettings->site_name ?? 'Hospital' }} | {{ $appsettings->contact_phones ?? '' }} | {{ $appsettings->contact_emails ?? '' }}<br>
        <em>This is an official prescription document. Please retain for your records.</em>
    </div>
</div>
</div>
</body>
</html>
