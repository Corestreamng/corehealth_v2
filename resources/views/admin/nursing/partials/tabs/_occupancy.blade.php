<div class="tab-pane fade" id="nr-occupancy" role="tabpanel">
                        <div class="p-3">
                            <!-- Occupancy Summary -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-primary">
                                            <i class="mdi mdi-bed"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-beds-total">0</h4>
                                            <p>Total Beds</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-danger">
                                            <i class="mdi mdi-bed-empty"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-beds-occupied">0</h4>
                                            <p>Occupied</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-success">
                                            <i class="mdi mdi-check-circle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-beds-available">0</h4>
                                            <p>Available</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-warning">
                                            <i class="mdi mdi-wrench"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-beds-maintenance">0</h4>
                                            <p>Maintenance</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Admission/Discharge Stats -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-account-plus"></i> Admissions</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-around text-center">
                                                <div>
                                                    <h3 class="text-success" id="nr-admissions-today">0</h3>
                                                    <small>Today</small>
                                                </div>
                                                <div>
                                                    <h3 class="text-primary" id="nr-admissions-period">0</h3>
                                                    <small>This Period</small>
                                                </div>
                                                <div>
                                                    <h3 class="text-info" id="nr-avg-los">0d</h3>
                                                    <small>Avg LOS</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-account-minus"></i> Discharges</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-around text-center">
                                                <div>
                                                    <h3 class="text-success" id="nr-discharges-today">0</h3>
                                                    <small>Today</small>
                                                </div>
                                                <div>
                                                    <h3 class="text-primary" id="nr-discharges-period">0</h3>
                                                    <small>This Period</small>
                                                </div>
                                                <div>
                                                    <h3 class="text-warning" id="nr-pending-discharges">0</h3>
                                                    <small>Pending</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ward Breakdown Table -->
                            <div class="card-modern">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-hospital-building"></i> Ward Breakdown</h6>
                                    <button class="btn btn-sm btn-success" id="nr-export-occupancy">
                                        <i class="mdi mdi-file-excel"></i> Export
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="nr-occupancy-table" style="width: 100%">
                                            <thead>
                                                <tr>
                                                    <th>Ward</th>
                                                    <th>Total Beds</th>
                                                    <th>Occupied</th>
                                                    <th>Available</th>
                                                    <th>Maintenance</th>
                                                    <th>Occupancy %</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>