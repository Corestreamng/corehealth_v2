@extends('admin.layouts.app')
@section('title', 'Pharmacy Damage Reports')
@section('page_name', 'Pharmacy')
@section('subpage_name', 'Damage Reports')
@section('content')

<div class="card-modern mb-2">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Pharmacy Damage Reports</h4>
            <button class="btn btn-danger" data-toggle="modal" data-target="#createDamageModal">
                <i class="mdi mdi-alert-octagon"></i> Report Damage
            </button>
        </div>

        <form id="filterForm">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="status_filter">Status</label>
                        <select class="form-control form-control-sm" id="status_filter" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="damage_type_filter">Damage Type</label>
                        <select class="form-control form-control-sm" id="damage_type_filter" name="damage_type">
                            <option value="">All Types</option>
                            <option value="expired">Expired</option>
                            <option value="broken">Broken</option>
                            <option value="contaminated">Contaminated</option>
                            <option value="spoiled">Spoiled</option>
                            <option value="theft">Theft</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="store_filter">Store</label>
                        <select class="form-control form-control-sm" id="store_filter" name="store_id">
                            <option value="">All Stores</option>
                            @foreach(\App\Models\Store::where('store_type', 'pharmacy')->orderBy('store_name')->get() as $store)
                                <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" class="form-control form-control-sm" id="start_date" name="start_date"
                            value="{{ date('Y-m-d', strtotime('-30 days')) }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" class="form-control form-control-sm" id="end_date" name="end_date"
                            value="{{ date('Y-m-d') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" id="applyFilters" class="btn btn-primary btn-sm d-block w-100">
                            <i class="mdi mdi-filter"></i> Apply
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
                    <table class="table table-sm table-bordered table-striped" style="width: 100%" id="damagesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Details</th>
                                <th>Value</th>
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

