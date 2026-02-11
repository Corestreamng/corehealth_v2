@extends('admin.layouts.app')
@section('title', 'Pharmacy Returns Management')
@section('page_name', 'Pharmacy')
@section('subpage_name', 'Returns Management')
@section('content')

<div class="card-modern mb-2">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Pharmacy Returns</h4>
            <button class="btn btn-primary" data-toggle="modal" data-target="#createReturnModal">
                <i class="mdi mdi-plus"></i> Create Return
            </button>
        </div>

        <form id="filterForm">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="status_filter">Status</label>
                        <select class="form-control form-control-sm" id="status_filter" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" class="form-control form-control-sm" id="start_date" name="start_date"
                            value="{{ date('Y-m-d', strtotime('-7 days')) }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" class="form-control form-control-sm" id="end_date" name="end_date"
                            value="{{ date('Y-m-d') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" id="applyFilters" class="btn btn-primary btn-sm d-block w-100">
                            <i class="mdi mdi-filter"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<section class="content">
    <div class="col-12">
        <div class="card-modern">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="returnsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Details</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Create Return Modal -->
<div class="modal fade" id="createReturnModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Pharmacy Return</h5>
                <button type="button" class="close" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="createReturnForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Search Dispensed Item</label>
                        <input type="text" class="form-control" id="dispensedItemSearch"
                            placeholder="Search by patient name, product name, or bill number...">
                        <div id="dispensedItemResults" class="list-group mt-2" style="max-height: 300px; overflow-y: auto;"></div>
                    </div>

                    <div id="selectedItemDetails" style="display: none;">
                        <div class="alert alert-info">
                            <strong>Selected Item:</strong>
                            <div id="selectedItemInfo"></div>
                        </div>

                        <input type="hidden" id="product_request_id" name="product_request_id">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Quantity to Return <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="qty_returned" name="qty_returned"
                                        step="0.01" min="0.01" required>
                                    <small class="form-text text-muted">Max: <span id="max_qty"></span></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Return Condition <span class="text-danger">*</span></label>
                                    <select class="form-control" id="return_condition" name="return_condition" required>
                                        <option value="">Select Condition</option>
                                        <option value="good">Good (Can be restocked)</option>
                                        <option value="expired">Expired</option>
                                        <option value="damaged">Damaged</option>
                                        <option value="wrong_item">Wrong Item</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Return Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="return_reason" name="return_reason"
                                rows="3" required minlength="10"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitReturnBtn" disabled>
                        <i class="mdi mdi-check"></i> Submit Return
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Return</h5>
                <button type="button" class="close" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="approveForm">
                <input type="hidden" id="approve_return_id" name="return_id">
                <div class="modal-body">
                    <p>Are you sure you want to approve this return? This will create a journal entry and process the refund.</p>
                    <div class="form-group">
                        <label>Approval Notes</label>
                        <textarea class="form-control" name="approval_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="mdi mdi-check"></i> Approve
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
            <div class="modal-header">
                <h5 class="modal-title">Reject Return</h5>
                <button type="button" class="close" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="rejectForm">
                <input type="hidden" id="reject_return_id" name="return_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="rejection_reason" rows="3" required minlength="10"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="mdi mdi-close"></i> Reject
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
    // Initialize DataTable
    const table = $('#returnsTable').DataTable({
        "dom": 'Bfrtip',
        "iDisplayLength": 50,
        "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "{{ route('pharmacy.returns.datatables') }}",
            "type": "GET",
            "data": function(d) {
                d.status = $('#status_filter').val();
                d.date_from = $('#start_date').val();
                d.date_to = $('#end_date').val();
            }
        },
        "columns": [
            { data: "DT_RowIndex", name: "DT_RowIndex", orderable: false, searchable: false },
            { data: "item_info", name: "product_name", orderable: false },
            { data: "details_info", orderable: false },
            { data: "status_info", orderable: false },
            { data: "actions", name: "actions", orderable: false, searchable: false }
        ]
    });

    // Apply filters
    $('#applyFilters').on('click', function() {
        table.ajax.reload();
    });

    // Search dispensed items
    let searchTimeout;
    $('#dispensedItemSearch').on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val();

        if (query.length < 2) {
            $('#dispensedItemResults').empty();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.ajax({
                url: "{{ route('pharmacy.returns.search-dispensed') }}",
                data: { search: query },
                success: function(response) {
                    let html = '';
                    if (response.length === 0) {
                        html = '<div class="list-group-item">No dispensed items found</div>';
                    } else {
                        response.forEach(function(item) {
                            html += `
                                <a href="#" class="list-group-item list-group-item-action select-item"
                                   data-item='${JSON.stringify(item)}'>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong>${item.product_name}</strong><br>
                                            <small>Patient: ${item.patient_name} | Bill: ${item.bill_number || 'N/A'}</small><br>
                                            <small>Qty: ${item.qty} | Amount: ${item.amount}</small>
                                        </div>
                                        <div>
                                            <span class="badge badge-info">${item.dispensed_date}</span>
                                        </div>
                                    </div>
                                </a>
                            `;
                        });
                    }
                    $('#dispensedItemResults').html(html);
                }
            });
        }, 300);
    });

    // Select item
    $(document).on('click', '.select-item', function(e) {
        e.preventDefault();
        const item = $(this).data('item');

        $('#product_request_id').val(item.product_request_id);
        $('#max_qty').text(item.qty);
        $('#qty_returned').attr('max', item.qty).val(item.qty);

        $('#selectedItemInfo').html(`
            <strong>${item.product_name}</strong><br>
            Patient: ${item.patient_name}<br>
            Dispensed Qty: ${item.qty} | Amount: ${item.amount}
        `);

        $('#selectedItemDetails').show();
        $('#submitReturnBtn').prop('disabled', false);
        $('#dispensedItemResults').empty();
        $('#dispensedItemSearch').val('');
    });

    // Submit return
    $('#createReturnForm').on('submit', function(e) {
        e.preventDefault();

        const formData = {
            product_request_id: $('#product_request_id').val(),
            qty_returned: $('#qty_returned').val(),
            return_condition: $('#return_condition').val(),
            return_reason: $('#return_reason').val()
        };

        $.ajax({
            url: "{{ route('pharmacy.returns.store') }}",
            method: 'POST',
            data: formData,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                $('#createReturnModal').modal('hide');
                $('#createReturnForm')[0].reset();
                $('#selectedItemDetails').hide();
                $('#submitReturnBtn').prop('disabled', true);
                table.ajax.reload();
                Swal.fire('Success!', response.message, 'success');
            },
            error: function(xhr) {
                Swal.fire('Error!', xhr.responseJSON?.message || 'Failed to create return', 'error');
            }
        });
    });

    // Approve return
    $(document).on('click', '.approve-return', function() {
        $('#approve_return_id').val($(this).data('id'));
        $('#approveModal').modal('show');
    });

    $('#approveForm').on('submit', function(e) {
        e.preventDefault();
        const returnId = $('#approve_return_id').val();

        $.ajax({
            url: `/pharmacy/returns/${returnId}/approve`,
            method: 'POST',
            data: $(this).serialize(),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                $('#approveModal').modal('hide');
                table.ajax.reload();
                Swal.fire('Success!', response.message, 'success');
            },
            error: function(xhr) {
                Swal.fire('Error!', xhr.responseJSON?.message || 'Failed to approve return', 'error');
            }
        });
    });

    // Reject return
    $(document).on('click', '.reject-return', function() {
        $('#reject_return_id').val($(this).data('id'));
        $('#rejectModal').modal('show');
    });

    $('#rejectForm').on('submit', function(e) {
        e.preventDefault();
        const returnId = $('#reject_return_id').val();

        $.ajax({
            url: `/pharmacy/returns/${returnId}/reject`,
            method: 'POST',
            data: $(this).serialize(),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                $('#rejectModal').modal('hide');
                table.ajax.reload();
                Swal.fire('Success!', response.message, 'success');
            },
            error: function(xhr) {
                Swal.fire('Error!', xhr.responseJSON?.message || 'Failed to reject return', 'error');
            }
        });
    });
});
</script>
@endsection
