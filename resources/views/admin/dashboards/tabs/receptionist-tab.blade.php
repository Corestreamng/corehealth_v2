{{-- Receptionist Dashboard Tab (Enhanced) --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 50%, #3b82f6 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-account-tie"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">{{ __('dashboard.welcome_back') }}, {{ Auth::user()->name ?? __('dashboard.tab_receptionist') }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-desktop-mac-dashboard me-1"></i> {{ __('dashboard.front_desk') }}
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
                    <p class="dash-stat-label">{{ __('dashboard.new_patients') }}</p>
                    <h2 class="dash-stat-value" id="recep-stat-new-patients">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-clock-outline me-1"></i>{{ __('dashboard.registered_today') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-account-plus"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #6d28d9, #8b5cf6);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.returning_patients') }}</p>
                    <h2 class="dash-stat-value" id="recep-stat-returning-patients">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-account-check me-1"></i>{{ __('dashboard.seen_today') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-account-check"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #0f766e, #14b8a6);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.admissions') }}</p>
                    <h2 class="dash-stat-value" id="recep-stat-admissions">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-hospital me-1"></i>{{ __('dashboard.active_today') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-hospital"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #c2410c, #ea580c);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.bookings') }}</p>
                    <h2 class="dash-stat-value" id="recep-stat-bookings">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-calendar-check me-1"></i>{{ __('dashboard.scheduled_today') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-calendar-check"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'recep-insights'])

{{-- Live Queues --}}
@include('admin.dashboards.components.queue-widget', ['containerId' => 'recep-queues'])

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-primary bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-primary"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.quick_actions') }}</h5>
                    <small class="text-muted">{{ __('dashboard.front_desk_operations') }}</small>
                </div>
            </div>
            <div class="row g-3">
                @if(Route::has('reception.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('reception.workbench', ['queue_filter' => 'waiting']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border-color: #d8b4fe;">
                            <i class="mdi mdi-desktop-mac-dashboard dash-shortcut-icon" style="color: #6b21a8;"></i>
                            <h6 class="dash-shortcut-title" style="color: #6b21a8;">{{ __('dashboard.workbench') }}</h6>
                            <small style="color: #6b21a8;">{{ __('dashboard.main_desk') }}</small>
                        </div>
                    </a>
                </div>
                @endif
                @if(Route::has('patient.create'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('patient.create') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dcfce7, #bbf7d0); border-color: #86efac;">
                            <i class="mdi mdi-account-plus dash-shortcut-icon" style="color: #166534;"></i>
                            <h6 class="dash-shortcut-title" style="color: #166534;">{{ __('dashboard.new_patient') }}</h6>
                            <small style="color: #166534;">{{ __('dashboard.register') }}</small>
                        </div>
                    </a>
                </div>
                @endif
                @if(Route::has('patient.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('patient.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #e0f2fe, #bae6fd); border-color: #7dd3fc;">
                            <i class="mdi mdi-account-multiple dash-shortcut-icon" style="color: #0369a1;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0369a1;">{{ __('dashboard.all_patients') }}</h6>
                            <small style="color: #0369a1;">{{ __('dashboard.directory') }}</small>
                        </div>
                    </a>
                </div>
                @endif
                @if(Route::has('billing.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('billing.workbench', ['queue_filter' => 'all']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fff7ed, #ffedd5); border-color: #fed7aa;">
                            <i class="mdi mdi-cash-register dash-shortcut-icon" style="color: #c2410c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #c2410c;">{{ __('dashboard.billing') }}</h6>
                            <small style="color: #c2410c;">{{ __('dashboard.payments') }}</small>
                        </div>
                    </a>
                </div>
                @endif
                @if(Route::has('appointments.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('appointments.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fef3c7, #fde68a); border-color: #fcd34d;">
                            <i class="mdi mdi-calendar-check dash-shortcut-icon" style="color: #b45309;"></i>
                            <h6 class="dash-shortcut-title" style="color: #b45309;">{{ __('dashboard.appointments') }}</h6>
                            <small style="color: #b45309;">{{ __('dashboard.schedule') }}</small>
                        </div>
                    </a>
                </div>
                @endif
                @if(Route::has('insurance.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('insurance.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f1f5f9, #e2e8f0); border-color: #cbd5e1;">
                            <i class="mdi mdi-shield-account dash-shortcut-icon" style="color: #334155;"></i>
                            <h6 class="dash-shortcut-title" style="color: #334155;">{{ __('dashboard.insurance') }}</h6>
                            <small style="color: #334155;">{{ __('dashboard.verification') }}</small>
                        </div>
                    </a>
                </div>
                @endif
                @if(Route::has('emergency.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('emergency.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fee2e2, #fecaca); border-color: #fca5a5;">
                            <i class="mdi mdi-alert-octagon dash-shortcut-icon" style="color: #991b1b;"></i>
                            <h6 class="dash-shortcut-title" style="color: #991b1b;">{{ __('dashboard.emergency') }}</h6>
                            <small style="color: #991b1b;">{{ __('dashboard.triage') }}</small>
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
    <div class="col-xl-4">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-warning bg-opacity-10">
                    <i class="mdi mdi-chart-bar text-warning"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.hourly_patient_flow') }}</h5>
                    <small class="text-muted">{{ __('dashboard.todays_arrival_pattern') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="recepHourlyFlowChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-primary bg-opacity-10">
                    <i class="mdi mdi-account-multiple-plus text-primary"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.patient_registrations') }}</h5>
                    <small class="text-muted">{{ __('dashboard.this_months_trend') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="recepRegistrationsChart"></canvas>
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
                    <h5 class="dash-section-title">{{ __('dashboard.patient_breakdown') }}</h5>
                    <small class="text-muted">{{ __('dashboard.new_returning_hmo') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="recepPatientBreakdownChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Recent Activity --}}
@include('admin.dashboards.components.mini-table', [
    'containerId' => 'recep-activity',
    'title' => __('dashboard.recent_patient_activity'),
    'subtitle' => __('dashboard.latest_queue_entries'),
    'icon' => 'mdi-history',
    'iconBg' => 'primary'
])
