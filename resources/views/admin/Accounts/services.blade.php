@extends('admin.layouts.app')
@section('title', 'Services and Products ')
@section('page_name', 'Services')
@section('subpage_name', 'Services Request List')
@section('content')
    <div id="content-wrapper">
        <div class="container">

            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <a href="{{ route('paid-services', $id) }}">Settled</a>
                            {{-- {{ __('Services') }} --}}
                        </div>
                        <div class="col-md-6">
                            {{-- @if (auth()->user()->can('user-create')) --}}
                            {{-- <a href="{{ route('add-to-queue') }}" id="loading-btn" data-loading-text="Loading..."
                                class="btn btn-primary btn-sm float-right">
                                <i class="fa fa-plus"></i>
                                New Request
                            </a> --}}
                            {{-- @endif --}}
                        </div>
                    </div>
                </div>
                <form action="{{ route('service-payment') }}" method="post">
                    @csrf
                    <div class="card-body">
                        <h1>Sevices</h1>
                        <div class="table-responsive">
                            <table id="service-list" class="table table-sm table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>SN</th>
                                        <th>Service Name</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Select</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        <hr>
                        <h4>Products</h4>
                        <div class="table-responsive">
                            <table id="products-list" class="table table-sm table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>SN</th>
                                        <th>Product Name</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Select</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        <input type="hidden" name="id" id="myInput" value="{{ $id }}"><br>
                        <button type="submit" class="align-self-end btn btn-primary"
                            style="margin-top: auto;">Proceed</button>
                    </div>
                    <br>
                </form>
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
        const dar = document.getElementById('myInput').value;
        console.log(dar);
        $(function() {

            $('#service-list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "/services-list/" + dar,
                    "type": "GET"
                },
                "columns": [{
                        data: "id",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "service.service_name",
                        name: "service"
                    },
                    {
                        data: "service.price.sale_price",
                        name: "service"
                    },
                    {
                        data: "qty"
                    },
                    {
                        data: "checkBox",
                        name: "checkBox"
                    },
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
    <script>
        // const dar = document.getElementById('myInput').value;
        console.log(dar);
        $(function() {
            $('#products-list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "/product-list/" + dar,
                    "type": "GET"
                },
                "columns": [{
                        data: "id",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "product.product_name",
                        name: "product"
                    },
                    {
                        data: "product.price.current_sale_price",
                        name: "price"
                    },
                    {
                        data:"qty"
                    },
                    {
                        data: "checkBox",
                        name: "checkBox"
                    },
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
