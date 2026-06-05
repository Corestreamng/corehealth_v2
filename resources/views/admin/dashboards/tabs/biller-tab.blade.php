{{-- Biller / Accounts Dashboard Tab (Enhanced) --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #064e3b 0%, #059669 50%, #10b981 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-cash-multiple"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">{{ __('dashboard.welcome_back') }}, {{ Auth::user()->name ?? __('dashboard.tab_biller') }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-cash-multiple me-1"></i> {{ __('dashboard.billing_desk') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #065f46, #059669);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.todays_revenue') }}</p>
                    <h2 class="dash-stat-value" id="biller-stat-revenue">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-clock-outline me-1"></i>{{ __('dashboard.updated_live') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-cash-multiple"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #7e22ce, #a855f7);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.payment_requests') }}</p>
                    <h2 class="dash-stat-value" id="biller-stat-payment-requests">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-clipboard-text me-1"></i>{{ __('dashboard.awaiting_action') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-clipboard-text"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #334155, #475569);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.my_payments') }}</p>
                    <h2 class="dash-stat-value" id="biller-stat-my-payments">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-cash-multiple me-1"></i>{{ __('dashboard.processed_today') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-receipt"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #991b1b, #dc2626);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.consultations') }}</p>
                    <h2 class="dash-stat-value" id="biller-stat-consultations">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-stethoscope me-1"></i>{{ __('dashboard.todays_encounters') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-stethoscope"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'biller-insights'])

{{-- Live Queues --}}
@include('admin.dashboards.components.queue-widget', ['containerId' => 'biller-queues'])

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-success bg-opacity-10">
                    <i class="mdi mdi-flash text-success"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.quick_actions') }}</h5>
                    <small class="text-muted">{{ __('dashboard.billing_accounts_tools') }}</small>
                </div>
            </div>
            <div class="row g-3">
                @if(Route::has('billing.workbench'))
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="{{ route('billing.workbench', ['queue_filter' => 'all']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #e0f2fe, #bae6fd); border-color: #7dd3fc;">
                            <i class="mdi mdi-view-dashboard dash-shortcut-icon" style="color: #0369a1;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0369a1;">{{ __('dashboard.billing') }}</h6>
                            <small style="color: #0369a1;">{{ __('dashboard.workbench') }}</small>
                        </div>
                    </a>
                </div>
                @endif
                @if(Route::has('product-or-service-request.index'))
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="{{ route('product-or-service-request.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fff7ed, #ffedd5); border-color: #fed7aa;">
                            <i class="mdi mdi-clipboard-text dash-shortcut-icon" style="color: #c2410c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #c2410c;">{{ __('dashboard.payment') }}</h6>
                            <small style="color: #c2410c;">{{ __('dashboard.requests') }}</small>
                        </div>
                    </a>
                </div>
                @endif
                @if(Route::has('my-transactions'))
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="{{ route('my-transactions') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dcfce7, #bbf7d0); border-color: #86efac;">
                            <i class="mdi mdi-receipt dash-shortcut-icon" style="color: #166534;"></i>
                            <h6 class="dash-shortcut-title" style="color: #166534;">{{ __('dashboard.my') }}</h6>
                            <small style="color: #166534;">{{ __('dashboard.transactions') }}</small>
                        </div>
                    </a>
                </div>
                @endif
                @if(Route::has('allPrevEncounters'))
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="{{ route('allPrevEncounters') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border-color: #d8b4fe;">
                            <i class="mdi mdi-stethoscope dash-shortcut-icon" style="color: #7e22ce;"></i>
                            <h6 class="dash-shortcut-title" style="color: #7e22ce;">{{ __('dashboard.consultations') }}</h6>
                            <small style="color: #7e22ce;">{{ __('dashboard.history') }}</small>
                        </div>
                    </a>
                </div>
                @endif
                @if(Route::has('patient.index'))
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="{{ route('patient.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fee2e2, #fecaca); border-color: #fca5a5;">
                            <i class="mdi mdi-account-multiple dash-shortcut-icon" style="color: #991b1b;"></i>
                            <h6 class="dash-shortcut-title" style="color: #991b1b;">{{ __('dashboard.all') }}</h6>
                            <small style="color: #991b1b;">{{ __('dashboard.patients') }}</small>
                        </div>
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Charts Row --}}
<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-success bg-opacity-10">
                    <i class="mdi mdi-chart-line text-success"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.revenue_trend') }}</h5>
                    <small class="text-muted">{{ __('dashboard.monthly_performance_overview') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="billerRevenueChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-primary bg-opacity-10">
                    <i class="mdi mdi-chart-donut text-primary"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.payment_methods') }}</h5>
                    <small class="text-muted">{{ __('dashboard.todays_breakdown') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="billerPaymentMethodsChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Recent Activity --}}
@include('admin.dashboards.components.mini-table', [
    'containerId' => 'biller-activity',
    'title' => __('dashboard.recent_payments'),
    'subtitle' => __('dashboard.latest_transactions_today'),
    'icon' => 'mdi-cash-check',
    'iconBg' => 'success'
])
