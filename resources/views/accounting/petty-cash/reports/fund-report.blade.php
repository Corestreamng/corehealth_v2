@extends('accounting.reports.pdf.layout')

@section('title', 'Petty Cash Fund Report')
@section('report_title', 'Petty Cash Fund Report')
@section('report_subtitle', $fund->fund_name . ' | ' . $dateFrom . ' to ' . $dateTo)

@section('styles')
<style>
    .fund-info-grid {
        display: table;
        width: 100%;
        margin-bottom: 15px;
    }
    .fund-info-cell {
        display: table-cell;
        width: 25%;
        padding: 10px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        text-align: center;
    }
    .fund-info-cell.primary { background: #007bff; color: white; }
    .fund-info-cell.success { background: #28a745; color: white; }
    .fund-info-cell.danger { background: #dc3545; color: white; }
    .fund-info-cell.warning { background: #ffc107; color: #212529; }
    .fund-info-label { font-size: 9px; text-transform: uppercase; opacity: 0.9; }
    .fund-info-value { font-size: 14px; font-weight: bold; }

    .section-title {
        background: #495057;
        color: white;
        padding: 8px 12px;
        margin: 15px 0 10px 0;
        font-size: 12px;
        font-weight: bold;
    }

    .fund-details {
        background: #f8f9fa;
        padding: 12px;
        margin-bottom: 15px;
        border-radius: 4px;
    }
    .fund-details table {
        width: 100%;
        font-size: 10px;
    }
    .fund-details td {
        padding: 4px 8px;
    }
    .fund-details td:first-child {
        font-weight: bold;
        width: 150px;
        color: #555;
    }

    .transactions-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .transactions-table th {
        background: #495057;
        color: white;
        padding: 8px 6px;
        text-align: left;
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
    }
    .transactions-table td {
        padding: 6px;
        border-bottom: 1px solid #dee2e6;
        font-size: 9px;
    }
    .transactions-table tbody tr:nth-child(even) {
        background-color: #f8f9fa;
    }
    .transactions-table .text-right {
        text-align: right;
    }
    .transactions-table .text-center {
        text-align: center;
    }

    .type-badge {
        display: inline-block;
        padding: 2px 6px;
        font-size: 8px;
        border-radius: 3px;
        text-transform: uppercase;
        font-weight: bold;
    }
    .type-disbursement { background: #dc3545; color: white; }
    .type-replenishment { background: #28a745; color: white; }
    .type-adjustment { background: #ffc107; color: #212529; }

    .status-badge {
        display: inline-block;
        padding: 2px 6px;
        font-size: 8px;
        border-radius: 3px;
        text-transform: uppercase;
    }
    .status-pending { background: #ffc107; color: #212529; }
    .status-approved { background: #17a2b8; color: white; }
    .status-disbursed { background: #28a745; color: white; }
    .status-rejected { background: #dc3545; color: white; }
    .status-voided { background: #6c757d; color: white; }

    .summary-row {
        background: #e9ecef;
        font-weight: bold;
    }
    .summary-row td {
        padding: 10px 6px;
        font-size: 10px;
    }

    .no-transactions {
        text-align: center;
        padding: 30px;
        color: #6c757d;
        font-style: italic;
    }
</style>
@endsection

@section('content')
<div class="container">
    <!-- Fund Summary Cards -->
    <div class="fund-info-grid">
        <div class="fund-info-cell primary">
            <div class="fund-info-label">Fund Limit</div>
            <div class="fund-info-value">₦{{ number_format($fund->fund_limit, 2) }}</div>
        </div>
        <div class="fund-info-cell success">
            <div class="fund-info-label">Current Balance</div>
            <div class="fund-info-value">₦{{ number_format($fund->current_balance, 2) }}</div>
        </div>
        <div class="fund-info-cell danger">
            <div class="fund-info-label">Total Disbursed</div>
            <div class="fund-info-value">₦{{ number_format($transactions->where('transaction_type', 'disbursement')->where('status', 'disbursed')->sum('amount'), 2) }}</div>
        </div>
        <div class="fund-info-cell warning">
            <div class="fund-info-label">Total Replenished</div>
            <div class="fund-info-value">₦{{ number_format($transactions->where('transaction_type', 'replenishment')->where('status', 'disbursed')->sum('amount'), 2) }}</div>
        </div>
    </div>

    <!-- Fund Details -->
    <div class="fund-details">
        <table>
            <tr>
                <td>Fund Code:</td>
                <td>{{ $fund->fund_code }}</td>
                <td>Department:</td>
                <td>{{ $fund->department?->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>GL Account:</td>
                <td>{{ $fund->account?->code ?? 'N/A' }} - {{ $fund->account?->name ?? '' }}</td>
                <td>Custodian:</td>
                <td>{{ $fund->custodian?->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Transaction Limit:</td>
                <td>₦{{ number_format($fund->transaction_limit, 2) }}</td>
                <td>Status:</td>
                <td>{{ ucfirst($fund->status) }}</td>
            </tr>
        </table>
    </div>

    <!-- Transactions Section -->
    <div class="section-title">
        <i class="mdi mdi-history"></i> Transaction History
    </div>

    @if($transactions->count() > 0)
        <table class="transactions-table">
            <thead>
                <tr>
                    <th style="width: 60px;">Date</th>
                    <th style="width: 100px;">Voucher #</th>
                    <th style="width: 70px;">Type</th>
                    <th>Description</th>
                    <th style="width: 80px;" class="text-right">Amount</th>
                    <th style="width: 60px;" class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalDisbursements = 0;
                    $totalReplenishments = 0;
                @endphp
                @foreach($transactions as $transaction)
                    @php
                        if ($transaction->transaction_type === 'disbursement' && $transaction->status === 'disbursed') {
                            $totalDisbursements += $transaction->amount;
                        }
                        if ($transaction->transaction_type === 'replenishment' && $transaction->status === 'disbursed') {
                            $totalReplenishments += $transaction->amount;
                        }
                    @endphp
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('M d, Y') }}</td>
                        <td>{{ $transaction->voucher_number ?? '-' }}</td>
                        <td>
                            <span class="type-badge type-{{ $transaction->transaction_type }}">
                                {{ ucfirst($transaction->transaction_type) }}
                            </span>
                        </td>
                        <td>
                            {{ Str::limit($transaction->description, 45) }}
                            <br><span style="font-size: 7px; color: #666;">
                                By: {{ $transaction->requestedBy?->name ?? '-' }}
                                @if($transaction->approvedBy)
                                    | Approved: {{ $transaction->approvedBy->name }}
                                @endif
                            </span>
                        </td>
                        <td class="text-right">
                            @if($transaction->transaction_type === 'disbursement')
                                <span style="color: #dc3545;">-₦{{ number_format($transaction->amount, 2) }}</span>
                            @else
                                <span style="color: #28a745;">+₦{{ number_format($transaction->amount, 2) }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="status-badge status-{{ $transaction->status }}">
                                {{ ucfirst($transaction->status) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="summary-row">
                    <td colspan="3" class="text-right">Total Disbursements:</td>
                    <td></td>
                    <td class="text-right" style="color: #dc3545;">-₦{{ number_format($totalDisbursements, 2) }}</td>
                    <td></td>
                </tr>
                <tr class="summary-row">
                    <td colspan="3" class="text-right">Total Replenishments:</td>
                    <td></td>
                    <td class="text-right" style="color: #28a745;">+₦{{ number_format($totalReplenishments, 2) }}</td>
                    <td></td>
                </tr>
                <tr class="summary-row">
                    <td colspan="3" class="text-right">Net Movement:</td>
                    <td></td>
                    <td class="text-right" style="font-weight: bold;">₦{{ number_format($totalReplenishments - $totalDisbursements, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @else
        <div class="no-transactions">
            No transactions found for the selected period.
        </div>
    @endif

    <!-- Report Footer -->
    <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #dee2e6;">
        <table style="width: 100%; font-size: 9px;">
            <tr>
                <td style="width: 50%;">
                    <strong>Report Generated:</strong> {{ now()->format('F d, Y h:i A') }}
                </td>
                <td style="width: 50%; text-align: right;">
                    <strong>Generated By:</strong> {{ auth()->user()->name ?? 'System' }}
                </td>
            </tr>
        </table>
    </div>
</div>
@endsection
