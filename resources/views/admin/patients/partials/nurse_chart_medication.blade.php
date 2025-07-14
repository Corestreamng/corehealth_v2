<div class="medication-chart-section">
    <h5>Medication Chart / Treatment Sheet</h5>
    <div id="medication-chart-list">
        <div class="text-center my-4" id="medication-loading" style="display:none;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-2 text-primary">Loading drugs...</div>
        </div>
        <!-- Table will be loaded via AJAX -->
    </div>
    <!-- Set Timing Modal -->
    <div class="modal fade" id="setTimingModal" tabindex="-1" aria-labelledby="setTimingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="setTimingForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="setTimingModalLabel">Set Drug Timing</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="product_or_service_request_id" id="set_timing_request_id">
                        <div class="mb-3">
                            <label for="scheduled_time" class="form-label">Scheduled Time</label>
                            <input type="datetime-local" class="form-control" name="scheduled_time" id="scheduled_time" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="setTimingSubmitBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="setTimingLoading"></span>
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Modal for administration (Bootstrap 5) -->
    <div class="modal fade" id="administerModal" tabindex="-1" aria-labelledby="administerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="administerForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="administerModalLabel">Administer Medication</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="schedule_id" id="administer_schedule_id">
                        <div class="mb-3">
                            <label for="administered_time" class="form-label">Time of Administration</label>
                            <input type="datetime-local" class="form-control" name="administered_time" id="administered_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="route" class="form-label">Route</label>
                            <input type="text" class="form-control" name="route" id="route" required>
                        </div>
                        <div class="mb-3">
                            <label for="note" class="form-label">Extra Note (optional)</label>
                            <textarea class="form-control" name="note" id="note"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="administerSubmitBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="administerLoading"></span>
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
