@extends('admin.layouts.app')
@section('title', 'My Stock & Utilization')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'My Stock')

@section('content')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    .store-selector-card {
        border-left: 4px solid #17a2b8;
    }

    .stock-card {
        border-radius: 12px;
        transition: transform 0.2s, box-shadow 0.2s;
        border: 1px solid #e9ecef;
    }

    .stock-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
    }

    .badge-status {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
        border-radius: 4px;
    }

    .badge-low {
        background-color: #ffeeba;
        color: #856404;
    }

    .badge-out {
        background-color: #f8d7da;
        color: #721c24;
    }

    .badge-ok {
        background-color: #d4edda;
        color: #155724;
    }

    .nav-tabs-premium .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: #495057;
        font-weight: 500;
        padding: 0.75rem 1.25rem;
    }

    .nav-tabs-premium .nav-link.active {
        color: #007bff;
        border-bottom-color: #007bff;
        background: transparent;
    }

    .patient-result-item {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f1f3f5;
        transition: background-color 0.15s;
    }

    .patient-result-item:hover {
        background-color: #f8f9fa;
    }

    .patient-suggestions-box {
        position: absolute;
        z-index: 1050;
        background: white;
        border: 1px solid #ced4da;
        border-radius: 4px;
        max-height: 250px;
        overflow-y: auto;
        width: 100%;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .tariff-preview-panel {
        background-color: #f8f9fa;
        border: 1px dashed #dee2e6;
        border-radius: 6px;
        padding: 12px;
        margin-top: 10px;
    }
</style>

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header with active store info and switcher -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
            <div>
                <h3 class="mb-0">My Stock &amp; Utilization</h3>
                <p class="text-muted mb-0">Monitor store inventory level and record stock usage logs</p>
            </div>
            <div class="mt-3 mt-md-0 d-flex align-items-center">
                <div class="mr-2">
                    <span class="text-muted small d-block">Active Operating Store</span>
                    <strong class="text-info">{{ $activeStore->store_name ?? 'None' }}</strong>
                </div>
                <div>
                    <select id="store-switcher" class="form-control form-control-sm">
                        @foreach($myStores as $store)
                        <option value="{{ $store->id }}" {{ $activeStore && $activeStore->id == $store->id ? 'selected' : '' }}>
                            {{ $store->store_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if(!$activeStore)
        <div class="alert alert-warning">
            <i class="mdi mdi-alert mr-2"></i> No active store context resolved for your role or department. Please contact the administrator.
        </div>
        @else
        <!-- Store Context Details Banner -->
        <div class="card store-selector-card shadow-sm mb-4">
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Store Type / Role</small>
                        <span class="font-weight-bold text-uppercase">{{ str_replace('_', ' ', $activeStore->distribution_role) }}</span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Store Code</small>
                        <span class="font-weight-bold">{{ $activeStore->code ?? 'N/A' }}</span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Associated Ward / Dept</small>
                        <span>{{ $activeStore->ward->ward_name ?? $activeStore->department->name ?? 'Global Store' }}</span>
                    </div>
                    <div class="col-md-3 text-md-right mt-2 mt-md-0">
                        <a href="{{ route('inventory.requisitions.index') }}" class="btn btn-secondary btn-sm">
                            <i class="mdi mdi-arrow-left"></i> Requisitions Index
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabbed Interface -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white pb-0">
                <ul class="nav nav-tabs nav-tabs-premium border-0" id="myStockTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="grid-tab" data-toggle="tab" href="#grid-pane" role="tab" aria-controls="grid-pane" aria-selected="true">
                            <i class="mdi mdi-grid mr-1"></i> Stock Cards
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="history-tab" data-toggle="tab" href="#history-pane" role="tab" aria-controls="history-pane" aria-selected="false">
                            <i class="mdi mdi-history mr-1"></i> Utilization &amp; Transaction History
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content" id="myStockTabsContent">

                    <!-- TAB 1: Stock Grid -->
                    <div class="tab-pane fade show active" id="grid-pane" role="tabpanel" aria-labelledby="grid-tab">
                        <!-- Filters row -->
                        <div class="row mb-4 align-items-center">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white border-right-0"><i class="mdi mdi-magnify text-muted"></i></span>
                                    </div>
                                    <input type="text" id="product-search" class="form-control border-left-0" placeholder="Search product name or code...">
                                </div>
                            </div>
                            <div class="col-md-4 mt-2 mt-md-0">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-secondary btn-sm stock-filter-btn active" data-filter="all">All</button>
                                    <button type="button" class="btn btn-outline-warning btn-sm stock-filter-btn" data-filter="low">Low Stock</button>
                                    <button type="button" class="btn btn-outline-danger btn-sm stock-filter-btn" data-filter="out">Out of Stock</button>
                                    <button type="button" class="btn btn-outline-info btn-sm stock-filter-btn" data-filter="expiring_soon">Expiring Soon</button>
                                    <button type="button" class="btn btn-outline-dark btn-sm stock-filter-btn" data-filter="expired">Expired</button>
                                </div>
                            </div>
                            <div class="col-md-4 text-md-right mt-2 mt-md-0">
                                <span class="text-muted small" id="total-count-label">Loading items...</span>
                            </div>
                        </div>

                        <!-- Grid container -->
                        <div class="row" id="product-grid-container">
                            <!-- Dynamically loaded -->
                        </div>

                        <!-- Pagination container -->
                        <div class="d-flex justify-content-center mt-4" id="pagination-container">
                            <!-- Pagination links -->
                        </div>
                    </div>

                    <!-- TAB 2: History Table -->
                    <div class="tab-pane fade" id="history-pane" role="tabpanel" aria-labelledby="history-tab">
                        <!-- History Filters -->
                        <div class="row mb-3">
                            <div class="col-md-2 mb-2">
                                <label for="history-start-date" class="small text-muted mb-1 d-block">Start Date</label>
                                <input type="date" id="history-start-date" class="form-control form-control-sm" value="{{ date('Y-m-01') }}">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label for="history-end-date" class="small text-muted mb-1 d-block">End Date</label>
                                <input type="date" id="history-end-date" class="form-control form-control-sm" value="{{ date('Y-m-t') }}">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label for="history-product-search" class="small text-muted mb-1 d-block">Product</label>
                                <div class="position-relative">
                                    <input type="text" id="history-product-search" class="form-control form-control-sm" placeholder="Search product..." autocomplete="off">
                                    <input type="hidden" id="history-product-id">
                                    <div id="history-product-suggestions" class="position-absolute w-100 bg-white border rounded shadow-sm d-none" style="z-index: 1000; max-height: 200px; overflow-y: auto;">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label for="history-category" class="small text-muted mb-1 d-block">Category</label>
                                <select id="history-category" class="form-control form-control-sm">
                                    <option value="">All Categories</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label for="history-type" class="small text-muted mb-1 d-block">Transaction Type</label>
                                <select id="history-type" class="form-control form-control-sm">
                                    <option value="">All Types</option>
                                    <option value="in">Inbound (Added/Received)</option>
                                    <option value="out">Outbound (Dispensed/Used)</option>
                                    <option value="adjustment">Adjustments</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label for="history-performer-search" class="small text-muted mb-1 d-block">Performer</label>
                                <div class="position-relative">
                                    <input type="text" id="history-performer-search" class="form-control form-control-sm" placeholder="Search staff..." autocomplete="off">
                                    <input type="hidden" id="history-performer-id">
                                    <div id="history-performer-suggestions" class="position-absolute w-100 bg-white border rounded shadow-sm d-none" style="z-index: 1000; max-height: 200px; overflow-y: auto;">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2 mt-md-4">
                                <button id="apply-history-filters" class="btn btn-primary btn-sm w-100">
                                    <i class="mdi mdi-filter"></i> Apply Filters
                                </button>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div id="history-summary-cards" class="row mb-3 d-none"></div>

                        <!-- Data Table -->
                        <div class="table-responsive">
                            <table id="history-table" class="table table-sm table-bordered table-striped" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Product Name</th>
                                        <th>Batch Number</th>
                                        <th>Type</th>
                                        <th>Qty</th>
                                        <th>Details / Reference</th>
                                        <th>Recorded By</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- MODAL: Record Stock Utilization -->
<div class="modal fade" id="utilizationModal" tabindex="-1" role="dialog" aria-labelledby="utilizationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="utilizationModalLabel">Record Stock Utilization</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="utilization-form">
                @csrf
                <input type="hidden" name="store_id" value="{{ $activeStore ? $activeStore->id : '' }}">
                <input type="hidden" name="product_id" id="modal-product-id">

                <div class="modal-body">
                    <!-- Product Info Header -->
                    <div class="p-3 mb-3 bg-light rounded d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small d-block">PRODUCT NAME</span>
                            <strong class="h5 mb-0 text-primary" id="modal-product-name">Product Name</strong>
                        </div>
                        <div class="text-right">
                            <span class="text-muted small d-block">AVAILABLE STOCK</span>
                            <strong class="h5 mb-0 text-success" id="modal-product-qty">0</strong>
                        </div>
                    </div>

                    <!-- Step 1: Batch & Quantity -->
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Rotation Strategy / Batch</label>
                            <select name="strategy" id="strategy-selector" class="form-control">
                                <option value="fifo">FIFO (First In, First Out)</option>
                                <option value="fefo">FEFO (First Expired, First Out)</option>
                                <option value="batch">Select Specific Batch</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group d-none" id="specific-batch-group">
                            <label class="font-weight-bold text-danger">Select Batch *</label>
                            <select name="stock_batch_id" id="stock-batch-selector" class="form-control">
                                <!-- Loaded via AJAX -->
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">Quantity *</label>
                            <input type="number" name="qty" id="util-qty" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">Unit</label>
                            <select name="unit_packaging" id="util-packaging" class="form-control">
                                <option value="" data-base="1">Loading...</option>
                            </select>
                            <input type="hidden" name="unit" id="util-unit-name">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">Reason *</label>
                            <select name="reason" class="form-control" required>
                                <option value="Clinical Treatment">Clinical Treatment</option>
                                <option value="Department Stationary">Department Stationary</option>
                                <option value="Internal Consumables">Internal Consumables</option>
                                <option value="Expiry/Waste Writeoff">Expiry/Waste Writeoff</option>
                                <option value="Damaged/Loss">Damaged/Loss</option>
                            </select>
                        </div>
                    </div>

                    <!-- Duration range input -->
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Utilization Start Date</label>
                            <input type="datetime-local" name="start_date" class="form-control">
                            <small class="text-muted">When did the utilization begin? (Optional)</small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Utilization End Date</label>
                            <input type="datetime-local" name="end_date" class="form-control">
                            <small class="text-muted">When did it end? (Optional, e.g. continuous use)</small>
                        </div>
                    </div>

                    <!-- Step 2: Destination Toggle -->
                    <hr>
                    <div class="form-group">
                        <label class="font-weight-bold d-block">Utilization Destination</label>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="dest-internal" name="utilization_type" value="internal" class="custom-control-input" checked>
                            <label class="custom-control-label" for="dest-internal">Department / Internal Use</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="dest-patient" name="utilization_type" value="patient" class="custom-control-input">
                            <label class="custom-control-label" for="dest-patient">Patient Administered</label>
                        </div>
                    </div>

                    <!-- Patient specific details -->
                    <div id="patient-assignment-panel" class="d-none border p-3 rounded bg-white">
                        <div class="form-group position-relative">
                            <label class="font-weight-bold text-primary">Search Patient *</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="mdi mdi-account-search"></i></span>
                                </div>
                                <input type="text" id="patient-search-input" class="form-control" placeholder="Search by Name, HMO No, or File Number...">
                            </div>
                            <input type="hidden" name="patient_id" id="selected-patient-id">
                            <div class="patient-suggestions-box d-none" id="suggestions-box"></div>
                        </div>

                        <!-- Selected Patient Summary -->
                        <div id="patient-summary" class="alert alert-info py-2 d-none">
                            <strong>Selected Patient:</strong> <span id="summary-patient-name"></span>
                            <br><small>HMO/Scheme: <span id="summary-hmo-name"></span> | File No: <span id="summary-file-no"></span></small>
                        </div>

                        <!-- Billing toggle & HMO tariff preview -->
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="is_billed" value="1" class="custom-control-input" id="is-billed-checkbox" checked>
                                <label class="custom-control-label font-weight-bold text-success" for="is-billed-checkbox">Bill Patient for this item</label>
                            </div>
                            <small class="text-muted">If checked, a billing request will automatically be created under the patient's HMO / Tariff pricing.</small>
                        </div>

                        <!-- Tariff Preview Panel -->
                        <div class="tariff-preview-panel d-none" id="tariff-preview">
                            <h6 class="border-bottom pb-1 mb-2 font-weight-bold text-secondary"><i class="mdi mdi-calculator"></i> HMO Tariff pricing preview</h6>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted d-block">Patient Copay (Payable)</small>
                                    <strong class="h5 text-dark" id="preview-payable">0.00</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">HMO Coverage (Claimable)</small>
                                    <strong class="h5 text-primary" id="preview-claim">0.00</strong>
                                </div>
                            </div>
                            <small class="text-info mt-2 d-block" id="preview-scheme"></small>
                        </div>
                    </div>

                    <!-- General Notes -->
                    <div class="form-group mt-3">
                        <label class="font-weight-bold">Additional Notes / Comments</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Describe usage particulars..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="btn-submit-utilization">
                        <i class="mdi mdi-check"></i> Record Utilization
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const activeStoreId = "{{ $activeStore ? $activeStore->id : '' }}";
        if (!activeStoreId) return;

        let currentPage = 1;
        let productsList = [];

        // Trigger initial load
        loadProducts(1);

        // Switch active store
        document.getElementById('store-switcher').addEventListener('change', function() {
            window.location.href = `?store_id=${this.value}`;
        });

        // Filtering logic for Product Grid
        document.getElementById('product-search').addEventListener('input', debounce(function() {
            loadProducts(1);
        }, 300));

        $('.stock-filter-btn').on('click', function() {
            $('.stock-filter-btn').removeClass('active');
            $(this).addClass('active');
            loadProducts(1);
        });

        // Load products grid
        function loadProducts(page = 1) {
            currentPage = page;
            const searchVal = document.getElementById('product-search').value;
            const levelVal = document.querySelector('.stock-filter-btn.active').getAttribute('data-filter');
            const gridContainer = document.getElementById('product-grid-container');
            const countLabel = document.getElementById('total-count-label');

            gridContainer.innerHTML = '<div class="col-12 text-center py-5"><i class="mdi mdi-loading mdi-spin h3"></i><p>Loading products...</p></div>';

            fetch(`{{ route('inventory.requisitions.my-stock.products') }}?store_id=${activeStoreId}&search=${encodeURIComponent(searchVal)}&stock_level=${levelVal}&page=${page}`)
                .then(res => res.json())
                .then(data => {
                    productsList = data.data || [];
                    countLabel.textContent = `Showing ${data.from || 0}-${data.to || 0} of ${data.total || 0} items`;

                    if (productsList.length === 0) {
                        gridContainer.innerHTML = '<div class="col-12 text-center py-5 text-muted"><i class="mdi mdi-dropbox h1"></i><p>No products found matching the filters.</p></div>';
                        document.getElementById('pagination-container').innerHTML = '';
                        return;
                    }

                    gridContainer.innerHTML = '';
                    productsList.forEach(item => {
                        const p = item.product;
                        if (!p) return;

                        let reorderThreshold = item.reorder_level > 0 ? item.reorder_level : (p.reorder_alert || 0);

                        let badgeClass = 'badge-ok';
                        let badgeLabel = 'In Stock';
                        if (item.current_quantity <= 0) {
                            badgeClass = 'badge-out';
                            badgeLabel = 'Out of Stock';
                        } else if (item.current_quantity <= reorderThreshold) {
                            badgeClass = 'badge-low';
                            badgeLabel = 'Low Stock';
                        }

                        // Format expiry date helper: "29 Nov 2026"
                        function fmtExpiry(dateStr) {
                            if (!dateStr) return { label: 'No Exp', cls: 'text-muted' };
                            const d = new Date(dateStr);
                            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                            const label = `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
                            const diffDays = Math.ceil((d - new Date()) / (1000 * 60 * 60 * 24));
                            if (diffDays < 0)   return { label: label + ' ⚠', cls: 'text-danger font-weight-bold' };
                            if (diffDays <= 90) return { label, cls: 'text-warning font-weight-bold' };
                            return { label, cls: 'text-muted' };
                        }

                        let batchesHtml = '';
                        if (item.batches && item.batches.length > 0) {
                            batchesHtml = `<div class="mt-2 mb-2">
                                <div class="d-flex justify-content-between" style="font-size:0.68rem; font-weight:600; color:#aaa; text-transform:uppercase; border-bottom:1px solid #eee; padding-bottom:2px; margin-bottom:4px;">
                                    <span>Batch</span><span>Qty</span><span>Expiry</span>
                                </div>`;
                            item.batches.forEach(b => {
                                const exp = fmtExpiry(b.expiry_date);
                                const shortBatch = b.batch_number.length > 12 ? b.batch_number.substring(0, 11) + '…' : b.batch_number;
                                batchesHtml += `<div class="d-flex justify-content-between align-items-start" style="font-size:0.72rem; margin-bottom:3px; flex-wrap:wrap; gap:1px;">
                                    <span class="text-muted" style="max-width:42%; word-break:break-all; line-height:1.3;" title="${b.batch_number}">${shortBatch}</span>
                                    <span class="font-weight-bold text-dark" style="min-width:20px; text-align:center;">${b.current_qty}</span>
                                    <span class="${exp.cls}" style="text-align:right;">${exp.label}</span>
                                </div>`;
                            });
                            batchesHtml += '</div>';
                        } else {
                            batchesHtml = '<div class="text-muted font-italic mb-2" style="font-size:0.72rem;">No active batches</div>';
                        }

                        const card = `
                        <div class="col-md-4 col-lg-3 mb-4">
                            <div class="card stock-card h-100 shadow-sm bg-white">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge-status ${badgeClass}">${badgeLabel}</span>
                                        <small class="text-muted font-weight-bold">${p.product_code || 'No Code'}</small>
                                    </div>
                                    <h6 class="font-weight-bold text-dark mb-1">${p.product_name}</h6>
                                    <small class="text-muted mb-2">${p.category ? p.category.category_name : 'Uncategorized'}</small>
                                    
                                    <div class="mt-auto pt-3 border-top">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small">Available Qty:</span>
                                            <span class="font-weight-bold ${item.current_quantity <= reorderThreshold ? 'text-danger' : 'text-success'}">${item.current_quantity}</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small">Reorder Threshold:</span>
                                            <span>${reorderThreshold}</span>
                                        </div>
                                        ${batchesHtml}
                                        <button class="btn btn-outline-info btn-sm btn-block record-util-btn" data-id="${item.product_id}" data-name="${p.product_name}" data-qty="${item.current_quantity}">
                                            <i class="mdi mdi-pencil-box-outline"></i> Record Usage
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                        gridContainer.insertAdjacentHTML('beforeend', card);
                    });

                    // Attach click listeners to btn
                    document.querySelectorAll('.record-util-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            openUtilizationModal(this.dataset.id, this.dataset.name, this.dataset.qty);
                        });
                    });

                    renderPagination(data);
                });
        }

        // Pagination builder
        function renderPagination(data) {
            const container = document.getElementById('pagination-container');
            container.innerHTML = '';

            if (data.last_page <= 1) return;

            let nav = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm mb-0">';

            // Previous page
            nav += `<li class="page-item ${data.current_page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${data.current_page - 1}">&laquo;</a>
                </li>`;

            // Render pages
            for (let i = 1; i <= data.last_page; i++) {
                if (i === 1 || i === data.last_page || (i >= data.current_page - 2 && i <= data.current_page + 2)) {
                    nav += `<li class="page-item ${data.current_page === i ? 'active' : ''}">
                            <a class="page-link" href="#" data-page="${i}">${i}</a>
                        </li>`;
                } else if (i === 2 || i === data.last_page - 1) {
                    nav += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }

            // Next page
            nav += `<li class="page-item ${data.current_page === data.last_page ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${data.current_page + 1}">&raquo;</a>
                </li>`;

            nav += '</ul></nav>';
            container.innerHTML = nav;

            container.querySelectorAll('.page-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = parseInt(this.dataset.page);
                    if (page && page !== data.current_page) {
                        loadProducts(page);
                    }
                });
            });
        }

        // Modal Opening & Setup
        function openUtilizationModal(productId, productName, currentQty) {
            document.getElementById('modal-product-id').value = productId;
            document.getElementById('modal-product-name').textContent = productName;
            document.getElementById('modal-product-qty').textContent = currentQty;

            // Reset form
            document.getElementById('utilization-form').reset();
            document.getElementById('patient-assignment-panel').classList.add('d-none');
            document.getElementById('specific-batch-group').classList.add('d-none');
            document.getElementById('tariff-preview').classList.add('d-none');
            document.getElementById('patient-summary').classList.add('d-none');
            document.getElementById('selected-patient-id').value = '';

            // Load specific batches if strategy is changed
            const batchSelect = document.getElementById('stock-batch-selector');
            batchSelect.innerHTML = '<option value="">Loading batches...</option>';

            // Trigger Modal Open
            $('#utilizationModal').modal('show');

            // Fetch packagings for this product
            const $pkgSelect = $('#util-packaging');
            $pkgSelect.html('<option value="" data-base="1">Loading...</option>');

            $.ajax({
                url: '/products/' + productId + '/packagings',
                method: 'GET',
                success: function(response) {
                    const baseUnit = response.base_unit_name || 'units';
                    $pkgSelect.html('<option value="" data-base="1">' + baseUnit + ' (base)</option>');
                    document.getElementById('util-unit-name').value = baseUnit;

                    if (response.packagings && response.packagings.length > 0) {
                        response.packagings.forEach(function(pkg) {
                            let isDefault = pkg.is_default_purchase ? ' selected' : '';
                            $pkgSelect.append('<option value="' + pkg.id + '" data-base="' + pkg.base_unit_qty + '"' + isDefault + '>' + pkg.name + ' (' + parseFloat(pkg.base_unit_qty) + ' ' + baseUnit + ')</option>');
                        });
                    }

                    // Trigger change to update hidden unit name
                    $pkgSelect.trigger('change');
                },
                error: function() {
                    $pkgSelect.html('<option value="" data-base="1">units (base)</option>');
                    document.getElementById('util-unit-name').value = 'units';
                }
            });

            $pkgSelect.off('change').on('change', function() {
                let selectedText = $(this).find(':selected').text();
                // remove the (base) or (X units) part for cleaner DB logging if desired
                selectedText = selectedText.split(' (')[0];
                document.getElementById('util-unit-name').value = selectedText;
            });
        }

        // Toggle specific batch dropdown
        document.getElementById('strategy-selector').addEventListener('change', function() {
            const batchGroup = document.getElementById('specific-batch-group');
            if (this.value === 'batch') {
                batchGroup.classList.remove('d-none');
                loadStoreBatches();
            } else {
                batchGroup.classList.add('d-none');
            }
        });

        function loadStoreBatches() {
            const productId = document.getElementById('modal-product-id').value;
            const selector = document.getElementById('stock-batch-selector');
            selector.innerHTML = '<option value="">Loading batches...</option>';

            fetch(`{{ route('inventory.requisitions.my-stock.batches') }}?store_id=${activeStoreId}&product_id=${productId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.length === 0) {
                        selector.innerHTML = '<option value="">No active stock batches found</option>';
                        return;
                    }
                    selector.innerHTML = '<option value="">-- Choose Batch --</option>';
                    data.forEach(batch => {
                        const expiryText = batch.expiry_date ? ` (Exp: ${batch.expiry_date})` : ' (No Expiry)';
                        selector.insertAdjacentHTML('beforeend', `<option value="${batch.id}">Batch ${batch.batch_number} - Qty: ${batch.current_qty}${expiryText}</option>`);
                    });
                });
        }

        // Toggle destination panels
        document.getElementById('dest-internal').addEventListener('change', function() {
            document.getElementById('patient-assignment-panel').classList.add('d-none');
        });

        document.getElementById('dest-patient').addEventListener('change', function() {
            document.getElementById('patient-assignment-panel').classList.remove('d-none');
        });

        // Patient AJAX Live Search
        const searchInput = document.getElementById('patient-search-input');
        const suggestionsBox = document.getElementById('suggestions-box');

        searchInput.addEventListener('input', debounce(function() {
            const term = this.value;
            if (term.length < 2) {
                suggestionsBox.innerHTML = '';
                suggestionsBox.classList.add('d-none');
                return;
            }

            fetch(`{{ route('inventory.requisitions.my-stock.patients') }}?term=${encodeURIComponent(term)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.length === 0) {
                        suggestionsBox.innerHTML = '<div class="p-2 text-muted small">No patients found</div>';
                        suggestionsBox.classList.remove('d-none');
                        return;
                    }

                    suggestionsBox.innerHTML = '';
                    suggestionsBox.classList.remove('d-none');
                    data.forEach(p => {
                        const row = `
                        <div class="patient-result-item" data-id="${p.id}" data-name="${p.name}" data-hmo="${p.hmo_name}" data-fileno="${p.file_no}">
                            <strong>${p.name}</strong> <span class="badge badge-secondary float-right">${p.file_no}</span>
                            <br><small class="text-muted">HMO: ${p.hmo_name}</small>
                        </div>
                    `;
                        suggestionsBox.insertAdjacentHTML('beforeend', row);
                    });

                    // Attach click listeners to suggestion items
                    suggestionsBox.querySelectorAll('.patient-result-item').forEach(item => {
                        item.addEventListener('click', function() {
                            selectPatient(this.dataset.id, this.dataset.name, this.dataset.hmo, this.dataset.fileno);
                        });
                    });
                });
        }, 250));

        // Handle clicking outside suggestions
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                suggestionsBox.classList.add('d-none');
            }
        });

        function selectPatient(id, name, hmoName, fileNo) {
            document.getElementById('selected-patient-id').value = id;
            document.getElementById('summary-patient-name').textContent = name;
            document.getElementById('summary-hmo-name').textContent = hmoName;
            document.getElementById('summary-file-no').textContent = fileNo;

            document.getElementById('patient-summary').classList.remove('d-none');
            suggestionsBox.classList.add('d-none');
            searchInput.value = '';

            // Trigger Tariff Preview calculation
            fetchTariffPreview();
        }

        // Trigger Tariff Preview on quantity changes or billing checkbox toggle
        document.getElementById('util-qty').addEventListener('input', function() {
            fetchTariffPreview();
        });

        document.getElementById('is-billed-checkbox').addEventListener('change', function() {
            if (this.checked) {
                fetchTariffPreview();
            } else {
                document.getElementById('tariff-preview').classList.add('d-none');
            }
        });

        function fetchTariffPreview() {
            const patientId = document.getElementById('selected-patient-id').value;
            const productId = document.getElementById('modal-product-id').value;
            const qty = document.getElementById('util-qty').value;
            const isBilled = document.getElementById('is-billed-checkbox').checked;

            if (!patientId || !productId || qty <= 0 || !isBilled) {
                document.getElementById('tariff-preview').classList.add('d-none');
                return;
            }

            fetch(`{{ route('inventory.requisitions.my-stock.tariff-preview') }}?patient_id=${patientId}&product_id=${productId}&qty=${qty}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('preview-payable').textContent = formatCurrency(data.payable_amount);
                        document.getElementById('preview-claim').textContent = formatCurrency(data.claims_amount);
                        document.getElementById('preview-scheme').textContent = `Pricing scheme: ${data.hmo_name} (${data.coverage_mode.toUpperCase()})`;
                        document.getElementById('tariff-preview').classList.remove('d-none');
                    }
                });
        }

        function formatCurrency(amount) {
            return parseFloat(amount).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Form submission
        document.getElementById('utilization-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('btn-submit-utilization');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-1"></i> Recording...';

            const formData = new FormData(this);
            const data = {};
            formData.forEach((val, key) => {
                if (key === 'is_billed') {
                    data[key] = true;
                } else {
                    data[key] = val;
                }
            });
            if (!data.is_billed) data.is_billed = false;

            // Convert quantity based on selected packaging
            const baseFactor = parseFloat($('#util-packaging').find(':selected').data('base')) || 1;
            if (baseFactor > 1 && data.qty) {
                data.qty = Math.round(parseFloat(data.qty) * baseFactor);
            }

            // Remove the dropdown's raw value, we already set the hidden "unit" field to the text
            delete data.unit_packaging;

            // Verify patient selected if patient use
            if (data.utilization_type === 'patient' && !data.patient_id) {
                toastr.error('Please search and select a patient first.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="mdi mdi-check"></i> Record Utilization';
                return;
            }

            fetch(`{{ route('inventory.requisitions.my-stock.utilize') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(data)
                })
                .then(res => res.json())
                .then(res => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="mdi mdi-check"></i> Record Utilization';

                    if (res.success) {
                        toastr.success(res.message);
                        $('#utilizationModal').modal('hide');
                        loadProducts(currentPage);
                        // Reload history if table is initialized
                        if ($.fn.DataTable.isDataTable('#history-table')) {
                            $('#history-table').DataTable().ajax.reload();
                        }
                    } else {
                        toastr.error(res.message || 'Utilization failed');
                    }
                })
                .catch(err => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="mdi mdi-check"></i> Record Utilization';
                    toastr.error('An unexpected error occurred.');
                });
        });

        // ==========================================
        // History DataTable Initialization
        // ==========================================

        let historyTable = $('#history-table').DataTable({
            dom: 'Bfrtip',
            iDisplayLength: 25,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print'],
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('inventory.requisitions.my-stock.history') }}",
                type: "GET",
                data: function(d) {
                    d.store_id = activeStoreId;
                    d.start_date = $('#history-start-date').val();
                    d.end_date = $('#history-end-date').val();
                    d.transaction_type = $('#history-type').val();
                    d.product_id = $('#history-product-id').val();
                    d.category_id = $('#history-category').val();
                    d.performer_id = $('#history-performer-id').val();
                }
            },
            columns: [{
                    data: "created_at",
                    name: "stock_batch_transactions.created_at"
                },
                {
                    data: "product",
                    name: "stockBatch.product.product_name"
                },
                {
                    data: "batch",
                    name: "stockBatch.batch_number"
                },
                {
                    data: "type",
                    name: "stock_batch_transactions.type"
                },
                {
                    data: "qty",
                    name: "stock_batch_transactions.qty"
                },
                {
                    data: "reference",
                    name: "notes"
                },
                {
                    data: "performer",
                    name: "performer.name"
                }
            ],
            drawCallback: function(settings) {
                let json = this.api().ajax.json();
                if (json && json.summary_stats) {
                    renderSummaryCards(json.summary_stats);
                }
            },
            order: [
                [0, 'desc']
            ]
        });

        function renderSummaryCards(stats) {
            const container = document.getElementById('history-summary-cards');
            if(!container) return;
            
            container.classList.remove('d-none');
            
            if (stats.mode === 'product') {
                container.innerHTML = `
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="card bg-light border-0 shadow-sm h-100">
                            <div class="card-body p-3 text-center">
                                <h6 class="text-muted mb-1"><i class="mdi mdi-ray-start"></i> Opening Balance</h6>
                                <h3 class="mb-0 text-dark">${stats.opening_balance}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="card shadow-sm h-100" style="background-color:#f0fdf4; border-left: 4px solid #22c55e;">
                            <div class="card-body p-3 text-center">
                                <h6 class="text-success mb-1"><i class="mdi mdi-arrow-down-bold"></i> Total In</h6>
                                <h3 class="mb-0 text-success">+${stats.total_in}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="card shadow-sm h-100" style="background-color:#fef2f2; border-left: 4px solid #ef4444;">
                            <div class="card-body p-3 text-center">
                                <h6 class="text-danger mb-1"><i class="mdi mdi-arrow-up-bold"></i> Total Out</h6>
                                <h3 class="mb-0 text-danger">-${stats.total_out}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="card shadow-sm h-100" style="background-color:#eff6ff; border-left: 4px solid #3b82f6;">
                            <div class="card-body p-3 text-center">
                                <h6 class="text-primary mb-1"><i class="mdi mdi-ray-end"></i> Closing Balance</h6>
                                <h3 class="mb-0 text-primary">${stats.closing_balance}</h3>
                            </div>
                        </div>
                    </div>
                    ${stats.total_damaged > 0 ? `
                    <div class="col-12 mt-2">
                        <div class="alert py-2 mb-0 shadow-sm" style="background-color:#fffbeb; border:1px solid #fcd34d; color:#b45309;">
                            <i class="mdi mdi-alert"></i> <strong>Warning:</strong> ${stats.total_damaged} units of this product were damaged or expired during this period.
                        </div>
                    </div>
                    ` : ''}
                `;
            } else {
                container.innerHTML = `
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="card bg-light border-0 shadow-sm h-100">
                            <div class="card-body p-3 text-center">
                                <h6 class="text-muted mb-1"><i class="mdi mdi-tag-multiple"></i> Products Touched</h6>
                                <h3 class="mb-0 text-dark">${stats.unique_products}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="card shadow-sm h-100" style="background-color:#f0fdf4; border-left: 4px solid #22c55e;">
                            <div class="card-body p-3 text-center">
                                <h6 class="text-success mb-1"><i class="mdi mdi-login"></i> Items Received</h6>
                                <h3 class="mb-0 text-success">${stats.total_in}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="card shadow-sm h-100" style="background-color:#fffbeb; border-left: 4px solid #f59e0b;">
                            <div class="card-body p-3 text-center">
                                <h6 class="text-warning mb-1" style="color: #d97706 !important;"><i class="mdi mdi-logout"></i> Items Dispensed</h6>
                                <h3 class="mb-0 text-warning" style="color: #d97706 !important;">${stats.total_out}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="card shadow-sm h-100" style="${stats.total_damaged > 0 ? 'background-color:#fef2f2; border-left: 4px solid #ef4444;' : 'background-color:#f8fafc; border-left: 4px solid #94a3b8;'}">
                            <div class="card-body p-3 text-center">
                                <h6 class="${stats.total_damaged > 0 ? 'text-danger' : 'text-muted'} mb-1"><i class="mdi mdi-archive-remove"></i> Damaged / Expired</h6>
                                <h3 class="mb-0 ${stats.total_damaged > 0 ? 'text-danger' : 'text-muted'}">${stats.total_damaged}</h3>
                            </div>
                        </div>
                    </div>
                `;
            }
        }

        $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            if (e.target.id === 'history-tab') {
                historyTable.columns.adjust().responsive?.recalc();
            }
        });

        // Bind the apply filters button to reload the table
        $('#apply-history-filters').off('click').on('click', function() {
            if (historyTable) {
                historyTable.ajax.reload();
            }
        });

        // Custom Product Autocomplete with debounce and abort
        const productSearchInput = document.getElementById('history-product-search');
        const productIdHidden = document.getElementById('history-product-id');
        const productSuggestions = document.getElementById('history-product-suggestions');
        let productSearchAbortController = null;

        productSearchInput.addEventListener('input', debounce(function() {
            const term = this.value.trim();
            
            // Clear hidden ID if input is changed
            productIdHidden.value = '';
            
            // Unlock category dropdown since product was changed
            document.getElementById('history-category').removeAttribute('disabled');

            if (productSearchAbortController) {
                productSearchAbortController.abort();
            }

            if (term.length < 2) {
                productSuggestions.classList.add('d-none');
                productSuggestions.innerHTML = '';
                return;
            }

            productSearchAbortController = new AbortController();
            
            productSuggestions.innerHTML = '<div class="p-2 text-muted small">Searching...</div>';
            productSuggestions.classList.remove('d-none');

            fetch(`{{ route('live-search-products') }}?term=${encodeURIComponent(term)}`, {
                signal: productSearchAbortController.signal
            })
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    productSuggestions.innerHTML = '<div class="p-2 text-muted small">No products found</div>';
                    return;
                }

                productSuggestions.innerHTML = '';
                data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'p-2 border-bottom';
                    div.style.cursor = 'pointer';
                    const catName = item.category ? item.category.category_name : 'No Category';
                    div.innerHTML = `<strong>${item.product_name}</strong> <small class="text-muted d-block">${item.product_code || 'No Code'} &bull; <span class="text-info">${catName}</span></small>`;
                    
                    div.addEventListener('mouseenter', () => div.classList.add('bg-light'));
                    div.addEventListener('mouseleave', () => div.classList.remove('bg-light'));
                    
                    div.addEventListener('click', () => {
                        productSearchInput.value = item.product_name;
                        productIdHidden.value = item.id;
                        productSuggestions.classList.add('d-none');
                        
                        // Auto-select and lock the category dropdown
                        const catSelect = document.getElementById('history-category');
                        if (item.category && item.category.id) {
                            catSelect.value = item.category.id;
                            catSelect.setAttribute('disabled', 'disabled');
                        } else {
                            catSelect.value = '';
                            catSelect.removeAttribute('disabled');
                        }
                    });
                    
                    productSuggestions.appendChild(div);
                });
            })
            .catch(err => {
                if (err.name !== 'AbortError') {
                    productSuggestions.innerHTML = '<div class="p-2 text-danger small">Error loading products</div>';
                }
            });
        }, 300));

        // Hide suggestions on click outside
        document.addEventListener('click', function(e) {
            if (!productSearchInput.contains(e.target) && !productSuggestions.contains(e.target)) {
                productSuggestions.classList.add('d-none');
            }
            if (!performerSearchInput.contains(e.target) && !performerSuggestions.contains(e.target)) {
                performerSuggestions.classList.add('d-none');
            }
        });

        // Custom Performer Autocomplete with debounce and abort
        const performerSearchInput = document.getElementById('history-performer-search');
        const performerIdHidden = document.getElementById('history-performer-id');
        const performerSuggestions = document.getElementById('history-performer-suggestions');
        let performerSearchAbortController = null;

        performerSearchInput.addEventListener('input', debounce(function() {
            const term = this.value.trim();
            
            performerIdHidden.value = '';

            if (performerSearchAbortController) {
                performerSearchAbortController.abort();
            }

            if (term.length < 2) {
                performerSuggestions.classList.add('d-none');
                performerSuggestions.innerHTML = '';
                return;
            }

            performerSearchAbortController = new AbortController();
            
            performerSuggestions.innerHTML = '<div class="p-2 text-muted small">Searching...</div>';
            performerSuggestions.classList.remove('d-none');

            fetch(`{{ route('inventory.requisitions.my-stock.performers') }}?term=${encodeURIComponent(term)}`, {
                signal: performerSearchAbortController.signal
            })
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    performerSuggestions.innerHTML = '<div class="p-2 text-muted small">No staff found</div>';
                    return;
                }

                performerSuggestions.innerHTML = '';
                data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'p-2 border-bottom';
                    div.style.cursor = 'pointer';
                    div.innerHTML = `<strong>${item.name}</strong>`;
                    
                    div.addEventListener('mouseenter', () => div.classList.add('bg-light'));
                    div.addEventListener('mouseleave', () => div.classList.remove('bg-light'));
                    
                    div.addEventListener('click', () => {
                        performerSearchInput.value = item.name;
                        performerIdHidden.value = item.id;
                        performerSuggestions.classList.add('d-none');
                    });
                    
                    performerSuggestions.appendChild(div);
                });
            })
            .catch(err => {
                if (err.name !== 'AbortError') {
                    performerSuggestions.innerHTML = '<div class="p-2 text-danger small">Error loading staff</div>';
                }
            });
        }, 300));

        // Helper functions
        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }
    });
</script>
@endsection