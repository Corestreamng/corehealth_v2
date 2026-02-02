@extends('admin.layouts.app')

@section('title', 'ESS - My Disciplinary Records')

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
        <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
            <h6 class="mb-0" style="font-weight: 600;">
                <i class="mdi mdi-format-list-bulleted text-primary mr-2"></i>Disciplinary Queries
            </h6>
            <div class="d-flex gap-2">
                <select id="statusFilter" class="form-control form-control-sm mr-2" style="border-radius: 8px; width: 150px;">
                    <option value="">All Status</option>
                    <option value="issued">Issued</option>
                    <option value="response_received">Response Received</option>
                    <option value="under_review">Under Review</option>
                    <option value="closed">Closed</option>
                </select>
                <select id="severityFilter" class="form-control form-control-sm" style="border-radius: 8px; width: 150px;">
                    <option value="">All Severity</option>
                    <option value="minor">Minor</option>
                    <option value="moderate">Moderate</option>
                    <option value="major">Major</option>
                    <option value="gross_misconduct">Gross Misconduct</option>
                </select>
            </div>
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

    <!-- Active Suspension Alert -->
    @if(isset($activeSuspension) && $activeSuspension)
    <div class="alert alert-danger d-flex align-items-center mb-4" style="border-radius: 12px;">
        <div class="mr-3">
            <i class="mdi mdi-account-lock" style="font-size: 2rem;"></i>
        </div>
        <div class="flex-grow-1">
            <h6 class="mb-1">You are currently suspended</h6>
            <p class="mb-0 small">
                <strong>Period:</strong> {{ $activeSuspension->start_date->format('M d, Y') }}
                @if($activeSuspension->end_date)
                    to {{ $activeSuspension->end_date->format('M d, Y') }}
                @else
                    (Indefinite)
                @endif
                <br>
                <strong>Type:</strong> {{ ucfirst($activeSuspension->type) }} Suspension
            </p>
        </div>
    </div>
    @endif

    <!-- Suspensions History -->
    @if(isset($suspensions) && $suspensions->count() > 0)
    <div class="card-modern border-0 mb-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
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
                            <th>Suspension #</th>
                            <th>Type</th>
                            <th>Period</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($suspensions as $suspension)
                        <tr class="{{ $suspension->status === 'active' ? 'table-danger' : '' }}">
                            <td><strong>{{ $suspension->suspension_number }}</strong></td>
                            <td>
                                <span class="badge badge-{{ $suspension->type === 'paid' ? 'info' : 'warning' }}" style="border-radius: 6px;">
                                    {{ ucfirst($suspension->type) }}
                                </span>
                            </td>
                            <td>
                                {{ $suspension->start_date->format('M d, Y') }}
                                @if($suspension->end_date)
                                    <br><small class="text-muted">to {{ $suspension->end_date->format('M d, Y') }}</small>
                                @else
                                    <br><small class="text-danger">Indefinite</small>
                                @endif
                            </td>
                            <td>
                                @if($suspension->end_date)
                                    {{ $suspension->start_date->diffInDays($suspension->end_date) + 1 }} days
                                @else
                                    <span class="text-danger">Ongoing</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusClass = match($suspension->status) {
                                        'active' => 'danger',
                                        'lifted' => 'success',
                                        'expired' => 'secondary',
                                        default => 'warning'
                                    };
                                @endphp
                                <span class="badge badge-{{ $statusClass }}" style="border-radius: 6px;">
                                    {{ ucfirst($suspension->status) }}
                                </span>
                                @if($suspension->status === 'lifted')
                                    <br><small class="text-muted">{{ $suspension->lifted_at?->format('M d, Y') }}</small>
                                @endif
                            </td>
                            <td>
                                <span data-toggle="tooltip" title="{{ $suspension->reason }}">
                                    {{ Str::limit($suspension->reason, 30) }}
                                </span>
                                @if($suspension->disciplinaryQuery)
                                    <br><small class="text-muted">
                                        <a href="javascript:void(0)" onclick="viewQuery({{ $suspension->disciplinaryQuery->id }})">
                                            Related Query: {{ $suspension->disciplinaryQuery->query_number }}
                                        </a>
                                    </small>
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
    <div class="modal-dialog modal-dialog-centered" style="max-width: 900px;">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-warning text-dark" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-file-document mr-2"></i>Query Details - <span id="viewQueryNumber"></span>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem; max-height: 80vh; overflow-y: auto;">
                <div class="row">
                    <!-- Left Column: Query Info & Timeline -->
                    <div class="col-lg-5">
                        <!-- Query Summary Card -->
                        <div class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-information-outline mr-1"></i> Query Information
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0" style="font-size: 0.9rem;">
                                    <tbody id="queryInfoTable">
                                        <!-- Populated by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Workflow Timeline -->
                        <div class="card-modern" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-timeline-text-outline mr-1"></i> Query Timeline
                                </h6>
                            </div>
                            <div class="card-body py-2 px-3">
                                <div id="queryTimeline" class="timeline-vertical">
                                    <!-- Populated by JS -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Details & Response -->
                    <div class="col-lg-7">
                        <!-- Subject & Description -->
                        <div class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-file-document-outline mr-1"></i> Query Details
                                </h6>
                            </div>
                            <div class="card-body">
                                <h6 class="text-primary mb-2" id="viewSubject"></h6>
                                <div id="viewDescription" class="bg-light p-3 rounded" style="font-size: 0.9rem; white-space: pre-wrap;"></div>
                            </div>
                        </div>

                        <!-- My Response (if submitted) -->
                        <div id="responseCard" class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef; display: none;">
                            <div class="card-header bg-info text-white" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-comment-text-outline mr-1"></i> My Response
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="viewResponse" class="bg-light p-3 rounded" style="font-size: 0.9rem; white-space: pre-wrap;"></div>
                                <small class="text-muted mt-2 d-block" id="viewResponseMeta"></small>
                            </div>
                        </div>

                        <!-- Decision (if any) -->
                        <div id="decisionCard" class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef; display: none;">
                            <div class="card-header" id="decisionCardHeader" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-scale-balance mr-1"></i> Management Decision
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="mr-2"><strong>Outcome:</strong></span>
                                    <span id="viewOutcomeBadge"></span>
                                </div>
                                <div id="viewDecisionNotes" class="bg-light p-3 rounded" style="font-size: 0.9rem; white-space: pre-wrap;"></div>
                                <small class="text-muted mt-2 d-block" id="viewDecisionMeta"></small>
                            </div>
                        </div>

                        <!-- Response Form (for open queries) -->
                        <div id="responseFormCard" class="card-modern mb-3" style="border-radius: 10px; border: 2px solid #ffc107; display: none;">
                            <div class="card-header bg-warning text-dark" style="border-radius: 8px 8px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-reply mr-1"></i> Submit Your Response
                                </h6>
                            </div>
                            <div class="card-body">
                                <form id="responseForm" enctype="multipart/form-data">
                                    @csrf
                                    <input type="hidden" name="query_id" id="response_query_id">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold">Your Response <span class="text-danger">*</span></label>
                                        <textarea name="response" id="staff_response" class="form-control" rows="5"
                                                  placeholder="Please provide your detailed response to this query. Be clear and factual in your explanation..."
                                                  required style="border-radius: 8px;"></textarea>
                                        <small class="text-muted">Provide a clear and detailed response to the query raised against you.</small>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold">Supporting Documents (Optional)</label>
                                        <input type="file" name="attachments[]" id="response_attachments" class="form-control"
                                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple style="border-radius: 8px;">
                                        <small class="text-muted">Upload supporting evidence (PDF, images, Word - max 5MB each)</small>
                                    </div>
                                    <button type="submit" class="btn btn-warning btn-block" id="submitResponseBtn" style="border-radius: 8px;">
                                        <i class="mdi mdi-send mr-1"></i> Submit Response
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Attachments (if any) -->
                        <div id="attachmentsCard" class="card-modern" style="border-radius: 10px; border: 1px solid #e9ecef; display: none;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-paperclip mr-1"></i> Attachments
                                </h6>
                            </div>
                            <div class="card-body py-2">
                                <div id="attachmentsList"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Timeline CSS -->
