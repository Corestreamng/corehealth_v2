@extends('admin.layouts.app')
@section('title', 'Consultations History')
@section('page_name', 'Consultations')
@section('subpage_name', 'History')
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
            <button class="nav-link active" id="cont_data_tab" data-bs-toggle="tab" data-bs-target="#cont" type="button"
                role="tab" aria-controls="cont_data" aria-selected="true">Previous</button>
        </li>
    </ul>
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="cont" role="tabpanel" aria-labelledby="cont_tab">
            <div class="card mt-2">
                <div class="card-body table-responsive">
                    <div id="tableContainer" style="display: none;">
                        <table class="table table-sm table-bordered table-striped" id="all_prev_consult_list"
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
                    <div id="initialMessage" class="text-center py-4">
                        <p class="text-muted">Please select a date range and click "Fetch Data" to load consultations.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script>
        $(function() {
            let dataTable = null;

            // Function to initialize DataTable
            function initializeDataTable() {
                if (dataTable) {
                    dataTable.destroy();
                }

                dataTable = $('#all_prev_consult_list').DataTable({
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
                            return d;
                        }
                    },
                    "columns": [{
                            data: 'DT_RowIndex',
                            name: 'DT_RowIndex',
                            orderable: false,
                            searchable: false
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
                            data: "doctor_id",
                            name: "doctor_id"
                        },
                        {
                            data: "created_at",
                            name: "created_at"
                        },
                        {
                            data: "view",
                            name: "view",
                            orderable: false,
                            searchable: false
                        },
                    ],
                    "order": [
                        [6, 'desc']
                    ], // Order by created_at by default
                    "paging": true
                });
            }

            // Handle fetch button click
            $('#fetchData').click(function() {
                // Validate dates
                const startDate = new Date($('#start_date').val());
                const endDate = new Date($('#end_date').val());

                if (!$('#start_date').val() || !$('#end_date').val()) {
                    alert('Please select both start and end dates');
                    return;
                }

                if (startDate > endDate) {
                    alert('Start date cannot be later than end date');
                    return;
                }

                // Hide initial message and show table
                $('#initialMessage').hide();
                $('#tableContainer').show();

                // Initialize and load DataTable
                initializeDataTable();
            });

            // Date input validation
            $('#start_date, #end_date').on('change', function() {
                const startDate = new Date($('#start_date').val());
                const endDate = new Date($('#end_date').val());

                if (startDate > endDate) {
                    alert('Start date cannot be later than end date');
                    $(this).val(''); // Clear the invalid input
                }
            });
        });
    </script>
@endsection
