@extends('admin.layouts.app')
@section('title', 'Patients List')
@section('page_name', 'Patients')
@section('subpage_name', 'List Patients')
@section('content')
    <div id="content-wrapper">
        <div class="container">

            <div class="card-modern">
                <div class="card-header">
                    <h3 class="card-title">{{ __('Patients ') }}</h3>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="patient-list" class="table table-sm table-bordered table-striped ">
                            <thead>
                                <tr>
                                    <th># </th>
                                    <th>Fullname</th>
                                    <th>File No</th>
                                    <th>HMO / Insurance</th>
                                    <th>HMO Number</th>
                                    <th>Phone</th>
                                    <th>Date</th>
                                    <th>View</th>
                                    <th>Edit</th>
                                    <th>Workbench</th>
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
            $('#patient-list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel','csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientsList') }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "fullname",
                        name: "fullname"
                    },
                    {
                        data: "file_no",
                        name: "file_no"
                    },
                    {
                        data: "hmo_id",
                        name: "hmo_id"
                    },
                    {
                        data: "hmo_no",
                        name: "hmo_no"
                    },
                    {
                        data: "phone_no",
                        name: "phone_no"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                    {
                        data: "view",
                        name: "view"
                    },
                    {
                        data: "edit",
                        name: "edit"
                    },
                    {
                        data: "workbenches",
                        name: "workbenches",
                        orderable: false,
                        searchable: false
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
