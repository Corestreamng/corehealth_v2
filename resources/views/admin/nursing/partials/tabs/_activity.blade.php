<div class="tab-pane fade show active" id="nr-activity" role="tabpanel">
                        <div class="p-3">
                            <!-- Stats Cards -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon bg-primary">
                                            <i class="mdi mdi-account-group"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-patients">0</h3>
                                            <p>Patients Served</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon bg-danger">
                                            <i class="mdi mdi-heart-pulse"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-vitals">0</h3>
                                            <p>Vitals Recorded</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon bg-warning">
                                            <i class="mdi mdi-pill"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-medications">0</h3>
                                            <p>Medications Given</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon bg-info">
                                            <i class="mdi mdi-needle"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-injections">0</h3>
                                            <p>Injections</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon bg-success">
                                            <i class="mdi mdi-shield-check"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-immunizations">0</h3>
                                            <p>Immunizations</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon bg-secondary">
                                            <i class="mdi mdi-note-text"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-notes">0</h3>
                                            <p>Notes Written</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon" style="background: #6f42c1;">
                                            <i class="mdi mdi-swap-horizontal"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-handovers">0</h3>
                                            <p>Handovers</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="nr-stat-card">
                                        <div class="nr-stat-icon" style="background: #e83e8c;">
                                            <i class="mdi mdi-clock-check"></i>
                                        </div>
                                        <div class="nr-stat-content">
                                            <h3 id="nr-stat-shifts">0</h3>
                                            <p>Shifts Completed</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Charts Row -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-8">
                                    <div class="card-modern h-100">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-chart-line"></i> Activity Trend</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="nr-activity-trend-chart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card-modern h-100">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-chart-pie"></i> Activity Distribution</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="nr-activity-distribution-chart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Top Performers & Peak Hours -->
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><i class="mdi mdi-trophy"></i> Top Performers</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0" id="nr-top-performers-table">
                                                    <thead>
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Nurse</th>
                                                            <th>Actions</th>
                                                            <th>Patients</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card-modern">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="mdi mdi-clock-outline"></i> Peak Activity Hours</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="nr-peak-hours-chart" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>