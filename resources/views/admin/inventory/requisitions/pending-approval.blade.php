@extends('admin.layouts.app')
@section('title', 'Pending Approval')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Requisitions - Pending Approval')

@push('styles')
<style>
    .requisition-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1rem;
        border-left: 4px solid #ffc107;
    }
    .requisition-header {
        padding: 1rem;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .requisition-body {
        padding: 1rem;
    }
    .requisition-footer {
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 0 0 8px 8px;
    }
    .store-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.8rem;
    }
    .from-store { background: #e3f2fd; color: #1565c0; }
    .to-store { background: #e8f5e9; color: #2e7d32; }
</style>
@endpush

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Requisitions Pending Approval</h4>
            <a href="{{ route('inventory.requisitions.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="mdi mdi-arrow-left"></i> All Requisitions
            </a>
        </div>

        @forelse($requisitions as $requisition)
        <div class="requisition-card">
            <div class="requisition-header">
                <div>
                    <h6 class="mb-1">{{ $requisition->requisition_number }}</h6>
                    <small class="text-muted">Requested {{ $requisition->created_at->diffForHumans() }} by {{ $requisition->requester->name ?? 'Unknown' }}</small>
                </div>
                <div>
                    <span class="store-badge from-store">From: {{ $requisition->fromStore->store_name ?? 'N/A' }}</span>
                    <i class="mdi mdi-arrow-right mx-2"></i>
                    <span class="store-badge to-store">To: {{ $requisition->toStore->store_name ?? 'N/A' }}</span>
                </div>
            </div>

            <div class="requisition-body">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Requested</th>
                            <th class="text-center">Available</th>
                            <th class="text-center">Approve Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requisition->items as $item)
                        <tr>
                            <td>{{ $item->product->product_name ?? 'Unknown' }}</td>
                            <td class="text-center">{{ $item->requested_qty }}</td>
                            <td class="text-center">
                                @php
                                    $available = \App\Models\StockBatch::where('product_id', $item->product_id)
                                        ->where('store_id', $requisition->from_store_id)
                                        ->active()
                                        ->sum('current_qty');
                                @endphp
                                <span class="{{ $available < $item->requested_qty ? 'text-danger' : 'text-success' }}">
                                    {{ $available }}
                                </span>
                            </td>
                            <td class="text-center">
                                <input type="number"
                                       class="form-control form-control-sm approve-qty"
                                       style="width: 80px; display: inline-block;"
                                       data-item-id="{{ $item->id }}"
                                       min="0"
                                       max="{{ $item->requested_qty }}"
                                       value="{{ min($item->requested_qty, $available) }}">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                @if($requisition->request_notes)
                <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted">Notes:</small>
                    <p class="mb-0">{{ $requisition->request_notes }}</p>
                </div>
                @endif
            </div>

            <div class="requisition-footer d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge badge-warning">Pending Approval</span>
                </div>
                <div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="rejectRequisition({{ $requisition->id }})">
                        <i class="mdi mdi-close"></i> Reject
                    </button>
                    <button type="button" class="btn btn-success btn-sm" onclick="approveRequisition({{ $requisition->id }}, this)">
                        <i class="mdi mdi-check"></i> Approve
                    </button>
                </div>
            </div>
        </div>
        @empty
        <div class="alert alert-info">
            <i class="mdi mdi-information"></i> No requisitions pending approval.
        </div>
        @endforelse
    </div>
</div>
@endsection

@section('scripts')
<script>
function approveRequisition(id, btn) {
    const card = $(btn).closest('.requisition-card');
    const approvedQtys = {};

    card.find('.approve-qty').each(function() {
        approvedQtys[$(this).data('item-id')] = parseInt($(this).val()) || 0;
    });

    $(btn).prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

    $.post(`/inventory/requisitions/${id}/approve`, {
        _token: '{{ csrf_token() }}',
        approved_qtys: approvedQtys
    })
    .done(function(response) {
        toastr.success(response.message || 'Requisition approved');
        card.fadeOut(300, function() { $(this).remove(); });
    })
    .fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Failed to approve');
        $(btn).prop('disabled', false).html('<i class="mdi mdi-check"></i> Approve');
    });
}

function rejectRequisition(id) {
    var reason = prompt('Enter rejection reason:');
    if (reason) {
        $.post(`/inventory/requisitions/${id}/reject`, {
            _token: '{{ csrf_token() }}',
            reason: reason
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
</script>
@endsection
