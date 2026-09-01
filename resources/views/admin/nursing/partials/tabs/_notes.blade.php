<div class="tab-pane fade" id="nr-notes" role="tabpanel">
                        <div class="p-3">
                            <!-- Notes Summary -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-primary">
                                            <i class="mdi mdi-note-text"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-notes-total">0</h4>
                                            <p>Total Notes</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-danger">
                                            <i class="mdi mdi-alert-circle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-notes-critical">0</h4>
                                            <p>Critical/Incident</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="nr-stat-card nr-stat-card-sm">
                                        <div class="nr-stat-icon bg-info">
                                            <i class="mdi mdi-account-group"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h4 id="nr-notes-patients">0</h4>
                                            <p>Patients Documented</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes DataTable -->
                            <div class="card-modern">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="mdi mdi-table"></i> Nursing Notes Log</h6>
                                    <button class="btn btn-sm btn-success" id="nr-export-notes">
                                        <i class="mdi mdi-file-excel"></i> Export
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="nr-notes-table" style="width: 100%">
                                            <thead>
                                                <tr>
                                                    <th>Date/Time</th>
                                                    <th>Patient</th>
                                                    <th>Note Type</th>
                                                    <th>Summary</th>
                                                    <th>Written By</th>
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