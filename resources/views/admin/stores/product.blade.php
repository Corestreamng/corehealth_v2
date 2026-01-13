@extends('admin.layouts.app')
@section('title', 'Store Management')
@section('page_name', 'Store Management')
@section('subpage_name', 'Store Products')
@section('content')
    <div id="content-wrapper">
        <div class="container">

            {{-- @include('admin.layouts.partials.infoBox') --}}
            <div modern">
                <div class="card-header">
                    <h4 class="card-title">Stock Status for ({{ $product->product_name }}) as of {!! $now !!}</h4>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="products-sum" class="table table-sm  table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Product</th>
                                    <th>Store</th>
                                    <th>Quantity</th>
                                    <th>Quantity Sold</th>
                                    <th>Issued</th>
                                    <th>Total</th>
                                </tr>
                            </thead>

                        </table>
                    </div>

                </div>
                <div class="card-footer bg-transparent border-info">
                    <div class="form-group row">
                        <div class="col-md-6"><a href="{{ route('products.index') }}" class="btn btn-success"> <i
                                    class="fa fa-close"></i> Back</a></div>
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
                $('#products-sum').DataTable({
                    "dom": 'Bfrtip',
                "iDisplayLength": 50,
                    "lengthMenu": [
                        [10, 25, 50, 100, 200, 300, 500, 1000, 2000, 3000, 5000, -1],
                        [10, 25, 50, 100, 200, 300, 500, 1000, 2000, 3000, 5000, "All"]
                    ],
                    "buttons": [
                        'pageLength',
                        'copyHtml5',
                        'excelHtml5',
                        'csvHtml5',
                        'pdfHtml5',
                        'print',
                        // 'colvis',
                        {
                            extend: 'collection',
                            text: 'Table control',
                            buttons: [{
                                    text: 'Toggle start date',
                                    action: function(e, dt, node, config) {
                                        dt.column(-2).visible(!dt.column(-2).visible());
                                    }
                                },
                                {
                                    text: 'Toggle salary',
                                    action: function(e, dt, node, config) {
                                        dt.column(-1).visible(!dt.column(-1).visible());
                                    }
                                },
                                {
                                    collectionTitle: 'Visibility control',
                                    extend: 'colvis'
                                }
                            ]
                        }
                    ],
                    "processing": true,
                    "serverSide": true,
                    "ajax": {
                        "url": "{{ route('listProductslocations', $id) }}",
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
                            data: "store",
                            name: "store"
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

                    ],

                    // Update footer
                    // var numFormat = $.fn.DataTable.render.number( '\,', '.', 2, '£' ).display;
                    // $( api.column( 4 ).footer() ).html(
                    //     'Due '+ numFormat(current_quantity)
                    // );
                    // $( api.column( 5 ).footer() ).html(
                    //     'Paid '+ numFormat(quantity_sale)
                    // );
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
