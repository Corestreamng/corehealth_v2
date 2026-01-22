@extends('admin.layouts.app')
@section('title', 'Requisition - ' . $requisition->requisition_number)
@section('page_name', 'Inventory Management')
@section('subpage_name', 'View Requisition')

@section('content')
<style>
    .req-header {
        background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
        color: white;
        padding: 2rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    .status-badge {
        font-size: 0.85rem;
        padding: 0.5em 1em;
        border-radius: 20px;
    }
    .status-pending { background-color: rgba(255,255,255,0.2); }
    .status-approved { background-color: #17a2b8; }
    .status-partial_fulfilled { background-color: #ffc107; color: #212529; }
    .status-fulfilled { background-color: #28a745; }
    .status-rejected { background-color: #dc3545; }
    .status-cancelled { background-color: #6c757d; }

    .priority-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
    }
    .priority-low { background: #e9ecef; color: #495057; }
    .priority-normal { background: #cfe2ff; color: #084298; }
    .priority-high { background: #fff3cd; color: #664d03; }
    .priority-urgent { background: #f8d7da; color: #842029; }

    .detail-card {
        background: #fff;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .detail-card h5 {
        border-bottom: 2px solid #6f42c1;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
    }
    .detail-label {
        font-weight: 500;
        color: #6c757d;
        font-size: 0.85rem;
    }
    .detail-value {
        font-weight: 600;
        color: #212529;
    }
    .items-table th {
        background: #f8f9fa;
    }
    .transfer-flow {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        padding: 1rem;
        background: rgba(255,255,255,0.1);
        border-radius: 8px;
        margin-top: 1rem;
    }
    .store-box {
        background: rgba(255,255,255,0.2);
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
    }
    .fulfill-input {
        width: 80px;
        text-align: center;
    }
</style>
@endpush

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="req-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <h2 class="mb-0">{{ $requisition->requisition_number }}</h2>
                        <span class="status-badge status-{{ $requisition->status }}">
                            {{ ucfirst(str_replace('_', ' ', $requisition->status)) }}
                        </span>
                        <span class="priority-badge priority-{{ $requisition->priority }}">
                            {{ ucfirst($requisition->priority) }} Priority
                        </span>
                    </div>
                    <div class="transfer-flow">
                        <div class="store-box">
                            <small class="d-block opacity-75">From</small>
                            <strong>{{ $requisition->fromStore->store_name }}</strong>
                        </div>
                        <i class="mdi mdi-arrow-right-bold" style="font-size: 1.5rem;"></i>
                        <div class="store-box">
                            <small class="d-block opacity-75">To</small>
                            <strong>{{ $requisition->toStore->store_name }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-right">
                    <div class="mb-1">Request Date: {{ $requisition->request_date->format('M d, Y') }}</div>
                    @if($requisition->required_by_date)
                    <div>Required By: {{ $requisition->required_by_date->format('M d, Y') }}</div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-4">
            <a href="{{ route('inventory.requisitions.index') }}" class="btn btn-secondary btn-sm">
                <i class="mdi mdi-arrow-left"></i> Back
            </a>

            @if($requisition->status === 'pending')
                @can('requisitions.approve')
                <button type="button" class="btn btn-success btn-sm" onclick="approveRequisition()">
                    <i class="mdi mdi-check"></i> Approve
                </button>
                <button type="button" class="btn btn-danger btn-sm" onclick="rejectRequisition()">
                    <i class="mdi mdi-close"></i> Reject
                </button>
                @endcan
                @if(auth()->id() == $requisition->requested_by)
                <button type="button" class="btn btn-warning btn-sm" onclick="cancelRequisition()">
                    <i class="mdi mdi-cancel"></i> Cancel
                </button>
                @endif
            @endif

            @if(in_array($requisition->status, ['approved', 'partial_fulfilled']))
                @can('requisitions.fulfill')
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#fulfillModal">
                    <i class="mdi mdi-package"></i> Fulfill Items
                </button>
                @endcan
            @endif

            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                <i class="mdi mdi-printer"></i> Print
            </button>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-md-8">
                <!-- Requested Items -->
                <div class="detail-card">
                    <h5><i class="mdi mdi-package-variant"></i> Requested Items</h5>
                    <div class="table-responsive">
                        <table class="table table-sm items-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th class="text-center">Requested</th>
                                    <th class="text-center">Fulfilled</th>
                                    <th class="text-center">Pending</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($requisition->items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <strong>{{ $item->product->product_name }}</strong>
                                        <br><small class="text-muted">{{ $item->product->product_code }}</small>
                                    </td>
                                    <td class="text-center">{{ $item->quantity_requested }}</td>
                                    <td class="text-center">
                                        <span class="{{ $item->quantity_fulfilled >= $item->quantity_requested ? 'text-success font-weight-bold' : '' }}">
                                            {{ $item->quantity_fulfilled }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @php $pending = $item->quantity_requested - $item->quantity_fulfilled; @endphp
                                        <span class="{{ $pending > 0 ? 'text-warning' : 'text-success' }}">
                                            {{ max(0, $pending) }}
                                        </span>
                                    </td>
                                    <td>{{ $item->notes ?: '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($requisition->reason)
                <div class="detail-card">
                    <h5><i class="mdi mdi-comment-text"></i> Reason</h5>
                    <p class="mb-0">{{ $requisition->reason }}</p>
                </div>
                @endif

                @if($requisition->notes)
                <div class="detail-card">
                    <h5><i class="mdi mdi-note-text"></i> Notes</h5>
                    <p class="mb-0">{{ $requisition->notes }}</p>
                </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Details -->
                <div class="detail-card">
                    <h5><i class="mdi mdi-information"></i> Details</h5>
                    <div class="mb-3">
                        <div class="detail-label">Requested By</div>
                        <div class="detail-value">{{ $requisition->requestedBy->name ?? 'N/A' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="detail-label">Created At</div>
                        <div class="detail-value">{{ $requisition->created_at->format('M d, Y H:i') }}</div>
                    </div>
                    @if($requisition->approved_at)
                    <div class="mb-3">
                        <div class="detail-label">Approved By</div>
                        <div class="detail-value">{{ $requisition->approvedBy->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $requisition->approved_at->format('M d, Y H:i') }}</small>
                    </div>
                    @endif
                    @if($requisition->fulfilled_at)
                    <div class="mb-3">
                        <div class="detail-label">Fulfilled By</div>
                        <div class="detail-value">{{ $requisition->fulfilledBy->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $requisition->fulfilled_at->format('M d, Y H:i') }}</small>
                    </div>
                    @endif
                    @if($requisition->rejected_at)
                    <div class="mb-3">
                        <div class="detail-label text-danger">Rejected</div>
                        <div class="detail-value">{{ $requisition->rejected_at->format('M d, Y H:i') }}</div>
                        @if($requisition->rejection_reason)
                        <small class="text-muted">Reason: {{ $requisition->rejection_reason }}</small>
                        @endif
                    </div>
                    @endif
                </div>

                <!-- Stock Availability -->
                @if(in_array($requisition->status, ['approved', 'partial_fulfilled']))
                <div class="detail-card">
                    <h5><i class="mdi mdi-cube"></i> Source Stock Availability</h5>
                    @foreach($requisition->items as $item)
                    @php
                        $pending = $item->quantity_requested - $item->quantity_fulfilled;
                        $available = $item->product->getAvailableQtyInStore($requisition->from_store_id);
                    @endphp
                    @if($pending > 0)
                    <div class="d-flex justify-content-between align-items-center mb-2 py-2 border-bottom">
                        <div>
                            <strong>{{ $item->product->product_name }}</strong>
                            <br><small>Need: {{ $pending }}</small>
                        </div>
                        <div class="text-right">
                            <span class="badge {{ $available >= $pending ? 'badge-success' : 'badge-warning' }}">
                                {{ $available }} available
                            </span>
                        </div>
                    </div>
                    @endif
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Fulfill Modal -->
@if(in_array($requisition->status, ['approved', 'partial_fulfilled']))
<div class="modal fade" id="fulfillModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fulfill Requisition Items</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="fulfill-form" method="POST" action="{{ route('inventory.requisitions.fulfill', $requisition) }}">
                @csrf
                <div class="modal-body">
                    <p class="text-muted">Select batches and quantities to fulfill from <strong>{{ $requisition->fromStore->store_name }}</strong></p>

                    @foreach($requisition->items as $item)
                    @php
                        $pending = $item->quantity_requested - $item->quantity_fulfilled;
                    @endphp
                    @if($pending > 0)
                    <div class="card-modern mb-3">
                        <div class="card-header bg-light">
                            <strong>{{ $item->product->product_name }}</strong>
                            <span class="float-right">Pending: {{ $pending }}</span>
                        </div>
                        <div class="card-body">
                            <input type="hidden" name="items[{{ $item->id }}][requisition_item_id]" value="{{ $item->id }}">
                            <input type="hidden" name="items[{{ $item->id }}][product_id]" value="{{ $item->product_id }}">

                            <div class="form-group">
                                <label>Select Batch</label>
                                <select name="items[{{ $item->id }}][batch_id]" class="form-control batch-select"
                                        data-product="{{ $item->product_id }}" data-item="{{ $item->id }}">
                                    <option value="">Loading batches...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Quantity to Transfer</label>
                                <input type="number" name="items[{{ $item->id }}][quantity]"
                                       class="form-control fulfill-qty" min="0" max="{{ $pending }}" value="0">
                                <small class="text-muted">Max: {{ $pending }}</small>
                            </div>
                        </div>
                    </div>
                    @endif
                    @endforeach
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Process Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
@if(in_array($requisition->status, ['approved', 'partial_fulfilled']))
$(function() {
    // Load batches when modal opens
    $('#fulfillModal').on('show.bs.modal', function() {
        $('.batch-select').each(function() {
            var select = $(this);
            var productId = select.data('product');

            $.get('{{ route("inventory.requisitions.available-batches", $requisition) }}', {
                product_id: productId
            }).done(function(response) {
                select.empty();
                select.append('<option value="">Select batch...</option>');
                response.batches.forEach(function(batch) {
                    select.append(
                        '<option value="' + batch.id + '" data-available="' + batch.current_quantity + '">' +
                        batch.batch_number + ' - Qty: ' + batch.current_quantity +
                        (batch.expiry_date ? ' - Exp: ' + batch.expiry_date : '') +
                        '</option>'
                    );
                });
            });
        });
    });

    // Update max quantity when batch is selected
    $('.batch-select').on('change', function() {
        var selected = $(this).find(':selected');
        var available = parseInt(selected.data('available')) || 0;
        var qtyInput = $(this).closest('.card-body').find('.fulfill-qty');
        var maxPending = parseInt(qtyInput.attr('max'));
        qtyInput.attr('data-batch-max', Math.min(available, maxPending));
    });

    // Validate quantity
    $('.fulfill-qty').on('input', function() {
        var batchMax = parseInt($(this).data('batch-max')) || parseInt($(this).attr('max'));
        if (parseInt($(this).val()) > batchMax) {
            $(this).val(batchMax);
            toastr.warning('Quantity limited to available stock');
        }
    });
});
@endif

function approveRequisition() {
    if (confirm('Approve this requisition?')) {
        $.post('{{ route("inventory.requisitions.approve", $requisition) }}', { _token: '{{ csrf_token() }}' })
            .done(function(response) {
                toastr.success(response.message || 'Requisition approved');
                location.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve');
            });
    }
}

function rejectRequisition() {
    var reason = prompt('Please enter rejection reason:');
    if (reason) {
        $.post('{{ route("inventory.requisitions.reject", $requisition) }}', {
            _token: '{{ csrf_token() }}',
            rejection_reason: reason
        })
            .done(function(response) {
                toastr.success(response.message || 'Requisition rejected');
                location.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to reject');
            });
    }
}

function cancelRequisition() {
    if (confirm('Cancel this requisition?')) {
        $.post('{{ route("inventory.requisitions.cancel", $requisition) }}', { _token: '{{ csrf_token() }}' })
            .done(function(response) {
                toastr.success(response.message || 'Requisition cancelled');
                location.reload();
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to cancel');
            });
    }
}
</script>
@endsection
