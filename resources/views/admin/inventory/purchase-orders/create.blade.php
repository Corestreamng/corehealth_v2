@extends('admin.layouts.app')
@section('title', isset($purchaseOrder) ? 'Edit Purchase Order' : 'Create Purchase Order')
@section('page_name', 'Inventory Management')
@section('subpage_name', isset($purchaseOrder) ? 'Edit Purchase Order' : 'Create Purchase Order')

@section('content')
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
<div id="content-wrapper">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0">{{ isset($purchaseOrder) ? 'Edit Purchase Order' : 'Create Purchase Order' }}</h3>
                <p class="text-muted mb-0">{{ isset($purchaseOrder) ? 'Update order details' : 'Order stock from suppliers' }}</p>
            </div>
            @hasanyrole('SUPERADMIN|ADMIN|STORE')
            <a href="{{ route('inventory.store-workbench.index') }}{{ request('store_id') ? '?store_id=' . request('store_id') : '' }}" class="btn btn-secondary btn-sm">
                <i class="mdi mdi-arrow-left"></i> Back to Workbench
            </a>
            @endhasanyrole
        </div>
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
                            <select name="target_store_id" id="store_id" class="form-control @error('target_store_id') is-invalid @enderror" required>
                                <option value="">Select Store</option>
                                @foreach($stores as $store)
                                <option value="{{ $store->id }}" {{ old('target_store_id', request('store_id', $purchaseOrder->target_store_id ?? '')) == $store->id ? 'selected' : '' }}>
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
                            <select name="supplier_id" id="supplier_id" class="form-control supplier-select @error('supplier_id') is-invalid @enderror" required>
                                <option value="">Select supplier...</option>
                            </select>
                            @error('supplier_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">
                                <a href="{{ route('suppliers.create') }}" target="_blank">+ Add new supplier</a>
                            </small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="expected_date">Expected Delivery Date</label>
                            <input type="date" name="expected_date" id="expected_date"
                                   class="form-control @error('expected_date') is-invalid @enderror"
                                   value="{{ old('expected_date', isset($purchaseOrder) && $purchaseOrder->expected_date ? $purchaseOrder->expected_date->format('Y-m-d') : '') }}">
                            @error('expected_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $purchaseOrder->notes ?? '') }}</textarea>
                            @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
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
                        <button type="button" id="save-draft-btn" class="btn btn-primary">
                            <i class="mdi mdi-content-save"></i> Save Draft
                        </button>
                        <button type="button" id="save-submit-btn" class="btn btn-success">
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

    // Initialize Supplier Select2
    var supplierSelect = $('#supplier_id').select2({
        placeholder: 'Select supplier...',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
            url: '{{ route("suppliers.search") }}',
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
                            text: item.text
                        };
                    })
                };
            }
        }
    });

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

    // Handle form submission via AJAX
    $('#save-draft-btn, #save-submit-btn').on('click', function(e) {
        e.preventDefault();
        var action = $(this).attr('id') === 'save-draft-btn' ? 'save' : 'submit';
        submitForm(action);
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

function submitForm(action) {
    var form = $('#po-form');
    var items = [];
    var hasErrors = false;

    // Validate required fields
    if (!$('#store_id').val()) {
        alert('Please select a destination store');
        return;
    }

    if (!$('#supplier_id').val()) {
        alert('Please select a supplier');
        return;
    }

    // Collect items
    $('.item-row').each(function() {
        var row = $(this);
        var productId = row.find('.product-select').val();
        var quantity = row.find('.qty-input').val();
        var unitPrice = row.find('.price-input').val();

        if (!productId || !quantity || !unitPrice) {
            hasErrors = true;
            return false;
        }

        items.push({
            product_id: productId,
            ordered_qty: parseInt(quantity),
            unit_cost: parseFloat(unitPrice)
        });
    });

    if (hasErrors) {
        alert('Please fill in all item fields');
        return;
    }

    if (items.length === 0) {
        alert('Please add at least one item');
        return;
    }

    // Prepare form data
    var formData = {
        supplier_id: $('#supplier_id').val(),
        target_store_id: $('#store_id').val(),
        expected_date: $('#expected_date').val(),
        notes: $('#notes').val(),
        items: items,
        action: action,
        _token: $('input[name="_token"]').val()
    };

    // Disable buttons during submission
    var buttons = $('#save-draft-btn, #save-submit-btn');
    buttons.prop('disabled', true);

    // Submit via AJAX
    $.ajax({
        url: form.attr('action'),
        method: form.find('input[name="_method"]').length ? 'PUT' : 'POST',
        data: JSON.stringify(formData),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert(response.message || 'Purchase order saved successfully');
                if (response.redirect) {
                    window.location.href = response.redirect;
                } else {
                    window.location.href = '{{ route("inventory.purchase-orders.index") }}';
                }
            } else {
                alert(response.message || 'Failed to save purchase order');
                buttons.prop('disabled', false);
            }
        },
        error: function(xhr) {
            var message = 'Error saving purchase order';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                var errors = xhr.responseJSON.errors;
                message = Object.values(errors).flat().join('\n');
            }
            alert(message);
            buttons.prop('disabled', false);
        }
    });
}
</script>
@endsection
