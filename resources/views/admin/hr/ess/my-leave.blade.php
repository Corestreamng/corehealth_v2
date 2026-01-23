@extends('admin.layouts.app')

@section('title', 'ESS - My Leave')

@section('styles')
<link href="{{ asset('plugins/datatables/datatables.min.css') }}" rel="stylesheet">
<link href="{{ asset('plugins/select2/select2.min.css') }}" rel="stylesheet">
<link href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}" rel="stylesheet">
<link href="{{ asset('plugins/bootstrap-datepicker/bootstrap-datepicker.min.css') }}" rel="stylesheet">
@endsection

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                <i class="mdi mdi-calendar-account mr-2"></i>My Leave
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{ route('hr.ess.index') }}">ESS</a></li>
                    <li class="breadcrumb-item active">My Leave</li>
                </ol>
            </nav>
        </div>
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#requestLeaveModal" style="border-radius: 8px;">
            <i class="mdi mdi-plus mr-1"></i>Request Leave
        </button>
    </div>

    <!-- Leave Balance Cards -->
    <div class="row mb-4">
        @foreach($leaveBalances as $balance)
        <div class="col-md-3 mb-3">
            <div class="card border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="text-muted mb-0">{{ $balance->leaveType->name ?? 'N/A' }}</h6>
                        @php
                            $percentage = $balance->entitlement > 0
                                ? (($balance->balance_remaining / $balance->entitlement) * 100)
                                : 0;
                            $colorClass = $percentage > 50 ? 'success' : ($percentage > 20 ? 'warning' : 'danger');
                        @endphp
                        <span class="badge badge-{{ $colorClass }}" style="border-radius: 6px;">
                            {{ number_format($percentage, 0) }}%
                        </span>
                    </div>
                    <h2 class="mb-1" style="font-weight: 700;">{{ $balance->balance_remaining }}</h2>
                    <small class="text-muted">of {{ $balance->entitlement }} days remaining</small>
                    <div class="progress mt-2" style="height: 6px; border-radius: 3px;">
                        <div class="progress-bar bg-{{ $colorClass }}" style="width: {{ $percentage }}%"></div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Leave Requests Table -->
    <div class="card border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
            <h6 class="mb-0" style="font-weight: 600;">
                <i class="mdi mdi-format-list-bulleted text-primary mr-2"></i>My Leave Requests
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="leaveRequestsTable" class="table table-hover" style="width: 100%;">
                    <thead class="bg-light">
                        <tr>
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Days</th>
                            <th>Status</th>
                            <th>Requested On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Request Leave Modal -->
<div class="modal fade" id="requestLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-primary text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-calendar-plus mr-2"></i>Request Leave
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="leaveRequestForm">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Leave Type <span class="text-danger">*</span></label>
                            <select name="leave_type_id" id="leave_type_id" class="form-control select2" required>
                                <option value="">Select Leave Type</option>
                                @foreach($leaveTypes as $type)
                                @php
                                    $balance = $leaveBalances->where('leave_type_id', $type->id)->first();
                                    $remaining = $balance ? $balance->balance_remaining : 0;
                                @endphp
                                <option value="{{ $type->id }}"
                                        data-max-days="{{ $type->max_days_per_request }}"
                                        data-balance="{{ $remaining }}"
                                        data-requires-doc="{{ $type->requires_document }}">
                                    {{ $type->name }} ({{ $remaining }} days available)
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">Start Date <span class="text-danger">*</span></label>
                            <input type="text" name="start_date" id="start_date" class="form-control datepicker"
                                   placeholder="Select start date" required style="border-radius: 8px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">End Date <span class="text-danger">*</span></label>
                            <input type="text" name="end_date" id="end_date" class="form-control datepicker"
                                   placeholder="Select end date" required style="border-radius: 8px;">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="alert alert-info" id="daysInfo" style="display: none; border-radius: 8px;">
                                <i class="mdi mdi-information mr-2"></i>
                                <span id="daysInfoText"></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" id="reason" class="form-control" rows="3"
                                      placeholder="Please provide a reason for your leave request" required
                                      style="border-radius: 8px;"></textarea>
                        </div>
                    </div>
                    <div class="row" id="documentSection" style="display: none;">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Supporting Document <span class="text-danger">*</span></label>
                            <input type="file" name="document" id="document" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png" style="border-radius: 8px;">
                            <small class="text-muted">Upload PDF, JPG, or PNG (max 5MB)</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Contact During Leave</label>
                            <input type="text" name="contact_during_leave" id="contact_during_leave" class="form-control"
                                   placeholder="Phone number or email for emergencies" style="border-radius: 8px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px;">
                        <i class="mdi mdi-send mr-1"></i>Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Leave Request Modal -->
