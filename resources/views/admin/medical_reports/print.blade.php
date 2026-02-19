<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report->title ?? 'Medical Report' }} - {{ $report->patient && $report->patient->user ? trim(($report->patient->user->surname ?? '') . ' ' . ($report->patient->user->firstname ?? '')) : 'Patient' }}</title>
    @php
        $sett = appsettings();
        $hosColor = $sett->hos_color ?? '#0066cc';
    @endphp
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            background: white;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
        }

        /* Result Header & Branding */
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px;
            border-bottom: 3px solid {{ $hosColor }};
            background: #f8f9fa;
        }
        .result-header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .result-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 8px;
        }
        .result-hospital-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: {{ $hosColor }};
            max-width: 300px;
            line-height: 1.3;
        }
        .result-header-right {
            text-align: right;
            font-size: 0.9rem;
            color: #495057;
            line-height: 1.6;
        }
        .result-header-right strong {
            color: #212529;
        }

        /* Result Title Section */
        .result-title-section {
            background: {{ $hosColor }};
            color: white;
            text-align: center;
            padding: 10px 20px;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0;
        }

        /* Patient Info Section */
        .result-patient-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .result-info-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .result-info-row {
            display: flex;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #eee;
        }
        .result-info-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .result-info-label {
            font-weight: 600;
            color: #6c757d;
            min-width: 120px;
            font-size: 0.9rem;
        }
        .result-info-value {
            color: #212529;
            font-weight: 500;
            flex: 1;
        }

        /* Report Content */
        .result-section {
            padding: 20px;
        }
        .report-content {
            padding: 10px 0;
            min-height: 200px;
        }
        .report-content h1, .report-content h2, .report-content h3, .report-content h4 {
            margin: 15px 0 8px 0;
            color: #222;
        }
        .report-content h2 {
            font-size: 18px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .report-content h3 { font-size: 16px; }
        .report-content h4 { font-size: 14px; }
        .report-content p { margin-bottom: 8px; font-size: 13px; line-height: 1.7; }
        .report-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 0.9rem;
        }
        .report-content table th {
            background: {{ $hosColor }};
            color: white;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
        }
        .report-content table td {
            padding: 10px 12px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: top;
        }
        .report-content table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .report-content ul, .report-content ol {
            margin: 8px 0 8px 25px;
        }
        .report-content li { margin-bottom: 4px; }

        /* Doctor Signature */
        .doctor-signature {
            margin-top: 60px;
            padding: 0 20px 20px;
            display: flex;
            justify-content: flex-end;
        }
        .signature-block {
            text-align: center;
            min-width: 250px;
        }
        .signature-line {
            border-top: 1px solid #333;
            padding-top: 5px;
            margin-top: 40px;
        }
        .doctor-name { font-weight: 700; font-size: 14px; }
        .doctor-title { font-size: 12px; color: #666; }

        /* Status watermark */
        .draft-watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            font-weight: 700;
            color: rgba(255, 0, 0, 0.08);
            pointer-events: none;
            z-index: 0;
        }

        /* Footer - fixed to bottom of every printed page */
        .result-footer {
            font-size: 0.75rem;
            color: #6c757d;
            text-align: center;
            border-top: 1px solid #ccc;
            padding: 8px 20px;
            background: white;
        }

        @media screen {
            .result-footer {
                margin-top: 40px;
                border-top: 2px solid #dee2e6;
                padding: 20px;
                background: #f8f9fa;
                font-size: 0.85rem;
            }
        }

        @page {
            margin: 15mm 10mm 25mm 10mm;
        }

        @media print {
            body { background: white; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .container { padding: 0; padding-bottom: 0; }
            .result-header, .result-title-section { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .report-content { page-break-inside: auto; }

            .result-footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                padding: 6px 10mm;
                font-size: 0.7rem;
            }

            /* Reserve space so content doesn't overlap the fixed footer */
            .content-wrapper {
                padding-bottom: 80px;
            }
        }
    </style>
</head>
<body>
    <div class="container">

        {{-- Print / Close Buttons --}}
        <div class="no-print" style="text-align: right; padding: 15px 20px;">
            <button onclick="window.print()" style="padding: 8px 20px; background: {{ $hosColor }}; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
                🖨 Print Report
            </button>
            <button onclick="window.close()" style="padding: 8px 20px; background: #6c757d; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin-left: 5px;">
                ✕ Close
            </button>
        </div>

        {{-- Draft watermark --}}
        @if($report->status !== 'finalized')
            <div class="draft-watermark">DRAFT</div>
        @endif

        {{-- Footer (placed early in DOM so position:fixed prints on every page) --}}
        <div class="result-footer">
            <div>{{ $sett->site_name ?? 'Hospital Name' }} | {{ $sett->contact_address ?? '' }}</div>
            <div>{{ $sett->contact_phones ?? '' }} | {{ $sett->contact_emails ?? '' }}</div>
            <div style="margin-top: 4px;">
                This is a computer-generated document.
                {{ $report->status === 'finalized' ? 'This report has been finalized and signed electronically.' : 'DRAFT - Not yet finalized.' }}
            </div>
        </div>

        <div class="content-wrapper">

        {{-- Hospital Header --}}
        <div class="result-header">
            <div class="result-header-left">
                @if(isset($sett->logo) && $sett->logo)
                    <img src="data:image/jpeg;base64,{{ $sett->logo }}" alt="Hospital Logo" class="result-logo" />
                @endif
                <div class="result-hospital-name">{{ $sett->site_name ?? 'Hospital Name' }}</div>
            </div>
            <div class="result-header-right">
                <div><strong>Address:</strong> {{ $sett->contact_address ?? 'N/A' }}</div>
                <div><strong>Phone:</strong> {{ $sett->contact_phones ?? 'N/A' }}</div>
                <div><strong>Email:</strong> {{ $sett->contact_emails ?? 'N/A' }}</div>
            </div>
        </div>

        {{-- Title Banner --}}
        <div class="result-title-section">{{ strtoupper($report->title ?? 'MEDICAL REPORT') }}</div>

        {{-- Patient Info --}}
        @php
            $patient = $report->patient;
            $user = $patient ? $patient->user : null;
            $patientName = $user ? trim(($user->surname ?? '') . ' ' . ($user->firstname ?? '') . ' ' . ($user->othername ?? '')) : '-';
            $doctor = $report->doctor;
            $doctorName = $doctor ? trim(($doctor->surname ?? '') . ' ' . ($doctor->firstname ?? '') . ' ' . ($doctor->othername ?? '')) : '-';
        @endphp

        @if($patient)
        <div class="result-patient-info">
            <div class="result-info-box">
                <div class="result-info-row">
                    <div class="result-info-label">Patient Name:</div>
                    <div class="result-info-value">{{ $patientName }}</div>
                </div>
                <div class="result-info-row">
                    <div class="result-info-label">Patient ID:</div>
                    <div class="result-info-value">{{ $patient->file_no ?? '-' }}</div>
                </div>
                <div class="result-info-row">
                    <div class="result-info-label">Gender:</div>
                    <div class="result-info-value">{{ ucfirst($patient->gender ?? '-') }}</div>
                </div>
                <div class="result-info-row">
                    <div class="result-info-label">Age:</div>
                    <div class="result-info-value">{{ $patient->dob ? \Carbon\Carbon::parse($patient->dob)->age . ' Years' : '-' }}</div>
                </div>
                <div class="result-info-row">
                    <div class="result-info-label">Date of Birth:</div>
                    <div class="result-info-value">{{ $patient->dob ? \Carbon\Carbon::parse($patient->dob)->format('d M Y') : '-' }}</div>
                </div>
            </div>
            <div class="result-info-box">
                <div class="result-info-row">
                    <div class="result-info-label">Phone:</div>
                    <div class="result-info-value">{{ $patient->phone_no ?? '-' }}</div>
                </div>
                <div class="result-info-row">
                    <div class="result-info-label">Address:</div>
                    <div class="result-info-value">{{ $patient->address ?? '-' }}</div>
                </div>
                <div class="result-info-row">
                    <div class="result-info-label">Blood Group:</div>
                    <div class="result-info-value">{{ $patient->blood_group ?? '-' }}</div>
                </div>
                <div class="result-info-row">
                    <div class="result-info-label">Report Date:</div>
                    <div class="result-info-value">{{ $report->report_date ? $report->report_date->format('d M Y') : date('d M Y') }}</div>
                </div>
                <div class="result-info-row">
                    <div class="result-info-label">Doctor:</div>
                    <div class="result-info-value">{{ $doctorName }}</div>
                </div>
            </div>
        </div>
        @endif

        {{-- Report Content --}}
        <div class="result-section">
            <div class="report-content">
                {!! $report->content !!}
            </div>
        </div>

        {{-- Doctor Signature --}}
        <div class="doctor-signature">
            <div class="signature-block">
                <div class="signature-line">
                    <div class="doctor-name">{{ $doctorName }}</div>
                    <div class="doctor-title">Attending Physician</div>
                    @if($report->status === 'finalized' && $report->finalized_at)
                        <div class="doctor-title">Signed: {{ $report->finalized_at->format('d M Y h:i A') }}</div>
                    @endif
                </div>
            </div>
        </div>

        </div> {{-- /.content-wrapper --}}
    </div>
</body>
</html>
