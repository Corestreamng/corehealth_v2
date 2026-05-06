{{-- Morgue Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #475569 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-emoticon-dead"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">Welcome back, {{ Auth::user()->name ?? 'Morgue Attendant' }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-grave-stone me-1"></i> Morgue Management
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'morgue-insights'])

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #1e293b, #334155);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Current Occupants</p>
                    <h2 class="dash-stat-value" id="morgue-stat-occupants">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-archive-outline me-1"></i>Currently stored</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-emoticon-dead"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #1e40af, #3b82f6);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Admissions Today</p>
                    <h2 class="dash-stat-value" id="morgue-stat-admissions">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-login-variant me-1"></i>Bodies received</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-login-variant"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #059669, #10b981);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Releases Today</p>
                    <h2 class="dash-stat-value" id="morgue-stat-releases">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-logout-variant me-1"></i>Bodies released</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-logout-variant"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #b45309, #f59e0b);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Pending Release</p>
                    <h2 class="dash-stat-value" id="morgue-stat-pending">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-clock-outline me-1"></i>Awaiting pickup</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-clock-outline"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Queues --}}
@include('admin.dashboards.components.queue-widget', ['containerId' => 'morgue-queues'])

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-dark bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-dark"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Morgue Quick Actions</h5>
                    <small class="text-muted">Operations & record management</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('morgue.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('morgue.workbench') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f8fafc, #f1f5f9); border-color: #e2e8f0;">
                            <i class="mdi mdi-desktop-mac-dashboard dash-shortcut-icon" style="color: #475569;"></i>
                            <h6 class="dash-shortcut-title" style="color: #475569;">Morgue Workbench</h6>
                            <small style="color: #475569;">Main Operations</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('morgue.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('morgue.workbench', ['action' => 'admission']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f1f5f9, #e2e8f0); border-color: #cbd5e1;">
                            <i class="mdi mdi-login-variant dash-shortcut-icon" style="color: #334155;"></i>
                            <h6 class="dash-shortcut-title" style="color: #334155;">New Admission</h6>
                            <small style="color: #334155;">Body Intake</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('morgue.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('morgue.workbench', ['queue' => 'admitted']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f1f5f9, #e2e8f0); border-color: #cbd5e1;">
                            <i class="mdi mdi-archive-outline dash-shortcut-icon" style="color: #334155;"></i>
                            <h6 class="dash-shortcut-title" style="color: #334155;">Occupants List</h6>
                            <small style="color: #334155;">Current Storage</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('death-records.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('death-records.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fef2f2, #fee2e2); border-color: #fecaca;">
                            <i class="mdi mdi-file-document-outline dash-shortcut-icon" style="color: #b91c1c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #b91c1c;">Death Records</h6>
                            <small style="color: #b91c1c;">Certificates</small>
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
                <div class="dash-section-icon bg-secondary bg-opacity-10">
                    <i class="mdi mdi-chart-line text-secondary"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Admission Trend</h5>
                    <small class="text-muted">Monthly intake pattern</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="morgueAdmissionChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-info bg-opacity-10">
                    <i class="mdi mdi-chart-donut text-info"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Status Breakdown</h5>
                    <small class="text-muted">Admitted vs Released</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="morgueStatusChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Recent Activity --}}
@include('admin.dashboards.components.mini-table', [
    'containerId' => 'morgue-activity',
    'title' => 'Recent Morgue Activity',
    'subtitle' => 'Latest admissions & releases today',
    'icon' => 'mdi-history',
    'iconBg' => 'dark'
])
