{{-- ESS Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #0369a1 0%, #0ea5e9 50%, #38bdf8 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-account-circle"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">{{ __('dashboard.hello') }}, {{ Auth::user()->name }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-shield-account me-1"></i> {{ __('dashboard.staff_portal') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'ess-insights'])

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #0284c7, #0ea5e9);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.leave_balance') }}</p>
                    <h2 class="dash-stat-value" id="ess-stat-balance">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-wallet-membership me-1"></i>{{ __('dashboard.days_remaining') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-wallet-membership"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #b45309, #f59e0b);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.pending_requests') }}</p>
                    <h2 class="dash-stat-value" id="ess-stat-pending">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-clock-outline me-1"></i>{{ __('dashboard.awaiting_approval') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-clock-outline"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #059669, #10b981);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.upcoming_leave') }}</p>
                    <h2 class="dash-stat-value" id="ess-stat-upcoming">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-calendar-check me-1"></i>{{ __('dashboard.approved_trips') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-calendar-check"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #4f46e5, #6366f1);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.recent_payslip') }}</p>
                    <h2 class="dash-stat-value" id="ess-stat-payslip">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-cash-multiple me-1"></i>{{ __('dashboard.last_generated') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-cash-multiple"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Personal Stats --}}
@include('admin.dashboards.components.queue-widget', [
    'containerId' => 'ess-queues',
    'title' => __('dashboard.my_request_summary'),
    'subtitle' => __('dashboard.personal_hr_pipeline'),
    'icon' => 'mdi-account-check',
    'iconColor' => 'primary'
])

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-info bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-info"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.my_actions') }}</h5>
                    <small class="text-muted">{{ __('dashboard.personal_hr_tools') }}</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('hr.ess.my-leave.request'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('hr.ess.my-leave.request') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f0fdfa, #ccfbf1); border-color: #99f6e4;">
                            <i class="mdi mdi-calendar-plus dash-shortcut-icon" style="color: #0d9488;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0d9488;">{{ __('dashboard.apply_for_leave') }}</h6>
                            <small style="color: #0d9488;">{{ __('dashboard.request_time_off') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('hr.ess.my-payslips'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('hr.ess.my-payslips') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fefce8, #fef9c3); border-color: #fef08a;">
                            <i class="mdi mdi-cash-multiple dash-shortcut-icon" style="color: #a16207;"></i>
                            <h6 class="dash-shortcut-title" style="color: #a16207;">{{ __('dashboard.my_payslips') }}</h6>
                            <small style="color: #a16207;">{{ __('dashboard.earnings') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('hr.ess.my-profile'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('hr.ess.my-profile') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #eff6ff, #dbeafe); border-color: #bfdbfe;">
                            <i class="mdi mdi-file-document-edit-outline dash-shortcut-icon" style="color: #1e40af;"></i>
                            <h6 class="dash-shortcut-title" style="color: #1e40af;">{{ __('dashboard.edit_profile') }}</h6>
                            <small style="color: #1e40af;">{{ __('dashboard.update_info') }}</small>
                        </div>
                    </a>
                </div>

                <div class="col-6 col-md-3">
                    <a href="{{ route('hr.ess.my-profile') }}#password" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f5f3ff, #ede9fe); border-color: #ddd6fe;">
                            <i class="mdi mdi-lock-reset dash-shortcut-icon" style="color: #5b21b6;"></i>
                            <h6 class="dash-shortcut-title" style="color: #5b21b6;">{{ __('dashboard.security') }}</h6>
                            <small style="color: #5b21b6;">{{ __('dashboard.change_password') }}</small>
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
                <div class="dash-section-icon bg-info bg-opacity-10">
                    <i class="mdi mdi-chart-donut text-info"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">{{ __('dashboard.leave_balance') }}</h5>
                    <small class="text-muted">{{ __('dashboard.remaining_days_per_type') }}</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="essLeaveChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        @include('admin.dashboards.components.mini-table', [
            'containerId' => 'ess-activity',
            'title' => __('dashboard.my_recent_requests'),
            'subtitle' => __('dashboard.latest_status_updates'),
            'icon' => 'mdi-history',
            'iconBg' => 'info'
        ])
    </div>
</div>
