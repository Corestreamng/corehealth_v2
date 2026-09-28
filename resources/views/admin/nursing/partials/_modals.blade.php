<div class="modal fade" id="medicationLogsModal" tabindex="-1" aria-labelledby="medicationLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="medication-logs-title">Activity Logs</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="medication-logs-content">
                    <!-- Logs will be populated via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Note Modal -->
<div class="modal fade" id="editNoteModal" tabindex="-1" aria-labelledby="editNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editNoteModalLabel">Edit Nursing Note</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="edit-note-form">
                    <input type="hidden" id="edit-note-id">
                    <div class="form-group">
                        <label for="edit-note-content">Note Content</label>
                        <div id="edit-note-editor"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updatedNote()">Update Note</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Vital Modal -->
<div class="modal fade" id="editVitalModal" tabindex="-1" aria-labelledby="editVitalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: {{ $hosColor }}; color: white;">
                <h5 class="modal-title" id="editVitalModalLabel"><i class="mdi mdi-heart-pulse"></i> Edit Vitals</h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="edit-vital-form">
                    <input type="hidden" id="edit-vital-id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit-blood-pressure" class="form-label"><i class="mdi mdi-heart-pulse text-danger"></i> Blood Pressure</label>
                            <input type="text" class="form-control" id="edit-blood-pressure" placeholder="e.g., 120/80">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-temp" class="form-label"><i class="mdi mdi-thermometer text-warning"></i> Temperature (┬░C)</label>
                            <input type="number" step="0.1" class="form-control" id="edit-temp" placeholder="e.g., 36.5">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-heart-rate" class="form-label"><i class="mdi mdi-heart text-danger"></i> Heart Rate (bpm)</label>
                            <input type="number" class="form-control" id="edit-heart-rate" placeholder="e.g., 72">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-resp-rate" class="form-label"><i class="mdi mdi-lungs text-primary"></i> Resp. Rate (bpm)</label>
                            <input type="number" class="form-control" id="edit-resp-rate" placeholder="e.g., 16">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-weight" class="form-label"><i class="mdi mdi-weight text-success"></i> Weight (kg)</label>
                            <input type="number" step="0.1" class="form-control" id="edit-weight" placeholder="e.g., 70">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-height" class="form-label"><i class="mdi mdi-human-male-height"></i> Height (cm)</label>
                            <input type="number" step="0.1" class="form-control" id="edit-height" placeholder="e.g., 170">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-spo2" class="form-label"><i class="mdi mdi-percent"></i> SpO2 (%)</label>
                            <input type="number" step="0.1" class="form-control" id="edit-spo2" placeholder="e.g., 98">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit-blood-sugar" class="form-label"><i class="mdi mdi-water"></i> Blood Sugar (mg/dL)</label>
                            <input type="number" step="0.1" class="form-control" id="edit-blood-sugar" placeholder="e.g., 100">
                        </div>
                        <div class="col-12 mb-3">
                            <label for="edit-other-notes" class="form-label"><i class="mdi mdi-note-text"></i> Notes</label>
                            <textarea class="form-control" id="edit-other-notes" rows="2" placeholder="Any additional notes..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateVital()"><i class="mdi mdi-check"></i> Update Vitals</button>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.invest_res_view_modal')
@include('admin.partials.invest_res_view_js')

<!-- Delete Reason Modal -->
<div class="modal fade" id="deleteReasonModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fa fa-trash"></i> Delete Lab Request</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="deleteRequestForm">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action will soft delete the lab request. It can be restored from the trash later.
                    </div>
                    <div class="mb-3">
                        <p><strong>Service:</strong> <span id="delete_service_name"></span></p>
                        <p><strong>Request ID:</strong> <span id="delete_request_id"></span></p>
                    </div>
                    <div class="form-group">
                        <label for="delete_reason">Reason for Deletion <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="delete_reason" name="reason" rows="4"
                                  placeholder="Please provide a detailed reason for deleting this lab request (minimum 10 characters)"
                                  required minlength="10"></textarea>
                        <small class="form-text text-muted">This reason will be logged for audit purposes.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-trash"></i> Delete Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Dismiss Reason Modal -->
