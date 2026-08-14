@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-store"></i> Ward & Department Sub-Stores Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'ward-dept-stores', 'zoneLabel' => 'Ward & Department Sub-Stores Zone'])

<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="subStoreTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3 font-weight-bold" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock" type="button" role="tab">
                    <i class="mdi mdi-package text-info"></i> Sub-Store Inventory
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="requisitions-tab" data-bs-toggle="tab" data-bs-target="#requisitions" type="button" role="tab">
                    <i class="mdi mdi-swap-horizontal text-success"></i> Store Requisitions
                </button>
            </li>
        </ul>
    </div>
    
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="subStoreTabsContent">
            
            {{-- Stock Inventory Tab --}}
            <div class="tab-pane fade show active" id="stock" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-info text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Sub-Store SKUs</h6>
                                <h3 class="mb-0">{{ $kpis['sub_store_skus'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Inventory Valuation</h6>
                                <h3 class="mb-0">₦{{ number_format($kpis['sub_store_value'] ?? 0, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-primary text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Active Sub-Stores</h6>
                                <h3 class="mb-0">{{ $kpis['active_sub_stores_count'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-substore-stock" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Sub-Store Details</th>
                                        <th>Product Item</th>
                                        <th>Stock Level</th>
                                        <th>Valuation (Cost)</th>
                                        <th>Audit Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Requisitions Tab --}}
            <div class="tab-pane fade" id="requisitions" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card bg-primary text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Requisitions</h6>
                                <h3 class="mb-0">{{ $kpis['total_requisitions_count'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-success text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Fulfilled Requisitions</h6>
                                <h3 class="mb-0">{{ $kpis['fulfilled_requisitions_count'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-substore-requisitions" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Store Requisition Flow</th>
                                        <th>Status</th>
                                        <th>Audit Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
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
    let hmoId = $('#filter_hmo_id').val();
    let gender = $('#filter_gender').val();
    let ageRange = $('#filter_age_range').val();

    let commonDtConfig = {
        dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex gap-2"B><"d-flex align-items-center"f>>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
        iDisplayLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
        processing: true,
        serverSide: true,
        responsive: true,
        order: [[0, 'desc']]
    };

    function appendMultidimData(d) {
        d.start_date = $('#filter_start_date').val();
        d.end_date = $('#filter_end_date').val();
        d.hmo_scheme_id = $('#filter_hmo_scheme_id').val();
        d.hmo_id = $('#filter_hmo_id').val();
        d.gender = $('#filter_gender').val();
        d.age_range = $('#filter_age_range').val();
        d.audit_status = $('#filter_audit_status').val();
    }

    $('#table-substore-stock').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.ward-dept-stores.data', 'stock') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'store_details', name: 'store.store_name' },
            { data: 'product_details', name: 'product.product_name' },
            { data: 'quantity_badge', name: 'quantity' },
            { data: 'valuation', name: 'unit_cost' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-substore-requisitions').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.ward-dept-stores.data', 'requisitions') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'stores_flow', name: 'fromStore.store_name' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
});
</script>
@endpush
