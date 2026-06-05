{{-- Maternity Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #701a75 0%, #a21caf 50%, #d946ef 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-mother-nurse"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">{{ __('dashboard.welcome_back') }}, {{ Auth::user()->name ?? __('dashboard.tab_maternity') }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-human-pregnant me-1"></i> {{ __('dashboard.maternity_antenatal_care') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'mat-insights'])

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #be123c, #e11d48);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.active_enrolments') }}</p>
                    <h2 class="dash-stat-value" id="mat-stat-active">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-account-multiple me-1"></i>{{ __('dashboard.currently_enrolled') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-mother-nurse"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #1e40af, #3b82f6);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.anc_visits_today') }}</p>
                    <h2 class="dash-stat-value" id="mat-stat-anc">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-calendar-check me-1"></i>{{ __('dashboard.antenatal_care') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-hospital-building"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #7e22ce, #9333ea);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.deliveries_today') }}</p>
                    <h2 class="dash-stat-value" id="mat-stat-deliveries">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-baby-carriage me-1"></i>{{ __('dashboard.births_recorded') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-baby-carriage"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #059669, #10b981);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.postnatal_care') }}</p>
                    <h2 class="dash-stat-value" id="mat-stat-pnc">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-check-circle-outline me-1"></i>{{ __('dashboard.follow_ups') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-human-female-boy"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Queues --}}
@include('admin.dashboards.components.queue-widget', ['containerId' => 'mat-queues'])

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-danger bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-danger"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.maternity_quick_actions') }}</h5>
                    <small class="text-muted">{{ __('dashboard.workbench_clinical_tools') }}</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('maternity-workbench.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('maternity-workbench.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fdf4ff, #fae8ff); border-color: #f5d0fe;">
                            <i class="mdi mdi-desktop-mac-dashboard dash-shortcut-icon" style="color: #86198f;"></i>
                            <h6 class="dash-shortcut-title" style="color: #86198f;">{{ __('dashboard.maternity_workbench') }}</h6>
                            <small style="color: #86198f;">{{ __('dashboard.main_panel') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('maternity-enrollment.create'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('maternity-enrollment.create') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f0fdfa, #ccfbf1); border-color: #99f6e4;">
                            <i class="mdi mdi-account-plus dash-shortcut-icon" style="color: #0d9488;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0d9488;">{{ __('dashboard.new_enrollment') }}</h6>
                            <small style="color: #0d9488;">{{ __('dashboard.anc_booking') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('maternity-workbench.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('maternity-workbench.index', ['queue' => 'anc']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #eff6ff, #dbeafe); border-color: #bfdbfe;">
                            <i class="mdi mdi-calendar-heart dash-shortcut-icon" style="color: #1e40af;"></i>
                            <h6 class="dash-shortcut-title" style="color: #1e40af;">{{ __('dashboard.anc_visits') }}</h6>
                            <small style="color: #1e40af;">{{ __('dashboard.antenatal') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('maternity-workbench.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('maternity-workbench.index', ['queue' => 'delivery']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fff1f2, #ffe4e6); border-color: #fecdd3;">
                            <i class="mdi mdi-baby-carriage dash-shortcut-icon" style="color: #be123c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #be123c;">{{ __('dashboard.labor_delivery') }}</h6>
                            <small style="color: #be123c;">{{ __('dashboard.management') }}</small>
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
                <div class="dash-section-icon bg-danger bg-opacity-10">
                    <i class="mdi mdi-chart-line text-danger"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.anc_enrollment_trend') }}</h5>
                    <small class="text-muted">{{ __('dashboard.monthly_new_registrations') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="maternityEnrollmentChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-info bg-opacity-10">
                    <i class="mdi mdi-alert-circle-outline text-info"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.risk_distribution') }}</h5>
                    <small class="text-muted">{{ __('dashboard.active_pregnancies_risk_profile') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="maternityRiskChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Recent Activity --}}
@include('admin.dashboards.components.mini-table', [
    'containerId' => 'mat-activity',
    'title' => __('dashboard.recent_maternity_activity'),
    'subtitle' => __('dashboard.latest_enrollments_deliveries_today'),
    'icon' => 'mdi-history',
    'iconBg' => 'danger'
])
