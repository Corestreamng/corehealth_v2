@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-trending-up"></i> Store Utilization vs Revenue Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'store-utilization-revenue', 'zoneLabel' => 'Store Utilization vs Revenue Zone'])

{{-- PARENT-LEVEL NAV --}}
<ul class="nav nav-pills mb-3" id="su-parent-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active font-weight-bold px-4" id="su-audit-register-tab" data-bs-toggle="pill" data-bs-target="#su-audit-register" type="button" role="tab">
            <i class="mdi mdi-clipboard-check-outline"></i> Audit Register
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link font-weight-bold px-4" id="su-stories-tab" data-bs-toggle="pill" data-bs-target="#su-stories" type="button" role="tab">
            <i class="mdi mdi-chart-timeline-variant"></i> Utilization Stories
        </button>
    </li>
</ul>

<div class="tab-content" id="su-parent-content">

{{-- TAB 1: AUDIT REGISTER --}}
<div class="tab-pane fade show active" id="su-audit-register" role="tabpanel">
<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="utilizationTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3 font-weight-bold" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">
                    <i class="mdi mdi-chart-line text-info"></i> Utilization Movements
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="revenue-tab" data-bs-toggle="tab" data-bs-target="#revenue" type="button" role="tab">
                    <i class="mdi mdi-currency-usd text-success"></i> Associated Billed Revenue
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="dept-recon-tab" data-bs-toggle="tab" data-bs-target="#dept-recon" type="button" role="tab">
                    <i class="mdi mdi-scale-balance text-primary"></i> Dept Requisitions vs Revenue Reconciliation
                </button>
            </li>
        </ul>
    </div>
    
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="utilizationTabsContent">
            
            {{-- Movements Tab --}}
            <div class="tab-pane fade show active" id="transactions" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-info text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Stock Transactions</h6>
                                <h3 class="mb-0">{{ $kpis['total_transactions'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Items Sold/Used</h6>
                                <h3 class="mb-0">{{ $kpis['total_qty_used'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-danger text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Adjustments/Loss</h6>
                                <h3 class="mb-0">{{ $kpis['total_qty_lost'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-utilization-txns" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Product Name</th>
                                        <th>Batch Number</th>
                                        <th>Type</th>
                                        <th>Qty</th>
                                        <th>Details / Reference</th>
                                        <th>Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Revenue Tab --}}
            <div class="tab-pane fade" id="revenue" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-success text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Billed Revenue</h6>
                                <h3 class="mb-0">₦{{ number_format($kpis['total_billed_revenue'] ?? 0, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-primary text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Cash Revenue</h6>
                                <h3 class="mb-0">₦{{ number_format($kpis['cash_revenue'] ?? 0, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>HMO Revenue</h6>
                                <h3 class="mb-0">₦{{ number_format($kpis['hmo_revenue'] ?? 0, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-revenue" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Product Item</th>
                                        <th>Dispensing Store</th>
                                        <th>Billed Revenue</th>
                                        <th>Coverage</th>
                                        <th>Audit Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Dept Reconciliations Tab --}}
            <div class="tab-pane fade" id="dept-recon" role="tabpanel">
                <div class="alert alert-info border-0 shadow-sm mb-4">
                    <i class="mdi mdi-information-outline"></i> <strong>Department Requisition vs Revenue Reconciliation:</strong> Matches store requisitions fulfilled for departmental stores (using batch/product buying prices as fallback) against service revenue generated by each respective department.
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Department Unit</th>
                                        <th>Fulfilled Requisitions Cost (Cost)</th>
                                        <th>Billed Service Revenue (Revenue)</th>
                                        <th>Gross Profit / Margin</th>
                                        <th>Audit Risk Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($deptReconciliations as $key => $d)
                                    <tr>
                                        <td class="font-weight-bold text-dark fs-6">{{ $d->name }}</td>
                                        <td class="text-nowrap font-weight-bold text-danger">₦{{ number_format($d->cost, 2) }}</td>
                                        <td class="text-nowrap font-weight-bold text-success">₦{{ number_format($d->revenue, 2) }}</td>
                                        <td class="text-nowrap font-weight-bold {{ $d->margin >= 0 ? 'text-success' : 'text-danger' }}">
                                            ₦{{ number_format($d->margin, 2) }}
                                        </td>
                                        <td>
                                            @if($d->margin >= 0)
                                                <span class="badge bg-success"><i class="mdi mdi-check-circle"></i> Balanced / Profit</span>
                                            @else
                                                <span class="badge bg-danger"><i class="mdi mdi-alert-circle"></i> Revenue Deficit</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>
</div>{{-- /su-audit-register --}}

{{-- TAB 2: UTILIZATION STORIES --}}
<div class="tab-pane fade" id="su-stories" role="tabpanel">
<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="suStoryTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active py-3 font-weight-bold" id="st-disp-rev-tab" data-bs-toggle="tab" data-bs-target="#st-disp-rev" type="button">
                    <i class="mdi mdi-cash-multiple text-primary"></i> 11. Dispensing Revenue & Margins
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-store-contrib-tab" data-bs-toggle="tab" data-bs-target="#st-store-contrib" type="button">
                    <i class="mdi mdi-store-cog text-success"></i> 12. Store Dispensing Contribution
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-gap-recon-tab" data-bs-toggle="tab" data-bs-target="#st-gap-recon" type="button">
                    <i class="mdi mdi-beaker-question-outline text-info"></i> 13. Consumption vs Billing Gap
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-daily-trend-tab" data-bs-toggle="tab" data-bs-target="#st-daily-trend" type="button">
                    <i class="mdi mdi-chart-bell-curve-cumulative text-warning"></i> 14. Daily Movement Trends
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-turnover-tab" data-bs-toggle="tab" data-bs-target="#st-turnover" type="button">
                    <i class="mdi mdi-rotate-3d-variant text-danger"></i> 15. Product Turnover & Idle Stock
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="suStoryContent">
            @foreach(['dispensing-revenue-attribution' => 'st-disp-rev', 'store-dispensing-contribution' => 'st-store-contrib', 'consumption-vs-billing-gap' => 'st-gap-recon', 'daily-stock-movement-trend' => 'st-daily-trend', 'product-turnover-rate' => 'st-turnover'] as $storySlug => $paneId)
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
</div>{{-- /su-stories --}}

</div>{{-- /su-parent-content --}}
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

    $('#table-utilization-txns').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.store-utilization-revenue.data', 'transactions') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'product', name: 'stockBatch.product.product_name' },
            { data: 'batch', name: 'stockBatch.batch_number' },
            { data: 'type', name: 'type' },
            { data: 'qty_formatted', name: 'qty' },
            { data: 'reference', name: 'notes' },
            { data: 'performer', name: 'performer.firstname' }
        ]
    }));

    $('#table-revenue').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.store-utilization-revenue.data', 'revenue') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'product_name', name: 'product.product_name' },
            { data: 'store_name', name: 'dispensedFromStore.store_name' },
            { data: 'revenue_amount', name: 'payable_amount' },
            { data: 'coverage', name: 'coverage_mode' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    // Story Data Loader
    var storyDataUrl = "{{ route('audit.store-utilization-stories.data', '__STORY__') }}";

    function loadSuStory(paneEl) {
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

    $('#su-stories-tab').on('shown.bs.tab', function() {
        var $activePane = $('#suStoryContent .tab-pane.active');
        if ($activePane.length) loadSuStory($activePane[0]);
    });
    $('#suStoryTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).data('bs-target');
        loadSuStory($(target)[0]);
    });
    $('#apply_filters_btn').on('click', function() {
        $('#suStoryContent .tab-pane').data('loaded', 0);
        var $activePane = $('#suStoryContent .tab-pane.active');
        if ($('#su-stories-tab').hasClass('active') && $activePane.length) loadSuStory($activePane[0]);
    });
});
</script>
@endpush
