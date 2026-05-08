{{-- filepath: c:\Users\MrApollos\Documents\work\corehealth_v2\resources\views\admin\Accounts\receipt_thermal.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Receipt (Thermal)</title>
    <style>
        :root {
            --ink: #111;
            --muted: #555;
            --border: #bbb;
        }
        * { box-sizing: border-box; }
        .receipt-thermal { font-family: 'Segoe UI', Arial, sans-serif; font-size: 19px; width: 78mm; margin: 0; padding: 0; color: var(--ink); }
        .receipt-thermal .wrap { padding: 7px 7px 10px; }
        .receipt-thermal .receipt-header { text-align: center; margin-bottom: 8px; }
        .receipt-thermal .receipt-header img { width: 96px; height: auto; }
        .receipt-thermal .receipt-header .name { font-size: 22px; font-weight: 700; margin-top: 5px; letter-spacing: 0.3px; }
        .receipt-thermal .receipt-header .meta { font-size: 16px; color: var(--muted); line-height: 1.5; margin-top: 3px; }
        .receipt-thermal .hr { border: 0; border-top: 1px dashed var(--border); margin: 7px 0; }
        .receipt-thermal .hr-solid { border: 0; border-top: 2px solid var(--ink); margin: 7px 0; }
        .receipt-thermal .title { font-size: 22px; font-weight: 700; letter-spacing: 1.5px; text-align: center; margin: 5px 0 7px; }
        .receipt-thermal .details { font-size: 16px; line-height: 1.7; margin-bottom: 7px; }
        .receipt-thermal .details b { display: inline-block; width: 90px; }
        /* Line-item list — uses more vertical space and is much clearer */
        .receipt-thermal .item-list { width: 100%; margin: 5px 0; }
        .receipt-thermal .item-row { border-top: 1px dashed var(--border); padding: 7px 0 5px; }
        .receipt-thermal .item-row:last-child { border-bottom: 1px dashed var(--border); }
        .receipt-thermal .item-name { font-size: 18px; font-weight: 700; }
        .receipt-thermal .item-type { font-size: 15px; color: var(--muted); font-style: italic; margin-bottom: 3px; }
        .receipt-thermal .item-line { display: flex; justify-content: space-between; font-size: 16px; line-height: 1.6; }
        .receipt-thermal .item-line .label { color: var(--muted); }
        .receipt-thermal .item-line .val { font-weight: 600; }
        .receipt-thermal .totals-block { margin-top: 7px; }
        .receipt-thermal .total-row { display: flex; justify-content: space-between; font-size: 18px; padding: 3px 0; border-top: 1px dashed var(--border); }
        .receipt-thermal .total-row.grand { font-size: 21px; font-weight: 700; border-top: 2px solid var(--ink); padding-top: 5px; margin-top: 2px; }
        .receipt-thermal .in-words { margin-top: 5px; font-size: 16px; line-height: 1.5; }
        .receipt-thermal .receipt-notes { margin-top: 7px; font-size: 16px; color: var(--muted); line-height: 1.5; }
        .receipt-thermal .receipt-footer { margin-top: 8px; font-size: 16px; color: var(--muted); line-height: 1.6; }
        @media print {
            .receipt-thermal { width: 78mm; margin: 0; padding: 0; }
            .receipt-thermal .wrap { padding: 5px 5px 8px; }
        }
    </style>
</head>
<body>
    <div class="receipt-thermal">
    <div class="wrap">
        <div class="receipt-header">
            <img src="data:image/jpeg;base64,{{ $site->logo }}" alt="Logo"/><br>
            <div class="name">{{ $site->site_name }}</div>
            <div class="meta">{{ $site->contact_address }}<br>Phone: {{ $site->contact_phones }}</div>
        </div>
        <hr class="hr-solid">
        <div class="title">RECEIPT</div>
        <div class="details">
            <b>Patient:</b> {{ $patientName }}<br>
            <b>File No:</b> {{ $patientFileNo ?? 'N/A' }}<br>
            <b>Date:</b> {{ $date }}<br>
            <b>Ref:</b> {{ $ref }}<br>
            <b>Served By:</b> {{ $currentUserName }}<br>
        </div>
        <hr class="hr">

        {{-- Line items — stacked layout for clarity and vertical use --}}
        <div class="item-list">
        @foreach($receiptDetails as $row)
            <div class="item-row">
                <div class="item-name">{{ $row['name'] }}</div>
                <div class="item-type">{{ $row['type'] }}</div>
                <div class="item-line">
                    <span class="label">Qty × Unit Price</span>
                    <span class="val">{{ $row['qty'] }} × ₦{{ number_format($row['price'], 2) }}</span>
                </div>
                @if((float)$row['discount_amount'] > 0 || (float)$row['discount_percent'] > 0)
                <div class="item-line">
                    <span class="label">Discount ({{ $row['discount_percent'] }}%)</span>
                    <span class="val">-₦{{ number_format($row['discount_amount'], 2) }}</span>
                </div>
                @endif
                <div class="item-line">
                    <span class="label">Amount Paid</span>
                    <span class="val">₦{{ number_format($row['amount_paid'], 2) }}</span>
                </div>
            </div>
        @endforeach
        </div>

        <div class="totals-block">
            @if($totalDiscount > 0)
            <div class="total-row">
                <span>Total Discount</span>
                <span>-₦{{ number_format($totalDiscount, 2) }}</span>
            </div>
            @endif
            <div class="total-row grand">
                <span>GRAND TOTAL PAID</span>
                <span>₦{{ number_format($totalPaid, 2) }}</span>
            </div>
        </div>

        <div class="in-words"><strong>In Words:</strong> {{ $amountInWords ?? '' }}</div>
        <div class="receipt-notes">{!! !empty($notes) ? $notes : 'Funds received in good condition.' !!}</div>
        @php
            $paymentTypeNames = [
                // Uppercase (Billing Workbench)
                'CASH' => 'Cash',
                'POS' => 'POS/Card',
                'TRANSFER' => 'Bank Transfer',
                'MOBILE' => 'Mobile Money',
                'ACCOUNT' => 'Patient Account',
                'CHEQUE' => 'Cheque',
                'TELLER' => 'Teller',
                'ACC_DEPOSIT' => 'Account Deposit',
                'ACC_WITHDRAW' => 'Patient Account',
                'ACC_ADJUSTMENT' => 'Account Adjustment',
                // Lowercase (Accounting Module)
                'cash' => 'Cash',
                'pos' => 'POS/Card',
                'transfer' => 'Bank Transfer',
                'mobile' => 'Mobile Money',
                'cheque' => 'Cheque',
            ];
            $paymentTypeDisplay = $paymentTypeNames[$paymentType] ?? ucfirst($paymentType ?? 'N/A');
        @endphp
        <div class="receipt-footer">
            <em>Received via: <strong>{{ $paymentTypeDisplay }}</strong></em><br>
            <em>Generated by: {{ $currentUserName }}</em>
        </div>
    </div>
    </div>
</body>
</html>
