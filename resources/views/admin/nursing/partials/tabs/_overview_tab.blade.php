<div class="workspace-tab-content active" id="overview-tab">
                <div class="overview-container p-3">
                    <div id="patient-overview-content">
                        <!-- Patient Summary Row -->
                        <div class="row">
                            <!-- Patient Demographics Card -->
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card-modern h-100">
                                    <div class="card-header bg-primary text-white py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-account"></i> Patient Information</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div id="overview-patient-info">
                                            <p class="text-muted text-center py-3">Select a patient to view details</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Admission Status Card -->
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card-modern h-100">
                                    <div class="card-header bg-info text-white py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-bed"></i> Admission Status</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div id="overview-admission-info">
                                            <p class="text-muted text-center py-3">No admission data</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Vital Signs Card -->
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card-modern h-100">
                                    <div class="card-header bg-success text-white py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-heart-pulse"></i> Latest Vitals</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div id="overview-vitals-info">
                                            <p class="text-muted text-center py-3">No vitals recorded</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions Row -->
                        <div class="row">
                            <!-- Pending Medications -->
                            <div class="col-lg-6 mb-3">
                                <div class="card-modern h-100">
                                    <div class="card-header bg-warning py-2 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><i class="mdi mdi-pill"></i> Pending Medications</h6>
                                        <span class="badge badge-light" id="overview-pending-meds-count">0</span>
                                    </div>
                                    <div class="card-body p-2" style="max-height: 200px; overflow-y: auto;">
                                        <div id="overview-pending-meds">
                                            <p class="text-muted text-center py-2">No pending medications</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Today's Tasks -->
                            <div class="col-lg-6 mb-3">
                                <div class="card-modern h-100">
                                    <div class="card-header bg-secondary text-white py-2 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><i class="mdi mdi-clipboard-check"></i> Today's Tasks</h6>
                                        <span class="badge badge-light" id="overview-tasks-count">0</span>
                                    </div>
                                    <div class="card-body p-2" style="max-height: 200px; overflow-y: auto;">
                                        <div id="overview-tasks">
                                            <p class="text-muted text-center py-2">No tasks pending</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Latest Notes Row -->
                        <div class="row">
                            <!-- Latest Nurse Note -->
                            <div class="col-lg-6 mb-3">
                                <div class="card-modern h-100">
                                    <div class="card-header bg-purple text-white py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-note-text"></i> Latest Nurse Note</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div id="overview-nurse-note">
                                            <p class="text-muted text-center py-2">No nursing notes</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Latest Doctor Note -->
                            <div class="col-lg-6 mb-3">
                                <div class="card-modern h-100">
                                    <div class="card-header bg-dark text-white py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-stethoscope"></i> Latest Doctor Note</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div id="overview-doctor-note">
                                            <p class="text-muted text-center py-2">No doctor notes</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Allergies & Alerts Row -->
                        <div class="row">
                            <div class="col-12 mb-3">
                                <div class="card-modern border-danger">
                                    <div class="card-header bg-danger text-white py-2">
                                        <h6 class="mb-0"><i class="mdi mdi-alert-circle"></i> Allergies & Alerts</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <div id="overview-allergies">
                                            <p class="text-muted text-center py-2">No allergies or alerts recorded</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>