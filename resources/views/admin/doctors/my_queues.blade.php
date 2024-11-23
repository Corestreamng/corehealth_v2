@extends('admin.layouts.app')
@section('title', 'Consultations Categories')
@section('page_name', 'Consultations')
@section('subpage_name', 'Categories')
@section('content')
    <div class="card mb-2">
        <div class="card-body">
            <form id="dateRangeForm">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" class="form-control form-control-sm" id="start_date" name="start_date"
                                value="{{ date('Y-m-d', strtotime('-1 day')) }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" class="form-control form-control-sm" id="end_date" name="end_date"
                                value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" id="fetchData" class="btn btn-primary btn-sm d-block">
                                Fetch Data
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="new_tab" data-bs-toggle="tab" data-bs-target="#new" type="button"
                role="tab" aria-controls="new" aria-selected="true">New</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="cont_data_tab" data-bs-toggle="tab" data-bs-target="#cont" type="button"
                role="tab" aria-controls="cont_data" aria-selected="false">Continuing</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="prev_data_tab" data-bs-toggle="tab" data-bs-target="#prev" type="button"
                role="tab" aria-controls="prev_data" aria-selected="false">Previous</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="my_admissions_tab" data-bs-toggle="tab" data-bs-target="#my_admissions"
                type="button" role="tab" aria-controls="my_admissions" aria-selected="false">My admissions</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="other_admissions_tab" data-bs-toggle="tab" data-bs-target="#other_admissions"
                type="button" role="tab" aria-controls="other_admissions" aria-selected="false">Other
                admissions</button>
        </li>
        {{-- <li class="nav-item" role="presentation">
            <button class="nav-link" id="scheduled_tab" data-bs-toggle="tab" data-bs-target="#scheduled" type="button"
                role="tab" aria-controls="scheduled" aria-selected="false">Scheduled</button>
        </li> --}}
    </ul>
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="new" role="tabpanel" aria-labelledby="new_tab">
            <div class="card mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="new_consult_list" style="width: 100%">
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
                    <table class="table table-sm table-bordered table-striped" id="cont_consult_list" style="width: 100%">
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
        <div class="tab-pane fade" id="prev" role="tabpanel" aria-labelledby="prev_tab">
            <div class="card mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="prev_consult_list"
                        style="width: 100%">
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
        <div class="tab-pane fade" id="my_admissions" role="tabpanel" aria-labelledby="my_admissions_tab">
            <div class="card mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="my_admissions_list"
                        style="width: 100%">
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
        <div class="tab-pane fade" id="other_admissions" role="tabpanel" aria-labelledby="other_admissions_tab">
            <div class="card mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="other_admissions_list"
                        style="width: 100%">
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
        {{-- <div class="tab-pane fade" id="scheduled" role="tabpanel" aria-labelledby="scheduled_tab">
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
        </div> --}}

    </div>
@endsection
@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script>
        // Function to initialize a DataTable with a given selector and AJAX URL
        function initializeDataTable(selector, ajaxUrl) {
            $(selector).DataTable({
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
                    "url": ajaxUrl,
                    "type": "GET",
                    "data": function(d) {
                        // Add date range from form inputs
                        d.start_date = $('#start_date').val();
                        d.end_date = $('#end_date').val();
                    }
                },
                "columns": getColumns(selector),
                "paging": true
            });
        }

        // Define columns based on DataTable selector
        function getColumns(selector) {
            const commonColumns = [{
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
                }
            ];

            if (selector === '#my_admissions_list' || selector === '#other_admissions_list') {
                return [{
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
                    }
                ];
            }

            return commonColumns;
        }

        // Initialize all DataTables on page load
        $(function() {
            initializeDataTable('#new_consult_list', "{{ url('NewEncounterList') }}");
            initializeDataTable('#cont_consult_list', "{{ url('ContEncounterList') }}");
            initializeDataTable('#prev_consult_list', "{{ url('PrevEncounterList') }}");
            initializeDataTable('#scheduled_consult_list', "{{ url('patientsList') }}");
            initializeDataTable('#my_admissions_list', "{{ route('my-admission-requests-list') }}");
            initializeDataTable('#other_admissions_list', "{{ route('admission-requests-list') }}");
        });

        // Function to refresh all DataTables
        function refreshDataTables() {
            const tables = ['#new_consult_list', '#cont_consult_list', '#prev_consult_list', '#scheduled_consult_list', '#my_admissions_list', '#other_admissions_list'];
            tables.forEach(function(selector) {
                $(selector).DataTable().ajax.reload();
            });
        }

        // Attach the function to the Fetch Data button
        $('#fetchData').on('click', function() {
            refreshDataTables();
        });
    </script>

@endsection
