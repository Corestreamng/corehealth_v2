@extends('admin.layouts.app')
@section('title', 'Expenses')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Expenses')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    .stat-card {
        background: #fff;
        border-radius: 8px;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1rem;
    }
    .stat-card h5 {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 0.5rem;
    }
    .stat-card .value {
        font-size: 1.5rem;
        font-weight: 600;
    }
</style>
@endpush

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <h5>Total Expenses</h5>
                    <div class="value text-primary">{{ $stats['total'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h5>Pending Approval</h5>
                    <div class="value text-warning">{{ $stats['pending'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h5>Approved This Month</h5>
                    <div class="value text-success">₦{{ number_format($stats['approved_this_month'] ?? 0, 2) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h5>Top Category</h5>
                    @php
                        $topCategory = collect($stats['by_category'] ?? [])->sortDesc()->keys()->first();
                    @endphp
                    <div class="value text-info">{{ ucfirst(str_replace('_', ' ', $topCategory ?? 'N/A')) }}</div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="card-modern">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Expenses</h3>
                    <div>
                        @can('expenses.create')
                        <a href="{{ route('inventory.expenses.create') }}" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-plus"></i> New Expense
                        </a>
                        @endcan
                        <a href="{{ route('inventory.expenses.summary-report') }}" class="btn btn-outline-info btn-sm">
                            <i class="mdi mdi-chart-bar"></i> Reports
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-2">
                        <select id="status-filter" class="form-control form-control-sm">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="voided">Voided</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="category-filter" class="form-control form-control-sm">
                            <option value="">All Categories</option>
                            @foreach($categories as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" id="date-from" class="form-control form-control-sm" placeholder="From Date">
                    </div>
                    <div class="col-md-2">
                        <input type="date" id="date-to" class="form-control form-control-sm" placeholder="To Date">
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="expense-table" class="table table-sm table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th class="text-right">Amount</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(function() {
    var table = $('#expense-table').DataTable({
        dom: 'Bfrtip',
        iDisplayLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print'],
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('inventory.expenses.index') }}",
            type: "GET",
            data: function(d) {
                d.status = $('#status-filter').val();
                d.category = $('#category-filter').val();
                d.date_from = $('#date-from').val();
                d.date_to = $('#date-to').val();
            }
        },
        columns: [
            { data: "expense_date_formatted", name: "expense_date" },
            { data: "description", name: "description" },
            { data: "category_badge", name: "category" },
            { data: "amount_formatted", name: "amount", className: "text-right" },
            { data: "reference_info", name: "reference_info", orderable: false },
            { data: "status_badge", name: "status" },
            { data: "created_by_name", name: "createdBy.name" },
            { data: "actions", name: "actions", orderable: false, searchable: false }
        ],
        order: [[0, 'desc']]
    });

    // Filters
    $('#status-filter, #category-filter, #date-from, #date-to').on('change', function() {
        table.ajax.reload();
    });
});

function approveExpense(id) {
    if (confirm('Approve this expense?')) {
        $.post(`/inventory/expenses/${id}/approve`, { _token: '{{ csrf_token() }}' })
            .done(function(response) {
                toastr.success(response.message || 'Expense approved');
                $('#expense-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve');
            });
    }
}

function rejectExpense(id) {
    var reason = prompt('Please enter rejection reason:');
    if (reason) {
        $.post(`/inventory/expenses/${id}/reject`, {
            _token: '{{ csrf_token() }}',
            rejection_reason: reason
        })
            .done(function(response) {
                toastr.success(response.message || 'Expense rejected');
                $('#expense-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to reject');
            });
    }
}

function voidExpense(id) {
    var reason = prompt('Please enter void reason:');
    if (reason) {
        $.post(`/inventory/expenses/${id}/void`, {
            _token: '{{ csrf_token() }}',
            reason: reason
        })
            .done(function(response) {
                toastr.success(response.message || 'Expense voided');
                $('#expense-table').DataTable().ajax.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to void');
            });
    }
}
</script>
@endsection
