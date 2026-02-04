@extends('accounting.reports.pdf.layout')

@section('title', 'Lease Portfolio Report')
@section('report_title', 'Lease Portfolio Report')
@section('report_subtitle', 'As of ' . now()->format('F d, Y'))

@section('styles')
<style>
    .summary-grid {
        display: table;
        width: 100%;
        margin-bottom: 15px;
    }
    .summary-cell {
        display: table-cell;
        text-align: center;
        padding: 10px 8px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }
    .summary-cell.primary { background: #007bff; color: white; }
    .summary-cell.success { background: #28a745; color: white; }
    .summary-cell.info { background: #17a2b8; color: white; }
    .summary-cell.warning { background: #ffc107; color: #212529; }
    .summary-cell.danger { background: #dc3545; color: white; }
    .summary-cell.dark { background: #343a40; color: white; }
    .summary-cell.secondary { background: #6c757d; color: white; }
    .summary-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
    .summary-value { font-size: 13px; font-weight: bold; margin-top: 3px; }

    .section-title {
        background: #495057;
        color: white;
        padding: 8px 12px;
        margin: 15px 0 10px 0;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
    }

    .lease-table {
        margin-bottom: 15px;
    }
    .lease-table th {
        background: #f5f5f5;
        font-size: 8px;
        padding: 6px;
        text-transform: uppercase;
    }
    .lease-table td {
        font-size: 9px;
        padding: 5px;
    }
    .lease-table tr:nth-child(even) {
        background: #f9f9f9;
    }

    .type-badge {
        font-size: 7px;
        padding: 2px 5px;
        border-radius: 2px;
        text-transform: uppercase;
    }
    .type-operating { background: #6c757d; color: white; }
    .type-finance { background: #007bff; color: white; }
    .type-short_term { background: #17a2b8; color: white; }
    .type-low_value { background: #e9ecef; color: #212529; }

    .status-badge {
        font-size: 7px;
        padding: 2px 5px;
        border-radius: 2px;
        text-transform: uppercase;
    }
    .status-active { background: #d4edda; color: #155724; }
    .status-draft { background: #e9ecef; color: #6c757d; }
    .status-expired { background: #343a40; color: white; }
    .status-terminated { background: #f8d7da; color: #721c24; }

    .ifrs-note {
        background: #e7f3ff;
        border-left: 3px solid #007bff;
        padding: 10px 12px;
        margin: 15px 0;
        font-size: 9px;
    }
    .ifrs-note strong {
        color: #007bff;
    }

    .grand-total-box {
        background: #212529;
        color: white;
        padding: 10px 15px;
        text-align: right;
        margin-top: 15px;
    }
    .grand-total-box span {
        font-size: 14px;
        font-weight: bold;
    }

    .type-summary-table {
        margin-bottom: 15px;
    }
    .type-summary-table th {
        background: #495057;
        color: white;
        font-size: 8px;
        padding: 6px;
    }
    .type-summary-table td {
        font-size: 9px;
        padding: 5px;
    }
</style>
@endsection

@section('content')
@php
    $stats = $stats ?? [];
    $byType = $stats['by_type'] ?? [];
@endphp

<!-- IFRS 16 Note -->
<div class="ifrs-note">
    <strong>IFRS 16 Compliance:</strong> This report presents lease portfolio values in accordance with IFRS 16.
    Right-of-Use (ROU) Assets and Lease Liabilities are recognized for all leases except short-term and low-value leases
    elected for simplified treatment.
</div>

<!-- Summary Statistics -->
<div class="summary-grid">
    <div class="summary-cell primary">
        <div class="summary-label">Active Leases</div>
        <div class="summary-value">{{ number_format($stats['active_count'] ?? 0) }}</div>
    </div>
    <div class="summary-cell info">
        <div class="summary-label">Total ROU Assets</div>
        <div class="summary-value">₦{{ number_format($stats['total_rou_asset'] ?? 0, 2) }}</div>
    </div>
    <div class="summary-cell warning">
        <div class="summary-label">Total Liability</div>
        <div class="summary-value">₦{{ number_format($stats['total_liability'] ?? 0, 2) }}</div>
    </div>
    <div class="summary-cell secondary">
        <div class="summary-label">Monthly Depreciation</div>
        <div class="summary-value">₦{{ number_format($stats['monthly_depreciation'] ?? 0, 2) }}</div>
    </div>
</div>

<div class="summary-grid">
    <div class="summary-cell success">
        <div class="summary-label">Payments Due This Month</div>
        <div class="summary-value">₦{{ number_format($stats['payments_due_this_month'] ?? 0, 2) }}</div>
    </div>
    <div class="summary-cell danger">
        <div class="summary-label">Overdue Payments</div>
        <div class="summary-value">₦{{ number_format($stats['overdue_payments'] ?? 0, 2) }}</div>
    </div>
    <div class="summary-cell dark">
        <div class="summary-label">Expiring in 90 Days</div>
        <div class="summary-value">{{ number_format($stats['expiring_soon'] ?? 0) }}</div>
    </div>
</div>

<!-- Summary by Lease Type -->
@if(!empty($byType))
<div class="section-title">SUMMARY BY LEASE TYPE</div>
<table class="type-summary-table">
    <thead>
        <tr>
            <th style="width: 25%;">Lease Type</th>
            <th style="width: 15%;" class="text-center">Count</th>
            <th style="width: 30%;" class="text-right">ROU Asset Value</th>
            <th style="width: 30%;" class="text-right">Lease Liability</th>
        </tr>
    </thead>
    <tbody>
        @foreach($byType as $type => $data)
        <tr>
            <td>
                <span class="type-badge type-{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</span>
            </td>
            <td class="text-center">{{ $data['count'] ?? 0 }}</td>
            <td class="text-right">₦{{ number_format($data['rou_asset'] ?? 0, 2) }}</td>
            <td class="text-right">₦{{ number_format($data['liability'] ?? 0, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<!-- Lease Details -->
<div class="section-title">LEASE PORTFOLIO DETAILS</div>
<table class="lease-table">
    <thead>
        <tr>
            <th style="width: 10%;">Lease #</th>
            <th style="width: 8%;">Type</th>
            <th style="width: 8%;">Status</th>
            <th style="width: 18%;">Leased Item</th>
            <th style="width: 12%;">Lessor</th>
            <th style="width: 8%;">Start Date</th>
            <th style="width: 8%;">End Date</th>
            <th style="width: 14%;" class="text-right">ROU Asset</th>
            <th style="width: 14%;" class="text-right">Liability</th>
        </tr>
    </thead>
    <tbody>
        @forelse($leases ?? [] as $lease)
        <tr>
            <td><code>{{ $lease->lease_number }}</code></td>
            <td>
                <span class="type-badge type-{{ $lease->lease_type }}">
                    {{ ucfirst(str_replace('_', ' ', $lease->lease_type)) }}
                </span>
            </td>
            <td>
                <span class="status-badge status-{{ $lease->status }}">
                    {{ ucfirst($lease->status) }}
                </span>
            </td>
            <td>{{ Str::limit($lease->leased_item, 25) }}</td>
            <td>{{ Str::limit($lease->supplier_name ?? $lease->lessor_name ?? '-', 15) }}</td>
            <td>{{ \Carbon\Carbon::parse($lease->commencement_date)->format('M d, Y') }}</td>
            <td>{{ \Carbon\Carbon::parse($lease->end_date)->format('M d, Y') }}</td>
            <td class="text-right">₦{{ number_format($lease->current_rou_asset_value, 2) }}</td>
            <td class="text-right">₦{{ number_format($lease->current_lease_liability, 2) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="9" class="text-center" style="padding: 20px; color: #6c757d;">
                No leases found.
            </td>
        </tr>
        @endforelse
    </tbody>
</table>

<!-- Grand Totals -->
<div class="grand-total-box">
    <div style="display: table; width: 100%;">
        <div style="display: table-cell; text-align: left;">
            Total Active Leases: <strong>{{ $stats['active_count'] ?? 0 }}</strong>
        </div>
        <div style="display: table-cell; text-align: center;">
            ROU Assets: <span>₦{{ number_format($stats['total_rou_asset'] ?? 0, 2) }}</span>
        </div>
        <div style="display: table-cell; text-align: right;">
            Lease Liabilities: <span>₦{{ number_format($stats['total_liability'] ?? 0, 2) }}</span>
        </div>
    </div>
</div>

<!-- IFRS 16 Account Mapping Reference -->
<div class="ifrs-note" style="margin-top: 15px;">
    <strong>Account Mapping Reference:</strong><br>
    • ROU Asset: 1460 - Right-of-Use Asset<br>
    • Lease Liability: 2310 - Lease Liability<br>
    • Depreciation Expense: 6260 - Depreciation Expense<br>
    • Interest Expense: 6300 - Interest Expense
</div>
@endsection