<style>
.timeline-vertical {
    position: relative;
    padding-left: 30px;
}
.timeline-vertical::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}
.timeline-item {
    position: relative;
    padding-bottom: 15px;
    margin-bottom: 5px;
}
.timeline-item:last-child {
    padding-bottom: 0;
    margin-bottom: 0;
}
.timeline-item .timeline-dot {
    position: absolute;
    left: -24px;
    top: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: white;
}
.timeline-item .timeline-dot.completed {
    background: #28a745;
}
.timeline-item .timeline-dot.pending {
    background: #e9ecef;
    border: 2px solid #adb5bd;
}
.timeline-item .timeline-dot.pending i {
    color: #adb5bd;
}
.timeline-item .timeline-dot.warning {
    background: #ffc107;
}
.timeline-item .timeline-dot.danger {
    background: #dc3545;
}
.timeline-item .timeline-dot.current {
    background: #007bff;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(0, 123, 255, 0); }
    100% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0); }
}
.timeline-item .timeline-content {
    padding-left: 5px;
}
.timeline-item .timeline-title {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 2px;
}
.timeline-item .timeline-meta {
    font-size: 0.8rem;
    color: #6c757d;
}
</style>
@endsection

@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Initialize DataTable with filters
    var table = $('#queriesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("hr.ess.my-disciplinary.data") }}',
            data: function(d) {
                d.status = $('#statusFilter').val();
                d.severity = $('#severityFilter').val();
            }
        },
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
            emptyTable: "No disciplinary records found",
            processing: '<i class="fas fa-spinner fa-spin"></i> Loading...'
        }
    });

    // Filter change handlers
    $('#statusFilter, #severityFilter').on('change', function() {
        table.ajax.reload();
    });

    // View query button click
    $(document).on('click', '.view-query', function() {
        viewQuery($(this).data('id'));
    });

    // Respond query button click
    $(document).on('click', '.respond-query', function() {
        viewQuery($(this).data('id'));
    });

    // Submit response form
    $('#responseForm').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        var queryId = $('#response_query_id').val();

        $('#submitResponseBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Submitting...');

        $.ajax({
            url: '{{ route("hr.ess.my-disciplinary.respond", ":id") }}'.replace(':id', queryId),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#viewQueryModal').modal('hide');
                    table.ajax.reload();
                    // Refresh page to update active query alert
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    toastr.error(response.message || 'An error occurred');
                }
            },
            error: function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    Object.keys(errors).forEach(function(key) {
                        toastr.error(errors[key][0]);
                    });
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    toastr.error(xhr.responseJSON.message);
                } else {
                    toastr.error('An error occurred. Please try again.');
                }
            },
            complete: function() {
                $('#submitResponseBtn').prop('disabled', false).html('<i class="mdi mdi-send mr-1"></i> Submit Response');
            }
        });
    });
});

