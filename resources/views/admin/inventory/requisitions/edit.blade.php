@extends('admin.layouts.app')
@section('title', 'Edit Requisition')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Edit Requisition')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}">
<style>
    .req-header-card { border-left: 4px solid #007bff; }
    .req-status-badge { font-size: 1rem; padding: 0.4rem 0.8rem; border-radius: 50px; font-weight: 600; }
    .table-items th { background: #f8f9fa; text-transform: uppercase; font-size: 0.85rem; color: #495057; }
    .table-items td { vertical-align: middle; }
    .qty-input { width: 80px; text-align: center; }
    .pkg-select { width: 140px; }
    .item-row.readonly-item { background: #fdfdfd; }
    .item-row.readonly-item input, .item-row.readonly-item select { background: #e9ecef; pointer-events: none; }

    /* Product Browser */
    .product-browser {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .browser-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .browser-header h5 {
        margin: 0;
        font-weight: 600;
    }
    .browser-filters {
        padding: 1rem 1.5rem;
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .filter-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .filter-group label {
        font-size: 0.85rem;
        color: #6c757d;
        margin: 0;
    }
    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
        padding: 1.5rem;
        max-height: 500px;
        overflow-y: auto;
    }
    .product-card {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 1rem;
        display: flex;
        gap: 1rem;
        transition: all 0.2s;
    }
    .product-card:hover {
        border-color: #007bff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .product-card.out-of-stock {
        background: #f8f9fa;
    }
    .product-card .product-info {
        flex: 1;
    }
    .product-card .product-name {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    .product-card .product-code {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .product-card .stock-levels {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.5rem;
        font-size: 0.85rem;
        flex-wrap: wrap;
    }
    .stock-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    .stock-badge.good { background: #d4edda; color: #155724; }
    .stock-badge.low { background: #fff3cd; color: #856404; }
    .stock-badge.out { background: #f8d7da; color: #721c24; }
    .product-card .add-actions {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .qty-mini-input {
        width: 60px;
        text-align: center;
        padding: 0.25rem;
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }
    .btn-add-item {
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.85rem;
    }
    .pkg-select-card {
        font-size: 0.75rem;
        padding: 0.2rem 0.25rem;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        max-width: 115px;
    }
    .base-equiv-label {
        font-size: 0.7rem;
        color: #17a2b8;
        text-align: center;
    }
    
    .product-search-bar {
        position: relative;
    }
    .product-search-bar input {
        padding-left: 2.5rem;
        border-radius: 8px;
    }
    .product-search-bar .search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
    }
</style>

<div class="row mb-4">
    <div class="col-12">
        <div class="card req-header-card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0">Requisition <span class="text-primary">{{ $requisition->requisition_number }}</span></h3>
                    <div>
                        <span class="req-status-badge badge badge-{{ $requisition->status === 'pending' ? 'warning' : ($requisition->status === 'approved' ? 'primary' : 'success') }}">
                            {{ strtoupper($requisition->status) }}
                        </span>
                    </div>
                </div>

                <form id="editRequisitionForm" action="{{ route('inventory.requisitions.update', $requisition->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-5">
                            <label class="font-weight-bold text-muted">Destination Store (Requesting For)</label>
                            @if($requisition->canEditHeader())
                                <select name="to_store_id" id="toStore" class="form-control select2">
                                    <option value="">-- Select Store --</option>
                                    @foreach($myStores as $store)
                                        <option value="{{ $store->id }}" {{ $requisition->to_store_id == $store->id ? 'selected' : '' }}>
                                            {{ $store->store_name }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <div class="form-control bg-light">{{ $requisition->toStore->store_name }}</div>
                                <input type="hidden" id="toStore" value="{{ $requisition->to_store_id }}">
                            @endif
                        </div>
                        <div class="col-md-2 text-center d-flex align-items-center justify-content-center">
                            <i class="mdi mdi-arrow-right-thick text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <div class="col-md-5">
                            <label class="font-weight-bold text-muted">Source Store (Requesting From)</label>
                            @if($requisition->canEditHeader())
                                <select name="from_store_id" id="fromStore" class="form-control select2">
                                    <option value="">-- Select Store --</option>
                                    @foreach($stores as $store)
                                        <option value="{{ $store->id }}" {{ $requisition->from_store_id == $store->id ? 'selected' : '' }}>
                                            {{ $store->store_name }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <div class="form-control bg-light">{{ $requisition->fromStore->store_name }}</div>
                                <input type="hidden" id="fromStore" value="{{ $requisition->from_store_id }}">
                            @endif
                        </div>
                    </div>
                    
                    <div id="lanePolicyBanner" class="alert mt-3" style="display:none;"></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 font-weight-bold">Requested Items</h5>
        @if($requisition->canEditItems())
            <div class="col-md-4 px-0 text-right">
                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#productBrowserModal">
                    <i class="mdi mdi-plus"></i> Browse Products
                </button>
            </div>
        @endif
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-items mb-0" id="itemsTable">
                <thead>
                    <tr>
                        <th width="35%">Product</th>
                        <th width="15%" class="text-center">Packaging</th>
                        <th width="15%" class="text-center">Quantity</th>
                        <th width="15%" class="text-center">Base Equivalent</th>
                        <th width="10%" class="text-center">Status</th>
                        <th width="10%" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requisition->items as $item)
                        @php
                            $fulfilled = $item->fulfilled_qty ?? 0;
                            $isReadOnly = !$requisition->canEditItemQty($item) || in_array($item->status, ['fulfilled', 'cancelled', 'returned']);
                            $minQty = $fulfilled > 0 ? $fulfilled : 1;
                        @endphp
                        <tr class="item-row {{ $isReadOnly ? 'readonly-item' : '' }}" data-item-id="{{ $item->id }}" data-product-id="{{ $item->product_id }}">
                            <td>
                                <strong>{{ $item->product->product_name }}</strong><br>
                                <small class="text-muted">{{ $item->product->product_code }}</small>
                            </td>
                            <td class="text-center">
                                <select class="form-control form-control-sm pkg-select mx-auto" {{ $isReadOnly ? 'disabled' : '' }}>
                                    <option value="" data-base-qty="1" {{ !$item->packaging_id ? 'selected' : '' }}>{{ $item->product->base_unit ?? 'Unit' }} (1)</option>
                                    @foreach($item->product->packagings as $pkg)
                                        <option value="{{ $pkg->id }}" data-base-qty="{{ $pkg->base_unit_qty }}" {{ $item->packaging_id == $pkg->id ? 'selected' : '' }}>
                                            {{ $pkg->name }} ({{ (int)$pkg->base_unit_qty }})
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="text-center">
                                <input type="number" class="form-control form-control-sm qty-input mx-auto" 
                                    value="{{ $item->requested_qty }}" 
                                    min="{{ $minQty }}" 
                                    {{ $isReadOnly ? 'readonly' : '' }}>
                                @if($fulfilled > 0)
                                    <div class="mt-1"><small class="text-success">Fulfilled: {{ $fulfilled }}</small></div>
                                @endif
                            </td>
                            <td class="text-center align-middle">
                                <span class="base-equiv badge badge-info badge-pill" data-base-unit="{{ $item->product->base_unit ?? 'Units' }}">
                                    {{ $item->requested_qty * ($item->packaging ? $item->packaging->base_unit_qty : 1) }} {{ $item->product->base_unit ?? 'Units' }}
                                </span>
                            </td>
                            <td class="text-center align-middle item-status-col">
                                <span class="badge badge-{{ $item->status === 'pending' ? 'warning' : ($item->status === 'fulfilled' ? 'success' : ($item->status === 'returned' ? 'secondary' : 'info')) }}">
                                    {{ ucfirst($item->status) }}
                                </span>
                            </td>
                            <td class="text-center align-middle action-col">
                                @if($requisition->canEditItems() && $fulfilled == 0 && $item->status !== 'returned')
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="mdi mdi-trash-can"></i></button>
                                @elseif($fulfilled > 0 && !in_array($item->status, ['returned', 'cancelled']))
                                    <button type="button" class="btn btn-sm btn-outline-warning btn-return" 
                                        data-item-id="{{ $item->id }}" 
                                        data-product="{{ $item->product->product_name }}"
                                        data-fulfilled="{{ $fulfilled }}"
                                        title="Return Item">
                                        <i class="mdi mdi-keyboard-return"></i> Return
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card-footer bg-light p-4">
        <div class="row">
            <div class="col-md-8">
                <label class="font-weight-bold">Notes / Comments</label>
                <textarea id="notes" class="form-control" rows="3" placeholder="Add any special instructions..." {{ $requisition->canEditHeader() ? '' : 'readonly' }}>{{ $requisition->notes }}</textarea>
            </div>
            <div class="col-md-4 d-flex flex-column justify-content-end align-items-end mt-3 mt-md-0">
                <div class="w-100 text-right mb-2">
                    <h5 class="mb-0">Total Items: <span id="totalItemsBadge" class="badge badge-primary">{{ $requisition->items->count() }}</span></h5>
                </div>
                <div class="w-100 text-right">
                    <button type="button" class="btn btn-secondary mr-2" onclick="window.history.back()">Cancel</button>
                    <button type="button" class="btn btn-success" id="btnSaveRequisition">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Product Browser Modal -->
<div class="modal fade" id="productBrowserModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-body p-0">
                <!-- Product Browser -->
                <div class="product-browser">
                    <div class="browser-header">
                        <h5><i class="mdi mdi-package-variant"></i> Available Products at Source Store</h5>
                        <div>
                            <span class="badge badge-light mr-3" id="product-count">0 products</span>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 1;">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    </div>
                    <div class="browser-filters">
                        <div class="filter-group flex-grow-1">
                            <div class="product-search-bar w-100">
                                <i class="mdi mdi-magnify search-icon"></i>
                                <input type="text" class="form-control" id="product-search" placeholder="Search products...">
                            </div>
                        </div>
                        <div class="filter-group">
                            <label>Category:</label>
                            <select class="form-control form-control-sm" id="category-filter">
                                <option value="">All Categories</option>
                                @foreach(\App\Models\ProductCategory::orderBy('category_name')->get() as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->category_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Show:</label>
                            <select class="form-control form-control-sm" id="stock-filter">
                                <option value="all" selected>All Products</option>
                                <option value="in-stock">In Stock Only</option>
                                <option value="low">Low Stock</option>
                            </select>
                        </div>
                    </div>
                    <div class="product-grid" id="product-grid">
                        <div class="text-center text-muted py-5 w-100" style="grid-column: 1 / -1;">
                            <i class="mdi mdi-store-search mdi-48px"></i>
                            <p>Loading products...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Return Modal -->
<div class="modal fade" id="returnModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="mdi mdi-keyboard-return"></i> Return Requisition Item</h5>
                <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="returnForm">
                <div class="modal-body">
                    <p>You are about to return <strong id="returnProductName"></strong>.</p>
                    <input type="hidden" id="returnItemId" name="store_requisition_item_id">
                    <input type="hidden" name="store_requisition_id" value="{{ $requisition->id }}">
                    <input type="hidden" name="restock" value="1">
                    
                    <div class="form-group">
                        <label>Quantity to Return (Base Units)</label>
                        <input type="number" id="returnQty" name="qty_returned" class="form-control" min="1" required>
                        <small class="form-text text-muted">Maximum returnable: <span id="maxReturnLabel">0</span></small>
                    </div>
                    
                    <div class="form-group">
                        <label>Condition</label>
                        <select name="return_condition" class="form-control" required>
                            <option value="good">Good (Restockable)</option>
                            <option value="damaged">Damaged</option>
                            <option value="partial">Partial/Incomplete</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Reason for Return</label>
                        <textarea name="return_reason" class="form-control" rows="3" required minlength="5"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="btnSubmitReturn">Submit Return</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ asset('assets/js/select2.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $('.select2').select2({ width: '100%' });
    $('[data-toggle="tooltip"]').tooltip();
    
    // --- Same Store Check & Lane Policy UI ---
    $('#toStore, #fromStore').on('change', function() {
        var to = $('#toStore').val();
        var from = $('#fromStore').val();
        var banner = $('#lanePolicyBanner');
        
        if (to && from) {
            if (to === from) {
                banner.removeClass('alert-info').addClass('alert-danger').html('<strong>Error:</strong> Source and Destination stores cannot be the same.').show();
                $('#btnSaveRequisition').prop('disabled', true);
            } else {
                banner.removeClass('alert-danger').addClass('alert-info').html('<i class="mdi mdi-information-outline"></i> Transfer route verified.').show();
                $('#btnSaveRequisition').prop('disabled', false);
            }
        } else {
            banner.hide();
        }
    });

    // --- Product Browser Modal Logic ---
    var productsData = [];
    var currentPage = 1;
    var isAllLoaded = false;
    var isLoadingProducts = false;
    var dbTotalProducts = 0;
    var searchTimeout = null;
    var lastSourceStoreId = null;

    function loadSourceProducts(resetList = false) {
        var sourceStoreId = $('#fromStore').val();
        if (!sourceStoreId) return;
        
        var $grid = $('#product-grid');
        
        if (resetList) {
            currentPage = 1;
            isAllLoaded = false;
            productsData = [];
            $grid.html('<div class="text-center text-muted py-5 w-100" style="grid-column: 1 / -1;"><i class="mdi mdi-store-search mdi-48px"></i><p>Loading products...</p></div>');
        } else if (isAllLoaded) {
            return;
        }

        if (isLoadingProducts) return;
        
        isLoadingProducts = true;
        var page = currentPage;
        
        $.ajax({
            url: '{{ route("inventory.purchase-orders.search-products") }}',
            data: {
                store_id: sourceStoreId,
                search: $('#product-search').val(),
                category_id: $('#category-filter').val(),
                stock_filter: $('#stock-filter').val(),
                page: page,
                limit: 50
            }
        }).done(function(data, textStatus, jqXHR) {
            dbTotalProducts = parseInt(jqXHR.getResponseHeader('X-Total-Count')) || 0;
            var products = Array.isArray(data) ? data : (data.results || data.data || []);
            
            if (products.length < 50) {
                isAllLoaded = true;
            }
            
            var mapped = products.map(function(p) {
                return {
                    id: p.id,
                    product_name: p.product_name || p.text,
                    product_code: p.product_code || p.code || '',
                    category_id: p.category_id,
                    base_unit: p.base_unit_name || p.base_unit || 'Piece',
                    product_type: p.product_type || 'drug',
                    allow_decimal_qty: p.allow_decimal_qty,
                    packagings: p.packagings || [],
                    storeStock: p.stock || 0,
                    batches: p.batches || []
                };
            });
            
            if (resetList) {
                productsData = mapped;
                $grid.empty();
            } else {
                mapped.forEach(function(item) {
                    var exists = productsData.some(function(existing) { return existing.id === item.id; });
                    if (!exists) productsData.push(item);
                });
            }
            
            currentPage++;
            $('#product-count').text(dbTotalProducts + ' products');
            renderProductGrid(productsData);
            
        }).fail(function() {
            if (page === 1) {
                $('#product-grid').html('<div class="alert alert-danger w-100 text-center" style="grid-column: 1 / -1;"><i class="mdi mdi-alert"></i> Failed to load products. Please try again.</div>');
            }
        }).always(function() {
            isLoadingProducts = false;
        });
    }

    $('#productBrowserModal').on('show.bs.modal', function () {
        var currentSourceStoreId = $('#fromStore').val();
        if (!currentSourceStoreId) {
            Swal.fire('Error', 'Please select a Source Store first.', 'warning');
            return false;
        }
        
        if (currentSourceStoreId !== lastSourceStoreId) {
            lastSourceStoreId = currentSourceStoreId;
            loadSourceProducts(true);
        }
    });

    $('#product-search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { loadSourceProducts(true); }, 400);
    });
    
    $('#category-filter, #stock-filter').on('change', function() {
        loadSourceProducts(true);
    });
    
    $('#product-grid').on('scroll', function() {
        var el = $(this);
        if (el[0].scrollHeight - el.scrollTop() - el.innerHeight() < 100) {
            loadSourceProducts(false);
        }
    });

    function escapeHtml(text) {
        if (text == null) return '';
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function renderProductGrid(products) {
        var grid = $('#product-grid');
        var search = ($('#product-search').val() || '').toLowerCase();
        var category = $('#category-filter').val();
        var stockFilter = $('#stock-filter').val();

        var filtered = products.filter(function(p) {
            if (search && p.product_name.toLowerCase().indexOf(search) === -1 &&
                (p.product_code || '').toLowerCase().indexOf(search) === -1) return false;
            if (category && p.category_id != category) return false;
            if (stockFilter === 'in-stock' && p.storeStock <= 0) return false;
            if (stockFilter === 'low' && p.storeStock > 10) return false;
            return true;
        });

        $('#product-count').text(dbTotalProducts + ' products');

        if (filtered.length === 0) {
            grid.html('<div class="text-center text-muted py-5 w-100" style="grid-column: 1 / -1;"><i class="mdi mdi-package-variant-closed mdi-48px"></i><p>No products found matching your search.</p></div>');
            return;
        }
        
        var html = '';
        filtered.forEach(function(p) {
            var inStock = p.storeStock > 0;
            var outOfStockClass = !inStock ? 'out-of-stock' : '';
            var disabledAttr = !inStock ? 'disabled' : '';
            var typeBadge = {drug: '<span class="badge" style="background:#d4edda;color:#155724;font-size:0.65rem;">Drug</span>', consumable: '<span class="badge" style="background:#fff3cd;color:#856404;font-size:0.65rem;">Cons.</span>', utility: '<span class="badge" style="background:#d1ecf1;color:#0c5460;font-size:0.65rem;">Util.</span>'}[p.product_type] || '';
            var stockClass = !inStock ? 'out' : (p.storeStock < 10 ? 'low' : 'good');
            var stockText = !inStock ? 'Unavailable' : (p.storeStock + ' ' + escapeHtml(p.base_unit));
            
            var pkgs = p.packagings || [];
            var pkgOptions = '<option value="" data-base-qty="1">'+escapeHtml(p.base_unit)+' (1)</option>';
            var defaultPkgId = '';
            
            if (pkgs.length > 0) {
                var defDisp = pkgs.find(function(pkg) { return pkg.is_default_dispense; });
                if (defDisp) {
                    defaultPkgId = defDisp.id;
                }
                
                pkgs.forEach(function(pkg) {
                    var sel = (String(pkg.id) === String(defaultPkgId)) ? ' selected' : '';
                    pkgOptions += '<option value="'+pkg.id+'" data-base-qty="'+pkg.base_unit_qty+'"'+sel+'>'+escapeHtml(pkg.name)+' ('+parseInt(pkg.base_unit_qty)+')</option>';
                });
                
                if (!defaultPkgId) {
                    pkgOptions = pkgOptions.replace('data-base-qty="1"', 'data-base-qty="1" selected');
                }
            }
            
            var maxAttr = '';
            if (inStock) {
                var defMax = p.storeStock;
                var baseQtyPerUnit = 1;
                if (defaultPkgId !== '') {
                    var defaultPkg = pkgs.find(function(pkg) { return String(pkg.id) === String(defaultPkgId); });
                    if (defaultPkg) {
                        baseQtyPerUnit = parseFloat(defaultPkg.base_unit_qty) || 1;
                    }
                }
                if (baseQtyPerUnit > 1) {
                    defMax = Math.floor(p.storeStock / baseQtyPerUnit);
                }
                maxAttr = ' max="' + (defMax > 0 ? defMax : 1) + '"';
            }
            
            html += `
                <div class="product-card ${outOfStockClass}" data-product-id="${p.id}">
                    <div class="product-info">
                        <div class="product-name">${escapeHtml(p.product_name)} ${typeBadge}</div>
                        <div class="product-code">${escapeHtml(p.product_code)}</div>
                        <div class="stock-levels">
                            <span class="stock-badge ${stockClass}"><i class="mdi mdi-store"></i> ${stockText}</span>
                            ${!inStock ? '<div class="text-muted mt-1" style="font-size: 0.75rem; line-height: 1.25; width: 100%;"><i class="mdi mdi-information-outline"></i> No stock available at source store. You can still include this item in your requisition.</div>' : ''}
                        </div>
                    </div>
                    <div class="add-actions">
                        <select class="pkg-select-card" ${disabledAttr}>
                            ${pkgOptions}
                        </select>
                        <div class="d-flex align-items-center mb-1">
                            <input type="number" class="qty-mini-input mr-1" value="1" min="1" ${maxAttr} ${disabledAttr}>
                            <button type="button" class="btn btn-primary btn-add-item" ${disabledAttr}>
                                <i class="mdi mdi-plus"></i> Add
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        grid.html(html);
    }

    $(document).on('click', '.product-card .btn-add-item', function() {
        var card = $(this).closest('.product-card');
        var productId = card.data('product-id');
        
        var p = productsData.find(function(item) { return item.id == productId; });
        if (!p) return;
        
        if($('#itemsTable tbody tr[data-product-id="'+productId+'"]').length > 0) {
            Swal.fire('Already Added', 'This product is already in the list. Adjust its quantity instead.', 'info');
            return;
        }
        
        var selectedPkgId = card.find('.pkg-select-card').val();
        var qty = parseFloat(card.find('.qty-mini-input').val()) || 1;
        
        var pkgOptions = '<option value="" data-base-qty="1">'+escapeHtml(p.base_unit)+' (1)</option>';
        var selectedBaseQty = 1;
        
        if (p.packagings && p.packagings.length > 0) {
            p.packagings.forEach(function(pkg) {
                var isSelected = (String(pkg.id) === String(selectedPkgId));
                if (isSelected) selectedBaseQty = pkg.base_unit_qty;
                pkgOptions += '<option value="'+pkg.id+'" data-base-qty="'+pkg.base_unit_qty+'" '+(isSelected?'selected':'')+'>'+escapeHtml(pkg.name)+' ('+parseInt(pkg.base_unit_qty)+')</option>';
            });
            if (!selectedPkgId) {
                pkgOptions = pkgOptions.replace('data-base-qty="1"', 'data-base-qty="1" selected');
            }
        }
        
        var baseEquiv = qty * selectedBaseQty;
        
        var rowHtml = `
            <tr class="item-row new-item" data-product-id="${p.id}">
                <td>
                    <strong>${escapeHtml(p.product_name)}</strong><br>
                    <small class="text-muted">${escapeHtml(p.product_code)}</small>
                </td>
                <td class="text-center">
                    <select class="form-control form-control-sm pkg-select mx-auto">${pkgOptions}</select>
                </td>
                <td class="text-center">
                    <input type="number" class="form-control form-control-sm qty-input mx-auto" value="${qty}" min="1">
                </td>
                <td class="text-center align-middle">
                    <span class="base-equiv badge badge-info badge-pill" data-base-unit="${escapeHtml(p.base_unit)}">${baseEquiv} ${escapeHtml(p.base_unit)}</span>
                </td>
                <td class="text-center align-middle item-status-col">
                    <span class="badge badge-warning" data-toggle="tooltip" title="This item has not been saved to the database yet. Click 'Save Changes' below."><i class="mdi mdi-alert-circle-outline mr-1"></i> New (Unsaved)</span>
                </td>
                <td class="text-center align-middle action-col">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="mdi mdi-trash-can"></i></button>
                </td>
            </tr>
        `;
        
        $('#itemsTable tbody').append(rowHtml);
        updateTotals();
        
        card.find('.qty-mini-input').val(1);
        
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true,
        });
        Toast.fire({
            icon: 'success',
            title: escapeHtml(p.product_name) + ' added!'
        });
        
        $('[data-toggle="tooltip"]').tooltip();
    });
    
    // --- Calculate Base Equiv ---
    $(document).on('input change', '.qty-input, .pkg-select', function() {
        var tr = $(this).closest('tr');
        var qty = parseFloat(tr.find('.qty-input').val()) || 1;
        var baseQty = parseFloat(tr.find('.pkg-select option:selected').data('base-qty')) || 1;
        var unit = tr.find('.base-equiv').data('base-unit') || 'Units';
        tr.find('.base-equiv').text((qty * baseQty) + ' ' + unit);
        updateTotals();
    });
    
    // --- Remove Item ---
    $(document).on('click', '.btn-remove', function() {
        var tr = $(this).closest('tr');
        if (tr.data('item-id')) {
            // Existing item: hide and mark for deletion
            tr.addClass('deleted-item').hide();
        } else {
            // New item: simply remove from DOM
            tr.remove();
        }
        updateTotals();
    });
    
    // --- Totals ---
    function updateTotals() {
        var count = $('#itemsTable tbody tr.item-row:not(.deleted-item):visible').length;
        $('#totalItemsBadge').text(count);
    }
    
    // --- Submit Edit Requisition ---
    $('#btnSaveRequisition').click(function() {
        var items = [];
        $('#itemsTable tbody tr.item-row').each(function() {
            var tr = $(this);
            if(tr.find('.item-status-col').text().toLowerCase().includes('returned')) return;
            
            var itemId = tr.data('item-id') || null;
            var productId = tr.data('product-id');
            var inputQty = parseFloat(tr.find('.qty-input').val()) || 1;
            var pkgSelect = tr.find('.pkg-select');
            var pkgId = pkgSelect.val();
            var baseQty = parseFloat(pkgSelect.find('option:selected').data('base-qty')) || 1;
            var finalQty = Math.round(inputQty * baseQty);
            var isDeleted = tr.hasClass('deleted-item');
            
            if (!itemId && isDeleted) return;
            
            var payloadItem = {
                item_id: itemId,
                product_id: productId,
                qty: finalQty,
                packaging_id: pkgId || null,
                packaging_qty: pkgId ? inputQty : null
            };
            
            if (isDeleted) {
                payloadItem._delete = true;
            }
            
            items.push(payloadItem);
        });
        
        var totalNonDeletedRows = $('#itemsTable tbody tr.item-row:not(.deleted-item)').length;
        if(totalNonDeletedRows === 0) {
            Swal.fire('Error', 'Requisition must have at least one active item.', 'error');
            return;
        }
        
        // Prevent submitting if stores are the same
        if ($('#toStore').val() === $('#fromStore').val() && $('#toStore').val() !== "") {
            Swal.fire('Error', 'Source and Destination stores cannot be the same.', 'error');
            return;
        }
        
        var payload = {
            _token: '{{ csrf_token() }}',
            _method: 'PUT',
            to_store_id: $('#toStore').val(),
            from_store_id: $('#fromStore').val(),
            notes: $('#notes').val(),
            items: items
        };
        
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-spin mdi-loading"></i> Saving...');
        
        $.ajax({
            url: $('#editRequisitionForm').attr('action'),
            type: 'POST',
            data: payload,
            success: function(res) {
                if(res.success) {
                    Swal.fire('Saved!', 'Requisition updated successfully.', 'success').then(() => {
                        window.location.href = "{{ route('inventory.requisitions.show', $requisition->id) }}";
                    });
                } else {
                    Swal.fire('Error', res.message || 'Failed to update.', 'error');
                    btn.prop('disabled', false).text('Save Changes');
                }
            },
            error: function(xhr) {
                var msg = 'An error occurred.';
                if(xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                Swal.fire('Error', msg, 'error');
                btn.prop('disabled', false).text('Save Changes');
            }
        });
    });
    
    // --- Return Modal Logic ---
    $(document).on('click', '.btn-return', function() {
        var itemId = $(this).data('item-id');
        var product = $(this).data('product');
        var fulfilled = $(this).data('fulfilled');
        
        $('#returnItemId').val(itemId);
        $('#returnProductName').text(product);
        $('#maxReturnLabel').text(fulfilled);
        $('#returnQty').attr('max', fulfilled).val(fulfilled);
        
        $('#returnModal').modal('show');
    });
    
    $('#returnForm').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        var btn = $('#btnSubmitReturn');
        
        btn.prop('disabled', true).html('<i class="mdi mdi-spin mdi-loading"></i>');
        
        $.ajax({
            url: "{{ route('inventory.requisition-returns.store') }}",
            type: "POST",
            data: form.serialize() + "&_token={{ csrf_token() }}",
            success: function(res) {
                if(res.success) {
                    $('#returnModal').modal('hide');
                    
                    var tr = $('tr[data-item-id="'+$('#returnItemId').val()+'"]');
                    tr.find('.action-col').empty();
                    tr.addClass('readonly-item');
                    tr.find('input, select').prop('disabled', true);
                    
                    if (res.auto_approved) {
                        Swal.fire('Returned', 'Return auto-approved. Stock has been moved.', 'success');
                        tr.find('.item-status-col').html('<span class="badge badge-secondary">Returned</span>');
                    } else {
                        Swal.fire({
                            title: 'Return Pending',
                            text: 'Your return has been logged and is awaiting approval from the destination store. The physical stock will not be adjusted until it is approved.',
                            icon: 'info'
                        });
                        tr.find('.item-status-col').html('<span class="badge badge-warning">Return Pending</span>');
                    }
                    
                    // We'll do an inline ajax call here to flag the item so it is ignored on save
                    $.ajax({
                        url: "{{ url('inventory/requisitions') }}/" + "{{ $requisition->id }}" + "/items/" + $('#returnItemId').val() + "/mark-returned",
                        type: "POST",
                        data: { _token: "{{ csrf_token() }}", _method: "PATCH" }
                    });
                    
                    form[0].reset();
                } else {
                    Swal.fire('Error', res.message || 'Failed to submit return', 'error');
                }
            },
            error: function(xhr) {
                var msg = 'Failed to submit return.';
                if(xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                Swal.fire('Error', msg, 'error');
            },
            complete: function() {
                btn.prop('disabled', false).text('Submit Return');
            }
        });
    });
});
</script>
@endsection
