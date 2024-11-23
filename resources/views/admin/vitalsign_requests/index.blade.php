@extends('admin.layouts.app')
@section('title', 'List Vitals Requests')
@section('page_name', 'Vitals')
@section('subpage_name', 'List Vitals')
@section('content')

    <section class="content">

        <!-- Date Range Filter Card -->
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

        <!-- DataTable -->
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped" style="width: 100%" id="vitals_history">
                            <thead>
                                <th>#</th>
                                <th>Patient</th>
                                <th>Service</th>
                                <th>Entry</th>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </section>

@endsection

@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script>
        $(function() {
            const table = $('#vitals_history').DataTable({
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
                    "url": "{{ route('patientVitalsQueue') }}",
                    "type": "GET",
                    "data": function(d) {
                        d.start_date = $('#start_date').val();
                        d.end_date = $('#end_date').val();
                    }
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "patient_id",
                        name: "patient_id"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],
                "paging": true
            });

            // Fetch Data Button Click
            $('#fetchData').click(function() {
                table.ajax.reload();
            });
        });
    </script>
@endsection
