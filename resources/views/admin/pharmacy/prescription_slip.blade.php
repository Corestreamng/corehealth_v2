<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Slip - {{ $patient->file_no }}</title>
    <style>
        @page {
            size: A5;
            margin: 10mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #333;
            padding: 15px;
        }

        .prescription-header {
            border-bottom: 3px solid {{ $appsettings->hos_color ?? '#0066cc' }};
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .hospital-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .hospital-info {
            flex: 1;
            text-align: center;
            padding: 0 15px;
        }

        .hospital-name {
            font-size: 20pt;
            font-weight: bold;
            color: {{ $appsettings->hos_color ?? '#0066cc' }};
            margin-bottom: 5px;
        }

        .hospital-address {
            font-size: 9pt;
            color: #666;
            margin-bottom: 3px;
        }

        .prescription-title {
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
            color: {{ $appsettings->hos_color ?? '#0066cc' }};
            margin: 20px 0 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .patient-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
        }

        .patient-row {
            display: flex;
            margin-bottom: 5px;
            font-size: 10pt;
        }

        .patient-row strong {
            min-width: 120px;
            color: #555;
        }

        .prescriptions-section {
            margin: 20px 0;
        }

        .prescription-item {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 12px;
            background: #fff;
            page-break-inside: avoid;
        }

        .medication-name {
            font-size: 12pt;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .medication-code {
            font-size: 9pt;
            color: #666;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            margin-left: 8px;
        }

        .dose-info {
            background: #e7f3ff;
            border-left: 3px solid {{ $appsettings->hos_color ?? '#0066cc' }};
            padding: 8px 12px;
            margin: 8px 0;
            font-size: 11pt;
        }

        .dose-label {
            font-weight: bold;
            color: {{ $appsettings->hos_color ?? '#0066cc' }};
        }

        .doctor-info {
            font-size: 9pt;
            color: #666;
            margin-top: 5px;
        }

        .signatures-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            width: 45%;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
            text-align: center;
        }

        .signature-label {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .signature-name {
            font-size: 9pt;
            color: #666;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #dee2e6;
            text-align: center;
            font-size: 8pt;
            color: #999;
        }

        .print-date {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 8pt;
            color: #999;
        }

        @media print {
            body {
                padding: 0;
            }

            .prescription-item {
                border-color: #333;
            }

            @page {
                margin: 10mm;
            }
        }

        /* HMO Info Styling */
        .hmo-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 8px 12px;
            margin-top: 10px;
            font-size: 9pt;
        }

        .hmo-label {
            font-weight: bold;
            color: #856404;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80pt;
            color: rgba(0, 0, 0, 0.03);
            font-weight: bold;
            z-index: -1;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="watermark">{{ $appsettings->hospital_name ?? 'HOSPITAL' }}</div>

    <div class="print-date">
        Printed: {{ $print_date }}
    </div>

    <!-- Hospital Header -->
    <div class="prescription-header">
        @if($appsettings->logo)
            <img src="data:image/jpeg;base64,{{ $appsettings->logo }}" alt="Hospital Logo" class="hospital-logo">
        @endif

        <div class="hospital-info">
            <div class="hospital-name">{{ $appsettings->hospital_name ?? 'HOSPITAL NAME' }}</div>
            <div class="hospital-address">{{ $appsettings->address ?? 'Hospital Address' }}</div>
            <div class="hospital-address">
                Tel: {{ $appsettings->phone ?? 'N/A' }} |
                Email: {{ $appsettings->email ?? 'N/A' }}
            </div>
            @if($appsettings->website)
                <div class="hospital-address">{{ $appsettings->website }}</div>
            @endif
        </div>

        @if($appsettings->logo)
            <div style="width: 80px;"></div> <!-- Spacer for balance -->
        @endif
    </div>

    <div class="prescription-title">📋 PRESCRIPTION SLIP</div>

    <!-- Patient Information -->
    <div class="patient-section">
        <div class="patient-row">
            <strong>Patient Name:</strong>
            <span>{{ userfullname($patient->user_id) }}</span>
        </div>
        <div class="patient-row">
            <strong>File Number:</strong>
            <span>{{ $patient->file_no }}</span>
        </div>
        <div class="patient-row">
            <strong>Age/Gender:</strong>
            <span>{{ $patient->dob ? \Carbon\Carbon::parse($patient->dob)->age : 'N/A' }} years / {{ $patient->gender ?? 'N/A' }}</span>
        </div>
        @if($patient->hmo)
            <div class="patient-row">
                <strong>HMO:</strong>
                <span>{{ $patient->hmo->name }} ({{ $patient->hmo_no ?? 'N/A' }})</span>
            </div>
        @endif
        <div class="patient-row">
            <strong>Date:</strong>
            <span>{{ \Carbon\Carbon::now()->format('d M Y') }}</span>
        </div>
    </div>

    <!-- Prescriptions -->
    <div class="prescriptions-section">
        <h3 style="font-size: 12pt; margin-bottom: 15px; color: #555;">💊 Prescribed Medications</h3>

        @foreach($prescriptions as $index => $prescription)
            <div class="prescription-item">
                <div>
                    <span class="medication-name">{{ $index + 1 }}. {{ $prescription->product->product_name }}</span>
                    @if($prescription->product->product_code)
                        <span class="medication-code">{{ $prescription->product->product_code }}</span>
                    @endif
                </div>

                @if($prescription->dose)
                    <div class="dose-info">
                        <span class="dose-label">Dosage & Frequency:</span> {{ $prescription->dose }}
                    </div>
                @else
                    <div class="dose-info">
                        <span class="dose-label">Dosage & Frequency:</span> As directed by physician
                    </div>
                @endif

                <div class="doctor-info">
                    <strong>Prescribed by:</strong>
                    Dr. {{ $prescription->doctor ? userfullname($prescription->doctor_id) : 'N/A' }}
                    @if($prescription->created_at)
                        ({{ \Carbon\Carbon::parse($prescription->created_at)->format('d M Y H:i') }})
                    @endif
                </div>

                @if($prescription->productOrServiceRequest)
                    @php
                        $posr = $prescription->productOrServiceRequest;
                    @endphp
                    @if($posr->coverage_mode && $posr->coverage_mode != 'none')
                        <div class="hmo-info">
                            <span class="hmo-label">HMO Coverage:</span>
                            {{ ucfirst($posr->coverage_mode) }} Coverage -
                            Patient Pays: ₦{{ number_format($posr->payable_amount ?? 0, 2) }},
                            HMO Claims: ₦{{ number_format($posr->claims_amount ?? 0, 2) }}
                        </div>
                    @endif
                @endif
            </div>
        @endforeach
    </div>

    <!-- Instructions -->
    <div style="background: #e9ecef; padding: 10px 15px; border-radius: 5px; margin: 20px 0; font-size: 9pt;">
        <strong>⚠️ Instructions:</strong>
        <ul style="margin: 5px 0 0 20px;">
            <li>Take medications exactly as prescribed by your doctor</li>
            <li>Complete the full course of treatment even if symptoms improve</li>
            <li>Consult your doctor if you experience any adverse reactions</li>
            <li>Keep medications out of reach of children</li>
        </ul>
    </div>

    <!-- Signatures -->
    <div class="signatures-section">
        <div class="signature-box">
            <div class="signature-line">
                <div class="signature-label">Attending Pharmacist</div>
                <div class="signature-name">{{ $pharmacist }}</div>
                <div style="font-size: 8pt; color: #999; margin-top: 3px;">
                    {{ \Carbon\Carbon::now()->format('d M Y H:i') }}
                </div>
            </div>
        </div>

        <div class="signature-box">
            <div class="signature-line">
                <div class="signature-label">Patient/Guardian Signature</div>
                <div style="font-size: 8pt; color: #999; margin-top: 20px;">
                    _______________________
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>This prescription slip was generated electronically by {{ $appsettings->hospital_name ?? 'Hospital' }}</p>
        <p>For inquiries, contact: {{ $appsettings->phone ?? 'N/A' }} | {{ $appsettings->email ?? 'N/A' }}</p>
        <p style="margin-top: 10px; font-size: 7pt;">
            <em>This is an official hospital document. Keep for your records.</em>
        </p>
    </div>

    <script>
        // Auto print when page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
