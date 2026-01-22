@extends('admin.layouts.app')
@section('title', 'Receive Items - ' . $purchaseOrder->po_number)
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Receive Purchase Order')

@push('styles')
<style>
    .receive-header {
        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    .receive-item {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.2s;
    }
    .receive-item:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .receive-item.fully-received {
        background: #d4edda;
        border-color: #c3e6cb;
    }
    .product-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .product-icon {
        width: 50px;
        height: 50px;
        background: #e9ecef;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: #6c757d;
    }
    .qty-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.85rem;
    }
    .qty-ordered { background: #e9ecef; }
    .qty-received { background: #d4edda; color: #155724; }
    .qty-pending { background: #fff3cd; color: #856404; }

    .batch-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 1rem;
        margin-top: 1rem;
    }
    .form-section {
        background: #fff;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
</style>
@endpush

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="receive-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-1">Receive Items for {{ $purchaseOrder->po_number }}</h4>
                    <p class="mb-0">{{ $purchaseOrder->supplier_name }}</p>
                </div>
                <div class="col-md-4 text-right">
                    <div class="mb-1">Store: <strong>{{ $purchaseOrder->store->store_name }}</strong></div>
                    <div>Order Date: {{ $purchaseOrder->order_date->format('M d, Y') }}</div>
                </div>
            </div>
        </div>

        <form id="receive-form" method="POST" action="{{ route('inventory.purchase-orders.receive.process', $purchaseOrder) }}">
            @csrf

            <!-- Items to Receive -->
            <div class="form-section">
                <h5><i class="mdi mdi-package-down"></i> Items to Receive</h5>

                @foreach($purchaseOrder->items as $item)
                @php
                    $pending = $item->quantity_ordered - $item->quantity_received;
                    $fullyReceived = $pending <= 0;
                @endphp
                <div class="receive-item {{ $fullyReceived ? 'fully-received' : '' }}">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="product-info">
                                <div class="product-icon">
                                    <i class="mdi mdi-pill"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">{{ $item->product->product_name }}</h6>
                                    <small class="text-muted">{{ $item->product->product_code }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="d-flex gap-3 flex-wrap">
                                <div>
                                    <small class="text-muted d-block">Ordered</small>
                                    <span class="qty-badge qty-ordered">{{ $item->quantity_ordered }}</span>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Already Received</small>
                                    <span class="qty-badge qty-received">{{ $item->quantity_received }}</span>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Pending</small>
                                    <span class="qty-badge qty-pending">{{ max(0, $pending) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if(!$fullyReceived)
                    <div class="batch-section">
                        <input type="hidden" name="items[{{ $item->id }}][purchase_order_item_id]" value="{{ $item->id }}">
                        <input type="hidden" name="items[{{ $item->id }}][product_id]" value="{{ $item->product_id }}">

                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Receive Qty <span class="text-danger">*</span></label>
                                    <input type="number"
                                           name="items[{{ $item->id }}][quantity]"
                                           class="form-control qty-receive-input"
                                           min="0"
                                           max="{{ $pending }}"
                                           value="{{ $pending }}"
                                           data-max="{{ $pending }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Batch Number <span class="text-danger">*</span></label>
                                    <input type="text"
                                           name="items[{{ $item->id }}][batch_number]"
                                           class="form-control"
                                           placeholder="e.g., BTH001"
                                           value="{{ old('items.'.$item->id.'.batch_number') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Expiry Date</label>
                                    <input type="date"
                                           name="items[{{ $item->id }}][expiry_date]"
                                           class="form-control"
                                           value="{{ old('items.'.$item->id.'.expiry_date') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Manufacture Date</label>
                                    <input type="date"
                                           name="items[{{ $item->id }}][manufacture_date]"
                                           class="form-control"
                                           value="{{ old('items.'.$item->id.'.manufacture_date') }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Unit Cost</label>
                                    <input type="number"
                                           name="items[{{ $item->id }}][cost_price]"
                                           class="form-control"
                                           step="0.01"
                                           value="{{ $item->unit_price }}">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Selling Price</label>
                                    <input type="number"
                                           name="items[{{ $item->id }}][selling_price]"
                                           class="form-control"
                                           step="0.01"
                                           value="{{ $item->product->unit_price ?? $item->unit_price }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group mb-0">
                                    <label>Notes</label>
                                    <input type="text"
                                           name="items[{{ $item->id }}][notes]"
                                           class="form-control"
                                           placeholder="Any notes about this batch...">
                                </div>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="text-center py-2 text-success">
                        <i class="mdi mdi-check-circle"></i> Fully received
                    </div>
                    @endif
                </div>
                @endforeach
            </div>

            <!-- Receiving Options -->
            <div class="form-section">
                <h5><i class="mdi mdi-cog"></i> Receiving Options</h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Received Date</label>
                            <input type="date" name="received_date" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Invoice/Delivery Note #</label>
                            <input type="text" name="invoice_number" class="form-control" placeholder="Supplier invoice number">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input type="checkbox" name="create_expense" id="create_expense" class="form-check-input" checked>
                            <label for="create_expense" class="form-check-label">
                                Create expense record for this receipt
                            </label>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Receiving Notes</label>
                            <textarea name="receiving_notes" rows="2" class="form-control" placeholder="Any notes about this delivery..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-section">
                <div class="d-flex justify-content-between">
                    <a href="{{ route('inventory.purchase-orders.show', $purchaseOrder) }}" class="btn btn-secondary">
                        <i class="mdi mdi-arrow-left"></i> Back to PO
                    </a>
                    <div>
                        <button type="button" class="btn btn-outline-secondary" onclick="fillAll()">
                            <i class="mdi mdi-check-all"></i> Receive All Pending
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearAll()">
                            <i class="mdi mdi-close-circle"></i> Clear All
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="mdi mdi-package-down"></i> Process Receipt
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    // Validate quantity doesn't exceed pending
    $('.qty-receive-input').on('input', function() {
        var max = parseInt($(this).data('max'));
        var val = parseInt($(this).val()) || 0;
        if (val > max) {
            $(this).val(max);
            toastr.warning('Quantity cannot exceed pending amount (' + max + ')');
        }
    });

    // Form submission
    $('#receive-form').on('submit', function(e) {
        var hasItems = false;
        $('.qty-receive-input').each(function() {
            if (parseInt($(this).val()) > 0) {
                hasItems = true;
                return false;
            }
        });

        if (!hasItems) {
            e.preventDefault();
            toastr.error('Please enter at least one item to receive');
            return false;
        }

        // Validate batch numbers for items being received
        var missingBatch = false;
        $('.qty-receive-input').each(function() {
            var qty = parseInt($(this).val()) || 0;
            if (qty > 0) {
                var row = $(this).closest('.batch-section');
                var batchNumber = row.find('input[name$="[batch_number]"]').val();
                if (!batchNumber) {
                    missingBatch = true;
                    toastr.error('Batch number is required for items being received');
                    row.find('input[name$="[batch_number]"]').focus();
                    return false;
                }
            }
        });

        if (missingBatch) {
            e.preventDefault();
            return false;
        }
    });
});

function fillAll() {
    $('.qty-receive-input').each(function() {
        $(this).val($(this).data('max'));
    });
}

function clearAll() {
    $('.qty-receive-input').val(0);
}
</script>
@endsection
