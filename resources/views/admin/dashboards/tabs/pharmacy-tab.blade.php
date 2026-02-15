{{-- Pharmacy/Store Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #14532d 0%, #15803d 50%, #22c55e 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-pill"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">Welcome back, {{ Auth::user()->name ?? 'Pharmacist' }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-store me-1"></i> Pharmacy & Store
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #b45309, #d97706);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Queue Today</p>
                    <h2 class="dash-stat-value" id="pharm-stat-queue">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-cart me-1"></i>Awaiting dispensing</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-cart"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #166534, #22c55e);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Dispensed Today</p>
                    <h2 class="dash-stat-value" id="pharm-stat-dispensed">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-pill me-1"></i>Completed orders</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-pill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #6d28d9, #8b5cf6);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Total Products</p>
                    <h2 class="dash-stat-value" id="pharm-stat-products">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-package-variant me-1"></i>In inventory</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-package-variant"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #991b1b, #dc2626);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Low Stock</p>
                    <h2 class="dash-stat-value" id="pharm-stat-low-stock">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-alert me-1"></i>Reorder needed</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-alert"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-warning bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-warning"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Quick Actions</h5>
                    <small class="text-muted">Pharmacy & store tools</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('pharmacy.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('pharmacy.workbench') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #e0f2fe, #bae6fd); border-color: #7dd3fc;">
                            <i class="mdi mdi-pill dash-shortcut-icon" style="color: #0369a1;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0369a1;">Pharmacy</h6>
                            <small style="color: #0369a1;">Workbench</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('product-category.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('product-category.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fff7ed, #ffedd5); border-color: #fed7aa;">
                            <i class="mdi mdi-tag-multiple dash-shortcut-icon" style="color: #c2410c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #c2410c;">Categories</h6>
                            <small style="color: #c2410c;">Product types</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('stores.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('stores.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dcfce7, #bbf7d0); border-color: #86efac;">
                            <i class="mdi mdi-store dash-shortcut-icon" style="color: #166534;"></i>
                            <h6 class="dash-shortcut-title" style="color: #166534;">Stores</h6>
                            <small style="color: #166534;">Locations</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('products.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('products.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border-color: #d8b4fe;">
                            <i class="mdi mdi-package-variant dash-shortcut-icon" style="color: #7e22ce;"></i>
                            <h6 class="dash-shortcut-title" style="color: #7e22ce;">Products</h6>
                            <small style="color: #7e22ce;">Inventory</small>
                        </div>
                    </a>
                </div>
                @endif
            </div>

            <div class="row g-3 mt-1">
                @if(Route::has('patient.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('patient.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dbeafe, #bfdbfe); border-color: #93c5fd;">
                            <i class="mdi mdi-account-multiple dash-shortcut-icon" style="color: #1e40af;"></i>
                            <h6 class="dash-shortcut-title" style="color: #1e40af;">Patients</h6>
                            <small style="color: #1e40af;">Directory</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('stock.index') || Route::has('inventory.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ Route::has('stock.index') ? route('stock.index') : route('inventory.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #ccfbf1, #99f6e4); border-color: #5eead4;">
                            <i class="mdi mdi-archive dash-shortcut-icon" style="color: #0f766e;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0f766e;">Stock</h6>
                            <small style="color: #0f766e;">Management</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('suppliers.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('suppliers.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fef3c7, #fde68a); border-color: #fcd34d;">
                            <i class="mdi mdi-truck dash-shortcut-icon" style="color: #b45309;"></i>
                            <h6 class="dash-shortcut-title" style="color: #b45309;">Suppliers</h6>
                            <small style="color: #b45309;">Vendors</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('purchase-orders.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('purchase-orders.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #e0e7ff, #c7d2fe); border-color: #a5b4fc;">
                            <i class="mdi mdi-clipboard-text dash-shortcut-icon" style="color: #3730a3;"></i>
                            <h6 class="dash-shortcut-title" style="color: #3730a3;">Purchase</h6>
                            <small style="color: #3730a3;">Orders</small>
                        </div>
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Charts --}}
<div class="row g-4">
    <div class="col-xl-6">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-success bg-opacity-10">
                    <i class="mdi mdi-chart-line text-success"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Dispensing Trend</h5>
                    <small class="text-muted">Daily prescription fulfillment</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="pharmacyDispensingChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-warning bg-opacity-10">
                    <i class="mdi mdi-package-variant text-warning"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Stock Levels</h5>
                    <small class="text-muted">Current inventory status</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="pharmacyStockChart"></canvas>
            </div>
        </div>
    </div>
</div>
