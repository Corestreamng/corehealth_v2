@php
    $sett = appsettings();
    $hosColor = $sett->hos_color ?? '#0066cc';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Results - {{ $req->service->service_name ?? 'N/A' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 30px;
            border-bottom: 3px solid {{ $hosColor }};
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            width: 80px;
            height: 80px;
        }

        .hospital-name {
            font-size: 28px;
            font-weight: bold;
            color: {{ $hosColor }};
            text-transform: uppercase;
        }

        .header-right {
            text-align: right;
            font-size: 14px;
            color: #666;
        }

        .title-section {
            background: {{ $hosColor }};
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 8px;
        }

        .patient-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 30px;
            background: #f8f9fa;
        }

        .info-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #333;
            min-width: 100px;
        }

        .info-value {
            color: #666;
            flex: 1;
        }

        .results-section {
            padding: 30px;
        }

        .results-title {
            font-size: 22px;
            font-weight: bold;
            color: {{ $hosColor }};
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid {{ $hosColor }};
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .results-table thead {
            background: {{ $hosColor }};
            color: white;
        }

        .results-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 16px;
        }

        .results-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #ddd;
        }

        .results-table tbody tr:hover {
            background: #f8f9fa;
        }

        .test-group {
            background: #f0f0f0;
            font-weight: bold;
            color: #333;
        }

        .test-parameter {
            padding-left: 30px !important;
            color: #555;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #28a745;
            color: white;
        }

        .badge-danger {
            background: #dc3545;
            color: white;
        }

        .badge-warning {
            background: #ffc107;
            color: #000;
        }

        .badge-secondary {
            background: #6c757d;
            color: white;
        }

        .footer {
            padding: 30px;
            text-align: center;
            font-size: 12px;
            color: #999;
            border-top: 1px solid #ddd;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            padding: 40px 30px 20px;
        }

        .signature-box {
            text-align: center;
            min-width: 200px;
        }

        .signature-line {
            border-top: 2px solid #333;
            margin-top: 40px;
            padding-top: 10px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                max-width: 100%;
            }

            .no-print {
                display: none !important;
            }
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: {{ $hosColor }};
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            z-index: 1000;
        }

        .print-button:hover {
            opacity: 0.9;
        }

        .v2-results-content {
            margin-top: 20px;
        }

        .v1-results-content {
            margin-top: 20px;
            line-height: 1.6;
        }

        .v1-results-content table {
            width: 100%;
            border-collapse: collapse;
        }

        .v1-results-content table td,
        .v1-results-content table th {
            padding: 8px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">
        Print Results
    </button>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                @if($sett->logo)
                    <img src="data:image/jpeg;base64,{{ $sett->logo }}" alt="Hospital Logo" class="logo">
                @endif
                <div class="hospital-name">{{ $sett->site_name ?? 'Hospital' }}</div>
            </div>
            <div class="header-right">
                <div>{{ $sett->contact_address ?? '' }}</div>
                <div>{{ $sett->contact_phones ?? '' }}</div>
                <div>{{ $sett->contact_emails ?? '' }}</div>
            </div>
        </div>

        <!-- Title -->
        <div class="title-section">
            TEST RESULTS
        </div>

        <!-- Patient Information -->
        <div class="patient-info">
            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value">{{ $req->patient && $req->patient->user ? userfullname($req->patient->user->id) : 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date:</span>
                    <span class="info-value">{{ $req->result_date ? date('Y-m-d', strtotime($req->result_date)) : 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient #:</span>
                    <span class="info-value">{{ $req->patient->file_no ?? 'N/A' }}</span>
                </div>
            </div>

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Age:</span>
                    <span class="info-value">
                        @if($req->patient && $req->patient->user && $req->patient->user->date_of_birth)
                            {{ \Carbon\Carbon::parse($req->patient->user->date_of_birth)->age }} years old
                        @else
                            N/A
                        @endif
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Sex:</span>
                    <span class="info-value">{{ $req->patient && $req->patient->user ? ucfirst($req->patient->user->gender ?? 'N/A') : 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Test ID:</span>
                    <span class="info-value">{{ str_pad($req->id, 7, '0', STR_PAD_LEFT) }}</span>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div class="results-section">
            <div class="results-title">{{ $req->service->service_name ?? 'Test Results' }}</div>

            @if($req->result_data && is_array($req->result_data) && $req->service && $req->service->result_template_v2)
                {{-- V2 Template: Structured Results with Status --}}
                @php
                    $template = $req->service->result_template_v2;
                    $parameters = $template['parameters'] ?? [];

                    // Group parameters by category if they have a category field
                    $groupedParams = [];
                    foreach ($parameters as $param) {
                        $category = $param['category'] ?? $template['template_name'] ?? 'Results';
                        if (!isset($groupedParams[$category])) {
                            $groupedParams[$category] = [];
                        }
                        $groupedParams[$category][] = $param;
                    }
                @endphp

                <div class="v2-results-content">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Test</th>
                                <th style="width: 25%;">Results</th>
                                <th style="width: 30%;">Reference Range</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groupedParams as $category => $params)
                                @if(count($groupedParams) > 1)
                                    <tr>
                                        <td colspan="3" class="test-group">{{ $category }}</td>
                                    </tr>
                                @endif

                                @foreach($params as $param)
                                    @if(isset($req->result_data[$param['id']]))
                                        @php
                                            $paramData = $req->result_data[$param['id']];
                                            $value = $paramData['value'] ?? $paramData;

                                            // Format value
                                            if ($param['type'] === 'boolean') {
                                                $displayValue = ($value === true || $value === 'true') ? 'Yes/Positive' : 'No/Negative';
                                            } elseif ($param['type'] === 'float' && is_numeric($value)) {
                                                $displayValue = number_format($value, 2);
                                            } else {
                                                $displayValue = $value;
                                            }

                                            // Add unit if present
                                            if (!empty($param['unit'])) {
                                                $displayValue .= ' ' . $param['unit'];
                                            }

                                            // Format reference range
                                            $refRange = '';
                                            if (isset($param['reference_range'])) {
                                                $ref = $param['reference_range'];
                                                if (isset($ref['min']) && isset($ref['max'])) {
                                                    $refRange = $ref['min'] . ' - ' . $ref['max'];
                                                } elseif (isset($ref['reference_value'])) {
                                                    if ($param['type'] === 'boolean') {
                                                        $refRange = $ref['reference_value'] ? 'Yes/Positive' : 'No/Negative';
                                                    } else {
                                                        $refRange = $ref['reference_value'];
                                                    }
                                                } elseif (isset($ref['text'])) {
                                                    $refRange = $ref['text'];
                                                }
                                            }
                                        @endphp

                                        <tr>
                                            <td class="test-parameter">{{ $param['name'] }}</td>
                                            <td><strong>{{ $displayValue }}</strong></td>
                                            <td>{{ $refRange }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                {{-- V1 Template: Legacy HTML Results --}}
                <div class="v1-results-content">
                    {!! $req->result ?? '<p>No results available</p>' !!}
                </div>
            @endif

            {{-- Attachments if any --}}
            @if($req->attachments)
                @php
                    $attachments = is_string($req->attachments) ? json_decode($req->attachments, true) : $req->attachments;
                @endphp
                @if(!empty($attachments))
                    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <h4 style="color: {{ $hosColor }}; margin-bottom: 15px;">
                            Attachments
                        </h4>
                        @foreach($attachments as $attachment)
                            <div style="padding: 8px 0;">
                                {{ $attachment['name'] }}
                                <small class="text-muted">({{ number_format($attachment['size'] / 1024, 2) }} KB)</small>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">
                    <strong>{{ $req->resultBy ? userfullname($req->resultBy->id) : 'N/A' }}</strong><br>
                    <small>Medical Laboratory Scientist</small>
                </div>
            </div>

            <div class="signature-box">
                <div class="signature-line">
                    <strong>Authorized Signature</strong><br>
                    <small>{{ date('d M Y', strtotime($req->result_date ?? now())) }}</small>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>This is a computer-generated report and does not require a signature.</p>
            <p>For any queries, please contact: {{ $sett->contact_phones ?? 'N/A' }} | {{ $sett->contact_emails ?? 'N/A' }}</p>
            <p style="margin-top: 10px; font-size: 10px;">Generated on {{ date('d M Y H:i:s') }}</p>
        </div>
    </div>
</body>
</html>
