@extends('admin.layouts.app')
@section('title', 'HMO Workbench')
@section('page_name', 'HMO Management')
@section('subpage_name', 'HMO Workbench')
@section('content')

<section class="content">
    <div class="container-fluid">
        <!-- Queue Stats Cards -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Pending Validation</h6>
                                <h3 class="mb-0" id="pending_count">0</h3>
                            </div>
                            <i class="mdi mdi-clock-alert" style="font-size: 2.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Express (Auto)</h6>
                                <h3 class="mb-0" id="express_count">0</h3>
                            </div>
                            <i class="mdi mdi-check-circle" style="font-size: 2.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Approved Today</h6>
                                <h3 class="mb-0" id="approved_today_count">0</h3>
                            </div>
                            <i class="mdi mdi-thumb-up" style="font-size: 2.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Rejected Today</h6>
                                <h3 class="mb-0" id="rejected_today_count">0</h3>
                            </div>
                            <i class="mdi mdi-thumb-down" style="font-size: 2.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="card-title"><i class="fa fa-filter"></i> Filters & Search</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Search</label>
                            <input type="text" class="form-control form-control-sm" id="search_input" placeholder="Patient name, file no, request ID...">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>HMO</label>
                            <select class="form-control form-control-sm" id="filter_hmo">
                                <option value="">All HMOs</option>
                                @foreach($hmos as $hmo)
                                    <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Coverage Mode</label>
                            <select class="form-control form-control-sm" id="filter_coverage">
                                <option value="">All Modes</option>
                                <option value="express">Express</option>
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date From</label>
                            <input type="date" class="form-control form-control-sm" id="filter_date_from">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Date To</label>
                            <input type="date" class="form-control form-control-sm" id="filter_date_to">
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-primary btn-block btn-sm" id="applyFilters">
                                <i class="fa fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs and DataTable -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="workbenchTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="pending-tab" data-toggle="tab" href="#pending" role="tab">
                            <i class="mdi mdi-clock-alert"></i> Pending <span class="badge badge-warning" id="pending_badge">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="express-tab" data-toggle="tab" href="#express" role="tab">
                            <i class="mdi mdi-flash"></i> Express <span class="badge badge-success" id="express_badge">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="approved-tab" data-toggle="tab" href="#approved" role="tab">
                            <i class="mdi mdi-check"></i> Approved
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="rejected-tab" data-toggle="tab" href="#rejected" role="tab">
                            <i class="mdi mdi-close"></i> Rejected
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="all-tab" data-toggle="tab" href="#all" role="tab">
                            <i class="mdi mdi-view-list"></i> All
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <div class="table-responsive">
                        <table id="requestsTable" class="table table-sm table-bordered table-striped table-hover display">
                            <thead>
                                <tr>
                                    <th>S/N</th>
                                    <th>Request ID</th>
                                    <th>Date</th>
                                    <th>Patient</th>
                                    <th>HMO</th>
                                    <th>Type</th>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Original</th>
                                    <th>HMO Covers</th>
                                    <th>Patient Pays</th>
                                    <th>Coverage</th>
                                    <th>Status</th>
                                    <th>Validated By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- View Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Request Details</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Patient Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr><th width="40%">Name:</th><td id="detail_patient_name"></td></tr>
                            <tr><th>File No:</th><td id="detail_file_no"></td></tr>
                            <tr><th>HMO No:</th><td id="detail_hmo_no"></td></tr>
                            <tr><th>HMO:</th><td id="detail_hmo_name"></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Request Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr><th width="40%">Request ID:</th><td id="detail_request_id"></td></tr>
                            <tr><th>Date:</th><td id="detail_created_at"></td></tr>
                            <tr><th>Type:</th><td id="detail_item_type"></td></tr>
                            <tr><th>Item:</th><td id="detail_item_name"></td></tr>
                            <tr><th>Quantity:</th><td id="detail_qty"></td></tr>
                        </table>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-12">
                        <h6 class="text-primary">Pricing & Coverage</h6>
                        <table class="table table-sm table-bordered">
                            <tr>
                                <th>Original Price</th>
                                <th>HMO Covers (Claims)</th>
                                <th>Patient Pays</th>
                                <th>Coverage Mode</th>
                            </tr>
                            <tr>
                                <td>₦<span id="detail_original_price"></span></td>
                                <td>₦<span id="detail_claims_amount"></span></td>
                                <td>₦<span id="detail_payable_amount"></span></td>
                                <td><span id="detail_coverage_mode"></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-12">
                        <h6 class="text-primary">Validation Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr><th width="20%">Status:</th><td><span id="detail_validation_status"></span></td></tr>
                            <tr><th>Auth Code:</th><td id="detail_auth_code">-</td></tr>
                            <tr><th>Validated By:</th><td id="detail_validated_by">-</td></tr>
                            <tr><th>Validated At:</th><td id="detail_validated_at">-</td></tr>
                            <tr><th>Notes:</th><td id="detail_validation_notes">-</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Approve Request</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="approveForm">
                @csrf
                <input type="hidden" id="approve_request_id">
                <input type="hidden" id="approve_coverage_mode">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Confirm Approval:</strong> You are about to approve this HMO request.
                    </div>
                    <div class="form-group" id="auth_code_div" style="display:none;">
                        <label>Authorization Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="auth_code" name="auth_code" placeholder="Enter HMO auth code">
                        <small class="form-text text-danger" id="error_auth_code"></small>
                        <small class="form-text text-muted">Required for secondary coverage</small>
                    </div>
                    <div class="form-group">
                        <label>Validation Notes</label>
                        <textarea class="form-control" id="approve_notes" name="validation_notes" rows="3" placeholder="Optional notes..."></textarea>
                        <small class="form-text text-danger" id="error_validation_notes"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-check"></i> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Reject Request</h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="rejectForm">
                @csrf
                <input type="hidden" id="reject_request_id">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Confirm Rejection:</strong> You are about to reject this HMO request.
                    </div>
                    <div class="form-group">
                        <label>Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject_notes" name="validation_notes" rows="3" placeholder="Please provide reason for rejection..." required></textarea>
                        <small class="form-text text-danger" id="error_reject_notes"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-times"></i> Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<script>
