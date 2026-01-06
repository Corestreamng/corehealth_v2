@extends('admin.layouts.app')
@section('title', 'HMO Tariff Management')
@section('page_name', 'HMO Tariffs')
@section('subpage_name', 'Tariff Management')
@section('content')

<section class="content">
    <div class="container-fluid">
        <!-- Filters Card -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="card-title"><i class="fa fa-filter"></i> Filters</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>HMO</label>
                            <select class="form-control form-control-sm" id="filter_hmo_id">
                                <option value="">All HMOs</option>
                                @foreach($hmos as $hmo)
                                    <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Type</label>
                            <select class="form-control form-control-sm" id="filter_type">
                                <option value="">All Types</option>
                                <option value="product">Products</option>
                                <option value="service">Services</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Coverage Mode</label>
                            <select class="form-control form-control-sm" id="filter_coverage_mode">
                                <option value="">All Modes</option>
                                <option value="express">Express</option>
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-primary btn-block" id="applyFilters">
                                <i class="fa fa-search"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Card -->
        <div class="card">
            <div class="card-header">
                <button class="btn btn-success" id="addTariffBtn">
                    <i class="fa fa-plus"></i> Add New Tariff
                </button>
                <button class="btn btn-info" id="exportCsvBtn">
                    <i class="fa fa-download"></i> Export CSV
                </button>
                <button class="btn btn-warning" id="importCsvBtn">
                    <i class="fa fa-upload"></i> Import CSV
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tariffsTable" class="table table-sm table-bordered table-striped display">
                        <thead>
                            <tr>
                                <th>S/N</th>
                                <th>HMO</th>
                                <th>Type</th>
                                <th>Item</th>
                                <th>Original Price</th>
                                <th>Claims Amount</th>
                                <th>Payable Amount</th>
                                <th>Coverage Mode</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Add/Edit Tariff Modal -->
