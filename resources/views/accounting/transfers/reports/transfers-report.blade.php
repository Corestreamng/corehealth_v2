@extends('accounting.reports.pdf.layout')

@section('title', 'Inter-Account Transfers Report')
@section('report_title', 'Inter-Account Transfers Report')
@section('report_subtitle', $dateFrom . ' to ' . $dateTo . ' | ' . $statusFilter)

@section('styles')
<style>
    body { font-size: 9px; }

    /* Summary Section */
    .summary-section {
        margin-bottom: 20px;
    }

    .summary-boxes {
        display: table;
        width: 100%;
        margin-bottom: 15px;
    }

    .summary-box {
        display: table-cell;
        width: 25%;
        padding: 10px;
        text-align: center;
        border: 1px solid #dee2e6;
        background-color: #f8f9fa;
    }

    .summary-box .label {
        font-size: 8px;
        color: #6c757d;
        text-transform: uppercase;
        margin-bottom: 4px;
        font-weight: 600;
    }

    .summary-box .count {
        font-size: 14px;
        font-weight: bold;
        color: #212529;
    }

    .summary-box .amount {
        font-size: 10px;
        color: #495057;
        margin-top: 2px;
    }

    .summary-box.total { border-left: 3px solid #0066cc; }
    .summary-box.total .count { color: #0066cc; }

    .summary-box.cleared { border-left: 3px solid #28a745; }
    .summary-box.cleared .count { color: #28a745; }

    .summary-box.pending { border-left: 3px solid #ffc107; }
    .summary-box.pending .count { color: #856404; }

    .summary-box.failed { border-left: 3px solid #dc3545; }
    .summary-box.failed .count { color: #dc3545; }

    /* Method Breakdown */
    .method-breakdown {
        margin-bottom: 15px;
    }

    .method-table {
        width: 50%;
        margin-bottom: 15px;
    }

    .method-table th {
        background-color: #e9ecef;
        font-size: 8px;
        padding: 6px;
    }

    .method-table td {
        padding: 6px;
        font-size: 9px;
    }

    /* Transfers Table */
    .transfers-table {
        margin-top: 10px;
        font-size: 8px;
    }

    .transfers-table thead th {
        background-color: #343a40;
        color: white;
        font-weight: bold;
        text-align: left;
        padding: 6px 4px;
        border-bottom: none;
        font-size: 7px;
        text-transform: uppercase;
    }

    .transfers-table tbody td {
        padding: 5px 4px;
        border-bottom: 1px solid #e9ecef;
        vertical-align: top;
    }

    .transfers-table tbody tr:nth-child(even) {
        background-color: #f8f9fa;
    }

    .text-right { text-align: right; }
    .text-center { text-align: center; }

    /* Status Badges */
    .badge {
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 7px;
        font-weight: bold;
        text-transform: uppercase;
    }

    .badge-cleared { background-color: #d4edda; color: #155724; }
    .badge-pending { background-color: #fff3cd; color: #856404; }
    .badge-approved { background-color: #cce5ff; color: #004085; }
    .badge-initiated { background-color: #d1ecf1; color: #0c5460; }
    .badge-in_transit { background-color: #e2e3e5; color: #383d41; }
    .badge-failed { background-color: #f8d7da; color: #721c24; }
    .badge-rejected { background-color: #f8d7da; color: #721c24; }
    .badge-reversed { background-color: #f5c6cb; color: #721c24; }

    /* Flow indicator */
    .flow-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 2px;
        margin-right: 4px;
    }
    .flow-bank-to-bank { background-color: #17a2b8; }
    .flow-bank-to-cash { background-color: #fd7e14; }
    .flow-cash-to-bank { background-color: #28a745; }
    .flow-cash-to-cash { background-color: #6f42c1; }

    .total-row {
        font-weight: bold;
        background-color: #e9ecef;
        border-top: 2px solid #343a40;
    }

    .total-row td {
        padding: 8px 4px;
    }

    .no-records {
        text-align: center;
        color: #6c757d;
        font-style: italic;
        padding: 30px;
    }

    .section-title {
        font-size: 11px;
        font-weight: bold;
        margin: 15px 0 10px 0;
        padding-bottom: 5px;
        border-bottom: 2px solid #dee2e6;
    }
</style>
@endsection

@section('content')
    <!-- Summary Section -->
    <div class="summary-section">
        <div class="summary-boxes">
            <div class="summary-box total">
                <div class="label">Total Transfers</div>
                <div class="count">{{ number_format($summary['total_count']) }}</div>
                <div class="amount">₦{{ number_format($summary['total_amount'], 2) }}</div>
            </div>
            <div class="summary-box cleared">
                <div class="label">Cleared</div>
                <div class="count">{{ number_format($summary['cleared_count']) }}</div>
                <div class="amount">₦{{ number_format($summary['cleared_amount'], 2) }}</div>
            </div>
            <div class="summary-box pending">
                <div class="label">Pending/In-Transit</div>
                <div class="count">{{ number_format($summary['pending_count']) }}</div>
                <div class="amount">₦{{ number_format($summary['pending_amount'], 2) }}</div>
            </div>
            <div class="summary-box failed">
                <div class="label">Failed</div>
                <div class="count">{{ number_format($summary['failed_count']) }}</div>
                <div class="amount">₦{{ number_format($summary['failed_amount'], 2) }}</div>
            </div>
        </div>

        @if($byMethod->isNotEmpty())
        <div class="method-breakdown">
            <div class="section-title">Breakdown by Transfer Method</div>
            <table class="method-table">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th class="text-center">Count</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($byMethod as $method)
                    <tr>
                        <td>{{ $method['method'] }}</td>
                        <td class="text-center">{{ $method['count'] }}</td>
                        <td class="text-right">₦{{ number_format($method['amount'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    <!-- Transfers List -->
    <div class="section-title">Transfer Details</div>

    @if($transfers->isEmpty())
        <div class="no-records">No transfers found for the selected criteria.</div>
    @else
        <table class="transfers-table">
            <thead>
                <tr>
                    <th style="width: 8%;">Transfer #</th>
                    <th style="width: 7%;">Date</th>
                    <th style="width: 14%;">From Account</th>
                    <th style="width: 14%;">To Account</th>
                    <th style="width: 9%;" class="text-right">Amount</th>
                    <th style="width: 7%;" class="text-right">Fee</th>
                    <th style="width: 7%;">Method</th>
                    <th style="width: 7%;">Status</th>
                    <th style="width: 12%;">Reference</th>
                    <th style="width: 8%;">Initiated</th>
                    <th style="width: 7%;">JE #</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transfers as $transfer)
                @php
                    $fromType = $transfer->fromBank?->is_cash_account ? 'cash' : 'bank';
                    $toType = $transfer->toBank?->is_cash_account ? 'cash' : 'bank';
                    $flowType = $fromType . '-to-' . $toType;

                    $statusClass = match($transfer->status) {
                        'cleared' => 'badge-cleared',
                        'pending_approval' => 'badge-pending',
                        'approved' => 'badge-approved',
                        'initiated' => 'badge-initiated',
                        'in_transit' => 'badge-in_transit',
                        'failed' => 'badge-failed',
                        'rejected' => 'badge-rejected',
                        'reversed' => 'badge-reversed',
                        default => 'badge-pending'
                    };
                @endphp
                <tr>
                    <td>{{ $transfer->transfer_number }}</td>
                    <td>{{ $transfer->transfer_date?->format('M d, Y') }}</td>
                    <td>
                        <span class="flow-indicator flow-{{ $flowType }}"></span>
                        {{ $transfer->fromBank?->bank_name ?? 'N/A' }}
                    </td>
                    <td>{{ $transfer->toBank?->bank_name ?? 'N/A' }}</td>
                    <td class="text-right">₦{{ number_format($transfer->amount, 2) }}</td>
                    <td class="text-right">
                        @if($transfer->transfer_fee > 0)
                            ₦{{ number_format($transfer->transfer_fee, 2) }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ strtoupper($transfer->transfer_method) }}</td>
                    <td>
                        <span class="badge {{ $statusClass }}">
                            {{ str_replace('_', ' ', $transfer->status) }}
                        </span>
                    </td>
                    <td style="font-size: 7px;">{{ $transfer->reference ?? '-' }}</td>
                    <td>
                        {{ $transfer->initiator ? ($transfer->initiator->surname ?? '') . ' ' . ($transfer->initiator->firstname ?? '') : '-' }}
                    </td>
                    <td>
                        {{ $transfer->journalEntry?->entry_number ?? '-' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="4"><strong>TOTAL ({{ $transfers->count() }} transfers)</strong></td>
                    <td class="text-right"><strong>₦{{ number_format($summary['total_amount'], 2) }}</strong></td>
                    <td class="text-right"><strong>₦{{ number_format($summary['total_fees'], 2) }}</strong></td>
                    <td colspan="5"></td>
                </tr>
            </tfoot>
        </table>
    @endif

    <!-- Footer Notes -->
    <div style="margin-top: 20px; font-size: 8px; color: #6c757d;">
        <strong>Legend:</strong>
        <span style="margin-left: 10px;"><span class="flow-indicator flow-bank-to-bank"></span> Bank to Bank</span>
        <span style="margin-left: 10px;"><span class="flow-indicator flow-bank-to-cash"></span> Bank to Cash</span>
        <span style="margin-left: 10px;"><span class="flow-indicator flow-cash-to-bank"></span> Cash to Bank</span>
        <span style="margin-left: 10px;"><span class="flow-indicator flow-cash-to-cash"></span> Cash to Cash</span>
    </div>
@endsection
