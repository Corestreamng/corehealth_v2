@extends('admin.layouts.app')
@section('title', 'Liabilities')
@section('page_name', 'Accounting')
@section('subpage_name', 'Liabilities')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Liabilities', 'url' => route('accounting.liabilities.index'), 'icon' => 'mdi-credit-card-clock']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-primary" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-file-document-multiple mr-1"></i> Active Liabilities</h5>
                    <div class="value text-primary">{{ number_format($stats['active_count'] ?? 0) }}</div>
                    <small class="text-muted">Currently active</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-danger" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-cash-multiple mr-1"></i> Total Outstanding</h5>
                    <div class="value text-danger">₦{{ number_format($stats['total_balance'] ?? 0, 2) }}</div>
                    <small class="text-muted">Total balance</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-warning" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-calendar-month mr-1"></i> Current Portion</h5>
                    <div class="value text-warning">₦{{ number_format($stats['current_portion'] ?? 0, 2) }}</div>
                    <small class="text-muted">Due within 12 months</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-info" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-calendar-clock mr-1"></i> Non-Current</h5>
                    <div class="value text-info">₦{{ number_format($stats['non_current_portion'] ?? 0, 2) }}</div>
                    <small class="text-muted">Long-term portion</small>
                </div>
            </div>
        </div>

        <!-- Second Row Stats -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-success" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-calendar-today mr-1"></i> Due This Month</h5>
                    <div class="value text-success">₦{{ number_format($stats['payments_due_this_month'] ?? 0, 2) }}</div>
                    <small class="text-muted">Scheduled payments</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-danger" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-alert-circle mr-1"></i> Overdue</h5>
                    <div class="value text-danger">₦{{ number_format($stats['overdue_payments'] ?? 0, 2) }}</div>
                    <small class="text-muted">Past due date</small>
                </div>
            </div>
            @foreach($stats['by_type'] ?? [] as $type => $data)
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-secondary" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-tag mr-1"></i> {{ ucfirst(str_replace('_', ' ', $type)) }}</h5>
                    <div class="value text-secondary">{{ $data['count'] ?? 0 }}</div>
                    <small class="text-muted">₦{{ number_format($data['balance'] ?? 0, 2) }}</small>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Main Card -->
        <div class="card-modern card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="mdi mdi-credit-card-clock mr-2"></i>Liabilities Register</h5>
                <div>
                    <a href="{{ route('accounting.liabilities.create') }}" class="btn btn-primary btn-sm">
                        <i class="mdi mdi-plus"></i> New Liability
                    </a>
                    <div class="btn-group ml-2">
                        <a href="{{ route('accounting.liabilities.export.pdf', request()->query()) }}" class="btn btn-danger btn-sm">
                            <i class="mdi mdi-file-pdf"></i> PDF
                        </a>
                        <a href="{{ route('accounting.liabilities.export.excel', request()->query()) }}" class="btn btn-success btn-sm">
                            <i class="mdi mdi-file-excel"></i> Excel
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="status-filter" class="form-label">Status</label>
                        <select id="status-filter" class="form-control form-control-sm filter-control">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="paid_off">Paid Off</option>
                            <option value="restructured">Restructured</option>
                            <option value="defaulted">Defaulted</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="type-filter" class="form-label">Liability Type</label>
                        <select id="type-filter" class="form-control form-control-sm filter-control">
                            <option value="">All Types</option>
                            <option value="loan">Loan</option>
                            <option value="mortgage">Mortgage</option>
                            <option value="overdraft">Overdraft</option>
                            <option value="credit_line">Credit Line</option>
                            <option value="bond">Bond</option>
                            <option value="deferred_revenue">Deferred Revenue</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="creditor-filter" class="form-label">Creditor</label>
                        <input type="text" id="creditor-filter" class="form-control form-control-sm filter-control" placeholder="Search creditor...">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" id="reset-filters" class="btn btn-outline-secondary btn-sm">
                            <i class="mdi mdi-refresh"></i> Reset
                        </button>
                    </div>
                </div>

                <!-- DataTable -->
                <div class="table-responsive">
                    <table id="liabilities-table" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Number</th>
                                <th>Type</th>
                                <th>Creditor</th>
                                <th>Principal</th>
                                <th>Balance</th>
                                <th>Rate</th>
                                <th>Start Date</th>
                                <th>Maturity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
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
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .stat-card h5 {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 10px;
    }
    .stat-card .value {
        font-size: 1.5rem;
        font-weight: 600;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    var table = $('#liabilities-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('accounting.liabilities.datatable') }}",
            data: function(d) {
                d.status = $('#status-filter').val();
                d.liability_type = $('#type-filter').val();
                d.creditor = $('#creditor-filter').val();
            }
        },
        columns: [
            { data: 'liability_number', name: 'liability_number' },
            { data: 'type_badge', name: 'liability_type' },
            { data: 'creditor_name', name: 'creditor_name' },
            { data: 'principal_formatted', name: 'principal_amount' },
            { data: 'balance_formatted', name: 'current_balance' },
            { data: 'interest_rate', name: 'interest_rate', render: d => d + '%' },
            { data: 'start_date_formatted', name: 'start_date' },
            { data: 'maturity_date_formatted', name: 'maturity_date' },
            { data: 'status_badge', name: 'status' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: true,
        language: {
            processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...',
            emptyTable: 'No liabilities found'
        }
    });

    // Filter handlers
    $('.filter-control').on('change keyup', function() {
        table.ajax.reload();
    });

    $('#reset-filters').on('click', function() {
        $('#status-filter, #type-filter, #creditor-filter').val('');
        table.ajax.reload();
    });
});
</script>
@endpush
