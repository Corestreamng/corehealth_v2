@extends('accounting.reports.pdf.layout')

@section('title', 'Transfer Voucher - ' . $transfer->transfer_number)
@section('report_title', 'Inter-Account Transfer Voucher')
@section('report_subtitle', $transfer->transfer_number . ' | ' . $transfer->transfer_date->format('F d, Y'))

@section('styles')
<style>
    body { font-size: 10px; }

    /* Transfer Header */
    .transfer-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .transfer-number {
        font-size: 18px;
        font-weight: 700;
        color: #212529;
    }

    .transfer-status {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 15px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-cleared { background: #d4edda; color: #155724; }
    .status-pending_approval { background: #fff3cd; color: #856404; }
    .status-approved { background: #cce5ff; color: #004085; }
    .status-initiated { background: #d1ecf1; color: #0c5460; }
    .status-in_transit { background: #e2e3e5; color: #383d41; }
    .status-failed { background: #f8d7da; color: #721c24; }
    .status-rejected { background: #f8d7da; color: #721c24; }
    .status-cancelled { background: #e2e3e5; color: #383d41; }

    /* Transfer Flow Section */
    .transfer-flow {
        margin-bottom: 20px;
    }

    .flow-container {
        display: table;
        width: 100%;
    }

    .flow-bank {
        display: table-cell;
        width: 40%;
        padding: 15px;
        vertical-align: top;
    }

    .flow-arrow {
        display: table-cell;
        width: 20%;
        text-align: center;
        vertical-align: middle;
    }

    .bank-box {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 15px;
        text-align: center;
    }

    .bank-box.source {
        border-left: 4px solid #dc3545;
    }

    .bank-box.destination {
        border-left: 4px solid #28a745;
    }

    .bank-icon {
        font-size: 24px;
        margin-bottom: 8px;
    }

    .bank-name {
        font-size: 12px;
        font-weight: 600;
        color: #212529;
        margin-bottom: 4px;
    }

    .bank-account {
        font-size: 10px;
        color: #6c757d;
    }

    .bank-type {
        font-size: 9px;
        color: #adb5bd;
        text-transform: uppercase;
        margin-top: 5px;
    }

    .flow-amount {
        font-size: 20px;
        font-weight: 700;
        color: #007bff;
        margin: 10px 0;
    }

    .flow-method {
        display: inline-block;
        padding: 3px 10px;
        background: #e9ecef;
        border-radius: 3px;
        font-size: 9px;
        font-weight: 600;
        text-transform: uppercase;
    }

    /* Detail Sections */
    .detail-section {
        margin-bottom: 15px;
    }

    .section-title {
        font-size: 11px;
        font-weight: 700;
        color: #495057;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding-bottom: 5px;
        border-bottom: 2px solid #dee2e6;
        margin-bottom: 10px;
    }

    .detail-table {
        width: 100%;
        margin-bottom: 15px;
    }

    .detail-table td {
        padding: 6px 10px;
        border-bottom: 1px solid #e9ecef;
    }

    .detail-table .label {
        width: 35%;
        color: #6c757d;
        font-weight: 500;
    }

    .detail-table .value {
        color: #212529;
    }

    /* Two Column Layout */
    .two-column {
        display: table;
        width: 100%;
    }

    .two-column .column {
        display: table-cell;
        width: 50%;
        padding-right: 15px;
        vertical-align: top;
    }

    .two-column .column:last-child {
        padding-right: 0;
        padding-left: 15px;
    }

    /* Fee Box */
    .fee-box {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 4px;
        padding: 10px;
        margin-bottom: 15px;
    }

    .fee-box .fee-label {
        font-size: 9px;
        color: #856404;
        text-transform: uppercase;
    }

    .fee-box .fee-amount {
        font-size: 14px;
        font-weight: 600;
        color: #856404;
    }

    /* Journal Entry Table */
    .journal-table {
        width: 100%;
        border-collapse: collapse;
    }

    .journal-table th {
        background: #343a40;
        color: white;
        padding: 8px 10px;
        text-align: left;
        font-size: 9px;
        text-transform: uppercase;
    }

    .journal-table td {
        padding: 8px 10px;
        border-bottom: 1px solid #dee2e6;
    }

    .journal-table tfoot td {
        font-weight: 700;
        background: #f8f9fa;
        border-top: 2px solid #343a40;
    }

    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .text-success { color: #28a745; }
    .text-danger { color: #dc3545; }

    /* Timeline */
    .timeline-section {
        margin-top: 20px;
    }

    .timeline-item {
        padding: 8px 0;
        border-bottom: 1px dashed #dee2e6;
    }

    .timeline-item:last-child {
        border-bottom: none;
    }

    .timeline-item .timeline-date {
        font-size: 9px;
        color: #6c757d;
    }

    .timeline-item .timeline-action {
        font-weight: 600;
        color: #212529;
    }

    .timeline-item .timeline-user {
        font-size: 9px;
        color: #6c757d;
    }

    /* Signature Section */
    .signature-section {
        margin-top: 30px;
        display: table;
        width: 100%;
    }

    .signature-box {
        display: table-cell;
        width: 33.33%;
        text-align: center;
        padding: 10px;
    }

    .signature-line {
        border-top: 1px solid #212529;
        margin-top: 40px;
        padding-top: 5px;
        font-size: 10px;
    }

    .signature-title {
        font-size: 9px;
        color: #6c757d;
    }
</style>
@endsection

@section('content')
    <!-- Transfer Header -->
    <div class="transfer-header">
        <div style="display: table; width: 100%;">
            <div style="display: table-cell; width: 60%; vertical-align: middle;">
                <div class="transfer-number">{{ $transfer->transfer_number }}</div>
                <div style="color: #6c757d; font-size: 10px; margin-top: 3px;">
                    Created: {{ $transfer->created_at->format('M d, Y h:i A') }}
                </div>
            </div>
            <div style="display: table-cell; width: 40%; text-align: right; vertical-align: middle;">
                <span class="transfer-status status-{{ $transfer->status }}">
                    {{ str_replace('_', ' ', $transfer->status) }}
                </span>
            </div>
        </div>
    </div>

    <!-- Transfer Flow -->
    <div class="transfer-flow">
        <div class="flow-container">
            <div class="flow-bank">
                <div class="bank-box source">
                    <div class="bank-icon">🏦</div>
                    <div class="bank-name">{{ $transfer->fromBank?->bank_name ?? 'N/A' }}</div>
                    <div class="bank-account">{{ $transfer->fromBank?->account_number ?? '' }}</div>
                    <div class="bank-type">
                        {{ $transfer->fromBank?->is_cash_account ? '💵 Cash Account' : '🏛️ Bank Account' }}
                    </div>
                </div>
            </div>
            <div class="flow-arrow">
                <div style="font-size: 24px;">➔</div>
                <div class="flow-amount">₦{{ number_format($transfer->amount, 2) }}</div>
                <div class="flow-method">{{ strtoupper($transfer->transfer_method) }}</div>
            </div>
            <div class="flow-bank">
                <div class="bank-box destination">
                    <div class="bank-icon">🏦</div>
                    <div class="bank-name">{{ $transfer->toBank?->bank_name ?? 'N/A' }}</div>
                    <div class="bank-account">{{ $transfer->toBank?->account_number ?? '' }}</div>
                    <div class="bank-type">
                        {{ $transfer->toBank?->is_cash_account ? '💵 Cash Account' : '🏛️ Bank Account' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transfer Details -->
    <div class="two-column">
        <div class="column">
            <div class="detail-section">
                <div class="section-title">Transfer Information</div>
                <table class="detail-table">
                    <tr>
                        <td class="label">Reference Number</td>
                        <td class="value">{{ $transfer->reference ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Transfer Date</td>
                        <td class="value">{{ $transfer->transfer_date->format('F d, Y') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Transfer Method</td>
                        <td class="value">{{ strtoupper($transfer->transfer_method) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Expected Clearance</td>
                        <td class="value">{{ $transfer->expected_clearance_date?->format('F d, Y') ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Actual Clearance</td>
                        <td class="value">{{ $transfer->actual_clearance_date?->format('F d, Y') ?? 'Pending' }}</td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="column">
            <div class="detail-section">
                <div class="section-title">Authorization</div>
                <table class="detail-table">
                    <tr>
                        <td class="label">Initiated By</td>
                        <td class="value">{{ $transfer->initiator ? trim(($transfer->initiator->surname ?? '') . ' ' . ($transfer->initiator->firstname ?? '')) : 'System' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Initiated On</td>
                        <td class="value">{{ $transfer->created_at->format('M d, Y h:i A') }}</td>
                    </tr>
                    @if($transfer->approver)
                    <tr>
                        <td class="label">Approved By</td>
                        <td class="value">{{ trim(($transfer->approver->surname ?? '') . ' ' . ($transfer->approver->firstname ?? '')) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Approved On</td>
                        <td class="value">{{ $transfer->approved_at?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <!-- Description -->
    @if($transfer->description)
    <div class="detail-section">
        <div class="section-title">Description / Purpose</div>
        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px;">
            {{ $transfer->description }}
        </div>
    </div>
    @endif

    <!-- Fee Information -->
    @if($transfer->transfer_fee > 0)
    <div class="detail-section">
        <div class="section-title">Fee Information</div>
        <div class="two-column">
            <div class="column">
                <div class="fee-box">
                    <div class="fee-label">Transfer Fee</div>
                    <div class="fee-amount">₦{{ number_format($transfer->transfer_fee, 2) }}</div>
                </div>
            </div>
            <div class="column">
                <table class="detail-table">
                    <tr>
                        <td class="label">Fee Account</td>
                        <td class="value">{{ $transfer->feeAccount?->account_name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Total Deduction</td>
                        <td class="value text-danger" style="font-weight: 700;">₦{{ number_format($transfer->amount + $transfer->transfer_fee, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Journal Entry -->
    @if($transfer->journalEntry)
    <div class="detail-section">
        <div class="section-title">Journal Entry - {{ $transfer->journalEntry->entry_number }}</div>
        <table class="journal-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Account Code</th>
                    <th style="width: 35%;">Account Name</th>
                    <th style="width: 25%;">Description</th>
                    <th style="width: 12.5%;" class="text-right">Debit</th>
                    <th style="width: 12.5%;" class="text-right">Credit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transfer->journalEntry->lines as $line)
                <tr>
                    <td>{{ $line->account?->account_number ?? '-' }}</td>
                    <td>{{ $line->account?->account_name ?? '-' }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="text-right">{{ $line->debit > 0 ? '₦' . number_format($line->debit, 2) : '-' }}</td>
                    <td class="text-right">{{ $line->credit > 0 ? '₦' . number_format($line->credit, 2) : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><strong>Total</strong></td>
                    <td class="text-right">₦{{ number_format($transfer->journalEntry->lines->sum('debit'), 2) }}</td>
                    <td class="text-right">₦{{ number_format($transfer->journalEntry->lines->sum('credit'), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif

    <!-- Notes -->
    @if($transfer->notes)
    <div class="detail-section">
        <div class="section-title">Additional Notes</div>
        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-style: italic;">
            {{ $transfer->notes }}
        </div>
    </div>
    @endif

    <!-- Failure Reason -->
    @if($transfer->status === 'failed' && $transfer->failure_reason)
    <div class="detail-section">
        <div class="section-title" style="color: #dc3545;">Failure Reason</div>
        <div style="background: #f8d7da; padding: 10px; border-radius: 4px; color: #721c24; border: 1px solid #f5c6cb;">
            {{ $transfer->failure_reason }}
        </div>
    </div>
    @endif

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">Prepared By</div>
            <div class="signature-title">{{ $transfer->initiator ? trim(($transfer->initiator->surname ?? '') . ' ' . ($transfer->initiator->firstname ?? '')) : '_______________' }}</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">Approved By</div>
            <div class="signature-title">{{ $transfer->approver ? trim(($transfer->approver->surname ?? '') . ' ' . ($transfer->approver->firstname ?? '')) : '_______________' }}</div>
        </div>
        <div class="signature-box">
            <div class="signature-line">Received By</div>
            <div class="signature-title">_______________</div>
        </div>
    </div>

    <!-- Footer Note -->
    <div style="margin-top: 20px; font-size: 8px; color: #6c757d; text-align: center; border-top: 1px solid #dee2e6; padding-top: 10px;">
        This is a computer-generated document. Transfer voucher printed on {{ now()->format('F d, Y \a\t h:i A') }}.
    </div>
@endsection
