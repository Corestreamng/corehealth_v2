@extends('admin.layouts.app')
@section('title', 'Store Management')
@section('page_name', 'Store Management')
@section('subpage_name', 'Store List')
@section('content')
    <div id="content-wrapper">
        <div class="container">
            <div class="card-modern">
                <div class="card-header">
                    <div class="clearfix">
                        <h3 class="float-left">{{ __('Store List') }}</h3>
                        @can('can-manage-store')
                            <a href="{{ route('stores.create') }}" class="btn btn-primary btn-sm float-right">New Store</a>
                        @endcan
                    </div>


                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="stores" class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Store</th>
                                    <th>Location</th>
                                    <th>Visible</th>
                                    <th>Edit </th>
                                    <th>Products</th>
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
            // $.noConflict();
            $('#stores').DataTable({
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
                    "url": "{{ route('store-list') }}",
                    "type": "GET"
                },
                "columns": [{
                            data: "DT_RowIndex",
                            name: "DT_RowIndex"
                        },
                        {
                            data: "store_name",
                            name: "store_name"
                        },
                        {
                            data: "location",
                            name: "location"
                        },
                        {
                            data: "status",
                            name: "status"
                        },
                        {
                            data: "edit",
                            name: "edit"
                        },
                        {
                            data: "view",
                            name: "view"
                        }
                ],
                // initComplete: function () {
                // this.api().columns().every(function () {
                // var column = this;
                // var input = document.createElement("input");
                // $(input).appendTo($(column.footer()).empty())
                // .on('change', function () {
                // column.search($(this).val(), false, false, true).draw();
                // });
                // });
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
        {{-- <script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>

        <script>
            $(function() {
                $.noConflict();
                $('#products').DataTable({
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
                        'colvis',
                    ],
                    "processing": true,
                    "serverSide": true,
                    "ajax": {
                        "url": "{{ route('store-list') }}",
                        "type": "GET"
                    },
                    "columns": [{
                            data: "DT_RowIndex",
                            name: "DT_RowIndex"
                        },
                        {
                            data: "store_name",
                            name: "store_name"
                        },
                        {
                            data: "location",
                            name: "location"
                        },
                        {
                            data: "visible",
                            name: "visible"
                        },
                        {
                            data: "edit",
                            name: "edit"
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
        </script> --}}
    @endsection
