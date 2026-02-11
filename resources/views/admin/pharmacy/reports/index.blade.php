@extends('admin.layouts.app')
@section('title', 'Pharmacy Stock Reports')
@section('page_name', 'Pharmacy')
@section('subpage_name', 'Stock Reports')
@section('content')

<div class="row">
    <!-- Summary Cards -->
    <div class="col-md-3">
        <div class="card-modern text-center">
            <div class="card-body">
                <h6 class="text-muted">Total Products</h6>
                <h3 id="total_products">-</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-modern text-center">
            <div class="card-body">
                <h6 class="text-muted">Total Stock Value</h6>
                <h3 id="total_value">-</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-modern text-center">
            <div class="card-body">
                <h6 class="text-muted text-warning">Low Stock Items</h6>
                <h3 id="low_stock_count" class="text-warning">-</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-modern text-center">
            <div class="card-body">
                <h6 class="text-muted text-danger">Out of Stock</h6>
                <h3 id="out_of_stock_count" class="text-danger">-</h3>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Reports -->
<div class="card-modern mb-2 mt-3">
    <div class="card-body">
        <ul class="nav nav-tabs" id="reportTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="stock-tab" data-toggle="tab" href="#stock" role="tab">
                    <i class="mdi mdi-package-variant"></i> Stock Report
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="valuation-tab" data-toggle="tab" href="#valuation" role="tab">
                    <i class="mdi mdi-cash-multiple"></i> Stock Valuation
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="expiring-tab" data-toggle="tab" href="#expiring" role="tab">
                    <i class="mdi mdi-clock-alert"></i> Expiring Stock
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="by-store-tab" data-toggle="tab" href="#by-store" role="tab">
                    <i class="mdi mdi-store"></i> By Store
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="by-category-tab" data-toggle="tab" href="#by-category" role="tab">
                    <i class="mdi mdi-tag-multiple"></i> By Category
                </a>
            </li>
        </ul>

        <div class="tab-content mt-3" id="reportTabContent">
            <!-- Stock Report Tab -->
            <div class="tab-pane fade show active" id="stock" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="stock_store_filter">
                            <option value="">All Stores</option>
                            @foreach(\App\Models\Store::where('store_type', 'pharmacy')->orderBy('store_name')->get() as $store)
                                <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="stock_category_filter">
                            <option value="">All Categories</option>
                            @foreach(\App\Models\ProductCategory::orderBy('name')->get() as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="stock_level_filter">
                            <option value="">All Stock Levels</option>
                            <option value="available">In Stock</option>
                            <option value="low">Low Stock</option>
                            <option value="out">Out of Stock</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary btn-sm btn-block" id="applyStockFilters">
                            <i class="mdi mdi-filter"></i> Apply Filters
                        </button>
                        <button class="btn btn-success btn-sm btn-block mt-1" id="exportStockReport">
                            <i class="mdi mdi-download"></i> Export CSV
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="stockTable" style="width: 100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Store</th>
                                <th>Current Qty</th>
                                <th>Reorder Level</th>
                                <th>Unit Cost</th>
                                <th>Total Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>

            <!-- Valuation Tab -->
            <div class="tab-pane fade" id="valuation" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <select class="form-control form-control-sm" id="valuation_store_filter">
                            <option value="">All Stores</option>
                            @foreach(\App\Models\Store::where('store_type', 'pharmacy')->orderBy('store_name')->get() as $store)
                                <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary btn-sm btn-block" id="loadValuation">
                            <i class="mdi mdi-chart-line"></i> Load Valuation
                        </button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card-modern">
                            <div class="card-body">
                                <h5>Valuation Summary</h5>
                                <div id="valuationSummary">
                                    <p class="text-muted">Click "Load Valuation" to view summary</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card-modern">
                            <div class="card-body">
                                <h5>Top 10 Most Valuable Items</h5>
                                <div id="topValueItems">
                                    <p class="text-muted">Click "Load Valuation" to view items</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expiring Stock Tab -->
            <div class="tab-pane fade" id="expiring" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="expiring_store_filter">
                            <option value="">All Stores</option>
                            @foreach(\App\Models\Store::where('store_type', 'pharmacy')->orderBy('store_name')->get() as $store)
                                <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control form-control-sm" id="expiring_days_filter">
                            <option value="30">Next 30 Days</option>
                            <option value="60">Next 60 Days</option>
                            <option value="90" selected>Next 90 Days</option>
                            <option value="180">Next 180 Days</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary btn-sm btn-block" id="loadExpiringStock">
                            <i class="mdi mdi-clock-alert"></i> Load Expiring Stock
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="expiringTable" style="width: 100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Store</th>
                                <th>Batch</th>
                                <th>Expiry Date</th>
                                <th>Days to Expiry</th>
                                <th>Qty Available</th>
                                <th>Total Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>

            <!-- By Store Tab -->
            <div class="tab-pane fade" id="by-store" role="tabpanel">
                <button class="btn btn-primary btn-sm mb-3" id="loadByStore">
                    <i class="mdi mdi-refresh"></i> Refresh Store Summary
                </button>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Store</th>
                                <th>Total Products</th>
                                <th>Total Quantity</th>
                                <th>Total Value</th>
                                <th>Out of Stock</th>
                                <th>Low Stock</th>
                            </tr>
                        </thead>
                        <tbody id="storeTableBody">
                            <tr>
                                <td colspan="6" class="text-center text-muted">Click "Refresh Store Summary" to load data</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- By Category Tab -->
            <div class="tab-pane fade" id="by-category" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <select class="form-control form-control-sm" id="category_store_filter">
                            <option value="">All Stores</option>
                            @foreach(\App\Models\Store::where('store_type', 'pharmacy')->orderBy('store_name')->get() as $store)
                                <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary btn-sm btn-block" id="loadByCategory">
                            <i class="mdi mdi-refresh"></i> Load Category Summary
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Total Products</th>
                                <th>Total Quantity</th>
                                <th>Total Value</th>
                            </tr>
                        </thead>
                        <tbody id="categoryTableBody">
                            <tr>
                                <td colspan="4" class="text-center text-muted">Click "Load Category Summary" to load data</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<script>
$(function() {
    let stockTable, expiringTable;

    // Initialize Stock DataTable
    stockTable = $('#stockTable').DataTable({
        "dom": 'Bfrtip',
        "iDisplayLength": 50,
        "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "{{ route('pharmacy.reports.stock') }}",
            "type": "GET",
            "data": function(d) {
                d.store_id = $('#stock_store_filter').val();
                d.category_id = $('#stock_category_filter').val();
                d.stock_level = $('#stock_level_filter').val();
            }
        },
        "columns": [
            { data: "DT_RowIndex", name: "DT_RowIndex", orderable: false, searchable: false },
            { data: "product_name", name: "products.product_name" },
            { data: "category_name", name: "product_categories.category_name" },
            { data: "store_name", name: "stores.store_name" },
            { data: "current_quantity", name: "store_stocks.current_quantity" },
            { data: "reorder_level", name: "store_stocks.reorder_level" },
            { data: "unit_cost", name: "prices.pr_buy_price", searchable: false, render: $.fn.dataTable.render.number(',', '.', 2) },
            { data: "total_value_formatted", name: "total_value", searchable: false, orderable: false },
            { data: "stock_status", name: "stock_status", orderable: false, searchable: false }
        ]
    });

    $('#applyStockFilters').on('click', function() {
        stockTable.ajax.reload();
        updateSummaryCards();
    });

    // Export stock report
    $('#exportStockReport').on('click', function() {
        const params = new URLSearchParams({
            store_id: $('#stock_store_filter').val(),
            category_id: $('#stock_category_filter').val(),
            stock_level: $('#stock_level_filter').val()
        });
        window.location.href = "{{ route('pharmacy.reports.export-stock') }}?" + params.toString();
    });

    // Load valuation report
    $('#loadValuation').on('click', function() {
        const storeId = $('#valuation_store_filter').val();

        $.ajax({
            url: "{{ route('pharmacy.reports.valuation') }}",
            data: { store_id: storeId },
            success: function(response) {
                let summaryHtml = `
                    <p><strong>Total Items:</strong> ${response.total_items}</p>
                    <p><strong>Total Valuation:</strong> ₦${parseFloat(response.total_valuation).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                `;
                $('#valuationSummary').html(summaryHtml);

                let topItemsHtml = '<ol>';
                response.items.slice(0, 10).forEach(function(item) {
                    topItemsHtml += `<li>${item.product_name}: ₦${parseFloat(item.total_value).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</li>`;
                });
                topItemsHtml += '</ol>';
                $('#topValueItems').html(topItemsHtml);
            },
            error: function() {
                Swal.fire('Error!', 'Failed to load valuation data', 'error');
            }
        });
    });

    // Load expiring stock
    $('#loadExpiringStock').on('click', function() {
        if (expiringTable) {
            expiringTable.destroy();
        }

        expiringTable = $('#expiringTable').DataTable({
            "dom": 'Bfrtip',
            "buttons": ['copy', 'excel', 'csv', 'pdf', 'print'],
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "{{ route('pharmacy.reports.expiring-stock') }}",
                "type": "GET",
                "data": function(d) {
                    d.store_id = $('#expiring_store_filter').val();
                    d.days = $('#expiring_days_filter').val();
                }
            },
            "columns": [
                { data: "DT_RowIndex", name: "DT_RowIndex", orderable: false, searchable: false },
                { data: "product_name", name: "products.product_name" },
                { data: "store_name", name: "stores.store_name" },
                { data: "batch_number", name: "stock_batches.batch_number" },
                { data: "expiry_date", name: "expiry_date" },
                { data: "days_to_expiry", name: "days_to_expiry" },
                { data: "quantity_available", name: "quantity_available" },
                { data: "total_value_formatted", name: "total_value" },
                { data: "expiry_status", name: "expiry_status", orderable: false }
            ]
        });
    });

    // Load by store
    $('#loadByStore').on('click', function() {
        $.ajax({
            url: "{{ route('pharmacy.reports.stock-by-store') }}",
            success: function(response) {
                let html = '';
                response.forEach(function(store) {
                    html += `
                        <tr>
                            <td>${store.store_name}</td>
                            <td>${store.total_products}</td>
                            <td>${parseFloat(store.total_quantity).toLocaleString()}</td>
                            <td>₦${parseFloat(store.total_value).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                            <td><span class="badge badge-danger">${store.out_of_stock_count}</span></td>
                            <td><span class="badge badge-warning">${store.low_stock_count}</span></td>
                        </tr>
                    `;
                });
                $('#storeTableBody').html(html || '<tr><td colspan="6" class="text-center">No data available</td></tr>');
            }
        });
    });

    // Load by category
    $('#loadByCategory').on('click', function() {
        const storeId = $('#category_store_filter').val();

        $.ajax({
            url: "{{ route('pharmacy.reports.stock-by-category') }}",
            data: { store_id: storeId },
            success: function(response) {
                let html = '';
                response.forEach(function(category) {
                    html += `
                        <tr>
                            <td>${category.category_name || 'Uncategorized'}</td>
                            <td>${category.total_products}</td>
                            <td>${parseFloat(category.total_quantity).toLocaleString()}</td>
                            <td>₦${parseFloat(category.total_value).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        </tr>
                    `;
                });
                $('#categoryTableBody').html(html || '<tr><td colspan="4" class="text-center">No data available</td></tr>');
            }
        });
    });

    // Update summary cards
    function updateSummaryCards() {
        // This would need a backend endpoint to calculate these
        $('#total_products').text('Loading...');
        $('#total_value').text('Loading...');
        $('#low_stock_count').text('Loading...');
        $('#out_of_stock_count').text('Loading...');
    }

    // Initial load
    updateSummaryCards();
});
</script>
@endsection
