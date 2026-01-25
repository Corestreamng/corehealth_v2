@extends('admin.layouts.app')

@section('title', 'Leave Types')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-calendar-clock mr-2"></i>Leave Types
                    </h3>
                    <p class="text-muted mb-0">Configure leave types and their constraints</p>
                </div>
                @can('leave-type.create')
                <button type="button" class="btn btn-primary" id="addLeaveTypeBtn" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                    <i class="mdi mdi-plus mr-1"></i> Add Leave Type
                </button>
                @endcan
            </div>

            <!-- Leave Types Table Card -->
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                    <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-format-list-bulleted mr-2" style="color: var(--primary-color);"></i>
                        Leave Types List
                    </h5>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table id="leaveTypesTable" class="table table-hover" style="width: 100%;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="font-weight: 600; color: #495057;">SN</th>
                                    <th style="font-weight: 600; color: #495057;">Name</th>
                                    <th style="font-weight: 600; color: #495057;">Code</th>
                                    <th style="font-weight: 600; color: #495057;">Max Days/Year</th>
                                    <th style="font-weight: 600; color: #495057;">Max Consecutive</th>
                                    <th style="font-weight: 600; color: #495057;">Paid</th>
                                    <th style="font-weight: 600; color: #495057;">Carry Forward</th>
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

