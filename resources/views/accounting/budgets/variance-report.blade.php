{{--
    Budget Variance Report
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.10
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Budget Variance Report')
@section('page_name', 'Accounting')
@section('subpage_name', 'Variance Report')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Budgets', 'url' => route('accounting.budgets.index'), 'icon' => 'mdi-calculator'],
        ['label' => 'Variance Report', 'url' => '#', 'icon' => 'mdi-chart-bar']
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
.filter-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
.budget-section {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 15px;
}
.budget-section-header {
    background: #f8f9fa;
    padding: 12px 15px;
    border-radius: 8px 8px 0 0;
    border-bottom: 1px solid #e9ecef;
}
.budget-section-body { padding: 15px; }
.variance-positive { color: #28a745; }
.variance-negative { color: #dc3545; }
.item-row {
    padding: 8px 0;
    border-bottom: 1px solid #f1f1f1;
}
.item-row:last-child { border-bottom: none; }
.stat-box {
    text-align: center;
    padding: 20px;
    border-radius: 8px;
    background: #f8f9fa;
}
.stat-box .amount { font-size: 1.5rem; font-weight: 700; }
.stat-box .label { color: #666; font-size: 0.85rem; }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="report-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3 class="mb-1">Budget Variance Report</h3>
                <p class="mb-0 opacity-75">Compare budgeted vs actual spending across departments</p>
            </div>
            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                <button class="btn btn-light btn-sm" onclick="window.print()">
                    <i class="mdi mdi-printer"></i> Print
                </button>
                <button class="btn btn-light btn-sm" id="btnExport">
                    <i class="mdi mdi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <form action="{{ route('accounting.budgets.variance-report') }}" method="GET" class="row align-items-end">
            <div class="col-md-4">
                <label>Fiscal Year</label>
                <select name="fiscal_year_id" class="form-control">
                    <option value="">All Years</option>
                    @foreach($fiscalYears as $fy)
                        <option value="{{ $fy->id }}" {{ $fiscalYearId == $fy->id ? 'selected' : '' }}>
                            {{ $fy->year }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label>Department</label>
                <select name="department_id" class="form-control">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ $departmentId == $dept->id ? 'selected' : '' }}>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="mdi mdi-filter"></i> Apply Filter
                </button>
            </div>
        </form>
    </div>

    @if(count($reportData) > 0)
        <!-- Summary Stats -->
        @php
            $grandTotalBudget = $reportData->sum('total_budgeted');
            $grandTotalActual = $reportData->sum('total_actual');
            $grandTotalVariance = $grandTotalBudget - $grandTotalActual;
            $overallUtilization = $grandTotalBudget > 0 ? ($grandTotalActual / $grandTotalBudget) * 100 : 0;
        @endphp
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="stat-box" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
                    <div class="amount text-info">₦{{ number_format($grandTotalBudget, 2) }}</div>
                    <div class="label">Total Budgeted</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box" style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);">
                    <div class="amount text-danger">₦{{ number_format($grandTotalActual, 2) }}</div>
                    <div class="label">Total Actual</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box" style="background: linear-gradient(135deg, {{ $grandTotalVariance >= 0 ? '#d4edda' : '#f8d7da' }} 0%, {{ $grandTotalVariance >= 0 ? '#c3e6cb' : '#f5c6cb' }} 100%);">
                    <div class="amount {{ $grandTotalVariance >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $grandTotalVariance >= 0 ? '+' : '' }}₦{{ number_format($grandTotalVariance, 2) }}
                    </div>
                    <div class="label">Total Variance</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                @php
                    $utilizationColor = $overallUtilization > 90 ? '#dc3545' : ($overallUtilization > 75 ? '#ffc107' : '#28a745');
                @endphp
                <div class="stat-box">
                    <div class="amount" style="color: {{ $utilizationColor }}">{{ number_format($overallUtilization, 1) }}%</div>
                    <div class="label">Overall Utilization</div>
                </div>
            </div>
        </div>

        <!-- Report Details -->
        <div class="report-card">
            <h6><i class="mdi mdi-format-list-bulleted mr-2"></i>Variance by Budget</h6>

            @foreach($reportData as $budget)
                @php
                    $budgetUtilization = $budget['total_budgeted'] > 0
                        ? ($budget['total_actual'] / $budget['total_budgeted']) * 100
                        : 0;
                    $headerColor = $budget['total_variance'] >= 0 ? 'success' : 'danger';
                @endphp
                <div class="budget-section">
                    <div class="budget-section-header">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <strong>{{ $budget['budget_name'] }}</strong><br>
                                <small class="text-muted">{{ $budget['department'] }}</small>
                            </div>
                            <div class="col-md-2 text-right">
                                <small class="text-muted">Budget</small><br>
                                <strong>₦{{ number_format($budget['total_budgeted'], 2) }}</strong>
                            </div>
                            <div class="col-md-2 text-right">
                                <small class="text-muted">Actual</small><br>
                                <strong class="text-danger">₦{{ number_format($budget['total_actual'], 2) }}</strong>
                            </div>
                            <div class="col-md-2 text-right">
                                <small class="text-muted">Variance</small><br>
                                <strong class="{{ $budget['total_variance'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $budget['total_variance'] >= 0 ? '+' : '' }}₦{{ number_format($budget['total_variance'], 2) }}
                                </strong>
                            </div>
                            <div class="col-md-2 text-right">
                                <small class="text-muted">Utilization</small><br>
                                @php
                                    $itemColor = $budgetUtilization > 90 ? 'danger' : ($budgetUtilization > 75 ? 'warning' : 'success');
                                @endphp
                                <span class="badge badge-{{ $itemColor }}">{{ number_format($budgetUtilization, 1) }}%</span>
                            </div>
                        </div>
                    </div>
                    <div class="budget-section-body">
                        @foreach($budget['items'] as $item)
                            <div class="item-row">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <strong>{{ $item['account_code'] }}</strong> - {{ $item['account_name'] }}
                                    </div>
                                    <div class="col-md-2 text-right">
                                        ₦{{ number_format($item['budgeted'], 2) }}
                                    </div>
                                    <div class="col-md-2 text-right">
                                        ₦{{ number_format($item['actual'], 2) }}
                                    </div>
                                    <div class="col-md-2 text-right">
                                        <span class="{{ $item['variance'] >= 0 ? 'variance-positive' : 'variance-negative' }}">
                                            {{ $item['variance'] >= 0 ? '+' : '' }}₦{{ number_format($item['variance'], 2) }}
                                        </span>
                                    </div>
                                    <div class="col-md-2 text-right">
                                        @php
                                            $lineColor = abs($item['variance_percent']) > 20 ? 'danger' : (abs($item['variance_percent']) > 10 ? 'warning' : 'success');
                                        @endphp
                                        <span class="badge badge-{{ $lineColor }}">
                                            {{ $item['variance_percent'] >= 0 ? '+' : '' }}{{ number_format($item['variance_percent'], 1) }}%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="report-card">
            <div class="text-center py-5">
                <i class="mdi mdi-chart-bar text-muted" style="font-size: 4rem;"></i>
                <h5 class="text-muted mt-3">No approved budgets found</h5>
                <p class="text-muted">Create and approve budgets to see variance analysis</p>
                <a href="{{ route('accounting.budgets.create') }}" class="btn btn-primary">
                    <i class="mdi mdi-plus"></i> Create Budget
                </a>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#btnExport').on('click', function() {
        var params = new URLSearchParams(window.location.search);
        params.set('export', 'csv');
        window.location.href = '{{ route('accounting.budgets.variance-report') }}?' + params.toString();
    });
});
</script>
@endpush
