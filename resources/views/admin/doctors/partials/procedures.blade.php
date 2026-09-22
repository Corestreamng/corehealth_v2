{{-- Procedures - Tabbed History and New Booking Request --}}
<div class="card-modern mt-2 tp-context-borderable">
    <div class="card-body">
        {{-- Active Plan Context Bar (Phase 9) --}}
        @include('admin.partials.active_plan_context_bar')

        {{-- Treatment Plans + Save as Template --}}
        <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
            <div class="btn-group">
                <button class="btn btn-sm btn-outline-success" onclick="ClinicalOrdersKit.openSaveTemplateModal()">
                    <i class="fa fa-save"></i> Save as Template
                </button>
            </div>
        </div>

        {{-- Sub-tabs for History and New Request --}}
        <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="proc-history-tab" data-bs-toggle="tab" data-bs-target="#proc-history" type="button" role="tab">
                    <i class="fa fa-history"></i> Procedure History
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="proc-new-tab" data-bs-toggle="tab" data-bs-target="#proc-new" type="button" role="tab">
                    <i class="fa fa-plus-circle"></i> Request / Book Procedure
                </button>
            </li>
        </ul>

        <div class="tab-content">
            {{-- 1. History Tab --}}
            <div class="tab-pane fade show active tab-content-fade" id="proc-history" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="fa fa-user-md text-primary"></i> Patient Procedure History</h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.EncounterProcedures.initProcedureHistoryTable()">
                        <i class="fa fa-sync-alt"></i> Refresh History
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" style="width: 100%" id="procedure_history_list">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100%;"><i class="fa fa-user-md"></i> Procedure Requests</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            {{-- 2. New Procedure Request / Booking Tab --}}
            <div class="tab-pane fade tab-content-fade" id="proc-new" role="tabpanel">
                <div id="procedures_save_message" class="mb-2"></div>
                <h5 class="mb-3"><i class="fa fa-plus-circle text-success"></i> Book / Request Procedure</h5>

                {{-- Search Bar --}}
                <div class="form-group mb-3 position-relative">
                    <label for="procedure_search" class="form-label fw-bold">
                        <i class="fa fa-search text-muted"></i> Search Procedure Catalog
                    </label>
                    <input type="text" class="form-control form-control-lg" id="procedure_search"
                        placeholder="Type procedure name, code, or indication..." autocomplete="off">
                    <ul class="list-group proc-search-dropdown shadow" id="procedure_search_results" style="display: none;"></ul>
                </div>

                {{-- Procedure Booking Configurator Card --}}
                <div id="proc_config_card" class="proc-config-card p-3 mb-4" style="display: none;">
                    <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-3">
                        <div>
                            <span class="badge bg-primary text-uppercase me-2" id="proc_config_category">Procedures</span>
                            <span class="fs-5 fw-bold text-dark" id="proc_config_title">Selected Procedure</span>
                            <small class="text-muted ms-2" id="proc_config_code"></small>
                        </div>
                        <button type="button" class="btn btn-sm btn-close" aria-label="Close" onclick="window.EncounterProcedures.cancelProcedureBookingConfig()"></button>
                    </div>

                    {{-- Live Tariff / Price Benchmark Guide Banner --}}
                    <div class="proc-benchmark-box mb-3" id="proc_bench_box">
                        <div class="d-flex flex-wrap justify-content-between align-items-center">
                            <div>
                                <small class="text-muted d-block"><i class="fa fa-tag"></i> Standard Catalog Price</small>
                                <span class="fw-bold fs-6 text-dark" id="proc_bench_catalog">₦0.00</span>
                            </div>
                            <div id="proc_bench_hmo_box" style="display: none;">
                                <small class="text-success fw-bold d-block"><i class="fa fa-shield-alt"></i> HMO Tariff Guidance (<span id="proc_bench_mode">MODE</span>)</small>
                                <span><b>Patient:</b> <span id="proc_bench_payable">₦0.00</span> | <b>HMO Claims:</b> <span id="proc_bench_claims">₦0.00</span></span>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary proc-preset-btn" onclick="window.EncounterProcedures.setProcPreset('tariff')">
                                    <i class="fa fa-sync-alt"></i> Reset to Benchmark
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Pricing & Billing Section --}}
                    @if(appsettings('allow_doctor_set_procedure_price'))
                        <div class="card-modern bg-light p-3 mb-3 border">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="fw-bold mb-0 text-dark">
                                    <i class="fa fa-money-bill-wave text-success"></i> Procedure Billing & Doctor Pricing
                                </label>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="defer_proc_billing" style="cursor: pointer;">
                                    <label class="form-check-label fw-bold text-primary small" for="defer_proc_billing" style="cursor: pointer;">
                                        <i class="fa fa-clock"></i> Defer Base Fee Billing (Bill later in Workbench)
                                    </label>
                                </div>
                            </div>

                            <div id="proc_deferred_alert" class="alert alert-warning py-2 mb-0 small" style="display: none;">
                                <i class="fa fa-info-circle"></i> <b>Billing Deferred:</b> The procedure will be booked without creating an immediate bill. The surgeon or clinician can set pricing and bill from the Procedure Workbench later.
                            </div>

                            <div id="proc_billing_fields_container">
                                <div class="row g-2 align-items-end mb-2">
                                    <div class="col-md-3">
                                        <label for="proc_coverage_mode" class="form-label small fw-bold">Coverage Mode</label>
                                        <select class="form-select form-select-sm" id="proc_coverage_mode">
                                            <option value="cash">Self-Pay (Cash / Direct)</option>
                                            <option value="express">HMO: Express (Auto-Approved)</option>
                                            <option value="primary">HMO: Primary (Pre-Auth Required)</option>
                                            <option value="secondary">HMO: Secondary (Specialist Auth)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="proc_payable_amount" class="form-label small fw-bold">Patient Payable (₦)</label>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="proc_payable_amount" placeholder="0.00">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="proc_claims_amount" class="form-label small fw-bold">HMO Claims (₦)</label>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="proc_claims_amount" placeholder="0.00">
                                    </div>
                                    <div class="col-md-3" id="proc_auth_code_container" style="display: none;">
                                        <label for="proc_auth_code" class="form-label small fw-bold">Pre-Auth Code (Optional)</label>
                                        <input type="text" class="form-control form-control-sm" id="proc_auth_code" placeholder="AUTH-1234">
                                    </div>
                                </div>

                                <div class="d-flex gap-1 align-items-center">
                                    <small class="text-muted me-1">Quick Presets:</small>
                                    <button type="button" class="btn btn-light btn-sm proc-preset-btn border" onclick="window.EncounterProcedures.setProcPreset('patient')">100% Patient</button>
                                    <button type="button" class="btn btn-light btn-sm proc-preset-btn border" onclick="window.EncounterProcedures.setProcPreset('hmo')">100% HMO</button>
                                    <button type="button" class="btn btn-light btn-sm proc-preset-btn border" onclick="window.EncounterProcedures.setProcPreset('split')">50/50 Co-Pay</button>
                                </div>
                            </div>
                        </div>
                    @else
                        <input type="hidden" id="proc_coverage_mode" value="cash">
                        <input type="hidden" id="proc_payable_amount" value="0">
                        <input type="hidden" id="proc_claims_amount" value="0">
                        <input type="hidden" id="defer_proc_billing" value="0">
                    @endif

                    {{-- Clinical Scheduling & Indication Inputs --}}
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <label for="proc_priority" class="form-label small fw-bold">
                                <i class="fa fa-exclamation-triangle text-warning"></i> Priority
                            </label>
                            <select class="form-select form-select-sm" id="proc_priority">
                                <option value="routine">Routine</option>
                                <option value="urgent">Urgent</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="proc_scheduled_date" class="form-label small fw-bold">
                                <i class="fa fa-calendar-alt text-info"></i> Scheduled Date (Optional)
                            </label>
                            <input type="date" class="form-control form-control-sm" id="proc_scheduled_date">
                        </div>
                        <div class="col-md-3">
                            <label for="proc_scheduled_time" class="form-label small fw-bold">
                                <i class="fa fa-clock text-info"></i> Scheduled Time (Optional)
                            </label>
                            <input type="time" class="form-control form-control-sm" id="proc_scheduled_time">
                        </div>
                        <div class="col-md-3">
                            <label for="proc_operating_room" class="form-label small fw-bold">
                                <i class="fa fa-door-open text-secondary"></i> Theatre / Room (Optional)
                            </label>
                            <input type="text" class="form-control form-control-sm" id="proc_operating_room" placeholder="e.g. Theatre 1 / Minor OR">
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="proc_pre_notes" class="form-label small fw-bold">
                            <i class="fa fa-sticky-note text-info"></i> Pre-Procedure Clinical Notes & Indications
                        </label>
                        <textarea class="form-control form-control-sm" id="proc_pre_notes" rows="2"
                            placeholder="Clinical indications, instructions for theatre/prep, patient warnings..."></textarea>
                    </div>

                    {{-- Configurator Action Buttons --}}
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.EncounterProcedures.cancelProcedureBookingConfig()">
                            <i class="fa fa-times"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" id="add_proc_btn" onclick="window.EncounterProcedures.addConfiguredProcedure()">
                            <i class="fa fa-plus-circle"></i> Add Procedure to Encounter
                        </button>
                    </div>
                </div>

                {{-- Selected Procedures Table --}}
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Procedure</th>
                                <th>Category & Schedule</th>
                                <th>Price & Billing Breakdown</th>
                                <th style="width: 130px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="selected-procedures"></tbody>
                    </table>
                </div>

                <div id="no_procedures_message" class="alert alert-info mt-3 py-2 small">
                    <i class="fa fa-info-circle"></i> Search and select procedures above to book and add to this encounter.
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Procedure Details Modal --}}
<div class="modal fade" id="procedureDetailsModal" tabindex="-1" aria-labelledby="procedureDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title fs-6" id="procedureDetailsModalLabel"><i class="fa fa-user-md me-1"></i> Procedure Details</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="procedureDetailsContent">
                <div class="text-center py-4">
                    <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">Loading procedure details...</p>
                </div>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Procedure Team Modal --}}
