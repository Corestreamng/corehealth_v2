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
            $('#filter-type, #filter-category').on('change', function() {
                table.ajax.reload();
            });
        });
    </script>
@endsection
