<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@yield('title', 'Financial Report')</title>
    @php
        $sett = function_exists('appsettings') ? appsettings() : null;
        $hosColor = $sett->hos_color ?? config('app.hospital_color', '#0066cc');
        $hospitalName = $sett->site_name ?? config('app.name', 'CoreHealth Hospital');
        $hospitalAddress = $sett->contact_address ?? '';
        $hospitalPhone = $sett->contact_phones ?? '';
        $hospitalEmail = $sett->contact_emails ?? '';
        $hospitalLogo = $sett->logo ?? null;
        $hospitalMotto = $sett->slogan ?? $sett->tagline ?? '';
    @endphp
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            background: white;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        .container {
            max-width: 100%;
            padding: 15px 20px;
        }

        /* Hospital Header - Payslip Style */
        .report-header {
            display: table;
            width: 100%;
            border-bottom: 3px solid {{ $hosColor }};
            padding-bottom: 12px;
            margin-bottom: 15px;
            background: linear-gradient(to bottom, #f8f9fa 0%, #ffffff 100%);
        }
        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 65%;
        }
        .header-right {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 35%;
            font-size: 10px;
            color: #495057;
            line-height: 1.6;
            padding-top: 5px;
        }
        .header-right strong {
            color: #212529;
        }
        .logo-section {
            display: table;
            width: 100%;
        }
        .logo-cell {
            display: table-cell;
            vertical-align: middle;
            width: 75px;
            padding-right: 12px;
        }
        .hospital-info {
            display: table-cell;
            vertical-align: middle;
        }
        .hospital-logo {
            max-width: 70px;
            max-height: 70px;
            border-radius: 6px;
        }
        .hospital-name {
            font-size: 22px;
            font-weight: 700;
            color: {{ $hosColor }};
            margin-bottom: 2px;
            line-height: 1.2;
        }
        .hospital-motto {
            font-size: 10px;
            font-style: italic;
            color: #6c757d;
            margin-bottom: 5px;
        }
        .hospital-contact {
            font-size: 10px;
            color: #495057;
            line-height: 1.5;
        }
        .hospital-contact i {
            color: {{ $hosColor }};
            margin-right: 3px;
        }

        /* Report Title Section - Branded */
        .report-title-section {
            background: {{ $hosColor }};
            color: white;
            text-align: center;
            padding: 10px 20px;
            margin-bottom: 15px;
        }
        .report-title {
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 0;
        }
        .report-subtitle {
            font-size: 11px;
            margin-top: 4px;
            opacity: 0.9;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            border-bottom: 2px solid #333;
            font-size: 10px;
            text-transform: uppercase;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
            border-top: 2px solid #333;
        }
        .subtotal-row {
            font-weight: bold;
            background-color: #f8f8f8;
        }
        .group-header {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .indent-1 {
            padding-left: 25px;
        }
        .indent-2 {
            padding-left: 40px;
        }

        /* Summary Boxes */
        .summary-box {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            background: #f9f9f9;
        }
        .summary-box h4 {
            margin-bottom: 10px;
            color: {{ $hosColor }};
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        /* Balance Check */
        .balance-check {
            margin-top: 15px;
            padding: 10px;
            text-align: center;
            border-radius: 4px;
        }
        .balanced {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .unbalanced {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Colors */
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .text-warning { color: #ffc107; }
        .text-info { color: #17a2b8; }
        .text-muted { color: #6c757d; }
        .bg-success { background-color: #d4edda; }
        .bg-danger { background-color: #f8d7da; }
        .bg-warning { background-color: #fff3cd; }

        /* Footer */
        .report-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            font-size: 9px;
            color: #666;
            border-top: 1px solid #ddd;
            padding: 10px 20px;
            background: white;
        }
        .footer-left {
            float: left;
        }
        .footer-right {
            float: right;
        }

        /* Page break */
        .page-break {
            page-break-after: always;
        }

        /* Additional utility classes */
        .font-bold { font-weight: bold; }
        .font-italic { font-style: italic; }
        .small { font-size: 10px; }
        .mb-1 { margin-bottom: 5px; }
        .mb-2 { margin-bottom: 10px; }
        .mb-3 { margin-bottom: 15px; }
        .mt-1 { margin-top: 5px; }
        .mt-2 { margin-top: 10px; }
        .mt-3 { margin-top: 15px; }
        .p-2 { padding: 10px; }
        .border { border: 1px solid #ddd; }
    </style>
    @yield('styles')
</head>
<body>
    <div class="container">
        <!-- Hospital Header - Enhanced like Payslip -->
        <div class="report-header">
            <div class="header-left">
                <div class="logo-section">
                    @if($hospitalLogo)
                    <div class="logo-cell">
                        <img src="data:image/jpeg;base64,{{ $hospitalLogo }}" alt="Logo" class="hospital-logo">
                    </div>
                    @endif
                    <div class="hospital-info">
                        <div class="hospital-name">{{ $hospitalName }}</div>
                        @if($hospitalMotto)
                        <div class="hospital-motto">"{{ $hospitalMotto }}"</div>
                        @endif
                        <div class="hospital-contact">
                            @if($hospitalAddress)
                            <div>📍 {{ $hospitalAddress }}</div>
                            @endif
                            @if($hospitalPhone)
                            <span>📞 {{ $hospitalPhone }}</span>
                            @endif
                            @if($hospitalEmail)
                            <span style="margin-left: 10px;">✉ {{ $hospitalEmail }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="header-right">
                <div style="background: #f8f9fa; padding: 8px 12px; border-radius: 4px; border: 1px solid #e9ecef;">
                    <strong style="color: {{ $hosColor }};">Report Generated</strong><br>
                    <span>{{ now()->format('M d, Y') }}</span><br>
                    <span>{{ now()->format('h:i A') }}</span><br>
                    <span style="font-size: 9px; color: #666;">By: {{ auth()->user()->name ?? 'System' }}</span>
                </div>
            </div>
        </div>

        <!-- Report Title -->
        <div class="report-title-section">
            <h1 class="report-title">@yield('report_title', 'Financial Report')</h1>
            @hasSection('report_subtitle')
            <div class="report-subtitle">@yield('report_subtitle')</div>
            @endif
        </div>

        <!-- Report Content -->
        @yield('content')
    </div>

    <!-- Footer -->
    <div class="report-footer">
        <div class="footer-left">
            {{ $hospitalName }} - Confidential Financial Report
        </div>
        <div class="footer-right">
            Page <span class="page-number"></span> | Printed: {{ now()->format('F d, Y \a\t H:i:s') }}
        </div>
    </div>
</body>
</html>
