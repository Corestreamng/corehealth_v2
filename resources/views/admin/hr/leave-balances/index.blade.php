@extends('admin.layouts.app')

@section('title', 'Leave Balances')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-calendar-account mr-2"></i>Leave Balances
                    </h3>
                    <p class="text-muted mb-0">View and manage staff leave balances for {{ date('Y') }}</p>
                </div>
                <div class="d-flex">
                    <select id="leaveTypeFilter" class="form-control mr-2" style="border-radius: 8px; width: 180px;">
                        <option value="">All Leave Types</option>
                        @foreach($leaveTypes ?? [] as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @can('leave-balance.adjust')
                    <button type="button" class="btn btn-outline-primary mr-2" id="initBalancesBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-refresh mr-1"></i> Initialize Year
                    </button>
                    @endcan
                    <button type="button" class="btn btn-success" id="exportBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-download mr-1"></i> Export
                    </button>
                </div>
            </div>

            <!-- Leave Balances Table Card -->
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                    <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-format-list-bulleted mr-2" style="color: var(--primary-color);"></i>
                        Staff Leave Balances
                    </h5>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table id="leaveBalancesTable" class="table table-hover" style="width: 100%;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="font-weight: 600; color: #495057;">SN</th>
                                    <th style="font-weight: 600; color: #495057;">Staff</th>
                                    <th style="font-weight: 600; color: #495057;">Employee ID</th>
                                    <th style="font-weight: 600; color: #495057;">Leave Type</th>
                                    <th style="font-weight: 600; color: #495057;">Entitled</th>
                                    <th style="font-weight: 600; color: #495057;">Used</th>
                                    <th style="font-weight: 600; color: #495057;">Pending</th>
                                    <th style="font-weight: 600; color: #495057;">Carried</th>
                                    <th style="font-weight: 600; color: #495057;">Available</th>
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

<!-- Adjust Balance Modal -->
<div class="modal fade" id="adjustBalanceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-plus-minus mr-2" style="color: var(--primary-color);"></i>
                    Adjust Leave Balance
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="adjustBalanceForm">
                @csrf
                <input type="hidden" name="balance_id" id="balance_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="alert alert-info" style="border-radius: 8px;">
                        <strong id="adjustStaffName"></strong><br>
                        <span id="adjustLeaveType"></span> - Current Available: <strong id="adjustCurrentBalance">0</strong> days
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Adjustment Type *</label>
                        <select class="form-control" name="adjustment_type" id="adjustment_type" required style="border-radius: 8px;">
                            <option value="add">Add Days (+)</option>
                            <option value="deduct">Deduct Days (-)</option>
                            <option value="set_entitled">Set Entitled Days</option>
                            <option value="set_carried">Set Carried Forward</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Days *</label>
                        <input type="number" class="form-control" name="days" id="adjust_days" required
                               min="0" step="0.5" style="border-radius: 8px; padding: 0.75rem;">
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Reason *</label>
                        <textarea class="form-control" name="reason" id="adjust_reason" rows="2" required
                                  style="border-radius: 8px; padding: 0.75rem;" placeholder="Reason for adjustment"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveAdjustmentBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-check mr-1"></i> Apply Adjustment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Initialize Balances Modal -->
<div class="modal fade" id="initBalancesModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-refresh mr-2" style="color: var(--primary-color);"></i>
                    Initialize Year Balances
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="initBalancesForm">
                @csrf
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="alert alert-warning" style="border-radius: 8px;">
                        <i class="mdi mdi-alert mr-1"></i>
                        <strong>Warning:</strong> This will create or reset leave balances for all active staff.
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Year *</label>
                        <input type="number" class="form-control" name="year" id="init_year" required
                               value="{{ date('Y') }}" min="2020" max="2099" style="border-radius: 8px; padding: 0.75rem;">
                    </div>

                    <div class="custom-control custom-checkbox mb-3">
                        <input type="checkbox" class="custom-control-input" id="carry_forward" name="carry_forward" checked>
                        <label class="custom-control-label" for="carry_forward" style="font-weight: 600; color: #495057;">
                            Carry forward unused days from previous year
                        </label>
                    </div>

                    <div class="custom-control custom-checkbox mb-3">
                        <input type="checkbox" class="custom-control-input" id="reset_existing" name="reset_existing">
                        <label class="custom-control-label" for="reset_existing" style="font-weight: 600; color: #495057;">
                            Reset existing balances (overwrite current data)
                        </label>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="initBalancesSubmitBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-refresh mr-1"></i> Initialize Balances
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
    // Initialize DataTable
    const table = $('#leaveBalancesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('hr.leave-balances.index') }}",
            data: function(d) {
                d.leave_type_id = $('#leaveTypeFilter').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'staff_name', name: 'staff.user.firstname' },
            { data: 'employee_id', name: 'staff.employee_id' },
            { data: 'leave_type', name: 'leaveType.name' },
            { data: 'entitled_days', name: 'entitled_days' },
            { data: 'used_days', name: 'used_days' },
            { data: 'pending_days', name: 'pending_days' },
            { data: 'carried_forward', name: 'carried_forward' },
            { data: 'available', name: 'available', orderable: false,
              render: function(data, type, row) {
                  const available = parseFloat(row.entitled_days) - parseFloat(row.used_days) - parseFloat(row.pending_days) + parseFloat(row.carried_forward);
                  const cls = available > 0 ? 'success' : (available == 0 ? 'warning' : 'danger');
                  return '<span class="badge badge-' + cls + '" style="font-size: 1em;">' + available.toFixed(1) + '</span>';
              }
            },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[1, 'asc']],
        language: {
            emptyTable: "No leave balances found. Initialize year balances to create records.",
            processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>'
        }
    });

    // Filter change
    $('#leaveTypeFilter').change(function() {
        table.ajax.reload();
    });

    // Initialize balances button
    $('#initBalancesBtn').click(function() {
        $('#initBalancesModal').modal('show');
    });

    // Initialize balances submit
    $('#initBalancesForm').submit(function(e) {
        e.preventDefault();

        $('#initBalancesSubmitBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...');

        $.ajax({
            url: "{{ route('hr.leave-balances.initialize') }}",
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                year: $('#init_year').val(),
                carry_forward: $('#carry_forward').is(':checked') ? 1 : 0,
                reset_existing: $('#reset_existing').is(':checked') ? 1 : 0
            },
            success: function(response) {
                $('#initBalancesModal').modal('hide');
                table.ajax.reload();
                toastr.success(response.message || 'Year balances initialized successfully');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to initialize balances');
            },
            complete: function() {
                $('#initBalancesSubmitBtn').prop('disabled', false).html('<i class="mdi mdi-refresh mr-1"></i> Initialize Balances');
            }
        });
    });

    // Adjust balance button
    $(document).on('click', '.adjust-btn', function() {
        const data = $(this).data();
        $('#balance_id').val(data.id);
        $('#adjustStaffName').text(data.staff);
        $('#adjustLeaveType').text(data.type);
        $('#adjustCurrentBalance').text(data.available);
        $('#adjustment_type').val('add');
        $('#adjust_days').val('');
        $('#adjust_reason').val('');
        $('#adjustBalanceModal').modal('show');
    });

    // Adjust balance submit
    $('#adjustBalanceForm').submit(function(e) {
        e.preventDefault();

        const id = $('#balance_id').val();

        $('#saveAdjustmentBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');

        $.ajax({
            url: "{{ route('hr.leave-balances.index') }}/" + id + "/adjust",
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                adjustment_type: $('#adjustment_type').val(),
                days: $('#adjust_days').val(),
                reason: $('#adjust_reason').val()
            },
            success: function(response) {
                $('#adjustBalanceModal').modal('hide');
                table.ajax.reload();
                toastr.success(response.message || 'Balance adjusted successfully');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to adjust balance');
            },
            complete: function() {
                $('#saveAdjustmentBtn').prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Apply Adjustment');
            }
        });
    });

    // Export
    $('#exportBtn').click(function() {
        const leaveTypeId = $('#leaveTypeFilter').val();
        let url = "{{ route('hr.leave-balances.export') }}";
        if (leaveTypeId) {
            url += '?leave_type_id=' + leaveTypeId;
        }
        window.location.href = url;
    });
});
</script>
@endsection