<!-- Add/Edit Leave Type Modal -->
<div class="modal fade" id="leaveTypeModal" tabindex="-1" role="dialog" aria-labelledby="leaveTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" id="leaveTypeModalLabel" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-calendar-clock mr-2" style="color: var(--primary-color);"></i>
                    <span id="modalTitleText">Add Leave Type</span>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="leaveTypeForm">
                @csrf
                <input type="hidden" name="leave_type_id" id="leave_type_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Leave Type Name *</label>
                            <input type="text" class="form-control" name="name" id="name" required
                                   style="border-radius: 8px; padding: 0.75rem;" placeholder="e.g., Annual Leave">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Code *</label>
                            <input type="text" class="form-control" name="code" id="code" required
                                   style="border-radius: 8px; padding: 0.75rem;" placeholder="e.g., AL" maxlength="10">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="2"
                                      style="border-radius: 8px; padding: 0.75rem;" placeholder="Brief description of this leave type"></textarea>
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="mb-3" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-cog mr-1"></i> Constraints
                    </h6>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Max Days Per Year *</label>
                            <input type="number" class="form-control" name="max_days_per_year" id="max_days_per_year"
                                   required min="0" max="365" value="20"
                                   style="border-radius: 8px; padding: 0.75rem;">
                            <small class="text-muted">Maximum annual entitlement</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Max Consecutive Days</label>
                            <input type="number" class="form-control" name="max_consecutive_days" id="max_consecutive_days"
                                   min="0" max="365"
                                   style="border-radius: 8px; padding: 0.75rem;">
                            <small class="text-muted">Leave blank for no limit</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Min Notice Days</label>
                            <input type="number" class="form-control" name="min_notice_days" id="min_notice_days"
                                   min="0" value="3"
                                   style="border-radius: 8px; padding: 0.75rem;">
                            <small class="text-muted">Advance notice required</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Max Requests/Year</label>
                            <input type="number" class="form-control" name="max_requests_per_year" id="max_requests_per_year"
                                   min="1" max="50"
                                   style="border-radius: 8px; padding: 0.75rem;">
                            <small class="text-muted">Leave blank for unlimited</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Max Carry Forward</label>
                            <input type="number" class="form-control" name="max_carry_forward" id="max_carry_forward"
                                   min="0" max="365" value="5"
                                   style="border-radius: 8px; padding: 0.75rem;">
                            <small class="text-muted">Days to carry to next year</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Min Service Months</label>
                            <input type="number" class="form-control" name="min_service_months" id="min_service_months"
                                   min="0" value="0"
                                   style="border-radius: 8px; padding: 0.75rem;">
                            <small class="text-muted">Service required to access</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Color</label>
                            <input type="color" class="form-control" name="color" id="color"
                                   value="#3498db" style="border-radius: 8px; height: 40px;">
                            <small class="text-muted">Calendar display color</small>
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="mb-3" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-toggle-switch mr-1"></i> Options
                    </h6>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="is_paid" name="is_paid" checked>
                                <label class="custom-control-label" for="is_paid" style="font-weight: 600; color: #495057;">
                                    Paid Leave
                                </label>
                            </div>
                            <small class="text-muted">Staff paid during leave</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="requires_attachment" name="requires_attachment">
                                <label class="custom-control-label" for="requires_attachment" style="font-weight: 600; color: #495057;">
                                    Requires Attachment
                                </label>
                            </div>
                            <small class="text-muted">Documents needed</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="allow_half_day" name="allow_half_day">
                                <label class="custom-control-label" for="allow_half_day" style="font-weight: 600; color: #495057;">
                                    Allow Half Day
                                </label>
                            </div>
                            <small class="text-muted">Allow half-day requests</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="allow_carry_forward" name="allow_carry_forward" checked>
                                <label class="custom-control-label" for="allow_carry_forward" style="font-weight: 600; color: #495057;">
                                    Allow Carry Forward
                                </label>
                            </div>
                            <small class="text-muted">Carry unused to next year</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" checked>
                                <label class="custom-control-label" for="is_active" style="font-weight: 600; color: #495057;">
                                    Active
                                </label>
                            </div>
                            <small class="text-muted">Available for selection</small>
                        </div>
                    </div>

                    <div class="row mt-3" id="genderSpecificSection">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Gender Specific</label>
                            <select class="form-control" name="gender_specific" id="gender_specific" style="border-radius: 8px; padding: 0.75rem;">
                                <option value="">All Genders</option>
                                <option value="male">Male Only</option>
                                <option value="female">Female Only</option>
                            </select>
                            <small class="text-muted">Restrict to specific gender</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveLeaveTypeBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-check mr-1"></i> Save Leave Type
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-delete mr-2"></i>
                    Delete Leave Type
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center" style="padding: 1.5rem;">
                <p class="mb-0">Are you sure you want to delete <strong id="deleteItemName"></strong>?</p>
                <small class="text-muted">This action cannot be undone</small>
                <input type="hidden" id="deleteItemId">
            </div>
            <div class="modal-footer justify-content-center" style="border-top: 1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" style="border-radius: 8px;">
                    <i class="mdi mdi-delete mr-1"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(function() {
    // Initialize DataTable
    const table = $('#leaveTypesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('hr.leave-types.index') }}",
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'code', name: 'code' },
            { data: 'max_days_per_year', name: 'max_days_per_year' },
            { data: 'max_consecutive_days', name: 'max_consecutive_days',
              render: function(data) { return data || '<span class="text-muted">No limit</span>'; }
            },
            { data: 'is_paid', name: 'is_paid',
              render: function(data) {
                  return data ? '<span class="badge badge-success">Paid</span>' : '<span class="badge badge-secondary">Unpaid</span>';
              }
            },
            { data: 'allow_carry_forward', name: 'allow_carry_forward',
              render: function(data, type, row) {
                  if (!data) return '<span class="badge badge-secondary">No</span>';
                  return '<span class="badge badge-info">Max ' + (row.max_carry_forward || 0) + ' days</span>';
              }
            },
            { data: 'is_active', name: 'is_active',
              render: function(data) {
                  return data ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>';
              }
            },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[1, 'asc']],
        language: {
            emptyTable: "No leave types configured yet",
            processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>'
        }
    });

    // Add button click
    $('#addLeaveTypeBtn').click(function() {
        $('#leaveTypeForm')[0].reset();
        $('#leave_type_id').val('');
        $('#modalTitleText').text('Add Leave Type');
        $('#leaveTypeModal').modal('show');
    });

    // Edit button click
    $(document).on('click', '.edit-btn', function() {
        const data = $(this).data();
        $('#leave_type_id').val(data.id);
        $('#name').val(data.name);
        $('#code').val(data.code);
        $('#description').val(data.description);
        $('#max_days_per_year').val(data.max_days_per_year);
        $('#max_consecutive_days').val(data.max_consecutive_days);
        $('#min_notice_days').val(data.min_notice_days);
        $('#max_requests_per_year').val(data.max_requests_per_year);
        $('#max_carry_forward').val(data.max_carry_forward);
        $('#min_service_months').val(data.min_service_months);
        $('#color').val(data.color || '#3498db');
        $('#gender_specific').val(data.gender_specific || '');
        $('#is_paid').prop('checked', data.is_paid == 1);
        $('#requires_attachment').prop('checked', data.requires_attachment == 1);
        $('#allow_half_day').prop('checked', data.allow_half_day == 1);
        $('#allow_carry_forward').prop('checked', data.allow_carry_forward == 1);
        $('#is_active').prop('checked', data.is_active == 1);
        $('#modalTitleText').text('Edit Leave Type');
        $('#leaveTypeModal').modal('show');
    });

    // Form submission
    $('#leaveTypeForm').submit(function(e) {
        e.preventDefault();
        const id = $('#leave_type_id').val();
        const url = id ? "{{ route('hr.leave-types.index') }}/" + id : "{{ route('hr.leave-types.store') }}";
        const method = id ? 'PUT' : 'POST';

        const formData = {
            _token: '{{ csrf_token() }}',
            name: $('#name').val(),
            code: $('#code').val(),
            description: $('#description').val(),
            max_days_per_year: $('#max_days_per_year').val(),
            max_consecutive_days: $('#max_consecutive_days').val() || null,
            min_notice_days: $('#min_notice_days').val(),
            max_requests_per_year: $('#max_requests_per_year').val() || null,
            max_carry_forward: $('#max_carry_forward').val(),
            min_service_months: $('#min_service_months').val(),
            color: $('#color').val(),
            gender_specific: $('#gender_specific').val() || null,
            is_paid: $('#is_paid').is(':checked') ? 1 : 0,
            requires_attachment: $('#requires_attachment').is(':checked') ? 1 : 0,
            allow_half_day: $('#allow_half_day').is(':checked') ? 1 : 0,
            allow_carry_forward: $('#allow_carry_forward').is(':checked') ? 1 : 0,
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };

        $('#saveLeaveTypeBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');

        $.ajax({
            url: url,
            method: method,
            data: formData,
            success: function(response) {
                $('#leaveTypeModal').modal('hide');
                table.ajax.reload();
                toastr.success(response.message || 'Leave type saved successfully');
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
                $('#saveLeaveTypeBtn').prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Save Leave Type');
            }
        });
    });

    // Delete button click
    $(document).on('click', '.delete-btn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        $('#deleteItemId').val(id);
        $('#deleteItemName').text(name);
        $('#deleteModal').modal('show');
    });

    // Confirm delete
    $('#confirmDeleteBtn').click(function() {
        const id = $('#deleteItemId').val();

        $.ajax({
            url: "{{ route('hr.leave-types.index') }}/" + id,
            method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                $('#deleteModal').modal('hide');
                table.ajax.reload();
                toastr.success(response.message || 'Leave type deleted successfully');
            },
            error: function(xhr) {
                let message = 'Failed to delete leave type';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                toastr.error(message);
            }
        });
    });
});
</script>
@endsection
