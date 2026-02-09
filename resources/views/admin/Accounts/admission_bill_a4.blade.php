{{-- Admission Bill A4 Template --}}
<!DOCTYPE html>
<html>
<head>
    <title>Admission Bill (A4)</title>
    <style>
        :root {
            --brand: {{ $site->hos_color ?? '#0a6cf2' }};
            --ink: #1d1d1f;
            --muted: #5f6368;
            --border: #d7d9dc;
            --bg: #f7f9fb;
        }
        * { box-sizing: border-box; }
        .admission-bill { margin: 0; padding: 24px; font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: var(--ink); background: var(--bg); }
        .admission-bill .sheet { background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 24px; box-shadow: 0 8px 24px rgba(0,0,0,0.04); max-width: 900px; width: 100%; margin: 0 auto; }
        .admission-bill .brand-bar { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid var(--brand); padding-bottom: 12px; margin-bottom: 16px; }
        .admission-bill .brand-left { display: flex; align-items: center; gap: 12px; }
        .admission-bill .brand-left img { width: 72px; height: auto; }
        .admission-bill .brand-name { font-size: 20px; font-weight: 700; color: var(--brand); letter-spacing: 0.5px; }
        .admission-bill .brand-meta { font-size: 11px; color: var(--muted); line-height: 1.4; }
        .admission-bill .doc-title { text-align: right; font-size: 22px; font-weight: 700; letter-spacing: 1px; color: var(--brand); }
        .admission-bill .doc-title small { display: block; font-size: 11px; color: var(--muted); font-weight: 500; }

        .admission-bill .admission-header { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-radius: 8px; padding: 16px; margin-bottom: 16px; border-left: 4px solid var(--brand); }
        .admission-bill .admission-header h3 { margin: 0 0 12px 0; font-size: 16px; color: var(--brand); }
        .admission-bill .admission-info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .admission-bill .admission-info-item { }
        .admission-bill .admission-info-item .label { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .admission-bill .admission-info-item .value { font-size: 13px; font-weight: 600; color: var(--ink); }
        .admission-bill .admission-info-item.full-width { grid-column: span 3; }

        .admission-bill .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .admission-bill .status-badge.admitted { background: #dcfce7; color: #166534; }
        .admission-bill .status-badge.discharged { background: #f3f4f6; color: #4b5563; }

        .admission-bill .section-title { font-size: 14px; font-weight: 700; color: var(--ink); margin: 20px 0 12px 0; padding-bottom: 8px; border-bottom: 2px solid var(--border); }

        .admission-bill .category-block { margin-bottom: 16px; }
        .admission-bill .category-header { display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 10px 12px; border-radius: 6px; margin-bottom: 8px; }
        .admission-bill .category-name { font-weight: 600; color: var(--ink); display: flex; align-items: center; gap: 8px; }
        .admission-bill .category-icon { width: 24px; height: 24px; background: var(--brand); color: white; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        .admission-bill .category-total { font-weight: 700; color: var(--brand); }

        .admission-bill .category-items { margin-left: 32px; }
        .admission-bill .category-items table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .admission-bill .category-items th { text-align: left; padding: 6px 8px; background: #f1f5f9; color: var(--muted); font-weight: 600; text-transform: uppercase; font-size: 10px; }
        .admission-bill .category-items td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
        .admission-bill .category-items .text-right { text-align: right; }

        .admission-bill .totals-section { background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%); border-radius: 8px; padding: 16px; margin-top: 20px; color: white; }
        .admission-bill .totals-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; }
        .admission-bill .totals-row.subtotal { opacity: 0.8; }
        .admission-bill .totals-row.grand-total { border-top: 1px solid rgba(255,255,255,0.3); margin-top: 8px; padding-top: 12px; font-size: 18px; font-weight: 700; }
        .admission-bill .totals-row .green { color: #86efac; }
        .admission-bill .totals-row .yellow { color: #fbbf24; }

        .admission-bill .amount-words { margin-top: 12px; font-size: 12px; font-style: italic; color: var(--muted); }

        .admission-bill .timeline-section { margin-top: 20px; }
        .admission-bill .timeline-day { background: #fafafa; border-left: 3px solid var(--brand); padding: 10px 12px; margin-bottom: 8px; border-radius: 0 6px 6px 0; }
        .admission-bill .timeline-day-header { font-weight: 600; color: var(--brand); font-size: 12px; margin-bottom: 6px; }
        .admission-bill .timeline-day-items { font-size: 11px; color: var(--muted); }
        .admission-bill .timeline-item { display: flex; justify-content: space-between; padding: 2px 0; }

        .admission-bill .footer { margin-top: 20px; text-align: right; font-size: 11px; color: var(--muted); border-top: 1px solid var(--border); padding-top: 12px; }

        @media print {
            @page { size: A4; margin: 10mm; }
            .admission-bill { background: #fff; padding: 0; }
            .admission-bill .sheet { box-shadow: none; border: none; margin: 0; width: 100%; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="admission-bill">
    <div class="sheet">
        <div class="brand-bar">
            <div class="brand-left">
                <img src="data:image/jpeg;base64,{{ $site->logo }}" alt="Logo">
                <div>
                    <div class="brand-name">{{ $site->site_name }}</div>
                    <div class="brand-meta">{{ $site->contact_address }}<br>Phone: {{ $site->contact_phones }} | Email: {{ $site->contact_emails }}</div>
                </div>
            </div>
            <div class="doc-title">
                ADMISSION BILL
                <small>{{ $billNo }}</small>
            </div>
        </div>

        <!-- Admission Header -->
        <div class="admission-header">
            <h3>
                {{ $admission['patient_name'] }}
                <span class="status-badge {{ $admission['status'] }}">
                    {{ $admission['status'] === 'admitted' ? '● Currently Admitted' : '● Discharged' }}
                </span>
            </h3>
            <div class="admission-info-grid">
                <div class="admission-info-item">
                    <div class="label">File Number</div>
                    <div class="value">{{ $admission['patient_file_no'] }}</div>
                </div>
                <div class="admission-info-item">
                    <div class="label">Admitted</div>
                    <div class="value">{{ $admission['admitted_date'] }}</div>
                </div>
                <div class="admission-info-item">
                    <div class="label">Discharged</div>
                    <div class="value">{{ $admission['discharge_date'] }}</div>
                </div>
                <div class="admission-info-item">
                    <div class="label">Length of Stay</div>
                    <div class="value">{{ $admission['los'] }}</div>
                </div>
                <div class="admission-info-item">
                    <div class="label">Ward / Bed</div>
                    <div class="value">{{ $admission['ward'] }} / {{ $admission['bed'] }}</div>
                </div>
                <div class="admission-info-item">
                    <div class="label">Attending Doctor</div>
                    <div class="value">{{ $admission['doctor'] }}</div>
                </div>
                <div class="admission-info-item full-width">
                    <div class="label">Admission Reason</div>
                    <div class="value">{{ $admission['reason'] }}</div>
                </div>
            </div>
        </div>

        <!-- Categorized Bill -->
        <div class="section-title">Bill Summary by Category</div>

        @foreach($categories as $category)
        <div class="category-block">
            <div class="category-header">
                <div class="category-name">
                    <span class="category-icon">{{ $category['count'] }}</span>
                    {{ $category['name'] }}
                </div>
                <div class="category-total">₦{{ number_format($category['total'], 2) }}</div>
            </div>
            <div class="category-items">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($category['items'] as $item)
                        <tr>
                            <td>{{ $item['date'] }}</td>
                            <td>{{ $item['name'] }}</td>
                            <td>{{ $item['qty'] }}</td>
                            <td class="text-right">₦{{ number_format($item['price'], 2) }}</td>
                            <td class="text-right">₦{{ number_format($item['amount'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach

        <!-- Totals -->
        <div class="totals-section">
            <div class="totals-row subtotal">
                <span>Gross Total:</span>
                <span>₦{{ number_format($totals['gross'], 2) }}</span>
            </div>
            @if($totals['discount'] > 0)
            <div class="totals-row subtotal">
                <span>Total Discount:</span>
                <span class="green">-₦{{ number_format($totals['discount'], 2) }}</span>
            </div>
            @endif
            @if($totals['hmo'] > 0)
            <div class="totals-row subtotal">
                <span>HMO Coverage:</span>
                <span class="green">-₦{{ number_format($totals['hmo'], 2) }}</span>
            </div>
            @endif
            <div class="totals-row subtotal">
                <span>Amount Paid:</span>
                <span class="green">-₦{{ number_format($totals['paid'], 2) }}</span>
            </div>
            <div class="totals-row grand-total">
                <span>Balance Due:</span>
                <span class="yellow">₦{{ number_format($totals['balance'], 2) }}</span>
            </div>
        </div>

        <div class="amount-words">
            <strong>Amount in Words:</strong> {{ $amountInWords }}
        </div>

        <!-- Day-by-Day Timeline -->
        @if(count($timeline) > 0)
        <div class="timeline-section">
            <div class="section-title">Day-by-Day Breakdown</div>
            @foreach($timeline as $day)
            <div class="timeline-day">
                <div class="timeline-day-header">Day {{ $day['day_number'] }} - {{ $day['date'] }} (₦{{ number_format($day['total'], 2) }})</div>
                <div class="timeline-day-items">
                    @foreach($day['items'] as $item)
                    <div class="timeline-item">
                        <span>{{ $item['name'] }}</span>
                        <span>₦{{ number_format($item['amount'], 2) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <div class="footer">
            Generated: {{ $date }} | Prepared by: {{ $currentUserName }}<br>
            This document is a summary of charges incurred during the admission period.
        </div>
    </div>
    </div>
</body>
</html>
