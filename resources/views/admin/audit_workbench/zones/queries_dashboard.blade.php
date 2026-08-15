@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-alert-circle-outline"></i> Unified Query Dashboard</h4>
    <div>
        <button class="btn btn-outline-success btn-sm"><i class="mdi mdi-download"></i> Export CSV</button>
    </div>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'queries-dashboard', 'zoneLabel' => 'Unified Query Dashboard'])

<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="queryTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3 font-weight-bold" id="active-queries-tab" data-bs-toggle="tab" data-bs-target="#active-queries" type="button" role="tab">
                    <i class="mdi mdi-alert-circle text-danger"></i> Active Queries
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="resolved-queries-tab" data-bs-toggle="tab" data-bs-target="#resolved-queries" type="button" role="tab">
                    <i class="mdi mdi-check-circle text-success"></i> Resolved Queries
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="custom-reports-tab" data-bs-toggle="tab" data-bs-target="#custom-reports" type="button" role="tab">
                    <i class="mdi mdi-file-chart text-info"></i> Custom Reports
                </button>
            </li>
        </ul>
    </div>
    
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="queryTabsContent">
            
            {{-- Active Queries Tab --}}
            <div class="tab-pane fade show active" id="active-queries" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card bg-danger text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Active Queries (Hospital-wide)</h6>
                                <h3 class="mb-0">{{ $kpis['total_active'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-warning text-dark h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Active Queries in Period</h6>
                                <h3 class="mb-0">{{ $kpis['active_in_period'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-active-queries" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date Raised</th>
                                        <th>Record Details</th>
                                        <th>Query Info</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Resolved Queries Tab --}}
            <div class="tab-pane fade" id="resolved-queries" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card bg-success text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Resolved Queries (Hospital-wide)</h6>
                                <h3 class="mb-0">{{ $kpis['total_resolved'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-info text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Resolved Queries in Period</h6>
                                <h3 class="mb-0">{{ $kpis['resolved_in_period'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-resolved-queries" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date Raised</th>
                                        <th>Record Details</th>
                                        <th>Query Info</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Custom Reports Tab --}}
            <div class="tab-pane fade" id="custom-reports" role="tabpanel">
                <div class="alert alert-info">
                    <i class="mdi mdi-information"></i> <strong>Note:</strong> Custom reporting engine is currently in beta. You will soon be able to build custom queries directly from this tab.
                </div>
            </div>
            
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let startDate = $('#filter_start_date').val();
    let endDate = $('#filter_end_date').val();

    let commonDtConfig = {
        processing: true,
        serverSide: true,
        responsive: true,
        pageLength: 25,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row mt-3"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
    };

    function appendMultidimData(d) {
        d.start_date = $('#filter_start_date').val();
        d.end_date = $('#filter_end_date').val();
    }

    $('#table-active-queries').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.queries-dashboard.data', 'active-queries') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'record_details', name: 'auditable_type' },
            { data: 'query_info', name: 'query_reason' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-resolved-queries').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.queries-dashboard.data', 'resolved-queries') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'record_details', name: 'auditable_type' },
            { data: 'query_info', name: 'query_reason' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
});
</script>
@endpush
