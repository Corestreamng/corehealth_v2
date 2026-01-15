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
</style>

<div class="medication-chart-section">
    <h5>Medication Chart / Treatment Sheet</h5>

    <!-- Drug Selection and Controls -->
    <div class="mb-4">
        <div modern shadow-sm">
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
                            <!-- Custom Date Range -->
                            <div class="btn-group date-range-group ms-2">
                                <input type="date" class="form-control form-control-sm" id="med-start-date"
                                    placeholder="Start Date">
                                <span class="input-group-text">to</span>
                                <input type="date" class="form-control form-control-sm" id="med-end-date"
                                    placeholder="End Date">
                                <button type="button" class="btn btn-primary btn-sm" id="apply-date-range-btn">
                                    <i class="mdi mdi-filter"></i>
                                    <span class="d-none d-sm-inline">Apply</span>
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

                        <div class="mb-3">
                            <label id="administer-medication-info" class="form-label fw-bold text-primary"></label>
                        </div>

                        <div class="mb-3">
                            <label id="administer-scheduled-time" class="form-label text-muted"></label>
                        </div>

                        <!-- Store Selection with Stock Display -->
                        <div class="mb-3 p-3 rounded" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 2px solid #81c784;">
                            <label for="administer_store_id" class="form-label fw-bold">
                                <i class="mdi mdi-store text-success"></i> Select Dispensing Store <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="administer_store_id" name="store_id" required>
                                <option value="">-- Choose Store --</option>
                                @foreach($stores ?? [] as $store)
                                    <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                @endforeach
                            </select>
                            <div id="administer-stock-info" class="mt-2 p-2 bg-white rounded shadow-sm" style="display: none;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="small text-muted"><i class="mdi mdi-package-variant"></i> Available Stock:</span>
                                    <span id="administer-stock-qty" class="badge bg-secondary">--</span>
                                </div>
                            </div>
                            <div id="administer-stock-warning" class="alert alert-danger mt-2 py-2 small" style="display: none;">
                                <i class="mdi mdi-alert-circle"></i> <span id="administer-stock-warning-text">Insufficient stock!</span>
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
</div>
