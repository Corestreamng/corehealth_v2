@extends('accounting.reports.pdf.layout')

@section('title', 'CAPEX Report')
@section('report_title', 'Capital Expenditure (CAPEX) Report')
@section('report_subtitle', 'Fiscal Year: ' . ($fiscalYear ?? date('Y')))

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
    .status-approved { background: #d4edda; color: #155724; }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-rejected { background: #f8d7da; color: #721c24; }
    .status-completed { background: #d1ecf1; color: #0c5460; }
    .status-default { background: #e2e3e5; color: #383d41; }

    .priority-badge {
        font-size: 8px;
        padding: 2px 6px;
        border-radius: 3px;
        font-weight: bold;
    }
    .priority-high { background: #f8d7da; color: #721c24; }
    .priority-medium { background: #fff3cd; color: #856404; }
    .priority-low { background: #d1ecf1; color: #0c5460; }
</style>
@endsection

@section('content')
    @if(isset($stats))
    <div class="summary-grid">
        <div class="summary-cell primary">
            <div class="summary-label">Total Requests</div>
            <div class="summary-value">{{ $stats['total'] ?? 0 }}</div>
        </div>
        <div class="summary-cell success">
            <div class="summary-label">Approved</div>
            <div class="summary-value">{{ $stats['approved'] ?? 0 }}</div>
        </div>
        <div class="summary-cell warning">
            <div class="summary-label">Pending</div>
            <div class="summary-value">{{ $stats['pending'] ?? 0 }}</div>
        </div>
        <div class="summary-cell info">
            <div class="summary-label">Total Requested</div>
            <div class="summary-value">₦{{ number_format($stats['total_requested'] ?? 0, 0) }}</div>
        </div>
        <div class="summary-cell success">
            <div class="summary-label">Total Approved</div>
            <div class="summary-value">₦{{ number_format($stats['total_approved'] ?? 0, 0) }}</div>
        </div>
        <div class="summary-cell danger">
            <div class="summary-label">Actual Spent</div>
            <div class="summary-value">₦{{ number_format($stats['total_actual'] ?? 0, 0) }}</div>
        </div>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Reference</th>
                <th>Title</th>
                <th>Category</th>
                <th>Cost Center</th>
                <th class="text-right">Requested</th>
                <th class="text-right">Approved</th>
                <th class="text-right">Actual</th>
                <th class="text-center">Priority</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($capexList as $capex)
            <tr>
                <td>{{ $capex->reference_number }}</td>
                <td>{{ $capex->title }}</td>
                <td>{{ ucfirst($capex->category) }}</td>
                <td>{{ $capex->cost_center ?? '-' }}</td>
                <td class="text-right">₦{{ number_format($capex->requested_amount, 2) }}</td>
                <td class="text-right">{{ $capex->approved_amount ? '₦'.number_format($capex->approved_amount, 2) : '-' }}</td>
                <td class="text-right">{{ $capex->actual_amount ? '₦'.number_format($capex->actual_amount, 2) : '-' }}</td>
                <td class="text-center">
                    @php
                        $priorityClass = match($capex->priority) {
                            'high' => 'priority-high',
                            'medium' => 'priority-medium',
                            'low' => 'priority-low',
                            default => 'status-default'
                        };
                    @endphp
                    <span class="priority-badge {{ $priorityClass }}">{{ ucfirst($capex->priority) }}</span>
                </td>
                <td class="text-center">
                    @php
                        $statusClass = match($capex->status) {
                            'approved' => 'status-approved',
                            'pending' => 'status-pending',
                            'rejected' => 'status-rejected',
                            'completed' => 'status-completed',
                            default => 'status-default'
                        };
                    @endphp
                    <span class="status-badge {{ $statusClass }}">{{ ucfirst($capex->status) }}</span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center">No CAPEX requests found</td>
            </tr>
            @endforelse
        </tbody>
        @if($capexList->count() > 0)
        <tfoot>
            <tr class="total-row">
                <th colspan="4">Totals</th>
                <th class="text-right">₦{{ number_format($capexList->sum('requested_amount'), 2) }}</th>
                <th class="text-right">₦{{ number_format($capexList->sum('approved_amount'), 2) }}</th>
                <th class="text-right">₦{{ number_format($capexList->sum('actual_amount'), 2) }}</th>
                <th colspan="2"></th>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
