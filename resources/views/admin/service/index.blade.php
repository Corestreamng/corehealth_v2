@extends('admin.layouts.app')
@section('title', 'Services ')
@section('page_name', 'Services ')
@section('subpage_name', isset($categoryName) ? $categoryName . ' Services' : 'Services List')
@section('content')
    <div id="content-wrapper">
        <div class="container">

            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            {{ isset($categoryName) ? $categoryName . ' Services' : __('Services') }}
                        </div>
                        <div class="col-md-6">
                            {{-- @if (auth()->user()->can('user-create')) --}}
                            <a href="{{ route('services.create') }}" id="loading-btn" data-loading-text="Loading..."
                                class="btn btn-primary btn-sm float-right">
                                <i class="fa fa-plus"></i>
                                Add Service
                            </a>
                            {{-- @endif --}}
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="products-list" class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Service</th>
                                    <th>Category </th>
                                    <th>Service Code</th>
                                    <th>Status</th>
                                    <th>Price</th>
                                    <th>Template</th>
                                    <th>View</th>
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
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('services-list') }}",
                    "type": "GET",
                    "data": function(d) {
                        @if(isset($filterCategory))
                        d.category = {{ $filterCategory }};
                        @endif
                    }
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "service_name",
                        name: "service_name"
                    },
                    {
                        data: "category_id",
                        name: "category_id"
                    },
                    {
                        data: "service_code",
                        name: "service_code"
                    },
                    {
                        data: "visible",
                        name: "visible"
                    },
                    {
                        data: "adjust",
                        name: "adjust"
                    },
                    {
                        data: "template",
                        name: "template"
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
