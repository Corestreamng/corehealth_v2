@extends('admin.layouts.app')

@section('title', 'Disciplinary Queries')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-gavel mr-2"></i>Disciplinary Queries
                    </h3>
                    <p class="text-muted mb-0">Issue and manage disciplinary queries</p>
                </div>
                <div class="d-flex">
                    <select id="statusFilter" class="form-control mr-2" style="border-radius: 8px; width: 150px;">
                        <option value="">All Status</option>
                        <option value="pending">Pending Response</option>
                        <option value="responded">Responded</option>
                        <option value="reviewed">Under Review</option>
                        <option value="closed">Closed</option>
                    </select>
                    @can('disciplinary.create')
                    <button type="button" class="btn btn-primary" id="addQueryBtn" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                        <i class="mdi mdi-plus mr-1"></i> Issue Query
                    </button>
                    @endcan
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Pending Response</h6>
                                    <h3 class="mb-0" id="pendingCount">0</h3>
                                </div>
                                <i class="mdi mdi-clock-alert-outline" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Under Review</h6>
                                    <h3 class="mb-0" id="reviewCount">0</h3>
                                </div>
                                <i class="mdi mdi-file-search-outline" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Warnings Issued</h6>
                                    <h3 class="mb-0" id="warningsCount">0</h3>
                                </div>
                                <i class="mdi mdi-alert-outline" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                        <div class="card-body text-dark">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-muted">Dismissals</h6>
                                    <h3 class="mb-0" id="dismissalsCount">0</h3>
                                </div>
                                <i class="mdi mdi-account-off-outline" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Queries Table Card -->
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                    <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-format-list-bulleted mr-2" style="color: var(--primary-color);"></i>
                        Disciplinary Queries
                    </h5>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table id="queriesTable" class="table table-hover" style="width: 100%;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="font-weight: 600; color: #495057;">SN</th>
                                    <th style="font-weight: 600; color: #495057;">Query No</th>
                                    <th style="font-weight: 600; color: #495057;">Staff</th>
                                    <th style="font-weight: 600; color: #495057;">Subject</th>
                                    <th style="font-weight: 600; color: #495057;">Severity</th>
                                    <th style="font-weight: 600; color: #495057;">Status</th>
                                    <th style="font-weight: 600; color: #495057;">Outcome</th>
                                    <th style="font-weight: 600; color: #495057;">Date</th>
                                    <th style="font-weight: 600; color: #495057;">Actions</th>
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

