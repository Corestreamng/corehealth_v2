@extends('admin.layouts.app')
@section('title', 'Pending Fulfillment')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Requisitions - Pending Fulfillment')

@section('content')
<style>
    .requisition-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1rem;
        border-left: 4px solid #17a2b8;
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
    .fulfillment-info {
        background: #e3f2fd;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        margin-bottom: 1rem;
    }
</style>
<div id="content-wrapper">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Requisitions Pending Fulfillment</h4>
                <p class="text-muted mb-0">{{ $store->store_name ?? 'All Stores' }}</p>
            </div>
            <a href="{{ route('inventory.requisitions.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="mdi mdi-arrow-left"></i> All Requisitions
            </a>
        </div>

        <div class="fulfillment-info">
            <i class="mdi mdi-information"></i>
            These requisitions have been approved and are waiting for stock to be transferred from <strong>{{ $store->store_name }}</strong>.
        </div>

        @forelse($requisitions as $requisition)
        <div class="requisition-card">
            <div class="requisition-header">
                <div>
                    <h6 class="mb-1">{{ $requisition->requisition_number }}</h6>
                    <small class="text-muted">
                        Approved {{ $requisition->approved_at?->diffForHumans() ?? 'N/A' }}
                        @if($requisition->approver)
                            by {{ $requisition->approver->name }}
                        @endif
                    </small>
                </div>
                <div>
                    <span class="badge badge-info">To: {{ $requisition->toStore->store_name ?? 'N/A' }}</span>
                </div>
            </div>

            <div class="requisition-body">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Approved</th>
                            <th class="text-center">Fulfilled</th>
                            <th class="text-center">Remaining</th>
                            <th class="text-center">Fulfill Now</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requisition->items as $item)
                        @php
                            $remaining = $item->approved_qty - ($item->fulfilled_qty ?? 0);
                        @endphp
                        @if($remaining > 0)
                        <tr>
                            <td>{{ $item->product->product_name ?? 'Unknown' }}</td>
                            <td class="text-center">{{ $item->approved_qty }}</td>
                            <td class="text-center">{{ $item->fulfilled_qty ?? 0 }}</td>
                            <td class="text-center text-warning">{{ $remaining }}</td>
                            <td class="text-center">
                                <input type="number"
                                       class="form-control form-control-sm fulfill-qty"
                                       style="width: 80px; display: inline-block;"
                                       data-item-id="{{ $item->id }}"
                                       min="0"
                                       max="{{ $remaining }}"
                                       value="{{ $remaining }}">
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>

                @if($requisition->approval_notes)
                <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted">Approval Notes:</small>
                    <p class="mb-0">{{ $requisition->approval_notes }}</p>
                </div>
                @endif
            </div>

            <div class="requisition-footer d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge badge-info">Approved - Awaiting Fulfillment</span>
                </div>
                <div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="fulfillRequisition({{ $requisition->id }}, this)">
                        <i class="mdi mdi-truck-delivery"></i> Fulfill & Transfer
                    </button>
                </div>
            </div>
        </div>
        @empty
        <div class="alert alert-info">
            <i class="mdi mdi-information"></i> No requisitions pending fulfillment for {{ $store->store_name }}.
        </div>
        @endforelse
    </div>
</div>
@endsection

@section('scripts')
<script>
function fulfillRequisition(id, btn) {
    const card = $(btn).closest('.requisition-card');
    const fulfillments = [];

    card.find('.fulfill-qty').each(function() {
        const qty = parseInt($(this).val()) || 0;
        if (qty > 0) {
            fulfillments.push({
                item_id: $(this).data('item-id'),
                qty: qty
            });
        }
    });

    if (fulfillments.length === 0) {
        toastr.warning('Please enter quantities to fulfill');
        return;
    }

    $(btn).prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

    $.post(`/inventory/requisitions/${id}/fulfill`, {
        _token: '{{ csrf_token() }}',
        fulfillments: fulfillments
    })
    .done(function(response) {
        toastr.success(response.message || 'Requisition fulfilled successfully');
        setTimeout(() => location.reload(), 1000);
    })
    .fail(function(xhr) {
        toastr.error(xhr.responseJSON?.message || 'Failed to fulfill requisition');
        $(btn).prop('disabled', false).html('<i class="mdi mdi-truck-delivery"></i> Fulfill & Transfer');
    });
}
</script>
@endsection
