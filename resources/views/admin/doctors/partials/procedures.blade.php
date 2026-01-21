{{-- Procedures - Tabbed History and New Request --}}
<style>
    .service-tabs .nav-link {
        border-radius: 0;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    .service-tabs .nav-link:hover {
        background-color: #f8f9fa;
        transform: translateY(-2px);
    }
    .service-tabs .nav-link.active {
        background-color: {{ appsettings('hos_color', '#007bff') }};
        color: white !important;
        border-color: {{ appsettings('hos_color', '#007bff') }};
    }
    .tab-content-fade {
        animation: fadeIn 0.4s ease-in;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .priority-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .priority-routine { background-color: #d4edda; color: #155724; }
    .priority-urgent { background-color: #fff3cd; color: #856404; }
    .priority-emergency { background-color: #f8d7da; color: #721c24; }
    .status-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .status-requested { background-color: #e2e3e5; color: #383d41; }
    .status-scheduled { background-color: #cce5ff; color: #004085; }
    .status-in_progress { background-color: #fff3cd; color: #856404; }
    .status-completed { background-color: #d4edda; color: #155724; }
    .status-cancelled { background-color: #f8d7da; color: #721c24; }
</style>

<div class="card-modern mt-2">
    <div class="card-body">
        {{-- Sub-tabs for History and New Request --}}
        <ul class="nav nav-tabs service-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="proc-history-tab" data-bs-toggle="tab"
                    data-bs-target="#proc-history" type="button" role="tab">
                    <i class="fa fa-history"></i> Procedure History
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="proc-new-tab" data-bs-toggle="tab"
                    data-bs-target="#proc-new" type="button" role="tab">
                    <i class="fa fa-plus-circle"></i> Request Procedure
                </button>
            </li>
        </ul>

        <div class="tab-content">
            {{-- History Tab --}}
            <div class="tab-pane fade show active tab-content-fade" id="proc-history" role="tabpanel">
                <h5 class="mb-3"><i class="fa fa-user-md"></i> Procedure History</h5>
                <div class="table-responsive">
                    <table class="table table-hover" style="width: 100%" id="procedure_history_list">
                        <thead class="table-light">
                            <tr>
                                <th><i class="fa fa-user-md"></i> Procedure</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            {{-- New Procedure Request Tab --}}
            <div class="tab-pane fade tab-content-fade" id="proc-new" role="tabpanel">
                <h5 class="mb-3"><i class="fa fa-plus-circle"></i> Request New Procedure</h5>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="procedure_search"><i class="fa fa-search"></i> Search Procedure</label>
                            <input type="text" class="form-control" id="procedure_search"
                                placeholder="Search procedures..." autocomplete="off">
                            <ul class="list-group" id="procedure_search_results" style="display: none; position: absolute; z-index: 1000; width: calc(100% - 30px);"></ul>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-3">
                            <label for="procedure_priority"><i class="fa fa-exclamation-triangle"></i> Priority</label>
                            <select class="form-control" id="procedure_priority">
                                <option value="routine">Routine</option>
                                <option value="urgent">Urgent</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-3">
                            <label for="procedure_scheduled_date"><i class="fa fa-calendar"></i> Scheduled Date (Optional)</label>
                            <input type="date" class="form-control" id="procedure_scheduled_date">
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label for="procedure_pre_notes"><i class="fa fa-sticky-note"></i> Pre-Procedure Notes</label>
                    <textarea class="form-control" id="procedure_pre_notes" rows="3" placeholder="Clinical notes, indications, patient preparation instructions..."></textarea>
                </div>

                {{-- Selected Procedures List --}}
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Procedure</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>HMO Status</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="selected-procedures"></tbody>
                    </table>
                </div>

                <div id="no_procedures_message" class="alert alert-info mt-3">
                    <i class="fa fa-info-circle"></i> Search and select procedures above to add to this encounter.
                </div>
            </div>
        </div>

        {{-- Navigation Buttons --}}
        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
            <button type="button" onclick="switch_tab(event,'medications_tab')" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Previous
            </button>
            <div>
                <button type="button" onclick="saveProceduresAndNext()" id="save_procedures_btn" class="btn btn-success me-2">
                    <i class="fa fa-save"></i> Save & Next
                </button>
                <button type="button" onclick="saveProcedures()" class="btn btn-outline-success">
                    <i class="fa fa-save"></i> Save
                </button>
            </div>
        </div>
        <div id="procedures_save_message" class="mt-2"></div>
    </div>
</div>

{{-- Procedure Details Modal --}}
<div class="modal fade" id="procedureDetailsModal" tabindex="-1" aria-labelledby="procedureDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="procedureDetailsModalLabel"><i class="fa fa-user-md"></i> Procedure Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="procedureDetailsContent">
                    <div class="text-center py-4">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2">Loading procedure details...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" onclick="openTeamModal(currentProcedureId)" id="manageTeamBtn">
                    <i class="fa fa-users"></i> Manage Team
                </button>
                <button type="button" class="btn btn-warning" onclick="openNotesModal(currentProcedureId)" id="manageNotesBtn">
                    <i class="fa fa-sticky-note"></i> Notes
                </button>
                <button type="button" class="btn btn-primary" id="printProcedureBtn">
                    <i class="fa fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Procedure Team Modal --}}
<div class="modal fade" id="procedureTeamModal" tabindex="-1" aria-labelledby="procedureTeamModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="procedureTeamModalLabel"><i class="fa fa-users"></i> Procedure Team</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{-- Add Team Member Form --}}
                <div class="card-modern mb-3">
                    <div class="card-header bg-light">
                        <i class="fa fa-user-plus"></i> Add Team Member
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="team_member_user" class="form-label">Staff Member</label>
                                @php
                                    // Get staff users using UserCategory (exclude is_admin=19 which is Patient)
                                    // User categories: 19=Patient, 20=Receptionist, 21=Doctor, 22=Nurse, 23=Pharmacist, 24=Lab Tech, 25=Others
                                    $staffUsers = \App\Models\User::with(['category', 'staff_profile.specialization'])
                                        ->where('status', 1)
                                        ->where('is_admin', '!=', 19) // Exclude patients
                                        ->orderBy('surname')
                                        ->orderBy('firstname')
                                        ->get();
                                @endphp
                                <select class="form-select" name="team_member_user" id="team_member_user">
                                    <option value="">-- Select Staff ({{ $staffUsers->count() }} available) --</option>
                                    @foreach($staffUsers as $user)
                                        @php
                                            $categoryName = $user->category->name ?? 'Staff';
                                            $specialty = $user->staff_profile->specialization->name ?? null;
                                            $label = userfullname($user->id) . ' - ' . $categoryName;
                                            if ($specialty) {
                                                $label .= ' (' . $specialty . ')';
                                            }
                                        @endphp
                                        <option value="{{ $user->id }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label for="team_member_role" class="form-label">Role</label>
                                <select class="form-select" id="team_member_role" onchange="toggleCustomRole()">
                                    @foreach(\App\Models\ProcedureTeamMember::ROLES as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-5 mb-3" id="custom_role_container" style="display: none;">
                                <label for="team_member_custom_role" class="form-label">Custom Role</label>
                                <input type="text" class="form-control" id="team_member_custom_role" placeholder="Specify role...">
                            </div>

                            <div class="col-md-2 mb-3">
                                <label class="form-label d-block user-select-none" style="opacity: 0;">Spacing</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="team_member_is_lead">
                                    <label class="form-check-label ms-1" for="team_member_is_lead">
                                        Lead
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="team_member_notes" class="form-label">Notes (Optional)</label>
                                <input type="text" class="form-control" id="team_member_notes" placeholder="Any additional notes...">
                            </div>
                        </div>

                        <div class="mt-2">
                            <button type="button" class="btn btn-primary" onclick="addTeamMember()">
                                <i class="fa fa-plus"></i> Add Member
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Team List --}}
                <h6><i class="fa fa-list"></i> Current Team</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="procedure_team_table">
                        <thead class="table-light">
                            <tr>
                                <th>Staff Member</th>
                                <th>Role</th>
                                <th>Lead</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="procedure_team_list">
                            <tr>
                                <td colspan="5" class="text-center text-muted">Loading team members...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Procedure Notes Modal --}}
<div class="modal fade" id="procedureNotesModal" tabindex="-1" aria-labelledby="procedureNotesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="procedureNotesModalLabel"><i class="fa fa-sticky-note"></i> Procedure Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{-- Add Note Form --}}
                <div class="card-modern mb-3">
                    <div class="card-header bg-light">
                        <i class="fa fa-plus"></i> Add Note
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="note_type">Note Type</label>
                                    <select class="form-control" id="note_type">
                                        @foreach(\App\Models\ProcedureNote::NOTE_TYPES as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="note_title">Title</label>
                                    <input type="text" class="form-control" id="note_title" placeholder="Note title...">
                                </div>
                            </div>
                        </div>
                        <div class="form-group mt-3">
                            <label for="note_content">Content</label>
                            <textarea class="form-control ckeditor-notes" id="note_content" rows="6"></textarea>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-primary" onclick="addProcedureNote()">
                                <i class="fa fa-plus"></i> Add Note
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Notes List --}}
                <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
                    <h6 class="m-0"><i class="fa fa-list"></i> Procedure Notes</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-borderless">
                        <tbody id="procedure_notes_list">
                            <tr>
                                <td class="text-center text-muted py-3">Loading notes...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Procedure Module JavaScript
let selectedProcedures = [];
let procedureCategoryId = {{ appsettings('procedure_category_id', 0) }};
let currentProcedureId = null;

$(document).ready(function() {
    // Initialize procedure history DataTable
    initProcedureHistoryTable();

    // Setup search functionality
    setupProcedureSearch();
});

function initProcedureHistoryTable() {
    if ($.fn.DataTable.isDataTable('#procedure_history_list')) {
        $('#procedure_history_list').DataTable().destroy();
    }

    $('#procedure_history_list').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("procedureHistoryList", ["patient_id" => $encounter->patient_id ?? 0]) }}',
            type: 'GET',
            error: function(xhr, error, thrown) {
                console.log('Error loading procedure history:', error);
            }
        },
        columns: [
            { data: 'procedure', name: 'procedure' },
            { data: 'priority', name: 'priority' },
            { data: 'status', name: 'procedure_status' },
            { data: 'date', name: 'requested_on' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[3, 'desc']],
        language: {
            emptyTable: "No procedures found for this patient"
        }
    });
}

function setupProcedureSearch() {
    let searchTimeout;

    $('#procedure_search').on('keyup', function() {
        const query = $(this).val();

        clearTimeout(searchTimeout);

        if (query.length < 2) {
            $('#procedure_search_results').hide();
            return;
        }

        searchTimeout = setTimeout(function() {
            searchProcedures(query);
        }, 300);
    });

    // Hide results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#procedure_search, #procedure_search_results').length) {
            $('#procedure_search_results').hide();
        }
    });
}

