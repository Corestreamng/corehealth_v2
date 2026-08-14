@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-store"></i> Ward & Department Sub-Stores Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'ward-dept-stores', 'zoneLabel' => 'Ward & Department Sub-Stores Zone'])

{{-- PARENT-LEVEL NAV --}}
<ul class="nav nav-pills mb-3" id="wd-parent-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active font-weight-bold px-4" id="wd-audit-register-tab" data-bs-toggle="pill" data-bs-target="#wd-audit-register" type="button" role="tab">
            <i class="mdi mdi-clipboard-check-outline"></i> Audit Register
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link font-weight-bold px-4" id="wd-stories-tab" data-bs-toggle="pill" data-bs-target="#wd-stories" type="button" role="tab">
            <i class="mdi mdi-chart-timeline-variant"></i> Sub-Store Stories
        </button>
    </li>
</ul>

<div class="tab-content" id="wd-parent-content">

{{-- TAB 1: AUDIT REGISTER --}}
<div class="tab-pane fade show active" id="wd-audit-register" role="tabpanel">
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
</div>{{-- /wd-audit-register --}}

{{-- TAB 2: SUB-STORE STORIES --}}
<div class="tab-pane fade" id="wd-stories" role="tabpanel">
<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="wdStoryTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active py-3 font-weight-bold" id="st-substore-val-tab" data-bs-toggle="tab" data-bs-target="#st-substore-val" type="button">
                    <i class="mdi mdi-store-24-hour text-primary"></i> 6. Sub-Store Stock Valuation
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-req-fulfill-tab" data-bs-toggle="tab" data-bs-target="#st-req-fulfill" type="button">
                    <i class="mdi mdi-clock-check text-success"></i> 7. Requisition Fulfillment Rate
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-req-items-tab" data-bs-toggle="tab" data-bs-target="#st-req-items" type="button">
                    <i class="mdi mdi-format-list-checks text-info"></i> 8. Requisition Items & Gap
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-ward-mov-tab" data-bs-toggle="tab" data-bs-target="#st-ward-mov" type="button">
                    <i class="mdi mdi-arrow-decision text-warning"></i> 9. Ward Stock Movements
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-returns-tab" data-bs-toggle="tab" data-bs-target="#st-returns" type="button">
                    <i class="mdi mdi-arrow-u-left-top text-danger"></i> 10. Store & Patient Returns
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="wdStoryContent">
            @foreach(['substore-valuation' => 'st-substore-val', 'requisition-fulfillment' => 'st-req-fulfill', 'requisition-items-audit' => 'st-req-items', 'ward-stock-movement' => 'st-ward-mov', 'return-analysis' => 'st-returns'] as $storySlug => $paneId)
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
</div>{{-- /wd-stories --}}

</div>{{-- /wd-parent-content --}}
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

    $('#table-substore-stock').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.ward-dept-stores.data', 'stock') }}", data: appendMultidimData },
        columns: [
            { data: 'store_details', name: 'store.store_name' },
            { data: 'product_details', name: 'product.product_name' },
            { data: 'quantity_badge', name: 'quantity' },
            { data: 'valuation', name: 'unit_cost' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-substore-requisitions').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.ward-dept-stores.data', 'requisitions') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'stores_flow', name: 'fromStore.store_name' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    // Story Data Loader
    var storyDataUrl = "{{ route('audit.ward-dept-stories.data', '__STORY__') }}";

    function loadWdStory(paneEl) {
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

    $('#wd-stories-tab').on('shown.bs.tab', function() {
        var $activePane = $('#wdStoryContent .tab-pane.active');
        if ($activePane.length) loadWdStory($activePane[0]);
    });
    $('#wdStoryTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).data('bs-target');
        loadWdStory($(target)[0]);
    });
    $('#apply_filters_btn').on('click', function() {
        $('#wdStoryContent .tab-pane').data('loaded', 0);
        var $activePane = $('#wdStoryContent .tab-pane.active');
        if ($('#wd-stories-tab').hasClass('active') && $activePane.length) loadWdStory($activePane[0]);
    });
});
</script>
@endpush
