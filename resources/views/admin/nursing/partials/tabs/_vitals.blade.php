<div class="tab-pane fade" id="nr-vitals" role="tabpanel">
                        <div class="p-3">
                            <!-- Vitals Summary Cards -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-primary">
                                            <i class="mdi mdi-heart-pulse"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-vitals-total">0</h4>
                                            <p>Total Records</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-danger">
                                            <i class="mdi mdi-alert-circle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-vitals-abnormal">0</h4>
                                            <p>Abnormal Readings</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-warning">
                                            <i class="mdi mdi-thermometer-alert"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-vitals-fever">0</h4>
                                            <p>Fever Cases</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-info">
                                            <i class="mdi mdi-blood-bag"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-vitals-hypertension">0</h4>
                                            <p>High BP Cases</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Vitals DataTable -->
                            <div class="card-modern">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-table"></i> Vitals Records</h6>
                                    <button class="btn btn-sm btn-success" id="nr-export-vitals">
                                        <i class="mdi mdi-file-excel"></i> Export
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="nr-vitals-table" style="width: 100%">
                                            <thead>
                                                <tr>
                                                    <th>Date/Time</th>
                                                    <th>Patient</th>
                                                    <th>Ward/Bed</th>
                                                    <th>BP</th>
                                                    <th>HR</th>
                                                    <th>Temp</th>
                                                    <th>RR</th>
                                                    <th>SpO2</th>
                                                    <th>Recorded By</th>
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