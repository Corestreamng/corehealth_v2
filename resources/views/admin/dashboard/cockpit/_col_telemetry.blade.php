{{-- ==========================================================================
     Column 2: Clinical Telemetry & Telemetry Analytics (44% Desktop Width)
     High-Density 2x3 KPI Matrix, Dual Micro Sparklines & Insights Strip
     ========================================================================== --}}
<div class="dash-column dash-col-telemetry">

    {{-- 1. High-Density 2x3 KPI Matrix --}}
    <div class="dash-kpi-matrix">

        {{-- Tile 1: Today's Revenue --}}
        <a href="{{ route('billing.workbench') }}" class="dash-kpi-tile kpi-tile-revenue text-decoration-none" title="Billing: Open Workbench">
            <div class="dash-kpi-head">
                <span class="dash-kpi-label">Today's Revenue</span>
                <i class="mdi mdi-cash-multiple dash-kpi-icon"></i>
            </div>
            <div class="dash-kpi-val" id="kpi-today-revenue">₦0</div>
            <div class="dash-kpi-sub text-success">
                <i class="mdi mdi-check-circle-outline"></i> Settled Collections
            </div>
        </a>

        {{-- Tile 2: Bed Occupancy --}}
        <a href="{{ route('beds.index') }}" class="dash-kpi-tile kpi-tile-occupancy text-decoration-none" title="Wards: View Bed Occupancy">
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

        {{-- Tile 3: Vitals Recorded --}}
        <a href="{{ route('nursing-workbench.index', ['queue_filter' => 'vitals']) }}" class="dash-kpi-tile kpi-tile-vitals text-decoration-none" title="Nursing: Vitals Queue">
            <div class="dash-kpi-head">
                <span class="dash-kpi-label">Vitals Checked</span>
                <i class="mdi mdi-heart-pulse dash-kpi-icon"></i>
            </div>
            <div class="dash-kpi-val" id="kpi-vitals-today">0</div>
            <div class="dash-kpi-sub text-info">
                <i class="mdi mdi-calendar-check"></i> Recorded Today
            </div>
        </a>

        {{-- Tile 4: Pharmacy Dispensed --}}
        <a href="{{ route('pharmacy.workbench') }}" class="dash-kpi-tile kpi-tile-pharmacy text-decoration-none" title="Pharmacy: Dispensary Workbench">
            <div class="dash-kpi-head">
                <span class="dash-kpi-label">Dispensed Today</span>
                <i class="mdi mdi-pill dash-kpi-icon"></i>
            </div>
            <div class="dash-kpi-val" id="kpi-dispensed-today">0</div>
            <div class="dash-kpi-sub text-purple">
                <i class="mdi mdi-check-all"></i> Fulfilled Prescriptions
            </div>
        </a>

        {{-- Tile 5: Lab Completed --}}
        <a href="{{ route('lab.workbench') }}" class="dash-kpi-tile kpi-tile-lab text-decoration-none" title="Laboratory: Diagnostic Workbench">
            <div class="dash-kpi-head">
                <span class="dash-kpi-label">Lab Tests Done</span>
                <i class="mdi mdi-flask-outline dash-kpi-icon"></i>
            </div>
            <div class="dash-kpi-val" id="kpi-lab-completed">0</div>
            <div class="dash-kpi-sub text-teal">
                <i class="mdi mdi-check-decagram"></i> Completed Today
            </div>
        </a>

        {{-- Tile 6: Stock Alerts --}}
        <a href="{{ route('inventory.store-workbench.index') }}" class="dash-kpi-tile kpi-tile-stock text-decoration-none" title="Store: Inventory Workbench">
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

    {{-- 2. Dual Telemetry Micro Sparklines (Height ~150px) --}}
    <div class="dash-charts-row">
        {{-- Chart A: Hourly Patient Flow --}}
        <div class="dash-chart-card">
            <div class="dash-chart-head">
                <div class="dash-chart-title">
                    <i class="mdi mdi-chart-bar text-success me-1"></i> Hourly Inflow
                </div>
                <span class="dash-chart-badge">7AM - 8PM</span>
            </div>
            <div class="dash-chart-canvas-wrap">
                <canvas id="dash-chart-hourly"></canvas>
            </div>
        </div>

        {{-- Chart B: 7-Day Revenue Velocity --}}
        <div class="dash-chart-card">
            <div class="dash-chart-head">
                <div class="dash-chart-title">
                    <i class="mdi mdi-chart-timeline-variant text-primary me-1"></i> Revenue Velocity
                </div>
                <span class="dash-chart-badge">Last 7 Days</span>
            </div>
            <div class="dash-chart-canvas-wrap">
                <canvas id="dash-chart-revenue"></canvas>
            </div>
        </div>
    </div>

    {{-- 3. Live Operational Insights Strip Container --}}
    <div class="dash-card dash-insights-box">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <i class="mdi mdi-lightbulb-on-outline text-warning"></i>
                <span>Operational Insights & Telemetry Alerts</span>
            </div>
            <span class="dash-card-badge" id="dash-insights-count">Live</span>
        </div>
        <div class="dash-card-body dash-scrollable">
            <div class="dash-insights-list" id="dash-insights-strip">
                <div class="dash-insight-pill insight-ok">
                    <i class="mdi mdi-check-circle"></i>
                    <span>All operational queues running within safety thresholds</span>
                </div>
            </div>
        </div>
    </div>

</div>
