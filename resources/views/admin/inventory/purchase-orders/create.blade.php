@extends('admin.layouts.app')
@section('title', isset($purchaseOrder) ? 'Edit Purchase Order' : 'Create Purchase Order')
@section('page_name', 'Inventory Management')
@section('subpage_name', isset($purchaseOrder) ? 'Edit Purchase Order' : 'Create Purchase Order')

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
    .remove-item-btn:hover {
        color: #a71d2a;
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
</style>
@endpush

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <form id="po-form" method="POST"
              action="{{ isset($purchaseOrder) ? route('inventory.purchase-orders.update', $purchaseOrder) : route('inventory.purchase-orders.store') }}">
            @csrf
            @if(isset($purchaseOrder))
            @method('PUT')
            @endif

            <!-- Header Section -->
            <div class="form-section">
                <h5><i class="mdi mdi-file-document"></i> Purchase Order Details</h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="store_id">Destination Store <span class="text-danger">*</span></label>
                            <select name="store_id" id="store_id" class="form-control @error('store_id') is-invalid @enderror" required>
                                <option value="">Select Store</option>
                                @foreach($stores as $store)
                                <option value="{{ $store->id }}" {{ old('store_id', $purchaseOrder->store_id ?? '') == $store->id ? 'selected' : '' }}>
                                    {{ $store->store_name }}
                                </option>
                                @endforeach
                            </select>
                            @error('store_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="supplier_name">Supplier Name <span class="text-danger">*</span></label>
                            <input type="text" name="supplier_name" id="supplier_name"
                                   class="form-control @error('supplier_name') is-invalid @enderror"
                                   value="{{ old('supplier_name', $purchaseOrder->supplier_name ?? '') }}" required>
                            @error('supplier_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="supplier_contact">Supplier Contact</label>
                            <input type="text" name="supplier_contact" id="supplier_contact"
                                   class="form-control"
                                   value="{{ old('supplier_contact', $purchaseOrder->supplier_contact ?? '') }}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="order_date">Order Date <span class="text-danger">*</span></label>
                            <input type="date" name="order_date" id="order_date"
                                   class="form-control @error('order_date') is-invalid @enderror"
                                   value="{{ old('order_date', isset($purchaseOrder) ? $purchaseOrder->order_date->format('Y-m-d') : date('Y-m-d')) }}" required>
                            @error('order_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="expected_delivery_date">Expected Delivery Date</label>
                            <input type="date" name="expected_delivery_date" id="expected_delivery_date"
                                   class="form-control"
                                   value="{{ old('expected_delivery_date', isset($purchaseOrder) && $purchaseOrder->expected_delivery_date ? $purchaseOrder->expected_delivery_date->format('Y-m-d') : '') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="payment_terms">Payment Terms</label>
                            <select name="payment_terms" id="payment_terms" class="form-control">
                                <option value="">Select Terms</option>
                                <option value="cash" {{ old('payment_terms', $purchaseOrder->payment_terms ?? '') == 'cash' ? 'selected' : '' }}>Cash on Delivery</option>
                                <option value="net_7" {{ old('payment_terms', $purchaseOrder->payment_terms ?? '') == 'net_7' ? 'selected' : '' }}>Net 7 Days</option>
                                <option value="net_15" {{ old('payment_terms', $purchaseOrder->payment_terms ?? '') == 'net_15' ? 'selected' : '' }}>Net 15 Days</option>
                                <option value="net_30" {{ old('payment_terms', $purchaseOrder->payment_terms ?? '') == 'net_30' ? 'selected' : '' }}>Net 30 Days</option>
                                <option value="net_60" {{ old('payment_terms', $purchaseOrder->payment_terms ?? '') == 'net_60' ? 'selected' : '' }}>Net 60 Days</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" rows="2" class="form-control">{{ old('notes', $purchaseOrder->notes ?? '') }}</textarea>
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
                    @if(isset($purchaseOrder) && $purchaseOrder->items->count() > 0)
                        @foreach($purchaseOrder->items as $index => $item)
                        <div class="item-row" data-index="{{ $index }}">
                            <div class="row align-items-end">
                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label>Product <span class="text-danger">*</span></label>
                                        <select name="items[{{ $index }}][product_id]" class="form-control product-select" required>
                                            <option value="{{ $item->product_id }}" selected>{{ $item->product->product_name }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group mb-0">
                                        <label>Quantity <span class="text-danger">*</span></label>
                                        <input type="number" name="items[{{ $index }}][quantity_ordered]"
                                               class="form-control qty-input" value="{{ $item->quantity_ordered }}" min="1" required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group mb-0">
                                        <label>Unit Price <span class="text-danger">*</span></label>
                                        <input type="number" name="items[{{ $index }}][unit_price]"
                                               class="form-control price-input" value="{{ $item->unit_price }}" step="0.01" min="0" required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group mb-0">
                                        <label>Line Total</label>
                                        <input type="text" class="form-control line-total" readonly value="{{ number_format($item->line_total, 2) }}">
                                    </div>
                                </div>
                                <div class="col-md-2 text-right">
                                    <span class="remove-item-btn" onclick="removeItem(this)">
                                        <i class="mdi mdi-delete mdi-24px"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @endif
                </div>

                <div class="text-muted text-center py-3" id="no-items-message" style="{{ isset($purchaseOrder) && $purchaseOrder->items->count() > 0 ? 'display:none' : '' }}">
                    No items added yet. Click "Add Item" to add products.
                </div>
            </div>

            <!-- Totals Section -->
            <div class="form-section">
                <div class="row justify-content-end">
                    <div class="col-md-4">
                        <div class="total-section">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span id="subtotal">₦0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax/VAT (%):</span>
                                <input type="number" name="tax_amount" id="tax_amount"
                                       class="form-control form-control-sm" style="width: 100px"
                                       value="{{ old('tax_amount', $purchaseOrder->tax_amount ?? 0) }}" step="0.01" min="0">
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping:</span>
                                <input type="number" name="shipping_cost" id="shipping_cost"
                                       class="form-control form-control-sm" style="width: 100px"
                                       value="{{ old('shipping_cost', $purchaseOrder->shipping_cost ?? 0) }}" step="0.01" min="0">
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>Total:</strong>
                                <strong id="grand-total">₦0.00</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="form-section">
                <div class="d-flex justify-content-between">
                    <a href="{{ route('inventory.purchase-orders.index') }}" class="btn btn-secondary">
                        <i class="mdi mdi-arrow-left"></i> Back to List
                    </a>
                    <div>
                        <button type="submit" name="action" value="save" class="btn btn-primary">
                            <i class="mdi mdi-content-save"></i> Save Draft
                        </button>
                        <button type="submit" name="action" value="submit" class="btn btn-success">
                            <i class="mdi mdi-send"></i> Save & Submit
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Item Row Template -->
<template id="item-row-template">
    <div class="item-row" data-index="__INDEX__">
        <div class="row align-items-end">
            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label>Product <span class="text-danger">*</span></label>
                    <select name="items[__INDEX__][product_id]" class="form-control product-select" required>
                        <option value="">Search product...</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-0">
                    <label>Quantity <span class="text-danger">*</span></label>
                    <input type="number" name="items[__INDEX__][quantity_ordered]"
                           class="form-control qty-input" value="1" min="1" required>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-0">
                    <label>Unit Price <span class="text-danger">*</span></label>
                    <input type="number" name="items[__INDEX__][unit_price]"
                           class="form-control price-input" value="0" step="0.01" min="0" required>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-0">
                    <label>Line Total</label>
                    <input type="text" class="form-control line-total" readonly value="0.00">
                </div>
            </div>
            <div class="col-md-2 text-right">
                <span class="remove-item-btn" onclick="removeItem(this)">
                    <i class="mdi mdi-delete mdi-24px"></i>
                </span>
            </div>
        </div>
    </div>
</template>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/select2.min.js') }}"></script>
<script>
$(function() {
    var itemIndex = {{ isset($purchaseOrder) ? $purchaseOrder->items->count() : 0 }};

    // Initialize Select2 for existing items
    initProductSelect($('.product-select'));
    calculateTotals();

    // Add item
    $('#add-item-btn').on('click', function() {
        var template = $('#item-row-template').html();
        template = template.replace(/__INDEX__/g, itemIndex);
        $('#items-container').append(template);

        var newSelect = $('#items-container .item-row:last .product-select');
        initProductSelect(newSelect);

        $('#no-items-message').hide();
        itemIndex++;
    });

    // Event delegation for quantity and price changes
    $(document).on('input', '.qty-input, .price-input', function() {
        var row = $(this).closest('.item-row');
        var qty = parseFloat(row.find('.qty-input').val()) || 0;
        var price = parseFloat(row.find('.price-input').val()) || 0;
        row.find('.line-total').val((qty * price).toFixed(2));
        calculateTotals();
    });

    // Recalculate on tax/shipping change
    $('#tax_amount, #shipping_cost').on('input', function() {
        calculateTotals();
    });
});

function initProductSelect(element) {
    element.select2({
        placeholder: 'Search product...',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("inventory.purchase-orders.search-products") }}',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return {
                    results: data.map(function(item) {
                        return {
                            id: item.id,
                            text: item.product_name + ' (' + item.product_code + ')',
                            price: item.purchase_price || item.unit_price || 0
                        };
                    })
                };
            }
        }
    }).on('select2:select', function(e) {
        var data = e.params.data;
        var row = $(this).closest('.item-row');
        if (data.price) {
            row.find('.price-input').val(data.price);
            var qty = parseFloat(row.find('.qty-input').val()) || 1;
            row.find('.line-total').val((qty * data.price).toFixed(2));
            calculateTotals();
        }
    });
}

function removeItem(btn) {
    var container = $('#items-container');
    $(btn).closest('.item-row').remove();

    if (container.find('.item-row').length === 0) {
        $('#no-items-message').show();
    }
    calculateTotals();
}

function calculateTotals() {
    var subtotal = 0;
    $('.item-row').each(function() {
        var lineTotal = parseFloat($(this).find('.line-total').val()) || 0;
        subtotal += lineTotal;
    });

    var tax = parseFloat($('#tax_amount').val()) || 0;
    var shipping = parseFloat($('#shipping_cost').val()) || 0;
    var total = subtotal + tax + shipping;

    $('#subtotal').text('₦' + subtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
    $('#grand-total').text('₦' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
}
</script>
@endsection
