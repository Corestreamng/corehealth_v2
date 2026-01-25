@extends('admin.layouts.app')

@section('title', 'ESS - My Disciplinary Records')

@section('styles')
<link href="{{ asset('plugins/datatables/datatables.min.css') }}" rel="stylesheet">
@endsection

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                <i class="mdi mdi-gavel mr-2"></i>My Disciplinary Records
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{ route('hr.ess.index') }}">ESS</a></li>
                    <li class="breadcrumb-item active">My Disciplinary</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Active Alert -->
    @if($activeQuery)
    <div class="alert alert-warning d-flex align-items-center mb-4" style="border-radius: 12px;">
        <div class="mr-3">
            <i class="mdi mdi-alert-circle" style="font-size: 2rem;"></i>
        </div>
        <div class="flex-grow-1">
            <h6 class="mb-1">You have an active disciplinary query requiring attention</h6>
            <p class="mb-0 small">Subject: <strong>{{ $activeQuery->subject }}</strong> - Please submit your response below.</p>
        </div>
        <button class="btn btn-warning btn-sm" onclick="viewQuery({{ $activeQuery->id }})" style="border-radius: 6px;">
            <i class="mdi mdi-eye mr-1"></i>View & Respond
        </button>
    </div>
    @endif

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Queries</h6>
                            <h3 class="mb-0" style="font-weight: 700;">{{ $totalQueries ?? 0 }}</h3>
                        </div>
                        <div class="text-primary" style="font-size: 2rem; opacity: 0.3;">
                            <i class="mdi mdi-file-document-multiple"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Open Queries</h6>
                            <h3 class="mb-0" style="font-weight: 700;">{{ $openQueries ?? 0 }}</h3>
                        </div>
                        <div class="text-warning" style="font-size: 2rem; opacity: 0.5;">
                            <i class="mdi mdi-clock-alert"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Warnings Received</h6>
                            <h3 class="mb-0" style="font-weight: 700;">{{ $warningsCount ?? 0 }}</h3>
                        </div>
                        <div class="text-info" style="font-size: 2rem; opacity: 0.5;">
                            <i class="mdi mdi-alert"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Suspensions</h6>
                            <h3 class="mb-0" style="font-weight: 700;">{{ $suspensionsCount ?? 0 }}</h3>
                        </div>
                        <div class="text-danger" style="font-size: 2rem; opacity: 0.5;">
                            <i class="mdi mdi-account-lock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Disciplinary Queries Table -->
    <div class="card-modern border-0 mb-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
            <h6 class="mb-0" style="font-weight: 600;">
                <i class="mdi mdi-format-list-bulleted text-primary mr-2"></i>Disciplinary Queries
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="queriesTable" class="table table-hover" style="width: 100%;">
                    <thead class="bg-light">
                        <tr>
                            <th>Date</th>
                            <th>Subject</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Outcome</th>
                            <th>Response Deadline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <!-- Suspensions History -->
    @if($suspensions && $suspensions->count() > 0)
    <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
            <h6 class="mb-0" style="font-weight: 600;">
                <i class="mdi mdi-account-lock text-danger mr-2"></i>Suspension History
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Duration</th>
                            <th>With Pay</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($suspensions as $suspension)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($suspension->start_date)->format('M d, Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($suspension->end_date)->format('M d, Y') }}</td>
                            <td>{{ $suspension->duration_days }} days</td>
                            <td>
                                @if($suspension->is_paid)
                                <span class="badge badge-success" style="border-radius: 6px;">Yes</span>
                                @else
                                <span class="badge badge-danger" style="border-radius: 6px;">No</span>
                                @endif
                            </td>
                            <td>{{ Str::limit($suspension->reason, 50) }}</td>
                            <td>
                                @if($suspension->is_active)
                                <span class="badge badge-danger" style="border-radius: 6px;">Active</span>
                                @else
                                <span class="badge badge-secondary" style="border-radius: 6px;">Completed</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- View Query Modal -->
<div class="modal fade" id="viewQueryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-warning text-dark" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-file-document mr-2"></i>Query Details
                </h5>
                <button type="button" class="close"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Query Date:</strong> <span id="view_query_date"></span></p>
                        <p><strong>Subject:</strong> <span id="view_subject"></span></p>
                        <p><strong>Severity:</strong> <span id="view_severity"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> <span id="view_status"></span></p>
                        <p><strong>Response Deadline:</strong> <span id="view_deadline"></span></p>
                        <p><strong>Outcome:</strong> <span id="view_outcome"></span></p>
                    </div>
                </div>

                <div class="card-modern mb-3" style="border-radius: 8px; background: #f8f9fa;">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Query Description</h6>
                        <p id="view_description" class="mb-0"></p>
                    </div>
                </div>

                <div id="existingResponseSection" style="display: none;">
                    <div class="card-modern mb-3" style="border-radius: 8px; background: #e3f2fd;">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Your Response</h6>
                            <p id="view_response" class="mb-0"></p>
                            <small class="text-muted">Submitted: <span id="view_response_date"></span></small>
                        </div>
                    </div>
                </div>

                <div id="decisionSection" style="display: none;">
                    <div class="card-modern mb-3" style="border-radius: 8px; background: #fff3e0;">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Management Decision</h6>
                            <p id="view_decision_notes" class="mb-0"></p>
                            <small class="text-muted">Decided by: <span id="view_decided_by"></span></small>
                        </div>
                    </div>
                </div>

                <!-- Response Form (for open queries) -->
                <div id="responseFormSection" style="display: none;">
                    <hr>
                    <h6 class="mb-3"><i class="mdi mdi-reply mr-2"></i>Submit Your Response</h6>
                    <form id="responseForm">
                        @csrf
                        <input type="hidden" name="query_id" id="response_query_id">
                        <div class="form-group">
                            <label class="font-weight-bold">Your Response <span class="text-danger">*</span></label>
                            <textarea name="staff_response" id="staff_response" class="form-control" rows="5"
                                      placeholder="Please provide your response to this query..." required
                                      style="border-radius: 8px;"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Supporting Document (Optional)</label>
                            <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png"
                                   style="border-radius: 8px;">
                            <small class="text-muted">Upload any supporting documents (PDF, JPG, PNG - max 5MB)</small>
                        </div>
                        <button type="submit" class="btn btn-primary" style="border-radius: 8px;">
                            <i class="mdi mdi-send mr-1"></i>Submit Response
                        </button>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('plugins/datatables/datatables.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#queriesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("ess.my-disciplinary.data") }}',
        columns: [
            { data: 'query_date', name: 'query_date' },
            { data: 'subject', name: 'subject' },
            { data: 'severity', name: 'severity' },
            { data: 'status', name: 'status' },
            { data: 'outcome', name: 'outcome' },
            { data: 'response_deadline', name: 'response_deadline' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        language: {
            emptyTable: "No disciplinary records found"
        }
    });

    // Submit response
    $('#responseForm').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        var queryId = $('#response_query_id').val();

        $.ajax({
            url: '{{ url("ess/my-disciplinary") }}/' + queryId + '/respond',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#viewQueryModal').modal('hide');
                    table.ajax.reload();
                    location.reload(); // Refresh to update active query alert
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    Object.keys(errors).forEach(function(key) {
                        toastr.error(errors[key][0]);
                    });
                } else {
                    toastr.error('An error occurred. Please try again.');
                }
            }
        });
    });
});

