{{-- Invoice Thermal Template --}}
<!DOCTYPE html>
<html>
<head>
    <title>Invoice (Thermal)</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        .invoice-thermal {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 19px;
            color: #000;
            background: #fff;
            width: 78mm;
            padding: 7px;
            line-height: 1.4;
        }
        .invoice-thermal .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 8px; }
        .invoice-thermal .header img { width: 96px; height: auto; margin-bottom: 5px; }
        .invoice-thermal .header .name { font-weight: bold; font-size: 22px; }
        .invoice-thermal .header .address { font-size: 16px; color: #333; line-height: 1.5; }
        .invoice-thermal .title { text-align: center; font-weight: bold; font-size: 22px; margin: 8px 0; letter-spacing: 2px; }
        .invoice-thermal .proforma-badge { text-align: center; background: #f59e0b; color: #fff; padding: 5px; font-size: 15px; font-weight: bold; letter-spacing: 1px; margin-bottom: 8px; }
        .invoice-thermal .info { font-size: 16px; margin-bottom: 8px; line-height: 1.7; }
        .invoice-thermal .info div { display: flex; justify-content: space-between; }
        .invoice-thermal .info .label { color: #555; }
        .invoice-thermal .divider { border-top: 1px dashed #000; margin: 8px 0; }
        .invoice-thermal .divider-solid { border-top: 2px solid #000; margin: 8px 0; }
        /* Stacked item cards — same pattern as receipt_thermal */
        .invoice-thermal .item-list { width: 100%; margin: 5px 0; }
        .invoice-thermal .item-row { border-top: 1px dashed #bbb; padding: 7px 0 5px; }
        .invoice-thermal .item-row:last-child { border-bottom: 1px dashed #bbb; }
        .invoice-thermal .item-name { font-size: 18px; font-weight: 700; }
        .invoice-thermal .item-type { font-size: 15px; color: #555; font-style: italic; margin-bottom: 3px; }
        .invoice-thermal .item-line { display: flex; justify-content: space-between; font-size: 16px; line-height: 1.6; }
        .invoice-thermal .item-line .label { color: #555; }
        .invoice-thermal .item-line .val { font-weight: 600; }
        .invoice-thermal .item-line .hmo { color: #059669; font-weight: 600; }
        .invoice-thermal .total-section { margin-top: 8px; font-size: 18px; }
        .invoice-thermal .total-section div { display: flex; justify-content: space-between; padding: 3px 0; border-top: 1px dashed #bbb; }
        .invoice-thermal .grand-total { font-weight: bold; font-size: 21px; border-top: 2px solid #000 !important; padding-top: 5px !important; margin-top: 2px; }
        .invoice-thermal .hmo-coverage { color: #059669; }
        .invoice-thermal .footer { text-align: center; font-size: 15px; color: #555; margin-top: 12px; border-top: 1px dashed #000; padding-top: 8px; line-height: 1.7; }
        .invoice-thermal .warning-note { background: #fef3c7; padding: 8px; font-size: 15px; text-align: center; margin-top: 8px; border: 1px solid #f59e0b; line-height: 1.5; }
        @media print {
            @page { size: 78mm auto; margin: 0; }
            body { margin: 0; }
            .invoice-thermal { width: 78mm; padding: 5px; }
        }
    </style>
</head>
<body>
<div class="invoice-thermal">
    <div class="header">
        <img src="data:image/jpeg;base64,{{ $site->logo }}" alt="Logo">
        <div class="name">{{ $site->site_name }}</div>
        <div class="address">{{ $site->contact_address }}</div>
        <div class="address">Tel: {{ $site->contact_phones }}</div>
    </div>

    <div class="title">INVOICE</div>
    <div class="proforma-badge">⚠ PROFORMA / UNPAID</div>

    <div class="info">
        <div><span class="label">Invoice #:</span><span>{{ $invoiceNo }}</span></div>
        <div><span class="label">Date:</span><span>{{ $date }}</span></div>
        <div><span class="label">Patient:</span><span>{{ $patientName }}</span></div>
        <div><span class="label">File No:</span><span>{{ $patientFileNo ?? 'N/A' }}</span></div>
        @if($hmoName)
        <div><span class="label">HMO:</span><span>{{ $hmoName }}</span></div>
        @endif
    </div>

    <div class="divider"></div>

    <div class="item-list">
    @foreach($invoiceDetails as $row)
        <div class="item-row">
            <div class="item-name">{{ $row['name'] }}</div>
            <div class="item-line">
                <span class="label">Qty × Unit Price</span>
                <span class="val">{{ $row['qty'] }} × ₦{{ number_format($row['price'] ?? ($row['amount'] / max($row['qty'],1)), 2) }}</span>
            </div>
            @if((float)($row['hmo_coverage'] ?? 0) > 0)
            <div class="item-line">
                <span class="label">HMO Coverage</span>
                <span class="hmo">-₦{{ number_format($row['hmo_coverage'], 2) }}</span>
            </div>
            @endif
            <div class="item-line">
                <span class="label">Amount</span>
                <span class="val">₦{{ number_format($row['amount'], 2) }}</span>
            </div>
        </div>
    @endforeach
    </div>

    <div class="divider-solid"></div>

    <div class="total-section">
        <div><span>Subtotal:</span><span>₦{{ number_format($totalAmount + $totalDiscount, 2) }}</span></div>
        @if($totalDiscount> 0)
        <div><span>Discount:</span><span>-₦{{ number_format($totalDiscount, 2) }}</span></div>
        @endif
        <div><span>Total:</span><span>₦{{ number_format($totalAmount, 2) }}</span></div>
        @if($totalHmoCoverage> 0)
        <div class="hmo-coverage"><span>HMO Coverage:</span><span>-₦{{ number_format($totalHmoCoverage, 2) }}</span></div>
        @endif
        <div class="grand-total"><span>PAYABLE:</span><span>₦{{ number_format($patientPayable, 2) }}</span></div>
    </div>

    <div class="warning-note">
        ⚠ This is a proforma invoice.<br>
        Valid for 7 days from date of issue.
    </div>

    <div class="footer">
        Cashier: {{ $currentUserName }}<br>
        {{ now()->format('Y-m-d H:i:s') }}<br>
        Thank you for choosing {{ $site->site_name }}
    </div>
</div>
</body>
</html>
