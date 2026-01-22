@extends('admin.layouts.app')
@section('title', 'Edit Purchase Order')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Edit Purchase Order')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}">
<style>
    .item-row {
        background: #f8f9fa;
        padding: 1rem;
        margin-bottom: 0.5rem;
        border-radius: 4px;
        border: 1px solid #dee2e6;
    }
    .item-row:hover {
        background: #e9ecef;
    }
    .remove-item-btn {
        color: #dc3545;
        cursor: pointer;
    }
    .total-section {
        background: #fff;
        padding: 1rem;
        border-radius: 4px;
        border: 1px solid #dee2e6;
    }
    .form-section {
        background: #fff;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .form-section h5 {
        border-bottom: 2px solid #007bff;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
    }
    .po-header {
        background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
</style>
@endpush

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="po-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Edit Purchase Order</h4>
                    <p class="mb-0 opacity-75">{{ $purchaseOrder->po_number }}</p>
                </div>
                <span class="badge badge-light px-3 py-2">{{ ucfirst($purchaseOrder->status) }}</span>
            </div>
        </div>

        @if(!$purchaseOrder->canEdit())
        <div class="alert alert-warning">
            <i class="mdi mdi-alert"></i> This purchase order cannot be edited in its current status.
        </div>
        @else
        <form id="po-form" method="POST" action="{{ route('inventory.purchase-orders.update', $purchaseOrder) }}">
            @csrf
            @method('PUT')

            <!-- Header Section -->
            <div class="form-section">
                <h5><i class="mdi mdi-file-document"></i> Purchase Order Details</h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="target_store_id">Destination Store <span class="text-danger">*</span></label>
                            <select name="target_store_id" id="target_store_id" class="form-control @error('target_store_id') is-invalid @enderror" required>
                                <option value="">Select Store</option>
                                @foreach($stores as $store)
                                <option value="{{ $store->id }}" {{ old('target_store_id', $purchaseOrder->target_store_id) == $store->id ? 'selected' : '' }}>
                                    {{ $store->store_name }}
                                </option>
                                @endforeach
                            </select>
                            @error('target_store_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" id="supplier_id" class="form-control @error('supplier_id') is-invalid @enderror" required>
                                <option value="">Select Supplier</option>
                                @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ old('supplier_id', $purchaseOrder->supplier_id) == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->supplier_name }}
                                </option>
                                @endforeach
                            </select>
                            @error('supplier_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="expected_date">Expected Delivery Date</label>
                            <input type="date" name="expected_date" id="expected_date"
                                   class="form-control"
                                   value="{{ old('expected_date', $purchaseOrder->expected_date?->format('Y-m-d')) }}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" rows="2" class="form-control">{{ old('notes', $purchaseOrder->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Section -->
            <div class="form-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="mdi mdi-package-variant"></i> Line Items</h5>
                    <button type="button" class="btn btn-success btn-sm" id="add-item-btn">
                        <i class="mdi mdi-plus"></i> Add Item
                    </button>
                </div>

                <div id="items-container">
                    @foreach($purchaseOrder->items as $index => $item)
                    <div class="item-row" data-index="{{ $index }}">
                        <div class="row align-items-end">
                            <div class="col-md-5">
                                <div class="form-group mb-0">
                                    <label>Product <span class="text-danger">*</span></label>
                                    <select name="items[{{ $index }}][product_id]" class="form-control product-select" required>
                                        <option value="">Select Product</option>
                                        @foreach($products as $product)
                                        <option value="{{ $product->id }}" {{ $item->product_id == $product->id ? 'selected' : '' }}>
                                            {{ $product->product_name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group mb-0">
                                    <label>Quantity <span class="text-danger">*</span></label>
                                    <input type="number" name="items[{{ $index }}][ordered_qty]" class="form-control qty-input"
                                           min="1" value="{{ $item->ordered_qty }}" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group mb-0">
                                    <label>Unit Cost <span class="text-danger">*</span></label>
                                    <input type="number" name="items[{{ $index }}][unit_cost]" class="form-control cost-input"
                                           step="0.01" min="0" value="{{ $item->unit_cost }}" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group mb-0">
                                    <label>Line Total</label>
                                    <input type="text" class="form-control line-total" readonly
                                           value="₦{{ number_format($item->ordered_qty * $item->unit_cost, 2) }}">
                                </div>
                            </div>
                            <div class="col-md-1 text-center">
                                <span class="remove-item-btn" onclick="removeItem(this)">
                                    <i class="mdi mdi-delete mdi-24px"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Totals -->
                <div class="total-section mt-3">
                    <div class="row justify-content-end">
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <strong id="subtotal">₦{{ number_format($purchaseOrder->subtotal ?? 0, 2) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="h5 mb-0">Total:</span>
                                <strong class="h5 mb-0 text-primary" id="grand-total">₦{{ number_format($purchaseOrder->total_amount ?? 0, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-flex justify-content-between">
                <a href="{{ route('inventory.purchase-orders.show', $purchaseOrder) }}" class="btn btn-secondary">
                    <i class="mdi mdi-arrow-left"></i> Cancel
                </a>
                <div>
                    <button type="submit" name="action" value="save" class="btn btn-primary">
                        <i class="mdi mdi-content-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/select2.min.js') }}"></script>
<script>
let itemIndex = {{ count($purchaseOrder->items) }};

$(function() {
    initSelect2();
    calculateTotals();

    $('#add-item-btn').on('click', addItem);
    $(document).on('change keyup', '.qty-input, .cost-input', calculateTotals);
});

function initSelect2() {
    $('.product-select').select2({
        placeholder: 'Select Product',
        allowClear: true
    });
}

function addItem() {
    const template = `
        <div class="item-row" data-index="${itemIndex}">
            <div class="row align-items-end">
                <div class="col-md-5">
                    <div class="form-group mb-0">
                        <label>Product <span class="text-danger">*</span></label>
                        <select name="items[${itemIndex}][product_id]" class="form-control product-select" required>
                            <option value="">Select Product</option>
                            @foreach($products as $product)
                            <option value="{{ $product->id }}">{{ $product->product_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group mb-0">
                        <label>Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="items[${itemIndex}][ordered_qty]" class="form-control qty-input" min="1" value="1" required>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group mb-0">
                        <label>Unit Cost <span class="text-danger">*</span></label>
                        <input type="number" name="items[${itemIndex}][unit_cost]" class="form-control cost-input" step="0.01" min="0" value="0" required>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group mb-0">
                        <label>Line Total</label>
                        <input type="text" class="form-control line-total" readonly value="₦0.00">
                    </div>
                </div>
                <div class="col-md-1 text-center">
                    <span class="remove-item-btn" onclick="removeItem(this)">
                        <i class="mdi mdi-delete mdi-24px"></i>
                    </span>
                </div>
            </div>
        </div>
    `;

    $('#items-container').append(template);
    itemIndex++;
    initSelect2();
}

function removeItem(btn) {
    $(btn).closest('.item-row').remove();
    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;

    $('.item-row').each(function() {
        const qty = parseFloat($(this).find('.qty-input').val()) || 0;
        const cost = parseFloat($(this).find('.cost-input').val()) || 0;
        const lineTotal = qty * cost;

        $(this).find('.line-total').val('₦' + lineTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        subtotal += lineTotal;
    });

    $('#subtotal').text('₦' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    $('#grand-total').text('₦' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
}
</script>
@endsection