function searchProcedures(query) {
    $.ajax({
        url: '{{ route("live-search-services") }}',
        type: 'GET',
        data: {
            term: query,
            category_id: procedureCategoryId,
            patient_id: {{ $encounter->patient_id ?? 0 }}
        },
        success: function(data) {
            const $results = $('#procedure_search_results');
            $results.empty();

            if (data.length === 0) {
                $results.append('<li class="list-group-item text-muted">No procedures found</li>');
            } else {
                data.forEach(function(item) {
                    // Check if already selected
                    const isSelected = selectedProcedures.some(p => p.id === item.id);

                    const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                    const name = item.service_name || 'Unknown';
                    const code = item.service_code || '';
                    const price = item.price && item.price.sale_price !== undefined ? item.price.sale_price : 0;
                    const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                    const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                    const mode = item.coverage_mode || null;

                    const coverageBadge = mode ?
                        `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ${payable}</span> <span class='text-success ms-1'>Claim: ${claims}</span>` :
                        '';
                    const displayName = `${name}[${code}]`;

                    const disabledClass = isSelected ? 'disabled' : '';
                    const disabledBadge = isSelected ? '<span class="badge bg-warning ms-2">Already Added</span>' : '';
                    const cursorStyle = isSelected ? 'cursor: not-allowed;' : 'cursor: pointer;';

                    const mk = `<li class='list-group-item ${disabledClass}'
                        style="background-color: #f0f0f0; ${cursorStyle}"
                        ${!isSelected ? `onclick="addProcedure(${JSON.stringify(item).replace(/"/g, '&quot;')})"` : ''}>
                        [${category}]<b>${displayName}</b> NGN ${price} ${coverageBadge} ${disabledBadge}</li>`;
                    $results.append(mk);
                });
            }

            $results.show();
        },
        error: function(xhr) {
            console.error('Error searching procedures:', xhr);
            $('#procedure_search_results').html('<li class="list-group-item text-danger">Error searching procedures</li>').show();
        }
    });
}

function addProcedure(procedure) {
    // Check if already added
    if (selectedProcedures.some(p => p.id === procedure.id)) {
        alert('This procedure is already added');
        return;
    }

    const priority = $('#procedure_priority').val();
    const scheduledDate = $('#procedure_scheduled_date').val();
    const preNotes = $('#procedure_pre_notes').val();

    procedure.priority = priority;
    procedure.scheduled_date = scheduledDate;
    procedure.pre_notes = preNotes;

    selectedProcedures.push(procedure);
    updateSelectedProceduresTable();

    // Clear search
    $('#procedure_search').val('');
    $('#procedure_search_results').hide();

    // Hide no procedures message
    $('#no_procedures_message').hide();
}

function removeProcedure(procedureId) {
    selectedProcedures = selectedProcedures.filter(p => p.id !== procedureId);
    updateSelectedProceduresTable();

    if (selectedProcedures.length === 0) {
        $('#no_procedures_message').show();
    }
}

function updateSelectedProceduresTable() {
    const $tbody = $('#selected-procedures');
    $tbody.empty();

    selectedProcedures.forEach(function(procedure, index) {
        const coverageDisplay = procedure.coverage_mode === 'hmo' ?
            '<span class="badge bg-success"><i class="fa fa-shield-alt"></i> HMO Covered</span>' :
            '<span class="badge bg-secondary"><i class="fa fa-wallet"></i> Self-Pay</span>';

        const priceDisplay = procedure.coverage_mode === 'hmo' ?
            `₦${formatNumber(procedure.claims_amount)}` :
            `₦${formatNumber(procedure.payable_amount)}`;

        const priorityClass = `priority-${procedure.priority}`;
        const priorityLabel = procedure.priority.charAt(0).toUpperCase() + procedure.priority.slice(1);

        const categoryName = procedure.category ? procedure.category.category_name : 'Procedures';

        $tbody.append(`
            <tr data-procedure-id="${procedure.id}">
                <td>
                    <strong>${procedure.service_name}</strong>
                    <br><small class="text-muted">${procedure.service_code || ''}</small>
                    ${procedure.pre_notes ? `<br><small class="text-info"><i class="fa fa-sticky-note"></i> ${procedure.pre_notes.substring(0, 50)}...</small>` : ''}
                </td>
                <td><small>${categoryName}</small></td>
                <td>${priceDisplay}</td>
                <td>${coverageDisplay}</td>
                <td>
                    <span class="priority-badge ${priorityClass}">${priorityLabel}</span>
                    ${procedure.scheduled_date ? `<br><small class="text-muted"><i class="fa fa-calendar"></i> ${procedure.scheduled_date}</small>` : ''}
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeProcedure(${procedure.id})">
                        <i class="fa fa-times"></i>
                    </button>
                </td>
            </tr>
        `);
    });

    if (selectedProcedures.length === 0) {
        $tbody.append(`
            <tr>
                <td colspan="6" class="text-center text-muted">
                    <i class="fa fa-info-circle"></i> No procedures selected
                </td>
            </tr>
        `);
    }
}

function saveProcedures() {
    if (selectedProcedures.length === 0) {
        showProcedureMessage('info', 'No procedures to save. Add some procedures first.');
        return;
    }

    const encounterId = {{ $encounter->id ?? 0 }};

    if (!encounterId) {
        showProcedureMessage('danger', 'No active encounter. Please save the encounter first.');
        return;
    }

    $('#save_procedures_btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

    $.ajax({
        url: `/encounters/${encounterId}/save-procedures`,
        type: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            procedures: selectedProcedures.map(p => ({
                service_id: p.id,
                priority: p.priority,
                scheduled_date: p.scheduled_date,
                pre_notes: p.pre_notes
            }))
        },
        success: function(response) {
            if (response.success) {
                showProcedureMessage('success', response.message || 'Procedures saved successfully!');
                // Clear selected procedures
                selectedProcedures = [];
                updateSelectedProceduresTable();
                $('#no_procedures_message').show();
                // Reload history table
                $('#procedure_history_list').DataTable().ajax.reload();
            } else {
                showProcedureMessage('danger', response.message || 'Failed to save procedures');
            }
        },
        error: function(xhr) {
            const errorMsg = xhr.responseJSON?.message || 'An error occurred while saving procedures';
            showProcedureMessage('danger', errorMsg);
        },
        complete: function() {
            $('#save_procedures_btn').prop('disabled', false).html('<i class="fa fa-save"></i> Save & Next');
        }
    });
}

function saveProceduresAndNext() {
    saveProcedures();
    setTimeout(function() {
        switch_tab(event, 'admissions_tab');
    }, 1000);
}

function showProcedureMessage(type, message) {
    $('#procedures_save_message').html(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `);

    // Auto-hide after 5 seconds
    setTimeout(function() {
        $('#procedures_save_message .alert').alert('close');
    }, 5000);
}

function viewProcedureDetails(procedureId) {
    $('#procedureDetailsContent').html(`
        <div class="text-center py-4">
            <i class="fa fa-spinner fa-spin fa-2x"></i>
            <p class="mt-2">Loading procedure details...</p>
        </div>
    `);

    $('#procedureDetailsModal').modal('show');

    $.ajax({
        url: `/procedures/${procedureId}`,
        type: 'GET',
        success: function(response) {
            renderProcedureDetails(response);
        },
        error: function(xhr) {
            $('#procedureDetailsContent').html(`
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-triangle"></i> Error loading procedure details
                </div>
            `);
        }
    });
}

function renderProcedureDetails(procedure) {
    const statusClass = `status-${procedure.procedure_status}`;
    const priorityClass = `priority-${procedure.priority}`;

    const html = `
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fa fa-info-circle"></i> Procedure Information</h6>
                <table class="table table-sm">
                    <tr><th>Procedure:</th><td>${procedure.service?.service_name || 'N/A'}</td></tr>
                    <tr><th>Code:</th><td>${procedure.service?.service_code || 'N/A'}</td></tr>
                    <tr><th>Status:</th><td><span class="status-badge ${statusClass}">${procedure.procedure_status}</span></td></tr>
                    <tr><th>Priority:</th><td><span class="priority-badge ${priorityClass}">${procedure.priority}</span></td></tr>
                    <tr><th>Requested:</th><td>${procedure.requested_on || 'N/A'}</td></tr>
                    <tr><th>Scheduled:</th><td>${procedure.scheduled_date || 'Not scheduled'}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6><i class="fa fa-user-md"></i> Clinical Information</h6>
                <table class="table table-sm">
                    <tr><th>Requested By:</th><td>${procedure.requested_by_user?.name || 'N/A'}</td></tr>
                    <tr><th>Operating Room:</th><td>${procedure.operating_room || 'TBD'}</td></tr>
                    <tr><th>Outcome:</th><td>${procedure.outcome || 'Pending'}</td></tr>
                </table>
            </div>
        </div>
        ${procedure.pre_notes ? `
        <div class="mt-3">
            <h6><i class="fa fa-sticky-note"></i> Pre-Procedure Notes</h6>
            <div class="p-2 bg-light rounded">${procedure.pre_notes}</div>
        </div>
        ` : ''}
        ${procedure.post_notes ? `
        <div class="mt-3">
            <h6><i class="fa fa-notes-medical"></i> Post-Procedure Notes</h6>
            <div class="p-2 bg-light rounded">${procedure.post_notes}</div>
        </div>
        ` : ''}
        ${procedure.outcome_notes ? `
        <div class="mt-3">
            <h6><i class="fa fa-clipboard-check"></i> Outcome Notes</h6>
            <div class="p-2 bg-light rounded">${procedure.outcome_notes}</div>
        </div>
        ` : ''}
    `;

    $('#procedureDetailsContent').html(html);
    currentProcedureId = procedure.id;
}

function deleteProcedureRequest(procedureId, encounterId, procedureName) {
    currentDeleteItem = {
        type: 'procedure',
        id: procedureId,
        encounterId: encounterId,
        name: procedureName
    };

    $('#deleteItemInfo').html(`
        <strong>Procedure:</strong> ${procedureName}<br>
        <strong>Type:</strong> Procedure Request
    `);

    $('#deleteConfirmModal').modal('show');
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-NG').format(num || 0);
}

// ========================================
// TEAM MANAGEMENT
// ========================================

// Wrapper function for datatable action button
function manageProcedureTeam(procedureId) {
    openTeamModal(procedureId);
}

// Wrapper function for datatable action button
function manageProcedureNotes(procedureId) {
    openNotesModal(procedureId);
}

function toggleCustomRole() {
    const role = $('#team_member_role').val();
    if (role === 'other') {
        $('#custom_role_container').show();
        $('#team_member_custom_role').prop('required', true);
    } else {
        $('#custom_role_container').hide();
        $('#team_member_custom_role').prop('required', false).val('');
    }
}

function openTeamModal(procedureId) {
    if (!procedureId) {
        alert('No procedure selected');
        return;
    }

    currentProcedureId = procedureId;
    $('#procedure_team_list').html('<tr><td colspan="5" class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>');

    // Reset selection
    $('#team_member_user').val('');

    $('#procedureTeamModal').modal('show');

    loadTeamMembers(procedureId);
}

function loadTeamMembers(procedureId) {
    console.log('Loading team members for procedure:', procedureId);
    $.ajax({
        url: `/procedures/${procedureId}/team`,
        type: 'GET',
        success: function(response) {
            console.log('Team response:', response);
            if (response.success) {
                renderTeamList(response.team);
            } else {
                $('#procedure_team_list').html('<tr><td colspan="5" class="text-center text-danger">Error loading team</td></tr>');
            }
        },
        error: function(xhr) {
            console.error('Error loading team:', xhr.status, xhr.responseText);
            $('#procedure_team_list').html('<tr><td colspan="5" class="text-center text-danger">Error loading team: ' + xhr.status + '</td></tr>');
        }
    });
}

function renderTeamList(team) {
    if (!team || team.length === 0) {
        $('#procedure_team_list').html('<tr><td colspan="5" class="text-center text-muted">No team members assigned yet</td></tr>');
        return;
    }

    let html = '';
    team.forEach(function(member) {
        const roleDisplay = member.role === 'other' && member.custom_role ? member.custom_role : getRoleLabel(member.role);
        const leadBadge = member.is_lead ? '<span class="badge bg-success">Lead</span>' : '';

        html += `
            <tr>
                <td>${member.user?.name || 'Unknown'}</td>
                <td>${roleDisplay}</td>
                <td>${leadBadge}</td>
                <td><small>${member.notes || '-'}</small></td>
                <td>
                    <button class="btn btn-sm btn-outline-danger" onclick="removeTeamMember(${member.id})" title="Remove">
                        <i class="fa fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    $('#procedure_team_list').html(html);
}

function getRoleLabel(role) {
    const roles = {
        'chief_surgeon': 'Chief Surgeon',
        'assistant_surgeon': 'Assistant Surgeon',
        'anesthesiologist': 'Anesthesiologist',
        'nurse_anesthetist': 'Nurse Anesthetist',
        'scrub_nurse': 'Scrub Nurse',
        'circulating_nurse': 'Circulating Nurse',
        'surgical_first_assistant': 'Surgical First Assistant',
        'perfusionist': 'Perfusionist',
        'radiologist': 'Radiologist',
        'pathologist': 'Pathologist',
        'other': 'Other'
    };
    return roles[role] || role;
}

function addTeamMember() {
    const userId = $('#team_member_user').val();
    const role = $('#team_member_role').val();
    const customRole = $('#team_member_custom_role').val();
    const isLead = $('#team_member_is_lead').is(':checked');
    const notes = $('#team_member_notes').val();

    if (!currentProcedureId) {
        alert('No procedure selected. Please try again.');
        return;
    }

    if (!userId) {
        alert('Please select a staff member');
        return;
    }

    if (role === 'other' && !customRole) {
        alert('Please specify the custom role');
        return;
    }

    $.ajax({
        url: `/procedures/${currentProcedureId}/team`,
        type: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            user_id: userId,
            role: role,
            custom_role: customRole,
            is_lead: isLead ? '1' : '0',
            notes: notes
        },
        success: function(response) {
            if (response.success) {
                // Clear form
                $('#team_member_user').val('').trigger('change');
                $('#team_member_role').val('chief_surgeon');
                $('#team_member_custom_role').val('');
                $('#team_member_is_lead').prop('checked', false);
                $('#team_member_notes').val('');
                toggleCustomRole();

                // Reload team list
                loadTeamMembers(currentProcedureId);
            } else {
                alert(response.message || 'Failed to add team member');
            }
        },
        error: function(xhr) {
            console.error('Error adding team member:', xhr.status, xhr.responseText);
            alert(xhr.responseJSON?.message || 'Error adding team member: ' + xhr.status);
        }
    });
}

function removeTeamMember(memberId) {
    if (!confirm('Are you sure you want to remove this team member?')) {
        return;
    }

    $.ajax({
        url: `/procedures/${currentProcedureId}/team/${memberId}`,
        type: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                loadTeamMembers(currentProcedureId);
            } else {
                alert(response.message || 'Failed to remove team member');
            }
        },
        error: function(xhr) {
            alert(xhr.responseJSON?.message || 'Error removing team member');
        }
    });
}

// ========================================
// NOTES MANAGEMENT
// ========================================

let noteEditorInstance = null;

function openNotesModal(procedureId) {
    if (!procedureId) {
        alert('No procedure selected');
        return;
    }

    currentProcedureId = procedureId;
    $('#procedure_notes_list').html('<div class="text-center py-3"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');

    $('#procedureNotesModal').modal('show');

    // Initialize CKEditor after modal is shown
    setTimeout(function() {
        initializeNotesEditor();
    }, 300);

    loadProcedureNotes(procedureId);
}

function initializeNotesEditor() {
    // Destroy existing instance if any
    if (noteEditorInstance) {
        noteEditorInstance.destroy().catch(err => console.log('Error destroying editor:', err));
        noteEditorInstance = null;
    }

    const editorElement = document.querySelector('#note_content');
    if (!editorElement) return;

    if (typeof ClassicEditor !== 'undefined') {
        ClassicEditor
            .create(editorElement, {
                toolbar: {
                    items: [
                        'undo', 'redo',
                        '|', 'heading',
                        '|', 'bold', 'italic', 'underline',
                        '|', 'bulletedList', 'numberedList',
                        '|', 'link', 'insertTable',
                        '|', 'outdent', 'indent'
                    ]
                }
            })
            .then(editor => {
                noteEditorInstance = editor;
            })
            .catch(error => {
                console.error('Error initializing CKEditor:', error);
            });
    }
}

function loadProcedureNotes(procedureId) {
    $.ajax({
        url: `/procedures/${procedureId}/notes`,
        type: 'GET',
        success: function(response) {
            if (response.success) {
                renderNotesList(response.notes);
            } else {
                $('#procedure_notes_list').html('<div class="text-center text-danger py-3">Error loading notes</div>');
            }
        },
        error: function(xhr) {
            $('#procedure_notes_list').html('<div class="text-center text-danger py-3">Error loading notes</div>');
        }
    });
}

function renderNotesList(notes) {
    if (!notes || notes.length === 0) {
        $('#procedure_notes_list').html('<div class="text-center text-muted py-3">No notes added yet</div>');
        return;
    }

    const noteTypes = {
        'pre_op': { label: 'Pre-Operative', color: 'info' },
        'intra_op': { label: 'Intra-Operative', color: 'warning' },
        'post_op': { label: 'Post-Operative', color: 'success' },
        'anesthesia': { label: 'Anesthesia', color: 'primary' },
        'nursing': { label: 'Nursing', color: 'secondary' }
    };

    let html = '';

    notes.forEach(function(note) {
        const typeInfo = noteTypes[note.note_type] || { label: note.note_type, color: 'secondary' };
        const createdDate = new Date(note.created_at).toLocaleString();
        // Use created_by relation (which overwrites created_by id in JSON if snake_case) or fallbacks
        const userObj = note.created_by || note.created_by_user;
        const createdBy = userObj ? (userObj.name || userObj.surname + ' ' + userObj.firstname) : 'Unknown';

        html += `
            <tr>
                <td class="p-0 mb-3 d-block">
                    <div class="card-modern border shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-${typeInfo.color} me-2">${typeInfo.label}</span>
                                <span class="fw-bold text-dark">${note.title || 'Untitled'}</span>
                            </div>
                            <small class="text-muted d-flex align-items-center">
                                <i class="fa fa-user me-1"></i> ${createdBy}
                                <span class="mx-2">|</span>
                                <i class="fa fa-clock-o me-1"></i> ${createdDate}
                            </small>
                        </div>
                        <div class="card-body py-2 bg-light bg-opacity-10">
                            <div class="note-content">${note.content}</div>
                        </div>
                        <div class="card-footer bg-white border-top-0 py-1 text-end">
                            <button class="btn btn-sm btn-link text-danger p-0 text-decoration-none" onclick="deleteProcedureNote(${note.id})">
                                <i class="fa fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </td>
            </tr>
        `;
    });

    $('#procedure_notes_list').html(html);
}

function addProcedureNote() {
    const noteType = $('#note_type').val();
    const title = $('#note_title').val();

    let content = '';
    if (noteEditorInstance) {
        content = noteEditorInstance.getData();
    } else {
        content = $('#note_content').val();
    }

    if (!title) {
        alert('Please enter a note title');
        return;
    }

    if (!content || content.trim() === '') {
        alert('Please enter note content');
        return;
    }

    $.ajax({
        url: `/procedures/${currentProcedureId}/notes`,
        type: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            note_type: noteType,
            title: title,
            content: content
        },
        success: function(response) {
            if (response.success) {
                // Clear form
                $('#note_type').val('pre_op');
                $('#note_title').val('');
                if (noteEditorInstance) {
                    noteEditorInstance.setData('');
                } else {
                    $('#note_content').val('');
                }

                // Reload notes list
                loadProcedureNotes(currentProcedureId);
            } else {
                alert(response.message || 'Failed to add note');
            }
        },
        error: function(xhr) {
            alert(xhr.responseJSON?.message || 'Error adding note');
        }
    });
}

function deleteProcedureNote(noteId) {
    if (!confirm('Are you sure you want to delete this note?')) {
        return;
    }

    $.ajax({
        url: `/procedures/${currentProcedureId}/notes/${noteId}`,
        type: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                loadProcedureNotes(currentProcedureId);
            } else {
                alert(response.message || 'Failed to delete note');
            }
        },
        error: function(xhr) {
            alert(xhr.responseJSON?.message || 'Error deleting note');
        }
    });
}

// Cleanup editors when modals are closed
$('#procedureNotesModal').on('hidden.bs.modal', function() {
    if (noteEditorInstance) {
        noteEditorInstance.destroy().catch(err => {});
        noteEditorInstance = null;
    }
});

// ========================================
// PROCEDURE CANCELLATION
// ========================================

function cancelProcedure(procedureId, procedureName) {
    currentProcedureId = procedureId;

    // Show confirmation with reason input - improved UX
    const html = `
        <div class="text-start">
            <div class="alert alert-danger mb-3">
                <i class="fa fa-exclamation-triangle fa-lg me-2"></i>
                <strong>You are about to cancel:</strong><br>
                <span class="fs-5">${procedureName}</span>
            </div>
            <div class="form-group mb-3">
                <label for="cancellation_reason" class="form-label fw-bold">
                    <i class="fa fa-comment-dots me-1"></i> Reason for Cancellation <span class="text-danger">*</span>
                </label>
                <textarea class="form-control" id="cancellation_reason" rows="3" required
                    placeholder="Please provide a detailed reason for cancellation..."
                    style="border: 2px solid #dee2e6;"></textarea>
                <small class="text-muted">This will be recorded in the patient's medical record.</small>
            </div>
            <div class="form-check form-switch mb-2">
                <input type="checkbox" class="form-check-input" id="process_refund" checked style="cursor: pointer;">
                <label class="form-check-label" for="process_refund" style="cursor: pointer;">
                    <i class="fa fa-money-bill-wave me-1 text-success"></i> Process refund (if payment was made)
                </label>
            </div>
        </div>
    `;

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Cancel Procedure?',
            html: html,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa fa-times"></i> Cancel Procedure',
            cancelButtonText: 'Keep Procedure',
            preConfirm: () => {
                const reason = document.getElementById('cancellation_reason').value;
                if (!reason || reason.trim() === '') {
                    Swal.showValidationMessage('Please provide a cancellation reason');
                    return false;
                }
                return {
                    reason: reason,
                    refund: document.getElementById('process_refund').checked
                };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                executeCancelProcedure(procedureId, result.value.reason, result.value.refund);
            }
        });
    } else {
        // Fallback for no SweetAlert
        if (confirm(`Cancel procedure: ${procedureName}?\n\nNote: A refund may be processed if applicable.`)) {
            const reason = prompt('Please provide a reason for cancellation:');
            if (reason && reason.trim() !== '') {
                executeCancelProcedure(procedureId, reason, true);
            } else {
                alert('Cancellation reason is required.');
            }
        }
    }
}

function executeCancelProcedure(procedureId, reason, processRefund) {
    $.ajax({
        url: `/procedures/${procedureId}/cancel`,
        type: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            cancellation_reason: reason,
            refund_eligible: processRefund ? '1' : '0'
        },
        beforeSend: function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Processing...',
                    text: 'Cancelling procedure...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            }
        },
        success: function(response) {
            if (response.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Cancelled!',
                        text: response.message || 'Procedure has been cancelled.',
                        icon: 'success'
                    });
                } else {
                    alert(response.message || 'Procedure cancelled successfully.');
                }

                // Reload the history table
                $('#procedure_history_list').DataTable().ajax.reload();

                // Close details modal if open
                $('#procedureDetailsModal').modal('hide');
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Error',
                        text: response.message || 'Failed to cancel procedure.',
                        icon: 'error'
                    });
                } else {
                    alert(response.message || 'Failed to cancel procedure.');
                }
            }
        },
        error: function(xhr) {
            const errorMsg = xhr.responseJSON?.message || 'An error occurred while cancelling the procedure.';
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Error',
                    text: errorMsg,
                    icon: 'error'
                });
            } else {
                alert(errorMsg);
            }
        }
    });
}

// ========================================
// PRINT PROCEDURE
// ========================================

function printProcedure(procedureId) {
    window.open(`/procedures/${procedureId}/print`, '_blank', 'width=800,height=600');
}
</script>
@endpush