<div class="modal fade" id="dismissReasonModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fa fa-ban"></i> Dismiss Lab Request</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
            </div>
            <form id="dismissRequestForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <strong>Info:</strong> Dismissed requests can be restored later from the trash panel.
                    </div>
                    <div class="mb-3">
                        <p><strong>Service:</strong> <span id="dismiss_service_name"></span></p>
                        <p><strong>Request ID:</strong> <span id="dismiss_request_id"></span></p>
                    </div>
                    <div class="form-group">
                        <label for="dismiss_reason">Reason for Dismissal <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="dismiss_reason" name="reason" rows="4"
                                  placeholder="Please provide a reason for dismissing this lab request (minimum 10 characters)"
                                  required minlength="10"></textarea>
                        <small class="form-text text-muted">This reason will be logged for audit purposes.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fa fa-ban"></i> Dismiss Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Audit Log Modal -->
<div class="modal fade" id="auditLogModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fa fa-clipboard-list"></i> Audit Trail</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label>Action Type</label>
                        <select class="form-control" id="audit_action_filter">
                            <option value="">All Actions</option>
                            <option value="view">View</option>
                            <option value="edit">Edit</option>
                            <option value="delete">Delete</option>
                            <option value="restore">Restore</option>
                            <option value="dismiss">Dismiss</option>
                            <option value="undismiss">Undismiss</option>
                            <option value="billing">Billing</option>
                            <option value="sample_collection">Sample Collection</option>
                            <option value="result_entry">Result Entry</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" class="form-control" id="audit_from_date">
                    </div>
                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" class="form-control" id="audit_to_date">
                    </div>
                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary btn-block" id="applyAuditFilter">
                            <i class="fa fa-filter"></i> Apply Filter
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="audit-log-table" style="width: 100%">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="exportAuditLog">
                    <i class="fa fa-download"></i> Export to Excel
                </button>
            </div>
        </div>
    </div>
</div>




<!-- =============================================
     SHIFT MANAGEMENT UI COMPONENTS
     ============================================= -->

<!-- Workbench Lock Overlay (shown when no active shift) -->
<div id="shift-lock-overlay" class="shift-lock-overlay" style="display: none;">
    <div class="shift-lock-content">
        <div class="shift-lock-icon">
            <i class="mdi mdi-clock-alert-outline"></i>
        </div>
        <h3>Start Your Shift</h3>
        <p class="text-muted">Please start your shift to access the nursing workbench and begin documenting patient care.</p>
        <div id="pending-handovers-preview" class="pending-handovers-preview" style="display: none;">
            <div class="alert alert-warning mb-3">
                <i class="mdi mdi-alert-circle"></i>
                <span id="pending-handovers-count">0</span> handover(s) from the last 24 hours need your attention
            </div>
            <div id="pending-handovers-list" class="pending-handovers-list mb-3"></div>
        </div>
        <button class="btn btn-lg btn-success" id="start-shift-btn">
            <i class="mdi mdi-play-circle"></i> Start Shift
        </button>
        <div class="shift-lock-nav-buttons mt-4">
            <a href="{{ route('home') }}" class="btn btn-outline-primary btn-lg me-2">
                <i class="mdi mdi-home"></i> Home
            </a>
            <a href="{{ route('logout') }}"
               onclick="event.preventDefault(); document.getElementById('shift-overlay-logout-form').submit();"
               class="btn btn-outline-danger btn-lg">
                <i class="mdi mdi-logout"></i> Logout
            </a>
            <form id="shift-overlay-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                @csrf
            </form>
        </div>
    </div>
</div>

