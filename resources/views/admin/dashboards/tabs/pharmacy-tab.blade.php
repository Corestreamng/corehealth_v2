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
                            <h3 class="dash-welcome-title">{{ __('dashboard.welcome_back') }}, {{ Auth::user()->name ?? __('dashboard.tab_pharmacy') }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-pill me-1"></i> {{ __('dashboard.pharmacy_operations') }}
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
                    <p class="dash-stat-label">{{ __('dashboard.queue_today') }}</p>
                    <h2 class="dash-stat-value" id="pharm-stat-queue">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-cart me-1"></i>{{ __('dashboard.awaiting_dispensing') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-cart"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #166534, #22c55e);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.dispensed_today') }}</p>
                    <h2 class="dash-stat-value" id="pharm-stat-dispensed">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-pill me-1"></i>{{ __('dashboard.completed_orders') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-pill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #6d28d9, #8b5cf6);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.total_products') }}</p>
                    <h2 class="dash-stat-value" id="pharm-stat-products">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-package-variant me-1"></i>{{ __('dashboard.in_inventory') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-package-variant"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #991b1b, #dc2626);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.low_stock') }}</p>
                    <h2 class="dash-stat-value" id="pharm-stat-low-stock">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-alert me-1"></i>{{ __('dashboard.reorder_needed') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-alert"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'pharm-insights'])

{{-- Live Queues --}}
@include('admin.dashboards.components.queue-widget', ['containerId' => 'pharm-queues'])

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-warning bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-warning"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.pharmacy_quick_actions') }}</h5>
                    <small class="text-muted">{{ __('dashboard.dispensing_clinical_tools') }}</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('pharmacy.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('pharmacy.workbench', ['queue_filter' => 'all']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #e0f2fe, #bae6fd); border-color: #7dd3fc;">
                            <i class="mdi mdi-pill dash-shortcut-icon" style="color: #0369a1;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0369a1;">{{ __('dashboard.pharmacy') }}</h6>
                            <small style="color: #0369a1;">{{ __('dashboard.workbench') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('product-category.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('product-category.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fff7ed, #ffedd5); border-color: #fed7aa;">
                            <i class="mdi mdi-tag-multiple dash-shortcut-icon" style="color: #c2410c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #c2410c;">{{ __('dashboard.categories') }}</h6>
                            <small style="color: #c2410c;">{{ __('dashboard.product_types') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('stores.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('stores.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dcfce7, #bbf7d0); border-color: #86efac;">
                            <i class="mdi mdi-store dash-shortcut-icon" style="color: #166534;"></i>
                            <h6 class="dash-shortcut-title" style="color: #166534;">{{ __('dashboard.stores') }}</h6>
                            <small style="color: #166534;">{{ __('dashboard.locations') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('products.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('products.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border-color: #d8b4fe;">
                            <i class="mdi mdi-package-variant dash-shortcut-icon" style="color: #7e22ce;"></i>
                            <h6 class="dash-shortcut-title" style="color: #7e22ce;">{{ __('dashboard.products') }}</h6>
                            <small style="color: #7e22ce;">{{ __('dashboard.inventory') }}</small>
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
                            <h6 class="dash-shortcut-title" style="color: #1e40af;">{{ __('dashboard.patients') }}</h6>
                            <small style="color: #1e40af;">{{ __('dashboard.directory') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('pharmacy.reports.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('pharmacy.reports.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #ccfbf1, #99f6e4); border-color: #5eead4;">
                            <i class="mdi mdi-chart-box-outline dash-shortcut-icon" style="color: #0f766e;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0f766e;">{{ __('dashboard.reports') }}</h6>
                            <small style="color: #0f766e;">{{ __('dashboard.analytics') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('pharmacy.returns.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('pharmacy.returns.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fef3c7, #fde68a); border-color: #fcd34d;">
                            <i class="mdi mdi-keyboard-return dash-shortcut-icon" style="color: #b45309;"></i>
                            <h6 class="dash-shortcut-title" style="color: #b45309;">{{ __('dashboard.returns') }}</h6>
                            <small style="color: #b45309;">{{ __('dashboard.patient_returns') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('pharmacy.damages.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('pharmacy.damages.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fee2e2, #fecaca); border-color: #fca5a5;">
                            <i class="mdi mdi-package-variant-remove dash-shortcut-icon" style="color: #b91c1c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #b91c1c;">{{ __('dashboard.damages') }}</h6>
                            <small style="color: #b91c1c;">{{ __('dashboard.loss_mgt') }}</small>
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
                <div class="dash-section-icon bg-success bg-opacity-10">
                    <i class="mdi mdi-chart-line text-success"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.dispensing_trend') }}</h5>
                    <small class="text-muted">{{ __('dashboard.daily_prescription_fulfillment') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="pharmacyDispensingChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-warning bg-opacity-10">
                    <i class="mdi mdi-package-variant text-warning"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.stock_health') }}</h5>
                    <small class="text-muted">{{ __('dashboard.current_inventory_status') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="pharmacyStockChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Recent Activity --}}
@include('admin.dashboards.components.mini-table', [
    'containerId' => 'pharm-activity',
    'title' => __('dashboard.recent_dispensing'),
    'subtitle' => __('dashboard.latest_prescriptions_today'),
    'icon' => 'mdi-pill',
    'iconBg' => 'success'
])
