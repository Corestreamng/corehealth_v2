@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-book-open-page-variant"></i> Service Registers vs Billing Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'service-registers-billing', 'zoneLabel' => 'Service Registers vs Billing Zone'])

{{-- PARENT-LEVEL NAV --}}
<ul class="nav nav-pills mb-3" id="sr-parent-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active font-weight-bold px-4" id="sr-audit-register-tab" data-bs-toggle="pill" data-bs-target="#sr-audit-register" type="button" role="tab">
            <i class="mdi mdi-clipboard-check-outline"></i> Audit Register
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link font-weight-bold px-4" id="sr-stories-tab" data-bs-toggle="pill" data-bs-target="#sr-stories" type="button" role="tab">
            <i class="mdi mdi-chart-timeline-variant"></i> Service Stories
        </button>
    </li>
</ul>

<div class="tab-content" id="sr-parent-content">

{{-- TAB 1: AUDIT REGISTER --}}
<div class="tab-pane fade show active" id="sr-audit-register" role="tabpanel">
<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="serviceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3 font-weight-bold" id="services-tab" data-bs-toggle="tab" data-bs-target="#services" type="button" role="tab">
                    <i class="mdi mdi-doctor text-info"></i> Clinical Services
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="billing-tab" data-bs-toggle="tab" data-bs-target="#billing" type="button" role="tab">
                    <i class="mdi mdi-currency-usd text-success"></i> Billed Services
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="procedures-tab" data-bs-toggle="tab" data-bs-target="#procedures" type="button" role="tab">
                    <i class="mdi mdi-knife text-danger"></i> Procedures & Theatre
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="maternity-tab" data-bs-toggle="tab" data-bs-target="#maternity" type="button" role="tab">
                    <i class="mdi mdi-baby-carriage text-warning"></i> Maternity & Antenatal
                </button>
            </li>
        </ul>
    </div>
    
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="serviceTabsContent">
            
            {{-- Services Tab --}}
            <div class="tab-pane fade show active" id="services" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Encounters</h6>
                                <h3 class="mb-0">{{ $kpis['total_encounters'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Inpatient Admissions</h6>
                                <h3 class="mb-0">{{ $kpis['total_admissions'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Surgical Procedures</h6>
                                <h3 class="mb-0">{{ $kpis['total_procedures'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-clinical-services" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Patient Details</th>
                                        <th>Attending Doctor</th>
                                        <th>Audit Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Billing Tab --}}
            <div class="tab-pane fade" id="billing" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-success text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Service Revenue</h6>
                                <h3 class="mb-0">₦{{ number_format($kpis['total_service_revenue'] ?? 0, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Billed Service Count</h6>
                                <h3 class="mb-0">{{ $kpis['total_billed_services'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-primary text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Avg Revenue/Service</h6>
                                <h3 class="mb-0">₦{{ number_format($kpis['avg_revenue_per_service'] ?? 0, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-billed-services" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Billed Date</th>
                                        <th>Patient Details</th>
                                        <th>Service Details</th>
                                        <th>Total Amount</th>
                                        <th>Audit Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Procedures Tab --}}
            <div class="tab-pane fade" id="procedures" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-procedures" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date & Category</th>
                                        <th>Patient Details</th>
                                        <th>Procedure Name</th>
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

            {{-- Maternity Tab --}}
            <div class="tab-pane fade" id="maternity" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-maternity" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Enrollment Date</th>
                                        <th>Patient Details</th>
                                        <th>EDD / Gestation</th>
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
</div>{{-- /sr-audit-register --}}

{{-- TAB 2: SERVICE STORIES --}}
<div class="tab-pane fade" id="sr-stories" role="tabpanel">
<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="srStoryTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active py-3 font-weight-bold" id="st-svc-cat-tab" data-bs-toggle="tab" data-bs-target="#st-svc-cat" type="button">
                    <i class="mdi mdi-shape text-primary"></i> 21. Service Category Revenue
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-doc-ref-tab" data-bs-toggle="tab" data-bs-target="#st-doc-ref" type="button">
                    <i class="mdi mdi-doctor text-success"></i> 22. Doctor Referral & Billing
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-svc-hmo-comp-tab" data-bs-toggle="tab" data-bs-target="#st-svc-hmo-comp" type="button">
                    <i class="mdi mdi-check-decagram text-info"></i> 23. Service HMO Approval Risk
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-unbilled-enc-tab" data-bs-toggle="tab" data-bs-target="#st-unbilled-enc" type="button">
                    <i class="mdi mdi-ghost text-danger"></i> 24. Unbilled Encounters Leakage
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-proc-audit-tab" data-bs-toggle="tab" data-bs-target="#st-proc-audit" type="button">
                    <i class="mdi mdi-needle text-warning"></i> 25. Procedure & Theatre Billing
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="srStoryContent">
            @foreach(['service-category-revenue' => 'st-svc-cat', 'doctor-referral-billing' => 'st-doc-ref', 'service-vs-hmo-compliance' => 'st-svc-hmo-comp', 'unbilled-encounters' => 'st-unbilled-enc', 'procedure-billing-audit' => 'st-proc-audit'] as $storySlug => $paneId)
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
</div>{{-- /sr-stories --}}

</div>{{-- /sr-parent-content --}}
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

    $('#table-clinical-services').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.service-registers-billing.data', 'clinical-services') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_details', name: 'patient.user.name' },
            { data: 'doctor_name', name: 'doctor.name' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-billed-services').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.service-registers-billing.data', 'billed-services') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_details', name: 'patient.user.name' },
            { data: 'service_details', name: 'service.service_name' },
            { data: 'total_formatted', name: 'payable_amount' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-procedures').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.service-registers-billing.data', 'procedures') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_details', name: 'patient.user.name' },
            { data: 'procedure_name', name: 'procedure_definition.name' },
            { data: 'status_badge', name: 'procedure_status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-maternity').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.service-registers-billing.data', 'maternity') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_details', name: 'patient.user.name' },
            { data: 'edd_gestation', name: 'edd' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    // Story Data Loader
    var storyDataUrl = "{{ route('audit.service-registers-stories.data', '__STORY__') }}";

    function loadSrStory(paneEl) {
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

    $('#sr-stories-tab').on('shown.bs.tab', function() {
        var $activePane = $('#srStoryContent .tab-pane.active');
        if ($activePane.length) loadSrStory($activePane[0]);
    });
    $('#srStoryTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).data('bs-target');
        loadSrStory($(target)[0]);
    });
    $('#apply_filters_btn').on('click', function() {
        $('#srStoryContent .tab-pane').data('loaded', 0);
        var $activePane = $('#srStoryContent .tab-pane.active');
        if ($('#sr-stories-tab').hasClass('active') && $activePane.length) loadSrStory($activePane[0]);
    });
});
</script>
@endpush
