@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-bed"></i> Admissions & Discharges Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'admissions-discharges', 'zoneLabel' => 'Admissions & Discharges Zone'])

{{-- PARENT LEVEL NAVIGATION --}}
<ul class="nav nav-pills mb-3" id="admissions-parent-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active font-weight-bold px-4" id="adms-audit-register-tab" data-bs-toggle="pill" data-bs-target="#adms-audit-register" type="button" role="tab">
            <i class="mdi mdi-clipboard-check-outline me-1"></i> Audit Register
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link font-weight-bold px-4" id="adms-stories-tab" data-bs-toggle="pill" data-bs-target="#adms-stories" type="button" role="tab">
            <i class="mdi mdi-chart-timeline-variant me-1"></i> Admissions & Discharges Stories
        </button>
    </li>
</ul>

<div class="tab-content" id="adms-parent-content">

    {{-- TAB 1: AUDIT REGISTER --}}
    <div class="tab-pane fade show active" id="adms-audit-register" role="tabpanel">
        <div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
            <div class="card-header bg-white border-bottom p-0">
                <ul class="nav nav-tabs nav-fill audit-tabs" id="admissionsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-3 font-weight-bold" id="admissions-tab" data-bs-toggle="tab" data-bs-target="#admissions" type="button" role="tab">
                            <i class="mdi mdi-bed text-info me-1"></i> Inpatient Admissions
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-3 font-weight-bold" id="discharges-tab" data-bs-toggle="tab" data-bs-target="#discharges" type="button" role="tab">
                            <i class="mdi mdi-exit-run text-success me-1"></i> Discharges & Clearance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-3 font-weight-bold" id="triangulation-tab" data-bs-toggle="tab" data-bs-target="#triangulation" type="button" role="tab">
                            <i class="mdi mdi-calculator-variant text-primary me-1"></i> Ward Triangulation (Admissions vs Store Requisitions vs Bills)
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4 bg-light">
                <div class="tab-content" id="admissionsTabsContent">

                    {{-- Admissions Tab --}}
                    <div class="tab-pane fade show active" id="admissions" role="tabpanel">
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h6>Total Admissions</h6>
                                        <h3 class="mb-0">{{ $kpis['total_admissions'] ?? 0 }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h6>Currently Admitted</h6>
                                        <h3 class="mb-0">{{ $kpis['currently_admitted'] ?? 0 }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-dark h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h6>Bed Occupancy Rate</h6>
                                        <h3 class="mb-0">{{ number_format($kpis['bed_occupancy_rate'] ?? 0, 1) }}%</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h6>Avg Length of Stay</h6>
                                        <h3 class="mb-0">{{ number_format($kpis['avg_length_of_stay'] ?? 0, 1) }} Days</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0">
                            <div class="card-body p-3">
                                <div class="table-responsive">
                                    <table id="table-admissions" class="table table-hover align-middle w-100">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Patient Details</th>
                                                <th>Ward & Bed Location</th>
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

                    {{-- Discharges Tab --}}
                    <div class="tab-pane fade" id="discharges" role="tabpanel">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="card bg-success text-white h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h6>Total Discharges</h6>
                                        <h3 class="mb-0">{{ $kpis['total_discharges'] ?? 0 }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h6>Pending Clearance</h6>
                                        <h3 class="mb-0">{{ $kpis['pending_clearance'] ?? 0 }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-danger text-white h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h6>Absconded / DAMA</h6>
                                        <h3 class="mb-0">{{ $kpis['absconded_dama'] ?? 0 }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0">
                            <div class="card-body p-3">
                                <div class="table-responsive">
                                    <table id="table-discharges" class="table table-hover align-middle w-100">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Discharge Date</th>
                                                <th>Patient Details</th>
                                                <th>Ward & Bed</th>
                                                <th>Length of Stay</th>
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

                    {{-- Ward Triangulation Tab --}}
                    <div class="tab-pane fade" id="triangulation" role="tabpanel">
                        <div class="alert alert-info border-0 shadow-sm mb-4">
                            <i class="mdi mdi-information-outline"></i> <strong>Ward Inpatient Triangulation:</strong> Matches admitted patients per ward against the monetary value of store requisitions fulfilled for that ward's store, and compares it against accumulated patient bills.
                        </div>

                        <div class="card shadow-sm border-0">
                            <div class="card-body p-3">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle w-100">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Ward Name</th>
                                                <th>Associated Store</th>
                                                <th>Admitted Patients</th>
                                                <th>Fulfilled Ward Requisitions (Cost)</th>
                                                <th>Accumulated Inpatient Bills</th>
                                                <th>Net Variance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($wardTriangulation as $row)
                                            <tr>
                                                <td class="font-weight-bold text-dark">{{ $row->ward->name }}</td>
                                                <td>
                                                    @if($row->store)
                                                    <span class="badge bg-light text-dark border"><i class="mdi mdi-store"></i> {{ $row->store->store_name }}</span>
                                                    @else
                                                    <span class="badge bg-secondary">No Linked Store</span>
                                                    @endif
                                                </td>
                                                <td><span class="badge bg-primary fs-6">{{ $row->admissions_count }}</span></td>
                                                <td class="text-nowrap font-weight-bold text-danger">₦{{ number_format($row->req_fulfilled_value, 2) }}</td>
                                                <td class="text-nowrap font-weight-bold text-success">₦{{ number_format($row->patient_bills_value, 2) }}</td>
                                                <td class="text-nowrap font-weight-bold {{ $row->variance >= 0 ? 'text-success' : 'text-danger' }}">
                                                    ₦{{ number_format($row->variance, 2) }}
                                                    @if($row->variance < 0)
                                                        <span class="badge bg-danger ms-1"><span class="text-white">Leakage Risk</span></span>
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
    </div>

    {{-- TAB 2: ADMISSIONS & DISCHARGES STORIES --}}
    <div class="tab-pane fade" id="adms-stories" role="tabpanel">
        <div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
            <div class="card-header bg-white border-bottom p-0">
                <ul class="nav nav-tabs nav-fill audit-tabs" id="admsStoryTabs" role="tablist">
                    @php
                        $admsStoriesList = [
                            'ward-occupancy-capacity' => ['id' => 'st-ward-occ', 'label' => '1. Ward Occupancy', 'icon' => 'mdi-bed-empty', 'color' => 'text-primary'],
                            'admission-source-priority' => ['id' => 'st-adm-prio', 'label' => '2. Priority & Triage', 'icon' => 'mdi-ambulance', 'color' => 'text-danger'],
                            'doctor-admission-volume' => ['id' => 'st-doc-adm', 'label' => '3. Doctor Admissions', 'icon' => 'mdi-doctor', 'color' => 'text-success'],
                            'admission-length-of-stay-distribution' => ['id' => 'st-los-risk', 'label' => '4. Length of Stay Risk', 'icon' => 'mdi-calendar-range', 'color' => 'text-warning'],
                            'discharge-clearance-turnaround' => ['id' => 'st-dis-clear', 'label' => '5. Discharge Clearance', 'icon' => 'mdi-check-decagram', 'color' => 'text-info'],
                            'absconded-dama-revenue-leakage' => ['id' => 'st-abs-dama', 'label' => '6. Absconded & DAMA Loss', 'icon' => 'mdi-exit-run', 'color' => 'text-danger'],
                            'readmission-rate-analysis' => ['id' => 'st-readm-rate', 'label' => '7. 30-Day Readmission', 'icon' => 'mdi-refresh', 'color' => 'text-secondary'],
                            'discharge-billing-reconciliation' => ['id' => 'st-dis-recon', 'label' => '8. Billing Reconciliation', 'icon' => 'mdi-receipt', 'color' => 'text-dark'],
                            'ward-requisition-vs-billing-variance' => ['id' => 'st-req-billing', 'label' => '9. Requisition vs Billing', 'icon' => 'mdi-calculator-variant', 'color' => 'text-primary'],
                            'ward-bed-fee-revenue-attribution' => ['id' => 'st-bed-fee', 'label' => '10. Bed Fee Revenue', 'icon' => 'mdi-currency-ngn', 'color' => 'text-success'],
                            'ward-drug-administration-audit' => ['id' => 'st-drug-admin', 'label' => '11. Drug Admin Audit', 'icon' => 'mdi-pill', 'color' => 'text-info'],
                            'ward-cost-per-patient-day' => ['id' => 'st-cost-day', 'label' => '12. Cost per Patient Day', 'icon' => 'mdi-chart-areaspline', 'color' => 'text-warning'],
                        ];
                    @endphp
                    @foreach($admsStoriesList as $sSlug => $sMeta)
                        <li class="nav-item">
                            <button class="nav-link {{ $loop->first ? 'active' : '' }} py-3 font-weight-bold" 
                                    id="{{ $sMeta['id'] }}-tab" 
                                    data-bs-toggle="tab" 
                                    data-bs-target="#{{ $sMeta['id'] }}" 
                                    type="button">
                                <i class="mdi {{ $sMeta['icon'] }} {{ $sMeta['color'] }} me-1"></i> {{ $sMeta['label'] }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
            
            <div class="card-body p-4 bg-light">
                <div class="tab-content" id="admsStoryContent">
                    @foreach($admsStoriesList as $sSlug => $sMeta)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" 
                             id="{{ $sMeta['id'] }}" 
                             role="tabpanel" 
                             data-story="{{ $sSlug }}">
                            <div class="row g-3 mb-4 story-cards"></div>
                            <div class="card shadow-sm border-0">
                                <div class="card-body p-3">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle w-100 story-table" id="table-{{ $sMeta['id'] }}">
                                            <thead class="bg-light"><tr></tr></thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
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

    $('#table-admissions').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.admissions-discharges.data', 'admissions') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_details', name: 'patient.user.surname' },
            { data: 'ward_bed', name: 'ward.name' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-discharges').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.admissions-discharges.data', 'discharges') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'discharge_date', name: 'discharge_date' },
            { data: 'patient_details', name: 'patient.user.surname' },
            { data: 'ward_bed', name: 'ward.name' },
            { data: 'stay_days', name: 'created_at' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    // Story Data Loader
    var storyDataUrl = "{{ route('audit.admissions-discharges-stories.data', '__STORY__') }}";

    function loadAdmsStory(paneEl) {
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

    $('#adms-stories-tab').on('shown.bs.tab', function() {
        var $activePane = $('#admsStoryContent .tab-pane.active');
        if ($activePane.length) loadAdmsStory($activePane[0]);
    });
    $('#admsStoryTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).data('bs-target');
        loadAdmsStory($(target)[0]);
    });
    $('#apply_filters_btn').on('click', function() {
        $('#admsStoryContent .tab-pane').data('loaded', 0);
        var $activePane = $('#admsStoryContent .tab-pane.active');
        if ($('#adms-stories-tab').hasClass('active') && $activePane.length) loadAdmsStory($activePane[0]);
    });
});
</script>
@endpush