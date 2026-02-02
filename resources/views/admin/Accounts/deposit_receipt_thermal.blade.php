{{-- Deposit Receipt (Thermal 80mm) --}}
<!DOCTYPE html>
<html>
<head>
    <title>Deposit Receipt (Thermal)</title>
    <style>
        :root {
            --ink: #111;
            --muted: #444;
            --border: #ccc;
            --success: #28a745;
        }
        * { box-sizing: border-box; }
        .receipt-thermal { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10px; width: 55mm; margin: 0; padding: 0; color: var(--ink); }
        .receipt-thermal .wrap { padding: 8px; }
        .receipt-thermal .receipt-header { text-align: center; margin-bottom: 6px; }
        .receipt-thermal .receipt-header img { width: 46px; height: auto; }
        .receipt-thermal .receipt-header .name { font-size: 11px; font-weight: 700; margin-top: 3px; }
        .receipt-thermal .receipt-header .meta { font-size: 9px; color: var(--muted); line-height: 1.3; }
        .receipt-thermal .hr { border: 0; border-top: 1px dashed var(--border); margin: 6px 0; }
        .receipt-thermal .title { font-size: 12px; font-weight: 700; letter-spacing: 0.3px; text-align: center; margin: 3px 0; background: #f5f5f5; padding: 4px; }
        .receipt-thermal .details { font-size: 9px; line-height: 1.4; margin-bottom: 6px; }
        .receipt-thermal .details b { display: inline-block; width: 60px; }
        .receipt-thermal .amount-box { background: #111; color: #fff; padding: 10px 6px; text-align: center; margin: 8px 0; }
        .receipt-thermal .amount-box .label { font-size: 9px; opacity: 0.8; }
        .receipt-thermal .amount-box .value { font-size: 16px; font-weight: 700; margin: 2px 0; }
        .receipt-thermal .amount-box .sub { font-size: 8px; opacity: 0.7; }
        .receipt-thermal .balance-row { display: flex; justify-content: space-between; font-size: 9px; padding: 3px 0; border-bottom: 1px dotted var(--border); }
        .receipt-thermal .balance-row:last-child { border-bottom: none; font-weight: 700; }
        .receipt-thermal .notes { margin-top: 6px; font-size: 8px; color: var(--muted); font-style: italic; }
        .receipt-thermal .footer { margin-top: 8px; text-align: center; font-size: 8px; color: var(--muted); }
        .receipt-thermal .footer .barcode { font-family: 'Libre Barcode 39', monospace; font-size: 24px; letter-spacing: -2px; }
        @media print {
            .receipt-thermal { width: 55mm; margin: 0; padding: 0; }
            .receipt-thermal .wrap { padding: 6px; }
        }
    </style>
</head>
<body>
    <div class="receipt-thermal">
    <div class="wrap">
        <div class="receipt-header">
            @if($site->logo)
            <img src="data:image/jpeg;base64,{{ $site->logo }}" alt="Logo"/><br>
            @endif
            <div class="name">{{ $site->site_name }}</div>
            <div class="meta">{{ $site->contact_address }}<br>Tel: {{ $site->contact_phones }}</div>
        </div>

        <hr class="hr">
        <div class="title">💰 DEPOSIT RECEIPT</div>
        <hr class="hr">

        <div class="details">
            <b>Receipt:</b> {{ $depositNumber }}<br>
            <b>Date:</b> {{ $date }}<br>
            <b>Patient:</b> {{ $patientName }}<br>
            <b>File No:</b> {{ $patientFileNo ?? 'N/A' }}<br>
            <b>Type:</b> {{ $depositType }}<br>
            <b>Method:</b> {{ $paymentMethod }}<br>
            @if($bank)
            <b>Bank:</b> {{ $bank }}<br>
            @endif
            @if($paymentReference)
            <b>Ref:</b> {{ $paymentReference }}<br>
            @endif
        </div>

        <div class="amount-box">
            <div class="label">AMOUNT DEPOSITED</div>
            <div class="value">₦{{ number_format($amount, 2) }}</div>
            <div class="sub">{{ $amountInWords }}</div>
        </div>

        <div style="background: #f9f9f9; padding: 6px; margin: 6px 0;">
            <div class="balance-row">
                <span>Previous Balance:</span>
                <span>₦{{ number_format($previousBalance, 2) }}</span>
            </div>
            <div class="balance-row">
                <span>Deposit:</span>
                <span style="color: var(--success);">+₦{{ number_format($amount, 2) }}</span>
            </div>
            <div class="balance-row">
                <span>New Balance:</span>
                <span>₦{{ number_format($newBalance, 2) }}</span>
            </div>
        </div>

        @if($notes)
        <div class="notes">Note: {{ $notes }}</div>
        @endif

        <hr class="hr">

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
            <div>Received by: {{ $receivedBy }}</div>
            <div style="margin-top: 4px;">Received via: {{ $paymentMethodDisplay }}</div>
            <div style="margin-top: 4px;">{{ now()->format('Y-m-d H:i:s') }}</div>
            <div style="margin-top: 6px; font-size: 7px;">Thank you for your deposit!</div>
            <div style="margin-top: 4px;">--- END OF RECEIPT ---</div>
        </div>
    </div>
    </div>
</body>
</html>
