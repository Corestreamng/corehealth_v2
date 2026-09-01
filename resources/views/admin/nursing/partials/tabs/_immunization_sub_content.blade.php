<div class="tab-content" id="immunization-sub-content">
                        <!-- Schedule Sub-tab (Now Primary) -->
                        <div class="tab-pane fade show active" id="immunization-schedule" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-header py-2">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <h6 class="mb-0"><i class="mdi mdi-calendar-check"></i> Immunization Schedules</h6>
                                        <div class="d-flex align-items-center gap-2">
                                            <select class="form-control form-control-sm mr-2" id="schedule-template-select" style="width: 200px;">
                                                <option value="">Select Schedule Template...</option>
                                            </select>
                                            <button type="button" class="btn btn-sm btn-primary" id="btn-add-schedule" title="Add selected schedule to patient">
                                                <i class="mdi mdi-plus"></i> Add Schedule
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Active Schedules Summary -->
                                    <div class="mb-3" id="patient-active-schedules">
                                        <div class="alert alert-info py-2 mb-2">
                                            <i class="mdi mdi-information"></i> Select a patient to view their immunization schedules
                                        </div>
                                    </div>

                                    <!-- Schedule Legend -->
                                    <div class="mb-3 d-flex flex-wrap align-items-center">
                                        <span class="mr-3 small text-muted">Status:</span>
                                        <span class="badge badge-secondary mr-2"><i class="mdi mdi-clock-outline"></i> Pending</span>
                                        <span class="badge badge-warning mr-2"><i class="mdi mdi-alert"></i> Due Now</span>
                                        <span class="badge badge-danger mr-2"><i class="mdi mdi-alert-circle"></i> Overdue</span>
                                        <span class="badge badge-success mr-2"><i class="mdi mdi-check"></i> Administered</span>
                                        <span class="badge badge-info mr-2"><i class="mdi mdi-skip-next"></i> Skipped</span>
                                        <span class="badge badge-dark"><i class="mdi mdi-cancel"></i> Contraindicated</span>
                                    </div>

                                    <!-- Schedule Timeline Container -->
                                    <div id="immunization-schedule-container">
                                        <div class="text-center py-4">
                                            <i class="mdi mdi-calendar-clock mdi-48px text-muted"></i>
                                            <p class="text-muted mt-2">Select a patient to view their immunization schedules</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- History & Timeline Sub-tab -->
                        <div class="tab-pane fade" id="immunization-history" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-header py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><i class="mdi mdi-history"></i> Immunization History & Timeline</h6>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-primary active" id="view-timeline-btn" data-view="timeline">
                                                <i class="mdi mdi-chart-timeline-variant"></i> Timeline
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" id="view-calendar-btn" data-view="calendar">
                                                <i class="mdi mdi-calendar-month"></i> Calendar
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" id="view-table-btn" data-view="table">
                                                <i class="mdi mdi-table"></i> Table
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- History Views Container -->
                                    <div id="immunization-history-container">
                                        <!-- Timeline View (Default) -->
                                        <div class="history-view" id="history-timeline-view">
                                            <div class="text-center py-4">
                                                <i class="mdi mdi-chart-timeline-variant mdi-48px text-muted"></i>
                                                <p class="text-muted mt-2">Select a patient to view their immunization history</p>
                                            </div>
                                        </div>

                                        <!-- Calendar View -->
                                        <div class="history-view d-none" id="history-calendar-view">
                                            <div id="immunization-calendar"></div>
                                        </div>

                                        <!-- Table View -->
                                        <div class="history-view d-none" id="history-table-view">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover" id="immunization-history-table" style="width:100%">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Vaccine</th>
                                                            <th>Dose #</th>
                                                            <th>Dose Amount</th>
                                                            <th>Batch</th>
                                                            <th>Site</th>
                                                            <th>Nurse</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>