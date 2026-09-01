<div class="tab-content" id="clinical-requests-sub-content">

                        <!-- ===== PRESCRIPTIONS SUB-TAB ===== -->
                        <div class="tab-pane fade show active" id="cr-prescriptions" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-body">
                                    {{-- Treatment Plans + Re-prescribe buttons (Plan §6.4, §5.3) --}}
                                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                                        <div class="btn-group">

                                            <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                                <i class="fa fa-save"></i> Save as Template
                                            </button>
                                        </div>
                                        {{-- Re-prescribe from previous encounter dropdown (Plan §5.3) --}}
                                        <div class="dropdown" id="cr-rp-encounter-dropdown">
                                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                                <i class="fa fa-redo"></i> Re-prescribe from Encounter
                                            </button>
                                            <ul class="dropdown-menu rp-encounter-menu" style="min-width: 320px; max-height: 300px; overflow-y: auto;">
                                                <li class="dropdown-item text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cr-presc-history" type="button"><i class="fa fa-history"></i> Drug History</button></li>
                                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cr-presc-new" type="button"><i class="fa fa-plus-circle"></i> Add Prescription</button></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="cr-presc-history" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-hover" style="width:100%" id="cr_presc_history_list">
                                                    <thead class="table-light"><th style="width:100%"><i class="mdi mdi-pill"></i> Prescriptions</th></thead>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="cr-presc-new" role="tabpanel">
                                            <div id="cr_presc_message" class="mb-2"></div>
                                            <div class="alert alert-light py-2 px-3 mb-3 small" style="border-left: 4px solid #17a2b8;">
                                                <i class="mdi mdi-information-outline text-info"></i>
                                                <strong>Note:</strong> Prescriptions added here are sent to the <strong>Pharmacy</strong> for dispensing and billing.
                                                For quick/direct billing, use the <strong>Billing &rarr; Consumables</strong> tab instead.
                                            </div>
                                            <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Prescription</h6>

                                            {{-- Dose Mode Toggle — Segmented button group (Plan §2.2, structured default) --}}
                                            @include('admin.partials.dose-mode-toggle', ['prefix' => 'cr_'])

                                            <div class="form-group">
                                                <label>Search drugs/products</label>
                                                <input type="text" class="form-control" id="cr_presc_search"
                                                    placeholder="Type to search products..." autocomplete="off">
                                                <ul class="list-group co-search-dropdown" id="cr_presc_results"></ul>
                                            </div>
                                            <div class="table-responsive mt-3">
                                                <table class="table table-sm table-bordered table-striped">
                                                    <thead><th>Drug / Product</th><th>Price</th><th>Dose / Frequency</th><th style="width:40px;"><i class="fa fa-trash-alt text-muted" title="Remove"></i></th></thead>
                                                    <tbody id="cr-selected-products"></tbody>
                                                </table>
                                            </div>
                                            {{-- Save button removed — prescriptions auto-save on add (Plan §4.5) --}}
                                            <div id="cr_presc_message" class="mt-2"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== NON-PHARM / CARE PLAN SUB-TAB ===== -->
                        <div class="tab-pane fade" id="cr-non-pharm" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-body">
                                    <div id="cr-non-pharm-container"></div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== LAB REQUESTS SUB-TAB ===== -->
                        <div class="tab-pane fade" id="cr-lab" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-body">
                                    {{-- Treatment Plans + Save as Template (Plan §6.4: buttons at top of all 4 tab areas) --}}
                                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                                        <div class="btn-group">

                                            <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                                <i class="fa fa-save"></i> Save as Template
                                            </button>
                                        </div>
                                    </div>

                                    <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cr-lab-history" type="button"><i class="fa fa-history"></i> Lab History</button></li>
                                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cr-lab-new" type="button"><i class="fa fa-plus-circle"></i> New Lab Request</button></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="cr-lab-history" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-hover" style="width:100%" id="cr_lab_history_list">
                                                    <thead class="table-light"><th style="width:100%"><i class="mdi mdi-flask"></i> Lab Requests</th></thead>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="cr-lab-new" role="tabpanel">
                                            <div id="cr_lab_message" class="mb-2"></div>
                                            <div class="alert alert-light py-2 px-3 mb-3 small" style="border-left: 4px solid #17a2b8;">
                                                <i class="mdi mdi-information-outline text-info"></i>
                                                <strong>Note:</strong> Lab requests added here are sent to the <strong>Laboratory</strong> for processing and billing.
                                                For quick/direct billing, use the <strong>Billing &rarr; Labs</strong> tab instead.
                                            </div>
                                            <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Lab Request</h6>
                                            <div class="form-group">
                                                <label>Search lab services</label>
                                                <input type="text" class="form-control" id="cr_lab_search"
                                                    placeholder="Type to search lab services..." autocomplete="off">
                                                <ul class="list-group co-search-dropdown" id="cr_lab_results"></ul>
                                            </div>
                                            <div class="table-responsive mt-3">
                                                <table class="table table-sm table-bordered table-striped">
                                                    <thead><th>Lab Test</th><th>Price</th><th>Clinical Notes</th><th style="width:40px;"><i class="fa fa-trash-alt text-muted" title="Remove"></i></th></thead>
                                                    <tbody id="cr-selected-labs"></tbody>
                                                </table>
                                            </div>
                                            {{-- Phase 2d (Plan §4.5): Auto-save status — labs save on add --}}
                                            <div class="auto-save-status text-muted small mt-2" id="cr-labs-auto-save-status"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== IMAGING SUB-TAB ===== -->
                        <div class="tab-pane fade" id="cr-imaging" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-body">
                                    {{-- Treatment Plans + Save as Template (Plan §6.4: buttons at top of all 4 tab areas) --}}
                                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                                        <div class="btn-group">

                                            <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                                <i class="fa fa-save"></i> Save as Template
                                            </button>
                                        </div>
                                    </div>

                                    <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cr-imaging-history" type="button"><i class="fa fa-history"></i> Imaging History</button></li>
                                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cr-imaging-new" type="button"><i class="fa fa-plus-circle"></i> New Imaging Request</button></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="cr-imaging-history" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-hover" style="width:100%" id="cr_imaging_history_list">
                                                    <thead class="table-light"><th style="width:100%"><i class="mdi mdi-radioactive"></i> Imaging Requests</th></thead>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="cr-imaging-new" role="tabpanel">
                                            <div id="cr_imaging_message" class="mb-2"></div>
                                            <div class="alert alert-light py-2 px-3 mb-3 small" style="border-left: 4px solid #17a2b8;">
                                                <i class="mdi mdi-information-outline text-info"></i>
                                                <strong>Note:</strong> Imaging requests added here are sent to the <strong>Imaging</strong> department for processing and billing.
                                                For quick/direct billing, use the <strong>Billing &rarr; Imaging</strong> tab instead.
                                            </div>
                                            <h6 class="mb-3"><i class="fa fa-plus-circle"></i> New Imaging Request</h6>
                                            <div class="form-group">
                                                <label>Search imaging services</label>
                                                <input type="text" class="form-control" id="cr_imaging_search"
                                                    placeholder="Type to search imaging services..." autocomplete="off">
                                                <ul class="list-group co-search-dropdown" id="cr_imaging_results"></ul>
                                            </div>
                                            <div class="table-responsive mt-3">
                                                <table class="table table-sm table-bordered table-striped">
                                                    <thead><th>Imaging Study</th><th>Price</th><th>Clinical Notes</th><th style="width:40px;"><i class="fa fa-trash-alt text-muted" title="Remove"></i></th></thead>
                                                    <tbody id="cr-selected-imaging"></tbody>
                                                </table>
                                            </div>
                                            {{-- Phase 2d (Plan §4.5): Auto-save status — imaging saves on add --}}
                                            <div class="auto-save-status text-muted small mt-2" id="cr-imaging-auto-save-status"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ===== PROCEDURES SUB-TAB ===== -->
                        <div class="tab-pane fade" id="cr-procedures" role="tabpanel">
                            <div class="card-modern">
                                <div class="card-body">
                                    {{-- Treatment Plans + Save as Template (Plan §6.4: buttons at top of all 4 tab areas) --}}
                                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                                        <div class="btn-group">

                                            <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                                                <i class="fa fa-save"></i> Save as Template
                                            </button>
                                        </div>
                                    </div>

                                    <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
                                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cr-proc-history" type="button"><i class="fa fa-history"></i> Procedure History</button></li>
                                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cr-proc-new" type="button"><i class="fa fa-plus-circle"></i> Request Procedure</button></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="cr-proc-history" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-hover" style="width:100%" id="cr_proc_history_list">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width: 100%;"><i class="mdi mdi-medical-bag"></i> Procedure Requests</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody></tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="cr-proc-new" role="tabpanel">
                                            <div id="cr_proc_message" class="mb-2"></div>
                                            <h6 class="mb-3"><i class="fa fa-plus-circle"></i> Request New Procedure</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label><i class="fa fa-search"></i> Search Procedure</label>
                                                        <input type="text" class="form-control" id="cr_proc_search"
                                                            placeholder="Search procedures..." autocomplete="off">
                                                        <ul class="list-group co-search-dropdown" id="cr_proc_results"></ul>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group mb-3">
                                                        <label><i class="fa fa-exclamation-triangle"></i> Priority</label>
                                                        <select class="form-control" id="cr_proc_priority">
                                                            <option value="routine">Routine</option>
                                                            <option value="urgent">Urgent</option>
                                                            <option value="emergency">Emergency</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group mb-3">
                                                        <label><i class="fa fa-calendar"></i> Scheduled Date</label>
                                                        <input type="date" class="form-control" id="cr_proc_scheduled_date">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group mb-3">
                                                <label><i class="fa fa-sticky-note"></i> Pre-Procedure Notes</label>
                                                <textarea class="form-control" id="cr_proc_notes" rows="2" placeholder="Clinical notes, indications..."></textarea>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered table-striped">
                                                    <thead><tr><th>Procedure</th><th>Price</th><th>Priority</th><th style="width:40px;"><i class="fa fa-trash-alt text-muted" title="Remove"></i></th></tr></thead>
                                                    <tbody id="cr-selected-procedures"></tbody>
                                                </table>
                                            </div>
                                            {{-- Save button removed — procedures auto-save on add (Plan §4.5) --}}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>