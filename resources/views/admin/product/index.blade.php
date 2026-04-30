@extends('admin.layouts.app')
@section('title', 'Products')
@section('page_name', 'Products')
@section('subpage_name', 'Products List')
@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
        .badge-drug { background: #d4edda; color: #155724; }
        .badge-consumable { background: #fff3cd; color: #856404; }
        .badge-utility { background: #d1ecf1; color: #0c5460; }
        .filter-bar { background: #f8f9fa; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .filter-bar select { max-width: 180px; display: inline-block; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection
@section('content')
    <div class="container-fluid">
        <div class="card-modern">
            <div class="card-header-modern d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1 font-weight-bold text-dark">
                        <i class="mdi mdi-pill text-primary"></i> Products
                    </h2>
                    <p class="text-muted mb-0">Manage your product inventory, pricing and stock levels</p>
                </div>
                <div>
                    <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm">
                        <i class="mdi mdi-plus"></i> Add Product
                    </a>
                    <a href="{{ route('import-export.template.products') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="mdi mdi-file-download-outline"></i> Template
                    </a>
                    <button type="button" class="btn btn-outline-info btn-sm" id="btn-stock-template" data-bs-toggle="modal" data-bs-target="#storeSelectModal">
                        <i class="mdi mdi-database-export-outline"></i> Stock Template
                    </button>
                    <a href="{{ route('import-export.export.products') }}" class="btn btn-outline-success btn-sm">
                        <i class="mdi mdi-file-export-outline"></i> Export CSV
                    </a>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#importProductsModal">
                        <i class="mdi mdi-file-import-outline"></i> Import
                    </button>
                </div>
            </div>

            <div class="card-body">
                {{-- Filter Bar --}}
                <div class="filter-bar d-flex align-items-center gap-2 flex-wrap">
                    <label class="mb-0 mr-2 font-weight-bold"><i class="mdi mdi-filter-outline"></i> Filters:</label>
                    <select id="filter-type" class="form-control form-control-sm form-control-modern">
                        <option value="all">All Types</option>
                        <option value="drug">Drug</option>
                        <option value="consumable">Consumable</option>
                        <option value="utility">Utility</option>
                    </select>
                    <select id="filter-category" class="form-control form-control-sm form-control-modern">
                        <option value="all">All Categories</option>
                        @foreach ($categories as $catId => $catName)
                            <option value="{{ $catId }}">{{ $catName }}</option>
                        @endforeach
                    </select>

                    <div class="custom-control custom-switch ml-3">
                        <input type="checkbox" class="custom-control-input" id="show-deactivated">
                        <label class="custom-control-label" for="show-deactivated">Show Deactivated</label>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="products-list" class="table table-sm table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Type</th>
                                <th>Stock</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Deactivation Confirmation Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" role="dialog" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel">Confirm Status Change</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to <span id="status-action-text" class="font-weight-bold text-lowercase"></span> the product "<span id="status-item-name" class="font-weight-bold"></span>"?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-status-btn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Store Selection Modal for Stock Template Download -->
    <div class="modal fade" id="storeSelectModal" tabindex="-1" role="dialog" aria-labelledby="storeSelectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="storeSelectModalLabel"><i class="mdi mdi-database-export-outline mr-1"></i> Select Store</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Download a pre-filled product template with current stock levels for the selected store.</p>
                    <div class="form-group mb-0">
                        <label class="form-label font-weight-bold">Store</label>
                        <select id="stock-template-store" class="form-control form-control-modern">
                            <option value="">-- Select a store --</option>
                            @foreach($stores as $store)
                                <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="btn-download-stock-template" class="btn btn-info btn-sm disabled">
                        <i class="mdi mdi-download"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Products Modal -->
    <div class="modal fade" id="importProductsModal" tabindex="-1" role="dialog" aria-labelledby="importProductsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importProductsModalLabel"><i class="mdi mdi-file-import-outline mr-1"></i> Import Products</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="mdi mdi-information-outline"></i>
                        Upload a <strong>.xlsx</strong> or <strong>.csv</strong> file. Products are matched by <code>product_code</code> — existing records are updated, new ones are created.
                        <a href="{{ route('import-export.template.products') }}" class="ml-2 font-weight-bold">
                            <i class="mdi mdi-download"></i> Download Template
                        </a>
                    </div>
                    <div id="import-form-area">
                        <div class="form-group">
                            <label class="font-weight-bold">File <span class="text-danger">*</span></label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="import-file-input" accept=".xlsx,.xls,.csv">
                                <label class="custom-file-label" for="import-file-input">Choose file&hellip;</label>
                            </div>
                            <small class="text-muted">Accepted: .xlsx, .xls, .csv &mdash; max 10 MB</small>
                        </div>
                        <p class="text-muted small mb-0">
                            <i class="mdi mdi-information-outline"></i>
                            The <strong>default_bulk_pack_name</strong> and <strong>default_bulk_pack_qty</strong> columns set the default purchase (bulk) packaging unit for each product.
                        </p>
                    </div>
                    <div id="import-result-area" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success btn-sm" id="btn-run-import">
                        <i class="mdi mdi-upload"></i> Run Import
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
            var table = $('#products-list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('product-list') }}",
                    "type": "GET",
                    "data": function(d) {
                        d.product_type = $('#filter-type').val();
                        d.category_id = $('#filter-category').val();
                        d.show_deactivated = $('#show-deactivated').is(':checked');
                    }
                },
                "columns": [
                    { data: "DT_RowIndex", name: "DT_RowIndex", orderable: false, searchable: false },
                    { data: "product_info", name: "product_name" },
                    { data: "type_badge", name: "product_type", orderable: false },
                    { data: "current_quantity", name: "current_quantity" },
                    { data: "sale_price", name: "sale_price", orderable: false },
                    { data: "actions", name: "actions", orderable: false, searchable: false }
                ],
                "paging": true
            });

            // Filter change reloads table
            $('#filter-type, #filter-category, #show-deactivated').on('change', function() {
                table.ajax.reload();
            });

            let currentProductId = null;

            window.toggleProductStatus = function(id, action, name) {
                currentProductId = id;
                $('#status-action-text').text(action);
                $('#status-item-name').text(name);
                $('#statusModal').modal('show');
            };

            $('#confirm-status-btn').click(function() {
                if (!currentProductId) return;

                $(this).prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

                $.ajax({
                    url: `/products/${currentProductId}/toggle-status`,
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        $('#statusModal').modal('hide');
                        if (response.success) {
                            toastr.success(response.message);
                            table.ajax.reload(null, false);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        $('#statusModal').modal('hide');
                        toastr.error('An error occurred while updating status.');
                    },
                    complete: function() {
                        $('#confirm-status-btn').prop('disabled', false).text('Confirm');
                    }
                });
            });

            // Stock Template store selection
            var stockTemplateBaseUrl = '{{ route("import-export.template.products-stock", "__STORE__") }}';

            $('#stock-template-store').on('change', function() {
                var storeId = $(this).val();
                var $btn = $('#btn-download-stock-template');
                if (storeId) {
                    $btn.attr('href', stockTemplateBaseUrl.replace('__STORE__', storeId))
                        .removeClass('disabled');
                } else {
                    $btn.attr('href', '#').addClass('disabled');
                }
            });

            $('#storeSelectModal').on('hidden.bs.modal', function() {
                $('#stock-template-store').val('');
                $('#btn-download-stock-template').attr('href', '#').addClass('disabled');
            });

            // ---- Import Products ----
            $('#import-file-input').on('change', function() {
                var fileName = $(this).val().split('\\').pop() || 'Choose file\u2026';
                $(this).next('.custom-file-label').text(fileName);
            });

            $('#importProductsModal').on('hidden.bs.modal', function() {
                $('#import-file-input').val('');
                $('.custom-file-label').text('Choose file\u2026');
                $('#import-result-area').hide().html('');
                $('#import-form-area').show();
                $('#btn-run-import').prop('disabled', false).html('<i class="mdi mdi-upload"></i> Run Import');
            });

            $('#btn-run-import').on('click', function() {
                var file = $('#import-file-input')[0].files[0];
                if (!file) {
                    toastr.warning('Please select a file first.');
                    return;
                }
                var formData = new FormData();
                formData.append('file', file);
                formData.append('_token', '{{ csrf_token() }}');

                $('#btn-run-import').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Importing\u2026');

                $.ajax({
                    url: '{{ route("import-export.import.products") }}',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    success: function(response) {
                        $('#import-result-area').html(buildImportReport(response)).show();
                        $('#import-form-area').hide();
                        table.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Import failed. Please check your file and try again.';
                        toastr.error(msg);
                        $('#btn-run-import').prop('disabled', false).html('<i class="mdi mdi-upload"></i> Run Import');
                    }
                });
            });

            function buildImportReport(r) {
                var errHtml = '';
                if (r.errors && r.errors.length) {
                    errHtml = '<div class="alert alert-warning mt-2"><strong>Errors (' + r.errors.length + '):</strong><ul class="mb-0 mt-1">' +
                        r.errors.map(function(e) { return '<li>' + e + '</li>'; }).join('') +
                        '</ul></div>';
                }
                return '<div class="alert alert-success"><i class="mdi mdi-check-circle"></i> Import complete in ' + r.duration + 's &mdash; ' + r.total_rows + ' rows processed.</div>' +
                    '<div class="row text-center mb-3">' +
                    '<div class="col-4"><div class="border rounded p-2"><div class="h4 text-success mb-0">' + r.created + '</div><small class="text-muted">Created</small></div></div>' +
                    '<div class="col-4"><div class="border rounded p-2"><div class="h4 text-primary mb-0">' + r.updated + '</div><small class="text-muted">Updated</small></div></div>' +
                    '<div class="col-4"><div class="border rounded p-2"><div class="h4 text-warning mb-0">' + r.skipped + '</div><small class="text-muted">Skipped</small></div></div>' +
                    '</div>' + errHtml +
                    '<button class="btn btn-outline-secondary btn-sm mt-2" onclick="$(\"#import-form-area\").show(); $(\"#import-result-area\").hide(); $(\"#import-file-input\").val(\"\"); $(\".custom-file-label\").text(\"Choose file\u2026\"); $(\"#btn-run-import\").prop(\"disabled\", false).html(\"<i class=\\\"mdi mdi-upload\\\"></i> Run Import\");">' +
                    '<i class="mdi mdi-reload"></i> Import Another File</button>';
            }
        });
    </script>
@endsection
