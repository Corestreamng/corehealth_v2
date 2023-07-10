@extends('admin.layouts.app')
@section('title', 'Consultations Categories')
@section('page_name', 'Consultations')
@section('subpage_name', 'Categories')
@section('content')
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="new_tab" data-bs-toggle="tab" data-bs-target="#new" type="button"
                role="tab" aria-controls="new" aria-selected="true">New</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="cont_data_tab" data-bs-toggle="tab" data-bs-target="#cont" type="button"
                role="tab" aria-controls="cont_data" aria-selected="false">Previous</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="scheduled_tab" data-bs-toggle="tab" data-bs-target="#scheduled" type="button"
                role="tab" aria-controls="scheduled" aria-selected="false">Scheduled</button>
        </li>
    </ul>
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="new" role="tabpanel" aria-labelledby="new_tab">
            <div class="card mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="new_consult_list">
                        <thead>
                            <th>#</th>
                            <th>Patient Name</th>
                            <th>File No</th>
                            <th>HMO/Insurance</th>
                            <th>Clinic</th>
                            <th>Doctor</th>
                            <th>Time</th>
                            <th>Action</th>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="cont" role="tabpanel" aria-labelledby="cont_tab">
            <div class="card mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="prev_consult_list">
                        <thead>
                            <th>#</th>
                            <th>Patient Name</th>
                            <th>File No</th>
                            <th>HMO/Insurance</th>
                            <th>Clinic</th>
                            <th>Doctor</th>
                            <th>Time</th>
                            <th>Action</th>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="scheduled" role="tabpanel" aria-labelledby="scheduled_tab">
            <div class="card mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="scheduled_consult_list">
                        <thead>
                            <th>#</th>
                            <th>Patient Name</th>
                            <th>File No</th>
                            <th>HMO/Insurance</th>
                            <th>Clinic</th>
                            <th>Doctor</th>
                            <th>Time</th>
                            <th>Action</th>
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
            $('#new_consult_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('NewEncounterList') }}",
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
                        data: "clinic_id",
                        name: "clinic_id"
                    },
                    {
                        data: "staff_id",
                        name: "staff_id"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                    {
                        data: "view",
                        name: "view"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#prev_consult_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('PrevEncounterList') }}",
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
                        data: "clinic_id",
                        name: "clinic_id"
                    },
                    {
                        data: "staff_id",
                        name: "staff_id"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                    {
                        data: "view",
                        name: "view"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#scheduled_consult_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
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
                        data: "created_at",
                        name: "created_at"
                    },
                    {
                        data: "view",
                        name: "view"
                    },
                ],

                "paging": true
            });
        });
    </script>
@endsection
