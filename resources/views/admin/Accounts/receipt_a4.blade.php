{{-- filepath: c:\Users\MrApollos\Documents\work\corehealth_v2\resources\views\admin\Accounts\receipt_a4.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Receipt (A4)</title>
    <style>
        :root {
            --brand: {{ $site->hos_color ?? '#0a6cf2' }};
            --ink: #1d1d1f;
            --muted: #5f6368;
            --border: #d7d9dc;
            --bg: #f7f9fb;
        }
        * { box-sizing: border-box; }
        .receipt-a4 { margin: 0; padding: 24px; font-family: 'Segoe UI', Arial, sans-serif; font-size: 14px; color: var(--ink); background: var(--bg); }
        .receipt-a4 .sheet { background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 24px; box-shadow: 0 8px 24px rgba(0,0,0,0.04); max-width: 900px; width: 100%; margin: 0 auto; }
        .receipt-a4 .brand-bar { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid var(--brand); padding-bottom: 12px; margin-bottom: 16px; }
        .receipt-a4 .brand-left { display: flex; align-items: center; gap: 12px; }
        .receipt-a4 .brand-left img { width: 72px; height: auto; }
        .receipt-a4 .brand-name { font-size: 20px; font-weight: 700; color: var(--brand); letter-spacing: 0.5px; }
        .receipt-a4 .brand-meta { font-size: 12px; color: var(--muted); line-height: 1.4; }
        .receipt-a4 .doc-title { text-align: right; font-size: 24px; font-weight: 700; letter-spacing: 1px; color: var(--ink); }
        .receipt-a4 .doc-title small { display: block; font-size: 12px; color: var(--muted); font-weight: 500; }
        .receipt-a4 .details { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 8px 16px; margin-bottom: 16px; }
        .receipt-a4 .details div { font-size: 13px; color: var(--ink); }
        .receipt-a4 .badge { display: inline-block; background: rgba(10,108,242,0.08); color: var(--brand); padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; letter-spacing: 0.2px; }
        .receipt-a4 table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .receipt-a4 th, .receipt-a4 td { border: 1px solid var(--border); padding: 8px 10px; font-size: 13px; }
        .receipt-a4 thead th { background: rgba(10,108,242,0.06); color: var(--ink); font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
        .receipt-a4 tbody tr:nth-child(every) { background: #fff; }
        .receipt-a4 tbody tr:nth-child(odd) { background: #fbfcfd; }
        .receipt-a4 tfoot td { font-weight: 700; font-size: 13px; }
        .receipt-a4 .notes { margin-top: 12px; font-size: 12px; color: var(--muted); border-left: 3px solid var(--brand); padding-left: 10px; }
        .receipt-a4 .footer { margin-top: 18px; text-align: right; font-size: 12px; color: var(--muted); }
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
                <img src="data:image/jpeg;base64,{{ $site->logo }}" alt="Logo">
                <div>
                    <div class="brand-name">{{ $site->site_name }}</div>
                    <div class="brand-meta">{{ $site->contact_address }}<br>Phone: {{ $site->contact_phones }} | Email: {{ $site->contact_emails }}</div>
                </div>
            </div>
            <div class="doc-title">RECEIPT<small>{{ $ref }}</small></div>
        </div>

        <div class="details">
            <div><span class="badge">Patient</span><br>{{ $patientName }}</div>
            <div><span class="badge">File No.</span><br>{{ $patientFileNo ?? 'N/A' }}</div>
            <div><span class="badge">Date</span><br>{{ $date }}</div>
            <div><span class="badge">Served By</span><br>{{ $currentUserName }}</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Name</th>
                    <th>Unit Price</th>
                    <th>Qty</th>
                    <th>Disc %</th>
                    <th>Disc (₦)</th>
                    <th>Paid (₦)</th>
                </tr>
            </thead>
            <tbody>
            @foreach($receiptDetails as $row)
                <tr>
                    <td>{{ $row['type'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ number_format($row['price'], 2) }}</td>
                    <td>{{ $row['qty'] }}</td>
                    <td>{{ $row['discount_percent'] }}</td>
                    <td>{{ number_format($row['discount_amount'], 2) }}</td>
                    <td>{{ number_format($row['amount_paid'], 2) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" align="right">Total Discount</td>
                    <td colspan="2">{{ number_format($totalDiscount, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="5" align="right">Grand Total Paid</td>
                    <td colspan="2">{{ number_format($totalPaid, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <div style="margin-top:8px; font-size:12px; color: var(--ink);"><strong>Amount in Words:</strong> {{ $amountInWords ?? '' }}</div>

        <div class="notes">{!! !empty($notes) ? $notes : 'Funds received in good condition.' !!}</div>

        <div class="footer">Generated by: {{ $currentUserName }} | Received via: {{ $paymentType ?? 'N/A' }}</div>
    </div>
    </div>
</body>
</html>
