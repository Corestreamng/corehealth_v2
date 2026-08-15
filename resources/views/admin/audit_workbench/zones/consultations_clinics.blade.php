@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-doctor"></i> Consultations & Clinics Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'consultations-clinics', 'zoneLabel' => 'Consultations & Clinics Zone'])

{{-- PARENT LEVEL NAVIGATION --}}
<ul class="nav nav-pills mb-3" id="cc-parent-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active font-weight-bold px-4" id="cc-audit-register-tab" data-bs-toggle="pill" data-bs-target="#cc-audit-register" type="button" role="tab">
            <i class="mdi mdi-clipboard-check-outline me-1"></i> Audit Register
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link font-weight-bold px-4" id="cc-stories-tab" data-bs-toggle="pill" data-bs-target="#cc-stories" type="button" role="tab">
            <i class="mdi mdi-chart-timeline-variant me-1"></i> Consultations & Clinics Stories
        </button>
    </li>
</ul>

<div class="tab-content" id="cc-parent-content">

    {{-- TAB 1: AUDIT REGISTER --}}
    <div class="tab-pane fade show active" id="cc-audit-register" role="tabpanel">
        <div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
            <div class="card-header bg-white border-bottom p-0">
                <ul class="nav nav-tabs nav-fill audit-tabs" id="clinicsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-3 font-weight-bold" id="appointments-tab" data-bs-toggle="tab" data-bs-target="#appointments" type="button" role="tab">
                            <i class="mdi mdi-calendar-clock text-info me-1"></i> Appointments & Queue
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-3 font-weight-bold" id="encounters-tab" data-bs-toggle="tab" data-bs-target="#encounters" type="button" role="tab">
                            <i class="mdi mdi-account-details text-success me-1"></i> Clinical Encounters
                        </button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body p-4 bg-light">
                <div class="tab-content" id="clinicsTabsContent">
                    
                    {{-- Appointments Tab --}}
                    <div class="tab-pane fade show active" id="appointments" role="tabpanel">
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h6>Total Appointments</h6>
                                        <h3 class="mb-0">{{ $kpis['total_appointments'] ?? 0 }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h6>Completed</h6>
                                        <h3 class="mb-0">{{ $kpis['completed_appointments'] ?? 0 }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-dark h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h6>Pending / Waiting</h6>
                                        <h3 class="mb-0">{{ $kpis['pending_appointments'] ?? 0 }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger text-white h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h6>Cancelled / No-show</h6>
                                        <h3 class="mb-0">{{ $kpis['cancelled_appointments'] ?? 0 }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0">
                            <div class="card-body p-3">
                                <div class="table-responsive">
                                    <table id="table-appointments" class="table table-hover align-middle w-100">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Patient Details</th>
                                                <th>Clinic & Doctor</th>
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

                    {{-- Encounters Tab --}}
                    <div class="tab-pane fade" id="encounters" role="tabpanel">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="card bg-success text-white h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h6>Total Encounters</h6>
                                        <h3 class="mb-0">{{ $kpis['total_encounters'] ?? 0 }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h6>Avg Encounters/Day</h6>
                                        <h3 class="mb-0">{{ number_format($kpis['avg_encounters_per_day'] ?? 0, 1) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-primary text-white h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h6>Active Doctors</h6>
                                        <h3 class="mb-0">{{ $kpis['active_doctors'] ?? 0 }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0">
                            <div class="card-body p-3">
                                <div class="table-responsive">
                                    <table id="table-encounters" class="table table-hover align-middle w-100">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Started Date & Time</th>
                                                <th>Patient Details</th>
                                                <th>Attending Doctor</th>
                                                <th>Duration</th>
                                                <th>Outcome Status</th>
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
    </div>

    {{-- TAB 2: CONSULTATIONS & CLINICS STORIES --}}
    <div class="tab-pane fade" id="cc-stories" role="tabpanel">
        <div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
            <div class="card-header bg-white border-bottom p-0">
                <ul class="nav nav-tabs nav-fill audit-tabs" id="ccStoryTabs" role="tablist">
                    @php
                        $ccStoriesList = [
                            'appointment-completion-rate' => ['id' => 'st-app-comp', 'label' => '1. Clinic Completion Rate', 'icon' => 'mdi-check-all', 'color' => 'text-primary'],
                            'doctor-consultation-volume' => ['id' => 'st-doc-vol', 'label' => '2. Doctor Consult Workload', 'icon' => 'mdi-doctor', 'color' => 'text-success'],
                            'queue-wait-time-analysis' => ['id' => 'st-queue-wait', 'label' => '3. Queue Wait Time', 'icon' => 'mdi-clock-fast', 'color' => 'text-info'],
                            'hmo-vs-private-appointment-split' => ['id' => 'st-hmo-split', 'label' => '4. HMO vs Private Split', 'icon' => 'mdi-credit-card-outline', 'color' => 'text-warning'],
                            'encounter-duration-analysis' => ['id' => 'st-enc-dur', 'label' => '5. Encounter Duration', 'icon' => 'mdi-timer-outline', 'color' => 'text-secondary'],
                            'encounter-to-service-billing-gap' => ['id' => 'st-unbilled-gap', 'label' => '6. Unbilled Encounters Gap', 'icon' => 'mdi-alert-circle-outline', 'color' => 'text-danger'],
                            'encounter-outcome-distribution' => ['id' => 'st-enc-out', 'label' => '7. Encounter Outcomes', 'icon' => 'mdi-file-chart-outline', 'color' => 'text-dark'],
                            'daily-encounter-throughput-trend' => ['id' => 'st-daily-trend', 'label' => '8. Daily Throughput Trend', 'icon' => 'mdi-chart-timeline-variant', 'color' => 'text-purple'],
                        ];
                    @endphp
                    @foreach($ccStoriesList as $sSlug => $sMeta)
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
                <div class="tab-content" id="ccStoryContent">
                    @foreach($ccStoriesList as $sSlug => $sMeta)
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

    $('#table-appointments').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.consultations-clinics.data', 'appointments') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_details', name: 'patient.user.surname' },
            { data: 'clinic_doctor', name: 'clinic.name' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-encounters').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.consultations-clinics.data', 'encounters') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_details', name: 'patient.user.surname' },
            { data: 'doctor_details', name: 'doctor.surname' },
            { data: 'duration_badge', name: 'started_at' },
            { data: 'outcome_badge', name: 'outcome' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    // Story Data Loader
    var storyDataUrl = "{{ route('audit.consultations-clinics-stories.data', '__STORY__') }}";

    function loadCcStory(paneEl) {
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

    $('#cc-stories-tab').on('shown.bs.tab', function() {
        var $activePane = $('#ccStoryContent .tab-pane.active');
        if ($activePane.length) loadCcStory($activePane[0]);
    });
    $('#ccStoryTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).data('bs-target');
        loadCcStory($(target)[0]);
    });
    $('#apply_filters_btn').on('click', function() {
        $('#ccStoryContent .tab-pane').data('loaded', 0);
        var $activePane = $('#ccStoryContent .tab-pane.active');
        if ($('#cc-stories-tab').hasClass('active') && $activePane.length) loadCcStory($activePane[0]);
    });
});
</script>
@endpush
