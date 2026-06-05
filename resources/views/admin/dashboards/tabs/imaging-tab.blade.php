{{-- Imaging / Radiology Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #3730a3 0%, #4f46e5 50%, #6366f1 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-radiobox-marked"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">{{ __('dashboard.welcome_back') }}, {{ Auth::user()->name ?? __('dashboard.tab_imaging') }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-radiobox-marked me-1"></i> {{ __('dashboard.radiology') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #3730a3, #4f46e5);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.pending_scans') }}</p>
                    <h2 class="dash-stat-value" id="imaging-stat-pending">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-clock-outline me-1"></i>{{ __('dashboard.todays_queue') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-radiobox-marked"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #166534, #16a34a);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.completed_today') }}</p>
                    <h2 class="dash-stat-value" id="imaging-stat-completed">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-check-circle me-1"></i>{{ __('dashboard.finalized_scans') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-check-circle"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #1e3a8a, #1d4ed8);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.monthly_volume') }}</p>
                    <h2 class="dash-stat-value" id="imaging-stat-month">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-chart-line me-1"></i>{{ __('dashboard.current_month') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-image-multiple"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #86198f, #a21caf);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.available_scans') }}</p>
                    <h2 class="dash-stat-value" id="imaging-stat-services">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-format-list-bulleted me-1"></i>{{ __('dashboard.catalog_size') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-format-list-bulleted"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'imaging-insights'])

{{-- Live Queues --}}
@include('admin.dashboards.components.queue-widget', ['containerId' => 'imaging-queues'])

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-primary bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-primary"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.radiology_quick_actions') }}</h5>
                    <small class="text-muted">{{ __('dashboard.imaging_scanning_tools') }}</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('imaging.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('imaging.workbench') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #e0e7ff, #c7d2fe); border-color: #a5b4fc;">
                            <i class="mdi mdi-radiobox-marked dash-shortcut-icon" style="color: #3730a3;"></i>
                            <h6 class="dash-shortcut-title" style="color: #3730a3;">{{ __('dashboard.workbench') }}</h6>
                            <small style="color: #3730a3;">{{ __('dashboard.imaging_queue') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('imaging.results'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('imaging.results') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dcfce7, #bbf7d0); border-color: #86efac;">
                            <i class="mdi mdi-image-search dash-shortcut-icon" style="color: #166534;"></i>
                            <h6 class="dash-shortcut-title" style="color: #166534;">{{ __('dashboard.scans') }}</h6>
                            <small style="color: #166534;">{{ __('dashboard.view_results') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('patient.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('patient.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fce7f3, #fbcfe8); border-color: #f9a8d4;">
                            <i class="mdi mdi-account-multiple dash-shortcut-icon" style="color: #be185d;"></i>
                            <h6 class="dash-shortcut-title" style="color: #be185d;">{{ __('dashboard.patients') }}</h6>
                            <small style="color: #be185d;">{{ __('dashboard.directory') }}</small>
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
                <div class="dash-section-icon bg-primary bg-opacity-10">
                    <i class="mdi mdi-chart-line text-primary"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.imaging_request_trend') }}</h5>
                    <small class="text-muted">{{ __('dashboard.daily_scan_volume') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="imagingRequestsChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-info bg-opacity-10">
                    <i class="mdi mdi-chart-pie text-info"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.scan_distribution') }}</h5>
                    <small class="text-muted">{{ __('dashboard.by_service_type') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="imagingCategoriesChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Recent Activity --}}
@include('admin.dashboards.components.mini-table', [
    'containerId' => 'imaging-activity',
    'title' => __('dashboard.recent_imaging_activity'),
    'subtitle' => __('dashboard.latest_scan_requests'),
    'icon' => 'mdi-radiobox-marked',
    'iconBg' => 'primary'
])
