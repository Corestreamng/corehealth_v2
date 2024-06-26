@extends('admin.layouts.app')
@section('title', 'Service Price Setting ')
@section('page_name', 'Services ')
@section('subpage_name', 'Service Price List')
@section('content')
    <div id="content-wrapper">

        <div class="container">

            {{-- @include('admin.layouts.partials.infoBox') --}}

            <div class="card">

                <!-- /.card-header -->
                <div class="card-body">
                    <table id="products" class="table table-sm table-responsive table-bordered table-striped">
                        <thead>
                            <tr>
                                <th># </th>
                                <th>Service </th>
                                <th>Cost price </th>
                                <th>Sale Price</th>
                                <th>Max Discount</th>
                            </tr>
                        </thead>

                    </table>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->

        </div>

    </div>

@endsection

@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script>
        $(function() {
            $('#products').DataTable({
                // "dom": 'Bfrtip',
                "iDisplayLength": 50,
                // "buttons": [ 'copy', 'excel', 'pdf', 'print', 'colvis' ],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('listServicePrices') }}",
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
                        data: "pr_buy_price",
                        name: "pr_buy_price"
                    },
                    {
                        data: "current_sale_price",
                        name: "current_sale_price"
                    },
                    {
                        data: "max_discount",
                        name: "max_discount"
                    },
                    {
                        data: "pieces_price",
                        name: "pieces_price"
                    },
                    {
                        data: "pieces_max_discount",
                        name: "pieces_max_discount"
                    },
                    {
                        data: "initial_sale_price",
                        name: "initial_sale_price"
                    },
                    {
                        data: "initial_sale_date",
                        name: "initial_sale_date"
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
                "paging": true,
                //  "lengthChange": true,
                // "searching": true,
                // "ordering": true,
                //  "info": true,
                "autoWidth": false
            });
        });
    </script>
@endsection
