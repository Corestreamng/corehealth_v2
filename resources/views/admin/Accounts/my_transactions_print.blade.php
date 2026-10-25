<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Transactions Report - {{ $staffName }} ({{ $from }} to {{ $to }})</title>
    <style>
        :root {
            --brand: {{ $site->hos_color ?? '#011b33' }};
            --ink: #1d1d1f;
            --muted: #5f6368;
            --border: #d7d9dc;
            --bg: #f7f9fb;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Arial, sans-serif;
            font-size: 11px;
            color: var(--ink);
            background: #fff;
            padding: 16px;
            line-height: 1.4;
        }

        .report-sheet {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
        }

        /* Header */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid var(--brand);
            padding-bottom: 12px;
            margin-bottom: 14px;
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-logo {
            max-width: 70px;
            max-height: 70px;
            object-fit: contain;
        }

        .hospital-name {
            font-size: 18px;
            font-weight: 800;
            color: var(--brand);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .hospital-meta {
            font-size: 10px;
            color: var(--muted);
            line-height: 1.4;
        }

        .doc-meta {
            text-align: right;
        }

        .doc-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--brand);
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .doc-subtitle {
            font-size: 10px;
            color: var(--muted);
            margin-top: 3px;
        }

        /* Filter Context Strip */
        .context-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 14px;
            font-size: 10px;
        }

        .context-item {
            display: flex;
            gap: 5px;
        }

        .context-label {
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
        }

        .context-val {
            font-weight: 700;
            color: var(--ink);
        }

        /* KPI Matrix */
        .kpi-matrix {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }

        .kpi-card {
            background: #fff;
            border: 1px solid var(--border);
            border-left: 3px solid var(--brand);
            border-radius: 4px;
            padding: 8px 10px;
            text-align: center;
        }

        .kpi-card.total { border-left-color: var(--success); }
        .kpi-card.inflow { border-left-color: var(--brand); }
        .kpi-card.refund { border-left-color: var(--danger); }
        .kpi-card.count { border-left-color: var(--info); }
        .kpi-card.discount { border-left-color: var(--warning); }
        .kpi-card.voided { border-left-color: var(--danger); }

        .kpi-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--muted);
            letter-spacing: 0.3px;
        }

        .kpi-val {
            font-size: 14px;
            font-weight: 800;
            color: var(--ink);
            margin-top: 2px;
            font-family: 'Consolas', monospace;
        }

        .kpi-card.total .kpi-val { color: var(--success); }
        .kpi-card.inflow .kpi-val { color: var(--brand); }
        .kpi-card.refund .kpi-val { color: var(--danger); }
        .kpi-card.discount .kpi-val { color: #856404; }
        .kpi-card.voided .kpi-val { color: var(--danger); }

        /* Tender Breakdown Table */
        .section-heading {
            font-size: 11px;
            font-weight: 700;
            color: var(--brand);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 3px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tender-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 6px;
            margin-bottom: 14px;
        }

        .tender-box {
            background: #fafbfc;
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 9px;
        }

        .tender-box-header {
            display: flex;
            justify-content: space-between;
            color: var(--muted);
            font-weight: 600;
        }

        .tender-box-val {
            font-size: 12px;
            font-weight: 700;
            color: var(--ink);
            font-family: 'Consolas', monospace;
            margin-top: 2px;
        }

        /* Main Data Table */
        table.trans-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
            margin-bottom: 16px;
        }

        table.trans-table th {
            background: var(--brand);
            color: #fff;
            padding: 6px 8px;
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: 0.3px;
            border: 1px solid var(--brand);
        }

        table.trans-table td {
            padding: 5px 8px;
            border: 1px solid var(--border);
            vertical-align: top;
        }

        table.trans-table tbody tr:nth-child(even) {
            background: #fbfcfd;
        }

        table.trans-table tbody tr.row-voided {
            background: #fff5f5;
            color: #a0aec0;
        }

        table.trans-table tbody tr.row-voided td {
            text-decoration: line-through;
        }

        table.trans-table tbody tr.row-voided .no-strike {
            text-decoration: none !important;
        }

        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: 600;
        }

        .badge-method {
            background: rgba(1, 27, 51, 0.08);
            color: var(--brand);
            border: 1px solid rgba(1, 27, 51, 0.15);
        }

        .badge-voided {
            background: #ffe3e3;
            color: #c92a2a;
            border: 1px solid #ffa8a8;
            font-weight: 700;
        }

        .item-list {
            margin-top: 2px;
            font-size: 8.5px;
            color: var(--muted);
            padding-left: 12px;
        }

        .mono {
            font-family: 'Consolas', monospace;
            white-space: nowrap;
        }

        .text-end { text-align: right; }
        .text-center { text-align: center; }

        /* Totals Footer Row */
        .totals-row td {
            font-weight: 800;
            background: #f1f3f5 !important;
            border-top: 2px solid var(--brand);
            font-size: 10px;
        }

        /* Signatures Section */
        .signatures-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 30px;
            padding-top: 15px;
        }

        .sig-block {
            border-top: 1px dashed var(--border);
            padding-top: 6px;
            font-size: 9.5px;
        }

        .sig-title {
            font-weight: 700;
            color: var(--brand);
            text-transform: uppercase;
        }

        .sig-meta {
            color: var(--muted);
            font-size: 8.5px;
            margin-top: 2px;
        }

        /* Print Controls (Non-printing screen bar) */
        .no-print-toolbar {
            max-width: 210mm;
            margin: 0 auto 16px auto;
            background: #e7f5ff;
            border: 1px solid #a5d8ff;
            border-radius: 6px;
            padding: 10px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-print {
            background: var(--brand);
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-close-print {
            background: #6c757d;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 8px;
        }

        @media print {
            .no-print-toolbar {
                display: none !important;
            }
            body {
                padding: 0;
                background: #fff;
            }
            @page {
                size: A4 portrait;
                margin: 8mm 8mm 10mm 8mm;
            }
            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- On-Screen Action Bar (Hidden during print) -->
    <div class="no-print-toolbar">
        <div>
            <strong>Print Preview:</strong> My Transactions Report ({{ $from }} to {{ $to }})
        </div>
        <div>
            <button class="btn-print" onclick="window.print();">🖨️ Print Report</button>
            <button class="btn-close-print" onclick="window.close();">✕ Close</button>
        </div>
    </div>

    <div class="report-sheet">
        <!-- Header with Hospital Branding -->
        <div class="report-header">
            <div class="brand-section">
                @if(!empty($site->logo))
                    <img src="data:image/jpeg;base64,{{ $site->logo }}" alt="Hospital Logo" class="brand-logo">
                @endif
                <div>
                    <div class="hospital-name">{{ $site->site_name ?? 'CoreHealth Hospital' }}</div>
                    <div class="hospital-meta">
                        {{ $site->contact_address ?? '' }}<br>
                        Phone: {{ $site->contact_phones ?? 'N/A' }} | Email: {{ $site->contact_emails ?? 'N/A' }}
                    </div>
                </div>
            </div>
            <div class="doc-meta">
                <div class="doc-title">Cashier Transactions Report</div>
                <div class="doc-subtitle">
                    Period: <strong>{{ (str_contains($from, 'T') || str_contains($from, ':')) ? \Carbon\Carbon::parse(str_replace('T', ' ', $from))->format('M d, Y h:i A') : \Carbon\Carbon::parse($from)->format('M d, Y') }}</strong> to <strong>{{ (str_contains($to, 'T') || str_contains($to, ':')) ? \Carbon\Carbon::parse(str_replace('T', ' ', $to))->format('M d, Y h:i A') : \Carbon\Carbon::parse($to)->format('M d, Y') }}</strong><br>
                    Generated: {{ now()->format('d/m/Y h:i A') }}
                </div>
            </div>
        </div>

        <!-- Context Strip -->
        <div class="context-strip">
            <div class="context-item">
                <span class="context-label">Cashier / Staff:</span>
                <span class="context-val">{{ $staffName }}</span>
            </div>
            <div class="context-item">
                <span class="context-label">Tender Scope:</span>
                <span class="context-val">{{ $paymentType ?? 'All Payment Methods' }}</span>
            </div>
            <div class="context-item">
                <span class="context-label">Report ID:</span>
                <span class="context-val">TXR-{{ Auth::id() }}-{{ date('YmdHi') }}</span>
            </div>
        </div>

        <!-- 5-Card Financial KPI Matrix -->
        <div class="kpi-matrix">
            <div class="kpi-card total">
                <div class="kpi-label">Net Inflow Collected</div>
                <div class="kpi-val">₦{{ number_format((float) ($summary['total_amount'] ?? 0), 2) }}</div>
            </div>
            <div class="kpi-card inflow">
                <div class="kpi-label">Gross Cash Inflow</div>
                <div class="kpi-val">₦{{ number_format((float) ($summary['cash_inflow'] ?? 0), 2) }}</div>
            </div>
            <div class="kpi-card refund">
                <div class="kpi-label">Refunds &amp; Withdrawals</div>
                <div class="kpi-val">-₦{{ number_format((float) ($summary['refund_amount'] ?? 0), 2) }}</div>
            </div>
            <div class="kpi-card count">
                <div class="kpi-label">Active Transactions</div>
                <div class="kpi-val">{{ number_format($summary['count'] ?? 0) }}</div>
            </div>
            <div class="kpi-card discount">
                <div class="kpi-label">Total Discounts</div>
                <div class="kpi-val">₦{{ number_format((float) ($summary['total_discount'] ?? 0), 2) }}</div>
            </div>
        </div>

        <!-- Tender Breakdown Grid -->
        @if(!empty($summary['payment_method_kpis']))
        <div class="section-heading">
            <span>Payment Tender Breakdown</span>
            <small style="text-transform: none; font-size: 8.5px; color: var(--muted);">
                Net Collections by Tender @if(!empty($summary['split_transactions_count']) && $summary['split_transactions_count'] > 0)&bull; (Includes {{ $summary['split_transactions_count'] }} split transaction{{ $summary['split_transactions_count'] > 1 ? 's' : '' }})@endif
            </small>
        </div>
        <div class="tender-grid">
            @foreach($summary['payment_method_kpis'] as $code => $kpi)
            @if((!empty($kpi['amount']) && $kpi['amount'] > 0) || (!empty($kpi['count']) && $kpi['count'] > 0))
            <div class="tender-box">
                <div class="tender-box-header">
                    <span>{{ strtoupper($kpi['label'] ?? $code) }}</span>
                    <span>{{ $kpi['count'] }} tender{{ $kpi['count'] != 1 ? 's' : '' }}</span>
                </div>
                <div class="tender-box-val">₦{{ number_format((float) $kpi['amount'], 2) }}</div>
                @if(!empty($kpi['banks']) && count($kpi['banks']) > 0)
                <div style="margin-top: 4px; padding-top: 4px; border-top: 1px dotted #ccc; font-size: 8px; color: var(--muted);">
                    @foreach($kpi['banks'] as $b)
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1px;">
                        <span>{{ $b['name'] }} ({{ $b['count'] }}):</span>
                        <span class="mono" style="font-weight: bold; color: #222;">₦{{ number_format((float) $b['amount'], 2) }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endif
            @endforeach
        </div>
        @elseif(!empty($summary['by_type']) && count($summary['by_type']) > 0)
        <div class="section-heading">
            <span>Payment Tender Breakdown</span>
            <small style="text-transform: none; font-size: 8.5px; color: var(--muted);">Net Collections by Tender</small>
        </div>
        <div class="tender-grid">
            @foreach($summary['by_type'] as $type => $typeStat)
            <div class="tender-box">
                <div class="tender-box-header">
                    <span>{{ strtoupper(str_replace('_', ' ', $typeStat['label'] ?? $type)) }}</span>
                    <span>{{ $typeStat['count'] }} tx</span>
                </div>
                <div class="tender-box-val">₦{{ number_format((float) $typeStat['amount'], 2) }}</div>
                @if(!empty($typeStat['banks']) && count($typeStat['banks']) > 0)
                <div style="margin-top: 4px; padding-top: 4px; border-top: 1px dotted #ccc; font-size: 8px; color: var(--muted);">
                    @foreach($typeStat['banks'] as $b)
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1px;">
                        <span>{{ $b['name'] }} ({{ $b['count'] }}):</span>
                        <span class="mono" style="font-weight: bold; color: #222;">₦{{ number_format((float) $b['amount'], 2) }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        <!-- Category Summary Strip (Products vs Services) -->
        @if(!empty($summary['product_kpi']) || !empty($summary['service_kpi']))
        <div class="section-heading">
            <span>Item Categories Summary</span>
        </div>
        <div style="display: flex; gap: 12px; margin-bottom: 10px; font-size: 9.5px;">
            <div style="flex: 1; background: #f8f9fa; border: 1px solid var(--border); border-radius: 4px; padding: 6px 10px;">
                <strong>Pharmacy / Products:</strong>
                <span class="mono" style="color: var(--brand); font-weight: bold; margin-left: 6px;">
                    ₦{{ number_format((float) ($summary['product_total'] ?? 0), 2) }}
                </span>
                <span style="color: var(--muted); margin-left: 4px;">({{ $summary['product_kpi']['count'] ?? 0 }} items)</span>
            </div>
            <div style="flex: 1; background: #f8f9fa; border: 1px solid var(--border); border-radius: 4px; padding: 6px 10px;">
                <strong>Clinical / Services & Other:</strong>
                <span class="mono" style="color: var(--brand); font-weight: bold; margin-left: 6px;">
                    ₦{{ number_format((float) ($summary['service_total'] ?? 0), 2) }}
                </span>
                <span style="color: var(--muted); margin-left: 4px;">({{ $summary['service_kpi']['count'] ?? 0 }} items)</span>
            </div>
        </div>

        @if(!empty($summary['service_category_kpis']) || !empty($summary['product_category_kpis']))
        <div style="display: flex; gap: 12px; margin-bottom: 14px; font-size: 8.5px;">
            @if(!empty($summary['service_category_kpis']) && count($summary['service_category_kpis']) > 0)
            <div style="flex: 1; background: #fff; border: 1px solid var(--border); border-radius: 4px; padding: 6px 10px;">
                <div style="font-weight: bold; color: var(--brand); margin-bottom: 4px; text-transform: uppercase; font-size: 8px;">Clinical & Services Categories</div>
                <table style="width: 100%; border-collapse: collapse;">
                    @foreach($summary['service_category_kpis'] as $scat)
                    <tr style="border-bottom: 1px dotted #e9ecef;">
                        <td style="padding: 2px 0;">{{ $scat['name'] }}</td>
                        <td style="padding: 2px 4px; text-align: right; color: var(--muted);">{{ $scat['count'] }} item{{ $scat['count'] != 1 ? 's' : '' }}</td>
                        <td class="mono" style="padding: 2px 0 2px 6px; text-align: right; font-weight: bold;">₦{{ number_format((float) $scat['amount'], 2) }}</td>
                    </tr>
                    @endforeach
                </table>
            </div>
            @endif

            @if(!empty($summary['product_category_kpis']) && count($summary['product_category_kpis']) > 0)
            <div style="flex: 1; background: #fff; border: 1px solid var(--border); border-radius: 4px; padding: 6px 10px;">
                <div style="font-weight: bold; color: #2e7d32; margin-bottom: 4px; text-transform: uppercase; font-size: 8px;">Pharmacy & Product Categories</div>
                <table style="width: 100%; border-collapse: collapse;">
                    @foreach($summary['product_category_kpis'] as $pcat)
                    <tr style="border-bottom: 1px dotted #e9ecef;">
                        <td style="padding: 2px 0;">{{ $pcat['name'] }}</td>
                        <td style="padding: 2px 4px; text-align: right; color: var(--muted);">{{ $pcat['count'] }} item{{ $pcat['count'] != 1 ? 's' : '' }}</td>
                        <td class="mono" style="padding: 2px 0 2px 6px; text-align: right; font-weight: bold;">₦{{ number_format((float) $pcat['amount'], 2) }}</td>
                    </tr>
                    @endforeach
                </table>
            </div>
            @endif
        </div>
        @endif
        @endif

        <!-- Department Revenue Attribution Summary -->
        @if(!empty($summary['department_kpis']) && count($summary['department_kpis']) > 0)
        <div class="section-heading">
            <span>Department Revenue Attribution Summary</span>
        </div>
        <div style="background: #fff; border: 1px solid var(--border); border-radius: 4px; padding: 6px 10px; margin-bottom: 14px; font-size: 8.5px;">
            <div style="font-weight: bold; color: var(--brand); margin-bottom: 4px; text-transform: uppercase; font-size: 8px;">Revenue by Department</div>
            <table style="width: 100%; border-collapse: collapse;">
                @foreach($summary['department_kpis'] as $dkpi)
                <tr style="border-bottom: 1px dotted #e9ecef;">
                    <td style="padding: 2px 0; font-weight: 600;">{{ $dkpi['name'] }}</td>
                    <td style="padding: 2px 4px; text-align: right; color: var(--muted);">{{ $dkpi['count'] }} item{{ $dkpi['count'] != 1 ? 's' : '' }}</td>
                    <td class="mono" style="padding: 2px 0 2px 6px; text-align: right; font-weight: bold; color: var(--brand);">₦{{ number_format((float) $dkpi['amount'], 2) }}</td>
                </tr>
                @endforeach
            </table>
        </div>
        @endif

        <!-- Detailed Transaction Table -->
        <div class="section-heading">
            <span>Transaction Itemization</span>
            <small style="text-transform: none; font-size: 8.5px; color: var(--muted);">
                @if(!empty($isTruncated))
                    Showing latest {{ count($transactionsFormatted ?? []) }} of {{ number_format($totalCount ?? 0) }} entries &bull; Summary totals reflect all {{ number_format($totalCount ?? 0) }} records
                @else
                    Showing {{ count($transactionsFormatted ?? []) }} entries
                @endif
            </small>
        </div>

        <table class="trans-table">
            <thead>
                <tr>
                    <th width="30">#</th>
                    <th width="100">Date/Time</th>
                    <th width="90">Reference</th>
                    <th>Patient</th>
                    <th width="140">Tender / Method</th>
                    <th width="80" class="text-end">Amount</th>
                    <th width="70" class="text-end">Discount</th>
                    <th>Items Breakdown</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactionsFormatted as $idx => $tx)
                @php
                    $isRefund = ($tx['total'] < 0) || (!empty($tx['payment_type']) && in_array($tx['payment_type'], ['ACC_WITHDRAW', 'REFUND'])) || str_contains(strtoupper($tx['payment_method_display'] ?? ''), 'REFUND') || str_contains(strtoupper($tx['payment_method_display'] ?? ''), 'WITHDRAWAL');
                @endphp
                <tr class="{{ $tx['is_voided'] ? 'row-voided' : ($isRefund ? 'row-refund' : '') }}" @if($isRefund && !$tx['is_voided']) style="background: #fff8f8;" @endif>
                    <td class="text-center no-strike">{{ $idx + 1 }}</td>
                    <td class="mono no-strike">{{ $tx['created_at'] }}</td>
                    <td class="mono">
                        {{ $tx['reference_no'] ?: 'REF-' . $tx['id'] }}
                        @if($tx['is_voided'])
                            <div class="no-strike"><span class="badge badge-voided">VOIDED</span></div>
                        @endif
                    </td>
                    <td>
                        <strong>{{ $tx['patient_name'] }}</strong>
                        <div style="color: var(--muted); font-size: 8.5px;">File: {{ $tx['file_no'] }}</div>
                    </td>
                    <td>
                        @if($isRefund)
                            <span class="badge no-strike" style="background: #ffe3e3; color: #c92a2a; border: 1px solid #ffa8a8; font-weight: 700;">REFUND / WITHDRAWAL</span>
                        @else
                            <span class="badge badge-method no-strike">{{ $tx['payment_method_display'] }}</span>
                        @endif
                        @if(!empty($tx['bank_name']))
                            <div style="font-size: 8px; color: var(--muted); margin-top: 1px;">Bank: {{ $tx['bank_name'] }}</div>
                        @endif
                        @if($tx['is_split_payment'] && !empty($tx['splits']))
                            <div class="item-list no-strike" style="margin-top: 2px;">
                                @foreach($tx['splits'] as $sp)
                                    <div>• {{ $sp['payment_method'] }}: ₦{{ number_format((float) $sp['amount'], 2) }} {{ $sp['bank_name'] ? "({$sp['bank_name']})" : '' }}</div>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="text-end mono">
                        @if($isRefund)
                            <span style="color: var(--danger); font-weight: bold;">-₦{{ number_format(abs((float) $tx['total']), 2) }}</span>
                        @else
                            ₦{{ number_format((float) $tx['total'], 2) }}
                        @endif
                    </td>
                    <td class="text-end mono">
                        ₦{{ number_format((float) $tx['total_discount'], 2) }}
                    </td>
                    <td>
                        @if(!empty($tx['items']) && count($tx['items']) > 0)
                            <div class="item-list">
                                @foreach($tx['items'] as $item)
                                    <div>
                                        • {{ $item['name'] }}
                                        <span class="mono">(x{{ $item['qty'] }} = ₦{{ number_format((float) $item['amount'], 2) }})</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <span style="color: var(--muted); font-size: 8.5px;">General Payment</span>
                        @endif
                        @if($tx['is_voided'] && !empty($tx['void_reason']))
                            <div class="no-strike" style="font-size: 8px; color: var(--danger); margin-top: 2px;">
                                Void Reason: {{ $tx['void_reason'] }}
                            </div>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px; color: var(--muted);">
                        No transactions recorded for the selected period.
                    </td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="totals-row">
                    <td colspan="5" class="text-end">NET TOTALS (Excluding Voided):</td>
                    <td class="text-end mono">₦{{ number_format((float) ($summary['total_amount'] ?? 0), 2) }}</td>
                    <td class="text-end mono">₦{{ number_format((float) ($summary['total_discount'] ?? 0), 2) }}</td>
                    <td>{{ $summary['count'] ?? 0 }} Active Records</td>
                </tr>
            </tfoot>
        </table>

        <!-- Signatures & Accountability -->
        <div class="signatures-area">
            <div class="sig-block">
                <div class="sig-title">Cashier / Biller Certification</div>
                <div style="margin-top: 18px;">
                    <strong>{{ $staffName }}</strong>
                </div>
                <div class="sig-meta">Signature: __________________________ &nbsp;&nbsp;&nbsp; Date: __________________</div>
            </div>
            <div class="sig-block">
                <div class="sig-title">Audit & Accounts Verification</div>
                <div style="margin-top: 18px;">
                    <strong>Internal Auditor / Head of Accounts</strong>
                </div>
                <div class="sig-meta">Signature: __________________________ &nbsp;&nbsp;&nbsp; Date: __________________</div>
            </div>
        </div>

        <!-- Footer -->
        <div style="margin-top: 25px; padding-top: 8px; border-top: 1px solid var(--border); font-size: 8.5px; color: var(--muted); display: flex; justify-content: space-between;">
            <div>
                {{ $site->site_name ?? 'CoreHealth Hospital' }} &copy; {{ date('Y') }}. Generated from CoreHealth HMIS v2.
            </div>
            <div>
                Official Financial Audit Document &bull; Page 1 of 1
            </div>
        </div>
    </div>

    <!-- Auto Print Trigger -->
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>
