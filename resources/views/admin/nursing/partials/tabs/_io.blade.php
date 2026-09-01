<div class="tab-pane fade" id="nr-io" role="tabpanel">
                        <div class="p-3">
                            <!-- I/O Summary -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-info">
                                            <i class="mdi mdi-water"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-io-records">0</h4>
                                            <p>Total Records</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-success">
                                            <i class="mdi mdi-plus-circle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-io-positive">0</h4>
                                            <p>Positive Balance</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-danger">
                                            <i class="mdi mdi-minus-circle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-io-negative">0</h4>
                                            <p>Negative Balance</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-warning">
                                            <i class="mdi mdi-alert"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-io-critical">0</h4>
                                            <p>Critical Imbalance</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- I/O DataTable -->
                            <div class="card-modern">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-table"></i> Intake/Output Records</h6>
                                    <button class="btn btn-sm btn-success" id="nr-export-io">
                                        <i class="mdi mdi-file-excel"></i> Export
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="nr-io-table" style="width: 100%">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Patient</th>
                                                    <th>Ward/Bed</th>
                                                    <th>Total Intake</th>
                                                    <th>Total Output</th>
                                                    <th>Balance</th>
                                                    <th>Status</th>
                                                    <th>Recorded By</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>