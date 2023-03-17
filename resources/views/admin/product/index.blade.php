@extends('admin.layouts.app')
@section('title', 'Products ')
@section('page_name', 'Products ')
@section('subpage_name', 'Products List')
@section('content')
    <div id="content-wrapper">
        <div class="container">

            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-sm-6">
                            {{ __('Products') }}
                        </div>
                        <div class="col-sm-6">
                            {{-- @if (auth()->user()->can('user-create')) --}}
                            <a href="{{ route('products.create') }}" id="loading-btn" data-loading-text="Loading..."
                                class="btn btn-primary btn-sm float-right">
                                <i class="fa fa-plus"></i>
                                Add Product
                            </a>
                            {{-- @endif --}}
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="products-list"
                            class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Product</th>
                                    <th>Category </th>
                                    <th>Product Code</th>
                                    <th>Re-Order Alert</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Stock</th>
                                    <th>Price</th>
                                    <th>Store</th>
                                    <th>Issue</th>
                                    <th>Edit</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <!-- jQuery -->
    {{-- <script src="{{ asset('/plugins/dataT/jQuery-3.3.1/jquery-3.3.1.min.js') }}"></script> --}}
    <!-- Bootstrap 4 -->
    <!-- DataTables -->
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>

    <script>
        $(function() {
            $('#products-list').DataTable({
                "dom": 'Bfrtip',
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('product-list') }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "product_name",
                        name: "product_name"
                    },
                    {
                        data: "category_id",
                        name: "category_id"
                    },
                    {
                        data: "product_code",
                        name: "product_code"
                    },
                    {
                        data: "reorder_alert",
                        name: "reorder_alert"
                    },
                    {
                        data: "current_quantity",
                        name: "current_quantity"
                    },
                    {
                        data: "visible",
                        name: "visible"
                    },
                    {
                        data: "addstoke",
                        name: "addstoke"
                    },
                    {
                        data: "adjust",
                        name: "adjust"
                    },
                    {
                        data: "store",
                        name: "store"
                    },
                    {
                        data: "trans",
                        name: "trans"
                    },
                    {
                        data: "edit",
                        name: "edit"
                    }
                ],
                // initComplete: function () {
                //     this.api().columns().every(function () {
                //         var column = this;
                //         var input = document.createElement("input");
                //         $(input).appendTo($(column.footer()).empty())
                //         .on('change', function () {
                //             column.search($(this).val(), false, false, true).draw();
                //         });
                //     });
                // },
                "paging": true
                // "lengthChange": false,
                // "searching": true,
                // "ordering": true,
                // "info": true,
                // "autoWidth": false
            });
        });
    </script>
@endsection
