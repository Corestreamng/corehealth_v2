@extends('admin.layouts.app')

@section('title', 'Leave Requests')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-calendar-check mr-2"></i>Leave Requests
                    </h3>
                    <p class="text-muted mb-0">Manage and approve staff leave requests</p>
                </div>
                <div class="d-flex">
                    <select id="statusFilter" class="form-control mr-2" style="border-radius: 8px; width: 150px;">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    @can('leave-request.create')
                    <button type="button" class="btn btn-primary" id="addRequestBtn" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                        <i class="mdi mdi-plus mr-1"></i> New Request
                    </button>
                    @endcan
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Pending</h6>
                                    <h3 class="mb-0" id="pendingCount">0</h3>
                                </div>
                                <i class="mdi mdi-clock-outline" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Approved</h6>
                                    <h3 class="mb-0" id="approvedCount">0</h3>
                                </div>
                                <i class="mdi mdi-check-circle-outline" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Rejected</h6>
                                    <h3 class="mb-0" id="rejectedCount">0</h3>
                                </div>
                                <i class="mdi mdi-close-circle-outline" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">On Leave Today</h6>
                                    <h3 class="mb-0" id="onLeaveCount">0</h3>
                                </div>
                                <i class="mdi mdi-account-off-outline" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Requests Table Card -->
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                    <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-format-list-bulleted mr-2" style="color: var(--primary-color);"></i>
                        Leave Requests
                    </h5>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table id="leaveRequestsTable" class="table table-hover" style="width: 100%;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="font-weight: 600; color: #495057;">SN</th>
                                    <th style="font-weight: 600; color: #495057;">Staff</th>
                                    <th style="font-weight: 600; color: #495057;">Leave Type</th>
                                    <th style="font-weight: 600; color: #495057;">Period</th>
                                    <th style="font-weight: 600; color: #495057;">Days</th>
                                    <th style="font-weight: 600; color: #495057;">Status</th>
                                    <th style="font-weight: 600; color: #495057;">Applied On</th>
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

