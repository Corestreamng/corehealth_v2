@extends('admin.layouts.app')
@section('title', 'Petty Cash Funds')
@section('page_name', 'Accounting')
@section('subpage_name', 'Petty Cash Funds')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Petty Cash', 'url' => route('accounting.petty-cash.index'), 'icon' => 'mdi-cash-register'],
    ['label' => 'Funds', 'url' => route('accounting.petty-cash.funds.index'), 'icon' => 'mdi-wallet']
]])

<div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="mdi mdi-wallet mr-2"></i>Petty Cash Funds</h4>
                <p class="text-muted mb-0">Manage all petty cash funds and custodians</p>
            </div>
            <a href="{{ route('accounting.petty-cash.funds.create') }}" class="btn btn-primary">
                <i class="mdi mdi-plus"></i> New Fund
            </a>
        </div>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card border-left border-primary" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-wallet mr-1"></i> Total Funds</h5>
                    <div class="value text-primary">{{ number_format($stats['total_funds']) }}</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card border-left border-success" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-check-circle mr-1"></i> Active Funds</h5>
                    <div class="value text-success">{{ number_format($stats['active_funds']) }}</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card border-left border-info" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-cash mr-1"></i> Total Balance</h5>
                    <div class="value text-info">₦{{ number_format($stats['total_balance'], 2) }}</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card border-left border-secondary" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-scale-balance mr-1"></i> Total Limit</h5>
                    <div class="value text-secondary">₦{{ number_format($stats['total_limit'], 2) }}</div>
                </div>
            </div>
        </div>

        <!-- Funds Table -->
        <div class="card-modern card-modern">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="mdi mdi-format-list-bulleted mr-2"></i>All Funds</h5>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-control form-control-sm filter-control" id="status-filter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Department</label>
                        <select class="form-control form-control-sm filter-control" id="department-filter">
                            <option value="">All Departments</option>
                            @foreach(\App\Models\Department::where('is_active', true)->get() as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="funds-table">
                        <thead>
                            <tr>
                                <th>Fund Code</th>
                                <th>Fund Name</th>
                                <th>Custodian</th>
                                <th>Department</th>
                                <th class="text-right">Balance</th>
                                <th class="text-right">Limit</th>
                                <th style="width: 150px">Utilization</th>
                                <th>Transactions</th>
                                <th>Status</th>
                                <th style="width: 180px">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
</div>
@endsection

@push('styles')
<style>
.stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.stat-card h5 {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 8px;
}
.stat-card .value {
    font-size: 1.75rem;
    font-weight: 600;
}
.progress {
    border-radius: 10px;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    var table = $('#funds-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('accounting.petty-cash.funds.datatable') }}",
            data: function(d) {
                d.status = $('#status-filter').val();
                d.department_id = $('#department-filter').val();
            }
        },
        columns: [
            { data: 'fund_code', name: 'fund_code' },
            { data: 'fund_name', name: 'fund_name' },
            { data: 'custodian_name', name: 'custodian.name' },
            { data: 'department_name', name: 'department.name' },
            { data: 'balance_formatted', name: 'current_balance', className: 'text-right' },
            { data: 'limit_formatted', name: 'fund_limit', className: 'text-right' },
            { data: 'utilization', name: 'utilization', orderable: false, searchable: false },
            { data: 'transactions_count', name: 'transactions_count' },
            { data: 'status_badge', name: 'status' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'asc']],
        pageLength: 15
    });

    $('.filter-control').on('change', function() {
        table.ajax.reload();
    });
});
</script>
@endpush
