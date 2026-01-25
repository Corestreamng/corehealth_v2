@extends('admin.layouts.app')

@section('title', 'Staff Suspensions')

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
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(function() {
    // Initialize Select2
    $('.select2').select2({
        dropdownParent: $('#suspensionModal'),
        placeholder: 'Select Staff',
        allowClear: true
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
            { data: 'reason', name: 'reason' },
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
        $('#staff_id').val('').trigger('change');
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

    // Lift suspension button
    $(document).on('click', '.lift-btn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        $('#lift_suspension_id').val(id);
        $('#liftStaffName').text(name);
        $('#lift_notes').val('');
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
