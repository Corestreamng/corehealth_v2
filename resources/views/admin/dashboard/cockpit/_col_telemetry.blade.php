{{-- ==========================================================================
     Column 2: Clinical Telemetry & Telemetry Analytics (44% Desktop Width)
     Driven by Active Operational Pipeline Stage (Contextual 2x3 KPIs & Charts)
     ========================================================================== --}}
@php
    $u = Auth::user();
    $isAdm = $u && $u->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']);
    $revLink = ($isAdm || ($u && $u->hasAnyRole(['BILLER', 'CASHIER', 'ACCOUNTS']))) ? route('billing.workbench') : 'javascript:void(0);';
    $bedLink = ($isAdm || ($u && $u->hasRole('NURSE'))) ? url('nursing-workbench?filter=admitted') : 'javascript:void(0);';
    $vitalsLink = ($isAdm || ($u && $u->hasRole('NURSE'))) ? url('nursing-workbench?filter=vitals') : 'javascript:void(0);';
    $pharmLink = ($isAdm || ($u && $u->hasRole('PHARMACIST'))) ? route('pharmacy.workbench') : 'javascript:void(0);';
    $labLink = ($isAdm || ($u && $u->hasRole('LAB SCIENTIST'))) ? url('lab-workbench?tab=completed') : 'javascript:void(0);';
    $docLink = ($isAdm || ($u && $u->hasRole('DOCTOR'))) ? route('encounters.index') : 'javascript:void(0);';
    $recepLink = ($isAdm || ($u && $u->hasAnyRole(['RECEPTIONIST', 'Receptionist']))) ? route('patient.index') : 'javascript:void(0);';
    $storeLink = ($isAdm || ($u && $u->hasRole('STORE'))) ? route('inventory.store-workbench.index') : 'javascript:void(0);';
