{{-- Theatre Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #7f1d1d 0%, #b91c1c 50%, #ef4444 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-pulse"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">Theatre Command Center</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-hospital-building me-1"></i> Surgical Services
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'theatre-insights'])

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #1e40af, #3b82f6);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Scheduled Today</p>
                    <h2 class="dash-stat-value" id="theatre-stat-scheduled">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-calendar-clock me-1"></i>Planned surgeries</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-calendar-clock"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #be123c, #e11d48);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Ongoing Surgery</p>
                    <h2 class="dash-stat-value" id="theatre-stat-ongoing">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-pulse me-1"></i>Currently in theatre</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-pulse"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #059669, #10b981);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Completed Today</p>
                    <h2 class="dash-stat-value" id="theatre-stat-completed">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-check-circle-outline me-1"></i>Post-op stage</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-check-circle-outline"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #b45309, #f59e0b);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Pending Start</p>
                    <h2 class="dash-stat-value" id="theatre-stat-pending">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-clock-outline me-1"></i>Awaiting prep</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-clock-outline"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Queues --}}
@include('admin.dashboards.components.queue-widget', ['containerId' => 'theatre-queues'])

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-danger bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-danger"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Theatre Quick Actions</h5>
                    <small class="text-muted">Surgical planning & records</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('theatre.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('theatre.workbench') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fff1f2, #ffe4e6); border-color: #fecdd3;">
                            <i class="mdi mdi-desktop-mac-dashboard dash-shortcut-icon" style="color: #be123c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #be123c;">Theatre Workbench</h6>
                            <small style="color: #be123c;">Main Ops</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('procedures.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('procedures.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fef2f2, #fee2e2); border-color: #fecaca;">
                            <i class="mdi mdi-clipboard-list-outline dash-shortcut-icon" style="color: #b91c1c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #b91c1c;">Procedure List</h6>
                            <small style="color: #b91c1c;">All Cases</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('theatre.schedule'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('theatre.schedule') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fefce8, #fef9c3); border-color: #fef08a;">
                            <i class="mdi mdi-calendar-month dash-shortcut-icon" style="color: #a16207;"></i>
                            <h6 class="dash-shortcut-title" style="color: #a16207;">Theatre Schedule</h6>
                            <small style="color: #a16207;">Booking</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('procedure-notes.create'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('procedure-notes.create') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f0fdfa, #ccfbf1); border-color: #99f6e4;">
                            <i class="mdi mdi-file-document-edit-outline dash-shortcut-icon" style="color: #0d9488;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0d9488;">Procedure Notes</h6>
                            <small style="color: #0d9488;">Documentation</small>
                        </div>
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-4">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-danger bg-opacity-10">
                    <i class="mdi mdi-chart-donut text-danger"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Procedure Categories</h5>
                    <small class="text-muted">Breakdown by type</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="theatreDeptChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        @include('admin.dashboards.components.mini-table', [
            'containerId' => 'theatre-activity',
            'title' => 'Recent Surgical Activity',
            'subtitle' => 'Latest scheduled & completed cases',
            'icon' => 'mdi-history',
            'iconBg' => 'danger'
        ])
    </div>
</div>