<!-- Issue Query Modal -->
<div class="modal fade" id="queryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-file-alert mr-2" style="color: var(--primary-color);"></i>
                    <span id="modalTitleText">Issue Disciplinary Query</span>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="queryForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="query_id" id="query_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Staff Member *</label>
                            <select class="form-control select2" name="staff_id" id="staff_id" required style="width: 100%;">
                                <option value="">Select Staff</option>
                                @foreach($staffList ?? [] as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->user->firstname ?? '' }} {{ $staff->user->surname ?? '' }} ({{ $staff->employee_id ?? 'N/A' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Severity Level *</label>
                            <select class="form-control" name="severity" id="severity" required style="border-radius: 8px;">
                                <option value="minor">Minor</option>
                                <option value="moderate">Moderate</option>
                                <option value="major">Major</option>
                                <option value="gross">Gross Misconduct</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Subject *</label>
                            <input type="text" class="form-control" name="subject" id="subject" required
                                   style="border-radius: 8px; padding: 0.75rem;" placeholder="Brief subject of the query">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Incident Date *</label>
                            <input type="date" class="form-control" name="incident_date" id="incident_date" required
                                   style="border-radius: 8px; padding: 0.75rem;" max="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Description *</label>
                            <textarea class="form-control" name="description" id="description" rows="4" required
                                      style="border-radius: 8px; padding: 0.75rem;" placeholder="Detailed description of the incident/misconduct"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Response Deadline *</label>
                            <input type="date" class="form-control" name="response_deadline" id="response_deadline" required
                                   style="border-radius: 8px; padding: 0.75rem;" min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Witnesses (if any)</label>
                            <input type="text" class="form-control" name="witnesses" id="witnesses"
                                   style="border-radius: 8px; padding: 0.75rem;" placeholder="Names of witnesses">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Supporting Documents</label>
                            <input type="file" class="form-control" name="attachments[]" id="attachments" multiple
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="border-radius: 8px;">
                            <small class="text-muted">Evidence files (PDF, images, Word documents). Max 5MB each.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitQueryBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-send mr-1"></i> Issue Query
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Query Modal -->
<div class="modal fade" id="viewQueryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-eye mr-2" style="color: var(--primary-color);"></i>
                    Query Details - <span id="viewQueryNumber"></span>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <div id="queryDetails"></div>
            </div>
            <div class="modal-footer justify-content-between" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                <div id="queryActionButtons"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Process Decision Modal -->
<div class="modal fade" id="decisionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-warning" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title text-dark">
                    <i class="mdi mdi-scale-balance mr-2"></i>
                    Process Decision
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="decisionForm">
                @csrf
                <input type="hidden" id="decision_query_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Outcome *</label>
                        <select class="form-control" name="outcome" id="outcome" required style="border-radius: 8px;">
                            <option value="">Select Outcome</option>
                            <option value="no_action">No Action Required</option>
                            <option value="verbal_warning">Verbal Warning</option>
                            <option value="written_warning">Written Warning</option>
                            <option value="final_warning">Final Warning</option>
                            <option value="suspension">Suspension</option>
                            <option value="demotion">Demotion</option>
                            <option value="termination">Termination</option>
                            <option value="dismissed">Case Dismissed</option>
                        </select>
                    </div>

                    <div class="form-group" id="suspensionDaysGroup" style="display: none;">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Suspension Days</label>
                        <input type="number" class="form-control" name="suspension_days" id="suspension_days"
                               min="1" style="border-radius: 8px; padding: 0.75rem;">
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Decision Notes *</label>
                        <textarea class="form-control" name="decision_notes" id="decision_notes" rows="3" required
                                  style="border-radius: 8px; padding: 0.75rem;" placeholder="Provide justification for the decision"></textarea>
                    </div>

                    <div class="alert alert-warning d-none" id="terminationWarning" style="border-radius: 8px;">
                        <i class="mdi mdi-alert mr-1"></i>
                        <strong>Warning:</strong> Selecting termination will initiate the termination process for this staff member.
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="submitDecisionBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-check mr-1"></i> Submit Decision
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(function() {
    // Initialize Select2
    $('.select2').select2({
        dropdownParent: $('#queryModal'),
        placeholder: 'Select Staff',
        allowClear: true
    });

    // Initialize DataTable
    const table = $('#queriesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('hr.disciplinary.index') }}",
            data: function(d) {
                d.status = $('#statusFilter').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'query_number', name: 'query_number' },
            { data: 'staff_name', name: 'staff.user.firstname' },
            { data: 'subject', name: 'subject' },
            { data: 'severity_badge', name: 'severity' },
            { data: 'status_badge', name: 'status' },
            { data: 'outcome_badge', name: 'outcome' },
            { data: 'created_at', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[7, 'desc']],
        language: {
            emptyTable: "No disciplinary queries found",
            processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>'
        }
    });

    // Load stats
    function loadStats() {
        $.get("{{ route('hr.disciplinary.index') }}", { stats: true }, function(data) {
            $('#pendingCount').text(data.pending || 0);
            $('#reviewCount').text(data.reviewed || 0);
            $('#warningsCount').text(data.warnings || 0);
            $('#dismissalsCount').text(data.dismissals || 0);
        });
    }
    loadStats();

    // Status filter change
    $('#statusFilter').change(function() {
        table.ajax.reload();
    });

    // Add query button
    $('#addQueryBtn').click(function() {
        $('#queryForm')[0].reset();
        $('#query_id').val('');
        $('#staff_id').val('').trigger('change');
        $('#modalTitleText').text('Issue Disciplinary Query');
        $('#queryModal').modal('show');
    });

    // Submit query
    $('#queryForm').submit(function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const id = $('#query_id').val();
        const url = id ? "{{ route('hr.disciplinary.index') }}/" + id : "{{ route('hr.disciplinary.store') }}";

        $('#submitQueryBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...');

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#queryModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Query issued successfully');
            },
            error: function(xhr) {
                let message = 'An error occurred';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    message = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                }
                toastr.error(message);
            },
            complete: function() {
                $('#submitQueryBtn').prop('disabled', false).html('<i class="mdi mdi-send mr-1"></i> Issue Query');
            }
        });
    });

    // View query
    $(document).on('click', '.view-btn', function() {
        const id = $(this).data('id');

        $.get("{{ route('hr.disciplinary.index') }}/" + id, function(data) {
            $('#viewQueryNumber').text(data.query_number);

            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Staff:</strong> ${data.staff_name}</p>
                        <p><strong>Subject:</strong> ${data.subject}</p>
                        <p><strong>Severity:</strong> ${data.severity_badge}</p>
                        <p><strong>Incident Date:</strong> ${data.incident_date}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> ${data.status_badge}</p>
                        <p><strong>Response Deadline:</strong> ${data.response_deadline}</p>
                        <p><strong>Issued By:</strong> ${data.issued_by || '-'}</p>
                        <p><strong>Issued On:</strong> ${data.created_at}</p>
                    </div>
                    <div class="col-12 mt-3">
                        <h6><strong>Description:</strong></h6>
                        <p class="border p-2 rounded bg-light">${data.description}</p>
                    </div>
            `;

            if (data.staff_response) {
                html += `
                    <div class="col-12 mt-3">
                        <h6><strong>Staff Response:</strong></h6>
                        <p class="border p-2 rounded bg-light">${data.staff_response}</p>
                        <small class="text-muted">Responded on: ${data.responded_at}</small>
                    </div>
                `;
            }

            if (data.outcome) {
                html += `
                    <div class="col-12 mt-3">
                        <h6><strong>Decision:</strong></h6>
                        <p>Outcome: ${data.outcome_badge}</p>
                        <p class="border p-2 rounded bg-light">${data.decision_notes || '-'}</p>
                        <small class="text-muted">Decided by: ${data.decided_by || '-'} on ${data.decided_at || '-'}</small>
                    </div>
                `;
            }

            html += '</div>';
            $('#queryDetails').html(html);

            // Action buttons
            let actions = '';
            if (data.status === 'responded' || data.status === 'reviewed') {
                @can('disciplinary.decide')
                actions += '<button class="btn btn-warning mr-2 decision-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-scale-balance mr-1"></i> Process Decision</button>';
                @endcan
            }
            $('#queryActionButtons').html(actions);

            $('#viewQueryModal').modal('show');
        });
    });

    // Decision button click
    $(document).on('click', '.decision-btn', function() {
        const id = $(this).data('id');
        $('#decision_query_id').val(id);
        $('#outcome').val('');
        $('#decision_notes').val('');
        $('#suspension_days').val('');
        $('#suspensionDaysGroup').hide();
        $('#terminationWarning').addClass('d-none');
        $('#viewQueryModal').modal('hide');
        $('#decisionModal').modal('show');
    });

    // Outcome change
    $('#outcome').change(function() {
        const outcome = $(this).val();
        if (outcome === 'suspension') {
            $('#suspensionDaysGroup').show();
        } else {
            $('#suspensionDaysGroup').hide();
        }

        if (outcome === 'termination') {
            $('#terminationWarning').removeClass('d-none');
        } else {
            $('#terminationWarning').addClass('d-none');
        }
    });

    // Submit decision
    $('#decisionForm').submit(function(e) {
        e.preventDefault();

        const id = $('#decision_query_id').val();

        $('#submitDecisionBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...');

        $.ajax({
            url: "{{ route('hr.disciplinary.index') }}/" + id + "/decision",
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                outcome: $('#outcome').val(),
                suspension_days: $('#suspension_days').val(),
                decision_notes: $('#decision_notes').val()
            },
            success: function(response) {
                $('#decisionModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Decision processed successfully');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to process decision');
            },
            complete: function() {
                $('#submitDecisionBtn').prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Submit Decision');
            }
        });
    });
});
</script>
@endsection
