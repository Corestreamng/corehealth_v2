{{--
    Cost Center Report
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.11
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Cost Center Report: ' . $costCenter->code)
@section('page_name', 'Accounting')
@section('subpage_name', 'Cost Center Report')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Cost Centers', 'url' => route('accounting.cost-centers.index'), 'icon' => 'mdi-sitemap'],
        ['label' => $costCenter->code, 'url' => route('accounting.cost-centers.show', $costCenter->id), 'icon' => 'mdi-eye'],
        ['label' => 'Report', 'url' => '#', 'icon' => 'mdi-chart-bar']
    ]
])

<style>
.report-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
}
.report-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.report-card h6 {
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f1f1;
}
.stat-box {
    text-align: center;
    padding: 20px;
    border-radius: 8px;
    background: #f8f9fa;
}
.stat-box .amount { font-size: 1.5rem; font-weight: 700; }
.stat-box .label { color: #666; font-size: 0.85rem; }
.filter-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.account-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 5px;
    background: #f8f9fa;
}
.account-row:hover { background: #e9ecef; }
.variance-positive { color: #28a745; }
.variance-negative { color: #dc3545; }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="report-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3 class="mb-1">{{ $costCenter->name }}</h3>
                <div>
                    <span class="badge badge-light mr-2">{{ $costCenter->code }}</span>
                    <span class="badge badge-{{ $costCenter->center_type == 'revenue' ? 'success' : 'info' }}">
                        {{ ucfirst($costCenter->center_type) }} Center
                    </span>
                </div>
            </div>
            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                <a href="{{ route('accounting.cost-centers.report.pdf', array_merge(['costCenter' => $costCenter->id], request()->only(['from_date', 'to_date']))) }}"
                   class="btn btn-light btn-sm" target="_blank">
                    <i class="mdi mdi-file-pdf"></i> PDF
                </a>
                <a href="{{ route('accounting.cost-centers.report.excel', array_merge(['costCenter' => $costCenter->id], request()->only(['from_date', 'to_date']))) }}"
                   class="btn btn-light btn-sm">
                    <i class="mdi mdi-file-excel"></i> Excel
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <form action="{{ route('accounting.cost-centers.report', $costCenter->id) }}" method="GET" class="row align-items-end">
            <div class="col-md-3">
                <label>Start Date</label>
                <input type="date" name="start_date" class="form-control"
                       value="{{ request('start_date', $startDate->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3">
                <label>End Date</label>
                <input type="date" name="end_date" class="form-control"
                       value="{{ request('end_date', $endDate->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3">
                <label>Account Type</label>
                <select name="account_type" class="form-control">
                    <option value="">All Types</option>
                    <option value="expense" {{ request('account_type') == 'expense' ? 'selected' : '' }}>Expense</option>
                    <option value="revenue" {{ request('account_type') == 'revenue' ? 'selected' : '' }}>Revenue</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="mdi mdi-filter"></i> Apply Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Stats -->
    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);">
                <div class="amount text-success">₦{{ number_format($summary['total_revenue'], 2) }}</div>
                <div class="label">Total Revenue</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);">
                <div class="amount text-danger">₦{{ number_format($summary['total_expenses'], 2) }}</div>
                <div class="label">Total Expenses</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            @php
                $netAmount = $summary['total_revenue'] - $summary['total_expenses'];
            @endphp
            <div class="stat-box" style="background: linear-gradient(135deg, {{ $netAmount >= 0 ? '#d4edda' : '#f8d7da' }} 0%, {{ $netAmount >= 0 ? '#c3e6cb' : '#f5c6cb' }} 100%);">
                <div class="amount {{ $netAmount >= 0 ? 'text-success' : 'text-danger' }}">
                    ₦{{ number_format(abs($netAmount), 2) }}
                </div>
                <div class="label">{{ $netAmount >= 0 ? 'Net Surplus' : 'Net Deficit' }}</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
                <div class="amount text-info">{{ $summary['transaction_count'] }}</div>
                <div class="label">Transactions</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Expenses by Account -->
            <div class="report-card">
                <h6><i class="mdi mdi-format-list-bulleted mr-2"></i>Breakdown by Account</h6>
                @if(count($expensesByAccount) > 0)
                    @foreach($expensesByAccount as $item)
                        <div class="account-row">
                            <div>
                                <strong>{{ $item->account_code }}</strong> - {{ $item->account_name }}
                            </div>
                            <div class="text-right">
                                @if($item->total_debit > 0)
                                    <span class="text-danger mr-2">
                                        Dr: ₦{{ number_format($item->total_debit, 2) }}
                                    </span>
                                @endif
                                @if($item->total_credit > 0)
                                    <span class="text-success">
                                        Cr: ₦{{ number_format($item->total_credit, 2) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-4">
                        <i class="mdi mdi-file-document-outline text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No transactions for selected period</p>
                    </div>
                @endif
            </div>

            <!-- Monthly Trend -->
            <div class="report-card">
                <h6><i class="mdi mdi-chart-line mr-2"></i>Monthly Trend</h6>
                <canvas id="monthlyTrendChart" height="200"></canvas>
            </div>

            <!-- Transaction Details -->
            <div class="report-card">
                <h6><i class="mdi mdi-format-list-numbered mr-2"></i>Transaction Details</h6>
                <div class="table-responsive">
                    <table class="table table-hover table-sm" id="transactionsTable">
                        <thead class="thead-light">
                            <tr>
                                <th>Date</th>
                                <th>JE #</th>
                                <th>Account</th>
                                <th>Description</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $txn)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($txn->journalEntry->entry_date)->format('M d, Y') }}</td>
                                    <td>
                                        <a href="{{ route('accounting.journal-entries.show', $txn->journal_entry_id) }}">
                                            {{ $txn->journalEntry->entry_number }}
                                        </a>
                                    </td>
                                    <td>{{ $txn->account->code ?? 'N/A' }}</td>
                                    <td>{{ Str::limit($txn->description ?? $txn->journalEntry->description, 40) }}</td>
                                    <td class="text-right">{{ $txn->debit > 0 ? '₦' . number_format($txn->debit, 2) : '-' }}</td>
                                    <td class="text-right">{{ $txn->credit > 0 ? '₦' . number_format($txn->credit, 2) : '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4">No transactions found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Budget Comparison -->
            @if($budget)
            <div class="report-card">
                <h6><i class="mdi mdi-scale-balance mr-2"></i>Budget vs Actual</h6>
                @php
                    $budgetUsed = $summary['total_expenses'];
                    $budgetAmount = $budget->budgeted_amount;
                    $variance = $budgetAmount - $budgetUsed;
                    $utilization = $budgetAmount > 0 ? ($budgetUsed / $budgetAmount) * 100 : 0;
                    $progressColor = $utilization > 90 ? 'danger' : ($utilization > 75 ? 'warning' : 'success');
                @endphp
                <div class="text-center mb-3">
                    <h4 class="mb-0">{{ number_format($utilization, 1) }}%</h4>
                    <small class="text-muted">Budget Utilized</small>
                </div>
                <div class="progress mb-3" style="height: 20px;">
                    <div class="progress-bar bg-{{ $progressColor }}" style="width: {{ min($utilization, 100) }}%"></div>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Budget:</span>
                    <strong>₦{{ number_format($budgetAmount, 2) }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Actual:</span>
                    <strong>₦{{ number_format($budgetUsed, 2) }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Variance:</span>
                    <strong class="{{ $variance >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $variance >= 0 ? '+' : '' }}₦{{ number_format($variance, 2) }}
                    </strong>
                </div>
            </div>
            @endif

            <!-- Period Info -->
            <div class="report-card">
                <h6><i class="mdi mdi-calendar mr-2"></i>Report Period</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span>From:</span>
                    <strong>{{ $startDate->format('M d, Y') }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>To:</span>
                    <strong>{{ $endDate->format('M d, Y') }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Days:</span>
                    <strong>{{ $startDate->diffInDays($endDate) + 1 }}</strong>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="report-card">
                <h6><i class="mdi mdi-link mr-2"></i>Quick Actions</h6>
                <a href="{{ route('accounting.cost-centers.show', $costCenter->id) }}" class="btn btn-outline-primary btn-block btn-sm mb-2">
                    <i class="mdi mdi-eye mr-1"></i> View Details
                </a>
                <a href="{{ route('accounting.cost-centers.budgets', $costCenter->id) }}" class="btn btn-outline-success btn-block btn-sm mb-2">
                    <i class="mdi mdi-cash mr-1"></i> Manage Budget
                </a>
                <a href="{{ route('accounting.cost-centers.index') }}" class="btn btn-outline-secondary btn-block btn-sm">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Monthly Trend Chart
    var monthlyData = @json($monthlyTrend);
    var labels = monthlyData.map(item => item.month);
    var revenues = monthlyData.map(item => parseFloat(item.revenue) || 0);
    var expenses = monthlyData.map(item => parseFloat(item.expenses) || 0);

    new Chart(document.getElementById('monthlyTrendChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Revenue',
                    data: revenues,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Expenses',
                    data: expenses,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₦' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
});
</script>
@endpush
