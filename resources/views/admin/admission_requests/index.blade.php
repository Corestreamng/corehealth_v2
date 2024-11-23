@extends('admin.layouts.app')
@section('title', 'Admission Requests')
@section('page_name', 'Admission Requests')
@section('subpage_name', 'Admission Requests List')
@section('content')
    <div id="content-wrapper">
        <div class="container">
            <!-- Date Range Card -->
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

            <!-- Table Card -->
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            {{ __('Requests') }}
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
                                    <th>HMO/Insurance</th>
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
    <!-- DataTables -->
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>

    <script>
        $(function() {
            const table = $('#request-list').DataTable({
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
                    "type": "GET",
                    "data": function(d) {
                        d.start_date = $('#start_date').val();
                        d.end_date = $('#end_date').val();
                    }
                },
                "columns": [
                    { data: "DT_RowIndex", name: "DT_RowIndex" },
                    { data: "patient", name: "patient" },
                    { data: "file_no", name: "file_no" },
                    { data: "hmo", name: "hmo" },
                    { data: "hmo_no", name: "hmo_no" },
                    { data: "doctor_id", name: "doctor_id" },
                    { data: "billed_by", name: "billed_by" },
                    { data: "bed_id", name: "bed_id" },
                    { data: "show", name: "show" },
                ],
                "paging": true
            });

            // Fetch data on button click
            $('#fetchData').on('click', function() {
                table.ajax.reload();
            });
        });
    </script>
@endsection
