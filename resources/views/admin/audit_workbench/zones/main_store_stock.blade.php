@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-warehouse"></i> Main Store Stock Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'main-store-stock', 'zoneLabel' => 'Main Store Stock Zone'])

<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="stockTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3 font-weight-bold" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock" type="button" role="tab">
                    <i class="mdi mdi-package-variant text-info"></i> Inventory SKU Stock
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="po-tab" data-bs-toggle="tab" data-bs-target="#po" type="button" role="tab">
                    <i class="mdi mdi-truck-delivery text-success"></i> Purchase Orders & Deliveries
                </button>
            </li>
        </ul>
    </div>
    
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="stockTabsContent">
            
            {{-- Stock Inventory Tab --}}
            <div class="tab-pane fade show active" id="stock" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Inventory SKUs</h6>
                                <h3 class="mb-0">{{ $kpis['total_skus'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Stock Valuation</h6>
                                <h3 class="mb-0">₦{{ number_format($kpis['total_stock_value'] ?? 0, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Out of Stock SKUs</h6>
                                <h3 class="mb-0">{{ $kpis['out_of_stock_count'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Near Expiry Batches</h6>
                                <h3 class="mb-0">{{ $kpis['near_expiry_count'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-stock" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Product Details</th>
                                        <th>Batch & Expiry</th>
                                        <th>Quantity On-Hand</th>
                                        <th>Cost Valuation (Fallback)</th>
                                        <th>Audit Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Purchase Orders Tab --}}
            <div class="tab-pane fade" id="po" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-info text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Purchase Orders</h6>
                                <h3 class="mb-0">{{ $kpis['total_po_count'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total PO Value</h6>
                                <h3 class="mb-0">₦{{ number_format($kpis['total_po_value'] ?? 0, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Pending Deliveries</h6>
                                <h3 class="mb-0">{{ $kpis['pending_po_count'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-purchase-orders" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Order Date</th>
                                        <th>PO Number & Supplier</th>
                                        <th>Order Value</th>
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

    $('#table-stock').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.main-store-stock.data', 'stock') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'product_details', name: 'product.product_name' },
            { data: 'batch_expiry', name: 'batch_number' },
            { data: 'quantity_badge', name: 'quantity' },
            { data: 'cost_valuation', name: 'unit_cost' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-purchase-orders').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.main-store-stock.data', 'purchase-orders') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'po_supplier', name: 'po_number' },
            { data: 'amount_formatted', name: 'total_amount' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
});
</script>
@endpush
