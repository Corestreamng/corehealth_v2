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

                {{-- Reusable Procedure Booking Configurator Card --}}
                @include('admin.partials.clinical_procedure_booking_card', [
                    'prefix' => 'proc_',
                    'cancelHandler' => 'window.EncounterProcedures.cancelProcedureBookingConfig()',
                    'submitHandler' => 'window.EncounterProcedures.addConfiguredProcedure()',
                    'submitLabel' => 'Add Procedure to Encounter'
                ])


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
