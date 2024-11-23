@extends('admin.layouts.app')
@section('title', 'List Product Requests History')
@section('page_name', 'Products')
@section('subpage_name', 'List Requests History')
@section('content')

<section class="content">

    <div class="col-12">
        <!-- Date Range Card -->
        <div class="card mb-3">
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

        <!-- Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped" style="width: 100%"
                        id="presc_history_list">
                        <thead>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Product</th>
                            <th>Details</th>
                            <th>Action</th>
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
        const table = $('#presc_history_list').DataTable({
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
                "url": "{{ url('prescQueueHistoryList') }}",
                "type": "GET",
                "data": function(d) {
                    d.start_date = $('#start_date').val();  // Include start date
                    d.end_date = $('#end_date').val();      // Include end date
                }
            },
            "columns": [
                { data: "DT_RowIndex", name: "DT_RowIndex" },
                { data: "patient_id", name: "patient_id" },
                { data: "dose", name: "dose" },
                { data: "created_at", name: "created_at" },
                { data: "select", name: "select" }
            ],
            "paging": true
        });

        // Reload table on Fetch Data button click
        $('#fetchData').on('click', function() {
            table.ajax.reload();
        });
    });
</script>

@endsection
