{{-- Store/Inventory Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #7c2d12 0%, #9a3412 50%, #ea580c 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-store-warehouse"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">{{ __('dashboard.welcome_back') }}, {{ Auth::user()->name ?? __('dashboard.tab_store') }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-warehouse me-1"></i> {{ __('dashboard.inventory_store_operations') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Stats populated via JS --}}
{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'store-insights'])

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #9a3412, #c2410c);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.pending_requisitions') }}</p>
                    <h2 class="dash-stat-value" id="store-stat-pending-reqs">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-swap-horizontal me-1"></i>{{ __('dashboard.internal_requests') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-swap-horizontal"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #1e40af, #3b82f6);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.pending_pos') }}</p>
                    <h2 class="dash-stat-value" id="store-stat-pending-pos">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-cart-arrow-down me-1"></i>{{ __('dashboard.procurement') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-cart-arrow-down"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #7e22ce, #9333ea);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.approved_pos') }}</p>
                    <h2 class="dash-stat-value" id="store-stat-approved-pos">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-check-circle-outline me-1"></i>{{ __('dashboard.awaiting_delivery') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-check-circle-outline"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #059669, #10b981);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.to_fulfill') }}</p>
                    <h2 class="dash-stat-value" id="store-stat-fulfill">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-truck-delivery me-1"></i>{{ __('dashboard.ready_for_dispatch') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-truck-delivery"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Queues --}}
@include('admin.dashboards.components.queue-widget', ['containerId' => 'store-queues'])

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-warning bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-warning"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.store_quick_actions') }}</h5>
                    <small class="text-muted">{{ __('dashboard.inventory_supply_chain_tools') }}</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('inventory.store-workbench.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('inventory.store-workbench.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fff7ed, #ffedd5); border-color: #fed7aa;">
                            <i class="mdi mdi-apps dash-shortcut-icon" style="color: #c2410c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #c2410c;">{{ __('dashboard.store_workbench') }}</h6>
                            <small style="color: #c2410c;">{{ __('dashboard.main_operations') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('inventory.store-workbench.tally-card'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('inventory.store-workbench.tally-card') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f0fdf4, #dcfce7); border-color: #bbf7d0;">
                            <i class="mdi mdi-table-large dash-shortcut-icon" style="color: #15803d;"></i>
                            <h6 class="dash-shortcut-title" style="color: #15803d;">{{ __('dashboard.tally_card') }}</h6>
                            <small style="color: #15803d;">{{ __('dashboard.stock_ledger') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('inventory.requisitions.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('inventory.requisitions.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #e0f2fe, #bae6fd); border-color: #7dd3fc;">
                            <i class="mdi mdi-swap-horizontal dash-shortcut-icon" style="color: #0369a1;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0369a1;">{{ __('dashboard.requisitions') }}</h6>
                            <small style="color: #0369a1;">{{ __('dashboard.internal_requests_cap') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('inventory.purchase-orders.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('inventory.purchase-orders.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dcfce7, #bbf7d0); border-color: #86efac;">
                            <i class="mdi mdi-cart-arrow-down dash-shortcut-icon" style="color: #166534;"></i>
                            <h6 class="dash-shortcut-title" style="color: #166534;">{{ __('dashboard.purchase_orders') }}</h6>
                            <small style="color: #166534;">{{ __('dashboard.procurement') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('suppliers.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('suppliers.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border-color: #d8b4fe;">
                            <i class="mdi mdi-truck dash-shortcut-icon" style="color: #7e22ce;"></i>
                            <h6 class="dash-shortcut-title" style="color: #7e22ce;">{{ __('dashboard.suppliers') }}</h6>
                            <small style="color: #7e22ce;">{{ __('dashboard.vendors') }}</small>
                        </div>
                    </a>
                </div>
                @endif
            </div>

            <div class="row g-3 mt-1">
                @if(Route::has('products.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('products.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f0f9ff, #e0f2fe); border-color: #bae6fd;">
                            <i class="mdi mdi-package-variant dash-shortcut-icon" style="color: #0284c7;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0284c7;">{{ __('dashboard.product_master') }}</h6>
                            <small style="color: #0284c7;">{{ __('dashboard.catalog') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('stores.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('stores.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fdf2f8, #fce7f3); border-color: #fbcfe8;">
                            <i class="mdi mdi-store dash-shortcut-icon" style="color: #be185d;"></i>
                            <h6 class="dash-shortcut-title" style="color: #be185d;">{{ __('dashboard.stores') }}</h6>
                            <small style="color: #be185d;">{{ __('dashboard.locations') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('stock.index') || Route::has('inventory.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ Route::has('stock.index') ? route('stock.index') : route('inventory.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f0fdf4, #dcfce7); border-color: #bbf7d0;">
                            <i class="mdi mdi-archive-outline dash-shortcut-icon" style="color: #15803d;"></i>
                            <h6 class="dash-shortcut-title" style="color: #15803d;">{{ __('dashboard.stock_control') }}</h6>
                            <small style="color: #15803d;">{{ __('dashboard.inventory') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('import-export.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('import-export.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f8fafc, #f1f5f9); border-color: #e2e8f0;">
                            <i class="mdi mdi-file-import dash-shortcut-icon" style="color: #475569;"></i>
                            <h6 class="dash-shortcut-title" style="color: #475569;">{{ __('dashboard.import_export') }}</h6>
                            <small style="color: #475569;">{{ __('dashboard.bulk_tools') }}</small>
                        </div>
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Charts --}}
<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-warning bg-opacity-10">
                    <i class="mdi mdi-chart-bell-curve-cumulative text-warning"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.requisition_trend') }}</h5>
                    <small class="text-muted">{{ __('dashboard.daily_internal_stock_requests') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="storeRequisitionChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-info bg-opacity-10">
                    <i class="mdi mdi-package-variant text-info"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.inventory_health') }}</h5>
                    <small class="text-muted">{{ __('dashboard.global_stock_levels') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="storeStockChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Recent Activity --}}
@include('admin.dashboards.components.mini-table', [
    'containerId' => 'store-activity',
    'title' => __('dashboard.recent_store_activity'),
    'subtitle' => __('dashboard.latest_requisitions_pos_today'),
    'icon' => 'mdi-history',
    'iconBg' => 'warning'
])
