<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duty Shift Service Handover Audit — {{ $shiftLabel }} ({{ $startDate->format('d M Y') }})</title>
    <style>
        :root {
            --brand: {{ $site->hos_color ?? '#011b33' }};
            --brand-rgb: 1, 27, 51;
            --ink: #1e293b;
            --muted: #64748b;
            --border: #cbd5e1;
            --border-light: #e2e8f0;
            --bg-light: #f8fafc;
            --success: #15803d;
            --success-bg: #f0fdf4;
            --info: #0284c7;
            --info-bg: #f0f9ff;
            --warning: #b45309;
            --warning-bg: #fffbeb;
            --danger: #b91c1c;
            --danger-bg: #fef2f2;
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
            padding: 20px;
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
            margin-bottom: 12px;
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            max-width: 65px;
            max-height: 65px;
            object-fit: contain;
        }

        .hospital-name {
            font-size: 17px;
            font-weight: 800;
            color: var(--brand);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .hospital-meta {
            font-size: 9.5px;
            color: var(--muted);
            line-height: 1.35;
        }

        .doc-meta {
            text-align: right;
        }

        .doc-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--brand);
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .doc-subtitle {
            font-size: 10px;
            color: var(--muted);
            margin-top: 2px;
            font-weight: 600;
        }

        .shift-badge {
            display: inline-block;
            background: var(--brand);
            color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9.5px;
            font-weight: 700;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        /* Context Strip */
        .context-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-light);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 6px 12px;
            margin-bottom: 12px;
            font-size: 9.5px;
            flex-wrap: wrap;
            gap: 8px;
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
            padding: 7px 8px;
            text-align: center;
        }

        .kpi-card.total { border-left-color: #0d6efd; }
        .kpi-card.general { border-left-color: var(--success); }
        .kpi-card.inpatient { border-left-color: #8b5cf6; }
        .kpi-card.nhis { border-left-color: var(--info); }
        .kpi-card.tender { border-left-color: #059669; background: #ecfdf5; }

        .kpi-label {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--muted);
            letter-spacing: 0.3px;
        }

        .kpi-val {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--ink);
            margin-top: 2px;
            font-family: 'Consolas', 'Courier New', monospace;
        }

        .kpi-card.tender .kpi-val { color: var(--success); }
        .kpi-card.nhis .kpi-val { color: var(--info); }

        /* Section Headings */
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

        /* Data Tables */
        table.matrix-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
            margin-bottom: 14px;
        }

        table.matrix-table th {
            background: var(--brand);
            color: #fff;
            padding: 6px 7px;
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: 0.3px;
            border: 1px solid var(--brand);
        }

        table.matrix-table td {
            padding: 5px 7px;
            border: 1px solid var(--border);
            vertical-align: middle;
        }

        table.matrix-table tbody tr:nth-child(even) {
            background: #fbfcfd;
        }

        table.matrix-table tbody tr.row-highlight {
            background: #eff6ff;
            font-weight: 600;
        }

        table.matrix-table tfoot tr {
            background: #f1f5f9;
            font-weight: 700;
            border-top: 2px solid var(--brand);
        }

        table.matrix-table tfoot td {
            padding: 6px 7px;
            border: 1px solid var(--border);
        }

        .mono {
            font-family: 'Consolas', 'Courier New', monospace;
            font-variant-numeric: tabular-nums;
        }

        .text-end { text-align: right; }
        .text-center { text-align: center; }

        /* Tender Grid */
        .tender-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 6px;
            margin-bottom: 14px;
        }

        .tender-box {
            background: var(--bg-light);
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
            text-transform: uppercase;
            font-size: 8px;
        }

        .tender-box-val {
            font-size: 12px;
            font-weight: 700;
            color: var(--ink);
            font-family: 'Consolas', 'Courier New', monospace;
            margin-top: 2px;
        }

        .tender-sublist {
            margin-top: 3px;
            padding-top: 3px;
            border-top: 1px dashed var(--border);
            font-size: 8px;
            color: var(--muted);
        }

        /* Signatures Area */
        .signatures-area {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 16px;
            padding-top: 10px;
            border-top: 1px solid var(--border);
        }

        .sig-block {
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 8px 10px;
            background: #fff;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .sig-title {
            font-size: 9px;
            font-weight: 700;
            color: var(--brand);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 3px;
            margin-bottom: 5px;
        }

        .sig-meta {
            font-size: 8px;
            color: var(--muted);
            margin-top: 6px;
            line-height: 1.4;
        }

        .sig-line {
            border-bottom: 1px solid #94a3b8;
            margin-top: 16px;
            margin-bottom: 3px;
        }

        /* Print Media Styles */
        @media print {
            body {
                padding: 0;
                background: #fff;
            }
            .report-sheet {
                width: 100%;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="report-sheet">
        <!-- Header -->
        <div class="report-header">
            <div class="brand-section">
                @if(!empty($site->logo))
                    <img src="{{ asset('storage/' . $site->logo) }}" alt="Logo" class="brand-logo" onerror="this.style.display='none'">
                @endif
                <div>
                    <div class="hospital-name">{{ $site->site_name ?? 'Hospital Management System' }}</div>
                    <div class="hospital-meta">
                        {{ $site->address ?? 'Main Hospital Campus' }}
                        @if(!empty($site->phone_no)) &bull; Tel: {{ $site->phone_no }} @endif
                        @if(!empty($site->email)) &bull; {{ $site->email }} @endif
                    </div>
                    <div class="hospital-meta" style="margin-top: 1px; color: var(--brand); font-weight: 600;">
                        INTERNAL AUDIT & REVENUE ASSURANCE DIVISION
                    </div>
                </div>
            </div>
            <div class="doc-meta">
                <div class="doc-title">Duty Shift Handover Audit</div>
                <div class="doc-subtitle">Service Care Stream & Tender Reconciliation</div>
                <div class="shift-badge">{{ $shiftLabel }}</div>
            </div>
        </div>

        <!-- Filter Context Strip -->
        <div class="context-strip">
            <div class="context-item">
                <span class="context-label">Period:</span>
                <span class="context-val">{{ $startDate->format('d M Y') }} &rarr; {{ $endDate->format('d M Y') }}</span>
            </div>
            <div class="context-item">
                <span class="context-label">Duty Shift:</span>
                <span class="context-val">{{ $shiftLabel }}</span>
            </div>
            <div class="context-item">
                <span class="context-label">Officer / Cashier:</span>
                <span class="context-val">{{ $cashierName }}</span>
            </div>
            <div class="context-item">
                <span class="context-label">Care Stream:</span>
                <span class="context-val">{{ strtoupper($careStream) }}</span>
            </div>
            <div class="context-item">
                <span class="context-label">Generated:</span>
                <span class="context-val">{{ $printDate }} ({{ $printedBy }})</span>
            </div>
        </div>

        <!-- KPI Matrix -->
        <div class="kpi-matrix">
            <div class="kpi-card total">
                <div class="kpi-label">Total Gross Billed</div>
                <div class="kpi-val">₦{{ number_format($grandTotals['total_gross'], 2) }}</div>
            </div>
            <div class="kpi-card general">
                <div class="kpi-label">General (Outpatient)</div>
                <div class="kpi-val">₦{{ number_format($grandTotals['gen_amount'], 2) }}</div>
            </div>
            <div class="kpi-card inpatient">
                <div class="kpi-label">Inpatient (Wards)</div>
                <div class="kpi-val">₦{{ number_format($grandTotals['inpatient_amount'], 2) }}</div>
            </div>
            <div class="kpi-card nhis">
                <div class="kpi-label">Insured / Scheme Claims</div>
                <div class="kpi-val">₦{{ number_format($grandTotals['nhis_claims'], 2) }}</div>
            </div>
            <div class="kpi-card tender">
                <div class="kpi-label">Net Tender Collected</div>
                <div class="kpi-val">₦{{ number_format($tender['net_tender'], 2) }}</div>
            </div>
        </div>

        <!-- Section 1: Service Care Stream Matrix -->
        <div class="section-heading">
            <span><i class="mdi mdi-table"></i> Service Revenue Breakdown by Care Stream</span>
            <span style="font-size: 8.5px; color: var(--muted); text-transform: none;">General (OPD) &bull; Inpatient (IPD) &bull; Insured Schemes Pool (HMO, NHIA, State Health &amp; Retainerships)</span>
        </div>

        <table class="matrix-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Service Domain</th>
                    <th class="text-end" style="width: 15%;">General (Outpatient)</th>
                    <th class="text-end" style="width: 15%;">Inpatient (Wards)</th>
                    <th class="text-end" style="width: 15%;">Insured Claims</th>
                    <th class="text-end" style="width: 14%;">Insured Co-Pay</th>
                    <th class="text-end" style="width: 15%;">Total Gross (₦)</th>
                    <th class="text-center" style="width: 6%;">Volume</th>
                </tr>
            </thead>
            <tbody>
                @foreach($serviceMatrix as $row)
                <tr class="{{ $row['key'] === 'drugs' ? 'row-highlight' : '' }}">
                    <td>
                        <strong>{{ $row['name'] }}</strong>
                        <span style="font-size: 8px; color: var(--muted); display: block;">[{{ $row['code'] }}]</span>
                    </td>
                    <td class="text-end mono">
                        <div>₦{{ number_format($row['gen_amount'], 2) }}</div>
                        <small style="font-size: 7.5px; color: var(--muted);">{{ number_format($row['gen_count']) }} items</small>
                    </td>
                    <td class="text-end mono">
                        <div>₦{{ number_format($row['inpatient_amount'], 2) }}</div>
                        <small style="font-size: 7.5px; color: var(--muted);">{{ number_format($row['inpatient_count']) }} items</small>
                    </td>
                    <td class="text-end mono" style="color: var(--info);">
                        <div>₦{{ number_format($row['nhis_claims'], 2) }}</div>
                        <small style="font-size: 7.5px; color: var(--muted);">Claims AR</small>
                    </td>
                    <td class="text-end mono">
                        <div>₦{{ number_format($row['nhis_copay'], 2) }}</div>
                        <small style="font-size: 7.5px; color: var(--muted);">Patient Share</small>
                    </td>
                    <td class="text-end mono" style="font-weight: 700;">
                        ₦{{ number_format($row['total_gross'], 2) }}
                    </td>
                    <td class="text-center mono">
                        {{ number_format($row['total_volume']) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>TOTAL CARE SERVICES</strong></td>
                    <td class="text-end mono">
                        <strong>₦{{ number_format($grandTotals['gen_amount'], 2) }}</strong>
                        <div style="font-size: 7.5px;">{{ number_format($grandTotals['gen_count']) }} items</div>
                    </td>
                    <td class="text-end mono">
                        <strong>₦{{ number_format($grandTotals['inpatient_amount'], 2) }}</strong>
                        <div style="font-size: 7.5px;">{{ number_format($grandTotals['inpatient_count']) }} items</div>
                    </td>
                    <td class="text-end mono" style="color: var(--info);">
                        <strong>₦{{ number_format($grandTotals['nhis_claims'], 2) }}</strong>
                        <div style="font-size: 7.5px;">Claims Total</div>
                    </td>
                    <td class="text-end mono">
                        <strong>₦{{ number_format($grandTotals['nhis_copay'], 2) }}</strong>
                        <div style="font-size: 7.5px;">Co-pay Total</div>
                    </td>
                    <td class="text-end mono">
                        <strong style="font-size: 10.5px;">₦{{ number_format($grandTotals['total_gross'], 2) }}</strong>
                    </td>
                    <td class="text-center mono">
                        <strong>{{ number_format($grandTotals['total_volume']) }}</strong>
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- Section 2: Tender & Physical Handover Breakdown -->
        <div class="section-heading">
            <span><i class="mdi mdi-cash-register"></i> Shift Tender & Cashier Handover Reconciliation</span>
            <span style="font-size: 8.5px; color: var(--muted); text-transform: none;">Physical cash & electronic receipts collected during shift</span>
        </div>

        <div class="tender-grid">
            <div class="tender-box" style="border-left: 3px solid #16a34a;">
                <div class="tender-box-header">
                    <span>Cash Inflows</span>
                    <span>COLLECTED</span>
                </div>
                <div class="tender-box-val" style="color: #16a34a;">+₦{{ number_format($tender['cash_inflow'] ?? 0, 2) }}</div>
                <div class="tender-sublist">
                    {{ $tender['cash_inflow_count'] ?? 0 }} bill tenders &amp; deposits
                </div>
            </div>

            <div class="tender-box" style="border-left: 3px solid #dc2626; background: #fff5f5;">
                <div class="tender-box-header">
                    <span>Refunds &amp; Withdrawals</span>
                    <span>DISBURSED</span>
                </div>
                <div class="tender-box-val" style="color: #dc2626;">-₦{{ number_format(abs($tender['refunds'] ?? 0), 2) }}</div>
                <div class="tender-sublist">
                    {{ $tender['refunds_count'] ?? 0 }} deposit refund vouchers
                </div>
            </div>

            <div class="tender-box" style="border-left: 3px solid {{ $tender['cash'] < 0 ? '#dc2626' : '#16a34a' }};">
                <div class="tender-box-header">
                    <span>Physical Cash in Till</span>
                    <span>DRAWER</span>
                </div>
                <div class="tender-box-val" style="color: {{ $tender['cash'] < 0 ? '#dc2626' : '#16a34a' }};">₦{{ number_format($tender['cash'], 2) }}</div>
                <div class="tender-sublist">
                    Net currency to handover
                </div>
            </div>

            <div class="tender-box" style="border-left: 3px solid #0284c7;">
                <div class="tender-box-header">
                    <span>Electronic Tenders</span>
                    <span>POS / TRF</span>
                </div>
                <div class="tender-box-val" style="color: #0284c7;">₦{{ number_format(($tender['pos'] ?? 0) + ($tender['transfer'] ?? 0) + ($tender['mobile'] ?? 0), 2) }}</div>
                <div class="tender-sublist">
                    POS: ₦{{ number_format($tender['pos'] ?? 0, 2) }} &bull; Trf: ₦{{ number_format($tender['transfer'] ?? 0, 2) }}
                </div>
            </div>

            <div class="tender-box" style="border-left: 3px solid {{ $tender['net_tender'] < 0 ? '#dc2626' : '#d97706' }}; background: {{ $tender['net_tender'] < 0 ? '#fef2f2' : '#fffbeb' }};">
                <div class="tender-box-header">
                    <span>Net Handover</span>
                    <span>{{ $tender['net_tender'] < 0 ? 'DEFICIT (DISBURSED)' : 'RECONCILED' }}</span>
                </div>
                <div class="tender-box-val" style="color: {{ $tender['net_tender'] < 0 ? '#dc2626' : '#b45309' }}; font-size: 13px;">₦{{ number_format($tender['net_tender'], 2) }}</div>
                <div class="tender-sublist">
                    Total {{ $tender['total_payments_count'] }} recorded payment(s)
                </div>
            </div>
        </div>

        <div style="font-size: 8.5px; color: var(--muted); margin-bottom: 12px; background: #f8fafc; border: 1px solid var(--border-light); padding: 5px 8px; border-radius: 4px;">
            <strong>Secondary Settlement Summary:</strong>
            Deposit Applied: ₦{{ number_format($tender['deposit'], 2) }} &bull;
            Staff Credit: ₦{{ number_format($tender['staff_bill'], 2) }} &bull;
            Org Credit: ₦{{ number_format($tender['org_bill'], 2) }} &bull;
            <span style="color: #dc2626; font-weight: 700;">Refunds / Deposit Withdrawals: -₦{{ number_format(abs($tender['refunds'] ?? 0), 2) }} ({{ $tender['refunds_count'] ?? 0 }} vouchers)</span> &bull;
            Insured Schemes &amp; HMO Accrued Claims: ₦{{ number_format($grandTotals['nhis_claims'], 2) }}
        </div>

        <!-- Section 3: Tri-Party Handover & Accountability Certification -->
        <div class="signatures-area">
            <div class="sig-block">
                <div class="sig-title">1. Outgoing Duty Officer</div>
                <div style="font-size: 8.5px;">I certify that the above summary represents an exact reconciliation of services billed and cash/receipts collected during my shift.</div>
                <div class="sig-line"></div>
                <div class="sig-meta">
                    <strong>Name:</strong> {{ $cashierName }}<br>
                    <strong>Sign &amp; Time:</strong> _______________________ / ________
                </div>
            </div>

            <div class="sig-block">
                <div class="sig-title">2. Incoming Duty Officer</div>
                <div style="font-size: 8.5px;">I have physically verified the cash-in-till and POS merchant slips and hereby acknowledge receipt and handover takeover.</div>
                <div class="sig-line"></div>
                <div class="sig-meta">
                    <strong>Name:</strong> ________________________________<br>
                    <strong>Sign &amp; Time:</strong> _______________________ / ________
                </div>
            </div>

            <div class="sig-block">
                <div class="sig-title">3. Internal Audit / HOD Certification</div>
                <div style="font-size: 8.5px;">Reconciled against CoreHealth transactional database ledger and verified consistent with care stream policies.</div>
                <div class="sig-line"></div>
                <div class="sig-meta">
                    <strong>Auditor / HOD:</strong> _________________________<br>
                    <strong>Stamp &amp; Date:</strong> _______________________ / ________
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="margin-top: 14px; padding-top: 6px; border-top: 1px solid var(--border); font-size: 8px; color: var(--muted); display: flex; justify-content: space-between;">
            <div>
                {{ $site->site_name ?? 'CoreHealth Hospital' }} &bull; Official Ops Audit Shift Handover Document &bull; Generated: {{ $printDate }}
            </div>
            <div>
                CoreHealth HMIS v2 &bull; Page 1 of 1
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
