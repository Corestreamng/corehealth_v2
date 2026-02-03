@extends('accounting.reports.pdf.layout')

@section('title', 'Fixed Assets Register')
@section('report_title', 'Fixed Assets Register')
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
        padding: 8px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }
    .summary-cell.primary { background: #667eea; color: white; }
    .summary-cell.success { background: #28a745; color: white; }
    .summary-cell.warning { background: #ffc107; color: #212529; }
    .summary-cell.info { background: #17a2b8; color: white; }
    .summary-label { font-size: 9px; text-transform: uppercase; }
    .summary-value { font-size: 14px; font-weight: bold; }

    .assets-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
        font-size: 9px;
    }
    .assets-table th {
        background: #495057;
        color: white;
        padding: 6px;
        font-size: 9px;
        font-weight: 600;
        border: 1px solid #dee2e6;
    }
    .assets-table td {
        padding: 5px;
        border: 1px solid #dee2e6;
    }
    .assets-table tr:nth-child(even) {
        background: #f8f9fa;
    }

    .status-badge {
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 8px;
        font-weight: 600;
        display: inline-block;
    }
    .status-active { background: #d4edda; color: #155724; }
    .status-disposed { background: #d6d8db; color: #383d41; }
    .status-voided { background: #f8d7da; color: #721c24; }
    .status-fully-depreciated { background: #d1ecf1; color: #0c5460; }
    .status-impaired { background: #fff3cd; color: #856404; }

    .text-right { text-align: right; }
    .text-center { text-align: center; }
</style>
@endsection

@section('content')
<!-- Summary Cards -->
<div class="summary-grid">
    <div class="summary-cell primary">
        <div class="summary-label">Total Assets</div>
        <div class="summary-value">{{ $stats['total_assets'] }}</div>
    </div>
    <div class="summary-cell success">
        <div class="summary-label">Total Cost</div>
        <div class="summary-value">₦{{ number_format($stats['total_cost'], 0) }}</div>
    </div>
    <div class="summary-cell info">
        <div class="summary-label">Book Value</div>
        <div class="summary-value">₦{{ number_format($stats['total_book_value'], 0) }}</div>
    </div>
    <div class="summary-cell warning">
        <div class="summary-label">Accum. Depreciation</div>
        <div class="summary-value">₦{{ number_format($stats['total_accum_depreciation'], 0) }}</div>
    </div>
</div>

<!-- Assets Table -->
<table class="assets-table">
    <thead>
        <tr>
            <th style="width: 10%;">Asset #</th>
            <th style="width: 20%;">Name</th>
            <th style="width: 12%;">Category</th>
            <th style="width: 12%;">Department</th>
            <th style="width: 10%;">Acquisition Date</th>
            <th class="text-right" style="width: 10%;">Cost</th>
            <th class="text-right" style="width: 10%;">Book Value</th>
            <th class="text-right" style="width: 8%;">Accum. Depr.</th>
            <th class="text-center" style="width: 8%;">Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($assets as $asset)
        <tr>
            <td>{{ $asset->asset_number }}</td>
            <td>{{ $asset->name }}</td>
            <td>{{ $asset->category?->name ?? '-' }}</td>
            <td>{{ $asset->department?->name ?? '-' }}</td>
            <td>{{ $asset->acquisition_date?->format('M d, Y') ?? '-' }}</td>
            <td class="text-right">₦{{ number_format($asset->total_cost, 2) }}</td>
            <td class="text-right">₦{{ number_format($asset->book_value, 2) }}</td>
            <td class="text-right">₦{{ number_format($asset->accumulated_depreciation, 2) }}</td>
            <td class="text-center">
                @php
                    $statusClass = match($asset->status) {
                        'active' => 'status-active',
                        'disposed' => 'status-disposed',
                        'voided' => 'status-voided',
                        'fully_depreciated' => 'status-fully-depreciated',
                        'impaired' => 'status-impaired',
                        default => 'status-active'
                    };
                @endphp
                <span class="status-badge {{ $statusClass }}">
                    {{ ucfirst(str_replace('_', ' ', $asset->status)) }}
                </span>
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr style="background: #e9ecef; font-weight: 600;">
            <td colspan="5" class="text-right">TOTALS:</td>
            <td class="text-right">₦{{ number_format($assets->sum('total_cost'), 2) }}</td>
            <td class="text-right">₦{{ number_format($assets->sum('book_value'), 2) }}</td>
            <td class="text-right">₦{{ number_format($assets->sum('accumulated_depreciation'), 2) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

@if($stats['by_category']->count() > 0)
<!-- Category Breakdown -->
<div style="margin-top: 20px; page-break-inside: avoid;">
    <h4 style="font-size: 12px; margin-bottom: 10px; color: #495057; border-bottom: 2px solid #dee2e6; padding-bottom: 5px;">
        Assets by Category
    </h4>
    <table class="assets-table">
        <thead>
            <tr>
                <th>Category</th>
                <th class="text-center">Count</th>
                <th class="text-right">Total Value</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stats['by_category'] as $item)
            <tr>
                <td>{{ $item->category?->name ?? 'Uncategorized' }}</td>
                <td class="text-center">{{ $item->count }}</td>
                <td class="text-right">₦{{ number_format($item->value, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