<!-- Create Damage Modal -->
<div class="modal fade" id="createDamageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Report Pharmacy Damage</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="createDamageForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Store <span class="text-danger">*</span></label>
                                <select class="form-control" id="store_id" name="store_id" required>
                                    <option value="">Select Store</option>
                                    @foreach(\App\Models\Store::where('store_type', 'pharmacy')->orderBy('store_name')->get() as $store)
                                        <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Product <span class="text-danger">*</span></label>
                                <select class="form-control" id="product_id" name="product_id" required disabled>
                                    <option value="">Select store first</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Batch (Optional)</label>
                                <select class="form-control" id="batch_id" name="batch_id" disabled>
                                    <option value="">Select product first</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Damage Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="damage_type" name="damage_type" required>
                                    <option value="">Select Type</option>
                                    <option value="expired">Expired</option>
                                    <option value="broken">Broken</option>
                                    <option value="contaminated">Contaminated</option>
                                    <option value="spoiled">Spoiled</option>
                                    <option value="theft">Theft/Shrinkage</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Quantity Damaged <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="qty_damaged" name="qty_damaged"
                                    step="0.01" min="0.01" required>
                                <small class="form-text text-muted">Available: <span id="available_qty">-</span></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Unit Cost <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="unit_cost" name="unit_cost"
                                    step="0.01" min="0" required readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Total Value</label>
                                <input type="text" class="form-control" id="total_value_display" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Discovered Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="discovered_date" name="discovered_date"
                            value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="form-group">
                        <label>Damage Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="damage_reason" name="damage_reason"
                            rows="3" required minlength="10" placeholder="Describe the damage in detail..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Additional Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="mdi mdi-alert-octagon"></i> Submit Damage Report
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
                <h5 class="modal-title">Approve Damage Report</h5>
                <button type="button" class="close" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="approveForm">
                <input type="hidden" id="approve_damage_id" name="damage_id">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> Approving this damage report will:
                        <ul>
                            <li>Create a journal entry to write off the damaged inventory</li>
                            <li>Deduct the damaged quantity from stock</li>
                            <li>This action cannot be undone</li>
                        </ul>
                    </div>
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
                <h5 class="modal-title">Reject Damage Report</h5>
                <button type="button" class="close" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="rejectForm">
                <input type="hidden" id="reject_damage_id" name="damage_id">
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function() {
    // Initialize Select2
    $('#product_id, #batch_id').select2({
        dropdownParent: $('#createDamageModal'),
        width: '100%'
    });

    // Initialize DataTable
    const table = $('#damagesTable').DataTable({
        "dom": 'Bfrtip',
        "iDisplayLength": 50,
        "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "{{ route('pharmacy.damages.datatables') }}",
            "type": "GET",
            "data": function(d) {
                d.status = $('#status_filter').val();
                d.damage_type = $('#damage_type_filter').val();
                d.store_id = $('#store_filter').val();
                d.date_from = $('#start_date').val();
                d.date_to = $('#end_date').val();
            }
        },
        "columns": [
            { data: "DT_RowIndex", name: "DT_RowIndex", orderable: false, searchable: false },
            { data: "item_info", name: "product_name", orderable: false },
            { data: "details_info", orderable: false },
            { data: "total_value", name: "total_value", render: function(d) { return '₦' + parseFloat(d).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); } },
            { data: "status_info", orderable: false },
            { data: "actions", name: "actions", orderable: false, searchable: false }
        ]
    });

    // Apply filters
    $('#applyFilters').on('click', function() {
        table.ajax.reload();
    });

    // Load products when store is selected
    $('#store_id').on('change', function() {
        const storeId = $(this).val();
        $('#product_id').prop('disabled', !storeId).html('<option value="">Loading...</option>');
        $('#batch_id').prop('disabled', true).html('<option value="">Select product first</option>');
        $('#available_qty').text('-');
        $('#unit_cost').val('');

        if (storeId) {
            $.ajax({
                url: "{{ route('pharmacy.damages.search-products') }}",
                data: { store_id: storeId, search: '' },
                success: function(response) {
                    let options = '<option value="">Select Product</option>';
                    response.forEach(function(product) {
                        options += `<option value="${product.id}" data-qty="${product.current_quantity}" data-cost="${product.unit_cost}">
                            ${product.text}
                        </option>`;
                    });
                    $('#product_id').html(options);
                }
            });
        }
    });

    // Load batches and set unit cost when product is selected
    $('#product_id').on('change', function() {
        const productId = $(this).val();
        const storeId = $('#store_id').val();
        const selectedOption = $(this).find('option:selected');

        $('#available_qty').text(selectedOption.data('qty') || '-');
        $('#unit_cost').val(selectedOption.data('cost') || '');
        calculateTotal();

        $('#batch_id').prop('disabled', !productId).html('<option value="">Loading...</option>');

        if (productId && storeId) {
            $.ajax({
                url: "{{ route('pharmacy.damages.get-batches') }}",
                data: { product_id: productId, store_id: storeId },
                success: function(response) {
                    let options = '<option value="">No specific batch</option>';
                    response.forEach(function(batch) {
                        options += `<option value="${batch.id}" data-qty="${batch.quantity_available}" data-cost="${batch.unit_cost}">
                            ${batch.text}
                        </option>`;
                    });
                    $('#batch_id').html(options);
                }
            });
        }
    });

    // Update unit cost and available qty when batch is selected
    $('#batch_id').on('change', function() {
        const selectedBatch = $(this).find('option:selected');
        if ($(this).val()) {
            $('#available_qty').text(selectedBatch.data('qty') || '-');
            $('#unit_cost').val(selectedBatch.data('cost') || '');
        } else {
            const selectedProduct = $('#product_id').find('option:selected');
            $('#available_qty').text(selectedProduct.data('qty') || '-');
            $('#unit_cost').val(selectedProduct.data('cost') || '');
        }
        calculateTotal();
    });

    // Calculate total value
    $('#qty_damaged, #unit_cost').on('input', calculateTotal);

    function calculateTotal() {
        const qty = parseFloat($('#qty_damaged').val()) || 0;
        const cost = parseFloat($('#unit_cost').val()) || 0;
        const total = qty * cost;
        $('#total_value_display').val(total.toFixed(2));
    }

    // Submit damage report
    $('#createDamageForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: "{{ route('pharmacy.damages.store') }}",
            method: 'POST',
            data: $(this).serialize(),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                $('#createDamageModal').modal('hide');
                $('#createDamageForm')[0].reset();
                $('#product_id').prop('disabled', true);
                $('#batch_id').prop('disabled', true);
                table.ajax.reload();
                Swal.fire('Success!', response.message, 'success');
            },
            error: function(xhr) {
                Swal.fire('Error!', xhr.responseJSON?.message || 'Failed to create damage report', 'error');
            }
        });
    });

    // Approve damage
    $(document).on('click', '.approve-damage', function() {
        $('#approve_damage_id').val($(this).data('id'));
        $('#approveModal').modal('show');
    });

    $('#approveForm').on('submit', function(e) {
        e.preventDefault();
        const damageId = $('#approve_damage_id').val();

        $.ajax({
            url: `/pharmacy/damages/${damageId}/approve`,
            method: 'POST',
            data: $(this).serialize(),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                $('#approveModal').modal('hide');
                table.ajax.reload();
                Swal.fire('Success!', response.message, 'success');
            },
            error: function(xhr) {
                Swal.fire('Error!', xhr.responseJSON?.message || 'Failed to approve damage', 'error');
            }
        });
    });

    // Reject damage
    $(document).on('click', '.reject-damage', function() {
        $('#reject_damage_id').val($(this).data('id'));
        $('#rejectModal').modal('show');
    });

    $('#rejectForm').on('submit', function(e) {
        e.preventDefault();
        const damageId = $('#reject_damage_id').val();

        $.ajax({
            url: `/pharmacy/damages/${damageId}/reject`,
            method: 'POST',
            data: $(this).serialize(),
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                $('#rejectModal').modal('hide');
                table.ajax.reload();
                Swal.fire('Success!', response.message, 'success');
            },
            error: function(xhr) {
                Swal.fire('Error!', xhr.responseJSON?.message || 'Failed to reject damage', 'error');
            }
        });
    });
});
</script>
@endsection
