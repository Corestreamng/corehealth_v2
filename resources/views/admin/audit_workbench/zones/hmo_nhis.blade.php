@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-hospital-building"></i> HMO & NHIS Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'hmo-nhis', 'zoneLabel' => 'HMO & NHIS Zone'])

{{-- PARENT-LEVEL NAV --}}
<ul class="nav nav-pills mb-3" id="hn-parent-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active font-weight-bold px-4" id="hn-audit-register-tab" data-bs-toggle="pill" data-bs-target="#hn-audit-register" type="button" role="tab">
            <i class="mdi mdi-clipboard-check-outline"></i> Audit Register
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link font-weight-bold px-4" id="hn-stories-tab" data-bs-toggle="pill" data-bs-target="#hn-stories" type="button" role="tab">
            <i class="mdi mdi-chart-timeline-variant"></i> HMO Stories
        </button>
    </li>
</ul>

<div class="tab-content" id="hn-parent-content">

{{-- TAB 1: AUDIT REGISTER --}}
<div class="tab-pane fade show active" id="hn-audit-register" role="tabpanel">
<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="hmoTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3 font-weight-bold" id="services-tab" data-bs-toggle="tab" data-bs-target="#services" type="button" role="tab">
                    <i class="mdi mdi-clipboard-check text-info"></i> HMO Service Validations
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="claims-tab" data-bs-toggle="tab" data-bs-target="#claims" type="button" role="tab">
                    <i class="mdi mdi-file-document-outline text-warning"></i> Claims Processing
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="remittances-tab" data-bs-toggle="tab" data-bs-target="#remittances" type="button" role="tab">
                    <i class="mdi mdi-cash-register text-success"></i> HMO Remittances
                </button>
            </li>
        </ul>
    </div>
    
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="hmoTabsContent">
            
            {{-- Services Tab --}}
            <div class="tab-pane fade show active" id="services" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Pending Validations</h6>
                                <h3 class="mb-0">{{ $kpis['pending_validations'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Validated Services</h6>
                                <h3 class="mb-0">{{ $kpis['validated_services'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-danger text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Rejected Services</h6>
                                <h3 class="mb-0">{{ $kpis['rejected_services'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-hmo-services" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Patient & HMO</th>
                                        <th>Service / Product Item</th>
                                        <th>Claims Amount</th>
                                        <th>Validation Status</th>
                                        <th>Audit Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Claims Tab --}}
            <div class="tab-pane fade" id="claims" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Claimed Amount</h6>
                                <h3 class="mb-0">₦{{ number_format($kpis['total_claims_amount'] ?? 0, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Pending Claims Count</h6>
                                <h3 class="mb-0">{{ $kpis['pending_claims_count'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Processed Claims</h6>
                                <h3 class="mb-0">{{ $kpis['processed_claims_count'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-hmo-claims" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>HMO Provider</th>
                                        <th>Claimed Amount</th>
                                        <th>Audit Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Remittances Tab --}}
            <div class="tab-pane fade" id="remittances" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card bg-success text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Remittances Received</h6>
                                <h3 class="mb-0">₦{{ number_format($kpis['total_remittances_amount'] ?? 0, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-info text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Remittance Batches Count</h6>
                                <h3 class="mb-0">{{ $kpis['remittance_count'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-hmo-remittances" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Payment Date</th>
                                        <th>HMO Provider</th>
                                        <th>Remitted Amount</th>
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
</div>{{-- /hn-audit-register --}}

{{-- TAB 2: HMO STORIES --}}
<div class="tab-pane fade" id="hn-stories" role="tabpanel">
<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="hnStoryTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active py-3 font-weight-bold" id="st-hmo-provider-tab" data-bs-toggle="tab" data-bs-target="#st-hmo-provider" type="button">
                    <i class="mdi mdi-shield-account text-primary"></i> 16. Provider Exposure & Schemes
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-val-aging-tab" data-bs-toggle="tab" data-bs-target="#st-val-aging" type="button">
                    <i class="mdi mdi-clock-alert text-warning"></i> 17. Validation Status & Value at Risk
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-scheme-breakdown-tab" data-bs-toggle="tab" data-bs-target="#st-scheme-breakdown" type="button">
                    <i class="mdi mdi-shield-outline text-info"></i> 18. All Insurance Schemes Audit
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-cov-mode-tab" data-bs-toggle="tab" data-bs-target="#st-cov-mode" type="button">
                    <i class="mdi mdi-layers-triple text-purple"></i> 19. Coverage Mode Breakdown
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-remit-match-tab" data-bs-toggle="tab" data-bs-target="#st-remit-match" type="button">
                    <i class="mdi mdi-file-compare text-success"></i> 20. Remittance vs Claims Matching
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="hnStoryContent">
            @foreach(['hmo-claims-by-provider' => 'st-hmo-provider', 'validation-status-aging' => 'st-val-aging', 'scheme-breakdown' => 'st-scheme-breakdown', 'coverage-mode-analysis' => 'st-cov-mode', 'remittance-vs-claims-matching' => 'st-remit-match'] as $storySlug => $paneId)
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
</div>{{-- /hn-stories --}}

</div>{{-- /hn-parent-content --}}
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

    $('#table-hmo-services').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.hmo-nhis.data', 'services') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_hmo', name: 'patient.user.name' },
            { data: 'service_item', name: 'product.product_name' },
            { data: 'claims_amount_formatted', name: 'claims_amount' },
            { data: 'validation_status_badge', name: 'validation_status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-hmo-claims').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.hmo-nhis.data', 'claims') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'hmo_details', name: 'hmo.name' },
            { data: 'claim_amount_formatted', name: 'total_claims' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-hmo-remittances').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.hmo-nhis.data', 'remittances') }}", data: appendMultidimData },
        columns: [
            { data: 'payment_date', name: 'payment_date' },
            { data: 'hmo_details', name: 'hmo.name' },
            { data: 'amount_formatted', name: 'amount' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    // Story Data Loader
    var storyDataUrl = "{{ route('audit.hmo-nhis-stories.data', '__STORY__') }}";

    function loadHnStory(paneEl) {
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

    $('#hn-stories-tab').on('shown.bs.tab', function() {
        var $activePane = $('#hnStoryContent .tab-pane.active');
        if ($activePane.length) loadHnStory($activePane[0]);
    });
    $('#hnStoryTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).data('bs-target');
        loadHnStory($(target)[0]);
    });
    $('#apply_filters_btn').on('click', function() {
        $('#hnStoryContent .tab-pane').data('loaded', 0);
        var $activePane = $('#hnStoryContent .tab-pane.active');
        if ($('#hn-stories-tab').hasClass('active') && $activePane.length) loadHnStory($activePane[0]);
    });
});
</script>
@endpush