<!-- Create/Edit Leave Request Modal -->
<div class="modal fade" id="leaveRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-calendar-plus mr-2" style="color: var(--primary-color);"></i>
                    <span id="modalTitleText">New Leave Request</span>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="leaveRequestForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="leave_request_id" id="leave_request_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Staff *</label>
                            <select class="form-control select2" name="staff_id" id="staff_id" required style="width: 100%;">
                                <option value="">Select Staff</option>
                                @foreach($staffList ?? [] as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->user->firstname ?? '' }} {{ $staff->user->surname ?? '' }} ({{ $staff->employee_id ?? 'N/A' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Leave Type *</label>
                            <select class="form-control" name="leave_type_id" id="leave_type_id" required style="border-radius: 8px;">
                                <option value="">Select Leave Type</option>
                                @foreach($leaveTypes ?? [] as $type)
                                <option value="{{ $type->id }}" data-max-days="{{ $type->max_days_per_year }}" data-requires-attachment="{{ $type->requires_attachment }}">
                                    {{ $type->name }} (Max: {{ $type->max_days_per_year }} days)
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" id="start_date" required
                                   style="border-radius: 8px; padding: 0.75rem;" min="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">End Date *</label>
                            <input type="date" class="form-control" name="end_date" id="end_date" required
                                   style="border-radius: 8px; padding: 0.75rem;" min="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Reason *</label>
                            <textarea class="form-control" name="reason" id="reason" rows="3" required
                                      style="border-radius: 8px; padding: 0.75rem;" placeholder="Provide reason for leave request"></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Supporting Documents</label>
                            <input type="file" class="form-control" name="attachments[]" id="attachments" multiple
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="border-radius: 8px;">
                            <small class="text-muted">PDF, images, or Word documents. Max 5MB each.</small>
                        </div>
                    </div>

                    <!-- Leave Balance Info -->
                    <div id="balanceInfo" class="alert alert-info d-none" style="border-radius: 8px;">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        <span id="balanceText"></span>
                    </div>

                    <!-- Days Calculation -->
                    <div id="daysInfo" class="alert alert-secondary d-none" style="border-radius: 8px;">
                        <i class="mdi mdi-calendar-range mr-1"></i>
                        <strong>Duration:</strong> <span id="daysText">0</span> working days
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitRequestBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-check mr-1"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Leave Request Modal -->
<div class="modal fade" id="viewRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-eye mr-2" style="color: var(--primary-color);"></i>
                    Leave Request Details
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <div id="requestDetails"></div>
            </div>
            <div class="modal-footer justify-content-between" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                <div id="actionButtons"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve/Reject Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header" id="actionModalHeader" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title text-white" id="actionModalTitle"></h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="actionForm">
                <div class="modal-body" style="padding: 1.5rem;">
                    <input type="hidden" id="actionRequestId">
                    <input type="hidden" id="actionType">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Comments</label>
                        <textarea class="form-control" name="comments" id="actionComments" rows="3"
                                  style="border-radius: 8px; padding: 0.75rem;" placeholder="Add any comments (optional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn" id="actionSubmitBtn" style="border-radius: 8px;"></button>
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
        dropdownParent: $('#leaveRequestModal'),
        placeholder: 'Select Staff',
        allowClear: true
    });

    // Initialize DataTable
    const table = $('#leaveRequestsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('hr.leave-requests.index') }}",
            data: function(d) {
                d.status = $('#statusFilter').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'staff_name', name: 'staff.user.firstname' },
            { data: 'leave_type', name: 'leaveType.name' },
            { data: 'period', name: 'start_date' },
            { data: 'days_requested', name: 'days_requested' },
            { data: 'status_badge', name: 'status', orderable: false },
            { data: 'created_at', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[6, 'desc']],
        language: {
            emptyTable: "No leave requests found",
            processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>'
        }
    });

    // Load stats
    function loadStats() {
        $.get("{{ route('hr.leave-requests.index') }}", { stats: true }, function(data) {
            $('#pendingCount').text(data.pending || 0);
            $('#approvedCount').text(data.approved || 0);
            $('#rejectedCount').text(data.rejected || 0);
            $('#onLeaveCount').text(data.on_leave_today || 0);
        });
    }
    loadStats();

    // Status filter change
    $('#statusFilter').change(function() {
        table.ajax.reload();
    });

    // Add request button
    $('#addRequestBtn').click(function() {
        $('#leaveRequestForm')[0].reset();
        $('#leave_request_id').val('');
        $('#staff_id').val('').trigger('change');
        $('#balanceInfo').addClass('d-none');
        $('#daysInfo').addClass('d-none');
        $('#modalTitleText').text('New Leave Request');
        $('#leaveRequestModal').modal('show');
    });

    // Calculate days when dates change
    $('#start_date, #end_date').change(function() {
        const startDate = $('#start_date').val();
        const endDate = $('#end_date').val();

        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);

            if (end >= start) {
                // Calculate working days (excluding weekends)
                let days = 0;
                let current = new Date(start);
                while (current <= end) {
                    const dayOfWeek = current.getDay();
                    if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                        days++;
                    }
                    current.setDate(current.getDate() + 1);
                }

                $('#daysText').text(days);
                $('#daysInfo').removeClass('d-none');
            }
        }
    });

    // Load leave balance when staff and type selected
    $('#staff_id, #leave_type_id').change(function() {
        const staffId = $('#staff_id').val();
        const leaveTypeId = $('#leave_type_id').val();

        if (staffId && leaveTypeId) {
            $.get("{{ url('/hr/leave-balances') }}/" + staffId + "/" + leaveTypeId, function(data) {
                const available = data.entitled_days - data.used_days - data.pending_days + data.carried_forward;
                $('#balanceText').html(
                    '<strong>Balance:</strong> ' + available + ' days available ' +
                    '(Entitled: ' + data.entitled_days + ', Used: ' + data.used_days +
                    ', Pending: ' + data.pending_days + ', Carried: ' + data.carried_forward + ')'
                );
                $('#balanceInfo').removeClass('d-none');
            }).fail(function() {
                $('#balanceText').html('No balance record found for this leave type');
                $('#balanceInfo').removeClass('d-none').removeClass('alert-info').addClass('alert-warning');
            });
        }
    });

    // Submit leave request
    $('#leaveRequestForm').submit(function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const id = $('#leave_request_id').val();
        const url = id ? "{{ route('hr.leave-requests.index') }}/" + id : "{{ route('hr.leave-requests.store') }}";

        $('#submitRequestBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Submitting...');

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#leaveRequestModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Leave request submitted successfully');
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
                $('#submitRequestBtn').prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Submit Request');
            }
        });
    });

    // View request details
    $(document).on('click', '.view-btn', function() {
        const id = $(this).data('id');

        $.get("{{ route('hr.leave-requests.index') }}/" + id, function(data) {
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Staff:</strong> ${data.staff_name}</p>
                        <p><strong>Leave Type:</strong> ${data.leave_type}</p>
                        <p><strong>Period:</strong> ${data.start_date} to ${data.end_date}</p>
                        <p><strong>Days:</strong> ${data.days_requested}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> ${data.status_badge}</p>
                        <p><strong>Applied On:</strong> ${data.created_at}</p>
                        ${data.approved_by ? '<p><strong>Actioned By:</strong> ' + data.approved_by + '</p>' : ''}
                        ${data.approved_at ? '<p><strong>Actioned On:</strong> ' + data.approved_at + '</p>' : ''}
                    </div>
                    <div class="col-12">
                        <p><strong>Reason:</strong></p>
                        <p class="border p-2 rounded bg-light">${data.reason || '-'}</p>
                        ${data.approver_comments ? '<p><strong>Comments:</strong></p><p class="border p-2 rounded bg-light">' + data.approver_comments + '</p>' : ''}
                    </div>
                </div>
            `;
            $('#requestDetails').html(html);

            // Action buttons for pending requests
            let actions = '';
            if (data.status === 'pending') {
                @can('leave-request.approve')
                actions += '<button class="btn btn-success mr-2 approve-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-check mr-1"></i> Approve</button>';
                @endcan
                @can('leave-request.reject')
                actions += '<button class="btn btn-danger reject-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-close mr-1"></i> Reject</button>';
                @endcan
            }
            $('#actionButtons').html(actions);

            $('#viewRequestModal').modal('show');
        });
    });

    // Approve button click
    $(document).on('click', '.approve-btn', function() {
        const id = $(this).data('id');
        $('#actionRequestId').val(id);
        $('#actionType').val('approve');
        $('#actionModalHeader').removeClass('bg-danger').addClass('bg-success');
        $('#actionModalTitle').html('<i class="mdi mdi-check-circle mr-2"></i>Approve Leave Request');
        $('#actionSubmitBtn').removeClass('btn-danger').addClass('btn-success').html('<i class="mdi mdi-check mr-1"></i> Approve');
        $('#actionComments').val('');
        $('#viewRequestModal').modal('hide');
        $('#actionModal').modal('show');
    });

    // Reject button click
    $(document).on('click', '.reject-btn', function() {
        const id = $(this).data('id');
        $('#actionRequestId').val(id);
        $('#actionType').val('reject');
        $('#actionModalHeader').removeClass('bg-success').addClass('bg-danger');
        $('#actionModalTitle').html('<i class="mdi mdi-close-circle mr-2"></i>Reject Leave Request');
        $('#actionSubmitBtn').removeClass('btn-success').addClass('btn-danger').html('<i class="mdi mdi-close mr-1"></i> Reject');
        $('#actionComments').val('');
        $('#viewRequestModal').modal('hide');
        $('#actionModal').modal('show');
    });

    // Submit action
    $('#actionForm').submit(function(e) {
        e.preventDefault();

        const id = $('#actionRequestId').val();
        const action = $('#actionType').val();
        const comments = $('#actionComments').val();

        $.ajax({
            url: "{{ route('hr.leave-requests.index') }}/" + id + "/" + action,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                comments: comments
            },
            success: function(response) {
                $('#actionModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Action completed successfully');
            },
            error: function(xhr) {
                let message = 'An error occurred';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                toastr.error(message);
            }
        });
    });

    // Cancel request
    $(document).on('click', '.cancel-btn', function() {
        const id = $(this).data('id');

        if (confirm('Are you sure you want to cancel this leave request?')) {
            $.ajax({
                url: "{{ route('hr.leave-requests.index') }}/" + id + "/cancel",
                method: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    table.ajax.reload();
                    loadStats();
                    toastr.success(response.message || 'Leave request cancelled');
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to cancel request');
                }
            });
        }
    });
});
</script>
@endsection
