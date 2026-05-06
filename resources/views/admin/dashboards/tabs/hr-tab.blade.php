{{-- HR Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 50%, #2563eb 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-account-group"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">HR Operations Control</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-shield-account me-1"></i> Human Resources
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'hr-insights'])

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #1e40af, #3b82f6);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Total Staff</p>
                    <h2 class="dash-stat-value" id="hr-stat-total">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-account-group me-1"></i>Active employees</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-account-group"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #059669, #10b981);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">On Leave Today</p>
                    <h2 class="dash-stat-value" id="hr-stat-leave">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-palm-tree me-1"></i>Approved leave</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-palm-tree"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #b45309, #f59e0b);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Pending Requests</p>
                    <h2 class="dash-stat-value" id="hr-stat-pending">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-clock-outline me-1"></i>Awaiting approval</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-clock-outline"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #7e22ce, #9333ea);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">New Hires</p>
                    <h2 class="dash-stat-value" id="hr-stat-new">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-account-plus me-1"></i>This month</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-account-plus"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Queues --}}
@include('admin.dashboards.components.queue-widget', ['containerId' => 'hr-queues'])

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-primary bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-primary"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">HR Quick Actions</h5>
                    <small class="text-muted">Staffing & compliance</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('staff.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('staff.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #eff6ff, #dbeafe); border-color: #bfdbfe;">
                            <i class="mdi mdi-account-multiple dash-shortcut-icon" style="color: #1e40af;"></i>
                            <h6 class="dash-shortcut-title" style="color: #1e40af;">Staff Directory</h6>
                            <small style="color: #1e40af;">Manage personnel</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('hr.leave-requests.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('hr.leave-requests.index', ['status' => 'pending']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fffbeb, #fef3c7); border-color: #fde68a;">
                            <i class="mdi mdi-calendar-clock dash-shortcut-icon" style="color: #b45309;"></i>
                            <h6 class="dash-shortcut-title" style="color: #b45309;">Leave Approvals</h6>
                            <small style="color: #b45309;">Review requests</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('staff.create'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('staff.create') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f0fdf4, #dcfce7); border-color: #bbf7d0;">
                            <i class="mdi mdi-account-plus dash-shortcut-icon" style="color: #15803d;"></i>
                            <h6 class="dash-shortcut-title" style="color: #15803d;">Onboard Staff</h6>
                            <small style="color: #15803d;">New employee</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('hr.rosters.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('hr.rosters.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f5f3ff, #ede9fe); border-color: #ddd6fe;">
                            <i class="mdi mdi-timetable dash-shortcut-icon" style="color: #5b21b6;"></i>
                            <h6 class="dash-shortcut-title" style="color: #5b21b6;">Duty Roster</h6>
                            <small style="color: #5b21b6;">Shift planning</small>
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
                <div class="dash-section-icon bg-primary bg-opacity-10">
                    <i class="mdi mdi-chart-donut text-primary"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Staff by Department</h5>
                    <small class="text-muted">Distribution across units</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="hrDeptChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        @include('admin.dashboards.components.mini-table', [
            'containerId' => 'hr-activity',
            'title' => 'Recent HR Activity',
            'subtitle' => 'Latest leave requests & updates',
            'icon' => 'mdi-history',
            'iconBg' => 'primary'
        ])
    </div>
</div>
