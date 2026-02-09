{{-- Invoice Thermal Template --}}
<!DOCTYPE html>
<html>
<head>
    <title>Invoice (Thermal)</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        .invoice-thermal {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            background: #fff;
            width: 80mm;
            padding: 8px;
            line-height: 1.4;
        }
        .invoice-thermal .header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 8px; margin-bottom: 8px; }
        .invoice-thermal .header img { width: 48px; height: auto; margin-bottom: 4px; }
        .invoice-thermal .header .name { font-weight: bold; font-size: 14px; }
        .invoice-thermal .header .address { font-size: 10px; color: #333; }
        .invoice-thermal .title { text-align: center; font-weight: bold; font-size: 16px; margin: 8px 0; letter-spacing: 2px; }
        .invoice-thermal .proforma-badge { text-align: center; background: #f59e0b; color: #fff; padding: 4px; font-size: 10px; font-weight: bold; letter-spacing: 1px; margin-bottom: 8px; }
        .invoice-thermal .info { font-size: 11px; margin-bottom: 8px; }
        .invoice-thermal .info div { display: flex; justify-content: space-between; }
        .invoice-thermal .info .label { color: #555; }
        .invoice-thermal .divider { border-top: 1px dashed #000; margin: 8px 0; }
        .invoice-thermal table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .invoice-thermal th, .invoice-thermal td { padding: 4px 2px; text-align: left; }
        .invoice-thermal th { border-bottom: 1px solid #000; font-weight: bold; }
        .invoice-thermal .amount { text-align: right; }
        .invoice-thermal .total-section { margin-top: 8px; font-size: 11px; }
        .invoice-thermal .total-section div { display: flex; justify-content: space-between; padding: 2px 0; }
        .invoice-thermal .grand-total { font-weight: bold; font-size: 14px; border-top: 1px double #000; padding-top: 4px; margin-top: 4px; }
        .invoice-thermal .hmo-coverage { color: #059669; }
        .invoice-thermal .footer { text-align: center; font-size: 10px; color: #555; margin-top: 12px; border-top: 1px dashed #000; padding-top: 8px; }
        .invoice-thermal .warning-note { background: #fef3c7; padding: 6px; font-size: 10px; text-align: center; margin-top: 8px; border: 1px solid #f59e0b; }
        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { margin: 0; }
            .invoice-thermal { width: 80mm; }
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

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="amount">Qty</th>
                <th class="amount">HMO</th>
                <th class="amount">Amt</th>
            </tr>
        </thead>
        <tbody>
        @foreach($invoiceDetails as $row)
            <tr>
                <td>{{ Str::limit($row['name'], 18) }}</td>
                <td class="amount">{{ $row['qty'] }}</td>
                <td class="amount hmo-coverage">{{ $row['hmo_coverage'] > 0 ? number_format($row['hmo_coverage'], 0) : '-' }}</td>
                <td class="amount">{{ number_format($row['amount'], 0) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <div class="total-section">
        <div><span>Subtotal:</span><span>₦{{ number_format($totalAmount + $totalDiscount, 2) }}</span></div>
        @if($totalDiscount > 0)
        <div><span>Discount:</span><span>-₦{{ number_format($totalDiscount, 2) }}</span></div>
        @endif
        <div><span>Total:</span><span>₦{{ number_format($totalAmount, 2) }}</span></div>
        @if($totalHmoCoverage > 0)
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
