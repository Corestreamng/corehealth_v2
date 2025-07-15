<div class="medication-chart-section">
    <h5>Medication Chart / Treatment Sheet</h5>

    <!-- Drug selection and controls -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="form-group">
                <label for="drug-select" class="form-label">Select Medication</label>
                <select class="form-select" id="drug-select">
                    <option value="">-- Select a medication --</option>
                    <!-- Options will be populated via AJAX -->
                </select>
            </div>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div class="btn-group" id="medication-controls" style="display:none;">
                <button class="btn btn-primary" id="set-schedule-btn">Set Schedule</button>
                <button class="btn btn-outline-danger" id="discontinue-btn">Discontinue</button>
                <button class="btn btn-outline-success" id="resume-btn" style="display:none;">Resume</button>
                <button class="btn btn-outline-secondary" id="calendar-nav-prev">&lt;</button>
                <button class="btn btn-outline-secondary" id="calendar-nav-next">&gt;</button>
            </div>
        </div>
    </div>

    <!-- Status and loading indicators -->
    <div class="alert alert-info" id="drug-status" style="display:none;">
        <!-- Drug status will be shown here -->
    </div>

    <div class="text-center my-4" id="medication-loading" style="display:none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-2 text-primary">Loading medication data...</div>
    </div>

    <!-- Calendar view for the selected medication -->
    <div id="medication-calendar-view" style="display:none;">
        <h6 id="calendar-period" class="text-center mb-3"></h6>
        <div class="table-responsive">
            <table class="table table-bordered table-sm medication-calendar">
                <thead>
                    <tr>
                        <th>Time</th>
                        <!-- Days will be populated via JS -->
                    </tr>
                </thead>
                <tbody>
                    <!-- Timing slots will be populated via JS -->
                </tbody>
            </table>
        </div>
        <div class="mt-2 small text-muted">
            <span class="badge bg-info me-2">Scheduled</span>
            <span class="badge bg-success me-2">Administered</span>
            <span class="badge bg-danger me-2">Missed</span>
            <span class="badge bg-secondary me-2 text-decoration-line-through">Deleted</span>
        </div>
    </div>

    <!-- Medication list for initial selection -->
    <div id="medication-chart-list">
        <!-- Table will be loaded via AJAX -->
    </div>

    <!-- Schedule Setting Modal -->
    <div class="modal fade" id="setScheduleModal" tabindex="-1" aria-labelledby="setScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="scheduleForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="setScheduleModalLabel">Set Medication Schedule</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="product_or_service_request_id" id="schedule_request_id">

                        <div class="mb-3">
                            <label class="form-label">Medication Name: <span id="schedule-medication-name" class="fw-bold"></span></label>
                        </div>

                        <div class="mb-3">
                            <label for="administration_time" class="form-label">Administration Time</label>
                            <input type="time" class="form-control" id="administration_time" required>
                        </div>

                        <div class="mb-3">
                            <label for="dose" class="form-label">Dose</label>
                            <input type="text" class="form-control" id="dose" placeholder="e.g. 500mg" required>
                        </div>

                        <div class="mb-3">
                            <label for="route" class="form-label">Route</label>
                            <select class="form-select" id="schedule_route" required>
                                <option value="Oral">Oral</option>
                                <option value="IV">IV</option>
                                <option value="IM">IM</option>
                                <option value="SC">SC</option>
                                <option value="Topical">Topical</option>
                                <option value="Rectal">Rectal</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="repeat_daily" checked>
                                <label class="form-check-label" for="repeat_daily">
                                    Repeat daily
                                </label>
                            </div>
                        </div>

                        <div id="repeat-days-container" style="display:none;">
                            <label class="form-label">Select days:</label>
                            <div class="d-flex flex-wrap">
                                <div class="form-check me-3">
                                    <input class="form-check-input day-checkbox" type="checkbox" value="0" id="day-sun">
                                    <label class="form-check-label" for="day-sun">Sun</label>
                                </div>
                                <div class="form-check me-3">
                                    <input class="form-check-input day-checkbox" type="checkbox" value="1" id="day-mon">
                                    <label class="form-check-label" for="day-mon">Mon</label>
                                </div>
                                <div class="form-check me-3">
                                    <input class="form-check-input day-checkbox" type="checkbox" value="2" id="day-tue">
                                    <label class="form-check-label" for="day-tue">Tue</label>
                                </div>
                                <div class="form-check me-3">
                                    <input class="form-check-input day-checkbox" type="checkbox" value="3" id="day-wed">
                                    <label class="form-check-label" for="day-wed">Wed</label>
                                </div>
                                <div class="form-check me-3">
                                    <input class="form-check-input day-checkbox" type="checkbox" value="4" id="day-thu">
                                    <label class="form-check-label" for="day-thu">Thu</label>
                                </div>
                                <div class="form-check me-3">
                                    <input class="form-check-input day-checkbox" type="checkbox" value="5" id="day-fri">
                                    <label class="form-check-label" for="day-fri">Fri</label>
                                </div>
                                <div class="form-check me-3">
                                    <input class="form-check-input day-checkbox" type="checkbox" value="6" id="day-sat">
                                    <label class="form-check-label" for="day-sat">Sat</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="duration_days" class="form-label">Duration (days)</label>
                            <input type="number" class="form-control" id="duration_days" min="1" value="3" required>
                        </div>

                        <div class="mb-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="scheduleSubmitBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="scheduleLoading"></span>
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
                            <label id="administer-medication-info" class="form-label fw-bold"></label>
                        </div>
                        <div class="mb-3">
                            <label id="administer-scheduled-time" class="form-label text-muted"></label>
                        </div>
                        <div class="mb-3">
                            <label for="administered_time" class="form-label">Time of Administration</label>
                            <input type="datetime-local" class="form-control" name="administered_time" id="administered_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="administered_dose" class="form-label">Dose</label>
                            <input type="text" class="form-control" name="administered_dose" id="administered_dose" required>
                        </div>
                        <div class="mb-3">
                            <label for="administered_route" class="form-label">Route</label>
                            <select class="form-select" name="route" id="administered_route" required>
                                <option value="Oral">Oral</option>
                                <option value="IV">IV</option>
                                <option value="IM">IM</option>
                                <option value="SC">SC</option>
                                <option value="Topical">Topical</option>
                                <option value="Rectal">Rectal</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="note" class="form-label">Notes (optional)</label>
                            <textarea class="form-control" name="note" id="note"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="administerSubmitBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="administerLoading"></span>
                            Sign Administration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Discontinue Modal -->
    <div class="modal fade" id="discontinueModal" tabindex="-1" aria-labelledby="discontinueModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="discontinueForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="discontinueModalLabel">Discontinue Medication</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="product_or_service_request_id" id="discontinue_request_id">
                        <div class="mb-3">
                            <label id="discontinue-medication-name" class="form-label"></label>
                        </div>
                        <div class="mb-3">
                            <label for="discontinue_reason" class="form-label">Reason for Discontinuation (required)</label>
                            <textarea class="form-control" name="reason" id="discontinue_reason" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="discontinueSubmitBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="discontinueLoading"></span>
                            Discontinue
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Resume Modal -->
    <div class="modal fade" id="resumeModal" tabindex="-1" aria-labelledby="resumeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="resumeForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="resumeModalLabel">Resume Medication</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="product_or_service_request_id" id="resume_request_id">
                        <div class="mb-3">
                            <label id="resume-medication-name" class="form-label"></label>
                        </div>
                        <div class="mb-3">
                            <label for="resume_reason" class="form-label">Reason for Resuming (required)</label>
                            <textarea class="form-control" name="reason" id="resume_reason" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="resumeSubmitBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" id="resumeLoading"></span>
                            Resume
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
