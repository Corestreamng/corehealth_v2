{{-- Lab / Imaging Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #164e63 0%, #0e7490 50%, #06b6d4 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-flask"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">Welcome back, {{ Auth::user()->name ?? 'Lab Scientist' }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-flask me-1"></i> Lab & Imaging
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #164e63, #0e7490);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Lab Queue</p>
                    <h2 class="dash-stat-value" id="lab-stat-queue">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-flask me-1"></i>Pending tests</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-flask"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #3730a3, #4f46e5);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Imaging Queue</p>
                    <h2 class="dash-stat-value" id="lab-stat-imaging">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-radiobox-marked me-1"></i>Pending scans</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-radiobox-marked"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #166534, #16a34a);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Completed Today</p>
                    <h2 class="dash-stat-value" id="lab-stat-completed">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-check-circle me-1"></i>Lab + Imaging</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-check-circle"></i></div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #86198f, #a21caf);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Total Services</p>
                    <h2 class="dash-stat-value" id="lab-stat-services">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-format-list-bulleted me-1"></i>Available tests</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-format-list-bulleted"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-info bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-info"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Quick Actions</h5>
                    <small class="text-muted">Lab & imaging tools</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('lab.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('lab.workbench') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #cffafe, #a5f3fc); border-color: #67e8f9;">
                            <i class="mdi mdi-flask dash-shortcut-icon" style="color: #0e7490;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0e7490;">Lab</h6>
                            <small style="color: #0e7490;">Workbench</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('imaging.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('imaging.workbench') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #e0e7ff, #c7d2fe); border-color: #a5b4fc;">
                            <i class="mdi mdi-radiobox-marked dash-shortcut-icon" style="color: #3730a3;"></i>
                            <h6 class="dash-shortcut-title" style="color: #3730a3;">Imaging</h6>
                            <small style="color: #3730a3;">Workbench</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('lab.results'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('lab.results') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dcfce7, #bbf7d0); border-color: #86efac;">
                            <i class="mdi mdi-clipboard-check dash-shortcut-icon" style="color: #166534;"></i>
                            <h6 class="dash-shortcut-title" style="color: #166534;">Results</h6>
                            <small style="color: #166534;">Completed</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('patient.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('patient.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fce7f3, #fbcfe8); border-color: #f9a8d4;">
                            <i class="mdi mdi-account-multiple dash-shortcut-icon" style="color: #be185d;"></i>
                            <h6 class="dash-shortcut-title" style="color: #be185d;">Patients</h6>
                            <small style="color: #be185d;">Directory</small>
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
                <div class="dash-section-icon bg-info bg-opacity-10">
                    <i class="mdi mdi-chart-line text-info"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Lab Requests Trend</h5>
                    <small class="text-muted">Daily test requests</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="labRequestsChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-primary bg-opacity-10">
                    <i class="mdi mdi-chart-pie text-primary"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Test Categories</h5>
                    <small class="text-muted">Distribution by type</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="labCategoriesChart"></canvas>
            </div>
        </div>
    </div>
</div>
