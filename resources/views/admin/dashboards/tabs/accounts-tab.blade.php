{{-- Accounts / Audit Dashboard Tab --}}

{{-- Welcome Card --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="dash-welcome" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);">
            <div class="dash-welcome-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="dash-welcome-avatar">
                            <i class="mdi mdi-chart-areaspline"></i>
                        </div>
                        <div>
                            <h3 class="dash-welcome-title">Accounts & Audit</h3>
                            <div class="dash-welcome-sub">
                                <i class="mdi mdi-calendar-clock me-2"></i>
                                <span id="currentDateTime"></span>
                            </div>
                        </div>
                    </div>
                    <div class="dash-welcome-badge">
                        <i class="mdi mdi-finance me-1"></i> Financial Intelligence
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Financial KPI Cards --}}
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #065f46, #059669);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Today's Revenue</p>
                    <h2 class="dash-stat-value" id="acct-stat-today-revenue">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-clock-outline me-1"></i>Real-time</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-cash-check"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #1e40af, #3b82f6);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Month Revenue</p>
                    <h2 class="dash-stat-value" id="acct-stat-month-revenue">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-calendar me-1"></i>This month</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-chart-line"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #991b1b, #dc2626);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Outstanding</p>
                    <h2 class="dash-stat-value" id="acct-stat-outstanding">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-alert me-1"></i>Unpaid bills</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-cash-clock"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="dash-stat-card" style="background: linear-gradient(145deg, #7e22ce, #a855f7);">
            <div class="dash-stat-body">
                <div>
                    <p class="dash-stat-label">Collection Rate</p>
                    <h2 class="dash-stat-value" id="acct-stat-collection-rate">—</h2>
                    <span class="dash-stat-hint"><i class="mdi mdi-percent me-1"></i>This month</span>
                </div>
                <div class="dash-stat-icon"><i class="mdi mdi-percent"></i></div>
            </div>
        </div>
    </div>
</div>

{{-- Live Insights Strip --}}
@include('admin.dashboards.components.insights-strip', ['containerId' => 'acct-insights'])

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
                    <small class="text-muted">Financial management tools</small>
                </div>
            </div>

            <div class="row g-3">
                @if(Route::has('billing.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('billing.workbench', ['queue_filter' => 'all']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dbeafe, #bfdbfe); border-color: #93c5fd;">
                            <i class="mdi mdi-cash-register dash-shortcut-icon" style="color: #1e40af;"></i>
                            <h6 class="dash-shortcut-title" style="color: #1e40af;">Billing</h6>
                            <small style="color: #1e40af;">Workbench</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('my-transactions'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('my-transactions') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #dcfce7, #bbf7d0); border-color: #86efac;">
                            <i class="mdi mdi-receipt dash-shortcut-icon" style="color: #166534;"></i>
                            <h6 class="dash-shortcut-title" style="color: #166534;">Transactions</h6>
                            <small style="color: #166534;">My Transactions</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('transactions'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('transactions') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f3e8ff, #e9d5ff); border-color: #d8b4fe;">
                            <i class="mdi mdi-book-open-page-variant dash-shortcut-icon" style="color: #7e22ce;"></i>
                            <h6 class="dash-shortcut-title" style="color: #7e22ce;">All</h6>
                            <small style="color: #7e22ce;">Transactions</small>
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
            </div>

            <div class="row g-3 mt-1">
                @if(Route::has('hmo.workbench'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('hmo.workbench', ['queue_filter' => 'pending']) }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fff7ed, #ffedd5); border-color: #fed7aa;">
                            <i class="mdi mdi-shield-check dash-shortcut-icon" style="color: #c2410c;"></i>
                            <h6 class="dash-shortcut-title" style="color: #c2410c;">HMO</h6>
                            <small style="color: #c2410c;">Workbench</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('product-or-service-request.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('product-or-service-request.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #fef3c7, #fde68a); border-color: #fcd34d;">
                            <i class="mdi mdi-file-document dash-shortcut-icon" style="color: #b45309;"></i>
                            <h6 class="dash-shortcut-title" style="color: #b45309;">Payment</h6>
                            <small style="color: #b45309;">Requests</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('audit-logs.index'))
                <div class="col-6 col-md-3">
                    <a href="{{ route('audit-logs.index') }}" class="text-decoration-none">
                        <div class="dash-shortcut" style="background: linear-gradient(145deg, #f1f5f9, #e2e8f0); border-color: #cbd5e1;">
                            <i class="mdi mdi-history dash-shortcut-icon" style="color: #475569;"></i>
                            <h6 class="dash-shortcut-title" style="color: #475569;">Audit</h6>
                            <small style="color: #475569;">Logs</small>
                        </div>
                    </a>
                </div>
                @endif

                @if(Route::has('general-ledger.index'))
                <div class="col-6 col-md-3">
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
                    <h5 class="dash-section-title">Revenue Trend</h5>
                    <small class="text-muted">Monthly performance</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="acctRevenueChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="dash-chart-card">
            <div class="dash-chart-header">
                <div class="dash-section-icon bg-primary bg-opacity-10">
                    <i class="mdi mdi-chart-donut text-primary"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Payment Methods</h5>
                    <small class="text-muted">This month</small>
                </div>
            </div>
            <div class="dash-chart-body">
                <canvas id="acctPaymentMethodsChart"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Outstanding Aging & Department Revenue --}}
<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-danger bg-opacity-10">
                    <i class="mdi mdi-clock-alert text-danger"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Outstanding Aging</h5>
                    <small class="text-muted">Unpaid bills by age</small>
                </div>
            </div>
            <div id="acct-aging-bars">
                <div class="text-center py-3 text-muted">Loading...</div>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="dash-section-card">
            <div class="dash-section-header">
                <div class="dash-section-icon bg-info bg-opacity-10">
                    <i class="mdi mdi-chart-bar text-info"></i>
                </div>
                <div>
                    <h5 class="dash-section-title">Revenue by Department</h5>
                    <small class="text-muted">This month</small>
                </div>
            </div>
            <div id="acct-dept-bars">
                <div class="text-center py-3 text-muted">Loading...</div>
            </div>
        </div>
    </div>
</div>

{{-- Audit Log --}}
<div class="dash-section-card mb-4">
    <div class="dash-section-header">
        <div class="dash-section-icon bg-dark bg-opacity-10">
            <i class="mdi mdi-history text-dark"></i>
        </div>
        <div>
            <h5 class="dash-section-title">Audit Trail</h5>
            <small class="text-muted">Recent system activity</small>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 dash-mini-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Event</th>
                    <th>Module</th>
                </tr>
            </thead>
            <tbody id="acct-audit-body">
                <tr><td colspan="4" class="text-center text-muted py-3">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>
