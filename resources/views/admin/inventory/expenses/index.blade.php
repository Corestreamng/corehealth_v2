@extends('admin.layouts.app')
@section('title', 'Expenses')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Expenses')

@section('content')
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
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Summary Cards Row 1 -->
        <div class="row mb-3">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-primary" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-file-document-outline mr-1"></i> Total Expenses</h5>
                    <div class="value text-primary">{{ $stats['total'] ?? 0 }}</div>
                    <small class="text-muted">All time records</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-warning" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-clock-outline mr-1"></i> Pending Approval</h5>
                    <div class="value text-warning">{{ $stats['pending'] ?? 0 }}</div>
                    <small class="text-muted">₦{{ number_format($stats['pending_amount'] ?? 0, 2) }} total</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-success" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-check-circle mr-1"></i> Approved</h5>
                    <div class="value text-success">{{ $stats['approved'] ?? 0 }}</div>
                    <small class="text-muted">Total approved</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-secondary" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-cancel mr-1"></i> Voided</h5>
                    <div class="value text-secondary">{{ $stats['voided'] ?? 0 }}</div>
                    <small class="text-muted">Cancelled records</small>
                </div>
            </div>
        </div>

        <!-- Summary Cards Row 2 -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-info" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-calendar-today mr-1"></i> Today's Expenses</h5>
                    <div class="value text-info">₦{{ number_format($stats['today_expenses'] ?? 0, 2) }}</div>
                    <small class="text-muted">{{ now()->format('d M, Y') }}</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-success" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-currency-ngn mr-1"></i> This Month</h5>
                    <div class="value text-success">₦{{ number_format($stats['approved_this_month'] ?? 0, 2) }}</div>
                    @if(($stats['mom_change'] ?? 0) != 0)
                        <small class="{{ ($stats['mom_change'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">
                            <i class="mdi mdi-arrow-{{ ($stats['mom_change'] ?? 0) > 0 ? 'up' : 'down' }}"></i>
                            {{ abs($stats['mom_change'] ?? 0) }}% vs last month
                        </small>
                    @else
                        <small class="text-muted">{{ now()->format('F Y') }}</small>
                    @endif
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-dark" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-truck-delivery mr-1"></i> PO Payments</h5>
                    <div class="value" style="color: #6f42c1;">₦{{ number_format($stats['po_payments_this_month'] ?? 0, 2) }}</div>
                    <small class="text-muted">This month</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card border-left border-info" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-tag mr-1"></i> Top Category</h5>
                    @php
                        $topCategory = collect($stats['by_category'] ?? [])->sortDesc()->keys()->first();
                        $topAmount = collect($stats['by_category'] ?? [])->sortDesc()->first();
                    @endphp
                    <div class="value text-info">{{ ucfirst(str_replace('_', ' ', $topCategory ?? 'N/A')) }}</div>
                    @if($topAmount)
                        <small class="text-muted">₦{{ number_format($topAmount, 2) }}</small>
                    @endif
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

<!-- Void Expense Modal -->
<div class="modal fade" id="voidExpenseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="mdi mdi-alert"></i> Void Expense
                </h5>
                <button type="button" class="close"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>Warning!</strong> Voiding this expense will:
                    <ul class="mb-0 mt-2">
                        <li>Mark the expense as void</li>
                        <li id="po-payment-warning" style="display: none;">Reverse the payment on the associated Purchase Order</li>
                        <li>Keep the record for audit purposes</li>
                    </ul>
                </div>

                <div class="form-group">
                    <label for="void_reason">Void Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="void_reason" rows="3"
                              placeholder="Please explain why this expense is being voided..."
                              required></textarea>
                    <small class="text-muted">This reason will be recorded in the audit trail.</small>
                </div>

                <input type="hidden" id="void_expense_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">
                    <i class="mdi mdi-close"></i> Cancel
                </button>
                <button type="button" class="btn btn-warning" onclick="confirmVoid()">
                    <i class="mdi mdi-check"></i> Void Expense
                </button>
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
    // Reset the modal
    $('#void_reason').val('');
    $('#void_expense_id').val(id);
    $('#po-payment-warning').hide();

    // Check if this is a PO payment expense to show additional warning
    $.get(`/inventory/expenses/${id}`)
        .done(function(response) {
            if (response.expense && response.expense.category === 'purchase_order') {
                $('#po-payment-warning').show();
            }
        });

    // Show the modal
    $('#voidExpenseModal').modal('show');
}

function confirmVoid() {
    const reason = $('#void_reason').val().trim();
    const expenseId = $('#void_expense_id').val();

    if (!reason) {
        toastr.error('Please provide a void reason');
        return;
    }

    if (reason.length < 10) {
        toastr.error('Void reason must be at least 10 characters');
        return;
    }

    // Disable button to prevent double submission
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Voiding...';

    $.post(`/inventory/expenses/${expenseId}/void`, {
        _token: '{{ csrf_token() }}',
        reason: reason
    })
        .done(function(response) {
            toastr.success(response.message || 'Expense voided successfully');
            $('#voidExpenseModal').modal('hide');
            $('#expense-table').DataTable().ajax.reload();
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to void expense');
        })
        .always(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="mdi mdi-check"></i> Void Expense';
        });
}
</script>
@endsection
