{{-- Patient Account Statement (A4) --}}
<div class="statement-a4-wrapper">
    <style scoped>
        .statement-a4-wrapper {
            --brand: {{ $site->hos_color ?? '#0a6cf2' }};
            --ink: #1d1d1f;
            --muted: #5f6368;
            --border: #d7d9dc;
            --bg: #f7f9fb;
            --success: #28a745;
            --danger: #dc3545;
            --info: #17a2b8;
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            color: var(--ink);
            background: #fff;
            box-sizing: border-box;
        }
        .statement-a4-wrapper * { box-sizing: border-box; }
        .statement-a4-wrapper .statement { padding: 20px; max-width: 210mm; margin: 0 auto; }

        /* Header */
        .statement-a4-wrapper .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid var(--brand); padding-bottom: 15px; margin-bottom: 15px; }
        .statement-a4-wrapper .header-left { display: flex; align-items: center; gap: 12px; }
        .statement-a4-wrapper .header-left img { width: 60px; height: auto; }
        .statement-a4-wrapper .hospital-name { font-size: 18px; font-weight: 700; color: var(--brand); }
        .statement-a4-wrapper .hospital-meta { font-size: 10px; color: var(--muted); line-height: 1.4; }
        .statement-a4-wrapper .header-right { text-align: right; }
        .statement-a4-wrapper .doc-title { font-size: 20px; font-weight: 700; color: var(--ink); letter-spacing: 1px; }
        .statement-a4-wrapper .doc-subtitle { font-size: 10px; color: var(--muted); }

        /* Patient Info */
        .statement-a4-wrapper .patient-info { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: var(--bg); padding: 12px; border-radius: 6px; margin-bottom: 15px; }
        .statement-a4-wrapper .info-label { font-size: 9px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .statement-a4-wrapper .info-value { font-size: 12px; font-weight: 600; color: var(--ink); }

        /* Summary Cards */
        .statement-a4-wrapper .summary-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 15px; }
        .statement-a4-wrapper .summary-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 10px; border-radius: 6px; text-align: center; border-left: 3px solid var(--brand); }
        .statement-a4-wrapper .summary-card.deposits { border-left-color: var(--success); }
        .statement-a4-wrapper .summary-card.payments { border-left-color: var(--info); }
        .statement-a4-wrapper .summary-card.withdrawals { border-left-color: var(--danger); }
        .statement-a4-wrapper .summary-card.balance { border-left-color: var(--brand); background: linear-gradient(135deg, var(--brand), #0056b3); color: white; }
        .statement-a4-wrapper .summary-card .value { font-size: 14px; font-weight: 700; }
        .statement-a4-wrapper .summary-card .label { font-size: 9px; opacity: 0.8; }

        /* Section Headers */
        .statement-a4-wrapper .section-header { font-size: 12px; font-weight: 700; color: var(--brand); border-bottom: 1px solid var(--border); padding-bottom: 5px; margin: 15px 0 10px 0; display: flex; align-items: center; gap: 6px; }

        /* Transaction Table */
        .statement-a4-wrapper table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .statement-a4-wrapper th { background: var(--brand); color: white; padding: 8px 6px; text-align: left; font-weight: 600; text-transform: uppercase; font-size: 9px; letter-spacing: 0.3px; }
        .statement-a4-wrapper td { padding: 6px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        .statement-a4-wrapper tr:nth-child(even) { background: #fafbfc; }

        .statement-a4-wrapper .tx-type { display: inline-flex; align-items: center; gap: 4px; padding: 2px 6px; border-radius: 10px; font-size: 9px; font-weight: 600; }
        .statement-a4-wrapper .tx-type.deposit { background: rgba(40, 167, 69, 0.1); color: var(--success); }
        .statement-a4-wrapper .tx-type.payment { background: rgba(23, 162, 184, 0.1); color: var(--info); }
        .statement-a4-wrapper .tx-type.withdrawal { background: rgba(220, 53, 69, 0.1); color: var(--danger); }
        .statement-a4-wrapper .tx-type.adjustment { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
        .statement-a4-wrapper .tx-type.service { background: rgba(255, 193, 7, 0.1); color: #856404; }

        .statement-a4-wrapper .amount { font-weight: 600; font-family: 'Consolas', monospace; }
        .statement-a4-wrapper .amount.credit { color: var(--success); }
        .statement-a4-wrapper .amount.debit { color: var(--danger); }
        .statement-a4-wrapper .amount.balance { color: var(--brand); font-weight: 700; }

        .statement-a4-wrapper .description { max-width: 200px; font-size: 9px; color: var(--muted); }

        /* Footer */
        .statement-a4-wrapper .footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; font-size: 9px; color: var(--muted); }
        .statement-a4-wrapper .footer-note { max-width: 60%; line-height: 1.4; }

        /* Totals Row */
        .statement-a4-wrapper .totals-row td { font-weight: 700; background: #f8f9fa; border-top: 2px solid var(--border); }
    </style>

    <div class="statement">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                @if($site->logo)
                <img src="data:image/jpeg;base64,{{ $site->logo }}" alt="Logo">
                @endif
                <div>
                    <div class="hospital-name">{{ $site->site_name }}</div>
                    <div class="hospital-meta">
                        {{ $site->contact_address }}<br>
                        Tel: {{ $site->contact_phones }} | Email: {{ $site->contact_emails }}
                    </div>
                </div>
            </div>
            <div class="header-right">
                <div class="doc-title">ACCOUNT STATEMENT</div>
                <div class="doc-subtitle">
                    Period: {{ $dateFrom }} to {{ $dateTo }}<br>
                    Generated: {{ now()->format('M d, Y h:i A') }}
                </div>
            </div>
        </div>

        <!-- Patient Info -->
        <div class="patient-info">
            <div class="info-group">
                <div class="info-label">Patient Name</div>
                <div class="info-value">{{ $patientName }}</div>
            </div>
            <div class="info-group">
                <div class="info-label">File Number</div>
                <div class="info-value">{{ $patientFileNo }}</div>
            </div>
            <div class="info-group">
                <div class="info-label">HMO / Scheme</div>
                <div class="info-value">{{ $patientHmo }}</div>
            </div>
            <div class="info-group">
                <div class="info-label">Phone</div>
                <div class="info-value">{{ $patientPhone }}</div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-row">
            <div class="summary-card deposits">
                <div class="value">₦{{ number_format($summary['total_deposits'], 2) }}</div>
                <div class="label">Total Deposits</div>
            </div>
            <div class="summary-card payments">
                <div class="value">₦{{ number_format($summary['total_payments'], 2) }}</div>
                <div class="label">Total Payments</div>
            </div>
            <div class="summary-card withdrawals">
                <div class="value">₦{{ number_format($summary['total_withdrawals'], 2) }}</div>
                <div class="label">Withdrawals/Refunds</div>
            </div>
            <div class="summary-card balance">
                <div class="value">₦{{ number_format($summary['closing_balance'], 2) }}</div>
                <div class="label">Current Balance</div>
            </div>
        </div>

        <!-- Transaction Table -->
        <div class="section-header">
            Transaction Details ({{ count($transactions) }} records)
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 80px;">Date</th>
                    <th style="width: 90px;">Reference</th>
                    <th style="width: 90px;">Type</th>
                    <th>Description</th>
                    <th style="width: 80px; text-align: right;">Debit (₦)</th>
                    <th style="width: 80px; text-align: right;">Credit (₦)</th>
                    <th style="width: 90px; text-align: right;">Balance (₦)</th>
                </tr>
            </thead>
            <tbody>
                @if($showOpeningBalance)
                <tr style="background: #e3f2fd;">
                    <td>{{ $dateFrom }}</td>
                    <td>—</td>
                    <td><span class="tx-type" style="background: #e3f2fd; color: var(--brand);">Opening</span></td>
                    <td>Opening Balance</td>
                    <td></td>
                    <td></td>
                    <td class="amount balance" style="text-align: right;">{{ number_format($summary['opening_balance'], 2) }}</td>
                </tr>
                @endif

                @forelse($transactions as $tx)
                <tr>
                    <td>{{ $tx['date'] }}<br><small style="color: var(--muted);">{{ $tx['time'] }}</small></td>
                    <td style="font-family: monospace; font-size: 9px;">{{ $tx['reference'] }}</td>
                    <td>
                        <span class="tx-type {{ $tx['type_class'] }}">
                            {{ $tx['type_label'] }}
                        </span>
                    </td>
                    <td class="description">
                        {{ $tx['description'] }}
                        @if($tx['items'])
                        <br><small style="color: var(--muted);">{{ $tx['items'] }}</small>
                        @endif
                    </td>
                    <td class="amount debit" style="text-align: right;">
                        @if($tx['debit'] > 0)
                        {{ number_format($tx['debit'], 2) }}
                        @endif
                    </td>
                    <td class="amount credit" style="text-align: right;">
                        @if($tx['credit'] > 0)
                        {{ number_format($tx['credit'], 2) }}
                        @endif
                    </td>
                    <td class="amount balance" style="text-align: right;">{{ number_format($tx['running_balance'], 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px; color: var(--muted);">
                        No transactions found for this period
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if(count($transactions) > 0)
            <tfoot>
                <tr class="totals-row">
                    <td colspan="4" style="text-align: right; font-weight: 700;">Period Totals:</td>
                    <td class="amount debit" style="text-align: right;">{{ number_format($summary['period_debits'], 2) }}</td>
                    <td class="amount credit" style="text-align: right;">{{ number_format($summary['period_credits'], 2) }}</td>
                    <td class="amount balance" style="text-align: right;">{{ number_format($summary['closing_balance'], 2) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-note">
                <strong>Note:</strong> This statement reflects all account activity including deposits,
                payments for services, withdrawals, and adjustments. A positive balance indicates credit
                available for future services. Please contact our billing department for any discrepancies.
            </div>
            <div style="text-align: right;">
                <div>Prepared by: {{ $preparedBy }}</div>
                <div>{{ now()->format('Y-m-d H:i:s') }}</div>
            </div>
        </div>
    </div>
</div>
