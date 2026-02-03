{{--
    Cost Centers Dashboard
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.11
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Cost Centers')
@section('page_name', 'Accounting')
@section('subpage_name', 'Cost Centers')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Cost Centers', 'url' => '#', 'icon' => 'mdi-sitemap']
    ]
])

<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">

<style>
.stat-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid;
}
.stat-card.primary { border-left-color: #667eea; }
.stat-card.success { border-left-color: #28a745; }
.stat-card.warning { border-left-color: #ffc107; }
.stat-card.info { border-left-color: #17a2b8; }
.stat-card .value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #333;
}
.stat-card .label {
    color: #666;
    font-size: 0.85rem;
}
.filter-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
}
.top-center-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}
.top-center-item:last-child {
    border-bottom: none;
}
.budget-progress {
    height: 25px;
    border-radius: 15px;
    overflow: hidden;
    background: #e9ecef;
}
.budget-progress .fill {
    height: 100%;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
}
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
        <div class="col-lg-3 col-md-6">
            <div class="stat-card primary">
                <div class="value">{{ number_format($stats['total_centers']) }}</div>
                <div class="label">Total Cost Centers</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card warning">
                <div class="value">₦{{ number_format($stats['mtd_expenses'], 0) }}</div>
                <div class="label">MTD Expenses</div>
                <small class="text-muted">Month-to-date</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card success">
                <div class="value">₦{{ number_format($stats['ytd_expenses'], 0) }}</div>
                <div class="label">YTD Expenses</div>
                <small class="text-muted">Year-to-date</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card info">
                <div class="value">₦{{ number_format($stats['budget_data']['total_budget'], 0) }}</div>
                <div class="label">Total Budget</div>
                <small class="text-muted">Current fiscal year</small>
            </div>
        </div>
    </div>

    <!-- Budget Utilization -->
    @if($stats['budget_data']['total_budget'] > 0)
    <div class="row mb-4">
        <div class="col-12">
            <div class="card-modern">
                <div class="card-body">
                    <h6 class="mb-3">Budget Utilization</h6>
                    @php
                        $utilizationPercent = min(100, ($stats['budget_data']['utilized'] / $stats['budget_data']['total_budget']) * 100);
                        $utilizationColor = $utilizationPercent > 90 ? 'danger' : ($utilizationPercent > 75 ? 'warning' : 'success');
                    @endphp
                    <div class="budget-progress">
                        <div class="fill bg-{{ $utilizationColor }}" style="width: {{ $utilizationPercent }}%">
                            {{ number_format($utilizationPercent, 1) }}% Utilized
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small>Utilized: ₦{{ number_format($stats['budget_data']['utilized'], 0) }}</small>
                        <small>Remaining: ₦{{ number_format($stats['budget_data']['remaining'], 0) }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Action Buttons -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="d-flex justify-content-between flex-wrap">
                <div>
                    <a href="{{ route('accounting.cost-centers.create') }}" class="btn btn-primary">
                        <i class="mdi mdi-plus mr-1"></i> Add Cost Center
                    </a>
                    {{-- <a href="{{ route('accounting.cost-centers.allocations.index') }}" class="btn btn-outline-info ml-2">
                        <i class="mdi mdi-arrow-split-vertical mr-1"></i> Allocations
                    </a> --}}
                </div>
                <div class="btn-group">
                    <a href="{{ route('accounting.cost-centers.export.pdf', request()->query()) }}" class="btn btn-danger">
                        <i class="mdi mdi-file-pdf mr-1"></i> PDF
                    </a>
                    <a href="{{ route('accounting.cost-centers.export.excel', request()->query()) }}" class="btn btn-success">
                        <i class="mdi mdi-file-excel mr-1"></i> Excel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Table -->
        <div class="col-lg-8">
            <!-- Filters -->
            <div class="filter-card">
                <div class="row">
                    <div class="col-md-4">
                        <label>Type</label>
                        <select id="filter-type" class="form-control">
                            <option value="">All Types</option>
                            @foreach($centerTypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Department</label>
                        <select id="filter-department" class="form-control">
                            <option value="">All Departments</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Status</label>
                        <select id="filter-active" class="form-control">
                            <option value="">All</option>
                            <option value="1">Active Only</option>
                            <option value="0">Inactive Only</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <button type="button" id="btn-filter" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-filter mr-1"></i> Apply
                        </button>
                        <button type="button" id="btn-clear" class="btn btn-outline-secondary btn-sm ml-2">
                            <i class="mdi mdi-close mr-1"></i> Clear
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="cost-centers-table" class="table table-striped table-bordered w-100">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Department</th>
                                <th>YTD Expenses</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Top Centers by Expense -->
            <div class="card-modern mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="mdi mdi-trending-up mr-2"></i>Top Centers by YTD Expense</h6>
                </div>
                <div class="card-body">
                    @forelse($stats['top_centers'] as $center)
                        <div class="top-center-item">
                            <div>
                                <strong>{{ $center->name }}</strong>
                                <br><small class="text-muted">{{ $center->code }}</small>
                            </div>
                            <div class="text-right">
                                <span class="text-primary">₦{{ number_format($center->journal_entry_lines_sum_debit ?? 0, 0) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No data available</p>
                    @endforelse
                </div>
            </div>

            <!-- By Type -->
            <div class="card-modern">
                <div class="card-header">
                    <h6 class="mb-0"><i class="mdi mdi-chart-pie mr-2"></i>Centers by Type</h6>
                </div>
                <div class="card-body">
                    @foreach($centerTypes as $key => $label)
                        <div class="d-flex justify-content-between mb-2">
                            <span>
                                @php
                                    $colors = [
                                        'revenue' => 'success',
                                        'cost' => 'primary',
                                        'service' => 'info',
                                        'project' => 'warning',
                                    ];
                                @endphp
                                <span class="badge badge-{{ $colors[$key] ?? 'secondary' }}">●</span>
                                {{ $label }}
                            </span>
                            <span>{{ $stats['by_type'][$key] ?? 0 }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(document).ready(function() {
    var table = $('#cost-centers-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('accounting.cost-centers.datatable') }}',
            data: function(d) {
                d.center_type = $('#filter-type').val();
                d.department_id = $('#filter-department').val();
                d.active = $('#filter-active').val();
            }
        },
        columns: [
            { data: 'code', name: 'code' },
            { data: 'name', name: 'name' },
            { data: 'type_badge', name: 'center_type', orderable: false },
            { data: 'department_name', name: 'department_name', orderable: false },
            {
                data: 'ytd_expenses',
                name: 'ytd_expenses',
                orderable: false,
                render: function(data) {
                    return '₦' + parseFloat(data).toLocaleString('en-US', {minimumFractionDigits: 0});
                }
            },
            { data: 'status_badge', name: 'is_active', orderable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'asc']],
        pageLength: 25
    });

    $('#btn-filter').on('click', function() {
        table.draw();
    });

    $('#btn-clear').on('click', function() {
        $('#filter-type, #filter-department, #filter-active').val('');
        table.draw();
    });
});
</script>
@endpush
