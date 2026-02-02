@extends('admin.layouts.app')
@section('title', 'Fund Transactions - ' . $fund->fund_name)
@section('page_name', 'Accounting')
@section('subpage_name', 'Transactions')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Petty Cash', 'url' => route('accounting.petty-cash.index'), 'icon' => 'mdi-cash-register'],
    ['label' => $fund->fund_name, 'url' => route('accounting.petty-cash.funds.show', $fund), 'icon' => 'mdi-wallet'],
    ['label' => 'Transactions', 'url' => '#', 'icon' => 'mdi-history']
]])

<div class="container-fluid">
    <!-- Header -->
    <div class="card card-modern mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4 class="mb-1"><i class="mdi mdi-history mr-2"></i>{{ $fund->fund_name }} - Transactions</h4>
                    <small class="text-muted">Fund Code: {{ $fund->fund_code }}</small>
                </div>
                <div class="col-md-6 text-right">
                    <span class="text-muted mr-3">
                        Current Balance: <strong class="text-success">₦{{ number_format($fund->current_balance, 2) }}</strong>
                    </span>
                    <a href="{{ route('accounting.petty-cash.disbursement.create', $fund) }}" class="btn btn-danger mr-2">
                        <i class="mdi mdi-cash-minus"></i> Disbursement
                    </a>
                    <a href="{{ route('accounting.petty-cash.replenishment.create', $fund) }}" class="btn btn-success">
                        <i class="mdi mdi-cash-plus"></i> Replenish
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card card-modern mb-4">
        <div class="card-body py-3">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <select id="filter_type" class="form-control form-control-sm">
                        <option value="">All Types</option>
                        <option value="disbursement">Disbursement</option>
                        <option value="replenishment">Replenishment</option>
                        <option value="adjustment">Adjustment</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select id="filter_status" class="form-control form-control-sm">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <input type="date" id="filter_from" class="form-control form-control-sm" placeholder="From Date">
                </div>
                <div class="col-md-2 mb-2">
                    <input type="date" id="filter_to" class="form-control form-control-sm" placeholder="To Date">
                </div>
                <div class="col-md-2 mb-2 text-right">
                    <button type="button" class="btn btn-secondary btn-sm" id="resetFilters">
                        <i class="mdi mdi-refresh"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card card-modern">
        <div class="card-body">
            <table class="table table-bordered table-hover" id="transactionsTable">
                <thead class="thead-light">
                    <tr>
                        <th width="100">Date</th>
                        <th width="120">Reference</th>
                        <th width="100">Type</th>
                        <th>Description</th>
                        <th width="120">Expense Account</th>
                        <th width="120" class="text-right">Amount</th>
                        <th width="100">Status</th>
                        <th width="100">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Back Button -->
    <div class="mt-4">
        <a href="{{ route('accounting.petty-cash.funds.show', $fund) }}" class="btn btn-secondary">
            <i class="mdi mdi-arrow-left"></i> Back to Fund Details
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var table = $('#transactionsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("accounting.petty-cash.transactions.datatable", $fund) }}',
            data: function(d) {
                d.status = $('#filter_status').val();
                d.transaction_type = $('#filter_type').val();
                d.from_date = $('#filter_from').val();
                d.to_date = $('#filter_to').val();
            }
        },
        columns: [
            {
                data: 'transaction_date_formatted',
                name: 'transaction_date'
            },
            { data: 'reference_number', name: 'reference_number' },
            {
                data: 'type_badge',
                name: 'transaction_type',
                orderable: false
            },
            { data: 'description', name: 'description' },
            {
                data: 'expense_account',
                name: 'expense_account',
                render: function(data, type, row) {
                    if (row.expense_account) {
                        return row.expense_account.account_number || '-';
                    }
                    return '-';
                },
                orderable: false
            },
            {
                data: 'amount_formatted',
                name: 'amount',
                className: 'text-right'
            },
            {
                data: 'status_badge',
                name: 'status',
                orderable: false
            },
            {
                data: 'id',
                name: 'id',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    var actions = '<div class="btn-group btn-group-sm">';

                    // View details button
                    actions += '<button type="button" class="btn btn-outline-info btn-view" data-id="' + data + '" title="View">';
                    actions += '<i class="mdi mdi-eye"></i>';
                    actions += '</button>';

                    // Approve/Reject for pending items
                    if (row.status === 'pending') {
                        actions += '<button type="button" class="btn btn-outline-success btn-approve" data-id="' + data + '" title="Approve">';
                        actions += '<i class="mdi mdi-check"></i>';
                        actions += '</button>';
                        actions += '<button type="button" class="btn btn-outline-danger btn-reject" data-id="' + data + '" title="Reject">';
                        actions += '<i class="mdi mdi-close"></i>';
                        actions += '</button>';
                    }

                    actions += '</div>';
                    return actions;
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25
    });

    // Filters
    $('#filter_type, #filter_status, #filter_from, #filter_to').on('change', function() {
        table.draw();
    });

    $('#resetFilters').on('click', function() {
        $('#filter_type, #filter_status').val('');
        $('#filter_from, #filter_to').val('');
        table.draw();
    });

    // View transaction details
    $(document).on('click', '.btn-view', function() {
        var id = $(this).data('id');
        // Could open a modal or redirect to a detail page
        toastr.info('Transaction #' + id + ' details coming soon');
    });

    // Approve transaction
    $(document).on('click', '.btn-approve', function() {
        var id = $(this).data('id');
        if (confirm('Are you sure you want to approve this transaction?')) {
            $.ajax({
                url: '/accounting/petty-cash/transactions/' + id + '/approve',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    toastr.success('Transaction approved');
                    table.draw(false);
                },
                error: function(xhr) {
                    toastr.error('Failed to approve transaction');
                }
            });
        }
    });

    // Reject transaction
    $(document).on('click', '.btn-reject', function() {
        var id = $(this).data('id');
        var reason = prompt('Please enter rejection reason:');
        if (reason) {
            $.ajax({
                url: '/accounting/petty-cash/transactions/' + id + '/reject',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', reason: reason },
                success: function(response) {
                    toastr.success('Transaction rejected');
                    table.draw(false);
                },
                error: function(xhr) {
                    toastr.error('Failed to reject transaction');
                }
            });
        }
    });
});
</script>
@endpush
