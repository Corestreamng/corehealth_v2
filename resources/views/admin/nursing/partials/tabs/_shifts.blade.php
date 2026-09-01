<div class="tab-pane fade" id="nr-shifts" role="tabpanel">
                        <div class="p-3">
                            <!-- Shift Summary -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-primary">
                                            <i class="mdi mdi-clock-check"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-shifts-total">0</h4>
                                            <p>Shifts Completed</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-info">
                                            <i class="mdi mdi-timer"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-shifts-avg-duration">0h</h4>
                                            <p>Avg Duration</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-success">
                                            <i class="mdi mdi-swap-horizontal"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-shifts-handovers">0%</h4>
                                            <p>Handover Rate</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-warning">
                                            <i class="mdi mdi-clock-alert"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-shifts-overdue">0</h4>
                                            <p>Overdue Shifts</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Shifts DataTable -->
                            <div class="card-modern">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-table"></i> Shift History</h6>
                                    <button class="btn btn-sm btn-success" id="nr-export-shifts">
                                        <i class="mdi mdi-file-excel"></i> Export
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="nr-shifts-table" style="width: 100%">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Nurse</th>
                                                    <th>Shift Type</th>
                                                    <th>Ward</th>
                                                    <th>Start</th>
                                                    <th>End</th>
                                                    <th>Duration</th>
                                                    <th>Actions</th>
                                                    <th>Handover</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>