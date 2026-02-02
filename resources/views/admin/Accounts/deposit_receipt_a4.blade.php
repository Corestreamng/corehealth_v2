{{-- Deposit Receipt (A4) --}}
<!DOCTYPE html>
<html>
<head>
    <title>Deposit Receipt (A4)</title>
    <style>
        :root {
            --brand: {{ $site->hos_color ?? '#0a6cf2' }};
            --ink: #1d1d1f;
            --muted: #5f6368;
            --border: #d7d9dc;
            --bg: #f7f9fb;
            --success: #28a745;
        }
        * { box-sizing: border-box; }
        .receipt-a4 { margin: 0; padding: 24px; font-family: 'Segoe UI', Arial, sans-serif; font-size: 14px; color: var(--ink); background: var(--bg); }
        .receipt-a4 .sheet { background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 24px; box-shadow: 0 8px 24px rgba(0,0,0,0.04); max-width: 900px; width: 100%; margin: 0 auto; }
        .receipt-a4 .brand-bar { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid var(--brand); padding-bottom: 12px; margin-bottom: 16px; }
        .receipt-a4 .brand-left { display: flex; align-items: center; gap: 12px; }
        .receipt-a4 .brand-left img { width: 72px; height: auto; }
        .receipt-a4 .brand-name { font-size: 20px; font-weight: 700; color: var(--brand); letter-spacing: 0.5px; }
        .receipt-a4 .brand-meta { font-size: 12px; color: var(--muted); line-height: 1.4; }
        .receipt-a4 .doc-title { text-align: right; font-size: 24px; font-weight: 700; letter-spacing: 1px; color: var(--success); }
        .receipt-a4 .doc-title small { display: block; font-size: 12px; color: var(--muted); font-weight: 500; }
        .receipt-a4 .details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px 20px; margin-bottom: 20px; }
        .receipt-a4 .details div { font-size: 13px; color: var(--ink); }
        .receipt-a4 .badge { display: inline-block; background: rgba(10,108,242,0.08); color: var(--brand); padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; letter-spacing: 0.2px; }
        .receipt-a4 .badge-success { background: rgba(40,167,69,0.1); color: var(--success); }
        .receipt-a4 .amount-box { background: linear-gradient(135deg, var(--brand), #0056b3); color: white; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0; }
        .receipt-a4 .amount-box .label { font-size: 14px; opacity: 0.9; margin-bottom: 5px; }
        .receipt-a4 .amount-box .value { font-size: 32px; font-weight: 700; }
        .receipt-a4 .amount-box .sub { font-size: 12px; opacity: 0.8; margin-top: 5px; }
        .receipt-a4 .balance-info { display: flex; justify-content: space-between; background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0; }
        .receipt-a4 .balance-info .item { text-align: center; }
        .receipt-a4 .balance-info .item .label { font-size: 11px; color: var(--muted); text-transform: uppercase; }
        .receipt-a4 .balance-info .item .value { font-size: 18px; font-weight: 600; color: var(--ink); }
        .receipt-a4 .notes { margin-top: 12px; font-size: 12px; color: var(--muted); border-left: 3px solid var(--brand); padding-left: 10px; }
        .receipt-a4 .footer { margin-top: 20px; display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); border-top: 1px solid var(--border); padding-top: 15px; }
        .receipt-a4 .signature-box { margin-top: 30px; display: flex; justify-content: space-between; }
        .receipt-a4 .signature-box .sig { width: 200px; text-align: center; }
        .receipt-a4 .signature-box .sig-line { border-top: 1px solid var(--border); margin-top: 40px; padding-top: 5px; font-size: 11px; color: var(--muted); }
        @media print {
            @page { size: A4; margin: 10mm; }
            .receipt-a4 { background: #fff; padding: 0; }
            .receipt-a4 .sheet { box-shadow: none; border: none; margin: 0; width: 100%; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="receipt-a4">
    <div class="sheet">
        <div class="brand-bar">
            <div class="brand-left">
                @if($site->logo)
                <img src="data:image/jpeg;base64,{{ $site->logo }}" alt="Logo">
                @endif
                <div>
                    <div class="brand-name">{{ $site->site_name }}</div>
                    <div class="brand-meta">{{ $site->contact_address }}<br>Phone: {{ $site->contact_phones }} | Email: {{ $site->contact_emails }}</div>
                </div>
            </div>
            <div class="doc-title">DEPOSIT RECEIPT<small>{{ $depositNumber }}</small></div>
        </div>

        <div class="details">
            <div><span class="badge">Patient</span><br><strong>{{ $patientName }}</strong></div>
            <div><span class="badge">File No.</span><br><strong>{{ $patientFileNo ?? 'N/A' }}</strong></div>
            <div><span class="badge">Date & Time</span><br>{{ $date }}</div>
            <div><span class="badge badge-success">Deposit Type</span><br>{{ $depositType }}</div>
            <div><span class="badge">Payment Method</span><br>{{ $paymentMethod }}</div>
            @if($bank)
            <div><span class="badge">Bank</span><br>{{ $bank }}</div>
            @endif
            @if($paymentReference)
            <div><span class="badge">Reference</span><br>{{ $paymentReference }}</div>
            @endif
            <div><span class="badge">Received By</span><br>{{ $receivedBy }}</div>
        </div>

        <div class="amount-box">
            <div class="label">Amount Deposited</div>
            <div class="value">₦{{ number_format($amount, 2) }}</div>
            <div class="sub">{{ $amountInWords }}</div>
        </div>

        <div class="balance-info">
            <div class="item">
                <div class="label">Previous Balance</div>
                <div class="value">₦{{ number_format($previousBalance, 2) }}</div>
            </div>
            <div class="item">
                <div class="label">Deposit Amount</div>
                <div class="value" style="color: var(--success);">+₦{{ number_format($amount, 2) }}</div>
            </div>
            <div class="item">
                <div class="label">New Balance</div>
                <div class="value" style="color: var(--brand);">₦{{ number_format($newBalance, 2) }}</div>
            </div>
        </div>

        @if($notes)
        <div class="notes">
            <strong>Notes:</strong> {{ $notes }}
        </div>
        @endif

        <div class="signature-box">
            <div class="sig">
                <div class="sig-line">Patient / Representative</div>
            </div>
            <div class="sig">
                <div class="sig-line">Cashier: {{ $receivedBy }}</div>
            </div>
        </div>

        @php
            $paymentMethodNames = [
                'CASH' => 'Cash',
                'POS' => 'POS/Card',
                'TRANSFER' => 'Bank Transfer',
                'MOBILE' => 'Mobile Money',
                'CHEQUE' => 'Cheque',
                'TELLER' => 'Teller',
            ];
            $paymentMethodDisplay = $paymentMethodNames[$paymentMethod] ?? $paymentMethod ?? 'N/A';
        @endphp

        <div class="footer">
            <div>Receipt No: {{ $depositNumber }}</div>
            <div>Received via: {{ $paymentMethodDisplay }}</div>
            <div>Printed: {{ now()->format('Y-m-d H:i:s') }}</div>
        </div>
    </div>
    </div>
</body>
</html>
