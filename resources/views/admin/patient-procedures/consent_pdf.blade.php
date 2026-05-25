<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Informed Consent - {{ optional($procedure->service)->service_name ?? 'Procedure' }}</title>
    @php
        $hosColor = $sett->hos_color ?? '#0066cc';
    @endphp
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.6;
            color: #333;
            background: white;
            padding: 40px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 3px solid {{ $hosColor }};
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .logo-cell {
            width: 75px;
            vertical-align: middle;
        }
        .logo-img {
            max-height: 65px;
            max-width: 65px;
            object-fit: contain;
        }
        .hospital-cell {
            vertical-align: middle;
            padding-left: 10px;
        }
        .hospital-name {
            font-size: 18px;
            font-weight: bold;
            color: {{ $hosColor }};
            line-height: 1.2;
        }
        .contact-cell {
            text-align: right;
            vertical-align: middle;
            font-size: 9.5px;
            color: #555;
            line-height: 1.4;
        }
        .title-strip {
            background-color: {{ $hosColor }};
            color: white;
            text-align: center;
            padding: 8px 15px;
            font-size: 11.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 25px;
        }
        .document-content {
            font-size: 10.5px;
            color: #222;
            margin-bottom: 35px;
            text-align: justify;
        }
        .document-content p {
            margin-bottom: 12px;
        }
        .document-content h3, .document-content h4 {
            color: {{ $hosColor }};
            margin-top: 15px;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .document-content ul, .document-content ol {
            margin-left: 20px;
            margin-bottom: 12px;
        }
        .document-content li {
            margin-bottom: 4px;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .signature-cell {
            width: 50%;
            vertical-align: top;
            padding: 10px;
        }
        .details-box {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 12px;
            font-size: 10px;
        }
        .details-row {
            margin-bottom: 6px;
        }
        .details-label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            width: 110px;
        }
        .details-val {
            color: #111;
        }
        .signature-box {
            border: 1px dashed #ced4da;
            background-color: #fafafa;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
            min-height: 100px;
            display: block;
        }
        .signature-image {
            max-height: 55px;
            max-width: 200px;
            object-fit: contain;
        }
        .signature-line {
            border-top: 1px solid #888;
            width: 80%;
            margin: 10px auto 4px auto;
        }
        .signature-caption {
            font-size: 8.5px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #dee2e6;
            margin-top: 50px;
            padding-top: 10px;
            font-size: 8px;
            color: #777;
        }
    </style>
</head>
<body>

    <!-- Branded Table Header -->
    <table class="header-table">
        <tr>
            @if(!empty($sett->logo))
                <td class="logo-cell">
                    <img src="data:image/jpeg;base64,{{ $sett->logo }}" class="logo-img" alt="Logo" />
                </td>
            @endif
            <td class="hospital-cell">
                <div class="hospital-name">{{ $sett->site_name ?? 'Hospital Name' }}</div>
            </td>
            <td class="contact-cell">
                <div><strong>Address:</strong> {{ $sett->contact_address ?? 'N/A' }}</div>
                <div><strong>Phone:</strong> {{ $sett->contact_phones ?? 'N/A' }}</div>
                <div><strong>Email:</strong> {{ $sett->contact_emails ?? 'N/A' }}</div>
            </td>
        </tr>
    </table>

    <!-- Title Strip -->
    <div class="title-strip">
        DIGITAL INFORMED CONSENT DOCUMENT
    </div>

    <!-- Main Consent Content -->
    <div class="document-content">
        {!! $consentText !!}
    </div>

    <!-- Signature and Signee Identification Block -->
    <table class="signature-table">
        <tr>
            <!-- Signee Details -->
            <td class="signature-cell" style="padding-right: 15px;">
                <div class="details-box">
                    <div style="font-weight: bold; color: {{ $hosColor }}; font-size: 11px; margin-bottom: 10px; border-bottom: 1px solid #dee2e6; padding-bottom: 4px;">
                        Signee Identification
                    </div>
                    <div class="details-row">
                        <span class="details-label">Patient Name:</span>
                        <span class="details-val">{{ $procedure->patient ? userfullname($procedure->patient->user_id) : 'Unknown' }}</span>
                    </div>
                    <div class="details-row">
                        <span class="details-label">Signee Name:</span>
                        <span class="details-val"><strong>{{ $signeeName }}</strong></span>
                    </div>
                    <div class="details-row">
                        <span class="details-label">Relationship:</span>
                        <span class="details-val">{{ $relationship }}</span>
                    </div>
                    <div class="details-row">
                        <span class="details-label">Date & Time:</span>
                        <span class="details-val">{{ $date }}</span>
                    </div>
                    @if(!empty($notes))
                        <div class="details-row" style="margin-top: 10px; border-top: 1px dashed #dee2e6; padding-top: 8px;">
                            <span class="details-label" style="display: block; width: 100%; margin-bottom: 2px;">Clinical Consent Notes:</span>
                            <span class="details-val" style="font-style: italic; color: #555;">{{ $notes }}</span>
                        </div>
                    @endif
                </div>
            </td>

            <!-- Signature Capture -->
            <td class="signature-cell" style="padding-left: 15px;">
                <div class="signature-box">
                    <img src="{{ $signature }}" class="signature-image" alt="Digital Signature" />
                    <div class="signature-line"></div>
                    <div class="signature-caption">Digitally Signed &amp; Verified</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Footer Area -->
    <table class="footer-table">
        <tr>
            <td>
                Generated Automatically by {{ $sett->site_name ?? 'Hospital' }} Procedure Module &bull; ID: {{ $procedure->id }}
            </td>
            <td style="text-align: right;">
                Date Printed: {{ now()->format('d M Y H:i') }}
            </td>
        </tr>
    </table>

</body>
</html>
