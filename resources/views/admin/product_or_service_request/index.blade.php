@extends('admin.layouts.app')
@section('title', 'Services and Products')
@section('page_name', 'Services and Products')
@section('subpage_name', 'Services and Products Request List')
@section('content')
    <div id="content-wrapper">
        <div class="container">

            <!-- Date Filter Card -->
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

            <!-- Services and Products Table -->
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            {{ __('Services') }}
                        </div>
                        <div class="col-md-6">
                            <a href="{{ route('add-to-queue') }}" id="loading-btn" data-loading-text="Loading..."
                                class="btn btn-primary btn-sm float-right">
                                <i class="fa fa-plus"></i>
                                New Request
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="products-list" class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Patient</th>
                                    <th>File No</th>
                                    <th>HMO/Insurance</th>
                                    <th>HMO No</th>
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
            // Initialize DataTable
            const table = $('#products-list').DataTable({
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
                    "url": "{{ route('product-services-requesters-list') }}",
                    "type": "GET",
                    "data": function(d) {
                        // Add date range to AJAX request
                        d.start_date = $('#start_date').val();
                        d.end_date = $('#end_date').val();
                    }
                },
                "columns": [
                    {
                        data: "DT_RowIndex",
                        name: "DT_RowIndex",
                        orderable: false,
                        searchable: false
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
                        data: "show",
                        name: "show",
                        orderable: false,
                        searchable: false
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
