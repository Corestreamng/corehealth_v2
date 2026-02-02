@extends('admin.layouts.app')

@section('title', 'Staff Suspensions')

@section('style')
<style>
    /* Fix Select2 z-index issue in modals */
    .select2-container--open {
        z-index: 99999 !important;
    }
    .select2-dropdown {
        z-index: 99999 !important;
    }
    /* Fix Select2 search input focus in Bootstrap modals */
    .select2-search__field {
        z-index: 99999 !important;
    }
    .select2-container--open .select2-search--dropdown .select2-search__field {
        pointer-events: auto !important;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-account-lock mr-2"></i>Staff Suspensions
                    </h3>
                    <p class="text-muted mb-0">Manage staff suspensions and login access</p>
                </div>
                <div class="d-flex">
                    <select id="statusFilter" class="form-control mr-2" style="border-radius: 8px; width: 150px;">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="lifted">Lifted</option>
                        <option value="expired">Expired</option>
                    </select>
                    @can('suspension.create')
                    <button type="button" class="btn btn-danger" id="addSuspensionBtn" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                        <i class="mdi mdi-plus mr-1"></i> New Suspension
                    </button>
                    @endcan
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Currently Suspended</h6>
                                    <h3 class="mb-0" id="activeCount">0</h3>
                                </div>
                                <i class="mdi mdi-account-lock" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Lifted This Month</h6>
                                    <h3 class="mb-0" id="liftedCount">0</h3>
                                </div>
                                <i class="mdi mdi-lock-open-outline" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Total This Year</h6>
                                    <h3 class="mb-0" id="totalCount">0</h3>
                                </div>
                                <i class="mdi mdi-history" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suspensions Table Card -->
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                    <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-format-list-bulleted mr-2" style="color: var(--primary-color);"></i>
                        Suspension Records
                    </h5>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table id="suspensionsTable" class="table table-hover" style="width: 100%;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="font-weight: 600; color: #495057;">SN</th>
                                    <th style="font-weight: 600; color: #495057;">Staff</th>
                                    <th style="font-weight: 600; color: #495057;">Reason</th>
                                    <th style="font-weight: 600; color: #495057;">Start Date</th>
                                    <th style="font-weight: 600; color: #495057;">End Date</th>
                                    <th style="font-weight: 600; color: #495057;">Days</th>
                                    <th style="font-weight: 600; color: #495057;">With Pay</th>
                                    <th style="font-weight: 600; color: #495057;">Status</th>
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

<!-- New Suspension Modal -->
<div class="modal fade" id="suspensionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-account-lock mr-2"></i>
                    Create Staff Suspension
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="suspensionForm">
                @csrf
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Staff Member *</label>
                        <select class="form-control select2" name="staff_id" id="staff_id" required style="width: 100%;">
                            <option value="">Select Staff</option>
                            @foreach($staffList ?? [] as $staff)
                            <option value="{{ $staff->id }}">{{ $staff->user->firstname ?? '' }} {{ $staff->user->surname ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Related Query (Optional)</label>
                        <select class="form-control" name="disciplinary_query_id" id="disciplinary_query_id" style="border-radius: 8px;">
                            <option value="">No related query</option>
                        </select>
                        <small class="text-muted">Link to an existing disciplinary query</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #495057;">Start Date *</label>
                                <input type="date" class="form-control" name="start_date" id="start_date" required
                                       style="border-radius: 8px; padding: 0.75rem;" value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600; color: #495057;">End Date *</label>
                                <input type="date" class="form-control" name="end_date" id="end_date" required
                                       style="border-radius: 8px; padding: 0.75rem;">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Reason *</label>
                        <textarea class="form-control" name="reason" id="reason" rows="3" required
                                  style="border-radius: 8px; padding: 0.75rem;" placeholder="Reason for suspension"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Suspension Message (Login)</label>
                        <input type="text" class="form-control" name="suspension_message" id="suspension_message"
                               style="border-radius: 8px; padding: 0.75rem;" placeholder="Message shown when staff tries to login">
                        <small class="text-muted">This message will be displayed to the staff when they attempt to login</small>
                    </div>

                    <div class="custom-control custom-switch mb-3">
                        <input type="checkbox" class="custom-control-input" id="is_paid" name="is_paid">
                        <label class="custom-control-label" for="is_paid" style="font-weight: 600; color: #495057;">
                            Suspension with Pay
                        </label>
                    </div>

                    <div class="alert alert-danger" style="border-radius: 8px;">
                        <i class="mdi mdi-alert mr-1"></i>
                        <strong>Warning:</strong> This will immediately block the staff member from logging into the system.
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="submitSuspensionBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-lock mr-1"></i> Create Suspension
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Lift Suspension Modal -->
<div class="modal fade" id="liftModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-success text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-lock-open mr-2"></i>
                    Lift Suspension
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="liftForm">
                @csrf
                <input type="hidden" id="lift_suspension_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <p>Are you sure you want to lift the suspension for <strong id="liftStaffName"></strong>?</p>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Notes</label>
                        <textarea class="form-control" name="lift_notes" id="lift_notes" rows="2"
                                  style="border-radius: 8px; padding: 0.75rem;" placeholder="Optional notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-success" id="liftSuspensionBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-lock-open mr-1"></i> Lift Suspension
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Suspension Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 900px;" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-account-lock mr-2" style="color: var(--primary-color);"></i>
                    Suspension Details - <span id="viewSuspensionNumber"></span>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem; max-height: 80vh; overflow-y: auto;">
                <div class="row">
                    <!-- Left Column: Info & Timeline -->
                    <div class="col-lg-5">
                        <!-- Employee Summary Card -->
                        <div class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-account mr-1"></i> Employee Information
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0" style="font-size: 0.9rem;">
                                    <tbody id="suspensionInfoTable">
                                        <!-- Populated by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Suspension Timeline -->
                        <div class="card-modern" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-timeline-text-outline mr-1"></i> Suspension Timeline
                                </h6>
                            </div>
                            <div class="card-body py-2 px-3">
                                <div id="suspensionTimeline" class="timeline-vertical">
                                    <!-- Populated by JS -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Details -->
                    <div class="col-lg-7">
                        <!-- Suspension Type Card -->
                        <div id="suspensionTypeCard" class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #e9ecef;">
                            <div class="card-header" id="suspensionTypeHeader" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem; background-color: #dc3545; color: white;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-file-document-outline mr-1"></i> Suspension Details
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <span class="mr-2"><strong>Type:</strong></span>
                                    <span id="viewSuspTypeBadge"></span>
                                    <span class="ml-3 mr-2"><strong>Pay Status:</strong></span>
                                    <span id="viewPayBadge"></span>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <p class="mb-1 text-muted">Duration</p>
                                        <h5 id="viewDuration" class="mb-0"></h5>
                                    </div>
                                    <div class="col-6">
                                        <p class="mb-1 text-muted">Days Remaining</p>
                                        <h5 id="viewDaysRemaining" class="mb-0"></h5>
                                    </div>
                                </div>
                                <div id="viewReasonSection">
                                    <label class="text-muted mb-1" style="font-size: 0.85rem;">Reason for Suspension</label>
                                    <div id="viewSuspReason" class="bg-light p-3 rounded" style="font-size: 0.9rem; white-space: pre-wrap;"></div>
                                </div>
                                <small class="text-muted mt-2 d-block" id="viewSuspMeta"></small>
                            </div>
                        </div>

                        <!-- Login Message Card -->
                        <div id="loginMessageCard" class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #ffc107; display: none;">
                            <div class="card-header bg-warning text-dark" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-message-alert mr-1"></i> Login Block Message
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-2" style="font-size: 0.85rem;">This message is displayed when the staff attempts to log in:</p>
                                <div id="viewLoginMessage" class="bg-light p-3 rounded border-left border-warning" style="font-size: 0.9rem; border-left-width: 4px !important;"></div>
                            </div>
                        </div>

                        <!-- Lifted Card -->
                        <div id="liftedCard" class="card-modern mb-3" style="border-radius: 10px; border: 1px solid #28a745; display: none;">
                            <div class="card-header bg-success text-white" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-lock-open mr-1"></i> Suspension Lifted
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="viewLiftedInfo"></div>
                            </div>
                        </div>

                        <!-- Related Query Card -->
                        <div id="relatedQueryCard" class="card-modern" style="border-radius: 10px; border: 1px solid #e9ecef; display: none;">
                            <div class="card-header bg-light" style="border-radius: 10px 10px 0 0; padding: 0.75rem 1rem;">
                                <h6 class="mb-0" style="font-weight: 600;">
                                    <i class="mdi mdi-link mr-1"></i> Related Disciplinary Query
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="viewSuspRelatedQuery"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                <div id="suspensionActionButtons"></div>
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
.timeline-item .timeline-dot.danger {
    background: #dc3545;
}
.timeline-item .timeline-dot.warning {
    background: #ffc107;
}
.timeline-item .timeline-dot.current {
    background: #dc3545;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
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
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<script>
$(function() {
    // Fix Bootstrap modal enforceFocus to allow Select2 to work properly
    $.fn.modal.Constructor.prototype._enforceFocus = function() {};

    // Initialize Select2 on modal shown (with check for availability)
    $('#suspensionModal').on('shown.bs.modal', function () {
        if (typeof $.fn.select2 !== 'undefined' && !$('#staff_id').hasClass('select2-hidden-accessible')) {
            $('#staff_id').select2({
                dropdownParent: $('#suspensionModal'),
                placeholder: 'Select Staff',
                allowClear: true
            });
        }
    });

    // Initialize DataTable
    const table = $('#suspensionsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('hr.suspensions.index') }}",
            data: function(d) {
                d.status = $('#statusFilter').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'staff_name', name: 'staff.user.firstname' },
            { data: 'reason', name: 'reason',
              render: function(data) {
                  return data ? (data.length > 40 ? data.substring(0, 40) + '...' : data) : '-';
              }
            },
            { data: 'start_date', name: 'start_date' },
            { data: 'end_date', name: 'end_date' },
            { data: 'days', name: 'days', orderable: false },
            { data: 'is_paid', name: 'is_paid',
              render: function(data) {
                  return data ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>';
              }
            },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[3, 'desc']],
        language: {
            emptyTable: "No suspension records found",
            processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>'
        }
    });

    // Load stats
    function loadStats() {
        $.get("{{ route('hr.suspensions.index') }}", { stats: true }, function(data) {
            $('#activeCount').text(data.active || 0);
            $('#liftedCount').text(data.lifted || 0);
            $('#totalCount').text(data.total || 0);
        });
    }
    loadStats();

    // Status filter
    $('#statusFilter').change(function() {
        table.ajax.reload();
    });

    // Load staff queries when staff selected
    $('#staff_id').change(function() {
        const staffId = $(this).val();
        if (staffId) {
            $.get("{{ url('/hr/disciplinary') }}", { staff_id: staffId, status: 'closed' }, function(data) {
                let options = '<option value="">No related query</option>';
                if (data.data) {
                    data.data.forEach(function(query) {
                        options += '<option value="' + query.id + '">' + query.query_number + ' - ' + query.subject + '</option>';
                    });
                }
                $('#disciplinary_query_id').html(options);
            });
        }
    });

    // Add suspension button
    $('#addSuspensionBtn').click(function() {
        $('#suspensionForm')[0].reset();
        if ($('#staff_id').hasClass('select2-hidden-accessible')) {
            $('#staff_id').val('').trigger('change');
        }
        $('#suspensionModal').modal('show');
    });

    // Submit suspension
    $('#suspensionForm').submit(function(e) {
        e.preventDefault();

        $('#submitSuspensionBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...');

        $.ajax({
            url: "{{ route('hr.suspensions.store') }}",
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                staff_id: $('#staff_id').val(),
                disciplinary_query_id: $('#disciplinary_query_id').val() || null,
                start_date: $('#start_date').val(),
                end_date: $('#end_date').val(),
                reason: $('#reason').val(),
                suspension_message: $('#suspension_message').val(),
                is_paid: $('#is_paid').is(':checked') ? 1 : 0
            },
            success: function(response) {
                $('#suspensionModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Suspension created successfully');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to create suspension');
            },
            complete: function() {
                $('#submitSuspensionBtn').prop('disabled', false).html('<i class="mdi mdi-lock mr-1"></i> Create Suspension');
            }
        });
    });

    // View suspension button - with timeline
    $(document).on('click', '.view-btn', function() {
        const id = $(this).data('id');

        $.get("{{ route('hr.suspensions.index') }}/" + id, function(data) {
            // Set header
            $('#viewSuspensionNumber').text(data.suspension_number || 'SUSP-' + data.id);

            // Populate info table
            let infoHtml = `
                <tr><td class="border-0 py-2 pl-3"><i class="mdi mdi-account text-muted mr-2"></i>Staff</td>
                    <td class="border-0 py-2 font-weight-bold">${data.staff_name}</td></tr>
                <tr><td class="border-0 py-2 pl-3"><i class="mdi mdi-badge-account text-muted mr-2"></i>Employee ID</td>
                    <td class="border-0 py-2">${data.employee_id || '-'}</td></tr>
                <tr><td class="border-0 py-2 pl-3"><i class="mdi mdi-calendar-start text-muted mr-2"></i>Start Date</td>
                    <td class="border-0 py-2">${data.start_date}</td></tr>
                <tr><td class="border-0 py-2 pl-3"><i class="mdi mdi-calendar-end text-muted mr-2"></i>End Date</td>
                    <td class="border-0 py-2">${data.end_date}</td></tr>
                <tr><td class="border-0 py-2 pl-3"><i class="mdi mdi-flag text-muted mr-2"></i>Status</td>
                    <td class="border-0 py-2">${data.status_badge}</td></tr>
            `;
            $('#suspensionInfoTable').html(infoHtml);

            // Details card
            $('#viewSuspTypeBadge').html(data.type_badge);
            $('#viewPayBadge').html(data.is_paid
                ? '<span class="badge badge-success">With Pay</span>'
                : '<span class="badge badge-secondary">Without Pay</span>');
            $('#viewDuration').text(data.days + ' days');

            // Calculate days remaining
            const today = new Date();
            const endDate = new Date(data.end_date_raw || data.end_date);
            const daysRemaining = Math.ceil((endDate - today) / (1000 * 60 * 60 * 24));
            if (data.status === 'lifted') {
                $('#viewDaysRemaining').html('<span class="text-success">Lifted</span>');
            } else if (daysRemaining <= 0) {
                $('#viewDaysRemaining').html('<span class="text-muted">Expired</span>');
            } else {
                $('#viewDaysRemaining').html(`<span class="text-danger">${daysRemaining} days</span>`);
            }

            $('#viewSuspReason').text(data.reason);
            $('#viewSuspMeta').html(`<i class="mdi mdi-account mr-1"></i>Issued by ${data.issued_by} on ${data.created_at || data.start_date}`);

            // Build timeline
            let timelineHtml = '';

            // Step 1: Suspension Issued
            timelineHtml += `
                <div class="timeline-item">
                    <div class="timeline-dot danger"><i class="mdi mdi-lock"></i></div>
                    <div class="timeline-content">
                        <div class="timeline-title">Suspension Issued</div>
                        <div class="timeline-meta">${data.start_date} by ${data.issued_by}</div>
                    </div>
                </div>
            `;

            // Step 2: Login Blocked
            timelineHtml += `
                <div class="timeline-item">
                    <div class="timeline-dot danger"><i class="mdi mdi-account-lock"></i></div>
                    <div class="timeline-content">
                        <div class="timeline-title">System Access Blocked</div>
                        <div class="timeline-meta">Staff cannot log in during suspension</div>
                    </div>
                </div>
            `;

            // Step 3: Current Status
            if (data.status === 'lifted') {
                timelineHtml += `
                    <div class="timeline-item">
                        <div class="timeline-dot completed"><i class="mdi mdi-lock-open"></i></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Suspension Lifted</div>
                            <div class="timeline-meta">${data.lifted_at} by ${data.lifted_by}</div>
                        </div>
                    </div>
                `;
                timelineHtml += `
                    <div class="timeline-item">
                        <div class="timeline-dot completed"><i class="mdi mdi-check-circle"></i></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Access Restored</div>
                            <div class="timeline-meta">Staff can now log in normally</div>
                        </div>
                    </div>
                `;
            } else if (daysRemaining <= 0) {
                timelineHtml += `
                    <div class="timeline-item">
                        <div class="timeline-dot warning"><i class="mdi mdi-clock-alert"></i></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Suspension Period Ended</div>
                            <div class="timeline-meta">Awaiting formal lift</div>
                        </div>
                    </div>
                `;
                timelineHtml += `
                    <div class="timeline-item">
                        <div class="timeline-dot pending"><i class="mdi mdi-lock-open"></i></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Pending: Lift Suspension</div>
                            <div class="timeline-meta">Click "Lift Suspension" to restore access</div>
                        </div>
                    </div>
                `;
            } else {
                timelineHtml += `
                    <div class="timeline-item">
                        <div class="timeline-dot current"><i class="mdi mdi-clock"></i></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Suspension Active</div>
                            <div class="timeline-meta">${daysRemaining} days remaining until ${data.end_date}</div>
                        </div>
                    </div>
                `;
                timelineHtml += `
                    <div class="timeline-item">
                        <div class="timeline-dot pending"><i class="mdi mdi-lock-open"></i></div>
                        <div class="timeline-content">
                            <div class="timeline-title">Scheduled End</div>
                            <div class="timeline-meta">Auto-expires on ${data.end_date}</div>
                        </div>
                    </div>
                `;
            }

            $('#suspensionTimeline').html(timelineHtml);

            // Login Message Card
            if (data.suspension_message) {
                $('#loginMessageCard').show();
                $('#viewLoginMessage').text(data.suspension_message);
            } else {
                $('#loginMessageCard').hide();
            }

            // Lifted Card
            if (data.lifted_at) {
                $('#liftedCard').show();
                $('#viewLiftedInfo').html(`
                    <div class="d-flex align-items-center mb-2">
                        <i class="mdi mdi-check-circle text-success mr-2" style="font-size: 1.5rem;"></i>
                        <div>
                            <strong>Suspension was lifted early</strong><br>
                            <small class="text-muted">By ${data.lifted_by} on ${data.lifted_at}</small>
                        </div>
                    </div>
                    ${data.lift_reason ? `<div class="bg-light p-2 rounded mt-2"><small class="text-muted">Reason:</small><br>${data.lift_reason}</div>` : ''}
                `);
            } else {
                $('#liftedCard').hide();
            }

            // Related Query Card
            if (data.disciplinary_query) {
                $('#relatedQueryCard').show();
                $('#viewSuspRelatedQuery').html(`
                    <a href="{{ url('/hr/disciplinary') }}/${data.disciplinary_query.id}" class="text-primary">
                        <i class="mdi mdi-open-in-new mr-1"></i>
                        ${data.disciplinary_query.query_number} - ${data.disciplinary_query.subject}
                    </a>
                `);
            } else {
                $('#relatedQueryCard').hide();
            }

            // Action buttons
            let actions = '';
            if (data.status === 'active') {
                actions += '<button class="btn btn-success lift-btn-modal" data-id="' + data.id + '" data-name="' + data.staff_name + '" style="border-radius: 8px;"><i class="mdi mdi-lock-open mr-1"></i> Lift Suspension</button>';
            }
            $('#suspensionActionButtons').html(actions);

            $('#viewModal').modal('show');
        });
    });

    // Lift suspension button (from table)
    $(document).on('click', '.lift-btn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        $('#lift_suspension_id').val(id);
        $('#liftStaffName').text(name);
        $('#lift_notes').val('');
        $('#liftModal').modal('show');
    });

    // Lift suspension button (from view modal)
    $(document).on('click', '.lift-btn-modal', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        $('#lift_suspension_id').val(id);
        $('#liftStaffName').text(name);
        $('#lift_notes').val('');
        $('#viewModal').modal('hide');
        $('#liftModal').modal('show');
    });

    // Submit lift
    $('#liftForm').submit(function(e) {
        e.preventDefault();

        const id = $('#lift_suspension_id').val();

        $('#liftSuspensionBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...');

        $.ajax({
            url: "{{ route('hr.suspensions.index') }}/" + id + "/lift",
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                notes: $('#lift_notes').val()
            },
            success: function(response) {
                $('#liftModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Suspension lifted successfully');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to lift suspension');
            },
            complete: function() {
                $('#liftSuspensionBtn').prop('disabled', false).html('<i class="mdi mdi-lock-open mr-1"></i> Lift Suspension');
            }
        });
    });
});
</script>
@endsection
