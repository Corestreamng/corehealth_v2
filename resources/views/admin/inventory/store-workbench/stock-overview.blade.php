@extends('admin.layouts.app')
@section('title', 'Stock Overview - ' . ($selectedStore->store_name ?? 'All Stores'))
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Stock Overview')

@push('styles')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    .stock-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
    }
    .stock-good { background: #d4edda; color: #155724; }
    .stock-low { background: #fff3cd; color: #856404; }
    .stock-out { background: #f8d7da; color: #721c24; }

    .batch-card {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        font-size: 0.85rem;
    }
    .batch-card.expiring {
        border-color: #ffc107;
        background: #fffef0;
    }
    .batch-card.expired {
        border-color: #dc3545;
        background: #fff5f5;
    }

    .product-row {
        cursor: pointer;
    }
    .product-row:hover {
        background: #f8f9fa;
    }

    .batch-details {
        background: #fff;
        padding: 1rem;
        border-top: 1px solid #dee2e6;
    }

    .filter-card {
        background: #fff;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
</style>
@endpush

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0">Stock Overview</h3>
                <p class="text-muted mb-0">{{ $selectedStore->store_name ?? 'All Stores' }}</p>
            </div>
            <div>
                <a href="{{ route('inventory.store-workbench.index') }}?store_id={{ $selectedStore->id ?? '' }}" class="btn btn-secondary btn-sm">
                    <i class="mdi mdi-arrow-left"></i> Back to Workbench
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <div class="row">
                <div class="col-md-3">
                    <select id="store-filter" class="form-control form-control-sm">
                        <option value="">All Stores</option>
                        @foreach($stores as $store)
                        <option value="{{ $store->id }}" {{ request('store_id') == $store->id ? 'selected' : '' }}>
                            {{ $store->store_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="category-filter" class="form-control form-control-sm">
                        <option value="">All Categories</option>
                        @foreach($categories ?? [] as $category)
                        <option value="{{ $category->id }}">{{ $category->product_category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="stock-status-filter" class="form-control form-control-sm">
                        <option value="">All Stock Levels</option>
                        <option value="low" {{ request('filter') == 'low' ? 'selected' : '' }}>Low Stock</option>
                        <option value="out">Out of Stock</option>
                        <option value="expiring">Expiring Soon</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" id="search-input" class="form-control form-control-sm" placeholder="Search products...">
                </div>
            </div>
        </div>

        <!-- Stock Table -->
        <div class="card-modern">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="stock-table" class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th style="width: 30px;"></th>
                                <th>Product</th>
                                <th>Category</th>
                                <th class="text-center">Total Qty</th>
                                <th class="text-center">Batches</th>
                                <th class="text-center">Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products ?? [] as $product)
                            @php
                                $totalQty = $product->available_qty ?? 0;
                                $batchCount = $product->batches_count ?? 0;
                                $reorderLevel = $product->store_stock->reorder_level ?? 10;
                            @endphp
                            <tr class="product-row" data-product-id="{{ $product->id }}">
                                <td class="text-center">
                                    <i class="mdi mdi-chevron-right expand-icon"></i>
                                </td>
                                <td>
                                    <strong>{{ $product->product_name }}</strong>
                                    <br><small class="text-muted">{{ $product->product_code }}</small>
                                </td>
                                <td>{{ $product->category->product_category ?? 'N/A' }}</td>
                                <td class="text-center">
                                    <strong>{{ $totalQty }}</strong>
                                </td>
                                <td class="text-center">{{ $batchCount }}</td>
                                <td class="text-center">
                                    @if($totalQty <= 0)
                                    <span class="stock-badge stock-out">Out of Stock</span>
                                    @elseif($totalQty <= $reorderLevel)
                                    <span class="stock-badge stock-low">Low Stock</span>
                                    @else
                                    <span class="stock-badge stock-good">In Stock</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('inventory.store-workbench.product-batches', $product) }}?store_id={{ request('store_id') }}"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="mdi mdi-eye"></i> Batches
                                    </a>
                                </td>
                            </tr>
                            <tr class="batch-row" id="batches-{{ $product->id }}" style="display: none;">
                                <td colspan="7">
                                    <div class="batch-details">
                                        <h6 class="mb-3">Batch Details for {{ $product->product_name }}</h6>
                                        <div class="row" id="batch-container-{{ $product->id }}">
                                            <div class="col-12 text-center py-3">
                                                <i class="mdi mdi-loading mdi-spin"></i> Loading batches...
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="mdi mdi-package-variant-closed mdi-48px text-muted"></i>
                                    <p class="text-muted mt-2">No products found</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(method_exists($products ?? collect(), 'links'))
                <div class="mt-3">
                    {{ $products->appends(request()->query())->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    // Toggle batch details
    $('.product-row').on('click', function() {
        var productId = $(this).data('product-id');
        var batchRow = $('#batches-' + productId);
        var expandIcon = $(this).find('.expand-icon');

        if (batchRow.is(':visible')) {
            batchRow.hide();
            expandIcon.removeClass('mdi-chevron-down').addClass('mdi-chevron-right');
        } else {
            batchRow.show();
            expandIcon.removeClass('mdi-chevron-right').addClass('mdi-chevron-down');
            loadBatches(productId);
        }
    });

    // Load batches via AJAX
    function loadBatches(productId) {
        var container = $('#batch-container-' + productId);
        var storeId = $('#store-filter').val() || '{{ request("store_id") }}';

        $.get('{{ url("/inventory/store-workbench/product") }}/' + productId + '/batches', {
            store_id: storeId,
            ajax: 1
        }).done(function(response) {
            if (response.batches && response.batches.length > 0) {
                var html = '';
                response.batches.forEach(function(batch) {
                    var expiryClass = '';
                    if (batch.is_expired) {
                        expiryClass = 'expired';
                    } else if (batch.days_until_expiry && batch.days_until_expiry <= 90) {
                        expiryClass = 'expiring';
                    }

                    html += '<div class="col-md-4 mb-2">' +
                        '<div class="batch-card ' + expiryClass + '">' +
                        '<div class="d-flex justify-content-between">' +
                        '<strong>' + batch.batch_number + '</strong>' +
                        '<span>' + batch.current_quantity + ' units</span>' +
                        '</div>' +
                        '<div class="text-muted small">' +
                        'Expiry: ' + (batch.expiry_date || 'N/A') +
                        '</div>' +
                        '<div class="text-muted small">' +
                        'Cost: ₦' + parseFloat(batch.cost_price || 0).toFixed(2) +
                        '</div>' +
                        '<div class="mt-2">' +
                        '<a href="/inventory/store-workbench/batch/' + batch.id + '/adjust" class="btn btn-xs btn-outline-secondary">Adjust</a> ' +
                        '</div>' +
                        '</div>' +
                        '</div>';
                });
                container.html(html);
            } else {
                container.html('<div class="col-12 text-center text-muted py-3">No batches found for this product in the selected store</div>');
            }
        }).fail(function() {
            container.html('<div class="col-12 text-center text-danger py-3">Failed to load batches</div>');
        });
    }

    // Filters
    $('#store-filter, #category-filter, #stock-status-filter').on('change', function() {
        applyFilters();
    });

    var searchTimeout;
    $('#search-input').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            applyFilters();
        }, 500);
    });

    function applyFilters() {
        var params = new URLSearchParams();
        if ($('#store-filter').val()) params.set('store_id', $('#store-filter').val());
        if ($('#category-filter').val()) params.set('category_id', $('#category-filter').val());
        if ($('#stock-status-filter').val()) params.set('filter', $('#stock-status-filter').val());
        if ($('#search-input').val()) params.set('search', $('#search-input').val());

        window.location.href = '{{ route("inventory.store-workbench.stock-overview") }}?' + params.toString();
    }
});
</script>
@endsection
