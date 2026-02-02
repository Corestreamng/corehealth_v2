@extends('accounting.reports.pdf.layout')

@section('title', 'Lease Management Report')
@section('report_title', 'Lease Management Report (IFRS 16)')
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
    .status-expired { background: #fff3cd; color: #856404; }
    .status-terminated { background: #f8d7da; color: #721c24; }
    .status-default { background: #e2e3e5; color: #383d41; }
</style>
@endsection

@section('content')
    @php
        $activeLeases = $leases->where('status', 'active');
    @endphp

    <div class="summary-grid">
        <div class="summary-cell primary">
            <div class="summary-label">Total Leases</div>
            <div class="summary-value">{{ $leases->count() }}</div>
        </div>
        <div class="summary-cell success">
            <div class="summary-label">Active</div>
            <div class="summary-value">{{ $activeLeases->count() }}</div>
        </div>
        <div class="summary-cell info">
            <div class="summary-label">Monthly Payment</div>
            <div class="summary-value">₦{{ number_format($activeLeases->sum('monthly_payment'), 0) }}</div>
        </div>
        <div class="summary-cell warning">
            <div class="summary-label">ROU Asset Value</div>
            <div class="summary-value">₦{{ number_format($activeLeases->sum('current_rou_asset_value'), 0) }}</div>
        </div>
        <div class="summary-cell danger">
            <div class="summary-label">Lease Liability</div>
            <div class="summary-value">₦{{ number_format($activeLeases->sum('current_lease_liability'), 0) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Lease #</th>
                <th>Leased Item</th>
                <th>Type</th>
                <th>Lessor</th>
                <th class="text-right">Monthly</th>
                <th class="text-right">ROU Asset</th>
                <th class="text-right">Liability</th>
                <th>End Date</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($leases as $lease)
            <tr>
                <td>{{ $lease->lease_number }}</td>
                <td>{{ $lease->leased_item }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $lease->lease_type)) }}</td>
                <td>{{ $lease->lessor_name ?: '-' }}</td>
                <td class="text-right">₦{{ number_format($lease->monthly_payment, 2) }}</td>
                <td class="text-right">₦{{ number_format($lease->current_rou_asset_value, 2) }}</td>
                <td class="text-right">₦{{ number_format($lease->current_lease_liability, 2) }}</td>
                <td>{{ \Carbon\Carbon::parse($lease->end_date)->format('M d, Y') }}</td>
                <td class="text-center">
                    @php
                        $statusClass = match($lease->status) {
                            'active' => 'status-active',
                            'expired' => 'status-expired',
                            'terminated' => 'status-terminated',
                            default => 'status-default'
                        };
                    @endphp
                    <span class="status-badge {{ $statusClass }}">{{ ucfirst($lease->status) }}</span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center">No leases found</td>
            </tr>
            @endforelse
        </tbody>
        @if($leases->count() > 0)
        <tfoot>
            <tr class="total-row">
                <th colspan="4">Total ({{ $activeLeases->count() }} active)</th>
                <th class="text-right">₦{{ number_format($activeLeases->sum('monthly_payment'), 2) }}</th>
                <th class="text-right">₦{{ number_format($activeLeases->sum('current_rou_asset_value'), 2) }}</th>
                <th class="text-right">₦{{ number_format($activeLeases->sum('current_lease_liability'), 2) }}</th>
                <th colspan="2"></th>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
