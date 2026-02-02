{{-- Patient Account Statement (Thermal 80mm) --}}
<div class="statement-thermal-wrapper">
    <style scoped>
        .statement-thermal-wrapper {
            --brand: {{ $site->hos_color ?? '#0a6cf2' }};
            --ink: #000;
            --muted: #555;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 11px;
            color: var(--ink);
            background: #fff;
            width: 72mm;
            margin: 0 auto;
            padding: 8px;
            box-sizing: border-box;
        }
        .statement-thermal-wrapper * { box-sizing: border-box; margin: 0; padding: 0; }

        .statement-thermal-wrapper .header { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 8px; margin-bottom: 8px; }
        .statement-thermal-wrapper .hospital-name { font-size: 14px; font-weight: bold; }
        .statement-thermal-wrapper .hospital-meta { font-size: 9px; color: var(--muted); }
        .statement-thermal-wrapper .doc-title { font-size: 12px; font-weight: bold; margin-top: 6px; letter-spacing: 1px; }

        .statement-thermal-wrapper .patient-info { font-size: 10px; margin-bottom: 8px; border-bottom: 1px dashed #000; padding-bottom: 8px; }
        .statement-thermal-wrapper .patient-info div { display: flex; justify-content: space-between; margin: 2px 0; }
        .statement-thermal-wrapper .patient-info .label { color: var(--muted); }
        .statement-thermal-wrapper .patient-info .value { font-weight: bold; text-align: right; }

        .statement-thermal-wrapper .summary-box { background: #f5f5f5; padding: 6px; margin-bottom: 8px; font-size: 10px; }
        .statement-thermal-wrapper .summary-row { display: flex; justify-content: space-between; margin: 2px 0; }
        .statement-thermal-wrapper .summary-row.balance { border-top: 1px solid #000; padding-top: 4px; margin-top: 4px; font-weight: bold; font-size: 12px; }

        .statement-thermal-wrapper table { width: 100%; border-collapse: collapse; font-size: 9px; margin-bottom: 8px; }
        .statement-thermal-wrapper th { background: #333; color: #fff; padding: 4px 2px; text-align: left; font-size: 8px; }
        .statement-thermal-wrapper td { padding: 3px 2px; border-bottom: 1px dotted #ccc; }
        .statement-thermal-wrapper .text-right { text-align: right; }

        .statement-thermal-wrapper .amount { font-family: 'Consolas', monospace; }
        .statement-thermal-wrapper .credit { }
        .statement-thermal-wrapper .debit { }

        .statement-thermal-wrapper .footer { font-size: 8px; text-align: center; color: var(--muted); border-top: 1px dashed #000; padding-top: 6px; margin-top: 8px; }
    </style>

    <div class="header">
        <div class="hospital-name">{{ $site->site_name }}</div>
        <div class="hospital-meta">{{ $site->contact_phones }}</div>
        <div class="doc-title">ACCOUNT STATEMENT</div>
        <div style="font-size: 9px;">{{ $dateFrom }} - {{ $dateTo }}</div>
    </div>

    <div class="patient-info">
        <div><span class="label">Patient:</span> <span class="value">{{ $patientName }}</span></div>
        <div><span class="label">File No:</span> <span class="value">{{ $patientFileNo }}</span></div>
        <div><span class="label">HMO:</span> <span class="value">{{ Str::limit($patientHmo, 20) }}</span></div>
    </div>

    <div class="summary-box">
        <div class="summary-row">
            <span>Total Deposits:</span>
            <span>₦{{ number_format($summary['total_deposits'], 2) }}</span>
        </div>
        <div class="summary-row">
            <span>Total Payments:</span>
            <span>₦{{ number_format($summary['total_payments'], 2) }}</span>
        </div>
        <div class="summary-row">
            <span>Withdrawals:</span>
            <span>₦{{ number_format($summary['total_withdrawals'], 2) }}</span>
        </div>
        <div class="summary-row balance">
            <span>BALANCE:</span>
            <span>₦{{ number_format($summary['closing_balance'], 2) }}</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th class="text-right">Dr</th>
                <th class="text-right">Cr</th>
                <th class="text-right">Bal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $tx)
            <tr>
                <td>{{ $tx['short_date'] }}</td>
                <td>{{ $tx['short_type'] }}</td>
                <td class="text-right amount debit">
                    @if($tx['debit'] > 0){{ number_format($tx['debit'], 0) }}@endif
                </td>
                <td class="text-right amount credit">
                    @if($tx['credit'] > 0){{ number_format($tx['credit'], 0) }}@endif
                </td>
                <td class="text-right amount">{{ number_format($tx['running_balance'], 0) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center;">No transactions</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ count($transactions) }} transactions<br>
        Generated: {{ now()->format('d/m/Y H:i') }}<br>
        By: {{ $preparedBy }}
    </div>
</div>
