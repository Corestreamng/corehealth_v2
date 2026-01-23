@extends('admin.layouts.app')
@section('title', 'Expense Summary Report')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Expense Reports')

@section('content')
<style>
    .report-card {
        background: #fff;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1.5rem;
    }
    .report-card h5 {
        border-bottom: 2px solid #007bff;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
    }
    .stat-box {
        text-align: center;
        padding: 1.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    .stat-box h6 {
        font-size: 0.85rem;
        opacity: 0.9;
        margin-bottom: 0.5rem;
    }
    .stat-box .value {
        font-size: 1.75rem;
        font-weight: 700;
    }
    .category-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        border-bottom: 1px solid #e9ecef;
    }
    .category-item:last-child {
        border-bottom: none;
    }
    .category-item .name {
        font-weight: 600;
    }
    .category-item .amount {
        font-size: 1.1rem;
        color: #28a745;
    }
    .chart-container {
        position: relative;
        height: 300px;
    }
</style>

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-1">Expense Summary Report</h3>
                <p class="text-muted mb-0">Period: {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</p>
            </div>
            <div>
                <a href="{{ route('inventory.expenses.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="mdi mdi-arrow-left"></i> Back to Expenses
                </a>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                    <i class="mdi mdi-printer"></i> Print Report
                </button>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="report-card no-print">
            <form method="GET" action="{{ route('inventory.expenses.summary-report') }}" class="row">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block w-100">
                        <i class="mdi mdi-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Stats -->
        <div class="row">
            <div class="col-md-4">
                <div class="stat-box">
                    <h6>Total Expenses</h6>
                    <div class="value">₦{{ number_format($stats['total_expenses'], 2) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <h6>Total Transactions</h6>
                    <div class="value">{{ number_format($stats['total_count']) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <h6>Average Expense</h6>
                    <div class="value">₦{{ number_format($stats['average_expense'], 2) }}</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Expenses by Category -->
            <div class="col-md-6">
                <div class="report-card">
                    <h5><i class="mdi mdi-tag-multiple"></i> Expenses by Category</h5>
                    @if($stats['by_category']->count() > 0)
                        @foreach($stats['by_category'] as $category => $data)
                        <div class="category-item">
                            <div>
                                <div class="name">{{ ucfirst(str_replace('_', ' ', $category)) }}</div>
                                <small class="text-muted">{{ $data['count'] }} transaction(s)</small>
                            </div>
                            <div class="amount">₦{{ number_format($data['total'], 2) }}</div>
                        </div>
                        @endforeach
                    @else
                        <p class="text-muted text-center py-3">No approved expenses in this period</p>
                    @endif
                </div>
            </div>

            <!-- Expenses by Month -->
            <div class="col-md-6">
                <div class="report-card">
                    <h5><i class="mdi mdi-calendar-month"></i> Monthly Breakdown</h5>
                    @if($stats['by_month']->count() > 0)
                        @foreach($stats['by_month'] as $month => $total)
                        <div class="category-item">
                            <div class="name">{{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</div>
                            <div class="amount">₦{{ number_format($total, 2) }}</div>
                        </div>
                        @endforeach
                    @else
                        <p class="text-muted text-center py-3">No data available</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Category Chart -->
        <div class="row">
            <div class="col-md-12">
                <div class="report-card">
                    <h5><i class="mdi mdi-chart-pie"></i> Category Distribution</h5>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Category Chart
const categoryData = @json($stats['by_category']);
const categoryLabels = Object.keys(categoryData).map(key => key.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()));
const categoryAmounts = Object.values(categoryData).map(item => item.total);

const ctx = document.getElementById('categoryChart').getContext('2d');
new Chart(ctx, {
    type: 'pie',
    data: {
        labels: categoryLabels,
        datasets: [{
            data: categoryAmounts,
            backgroundColor: [
                '#667eea',
                '#764ba2',
                '#f093fb',
                '#f5576c',
                '#4facfe',
                '#00f2fe',
                '#43e97b',
                '#38f9d7'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        return label + ': ₦' + value.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                }
            }
        }
    }
});
</script>

<style>
@media print {
    .no-print,
    #left-sidebar,
    .navbar,
    button,
    .btn {
        display: none !important;
    }
    body {
        background: white;
    }
    .report-card {
        box-shadow: none;
        border: 1px solid #ddd;
        page-break-inside: avoid;
    }
}
</style>
@endsection