<div class="modal fade" id="procedureTeamModal" tabindex="-1" aria-labelledby="procedureTeamModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title fs-6" id="procedureTeamModalLabel"><i class="fa fa-users me-1"></i> Surgical & Procedure Team</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{-- Add Team Member Form --}}
                <div class="card-modern mb-3 p-3 border">
                    <h6 class="fw-bold mb-2"><i class="fa fa-user-plus text-primary"></i> Assign Team Member</h6>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            @php
                                $staffUsers = \App\Models\User::with(['category', 'staff_profile.specialization'])
                                    ->where('status', 1)
                                    ->where('is_admin', '!=', 19)
                                    ->orderBy('surname')
                                    ->orderBy('firstname')
                                    ->get();
                            @endphp
                            <label for="team_member_user" class="form-label small fw-bold">Staff Member</label>
                            <select class="form-select form-select-sm" id="team_member_user">
                                <option value="">-- Select Staff ({{ $staffUsers->count() }}) --</option>
                                @foreach($staffUsers as $user)
                                    <option value="{{ $user->id }}">{{ trim(($user->surname ?? '') . ' ' . ($user->firstname ?? '') . ' ' . ($user->othername ?? '')) }} ({{ $user->category->name ?? 'Staff' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="team_member_role" class="form-label small fw-bold">Role</label>
                            <select class="form-select form-select-sm" id="team_member_role">
                                @foreach(\App\Models\ProcedureTeamMember::ROLES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end pb-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="team_member_is_lead">
                                <label class="form-check-label small fw-bold" for="team_member_is_lead">Lead</label>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-12">
                            <input type="text" class="form-control form-control-sm" id="team_member_notes" placeholder="Notes / specific duties...">
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.EncounterProcedures.addTeamMember()">
                        <i class="fa fa-plus"></i> Add Member
                    </button>
                </div>

                {{-- Team List --}}
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Staff Member</th>
                                <th>Role</th>
                                <th>Notes</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="procedure_team_list"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Procedure Notes Modal --}}
<div class="modal fade" id="procedureNotesModal" tabindex="-1" aria-labelledby="procedureNotesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning py-2">
                <h5 class="modal-title fs-6" id="procedureNotesModalLabel"><i class="fa fa-sticky-note me-1"></i> Procedure Clinical Notes</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{-- Add Note Form --}}
                <div class="card-modern mb-3 p-3 border">
                    <h6 class="fw-bold mb-2"><i class="fa fa-plus text-primary"></i> Add Clinical Note</h6>
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label for="proc_note_type" class="form-label small fw-bold">Note Type</label>
                            <select class="form-select form-select-sm" id="proc_note_type">
                                @foreach(\App\Models\ProcedureNote::NOTE_TYPES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="proc_note_title" class="form-label small fw-bold">Title</label>
                            <input type="text" class="form-control form-control-sm" id="proc_note_title" placeholder="e.g. Operative findings, prep note...">
                        </div>
                    </div>
                    <div class="mb-2">
                        <textarea class="form-control form-control-sm" id="proc_note_content" rows="3" placeholder="Enter detailed note..."></textarea>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.EncounterProcedures.addProcedureNote()">
                        <i class="fa fa-plus"></i> Save Note
                    </button>
                </div>

                {{-- Notes List --}}
                <div class="table-responsive">
                    <table class="table table-sm table-borderless">
                        <tbody id="procedure_notes_list"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
