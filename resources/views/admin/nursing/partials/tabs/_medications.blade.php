<div class="tab-pane fade" id="nr-medications" role="tabpanel">
                        <div class="p-3">
                            <!-- Medication Summary -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-primary">
                                            <i class="mdi mdi-pill"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-meds-total">0</h4>
                                            <p>Total Administered</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-success">
                                            <i class="mdi mdi-check-circle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-meds-ontime">0%</h4>
                                            <p>On-Time Rate</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-warning">
                                            <i class="mdi mdi-clock-alert"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-meds-late">0</h4>
                                            <p>Late Administrations</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-danger">
                                            <i class="mdi mdi-close-circle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-meds-missed">0</h4>
                                            <p>Missed Doses</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Medications DataTable -->
                            <div class="card-modern">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-table"></i> Medication Administration Log</h6>
                                    <button class="btn btn-sm btn-success" id="nr-export-meds">
                                        <i class="mdi mdi-file-excel"></i> Export
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="nr-medications-table" style="width: 100%">
                                            <thead>
                                                <tr>
                                                    <th>Date/Time</th>
                                                    <th>Patient</th>
                                                    <th>Medication</th>
                                                    <th>Dose</th>
                                                    <th>Route</th>
                                                    <th>Scheduled</th>
                                                    <th>Administered By</th>
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