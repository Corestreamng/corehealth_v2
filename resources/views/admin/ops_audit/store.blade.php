@extends('admin.ops_audit.layout')

@section('title', 'Ops Audit — Store & Inventory')

@section('ops_audit_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="font-weight-bold mb-0"><i class="mdi mdi-store text-primary me-1"></i> Store & Inventory Audit</h5>
        <small class="text-muted">Requisitions, purchase orders, and batch entries</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-success font-weight-bold" onclick="openUniversalStampModal('bulk')">
            <i class="mdi mdi-check-all me-1"></i> Bulk Stamp
        </button>
        <button class="btn btn-sm btn-outline-info font-weight-bold" onclick="printCurrentTab()">
            <i class="mdi mdi-printer me-1"></i> Print
        </button>
    </div>
</div>

{{-- Filter Bar --}}
<form id="ops_audit_filter_form" class="ops-filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label>Start Date</label>
            <input type="date" name="start_date" class="form-control" value="{{ now()->subDays(30)->format('Y-m-d') }}">
        </div>
        <div class="col-md-2">
            <label>End Date</label>
            <input type="date" name="end_date" class="form-control" value="{{ now()->format('Y-m-d') }}">
        </div>
        <div class="col-md-2">
            <label>Store</label>
            <select name="store_id" class="form-select">
                <option value="">All Stores</option>
                @foreach($stores as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-auto">
            <button type="button" class="btn btn-primary btn-sm font-weight-bold px-3" id="btnApplyFilters">
                <i class="mdi mdi-filter me-1"></i> Apply
            </button>
        </div>
    </div>
</form>

{{-- Tabs --}}
<ul class="nav nav-tabs ops-tabs mb-0" id="storeTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link" id="tab-summary" data-bs-toggle="tab" href="#pane-summary" role="tab">
            <i class="mdi mdi-chart-bar me-1"></i> Stock Summary
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" id="tab-requisitions" data-bs-toggle="tab" href="#pane-requisitions" role="tab">
            <i class="mdi mdi-truck-delivery me-1"></i> Requisitions
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-purchase-orders" data-bs-toggle="tab" href="#pane-purchase-orders" role="tab">
            <i class="mdi mdi-file-document-outline me-1"></i> Purchase Orders
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-batches" data-bs-toggle="tab" href="#pane-batches" role="tab">
            <i class="mdi mdi-package-variant me-1"></i> Manual Batches
        </a>
    </li>
</ul>

<div class="tab-content bg-white border border-top-0 rounded-bottom p-3">
    {{-- Tab 1: Summary --}}
    <div class="tab-pane fade" id="pane-summary" role="tabpanel">
        <div class="alert alert-info">
            <i class="mdi mdi-information me-1"></i> For detailed stock movement analysis, cost valuations, and profit/loss breakdown, please refer to the main <strong>Inventory -> Summary Reports</strong> module.
        </div>
        <a href="{{ route('admin.inventory.summary') ?? '#' }}" class="btn btn-outline-primary">Go to Summary Reports</a>
    </div>

    {{-- Tab 2: Requisitions --}}
    <div class="tab-pane fade show active" id="pane-requisitions" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-requisitions"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="from_store_id" class="form-select form-select-sm ops-tab-filter" data-tab="requisitions">
                    <option value="">From Store</option>
                    @foreach($stores as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="to_store_id" class="form-select form-select-sm ops-tab-filter" data-tab="requisitions">
                    <option value="">To Store</option>
                    @foreach($stores as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="requisitions">
                    <option value="">Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="fulfilled">Fulfilled</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-requisitions">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Req No</th>
                        <th>From Store</th>
                        <th>To Store</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th>Approved By</th>
                        <th>Fulfilled By</th>
                        <th>Items</th>
                        <th>Total Value</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 3: Purchase Orders --}}
    <div class="tab-pane fade" id="pane-purchase-orders" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-purchase-orders"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="supplier_id" class="form-select form-select-sm ops-tab-filter" data-tab="purchase_orders">
                    <option value="">Supplier</option>
                    @foreach($suppliers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="target_store_id" class="form-select form-select-sm ops-tab-filter" data-tab="purchase_orders">
                    <option value="">Target Store</option>
                    @foreach($stores as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="purchase_orders">
                    <option value="">Status</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Submitted</option>
                    <option value="approved">Approved</option>
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="payment_status" class="form-select form-select-sm ops-tab-filter" data-tab="purchase_orders">
                    <option value="">Pay Status</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-purchase-orders">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>PO No</th>
                        <th>Supplier</th>
                        <th>Store</th>
                        <th>Status</th>
                        <th>Pay Status</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Created By</th>
                        <th>Approved By</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 4: Manual Batches --}}
    <div class="tab-pane fade" id="pane-batches" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-batches"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="store_id" class="form-select form-select-sm ops-tab-filter" data-tab="batches">
                    <option value="">Store</option>
                    @foreach($stores as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="product_id" class="form-select form-select-sm ops-tab-filter" data-tab="batches">
                    <option value="">Product</option>
                    @foreach($products as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="created_by" class="form-select form-select-sm ops-tab-filter" data-tab="batches">
                    <option value="">Created By</option>
                    @foreach($users as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-batches">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Store</th>
                        <th>Product</th>
                        <th>Batch No</th>
                        <th>Qty</th>
                        <th>Unit Cost</th>
                        <th>Total Value</th>
                        <th>Expiry</th>
                        <th>Created By</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('ops_audit_scripts')
<script>
$(function() {
    var requisitionsUrl = "{{ route('ops-audit.store.data', 'requisitions') }}";
    var purchaseOrdersUrl = "{{ route('ops-audit.store.data', 'purchase_orders') }}";
    var batchesUrl = "{{ route('ops-audit.store.data', 'batches') }}";

    function commonOpts(url, columns, kpiContainer) {
        return {
            dom: '<"d-flex justify-content-between align-items-center mb-2"<"d-flex gap-2"B>f>rt<"d-flex justify-content-between align-items-center mt-2"ip>',
            buttons: [
                { extend: 'copy', className: 'btn btn-xs btn-outline-secondary font-weight-bold' },
                { extend: 'excel', className: 'btn btn-xs btn-outline-success font-weight-bold' },
                { extend: 'pdf', className: 'btn btn-xs btn-outline-danger font-weight-bold' },
                { extend: 'print', className: 'btn btn-xs btn-outline-info font-weight-bold' }
            ],
            processing: true,
            serverSide: true,
            ajax: {
                url: url,
                type: 'GET',
                data: function(d) {
                    var form = $('#ops_audit_filter_form').serializeArray();
                    form.forEach(function(f) { d[f.name] = f.value; });

                    var tabName = $(this).closest('.tab-pane').attr('id')?.replace('pane-', '') || '';
                    $(`.ops-tab-filter[data-tab="${tabName}"]`).each(function() {
                        d[$(this).attr('name')] = $(this).val();
                    });
                },
                dataSrc: function(json) {
                    if (json.kpis && kpiContainer) {
                        renderOpsKpis(json.kpis, kpiContainer);
                    }
                    return json.data;
                }
            },
            columns: columns,
            order: [[0, 'desc']],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: {
                zeroRecords: '<div class="text-center py-3 text-muted"><i class="mdi mdi-database-off" style="font-size:2rem;"></i><br>No records found for this filter.</div>',
                processing: '<div class="text-center py-3"><i class="mdi mdi-loading mdi-spin text-primary" style="font-size:1.5rem;"></i> Loading...</div>'
            }
        };
    }

    var dtRequisitions = $('#dt-requisitions').DataTable(commonOpts(requisitionsUrl, [
        { data: 'date' },
        { data: 'req_no' },
        { data: 'from_store' },
        { data: 'to_store' },
        { data: 'status' },
        { data: 'requested_by' },
        { data: 'approved_by' },
        { data: 'fulfilled_by' },
        { data: 'items_count' },
        { data: 'total_value' },
        { data: 'audit', orderable: false, searchable: false }
    ], 'kpi-requisitions'));

    var dtPurchaseOrders = null;
    var dtBatches = null;
    
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).attr('href');

        if (target === '#pane-purchase-orders' && !dtPurchaseOrders) {
            dtPurchaseOrders = $('#dt-purchase-orders').DataTable(commonOpts(purchaseOrdersUrl, [
                { data: 'date' },
                { data: 'po_no' },
                { data: 'supplier' },
                { data: 'store' },
                { data: 'status' },
                { data: 'pay_status' },
                { data: 'total' },
                { data: 'paid' },
                { data: 'balance' },
                { data: 'created_by' },
                { data: 'approved_by' },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-purchase-orders'));
        }

        if (target === '#pane-batches' && !dtBatches) {
            dtBatches = $('#dt-batches').DataTable(commonOpts(batchesUrl, [
                { data: 'date' },
                { data: 'store' },
                { data: 'product' },
                { data: 'batch_no' },
                { data: 'qty' },
                { data: 'unit_cost' },
                { data: 'total_value' },
                { data: 'expiry' },
                { data: 'created_by' },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-batches'));
        }

        setTimeout(function() {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }, 200);
    });

    $('#btnApplyFilters').on('click', function() {
        if (dtRequisitions) dtRequisitions.ajax.reload();
        if (dtPurchaseOrders) dtPurchaseOrders.ajax.reload();
        if (dtBatches) dtBatches.ajax.reload();
    });

    $(document).on('change', '.ops-tab-filter', function() {
        var tab = $(this).data('tab');
        if (tab === 'requisitions' && dtRequisitions) dtRequisitions.ajax.reload();
        if (tab === 'purchase_orders' && dtPurchaseOrders) dtPurchaseOrders.ajax.reload();
        if (tab === 'batches' && dtBatches) dtBatches.ajax.reload();
    });
});

function printCurrentTab() {
    var activeTable = $('.tab-pane.active table.ops-datatable');
    if (activeTable.length && $.fn.DataTable.isDataTable(activeTable)) {
        activeTable.DataTable().button('.buttons-print').trigger();
    }
}
</script>
@endpush
