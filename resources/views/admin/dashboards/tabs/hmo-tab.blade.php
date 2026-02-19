{{-- HMO Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #2d1b4e 0%, #4a2d7a 50%, #7c3aed 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-shield-check"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">Welcome back, {{ Auth::user()->name ?? 'HMO Manager' }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-medical-bag me-1"></i> Claims & Insurance
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #2d1b4e, #7c3aed);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">HMO Patients</p>
                    <h2 class="dash-stat-value" id="hmo-stat-patients">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-account-group me-1"></i>Active enrollees</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-account-group"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #92400e, #f59e0b);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Pending Claims</p>
                    <h2 class="dash-stat-value" id="hmo-stat-pending-claims">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-clipboard-alert me-1"></i>Awaiting review</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-clipboard-alert"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #065f46, #10b981);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Approved Claims</p>
                    <h2 class="dash-stat-value" id="hmo-stat-approved-claims">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-check-circle me-1"></i>This month</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-check-circle"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #581c87, #a855f7);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Total HMOs</p>
                    <h2 class="dash-stat-value" id="hmo-stat-total-hmos">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-hospital-building me-1"></i>Partner providers</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-hospital-building"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'hmo-insights'])

{{-- Live Queues --}}
@include('admin.dashboards.components.queue-widget', ['containerId' => 'hmo-queues'])

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-primary bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-primary"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Quick Actions</h5>
                    <small class="text-muted">Claims & insurance management</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('hmo.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('hmo.workbench', ['queue_filter' => 'pending']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border-color: #d8b4fe;">
                            <i class="mdi mdi-shield-check dash-shortcut-icon" style="color: #6b21a8;"></i>
                            <h6 class="dash-shortcut-title" style="color: #6b21a8;">HMO</h6>
                            <small style="color: #6b21a8;">Workbench</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('hmo.reports'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('hmo.reports') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dbeafe, #bfdbfe); border-color: #93c5fd;">
                            <i class="mdi mdi-chart-bar dash-shortcut-icon" style="color: #1e40af;"></i>
                            <h6 class="dash-shortcut-title" style="color: #1e40af;">Reports</h6>
                            <small style="color: #1e40af;">Analytics</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('patient.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('patient.index', ['hmo_only' => 1]) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dcfce7, #bbf7d0); border-color: #86efac;">
                            <i class="mdi mdi-account-multiple dash-shortcut-icon" style="color: #166534;"></i>
                            <h6 class="dash-shortcut-title" style="color: #166534;">HMO Patients</h6>
                            <small style="color: #166534;">Enrollee list</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('hmo.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('hmo.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fff7ed, #ffedd5); border-color: #fed7aa;">
                            <i class="mdi mdi-hospital-building dash-shortcut-icon" style="color: #c2410c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #c2410c;">HMO List</h6>
                            <small style="color: #c2410c;">All providers</small>
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
                    <h5 class="dash-section-title">Claims Trend</h5>
                    <small class="text-muted">Monthly claims activity</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="hmoClaimsChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-success bg-opacity-10">
                    <i class="mdi mdi-chart-pie text-success"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Provider Distribution</h5>
                    <small class="text-muted">Patients by HMO provider</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="hmoDistributionChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Recent Activity --}}
@include('admin.dashboards.components.mini-table', [
    'containerId' => 'hmo-activity',
    'title' => 'Recent HMO Activity',
    'subtitle' => 'Latest claims and validations',
    'icon' => 'mdi-shield-check',
    'iconBg' => 'primary'
])
