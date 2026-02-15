{{-- Nursing Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #7f1d1d 0%, #b91c1c 50%, #dc2626 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-heart-pulse"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">Welcome back, {{ Auth::user()->name ?? 'Nurse' }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-heart-pulse me-1"></i> Nursing Station
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #991b1b, #b91c1c);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Vitals Queue</p>
                    <h2 class="dash-stat-value" id="nurs-stat-vitals-queue">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-thermometer me-1"></i>Pending vitals</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-thermometer"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #9a3412, #c2410c);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Bed Requests</p>
                    <h2 class="dash-stat-value" id="nurs-stat-bed-requests">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-bed me-1"></i>Awaiting allocation</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-bed"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #7e22ce, #9333ea);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Medication Due</p>
                    <h2 class="dash-stat-value" id="nurs-stat-medication-due">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-pill me-1"></i>To administer</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-pill"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #0e7490, #0891b2);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Admitted Patients</p>
                    <h2 class="dash-stat-value" id="nurs-stat-admitted">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-hospital-building me-1"></i>Currently admitted</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-hospital-building"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-danger bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-danger"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Quick Actions</h5>
                    <small class="text-muted">Nursing station tools</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('nursing.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('nursing.workbench') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fee2e2, #fecaca); border-color: #fca5a5;">
                            <i class="mdi mdi-heart-pulse dash-shortcut-icon" style="color: #991b1b;"></i>
                            <h6 class="dash-shortcut-title" style="color: #991b1b;">Nursing</h6>
                            <small style="color: #991b1b;">Workbench</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('patient.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('patient.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #e0f2fe, #bae6fd); border-color: #7dd3fc;">
                            <i class="mdi mdi-account-multiple dash-shortcut-icon" style="color: #0369a1;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0369a1;">Patients</h6>
                            <small style="color: #0369a1;">Directory</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('wards.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('wards.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border-color: #d8b4fe;">
                            <i class="mdi mdi-hospital-marker dash-shortcut-icon" style="color: #7e22ce;"></i>
                            <h6 class="dash-shortcut-title" style="color: #7e22ce;">Wards</h6>
                            <small style="color: #7e22ce;">Management</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('beds.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('beds.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #ccfbf1, #99f6e4); border-color: #5eead4;">
                            <i class="mdi mdi-bed dash-shortcut-icon" style="color: #0f766e;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0f766e;">Beds</h6>
                            <small style="color: #0f766e;">Allocation</small>
                        </div>
                    </a>
                </div>
                @endif
            </div>

            <div class="row g-3 mt-1">
                @if(Route::has('admission-requests.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('admission-requests.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fff7ed, #ffedd5); border-color: #fed7aa;">
                            <i class="mdi mdi-clipboard-list dash-shortcut-icon" style="color: #c2410c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #c2410c;">Admissions</h6>
                            <small style="color: #c2410c;">Requests</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('vaccine-schedule.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('vaccine-schedule.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fef3c7, #fde68a); border-color: #fcd34d;">
                            <i class="mdi mdi-needle dash-shortcut-icon" style="color: #b45309;"></i>
                            <h6 class="dash-shortcut-title" style="color: #b45309;">Vaccines</h6>
                            <small style="color: #b45309;">Schedule</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('encounters.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('encounters.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dbeafe, #bfdbfe); border-color: #93c5fd;">
                            <i class="mdi mdi-clipboard-text dash-shortcut-icon" style="color: #1e40af;"></i>
                            <h6 class="dash-shortcut-title" style="color: #1e40af;">Encounters</h6>
                            <small style="color: #1e40af;">View all</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('emergency.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('emergency.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fce7f3, #fbcfe8); border-color: #f9a8d4;">
                            <i class="mdi mdi-alert-octagon dash-shortcut-icon" style="color: #be185d;"></i>
                            <h6 class="dash-shortcut-title" style="color: #be185d;">Emergency</h6>
                            <small style="color: #be185d;">Triage</small>
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
                <div class="dash-section-icon bg-danger bg-opacity-10">
                    <i class="mdi mdi-chart-line text-danger"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Vitals Activity</h5>
                    <small class="text-muted">Daily vitals recorded</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="nursingVitalsChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-warning bg-opacity-10">
                    <i class="mdi mdi-chart-pie text-warning"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Bed Occupancy</h5>
                    <small class="text-muted">Current ward status</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="nursingBedChart"></canvas>
            </div>
        </div>
    </div>
</div>
