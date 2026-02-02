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

                @forelse($budgets as $budget)
                    <div class="budget-item {{ $budget->status }}">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <strong>{{ $budget->name ?? $budget->category }}</strong>
                                @if($budget->cost_center_name)
                                    <small class="text-muted d-block">{{ $budget->cost_center_name }}</small>
                                @endif
                                <span class="badge badge-{{ $budget->status == 'approved' ? 'success' : 'warning' }} badge-sm">
                                    {{ ucfirst($budget->status) }}
                                </span>
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
                <canvas id="categoryChart" height="250"></canvas>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Summary -->
            <div class="info-card">
                <h6><i class="mdi mdi-calculator mr-2"></i>Budget Summary</h6>
                @php
                    $totalBudget = $budgets->where('status', 'approved')->sum('amount');
                    $totalCommitted = $byCategory->sum('committed');
                    $totalSpent = $byCategory->sum('spent');
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
                @forelse($byCategory as $cat)
                    @php
                        $catTotal = $budgets->where('status', 'approved')->where('category', $cat->category)->sum('amount');
                        $catUtil = $catTotal > 0 ? round(($cat->committed / $catTotal) * 100, 1) : 0;
                    @endphp
                    <div class="category-bar">
                        <span style="width: 100px;">{{ ucfirst($cat->category ?? 'Other') }}</span>
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
            <form action="{{ route('accounting.capex.budget-overview') }}" method="POST" id="budgetForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Capex Budget</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Budget Name</label>
                        <input type="text" class="form-control" name="name" placeholder="e.g., IT Equipment Budget">
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
                    </div>
                    <div class="form-group mb-0">
                        <label>Cost Center</label>
                        <select class="form-control" name="cost_center_id">
                            <option value="">-- All Departments --</option>
                            {{-- Add cost centers dynamically --}}
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
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
