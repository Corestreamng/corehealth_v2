@extends('admin.layouts.app')
@section('title', 'Consultations History')
@section('page_name', 'Consultations')
@section('subpage_name', 'History')
@section('content')

    <!-- Date Filter Form -->
    <div class="card-modern mb-2">
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

    <!-- Tab and Table for Consultation History -->
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="cont_data_tab" data-bs-toggle="tab" data-bs-target="#cont" type="button"
                role="tab" aria-controls="cont" aria-selected="true">Previous</button>
        </li>
    </ul>
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="cont" role="tabpanel" aria-labelledby="cont_data_tab">
            <div class="card-modern mt-2">
                <div class="card-body table-responsive">
                    <table class="table table-sm table-bordered table-striped" id="all_prev_consult_list" style="width: 100%">
                        <thead>
                            {{-- <th>#</th> --}}
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
            // Initialize DataTable with server-side processing
            const table = $('#all_prev_consult_list').DataTable({
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
                    "url": "{{ url('AllprevEncounterList') }}",
                    "type": "GET",
                    "data": function(d) {
                        d.start_date = $('#start_date').val();
                        d.end_date = $('#end_date').val();
                    }
                },
                "columns": [
                    // {
                    //     data: "DT_RowIndex",
                    //     name: "DT_RowIndex"
                    // },
                    {
                        data: "fullname",
                        // name: "fullname",
                        searchable: true
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
                        data: "doctor_id",
                        name: "doctor_id"
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

            // Fetch data when the user clicks the 'Fetch Data' button
            $('#fetchData').click(function() {
                table.ajax.reload(); // Reload DataTable with the selected date range
            });
        });
    </script>
@endsection
