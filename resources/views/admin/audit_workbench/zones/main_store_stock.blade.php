@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-warehouse"></i> Main Store Stock Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'main-store-stock', 'zoneLabel' => 'Main Store Stock Zone'])

{{-- PARENT-LEVEL NAV --}}
<ul class="nav nav-pills mb-3" id="ms-parent-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active font-weight-bold px-4" id="ms-audit-register-tab" data-bs-toggle="pill" data-bs-target="#ms-audit-register" type="button" role="tab">
            <i class="mdi mdi-clipboard-check-outline"></i> Audit Register
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link font-weight-bold px-4" id="ms-stories-tab" data-bs-toggle="pill" data-bs-target="#ms-stories" type="button" role="tab">
            <i class="mdi mdi-chart-timeline-variant"></i> Main Store Stories
        </button>
    </li>
</ul>

<div class="tab-content" id="ms-parent-content">

{{-- TAB 1: AUDIT REGISTER --}}
<div class="tab-pane fade show active" id="ms-audit-register" role="tabpanel">
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
</div>{{-- /ms-audit-register --}}

{{-- TAB 2: MAIN STORE STORIES --}}
<div class="tab-pane fade" id="ms-stories" role="tabpanel">
<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="msStoryTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active py-3 font-weight-bold" id="st-batch-val-tab" data-bs-toggle="tab" data-bs-target="#st-batch-val" type="button">
                    <i class="mdi mdi-buffer text-primary"></i> 1. Category Stock Valuation
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-po-perf-tab" data-bs-toggle="tab" data-bs-target="#st-po-perf" type="button">
                    <i class="mdi mdi-truck-check text-success"></i> 2. Procurement & Price Variances
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-sup-analysis-tab" data-bs-toggle="tab" data-bs-target="#st-sup-analysis" type="button">
                    <i class="mdi mdi-account-group text-info"></i> 3. Supplier Payments & Debts
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-damage-loss-tab" data-bs-toggle="tab" data-bs-target="#st-damage-loss" type="button">
                    <i class="mdi mdi-alert-decagram text-danger"></i> 4. Damage & Expiry Losses
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-source-breakdown-tab" data-bs-toggle="tab" data-bs-target="#st-source-breakdown" type="button">
                    <i class="mdi mdi-source-branch text-secondary"></i> 5. Batch Acquisition Sources
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="msStoryContent">
            @foreach(['batch-valuation' => 'st-batch-val', 'procurement-performance' => 'st-po-perf', 'supplier-analysis' => 'st-sup-analysis', 'damage-expiry-losses' => 'st-damage-loss', 'batch-source-breakdown' => 'st-source-breakdown'] as $storySlug => $paneId)
            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $paneId }}" role="tabpanel" data-story="{{ $storySlug }}">
                <div class="row g-3 mb-4 story-cards"></div>
                <div class="card shadow-sm border-0"><div class="card-body p-3"><div class="table-responsive">
                    <table class="table table-hover align-middle w-100 story-table" id="table-{{ $paneId }}">
                        <thead class="bg-light"><tr></tr></thead>
                        <tbody></tbody>
                    </table>
                </div></div></div>
            </div>
            @endforeach
        </div>
    </div>
</div>
</div>{{-- /ms-stories --}}

</div>{{-- /ms-parent-content --}}
@endsection

@push('audit_scripts')
<script>
$(document).ready(function() {
    let commonDtConfig = {
        dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex gap-2"B><"d-flex align-items-center"f>>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
        iDisplayLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
        processing: true, serverSide: true, responsive: true, order: [[0, 'desc']]
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
        ajax: { url: "{{ route('audit.main-store-stock.data', 'stock') }}", data: appendMultidimData },
        columns: [
            { data: 'product_details', name: 'product.product_name' },
            { data: 'batch_expiry', name: 'batch_number' },
            { data: 'quantity_badge', name: 'quantity' },
            { data: 'cost_valuation', name: 'unit_cost' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-purchase-orders').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.main-store-stock.data', 'purchase-orders') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'po_supplier', name: 'po_number' },
            { data: 'amount_formatted', name: 'total_amount' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    // Story Data Loader
    var storyDataUrl = "{{ route('audit.main-store-stories.data', '__STORY__') }}";

    function loadMsStory(paneEl) {
        var $pane = $(paneEl);
        var story = $pane.data('story');
        if ($pane.data('loaded') == 1) return;

        var url = storyDataUrl.replace('__STORY__', story);
        var params = {
            start_date: $('#filter_start_date').val(),
            end_date: $('#filter_end_date').val(),
            hmo_scheme_id: $('#filter_hmo_scheme_id').val(),
            hmo_id: $('#filter_hmo_id').val(),
            gender: $('#filter_gender').val(),
            age_range: $('#filter_age_range').val(),
            audit_status: $('#filter_audit_status').val(),
        };

        $pane.find('.story-cards').html('<div class="col-12 text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>');

        $.get(url, params, function(data) {
            var cardsHtml = '';
            if (data.cards) {
                var colSize = data.cards.length <= 3 ? 4 : (data.cards.length <= 4 ? 3 : 2);
                data.cards.forEach(function(card) {
                    cardsHtml += '<div class="col-md-' + colSize + ' col-6 mb-2"><div class="card shadow-sm border-0 h-100 ' + card.class + '"><div class="card-body py-3 px-3"><h6 class="mb-1" style="font-size:0.8rem;">' + card.label + '</h6><h4 class="mb-0 font-weight-bold">' + card.value + '</h4></div></div></div>';
                });
            }
            $pane.find('.story-cards').html(cardsHtml);

            var $table = $pane.find('.story-table');
            var tableId = $table.attr('id');
            if (tableId && $.fn.DataTable.isDataTable('#' + tableId)) {
                $('#' + tableId).DataTable().clear().destroy();
            }
            $table.empty().append('<thead class="bg-light"><tr></tr></thead><tbody></tbody>');

            var $tr = $table.find('thead tr');
            if (data.headers) {
                data.headers.forEach(function(h) { $tr.append('<th>' + h + '</th>'); });
            }
            var $tbody = $table.find('tbody');
            if (data.rows) {
                data.rows.forEach(function(row) {
                    var trHtml = '<tr>';
                    Object.values(row).forEach(function(val) { trHtml += '<td>' + val + '</td>'; });
                    trHtml += '</tr>';
                    $tbody.append(trHtml);
                });
            }

            $table.DataTable({
                dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex gap-2"B><"d-flex align-items-center"f>>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
                buttons: ['copy', 'excel', 'pdf', 'print'],
                paging: true, pageLength: 25, order: [], responsive: true, destroy: true
            });
            $pane.data('loaded', 1);
        });
    }

    $('#ms-stories-tab').on('shown.bs.tab', function() {
        var $activePane = $('#msStoryContent .tab-pane.active');
        if ($activePane.length) loadMsStory($activePane[0]);
    });
    $('#msStoryTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).data('bs-target');
        loadMsStory($(target)[0]);
    });
    $('#apply_filters_btn').on('click', function() {
        $('#msStoryContent .tab-pane').data('loaded', 0);
        var $activePane = $('#msStoryContent .tab-pane.active');
        if ($('#ms-stories-tab').hasClass('active') && $activePane.length) loadMsStory($activePane[0]);
    });
});
</script>
@endpush