<div class="modal fade" id="viewLeaveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-info text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-eye mr-2"></i>Leave Request Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Leave Type:</strong> <span id="view_leave_type"></span></p>
                        <p><strong>Start Date:</strong> <span id="view_start_date"></span></p>
                        <p><strong>End Date:</strong> <span id="view_end_date"></span></p>
                        <p><strong>Days Requested:</strong> <span id="view_days"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> <span id="view_status"></span></p>
                        <p><strong>Requested On:</strong> <span id="view_created_at"></span></p>
                        <p><strong>Processed By:</strong> <span id="view_processed_by"></span></p>
                        <p><strong>Processed On:</strong> <span id="view_processed_at"></span></p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-12">
                        <p><strong>Reason:</strong></p>
                        <p id="view_reason" class="text-muted"></p>
                    </div>
                </div>
                <div class="row" id="view_rejection_section" style="display: none;">
                    <div class="col-md-12">
                        <div class="alert alert-danger" style="border-radius: 8px;">
                            <strong><i class="mdi mdi-alert mr-1"></i>Rejection Reason:</strong>
                            <p id="view_rejection_reason" class="mb-0 mt-2"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="view_modal_footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('plugins/datatables/datatables.min.js') }}"></script>
<script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-datepicker/bootstrap-datepicker.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Initialize plugins
    $('.select2').select2({
        theme: 'bootstrap4',
        dropdownParent: $('#requestLeaveModal')
    });

    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        startDate: new Date(),
        todayHighlight: true
    });

    // Initialize DataTable
    var table = $('#leaveRequestsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("ess.my-leave.data") }}',
        columns: [
            { data: 'leave_type', name: 'leaveType.name' },
            { data: 'start_date', name: 'start_date' },
            { data: 'end_date', name: 'end_date' },
            { data: 'days_requested', name: 'days_requested' },
            { data: 'status', name: 'status' },
            { data: 'created_at', name: 'created_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[5, 'desc']],
        language: {
            emptyTable: "No leave requests found"
        }
    });

    // Leave type change handler
    $('#leave_type_id').on('change', function() {
        var selected = $(this).find(':selected');
        var requiresDoc = selected.data('requires-doc') == 1;
        $('#documentSection').toggle(requiresDoc);
        $('#document').prop('required', requiresDoc);
    });

    // Calculate days when dates change
    $('#start_date, #end_date').on('change', function() {
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();

        if (startDate && endDate) {
            var start = new Date(startDate);
            var end = new Date(endDate);
            var days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;

            if (days > 0) {
                var selected = $('#leave_type_id').find(':selected');
                var maxDays = selected.data('max-days') || 999;
                var balance = selected.data('balance') || 0;

                var message = 'You are requesting ' + days + ' day(s) of leave.';

                if (days > maxDays) {
                    message = '<span class="text-danger">Warning: Maximum ' + maxDays + ' days allowed per request.</span>';
                } else if (days > balance) {
                    message = '<span class="text-warning">Warning: You only have ' + balance + ' days available.</span>';
                }

                $('#daysInfoText').html(message);
                $('#daysInfo').show();
            } else {
                $('#daysInfo').hide();
            }
        }
    });

    // Submit leave request
    $('#leaveRequestForm').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);

        $.ajax({
            url: '{{ route("ess.my-leave.store") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#requestLeaveModal').modal('hide');
                    $('#leaveRequestForm')[0].reset();
                    table.ajax.reload();
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

    // View leave request
    $(document).on('click', '.view-request', function() {
        var id = $(this).data('id');

        $.get('{{ url("ess/my-leave") }}/' + id, function(data) {
            $('#view_leave_type').text(data.leave_type ? data.leave_type.name : 'N/A');
            $('#view_start_date').text(data.start_date);
            $('#view_end_date').text(data.end_date);
            $('#view_days').text(data.days_requested + ' days');
            $('#view_created_at').text(data.created_at);
            $('#view_reason').text(data.reason);

            var statusClass = {
                'pending': 'warning',
                'approved': 'success',
                'rejected': 'danger',
                'cancelled': 'secondary'
            }[data.status] || 'secondary';

            $('#view_status').html('<span class="badge badge-' + statusClass + '">' + data.status.charAt(0).toUpperCase() + data.status.slice(1) + '</span>');

            if (data.processed_by) {
                $('#view_processed_by').text(data.processed_by.firstname + ' ' + data.processed_by.surname);
                $('#view_processed_at').text(data.processed_at);
            } else {
                $('#view_processed_by').text('N/A');
                $('#view_processed_at').text('N/A');
            }

            if (data.status === 'rejected' && data.rejection_reason) {
                $('#view_rejection_reason').text(data.rejection_reason);
                $('#view_rejection_section').show();
            } else {
                $('#view_rejection_section').hide();
            }

            // Show cancel button for pending requests
            var footer = $('#view_modal_footer');
            footer.find('.btn-cancel-request').remove();

            if (data.status === 'pending') {
                footer.prepend(
                    '<button type="button" class="btn btn-danger btn-cancel-request" data-id="' + data.id + '" style="border-radius: 8px;">' +
                    '<i class="mdi mdi-close mr-1"></i>Cancel Request</button>'
                );
            }

            $('#viewLeaveModal').modal('show');
        });
    });

    // Cancel leave request
    $(document).on('click', '.btn-cancel-request, .cancel-request', function() {
        var id = $(this).data('id');

        if (confirm('Are you sure you want to cancel this leave request?')) {
            $.ajax({
                url: '{{ url("ess/my-leave") }}/' + id + '/cancel',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        $('#viewLeaveModal').modal('hide');
                        table.ajax.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('An error occurred. Please try again.');
                }
            });
        }
    });
});
</script>
@endsection
