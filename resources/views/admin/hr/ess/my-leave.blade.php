@extends('admin.layouts.app')

@section('title', 'ESS - My Leave')

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
        @foreach($leaveTypes as $leaveType)
        @php
            $balance = $balances[$leaveType->id] ?? null;
            $available = $balance ? $balance->available : 0;
            $entitled = $balance ? $balance->total_entitled : $leaveType->max_days_per_year;
            $percentage = $entitled > 0 ? (($available / $entitled) * 100) : 0;
            $colorClass = $percentage > 50 ? 'success' : ($percentage > 20 ? 'warning' : 'danger');
        @endphp
        <div class="col-md-3 mb-3">
            <div class="card border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="text-muted mb-0">{{ $leaveType->name }}</h6>
                        <span class="badge badge-{{ $colorClass }}" style="border-radius: 6px;">
                            {{ number_format($percentage, 0) }}%
                        </span>
                    </div>
                    <h2 class="mb-1" style="font-weight: 700;">{{ number_format($available, 1) }}</h2>
                    <small class="text-muted">of {{ number_format($entitled, 1) }} days remaining</small>
                    <div class="progress mt-2" style="height: 6px; border-radius: 3px;">
                        <div class="progress-bar bg-{{ $colorClass }}" style="width: {{ min($percentage, 100) }}%"></div>
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
                <table class="table table-hover">
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
                    <tbody>
                        @forelse($leaveRequests as $request)
                        <tr>
                            <td>{{ $request->leaveType->name }}</td>
                            <td>{{ \Carbon\Carbon::parse($request->start_date)->format('M d, Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($request->end_date)->format('M d, Y') }}</td>
                            <td>{{ $request->days_requested }}</td>
                            <td>
                                @if($request->status === 'pending')
                                    <span class="badge badge-warning">Pending</span>
                                @elseif($request->status === 'approved')
                                    <span class="badge badge-success">Approved</span>
                                @elseif($request->status === 'rejected')
                                    <span class="badge badge-danger">Rejected</span>
                                @elseif($request->status === 'cancelled')
                                    <span class="badge badge-secondary">Cancelled</span>
                                @endif
                            </td>
                            <td>{{ \Carbon\Carbon::parse($request->created_at)->format('M d, Y') }}</td>
                            <td>
                                @if($request->status === 'pending')
                                <form action="{{ route('hr.ess.my-leave.cancel', $request->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Are you sure you want to cancel this leave request?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="mdi mdi-close-circle"></i> Cancel
                                    </button>
                                </form>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="mdi mdi-alert-circle-outline" style="font-size: 2rem;"></i>
                                <p class="mb-0">No leave requests found</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($leaveRequests->hasPages())
            <div class="mt-3">
                {{ $leaveRequests->links() }}
            </div>
            @endif
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
                    <!-- Leave Type Selection -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Leave Type <span class="text-danger">*</span></label>
                            <select name="leave_type_id" id="leave_type_id" class="form-control" required style="border-radius: 8px;">
                                <option value="">Select Leave Type</option>
                                @foreach($leaveTypes as $type)
                                @php
                                    $balance = $balances[$type->id] ?? null;
                                    $remaining = $balance ? $balance->available : 0;
                                @endphp
                                <option value="{{ $type->id }}"
                                        data-max-consecutive="{{ $type->max_consecutive_days ?? '' }}"
                                        data-min-notice="{{ $type->min_days_notice ?? 0 }}"
                                        data-balance="{{ $remaining }}"
                                        data-requires-attachment="{{ $type->requires_attachment ? 1 : 0 }}"
                                        data-allow-half-day="{{ $type->allow_half_day ? 1 : 0 }}"
                                        data-is-paid="{{ $type->is_paid ? 1 : 0 }}"
                                        data-max-requests="{{ $type->max_requests_per_year ?? '' }}"
                                        data-name="{{ $type->name }}">
                                    {{ $type->name }} ({{ number_format($remaining, 1) }} days available)
                                </option>
                                @endforeach
                            </select>
                            <small class="text-muted" id="leaveTypeInfo"></small>
                        </div>
                    </div>

                    <!-- Leave Type Details Alert -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="alert alert-info" id="leaveTypeDetails" style="display: none; border-radius: 8px;">
                                <h6 class="font-weight-bold mb-2"><i class="mdi mdi-information-outline mr-1"></i>Leave Type Requirements</h6>
                                <ul class="mb-0 pl-3" id="leaveTypeDetailsList"></ul>
                            </div>
                        </div>
                    </div>

                    <!-- Date Selection -->
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="font-weight-bold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="start_date" class="form-control"
                                   required style="border-radius: 8px;">
                            <small class="text-muted" id="minNoticeWarning"></small>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="font-weight-bold">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="end_date" class="form-control"
                                   required style="border-radius: 8px;">
                        </div>
                        <div class="col-md-2 mb-3" id="halfDaySection" style="display: none;">
                            <label class="font-weight-bold">Half Day?</label>
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" id="is_half_day" name="is_half_day" value="1">
                                <label class="custom-control-label" for="is_half_day">Yes</label>
                            </div>
                        </div>
                    </div>

                    <!-- Days Calculation Info -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="alert" id="daysInfo" style="display: none; border-radius: 8px;">
                                <i class="mdi mdi-calendar-check mr-2"></i>
                                <strong>Total Days:</strong> <span id="totalDays">0</span> days
                                <span id="daysWarning" class="ml-2"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Reason -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" id="reason" class="form-control" rows="3"
                                      placeholder="Please provide a detailed reason for your leave request" required
                                      style="border-radius: 8px;"></textarea>
                        </div>
                    </div>

                    <!-- Relief Staff -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Relief Staff <small class="text-muted">(Optional)</small></label>
                            <select name="relief_staff_id" id="relief_staff_id" class="form-control" style="border-radius: 8px;">
                                <option value="">Select a colleague to handle your duties</option>
                                @foreach($reliefStaff as $colleague)
                                    <option value="{{ $colleague->id }}">
                                        {{ $colleague->user->name ?? $colleague->employee_id ?? 'N/A' }} - {{ $colleague->specialization->name ?? 'N/A' }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Optional: Select a colleague who will cover your responsibilities during your absence</small>
                        </div>
                    </div>

                    <!-- Document Upload (Conditional) -->
                    <div class="row" id="documentSection" style="display: none;">
                        <div class="col-md-12 mb-3">
                            <div class="alert alert-warning" style="border-radius: 8px;">
                                <i class="mdi mdi-file-document-outline mr-2"></i>
                                <strong>Document Required:</strong> This leave type requires supporting documentation.
                            </div>
                            <label class="font-weight-bold">Supporting Document <span class="text-danger" id="docRequired">*</span></label>
                            <input type="file" name="document" id="document" class="form-control-file"
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <small class="text-muted">Accepted formats: PDF, JPG, PNG, DOC, DOCX (max 5MB)</small>
                        </div>
                    </div>

                    <!-- Contact During Leave -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Contact During Leave</label>
                            <input type="text" name="contact_during_leave" id="contact_during_leave" class="form-control"
                                   placeholder="Phone number or email for emergencies" style="border-radius: 8px;">
                            <small class="text-muted">Optional: Provide contact information in case of emergencies during your leave</small>
                        </div>
                    </div>

                    <!-- Handover Notes -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">Handover Notes</label>
                            <textarea name="handover_notes" id="handover_notes" class="form-control" rows="2"
                                      placeholder="Any work handover information or pending tasks (optional)"
                                      style="border-radius: 8px;"></textarea>
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
<script>
$(document).ready(function() {
    var selectedLeaveType = null;

    // Helper to format date as YYYY-MM-DD
    function formatDate(date) {
        var d = new Date(date);
        var month = '' + (d.getMonth() + 1);
        var day = '' + d.getDate();
        var year = d.getFullYear();

        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;

        return [year, month, day].join('-');
    }

    // Set default min date to today
    var today = formatDate(new Date());
    $('#start_date').attr('min', today);
    $('#end_date').attr('min', today);

    // Update end date min/max when start date changes
    $('#start_date').on('change', function() {
        var startVal = $(this).val();
        if (startVal) {
            $('#end_date').attr('min', startVal);
            if ($('#end_date').val() && $('#end_date').val() < startVal) {
                $('#end_date').val(startVal);
            }

            // Set max date based on max consecutive days
            if (selectedLeaveType && selectedLeaveType.maxConsecutive) {
                var maxDate = new Date(startVal);
                maxDate.setDate(maxDate.getDate() + parseInt(selectedLeaveType.maxConsecutive) - 1);
                $('#end_date').attr('max', formatDate(maxDate));
            } else {
                $('#end_date').removeAttr('max');
            }
        }
        calculateLeaveDays();
    });

    // Leave type change handler - show requirements
    $('#leave_type_id').on('change', function() {
        var selected = $(this).find(':selected');
        selectedLeaveType = {
            id: selected.val(),
            name: selected.data('name'),
            maxConsecutive: selected.data('max-consecutive'),
            minNotice: selected.data('min-notice'),
            balance: selected.data('balance'),
            requiresAttachment: selected.data('requires-attachment') == 1,
            allowHalfDay: selected.data('allow-half-day') == 1,
            isPaid: selected.data('is-paid') == 1,
            maxRequests: selected.data('max-requests')
        };

        if (selectedLeaveType.id) {
            // Show leave type details
            var details = [];

            if (selectedLeaveType.balance !== undefined) {
                details.push('<li><strong>Available Balance:</strong> ' + parseFloat(selectedLeaveType.balance).toFixed(1) + ' days</li>');
            }

            if (selectedLeaveType.maxConsecutive) {
                details.push('<li><strong>Max Consecutive Days:</strong> ' + selectedLeaveType.maxConsecutive + ' days per request</li>');
            }

            if (selectedLeaveType.minNotice > 0) {
                details.push('<li><strong>Minimum Notice:</strong> ' + selectedLeaveType.minNotice + ' days in advance</li>');
            }

            if (selectedLeaveType.allowHalfDay) {
                details.push('<li><strong>Half Day:</strong> Half-day requests are allowed</li>');
            }

            if (selectedLeaveType.isPaid) {
                details.push('<li class="text-success"><strong>Paid Leave</strong></li>');
            } else {
                details.push('<li class="text-warning"><strong>Unpaid Leave</strong></li>');
            }

            if (selectedLeaveType.requiresAttachment) {
                details.push('<li class="text-danger"><strong>Document Required:</strong> Supporting documentation must be uploaded</li>');
            }

            $('#leaveTypeDetailsList').html(details.join(''));
            $('#leaveTypeDetails').show();

            // Show/hide document section
            $('#documentSection').toggle(selectedLeaveType.requiresAttachment);
            $('#document').prop('required', selectedLeaveType.requiresAttachment);

            // Show/hide half day option
            $('#halfDaySection').toggle(selectedLeaveType.allowHalfDay);

            // Update date input min/max based on notice period and max consecutive
            updateDateMinimum();

        } else {
            $('#leaveTypeDetails').hide();
            $('#documentSection').hide();
            $('#halfDaySection').hide();
        }

        // Reset dates when leave type changes
        $('#start_date, #end_date').val('');
        $('#daysInfo').hide();
    });

    // Update date input minimum based on notice period
    function updateDateMinimum() {
        var minDate = new Date();

        if (selectedLeaveType && selectedLeaveType.minNotice > 0) {
            minDate.setDate(minDate.getDate() + parseInt(selectedLeaveType.minNotice));

            $('#minNoticeWarning').html(
                '<i class="mdi mdi-alert text-warning"></i> Requires ' + selectedLeaveType.minNotice + ' days notice'
            ).addClass('text-warning');
        } else {
            $('#minNoticeWarning').html('').removeClass('text-warning');
        }

        var minDateStr = formatDate(minDate);
        $('#start_date').attr('min', minDateStr);
        $('#end_date').attr('min', minDateStr);

        // Set max date based on max consecutive days
        if (selectedLeaveType && selectedLeaveType.maxConsecutive) {
            var startVal = $('#start_date').val();
            if (startVal) {
                var maxDate = new Date(startVal);
                maxDate.setDate(maxDate.getDate() + parseInt(selectedLeaveType.maxConsecutive) - 1);
                $('#end_date').attr('max', formatDate(maxDate));
            }
        } else {
            $('#end_date').removeAttr('max');
        }
    }

    // Calculate days when dates change
    $('#start_date, #end_date').on('change', function() {
        calculateLeaveDays();
    });

    // Half day checkbox change
    $('#is_half_day').on('change', function() {
        calculateLeaveDays();
    });

    function calculateLeaveDays() {
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();

        if (startDate && endDate && selectedLeaveType) {
            var start = new Date(startDate);
            var end = new Date(endDate);
            var days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;

            // Check if half day is selected
            var isHalfDay = $('#is_half_day').is(':checked');
            if (isHalfDay && days === 1) {
                days = 0.5;
            }

            if (days > 0) {
                var warnings = [];
                var alertClass = 'alert-info';

                // Check against max consecutive days
                if (selectedLeaveType.maxConsecutive && days > selectedLeaveType.maxConsecutive) {
                    warnings.push('<span class="text-danger"><i class="mdi mdi-alert-circle"></i> Exceeds maximum of ' +
                                selectedLeaveType.maxConsecutive + ' consecutive days</span>');
                    alertClass = 'alert-danger';
                }

                // Check against balance
                if (selectedLeaveType.balance !== undefined && days > selectedLeaveType.balance) {
                    warnings.push('<span class="text-warning"><i class="mdi mdi-alert"></i> Exceeds available balance of ' +
                                parseFloat(selectedLeaveType.balance).toFixed(1) + ' days</span>');
                    alertClass = alertClass === 'alert-danger' ? 'alert-danger' : 'alert-warning';
                }

                // Check start date against notice period
                if (selectedLeaveType.minNotice > 0) {
                    var today = new Date();
                    today.setHours(0, 0, 0, 0);
                    var minStartDate = new Date(today);
                    minStartDate.setDate(minStartDate.getDate() + parseInt(selectedLeaveType.minNotice));

                    if (start < minStartDate) {
                        warnings.push('<span class="text-danger"><i class="mdi mdi-alert-circle"></i> Requires ' +
                                    selectedLeaveType.minNotice + ' days advance notice</span>');
                        alertClass = 'alert-danger';
                    }
                }

                $('#totalDays').text(days);
                $('#daysWarning').html(warnings.length > 0 ? '<br>' + warnings.join('<br>') : '');
                $('#daysInfo').removeClass('alert-info alert-warning alert-danger').addClass(alertClass).show();
            } else if (days <= 0) {
                $('#daysInfo').hide();
                toastr.error('End date must be after start date');
            }
        }
    }

    // Submit leave request
    $('#leaveRequestForm').on('submit', function(e) {
        e.preventDefault();

        // Validate before submission
        if (!selectedLeaveType) {
            toastr.error('Please select a leave type');
            return false;
        }

        var startDate = new Date($('#start_date').val());
        var endDate = new Date($('#end_date').val());
        var days = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;

        var isHalfDay = $('#is_half_day').is(':checked');
        if (isHalfDay && days === 1) {
            days = 0.5;
        }

        // Check max consecutive days
        if (selectedLeaveType.maxConsecutive && days > selectedLeaveType.maxConsecutive) {
            toastr.error('Cannot request more than ' + selectedLeaveType.maxConsecutive + ' consecutive days for this leave type');
            return false;
        }

        // Check balance
        if (selectedLeaveType.balance !== undefined && days > selectedLeaveType.balance) {
            if (!confirm('You are requesting more days than your available balance. Do you want to proceed?')) {
                return false;
            }
        }

        // Check minimum notice
        if (selectedLeaveType.minNotice > 0) {
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var minStartDate = new Date(today);
            minStartDate.setDate(minStartDate.getDate() + parseInt(selectedLeaveType.minNotice));

            if (startDate < minStartDate) {
                toastr.error('This leave type requires ' + selectedLeaveType.minNotice + ' days advance notice');
                return false;
            }
        }

        var formData = new FormData(this);

        $.ajax({
            url: '{{ route("hr.ess.my-leave.store") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#requestLeaveModal').modal('hide');
                    $('#leaveRequestForm')[0].reset();
                    location.reload();
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
