{{-- Pharmacy Stock Reports Panel --}}
{{-- Integrates into pharmacy workbench as a queue-view panel --}}

<div class="queue-view" id="pharmacy-stock-reports-view">
    <div class="queue-view-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
        <h4><i class="mdi mdi-file-chart"></i> Stock Reports</h4>
        <div class="reports-header-actions">
            <button class="btn btn-sm btn-outline-light" id="btn-export-stock-csv">
                <i class="mdi mdi-download"></i> Export CSV
            </button>
            <button class="btn btn-secondary btn-close-queue" id="btn-close-stock-reports">
                <i class="mdi mdi-close"></i> Close
            </button>
        </div>
    </div>
    <div class="queue-view-content" style="padding: 1.5rem; overflow-y: auto; max-height: calc(100vh - 180px);">

        {{-- Summary Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card-mini" style="border-left: 4px solid #667eea;">
                    <div class="stat-icon-mini" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="mdi mdi-package-variant"></i>
                    </div>
                    <div class="stat-content-mini">
                        <h4 id="stock-stat-products" class="stat-skeleton">—</h4>
                        <small>Products</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card-mini" style="border-left: 4px solid #28a745;">
                    <div class="stat-icon-mini" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                        <i class="mdi mdi-cash-multiple"></i>
                    </div>
                    <div class="stat-content-mini">
                        <h4 id="stock-stat-value" class="stat-skeleton">—</h4>
                        <small>Total Value</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card-mini" style="border-left: 4px solid #ffc107;">
                    <div class="stat-icon-mini" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                        <i class="mdi mdi-alert-circle"></i>
                    </div>
                    <div class="stat-content-mini">
                        <h4 id="stock-stat-low" class="text-warning stat-skeleton">—</h4>
                        <small>Low Stock</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card-mini" style="border-left: 4px solid #dc3545;">
                    <div class="stat-icon-mini" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);">
                        <i class="mdi mdi-close-circle"></i>
                    </div>
                    <div class="stat-content-mini">
                        <h4 id="stock-stat-out" class="text-danger stat-skeleton">—</h4>
                        <small>Out of Stock</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Report Tabs --}}
        <ul class="nav nav-tabs nav-fill mb-3" id="stock-report-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#stock-overview-tab" role="tab">
                    <i class="mdi mdi-view-list"></i> Stock Overview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#stock-expiring-tab" role="tab">
                    <i class="mdi mdi-clock-alert"></i> Expiring Stock
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#stock-by-store-tab" role="tab">
                    <i class="mdi mdi-store"></i> By Store
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#stock-by-category-tab" role="tab">
                    <i class="mdi mdi-tag-multiple"></i> By Category
                </a>
            </li>
        </ul>

        <div class="tab-content">
            {{-- Stock Overview Tab --}}
            <div class="tab-pane fade show active" id="stock-overview-tab" role="tabpanel">
                <div class="date-presets-bar mb-3">
                    <span class="text-muted me-2"><i class="mdi mdi-filter-variant"></i></span>
                    <select class="form-control form-control-sm" id="stock-store-filter" style="width: auto; display: inline-block;">
                        <option value="">All Stores</option>
                    </select>
                    <select class="form-control form-control-sm" id="stock-category-filter" style="width: auto; display: inline-block;">
                        <option value="">All Categories</option>
                    </select>
                    <select class="form-control form-control-sm" id="stock-level-filter" style="width: auto; display: inline-block;">
                        <option value="">All Levels</option>
                        <option value="available">In Stock</option>
                        <option value="low">Low Stock</option>
                        <option value="out">Out of Stock</option>
                    </select>
                    <button class="btn btn-sm btn-primary" id="apply-stock-filters">
                        <i class="mdi mdi-filter"></i> Apply
                    </button>
                </div>
                <div class="card-modern">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped mb-0" id="stockOverviewTable" style="width: 100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Store</th>
                                        <th>Qty</th>
                                        <th>Reorder</th>
                                        <th>Unit Cost</th>
                                        <th>Total Value</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Expiring Stock Tab --}}
            <div class="tab-pane fade" id="stock-expiring-tab" role="tabpanel">
                <div class="date-presets-bar mb-3">
                    <span class="text-muted me-2"><i class="mdi mdi-clock-alert"></i> Show expiring within:</span>
                    <button class="btn btn-sm btn-outline-danger expiry-range-btn active" data-days="30">30 Days</button>
                    <button class="btn btn-sm btn-outline-warning expiry-range-btn" data-days="60">60 Days</button>
                    <button class="btn btn-sm btn-outline-info expiry-range-btn" data-days="90">90 Days</button>
                    <button class="btn btn-sm btn-outline-secondary expiry-range-btn" data-days="180">180 Days</button>
                </div>
                <div class="card-modern">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped mb-0" id="expiringStockTable" style="width: 100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Product</th>
                                        <th>Store</th>
                                        <th>Batch</th>
                                        <th>Expiry Date</th>
                                        <th>Days Left</th>
                                        <th>Qty Available</th>
                                        <th>Value</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- By Store Tab --}}
            <div class="tab-pane fade" id="stock-by-store-tab" role="tabpanel">
                <button class="btn btn-sm btn-primary mb-3" id="refresh-store-summary">
                    <i class="mdi mdi-refresh"></i> Refresh
                </button>
                <div class="row g-3" id="storeSummaryCards"></div>
            </div>

            {{-- By Category Tab --}}
            <div class="tab-pane fade" id="stock-by-category-tab" role="tabpanel">
                <div class="date-presets-bar mb-3">
                    <select class="form-control form-control-sm" id="category-store-filter" style="width: auto; display: inline-block;">
                        <option value="">All Stores</option>
                    </select>
                    <button class="btn btn-sm btn-primary" id="refresh-category-summary">
                        <i class="mdi mdi-refresh"></i> Refresh
                    </button>
                </div>
                <div class="card-modern">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Products</th>
                                        <th>Quantity</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>
                                <tbody id="categorySummaryBody">
                                    <tr>
                                        <td colspan="4" class="text-center text-muted p-4">Click "Refresh" to load</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
