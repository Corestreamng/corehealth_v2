{{-- Audit Dashboard Tab (Enhanced) --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #111827 0%, #1f2937 50%, #374151 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-shield-check"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">Internal Audit</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-security me-1"></i> Governance & Oversight
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Quick Stats --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #065f46, #059669);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Today's Revenue</p>
                    <h2 class="dash-stat-value" id="audit-stat-revenue">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-cash-check me-1"></i>Total sales today</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-cash-multiple"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #1e40af, #3b82f6);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Active Admissions</p>
                    <h2 class="dash-stat-value" id="audit-stat-admissions">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-hospital-building me-1"></i>Currently admitted</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-bed"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #991b1b, #dc2626);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Pending Requisitions</p>
                    <h2 class="dash-stat-value" id="audit-stat-requisitions">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-alert me-1"></i>Unfulfilled requests</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-cart-arrow-right"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #7e22ce, #a855f7);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Diagnostic Orders</p>
                    <h2 class="dash-stat-value" id="audit-stat-diagnostics">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-microscope me-1"></i>Lab/Imaging today</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-flask"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'audit-insights'])

{{-- Quick Actions --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-danger bg-opacity-10">
                    <i class="mdi mdi-flash-circle text-danger"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Audit Tools</h5>
                    <small class="text-muted">Workbench and reporting modules</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('audit.workbench'))
                <div class="col-6 col-md-4">
                    <a href="{{ route('audit.workbench') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fee2e2, #fecaca); border-color: #fca5a5;">
                            <i class="mdi mdi-shield-search dash-shortcut-icon" style="color: #991b1b;"></i>
                            <h6 class="dash-shortcut-title" style="color: #991b1b;">Audit</h6>
                            <small style="color: #991b1b;">Workbench</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('audit-logs.index'))
                <div class="col-6 col-md-4">
                    <a href="{{ route('audit-logs.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f1f5f9, #e2e8f0); border-color: #cbd5e1;">
                            <i class="mdi mdi-history dash-shortcut-icon" style="color: #475569;"></i>
                            <h6 class="dash-shortcut-title" style="color: #475569;">System</h6>
                            <small style="color: #475569;">Audit Logs</small>
                        </div>
                    </a>
                </div>
                @endif
                
                @if(Route::has('general-ledger.index'))
                <div class="col-6 col-md-4">
                    <a href="{{ route('general-ledger.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fce7f3, #fbcfe8); border-color: #f9a8d4;">
                            <i class="mdi mdi-notebook dash-shortcut-icon" style="color: #be185d;"></i>
                            <h6 class="dash-shortcut-title" style="color: #be185d;">General</h6>
                            <small style="color: #be185d;">Ledger</small>
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
    <div class="col-xl-8">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-success bg-opacity-10">
                    <i class="mdi mdi-chart-line text-success"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Revenue Audit Trend</h5>
                    <small class="text-muted">Total sales generated per day</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="auditRevenueTrendChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dash-section-card h-100">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-warning bg-opacity-10">
                    <i class="mdi mdi-alert-circle text-warning"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Risk Flags Today</h5>
                    <small class="text-muted">Anomalies requiring oversight</small>
                </div>
            </div>
            <div class="d-flex flex-column h-100 pb-3 justify-content-center">
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom border-light">
                    <div>
                        <h6 class="mb-0 text-muted">System Discounts</h6>
                    </div>
                    <h4 class="mb-0 text-warning" id="audit-stat-discounts">—</h4>
                </div>
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom border-light">
                    <div>
                        <h6 class="mb-0 text-muted">Payment Refunds</h6>
                    </div>
                    <h4 class="mb-0 text-danger" id="audit-stat-refunds">—</h4>
                </div>
                <div class="p-3 text-center">
                    <a href="{{ route('audit.workbench') }}" class="btn btn-sm btn-outline-dark">Investigate Now <i class="mdi mdi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Recent Activity --}}
@include('admin.dashboards.components.mini-table', [
    'containerId' => 'audit-activity',
    'title' => 'Recent System Audit Logs',
    'subtitle' => 'Latest system events captured',
    'icon' => 'mdi-history',
    'iconBg' => 'dark'
])
