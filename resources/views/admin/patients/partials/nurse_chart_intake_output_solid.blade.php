<div class="solid-intake-output-section">
    <!-- Date Range Filter -->
    <div class="card-modern mb-3">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="mdi mdi-calendar-range me-1"></i> Date Range Filter</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="solid_start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="solid_start_date">
                </div>
                <div class="col-md-3">
                    <label for="solid_end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="solid_end_date">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="btn-group w-100">
                        <button type="button" class="btn btn-primary" id="solid_apply_filter_btn">
                            <i class="mdi mdi-filter"></i> Apply
                        </button>
                        <button type="button" class="btn btn-secondary" id="solid_reset_filter_btn">
                            <i class="mdi mdi-refresh"></i> Reset
                        </button>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="btn-group w-100">
                        <button type="button" class="btn btn-sm btn-outline-success solid-date-preset" data-days="0" title="Today">
                            Today
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success solid-date-preset" data-days="7" title="Last 7 days">
                            7D
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success solid-date-preset" data-days="30" title="Last 30 days">
                            30D
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Statistics Dashboard -->
    <div class="card-modern mb-3" id="solid-statistics-card" style="display: none;">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="mdi mdi-chart-bar me-1"></i> Statistics & Trends</h6>
        </div>
        <div class="card-body">
            <div class="row text-center mb-3">
                <div class="col-md-3">
                    <div class="stat-box">
                        <h3 class="text-success mb-0" id="solid-total-intake">0</h3>
                        <small class="text-muted">Total Intake (g)</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h3 class="text-danger mb-0" id="solid-total-output">0</h3>
                        <small class="text-muted">Total Output (g)</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h3 id="solid-net-balance" class="mb-0">0</h3>
                        <small class="text-muted">Net Balance (g)</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h3 class="text-info mb-0" id="solid-record-count">0</h3>
                        <small class="text-muted">Total Records</small>
                    </div>
                </div>
            </div>
            <div class="chart-container" style="position: relative; height: 300px;">
                <canvas id="solidTrendsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Date Range Navigation -->
    <div class="card-modern mb-3">
        <div class="card-body p-2">
            <div class="d-flex justify-content-between align-items-center">
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="solid_prev_period_btn">
                        <i class="mdi mdi-chevron-left"></i> Previous 30 Days
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="solid_current_period_btn">
                        Current Period
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="solid_next_period_btn">
                        Next 30 Days <i class="mdi mdi-chevron-right"></i>
                    </button>
                </div>
                <div id="solid_date_range_display" class="text-muted small"></div>
            </div>
        </div>
    </div>

    <!-- Solid Legend -->
    <div class="card-modern mb-2">
        <div class="card-body p-2">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <small class="text-muted me-2">Legend:</small>
                <span class="badge bg-success rounded-pill d-flex align-items-center">
                    <i class="mdi mdi-food-apple me-1"></i> Intake
                </span>
                <span class="badge bg-danger rounded-pill d-flex align-items-center">
                    <i class="mdi mdi-delete-empty me-1"></i> Output
                </span>
                <span class="badge bg-info rounded-pill d-flex align-items-center">
                    <i class="mdi mdi-clock-start me-1"></i> Active
                </span>
                <span class="badge bg-secondary rounded-pill d-flex align-items-center">
                    <i class="mdi mdi-clock-end me-1"></i> Ended
                </span>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6><i class="mdi mdi-food-apple text-success me-1"></i>Solid Intake & Output</h6>
        <div class="btn-group">
            <button class="btn btn-outline-secondary btn-sm" id="exportSolidPdfBtn" title="Export to PDF">
                <i class="mdi mdi-file-pdf"></i> Export PDF
            </button>
            <button class="btn btn-success btn-sm rounded-pill" id="startSolidPeriodBtn">
                <i class="mdi mdi-plus-circle me-1"></i> Start New Period
            </button>
        </div>
    </div>
    <div id="solid-periods-list">
        <!-- Periods and records will be loaded via AJAX -->
    </div>
    <!-- Modal for adding record -->
    <div class="modal fade" id="solidRecordModal" tabindex="-1" aria-labelledby="solidRecordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="solidRecordForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="solidRecordModalLabel">
                            <i class="mdi mdi-food-apple me-1"></i> Add Solid Intake/Output
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="period_id" id="solid_period_id">
                        <div class="mb-3">
                            <label for="solid_type" class="form-label">Type</label>
                            <select class="form-control" name="type" id="solid_type" required>
                                <option value="intake">Intake</option>
                                <option value="output">Output</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="solid_amount" class="form-label">Amount (g)</label>
                            <input type="number" class="form-control" name="amount" id="solid_amount" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="solid_description" class="form-label">Description</label>
                            <input type="text" class="form-control" name="description" id="solid_description">
                        </div>
                        <div class="mb-3">
                            <label for="solid_recorded_at" class="form-label">Time</label>
                            <input type="datetime-local" class="form-control" name="recorded_at" id="solid_recorded_at" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-content-save me-1"></i> Save
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
