@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-pill"></i> Pharmacy & Mortuary Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'pharmacy-mortuary', 'zoneLabel' => 'Pharmacy & Mortuary Zone'])

{{-- PARENT-LEVEL NAV --}}
<ul class="nav nav-pills mb-3" id="pm-parent-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active font-weight-bold px-4" id="pm-audit-register-tab" data-bs-toggle="pill" data-bs-target="#pm-audit-register" type="button" role="tab">
            <i class="mdi mdi-clipboard-check-outline"></i> Audit Register
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link font-weight-bold px-4" id="pm-stories-tab" data-bs-toggle="pill" data-bs-target="#pm-stories" type="button" role="tab">
            <i class="mdi mdi-chart-timeline-variant"></i> Pharmacy Stories
        </button>
    </li>
</ul>

<div class="tab-content" id="pm-parent-content">

{{-- TAB 1: AUDIT REGISTER --}}
<div class="tab-pane fade show active" id="pm-audit-register" role="tabpanel">
<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="pmTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3 font-weight-bold" id="rx-tab" data-bs-toggle="tab" data-bs-target="#pharmacy-dispense" type="button" role="tab">
                    <i class="mdi mdi-pill text-success"></i> Pharmacy Dispense (Doctor Prescriptions)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="ward-billing-tab" data-bs-toggle="tab" data-bs-target="#ward-direct-billing" type="button" role="tab">
                    <i class="mdi mdi-store-24-hour text-primary"></i> Direct Billing (Nurse & Ward Consumables)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="mortuary-tab" data-bs-toggle="tab" data-bs-target="#mortuary" type="button" role="tab">
                    <i class="mdi mdi-coffin text-secondary"></i> Mortuary Admissions
                </button>
            </li>
        </ul>
    </div>
    
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="pmTabsContent">
            
            {{-- Pharmacy Dispense Tab --}}
            <div class="tab-pane fade show active" id="pharmacy-dispense" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card bg-success text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Pharmacy Prescriptions (Doctor Prescribed)</h6>
                                <h3 class="mb-0">{{ $kpis['pharmacy_dispense_count'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-info text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Classification</h6>
                                <h5 class="mb-0">Pharmacy Dispense (Fulfills Doctor Prescription)</h5>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-pharmacy-dispense" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Prescription Date</th>
                                        <th>Patient & Doctor</th>
                                        <th>Medication & Store</th>
                                        <th>Classification</th>
                                        <th>Audit Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Direct Ward Billing Tab --}}
            <div class="tab-pane fade" id="ward-direct-billing" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card bg-primary text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Direct Ward & Nurse Consumables Billed</h6>
                                <h3 class="mb-0">{{ $kpis['direct_ward_billing_count'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-warning text-dark h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Consumables Billed Revenue</h6>
                                <h3 class="mb-0">₦{{ number_format($kpis['direct_ward_billing_revenue'] ?? 0, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-ward-direct-billing" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Billing Date</th>
                                        <th>Patient Details</th>
                                        <th>Consumable & Store</th>
                                        <th>Billed Amount</th>
                                        <th>Classification</th>
                                        <th>Audit Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Mortuary Tab --}}
            <div class="tab-pane fade" id="mortuary" role="tabpanel">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card bg-secondary text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Total Admissions</h6>
                                <h3 class="mb-0">{{ $kpis['mortuary_admissions_count'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Currently Admitted</h6>
                                <h3 class="mb-0">{{ $kpis['mortuary_currently_admitted'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white h-100 shadow-sm border-0">
                            <div class="card-body">
                                <h6>Released Bodies</h6>
                                <h3 class="mb-0">{{ $kpis['mortuary_released'] ?? 0 }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table id="table-mortuary" class="table table-hover align-middle w-100">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Arrival Date</th>
                                        <th>Deceased / Patient Details</th>
                                        <th>Fridge / Tray Location</th>
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
</div>{{-- /pm-audit-register --}}

{{-- TAB 2: PHARMACY STORIES --}}
<div class="tab-pane fade" id="pm-stories" role="tabpanel">
<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="pmStoryTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active py-3 font-weight-bold" id="st-disp-perf-tab" data-bs-toggle="tab" data-bs-target="#st-disp-perf" type="button">
                    <i class="mdi mdi-account-tie text-primary"></i> 26. Pharmacist Dispenser Performance
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-adapt-audit-tab" data-bs-toggle="tab" data-bs-target="#st-adapt-audit" type="button">
                    <i class="mdi mdi-pill-off text-warning"></i> 27. Drug Adaptations Clinical Audit
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-consumable-kit-tab" data-bs-toggle="tab" data-bs-target="#st-consumable-kit" type="button">
                    <i class="mdi mdi-needle text-info"></i> 28. Nurse Consumables Billing Kit
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-drug-cat-tab" data-bs-toggle="tab" data-bs-target="#st-drug-cat" type="button">
                    <i class="mdi mdi-format-list-bulleted-type text-success"></i> 29. Drug Category Dispensing
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 font-weight-bold" id="st-pharm-loss-tab" data-bs-toggle="tab" data-bs-target="#st-pharm-loss" type="button">
                    <i class="mdi mdi-arrow-u-left-top text-danger"></i> 30. Returns, Damages & Write-offs
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-4 bg-light">
        <div class="tab-content" id="pmStoryContent">
            @foreach(['dispenser-performance' => 'st-disp-perf', 'prescription-adaptation-audit' => 'st-adapt-audit', 'ward-consumable-billing-kit' => 'st-consumable-kit', 'drug-category-dispensing' => 'st-drug-cat', 'return-damage-write-off' => 'st-pharm-loss'] as $storySlug => $paneId)
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
</div>{{-- /pm-stories --}}

</div>{{-- /pm-parent-content --}}
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

    $('#table-pharmacy-dispense').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.pharmacy-mortuary.data', 'pharmacy-dispense') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_doctor', name: 'patient.user.name' },
            { data: 'product_store', name: 'product.product_name' },
            { data: 'classification_badge', name: 'is_adapted' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-ward-direct-billing').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.pharmacy-mortuary.data', 'ward-direct-billing') }}", data: appendMultidimData },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_details', name: 'patient.user.name' },
            { data: 'product_store', name: 'product.product_name' },
            { data: 'amount_formatted', name: 'payable_amount' },
            { data: 'classification_badge', name: 'coverage_mode' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-mortuary').DataTable($.extend({}, commonDtConfig, {
        ajax: { url: "{{ route('audit.pharmacy-mortuary.data', 'mortuary') }}", data: appendMultidimData },
        columns: [
            { data: 'arrival_time', name: 'arrival_time' },
            { data: 'deceased_details', name: 'name' },
            { data: 'location', name: 'tray_number' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    // Story Data Loader
    var storyDataUrl = "{{ route('audit.pharmacy-mortuary-stories.data', '__STORY__') }}";

    function loadPmStory(paneEl) {
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

    $('#pm-stories-tab').on('shown.bs.tab', function() {
        var $activePane = $('#pmStoryContent .tab-pane.active');
        if ($activePane.length) loadPmStory($activePane[0]);
    });
    $('#pmStoryTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).data('bs-target');
        loadPmStory($(target)[0]);
    });
    $('#apply_filters_btn').on('click', function() {
        $('#pmStoryContent .tab-pane').data('loaded', 0);
        var $activePane = $('#pmStoryContent .tab-pane.active');
        if ($('#pm-stories-tab').hasClass('active') && $activePane.length) loadPmStory($activePane[0]);
    });
});
</script>
@endpush
