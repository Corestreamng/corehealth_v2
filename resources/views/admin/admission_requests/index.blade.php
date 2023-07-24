@extends('admin.layouts.app')
@section('title', 'Admission Requests ')
@section('page_name', 'Admission Requests')
@section('subpage_name', 'Admission Requests List')
@section('content')
    <div id="content-wrapper">
        <div class="container">

            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-sm-6">
                            {{ __('Requests') }}
                        </div>
                        <div class="col-sm-6">
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

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="request-list" class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Patient</th>
                                    <th>File No</th>
                                    <th>HMO/Insurance </th>
                                    <th>HMO No</th>
                                    <th>Requested By</th>
                                    <th>Bills</th>
                                    <th>Bed</th>
                                    <th>View</th>
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
            $('#request-list').DataTable({
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
                    "url": "{{ route('admission-requests-list') }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "patient",
                        name: "patient"
                    },
                    {
                        data: "file_no",
                        name: "file_no"
                    },
                    {
                        data: "hmo",
                        name: "hmo"
                    },
                    {
                        data: "hmo_no",
                        name: "hmo_no"
                    },
                    {
                        data: "doctor_id",
                        name: "doctor_id"
                    },
                    {
                        data: "billed_by",
                        name: "billed_by"
                    },
                    {
                        data: "bed_id",
                        name: "bed_id"
                    },
                    {
                        data: "show",
                        name: "show"
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
