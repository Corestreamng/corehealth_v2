<div class="fluid-intake-output-section">
    <!-- Date Range Filter -->
    <div class="card mb-3">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="mdi mdi-calendar-range me-1"></i> Date Range Filter</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="fluid_start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="fluid_start_date">
                </div>
                <div class="col-md-4">
                    <label for="fluid_end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="fluid_end_date">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="btn-group w-100">
                        <button type="button" class="btn btn-primary" id="fluid_apply_filter_btn">
                            <i class="mdi mdi-filter"></i> Apply
                        </button>
                        <button type="button" class="btn btn-secondary" id="fluid_reset_filter_btn">
                            <i class="mdi mdi-refresh"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Range Navigation -->
    <div class="card mb-3">
        <div class="card-body p-2">
            <div class="d-flex justify-content-between align-items-center">
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="fluid_prev_period_btn">
                        <i class="mdi mdi-chevron-left"></i> Previous 30 Days
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="fluid_current_period_btn">
                        Current Period
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="fluid_next_period_btn">
                        Next 30 Days <i class="mdi mdi-chevron-right"></i>
                    </button>
                </div>
                <div id="fluid_date_range_display" class="text-muted small"></div>
            </div>
        </div>
    </div>

    <!-- Fluid Legend -->
    <div class="card mb-2">
        <div class="card-body p-2">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <small class="text-muted me-2">Legend:</small>
                <span class="badge bg-primary rounded-pill d-flex align-items-center">
                    <i class="mdi mdi-water me-1"></i> Intake
                </span>
                <span class="badge bg-warning rounded-pill d-flex align-items-center">
                    <i class="mdi mdi-water-off me-1"></i> Output
                </span>
                <span class="badge bg-success rounded-pill d-flex align-items-center">
                    <i class="mdi mdi-clock-start me-1"></i> Active
                </span>
                <span class="badge bg-secondary rounded-pill d-flex align-items-center">
                    <i class="mdi mdi-clock-end me-1"></i> Ended
                </span>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6><i class="mdi mdi-water text-primary me-1"></i>Fluid Intake & Output</h6>
        <button class="btn btn-success btn-sm rounded-pill" id="startFluidPeriodBtn">
            <i class="mdi mdi-plus-circle me-1"></i> Start New Period
        </button>
    </div>
    <div id="fluid-periods-list">
        <!-- Periods and records will be loaded via AJAX -->
    </div>
    <!-- Modal for adding record -->
    <div class="modal fade" id="fluidRecordModal" tabindex="-1" aria-labelledby="fluidRecordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="fluidRecordForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="fluidRecordModalLabel">
                            <i class="mdi mdi-water me-1"></i> Add Fluid Intake/Output
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="period_id" id="fluid_period_id">
                        <div class="mb-3">
                            <label for="fluid_type" class="form-label">Type</label>
                            <select class="form-control" name="type" id="fluid_type" required>
                                <option value="intake">Intake</option>
                                <option value="output">Output</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="fluid_amount" class="form-label">Amount (ml)</label>
                            <input type="number" class="form-control" name="amount" id="fluid_amount" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="fluid_description" class="form-label">Description</label>
                            <input type="text" class="form-control" name="description" id="fluid_description">
                        </div>
                        <div class="mb-3">
                            <label for="fluid_recorded_at" class="form-label">Time</label>
                            <input type="datetime-local" class="form-control" name="recorded_at" id="fluid_recorded_at" required>
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