function viewQuery(id) {
    // Show loading state
    $('#queryInfoTable').html('<tr><td colspan="2" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>');
    $('#queryTimeline').html('<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>');
    $('#viewSubject').text('');
    $('#viewDescription').text('');
    $('#responseCard, #decisionCard, #attachmentsCard, #responseFormCard').hide();
    $('#viewQueryModal').modal('show');

    $.ajax({
        url: '{{ route("hr.ess.my-disciplinary.show", ":id") }}'.replace(':id', id),
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderQueryDetails(response.query);
                renderQueryTimeline(response.query);
            } else {
                toastr.error(response.message || 'Failed to load query details');
            }
        },
        error: function(xhr) {
            const msg = xhr.responseJSON?.message || 'Failed to load query details';
            $('#queryInfoTable').html(`<tr><td colspan="2" class="text-danger">${msg}</td></tr>`);
            toastr.error(msg);
        }
    });
}

function renderQueryDetails(data) {
    $('#viewQueryNumber').text(data.query_number || 'N/A');

    // Severity badge
    const severityColors = { minor: 'info', moderate: 'warning', major: 'danger', gross: 'dark', gross_misconduct: 'dark' };
    const severityBadge = `<span class="badge badge-${severityColors[data.severity] || 'secondary'}">${(data.severity || '').replace('_', ' ').toUpperCase()}</span>`;

    // Status badge
    const statusColors = { issued: 'warning', response_received: 'info', under_review: 'primary', closed: 'secondary' };
    const statusBadge = `<span class="badge badge-${statusColors[data.status] || 'secondary'}">${(data.status || '').replace(/_/g, ' ').toUpperCase()}</span>`;

    // Query info table
    let infoHtml = `
        <tr><th style="width: 40%;">Severity</th><td>${severityBadge}</td></tr>
        <tr><th>Status</th><td>${statusBadge}</td></tr>
        <tr><th>Incident Date</th><td>${data.incident_date_formatted || '-'}</td></tr>
        <tr><th>Response Deadline</th><td>${data.response_deadline_formatted || '-'}</td></tr>
        <tr><th>Query Date</th><td>${data.created_at_formatted || '-'}</td></tr>
        <tr><th>Issued By</th><td>${data.issued_by?.name || '-'}</td></tr>
    `;
    $('#queryInfoTable').html(infoHtml);

    // Subject and description
    $('#viewSubject').text(data.subject || '-');
    $('#viewDescription').text(data.description || '-');

    // My Response
    if (data.staff_response) {
        $('#responseCard').show();
        $('#viewResponse').text(data.staff_response);
        $('#viewResponseMeta').text(`Submitted on: ${data.response_received_at || '-'}`);
    }

    // Decision/Outcome
    if (data.outcome) {
        $('#decisionCard').show();
        const outcomeColors = {
            no_action: 'bg-secondary text-white',
            verbal_warning: 'bg-info text-white',
            warning: 'bg-warning text-dark',
            written_warning: 'bg-warning text-dark',
            final_warning: 'bg-orange text-white',
            suspension: 'bg-danger text-white',
            demotion: 'bg-danger text-white',
            termination: 'bg-dark text-white',
            dismissed: 'bg-success text-white',
            exonerated: 'bg-success text-white'
        };
        const headerClass = outcomeColors[data.outcome] || 'bg-secondary text-white';
        $('#decisionCardHeader').removeClass().addClass('card-header ' + headerClass).css('border-radius', '10px 10px 0 0');

        const outcomeBadge = `<span class="badge badge-lg ${headerClass.replace('bg-', 'badge-')}">${(data.outcome || '').replace(/_/g, ' ').toUpperCase()}</span>`;
        $('#viewOutcomeBadge').html(outcomeBadge);
        $('#viewDecisionNotes').text(data.hr_decision || 'No notes provided');
        $('#viewDecisionMeta').text(`Decided by: ${data.decided_by?.name || '-'} on ${data.decided_at || '-'}`);
    }

    // Show response form if status is 'issued'
    if (data.status === 'issued') {
        $('#responseFormCard').show();
        $('#response_query_id').val(data.id);
        $('#staff_response').val('');
    }

    // Attachments
    if (data.attachments && data.attachments.length > 0) {
        $('#attachmentsCard').show();
        let attachHtml = '';
        data.attachments.forEach(att => {
            attachHtml += `
                <div class="d-flex align-items-center py-2 border-bottom">
                    <i class="mdi mdi-file-document text-primary mr-2"></i>
                    <span class="flex-grow-1">${att.original_name || 'Document'}</span>
                    <a href="/storage/${att.file_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="mdi mdi-download"></i>
                    </a>
                </div>
            `;
        });
        $('#attachmentsList').html(attachHtml);
    }
}