@endphp
<div class="dash-column dash-col-telemetry">

    {{-- Domain Telemetry Header Card --}}
    <div class="dash-card h-100 d-flex flex-column">

        <div class="dash-card-header flex-shrink-0">
            <div class="dash-card-title">
                <i class="mdi mdi-chart-areaspline text-primary" id="col2-domain-icon"></i>
                <span id="col2-domain-title">Clinical Telemetry & Analytics</span>
            </div>
            <span class="dash-card-badge" id="col2-domain-badge">Active Domain</span>
        </div>

        <div class="dash-card-body dash-scrollable flex-grow-1">

            {{-- 1. High-Density 2x4 KPI Matrix (Container dynamically populated by active domain) --}}
            <div class="dash-kpi-matrix" id="dash-kpi-matrix">

                {{-- Default Server-Side Pre-rendered KPI Tiles (Replaced instantly on client domain select) --}}
                <a href="{{ $revLink }}" class="dash-kpi-tile kpi-tile-revenue">
                    <div class="dash-kpi-head">
                        <span class="dash-kpi-label">Today's Revenue</span>
                        <i class="mdi mdi-cash-multiple dash-kpi-icon"></i>
                    </div>
                    <div class="dash-kpi-val" id="kpi-today-revenue">₦0</div>
                    <div class="dash-kpi-sub text-success">
                        <i class="mdi mdi-check-circle-outline"></i> Settled Collections
                    </div>
                </a>

                <a href="{{ $bedLink }}" class="dash-kpi-tile kpi-tile-occupancy">
                    <div class="dash-kpi-head">
                        <span class="dash-kpi-label">Bed Occupancy</span>
                        <i class="mdi mdi-hotel dash-kpi-icon"></i>
                    </div>
                    <div class="dash-kpi-val">
                        <span id="kpi-admitted-patients">0</span>
                        <span class="dash-kpi-unit" id="kpi-occupancy-rate">0%</span>
                    </div>
                    <div class="dash-kpi-sub text-muted">
                        <i class="mdi mdi-bed"></i> Inpatient Census
                    </div>
                </a>

                <a href="{{ $vitalsLink }}" class="dash-kpi-tile kpi-tile-vitals">
                    <div class="dash-kpi-head">
                        <span class="dash-kpi-label">Vitals Checked</span>
                        <i class="mdi mdi-heart-pulse dash-kpi-icon"></i>
                    </div>
                    <div class="dash-kpi-val" id="kpi-vitals-today">0</div>
                    <div class="dash-kpi-sub text-info">
                        <i class="mdi mdi-calendar-check"></i> Recorded Today
                    </div>
                </a>

                <a href="{{ $pharmLink }}" class="dash-kpi-tile kpi-tile-pharmacy">
                    <div class="dash-kpi-head">
                        <span class="dash-kpi-label">Dispensed Today</span>
                        <i class="mdi mdi-pill dash-kpi-icon"></i>
                    </div>
                    <div class="dash-kpi-val" id="kpi-dispensed-today">0</div>
                    <div class="dash-kpi-sub text-purple">
                        <i class="mdi mdi-check-all"></i> Fulfilled Prescriptions
                    </div>
                </a>

                <a href="{{ $labLink }}" class="dash-kpi-tile kpi-tile-lab">
                    <div class="dash-kpi-head">
                        <span class="dash-kpi-label">Lab Tests Done</span>
                        <i class="mdi mdi-flask-outline dash-kpi-icon"></i>
                    </div>
                    <div class="dash-kpi-val" id="kpi-lab-completed">0</div>
                    <div class="dash-kpi-sub text-teal">
                        <i class="mdi mdi-check-decagram"></i> Completed Today
                    </div>
                </a>

                <a href="{{ $docLink }}" class="dash-kpi-tile kpi-tile-primary">
                    <div class="dash-kpi-head">
                        <span class="dash-kpi-label">Consultations</span>
                        <i class="mdi mdi-stethoscope dash-kpi-icon"></i>
                    </div>
                    <div class="dash-kpi-val" id="kpi-consultations-active">0</div>
                    <div class="dash-kpi-sub text-primary">
                        <i class="mdi mdi-account-clock"></i> Active Intake
                    </div>
                </a>

                <a href="{{ $recepLink }}" class="dash-kpi-tile kpi-tile-success">
                    <div class="dash-kpi-head">
                        <span class="dash-kpi-label">Registrations</span>
                        <i class="mdi mdi-account-plus dash-kpi-icon"></i>
                    </div>
                    <div class="dash-kpi-val" id="kpi-registered-today">0</div>
                    <div class="dash-kpi-sub text-success">
                        <i class="mdi mdi-folder-plus"></i> New Folders
                    </div>
                </a>

                <a href="{{ $storeLink }}" class="dash-kpi-tile kpi-tile-stock">
                    <div class="dash-kpi-head">
                        <span class="dash-kpi-label">Stock Alerts</span>
                        <i class="mdi mdi-alert-circle-outline dash-kpi-icon text-danger"></i>
                    </div>
                    <div class="dash-kpi-val">
                        <span class="text-danger" id="kpi-out-of-stock">0</span>
                        <span class="dash-kpi-unit text-warning">/ <span id="kpi-low-stock">0</span> Low</span>
                    </div>
                    <div class="dash-kpi-sub text-danger">
                        <i class="mdi mdi-package-variant-remove"></i> Critical Thresholds
                    </div>
                </a>

            </div>

            {{-- 2. Telemetry Dual Micro Sparklines --}}
            <div class="dash-charts-row mt-2">
                <div class="dash-chart-card">
                    <div class="dash-chart-head">
                        <div class="dash-chart-title">
                            <i class="mdi mdi-chart-areaspline text-primary me-1" id="col2-chart-icon"></i>
                            <span id="col2-chart-title">Hourly Activity Inflow</span>
                        </div>
                        <span class="dash-chart-badge" id="col2-chart-badge">Today (7AM - 8PM)</span>
                    </div>
                    <div class="dash-chart-canvas-wrap">
                        <canvas id="dash-chart-canvas" height="110"></canvas>
                    </div>
                </div>
                <div class="dash-chart-card">
                    <div class="dash-chart-head">
                        <div class="dash-chart-title">
                            <i class="mdi mdi-chart-timeline-variant text-success me-1" id="col2-chart2-icon"></i>
                            <span id="col2-chart2-title">7-Day Trend</span>
                        </div>
                        <span class="dash-chart-badge" id="col2-chart2-badge">Past Week</span>
                    </div>
                    <div class="dash-chart-canvas-wrap">
                        <canvas id="dash-chart-canvas-2" height="110"></canvas>
                    </div>
                </div>
            </div>

            {{-- 3. Departmental Operational Insights Strip (Compact Micro-Grid) --}}
            <div class="dash-insights-strip mt-2" id="dash-insights-strip">
                <div class="dash-insights-head">
                    <span class="dash-insights-title">
                        <i class="mdi mdi-lightbulb-on-outline text-warning me-1"></i>
                        <span>Departmental Operational Insights</span>
                    </span>
                    <span class="badge bg-primary text-white" id="dash-insights-count">Live</span>
                </div>
                <div class="dash-insights-list" id="dash-insights-container">
                    <div class="dash-insight-pill insight-info">
                        <i class="mdi mdi-information-outline"></i>
                        <span><strong>Telemetry:</strong> Live parameters active</span>
                    </div>
                    <div class="dash-insight-pill insight-ok">
                        <i class="mdi mdi-check-circle-outline"></i>
                        <span><strong>Status:</strong> Operations within nominal benchmarks</span>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>
