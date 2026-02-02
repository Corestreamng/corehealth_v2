{{--
    Budget Management Dashboard
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.10
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Budget Management')
@section('page_name', 'Accounting')
@section('subpage_name', 'Budgets')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Budgets', 'url' => '#', 'icon' => 'mdi-calculator']
    ]
])

<style>
.stat-card {
    border-radius: 10px;
    padding: 20px;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid;
    margin-bottom: 20px;
}
.stat-card .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.stat-card .stat-value { font-size: 1.5rem; font-weight: 700; color: #333; }
.stat-card .stat-label { color: #6c757d; font-size: 0.85rem; }
.filter-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.table-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
}
.utilization-bar {
    background: #e9ecef;
    border-radius: 10px;
    height: 20px;
    overflow: hidden;
}
.utilization-bar .progress-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.3s;
}
.dept-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f1f1;
}
.dept-item:last-child { border-bottom: none; }
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Stats Row -->
    <div class="row">
        <div class="col-md-3">
            <div class="stat-card" style="border-color: #667eea;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-value">₦{{ number_format($stats['total_budget'], 0) }}</div>
                        <div class="stat-label">Total Budget ({{ date('Y') }})</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(102, 126, 234, 0.1); color: #667eea;">
                        <i class="mdi mdi-calculator"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-color: #dc3545;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-value">₦{{ number_format($stats['ytd_actual'], 0) }}</div>
                        <div class="stat-label">YTD Actual Spending</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                        <i class="mdi mdi-cash-minus"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-color: {{ $stats['variance'] >= 0 ? '#28a745' : '#dc3545' }};">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-value {{ $stats['variance'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $stats['variance'] >= 0 ? '+' : '' }}₦{{ number_format($stats['variance'], 0) }}
                        </div>
                        <div class="stat-label">YTD Variance</div>
                    </div>
                    <div class="stat-icon" style="background: rgba({{ $stats['variance'] >= 0 ? '40, 167, 69' : '220, 53, 69' }}, 0.1); color: {{ $stats['variance'] >= 0 ? '#28a745' : '#dc3545' }};">
                        <i class="mdi mdi-{{ $stats['variance'] >= 0 ? 'trending-down' : 'trending-up' }}"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-color: #17a2b8;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="stat-value">{{ number_format($stats['utilization'], 1) }}%</div>
                        <div class="stat-label">Budget Utilization</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                        <i class="mdi mdi-percent"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Budget Utilization Bar -->
    @if($stats['total_budget'] > 0)
    <div class="filter-card mb-3">
        <div class="d-flex justify-content-between mb-2">
            <span><strong>YTD Budget Utilization</strong></span>
            <span>₦{{ number_format($stats['ytd_actual'], 2) }} of ₦{{ number_format($stats['total_budget'], 2) }}</span>
        </div>
        @php
            $progressColor = $stats['utilization'] > 90 ? '#dc3545' : ($stats['utilization'] > 75 ? '#ffc107' : '#28a745');
        @endphp
        <div class="utilization-bar">
            <div class="progress-fill" style="width: {{ min($stats['utilization'], 100) }}%; background: {{ $progressColor }};"></div>
        </div>
    </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <!-- Filters -->
            <div class="filter-card">
                <form id="filterForm" class="row align-items-end">
                    <div class="col-md-3">
                        <label>Fiscal Year</label>
                        <select name="fiscal_year_id" id="fiscal_year_id" class="form-control">
                            <option value="">All Years</option>
                            @foreach($fiscalYears as $fy)
                                <option value="{{ $fy->id }}" {{ $fy->year == date('Y') ? 'selected' : '' }}>
                                    {{ $fy->year }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Department</label>
                        <select name="department_id" id="department_id" class="form-control">
                            <option value="">All Departments</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="draft">Draft</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-primary btn-block" id="btnFilter">
                            <i class="mdi mdi-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Actions Bar -->
            <div class="d-flex justify-content-between mb-3">
                <div>
                    <a href="{{ route('accounting.budgets.create') }}" class="btn btn-primary">
                        <i class="mdi mdi-plus"></i> Create Budget
                    </a>
                    <a href="{{ route('accounting.budgets.variance-report') }}" class="btn btn-outline-info">
                        <i class="mdi mdi-chart-bar"></i> Variance Report
                    </a>
                </div>
                <div class="btn-group">
                    <a href="{{ route('accounting.budgets.index', array_merge(request()->all(), ['export' => 'pdf'])) }}" class="btn btn-danger">
                        <i class="mdi mdi-file-pdf mr-1"></i> PDF
                    </a>
                    <a href="{{ route('accounting.budgets.index', array_merge(request()->all(), ['export' => 'excel'])) }}" class="btn btn-success">
                        <i class="mdi mdi-file-excel mr-1"></i> Excel
                    </a>
                </div>
            </div>

            <!-- DataTable -->
            <div class="table-card">
                <table id="budgetsTable" class="table table-hover" style="width:100%">
                    <thead class="thead-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Fiscal Year</th>
                            <th>Department</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Budget Summary -->
            <div class="table-card mb-3">
                <h6 class="font-weight-bold mb-3"><i class="mdi mdi-chart-pie mr-2"></i>Budget Summary</h6>
                <div class="dept-item">
                    <span>Total Budgets</span>
                    <strong>{{ $stats['budget_count'] }}</strong>
                </div>
                <div class="dept-item">
                    <span>Approved</span>
                    <strong class="text-success">{{ $stats['approved_count'] }}</strong>
                </div>
                <div class="dept-item">
                    <span>Pending Approval</span>
                    <strong class="text-warning">{{ $stats['pending_count'] }}</strong>
                </div>
                <div class="dept-item">
                    <span>Monthly Budget</span>
                    <strong>₦{{ number_format($stats['monthly_budget'], 0) }}</strong>
                </div>
                <div class="dept-item">
                    <span>MTD Actual</span>
                    <strong class="text-danger">₦{{ number_format($stats['mtd_actual'], 0) }}</strong>
                </div>
            </div>

            <!-- Top Departments -->
            @if(count($stats['top_departments']) > 0)
            <div class="table-card">
                <h6 class="font-weight-bold mb-3"><i class="mdi mdi-domain mr-2"></i>Top Departments by Budget</h6>
                @foreach($stats['top_departments'] as $dept)
                    <div class="dept-item">
                        <div>
                            <div class="font-weight-bold">{{ $dept['department'] }}</div>
                            <small class="text-muted">₦{{ number_format($dept['budget'], 0) }}</small>
                        </div>
                        <div class="text-right">
                            @php
                                $color = $dept['utilization'] > 90 ? 'danger' : ($dept['utilization'] > 75 ? 'warning' : 'success');
                            @endphp
                            <span class="badge badge-{{ $color }}">{{ number_format($dept['utilization'], 1) }}%</span>
                        </div>
                    </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var table = $('#budgetsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('accounting.budgets.datatable') }}',
            data: function(d) {
                d.fiscal_year_id = $('#fiscal_year_id').val();
                d.department_id = $('#department_id').val();
                d.status = $('#status').val();
            }
        },
        columns: [
            { data: 'id', visible: false },
            { data: 'name' },
            { data: 'fiscal_year' },
            { data: 'department' },
            { data: 'total_amount', className: 'text-right' },
            { data: 'status', className: 'text-center' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, className: 'text-center' }
        ],
        order: [[0, 'desc']],
        pageLength: 15,
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
    });

    // Filter
    $('#btnFilter').on('click', function() {
        table.ajax.reload();
    });

    // Submit budget
    $(document).on('click', '.submit-budget', function() {
        var id = $(this).data('id');
        if (confirm('Submit this budget for approval?')) {
            $.ajax({
                url: '/accounting/budgets/' + id + '/submit',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        table.ajax.reload();
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
    $(document).on('click', '.approve-budget', function() {
        var id = $(this).data('id');
        if (confirm('Approve this budget?')) {
            $.ajax({
                url: '/accounting/budgets/' + id + '/approve',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        table.ajax.reload();
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
    $(document).on('click', '.reject-budget', function() {
        var id = $(this).data('id');
        var reason = prompt('Please provide a reason for rejection:');
        if (reason) {
            $.ajax({
                url: '/accounting/budgets/' + id + '/reject',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', reason: reason },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        table.ajax.reload();
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
});
</script>
@endpush
