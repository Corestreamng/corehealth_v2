@extends('admin.layouts.app')
@section('title', 'Service Management')
@section('page_name', 'Service Management')
@section('subpage_name', 'Service Issue History')
@section('content')

    <div id="content-wrapper">

        <div class="container">

            {{-- @include('admin.layouts.partials.infoBox') --}}
            <div class="card-modern">
                <div class="card-header">
                    <h4 class="card-title">All {{ $pp->service_name }} Issue History</h4>
                </div>
                @if($pp->is_combo && $pp->bundleItems->count() > 0)
                <div class="card-body border-bottom">
                    <h5 class="mb-3 text-primary"><i class="mdi mdi-package-variant"></i> Bundle Constituents</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>Item Name</th>
                                    <th>Type</th>
                                    <th>Qty</th>
                                    <th>Notes/Dose</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pp->bundleItems as $item)
                                <tr>
                                    <td>{{ $item->display_name }}</td>
                                    <td><span class="badge {{ $item->item_type === "service" ? "badge-info" : "badge-success" }}">{{ ucfirst($item->item_type) }}</span></td>
                                    <td>{{ $item->qty }}</td>
                                    <td>{{ $item->note ?: ($item->dose ?: "-") }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="products-issue" class="table table-sm table-responsive table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Service</th>
                                    <th>SIV Number</th>
                                    <th>Client</th>
                                    <th>Qty</th>
                                    <th>Issued Price</th>
                                    <th>Total Amount</th>
                                    <th>Date</th>
                                    <th>Budget Year</th>
                                    <th>Store</th>
                                    <th>Voucher</th>
                                </tr>
                            <tfoot>
                                <tr>
                                    <th colspan="5">Total Sale Amount: &#x20A6;{!! number_format($pc, 2, '.', ',') !!}
                                    </th>
                                    <th colspan="5">Total Sale Quantity: {!! $qt !!}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <!-- </div> -->
            </div>
        </div>



    @endsection
    @section('scripts')
        <!-- DataTables -->
        <script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>


        <script>
            $(function() {
                $('#products-issue').DataTable({
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
                        "url": "{{ route('listSalesService', $id) }}",
                        "type": "GET"
                    },
                    "columns": [

                        {
                            data: "DT_RowIndex",
                            name: "DT_RowIndex"
                        },
                        {
                            data: "product",
                            name: "product"
                        },
                        {
                            data: "trans",
                            name: "trans"
                        },
                        {
                            data: "customer",
                            name: "customer"
                        },
                        {
                            data: "quantity_buy",
                            name: "quantity_buy"
                        },
                        {
                            data: "sale_price",
                            name: "sale_price"
                        },
                        {
                            data: "total_amount",
                            name: "total_amount"
                        },
                        {
                            data: "sale_date",
                            name: "sale_date"
                        },
                        {
                            data: "budgetYear",
                            name: "budgetYear"
                        },
                        {
                            data: "store",
                            name: "store"
                        },
                        {
                            data: "view",
                            name: "view"
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
    <!-- {{-- <script>
            ( function($) {
                $(function() {
                    $('#product').DataTable({
                        dom: 'Bfrtip',
                        buttons: [ 'copy', 'excel', 'pdf', 'print', 'colvis' ],
                        processing: true,
                        serverSide: true,
                        ajax: '{!! route('products.data') !!}',
                        columns: [
                            { data: 'id', name: 'id' },
                            { data: 'tin', name: 'tin' },
                            { data: 'surname', name: 'surname' },
                            { data: 'firstname', name: 'firstname' },
                            { data: 'phone_no', name: 'phone_no' },
                            { data: 'email', name: 'email' },
                            { data: 'visible', name: 'visible' },
                            { data: 'status', name: 'status' },
                            { data: 'created_at', name: 'created_at' },
                            { data: 'action', name: 'action' }
                        ]
                    });
                });
            }) ( jQuery );

    </script> --}} -->
