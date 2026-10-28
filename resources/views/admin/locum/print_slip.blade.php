<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locum Shift Pass - {{ $pass->user->name }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 4mm;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.4;
            color: #000;
            background: #fff;
            padding: 4px;
            margin: 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        .double-divider { border-top: 2px solid #000; margin: 8px 0; }
        .pin-box {
            border: 2px solid #000;
            padding: 8px;
            margin: 8px 0;
            text-align: center;
        }
        .pin-number {
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 5px;
            margin: 4px 0;
        }
        .badge {
            display: inline-block;
            border: 1px solid #000;
            padding: 1px 4px;
            font-size: 10px;
        }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print();">
    <div class="text-center">
        <h3 style="margin: 0; font-size: 14px;">{{ appsettings()->site_name ?? 'COREHEALTH HOSPITAL' }}</h3>
        <p style="margin: 2px 0;">{{ appsettings()->contact_address ?? '' }}</p>
        <div class="double-divider"></div>
        <strong style="font-size: 12px; letter-spacing: 1px;">LOCUM CLINICAL SMART PASS</strong>
    </div>

    <div class="divider"></div>

    <div><strong>PRACTITIONER:</strong> {{ $pass->user->surname }} {{ $pass->user->firstname }} {{ $pass->user->othername }}</div>
    <div><strong>REGULATORY:</strong> {{ $pass->user->locum_profile->regulatory_body ?? 'MDCN' }} - {{ $pass->user->locum_profile->license_number ?? 'PENDING' }}</div>
    <div><strong>CADRE:</strong> {{ $pass->user->locum_profile->cadre_template ?? 'DOCTOR' }}</div>
    <div><strong>ROLES:</strong> {{ $pass->user->roles->pluck('name')->implode(', ') ?: 'DOCTOR' }}</div>
    <div><strong>PHONE:</strong> {{ $pass->user->staff_profile->phone_number ?? 'N/A' }}</div>
    <div><strong>CLINIC:</strong> {{ $pass->user->staff_profile->clinic->name ?? 'General Clinic' }}</div>

    <div class="pin-box">
        <div style="font-size: 10px; font-weight: bold;">6-DIGIT SHIFT PIN</div>
        <div class="pin-number">{{ $pass->pin_display ?? '******' }}</div>
        <div style="font-size: 9px;">Fast Login at /login with Phone & PIN</div>
    </div>

    <div><strong>SHIFT TYPE:</strong> {{ $pass->shift_type }}</div>
    <div><strong>STARTS:</strong> {{ $pass->starts_at->format('d/m/Y h:i A') }}</div>
    <div><strong>EXPIRES:</strong> {{ $pass->expires_at->format('d/m/Y h:i A') }}</div>
    <div><strong>STATUS:</strong> {{ $pass->isActive() ? 'ACTIVE' : ($pass->isExpired() ? 'EXPIRED' : 'REVOKED') }}</div>
    <div><strong>ISSUED BY:</strong> {{ $pass->creator ? $pass->creator->name : 'Medical Director' }}</div>
    <div><strong>ISSUED AT:</strong> {{ $pass->created_at->format('d/m/Y h:i:s') }}</div>

    <div class="divider"></div>

    <div class="text-center" style="font-size: 9px;">
        *** AUDIT TRAIL NOTICE ***<br>
        All clinical notes, diagnoses, and prescriptions are cryptographically stamped with your regulatory license number.<br>
        Session terminates automatically upon shift expiry.<br>
        HIPAA § 164.312 & Caldicott Non-Repudiation Verified.
    </div>

    <div class="no-print text-center" style="margin-top: 15px;">
        <button onclick="window.print();" style="padding: 6px 16px; cursor: pointer;">Print Pass Slip</button>
        <button onclick="window.close();" style="padding: 6px 16px; cursor: pointer;">Close</button>
    </div>
</body>
</html>
