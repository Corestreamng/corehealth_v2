<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid {{ $hospital['color'] }};
            padding-bottom: 15px;
        }
        .header h2 {
            margin: 0;
            color: {{ $hospital['color'] }};
        }
        .header p {
            margin: 2px 0;
            font-size: 9px;
            color: #666;
        }
        .report-title {
            text-align: center;
            margin: 15px 0;
        }
        .report-title h3 {
            margin: 0;
            color: {{ $hospital['color'] }};
        }
        .report-title p {
            margin: 3px 0;
            font-size: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table th {
            background-color: {{ $hospital['color'] }};
            color: white;
            padding: 6px 4px;
            text-align: left;
            font-size: 9px;
        }
        table td {
            border: 1px solid #ddd;
            padding: 5px 4px;
            font-size: 9px;
        }
        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .summary-box {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .summary-box span {
            margin-right: 30px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
        }
        .badge-success { background: #28a745; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-danger { background: #dc3545; color: white; }
        .signatures {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        .signature-box {
            display: table-cell;
            width: 33%;
            text-align: center;
            padding: 0 10px;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8px;
            color: #999;
        }
        .amount {
            text-align: right;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $hospital['name'] }}</h2>
        <p>{{ $hospital['address'] }}</p>
        <p>Tel: {{ $hospital['phones'] }} | Email: {{ $hospital['emails'] }}</p>
    </div>

    <div class="report-title">
        <h3>{{ $report['title'] }}</h3>
        <p><strong>HMO:</strong> {{ $report['hmo_name'] }}</p>
        <p><strong>Period:</strong> {{ $report['period'] }}</p>
    </div>

    <div class="summary-box">
        <span><strong>Total Claims:</strong> ₦{{ number_format($summary['total_claims'], 2) }}</span>
        <span><strong>Approved:</strong> ₦{{ number_format($summary['total_approved'], 2) }}</span>
        <span><strong>Count:</strong> {{ $summary['total_count'] }}</span>
        <span><strong>Approved Count:</strong> {{ $summary['approved_count'] }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th width="3%">S/N</th>
                <th width="15%">Patient</th>
                <th width="8%">File No</th>
                <th width="10%">HMO No</th>
                <th width="8%">Date</th>
                <th width="20%">Item</th>
                <th width="5%">Type</th>
                <th width="8%">Auth Code</th>
                <th width="4%">Qty</th>
                <th width="10%">Amount</th>
                <th width="9%">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($claims as $index => $claim)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ userfullname($claim->user_id) }}</td>
                <td>{{ $claim->user->patient_profile->file_no ?? 'N/A' }}</td>
                <td>{{ $claim->user->patient_profile->hmo_no ?? 'N/A' }}</td>
                <td>{{ $claim->created_at ? \Carbon\Carbon::parse($claim->created_at)->format('M d, Y') : 'N/A' }}</td>
                <td>{{ \App\Helpers\HmoHelper::getDisplayName($claim) }}</td>
                <td>{{ $claim->product_id ? 'Product' : 'Service' }}</td>
                <td>{{ $claim->auth_code ?? '-' }}</td>
                <td class="text-center">{{ $claim->qty ?? 1 }}</td>
                <td class="amount">₦{{ number_format($claim->claims_amount, 2) }}</td>
                <td>
                    @if($claim->validation_status == 'approved')
                        <span class="badge badge-success">Approved</span>
                    @elseif($claim->validation_status == 'rejected')
                        <span class="badge badge-danger">Rejected</span>
                    @else
                        <span class="badge badge-warning">Pending</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background: #f0f0f0; font-weight: bold;">
                <td colspan="9" class="text-right">TOTAL:</td>
                <td class="amount">₦{{ number_format($summary['total_claims'], 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line">Prepared By</div>
            <small>{{ $report['generated_by'] }}</small>
        </div>
        <div class="signature-box">
            <div class="signature-line">Verified By</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">Authorized Signature</div>
        </div>
    </div>

    <div class="footer">
        Generated on {{ $report['generated_at'] }} | {{ $hospital['name'] }}
    </div>
</body>
</html>
