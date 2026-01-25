@extends('admin.layouts.app')
@section('title', 'View Expense')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'View Expense')

@section('content')
<style>
    .detail-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .detail-card .card-header {
        border-bottom: 1px solid #e9ecef;
        padding: 1rem 1.25rem;
    }
    .detail-row {
        display: flex;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .detail-row:last-child {
        border-bottom: none;
    }
    .detail-label {
        font-weight: 600;
        color: #6c757d;
        width: 180px;
        flex-shrink: 0;
    }
    .detail-value {
        flex-grow: 1;
    }
    .status-pending { color: #ffc107; }
    .status-approved { color: #28a745; }
    .status-rejected { color: #dc3545; }
    .status-voided { color: #6c757d; }
    .amount-display {
        font-size: 1.75rem;
        font-weight: 700;
        color: #28a745;
    }
    .timeline-item {
        position: relative;
        padding-left: 30px;
        padding-bottom: 1rem;
        border-left: 2px solid #e9ecef;
        margin-left: 10px;
    }
    .timeline-item:last-child {
        border-left: none;
        padding-bottom: 0;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 0;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #007bff;
    }
    .timeline-item.approved::before { background: #28a745; }
    .timeline-item.rejected::before { background: #dc3545; }
    .timeline-item.voided::before { background: #6c757d; }
</style>

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Expense Details</h4>
                <p class="text-muted mb-0">{{ $expense->expense_number ?? 'EXP-' . str_pad($expense->id, 6, '0', STR_PAD_LEFT) }}</p>
            </div>
            <div>
                <a href="{{ route('inventory.expenses.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="mdi mdi-arrow-left"></i> Back to List
                </a>
                @if($expense->status === 'pending')
                    @if($expense->category !== 'purchase_order')
                        @can('expenses.edit')
                        <a href="{{ route('inventory.expenses.edit', $expense) }}" class="btn btn-warning btn-sm">
                            <i class="mdi mdi-pencil"></i> Edit
                        </a>
                        @endcan
                    @endif
                    @can('expenses.approve')
                    <button type="button" class="btn btn-success btn-sm" onclick="approveExpense({{ $expense->id }})">
                        <i class="mdi mdi-check"></i> Approve
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="rejectExpense({{ $expense->id }})">
                        <i class="mdi mdi-close"></i> Reject
                    </button>
                    @endcan
                @endif
                @if($expense->status === 'approved')
                    @can('expenses.void')
                    <button type="button" class="btn btn-secondary btn-sm" onclick="voidExpense({{ $expense->id }})">
                        <i class="mdi mdi-cancel"></i> Void
                    </button>
                    @endcan
                @endif
            </div>
        </div>

        <!-- Void Information Alert -->
        @if($expense->status === 'void' && $expense->voided_at)
        <div class="alert alert-warning">
            <h6 class="alert-heading">
                <i class="mdi mdi-alert"></i> Expense Voided
            </h6>
            <p class="mb-2">
                <strong>Voided By:</strong> {{ $expense->voidedBy->name ?? 'Unknown' }}<br>
                <strong>Voided On:</strong> {{ $expense->voided_at->format('M d, Y H:i') }}
            </p>
            @if($expense->void_reason)
            <hr>
            <p class="mb-0">
                <strong>Reason:</strong> {{ $expense->void_reason }}
            </p>
            @endif
        </div>
        @endif

        <!-- Rejection Information Alert -->
        @if($expense->status === 'rejected' && $expense->rejection_reason)
        <div class="alert alert-danger">
            <h6 class="alert-heading">
                <i class="mdi mdi-close-circle"></i> Expense Rejected
            </h6>
            <p class="mb-0">
                <strong>Reason:</strong> {{ $expense->rejection_reason }}
            </p>
        </div>
        @endif

        <div class="row">
            <!-- Main Details -->
            <div class="col-md-8">
                <div class="detail-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Expense Information</h5>
                        @php
                            $statusColors = [
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'voided' => 'secondary',
                            ];
                            $statusColor = $statusColors[$expense->status] ?? 'secondary';
                        @endphp
                        <span class="badge badge-{{ $statusColor }} px-3 py-2">
                            {{ ucfirst($expense->status) }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="detail-row">
                            <div class="detail-label">Description</div>
                            <div class="detail-value">{{ $expense->description }}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Category</div>
                            <div class="detail-value">
                                @php
                                    $categoryColors = [
                                        'purchase_order' => 'primary',
                                        'supplies' => 'info',
                                        'utilities' => 'warning',
                                        'salaries' => 'success',
                                        'maintenance' => 'secondary',
                                        'other' => 'dark',
                                    ];
                                    $categoryColor = $categoryColors[$expense->category] ?? 'secondary';
                                @endphp
                                <span class="badge badge-{{ $categoryColor }}">
                                    {{ ucfirst(str_replace('_', ' ', $expense->category)) }}
                                </span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Expense Date</div>
                            <div class="detail-value">{{ $expense->expense_date->format('F d, Y') }}</div>
                        </div>
                        @if($expense->supplier)
                        <div class="detail-row">
                            <div class="detail-label">Supplier</div>
                            <div class="detail-value">{{ $expense->supplier->company_name }}</div>
                        </div>
                        @endif
                        @if($expense->store)
                        <div class="detail-row">
                            <div class="detail-label">Store</div>
                            <div class="detail-value">{{ $expense->store->store_name }}</div>
                        </div>
                        @endif
                        <div class="detail-row">
                            <div class="detail-label">Payment Method</div>
                            <div class="detail-value">{{ ucfirst(str_replace('_', ' ', $expense->payment_method ?? '-')) }}</div>
                        </div>
                        @if($expense->bank)
                        <div class="detail-row">
                            <div class="detail-label">Bank Account</div>
                            <div class="detail-value">
                                {{ $expense->bank->name }} - {{ $expense->bank->account_number }}
                            </div>
                        </div>
                        @endif
                        @if($expense->payment_reference)
                        <div class="detail-row">
                            <div class="detail-label">Payment Reference</div>
                            <div class="detail-value">{{ $expense->payment_reference }}</div>
                        </div>
                        @endif
                        @if($expense->cheque_number)
                        <div class="detail-row">
                            <div class="detail-label">Cheque Number</div>
                            <div class="detail-value">{{ $expense->cheque_number }}</div>
                        </div>
                        @endif
                        @if($expense->notes)
                        <div class="detail-row">
                            <div class="detail-label">Notes</div>
                            <div class="detail-value">{{ $expense->notes }}</div>
                        </div>
                        @endif
                        @if($expense->reference)
                        <div class="detail-row">
                            <div class="detail-label">Reference</div>
                            <div class="detail-value">
                                @php
                                    $refType = class_basename($expense->reference_type);
                                @endphp
                                @if($refType === 'PurchaseOrder')
                                    <a href="{{ route('inventory.purchase-orders.show', $expense->reference_id) }}">
                                        {{ $expense->reference->po_number ?? 'PO #' . $expense->reference_id }}
                                    </a>
                                @else
                                    {{ $refType }} #{{ $expense->reference_id }}
                                @endif
                            </div>
                        </div>
                        @endif
                        @if($expense->rejection_reason)
                        <div class="detail-row">
                            <div class="detail-label">Rejection Reason</div>
                            <div class="detail-value text-danger">{{ $expense->rejection_reason }}</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Amount Card -->
                <div class="detail-card mb-4">
                    <div class="card-body text-center">
                        <p class="text-muted mb-2">Total Amount</p>
                        <div class="amount-display">₦{{ number_format($expense->amount, 2) }}</div>
                    </div>
                </div>

                <!-- Activity Timeline -->
                <div class="detail-card">
                    <div class="card-header">
                        <h6 class="mb-0">Activity Timeline</h6>
                    </div>
                    <div class="card-body">
                        <div class="timeline-item">
                            <small class="text-muted">{{ $expense->created_at->format('M d, Y H:i') }}</small>
                            <p class="mb-0">Created by <strong>{{ $expense->createdBy->name ?? 'Unknown' }}</strong></p>
                        </div>

                        @if($expense->status === 'approved' && $expense->approved_at)
                        <div class="timeline-item approved">
                            <small class="text-muted">{{ $expense->approved_at->format('M d, Y H:i') }}</small>
                            <p class="mb-0">Approved by <strong>{{ $expense->approvedBy->name ?? 'Unknown' }}</strong></p>
                        </div>
                        @endif

                        @if($expense->status === 'rejected')
                        <div class="timeline-item rejected">
                            <small class="text-muted">{{ $expense->updated_at->format('M d, Y H:i') }}</small>
                            <p class="mb-0">Rejected by <strong>{{ $expense->approvedBy->name ?? 'Unknown' }}</strong></p>
                        </div>
                        @endif

                        @if($expense->status === 'void' && $expense->voided_at)
                        <div class="timeline-item voided">
                            <small class="text-muted">{{ $expense->voided_at->format('M d, Y H:i') }}</small>
                            <p class="mb-0">Voided by <strong>{{ $expense->voidedBy->name ?? 'Unknown' }}</strong></p>
                        </div>
                        @endif
                    </div>
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
                        @if($expense->category === 'purchase_order')
                        <li>Reverse the payment on the associated Purchase Order</li>
                        @endif
                        <li>Keep the record for audit purposes</li>
                    </ul>
                </div>

                <div class="form-group">
                    <label for="void_reason">Void Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="void_reason" name="void_reason" rows="3"
                              placeholder="Please explain why this expense is being voided..."
                              autofocus></textarea>
                    <small class="text-muted">This reason will be recorded in the audit trail.</small>
                </div>

                <input type="hidden" id="void_expense_id" value="{{ $expense->id }}">
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
<script>
function approveExpense(id) {
    if (confirm('Are you sure you want to approve this expense?')) {
        $.post(`/inventory/expenses/${id}/approve`, { _token: '{{ csrf_token() }}' })
            .done(function(response) {
                toastr.success(response.message || 'Expense approved successfully');
                setTimeout(() => location.reload(), 1000);
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve expense');
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
                setTimeout(() => location.reload(), 1000);
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to reject expense');
            });
    }
}

function voidExpense(id) {
    // Reset the modal
    $('#void_reason').val('');
    $('#void_expense_id').val(id);

    // Show the modal
    $('#voidExpenseModal').modal('show');

    // Focus on textarea after modal is shown
    $('#voidExpenseModal').on('shown.bs.modal', function () {
        $('#void_reason').focus();
    });
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
            setTimeout(() => location.reload(), 1000);
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
