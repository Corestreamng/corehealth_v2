<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - {{ $payslip['employee_name'] }} - {{ $payslip['pay_period'] }}</title>
    @php
        $sett = appsettings();
        $hosColor = appsettings('hos_color') ?? '#0066cc';
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
            max-width: 800px;
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
            padding: 12px 20px;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Employee Info Section */
        .employee-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .info-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .info-row {
            display: flex;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #eee;
        }
        .info-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #6c757d;
            min-width: 130px;
            font-size: 0.9rem;
        }
        .info-value {
            color: #212529;
            font-weight: 500;
            flex: 1;
        }

        /* Earnings & Deductions */
        .payslip-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 20px;
        }
        .section {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        .section-title {
            padding: 12px 15px;
            font-weight: 700;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-title.earnings {
            background: #d4edda;
            color: #155724;
            border-bottom: 2px solid #28a745;
        }
        .section-title.deductions {
            background: #f8d7da;
            color: #721c24;
            border-bottom: 2px solid #dc3545;
        }
        .section-content {
            padding: 0;
        }
        .pay-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        .pay-item:last-child {
            border-bottom: none;
        }
        .pay-item.basic {
            background: #f8f9fa;
            font-weight: 600;
        }
        .pay-item-name {
            color: #495057;
        }
        .pay-item-amount {
            font-weight: 600;
            font-family: 'Consolas', monospace;
        }
        .section-total {
            display: flex;
            justify-content: space-between;
            padding: 12px 15px;
            font-weight: 700;
            font-size: 1rem;
        }
        .section-total.earnings {
            background: #28a745;
            color: white;
        }
        .section-total.deductions {
            background: #dc3545;
            color: white;
        }

        /* Net Pay Section */
        .net-pay-section {
            margin: 0 20px 20px;
            background: {{ $hosColor }};
            color: white;
            padding: 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .net-pay-label {
            font-size: 1.1rem;
            font-weight: 600;
        }
        .net-pay-label small {
            display: block;
            font-weight: 400;
            opacity: 0.8;
            font-size: 0.85rem;
            margin-top: 4px;
        }
        .net-pay-amount {
            font-size: 2rem;
            font-weight: 700;
            font-family: 'Consolas', monospace;
        }

        /* Bank Details */
        .bank-details {
            margin: 0 20px 20px;
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .bank-details-title {
            font-weight: 700;
            color: {{ $hosColor }};
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        .bank-details-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        .bank-detail-item label {
            display: block;
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 2px;
        }
        .bank-detail-item span {
            font-weight: 600;
            color: #212529;
        }

        /* Footer */
        .result-footer {
            padding: 20px;
            border-top: 2px solid #dee2e6;
            font-size: 0.85rem;
            color: #6c757d;
            text-align: center;
            background: #f8f9fa;
        }
        .result-footer .confidential {
            margin-top: 10px;
            font-size: 0.75rem;
            font-style: italic;
            color: #999;
        }

        /* Print Button (hidden on print) */
        .print-actions {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .print-actions button {
            padding: 10px 20px;
            font-size: 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-left: 10px;
        }
        .print-actions .btn-print {
            background: {{ $hosColor }};
            color: white;
        }
        .print-actions .btn-close {
            background: #6c757d;
            color: white;
        }

        @media print {
            body { background: white; }
            .print-actions { display: none !important; }
            .container { max-width: 100%; padding: 0; }
            .result-header, .result-title-section, .section-total, .net-pay-section { 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
            }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <button class="btn-print" onclick="window.print()"><i class="fa fa-print"></i> Print Payslip</button>
        <button class="btn-close" onclick="window.close()"><i class="fa fa-times"></i> Close</button>
    </div>

    <div class="container">
        <!-- Header -->
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

        <div class="result-title-section">PAYSLIP</div>

        <!-- Employee Info -->
        <div class="employee-info">
            <div class="info-box">
                <div class="info-row">
                    <div class="info-label">Employee Name:</div>
                    <div class="info-value">{{ $payslip['employee_name'] }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Employee ID:</div>
                    <div class="info-value">{{ $payslip['employee_id'] }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Department:</div>
                    <div class="info-value">{{ $payslip['department'] }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Job Title:</div>
                    <div class="info-value">{{ $payslip['job_title'] }}</div>
                </div>
            </div>
            <div class="info-box">
                <div class="info-row">
                    <div class="info-label">Payslip Number:</div>
                    <div class="info-value">{{ $payslip['payslip_number'] }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Pay Period:</div>
                    <div class="info-value">{{ $payslip['pay_period'] }}</div>
                </div>
                @if($payslip['work_period'] && $payslip['is_pro_rata'])
                <div class="info-row">
                    <div class="info-label">Work Period:</div>
                    <div class="info-value">{{ $payslip['work_period'] }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Days Worked:</div>
                    <div class="info-value">
                        <strong>{{ $payslip['days_worked'] }}</strong> of {{ $payslip['days_in_month'] }} days 
                        <span style="color: {{ $hosColor }}; font-weight: 600;">({{ $payslip['pro_rata_factor'] }}%)</span>
                    </div>
                </div>
                @endif
                <div class="info-row">
                    <div class="info-label">Payment Date:</div>
                    <div class="info-value">{{ $payslip['payment_date'] }}</div>
                </div>
            </div>
        </div>

        <!-- Earnings & Deductions -->
        <div class="payslip-body">
            <!-- Earnings -->
            <div class="section">
                <div class="section-title earnings">
                    <i class="fa fa-plus-circle"></i> Earnings
                </div>
                <div class="section-content">
                    <div class="pay-item basic">
                        <span class="pay-item-name">Basic Salary</span>
                        <span class="pay-item-amount">₦{{ number_format($payslip['basic_salary'], 2) }}</span>
                    </div>
                    @foreach($payslip['additions'] as $addition)
                    <div class="pay-item">
                        <span class="pay-item-name">{{ $addition['name'] }}</span>
                        <span class="pay-item-amount">₦{{ number_format($addition['amount'], 2) }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="section-total earnings">
                    <span>Gross Salary</span>
                    <span>₦{{ number_format($payslip['gross_salary'], 2) }}</span>
                </div>
            </div>

            <!-- Deductions -->
            <div class="section">
                <div class="section-title deductions">
                    <i class="fa fa-minus-circle"></i> Deductions
                </div>
                <div class="section-content">
                    @forelse($payslip['deductions'] as $deduction)
                    <div class="pay-item">
                        <span class="pay-item-name">{{ $deduction['name'] }}</span>
                        <span class="pay-item-amount">₦{{ number_format($deduction['amount'], 2) }}</span>
                    </div>
                    @empty
                    <div class="pay-item">
                        <span class="pay-item-name text-muted">No deductions</span>
                        <span class="pay-item-amount">₦0.00</span>
                    </div>
                    @endforelse
                </div>
                <div class="section-total deductions">
                    <span>Total Deductions</span>
                    <span>₦{{ number_format($payslip['total_deductions'], 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Net Pay -->
        <div class="net-pay-section">
            <div class="net-pay-label">
                Net Pay
                @if($payslip['is_pro_rata'])
                <small>Pro-rata: {{ $payslip['days_worked'] }} of {{ $payslip['days_in_month'] }} days ({{ $payslip['pro_rata_factor'] }}%)</small>
                @else
                <small>Amount payable to employee</small>
                @endif
            </div>
            <div class="net-pay-amount">₦{{ number_format($payslip['net_salary'], 2) }}</div>
        </div>

        @if($payslip['is_pro_rata'] && $payslip['full_net_salary'])
        <!-- Full Month Reference -->
        <div style="margin: 0 20px 20px; padding: 12px 20px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffc107; display: flex; justify-content: space-between; align-items: center;">
            <div style="color: #856404;">
                <i class="fa fa-info-circle"></i>
                <strong>Full Month Salary:</strong> If worked entire month
            </div>
            <div style="font-size: 1.2rem; font-weight: 700; color: #856404; font-family: 'Consolas', monospace;">
                ₦{{ number_format($payslip['full_net_salary'], 2) }}
            </div>
        </div>
        @endif

        <!-- Bank Details -->
        @if($payslip['bank_name'] || $payslip['bank_account_number'])
        <div class="bank-details">
            <div class="bank-details-title">
                <i class="fa fa-university"></i> Payment Details
            </div>
            <div class="bank-details-grid">
                <div class="bank-detail-item">
                    <label>Bank Name</label>
                    <span>{{ $payslip['bank_name'] ?? 'N/A' }}</span>
                </div>
                <div class="bank-detail-item">
                    <label>Account Number</label>
                    <span>{{ $payslip['bank_account_number'] ?? 'N/A' }}</span>
                </div>
                <div class="bank-detail-item">
                    <label>Account Name</label>
                    <span>{{ $payslip['bank_account_name'] ?? 'N/A' }}</span>
                </div>
            </div>
        </div>
        @endif

        <!-- Footer -->
        <div class="result-footer">
            <div>{{ $sett->site_name ?? 'Hospital Name' }} | {{ $sett->contact_address ?? '' }}</div>
            <div>{{ $sett->contact_phones ?? '' }} | {{ $sett->contact_emails ?? '' }}</div>
            <div class="confidential">
                This is a computer-generated payslip. This document is confidential and intended for the named employee only.
            </div>
        </div>
    </div>
</body>
</html>