function viewQuery(id) {
    $.get('{{ url("ess/my-disciplinary") }}/' + id, function(data) {
        $('#view_query_date').text(data.query_date);
        $('#view_subject').text(data.subject);
        $('#view_description').text(data.description);
        $('#view_deadline').text(data.response_deadline || 'N/A');

        // Severity badge
        var severityClass = {
            'minor': 'info',
            'major': 'warning',
            'gross': 'danger'
        }[data.severity] || 'secondary';
        $('#view_severity').html('<span class="badge badge-' + severityClass + '">' + data.severity.charAt(0).toUpperCase() + data.severity.slice(1) + '</span>');

        // Status badge
        var statusClass = {
            'issued': 'warning',
            'acknowledged': 'info',
            'responded': 'primary',
            'under_review': 'secondary',
            'closed': 'success'
        }[data.status] || 'secondary';
        $('#view_status').html('<span class="badge badge-' + statusClass + '">' + data.status.replace('_', ' ').toUpperCase() + '</span>');

        // Outcome
        if (data.outcome) {
            var outcomeClass = {
                'warning': 'warning',
                'written_warning': 'warning',
                'final_warning': 'danger',
                'suspension': 'danger',
                'termination': 'dark',
                'exonerated': 'success'
            }[data.outcome] || 'secondary';
            $('#view_outcome').html('<span class="badge badge-' + outcomeClass + '">' + data.outcome.replace('_', ' ').toUpperCase() + '</span>');
        } else {
            $('#view_outcome').text('Pending');
        }

        // Show/hide sections based on data
        if (data.staff_response) {
            $('#view_response').text(data.staff_response);
            $('#view_response_date').text(data.response_date || 'N/A');
            $('#existingResponseSection').show();
            $('#responseFormSection').hide();
        } else if (data.status === 'issued' || data.status === 'acknowledged') {
            $('#existingResponseSection').hide();
            $('#responseFormSection').show();
            $('#response_query_id').val(data.id);
            $('#staff_response').val('');
        } else {
            $('#existingResponseSection').hide();
            $('#responseFormSection').hide();
        }

        if (data.decision_notes) {
            $('#view_decision_notes').text(data.decision_notes);
            $('#view_decided_by').text(data.decided_by_name || 'N/A');
            $('#decisionSection').show();
        } else {
            $('#decisionSection').hide();
        }

        $('#viewQueryModal').modal('show');
    });
}
</script>
@endsection
