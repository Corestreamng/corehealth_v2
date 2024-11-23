@extends('admin.layouts.app')
@section('title', 'List Service Requests History')
@section('page_name', 'Services')
@section('subpage_name', 'List Requests History')
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
    <section class="content">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4>Investigation History</h4>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped" style="width: 100%" id="invest_history">
                            <thead>
                                <th>#</th>
                                <th>Patient</th>
                                <th>Service</th>
                                <th>Details</th>
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
            // Initialize DataTable
            const table = $('#invest_history').DataTable({
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
                    "url": "{{ url('investHistoryList') }}",
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
                        data: "result",
                        name: "result"
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

            // Filter Button Event
            $('#fetchData').click(function() {
                table.ajax.reload();
            });
        });
    </script>
@endsection
