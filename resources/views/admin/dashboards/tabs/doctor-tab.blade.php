{{-- Doctor Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #1e3a5f 0%, #1e40af 50%, #2563eb 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-stethoscope"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">Welcome back, {{ Auth::user()->name ?? 'Doctor' }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-doctor me-1"></i> Clinical Dashboard
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #1e40af, #3b82f6);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Consultations</p>
                    <h2 class="dash-stat-value" id="doc-stat-consultations">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-clipboard-text me-1"></i>Today's sessions</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-clipboard-text"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #065f46, #10b981);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Ward Rounds</p>
                    <h2 class="dash-stat-value" id="doc-stat-ward-rounds">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-bed me-1"></i>Admitted patients</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-bed"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #7e22ce, #a855f7);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">My Patients</p>
                    <h2 class="dash-stat-value" id="doc-stat-patients">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-account-multiple me-1"></i>Under care</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-account-multiple"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #b45309, #f59e0b);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Appointments</p>
                    <h2 class="dash-stat-value" id="doc-stat-appointments">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-calendar-check me-1"></i>Scheduled today</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-calendar-check"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'doc-insights'])

{{-- Live Queues --}}
@include('admin.dashboards.components.queue-widget', ['containerId' => 'doc-queues'])

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
                    <small class="text-muted">Clinical tools & workflows</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('encounters.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('encounters.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #e0f2fe, #bae6fd); border-color: #7dd3fc;">
                            <i class="mdi mdi-clipboard-text dash-shortcut-icon" style="color: #0369a1;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0369a1;">Encounters</h6>
                            <small style="color: #0369a1;">Consultations</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('patient.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('patient.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dcfce7, #bbf7d0); border-color: #86efac;">
                            <i class="mdi mdi-account-multiple dash-shortcut-icon" style="color: #166534;"></i>
                            <h6 class="dash-shortcut-title" style="color: #166534;">Patients</h6>
                            <small style="color: #166534;">Directory</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('add-to-queue'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('add-to-queue') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fff7ed, #ffedd5); border-color: #fed7aa;">
                            <i class="mdi mdi-account-plus dash-shortcut-icon" style="color: #c2410c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #c2410c;">Add to Queue</h6>
                            <small style="color: #c2410c;">New visit</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('lab.results'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('lab.results') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border-color: #d8b4fe;">
                            <i class="mdi mdi-flask dash-shortcut-icon" style="color: #7e22ce;"></i>
                            <h6 class="dash-shortcut-title" style="color: #7e22ce;">Lab Results</h6>
                            <small style="color: #7e22ce;">Review</small>
                        </div>
                    </a>
                </div>
                @endif
            </div>

            <div class="row g-3 mt-1">
                @if(Route::has('prescriptions.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('prescriptions.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #ccfbf1, #99f6e4); border-color: #5eead4;">
                            <i class="mdi mdi-pill dash-shortcut-icon" style="color: #0f766e;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0f766e;">Prescriptions</h6>
                            <small style="color: #0f766e;">Prescribe</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('imaging.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('imaging.workbench', ['queue_filter' => 'results']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dbeafe, #bfdbfe); border-color: #93c5fd;">
                            <i class="mdi mdi-radiobox-marked dash-shortcut-icon" style="color: #1e40af;"></i>
                            <h6 class="dash-shortcut-title" style="color: #1e40af;">Imaging</h6>
                            <small style="color: #1e40af;">Orders & results</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('notes.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('notes.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f1f5f9, #e2e8f0); border-color: #cbd5e1;">
                            <i class="mdi mdi-note-text dash-shortcut-icon" style="color: #334155;"></i>
                            <h6 class="dash-shortcut-title" style="color: #334155;">Notes</h6>
                            <small style="color: #334155;">Clinical notes</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('referrals.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('referrals.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fef3c7, #fde68a); border-color: #fcd34d;">
                            <i class="mdi mdi-account-arrow-right dash-shortcut-icon" style="color: #b45309;"></i>
                            <h6 class="dash-shortcut-title" style="color: #b45309;">Referrals</h6>
                            <small style="color: #b45309;">Send & receive</small>
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
    <div class="col-xl-6">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-primary bg-opacity-10">
                    <i class="mdi mdi-chart-line text-primary"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Consultation Trends</h5>
                    <small class="text-muted">Daily patient encounters</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="doctorConsultationsChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-success bg-opacity-10">
                    <i class="mdi mdi-chart-pie text-success"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Patient Outcomes</h5>
                    <small class="text-muted">Discharge & referral overview</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="doctorOutcomesChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Recent Activity --}}
@include('admin.dashboards.components.mini-table', [
    'containerId' => 'doc-activity',
    'title' => 'Recent Consultations',
    'subtitle' => 'Latest patient encounters',
    'icon' => 'mdi-stethoscope',
    'iconBg' => 'primary'
])
