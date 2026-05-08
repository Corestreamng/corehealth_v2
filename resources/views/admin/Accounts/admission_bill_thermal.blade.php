{{-- Admission Bill Thermal Template --}}
<!DOCTYPE html>
<html>
<head>
    <title>Admission Bill (Thermal)</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        .admission-bill-thermal {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 19px;
            color: #000;
            background: #fff;
            width: 78mm;
            padding: 7px;
            line-height: 1.4;
        }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 8px; }
        .header img { width: 96px; height: auto; margin-bottom: 5px; }
        .header .name { font-weight: bold; font-size: 22px; }
        .header .address { font-size: 16px; color: #333; line-height: 1.5; }
        .title { text-align: center; font-weight: bold; font-size: 22px; margin: 8px 0; letter-spacing: 1px; border-bottom: 2px solid #000; padding-bottom: 6px; }
        .bill-no { text-align: center; font-size: 16px; color: #222; margin-bottom: 8px; }
        .patient-info { margin-bottom: 8px; font-size: 16px; line-height: 1.7; }
        .patient-info .patient-name { font-weight: bold; font-size: 18px; margin-bottom: 4px; }
        .info-row { display: flex; justify-content: space-between; }
        .status-badge { display: inline-block; padding: 3px 8px; font-size: 14px; font-weight: bold; border: 1px solid #000; margin-top: 4px; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }

        .category-section { margin-bottom: 8px; }
        .category-header { font-weight: bold; background: #eee; padding: 5px; margin-bottom: 4px; display: flex; justify-content: space-between; font-size: 17px; }
        .category-items { font-size: 16px; padding-left: 10px; }
        .category-item { display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px dotted #ccc; }
        .category-item:last-child { border-bottom: none; }

        .totals { margin-top: 8px; border-top: 1px solid #000; padding-top: 6px; }
        .total-row { display: flex; justify-content: space-between; font-size: 18px; padding: 3px 0; border-top: 1px dashed #bbb; }
        .total-row.grand { font-weight: bold; font-size: 21px; border-top: 2px solid #000; margin-top: 4px; padding-top: 6px; }

        .footer { text-align: center; font-size: 15px; color: #222; margin-top: 10px; border-top: 1px dashed #000; padding-top: 8px; line-height: 1.7; }

        @media print {
            @page { size: 78mm auto; margin: 0; }
            body { margin: 0; }
            .admission-bill-thermal { width: 78mm; padding: 5px; }
        }
    </style>
</head>
<body>
<div class="admission-bill-thermal">
    <div class="header">
        <img src="data:image/jpeg;base64,{{ $site->logo }}" alt="Logo">
        <div class="name">{{ $site->site_name }}</div>
        <div class="address">{{ $site->contact_address }}</div>
        <div class="address">Tel: {{ $site->contact_phones }}</div>
    </div>

    <div class="title">ADMISSION BILL</div>
    <div class="bill-no">{{ $billNo }}</div>

    <div class="patient-info">
        <div class="patient-name">{{ $admission['patient_name'] }}</div>
        <div class="info-row">
            <span>File No:</span>
            <span>{{ $admission['patient_file_no'] }}</span>
        </div>
        <div class="info-row">
            <span>Admitted:</span>
            <span>{{ $admission['admitted_date'] }}</span>
        </div>
        <div class="info-row">
            <span>Discharged:</span>
            <span>{{ $admission['discharge_date'] }}</span>
        </div>
        <div class="info-row">
            <span>Stay:</span>
            <span>{{ $admission['los'] }}</span>
        </div>
        <div class="info-row">
            <span>Ward/Bed:</span>
            <span>{{ $admission['ward'] }}/{{ $admission['bed'] }}</span>
        </div>
        <div style="margin-top: 4px;">
            <span class="status-badge">{{ strtoupper($admission['status']) }}</span>
        </div>
    </div>

    <div class="divider"></div>

    @foreach($categories as $category)
    <div class="category-section">
        <div class="category-header">
            <span>{{ $category['name'] }} ({{ $category['count'] }})</span>
            <span>{{ number_format($category['total'], 0) }}</span>
        </div>
        <div class="category-items">
            @foreach($category['items'] as $item)
            <div class="category-item">
                <span>{{ Str::limit($item['name'], 20) }}</span>
                <span>{{ number_format($item['amount'], 0) }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach

    <div class="totals">
        <div class="total-row">
            <span>Gross Total:</span>
            <span>₦{{ number_format($totals['gross'], 2) }}</span>
        </div>
        @if($totals['discount']> 0)
        <div class="total-row">
            <span>Discount:</span>
            <span>-₦{{ number_format($totals['discount'], 2) }}</span>
        </div>
        @endif
        @if($totals['hmo']> 0)
        <div class="total-row">
            <span>HMO:</span>
            <span>-₦{{ number_format($totals['hmo'], 2) }}</span>
        </div>
        @endif
        <div class="total-row">
            <span>Paid:</span>
            <span>-₦{{ number_format($totals['paid'], 2) }}</span>
        </div>
        <div class="total-row grand">
            <span>BALANCE:</span>
            <span>₦{{ number_format($totals['balance'], 2) }}</span>
        </div>
    </div>

    <div class="footer">
        Cashier: {{ $currentUserName }}<br>
        {{ $date }}<br>
        Thank you for choosing {{ $site->site_name }}
    </div>
</div>
</body>
</html>
