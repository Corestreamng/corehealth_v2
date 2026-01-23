@extends('admin.layouts.app')
@section('title', 'Create Store Requisition')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Create Requisition')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}">
<style>
    /* ===== Requisition Page Redesign ===== */
    .requisition-wizard { font-family: 'Inter', -apple-system, sans-serif; }

    /* Step indicators */
    .wizard-steps {
        display: flex;
        justify-content: center;
        margin-bottom: 2rem;
        gap: 1rem;
    }
    .wizard-step {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: #f8f9fa;
        border-radius: 50px;
        color: #6c757d;
        font-weight: 500;
        transition: all 0.3s;
    }
    .wizard-step.active {
        background: #007bff;
        color: white;
    }
    .wizard-step.completed {
        background: #28a745;
        color: white;
    }
    .wizard-step .step-num {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
    }
    .wizard-step.active .step-num,
    .wizard-step.completed .step-num {
        background: rgba(255,255,255,0.3);
    }

    /* Store Selection Cards */
    .store-selection-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
    }
    .store-select-card {
        background: #fff;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 1.25rem;
        cursor: pointer;
        transition: all 0.2s;
        text-align: center;
    }
    .store-select-card:hover {
        border-color: #007bff;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .store-select-card.selected {
        border-color: #007bff;
        background: linear-gradient(135deg, #e7f1ff 0%, #fff 100%);
    }
    .store-select-card.selected.source {
        border-color: #28a745;
        background: linear-gradient(135deg, #d4edda 0%, #fff 100%);
    }
    .store-select-card.selected.destination {
        border-color: #17a2b8;
        background: linear-gradient(135deg, #d1ecf1 0%, #fff 100%);
    }
    .store-select-card .store-icon {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
        color: #6c757d;
    }
    .store-select-card.selected .store-icon { color: #007bff; }
    .store-select-card.selected.source .store-icon { color: #28a745; }
    .store-select-card.selected.destination .store-icon { color: #17a2b8; }
    .store-select-card .store-name {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }
    .store-select-card .store-stats {
        display: flex;
        justify-content: center;
        gap: 1rem;
        font-size: 0.8rem;
        color: #6c757d;
    }
    .store-select-card .stat-item {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .store-select-card .stat-value {
        font-weight: 700;
        font-size: 1.1rem;
        color: #212529;
    }

    /* Transfer Direction Visual */
    .transfer-visual {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 2rem;
        padding: 1.5rem;
        background: #f8f9fa;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }
    .transfer-store-box {
        background: #fff;
        padding: 1rem 2rem;
        border-radius: 8px;
        text-align: center;
        min-width: 180px;
        border: 2px solid #dee2e6;
    }
    .transfer-store-box.source { border-color: #28a745; }
    .transfer-store-box.destination { border-color: #17a2b8; }
    .transfer-store-box .label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }
    .transfer-store-box .name {
        font-weight: 600;
        font-size: 1.1rem;
    }
    .transfer-arrow {
        font-size: 2rem;
        color: #007bff;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

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
        opacity: 0.6;
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

    /* Suggestions Panel */
    .suggestions-panel {
        background: linear-gradient(135deg, #fff9e6 0%, #fff 100%);
        border: 1px solid #ffc107;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .suggestions-panel h6 {
        color: #856404;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .suggestion-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        background: #fff;
        border-radius: 6px;
        margin-bottom: 0.5rem;
        border: 1px solid #ffeeba;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .suggestion-item .product-info {
        flex: 1;
        min-width: 150px;
    }
    .suggestion-item .deficit {
        color: #dc3545;
        font-weight: 500;
        margin-right: 1rem;
    }

    /* Cart / Selected Items */
    .cart-panel {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        position: sticky;
        top: 1rem;
    }
    .cart-header {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .cart-header h5 {
        margin: 0;
        font-weight: 600;
    }
    .cart-badge {
        background: rgba(255,255,255,0.3);
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.9rem;
    }
    .cart-items {
        max-height: 400px;
        overflow-y: auto;
        padding: 1rem;
    }
    .cart-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 6px;
        margin-bottom: 0.5rem;
    }
    .cart-item .item-info {
        flex: 1;
        min-width: 0;
    }
    .cart-item .item-name {
        font-weight: 500;
        font-size: 0.9rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .cart-item .item-qty {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .cart-item .qty-display {
        background: #007bff;
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 4px;
        font-weight: 600;
    }
    .cart-item .remove-btn {
        color: #dc3545;
        cursor: pointer;
        padding: 0.25rem;
    }
    .cart-empty {
        text-align: center;
        padding: 2rem;
        color: #6c757d;
    }
    .cart-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid #dee2e6;
        background: #f8f9fa;
        border-radius: 0 0 12px 12px;
    }

    /* Form sections */
    .form-section {
        background: #fff;
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .form-section h5 {
        border-bottom: 2px solid #007bff;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
    }

    /* Search bar */
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

    /* Loading states */
    .loading-overlay {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem;
    }
    .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Step panels */
    .step-panel { display: none; }
    .step-panel.active { display: block; }

    /* Responsive */
    @media (max-width: 768px) {
        .wizard-steps { flex-wrap: wrap; }
        .transfer-visual { flex-direction: column; gap: 1rem; }
        .transfer-arrow { transform: rotate(90deg); }
    }
</style>

<div id="content-wrapper">
    <div class="container-fluid requisition-wizard">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0">Create Store Requisition</h3>
                <p class="text-muted mb-0">Request stock transfer between stores</p>
            </div>
            @hasanyrole('SUPERADMIN|ADMIN|STORE')
            <a href="{{ route('inventory.store-workbench.index') }}{{ request('to_store_id') ? '?store_id=' . request('to_store_id') : '' }}" class="btn btn-secondary btn-sm">
                <i class="mdi mdi-arrow-left"></i> Back to Workbench
            </a>
            @endhasanyrole
        </div>
        <form id="requisition-form" method="POST" action="{{ route('inventory.requisitions.store') }}">
            @csrf
            <input type="hidden" name="from_store_id" id="from_store_id">
            <input type="hidden" name="to_store_id" id="to_store_id">

            <!-- Wizard Steps Indicator -->
            <div class="wizard-steps">
                <div class="wizard-step active" data-step="1">
                    <span class="step-num">1</span>
                    <span>Select Stores</span>
                </div>
                <div class="wizard-step" data-step="2">
                    <span class="step-num">2</span>
                    <span>Add Items</span>
                </div>
                <div class="wizard-step" data-step="3">
                    <span class="step-num">3</span>
                    <span>Review & Submit</span>
                </div>
            </div>

            <!-- STEP 1: Store Selection -->
            <div class="step-panel active" id="step-1">
                <div class="row">
                    <!-- Source Store Selection -->
                    <div class="col-md-6 mb-4">
                        <div class="form-section">
                            <h5><i class="mdi mdi-store text-success"></i> Source Store (Transfer FROM)</h5>
                            <p class="text-muted mb-3">Select the store that has the items you need</p>
                            <div class="store-selection-grid" id="source-store-grid">
                                @foreach($stores as $store)
                                <div class="store-select-card" data-store-id="{{ $store->id }}" data-store-name="{{ $store->store_name }}" data-type="source">
                                    <i class="mdi mdi-store store-icon"></i>
                                    <div class="store-name">{{ $store->store_name }}</div>
                                    <div class="store-stats">
                                        <div class="stat-item">
                                            <span class="stat-value" data-stat="products">--</span>
                                            <span>Products</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-value" data-stat="stock">--</span>
                                            <span>In Stock</span>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Destination Store Selection -->
                    <div class="col-md-6 mb-4">
                        <div class="form-section">
                            <h5><i class="mdi mdi-store text-info"></i> Destination Store (Transfer TO)</h5>
                            <p class="text-muted mb-3">Select the store that needs the items</p>
                            <div class="store-selection-grid" id="dest-store-grid">
                                @foreach($stores as $store)
                                <div class="store-select-card" data-store-id="{{ $store->id }}" data-store-name="{{ $store->store_name }}" data-type="destination">
                                    <i class="mdi mdi-store store-icon"></i>
                                    <div class="store-name">{{ $store->store_name }}</div>
                                    <div class="store-stats">
                                        <div class="stat-item">
                                            <span class="stat-value" data-stat="low">--</span>
                                            <span>Low Stock</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-value" data-stat="out">--</span>
                                            <span>Out of Stock</span>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <button type="button" class="btn btn-primary btn-lg" id="btn-to-step-2" disabled>
                        Continue to Add Items <i class="mdi mdi-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 2: Add Items -->
            <div class="step-panel" id="step-2">
                <!-- Transfer Visual Summary -->
                <div class="transfer-visual" id="transfer-summary">
                    <div class="transfer-store-box source">
                        <div class="label">From</div>
                        <div class="name" id="source-store-name">--</div>
                    </div>
                    <div class="transfer-arrow">
                        <i class="mdi mdi-arrow-right-bold"></i>
                    </div>
                    <div class="transfer-store-box destination">
                        <div class="label">To</div>
                        <div class="name" id="dest-store-name">--</div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-change-stores">
                        <i class="mdi mdi-pencil"></i> Change
                    </button>
                </div>

                <div class="row">
                    <!-- Product Browser (Left) -->
                    <div class="col-lg-8 mb-4">
                        <!-- Suggestions Panel -->
                        <div class="suggestions-panel" id="suggestions-panel" style="display: none;">
                            <h6><i class="mdi mdi-lightbulb-on"></i> Suggested Items (Low at destination, available at source)</h6>
                            <div id="suggestions-list"></div>
                        </div>

                        <!-- Product Browser -->
                        <div class="product-browser">
                            <div class="browser-header">
                                <h5><i class="mdi mdi-package-variant"></i> Available Products at Source Store</h5>
                                <span class="badge badge-light" id="product-count">0 products</span>
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
                                        <option value="in-stock">In Stock Only</option>
                                        <option value="all">All Products</option>
                                        <option value="low">Low Stock</option>
                                    </select>
                                </div>
                            </div>
                            <div class="product-grid" id="product-grid">
                                <div class="text-center text-muted py-5">
                                    <i class="mdi mdi-store-search mdi-48px"></i>
                                    <p>Loading products...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cart Panel (Right) -->
                    <div class="col-lg-4 mb-4">
                        <div class="cart-panel">
                            <div class="cart-header">
                                <h5><i class="mdi mdi-cart"></i> Requisition Items</h5>
                                <span class="cart-badge" id="cart-count">0 items</span>
                            </div>
                            <div class="cart-items" id="cart-items">
                                <div class="cart-empty">
                                    <i class="mdi mdi-cart-outline mdi-48px"></i>
                                    <p>No items added yet</p>
                                    <small class="text-muted">Click "Add" on products to add them</small>
                                </div>
                            </div>
                            <div class="cart-footer">
                                <div class="form-group mb-3">
                                    <label>Priority</label>
                                    <select name="priority" class="form-control form-control-sm">
                                        <option value="normal">Normal</option>
                                        <option value="low">Low</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                <button type="button" class="btn btn-primary btn-block" id="btn-to-step-3" disabled>
                                    Review Requisition <i class="mdi mdi-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-left">
                    <button type="button" class="btn btn-secondary" id="btn-back-to-1">
                        <i class="mdi mdi-arrow-left"></i> Back to Store Selection
                    </button>
                </div>
            </div>

            <!-- STEP 3: Review & Submit -->
            <div class="step-panel" id="step-3">
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Transfer Summary -->
                        <div class="form-section">
                            <h5><i class="mdi mdi-clipboard-check"></i> Review Your Requisition</h5>

                            <div class="transfer-visual mb-4">
                                <div class="transfer-store-box source">
                                    <div class="label">From</div>
                                    <div class="name" id="review-source-name">--</div>
                                </div>
                                <div class="transfer-arrow">
                                    <i class="mdi mdi-arrow-right-bold"></i>
                                </div>
                                <div class="transfer-store-box destination">
                                    <div class="label">To</div>
                                    <div class="name" id="review-dest-name">--</div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered" id="review-items-table">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Product</th>
                                            <th class="text-center" style="width: 120px;">Qty Requested</th>
                                            <th class="text-center" style="width: 120px;">Available</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="review-items-body"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="form-section">
                            <h5><i class="mdi mdi-note-text"></i> Additional Information</h5>
                            <div class="form-group mb-0">
                                <label>Notes (Optional)</label>
                                <textarea name="request_notes" rows="3" class="form-control" placeholder="Any additional notes or reason for this transfer..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-section">
                            <h5><i class="mdi mdi-information"></i> Summary</h5>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Items:</span>
                                <strong id="summary-total-items">0</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Quantity:</span>
                                <strong id="summary-total-qty">0</strong>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Priority:</span>
                                <strong id="summary-priority">Normal</strong>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg btn-block" id="btn-submit">
                                <i class="mdi mdi-send"></i> Submit Requisition
                            </button>
                        </div>
                    </div>
                </div>

                <div class="text-left mt-3">
                    <button type="button" class="btn btn-secondary" id="btn-back-to-2">
                        <i class="mdi mdi-arrow-left"></i> Back to Add Items
                    </button>
                </div>
            </div>

            <!-- Hidden container for cart item inputs -->
            <div id="cart-hidden-inputs"></div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/select2.min.js') }}"></script>
<script>
$(function() {
    // State
    var sourceStoreId = null;
    var destStoreId = null;
    var sourceStoreName = '';
    var destStoreName = '';
    var cartItems = {}; // { productId: { name, code, qty, available } }
    var productsData = []; // All products from source store

    // Store selection handlers
    $('#source-store-grid .store-select-card').on('click', function() {
        var $card = $(this);
        var storeId = $card.data('store-id');

        // Can't select same store as destination
        if (destStoreId && storeId == destStoreId) {
            toastr.error('Source and destination cannot be the same store');
            return;
        }

        $('#source-store-grid .store-select-card').removeClass('selected source');
        $card.addClass('selected source');
        sourceStoreId = storeId;
        sourceStoreName = $card.data('store-name');
        $('#from_store_id').val(storeId);

        updateContinueButton();
    });

    $('#dest-store-grid .store-select-card').on('click', function() {
        var $card = $(this);
        var storeId = $card.data('store-id');

        // Can't select same store as source
        if (sourceStoreId && storeId == sourceStoreId) {
            toastr.error('Source and destination cannot be the same store');
            return;
        }

        $('#dest-store-grid .store-select-card').removeClass('selected destination');
        $card.addClass('selected destination');
        destStoreId = storeId;
        destStoreName = $card.data('store-name');
        $('#to_store_id').val(storeId);

        updateContinueButton();
    });

    function updateContinueButton() {
        $('#btn-to-step-2').prop('disabled', !(sourceStoreId && destStoreId));
    }

    // Step navigation
    $('#btn-to-step-2').on('click', function() {
        goToStep(2);
        loadSourceProducts();
    });

    $('#btn-back-to-1').on('click', function() {
        goToStep(1);
    });

    $('#btn-change-stores').on('click', function() {
        goToStep(1);
    });

    $('#btn-to-step-3').on('click', function() {
        goToStep(3);
        renderReviewTable();
    });

    $('#btn-back-to-2').on('click', function() {
        goToStep(2);
    });

    function goToStep(step) {
        $('.step-panel').removeClass('active');
        $('#step-' + step).addClass('active');

        $('.wizard-step').removeClass('active completed');
        $('.wizard-step').each(function() {
            var s = $(this).data('step');
            if (s < step) $(this).addClass('completed');
            if (s == step) $(this).addClass('active');
        });

        // Update store names in step 2
        if (step == 2) {
            $('#source-store-name').text(sourceStoreName);
            $('#dest-store-name').text(destStoreName);
        }
        if (step == 3) {
            $('#review-source-name').text(sourceStoreName);
            $('#review-dest-name').text(destStoreName);
            updateSummary();
        }

        // Scroll to top
        $('html, body').animate({ scrollTop: 0 }, 300);
    }

    // Load products from source store
    function loadSourceProducts() {
        var $grid = $('#product-grid');
        $grid.html('<div class="loading-overlay"><div class="spinner"></div></div>');

        $.get('{{ route("inventory.purchase-orders.search-products") }}', {
            search: '',
            store_id: sourceStoreId
        }).done(function(data) {
            // Transform the data
            productsData = (data.results || []).map(function(p) {
                return {
                    id: p.id,
                    name: p.text,
                    code: p.code,
                    category_id: p.category_id,
                    source_qty: p.stock || 0
                };
            });
            renderProductGrid(productsData);
        }).fail(function() {
            $grid.html('<div class="text-center text-danger py-5">Failed to load products</div>');
        });
    }

    // Render product grid
    function renderProductGrid(products) {
        var $grid = $('#product-grid');
        var search = ($('#product-search').val() || '').toLowerCase();
        var category = $('#category-filter').val();
        var stockFilter = $('#stock-filter').val();

        var filtered = products.filter(function(p) {
            if (search && p.name.toLowerCase().indexOf(search) === -1 &&
                (p.code || '').toLowerCase().indexOf(search) === -1) return false;
            if (category && p.category_id != category) return false;
            if (stockFilter === 'in-stock' && p.source_qty <= 0) return false;
            if (stockFilter === 'low' && p.source_qty > 10) return false;
            return true;
        });

        $('#product-count').text(filtered.length + ' products');

        if (filtered.length === 0) {
            $grid.html('<div class="text-center text-muted py-5"><i class="mdi mdi-package-variant-closed mdi-48px"></i><p>No products found</p></div>');
            return;
        }

        var html = '';
        filtered.forEach(function(p) {
            var inCart = cartItems[p.id] ? true : false;
            var outOfStock = p.source_qty <= 0;
            var stockClass = outOfStock ? 'out' : (p.source_qty < 10 ? 'low' : 'good');

            html += '<div class="product-card ' + (outOfStock ? 'out-of-stock' : '') + '" data-product-id="' + p.id + '">';
            html += '  <div class="product-info">';
            html += '    <div class="product-name">' + escapeHtml(p.name) + '</div>';
            html += '    <div class="product-code">' + escapeHtml(p.code || 'No code') + '</div>';
            html += '    <div class="stock-levels">';
            html += '      <span class="stock-badge ' + stockClass + '"><i class="mdi mdi-store"></i> Available: ' + p.source_qty + '</span>';
            html += '    </div>';
            html += '  </div>';
            html += '  <div class="add-actions">';
            if (!outOfStock) {
                html += '    <input type="number" class="qty-mini-input" value="' + (inCart ? cartItems[p.id].qty : 1) + '" min="1" max="' + p.source_qty + '" data-product-id="' + p.id + '">';
                html += '    <button type="button" class="btn btn-sm ' + (inCart ? 'btn-success' : 'btn-primary') + ' btn-add-item" data-product-id="' + p.id + '" data-name="' + escapeHtml(p.name) + '" data-code="' + escapeHtml(p.code || '') + '" data-available="' + p.source_qty + '">';
                html += '      <i class="mdi ' + (inCart ? 'mdi-check' : 'mdi-plus') + '"></i> ' + (inCart ? 'Update' : 'Add');
                html += '    </button>';
            } else {
                html += '    <span class="text-muted small">Out of stock</span>';
            }
            html += '  </div>';
            html += '</div>';
        });

        $grid.html(html);
    }

    // Product filters
    var searchTimeout;
    $('#product-search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            renderProductGrid(productsData);
        }, 300);
    });

    $('#category-filter, #stock-filter').on('change', function() {
        renderProductGrid(productsData);
    });

    // Add item to cart
    $(document).on('click', '.btn-add-item', function() {
        var $btn = $(this);
        var productId = $btn.data('product-id');
        var name = $btn.data('name');
        var code = $btn.data('code');
        var available = parseInt($btn.data('available')) || 0;
        var qty = parseInt($btn.closest('.add-actions').find('.qty-mini-input').val()) || 1;

        if (qty > available) {
            toastr.error('Quantity exceeds available stock (' + available + ')');
            return;
        }

        if (qty < 1) {
            toastr.error('Quantity must be at least 1');
            return;
        }

        cartItems[productId] = { name: name, code: code, qty: qty, available: available };

        renderCart();
        renderProductGrid(productsData);
        toastr.success((cartItems[productId] ? 'Updated' : 'Added') + ': ' + qty + 'x ' + name);
    });

    // Render cart
    function renderCart() {
        var $cart = $('#cart-items');
        var $hiddenInputs = $('#cart-hidden-inputs');
        var itemCount = Object.keys(cartItems).length;

        $('#cart-count').text(itemCount + ' items');
        $('#btn-to-step-3').prop('disabled', itemCount === 0);

        if (itemCount === 0) {
            $cart.html('<div class="cart-empty"><i class="mdi mdi-cart-outline mdi-48px"></i><p>No items added yet</p><small class="text-muted">Click "Add" on products to add them</small></div>');
            $hiddenInputs.html('');
            return;
        }

        var html = '';
        var hiddenHtml = '';
        var idx = 0;
        for (var id in cartItems) {
            var item = cartItems[id];
            html += '<div class="cart-item" data-product-id="' + id + '">';
            html += '  <div class="item-info">';
            html += '    <div class="item-name">' + escapeHtml(item.name) + '</div>';
            html += '    <small class="text-muted">' + escapeHtml(item.code) + '</small>';
            html += '  </div>';
            html += '  <div class="item-qty">';
            html += '    <input type="number" class="qty-mini-input cart-qty-input" value="' + item.qty + '" min="1" max="' + item.available + '" data-product-id="' + id + '">';
            html += '    <span class="remove-btn" data-product-id="' + id + '"><i class="mdi mdi-delete"></i></span>';
            html += '  </div>';
            html += '</div>';

            // Hidden inputs for form submission
            hiddenHtml += '<input type="hidden" name="items[' + idx + '][product_id]" value="' + id + '">';
            hiddenHtml += '<input type="hidden" name="items[' + idx + '][requested_qty]" value="' + item.qty + '" class="hidden-qty-' + id + '">';
            idx++;
        }

        $cart.html(html);
        $hiddenInputs.html(hiddenHtml);
    }

    // Update cart quantity
    $(document).on('change', '.cart-qty-input', function() {
        var productId = $(this).data('product-id');
        var newQty = parseInt($(this).val()) || 1;

        if (cartItems[productId]) {
            if (newQty > cartItems[productId].available) {
                newQty = cartItems[productId].available;
                $(this).val(newQty);
                toastr.warning('Maximum available: ' + cartItems[productId].available);
            }
            if (newQty < 1) {
                newQty = 1;
                $(this).val(1);
            }
            cartItems[productId].qty = newQty;
            $('.hidden-qty-' + productId).val(newQty);
            renderProductGrid(productsData);
        }
    });

    // Remove from cart
    $(document).on('click', '.remove-btn', function() {
        var productId = $(this).data('product-id');
        var name = cartItems[productId] ? cartItems[productId].name : '';
        delete cartItems[productId];
        renderCart();
        renderProductGrid(productsData);
        if (name) toastr.info('Removed: ' + name);
    });

    // Render review table
    function renderReviewTable() {
        var $tbody = $('#review-items-body');
        var html = '';

        for (var id in cartItems) {
            var item = cartItems[id];
            var warning = item.qty > item.available ? '<span class="text-danger ml-1"><i class="mdi mdi-alert"></i></span>' : '';

            html += '<tr data-product-id="' + id + '">';
            html += '  <td><strong>' + escapeHtml(item.name) + '</strong><br><small class="text-muted">' + escapeHtml(item.code) + '</small></td>';
            html += '  <td class="text-center"><strong>' + item.qty + '</strong></td>';
            html += '  <td class="text-center">' + item.available + warning + '</td>';
            html += '  <td class="text-center"><span class="text-danger" style="cursor:pointer;" onclick="removeFromReview(\'' + id + '\')"><i class="mdi mdi-delete"></i></span></td>';
            html += '</tr>';
        }

        if (!html) {
            html = '<tr><td colspan="4" class="text-center text-muted">No items</td></tr>';
        }

        $tbody.html(html);
    }

    window.removeFromReview = function(productId) {
        var name = cartItems[productId] ? cartItems[productId].name : '';
        delete cartItems[productId];
        renderReviewTable();
        renderCart();
        updateSummary();

        if (name) toastr.info('Removed: ' + name);

        if (Object.keys(cartItems).length === 0) {
            goToStep(2);
            toastr.warning('All items removed. Please add items to continue.');
        }
    };

    function updateSummary() {
        var itemCount = Object.keys(cartItems).length;
        var totalQty = 0;
        for (var id in cartItems) {
            totalQty += cartItems[id].qty;
        }

        $('#summary-total-items').text(itemCount);
        $('#summary-total-qty').text(totalQty);
        $('#summary-priority').text(ucfirst($('select[name="priority"]').val() || 'normal'));
    }

    $('select[name="priority"]').on('change', updateSummary);

    // Form submission via AJAX
    $('#requisition-form').on('submit', function(e) {
        e.preventDefault();

        if (Object.keys(cartItems).length === 0) {
            toastr.error('Please add at least one item');
            goToStep(2);
            return false;
        }
        if (!sourceStoreId || !destStoreId) {
            toastr.error('Please select both stores');
            goToStep(1);
            return false;
        }

        var $btn = $('#btn-submit');
        var originalText = $btn.html();
        $btn.html('<i class="mdi mdi-loading mdi-spin"></i> Submitting...').prop('disabled', true);

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    // Redirect to requisition detail or index
                    setTimeout(function() {
                        window.location.href = response.redirect || '{{ route("inventory.requisitions.index") }}';
                    }, 1000);
                } else {
                    toastr.error(response.message || 'Failed to create requisition');
                    $btn.html(originalText).prop('disabled', false);
                }
            },
            error: function(xhr) {
                var message = 'An error occurred';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    if (xhr.responseJSON.errors) {
                        var errors = xhr.responseJSON.errors;
                        message = Object.values(errors).flat().join('<br>');
                    }
                }
                toastr.error(message);
                $btn.html(originalText).prop('disabled', false);
            }
        });
    });

    // Helper functions
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function ucfirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
});
</script>
@endsection
