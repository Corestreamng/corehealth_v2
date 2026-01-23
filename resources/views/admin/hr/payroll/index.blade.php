@extends('admin.layouts.app')

@section('title', 'Payroll Batches')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                        <i class="mdi mdi-cash-register mr-2"></i>Payroll Batches
                    </h3>
                    <p class="text-muted mb-0">Process and manage monthly payroll</p>
                </div>
                @can('payroll.create')
                <button type="button" class="btn btn-primary" id="createBatchBtn" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                    <i class="mdi mdi-plus mr-1"></i> Create Payroll Batch
                </button>
                @endcan
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Draft</h6>
                                    <h3 class="mb-0" id="draftCount">0</h3>
                                </div>
                                <i class="mdi mdi-file-document-edit-outline" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Pending Approval</h6>
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
                    <div class="card border-0" style="border-radius: 10px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <div class="card-body text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">This Month Total</h6>
                                    <h4 class="mb-0" id="monthTotal">₦0</h4>
                                </div>
                                <i class="mdi mdi-currency-ngn" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payroll Batches Table Card -->
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                    <h5 class="mb-0" style="font-weight: 600; color: #1a1a1a;">
                        <i class="mdi mdi-format-list-bulleted mr-2" style="color: var(--primary-color);"></i>
                        Payroll Batches
                    </h5>
                    <select id="statusFilter" class="form-control" style="border-radius: 8px; width: 150px;">
                        <option value="">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table id="payrollBatchesTable" class="table table-hover" style="width: 100%;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="font-weight: 600; color: #495057;">SN</th>
                                    <th style="font-weight: 600; color: #495057;">Batch Number</th>
                                    <th style="font-weight: 600; color: #495057;">Period</th>
                                    <th style="font-weight: 600; color: #495057;">Staff Count</th>
                                    <th style="font-weight: 600; color: #495057;">Total Amount</th>
                                    <th style="font-weight: 600; color: #495057;">Status</th>
                                    <th style="font-weight: 600; color: #495057;">Created</th>
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

<!-- Create Batch Modal -->
<div class="modal fade" id="createBatchModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-cash-register mr-2" style="color: var(--primary-color);"></i>
                    Create Payroll Batch
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="createBatchForm">
                @csrf
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Payroll Month *</label>
                        <input type="month" class="form-control" name="pay_period" id="pay_period" required
                               style="border-radius: 8px; padding: 0.75rem;" value="{{ date('Y-m') }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Description</label>
                        <input type="text" class="form-control" name="description" id="description"
                               style="border-radius: 8px; padding: 0.75rem;" placeholder="e.g., January 2024 Salary">
                    </div>

                    <div class="alert alert-info" style="border-radius: 8px;">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        <small>This will create a payroll batch with all active staff members who have salary profiles configured.</small>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="createBatchSubmitBtn" style="border-radius: 8px;">
                        <i class="mdi mdi-plus mr-1"></i> Create Batch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Batch Modal -->
