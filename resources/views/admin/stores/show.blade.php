@extends('admin.layouts.app')
@section('title', 'Store Management')
@section('page_name', 'Store Management')
@section('subpage_name', 'Show Store')
@section('content')

    <div id="content-wrapper">
        <div class="container">

            {{-- @include('admin.layouts.partials.infoBox') --}}
            <div class="card-modern">
                <div class="card-header">
                    <h3 class="card-title">List of Product in {{ $store->store_name }} As on {!! $now !!}</h3>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="products-list" class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Product</th>
                                    <th>Current Quantity</th>
                                    <th>Quantity Sold</th>
                                    <th>Unsupply</th>
                                    <th>total</th>
                                    <th>Transfer Product</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
                <!-- </div> -->
            </div>
        </div>



    @endsection
    @section('scripts')
        <!-- DataTables -->
        <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>

        <script>
            $(function() {
                $('#products-list').DataTable({
                    // "dom": 'Bfrtip',
                "iDisplayLength": 50,
                    // "buttons": [ 'copy', 'excel', 'pdf', 'print', 'colvis' ],
                    "processing": true,
                    "serverSide": true,
                    "ajax": {
                        "url": "{{ route('listStoresProducts', $id) }}",
                        "type": "GET"
                    },
                    "columns": [{
                            data: "DT_RowIndex",
                            name: "DT_RowIndex"
                        },
                        {
                            data: "product",
                            name: "product"
                        },
                        {
                            data: "current_quantity",
                            name: "current_quantity"
                        },
                        {
                            data: "quantity_sale",
                            name: "quantity_sale"
                        },
                        {
                            data: "unsupply",
                            name: "unsupply"
                        },
                        {
                            data: "totalQt",
                            name: "totalQt"
                        },
                        {
                            data: "movestock",
                            name: "movestock"
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
