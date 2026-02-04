{{--
    Capex Budget Overview
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.7
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Capex Budget Overview')
@section('page_name', 'Accounting')
@section('subpage_name', 'Budget Overview')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Capital Expenditure', 'url' => route('accounting.capex.index'), 'icon' => 'mdi-factory'],
        ['label' => 'Budget Overview', 'url' => '#', 'icon' => 'mdi-wallet']
    ]
])

<style>
.budget-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 20px;
}
.info-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.info-card h6 {
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f1f1;
}
.budget-item {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}
.budget-item.approved { border-left: 4px solid #28a745; }
.budget-item.pending { border-left: 4px solid #ffc107; }
.stat-ring {
    width: 120px;
    height: 120px;
    position: relative;
}
.category-bar {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f1f1;
}
.category-bar:last-child { border-bottom: none; }
.category-bar .progress { flex: 1; margin: 0 15px; height: 8px; }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="budget-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h3 class="mb-1">Capex Budget Overview</h3>
                <div>Fiscal Year: {{ $fiscalYear }}</div>
                <small class="opacity-75 d-block mt-1">
                    <i class="mdi mdi-information-outline mr-1"></i>
                    Manage and monitor capital expenditure budgets by category and cost center
                </small>
            </div>
            <div class="col-md-6 text-md-right">
                <select class="form-control d-inline-block w-auto bg-light" id="yearSelect">
                    @foreach($fiscalYears as $year)
                        <option value="{{ $year }}" {{ $year == $fiscalYear ? 'selected' : '' }}>FY {{ $year }}</option>
                    @endforeach
                </select>
                <a href="{{ route('accounting.capex.index') }}" class="btn btn-light btn-sm ml-2">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back to Capex
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Budget Allocations -->
            <div class="info-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0"><i class="mdi mdi-wallet-outline mr-2"></i>Budget Allocations</h6>
                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addBudgetModal">
                        <i class="mdi mdi-plus mr-1"></i> Add Budget
                    </button>
                </div>
                <p class="text-muted small mb-3">
                    <i class="mdi mdi-information-outline mr-1"></i>
                    View and manage budget allocations for capital expenditures. Create separate budgets for different categories or departments. <strong>Committed</strong> represents approved requests, while <strong>Spent</strong> shows actual expenditures.
                </p>

                @forelse($budgets as $budget)
                    <div class="budget-item">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <strong>{{ $budget->budget_name ?? 'Unnamed Budget' }}</strong>
                                @if($budget->cost_center_name)
                                    <small class="text-muted d-block">{{ $budget->cost_center_name }}</small>
                                @endif
                                @if($budget->status)
                                    @php
                                        $statusClass = match($budget->status) {
                                            'approved', 'active' => 'success',
                                            'locked' => 'info',
                                            'draft' => 'warning',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <span class="badge badge-{{ $statusClass }} badge-sm">
                                        {{ ucfirst($budget->status) }}
                                    </span>
                                @endif
                            </div>
                            <div class="col-md-4 text-center">
                                <small class="text-muted">Allocated</small>
                                <div class="h5 mb-0">₦{{ number_format($budget->amount, 0) }}</div>
                            </div>
                            <div class="col-md-4 text-right">
                                <small class="text-muted">Committed / Spent</small>
                                <div>
                                    <span class="text-success">₦{{ number_format($budget->committed ?? 0, 0) }}</span>
                                    <span class="mx-1">/</span>
                                    <span class="text-info">₦{{ number_format($budget->spent ?? 0, 0) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        <i class="mdi mdi-wallet-outline mdi-48px"></i>
                        <p class="mt-2 mb-0">No budget allocations for {{ $fiscalYear }}</p>
                        <small>Click "Add Budget" to create a Capex budget</small>
                    </div>
                @endforelse
            </div>

            <!-- Spending by Category Chart -->
            <div class="info-card">
                <h6><i class="mdi mdi-chart-bar mr-2"></i>Spending by Category</h6>
                <p class="text-muted small mb-3">
                    <i class="mdi mdi-information-outline mr-1"></i>
                    Visual breakdown of capital expenditure spending across different asset categories for the selected fiscal year.
                </p>
                <canvas id="categoryChart" height="250"></canvas>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- CAPEX Requests Summary -->
            <div class="info-card mb-3">
                <h6><i class="mdi mdi-file-document-multiple mr-2"></i>CAPEX Requests (FY {{ $fiscalYear }})</h6>
                <p class="text-muted small mb-3">
                    <i class="mdi mdi-information-outline mr-1"></i>
                    Overview of capital expenditure requests and their status for this fiscal year.
                </p>
                <div class="row text-center">
                    <div class="col-6 col-md-3 mb-2">
                        <div class="h4 mb-0 text-warning">{{ $capexStats->pending ?? 0 }}</div>
                        <small class="text-muted">Pending</small>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="h4 mb-0 text-success">{{ $capexStats->approved ?? 0 }}</div>
                        <small class="text-muted">Approved</small>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="h4 mb-0 text-info">{{ $capexStats->in_progress ?? 0 }}</div>
                        <small class="text-muted">In Progress</small>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="h4 mb-0 text-primary">{{ $capexStats->completed ?? 0 }}</div>
                        <small class="text-muted">Completed</small>
                    </div>
                </div>
                @if(($capexStats->pending ?? 0) > 0)
                    <div class="alert alert-warning py-2 px-3 mt-2 mb-0">
                        <small>
                            <i class="mdi mdi-alert mr-1"></i>
                            ₦{{ number_format($capexStats->pending_amount ?? 0, 0) }} in pending requests awaiting approval
                        </small>
                    </div>
                @endif
                <a href="{{ route('accounting.capex.index', ['fiscal_year' => $fiscalYear]) }}" class="btn btn-outline-primary btn-sm btn-block mt-2">
                    <i class="mdi mdi-eye mr-1"></i> View All Requests
                </a>
            </div>

            <!-- Summary -->
            <div class="info-card">
                <h6><i class="mdi mdi-calculator mr-2"></i>Budget Summary</h6>
                <p class="text-muted small mb-3">
                    <i class="mdi mdi-information-outline mr-1"></i>
                    Overall budget performance metrics showing total allocated budget, committed amounts (approved requests), actual spending, and remaining available budget.
                </p>
                @php
                    $utilization = $totalBudget > 0 ? round(($totalCommitted / $totalBudget) * 100, 1) : 0;
                @endphp

                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Total Budget:</span>
                    <strong>₦{{ number_format($totalBudget, 0) }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Committed:</span>
                    <strong class="text-success">₦{{ number_format($totalCommitted, 0) }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Spent:</span>
                    <strong class="text-info">₦{{ number_format($totalSpent, 0) }}</strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Remaining:</span>
                    <strong class="{{ ($totalBudget - $totalCommitted) >= 0 ? 'text-success' : 'text-danger' }}">
                        ₦{{ number_format($totalBudget - $totalCommitted, 0) }}
                    </strong>
                </div>
                <div class="progress mb-2" style="height: 20px;">
                    <div class="progress-bar bg-{{ $utilization < 75 ? 'success' : ($utilization < 90 ? 'warning' : 'danger') }}"
                         style="width: {{ min($utilization, 100) }}%;">
                        {{ $utilization }}%
                    </div>
                </div>
                <small class="text-muted text-center d-block">Budget Utilization</small>
            </div>

            <!-- By Category Breakdown -->
            <div class="info-card">
                <h6><i class="mdi mdi-chart-pie mr-2"></i>By Category</h6>
                <p class="text-muted small mb-3">
                    <i class="mdi mdi-information-outline mr-1"></i>
                    Category-wise breakdown showing committed amounts against allocated budgets.
                </p>
                @forelse($byCategory as $cat)
                    @php
                        $catTotal = $cat->committed ?? 0;
                        $catUtil = $totalCommitted > 0 ? round(($cat->committed / $totalCommitted) * 100, 1) : 0;
                    @endphp
                    <div class="category-bar">
                        <span style="width: 100px;">{{ $categories[$cat->category] ?? ucfirst($cat->category ?? 'Other') }}</span>
                        <div class="progress">
                            <div class="progress-bar bg-info" style="width: {{ min($catUtil, 100) }}%;"></div>
                        </div>
                        <span style="width: 80px;" class="text-right">
                            ₦{{ number_format($cat->committed, 0) }}
                        </span>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">
                        <small>No spending data</small>
                    </div>
                @endforelse
            </div>

            <!-- Quick Links -->
            <div class="info-card">
                <h6><i class="mdi mdi-link mr-2"></i>Quick Links</h6>
                <a href="{{ route('accounting.capex.index') }}" class="btn btn-outline-primary btn-block btn-sm mb-2">
                    <i class="mdi mdi-format-list-bulleted mr-1"></i> View All Requests
                </a>
                <a href="{{ route('accounting.capex.create') }}" class="btn btn-outline-success btn-block btn-sm mb-2">
                    <i class="mdi mdi-plus mr-1"></i> New Capex Request
                </a>
                <a href="{{ route('accounting.capex.export') }}?fiscal_year={{ $fiscalYear }}" class="btn btn-outline-info btn-block btn-sm">
                    <i class="mdi mdi-download mr-1"></i> Export Report
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Add Budget Modal -->
<div class="modal fade" id="addBudgetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('accounting.capex.budget.store') }}" method="POST" id="budgetForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Capex Budget</h5>
                    <button type="button" class="close"  data-bs-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="alert alert-info py-2 px-3 mb-3">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        <small>Create a budget allocation for a specific category or cost center. This sets spending limits for capital expenditure requests.</small>
                    </div>
                    <div class="form-group">
                        <label>Budget Name</label>
                        <input type="text" class="form-control" name="name" placeholder="e.g., IT Equipment Budget">
                        <small class="form-text text-muted">Optional: Give this budget a descriptive name</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Category <span class="text-danger">*</span></label>
                                <select class="form-control" name="category" required>
                                    @foreach($categories as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Select the asset category for this budget</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Fiscal Year</label>
                                <input type="text" class="form-control bg-light" value="{{ $fiscalYear }}" readonly>
                                <input type="hidden" name="fiscal_year" value="{{ $fiscalYear }}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">₦</span>
                            </div>
                            <input type="number" class="form-control" name="amount" step="0.01" min="0" required>
                        </div>
                        <small class="form-text text-muted">Total budget allocation for this category in the fiscal year</small>
                    </div>
                    <div class="form-group mb-0">
                        <label>Cost Center</label>
                        <select class="form-control" name="cost_center_id">
                            <option value="">-- All Departments --</option>
                            @foreach($costCenters as $center)
                                <option value="{{ $center->id }}">{{ $center->name }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Optional: Assign this budget to a specific department or cost center</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-content-save mr-1"></i> Save Budget
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Year selector
    $('#yearSelect').on('change', function() {
        window.location.href = '{{ route("accounting.capex.budget-overview") }}?fiscal_year=' + $(this).val();
    });

    // Category Chart
    var categoryData = @json($byCategory);
    var categories = categoryData.map(d => d.category ? d.category.charAt(0).toUpperCase() + d.category.slice(1) : 'Other');
    var committed = categoryData.map(d => parseFloat(d.committed) || 0);
    var spent = categoryData.map(d => parseFloat(d.spent) || 0);

    new Chart(document.getElementById('categoryChart'), {
        type: 'bar',
        data: {
            labels: categories,
            datasets: [
                {
                    label: 'Committed',
                    data: committed,
                    backgroundColor: '#28a745',
                    borderRadius: 4
                },
                {
                    label: 'Spent',
                    data: spent,
                    backgroundColor: '#17a2b8',
                    borderRadius: 4
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
                            return '₦' + (value / 1000) + 'K';
                        }
                    }
                }
            }
        }
    });
});
</script>
@endpush