<div class="modal fade" id="viewBatchModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #1a1a1a;">
                    <i class="mdi mdi-eye mr-2" style="color: var(--primary-color);"></i>
                    Payroll Batch Details - <span id="viewBatchNumber"></span>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <div id="batchDetails"></div>
                <hr>
                <h6 class="mb-3" style="font-weight: 600;">Payroll Items</h6>
                <div class="table-responsive">
                    <table id="batchItemsTable" class="table table-sm table-bordered">
                        <thead class="bg-light">
                            <tr>
                                <th>Staff</th>
                                <th>Basic Salary</th>
                                <th>Gross Salary</th>
                                <th>Deductions</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot class="table-primary font-weight-bold">
                            <tr>
                                <td>TOTALS</td>
                                <td id="totalBasic">₦0.00</td>
                                <td id="totalGross">₦0.00</td>
                                <td id="totalDeductions">₦0.00</td>
                                <td id="totalNet">₦0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer justify-content-between" style="border-top: 1px solid #e9ecef;">
                <div id="batchActionButtons"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Submit/Approve/Reject Modal -->
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
                @csrf
                <input type="hidden" id="actionBatchId">
                <input type="hidden" id="actionType">
                <div class="modal-body" style="padding: 1.5rem;">
                    <p id="actionDescription"></p>
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600; color: #495057;">Comments</label>
                        <textarea class="form-control" name="comments" id="actionComments" rows="3"
                                  style="border-radius: 8px; padding: 0.75rem;" placeholder="Add comments (optional)"></textarea>
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
    // Initialize DataTable
    const table = $('#payrollBatchesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('hr.payroll.index') }}",
            data: function(d) {
                d.status = $('#statusFilter').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'batch_number', name: 'batch_number' },
            { data: 'pay_period_formatted', name: 'pay_period' },
            { data: 'staff_count', name: 'staff_count', orderable: false },
            { data: 'total_amount_formatted', name: 'total_net_amount' },
            { data: 'status_badge', name: 'status' },
            { data: 'created_at', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[6, 'desc']],
        language: {
            emptyTable: "No payroll batches found",
            processing: '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>'
        }
    });

    // Load stats
    function loadStats() {
        $.get("{{ route('hr.payroll.index') }}", { stats: true }, function(data) {
            $('#draftCount').text(data.draft || 0);
            $('#pendingCount').text(data.pending || 0);
            $('#approvedCount').text(data.approved || 0);
            $('#monthTotal').text('₦' + (data.month_total || 0).toLocaleString());
        });
    }
    loadStats();

    // Status filter
    $('#statusFilter').change(function() {
        table.ajax.reload();
    });

    // Create batch button
    $('#createBatchBtn').click(function() {
        $('#createBatchForm')[0].reset();
        $('#createBatchModal').modal('show');
    });

    // Submit create batch
    $('#createBatchForm').submit(function(e) {
        e.preventDefault();

        $('#createBatchSubmitBtn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Creating...');

        $.ajax({
            url: "{{ route('hr.payroll.store') }}",
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                pay_period: $('#pay_period').val(),
                description: $('#description').val()
            },
            success: function(response) {
                $('#createBatchModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Payroll batch created successfully');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to create batch');
            },
            complete: function() {
                $('#createBatchSubmitBtn').prop('disabled', false).html('<i class="mdi mdi-plus mr-1"></i> Create Batch');
            }
        });
    });

    // View batch
    $(document).on('click', '.view-btn', function() {
        const id = $(this).data('id');

        $.get("{{ route('hr.payroll.index') }}/" + id, function(data) {
            $('#viewBatchNumber').text(data.batch_number);

            let html = `
                <div class="row mb-3">
                    <div class="col-md-4">
                        <p><strong>Period:</strong> ${data.pay_period_formatted}</p>
                        <p><strong>Status:</strong> ${data.status_badge}</p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Created By:</strong> ${data.created_by || '-'}</p>
                        <p><strong>Created On:</strong> ${data.created_at}</p>
                    </div>
                    <div class="col-md-4">
                        ${data.approved_by ? '<p><strong>Approved By:</strong> ' + data.approved_by + '</p>' : ''}
                        ${data.approved_at ? '<p><strong>Approved On:</strong> ' + data.approved_at + '</p>' : ''}
                    </div>
                </div>
            `;
            $('#batchDetails').html(html);

            // Load items
            let itemsHtml = '';
            let totalBasic = 0, totalGross = 0, totalDeductions = 0, totalNet = 0;

            if (data.items && data.items.length) {
                data.items.forEach(item => {
                    const basic = parseFloat(item.basic_salary) || 0;
                    const gross = parseFloat(item.gross_salary) || 0;
                    const deductions = parseFloat(item.total_deductions) || 0;
                    const net = parseFloat(item.net_salary) || 0;

                    totalBasic += basic;
                    totalGross += gross;
                    totalDeductions += deductions;
                    totalNet += net;

                    itemsHtml += `
                        <tr>
                            <td>${item.staff_name || '-'}</td>
                            <td>₦${basic.toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                            <td>₦${gross.toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                            <td>₦${deductions.toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                            <td>₦${net.toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                            <td>${item.status_badge || '-'}</td>
                        </tr>
                    `;
                });
            } else {
                itemsHtml = '<tr><td colspan="6" class="text-center text-muted">No items in this batch</td></tr>';
            }

            $('#batchItemsTable tbody').html(itemsHtml);
            $('#totalBasic').text('₦' + totalBasic.toLocaleString('en-NG', {minimumFractionDigits: 2}));
            $('#totalGross').text('₦' + totalGross.toLocaleString('en-NG', {minimumFractionDigits: 2}));
            $('#totalDeductions').text('₦' + totalDeductions.toLocaleString('en-NG', {minimumFractionDigits: 2}));
            $('#totalNet').text('₦' + totalNet.toLocaleString('en-NG', {minimumFractionDigits: 2}));

            // Action buttons based on status
            let actions = '';
            if (data.status === 'draft') {
                actions += '<button class="btn btn-info mr-2 generate-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-cog mr-1"></i> Generate Items</button>';
                @can('payroll.submit')
                actions += '<button class="btn btn-primary submit-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-send mr-1"></i> Submit for Approval</button>';
                @endcan
            } else if (data.status === 'submitted') {
                @can('payroll.approve')
                actions += '<button class="btn btn-success mr-2 approve-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-check mr-1"></i> Approve</button>';
                actions += '<button class="btn btn-danger reject-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-close mr-1"></i> Reject</button>';
                @endcan
            } else if (data.status === 'approved') {
                actions += '<button class="btn btn-success mr-2 export-btn" data-id="' + data.id + '" style="border-radius: 8px;"><i class="mdi mdi-download mr-1"></i> Export CSV</button>';
                actions += '<a href="{{ url("/hr/payroll") }}/' + data.id + '/payslips" class="btn btn-info" style="border-radius: 8px;"><i class="mdi mdi-file-document-multiple mr-1"></i> View Payslips</a>';
            }
            $('#batchActionButtons').html(actions);

            $('#viewBatchModal').modal('show');
        });
    });

    // Generate items
    $(document).on('click', '.generate-btn', function() {
        const id = $(this).data('id');

        if (confirm('This will generate payroll items for all staff with salary profiles. Continue?')) {
            $.ajax({
                url: "{{ route('hr.payroll.index') }}/" + id + "/generate",
                method: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    toastr.success(response.message || 'Items generated successfully');
                    // Refresh the view
                    $('.view-btn[data-id="' + id + '"]').click();
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to generate items');
                }
            });
        }
    });

    // Submit for approval
    $(document).on('click', '.submit-btn', function() {
        const id = $(this).data('id');
        $('#actionBatchId').val(id);
        $('#actionType').val('submit');
        $('#actionModalHeader').removeClass('bg-success bg-danger').addClass('bg-primary');
        $('#actionModalTitle').html('<i class="mdi mdi-send mr-2"></i>Submit for Approval');
        $('#actionDescription').text('Submit this payroll batch for approval?');
        $('#actionSubmitBtn').removeClass('btn-success btn-danger').addClass('btn-primary').html('<i class="mdi mdi-send mr-1"></i> Submit');
        $('#actionComments').val('');
        $('#viewBatchModal').modal('hide');
        $('#actionModal').modal('show');
    });

    // Approve
    $(document).on('click', '.approve-btn', function() {
        const id = $(this).data('id');
        $('#actionBatchId').val(id);
        $('#actionType').val('approve');
        $('#actionModalHeader').removeClass('bg-primary bg-danger').addClass('bg-success');
        $('#actionModalTitle').html('<i class="mdi mdi-check-circle mr-2"></i>Approve Payroll');
        $('#actionDescription').html('<strong class="text-warning">Warning:</strong> Approving this batch will create an Expense entry for the total payroll amount.');
        $('#actionSubmitBtn').removeClass('btn-primary btn-danger').addClass('btn-success').html('<i class="mdi mdi-check mr-1"></i> Approve');
        $('#actionComments').val('');
        $('#viewBatchModal').modal('hide');
        $('#actionModal').modal('show');
    });

    // Reject
    $(document).on('click', '.reject-btn', function() {
        const id = $(this).data('id');
        $('#actionBatchId').val(id);
        $('#actionType').val('reject');
        $('#actionModalHeader').removeClass('bg-primary bg-success').addClass('bg-danger');
        $('#actionModalTitle').html('<i class="mdi mdi-close-circle mr-2"></i>Reject Payroll');
        $('#actionDescription').text('Reject this payroll batch? Please provide a reason.');
        $('#actionSubmitBtn').removeClass('btn-primary btn-success').addClass('btn-danger').html('<i class="mdi mdi-close mr-1"></i> Reject');
        $('#actionComments').val('');
        $('#viewBatchModal').modal('hide');
        $('#actionModal').modal('show');
    });

    // Submit action
    $('#actionForm').submit(function(e) {
        e.preventDefault();

        const id = $('#actionBatchId').val();
        const action = $('#actionType').val();

        $('#actionSubmitBtn').prop('disabled', true).prepend('<i class="mdi mdi-loading mdi-spin mr-1"></i> ');

        $.ajax({
            url: "{{ route('hr.payroll.index') }}/" + id + "/" + action,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                comments: $('#actionComments').val()
            },
            success: function(response) {
                $('#actionModal').modal('hide');
                table.ajax.reload();
                loadStats();
                toastr.success(response.message || 'Action completed successfully');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Action failed');
            },
            complete: function() {
                $('#actionSubmitBtn').prop('disabled', false);
            }
        });
    });

    // Export
    $(document).on('click', '.export-btn', function() {
        const id = $(this).data('id');
        window.location.href = "{{ route('hr.payroll.index') }}/" + id + "/export";
    });
});
</script>
@endsection
