@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-doctor"></i> Consultations & Clinics Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'consultations-clinics', 'zoneLabel' => 'Consultations & Clinics Zone'])

<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="clinicsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3 font-weight-bold" id="appointments-tab" data-bs-toggle="tab" data-bs-target="#appointments" type="button" role="tab">
                    <i class="mdi mdi-calendar-clock text-info"></i> Appointments & Queue
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="encounters-tab" data-bs-toggle="tab" data-bs-target="#encounters" type="button" role="tab">
                    <i class="mdi mdi-account-details text-success"></i> Clinical Encounters
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
});
</script>
@endpush
