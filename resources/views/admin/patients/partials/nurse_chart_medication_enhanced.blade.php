<style>
    /* Custom styles for date range filter */
    .date-range-group {
        display: flex;
        align-items: center;
    }

    .date-range-group .form-control {
        width: auto;
        min-width: 120px;
    }

    .date-range-group .input-group-text {
        padding: 0.25rem 0.5rem;
        background-color: #f8f9fa;
        border: 1px solid #ced4da;
    }

    /* Custom tooltip styles */
    .tooltip {
        opacity: 0.95 !important;
    }

    .tooltip-inner {
        max-width: 300px;
        padding: 10px;
        text-align: left;
        font-size: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        border-radius: 4px;
    }

    /* Improve schedule slots display */
    .schedule-slot {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .schedule-slot:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        z-index: 10;
    }

    /* Calendar Grid Layout */
    .calendar-weekday-header {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 1px;
        background-color: #dee2e6;
        border: 1px solid #dee2e6;
        border-bottom: none;
    }

    .weekday-name {
        background-color: #f8f9fa;
        padding: 10px 8px;
        text-align: center;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #495057;
    }

    .medication-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 1px;
        background-color: #dee2e6;
        border: 1px solid #dee2e6;
    }

    .calendar-day-cell {
        background-color: white;
        padding: 8px;
        min-height: 100px;
        display: flex;
        flex-direction: column;
    }

    .calendar-day-header {
        font-weight: 600;
        font-size: 0.7rem;
        color: #888;
        text-transform: uppercase;
        margin-bottom: 4px;
        padding-bottom: 4px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .calendar-day-date {
        font-size: 1rem;
        font-weight: 700;
        color: #333;
    }

    .calendar-schedules {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-top: 4px;
        overflow-y: auto;
        max-height: 120px;
    }

    .calendar-schedule-item {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .calendar-schedule-item .schedule-slot {
        font-size: 0.7rem;
        padding: 3px 6px;
        flex: 1;
    }

    .calendar-schedule-item .remove-schedule-btn {
        padding: 2px 4px;
        font-size: 0.6rem;
        line-height: 1;
        flex-shrink: 0;
    }

    .calendar-day-cell.today {
        background-color: #e3f2fd;
        border: 2px solid #2196F3;
    }

    .calendar-day-cell.today .calendar-day-date {
        color: #1976D2;
    }

    .calendar-day-cell.weekend {
        background-color: #fff9f0;
    }

    .calendar-day-cell.past-date {
        opacity: 0.7;
        background-color: #fafafa;
    }

    .calendar-day-cell.empty-day {
        background-color: #f5f5f5;
        opacity: 0.5;
        min-height: 60px;
    }

    /* Legend styling */
    #calendar-legend .badge {
        font-size: 0.8rem;
        padding: 0.4rem 0.7rem;
        font-weight: 500;
    }

    /* Card modern enhancements */
    .card-modern {
        border: none;
        border-radius: 8px;
        overflow: hidden;
    }

    .card-modern .card-header {
        border-bottom: 1px solid #e0e0e0;
    }

    /* Responsive adjustments */
    @media (max-width: 992px) {
        .date-range-group {
            margin-top: 0.5rem;
            width: 100%;
        }

        .date-range-group .form-control {
            flex: 1;
            min-width: 0;
        }

        .medication-calendar-grid {
            grid-template-columns: repeat(7, minmax(60px, 1fr));
            font-size: 0.75rem;
        }

        .calendar-day-cell {
            min-height: 80px;
            padding: 4px;
        }

        .calendar-day-header {
            font-size: 0.6rem;
        }

        .calendar-day-date {
            font-size: 0.85rem;
        }

        .calendar-schedule-item .schedule-slot {
            font-size: 0.6rem;
            padding: 2px 4px;
        }
    }

    @media (max-width: 576px) {
        .medication-calendar-grid {
            grid-template-columns: repeat(7, minmax(40px, 1fr));
        }

        .calendar-day-cell {
            min-height: 60px;
            padding: 2px;
        }

        .weekday-name {
            font-size: 0.65rem;
            padding: 6px 2px;
        }

        .calendar-schedule-item .schedule-slot i {
            display: none;
        }
    }

    /* Sub-tab styling */
    .med-sub-tabs {
        border-bottom: 2px solid #e9ecef;
        margin-bottom: 0;
    }
    .med-sub-tabs .nav-link {
        border: none;
        border-bottom: 3px solid transparent;
        color: #6c757d;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
        transition: all 0.2s ease;
        background: transparent;
        margin-bottom: -2px;
    }
    .med-sub-tabs .nav-link:hover {
        color: #495057;
        border-bottom-color: #dee2e6;
        background: transparent;
    }
    .med-sub-tabs .nav-link.active {
        color: #0d6efd;
        border-bottom-color: #0d6efd;
        background: transparent;
    }
    .med-sub-tabs .nav-link i {
        margin-right: 6px;
    }

    /* Unified Overview Calendar Styles */
    .unified-overview-calendar .calendar-weekday-header {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 1px;
        background-color: #4a5568;
    }
    .unified-overview-calendar .weekday-name {
        padding: 10px;
        text-align: center;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        color: white;
        background-color: #4a5568;
    }
    .unified-overview-calendar .medication-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 1px;
        background-color: #dee2e6;
        border: 1px solid #dee2e6;
    }
    .unified-overview-calendar .calendar-day-cell {
        background-color: white;
        min-height: 100px;
        padding: 6px;
        display: flex;
        flex-direction: column;
    }
    .unified-overview-calendar .calendar-day-cell.empty-day {
        background-color: #f8f9fa;
        min-height: 50px;
    }
    .unified-overview-calendar .calendar-day-cell.today {
        background-color: #e3f2fd;
        border: 2px solid #2196F3;
    }
    .unified-overview-calendar .calendar-day-cell.weekend {
        background-color: #fffde7;
    }
    .unified-overview-calendar .calendar-day-cell.past-date {
        opacity: 0.8;
        background-color: #fafafa;
    }
    .unified-overview-calendar .day-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 4px;
        padding-bottom: 4px;
        border-bottom: 1px solid #eee;
    }
    .unified-overview-calendar .day-name {
        font-size: 10px;
        color: #666;
        text-transform: uppercase;
    }
    .unified-overview-calendar .day-number {
        font-weight: bold;
        font-size: 14px;
        color: #333;
    }
    .unified-overview-calendar .schedule-items {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 4px;
        overflow-y: auto;
        max-height: 180px;
        padding: 2px;
    }
    .unified-overview-calendar .med-item {
        font-size: 11px;
        padding: 6px 8px;
        border-radius: 5px;
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        border-left: 3px solid;
        transition: all 0.15s ease;
    }
    .unified-overview-calendar .med-item:hover {
        transform: translateX(2px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .unified-overview-calendar .med-item .med-time {
        font-weight: 600;
        white-space: nowrap;
    }
    .unified-overview-calendar .med-item .med-name {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 500;
    }
    .unified-overview-calendar .med-item .med-status {
        font-size: 14px;
    }
    .unified-overview-calendar .med-item.status-given {
        opacity: 0.9;
    }
    .unified-overview-calendar .med-item.status-given .med-name {
        text-decoration: line-through;
        opacity: 0.8;
    }
    .unified-overview-calendar .med-item.status-missed {
        background-color: #ffebee !important;
        border-left-color: #f44336 !important;
    }
    .unified-overview-calendar .med-item.status-discontinued {
        opacity: 0.5;
        text-decoration: line-through;
    }

    /* Stats cards in overview */
    .overview-stat-card {
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        transition: transform 0.2s;
        border: 1px solid rgba(0,0,0,0.08);
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    }
    .overview-stat-card.stat-primary {
        background: linear-gradient(135deg, #e3f0ff 0%, #cfe2ff 100%) !important;
        border-left: 4px solid #0d6efd;
    }
    .overview-stat-card.stat-success {
        background: linear-gradient(135deg, #d1f5e0 0%, #b7ebc9 100%) !important;
        border-left: 4px solid #198754;
    }
    .overview-stat-card.stat-warning {
        background: linear-gradient(135deg, #fff6d5 0%, #ffecb5 100%) !important;
        border-left: 4px solid #ffc107;
    }
    .overview-stat-card.stat-danger {
        background: linear-gradient(135deg, #fde0e0 0%, #f5c6cb 100%) !important;
        border-left: 4px solid #dc3545;
    }
    .overview-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    }
    .overview-stat-card .stat-number {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .overview-stat-card .stat-label {
        font-size: 0.85rem;
        font-weight: 600;
    }
    /* Overview legend */
    .overview-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding: 8px 12px;
        background: #f8f9fa;
        border-radius: 6px;
        border: 1px solid #e9ecef;
    }
    .overview-legend .legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.8rem;
        color: #495057;
    }
</style>

<div class="medication-chart-section">
    <h5 class="mb-3">Medication Chart / Treatment Sheet</h5>

    <!-- Shared Date Filter - accessible by both tabs -->
    <div class="card-modern shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col-auto">
                    <span class="fw-bold text-muted small">
                        <i class="mdi mdi-calendar-range me-1"></i>Date Range:
                    </span>
                </div>
                <div class="col-auto">
                    <div class="d-flex align-items-center gap-2">
                        <input type="date" class="form-control form-control-sm" id="med-start-date" style="width: 140px;">
                        <span class="text-muted">to</span>
                        <input type="date" class="form-control form-control-sm" id="med-end-date" style="width: 140px;">
                        <button type="button" class="btn btn-primary btn-sm" id="apply-date-range-btn">
                            <i class="mdi mdi-filter"></i> Apply
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="reset-date-range-btn">
                            <i class="mdi mdi-refresh"></i> Reset
                        </button>
                    </div>
                </div>
                <div class="col-auto ms-auto">
                    <span id="date-range-summary" class="badge bg-info"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Sub-tabs: Entry and Overview -->
    <ul class="nav med-sub-tabs" id="medSubTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="med-entry-tab" data-bs-toggle="tab" data-bs-target="#med-entry-content"
                    type="button" role="tab" aria-controls="med-entry-content" aria-selected="true">
                <i class="mdi mdi-pencil-plus"></i>Entry
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="med-overview-tab" data-bs-toggle="tab" data-bs-target="#med-overview-content"
                    type="button" role="tab" aria-controls="med-overview-content" aria-selected="false">
                <i class="mdi mdi-view-grid"></i>Overview
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="med-rx-tab" data-bs-toggle="tab" data-bs-target="#med-rx-content"
                    type="button" role="tab" aria-controls="med-rx-content" aria-selected="false">
                <i class="mdi mdi-clipboard-list"></i>Prescriptions
                <span class="badge bg-primary ms-1" id="rx-tab-badge" style="display:none;">0</span>
            </button>
        </li>
    </ul>

    <div class="tab-content pt-3" id="medSubTabsContent">
        <!-- ENTRY TAB -->
        <div class="tab-pane fade show active" id="med-entry-content" role="tabpanel" aria-labelledby="med-entry-tab">

    <!-- Drug Selection and Controls -->
    <div class="mb-4">
        <div class="card-modern shadow-sm">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12">
                        <label for="drug-select" class="form-label fw-bold">
                            <i class="mdi mdi-pill text-primary me-1"></i> Select Drug/Medication
                        </label>
                        <select class="form-select" id="drug-select" style="width: 100%;">
                            <option value="">-- Select a medication --</option>
                            <!-- Will be populated via AJAX -->
                        </select>
                        {{-- §6.2: Drug source at medication entry level --}}
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-ward-stock">
                                <i class="mdi mdi-hospital-building me-1"></i> Administer from Ward Stock
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-add-patient-own">
                                <i class="mdi mdi-account-heart me-1"></i> Administer Patient's Own Drug
                            </button>
                        </div>
                    </div>
                </div>
                <hr class="my-3">
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2 medication-controls">
                            <button type="button" class="btn btn-primary btn-sm" id="set-schedule-btn" disabled>
                                <i class="mdi mdi-calendar-plus"></i>
                                <span class="d-none d-sm-inline">Set Schedule</span>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" id="discontinue-btn" disabled>
                                <i class="mdi mdi-calendar-remove"></i>
                                <span class="d-none d-sm-inline">Discontinue</span>
                            </button>
                            <button type="button" class="btn btn-success btn-sm" id="resume-btn" disabled>
                                <i class="mdi mdi-calendar-check"></i>
                                <span class="d-none d-sm-inline">Resume</span>
                            </button>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-secondary btn-sm" id="prev-month-btn">
                                    <i class="mdi mdi-chevron-left"></i>
                                    <span class="d-none d-sm-inline">Previous</span>
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" id="today-btn">
                                    <i class="mdi mdi-calendar-today d-inline d-sm-none"></i>
                                    <span class="d-none d-sm-inline">Today</span>
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" id="next-month-btn">
                                    <span class="d-none d-sm-inline">Next</span>
                                    <i class="mdi mdi-chevron-right"></i>
                                </button>
                            </div>
                            <button type="button" class="btn btn-info btn-sm" id="view-logs-btn">
                                <i class="mdi mdi-history"></i>
                                <span class="d-none d-sm-inline">View Logs</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div id="medication-status" class="mt-3 border-top pt-2"></div>
            </div>
        </div>
    </div>

    <!-- Loading indicator -->
    <div class="text-center my-4" id="medication-loading" style="display:none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-2 text-primary">Loading medication chart...</div>
    </div>

    <!-- Legend -->
    <div id="calendar-legend" class="mb-3" style="display:none;">
        <!-- Legend will be populated via JavaScript -->
    </div>

    <!-- Calendar View -->
    <div id="medication-calendar" class="mb-4" style="display:none;">
        <div class="card-modern shadow-sm">
            <div class="card-header bg-light py-2">
                <h6 id="calendar-title" class="card-title mb-0 text-center fw-bold"></h6>
            </div>
            <div class="card-body p-2">
                <!-- Calendar Container - weekday header + grid will be rendered here -->
                <div id="calendar-container">
                    <!-- Will be populated via JavaScript -->
                </div>
            </div>
        </div>
    </div>

        </div>
        <!-- END ENTRY TAB -->

        <!-- OVERVIEW TAB -->
        <div class="tab-pane fade" id="med-overview-content" role="tabpanel" aria-labelledby="med-overview-tab">

            <!-- Summary Stats -->
            <div class="row mb-4" id="overview-stats-row">
                <div class="col-md-3 col-6 mb-3">
                    <div class="overview-stat-card stat-primary">
                        <div class="stat-number text-primary" id="stat-total-meds">0</div>
                        <div class="stat-label text-primary">Active Medications</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="overview-stat-card stat-success">
                        <div class="stat-number text-success" id="stat-given">0</div>
                        <div class="stat-label text-success">Doses Given</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="overview-stat-card stat-warning">
                        <div class="stat-number text-warning" id="stat-scheduled">0</div>
                        <div class="stat-label text-warning">Scheduled</div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="overview-stat-card stat-danger">
                        <div class="stat-number text-danger" id="stat-missed">0</div>
                        <div class="stat-label text-danger">Missed</div>
                    </div>
                </div>
            </div>

            <!-- Loading indicator for overview -->
            <div class="text-center my-4" id="overview-loading" style="display:none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="mt-2 text-primary">Loading overview...</div>
            </div>

            <!-- Unified Calendar Overview -->
            <div class="card-modern shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                    <h6 class="mb-0 fw-bold">
                        <i class="mdi mdi-calendar-month me-2"></i>Medication Overview Calendar
                    </h6>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="overview-prev-btn">
                            <i class="mdi mdi-chevron-left"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="overview-today-btn">Today</button>
                        <button type="button" class="btn btn-outline-secondary" id="overview-next-btn">
                            <i class="mdi mdi-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div id="unified-overview-container" class="unified-overview-calendar">
                        <!-- Calendar will be rendered here via JavaScript -->
                    </div>
                </div>
                <div class="card-footer bg-white py-2">
                    <div class="overview-legend">
                        <div class="legend-item"><span>✅</span> Given</div>
                        <div class="legend-item"><span>❌</span> Missed</div>
                        <div class="legend-item"><span>🕐</span> Pending</div>
                        <div class="legend-item"><span>💊</span> Pharmacy</div>
                        <div class="legend-item"><span>🏥</span> Ward Stock</div>
                        <div class="legend-item"><span>👤</span> Patient's Own</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END OVERVIEW TAB -->

        <!-- PRESCRIPTIONS TAB -->
        <div class="tab-pane fade" id="med-rx-content" role="tabpanel" aria-labelledby="med-rx-tab">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-bold"><i class="mdi mdi-clipboard-list text-primary me-1"></i> Prescription Status Dashboard</h6>
                <button type="button" class="btn btn-outline-primary btn-sm" id="rx-refresh-btn">
                    <i class="mdi mdi-refresh"></i> Refresh
                </button>
            </div>

            <!-- Status summary cards -->
            <div class="row mb-3" id="rx-status-summary">
                <div class="col-md-3 col-6 mb-2">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-2">
                            <div class="fs-4 fw-bold text-success" id="rx-count-dispensed">0</div>
                            <small class="text-muted">Dispensed</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-2">
                            <div class="fs-4 fw-bold text-info" id="rx-count-billed">0</div>
                            <small class="text-muted">Billed / Awaiting Pharmacy</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-2">
                            <div class="fs-4 fw-bold text-warning" id="rx-count-requested">0</div>
                            <small class="text-muted">Requested / Awaiting Billing</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center py-2">
                            <div class="fs-4 fw-bold text-secondary" id="rx-count-total">0</div>
                            <small class="text-muted">Total Active</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter buttons -->
            <div class="btn-group mb-3" role="group" id="rx-filter-group">
                <button type="button" class="btn btn-outline-secondary btn-sm active" data-rx-filter="all">All</button>
                <button type="button" class="btn btn-outline-success btn-sm" data-rx-filter="3">Dispensed</button>
                <button type="button" class="btn btn-outline-info btn-sm" data-rx-filter="2">Billed</button>
                <button type="button" class="btn btn-outline-warning btn-sm" data-rx-filter="1">Requested</button>
            </div>

            <!-- Loading -->
            <div class="text-center my-4" id="rx-loading" style="display:none;">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                <span class="ms-2 text-muted">Loading prescriptions...</span>
            </div>

            <!-- Empty state -->
            <div class="text-center my-4 text-muted" id="rx-empty" style="display:none;">
                <i class="mdi mdi-clipboard-text-off" style="font-size:2rem;"></i>
                <p class="mt-2">No active prescriptions found for this patient.</p>
            </div>

            <!-- Prescriptions table -->
            <div class="table-responsive" id="rx-table-wrap" style="display:none;">
                <table class="table table-sm table-hover align-middle" id="rx-dashboard-table">
                    <thead class="table-light">
                        <tr>
                            <th>Drug</th>
                            <th>Dose</th>
                            <th>Prescribed By</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Administered</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="rx-dashboard-body">
                        <!-- Populated via JS -->
                    </tbody>
                </table>
            </div>
        </div>
        <!-- END PRESCRIPTIONS TAB -->
    </div>

    <!-- Set Schedule Modal -->
    <div class="modal fade" id="setScheduleModal" tabindex="-1" aria-labelledby="setScheduleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="setScheduleForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="setScheduleModalLabel">Set Medication Schedule</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close">x</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="schedule_patient_id" name="patient_id"
                            value="{{ $patient->id ?? '' }}">
                        <input type="hidden" id="schedule_medication_id" name="product_or_service_request_id">
                        <input type="hidden" id="schedule_drug_source" name="drug_source" value="pharmacy_dispensed">
                        <input type="hidden" id="schedule_product_id" name="product_id" value="">
                        <input type="hidden" id="schedule_external_drug_name" name="external_drug_name" value="">

                        <div class="mb-3">
                            <label for="schedule_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="schedule_date" name="start_date"
                                required>
                        </div>

                        <div class="mb-3">
                            <label for="schedule_time" class="form-label">Time</label>
                            <input type="time" class="form-control" id="schedule_time" name="time" required>
                        </div>

                        <div class="mb-3">
                            <label for="schedule_dose" class="form-label">Dose</label>
                            <input type="text" class="form-control" id="schedule_dose" name="dose" required>
                        </div>

                        <div class="mb-3">
                            <label for="schedule_route" class="form-label">Route</label>
                            <select class="form-select" id="schedule_route" name="route" required>
                                <option value="Oral">Oral</option>
                                <option value="IV">IV</option>
                                <option value="IM">IM</option>
                                <option value="SC">SC</option>
                                <option value="Topical">Topical</option>
                                <option value="Rectal">Rectal</option>
                                <option value="Inhalation">Inhalation</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <div class="form-check m-3">
                                <input class="form-check-input" type="radio" name="repeat_type" id="repeat_daily"
                                    value="daily" checked>
                                <label class="form-check-label" for="repeat_daily">
                                    Repeat daily
                                </label>
                            </div>
                            <div class="form-check m-3">
                                <input class="form-check-input" type="radio" name="repeat_type"
                                    id="repeat_selected_days" value="selected">
                                <label class="form-check-label" for="repeat_selected_days">
                                    Repeat on selected days
                                </label>
                            </div>
                            <div id="days-selector" class="mt-2" style="display:none;">
                                <div class="btn-group" role="group">
                                    <input type="checkbox" class="btn-check" id="day-0" name="selected_days[]"
                                        value="0" autocomplete="off">
                                    <label class="btn btn-outline-primary btn-sm" for="day-0">Sun</label>

                                    <input type="checkbox" class="btn-check" id="day-1" name="selected_days[]"
                                        value="1" autocomplete="off">
                                    <label class="btn btn-outline-primary btn-sm" for="day-1">Mon</label>

                                    <input type="checkbox" class="btn-check" id="day-2" name="selected_days[]"
                                        value="2" autocomplete="off">
                                    <label class="btn btn-outline-primary btn-sm" for="day-2">Tue</label>

                                    <input type="checkbox" class="btn-check" id="day-3" name="selected_days[]"
                                        value="3" autocomplete="off">
                                    <label class="btn btn-outline-primary btn-sm" for="day-3">Wed</label>

                                    <input type="checkbox" class="btn-check" id="day-4" name="selected_days[]"
                                        value="4" autocomplete="off">
                                    <label class="btn btn-outline-primary btn-sm" for="day-4">Thu</label>

                                    <input type="checkbox" class="btn-check" id="day-5" name="selected_days[]"
                                        value="5" autocomplete="off">
                                    <label class="btn btn-outline-primary btn-sm" for="day-5">Fri</label>

                                    <input type="checkbox" class="btn-check" id="day-6" name="selected_days[]"
                                        value="6" autocomplete="off">
                                    <label class="btn btn-outline-primary btn-sm" for="day-6">Sat</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="schedule_duration" class="form-label">Duration (days)</label>
                            <input type="number" class="form-control" id="schedule_duration" name="duration_days"
                                min="1" value="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Schedule</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Administer Modal -->
    <div class="modal fade" id="administerModal" tabindex="-1" aria-labelledby="administerModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="administerForm">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="administerModalLabel"><i class="mdi mdi-needle"></i> Administer Medication</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="administer_schedule_id" name="schedule_id">
                        <input type="hidden" id="administer_product_id" name="product_id">
                        <input type="hidden" id="administer_product_request_id" name="product_request_id">
                        {{-- Drug source: pharmacy_dispensed | ward_stock | patient_own --}}
                        <input type="hidden" id="administer_drug_source" name="drug_source" value="pharmacy_dispensed">
                        {{-- For patient_own direct schedules --}}
                        <input type="hidden" id="administer_external_drug_name" name="external_drug_name">

                        <div class="mb-3">
                            <label id="administer-medication-info" class="form-label fw-bold text-primary"></label>
                            <div id="administer-counts-info" class="small text-muted"></div>
                        </div>

                        <div class="mb-3">
                            <label id="administer-scheduled-time" class="form-label text-muted"></label>
                        </div>

                        {{-- Source indicator badge — swapped dynamically by JS --}}
                        <div class="mb-3" id="administer-source-badge">
                            <span class="badge bg-success"><i class="mdi mdi-pill"></i> Pharmacy Dispensed</span>
                            <small class="text-muted ms-2">Source is determined by the selected medication</small>
                        </div>

                        {{-- Pharmacy dispensed: qty per administration (hidden for direct entries) --}}
                        <div id="administer-pharmacy-qty-section" class="">
                            <div class="mb-3">
                                <label for="administer_pharmacy_qty" class="form-label">Qty per Administration <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="administer_pharmacy_qty" name="pharmacy_qty" min="0.01" value="1">
                                <small class="text-muted">Units consumed from prescribed quantity per this administration</small>
                                <div id="administer-remaining-info" class="mt-1 small"></div>
                            </div>
                        </div>

                        {{-- Ward stock: store select + qty (hidden by default) --}}
                        <div id="administer-ward-stock-section" class="d-none">
                            <div class="mb-3">
                                <label for="administer_store_id" class="form-label">Dispensing Store <span class="text-danger">*</span></label>
                                <select class="form-select" id="administer_store_id" name="store_id">
                                    <option value="">-- Select Store --</option>
                                </select>
                                <div id="administer-stock-info" class="mt-1 d-none">
                                    <small>Available: <span id="administer-stock-qty" class="badge bg-secondary">—</span></small>
                                </div>
                                <div id="administer-stock-warning" class="text-danger small mt-1 d-none">
                                    <i class="mdi mdi-alert"></i> <span id="administer-stock-warning-text"></span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="administer_qty" class="form-label">Qty to Deduct <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="administer_qty" name="qty" min="1" value="1">
                                <small class="text-muted">Number of units to deduct from store stock</small>
                            </div>
                        </div>

                        {{-- Patient's own: qty field (hidden by default) --}}
                        <div id="administer-patient-own-section" class="d-none">
                            <div class="mb-3">
                                <label for="administer_external_qty" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="administer_external_qty" name="external_qty" min="0.01" value="1">
                                <small class="text-muted">Quantity administered from patient's own supply</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="administered_at" class="form-label">Time of Administration</label>
                            <input type="datetime-local" class="form-control" id="administered_at"
                                name="administered_at" required>
                        </div>

                        <div class="mb-3">
                            <label for="administered_dose" class="form-label">Dose</label>
                            <input type="text" class="form-control" id="administered_dose"
                                name="administered_dose" required>
                        </div>

                        <div class="mb-3">
                            <label for="administered_route" class="form-label">Route</label>
                            <select class="form-select" id="administered_route" name="route" required>
                                <option value="Oral">Oral</option>
                                <option value="IV">IV</option>
                                <option value="IM">IM</option>
                                <option value="SC">SC</option>
                                <option value="Topical">Topical</option>
                                <option value="Rectal">Rectal</option>
                                <option value="Inhalation">Inhalation</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="administered_note" class="form-label">Notes</label>
                            <textarea class="form-control" id="administered_note" name="note" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="administerSubmitBtn">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <i class="mdi mdi-check"></i> Confirm Administration
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Discontinue Modal -->
    <div class="modal fade" id="discontinueModal" tabindex="-1" aria-labelledby="discontinueModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="discontinueForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="discontinueModalLabel">Discontinue Medication</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close">x</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="discontinue_patient_id" name="patient_id"
                            value="{{ $patient->id ?? '' }}">
                        <input type="hidden" id="discontinue_medication_id" name="product_or_service_request_id">

                        <div class="mb-3">
                            <label id="discontinue-medication-name" class="form-label fw-bold"></label>
                        </div>

                        <div class="alert alert-warning">
                            <i class="mdi mdi-alert-circle"></i> Discontinuing this medication will prevent future
                            administrations. This action can be reversed by using the Resume function.
                        </div>

                        <div class="mb-3">
                            <label for="discontinue_reason" class="form-label">Reason for Discontinuation</label>
                            <textarea class="form-control" id="discontinue_reason" name="reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="discontinueSubmitBtn" class="btn btn-danger">
                            <span class="spinner-border spinner-border-sm d-none" role="status"
                                aria-hidden="true"></span>
                            Discontinue Medication
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Resume Modal -->
    <div class="modal fade" id="resumeModal" tabindex="-1" aria-labelledby="resumeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="resumeForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="resumeModalLabel">Resume Medication</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close">x</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="resume_patient_id" name="patient_id"
                            value="{{ $patient->id ?? '' }}">
                        <input type="hidden" id="resume_medication_id" name="product_or_service_request_id">

                        <div class="mb-3">
                            <label id="resume-medication-name" class="form-label fw-bold"></label>
                        </div>

                        <div class="alert alert-info">
                            <i class="mdi mdi-information"></i> Resuming this medication will allow administrations to
                            continue according to the existing schedule.
                        </div>

                        <div class="mb-3">
                            <label for="resume_reason" class="form-label">Reason for Resuming</label>
                            <textarea class="form-control" id="resume_reason" name="reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="resumeSubmitBtn" class="btn btn-success">
                            <span class="spinner-border spinner-border-sm d-none" role="status"
                                aria-hidden="true"></span>
                            Resume Medication
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Administration Modal -->
    <div class="modal fade" id="editAdminModal" tabindex="-1" aria-labelledby="editAdminModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="editAdminForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editAdminModalLabel">Edit Administration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close">x</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_admin_id" name="administration_id">

                        <div class="mb-3">
                            <label for="edit_administered_at" class="form-label">Time of Administration</label>
                            <input type="datetime-local" class="form-control" id="edit_administered_at"
                                name="administered_at" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_dose" class="form-label">Dose</label>
                            <input type="text" class="form-control" id="edit_dose" name="dose" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_route" class="form-label">Route</label>
                            <select class="form-select" id="edit_route" name="route" required>
                                <option value="Oral">Oral</option>
                                <option value="IV">IV</option>
                                <option value="IM">IM</option>
                                <option value="SC">SC</option>
                                <option value="Topical">Topical</option>
                                <option value="Rectal">Rectal</option>
                                <option value="Inhalation">Inhalation</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="edit_comment" class="form-label">Notes</label>
                            <textarea class="form-control" id="edit_comment" name="comment" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="edit_reason" class="form-label">Reason for Editing</label>
                            <textarea class="form-control" id="edit_reason" name="edit_reason" rows="2" required></textarea>
                        </div>

                        <div class="alert alert-info small">
                            <i class="mdi mdi-clock-alert"></i> You can only edit administrations within <span
                                id="edit-window-time">30</span> minutes of the original entry.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="editAdminSubmitBtn" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm d-none" role="status"
                                aria-hidden="true"></span>
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Administration Modal -->
    <div class="modal fade" id="deleteAdminModal" tabindex="-1" aria-labelledby="deleteAdminModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="deleteAdminForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteAdminModalLabel">Delete Administration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close">x</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="delete_admin_id" name="administration_id">

                        <div class="alert alert-danger">
                            <i class="mdi mdi-alert-octagon"></i> Are you sure you want to delete this administration
                            record? This action will mark the record as deleted but will maintain it in the system for
                            audit purposes.
                        </div>

                        <div class="mb-3">
                            <label for="delete_reason" class="form-label">Reason for Deletion</label>
                            <textarea class="form-control" id="delete_reason" name="reason" rows="3" required></textarea>
                        </div>

                        <div class="alert alert-info small">
                            <i class="mdi mdi-clock-alert"></i> You can only delete administrations within <span
                                id="delete-window-time">30</span> minutes of the original entry.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="deleteAdminSubmitBtn" class="btn btn-danger">
                            <span class="spinner-border spinner-border-sm d-none" role="status"
                                aria-hidden="true"></span>
                            Delete Record
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Administration Details Modal -->
    <div class="modal fade" id="adminDetailsModal" tabindex="-1" aria-labelledby="adminDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminDetailsModalLabel">Administration Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
                </div>
                <div class="modal-body">
                    <div id="admin-details-content">
                        <!-- Will be populated via JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="edit-admin-btn">Edit</button>
                    <button type="button" class="btn btn-danger" id="delete-admin-btn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Overview Medication Details Modal -->
    <div class="modal fade" id="overviewMedDetailsModal" tabindex="-1" aria-labelledby="overviewMedDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="overviewMedDetailsModalLabel">
                        <i class="mdi mdi-pill me-2"></i>Medication Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="overviewMedDetailsModalBody">
                    <!-- Content populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Dismiss Prescription Confirmation Modal -->
    <div class="modal fade" id="dismissRxModal" tabindex="-1" aria-labelledby="dismissRxModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="dismissRxModalLabel"><i class="mdi mdi-close-circle"></i> Dismiss Prescription</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="dismiss-rx-id">
                    <div class="alert alert-warning mb-3">
                        <i class="mdi mdi-alert me-1"></i>
                        You are about to dismiss this prescription. This action cannot be undone.
                    </div>
                    <div class="mb-3" id="dismiss-rx-info">
                        <!-- Drug info populated by JS -->
                    </div>
                    <div class="mb-3">
                        <label for="dismiss-rx-reason" class="form-label fw-bold">Reason for Dismissal <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="dismiss-rx-reason" rows="3" placeholder="Enter reason for dismissing this prescription..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-dismiss-rx-btn">
                        <i class="mdi mdi-close-circle me-1"></i> Confirm Dismiss
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- §6.3: Patient's Own Drug Modal --}}
    <div class="modal fade" id="patientOwnModal" tabindex="-1" aria-labelledby="patientOwnModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form id="patientOwnForm">
                <div class="modal-content">
                    <div class="modal-header" style="background: #7b1fa2; color: #fff;">
                        <h5 class="modal-title" id="patientOwnModalLabel">
                            <i class="mdi mdi-account-heart me-1"></i> Administer Patient's Own Drug
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="drug_source" value="patient_own">

                        <div class="alert alert-light border mb-3">
                            <i class="mdi mdi-information-outline text-info me-1"></i>
                            Record a drug the patient brought from outside. No billing or stock changes.
                        </div>

                        <h6 class="text-muted mb-3"><i class="mdi mdi-pill me-1"></i> Drug Information</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="po_drug_name" class="form-label">Drug Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="po_drug_name" name="external_drug_name" placeholder="e.g. Metformin 500mg" required>
                            </div>
                            <div class="col-md-3">
                                <label for="po_qty" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="po_qty" name="external_qty" min="1" value="1" required>
                            </div>
                            <div class="col-md-3">
                                <label for="po_batch" class="form-label">Batch No.</label>
                                <input type="text" class="form-control" id="po_batch" name="external_batch_number" placeholder="Optional">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="po_expiry" class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" id="po_expiry" name="external_expiry_date">
                            </div>
                            <div class="col-md-8">
                                <label for="po_source_note" class="form-label">Source Note</label>
                                <input type="text" class="form-control" id="po_source_note" name="external_source_note" placeholder="e.g. Brought by wife, purchased from XYZ pharmacy">
                            </div>
                        </div>

                        <hr>
                        <h6 class="text-muted mb-3"><i class="mdi mdi-needle me-1"></i> Administration Details</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="po_dose" class="form-label">Dose <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="po_dose" name="administered_dose" placeholder="e.g. 500mg" required>
                            </div>
                            <div class="col-md-4">
                                <label for="po_route" class="form-label">Route <span class="text-danger">*</span></label>
                                <select class="form-select" id="po_route" name="route" required>
                                    <option value="Oral">Oral</option>
                                    <option value="IV">IV</option>
                                    <option value="IM">IM</option>
                                    <option value="SC">SC</option>
                                    <option value="Topical">Topical</option>
                                    <option value="Rectal">Rectal</option>
                                    <option value="Inhalation">Inhalation</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="po_administered_at" class="form-label">Administered At <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="po_administered_at" name="administered_at" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="po_comment" class="form-label">Comment</label>
                            <textarea class="form-control" id="po_comment" name="note" rows="2" placeholder="Optional notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white" id="patientOwnSubmitBtn" style="background: #7b1fa2;">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <i class="mdi mdi-check me-1"></i> Administer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- §6.4: Ward Stock Modal --}}
    <div class="modal fade" id="wardStockModal" tabindex="-1" aria-labelledby="wardStockModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form id="wardStockForm">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="wardStockModalLabel">
                            <i class="mdi mdi-hospital-building me-1"></i> Administer from Ward Stock
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="drug_source" value="ward_stock">

                        <div class="alert alert-light border mb-3">
                            <i class="mdi mdi-information-outline text-info me-1"></i>
                            Administer a drug from the ward/store inventory. Stock will be deducted.
                        </div>

                        <h6 class="text-muted mb-3"><i class="mdi mdi-store me-1"></i> Stock Selection</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="ws_store" class="form-label">Ward/Store <span class="text-danger">*</span></label>
                                <select class="form-select" id="ws_store" name="store_id" required>
                                    <option value="">-- Select Store --</option>
                                    {{-- Populated via AJAX --}}
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="ws_product_search" class="form-label">Search Product <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <input type="text" class="form-control" id="ws_product_search" placeholder="Type to search products..." autocomplete="off">
                                    <input type="hidden" id="ws_product_id" name="product_id">
                                    <ul class="list-group position-absolute w-100 shadow" id="ws_product_results" style="z-index: 1060; display: none; max-height: 250px; overflow-y: auto;"></ul>
                                </div>
                            </div>
                        </div>

                        {{-- Selected product info card --}}
                        <div id="ws_product_info" class="card border-primary mb-3" style="display: none;">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong id="ws_product_name"></strong>
                                        <small class="text-muted ms-2" id="ws_product_code"></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success" id="ws_available_stock">0 available</span>
                                        <span class="ms-2 text-muted" id="ws_product_price"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="ws_qty" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="ws_qty" name="qty" min="1" value="1" required>
                            </div>
                        </div>

                        <hr>
                        <h6 class="text-muted mb-3"><i class="mdi mdi-needle me-1"></i> Administration Details</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="ws_dose" class="form-label">Dose <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ws_dose" name="administered_dose" placeholder="e.g. 500mg" required>
                            </div>
                            <div class="col-md-4">
                                <label for="ws_route" class="form-label">Route <span class="text-danger">*</span></label>
                                <select class="form-select" id="ws_route" name="route" required>
                                    <option value="Oral">Oral</option>
                                    <option value="IV">IV</option>
                                    <option value="IM">IM</option>
                                    <option value="SC">SC</option>
                                    <option value="Topical">Topical</option>
                                    <option value="Rectal">Rectal</option>
                                    <option value="Inhalation">Inhalation</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="ws_administered_at" class="form-label">Administered At <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="ws_administered_at" name="administered_at" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="ws_comment" class="form-label">Comment</label>
                            <textarea class="form-control" id="ws_comment" name="note" rows="2" placeholder="Optional notes..."></textarea>
                        </div>

                        <hr>
                        {{-- §6.4: Bill Patient checkbox --}}
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="ws_bill_patient" name="bill_patient" value="1">
                            <label class="form-check-label" for="ws_bill_patient">
                                <strong>Bill Patient</strong>
                            </label>
                        </div>
                        <small class="text-muted d-block mb-2">
                            <i class="mdi mdi-information-outline me-1"></i>
                            When checked, creates a billing entry with HMO tariff pricing. The item will appear in the patient's billing queue for cashier processing.
                        </small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="wardStockSubmitBtn">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <i class="mdi mdi-check me-1"></i> Administer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
