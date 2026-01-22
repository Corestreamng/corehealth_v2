@extends('admin.layouts.app')
@section('title', 'Create Store Requisition')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Create Requisition')

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
    .store-card {
        background: #fff;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    .store-card:hover {
        border-color: #007bff;
    }
    .store-card.selected {
        border-color: #007bff;
        background: #e7f1ff;
    }
    .store-card i {
        font-size: 2rem;
        color: #6c757d;
    }
    .store-card.selected i {
        color: #007bff;
    }
    .transfer-arrow {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: #6c757d;
    }
    .available-stock {
        font-size: 0.85rem;
        color: #6c757d;
    }
    .available-stock.low {
        color: #dc3545;
    }
    .priority-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
    }
    .priority-low { background: #e9ecef; color: #495057; }
    .priority-normal { background: #cfe2ff; color: #084298; }
    .priority-high { background: #fff3cd; color: #664d03; }
    .priority-urgent { background: #f8d7da; color: #842029; }
</style>
@endpush

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <form id="requisition-form" method="POST" action="{{ route('inventory.requisitions.store') }}">
            @csrf

            <!-- Store Selection -->
            <div class="form-section">
                <h5><i class="mdi mdi-store"></i> Transfer Details</h5>
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <label class="d-block mb-2">Source Store (Transfer FROM)</label>
                        <select name="from_store_id" id="from_store_id" class="form-control @error('from_store_id') is-invalid @enderror" required>
                            <option value="">Select Source Store</option>
                            @foreach($stores as $store)
                            <option value="{{ $store->id }}" {{ old('from_store_id') == $store->id ? 'selected' : '' }}>
                                {{ $store->store_name }}
                            </option>
                            @endforeach
                        </select>
                        @error('from_store_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-2">
                        <div class="transfer-arrow">
                            <i class="mdi mdi-arrow-right-bold"></i>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="d-block mb-2">Destination Store (Transfer TO)</label>
                        <select name="to_store_id" id="to_store_id" class="form-control @error('to_store_id') is-invalid @enderror" required>
                            <option value="">Select Destination Store</option>
                            @foreach($stores as $store)
                            <option value="{{ $store->id }}" {{ old('to_store_id') == $store->id ? 'selected' : '' }}>
                                {{ $store->store_name }}
                            </option>
                            @endforeach
                        </select>
                        @error('to_store_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority" class="form-control">
                                <option value="normal" {{ old('priority') == 'normal' ? 'selected' : '' }}>Normal</option>
                                <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Low</option>
                                <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High</option>
                                <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Request Date</label>
                            <input type="date" name="request_date" class="form-control" value="{{ old('request_date', date('Y-m-d')) }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Required By Date</label>
                            <input type="date" name="required_by_date" class="form-control" value="{{ old('required_by_date') }}">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Section -->
            <div class="form-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="mdi mdi-package-variant"></i> Requested Items</h5>
                    <button type="button" class="btn btn-success btn-sm" id="add-item-btn" disabled>
                        <i class="mdi mdi-plus"></i> Add Item
                    </button>
                </div>

                <div id="source-store-notice" class="alert alert-info">
                    <i class="mdi mdi-information"></i> Please select a source store to add items.
                </div>

                <div id="items-container" style="display: none;"></div>

                <div class="text-muted text-center py-3" id="no-items-message" style="display: none;">
                    No items added yet. Click "Add Item" to add products.
                </div>
            </div>

            <!-- Notes Section -->
            <div class="form-section">
                <h5><i class="mdi mdi-note-text"></i> Additional Information</h5>
                <div class="form-group">
                    <label>Reason for Requisition</label>
                    <textarea name="reason" rows="3" class="form-control" placeholder="Why is this transfer needed?">{{ old('reason') }}</textarea>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="2" class="form-control" placeholder="Any additional notes...">{{ old('notes') }}</textarea>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-section">
                <div class="d-flex justify-content-between">
                    <a href="{{ route('inventory.requisitions.index') }}" class="btn btn-secondary">
                        <i class="mdi mdi-arrow-left"></i> Back to List
                    </a>
                    <button type="submit" class="btn btn-primary" id="submit-btn" disabled>
                        <i class="mdi mdi-send"></i> Submit Requisition
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Item Row Template -->
<template id="item-row-template">
    <div class="item-row" data-index="__INDEX__">
        <div class="row align-items-end">
            <div class="col-md-5">
                <div class="form-group mb-0">
                    <label>Product <span class="text-danger">*</span></label>
                    <select name="items[__INDEX__][product_id]" class="form-control product-select" required>
                        <option value="">Search product...</option>
                    </select>
                    <div class="available-stock mt-1" id="stock-info-__INDEX__"></div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-0">
                    <label>Requested Qty <span class="text-danger">*</span></label>
                    <input type="number" name="items[__INDEX__][quantity_requested]"
                           class="form-control qty-input" value="1" min="1" required>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-0">
                    <label>Notes</label>
                    <input type="text" name="items[__INDEX__][notes]"
                           class="form-control" placeholder="Optional notes">
                </div>
            </div>
            <div class="col-md-1 text-right">
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
    var itemIndex = 0;
    var sourceStoreId = null;
    var destStoreId = null;

    // Handle source store change
    $('#from_store_id').on('change', function() {
        sourceStoreId = $(this).val();
        updateUI();
        // Clear existing items when store changes
        $('#items-container').empty();
        itemIndex = 0;
    });

    // Handle destination store change
    $('#to_store_id').on('change', function() {
        destStoreId = $(this).val();
        validateStores();
    });

    function updateUI() {
        if (sourceStoreId) {
            $('#source-store-notice').hide();
            $('#items-container').show();
            $('#add-item-btn').prop('disabled', false);
            if ($('#items-container .item-row').length === 0) {
                $('#no-items-message').show();
            }
        } else {
            $('#source-store-notice').show();
            $('#items-container').hide();
            $('#add-item-btn').prop('disabled', true);
        }
        validateForm();
    }

    function validateStores() {
        if (sourceStoreId && destStoreId && sourceStoreId === destStoreId) {
            toastr.error('Source and destination stores cannot be the same');
            $('#to_store_id').val('');
            destStoreId = null;
        }
        validateForm();
    }

    function validateForm() {
        var hasItems = $('#items-container .item-row').length > 0;
        var storesValid = sourceStoreId && destStoreId && sourceStoreId !== destStoreId;
        $('#submit-btn').prop('disabled', !(hasItems && storesValid));
    }

    // Add item
    $('#add-item-btn').on('click', function() {
        var template = $('#item-row-template').html();
        template = template.replace(/__INDEX__/g, itemIndex);
        $('#items-container').append(template);

        var newSelect = $('#items-container .item-row:last .product-select');
        initProductSelect(newSelect);

        $('#no-items-message').hide();
        itemIndex++;
        validateForm();
    });

    // Initialize Select2 for product search
    window.initProductSelect = function(element) {
        element.select2({
            placeholder: 'Search product...',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("inventory.purchase-orders.search-products") }}',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term,
                        store_id: sourceStoreId
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.map(function(item) {
                            return {
                                id: item.id,
                                text: item.product_name + ' (' + item.product_code + ')',
                                available: item.available_qty || 0
                            };
                        })
                    };
                }
            }
        }).on('select2:select', function(e) {
            var data = e.params.data;
            var row = $(this).closest('.item-row');
            var index = row.data('index');
            var stockInfo = $('#stock-info-' + index);

            // Fetch available stock from source store
            $.get('/inventory/store-workbench/ajax/batch-availability', {
                product_id: data.id,
                store_id: sourceStoreId
            }).done(function(response) {
                var available = response.available_quantity || 0;
                stockInfo.html('Available in source: <strong>' + available + '</strong>');
                stockInfo.toggleClass('low', available < 10);
                row.find('.qty-input').attr('max', available);
            }).fail(function() {
                stockInfo.html('<span class="text-muted">Stock info unavailable</span>');
            });
        });
    };

    window.removeItem = function(btn) {
        $(btn).closest('.item-row').remove();
        if ($('#items-container .item-row').length === 0) {
            $('#no-items-message').show();
        }
        validateForm();
    };

    // Form validation
    $('#requisition-form').on('submit', function(e) {
        var hasItems = $('#items-container .item-row').length > 0;
        if (!hasItems) {
            e.preventDefault();
            toastr.error('Please add at least one item to the requisition');
            return false;
        }

        var fromStore = $('#from_store_id').val();
        var toStore = $('#to_store_id').val();
        if (!fromStore || !toStore) {
            e.preventDefault();
            toastr.error('Please select both source and destination stores');
            return false;
        }

        if (fromStore === toStore) {
            e.preventDefault();
            toastr.error('Source and destination stores cannot be the same');
            return false;
        }
    });
});
</script>
@endsection
