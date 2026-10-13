{{-- ==========================================================================
     Column 2: Clinical Telemetry & Telemetry Analytics (44% Desktop Width)
     Driven by Active Operational Pipeline Stage (Contextual 2x3 KPIs & Charts)
     ========================================================================== --}}
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

            {{-- 1. High-Density 2x3 KPI Matrix (Container dynamically populated by active domain) --}}
            <div class="dash-kpi-matrix" id="dash-kpi-matrix">

                {{-- Default Server-Side Pre-rendered KPI Tiles (Replaced instantly on client domain select) --}}
                <div class="dash-kpi-tile kpi-tile-revenue">
                    <div class="dash-kpi-head">
                        <span class="dash-kpi-label">Today's Revenue</span>
                        <i class="mdi mdi-cash-multiple dash-kpi-icon"></i>
                    </div>
                    <div class="dash-kpi-val" id="kpi-today-revenue">₦0</div>
                    <div class="dash-kpi-sub text-success">
                        <i class="mdi mdi-check-circle-outline"></i> Settled Collections
                    </div>
                </div>

                <div class="dash-kpi-tile kpi-tile-occupancy">
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
                </div>

                <div class="dash-kpi-tile kpi-tile-vitals">
                    <div class="dash-kpi-head">
                        <span class="dash-kpi-label">Vitals Checked</span>
                        <i class="mdi mdi-heart-pulse dash-kpi-icon"></i>
                    </div>
                    <div class="dash-kpi-val" id="kpi-vitals-today">0</div>
                    <div class="dash-kpi-sub text-info">
                        <i class="mdi mdi-calendar-check"></i> Recorded Today
                    </div>
                </div>

                <div class="dash-kpi-tile kpi-tile-pharmacy">
                    <div class="dash-kpi-head">
                        <span class="dash-kpi-label">Dispensed Today</span>
                        <i class="mdi mdi-pill dash-kpi-icon"></i>
                    </div>
                    <div class="dash-kpi-val" id="kpi-dispensed-today">0</div>
                    <div class="dash-kpi-sub text-purple">
                        <i class="mdi mdi-check-all"></i> Fulfilled Prescriptions
                    </div>
                </div>

                <div class="dash-kpi-tile kpi-tile-lab">
                    <div class="dash-kpi-head">
                        <span class="dash-kpi-label">Lab Tests Done</span>
                        <i class="mdi mdi-flask-outline dash-kpi-icon"></i>
                    </div>
                    <div class="dash-kpi-val" id="kpi-lab-completed">0</div>
                    <div class="dash-kpi-sub text-teal">
                        <i class="mdi mdi-check-decagram"></i> Completed Today
                    </div>
                </div>

                <div class="dash-kpi-tile kpi-tile-stock">
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
                </div>

            </div>

            {{-- 2. Telemetry Micro Sparkline Chart --}}
            <div class="dash-charts-row mt-3">
                <div class="dash-chart-card w-100">
                    <div class="dash-chart-head">
                        <div class="dash-chart-title">
                            <i class="mdi mdi-chart-areaspline text-primary me-1" id="col2-chart-icon"></i>
                            <span id="col2-chart-title">Hourly Operational Inflow</span>
                        </div>
                        <span class="dash-chart-badge" id="col2-chart-badge">Today (7AM - 8PM)</span>
                    </div>
                    <div class="dash-chart-canvas-wrap">
                        <canvas id="dash-chart-canvas" height="135"></canvas>
                    </div>
                </div>
            </div>

            {{-- 3. Operational Insights Strip --}}
            <div class="dash-insights-strip mt-3" id="dash-insights-strip">
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
                        <span>Telemetry active. Selecting a pipeline stage updates this console contextually.</span>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>
