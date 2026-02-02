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
        padding: 10px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }
    .summary-cell.primary { background: #007bff; color: white; }
    .summary-cell.success { background: #28a745; color: white; }
    .summary-cell.info { background: #17a2b8; color: white; }
    .summary-cell.warning { background: #ffc107; color: #212529; }
    .summary-cell.danger { background: #dc3545; color: white; }
    .summary-label { font-size: 9px; text-transform: uppercase; }
    .summary-value { font-size: 14px; font-weight: bold; }

    .status-badge {
        font-size: 8px;
        padding: 2px 6px;
        border-radius: 3px;
        font-weight: bold;
    }
    .status-active { background: #d4edda; color: #155724; }
    .status-disposed { background: #f8d7da; color: #721c24; }
    .status-maintenance { background: #fff3cd; color: #856404; }
    .status-default { background: #e2e3e5; color: #383d41; }
</style>
@endsection

@section('content')
    @if(isset($stats))
    <div class="summary-grid">
        <div class="summary-cell primary">
            <div class="summary-label">Total Assets</div>
            <div class="summary-value">{{ $stats['total'] ?? 0 }}</div>
        </div>
        <div class="summary-cell success">
            <div class="summary-label">Active</div>
            <div class="summary-value">{{ $stats['active'] ?? 0 }}</div>
        </div>
        <div class="summary-cell info">
            <div class="summary-label">Total Cost</div>
            <div class="summary-value">₦{{ number_format($stats['total_cost'] ?? 0, 0) }}</div>
        </div>
        <div class="summary-cell warning">
            <div class="summary-label">Accum. Depreciation</div>
            <div class="summary-value">₦{{ number_format($stats['total_depreciation'] ?? 0, 0) }}</div>
        </div>
        <div class="summary-cell danger">
            <div class="summary-label">Net Book Value</div>
            <div class="summary-value">₦{{ number_format($stats['total_nbv'] ?? 0, 0) }}</div>
        </div>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Asset #</th>
                <th>Name</th>
                <th>Category</th>
                <th>Department</th>
                <th>Acquired</th>
                <th class="text-right">Cost</th>
                <th class="text-right">Accum. Depr.</th>
                <th class="text-right">NBV</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($assets as $asset)
            <tr>
                <td>{{ $asset->asset_number }}</td>
                <td>{{ $asset->name }}</td>
                <td>{{ $asset->category->name ?? '-' }}</td>
                <td>{{ $asset->department->name ?? '-' }}</td>
                <td>{{ $asset->acquisition_date ? \Carbon\Carbon::parse($asset->acquisition_date)->format('M d, Y') : '-' }}</td>
                <td class="text-right">₦{{ number_format($asset->total_cost, 2) }}</td>
                <td class="text-right">₦{{ number_format($asset->accumulated_depreciation, 2) }}</td>
                <td class="text-right">₦{{ number_format($asset->book_value, 2) }}</td>
                <td class="text-center">
                    @php
                        $statusClass = match($asset->status) {
                            'active' => 'status-active',
                            'disposed' => 'status-disposed',
                            'under_maintenance' => 'status-maintenance',
                            default => 'status-default'
                        };
                    @endphp
                    <span class="status-badge {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $asset->status)) }}</span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center">No fixed assets found</td>
            </tr>
            @endforelse
        </tbody>
        @if($assets->count() > 0)
        <tfoot>
            <tr class="total-row">
                <th colspan="5">Total ({{ $assets->where('status', 'active')->count() }} active)</th>
                <th class="text-right">₦{{ number_format($assets->sum('total_cost'), 2) }}</th>
                <th class="text-right">₦{{ number_format($assets->sum('accumulated_depreciation'), 2) }}</th>
                <th class="text-right">₦{{ number_format($assets->sum('book_value'), 2) }}</th>
                <th></th>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