function renderQueryTimeline(data) {
    let timeline = [];

    // Query Issued
    timeline.push({
        title: 'Query Issued',
        completed: true,
        user: data.issued_by?.name || 'HR',
        date: data.created_at_formatted || '-',
        icon: 'mdi-file-alert'
    });

    // Response
    if (data.response_received_at) {
        timeline.push({
            title: 'Response Submitted',
            completed: true,
            user: 'You',
            date: data.response_received_at,
            icon: 'mdi-comment-text'
        });
    } else if (data.status === 'issued') {
        timeline.push({
            title: 'Awaiting Your Response',
            completed: false,
            current: true,
            icon: 'mdi-clock-outline'
        });
    }

    // Under Review
    if (data.status === 'response_received' || data.status === 'under_review' || data.status === 'closed') {
        timeline.push({
            title: 'Under Review',
            completed: data.status === 'closed',
            current: data.status === 'response_received' || data.status === 'under_review',
            icon: 'mdi-magnify'
        });
    }

    // Decision Made
    if (data.outcome) {
        const outcomeLabel = (data.outcome || '').replace(/_/g, ' ');
        timeline.push({
            title: `Decision: ${outcomeLabel.charAt(0).toUpperCase() + outcomeLabel.slice(1)}`,
            completed: true,
            user: data.decided_by?.name || '-',
            date: data.decided_at || '-',
            icon: 'mdi-scale-balance',
            dotClass: data.outcome === 'termination' || data.outcome === 'suspension' ? 'danger' :
                      data.outcome === 'exonerated' || data.outcome === 'dismissed' || data.outcome === 'no_action' ? 'completed' : 'warning'
        });
    }

    // Render timeline
    let html = '';
    timeline.forEach(event => {
        let dotClass = event.dotClass || (event.completed ? 'completed' : (event.current ? 'current' : 'pending'));
        let icon = event.icon || (event.completed ? 'mdi-check' : 'mdi-circle-outline');

        html += `
            <div class="timeline-item">
                <div class="timeline-dot ${dotClass}">
                    <i class="mdi ${icon}"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-title">${event.title}</div>
        `;

        if (event.completed && event.user) {
            html += `<div class="timeline-meta">By ${event.user} on ${event.date}</div>`;
        } else if (!event.completed) {
            html += `<div class="timeline-meta text-muted">${event.current ? 'Action Required' : 'Pending'}</div>`;
        }

        html += `
                </div>
            </div>
        `;
    });

    $('#queryTimeline').html(html);
}
</script>
@endsection
