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
                            <h3 class="dash-welcome-title">{{ __('dashboard.theatre_command_center') }}</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-hospital-building me-1"></i> {{ __('dashboard.surgical_services') }}
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
                    <p class="dash-stat-label">{{ __('dashboard.scheduled_today') }}</p>
                    <h2 class="dash-stat-value" id="theatre-stat-scheduled">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-calendar-clock me-1"></i>{{ __('dashboard.planned_surgeries') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-calendar-clock"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #be123c, #e11d48);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.ongoing_surgery') }}</p>
                    <h2 class="dash-stat-value" id="theatre-stat-ongoing">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-pulse me-1"></i>{{ __('dashboard.currently_in_theatre') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-pulse"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #059669, #10b981);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.completed_today') }}</p>
                    <h2 class="dash-stat-value" id="theatre-stat-completed">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-check-circle-outline me-1"></i>{{ __('dashboard.post_op_stage') }}</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-check-circle-outline"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #b45309, #f59e0b);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">{{ __('dashboard.pending_start') }}</p>
                    <h2 class="dash-stat-value" id="theatre-stat-pending">0</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-clock-outline me-1"></i>{{ __('dashboard.awaiting_prep') }}</span>
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
                    <h5 class="dash-section-title">{{ __('dashboard.theatre_quick_actions') }}</h5>
                    <small class="text-muted">{{ __('dashboard.surgical_planning_records') }}</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('theatre.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('theatre.workbench') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fff1f2, #ffe4e6); border-color: #fecdd3;">
                            <i class="mdi mdi-desktop-mac-dashboard dash-shortcut-icon" style="color: #be123c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #be123c;">{{ __('dashboard.theatre_workbench') }}</h6>
                            <small style="color: #be123c;">{{ __('dashboard.main_ops') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('procedures.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('procedures.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fef2f2, #fee2e2); border-color: #fecaca;">
                            <i class="mdi mdi-clipboard-list-outline dash-shortcut-icon" style="color: #b91c1c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #b91c1c;">{{ __('dashboard.procedure_list') }}</h6>
                            <small style="color: #b91c1c;">{{ __('dashboard.all_cases') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('theatre.schedule'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('theatre.schedule') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fefce8, #fef9c3); border-color: #fef08a;">
                            <i class="mdi mdi-calendar-month dash-shortcut-icon" style="color: #a16207;"></i>
                            <h6 class="dash-shortcut-title" style="color: #a16207;">{{ __('dashboard.theatre_schedule') }}</h6>
                            <small style="color: #a16207;">{{ __('dashboard.booking') }}</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('procedure-notes.create'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('procedure-notes.create') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f0fdfa, #ccfbf1); border-color: #99f6e4;">
                            <i class="mdi mdi-file-document-edit-outline dash-shortcut-icon" style="color: #0d9488;"></i>
                            <h6 class="dash-shortcut-title" style="color: #0d9488;">{{ __('dashboard.procedure_notes') }}</h6>
                            <small style="color: #0d9488;">{{ __('dashboard.documentation') }}</small>
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
                    <h5 class="dash-section-title">{{ __('dashboard.procedure_categories') }}</h5>
                    <small class="text-muted">{{ __('dashboard.breakdown_by_type') }}</small>
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
            'title' => __('dashboard.recent_surgical_activity'),
            'subtitle' => __('dashboard.latest_scheduled_completed_cases'),
            'icon' => 'mdi-history',
            'iconBg' => 'danger'
        ])
    </div>
</div>