<!-- Floating Shift Control Button -->
<div id="shift-control-fab" class="shift-control-fab" style="display: none;">
    <div class="shift-fab-timer">
        <span id="shift-elapsed-time">00:00</span>
    </div>
    <div class="shift-fab-main">
        <button class="btn btn-shift-control" id="shift-fab-btn" title="Shift Controls">
            <i class="mdi mdi-account-clock"></i>
        </button>
    </div>
    <div class="shift-fab-actions" style="display: none;">
        <button class="btn btn-sm btn-info shift-action-btn" id="view-shift-summary" title="View Shift Summary">
            <i class="mdi mdi-chart-bar"></i>
        </button>
        <button class="btn btn-sm btn-warning shift-action-btn" id="view-handovers-btn" title="View Handovers">
            <i class="mdi mdi-file-document-multiple"></i>
        </button>
        <button class="btn btn-sm btn-danger shift-action-btn" id="end-shift-btn" title="End Shift">
            <i class="mdi mdi-stop-circle"></i>
        </button>
    </div>
</div>

<!-- Start Shift Modal -->
<div class="modal fade" id="startShiftModal" tabindex="-1" aria-labelledby="startShiftModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="startShiftModalLabel">
                    <i class="mdi mdi-play-circle"></i> Start Your Shift
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Step 1: Shift Configuration -->
                <div id="shift-config-step" class="shift-step">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="shift-ward-select">Ward Assignment</label>
                                <select class="form-control" id="shift-ward-select">
                                    <option value="">All Wards (Floating)</option>
                                </select>
                                <small class="text-muted">Select your assigned ward to see relevant handovers</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="shift-type-select">Shift Type</label>
                                <select class="form-control" id="shift-type-select">
                                    <option value="">Auto-detect</option>
                                    <option value="morning">🌅 Morning (6AM - 2PM)</option>
                                    <option value="afternoon">☀️ Afternoon (2PM - 10PM)</option>
                                    <option value="night">🌙 Night (10PM - 6AM)</option>
                                </select>
                                <small class="text-muted">Leave blank to auto-detect based on current time</small>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-outline-primary" id="load-ward-handovers-btn">
                            <i class="mdi mdi-magnify"></i> Check for Handovers
                        </button>
                    </div>
                </div>

                <!-- Step 2: Pending Handovers (if any) -->
                <div id="shift-handovers-step" class="shift-step" style="display: none;">
                    <hr>
                    <div class="alert alert-info">
                        <i class="mdi mdi-information-outline"></i>
                        <strong>Review Previous Handovers</strong><br>
                        Please review and acknowledge the following handovers before starting your shift.
                    </div>
                    <div id="start-shift-handovers-list" class="handovers-acknowledgment-list"></div>
                    <div class="text-center mt-3">
                        <button class="btn btn-outline-primary btn-sm" id="load-more-handovers-btn">
                            <i class="mdi mdi-history"></i> Load Older Handovers
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirm-start-shift-btn">
                    <i class="mdi mdi-play-circle"></i> Start Shift
                </button>
            </div>
        </div>
    </div>
</div>

