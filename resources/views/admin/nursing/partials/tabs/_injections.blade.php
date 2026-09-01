<div class="tab-pane fade" id="nr-injections" role="tabpanel">
                        <div class="p-3">
                            <!-- Sub-tabs for Injections and Immunizations -->
                            <ul class="nav nav-pills mb-3" id="nr-inj-subtabs">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="pill" href="#nr-inj-list">
                                        <i class="mdi mdi-needle"></i> Injections
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="pill" href="#nr-imm-list">
                                        <i class="mdi mdi-shield-check"></i> Immunizations
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <!-- Injections List -->
                                <div class="tab-pane fade show active" id="nr-inj-list">
                                    <div class="card-modern">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><i class="mdi mdi-needle"></i> Injection Records</h6>
                                            <button class="btn btn-sm btn-success" id="nr-export-injections">
                                                <i class="mdi mdi-file-excel"></i> Export
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover" id="nr-injections-table" style="width: 100%">
                                                    <thead>
                                                        <tr>
                                                            <th>Date/Time</th>
                                                            <th>Patient</th>
                                                            <th>Drug</th>
                                                            <th>Dose</th>
                                                            <th>Route</th>
                                                            <th>Site</th>
                                                            <th>Batch No</th>
                                                            <th>Administered By</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Immunizations List -->
                                <div class="tab-pane fade" id="nr-imm-list">
                                    <div class="card-modern">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><i class="mdi mdi-shield-check"></i> Immunization Records</h6>
                                            <button class="btn btn-sm btn-success" id="nr-export-immunizations">
                                                <i class="mdi mdi-file-excel"></i> Export
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover" id="nr-immunizations-table" style="width: 100%">
                                                    <thead>
                                                        <tr>
                                                            <th>Date/Time</th>
                                                            <th>Patient</th>
                                                            <th>Age</th>
                                                            <th>Vaccine</th>
                                                            <th>Dose #</th>
                                                            <th>Batch No</th>
                                                            <th>Manufacturer</th>
                                                            <th>Administered By</th>
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