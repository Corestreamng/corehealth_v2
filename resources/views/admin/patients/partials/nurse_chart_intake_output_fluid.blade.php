<div class="fluid-intake-output-section">
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
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
