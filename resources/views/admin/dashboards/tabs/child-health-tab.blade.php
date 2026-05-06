{{-- Child Health Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #065f46 0%, #059669 50%, #10b981 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-baby-face-outline"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">Welcome, {{ Auth::user()->name ?? 'Pediatric Nurse' }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-needle me-1"></i> Child Health & Immunization
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'child-insights'])

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #0284c7, #0ea5e9);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Immunizations Today</p>
                    <h2 class="dash-stat-value" id="child-stat-today">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-needle me-1"></i>Scheduled shots</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-needle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #be123c, #e11d48);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Overdue Shots</p>
                    <h2 class="dash-stat-value" id="child-stat-overdue">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-alert-circle-outline me-1"></i>Missed appointments</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-alert-circle-outline"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #059669, #10b981);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Growth Monitoring</p>
                    <h2 class="dash-stat-value" id="child-stat-growth">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-chart-line me-1"></i>Weight/Height checks</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-chart-line"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #7e22ce, #9333ea);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">New Enrolments</p>
                    <h2 class="dash-stat-value" id="child-stat-new">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-baby-face-outline me-1"></i>This month</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-baby-face-outline"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Queues --}}
@include('admin.dashboards.components.queue-widget', ['containerId' => 'child-queues'])

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-success bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-success"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Child Health Actions</h5>
                    <small class="text-muted">Pediatric tools</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('immunization.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('immunization.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f0fdf4, #dcfce7); border-color: #bbf7d0;">
                            <i class="mdi mdi-needle dash-shortcut-icon" style="color: #15803d;"></i>
                            <h6 class="dash-shortcut-title" style="color: #15803d;">Immunization</h6>
                            <small style="color: #15803d;">Administer</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('child-growth.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('child-growth.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f0f9ff, #e0f2fe); border-color: #bae6fd;">
                            <i class="mdi mdi-chart-areaspline dash-shortcut-icon" style="color: #0369a1;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0369a1;">Growth Charts</h6>
                            <small style="color: #0369a1;">Monitoring</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('patient.create'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('patient.create', ['type' => 'child']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fff7ed, #ffedd5); border-color: #fed7aa;">
                            <i class="mdi mdi-account-plus-outline dash-shortcut-icon" style="color: #c2410c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #c2410c;">New Born</h6>
                            <small style="color: #c2410c;">Registration</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('nursing.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('nursing.workbench', ['queue' => 'pediatrics']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f5f3ff, #ede9fe); border-color: #ddd6fe;">
                            <i class="mdi mdi-hospital-box-outline dash-shortcut-icon" style="color: #5b21b6;"></i>
                            <h6 class="dash-shortcut-title" style="color: #5b21b6;">Pediatric Clinic</h6>
                            <small style="color: #5b21b6;">Workbench</small>
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
                <div class="dash-section-icon bg-success bg-opacity-10">
                    <i class="mdi mdi-chart-donut text-success"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Schedule Status</h5>
                    <small class="text-muted">Total vaccine schedule health</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="childStatusChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        @include('admin.dashboards.components.mini-table', [
            'containerId' => 'child-activity',
            'title' => 'Recent Pediatric Activity',
            'subtitle' => 'Latest immunizations & growth entries',
            'icon' => 'mdi-history',
            'iconBg' => 'success'
        ])
    </div>
</div>
