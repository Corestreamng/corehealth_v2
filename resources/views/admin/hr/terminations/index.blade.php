@extends('admin.layouts.app')

@section('title', 'Staff Terminations')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-account-off mr-2"></i>Staff Terminations
                    </h3>
                    <p class="text-muted mb-0">Manage staff terminations and exit process</p>
                </div>
                <div class="d-flex">
                    <select id="statusFilter" class="form-control mr-2" style="border-radius: 8px; width: 150px;">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    @can('termination.create')
                    <button type="button" class="btn btn-danger" id="addTerminationBtn" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                        <i class="mdi mdi-plus mr-1"></i> New Termination
                    </button>
                    @endcan
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);">
                        <div class="card-body text-dark">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-muted">Pending Exit</h6>
                                    <h3 class="mb-0" id="pendingCount">0</h3>
                                </div>
                                <i class="mdi mdi-clock-outline" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%);">
                        <div class="card-body text-dark">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-muted">Resigned</h6>
                                    <h3 class="mb-0" id="resignedCount">0</h3>
                                </div>
                                <i class="mdi mdi-account-arrow-right" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                        <div class="card-body text-dark">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-muted">Dismissed</h6>
                                    <h3 class="mb-0" id="dismissedCount">0</h3>
                                </div>
                                <i class="mdi mdi-account-remove" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-modern border-0" style="border-radius: 10px; background: linear-gradient(135deg, #d299c2 0%, #fef9d7 100%);">
                        <div class="card-body text-dark">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-muted">Total This Year</h6>
                                    <h3 class="mb-0" id="totalCount">0</h3>
                                </div>
                                <i class="mdi mdi-history" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Terminations Table Card -->
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                    <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-format-list-bulleted mr-2" style="color: var(--primary-color);"></i>
                        Termination Records
                    </h5>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table id="terminationsTable" class="table table-hover" style="width: 100%;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="font-weight: 600; color: #495057;">SN</th>
                                    <th style="font-weight: 600; color: #495057;">Staff</th>
                                    <th style="font-weight: 600; color: #495057;">Type</th>
                                    <th style="font-weight: 600; color: #495057;">Reason</th>
                                    <th style="font-weight: 600; color: #495057;">Last Working Day</th>
                                    <th style="font-weight: 600; color: #495057;">Exit Interview</th>
                                    <th style="font-weight: 600; color: #495057;">Clearance</th>
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

<!-- New Termination Modal -->
<div class="modal fade" id="terminationModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-account-off mr-2"></i>
                    Process Staff Termination
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="terminationForm">
                @csrf
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Staff Member *</label>
                            <select class="form-control select2" name="staff_id" id="staff_id" required style="width: 100%;">
                                <option value="">Select Staff</option>
                                @foreach($staffList ?? [] as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->user->firstname ?? '' }} {{ $staff->user->surname ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Termination Type *</label>
                            <select class="form-control" name="termination_type" id="termination_type" required style="border-radius: 8px;">
                                <option value="resignation">Resignation</option>
                                <option value="dismissal">Dismissal</option>
                                <option value="redundancy">Redundancy</option>
                                <option value="retirement">Retirement</option>
                                <option value="contract_end">Contract End</option>
                                <option value="mutual_agreement">Mutual Agreement</option>
                                <option value="death">Death</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Notice Date *</label>
                            <input type="date" class="form-control" name="notice_date" id="notice_date" required
                                   style="border-radius: 8px; padding: 0.75rem;" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Last Working Day *</label>
                            <input type="date" class="form-control" name="last_working_day" id="last_working_day" required
                                   style="border-radius: 8px; padding: 0.75rem;" min="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Reason *</label>
                            <textarea class="form-control" name="reason" id="reason" rows="3" required
                                      style="border-radius: 8px; padding: 0.75rem;" placeholder="Detailed reason for termination"></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Related Disciplinary Query (if applicable)</label>
                            <select class="form-control" name="disciplinary_query_id" id="disciplinary_query_id" style="border-radius: 8px;">
                                <option value="">None</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="mb-3" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-cash mr-1"></i> Final Settlement
                    </h6>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="is_eligible_for_severance" name="is_eligible_for_severance">
                                <label class="custom-control-label" for="is_eligible_for_severance">Eligible for Severance</label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="exit_interview_scheduled" name="exit_interview_scheduled">
                                <label class="custom-control-label" for="exit_interview_scheduled">Schedule Exit Interview</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3" id="severanceAmountGroup" style="display: none;">
                            <label class="form-label" style="font-weight: 600; color: #495057;">Severance Amount</label>
                            <input type="number" class="form-control" name="severance_amount" id="severance_amount"
                                   step="0.01" min="0" style="border-radius: 8px; padding: 0.75rem;">
                        </div>
                    </div>

                    <div class="alert alert-danger mt-3" style="border-radius: 8px;">
                        <i class="mdi mdi-alert mr-1"></i>
                        <strong>Warning:</strong> This action will permanently terminate the staff member's employment and block their system access.
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="submitTerminationBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-check mr-1"></i> Process Termination
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Termination Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-eye mr-2" style="color: var(--primary-color);"></i>
                    Termination Details
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <div id="terminationDetails"></div>
            </div>
            <div class="modal-footer justify-content-between" style="border-top: 1px solid #e9ecef;">
                <div id="terminationActionButtons"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Complete Exit Modal -->
<div class="modal fade" id="completeExitModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-success text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-check-circle mr-2"></i>
                    Complete Exit Process
                </h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="completeExitForm">
                @csrf
                <input type="hidden" id="complete_termination_id">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="form-group">
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" class="custom-control-input" id="clearance_completed" name="clearance_completed">
                            <label class="custom-control-label" for="clearance_completed">Clearance Completed</label>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" class="custom-control-input" id="exit_interview_conducted" name="exit_interview_conducted">
                            <label class="custom-control-label" for="exit_interview_conducted">Exit Interview Conducted</label>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" class="custom-control-input" id="final_payment_processed" name="final_payment_processed">
                            <label class="custom-control-label" for="final_payment_processed">Final Payment Processed</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Exit Interview Notes</label>
                        <textarea class="form-control" name="exit_interview_notes" id="exit_interview_notes" rows="3"
                                  style="border-radius: 8px; padding: 0.75rem;" placeholder="Exit interview feedback"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Final Settlement Amount</label>
                        <input type="number" class="form-control" name="final_settlement_amount" id="final_settlement_amount"
                               step="0.01" min="0" style="border-radius: 8px; padding: 0.75rem;">
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-success" id="completeExitBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-check mr-1"></i> Complete Exit
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
        dropdownParent: $('#terminationModal'),
        placeholder: 'Select Staff',
        allowClear: true
    });

    // Initialize DataTable
    const table = $('#terminationsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('hr.terminations.index') }}",
            data: function(d) {
                d.status = $('#statusFilter').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'staff_name', name: 'staff.user.firstname' },
            { data: 'termination_type_badge', name: 'termination_type' },
            { data: 'reason', name: 'reason',
              render: function(data) {
                  return data ? (data.length > 50 ? data.substring(0, 50) + '...' : data) : '-';
              }
            },
            { data: 'last_working_day', name: 'last_working_day' },
            { data: 'exit_interview', name: 'exit_interview_conducted', orderable: false },
            { data: 'clearance', name: 'clearance_completed', orderable: false },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[4, 'desc']],
        language: {
            emptyTable: "No termination records found",
            processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>'
        }
    });

    // Load stats
    function loadStats() {
        $.get("{{ route('hr.terminations.index') }}", { stats: true }, function(data) {
            $('#pendingCount').text(data.pending || 0);
            $('#resignedCount').text(data.resigned || 0);
            $('#dismissedCount').text(data.dismissed || 0);
            $('#totalCount').text(data.total || 0);
        });
    }
    loadStats();

    // Status filter
    $('#statusFilter').change(function() {
        table.ajax.reload();
    });

    // Severance checkbox
    $('#is_eligible_for_severance').change(function() {
        if ($(this).is(':checked')) {
            $('#severanceAmountGroup').show();
        } else {
            $('#severanceAmountGroup').hide();
        }
    });

    // Add termination button
    $('#addTerminationBtn').click(function() {
        $('#terminationForm')[0].reset();
        $('#staff_id').val('').trigger('change');
        $('#severanceAmountGroup').hide();
        $('#terminationModal').modal('show');
    });

    // Load staff queries when staff selected
    $('#staff_id').change(function() {
        const staffId = $(this).val();
        if (staffId) {
            $.get("{{ url('/hr/disciplinary') }}", { staff_id: staffId }, function(data) {
                let options = '<option value="">None</option>';
                if (data.data) {
                    data.data.forEach(function(query) {
                        options += '<option value="' + query.id + '">' + query.query_number + ' - ' + query.subject + '</option>';
                    });
                }
                $('#disciplinary_query_id').html(options);
            });
        }
    });

    // Submit termination
    $('#terminationForm').submit(function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to process this termination? This action cannot be easily undone.')) {
            return;
        }

        $('#submitTerminationBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...');

        $.ajax({
            url: "{{ route('hr.terminations.store') }}",
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                staff_id: $('#staff_id').val(),
                termination_type: $('#termination_type').val(),
                notice_date: $('#notice_date').val(),
                last_working_day: $('#last_working_day').val(),
                reason: $('#reason').val(),
                disciplinary_query_id: $('#disciplinary_query_id').val() || null,
                is_eligible_for_severance: $('#is_eligible_for_severance').is(':checked') ? 1 : 0,
                severance_amount: $('#severance_amount').val() || null,
                exit_interview_scheduled: $('#exit_interview_scheduled').is(':checked') ? 1 : 0
            },
            success: function(response) {
                $('#terminationModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Termination processed successfully');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to process termination');
            },
            complete: function() {
                $('#submitTerminationBtn').prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Process Termination');
            }
        });
    });

    // View termination
    $(document).on('click', '.view-btn', function() {
        const id = $(this).data('id');

        $.get("{{ route('hr.terminations.index') }}/" + id, function(data) {
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Staff:</strong> ${data.staff_name}</p>
                        <p><strong>Employee ID:</strong> ${data.employee_id || '-'}</p>
                        <p><strong>Type:</strong> ${data.termination_type_badge}</p>
                        <p><strong>Notice Date:</strong> ${data.notice_date}</p>
                        <p><strong>Last Working Day:</strong> ${data.last_working_day}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> ${data.status_badge}</p>
                        <p><strong>Clearance:</strong> ${data.clearance_completed ? '<span class="badge badge-success">Completed</span>' : '<span class="badge badge-warning">Pending</span>'}</p>
                        <p><strong>Exit Interview:</strong> ${data.exit_interview_conducted ? '<span class="badge badge-success">Conducted</span>' : '<span class="badge badge-warning">Pending</span>'}</p>
                        <p><strong>Severance:</strong> ${data.is_eligible_for_severance ? '₦' + (data.severance_amount || 0).toLocaleString() : 'Not Eligible'}</p>
                    </div>
                    <div class="col-12 mt-3">
                        <h6><strong>Reason:</strong></h6>
                        <p class="border p-2 rounded bg-light">${data.reason}</p>
                    </div>
                </div>
            `;

            if (data.exit_interview_notes) {
                html += `
                    <div class="col-12 mt-3">
                        <h6><strong>Exit Interview Notes:</strong></h6>
                        <p class="border p-2 rounded bg-light">${data.exit_interview_notes}</p>
                    </div>
                `;
            }

            $('#terminationDetails').html(html);

            // Action buttons for pending terminations
            let actions = '';
            if (data.status === 'pending') {
                @can('termination.complete')
                actions += '<button class="btn btn-success complete-exit-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-check-circle mr-1"></i> Complete Exit</button>';
                @endcan
            }
            $('#terminationActionButtons').html(actions);

            $('#viewModal').modal('show');
        });
    });

    // Complete exit button
    $(document).on('click', '.complete-exit-btn', function() {
        const id = $(this).data('id');
        $('#complete_termination_id').val(id);
        $('#completeExitForm')[0].reset();
        $('#viewModal').modal('hide');
        $('#completeExitModal').modal('show');
    });

    // Submit complete exit
    $('#completeExitForm').submit(function(e) {
        e.preventDefault();

        const id = $('#complete_termination_id').val();

        $('#completeExitBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...');

        $.ajax({
            url: "{{ route('hr.terminations.index') }}/" + id + "/complete",
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                clearance_completed: $('#clearance_completed').is(':checked') ? 1 : 0,
                exit_interview_conducted: $('#exit_interview_conducted').is(':checked') ? 1 : 0,
                final_payment_processed: $('#final_payment_processed').is(':checked') ? 1 : 0,
                exit_interview_notes: $('#exit_interview_notes').val(),
                final_settlement_amount: $('#final_settlement_amount').val()
            },
            success: function(response) {
                $('#completeExitModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Exit process completed');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to complete exit process');
            },
            complete: function() {
                $('#completeExitBtn').prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Complete Exit');
            }
        });
    });
});
</script>
@endsection