<div class="modal fade" id="tariffModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="tariffModalTitle">Add New Tariff</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="tariffForm">
                @csrf
                <input type="hidden" id="tariff_id" name="tariff_id">
                <input type="hidden" id="form_method" value="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>HMO <span class="text-danger">*</span></label>
                                <select class="form-control" id="hmo_id" name="hmo_id" required>
                                    <option value="">Select HMO</option>
                                    @foreach($hmos as $hmo)
                                        <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-danger" id="error_hmo_id"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Item Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="item_type" name="item_type" required>
                                    <option value="">Select Type</option>
                                    <option value="product">Product</option>
                                    <option value="service">Service</option>
                                </select>
                                <small class="form-text text-danger" id="error_item_type"></small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6" id="product_select_div" style="display:none;">
                            <div class="form-group">
                                <label>Product <span class="text-danger">*</span></label>
                                <select class="form-control" id="product_id" name="product_id">
                                    <option value="">Select Product</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" data-price="{{ $product->price ? $product->price->current_sale_price : 0 }}">
                                            {{ $product->product_name }} - ₦{{ $product->price ? number_format($product->price->current_sale_price, 2) : 0 }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-danger" id="error_product_id"></small>
                            </div>
                        </div>
                        <div class="col-md-6" id="service_select_div" style="display:none;">
                            <div class="form-group">
                                <label>Service <span class="text-danger">*</span></label>
                                <select class="form-control" id="service_id" name="service_id">
                                    <option value="">Select Service</option>
                                    @foreach($services as $service)
                                        <option value="{{ $service->id }}" data-price="{{ $service->price ? $service->price->sale_price : 0 }}">
                                            {{ $service->service_name }} - ₦{{ $service->price ? number_format($service->price->sale_price, 2) : 0 }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-danger" id="error_service_id"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Coverage Mode <span class="text-danger">*</span></label>
                                <select class="form-control" id="coverage_mode" name="coverage_mode" required>
                                    <option value="">Select Mode</option>
                                    <option value="express">Express (Auto-Approved)</option>
                                    <option value="primary">Primary (Requires Validation)</option>
                                    <option value="secondary">Secondary (Requires Validation + Auth Code)</option>
                                </select>
                                <small class="form-text text-danger" id="error_coverage_mode"></small>
                                <small class="form-text text-muted">
                                    <strong>Express:</strong> Auto-approved<br>
                                    <strong>Primary:</strong> Requires HMO executive approval<br>
                                    <strong>Secondary:</strong> Requires approval + authorization code
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Claims Amount (HMO Covers) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" class="form-control" id="claims_amount" name="claims_amount"
                                           min="0" step="0.01" placeholder="0.00" required>
                                </div>
                                <small class="form-text text-danger" id="error_claims_amount"></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payable Amount (Patient Pays) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" class="form-control" id="payable_amount" name="payable_amount"
                                           min="0" step="0.01" placeholder="0.00" required>
                                </div>
                                <small class="form-text text-danger" id="error_payable_amount"></small>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <strong>Note:</strong> Claims Amount + Payable Amount should typically equal the original price,
                        but you can adjust them as needed for your HMO agreements.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveTariffBtn">
                        <i class="fa fa-save"></i> Save Tariff
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CSV Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Import Tariffs from CSV</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="{{ route('hmo-tariffs.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>CSV File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control-file" name="csv_file" accept=".csv,.txt" required>
                        <small class="form-text text-muted">
                            CSV format: ID, HMO Name, Item Type, Item Name, Original Price, Claims Amount, Payable Amount, Coverage Mode
                        </small>
                    </div>
                    <div class="alert alert-info">
                        <strong>Instructions:</strong>
                        <ul class="mb-0">
                            <li>Export existing tariffs to get the correct CSV format</li>
                            <li>Existing tariffs will be updated if found</li>
                            <li>New tariffs will be created if not found</li>
                            <li>Max file size: 10MB</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fa fa-upload"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this tariff?</p>
                <input type="hidden" id="delete_tariff_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fa fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<script>
$(function() {
    let table = $('#tariffsTable').DataTable({
        "dom": 'Bfrtip',
        "iDisplayLength": 50,
        "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "{{ route('hmo-tariffs.data') }}",
            "type": "GET",
            "data": function(d) {
                d.hmo_id = $('#filter_hmo_id').val();
                d.type = $('#filter_type').val();
                d.coverage_mode = $('#filter_coverage_mode').val();
            }
        },
        "columns": [
            { "data": "DT_RowIndex", "orderable": false, "searchable": false },
            { "data": "hmo_name" },
            { "data": "item_type" },
            { "data": "item_name" },
            { "data": "original_price" },
            { "data": "claims_amount_formatted" },
            { "data": "payable_amount_formatted" },
            { "data": "coverage_badge" },
            { "data": "actions", "orderable": false, "searchable": false }
        ],
        "order": [[1, 'asc']]
    });

    // Apply filters
    $('#applyFilters').click(function() {
        table.ajax.reload();
    });

    // Show add tariff modal
    $('#addTariffBtn').click(function() {
        $('#tariffModalTitle').text('Add New Tariff');
        $('#tariffForm')[0].reset();
        $('#tariff_id').val('');
        $('#form_method').val('POST');
        $('#hmo_id, #item_type, #product_id, #service_id').prop('disabled', false);
        $('.text-danger').text('');
        $('#tariffModal').modal('show');
    });

    // Item type change handler
    $('#item_type').change(function() {
        let type = $(this).val();
        if (type === 'product') {
            $('#product_select_div').show();
            $('#service_select_div').hide();
            $('#service_id').val('').prop('required', false);
            $('#product_id').prop('required', true);
        } else if (type === 'service') {
            $('#service_select_div').show();
            $('#product_select_div').hide();
            $('#product_id').val('').prop('required', false);
            $('#service_id').prop('required', true);
        } else {
            $('#product_select_div, #service_select_div').hide();
            $('#product_id, #service_id').val('').prop('required', false);
        }
    });

    // Auto-fill prices when product/service selected
    $('#product_id, #service_id').change(function() {
        let price = $(this).find('option:selected').data('price');
        if (price) {
            $('#payable_amount').val(price);
            $('#claims_amount').val(0);
        }
    });

    // Submit tariff form
    $('#tariffForm').submit(function(e) {
        e.preventDefault();
        $('.text-danger').text('');

        let tariffId = $('#tariff_id').val();
        let url = tariffId ? "{{ url('admin/hmo-tariffs') }}/" + tariffId : "{{ route('hmo-tariffs.store') }}";
        let method = tariffId ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            type: method,
            data: $(this).serialize(),
            success: function(response) {
                $('#tariffModal').modal('hide');
                table.ajax.reload();
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

    // Edit tariff
    $(document).on('click', '.edit-tariff-btn', function() {
        let id = $(this).data('id');

        $.get("{{ url('admin/hmo-tariffs') }}/" + id, function(response) {
            let tariff = response.data;

            $('#tariffModalTitle').text('Edit Tariff');
            $('#tariff_id').val(tariff.id);
            $('#form_method').val('PUT');

            // Disable HMO and item selection for edit
            $('#hmo_id').val(tariff.hmo_id).prop('disabled', true);
            $('#item_type').prop('disabled', true);
            $('#product_id, #service_id').prop('disabled', true);

            if (tariff.product_id) {
                $('#item_type').val('product');
                $('#product_select_div').show();
                $('#product_id').val(tariff.product_id);
            } else {
                $('#item_type').val('service');
                $('#service_select_div').show();
                $('#service_id').val(tariff.service_id);
            }

            $('#claims_amount').val(tariff.claims_amount);
            $('#payable_amount').val(tariff.payable_amount);
            $('#coverage_mode').val(tariff.coverage_mode);

            $('.text-danger').text('');
            $('#tariffModal').modal('show');
        });
    });

    // Delete tariff
    $(document).on('click', '.delete-tariff-btn', function() {
        let id = $(this).data('id');
        $('#delete_tariff_id').val(id);
        $('#deleteModal').modal('show');
    });

    $('#confirmDeleteBtn').click(function() {
        let id = $('#delete_tariff_id').val();

        $.ajax({
            url: "{{ url('admin/hmo-tariffs') }}/" + id,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                $('#deleteModal').modal('hide');
                table.ajax.reload();
                swal('Deleted', response.message, 'success');
            },
            error: function(xhr) {
                swal('Error', 'Failed to delete tariff', 'error');
            }
        });
    });

    // Export CSV
    $('#exportCsvBtn').click(function() {
        let params = '?hmo_id=' + $('#filter_hmo_id').val() +
                     '&type=' + $('#filter_type').val() +
                     '&coverage_mode=' + $('#filter_coverage_mode').val();
        window.location.href = "{{ route('hmo-tariffs.export') }}" + params;
    });

    // Import CSV
    $('#importCsvBtn').click(function() {
        $('#importModal').modal('show');
    });
});
</script>
@endsection
