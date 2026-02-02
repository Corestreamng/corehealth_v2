{{--
    Cost Center Budgets Management
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.11
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Budgets: ' . $costCenter->code)
@section('page_name', 'Accounting')
@section('subpage_name', 'Cost Center Budgets')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Cost Centers', 'url' => route('accounting.cost-centers.index'), 'icon' => 'mdi-sitemap'],
        ['label' => $costCenter->code, 'url' => route('accounting.cost-centers.show', $costCenter->id), 'icon' => 'mdi-eye'],
        ['label' => 'Budgets', 'url' => '#', 'icon' => 'mdi-cash']
    ]
])

<style>
.budget-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
}
.budget-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.budget-card h6 {
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f1f1;
}
.budget-year-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.2s;
}
.budget-year-card:hover {
    border-color: #667eea;
}
.budget-year-card.current {
    border-color: #28a745;
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.05) 0%, rgba(40, 167, 69, 0.1) 100%);
}
.budget-progress { margin-top: 15px; }
.budget-progress .progress { height: 15px; border-radius: 8px; }
.budget-progress .progress-bar { border-radius: 8px; }
.select2-container { z-index: 9999 !important; }
.select2-dropdown { z-index: 9999 !important; }
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Header -->
    <div class="budget-header">
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
                <button class="btn btn-light btn-sm" data-toggle="modal" data-target="#addBudgetModal">
                    <i class="mdi mdi-plus"></i> Add Budget
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Budget List -->
            <div class="budget-card">
                <h6><i class="mdi mdi-calendar-month mr-2"></i>Budget History</h6>

                @forelse($budgets as $budget)
                    @php
                        $isCurrent = $budget->fiscal_year == date('Y');
                        $actualSpent = $budgetActuals[$budget->fiscal_year] ?? 0;
                        $utilization = $budget->budgeted_amount > 0 ? ($actualSpent / $budget->budgeted_amount) * 100 : 0;
                        $remaining = $budget->budgeted_amount - $actualSpent;
                        $progressColor = $utilization > 90 ? 'danger' : ($utilization > 75 ? 'warning' : 'success');
                    @endphp
                    <div class="budget-year-card {{ $isCurrent ? 'current' : '' }}">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <h4 class="mb-0">{{ $budget->fiscal_year }}</h4>
                                @if($isCurrent)
                                    <span class="badge badge-success">Current</span>
                                @endif
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="text-muted small">Budget Amount</div>
                                <h5 class="mb-0">₦{{ number_format($budget->budgeted_amount, 2) }}</h5>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="text-muted small">Actual Spent</div>
                                <h5 class="mb-0 text-danger">₦{{ number_format($actualSpent, 2) }}</h5>
                            </div>
                            <div class="col-md-2 text-center">
                                <div class="text-muted small">Remaining</div>
                                <h5 class="mb-0 {{ $remaining >= 0 ? 'text-success' : 'text-danger' }}">
                                    ₦{{ number_format(abs($remaining), 2) }}
                                </h5>
                            </div>
                            <div class="col-md-2 text-right">
                                <button class="btn btn-sm btn-outline-primary edit-budget"
                                        data-id="{{ $budget->id }}"
                                        data-year="{{ $budget->fiscal_year }}"
                                        data-amount="{{ $budget->budgeted_amount }}"
                                        data-notes="{{ $budget->notes }}">
                                    <i class="mdi mdi-pencil"></i>
                                </button>
                            </div>
                        </div>
                        <div class="budget-progress">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Utilization</small>
                                <small>{{ number_format($utilization, 1) }}%</small>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-{{ $progressColor }}" style="width: {{ min($utilization, 100) }}%"></div>
                            </div>
                        </div>
                        @if($budget->notes)
                            <div class="mt-2">
                                <small class="text-muted">{{ $budget->notes }}</small>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center py-4">
                        <i class="mdi mdi-cash-remove text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No budgets defined yet</p>
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addBudgetModal">
                            <i class="mdi mdi-plus"></i> Add First Budget
                        </button>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Quick Stats -->
            <div class="budget-card">
                <h6><i class="mdi mdi-chart-pie mr-2"></i>Current Year Summary</h6>
                @php
                    $currentBudget = $budgets->firstWhere('fiscal_year', date('Y'));
                    $currentActual = $budgetActuals[date('Y')] ?? 0;
                @endphp
                @if($currentBudget)
                    <div class="text-center mb-3">
                        <h2 class="mb-0">₦{{ number_format($currentBudget->budgeted_amount, 2) }}</h2>
                        <small class="text-muted">{{ date('Y') }} Budget</small>
                    </div>
                    @php
                        $utilization = $currentBudget->budgeted_amount > 0 ? ($currentActual / $currentBudget->budgeted_amount) * 100 : 0;
                        $remaining = $currentBudget->budgeted_amount - $currentActual;
                    @endphp
                    <div class="d-flex justify-content-between mb-2">
                        <span>Spent:</span>
                        <strong class="text-danger">₦{{ number_format($currentActual, 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Remaining:</span>
                        <strong class="{{ $remaining >= 0 ? 'text-success' : 'text-danger' }}">
                            ₦{{ number_format(abs($remaining), 2) }}
                        </strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Utilization:</span>
                        <strong>{{ number_format($utilization, 1) }}%</strong>
                    </div>
                @else
                    <div class="text-center py-3">
                        <p class="text-muted mb-2">No budget for {{ date('Y') }}</p>
                        <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#addBudgetModal">
                            Add {{ date('Y') }} Budget
                        </button>
                    </div>
                @endif
            </div>

            <!-- Budget Trend -->
            @if($budgets->count() >= 2)
            <div class="budget-card">
                <h6><i class="mdi mdi-trending-up mr-2"></i>Budget Trend</h6>
                <canvas id="budgetTrendChart" height="200"></canvas>
            </div>
            @endif

            <!-- Actions -->
            <div class="budget-card">
                <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                <a href="{{ route('accounting.cost-centers.show', $costCenter->id) }}" class="btn btn-outline-primary btn-block btn-sm mb-2">
                    <i class="mdi mdi-eye mr-1"></i> View Details
                </a>
                <a href="{{ route('accounting.cost-centers.report', $costCenter->id) }}" class="btn btn-outline-info btn-block btn-sm mb-2">
                    <i class="mdi mdi-chart-bar mr-1"></i> View Report
                </a>
                <a href="{{ route('accounting.cost-centers.index') }}" class="btn btn-outline-secondary btn-block btn-sm">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Add Budget Modal -->
<div class="modal fade" id="addBudgetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('accounting.cost-centers.budgets.store', $costCenter->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Budget</h5>
                    <button type="button" class="close"  data-bs-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Fiscal Year <span class="text-danger">*</span></label>
                        <select name="fiscal_year_id" class="form-control" required>
                            <option value="">Select Fiscal Year</option>
                            @foreach($fiscalYears as $fy)
                                <option value="{{ $fy->id }}">
                                    {{ $fy->year_name }} ({{ \Carbon\Carbon::parse($fy->start_date)->format('M Y') }} - {{ \Carbon\Carbon::parse($fy->end_date)->format('M Y') }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Expense Account <span class="text-danger">*</span></label>
                        <select name="account_id" class="form-control select2-account" required>
                            <option value="">Select Account</option>
                            @foreach($accounts as $groupName => $groupAccounts)
                                <optgroup label="{{ $groupName }}">
                                    @foreach($groupAccounts as $account)
                                        <option value="{{ $account->id }}">
                                            {{ $account->code }} - {{ $account->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Budget Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">₦</span>
                            </div>
                            <input type="number" name="budget_amount" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Optional notes about this budget"></textarea>
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

<!-- Edit Budget Modal -->
<div class="modal fade" id="editBudgetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" id="editBudgetForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Budget - <span id="editYear"></span></h5>
                    <button type="button" class="close"  data-bs-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Budget Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">₦</span>
                            </div>
                            <input type="number" name="budget_amount" id="editAmount" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-content-save mr-1"></i> Update Budget
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
    // Initialize Select2 for account dropdown in modal
    $('.select2-account').select2({
        dropdownParent: $('#addBudgetModal'),
        width: '100%',
        placeholder: 'Select Account',
        allowClear: true
    });

    // Edit budget
    $('.edit-budget').on('click', function() {
        var id = $(this).data('id');
        var year = $(this).data('year');
        var amount = $(this).data('amount');
        var notes = $(this).data('notes');

        $('#editYear').text(year);
        $('#editAmount').val(amount);
        $('#editNotes').val(notes);
        $('#editBudgetForm').attr('action', '{{ route('accounting.cost-centers.budgets.update', [$costCenter->id, '__ID__']) }}'.replace('__ID__', id));

        $('#editBudgetModal').modal('show');
    });

    // Budget trend chart
    @if($budgets->count() >= 2)
    var budgetData = @json($budgets->sortBy('fiscal_year')->values());
    var actualsData = @json($budgetActuals);

    new Chart(document.getElementById('budgetTrendChart'), {
        type: 'bar',
        data: {
            labels: budgetData.map(b => b.fiscal_year),
            datasets: [
                {
                    label: 'Budget',
                    data: budgetData.map(b => parseFloat(b.budgeted_amount)),
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderColor: '#667eea',
                    borderWidth: 1
                },
                {
                    label: 'Actual',
                    data: budgetData.map(b => actualsData[b.fiscal_year] || 0),
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: '#dc3545',
                    borderWidth: 1
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
    @endif
});
</script>
@endpush
