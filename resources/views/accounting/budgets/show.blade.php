{{--
    Budget Details
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.10
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Budget: ' . $budget->budget_name)
@section('page_name', 'Accounting')
@section('subpage_name', 'Budget Details')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Budgets', 'url' => route('accounting.budgets.index'), 'icon' => 'mdi-calculator'],
        ['label' => $budget->budget_name, 'url' => '#', 'icon' => 'mdi-eye']
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
.budget-header h3 { margin: 0; font-weight: 600; }
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
.stat-box {
    text-align: center;
    padding: 20px;
    border-radius: 8px;
    background: #f8f9fa;
}
.stat-box .amount { font-size: 1.5rem; font-weight: 700; }
.stat-box .label { color: #666; font-size: 0.85rem; }
.item-row {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 8px;
    background: #f8f9fa;
}
.item-row:hover { background: #e9ecef; }
.progress-thin {
    height: 8px;
    border-radius: 4px;
}
.balance-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    margin-bottom: 20px;
}
.balance-card .amount { font-size: 1.8rem; font-weight: 700; }
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Header -->
    <div class="budget-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h3>{{ $budget->budget_name }}</h3>
                <div class="mt-2">
                    <span class="badge badge-light mr-2">{{ $budget->fiscalYear->year ?? 'N/A' }}</span>
                    @php
                        $statusColors = [
                            'draft' => 'secondary',
                            'pending_approval' => 'warning',
                            'approved' => 'success',
                            'locked' => 'dark'
                        ];
                    @endphp
                    <span class="badge badge-{{ $statusColors[$budget->status] ?? 'secondary' }}">
                        {{ ucfirst(str_replace('_', ' ', $budget->status)) }}
                    </span>
                    @if($budget->department)
                        <span class="badge badge-light ml-1">{{ $budget->department->name }}</span>
                    @endif
                </div>
            </div>
            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                @if($budget->status == 'draft')
                    <a href="{{ route('accounting.budgets.edit', $budget->id) }}" class="btn btn-light btn-sm">
                        <i class="mdi mdi-pencil"></i> Edit
                    </a>
                @endif
                <a href="{{ route('accounting.budgets.export', $budget->id) }}" class="btn btn-light btn-sm">
                    <i class="mdi mdi-download"></i> Export
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    @php
        $totalBudgeted = $itemsWithActuals->sum('budgeted_amount');
        $totalActual = $itemsWithActuals->sum('actual_amount');
        $totalVariance = $totalBudgeted - $totalActual;
        $overallUtilization = $totalBudgeted > 0 ? ($totalActual / $totalBudgeted) * 100 : 0;
    @endphp
    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
                <div class="amount text-info">₦{{ number_format($totalBudgeted, 2) }}</div>
                <div class="label">Total Budgeted</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);">
                <div class="amount text-danger">₦{{ number_format($totalActual, 2) }}</div>
                <div class="label">YTD Actual</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-box" style="background: linear-gradient(135deg, {{ $totalVariance >= 0 ? '#d4edda' : '#f8d7da' }} 0%, {{ $totalVariance >= 0 ? '#c3e6cb' : '#f5c6cb' }} 100%);">
                <div class="amount {{ $totalVariance >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $totalVariance >= 0 ? '+' : '' }}₦{{ number_format($totalVariance, 2) }}
                </div>
                <div class="label">Variance</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            @php
                $utilizationColor = $overallUtilization > 90 ? '#dc3545' : ($overallUtilization > 75 ? '#ffc107' : '#28a745');
            @endphp
            <div class="stat-box" style="background: linear-gradient(135deg, rgba({{ $overallUtilization > 90 ? '220,53,69' : ($overallUtilization > 75 ? '255,193,7' : '40,167,69') }}, 0.1) 0%, rgba({{ $overallUtilization > 90 ? '220,53,69' : ($overallUtilization > 75 ? '255,193,7' : '40,167,69') }}, 0.2) 100%);">
                <div class="amount" style="color: {{ $utilizationColor }}">{{ number_format($overallUtilization, 1) }}%</div>
                <div class="label">Utilization</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Budget Items -->
            <div class="info-card">
                <h6><i class="mdi mdi-format-list-numbered mr-2"></i>Budget Line Items</h6>
                @foreach($itemsWithActuals as $item)
                    @php
                        $itemColor = $item->utilization > 90 ? 'danger' : ($item->utilization > 75 ? 'warning' : 'success');
                    @endphp
                    <div class="item-row">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <strong>{{ $item->account->code ?? 'N/A' }}</strong><br>
                                <small class="text-muted">{{ $item->account->name ?? 'Unknown Account' }}</small>
                            </div>
                            <div class="col-md-2 text-right">
                                <small class="text-muted">Budget</small><br>
                                <strong>₦{{ number_format($item->budgeted_amount, 2) }}</strong>
                            </div>
                            <div class="col-md-2 text-right">
                                <small class="text-muted">Actual</small><br>
                                <strong class="text-danger">₦{{ number_format($item->actual_amount, 2) }}</strong>
                            </div>
                            <div class="col-md-2 text-right">
                                <small class="text-muted">Variance</small><br>
                                <strong class="{{ $item->variance >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $item->variance >= 0 ? '+' : '' }}₦{{ number_format($item->variance, 2) }}
                                </strong>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">{{ number_format($item->utilization, 1) }}%</small>
                                <div class="progress progress-thin">
                                    <div class="progress-bar bg-{{ $itemColor }}" style="width: {{ min($item->utilization, 100) }}%"></div>
                                </div>
                            </div>
                        </div>
                        @if($item->notes)
                            <small class="text-muted mt-1 d-block">Note: {{ $item->notes }}</small>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Monthly Trend -->
            <div class="info-card">
                <h6><i class="mdi mdi-chart-line mr-2"></i>Monthly Budget vs Actual</h6>
                <canvas id="monthlyChart" height="250"></canvas>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Remaining Balance -->
            <div class="balance-card" style="background: linear-gradient(135deg, {{ $totalVariance >= 0 ? '#28a745' : '#dc3545' }} 0%, {{ $totalVariance >= 0 ? '#20c997' : '#c82333' }} 100%);">
                <div class="label">{{ $totalVariance >= 0 ? 'Remaining Budget' : 'Over Budget' }}</div>
                <div class="amount">₦{{ number_format(abs($totalVariance), 2) }}</div>
            </div>

            <!-- Budget Info -->
            <div class="info-card">
                <h6><i class="mdi mdi-information-outline mr-2"></i>Budget Information</h6>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Fiscal Year:</span>
                    <strong>{{ $budget->fiscalYear->year ?? 'N/A' }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Department:</span>
                    <strong>{{ $budget->department->name ?? 'Organization-wide' }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Status:</span>
                    <span class="badge badge-{{ $statusColors[$budget->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $budget->status)) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Created By:</span>
                    <strong>{{ $budget->createdBy->name ?? 'System' }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Created:</span>
                    <strong>{{ $budget->created_at->format('M d, Y') }}</strong>
                </div>
                @if($budget->approved_at)
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Approved By:</span>
                        <strong>{{ $budget->approvedBy->name ?? 'N/A' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Approved:</span>
                        <strong>{{ \Carbon\Carbon::parse($budget->approved_at)->format('M d, Y') }}</strong>
                    </div>
                @endif
                @if($budget->description)
                    <hr>
                    <small class="text-muted">{{ $budget->description }}</small>
                @endif
            </div>

            <!-- Actions -->
            <div class="info-card">
                <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                @if($budget->status == 'draft')
                    <a href="{{ route('accounting.budgets.edit', $budget->id) }}" class="btn btn-outline-primary btn-block btn-sm mb-2">
                        <i class="mdi mdi-pencil mr-1"></i> Edit Budget
                    </a>
                    <button class="btn btn-success btn-block btn-sm mb-2" id="submitBudget">
                        <i class="mdi mdi-send mr-1"></i> Submit for Approval
                    </button>
                @endif
                @if($budget->status == 'pending_approval' && auth()->user()->hasRole(['SUPERADMIN', 'ADMIN']))
                    <button class="btn btn-success btn-block btn-sm mb-2" id="approveBudget">
                        <i class="mdi mdi-check mr-1"></i> Approve
                    </button>
                    <button class="btn btn-danger btn-block btn-sm mb-2" id="rejectBudget">
                        <i class="mdi mdi-close mr-1"></i> Reject
                    </button>
                @endif
                @if($budget->isApprovedOnly() && auth()->user()->hasRole('SUPERADMIN'))
                    <button class="btn btn-warning btn-block btn-sm mb-2" id="unapproveBudget">
                        <i class="mdi mdi-undo mr-1"></i> Unapprove
                    </button>
                    <button class="btn btn-dark btn-block btn-sm mb-2" id="lockBudget">
                        <i class="mdi mdi-lock mr-1"></i> Lock Budget
                    </button>
                @endif
                @if($budget->isLocked())
                    <div class="alert alert-dark text-center mb-2">
                        <i class="mdi mdi-lock"></i> <strong>Budget Locked</strong><br>
                        <small>This budget is permanently locked and cannot be modified.</small>
                    </div>
                @endif
                <a href="{{ route('accounting.budgets.export', $budget->id) }}" class="btn btn-outline-info btn-block btn-sm mb-2">
                    <i class="mdi mdi-download mr-1"></i> Export Excel
                </a>
                <a href="{{ route('accounting.budgets.index') }}" class="btn btn-outline-secondary btn-block btn-sm">
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
    // Monthly Chart
    var monthlyData = @json($monthlyData);

    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: monthlyData.map(m => m.month),
            datasets: [
                {
                    label: 'Budget',
                    data: monthlyData.map(m => m.budget),
                    backgroundColor: 'rgba(102, 126, 234, 0.7)',
                    borderColor: '#667eea',
                    borderWidth: 1
                },
                {
                    label: 'Actual',
                    data: monthlyData.map(m => m.actual),
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

    // Submit budget
    $('#submitBudget').on('click', function() {
        if (confirm('Submit this budget for approval?')) {
            $.ajax({
                url: '{{ route('accounting.budgets.submit', $budget->id) }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        location.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('Failed to submit budget');
                }
            });
        }
    });

    // Approve budget
    $('#approveBudget').on('click', function() {
        if (confirm('Approve this budget?')) {
            $.ajax({
                url: '{{ route('accounting.budgets.approve', $budget->id) }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        location.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('Failed to approve budget');
                }
            });
        }
    });

    // Reject budget
    $('#rejectBudget').on('click', function() {
        var reason = prompt('Please provide a reason for rejection:');
        if (reason) {
            $.ajax({
                url: '{{ route('accounting.budgets.reject', $budget->id) }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', reason: reason },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        location.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('Failed to reject budget');
                }
            });
        }
    });

    // Unapprove budget
    $('#unapproveBudget').on('click', function() {
        var reason = prompt('⚠️ IMPORTANT: Please provide a detailed reason for unapproving this budget (minimum 10 characters):');
        if (reason && reason.length >= 10) {
            if (confirm('Are you sure you want to unapprove this budget? This action will be logged in the audit trail.')) {
                $.ajax({
                    url: '{{ route('accounting.budgets.unapprove', $budget->id) }}',
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}', reason: reason },
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            location.reload();
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function() {
                        toastr.error('Failed to unapprove budget');
                    }
                });
            }
        } else if (reason !== null) {
            toastr.error('Reason must be at least 10 characters long');
        }
    });

    // Lock budget
    $('#lockBudget').on('click', function() {
        if (confirm('⚠️ WARNING: Locking this budget will prevent all future changes, including unapproval. Are you sure?')) {
            $.ajax({
                url: '{{ route('accounting.budgets.lock', $budget->id) }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        location.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('Failed to lock budget');
                }
            });
        }
    });
});
</script>
@endpush
