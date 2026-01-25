@extends('admin.layouts.app')

@section('title', 'Leave Requests')

@section('styles')
<link rel="stylesheet" href="{{ asset('plugins/chosen/chosen.min.css') }}">
@endsection

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
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Staff *</label>
                            <select class="form-control chosen-select" name="staff_id" id="staff_id" required data-placeholder="Select Staff">
                                <option value="">Select Staff</option>
                                @foreach($staffList ?? [] as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->user->name ?? '' }} ({{ $staff->employee_id ?? 'N/A' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Leave Type *</label>
                            <select class="form-control" name="leave_type_id" id="leave_type_id" required style="border-radius: 8px;">
                                <option value="">Select Leave Type</option>
                                @foreach($leaveTypes ?? [] as $type)
                                <option value="{{ $type->id }}"
                                        data-max-consecutive="{{ $type->max_consecutive_days ?? '' }}"
                                        data-min-notice="{{ $type->min_days_notice ?? 0 }}"
                                        data-requires-attachment="{{ $type->requires_attachment ? 1 : 0 }}"
                                        data-allow-half-day="{{ $type->allow_half_day ? 1 : 0 }}"
                                        data-is-paid="{{ $type->is_paid ? 1 : 0 }}"
                                        data-max-requests="{{ $type->max_requests_per_year ?? '' }}"
                                        data-name="{{ $type->name }}">
                                    {{ $type->name }}
                                </option>
                                @endforeach
                            </select>
                            <small class="text-muted" id="leaveTypeInfo"></small>
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
                            <label class="form-label" style="font-weight: 600; color: #495057;">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" id="start_date" required
                                   style="border-radius: 8px; padding: 0.75rem;" min="{{ date('Y-m-d') }}">
                            <small class="text-muted" id="minNoticeWarning"></small>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">End Date *</label>
                            <input type="date" class="form-control" name="end_date" id="end_date" required
                                   style="border-radius: 8px; padding: 0.75rem;" min="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-2 mb-3" id="halfDaySection" style="display: none;">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Half Day?</label>
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

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Reason *</label>
                            <textarea class="form-control" name="reason" id="reason" rows="3" required
                                      style="border-radius: 8px; padding: 0.75rem;" placeholder="Please provide a detailed reason for your leave request"></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Relief Staff <small class="text-muted">(Optional)</small></label>
                            <select name="relief_staff_id" id="relief_staff_id" class="form-control" style="border-radius: 8px;">
                                <option value="">Select a colleague to handle duties</option>
                                @foreach($staffList ?? [] as $staff)
                                    <option value="{{ $staff->id }}">{{ $staff->user->name ?? '' }} ({{ $staff->employee_id ?? 'N/A' }})</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Optional: Select a colleague who will cover responsibilities during absence</small>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Handover Notes <small class="text-muted">(Optional)</small></label>
                            <textarea class="form-control" name="handover_notes" id="handover_notes" rows="2"
                                      style="border-radius: 8px; padding: 0.75rem;" placeholder="Brief notes about ongoing work or handover instructions"></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Contact During Leave <small class="text-muted">(Optional)</small></label>
                            <input type="text" class="form-control" name="contact_during_leave" id="contact_during_leave"
                                   style="border-radius: 8px; padding: 0.75rem;" placeholder="Phone number or email where you can be reached">
                        </div>

                        <!-- Document Upload (Conditional) -->
                        <div class="col-md-12 mb-3" id="documentSection" style="display: none;">
                            <div class="alert alert-warning" style="border-radius: 8px;">
                                <i class="mdi mdi-file-document-outline mr-2"></i>
                                <strong>Document Required:</strong> This leave type requires supporting documentation.
                            </div>
                            <label class="form-label" style="font-weight: 600; color: #495057;">Supporting Document <span class="text-danger" id="docRequired">*</span></label>
                            <input type="file" class="form-control" name="document" id="document"
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="border-radius: 8px;">
                            <small class="text-muted">PDF, images, or Word documents. Max 5MB.</small>
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
<script src="{{ asset('plugins/chosen/chosen.jquery.min.js') }}"></script>
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
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

    // Add request button - initialize Chosen when modal opens
    $('#addRequestBtn').click(function() {
        $('#leaveRequestForm')[0].reset();
        $('#leave_request_id').val('');
        selectedLeaveType = null;
        $('#leaveTypeDetails').hide();
        $('#documentSection').hide();
        $('#halfDaySection').hide();
        $('#daysInfo').hide();
        $('#modalTitleText').text('New Leave Request');
        $('#leaveRequestModal').modal('show');

        // Initialize Chosen after modal is shown
        setTimeout(function() {
            if (typeof $.fn.chosen !== 'undefined') {
                $('.chosen-select').chosen({
                    width: '100%',
                    no_results_text: 'No staff found matching',
                    allow_single_deselect: true
                });
            }
        }, 100);
    });

    // Destroy Chosen when modal closes
    $('#leaveRequestModal').on('hidden.bs.modal', function() {
        if (typeof $.fn.chosen !== 'undefined') {
            $('.chosen-select').chosen('destroy');
        }
    });

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
            requiresAttachment: selected.data('requires-attachment') == 1,
            allowHalfDay: selected.data('allow-half-day') == 1,
            isPaid: selected.data('is-paid') == 1,
            maxRequests: selected.data('max-requests')
        };

        if (selectedLeaveType.id) {
            // Show leave type details
            var details = [];

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
                    warnings.push('<span class=\"text-danger\"><i class=\"mdi mdi-alert-circle\"></i> Exceeds maximum of ' +
                                selectedLeaveType.maxConsecutive + ' consecutive days</span>');
                    alertClass = 'alert-danger';
                }

                // Check start date against notice period
                if (selectedLeaveType.minNotice > 0) {
                    var today = new Date();
                    today.setHours(0, 0, 0, 0);
                    var minStartDate = new Date(today);
                    minStartDate.setDate(minStartDate.getDate() + parseInt(selectedLeaveType.minNotice));

                    if (start < minStartDate) {
                        warnings.push('<span class=\"text-danger\"><i class=\"mdi mdi-alert-circle\"></i> Requires ' +
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
    $('#leaveRequestForm').submit(function(e) {
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
