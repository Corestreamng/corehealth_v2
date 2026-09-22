{{-- Procedures - Tabbed History and New Booking Request --}}
<div class="card-modern mt-2 tp-context-borderable proc-tab-card">
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
                <div class="proc-search-container mb-3 position-relative">
                    <label for="procedure_search" class="form-label fw-bold small text-muted">
                        <i class="fa fa-search text-muted"></i> Search Procedure Catalog
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa fa-search"></i></span>
                        <input type="text" class="form-control form-control-lg border-start-0 ps-0" id="procedure_search"
                            placeholder="Type procedure name, code, or indication..." autocomplete="off">
                    </div>
                    <ul class="list-group proc-search-dropdown shadow" id="procedure_search_results" style="display: none;"></ul>
                </div>

                {{-- Empty State Placeholder (Visible when no procedure is selected to maintain comfortable card height) --}}
                <div id="proc_empty_placeholder" class="proc-empty-state text-center py-5 px-3 my-3 border rounded-3 bg-light-subtle">
                    <div class="mb-3 text-secondary opacity-50">
                        <i class="fa fa-stethoscope fa-3x"></i>
                    </div>
                    <h6 class="fw-bold text-dark">No Procedure Selected</h6>
                    <p class="small text-muted mb-0 mx-auto" style="max-width: 520px;">
                        Search for a procedure above to schedule the session, set clinical indications, and configure procedure base fee billing or defer billing to the Procedure Workbench.
                    </p>
                </div>

                {{-- Procedure Booking Configurator Card --}}
                <div id="proc_config_card" class="proc-config-card mb-4 shadow-sm" style="display: none;">
                    {{-- 1. Streamlined Header with Live Benchmark Pill --}}
                    <div class="proc-config-header p-3 bg-light border-bottom d-flex justify-content-between align-items-center rounded-top">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-primary text-uppercase" id="proc_config_category">Procedure</span>
                            <span id="proc_config_surgical_badge" class="badge bg-danger" style="display: none;"><i class="fa fa-cut me-1"></i> Surgical (OR)</span>
                            <span id="proc_config_clinical_badge" class="badge bg-info text-dark" style="display: none;"><i class="fa fa-stethoscope me-1"></i> Bedside / Minor</span>
                            <h5 class="mb-0 fw-bold text-dark" id="proc_config_title">Selected Procedure</h5>
                            <small class="text-muted fw-normal" id="proc_config_code"></small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            {{-- Compact Live Tariff Benchmark Pill --}}
                            <div class="proc-benchmark-pill d-flex align-items-center gap-2 px-2 py-1 bg-white border rounded small">
                                <span class="text-muted"><i class="fa fa-tag text-secondary"></i> Catalog: <strong class="text-dark" id="proc_bench_catalog">₦0.00</strong></span>
                                <span id="proc_bench_hmo_box" style="display: none;" class="border-start ps-2">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle" id="proc_bench_mode">HMO</span>
                                    <span class="text-muted ms-1">Patient: <strong class="text-dark" id="proc_bench_payable">₦0.00</strong> | Claims: <strong class="text-success" id="proc_bench_claims">₦0.00</strong></span>
                                </span>
                                <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2 ms-1" onclick="window.EncounterProcedures.setProcPreset('tariff')" title="Reset to standard tariff benchmark">
                                    <i class="fa fa-sync-alt"></i> Benchmark
                                </button>
                            </div>
                            <button type="button" class="btn-close ms-2" aria-label="Close" onclick="window.EncounterProcedures.cancelProcedureBookingConfig()"></button>
                        </div>
                    </div>

                    <div class="p-3">
                        {{-- 2. Clean Procedure Base Fee & Pricing Section --}}
                        @if(appsettings('allow_doctor_set_procedure_price'))
                            <div class="proc-pricing-panel mb-3 p-3 rounded border">
                                {{-- Pricing Header with Defer Switch --}}
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-success fw-bold"><i class="fa fa-money-bill-wave"></i> Procedure Base Fee</span>
                                        <small class="text-muted">(Set clinical procedure fee & coverage)</small>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="defer_proc_billing" style="cursor: pointer;">
                                        <label class="form-check-label fw-bold text-primary small" for="defer_proc_billing" style="cursor: pointer;">
                                            <i class="fa fa-clock"></i> Defer Billing (Bill Later in Workbench)
                                        </label>
                                    </div>
                                </div>

                                {{-- Deferred Banner --}}
                                <div id="proc_deferred_alert" class="alert alert-warning py-2 px-3 mb-0 small rounded" style="display: none;">
                                    <i class="fa fa-info-circle me-1"></i>
                                    <strong>Base fee billing deferred:</strong> The procedure will be booked without generating an immediate bill. The surgeon or clinician can set pricing and bill from the Procedure Workbench later.
                                </div>

                                {{-- Unified Pricing Fields --}}
                                <div id="proc_billing_fields_container">
                                    <div class="row g-2 align-items-center mb-2">
                                        {{-- Total Fee --}}
                                        <div class="col-md-4">
                                            <label for="proc_total_price" class="form-label small fw-bold mb-1">
                                                Total Procedure Fee (₦) <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light fw-bold">₦</span>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm fw-bold" id="proc_total_price" placeholder="0.00">
                                            </div>
                                        </div>

                                        {{-- Coverage Mode --}}
                                        <div class="col-md-4">
                                            <label for="proc_coverage_mode" class="form-label small fw-bold mb-1">Coverage Mode</label>
                                            <select class="form-select form-select-sm" id="proc_coverage_mode">
                                                <option value="cash">Self-Pay (Cash / Direct)</option>
                                                <option value="express">HMO: Express (Auto-Approved)</option>
                                                <option value="primary">HMO: Primary (Pre-Auth Required)</option>
                                                <option value="secondary">HMO: Secondary (Specialist Auth)</option>
                                            </select>
                                        </div>

                                        {{-- Pre-Auth Code (visible for HMO Primary/Secondary) --}}
                                        <div class="col-md-4" id="proc_auth_code_container" style="display: none;">
                                            <label for="proc_auth_code" class="form-label small fw-bold mb-1">Pre-Auth Code</label>
                                            <input type="text" class="form-control form-control-sm" id="proc_auth_code" placeholder="AUTH-1234">
                                        </div>
                                    </div>

                                    {{-- Split Row for HMO or Co-Pay --}}
                                    <div id="proc_split_breakdown" class="bg-light p-2 rounded border mt-2">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-4">
                                                <label for="proc_payable_amount" class="form-label small text-muted mb-0 fw-semibold">Patient Payable (₦)</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="proc_payable_amount" placeholder="0.00">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="proc_claims_amount" class="form-label small text-muted mb-0 fw-semibold">HMO Claims (₦)</label>
                                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="proc_claims_amount" placeholder="0.00">
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <div class="btn-group btn-group-sm w-100 mt-3" role="group">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.EncounterProcedures.setProcPreset('patient')" title="100% Patient Payable">100% Patient</button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.EncounterProcedures.setProcPreset('hmo')" title="100% HMO Claims">100% HMO</button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.EncounterProcedures.setProcPreset('split')" title="50/50 Co-Pay">50/50</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <input type="hidden" id="proc_total_price" value="0">
                            <input type="hidden" id="proc_coverage_mode" value="cash">
                            <input type="hidden" id="proc_payable_amount" value="0">
                            <input type="hidden" id="proc_claims_amount" value="0">
                            <input type="hidden" id="defer_proc_billing" value="0">
                        @endif

                        {{-- 3. Clinical Scheduling & Notes (Clean 2-Column Layout) --}}
                        <div class="row g-3 mb-3">
                            {{-- Left: Scheduling --}}
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100 bg-light-subtle">
                                    <div class="fw-bold small text-secondary mb-2"><i class="fa fa-calendar-check text-primary me-1"></i> Scheduling & Priority</div>
                                    <div class="row g-2">
                                        <div class="col-12 mb-2">
                                            <label for="proc_priority" class="form-label small fw-bold mb-1">Priority</label>
                                            <select class="form-select form-select-sm" id="proc_priority">
                                                <option value="routine">Routine</option>
                                                <option value="urgent">Urgent</option>
                                                <option value="emergency">Emergency</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label for="proc_scheduled_date" class="form-label small fw-semibold mb-1">Date (Optional)</label>
                                            <input type="date" class="form-control form-control-sm" id="proc_scheduled_date">
                                        </div>
                                        <div class="col-6">
                                            <label for="proc_scheduled_time" class="form-label small fw-semibold mb-1">Time (Optional)</label>
                                            <input type="time" class="form-control form-control-sm" id="proc_scheduled_time">
                                        </div>
                                        <div class="col-12 mt-2">
                                            <label for="proc_operating_room" class="form-label small fw-semibold mb-1" id="proc_operating_room_label">Theatre / Room (Optional)</label>
                                            <input type="text" class="form-control form-control-sm" id="proc_operating_room" placeholder="e.g. Main OR 1 / Minor Procedure Room">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Right: Clinical Indications --}}
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100 bg-light-subtle d-flex flex-column">
                                    <div class="fw-bold small text-secondary mb-2"><i class="fa fa-notes-medical text-info me-1"></i> Clinical Indications & Notes</div>
                                    <textarea class="form-control form-control-sm flex-grow-1" id="proc_pre_notes" rows="5"
                                        placeholder="Clinical indications, procedure notes, diagnostic findings, special patient instructions..."></textarea>
                                </div>
                            </div>
                        </div>

                        {{-- 4. Clinical Preparation & Readiness Protocol (Dynamic Surgical vs Bedside) --}}
                        {{-- 4A. Surgical Preparation Controls --}}
                        <div id="proc_surgical_prep_box" class="border border-danger-subtle bg-danger-subtle bg-opacity-10 rounded p-3 mb-3" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom border-danger-subtle">
                                <div class="fw-bold small text-danger">
                                    <i class="fa fa-cut me-1"></i> Surgical Preparation & Anesthesia Plan (Pre-Op)
                                </div>
                                <span class="badge bg-danger">Theatre Protocol</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label for="proc_npo_status" class="form-label small fw-bold mb-1">Fasting (NPO) Status</label>
                                    <select class="form-select form-select-sm" id="proc_npo_status">
                                        <option value="npo_midnight">NPO from Midnight (Standard)</option>
                                        <option value="6_hours_fast">Fasting 6h Pre-Op</option>
                                        <option value="clear_fluids_2h">Clear Fluids Up to 2h</option>
                                        <option value="emergency_none">Emergency (No Fasting)</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="proc_anesthesia_type" class="form-label small fw-bold mb-1">Anesthesia Plan</label>
                                    <select class="form-select form-select-sm" id="proc_anesthesia_type">
                                        <option value="general">General Anesthesia (GA)</option>
                                        <option value="spinal">Spinal / Subarachnoid Block</option>
                                        <option value="epidural">Epidural Anesthesia</option>
                                        <option value="regional_block">Regional / Nerve Block</option>
                                        <option value="sedation_local">Local Anesthesia + IV Sedation</option>
                                        <option value="local_only">Local Anesthesia Only</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="proc_surgical_consent" class="form-label small fw-bold mb-1">Surgical Consent</label>
                                    <select class="form-select form-select-sm" id="proc_surgical_consent">
                                        <option value="required">Required (Form to be Signed)</option>
                                        <option value="already_signed">Consent Form Signed & Attached</option>
                                        <option value="emergency_implied">Emergency Implied Consent</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-center pt-3">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" id="proc_blood_required" style="cursor: pointer;">
                                        <label class="form-check-label small fw-bold text-danger" for="proc_blood_required" style="cursor: pointer;">
                                            <i class="fa fa-tint me-1"></i> G&X / Blood on Standby
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12 mt-2">
                                    <input type="text" class="form-control form-control-sm" id="proc_surgical_prep_notes"
                                        placeholder="Pre-Op Instructions: e.g. Pre-medication, surgical site prep, prophylactic antibiotics, special implants/staplers...">
                                </div>
                            </div>
                        </div>

                        {{-- 4B. Bedside / Minor Clinical Preparation Controls --}}
                        <div id="proc_clinical_prep_box" class="border border-info-subtle bg-info-subtle bg-opacity-10 rounded p-3 mb-3" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom border-info-subtle">
                                <div class="fw-bold small text-info text-dark">
                                    <i class="fa fa-stethoscope me-1"></i> Bedside Preparation & Clinical Consumables
                                </div>
                                <span class="badge bg-info text-dark">Bedside Protocol</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label for="proc_clinical_pack" class="form-label small fw-bold mb-1">Procedure Pack / Kit</label>
                                    <select class="form-select form-select-sm" id="proc_clinical_pack">
                                        <option value="routine_pack">Standard Treatment Pack</option>
                                        <option value="sterile_dressing_kit">Sterile Dressing Kit</option>
                                        <option value="biopsy_pack">Biopsy Pack & Formalin Container</option>
                                        <option value="catheter_kit">Catheterization Kit</option>
                                        <option value="suture_pack">Suture Pack & Instrument Tray</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="proc_clinical_consent" class="form-label small fw-bold mb-1">Informed Consent</label>
                                    <select class="form-select form-select-sm" id="proc_clinical_consent">
                                        <option value="routine_explained">Routine Clinical Explanation Given</option>
                                        <option value="written_form">Written Minor Consent Form</option>
                                        <option value="not_required">Not Required</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="proc_observation_plan" class="form-label small fw-bold mb-1">Post-Procedure Observation</label>
                                    <select class="form-select form-select-sm" id="proc_observation_plan">
                                        <option value="immediate">Immediate Outpatient Discharge</option>
                                        <option value="30_min">30 Minutes Bedside Observation</option>
                                        <option value="extended">Extended Observation / Ward Transfer</option>
                                    </select>
                                </div>
                                <div class="col-12 mt-2">
                                    <input type="text" class="form-control form-control-sm" id="proc_clinical_prep_notes"
                                        placeholder="Specific consumables or wound instructions (e.g. Chlorhexidine scrub, 1% Lignocaine local, Aquacel dressing)...">
                                </div>
                            </div>
                        </div>

                        {{-- 4. Footer Action Bar --}}
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <div class="text-muted small" id="proc_summary_text">
                                Ready to add procedure to encounter
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="window.EncounterProcedures.cancelProcedureBookingConfig()">
                                    <i class="fa fa-times"></i> Cancel
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" id="add_proc_btn" onclick="window.EncounterProcedures.addConfiguredProcedure()">
                                    <i class="fa fa-plus-circle"></i> Add Procedure to Encounter
                                </button>
                            </div>
                        </div>
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