<!-- End Shift Modal -->
<div class="modal fade" id="endShiftModal" tabindex="-1" aria-labelledby="endShiftModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="endShiftModalLabel">
                    <i class="mdi mdi-stop-circle"></i> End Your Shift
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Shift Summary -->
                <div class="shift-end-summary mb-4">
                    <h6 class="text-muted mb-3">Shift Summary</h6>
                    <div class="row text-center">
                        <div class="col">
                            <div class="stat-box">
                                <div class="stat-value" id="end-shift-duration">--:--</div>
                                <div class="stat-label">Duration</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stat-box">
                                <div class="stat-value" id="end-shift-vitals">0</div>
                                <div class="stat-label">Vitals</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stat-box">
                                <div class="stat-value" id="end-shift-medications">0</div>
                                <div class="stat-label">Medications</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stat-box">
                                <div class="stat-value" id="end-shift-notes">0</div>
                                <div class="stat-label">Notes</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="stat-box">
                                <div class="stat-value" id="end-shift-total">0</div>
                                <div class="stat-label">Total Actions</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Audit-Based Activity Preview -->
                <div class="audit-activity-preview mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="text-muted mb-0">
                            <i class="mdi mdi-history"></i> Recorded Activities (Auto-tracked)
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="load-shift-preview-btn">
                            <i class="mdi mdi-refresh"></i> Load Preview
                        </button>
                    </div>
                    <div id="shift-activity-preview" class="border rounded p-3 bg-light" style="max-height: 300px; overflow-y: auto;">
                        <div class="text-center text-muted py-3">
                            <i class="mdi mdi-information-outline"></i> Click "Load Preview" to see auto-tracked activities during your shift
                        </div>
                    </div>
                </div>

                <!-- Handover Form -->
                <div class="handover-form">
                    <h6 class="text-muted mb-3">Create Handover Document</h6>

                    <div class="form-group mb-3">
                        <label for="end-shift-critical-notes">
                            <i class="mdi mdi-alert text-danger"></i> Critical Notes
                            <small class="text-muted">(Urgent items for incoming nurse)</small>
                        </label>
                        <textarea class="form-control" id="end-shift-critical-notes" rows="3"
                            placeholder="Document any critical patient conditions, pending urgent tasks, or important alerts..."></textarea>
                    </div>

                    <div class="form-group mb-3">
                        <label for="end-shift-concluding-notes">
                            <i class="mdi mdi-note-text"></i> Concluding Notes
                        </label>
                        <textarea class="form-control" id="end-shift-concluding-notes" rows="3"
                            placeholder="General shift summary and observations..."></textarea>
                    </div>

                    <div class="form-group mb-3">
                        <label><i class="mdi mdi-format-list-checks"></i> Pending Tasks</label>
                        <div id="pending-tasks-container">
                            <div class="pending-task-row mb-2">
                                <div class="input-group">
                                    <select class="form-control form-control-sm pending-task-priority" style="max-width: 100px;">
                                        <option value="normal">Normal</option>
                                        <option value="low">Low</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                    <input type="text" class="form-control pending-task-desc" placeholder="Describe pending task...">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-danger remove-pending-task" type="button">
                                            <i class="mdi mdi-close"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary mt-2" id="add-pending-task-btn">
                            <i class="mdi mdi-plus"></i> Add Task
                        </button>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="create-handover-checkbox" checked>
                        <label class="form-check-label" for="create-handover-checkbox">
                            Create handover document for incoming nurse
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-end-shift-btn">
                    <i class="mdi mdi-stop-circle"></i> End Shift
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Handovers List Modal (Cards-based with Master-Detail View) -->
<div class="modal fade" id="handoversListModal" tabindex="-1" aria-labelledby="handoversListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-handover-wide">
        <div class="modal-content">
            <div class="modal-header bg-info text-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-light me-2" id="back-to-handovers-list-btn" style="display: none;">
                        <i class="mdi mdi-arrow-left"></i> Back to Handovers
                    </button>
                    <h5 class="modal-title mb-0" id="handoversListModalLabel">
                        <i class="mdi mdi-file-document-multiple"></i> Shift Handovers
                    </h5>
                </div>
                <button type="button" data-bs-dismiss="modal" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Master View: Filters & Cards/List -->
                <div id="handover-master-view" class="handover-master-view">
                    <!-- Filter Panel -->
                    <div class="handover-filter-panel p-3 bg-light border-bottom">
                        <!-- Primary Filters Row -->
                        <div class="row g-3 mb-2">
                            <div class="col-md-3">
                                <label class="form-label-modern">
                                    <i class="mdi mdi-hospital-building"></i> Ward
                                </label>
                                <select class="form-control form-control-modern" id="handover-filter-ward">
                                    <option value="">All Wards</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-modern">
                                    <i class="mdi mdi-clock-outline"></i> Shift Type
                                </label>
                                <select class="form-control form-control-modern" id="handover-filter-shift">
                                    <option value="">All Shifts</option>
                                    <option value="morning">🌅 Morning (6AM - 2PM)</option>
                                    <option value="afternoon">☀️ Afternoon (2PM - 10PM)</option>
                                    <option value="night">🌙 Night (10PM - 6AM)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label-modern">
                                    <i class="mdi mdi-magnify"></i> Search
                                </label>
                                <input type="text" class="form-control form-control-modern" id="handover-filter-search"
                                       placeholder="Search by nurse, summary...">
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button class="btn btn-primary flex-grow-1 btn-modern" id="apply-handover-filters">
                                    <i class="mdi mdi-filter"></i> Apply Filters
                                </button>
                            </div>
                        </div>

                        <!-- Advanced Filters -->
                        <div id="advancedFiltersSection">
                            <div class="row g-2 pt-3 border-top mt-2">
                                <div class="col-md-2">
                                    <label class="form-label-modern">
                                        <i class="mdi mdi-check-circle-outline"></i> Status
                                    </label>
                                    <select class="form-control form-control-modern" id="handover-filter-status">
                                        <option value="">All Status</option>
                                        <option value="pending">🟡 Pending</option>
                                        <option value="acknowledged">✅ Acknowledged</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label-modern">
                                        <i class="mdi mdi-alert-circle-outline"></i> Priority
                                    </label>
                                    <select class="form-control form-control-modern" id="handover-filter-priority">
                                        <option value="">All Priority</option>
                                        <option value="critical">🔴 Critical Only</option>
                                        <option value="has_tasks">📋 Has Pending Tasks</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label-modern">
                                        <i class="mdi mdi-calendar-start"></i> Date From
                                    </label>
                                    <input type="date" class="form-control form-control-modern" id="handover-filter-from">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label-modern">
                                        <i class="mdi mdi-calendar-end"></i> Date To
                                    </label>
                                    <input type="date" class="form-control form-control-modern" id="handover-filter-to">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label-modern">
                                        <i class="mdi mdi-sort"></i> Sort By
                                    </label>
                                    <select class="form-control form-control-modern" id="handover-filter-sort">
                                        <option value="newest">Newest First</option>
                                        <option value="oldest">Oldest First</option>
                                        <option value="priority">Priority First</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button class="btn btn-outline-danger w-100 btn-modern" id="clear-handover-filters">
                                        <i class="mdi mdi-filter-remove"></i> Clear All
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Summary Bar -->
                    <div class="handover-stats-bar px-3 py-2 bg-white border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex gap-3">
                            <span class="badge bg-secondary" id="handover-total-count">
                                <i class="mdi mdi-file-document-multiple"></i> Total: <span>0</span>
                            </span>
                            <span class="badge bg-warning text-dark" id="handover-pending-count">
                                <i class="mdi mdi-clock-alert"></i> Pending: <span>0</span>
                            </span>
                            <span class="badge bg-danger" id="handover-critical-count">
                                <i class="mdi mdi-alert-circle"></i> Critical: <span>0</span>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small" id="handover-page-info">Page 1 of 1</span>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary" id="handover-view-cards" title="Cards View">
                                    <i class="mdi mdi-view-grid"></i>
                                </button>
                                <button class="btn btn-outline-secondary" id="handover-view-list" title="List View">
                                    <i class="mdi mdi-view-list"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Cards Container -->
                    <div class="handover-cards-container p-3" id="handover-cards-container">
                        <!-- Loading State -->
                        <div class="handover-loading text-center py-5" id="handover-loading">
                            <div class="spinner-border text-info" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading handovers...</p>
                        </div>

                        <!-- Empty State -->
                        <div class="handover-empty text-center py-5" id="handover-empty" style="display: none;">
                            <i class="mdi mdi-file-document-outline text-muted" style="font-size: 4rem;"></i>
                            <h5 class="mt-3 text-muted">No Handovers Found</h5>
                            <p class="text-muted small">Try adjusting your filters or select a different ward/shift.</p>
                        </div>

                        <!-- Cards Grid (populated dynamically) -->
                        <div class="row g-3" id="handover-cards-grid"></div>
                    </div>

                    <!-- Pagination -->
                    <div class="handover-pagination p-3 bg-light border-top d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <label class="form-label-modern mb-0">Per page:</label>
                            <select class="form-control form-control-modern" id="handover-per-page" style="width: 100px; height: 40px !important;">
                                <option value="6">6</option>
                                <option value="12" selected>12</option>
                                <option value="24">24</option>
                                <option value="48">48</option>
                            </select>
                        </div>
                        <nav aria-label="Handover pagination">
                            <ul class="pagination pagination-sm mb-0" id="handover-pagination-list">
                                <!-- Pagination items populated dynamically -->
                            </ul>
                        </nav>
                    </div>
                </div>

                <!-- Detail View Pane (Inline, eliminates modal-on-modal stacking) -->
                <div id="handover-detail-view" class="handover-detail-view" style="display: none;">
                    <div id="handover-inline-detail-content">
                        <!-- Dynamic handover details loaded here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-secondary" id="footer-back-to-handovers-btn" style="display: none;">
                        <i class="mdi mdi-arrow-left"></i> Back to Handovers
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success" id="acknowledge-handover-inline-btn" style="display: none;">
                        <i class="mdi mdi-check-circle"></i> Acknowledge Handover
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Standalone Handover Detail Modal (Fallback for direct links) -->
<div class="modal fade" id="handoverDetailModal" tabindex="-1" aria-labelledby="handoverDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-handover-wide">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="handoverDetailModalLabel">
                    <i class="mdi mdi-file-document"></i> Handover Details
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="handover-detail-content">
                <!-- Dynamic content loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="acknowledge-handover-detail-btn" style="display: none;">
                    <i class="mdi mdi-check-circle"></i> Acknowledge
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Shift Summary Modal -->
<div class="modal fade" id="shiftSummaryModal" tabindex="-1" aria-labelledby="shiftSummaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="shiftSummaryModalLabel">
                    <i class="mdi mdi-chart-bar"></i> Current Shift Summary
                </h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="shift-summary-content">
                <!-- Dynamic content loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Last Office Modal --}}
    <div class="modal fade" id="lastOfficeModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="mdi mdi-emoticon-dead"></i> Complete Last Office</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="last-office-form">
                        <input type="hidden" id="last-office-record-id">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Patient</label>
                            <div id="last-office-patient-name" class="form-control-plaintext fw-bold text-primary"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Disposition <span class="text-danger">*</span></label>
                            <select class="form-select" id="last-office-disposition" required>
                                <option value="">-- Select --</option>
                                <option value="morgue">Send to Morgue</option>
                                <option value="release">Release to Family</option>
                            </select>
                            <small class="text-muted">If "Send to Morgue" is selected, the body will appear in the Morgue Workbench.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nurse's Last Office Notes</label>
                            <textarea class="form-control" id="last-office-notes" rows="4" placeholder="Record details of last office procedure..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-dark" id="btn-submit-last-office">
                        <i class="mdi mdi-check-circle"></i> Complete Procedure
                    </button>
                </div>
            </div>
        </div>
    </div>

<!-- Include Clinical Context Modal -->
@include('admin.partials.clinical_context_modal')
@include('admin.partials.treatment-plan-viewer-modal')

@include('admin.partials.re-prescribe-encounter-modal')
@include('admin.partials.invest_res_modal', ['save_route' => 'lab.saveResult'])
@include('admin.partials.invest_res_view_imaging_modal')
@include('admin.partials.invest_res_view_imaging_js')
@include('admin.partials.patient-form-modal')
@include('admin.partials.store_context_override_modal')
@include('admin.partials.bundle_view_modal')
@include('admin.partials.bundle_remove_modal')