$(function() {
    let currentTab = 'pending';
    let table;

    // Initialize DataTable
    function initDataTable() {
        if (table) {
            table.destroy();
        }

        table = $('#requestsTable').DataTable({
            "dom": 'Bfrtip',
            "iDisplayLength": 50,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "{{ route('hmo.requests') }}",
                "type": "GET",
                "data": function(d) {
                    d.tab = currentTab;
                    d.hmo_id = $('#filter_hmo').val();
                    d.coverage_mode = $('#filter_coverage').val();
                    d.date_from = $('#filter_date_from').val();
                    d.date_to = $('#filter_date_to').val();
                    d.search = $('#search_input').val();
                }
            },
            "columns": [
                { "data": "DT_RowIndex", "orderable": false, "searchable": false },
                { "data": "id" },
                { "data": "request_date" },
                { "data": "patient_info" },
                { "data": "hmo_name" },
                { "data": "item_type" },
                { "data": "item_name" },
                { "data": "qty" },
                { "data": "original_price" },
                { "data": "claims_amount_formatted" },
                { "data": "payable_amount_formatted" },
                { "data": "coverage_badge" },
                { "data": "status_badge" },
                { "data": "validated_info" },
                { "data": "actions", "orderable": false, "searchable": false }
            ],
            "order": [[2, 'desc']]
        });
    }

    // Load queue counts
    function loadQueueCounts() {
        $.get("{{ route('hmo.queue-counts') }}", function(data) {
            $('#pending_count, #pending_badge').text(data.pending);
            $('#express_count, #express_badge').text(data.express);
            $('#approved_today_count').text(data.approved_today);
            $('#rejected_today_count').text(data.rejected_today);
        });
    }

    // Initial load
    initDataTable();
    loadQueueCounts();

    // Tab change
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        currentTab = $(e.target).attr('href').substring(1);
        table.ajax.reload();
    });

    // Apply filters
    $('#applyFilters, #search_input').on('click keyup', function(e) {
        if (e.type === 'click' || (e.type === 'keyup' && e.keyCode === 13)) {
            table.ajax.reload();
        }
    });

    // View details
    $(document).on('click', '.view-details-btn', function() {
        let id = $(this).data('id');

        $.get("{{ url('hmo/requests') }}/" + id, function(response) {
            let data = response.data;

            $('#detail_request_id').text(data.id);
            $('#detail_patient_name').text(data.patient_name);
            $('#detail_file_no').text(data.file_no);
            $('#detail_hmo_no').text(data.hmo_no || 'N/A');
            $('#detail_hmo_name').text(data.hmo_name);
            $('#detail_item_type').text(data.item_type);
            $('#detail_item_name').text(data.item_name);
            $('#detail_qty').text(data.qty);
            $('#detail_original_price').text(parseFloat(data.original_price || 0).toFixed(2));
            $('#detail_claims_amount').text(parseFloat(data.claims_amount || 0).toFixed(2));
            $('#detail_payable_amount').text(parseFloat(data.payable_amount || 0).toFixed(2));

            let coverageBadge = data.coverage_mode === 'express' ? '<span class="badge badge-success">EXPRESS</span>' :
                               data.coverage_mode === 'primary' ? '<span class="badge badge-warning">PRIMARY</span>' :
                               '<span class="badge badge-danger">SECONDARY</span>';
            $('#detail_coverage_mode').html(coverageBadge);

            let statusBadge = data.validation_status === 'approved' ? '<span class="badge badge-success">APPROVED</span>' :
                             data.validation_status === 'rejected' ? '<span class="badge badge-danger">REJECTED</span>' :
                             '<span class="badge badge-warning">PENDING</span>';
            $('#detail_validation_status').html(statusBadge);

            $('#detail_auth_code').text(data.auth_code || '-');
            $('#detail_validated_by').text(data.validated_by_name || '-');
            $('#detail_validated_at').text(data.validated_at || '-');
            $('#detail_validation_notes').text(data.validation_notes || '-');
            $('#detail_created_at').text(data.created_at);

            $('#detailsModal').modal('show');
        });
    });

    // Show approve modal
    $(document).on('click', '.approve-btn', function() {
        let id = $(this).data('id');
        let mode = $(this).data('mode');

        $('#approve_request_id').val(id);
        $('#approve_coverage_mode').val(mode);
        $('#approve_notes').val('');
        $('#auth_code').val('');
        $('.text-danger').text('');

        if (mode === 'secondary') {
            $('#auth_code_div').show();
            $('#auth_code').prop('required', true);
        } else {
            $('#auth_code_div').hide();
            $('#auth_code').prop('required', false);
        }

        $('#approveModal').modal('show');
    });

    // Submit approve form
    $('#approveForm').submit(function(e) {
        e.preventDefault();
        $('.text-danger').text('');

        let id = $('#approve_request_id').val();

        $.ajax({
            url: "{{ url('hmo/requests') }}/" + id + "/approve",
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                $('#approveModal').modal('hide');
                table.ajax.reload();
                loadQueueCounts();
                swal('Success', response.message, 'success');
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    let errors = xhr.responseJSON.errors;
                    $.each(errors, function(key, value) {
                        $('#error_' + key).text(value[0]);
                    });
                } else {
                    swal('Error', xhr.responseJSON.message || 'An error occurred', 'error');
                }
            }
        });
    });

    // Show reject modal
    $(document).on('click', '.reject-btn', function() {
        let id = $(this).data('id');

        $('#reject_request_id').val(id);
        $('#reject_notes').val('');
        $('.text-danger').text('');

        $('#rejectModal').modal('show');
    });

    // Submit reject form
    $('#rejectForm').submit(function(e) {
        e.preventDefault();
        $('.text-danger').text('');

        let id = $('#reject_request_id').val();

        $.ajax({
            url: "{{ url('hmo/requests') }}/" + id + "/reject",
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                $('#rejectModal').modal('hide');
                table.ajax.reload();
                loadQueueCounts();
                swal('Success', response.message, 'success');
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    $('#error_reject_notes').text(xhr.responseJSON.message);
                } else {
                    swal('Error', xhr.responseJSON.message || 'An error occurred', 'error');
                }
            }
        });
    });

    // Auto-refresh counts every 30 seconds
    setInterval(loadQueueCounts, 30000);
});
</script>
@endsection
