{{-- Administration Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-shield-account"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">{{ __('dashboard.welcome_back') }}, {{ Auth::user()->name ?? __('dashboard.tab_admin') }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-shield-check me-1"></i> {{ __('dashboard.system_admin') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #1e293b, #334155);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.total_staff') }}</p>
                    <h2 class="dash-stat-value" id="admin-stat-staff">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-account-group me-1"></i>{{ __('dashboard.active_members') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-account-group"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #7e22ce, #a855f7);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.total_patients') }}</p>
                    <h2 class="dash-stat-value" id="admin-stat-patients">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-account-multiple me-1"></i>{{ __('dashboard.all_registered') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-account-multiple"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #0e7490, #06b6d4);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.total_clinics') }}</p>
                    <h2 class="dash-stat-value" id="admin-stat-clinics">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-hospital-building me-1"></i>{{ __('dashboard.active_clinics') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-hospital-building"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #065f46, #059669);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.total_revenue') }}</p>
                    <h2 class="dash-stat-value" id="admin-stat-revenue">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-calendar me-1"></i>{{ __('dashboard.all_time') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-chart-line"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'admin-insights'])

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-dark bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-dark"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.administration') }}</h5>
                    <small class="text-muted">{{ __('dashboard.system_configuration_management') }}</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('roles.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('roles.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border-color: #d8b4fe;">
                            <i class="mdi mdi-shield-account dash-shortcut-icon" style="color: #6b21a8;"></i>
                            <h6 class="dash-shortcut-title" style="color: #6b21a8;">{{ __('dashboard.roles') }}</h6>
                            <small style="color: #6b21a8;">{{ __('dashboard.access_control') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('permissions.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('permissions.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #e0f2fe, #bae6fd); border-color: #7dd3fc;">
                            <i class="mdi mdi-lock dash-shortcut-icon" style="color: #0369a1;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0369a1;">{{ __('dashboard.permissions') }}</h6>
                            <small style="color: #0369a1;">{{ __('dashboard.security') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('staff.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('staff.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fff7ed, #ffedd5); border-color: #fed7aa;">
                            <i class="mdi mdi-account-tie dash-shortcut-icon" style="color: #c2410c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #c2410c;">{{ __('dashboard.staff') }}</h6>
                            <small style="color: #c2410c;">{{ __('dashboard.management') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('specializations.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('specializations.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dcfce7, #bbf7d0); border-color: #86efac;">
                            <i class="mdi mdi-star dash-shortcut-icon" style="color: #166534;"></i>
                            <h6 class="dash-shortcut-title" style="color: #166534;">{{ __('dashboard.specializations') }}</h6>
                            <small style="color: #166534;">{{ __('dashboard.departments') }}</small>
                        </div>
                    </a>
                </div>
                @endif
            </div>

            <div class="row g-3 mt-1">
                @if(Route::has('clinics.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('clinics.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #ccfbf1, #99f6e4); border-color: #5eead4;">
                            <i class="mdi mdi-hospital-building dash-shortcut-icon" style="color: #0f766e;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0f766e;">{{ __('dashboard.clinics') }}</h6>
                            <small style="color: #0f766e;">{{ __('dashboard.facilities') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('hmo.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('hmo.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fef3c7, #fde68a); border-color: #fcd34d;">
                            <i class="mdi mdi-cash dash-shortcut-icon" style="color: #b45309;"></i>
                            <h6 class="dash-shortcut-title" style="color: #b45309;">{{ __('dashboard.hmos') }}</h6>
                            <small style="color: #b45309;">{{ __('dashboard.insurance') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('hospital-config.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('hospital-config.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f1f5f9, #e2e8f0); border-color: #cbd5e1;">
                            <i class="mdi mdi-cogs dash-shortcut-icon" style="color: #334155;"></i>
                            <h6 class="dash-shortcut-title" style="color: #334155;">{{ __('dashboard.hospital_config') }}</h6>
                            <small style="color: #334155;">{{ __('dashboard.settings') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('audit-logs.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('audit-logs.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #e0e7ff, #c7d2fe); border-color: #a5b4fc;">
                            <i class="mdi mdi-history dash-shortcut-icon" style="color: #3730a3;"></i>
                            <h6 class="dash-shortcut-title" style="color: #3730a3;">{{ __('dashboard.audit_logs') }}</h6>
                            <small style="color: #3730a3;">{{ __('dashboard.activity') }}</small>
                        </div>
                    </a>
                </div>
                @endif
            </div>

            <div class="row g-3 mt-1">
                @if(Route::has('wards.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('wards.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dbeafe, #bfdbfe); border-color: #93c5fd;">
                            <i class="mdi mdi-hospital-marker dash-shortcut-icon" style="color: #1e40af;"></i>
                            <h6 class="dash-shortcut-title" style="color: #1e40af;">{{ __('dashboard.wards') }}</h6>
                            <small style="color: #1e40af;">{{ __('dashboard.units') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('beds.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('beds.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #cffafe, #a5f3fc); border-color: #67e8f9;">
                            <i class="mdi mdi-bed dash-shortcut-icon" style="color: #0e7490;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0e7490;">{{ __('dashboard.beds') }}</h6>
                            <small style="color: #0e7490;">{{ __('dashboard.allocation') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('vaccine-schedule.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('vaccine-schedule.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fee2e2, #fecaca); border-color: #fca5a5;">
                            <i class="mdi mdi-needle dash-shortcut-icon" style="color: #991b1b;"></i>
                            <h6 class="dash-shortcut-title" style="color: #991b1b;">{{ __('dashboard.vaccine_schedule') }}</h6>
                            <small style="color: #991b1b;">{{ __('dashboard.immunization') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('transactions'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('transactions') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dcfce7, #bbf7d0); border-color: #86efac;">
                            <i class="mdi mdi-chart-line dash-shortcut-icon" style="color: #166534;"></i>
                            <h6 class="dash-shortcut-title" style="color: #166534;">{{ __('dashboard.all_transactions') }}</h6>
                            <small style="color: #166534;">{{ __('dashboard.financial') }}</small>
                        </div>
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Charts --}}
<div class="row g-4">
    <div class="col-xl-6">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-success bg-opacity-10">
                    <i class="mdi mdi-chart-line text-success"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.revenue_this_month') }}</h5>
                    <small class="text-muted">{{ __('dashboard.financial_overview') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="adminRevenueChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
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
                <canvas id="adminRegistrationsChart"></canvas>
            </div>
        </div>
    </div>
</div>
