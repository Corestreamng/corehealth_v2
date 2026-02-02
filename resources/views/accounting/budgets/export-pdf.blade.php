@extends('accounting.reports.pdf.layout')

@section('title', 'Budget Report')
@section('report_title', 'Budget Report')
@section('report_subtitle', 'Fiscal Year: ' . ($fiscalYear ?? date('Y')))

@section('styles')
<style>
    .budget-card {
        border: 1px solid #dee2e6;
        margin-bottom: 20px;
        page-break-inside: avoid;
    }
    .budget-header {
        background-color: #f5f5f5;
        padding: 10px 15px;
        border-bottom: 1px solid #dee2e6;
    }
    .budget-header h3 {
        margin: 0;
        font-size: 13px;
        font-weight: bold;
    }
    .budget-header .meta {
        font-size: 10px;
        color: #666;
        margin-top: 5px;
    }
    .budget-body {
        padding: 15px;
    }
    .budget-summary {
        display: table;
        width: 100%;
        margin-bottom: 15px;
    }
    .summary-col {
        display: table-cell;
        width: 25%;
        text-align: center;
        padding: 10px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }
    .summary-col .value {
        font-size: 13px;
        font-weight: bold;
        color: #007bff;
    }
    .summary-col .label {
        font-size: 9px;
        color: #666;
        text-transform: uppercase;
    }

    .status-badge {
        font-size: 8px;
        padding: 2px 6px;
        border-radius: 3px;
        font-weight: bold;
    }
    .status-approved { background: #d4edda; color: #155724; }
    .status-draft { background: #e2e3e5; color: #383d41; }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-rejected { background: #f8d7da; color: #721c24; }

    .positive { color: #28a745; }
    .negative { color: #dc3545; }

    .detail-table {
        margin-bottom: 0;
    }
    .detail-table th {
        background: #f5f5f5;
        font-size: 9px;
        padding: 6px;
    }
    .detail-table td {
        font-size: 9px;
        padding: 5px;
    }
</style>
@endsection

@section('content')
    @forelse($budgets as $budget)
    <div class="budget-card">
        <div class="budget-header">
            <h3>{{ $budget->name }}</h3>
            <div class="meta">
                Department: {{ $budget->department->name ?? 'Organization-wide' }} |
                Fiscal Year: {{ $budget->fiscalYear->year ?? 'N/A' }} |
                @php
                    $statusClass = match($budget->status) {
                        'approved' => 'status-approved',
                        'draft' => 'status-draft',
                        'pending' => 'status-pending',
                        'rejected' => 'status-rejected',
                        default => 'status-draft'
                    };
                @endphp
                <span class="status-badge {{ $statusClass }}">{{ ucfirst($budget->status) }}</span>
            </div>
        </div>
        <div class="budget-body">
            <div class="budget-summary">
                <div class="summary-col">
                    <div class="value">₦{{ number_format($budget->total_amount, 2) }}</div>
                    <div class="label">Total Budget</div>
                </div>
                <div class="summary-col">
                    <div class="value">₦{{ number_format($budget->actual_amount ?? 0, 2) }}</div>
                    <div class="label">Actual Spent</div>
                </div>
                <div class="summary-col">
                    @php
                        $variance = ($budget->total_amount ?? 0) - ($budget->actual_amount ?? 0);
                        $varianceClass = $variance >= 0 ? 'positive' : 'negative';
                    @endphp
                    <div class="value {{ $varianceClass }}">₦{{ number_format(abs($variance), 2) }}</div>
                    <div class="label">{{ $variance >= 0 ? 'Under Budget' : 'Over Budget' }}</div>
                </div>
                <div class="summary-col">
                    @php
                        $utilization = $budget->total_amount > 0 ? (($budget->actual_amount ?? 0) / $budget->total_amount) * 100 : 0;
                    @endphp
                    <div class="value">{{ number_format($utilization, 1) }}%</div>
                    <div class="label">Utilization</div>
                </div>
            </div>

            @if($budget->items && $budget->items->count() > 0)
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>Account Code</th>
                        <th>Account Name</th>
                        <th class="text-right">Budgeted</th>
                        <th class="text-right">Actual</th>
                        <th class="text-right">Variance</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($budget->items as $item)
                    <tr>
                        <td>{{ $item->account->code ?? '-' }}</td>
                        <td>{{ $item->account->name ?? '-' }}</td>
                        <td class="text-right">₦{{ number_format($item->budgeted_amount, 2) }}</td>
                        <td class="text-right">₦{{ number_format($item->actual_amount ?? 0, 2) }}</td>
                        <td class="text-right">
                            @php
                                $itemVariance = $item->budgeted_amount - ($item->actual_amount ?? 0);
                            @endphp
                            <span class="{{ $itemVariance >= 0 ? 'positive' : 'negative' }}">
                                ₦{{ number_format(abs($itemVariance), 2) }}
                            </span>
                        </td>
                        <td>{{ $item->notes ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p class="text-center text-muted">No line items found</p>
            @endif
        </div>
    </div>
    @empty
    <p class="text-center">No budgets found for the selected criteria</p>
    @endforelse
@endsection
