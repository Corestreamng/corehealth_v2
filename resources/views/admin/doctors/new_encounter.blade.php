@extends('admin.layouts.app')
@section('title', 'New Encounter')
@if (request()->get('admission_req_id') != '')
    @section('page_name', 'Ward Round')
@else
    @section('page_name', 'Consultations')
@endif
@section('subpage_name', 'New Encounter')
@section('content')
    <style>
        /* Fix for modals inside overflow containers */
        .modal {
            position: fixed !important;
            z-index: 1055 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
        }
        .modal-dialog {
            z-index: 1056 !important;
        }
    </style>

    {{-- Emergency Triage Alert Banner --}}
    @if (isset($doctorQueue) && $doctorQueue && $doctorQueue->priority === 'emergency')
        <div class="alert alert-danger border-danger shadow-sm mb-3" role="alert">
            <div class="d-flex align-items-start">
                <div class="me-3">
                    <i class="fa fa-exclamation-triangle fa-2x text-danger"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="alert-heading mb-1">
                        <i class="fa fa-bolt"></i> Emergency Patient
                        @if ($doctorQueue->source === 'emergency_intake')
                            <span class="badge bg-danger ms-2">Via Emergency Intake</span>
                        @endif
                    </h5>
                    @if ($doctorQueue->triage_note)
                        <hr class="my-2">
                        <p class="mb-0 small" style="white-space: pre-line;">{{ $doctorQueue->triage_note }}</p>
                    @endif
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif

    <!-- Action Bar -->
    <div class="card-modern mb-3 border-0 shadow-sm">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="mdi mdi-stethoscope text-primary" style="font-size: 24px; color: {{ appsettings('hos_color', '#007bff') }} !important;"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold">Consultation Actions</h6>
                        <small class="text-muted" style="font-size: 0.85rem;">
                            Manage patient admission status or finalize this consultation session.
                        </small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if (isset($admission_request))
                        <!-- Patient has admission request - show status and discharge button -->
                        <div class="d-flex align-items-center me-2 px-3 py-1 bg-light rounded border">
                            <i class="fa fa-bed me-2" style="color: {{ appsettings('hos_color', '#007bff') }};"></i>
                            <div class="d-flex flex-column lh-1">
                                @if($admission_request->admission_status === 'discharge_requested')
                                    <span class="fw-bold text-warning" style="font-size: 0.8rem;">Discharge Requested</span>
                                    <small class="text-muted" style="font-size: 0.7rem;">Awaiting Nursing</small>
                                @elseif($admission_request->admission_status === 'pending_checklist')
                                    <span class="fw-bold text-info" style="font-size: 0.8rem;">Admission Requested</span>
                                    <small class="text-muted" style="font-size: 0.7rem;">Pending Checklist</small>
                                @elseif($admission_request->discharged)
                                    <span class="fw-bold text-secondary" style="font-size: 0.8rem;">Discharged</span>
                                    <small class="text-muted" style="font-size: 0.7rem;">{{ $admission_request->discharge_date ? date('M j, Y', strtotime($admission_request->discharge_date)) : '' }}</small>
                                @else
                                    <span class="fw-bold text-dark" style="font-size: 0.8rem;">Admitted</span>
                                    <small class="text-muted" style="font-size: 0.7rem;">
                                        {{ $admission_request->bed ? $admission_request->bed->name : 'Pending Bed' }}
                                    </small>
                                @endif
                            </div>
                        </div>
                        @if(!$admission_request->discharged && $admission_request->admission_status !== 'discharge_requested')
                        <button type="button" class="btn btn-warning btn-sm d-flex align-items-center shadow-sm" onclick="openDischargeModal()">
                            <i class="fa fa-sign-out-alt me-2"></i> Request Discharge
                        </button>
                        @endif
                    @else
                        <!-- Patient not admitted - show admit button -->
                        <button type="button" class="btn btn-info btn-sm text-white d-flex align-items-center shadow-sm" onclick="openAdmitModal()">
                            <i class="fa fa-bed me-2"></i> Request Admission
                        </button>
                    @endif

                    <button type="button" class="btn btn-outline-dark btn-sm d-flex align-items-center shadow-sm" onclick="openReportBuilder()">
                        <i class="mdi mdi-file-document me-1"></i> Medical Report
                    </button>

                    <div class="vr mx-2 text-muted"></div>

                    <button type="button" class="btn btn-sm text-white d-flex align-items-center shadow-sm" style="background-color: {{ appsettings('hos_color', '#007bff') }};" onclick="$('#concludeEncounterModal').modal('show')">
                        <i class="fa fa-check-circle me-2"></i> Conclude Encounter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="patient_data_tab" data-bs-toggle="tab" data-bs-target="#patient_data"
                type="button" role="tab" aria-controls="patient_data" aria-selected="true">
                <i class="fa fa-user me-1"></i> Patient Data
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="vitals_data_tab" data-bs-toggle="tab" data-bs-target="#vitals" type="button"
                role="tab" aria-controls="vitals_data" aria-selected="false">
                <i class="fa fa-heartbeat me-1"></i> Vitals/ Allergies
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="nurse_charts_tab" data-bs-toggle="tab" data-bs-target="#nurse_charts"
                type="button" role="tab" aria-controls="nurse_charts" aria-selected="false">
                <i class="fa fa-notes-medical me-1"></i> Nurse Charts
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="inj_imm_history_tab" data-bs-toggle="tab" data-bs-target="#inj_imm_history"
                type="button" role="tab" aria-controls="inj_imm_history" aria-selected="false">
                <i class="fa fa-syringe me-1"></i> Inj/Imm History
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="clinical_notes_tab" data-bs-toggle="tab" data-bs-target="#clinical_notes"
                type="button" role="tab" aria-controls="clinical_notes" aria-selected="false">
                <i class="mdi mdi-note-text me-1"></i> Clinical Notes/Diagnosis
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="laboratory_services_tab" data-bs-toggle="tab" data-bs-target="#laboratory_services"
                type="button" role="tab" aria-controls="laboratory_services" aria-selected="false">
                <i class="fa fa-flask me-1"></i> Laboratory Services
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="imaging_services_tab" data-bs-toggle="tab" data-bs-target="#imaging_services"
                type="button" role="tab" aria-controls="imaging_services" aria-selected="false">
                <i class="fa fa-x-ray me-1"></i> Imaging Services
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="medications_tab" data-bs-toggle="tab" data-bs-target="#medications"
                type="button" role="tab" aria-controls="medications" aria-selected="false">
                <i class="fa fa-pills me-1"></i> Medications
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="procedures_tab" data-bs-toggle="tab" data-bs-target="#procedures"
                type="button" role="tab" aria-controls="procedures" aria-selected="false">
                <i class="fa fa-user-md me-1"></i> Procedures
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="admissions_tab" data-bs-toggle="tab" data-bs-target="#admissions"
                type="button" role="tab" aria-controls="admissions" aria-selected="false">
                <i class="fa fa-bed me-1"></i> Admission History
            </button>
        </li>
    </ul>
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="patient_data" role="tabpanel" aria-labelledby="patient_data">
            <div class="card-modern mt-2">
                <div class="card-body table-responsive">
                    <div class="row">
                        <div class="col-md-9">
                            <h1>{{ userfullname($patient->user->id) }}</h1>
                            <h3>File No: {{ $patient->file_no }}</h3>
                            @if ($patient->user->old_records)
                                <div class="form-group">
                                    <a href="{!! url('storage/image/user/old_records/' . $patient->user->old_records) !!}" target="_blank"><i class="fa fa-file"></i> Old
                                        Records</a>
                                    <br>
                                </div>
                            @else
                                <div class="form-group">
                                    <a href="#"><i class="fa fa-file"></i> No Old Records Attached</a>
                                    <br>
                                </div>
                            @endif
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <img src="{!! url('storage/image/user/' . $patient->user->filename) !!}" valign="middle" width="150px" height="120px" />
                                <br>
                            </div>
                        </div>
                    </div>
                    <br>
                    <hr>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped">
                            <tbody>
                                <tr>
                                    <th>Gender: </th>
                                    <td>{{ $patient->gender ?? 'N/A' }}</td>
                                    <th>D.O.B:</th>
                                    <td>{{ $patient->dob ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Blood Group:</th>
                                    <td>{{ $patient->blood_group ?? 'N/A' }}</td>
                                    <th>Genotype :</th>
                                    <td>{{ $patient->genotype ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Nationality: </th>
                                    <td>{{ $patient->nationality ?? 'N/A' }}</td>
                                    <th>Ethnicity:</th>
                                    <td>{{ $patient->ethnicity ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Address: </th>
                                    <td>{{ $patient->address ?? 'N/A' }}</td>
                                    <th>Other info:</th>
                                    <td>{{ $patient->misc ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Insurance/HMO: </th>
                                    <td>{{ $patient->hmo->name ?? 'N/A' }}</td>
                                    <th>HMO No:</th>
                                    <td>{{ $patient->hmo_no ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Disability status:</th>
                                    <td>{{ $patient->disability == 1 ? 'Disabled' : 'None' }}</td>
                                    <th>Phone: </th>
                                    <td>{{ $patient->phone_no }}</td>
                                </tr>
                                <tr>
                                    <th>Next Of Kin: </th>
                                    <td>{{ $patient->next_of_kin_name ?? 'N/A' }}</td>
                                    <th>Other next of kin info:</th>
                                    <td>
                                        Phone : {{ $patient->next_of_kin_phone ?? 'N/A' }} <br>
                                        Address : {{ $patient->next_of_kin_address ?? 'N/A' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @if (isset($admission_request))
                        <hr>
                        <h4>Admission Info
                            @if($admission_request->admission_status === 'discharge_requested')
                                <span class="badge bg-warning text-dark">Discharge Requested</span>
                            @elseif($admission_request->discharged)
                                <span class="badge bg-secondary">Discharged</span>
                            @else
                                <button type="button" class="btn btn-warning btn-sm" onclick="openDischargeModal()">
                                    <i class="fa fa-sign-out-alt"></i> Request Discharge
                                </button>
                            @endif
                        </h4>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <tr>
                                    <th>Requested By</th>
                                    <td>{{ userfullname($admission_request->doctor_id) }}</td>
                                    <th>Requested On</th>
                                    <td>{{ date('h:i a D M j, Y', strtotime($admission_request->created_at)) }}</td>
                                </tr>
                                <tr>
                                    <th>Bed</th>
                                    <td>{{ $admission_request->bed ? $admission_request->bed->name : 'Pending Assignment' }}</td>
                                    <th>Ward</th>
                                    <td>{{ $admission_request->bed ? $admission_request->bed->ward : 'N/A' }},
                                        <b>Unit:</b>
                                        {{ $admission_request->bed ? $admission_request->bed->unit : 'N/A' }}
                                    </td>
                                </tr>
                                @if($admission_request->admission_status)
                                <tr>
                                    <th>Status</th>
                                    <td colspan="3">
                                        @switch($admission_request->admission_status)
                                            @case('pending_checklist')
                                                <span class="badge bg-info">Pending Admission Checklist</span>
                                                @break
                                            @case('checklist_complete')
                                                <span class="badge bg-primary">Checklist Complete - Awaiting Bed</span>
                                                @break
                                            @case('admitted')
                                                <span class="badge bg-success">Admitted</span>
                                                @break
                                            @case('discharge_requested')
                                                <span class="badge bg-warning text-dark">Discharge Requested - Awaiting Nursing</span>
                                                @break
                                            @case('discharge_checklist')
                                                <span class="badge bg-warning text-dark">Discharge Checklist In Progress</span>
                                                @break
                                            @case('discharged')
                                                <span class="badge bg-secondary">Discharged</span>
                                                @break
                                            @default
                                                <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $admission_request->admission_status)) }}</span>
                                        @endswitch
                                    </td>
                                </tr>
                                @endif
                            </table>

                        </div>
                        <hr>
                    @endif
                    <br><button type="button" onclick="switch_tab(event,'vitals_data_tab')"
                        class="btn btn-primary mr-2">Next</button>
                    <a href="{{ route('encounters.index') }}"
                        onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                        class="btn btn-light">Exit</a>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="vitals" role="tabpanel" aria-labelledby="vitals_tab">
            <div class="mt-2">
                @include('admin.partials.unified_vitals', ['patient' => $patient])
            </div>
            <div class="card-modern mt-2 border-0">
                 <div class="card-body px-0">
                    <button type="button" onclick="switch_tab(event,'patient_data_tab')"
                        class="btn btn-secondary mr-2">
                        Prev
                    </button>
                    <button type="button" onclick="switch_tab(event,'nursing_notes_tab')"
                        class="btn btn-primary mr-2">Next</button>
                    <a href="{{ route('encounters.index') }}"
                        onclick="return confirm('Are you sure you wish to exit? Changes are yet to be saved')"
                        class="btn btn-light">Exit</a>
                 </div>
            </div>
        </div>
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var vitalsTab = document.getElementById('vitals_data_tab');
                if(vitalsTab){
                     // Bootstrap 5
                    vitalsTab.addEventListener('shown.bs.tab', function (event) {
                        if(window.initUnifiedVitals) {
                            window.initUnifiedVitals({{ $patient->id }});
                        }
                    });
                    // Fallback/Others
                    $(vitalsTab).on('shown.bs.tab', function (e) {
                         if(window.initUnifiedVitals) {
                            window.initUnifiedVitals({{ $patient->id }});
                        }
                    });
                }
            });
        </script>
        @endpush
        <div class="tab-pane fade" id="laboratory_services" role="tabpanel" aria-labelledby="laboratory_services_tab">
            @include('admin.doctors.partials.laboratory_services')
        </div>

        {{-- Imaging Services: Combined Imaging History + Imaging Request --}}
        <div class="tab-pane fade" id="imaging_services" role="tabpanel" aria-labelledby="imaging_services_tab">
            @include('admin.doctors.partials.imaging_services')
        </div>

        {{-- Medications: Combined Prescription History + New Prescription --}}
        <div class="tab-pane fade" id="medications" role="tabpanel" aria-labelledby="medications_tab">
            @include('admin.doctors.partials.medications')
        </div>

        {{-- Procedures Tab --}}
        <div class="tab-pane fade" id="procedures" role="tabpanel" aria-labelledby="procedures_tab">
            @include('admin.doctors.partials.procedures')
        </div>

        {{-- Admission History --}}
        <div class="tab-pane fade" id="admissions" role="tabpanel" aria-labelledby="admissions_tab">
            <div class="card-modern mt-2">
                <div class="card-body">
                    @include('admin.patients.partials.admissions')
                </div>
            </div>
        </div>

        {{-- Clinical Notes/Diagnosis: Combined History + New Entry --}}
        <div class="tab-pane fade" id="clinical_notes" role="tabpanel" aria-labelledby="clinical_notes_tab">
            @include('admin.doctors.partials.clinical_notes')
        </div>
        <div class="tab-pane fade" id="nurse_charts" role="tabpanel" aria-labelledby="nurse_charts_tab">
            <div class="card-modern mt-2">
                <div class="card-body">
                    <!-- Date Range Filter -->
                    <div class="card-modern mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="mdi mdi-calendar-range me-1"></i> Date Range Filter</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="chart-date-from" class="form-label">From Date</label>
                                    <input type="date" class="form-control" id="chart-date-from">
                                </div>
                                <div class="col-md-4">
                                    <label for="chart-date-to" class="form-label">To Date</label>
                                    <input type="date" class="form-control" id="chart-date-to">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="button" id="apply-date-filter" class="btn btn-primary me-2">
                                        <i class="mdi mdi-filter"></i> Apply Filter
                                    </button>
                                    <button type="button" id="reset-date-filter" class="btn btn-outline-secondary">
                                        <i class="mdi mdi-refresh"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Stats -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="text-muted mb-0">
                                <i class="mdi mdi-calendar me-1"></i>
                                <span id="date-range-summary">Showing data from the last 30 days</span>
                            </h5>
                        </div>
                        <div id="data-summary-stats" class="d-flex gap-3">
                            <!-- Will be populated with JavaScript -->
                        </div>
                    </div>

                    <!-- Tabs for Medication, Intake/Output Charts, and Nursing Notes -->
                    <ul class="nav nav-tabs mb-3" id="nurseChartTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="medication-chart-tab" data-toggle="tab" href="#medication-chart"
                               role="tab" aria-controls="medication-chart" aria-selected="true">
                               <i class="mdi mdi-pill me-1"></i> Medication Chart
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="fluid-io-chart-tab" data-toggle="tab" href="#fluid-io-chart"
                               role="tab" aria-controls="fluid-io-chart" aria-selected="false">
                               <i class="mdi mdi-water me-1"></i> Fluid Intake/Output
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="solid-io-chart-tab" data-toggle="tab" href="#solid-io-chart"
                               role="tab" aria-controls="solid-io-chart" aria-selected="false">
                               <i class="mdi mdi-food-apple me-1"></i> Solid Intake/Output
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="nursing-notes-history-tab" data-toggle="tab" href="#nursing-notes-history"
                               role="tab" aria-controls="nursing-notes-history" aria-selected="false">
                               <i class="mdi mdi-notebook me-1"></i> Nursing Notes History
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="nurseChartTabContent">
                        <!-- Medication Chart Tab -->
                        <div class="tab-pane fade show active" id="medication-chart" role="tabpanel" aria-labelledby="medication-chart-tab">
                            <div class="medication-chart-container">
                                <div id="medication-chart-content" class="mt-3">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2">Loading medication chart...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fluid I/O Chart Tab -->
                        <div class="tab-pane fade" id="fluid-io-chart" role="tabpanel" aria-labelledby="fluid-io-chart-tab">
                            <div class="fluid-io-chart-container">
                                <div id="fluid-io-chart-content" class="mt-3">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2">Loading fluid intake/output chart...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Solid I/O Chart Tab -->
                        <div class="tab-pane fade" id="solid-io-chart" role="tabpanel" aria-labelledby="solid-io-chart-tab">
                            <div class="solid-io-chart-container">
                                <div id="solid-io-chart-content" class="mt-3">
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2">Loading solid intake/output chart...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Nursing Notes History Tab -->
                        <div class="tab-pane fade" id="nursing-notes-history" role="tabpanel" aria-labelledby="nursing-notes-history-tab">
                            <div class="alert alert-info">
                                <i class="mdi mdi-information-outline me-2"></i>
                                View nursing notes history for the patient.
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped" style="width: 100%" id="nurse_note_hist_5">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Note type</th>
                                            <th>Details</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="button" onclick="switch_tab(event,'nursing_notes_tab')" class="btn btn-secondary me-2">
                            <i class="mdi mdi-arrow-left me-1"></i> Previous
                        </button>
                        <button type="button" onclick="switch_tab(event,'inj_imm_history_tab')" class="btn btn-primary">
                            Next <i class="mdi mdi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        {{-- Injection & Immunization History Tab --}}
        <div class="tab-pane fade" id="inj_imm_history" role="tabpanel" aria-labelledby="inj_imm_history_tab">
            <div class="card-modern mt-2">
                <div class="card-body">
                    @include('admin.patients.partials.injection_immunization_history', ['patient' => $patient])

                    <div class="mt-4">
                        <button type="button" onclick="switch_tab(event,'nurse_charts_tab')" class="btn btn-secondary me-2">
                            <i class="mdi mdi-arrow-left me-1"></i> Previous
                        </button>
                        <button type="button" onclick="switch_tab(event,'clinical_notes_tab')" class="btn btn-primary">
                            Next <i class="mdi mdi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <form action="{{ route('encounters.store') }}" method="post">
            @csrf
            <div class="tab-pane fade d-none" id="my_notes_old" role="tabpanel" aria-labelledby="my_notes_old_tab">
                <div class="card-modern mt-2">
                    <div class="card-body table-responsive">

                        <input type="hidden" value="{{ $req_entry->service_id ?? 'ward_round' }}"
                            name="req_entry_service_id" required>
                        <input type="hidden" value="{{ $req_entry->id ?? 'ward_round' }}" name="req_entry_id">
                        <input type="hidden" value="{{ request()->get('patient_id') }}" name="patient_id"
                            id="encounter_patient_id__">
                        <input type="hidden" value="{{ request()->get('queue_id') ?? 'ward_round' }}" name="queue_id">
                        <input type="hidden" id="encounter_id__" name="encounter_id" value="{{ $encounter->id }}"
                            required>
                        @if (request()->get('admission_req_id') != '')
                            <input type="hidden" value="{{ request()->get('admission_req_id') }}" name="queue_id">
                        @endif
                        <div class="form-group">
                            <div class="container">
                                <div class="accordion" id="accordionForProfile">
                                    <div class="accordion-item">
                                        <h4 class="accordion-header" id="flush-headingOne">
                                            <span class="collapsed" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#flush-collapseOne" aria-expanded="false"
                                                aria-controls="flush-collapseOne">
                                                <span class="fa fa-eye"></span>
                                                See Patient Profiles</span>
                                            <span class="fa fa-caret-down"></span>
                                        </h4>
                                        <div id="flush-collapseOne" class="accordion-collapse collapse"
                                            aria-labelledby="flush-headingOne" data-bs-parent="#accordionForProfile">
                                            <div class="accordion-body">
                                                <div class="d-flex justify-content-between">
                                                    <h5>Forms/Profiles</h5>
                                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                                        data-bs-target="#profileModal"> <span class="fa fa-plus"></span>
                                                        Fill New patient Profile
                                                    </button>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table" id="profile_forms_table" style="width: 100%">
                                                        <thead>
                                                            <th>#</th>
                                                            <th>Form Data</th>
                                                        </thead>
                                                        <tbody></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- {!!generateForm($formdata)!!} --}}

                            </div>
                        </div>
                        <hr>
                        @include('admin.doctors.partials.clinical_notes')
            {{-- Conclusion tab removed - now handled by modal --}}
                <style>
                    /* Custom Toggle Switch Styles */
                    .toggle-switch {
                        position: relative;
                        display: inline-block;
                        width: 60px;
                        height: 34px;
                    }

                    .toggle-switch input {
                        opacity: 0;
                        width: 0;
                        height: 0;
                    }

                    .toggle-slider {
                        position: absolute;
                        cursor: pointer;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background-color: #ccc;
                        transition: .4s;
                        border-radius: 34px;
                    }

                    .toggle-slider:before {
                        position: absolute;
                        content: "";
                        height: 26px;
                        width: 26px;
                        left: 4px;
                        bottom: 4px;
                        background-color: white;
                        transition: .4s;
                        border-radius: 50%;
                    }

                    input:checked + .toggle-slider {
                        background-color: {{ appsettings('hos_color', '#007bff') }};
                    }

                    input:focus + .toggle-slider {
                        box-shadow: 0 0 1px {{ appsettings('hos_color', '#007bff') }};
                    }

                    input:checked + .toggle-slider:before {
                        transform: translateX(26px);
                    }
                </style>
                {{-- Old conclusion tab content removed - now using modal instead --}}
        </form>
    </div>

    <!-- Medication Details Modal (Read-Only for Doctors) -->
    <div class="modal fade" id="medDetailsModal" tabindex="-1" aria-labelledby="medDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="medDetailsModalLabel">
                        <i class="mdi mdi-pill me-2"></i>Medication Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="medDetailsModalBody">
                    <!-- Content populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!--Profile / Form  Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">Fill Profile / Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">x</button>
                </div>
                <div class="modal-body">
                    <div class="card-modern">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-10">
                                    <label for="form_type">Form Type</label>
                                    <select id="form_type" class="form-control">
                                        <option value="">--select form--</option>
                                        <option value="test">Test</option>
                                        <option value="anc">ANC</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button onclick="getForm()" class="btn btn-primary mt-4">Get Form</button>
                                </div>
                            </div>
                            <hr>
                            <form action="{{ route('patient-form.store') }}" method="post" id="patient_form_form">


                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="nursingNoteModal" tabindex="-1" role="dialog" aria-labelledby="nursingNoteModal"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="investResModalLabel">Nursing Note Result (<span
                            id="note_type_name_"></span>)</h5>
                    <button type="button" class="close"  data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="nursing_note_template_" class="table-reponsive" style="border: 1px solid black;">

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
                </div>
                </form>
            </div>
        </div>
    </div>

@endsection
@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.min.js') }}" defer></script>
    <script src="{{ asset('plugins/ckeditor/ckeditor5/ckeditor.js') }}"></script>

    <style>
        /* Modern Toggle Switch Styling */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 30px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        .toggle-switch input:checked + .toggle-slider {
            background-color: #28a745;
        }

        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }

        .toggle-switch input:focus + .toggle-slider {
            box-shadow: 0 0 1px #28a745;
        }

        .diagnosis-fields-wrapper {
            overflow: hidden;
            transition: max-height 0.5s ease, opacity 0.5s ease;
        }

        .diagnosis-fields-wrapper.hidden {
            max-height: 0 !important;
            opacity: 0;
        }

        .diagnosis-toggle-container {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Diagnosis Search Styles */
        .diagnosis-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            margin: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }

        .diagnosis-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .diagnosis-badge .remove-btn {
            cursor: pointer;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-size: 12px;
        }

        .diagnosis-badge .remove-btn:hover {
            background: rgba(255,255,255,0.5);
        }

        #reasons_search_results .list-group-item {
            cursor: pointer;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        #reasons_search_results .list-group-item:hover {
            background: #f8f9fa;
            border-left-color: #667eea;
            padding-left: 18px;
        }

        #reasons_search_results .reason-code {
            font-weight: 600;
            color: #667eea;
            margin-right: 8px;
        }

        #reasons_search_results .reason-name {
            color: #333;
        }

        #reasons_search_results .reason-category {
            font-size: 11px;
            color: #6c757d;
            margin-top: 2px;
        }
    </style>

    <script>
        $(function() {
            // Diagnosis search and display is handled by the clinical_notes partial
            // which provides: addReason(), removeReason(), updateSelectedReasonsDisplay()
            // using clinicalSelectedReasons[] and table-based per-diagnosis display

            // Make removeReason accessible globally (clinical_notes partial defines it globally already)
            window.removeReasonByValue = function(value) {
                if (typeof removeReason === 'function') removeReason(value);
            };

            // AJAX search for reasons is handled by clinical_notes partial
            // which binds to #reasons_for_encounter_search and uses addReason(value, display, code, name)

            // Initialize Select2 for edit modal diagnosis dropdown (keep for edit modal)
            @if(appsettings('requirediagnosis', 0))
            $("#editEncounterReasons").select2({
                dropdownParent: $('#editEncounterModal'),
                placeholder: 'Select diagnosis codes',
                allowClear: true,
                tags: true
            });
            @endif

            // Handle Diagnosis Applicable Toggle for New Encounter Form
            $('#diagnosisApplicable').on('change', function() {
                const isChecked = $(this).is(':checked');
                console.log('New Encounter - Diagnosis Applicable toggle changed:', isChecked);

                const $diagnosisFields = $('#diagnosisFields');

                if (isChecked) {
                    // Diagnosis IS applicable - show fields with animation
                    $diagnosisFields.removeClass('hidden collapsed');
                    $diagnosisFields.attr('style', ''); // Remove inline style
                    $diagnosisFields.css({
                        'display': 'block',
                        'opacity': '1'
                    });

                    // Clear NA values if present
                    if ($('#reasons_for_encounter_comment_1').val() === 'NA') {
                        $('#reasons_for_encounter_comment_1').val('');
                    }
                    if ($('#reasons_for_encounter_comment_2').val() === 'NA') {
                        $('#reasons_for_encounter_comment_2').val('');
                    }
                } else {
                    // Diagnosis is NOT applicable - hide fields with animation
                    $diagnosisFields.css('opacity', '0');
                    setTimeout(function() {
                        $diagnosisFields.addClass('collapsed');
                    }, 300); // Wait for animation to complete

                    // Set values to NA/null
                    $('#reasons_for_encounter').val(null).trigger('change');
                    $('#reasons_for_encounter_comment_1').val('NA');
                    $('#reasons_for_encounter_comment_2').val('NA');
                }
            });

            // Set initial max-height for animation
            setTimeout(function() {
                const $diagnosisFields = $('#diagnosisFields');
                if ($diagnosisFields.length) {
                    $diagnosisFields.css('max-height', $diagnosisFields[0].scrollHeight + 'px');
                }
            }, 100);
        });
    </script>
    <script>
        function getForm() {
            let form_ = $('#patient_form_form');
            let form_id = $('#form_type').val();
            let encounter_id = $('#encounter_id__').val();
            let patient_id = $('#encounter_patient_id__').val();
            if (form_id != '') {
                getformreq = $.ajax({
                    type: 'GET',
                    url: "{{ route('patient-form.create') }}",
                    data: {
                        form_id: form_id,
                    },
                    success: function(data) {
                        console.log(data);
                        form_.html(data.formdata);
                        mar = `
                            <input type="hidden" name="form_id" value="${data.form_id}">
                            <input type="hidden" name="patient_id" value="${patient_id}">
                            <input type="hidden" name="encounter_id" value="${encounter_id}">
                            <button type="submit" class="btn btn-primary">Save</button>
                        `;
                        form_.append(mar);
                        form_.append(`{{ csrf_field() }}`);
                    },
                    error: function(x, y, z) {
                        console.log(x, y, z);
                        form_.html('Sorry, Failed to obtain form, please try again later');
                    }
                });
            }
        }
    </script>
    <script>
        // ClassicEditor
        //     .create(document.querySelector('.classic-editor'), {
        //         toolbar: {
        //             items: [
        //                 'undo', 'redo',
        //                 '|', 'heading',
        //                 '|', 'bold', 'italic',
        //                 '|', 'link', 'uploadImage', 'insertTable', 'mediaEmbed',
        //                 '|', 'bulletedList', 'numberedList', 'outdent', 'indent',
        //             ]
        //         },
        //         cloudServices: {
        //             // All predefined builds include the Easy Image feature.
        //             // Provide correct configuration values to use it.
        //             // tokenUrl: 'https://example.com/cs-token-endpoint',
        //             // uploadUrl: 'https://your-organization-id.cke-cs.com/easyimage/upload/'
        //             // Read more about Easy Image - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/easy-image.html.
        //             // For other image upload methods see the guide - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/image-upload.html.
        //         }
        //     })
        //     .then(editor => {
        //         window.editor = editor;
        //     })
        //     .catch(err => {
        //         console.error(err);
        //     });

        ClassicEditor
            .create(document.querySelector('.classic-editor2'), {
                toolbar: {
                    items: [
                        'undo', 'redo',
                        '|', 'heading',
                        '|', 'bold', 'italic',
                        '|', 'link', 'uploadImage', 'insertTable', 'mediaEmbed',
                        '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                    ]
                },
                cloudServices: {
                    // All predefined builds include the Easy Image feature.
                    // Provide correct configuration values to use it.
                    // tokenUrl: 'https://example.com/cs-token-endpoint',
                    // uploadUrl: 'https://your-organization-id.cke-cs.com/easyimage/upload/'
                    // Read more about Easy Image - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/easy-image.html.
                    // For other image upload methods see the guide - https://ckeditor.com/docs/ckeditor5/latest/features/images/image-upload/image-upload.html.
                }
            })
            .then(editor => {
                window.editor = editor;
            })
            .catch(err => {
                console.error(err);
            });
    </script>
    <script>
        function autosavenotes() {
            console.log('...');
            // Use the specific CKEditor instance for clinical notes (not the report builder editor)
            let notes = '';
            if (window.editor && typeof window.editor.getData === 'function') {
                notes = window.editor.getData();
            } else {
                notes = $('#doctor_diagnosis_text').val();
            }
            let encounter_id = $('#encounter_id__').val();
            let autosavesatustext = $('#autosave_status_text');
            let diagnosisData = $('#reasons_for_encounter_data').val() || '[]';
            let hasDiagnosis = diagnosisData && diagnosisData !== '[]';
            let hasNotes = notes && notes.trim() !== '' && notes.trim() !== '<p>&nbsp;</p>';
            if (hasNotes || hasDiagnosis) {
                autosavesatustext.html('<i class="mdi mdi-floppy"></i> <i class="fa fa-spinner fa-spin"></i> Autosaving...');
                autosavereq = $.ajax({
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: "{{ route('auto-save-encounter-note') }}",
                    data: {
                        patient_id: "{{ request()->get('patient_id') }}",
                        notes: notes,
                        encounter_id: encounter_id,
                        reasons_for_encounter_data: $('#reasons_for_encounter_data').val() || '[]'
                    },
                    success: function(data) {
                        console.log(data);
                        autosavesatustext.html('<i class="mdi mdi-floppy"></i> <i class="mdi mdi-cloud-check-outline text-success"></i> Autosaved')

                    },
                    error: function(x, y, z) {
                        console.log(x, y, z);
                        autosavesatustext.html('<i class="mdi mdi-floppy"></i> <i class="mdi mdi-cloud-alert text-danger"></i> Autosave failed')
                    }
                });
            }
        }

        setInterval(autosavenotes, 10000);
    </script>
    <script>
        $(function() {
            $('#profile_forms_table').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 5,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('patient-form-list', request()->get('patient_id')) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "form_data",
                        name: "form_data"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#encounter_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('EncounterHistoryList', request()->get('patient_id')) }}",
                    "type": "GET",
                    "data": function(d) {
                        d.exclude_encounter_id = {{ $encounter->id ?? 'null' }};
                    }
                },
                "columns": [
                    {
                        data: "info",
                        name: "info",
                        orderable: false
                    }
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#investigation_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('investigationHistoryList', request()->get('patient_id')) }}",
                    "type": "GET"
                },
                "columns": [
                    {
                        data: "info",
                        name: "info",
                        orderable: false
                    }
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#imaging_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('imagingHistoryList', request()->get('patient_id')) }}",
                    "type": "GET"
                },
                "columns": [
                    {
                        data: "info",
                        name: "info",
                        orderable: false
                    }
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#presc_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('prescHistoryList', request()->get('patient_id')) }}",
                    "type": "GET"
                },
                "columns": [
                    {
                        data: "info",
                        name: "info",
                        orderable: false
                    }
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#scheduled_consult_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientsList') }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "fullname",
                        name: "fullname"
                    },
                    {
                        data: "file_no",
                        name: "file_no"
                    },
                    {
                        data: "hmo_id",
                        name: "hmo_id"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                    {
                        data: "view",
                        name: "view"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        // ─── Dose Mode State ───
        let doseStructuredMode = false;

        function toggleDoseMode(isStructured) {
            doseStructuredMode = isStructured;
            // Convert existing rows
            $('#selected-products tr').each(function() {
                const $td = $(this).find('td:eq(2)');
                const existingVal = $td.find('input[name="consult_presc_dose[]"]').val() || '';
                const hiddenInput = $td.find('input[type="hidden"]').prop('outerHTML') || '';
                if (isStructured) {
                    $td.html(buildStructuredDoseHtml(existingVal) + hiddenInput);
                } else {
                    // Collapse structured to free text
                    const collapsed = collapseStructuredDose($td);
                    $td.html(`<input type='text' class='form-control' name='consult_presc_dose[]' value='${collapsed}' required>` + hiddenInput);
                }
            });
        }

        function buildStructuredDoseHtml(existingVal) {
            return `
                <div class="structured-dose">
                    <div class="row g-1 mb-1">
                        <div class="col-4">
                            <input type="number" class="form-control form-control-sm dose-amount" placeholder="Amt" min="0" step="0.01" onchange="updateStructuredDoseValue(this)">
                        </div>
                        <div class="col-4">
                            <select class="form-select form-select-sm dose-unit" onchange="updateStructuredDoseValue(this)">
                                <option value="mg">mg</option>
                                <option value="g">g</option>
                                <option value="ml">ml</option>
                                <option value="IU">IU</option>
                                <option value="mcg">mcg</option>
                                <option value="units">units</option>
                                <option value="drops">drops</option>
                                <option value="puffs">puffs</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <select class="form-select form-select-sm dose-route" onchange="updateStructuredDoseValue(this)">
                                <option value="PO">PO (Oral)</option>
                                <option value="IV">IV</option>
                                <option value="IM">IM</option>
                                <option value="SC">SC</option>
                                <option value="SL">SL</option>
                                <option value="PR">PR</option>
                                <option value="INH">INH (Inhaled)</option>
                                <option value="TOP">Topical</option>
                                <option value="OPTH">Ophthalmic</option>
                                <option value="OT">Otic</option>
                                <option value="NGT">NGT</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-1">
                        <div class="col-4">
                            <select class="form-select form-select-sm dose-frequency" onchange="updateStructuredDoseValue(this)">
                                <option value="OD">OD (once daily)</option>
                                <option value="BD">BD (twice daily)</option>
                                <option value="TDS">TDS (3x daily)</option>
                                <option value="QID">QID (4x daily)</option>
                                <option value="Q4H">Q4H</option>
                                <option value="Q6H">Q6H</option>
                                <option value="Q8H">Q8H</option>
                                <option value="Q12H">Q12H</option>
                                <option value="PRN">PRN (as needed)</option>
                                <option value="STAT">STAT (once)</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control dose-duration" placeholder="Dur" min="1" value="5" onchange="updateStructuredDoseValue(this)">
                                <select class="form-select dose-duration-unit" style="max-width:70px;" onchange="updateStructuredDoseValue(this)">
                                    <option value="days">d</option>
                                    <option value="weeks">w</option>
                                    <option value="months">m</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text" style="font-size:0.75em;">Qty</span>
                                <input type="number" class="form-control dose-qty" placeholder="Qty" min="1">
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="consult_presc_dose[]" class="structured-dose-value" value="">
                </div>`;
        }

        // Frequency multiplier map for auto-Qty calculation
        const freqMultiplierMap = { 'OD': 1, 'BD': 2, 'TDS': 3, 'QID': 4, 'Q4H': 6, 'Q6H': 4, 'Q8H': 3, 'Q12H': 2, 'PRN': 1, 'STAT': 1 };
        const durUnitMultiplierMap = { 'days': 1, 'weeks': 7, 'months': 30 };

        function autoCalculateQty($row) {
            const freq = $row.find('.dose-frequency').val() || 'OD';
            const dur = parseFloat($row.find('.dose-duration').val()) || 0;
            const durUnit = $row.find('.dose-duration-unit').val() || 'days';
            if (dur > 0 && freq !== 'PRN') {
                const totalDays = dur * (durUnitMultiplierMap[durUnit] || 1);
                const perDay = freqMultiplierMap[freq] || 1;
                const qty = Math.ceil(totalDays * perDay);
                $row.find('.dose-qty').val(qty);
            }
        }

        function updateStructuredDoseValue(el) {
            const $row = $(el).closest('.structured-dose');
            // Auto-calculate Qty when relevant fields change
            autoCalculateQty($row);

            const amount = $row.find('.dose-amount').val() || '';
            const unit = $row.find('.dose-unit').val() || '';
            const route = $row.find('.dose-route').val() || '';
            const freq = $row.find('.dose-frequency').val() || '';
            const dur = $row.find('.dose-duration').val() || '';
            const durUnit = $row.find('.dose-duration-unit').val() || '';
            const qty = $row.find('.dose-qty').val() || '';

            let parts = [];
            if (amount) parts.push(amount + unit);
            if (route) parts.push(route);
            if (freq) parts.push(freq);
            if (dur) parts.push(dur + ' ' + durUnit);
            if (qty) parts.push('Qty: ' + qty);

            $row.find('.structured-dose-value').val(parts.join(' | '));
        }

        function collapseStructuredDose($td) {
            const val = $td.find('.structured-dose-value').val();
            return val || '';
        }

        // ─── Dose Calculator ───
        function toggleDoseCalculator() {
            const panel = document.getElementById('dose_calculator_panel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        function calculateDose() {
            const weight = parseFloat($('#calc_weight').val()) || 0;
            const dosePerKg = parseFloat($('#calc_dose_per_kg').val()) || 0;
            const freqPerDay = parseInt($('#calc_frequency').val()) || 1;
            const duration = parseInt($('#calc_duration').val()) || 1;
            const tabStrength = parseFloat($('#calc_tab_strength').val()) || 1;

            if (weight <= 0 || dosePerKg <= 0) {
                $('#calc_results').html('<span class="text-muted">Enter weight and dose/kg to calculate...</span>');
                return;
            }

            const singleDose = weight * dosePerKg;
            const dailyDose = singleDose * freqPerDay;
            const totalCourse = dailyDose * duration;
            const totalTablets = Math.ceil(totalCourse / tabStrength);

            $('#calc_results').html(`
                <div class="d-flex flex-wrap gap-3">
                    <span><strong>Single dose:</strong> <span class="text-primary">${singleDose.toFixed(1)} mg</span></span>
                    <span><strong>Daily:</strong> <span class="text-info">${dailyDose.toFixed(1)} mg</span></span>
                    <span><strong>Total course:</strong> <span class="text-warning">${totalCourse.toFixed(1)} mg</span></span>
                    <span><strong>Tablets/units:</strong> <span class="badge bg-success">${totalTablets}</span> (${tabStrength}mg each)</span>
                </div>
            `);
        }

        // Apply calculator results to the last (or selected) structured dose row
        function applyCalculatorToSelected() {
            if (!doseStructuredMode) {
                alert('Switch to Structured Dose mode first.');
                return;
            }
            const $lastRow = $('#selected-products tr:last .structured-dose');
            if ($lastRow.length === 0) {
                alert('Add a product first, then apply calculator results.');
                return;
            }
            const weight = parseFloat($('#calc_weight').val()) || 0;
            const dosePerKg = parseFloat($('#calc_dose_per_kg').val()) || 0;
            const tabStrength = parseFloat($('#calc_tab_strength').val()) || 1;
            const freqPerDay = parseInt($('#calc_frequency').val()) || 1;
            const duration = parseInt($('#calc_duration').val()) || 1;

            if (weight <= 0 || dosePerKg <= 0) {
                alert('Enter weight and dose/kg in the calculator first.');
                return;
            }

            const singleDose = weight * dosePerKg;
            const totalTablets = Math.ceil((singleDose * freqPerDay * duration) / tabStrength);

            // Map calculator freqPerDay back to frequency code
            const freqReverseMap = { 1: 'OD', 2: 'BD', 3: 'TDS', 4: 'QID', 6: 'Q4H' };
            const freqCode = freqReverseMap[freqPerDay] || 'OD';

            $lastRow.find('.dose-amount').val(singleDose.toFixed(1));
            $lastRow.find('.dose-unit').val('mg');
            $lastRow.find('.dose-frequency').val(freqCode);
            $lastRow.find('.dose-duration').val(duration);
            $lastRow.find('.dose-duration-unit').val('days');
            $lastRow.find('.dose-qty').val(totalTablets);
            updateStructuredDoseValue($lastRow.find('.dose-amount')[0]);
        }

        function removeProdRow(obj) {
            $(obj).closest('tr').remove();
        }

        function setSearchValProd(name, id, price, coverageMode = null, claims = null, payable = null) {
            const coverageBadge = coverageMode ? `<div class="small mt-1"><span class="badge bg-info">${coverageMode.toUpperCase()}</span> <span class="text-danger">Pay: ${payable ?? price}</span> <span class="text-success">Claims: ${claims ?? 0}</span></div>` : '';

            let doseCell;
            if (doseStructuredMode) {
                doseCell = `<td>${buildStructuredDoseHtml('')}<input type='hidden' name='consult_presc_id[]' value='${id}'></td>`;
            } else {
                doseCell = `<td><input type='text' class='form-control' name='consult_presc_dose[]' required><input type='hidden' name='consult_presc_id[]' value='${id}'></td>`;
            }

            var mk = `
                <tr>
                    <td>${name}${coverageBadge}</td>
                    <td>${payable ?? price}</td>
                    ${doseCell}
                    <td><button class='btn btn-danger' onclick="removeProdRow(this)">x</button></td>
                </tr>
            `;

            $('#selected-products').append(mk);
            $('#consult_presc_res').html('');
        }

        function searchProducts(q) {
            if (q != "") {
                searchRequest = $.ajax({
                    url: "{{ url('live-search-products') }}",
                    method: "GET",
                    dataType: 'json',
                    data: {
                        term: q,
                        patient_id: '{{ $patient->id }}'
                    },
                    success: function(data) {
                        // Clear existing options from the select field
                        $('#consult_presc_res').html('');
                        console.log(data);
                        // data = JSON.parse(data);

                        for (var i = 0; i < data.length; i++) {
                            const item = data[i] || {};
                            const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                            const name = item.product_name || 'Unknown';
                            const code = item.product_code || '';
                            const qty = item.stock && item.stock.current_quantity !== undefined ? item.stock.current_quantity : 0;
                            const price = item.price && item.price.initial_sale_price !== undefined ? item.price.initial_sale_price : 0;
                            const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                            const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                            const mode = item.coverage_mode || null;
                            const coverageBadge = mode ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ${payable}</span> <span class='text-success ms-1'>Claim: ${claims}</span>` : '';
                            const displayName = `${name}[${code}](${qty} avail.)`;

                            var mk =
                                `<li class='list-group-item'
                                   style="background-color: #f0f0f0;"
                                   onclick="setSearchValProd('${displayName}', '${item.id}', '${price}', '${mode}', '${claims}', '${payable}')">
                                   [${category}]<b>${name}[${code}]</b> (${qty} avail.) NGN ${price} ${coverageBadge}</li>`;
                            $('#consult_presc_res').append(mk);
                            $('#consult_presc_res').show();
                        }
                    }
                });
            } else {
                $('#consult_presc_res').html('');
            }
        }
    </script>
    <script>
        function switch_tab(e, id_of_next_tab) {
            e.preventDefault();
            $('#' + id_of_next_tab).click();
        }

        function toggleAdmitNote(obj) {
            var opt = $(obj).val();
            console.log(opt);
            if (opt == '0') {
                $('#admit-note-div').html('');
            } else {
                var mk = `
                    <textarea name="admit_note" id="admit_note" class="form-control" placeholder="Enter brief note"></textarea>
                `;
                $('#admit-note-div').append(mk);
            }
        }


        function setSearchValSer(name, id, price, coverageMode = null, claims = null, payable = null) {
            const coverageBadge = coverageMode ? `<div class="small mt-1"><span class="badge bg-info">${coverageMode.toUpperCase()}</span> <span class="text-danger">Pay: ${payable ?? price}</span> <span class="text-success">Claims: ${claims ?? 0}</span></div>` : '';
            var mk = `
                <tr>
                    <td>${name}${coverageBadge}</td>
                    <td>${payable ?? price}</td>
                    <td>
                        <input type = 'text' class='form-control' name=consult_invest_note[]>
                        <input type = 'hidden' name=consult_invest_id[] value='${id}'>
                    </td>
                    <td><button class='btn btn-danger' onclick="removeProdRow(this)">x</button></td>
                </tr>
            `;

            $('#selected-services').append(mk);
            $('#consult_invest_res').html('');

        }

        function setSearchValImaging(name, id, price, coverageMode = null, claims = null, payable = null) {
            const coverageBadge = coverageMode ? `<div class="small mt-1"><span class="badge bg-info">${coverageMode.toUpperCase()}</span> <span class="text-danger">Pay: ${payable ?? price}</span> <span class="text-success">Claims: ${claims ?? 0}</span></div>` : '';
            var mk = `
                <tr>
                    <td>${name}${coverageBadge}</td>
                    <td>${payable ?? price}</td>
                    <td>
                        <input type = 'text' class='form-control' name=consult_imaging_note[]>
                        <input type = 'hidden' name=consult_imaging_id[] value='${id}'>
                    </td>
                    <td><button class='btn btn-danger' onclick="removeProdRow(this)">x</button></td>
                </tr>
            `;

            $('#selected-imaging-services').append(mk);
            $('#consult_imaging_res').html('');

        }

        function searchServices(q) {
            if (q != "") {
                searchRequest = $.ajax({
                    url: "{{ url('live-search-services') }}",
                    method: "GET",
                    dataType: 'json',
                    data: {
                        term: q,
                        patient_id: '{{ $patient->id }}'
                    },
                    success: function(data) {
                        // Clear existing options from the select field
                        $('#consult_invest_res').html('');
                        console.log(data);
                        // data = JSON.parse(data);

                        for (var i = 0; i < data.length; i++) {
                            const item = data[i] || {};
                            const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                            const name = item.service_name || 'Unknown';
                            const code = item.service_code || '';
                            const price = item.price && item.price.sale_price !== undefined ? item.price.sale_price : 0;
                            const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                            const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                            const mode = item.coverage_mode || null;
                            const coverageBadge = mode ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ${payable}</span> <span class='text-success ms-1'>Claim: ${claims}</span>` : '';
                            const displayName = `${name}[${code}]`;

                            var mk =
                                `<li class='list-group-item'
                                   style="background-color: #f0f0f0;"
                                   onclick="setSearchValSer('${displayName}', '${item.id}', '${price}', '${mode}', '${claims}', '${payable}')">
                                   [${category}]<b>${name}[${code}]</b> NGN ${price} ${coverageBadge}</li>`;
                            $('#consult_invest_res').append(mk);
                            $('#consult_invest_res').show();
                        }
                    }
                });
            } else {
                $('#consult_invest_res').html('');
            }
        }
    </script>

    <script>
        function searchImagingServices(q) {
            if (q != "") {
                searchRequest = $.ajax({
                    url: "{{ url('live-search-services') }}",
                    method: "GET",
                    dataType: 'json',
                    data: {
                        term: q,
                        category_id: 6, // Imaging category ID
                        patient_id: '{{ $patient->id }}'
                    },
                    success: function(data) {
                        // Clear existing options from the select field
                        $('#consult_imaging_res').html('');
                        console.log(data);
                        // data = JSON.parse(data);

                        for (var i = 0; i < data.length; i++) {
                            const item = data[i] || {};
                            const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                            const name = item.service_name || 'Unknown';
                            const code = item.service_code || '';
                            const price = item.price && item.price.sale_price !== undefined ? item.price.sale_price : 0;
                            const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                            const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                            const mode = item.coverage_mode || null;
                            const coverageBadge = mode ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ${payable}</span> <span class='text-success ms-1'>Claim: ${claims}</span>` : '';
                            const displayName = `${name}[${code}]`;

                            var mk =
                                `<li class='list-group-item'
                                   style="background-color: #f0f0f0;"
                                   onclick="setSearchValImaging('${displayName}', '${item.id}', '${price}', '${mode}', '${claims}', '${payable}')">
                                   [${category}]<b>${name}[${code}]</b> NGN ${price} ${coverageBadge}</li>`;
                            $('#consult_imaging_res').append(mk);
                            $('#consult_imaging_res').show();
                        }
                    }
                });
            } else {
                $('#consult_imaging_res').html('');
            }
        }
    </script>

    <script>
        $(function() {
            $('#nurse_note_hist_1').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 1]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#nurse_note_hist_2').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 2]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#nurse_note_hist_3').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 3]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#nurse_note_hist_4').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 4]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        $(function() {
            $('#nurse_note_hist_5').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('patientNursngNote', [$patient->id, 5]) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "nursing_note_type_id",
                        name: "nursing_note_type_id"
                    },
                    {
                        data: "created_by",
                        name: "created_by"
                    },
                    {
                        data: "select",
                        name: "select"
                    },
                ],

                "paging": true
            });
        });
    </script>
    <script>
        function setNoteInModal(obj) {
            $('#note_type_name_').text($(obj).attr('data-service-name'));
            $('#nursing_note_template_').html($(obj).attr('data-template'));
            $('#nursingNoteModal').modal('show');
        }
    </script>
    <script>
        function toggle_group(class_) {
            var x = document.getElementsByClassName(class_);
            for (i = 0; i < x.length; i++) {
                if (x[i].style.display === "none") {
                    x[i].style.display = "block";
                } else {
                    x[i].style.display = "none";
                }
            }

        }
    </script>
    @include('admin.partials.vitals-scripts')
    @include('admin.partials.nursing-note-save-scripts')
    @include('admin.patients.partials.modals')

    <script>
        function setResViewInModal(obj) {
            let res_obj = JSON.parse($(obj).attr('data-result-obj'));

            // Basic service info
            $('.invest_res_service_name_view').text($(obj).attr('data-service-name'));

            // Patient information
            let patientName = res_obj.patient.user.firstname + ' ' + res_obj.patient.user.surname;
            $('#res_patient_name').html(patientName);
            $('#res_patient_id').html(res_obj.patient.file_no);

            // Calculate age from date of birth
            let age = 'N/A';
            if (res_obj.patient.date_of_birth) {
                let dob = new Date(res_obj.patient.date_of_birth);
                let today = new Date();
                let ageYears = today.getFullYear() - dob.getFullYear();
                let monthDiff = today.getMonth() - dob.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                    ageYears--;
                }
                age = ageYears + ' years';
            }
            $('#res_patient_age').html(age);

            // Gender
            let gender = res_obj.patient.gender ? res_obj.patient.gender.toUpperCase() : 'N/A';
            $('#res_patient_gender').html(gender);

            // Test information
            $('#res_test_id').html(res_obj.id);
            $('#res_sample_date').html(res_obj.sample_date || 'N/A');
            $('#res_result_date').html(res_obj.result_date || 'N/A');
            $('#res_result_by').html(res_obj.results_person.firstname + ' ' + res_obj.results_person.surname);

            // Signature date (use result date)
            $('#res_signature_date').html(res_obj.result_date || '');

            // Generated date (current date)
            let now = new Date();
            let generatedDate = now.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            $('#res_generated_date').html(generatedDate);

            // Handle V2 results (structured data)
            if (res_obj.result_data && typeof res_obj.result_data === 'object') {
                let resultsHtml = '<table class="result-table"><thead><tr>';
                resultsHtml += '<th style="width: 40%;">Test Parameter</th>';
                resultsHtml += '<th style="width: 25%;">Results</th>';
                resultsHtml += '<th style="width: 25%;">Reference Range</th>';
                resultsHtml += '<th style="width: 10%;">Status</th>';
                resultsHtml += '</tr></thead><tbody>';

                res_obj.result_data.forEach(function(param) {
                    resultsHtml += '<tr>';
                    resultsHtml += '<td><strong>' + param.name + '</strong>';
                    if (param.code) {
                        resultsHtml += ' <span style="color: #999;">(' + param.code + ')</span>';
                    }
                    resultsHtml += '</td>';

                    // Value with unit
                    let valueDisplay = param.value;
                    if (param.unit) {
                        valueDisplay += ' ' + param.unit;
                    }
                    resultsHtml += '<td>' + valueDisplay + '</td>';

                    // Reference range
                    let refRange = 'N/A';
                    if (param.reference_range) {
                        if (param.type === 'integer' || param.type === 'float') {
                            if (param.reference_range.min !== undefined && param.reference_range.max !== undefined) {
                                refRange = param.reference_range.min + ' - ' + param.reference_range.max;
                                if (param.unit) refRange += ' ' + param.unit;
                            }
                        } else if (param.type === 'boolean' || param.type === 'enum') {
                            refRange = param.reference_range.reference_value || 'N/A';
                        } else if (param.reference_range.text) {
                            refRange = param.reference_range.text;
                        }
                    }
                    resultsHtml += '<td>' + refRange + '</td>';

                    // Status badge
                    let statusHtml = '';
                    if (param.status) {
                        let statusClass = 'status-' + param.status.toLowerCase().replace(' ', '-');
                        statusHtml = '<span class="result-status-badge ' + statusClass + '">' + param.status + '</span>';
                    }
                    resultsHtml += '<td>' + statusHtml + '</td>';
                    resultsHtml += '</tr>';
                });

                resultsHtml += '</tbody></table>';
                $('#invest_res').html(resultsHtml);
            } else {
                // V1 results (HTML content)
                $('#invest_res').html(res_obj.result);
            }

            // Handle attachments
            $('#invest_attachments').html('');
            if (res_obj.attachments) {
                let attachments = typeof res_obj.attachments === 'string' ? JSON.parse(res_obj.attachments) : res_obj.attachments;
                if (attachments && attachments.length > 0) {
                    let attachHtml = '<div class="result-attachments"><h6 style="margin-bottom: 15px;"><i class="mdi mdi-paperclip"></i> Attachments</h6><div class="row">';
                    attachments.forEach(function(attachment) {
                        let url = '{{ asset("storage") }}/' + attachment.path;
                        let icon = getFileIcon(attachment.type);
                        attachHtml += `<div class="col-md-4 mb-2">
                            <a href="${url}" target="_blank" class="btn btn-outline-primary btn-sm btn-block">
                                ${icon} ${attachment.name}
                            </a>
                        </div>`;
                    });
                    attachHtml += '</div></div>';
                    $('#invest_attachments').html(attachHtml);
                }
            }

            $('#investResViewModal').modal('show');
        }

        function getFileIcon(extension) {
            const icons = {
                'pdf': '<i class="mdi mdi-file-pdf"></i>',
                'doc': '<i class="mdi mdi-file-word"></i>',
                'docx': '<i class="mdi mdi-file-word"></i>',
                'jpg': '<i class="mdi mdi-file-image"></i>',
                'jpeg': '<i class="mdi mdi-file-image"></i>',
                'png': '<i class="mdi mdi-file-image"></i>'
            };
            return icons[extension] || '<i class="mdi mdi-file"></i>';
        }
    </script>

    <!-- Nurse Charts Scripts -->
    <script>
        // Wait for document to be ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize nurse chart tabs - using bootstrap 5 syntax
            const nurseChartTabTriggers = document.querySelectorAll('#nurseChartTabs a[data-toggle="tab"]');
            nurseChartTabTriggers.forEach(trigger => {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabId = this.getAttribute('href');
                    const tab = new bootstrap.Tab(trigger);
                    tab.show();
                });
            });

            // Initialize date filter with default dates (last 30 days)
            initDateFilter();

            // Add event listener for apply filter button
            document.getElementById('apply-date-filter').addEventListener('click', function() {
                loadNurseCharts();
            });

            // Add event listener for reset filter button
            document.getElementById('reset-date-filter').addEventListener('click', function() {
                initDateFilter();
                loadNurseCharts();
            });

            // Add event listener for nurse chart tab activation
            $('#nurse_charts_tab').on('shown.bs.tab', function(e) {
                loadNurseCharts();
            });

            // Helper function to extract medication name from various possible data structures
            function extractMedicationName(obj, prescriptions) {
                if (!obj) return 'N/A';

                // Try different possible paths to find the medication name
                if (obj.medication_name) return obj.medication_name;
                if (obj.product_name) return obj.product_name;
                if (obj.name) return obj.name;

                if (obj.product) {
                    if (typeof obj.product === 'object') {
                        return obj.product.product_name || obj.product.name || 'N/A';
                    }
                    return obj.product;
                }

                if (obj.prescription && obj.prescription.product) {
                    const product = obj.prescription.product;
                    return product.product_name || product.name || 'N/A';
                }

                // For administration records with a product_or_service_request_id
                // Look up the corresponding prescription
                if (obj.product_or_service_request_id && prescriptions && prescriptions.length > 0) {
                    const prescription = prescriptions.find(p => p.id === obj.product_or_service_request_id);
                    if (prescription) {
                        // Recursively call the function to extract the name from the prescription
                        // Passing null as second parameter to avoid infinite recursion
                        return extractMedicationName(prescription, null);
                    }
                }

                // Extract from dosage if it contains medication name
                if (obj.dose && typeof obj.dose === 'string' && obj.dose.includes(':')) {
                    const parts = obj.dose.split(':');
                    if (parts.length > 0) return parts[0].trim();
                }

                return 'N/A';
            }

            // Date utility functions - 30 days with today in the middle
            function getDefaultStartDate() {
                const date = new Date();
                date.setDate(date.getDate() - 15); // 15 days before today
                return date.toISOString().split('T')[0]; // Format as YYYY-MM-DD
            }

            function getDefaultEndDate() {
                const date = new Date();
                date.setDate(date.getDate() + 15); // 15 days after today
                return date.toISOString().split('T')[0]; // Format as YYYY-MM-DD
            }

            function formatDateForApi(dateString) {
                if (!dateString) return '';
                return dateString; // Already in YYYY-MM-DD format for API
            }

            // Initialize date filter with defaults
            function initDateFilter() {
                document.getElementById('chart-date-from').value = getDefaultStartDate();
                document.getElementById('chart-date-to').value = getDefaultEndDate();
            }

            // Format a date for display
            function formatDateForDisplay(dateString) {
                if (!dateString) return '';
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }

            // Update the date range summary display
            function updateDateRangeSummary(startDate, endDate) {
                const startFormatted = formatDateForDisplay(startDate);
                const endFormatted = formatDateForDisplay(endDate);
                const summaryElement = document.getElementById('date-range-summary');

                if (startDate && endDate) {
                    summaryElement.textContent = `Showing data from ${startFormatted} to ${endFormatted}`;
                } else if (startDate) {
                    summaryElement.textContent = `Showing data from ${startFormatted} onwards`;
                } else if (endDate) {
                    summaryElement.textContent = `Showing data up to ${endFormatted}`;
                } else {
                    summaryElement.textContent = `Showing all data`;
                }
            }

            // Load nurse charts data
            function loadNurseCharts() {
                const patientId = {{ $patient->id }};

                // Initialize date filter if needed
                if (!document.getElementById('chart-date-from').value) {
                    initDateFilter();
                }

                // Get current filter values
                const startDate = document.getElementById('chart-date-from').value;
                const endDate = document.getElementById('chart-date-to').value;

                // Update the date range summary
                updateDateRangeSummary(startDate, endDate);

                // Load medication chart with date filter
                loadMedicationChart(patientId, startDate, endDate);

                // Load intake/output charts with date filter
                loadIntakeOutputCharts(patientId, startDate, endDate);
            }

            // Function to load medication chart
            function loadMedicationChart(patientId, startDate, endDate) {
                // Show loading indicator
                const container = document.getElementById('medication-chart-content');
                container.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading medication data...</p>
                    </div>
                `;

                // Build URL with query parameters
                const url = new URL(`{{ url('/') }}/patients/${patientId}/nurse-chart/medication`);
                if (startDate) url.searchParams.append('start_date', formatDateForApi(startDate));
                if (endDate) url.searchParams.append('end_date', formatDateForApi(endDate));

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        console.log('Medication Chart Data:', data);
                        const container = document.getElementById('medication-chart-content');

                        // Update summary stats
                        const prescriptionCount = data.prescriptions ? data.prescriptions.length : 0;
                        const administrationCount = data.administrations ? data.administrations.length : 0;

                        document.getElementById('data-summary-stats').innerHTML = `
                            <span class="badge bg-primary rounded-pill fs-6">
                                <i class="mdi mdi-pill me-1"></i> ${prescriptionCount} medications
                            </span>
                            <span class="badge bg-info rounded-pill fs-6">
                                <i class="mdi mdi-history me-1"></i> ${administrationCount} administrations
                            </span>
                        `;

                        // Render calendar grid view
                        renderDoctorMedicationCalendar(data, startDate, endDate);
                    })
                    .catch(error => {
                        console.error('Error loading medication chart:', error);
                        document.getElementById('medication-chart-content').innerHTML =
                            '<div class="alert alert-danger">Failed to load medication chart data. Please try again later.</div>';
                    });
            }

            // Function to render medication calendar grid for doctor's view (read-only)
            // Unified calendar showing ALL medications in one grid
            function renderDoctorMedicationCalendar(data, startDateStr, endDateStr) {
                const container = document.getElementById('medication-chart-content');

                console.log('Rendering unified calendar with data:', data);
                console.log('Prescriptions count:', data.prescriptions ? data.prescriptions.length : 0);
                console.log('Administrations count:', data.administrations ? data.administrations.length : 0);

                if (!data.prescriptions || data.prescriptions.length === 0) {
                    container.innerHTML = '<div class="alert alert-info">No active medications found for this period.</div>';
                    return;
                }

                // Assign unique colors to each medication
                const medicationColors = [
                    { bg: '#e3f2fd', border: '#1976d2', text: '#0d47a1' },  // Blue
                    { bg: '#e8f5e9', border: '#388e3c', text: '#1b5e20' },  // Green
                    { bg: '#fff3e0', border: '#f57c00', text: '#e65100' },  // Orange
                    { bg: '#f3e5f5', border: '#8e24aa', text: '#6a1b9a' },  // Purple
                    { bg: '#e0f7fa', border: '#0097a7', text: '#006064' },  // Cyan
                    { bg: '#fce4ec', border: '#c2185b', text: '#880e4f' },  // Pink
                    { bg: '#fff8e1', border: '#ffa000', text: '#ff6f00' },  // Amber
                    { bg: '#e8eaf6', border: '#3f51b5', text: '#1a237e' },  // Indigo
                    { bg: '#efebe9', border: '#6d4c41', text: '#3e2723' },  // Brown
                    { bg: '#eceff1', border: '#546e7a', text: '#263238' },  // Blue Grey
                ];

                // Build medication color map
                const medColorMap = {};
                data.prescriptions.forEach((p, idx) => {
                    medColorMap[p.id] = medicationColors[idx % medicationColors.length];
                });

                // Add unified calendar CSS
                let html = `<style>
                    .unified-med-calendar .calendar-weekday-header {
                        display: grid;
                        grid-template-columns: repeat(7, 1fr);
                        gap: 1px;
                        background-color: #4a5568;
                    }
                    .unified-med-calendar .weekday-name {
                        padding: 10px;
                        text-align: center;
                        font-weight: 600;
                        font-size: 12px;
                        text-transform: uppercase;
                        color: white;
                        background-color: #4a5568;
                    }
                    .unified-med-calendar .medication-calendar-grid {
                        display: grid;
                        grid-template-columns: repeat(7, 1fr);
                        gap: 1px;
                        background-color: #dee2e6;
                        border: 1px solid #dee2e6;
                    }
                    .unified-med-calendar .calendar-day-cell {
                        background-color: white;
                        min-height: 100px;
                        padding: 6px;
                        display: flex;
                        flex-direction: column;
                    }
                    .unified-med-calendar .calendar-day-cell.empty-day {
                        background-color: #f8f9fa;
                        min-height: 50px;
                    }
                    .unified-med-calendar .calendar-day-cell.today {
                        background-color: #e3f2fd;
                        border: 2px solid #2196F3;
                    }
                    .unified-med-calendar .calendar-day-cell.weekend {
                        background-color: #fffde7;
                    }
                    .unified-med-calendar .calendar-day-cell.past-date {
                        opacity: 0.8;
                        background-color: #fafafa;
                    }
                    .unified-med-calendar .day-header {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 4px;
                        padding-bottom: 4px;
                        border-bottom: 1px solid #eee;
                    }
                    .unified-med-calendar .day-name {
                        font-size: 10px;
                        color: #666;
                        text-transform: uppercase;
                    }
                    .unified-med-calendar .day-number {
                        font-weight: bold;
                        font-size: 14px;
                        color: #333;
                    }
                    .unified-med-calendar .schedule-items {
                        flex: 1;
                        display: flex;
                        flex-direction: column;
                        gap: 4px;
                        overflow-y: auto;
                        max-height: 180px;
                        padding: 2px;
                    }
                    .unified-med-calendar .med-item {
                        font-size: 11px;
                        padding: 6px 8px;
                        border-radius: 5px;
                        display: flex;
                        align-items: center;
                        gap: 6px;
                        cursor: pointer;
                        border-left: 4px solid;
                        transition: transform 0.1s, box-shadow 0.1s;
                    }
                    .unified-med-calendar .med-item:hover {
                        transform: translateY(-1px);
                        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
                    }
                    .unified-med-calendar .med-item.given {
                        opacity: 1;
                    }
                    .unified-med-calendar .med-item.scheduled {
                        opacity: 0.9;
                    }
                    .unified-med-calendar .med-item.missed {
                        opacity: 0.8;
                    }
                    .unified-med-calendar .med-item.discontinued {
                        opacity: 0.6;
                        text-decoration: line-through;
                    }
                    .unified-med-calendar .med-item i {
                        font-size: 14px;
                        flex-shrink: 0;
                    }
                    .unified-med-calendar .med-details {
                        flex: 1;
                        min-width: 0;
                    }
                    .unified-med-calendar .med-name {
                        font-weight: 700;
                        font-size: 11px;
                        display: block;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }
                    .unified-med-calendar .med-time {
                        font-size: 10px;
                        font-weight: 600;
                        opacity: 0.85;
                        display: block;
                    }
                    .unified-med-calendar .no-schedules {
                        font-size: 10px;
                        color: #999;
                        font-style: italic;
                        text-align: center;
                        padding: 10px;
                    }
                </style>`;

                // Parse dates - default 30 days with today in the middle
                let start, end;
                if (startDateStr) {
                    start = new Date(startDateStr);
                } else {
                    start = new Date();
                    start.setDate(start.getDate() - 15);
                }
                if (endDateStr) {
                    end = new Date(endDateStr);
                } else {
                    end = new Date();
                    end.setDate(end.getDate() + 15);
                }
                start.setHours(0, 0, 0, 0);
                end.setHours(23, 59, 59, 999);

                html += '<div class="unified-med-calendar">';

                // Weekday header
                html += '<div class="calendar-weekday-header">';
                ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
                    html += `<div class="weekday-name">${day}</div>`;
                });
                html += '</div>';

                // Calendar grid
                html += '<div class="medication-calendar-grid">';

                // Add empty cells for alignment
                const daysBeforeStart = start.getDay();
                for (let i = 0; i < daysBeforeStart; i++) {
                    html += '<div class="calendar-day-cell empty-day"></div>';
                }

                // Today reference
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                // Generate days
                const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                let currentDate = new Date(start);

                while (currentDate <= end) {
                    const dateStr = currentDate.toISOString().split('T')[0];
                    const dayNum = currentDate.getDate();
                    const dayOfWeek = currentDate.getDay();
                    const isToday = currentDate.toDateString() === today.toDateString();
                    const isPast = currentDate < today;
                    const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

                    // Build cell classes
                    let cellClass = 'calendar-day-cell';
                    if (isToday) cellClass += ' today';
                    else if (isPast) cellClass += ' past-date';
                    if (isWeekend && !isToday) cellClass += ' weekend';

                    html += `<div class="${cellClass}">`;
                    html += `<div class="day-header">
                        <span class="day-name">${dayNames[dayOfWeek]}</span>
                        <span class="day-number">${dayNum}</span>
                    </div>`;
                    html += '<div class="schedule-items">';

                    // Collect all items for this day across ALL medications
                    let dayItems = [];

                    data.prescriptions.forEach(prescription => {
                        const medicationName = extractMedicationName(prescription, null);
                        const color = medColorMap[prescription.id];
                        const schedules = prescription.schedules || [];
                        const administrations = (data.administrations || []).filter(a =>
                            a.product_or_service_request_id === prescription.id
                        );

                        // Log for debugging
                        if (currentDate.toDateString() === today.toDateString()) {
                            console.log(`${medicationName}: ${schedules.length} schedules, ${administrations.length} administrations`);
                        }

                        // Find schedules for this day
                        const daySchedules = schedules.filter(s => {
                            if (!s.scheduled_time && !s.scheduled_at) return false;
                            const schedTime = s.scheduled_time || s.scheduled_at;
                            const schedDate = new Date(schedTime);
                            return schedDate.toDateString() === currentDate.toDateString();
                        });

                        // Find administrations for this day
                        const dayAdministrations = administrations.filter(a => {
                            if (!a.administered_at) return false;
                            const adminDate = new Date(a.administered_at);
                            return adminDate.toDateString() === currentDate.toDateString();
                        });

                        // Process schedules
                        daySchedules.forEach(schedule => {
                            const schedTime = schedule.scheduled_time || schedule.scheduled_at;
                            const schedDate = new Date(schedTime);
                            const time = schedDate.toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            });

                            // Check if administered
                            const admin = dayAdministrations.find(a => a.schedule_id === schedule.id);

                            // Check if discontinued
                            const isDiscontinued = prescription.discontinued_at &&
                                new Date(prescription.discontinued_at) < new Date(schedTime);

                            let status = 'scheduled';
                            let icon = 'mdi-clock-outline';
                            let tooltip = `<strong>${medicationName}</strong><br>Scheduled: ${time}<br>Dose: ${schedule.dose || 'N/A'}<br>Route: ${schedule.route || 'N/A'}`;

                            if (isDiscontinued) {
                                status = 'discontinued';
                                icon = 'mdi-close-circle-outline';
                                tooltip = `<strong>${medicationName}</strong><br>Discontinued<br>Time: ${time}`;
                            } else if (admin) {
                                status = 'given';
                                icon = 'mdi-check-circle';
                                const adminTime = new Date(admin.administered_at).toLocaleTimeString('en-US', {
                                    hour: 'numeric',
                                    minute: '2-digit',
                                    hour12: true
                                });
                            } else if (isPast) {
                                status = 'missed';
                                icon = 'mdi-alert-circle';
                            }

                            dayItems.push({
                                sortTime: schedDate.getTime(),
                                time: time,
                                medName: medicationName,
                                color: color,
                                status: status,
                                icon: icon,
                                isPrn: false,
                                // Detailed data for modal
                                dose: admin ? (admin.dose || schedule.dose) : schedule.dose,
                                route: admin ? (admin.route || schedule.route) : schedule.route,
                                scheduledTime: time,
                                administeredTime: admin ? new Date(admin.administered_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : null,
                                administeredBy: admin ? admin.administered_by_name : null,
                                comment: admin ? admin.comment : null,
                                scheduleId: schedule.id,
                                adminId: admin ? admin.id : null
                            });
                        });

                        // Process PRN (unscheduled) administrations
                        dayAdministrations.filter(a => !a.schedule_id).forEach(admin => {
                            const adminDate = new Date(admin.administered_at);
                            const adminTime = adminDate.toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            });

                            dayItems.push({
                                sortTime: adminDate.getTime(),
                                time: adminTime,
                                medName: medicationName,
                                color: color,
                                status: 'given',
                                icon: 'mdi-plus-circle',
                                isPrn: true,
                                // Detailed data for modal
                                dose: admin.dose,
                                route: admin.route,
                                scheduledTime: null,
                                administeredTime: adminTime,
                                administeredBy: admin.administered_by_name,
                                comment: admin.comment,
                                scheduleId: null,
                                adminId: admin.id
                            });
                        });
                    });

                    // Sort items by time
                    dayItems.sort((a, b) => a.sortTime - b.sortTime);

                    if (dayItems.length === 0) {
                        html += '<span class="no-schedules">-</span>';
                    } else {
                        dayItems.forEach((item, idx) => {
                            const iconColor = item.status === 'given' ? 'text-success' :
                                              item.status === 'missed' ? 'text-danger' :
                                              item.status === 'discontinued' ? 'text-muted' : 'text-primary';
                            const prnLabel = item.isPrn ? ' (PRN)' : '';

                            // Encode item data as JSON for the click handler
                            const itemData = JSON.stringify(item).replace(/"/g, '&quot;');

                            html += `<div class="med-item ${item.status}"
                                style="background-color: ${item.color.bg}; border-left-color: ${item.color.border}; color: ${item.color.text};"
                                onclick="showMedDetails(this)" data-med-details="${itemData}">
                                <i class="mdi ${item.icon} ${iconColor}"></i>
                                <div class="med-details">
                                    <span class="med-name">${item.medName}${prnLabel}</span>
                                    <span class="med-time">${item.time}</span>
                                </div>
                            </div>`;
                        });
                    }

                    html += '</div></div>';

                    // Next day
                    currentDate.setDate(currentDate.getDate() + 1);
                }

                html += '</div></div>';

                container.innerHTML = html;
            }

            // Show medication details modal - exposed globally for onclick handlers
            window.showMedDetails = function(element) {
                const data = JSON.parse(element.getAttribute('data-med-details'));

                // Status badge
                let statusBadge = '';
                switch(data.status) {
                    case 'given':
                        statusBadge = '<span class="badge bg-success"><i class="mdi mdi-check-circle me-1"></i>Given</span>';
                        break;
                    case 'scheduled':
                        statusBadge = '<span class="badge bg-primary"><i class="mdi mdi-clock-outline me-1"></i>Scheduled</span>';
                        break;
                    case 'missed':
                        statusBadge = '<span class="badge bg-danger"><i class="mdi mdi-alert-circle me-1"></i>Missed</span>';
                        break;
                    case 'discontinued':
                        statusBadge = '<span class="badge bg-secondary"><i class="mdi mdi-close-circle me-1"></i>Discontinued</span>';
                        break;
                }

                if (data.isPrn) {
                    statusBadge += ' <span class="badge bg-purple"><i class="mdi mdi-plus-circle me-1"></i>PRN</span>';
                }

                // Build modal content
                let content = `
                    <div class="row">
                        <div class="col-12 mb-3">
                            <h5 class="mb-2" style="color: ${data.color.text};">
                                <i class="mdi mdi-pill me-2"></i>${data.medName}
                            </h5>
                            ${statusBadge}
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Dose</label>
                            <div class="fw-bold">${data.dose || 'N/A'}</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Route</label>
                            <div class="fw-bold">${data.route || 'N/A'}</div>
                        </div>
                    </div>`;

                if (data.scheduledTime) {
                    content += `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Scheduled Time</label>
                            <div class="fw-bold"><i class="mdi mdi-clock-outline me-1 text-primary"></i>${data.scheduledTime}</div>
                        </div>`;

                    if (data.administeredTime) {
                        content += `
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Administered Time</label>
                            <div class="fw-bold"><i class="mdi mdi-check-circle me-1 text-success"></i>${data.administeredTime}</div>
                        </div>`;
                    } else {
                        content += `<div class="col-md-6 mb-3"></div>`;
                    }
                    content += `</div>`;
                } else if (data.administeredTime) {
                    content += `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Administered Time</label>
                            <div class="fw-bold"><i class="mdi mdi-check-circle me-1 text-success"></i>${data.administeredTime}</div>
                        </div>
                        <div class="col-md-6 mb-3"></div>
                    </div>`;
                }

                if (data.administeredBy) {
                    content += `
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="text-muted small">Administered By</label>
                            <div class="fw-bold"><i class="mdi mdi-account me-1"></i>${data.administeredBy}</div>
                        </div>
                    </div>`;
                }

                if (data.comment) {
                    content += `
                    <div class="row">
                        <div class="col-12">
                            <label class="text-muted small">Notes</label>
                            <div class="p-2 bg-light rounded">${data.comment}</div>
                        </div>
                    </div>`;
                }

                document.getElementById('medDetailsModalBody').innerHTML = content;
                document.getElementById('medDetailsModalLabel').textContent = 'Medication Details';

                // Move modal to body to avoid z-index/overflow issues
                const modalEl = document.getElementById('medDetailsModal');
                if (modalEl.parentElement !== document.body) {
                    document.body.appendChild(modalEl);
                }

                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }

            // Build calendar grid HTML for a single medication (kept for compatibility)
            function buildMedicationCalendarGrid(prescription, schedules, allAdministrations, startDate, endDate) {
                // Filter administrations for this prescription
                const administrations = (allAdministrations || []).filter(a =>
                    a.product_or_service_request_id === prescription.id
                );

                let html = '';

                // Weekday header
                html += '<div class="calendar-weekday-header">';
                ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
                    html += `<div class="weekday-name">${day}</div>`;
                });
                html += '</div>';

                // Calendar grid
                html += '<div class="medication-calendar-grid">';

                // Add empty cells for alignment
                const daysBeforeStart = startDate.getDay();
                for (let i = 0; i < daysBeforeStart; i++) {
                    html += '<div class="calendar-day-cell empty-day"></div>';
                }

                // Today reference
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                // Generate days
                const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                let currentDate = new Date(startDate);

                while (currentDate <= endDate) {
                    const dateStr = currentDate.toISOString().split('T')[0];
                    const dayNum = currentDate.getDate();
                    const dayOfWeek = currentDate.getDay();
                    const isToday = currentDate.toDateString() === today.toDateString();
                    const isPast = currentDate < today;
                    const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;

                    // Build cell classes
                    let cellClass = 'calendar-day-cell';
                    if (isToday) cellClass += ' today';
                    else if (isPast) cellClass += ' past-date';
                    if (isWeekend && !isToday) cellClass += ' weekend';

                    // Find schedules for this day
                    const daySchedules = schedules.filter(s => {
                        if (!s.scheduled_time && !s.scheduled_at) return false;
                        const schedTime = s.scheduled_time || s.scheduled_at;
                        const schedDate = new Date(schedTime);
                        return schedDate.toDateString() === currentDate.toDateString();
                    });

                    // Find administrations for this day
                    const dayAdministrations = administrations.filter(a => {
                        if (!a.administered_at) return false;
                        const adminDate = new Date(a.administered_at);
                        return adminDate.toDateString() === currentDate.toDateString();
                    });

                    html += `<div class="${cellClass}">`;
                    html += `<div class="day-header">
                        <span class="day-name">${dayNames[dayOfWeek]}</span>
                        <span class="day-number">${dayNum}</span>
                    </div>`;
                    html += '<div class="schedule-items">';

                    if (daySchedules.length === 0 && dayAdministrations.length === 0) {
                        html += '<span class="no-schedules">-</span>';
                    } else {
                        // Render scheduled doses
                        daySchedules.forEach(schedule => {
                            const schedTime = schedule.scheduled_time || schedule.scheduled_at;
                            const time = new Date(schedTime).toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            });

                            // Check if administered
                            const admin = dayAdministrations.find(a => a.schedule_id === schedule.id);

                            // Check if discontinued
                            const isDiscontinued = prescription.discontinued_at &&
                                new Date(prescription.discontinued_at) < new Date(schedTime);

                            let badgeClass = 'scheduled';
                            let icon = 'mdi-clock-outline';
                            let tooltip = `Scheduled: ${time}<br>Dose: ${schedule.dose || 'N/A'}<br>Route: ${schedule.route || 'N/A'}`;

                            if (isDiscontinued) {
                                badgeClass = 'discontinued';
                                icon = 'mdi-close-circle-outline';
                                tooltip = `Discontinued<br>Time: ${time}`;
                            } else if (admin) {
                                badgeClass = 'given';
                                icon = 'mdi-check-circle';
                                const adminTime = new Date(admin.administered_at).toLocaleTimeString('en-US', {
                                    hour: 'numeric',
                                    minute: '2-digit',
                                    hour12: true
                                });
                                tooltip = `Given at ${adminTime}<br>Dose: ${admin.dose || schedule.dose || 'N/A'}<br>By: ${admin.administered_by_name || 'Unknown'}<br>Store: ${admin.store_name || 'N/A'}`;
                            } else if (isPast) {
                                badgeClass = 'missed';
                                icon = 'mdi-alert-circle';
                                tooltip = `Missed<br>Scheduled: ${time}<br>Dose: ${schedule.dose || 'N/A'}`;
                            }

                            html += `<div class="schedule-badge ${badgeClass}" data-bs-toggle="tooltip" data-bs-html="true" title="${tooltip}">
                                <i class="mdi ${icon}"></i> ${time}
                            </div>`;
                        });

                        // Render PRN (unscheduled) administrations
                        dayAdministrations.filter(a => !a.schedule_id).forEach(admin => {
                            const adminTime = new Date(admin.administered_at).toLocaleTimeString('en-US', {
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            });
                            const tooltip = `PRN Given at ${adminTime}<br>Dose: ${admin.dose || 'N/A'}<br>By: ${admin.administered_by_name || 'Unknown'}<br>Store: ${admin.store_name || 'N/A'}`;

                            html += `<div class="schedule-badge prn" data-bs-toggle="tooltip" data-bs-html="true" title="${tooltip}">
                                <i class="mdi mdi-plus-circle"></i> ${adminTime}
                            </div>`;
                        });
                    }

                    html += '</div></div>';

                    // Next day
                    currentDate.setDate(currentDate.getDate() + 1);
                }

                html += '</div>';

                return html;
            }

            // Function to load intake/output charts
            function loadIntakeOutputCharts(patientId, startDate, endDate) {
                // Show loading indicators
                const fluidContainer = document.getElementById('fluid-io-chart-content');
                const solidContainer = document.getElementById('solid-io-chart-content');

                const loadingHtml = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading data...</p>
                    </div>
                `;

                fluidContainer.innerHTML = loadingHtml;
                solidContainer.innerHTML = loadingHtml;

                // Build URL with query parameters
                const url = new URL(`{{ url('/') }}/patients/${patientId}/nurse-chart/intake-output`);
                if (startDate) url.searchParams.append('start_date', formatDateForApi(startDate));
                if (endDate) url.searchParams.append('end_date', formatDateForApi(endDate));

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        // Process fluid periods
                        renderIntakeOutputChart(data.fluidPeriods, 'fluid-io-chart-content', 'Fluid');

                        // Process solid periods
                        renderIntakeOutputChart(data.solidPeriods, 'solid-io-chart-content', 'Solid');

                        // Count total I/O records
                        let totalRecords = 0;
                        let totalPeriods = 0;

                        if (data.fluidPeriods) {
                            totalPeriods += data.fluidPeriods.length;
                            data.fluidPeriods.forEach(period => {
                                if (period.records) totalRecords += period.records.length;
                            });
                        }

                        if (data.solidPeriods) {
                            totalPeriods += data.solidPeriods.length;
                            data.solidPeriods.forEach(period => {
                                if (period.records) totalRecords += period.records.length;
                            });
                        }

                        // Add to existing summary stats
                        const statsContainer = document.getElementById('data-summary-stats');
                        statsContainer.innerHTML += `
                            <span class="badge bg-warning rounded-pill fs-6">
                                <i class="mdi mdi-water me-1"></i> ${totalRecords} I/O records
                            </span>
                        `;
                    })
                    .catch(error => {
                        console.error('Error loading intake/output charts:', error);
                        document.getElementById('fluid-io-chart-content').innerHTML =
                            '<div class="alert alert-danger">Failed to load fluid intake/output data. Please try again later.</div>';
                        document.getElementById('solid-io-chart-content').innerHTML =
                            '<div class="alert alert-danger">Failed to load solid intake/output data. Please try again later.</div>';
                    });
            }

            // Helper function to render intake/output chart
            function renderIntakeOutputChart(periods, containerId, type) {
                const container = document.getElementById(containerId);

                // Sort periods by started_at, newest first
                const sortedPeriods = [...periods].sort((a, b) => {
                    return new Date(b.started_at) - new Date(a.started_at);
                });

                if (sortedPeriods.length === 0) {
                    container.innerHTML = `<div class="alert alert-info">No ${type.toLowerCase()} intake/output records found</div>`;
                    return;
                }

                let html = '';

                // Add legend
                html += `<div class="card-modern mb-2">
                    <div class="card-body p-2">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <small class="text-muted me-2">Legend:</small>
                            <span class="badge bg-primary rounded-pill d-flex align-items-center">
                                <i class="mdi mdi-arrow-down-bold me-1"></i> Intake
                            </span>
                            <span class="badge bg-warning rounded-pill d-flex align-items-center">
                                <i class="mdi mdi-arrow-up-bold me-1"></i> Output
                            </span>
                            <span class="badge bg-success rounded-pill d-flex align-items-center">
                                <i class="mdi mdi-clock-start me-1"></i> Active
                            </span>
                            <span class="badge bg-secondary rounded-pill d-flex align-items-center">
                                <i class="mdi mdi-clock-end me-1"></i> Ended
                            </span>
                        </div>
                    </div>
                </div>`;

                // Process each period
                sortedPeriods.forEach((period, index) => {
                    const isActive = !period.ended_at;
                    const startTime = new Date(period.started_at).toLocaleString();
                    const endTime = period.ended_at ? new Date(period.ended_at).toLocaleString() : 'Ongoing';

                    // Calculate totals
                    let intakeTotal = 0;
                    let outputTotal = 0;

                    // Sort records by created_at, newest first
                    const sortedRecords = [...period.records].sort((a, b) => {
                        return new Date(b.created_at) - new Date(a.created_at);
                    });

                    sortedRecords.forEach(record => {
                        if (record.type === 'intake') {
                            intakeTotal += parseFloat(record.amount);
                        } else {
                            outputTotal += parseFloat(record.amount);
                        }
                    });

                    const balance = intakeTotal - outputTotal;
                    const balanceClass = balance > 0 ? 'text-success' : (balance < 0 ? 'text-danger' : 'text-muted');

                    // Period card
                    html += `<div class="card-modern shadow-sm mb-3 period-card">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">
                                    Period ${isActive ?
                                        '<span class="badge bg-success ms-1">Active</span>' :
                                        '<span class="badge bg-secondary ms-1">Ended</span>'
                                    }
                                </h6>
                                <small class="text-muted">Started: ${startTime}</small>
                                ${period.ended_at ? `<br><small class="text-muted">Ended: ${endTime}</small>` : ''}
                            </div>
                            <div class="text-end">
                                <span class="fw-bold">Nurse: ${period.nurse_name}</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Time</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Amount</th>
                                            <th>Recorded By</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;

                    if (sortedRecords.length > 0) {
                        sortedRecords.forEach(record => {
                            const recordTime = new Date(record.created_at).toLocaleString();
                            const recordType = record.type === 'intake' ? 'Intake' : 'Output';
                            const typeClass = record.type === 'intake' ? 'bg-primary' : 'bg-warning';
                            const typeIcon = record.type === 'intake' ? 'mdi-arrow-down-bold' : 'mdi-arrow-up-bold';

                            html += `<tr>
                                <td>${recordTime}</td>
                                <td><span class="badge ${typeClass} rounded-pill"><i class="mdi ${typeIcon} me-1"></i> ${recordType}</span></td>
                                <td>${record.description || 'N/A'}</td>
                                <td>${record.amount} ${record.unit || ''}</td>
                                <td>${record.nurse_name}</td>
                            </tr>`;
                        });
                    } else {
                        html += `<tr><td colspan="5" class="text-center">No records found for this period</td></tr>`;
                    }

                    // Add balance row
                    html += `</tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-light">
                                <div class="row">
                                    <div class="col-md-4">
                                        <span class="text-primary fw-bold">Total Intake: ${intakeTotal} ${type === 'Fluid' ? 'ml' : 'g'}</span>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-warning fw-bold">Total Output: ${outputTotal} ${type === 'Fluid' ? 'ml' : 'g'}</span>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <span class="${balanceClass} fw-bold">Balance: ${balance} ${type === 'Fluid' ? 'ml' : 'g'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;

                    // Add a separator between periods
                    if (index < sortedPeriods.length - 1) {
                        html += '<hr class="my-3 opacity-50">';
                    }
                });

                container.innerHTML = html;
            }
        });
    </script>

    <script>
        // AJAX Functions for Incremental Saving
        const encounterId = '{{ $encounter->id }}';
        const patientId = '{{ request()->get("patient_id") }}';
        const queueId = '{{ request()->get("queue_id") ?? "ward_round" }}';

        // Helper function to show messages
        function showMessage(elementId, message, type = 'success') {
            const element = document.getElementById(elementId);
            const typeMap = { success: 'alert-success', error: 'alert-danger', warning: 'alert-warning', info: 'alert-info' };
            const alertClass = typeMap[type] || 'alert-info';
            element.innerHTML = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
            // Scroll the message into view
            element.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setTimeout(() => { element.innerHTML = ''; }, 5000);
        }

        // Helper function to disable/enable button
        function setButtonLoading(buttonId, loading) {
            const btn = document.getElementById(buttonId);
            if (loading) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-save"></i> Save & Next';
            }
        }

        // Save Diagnosis
        function saveDiagnosis(showModal = true) {
            setButtonLoading('save_diagnosis_btn', true);

            // Get diagnosis from CKEditor if available, otherwise from textarea
            let diagnosisText = '';
            if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances['doctor_diagnosis_text']) {
                diagnosisText = CKEDITOR.instances['doctor_diagnosis_text'].getData();
            } else {
                diagnosisText = $('#doctor_diagnosis_text').val();
            }

            const formData = new FormData();
            formData.append('doctor_diagnosis', diagnosisText);

            @if(appsettings('requirediagnosis', 0))
            // Check if diagnosis is applicable
            const diagnosisApplicable = $('#diagnosisApplicable').is(':checked');
            formData.append('diagnosis_applicable', diagnosisApplicable ? '1' : '0');

            if (diagnosisApplicable) {
                // Get selected reasons from the new AJAX search component
                const reasonsData = $('#reasons_for_encounter_data').val();
                let parsedReasons = [];

                try {
                    parsedReasons = JSON.parse(reasonsData);
                } catch (e) {
                    console.error('Error parsing reasons data:', e);
                }

                if (!parsedReasons || parsedReasons.length === 0) {
                    showMessage('diagnosis_save_message', 'Please select at least one diagnosis reason or toggle off "Diagnosis Applicable"', 'error');
                    setButtonLoading('save_diagnosis_btn', false);
                    return;
                }

                // Send reasons as values (code-name format) for backward compat
                parsedReasons.forEach(reason => {
                    formData.append('reasons_for_encounter[]', reason.value);
                });

                // Send per-diagnosis comments as JSON
                const perDiagnosisComments = parsedReasons.map(r => ({
                    code: r.code || '',
                    name: r.name || '',
                    value: r.value || '',
                    comment_1: r.comment_1 || 'NA',
                    comment_2: r.comment_2 || 'NA'
                }));
                formData.append('per_diagnosis_comments', JSON.stringify(perDiagnosisComments));

                // Legacy global comments (first diagnosis values or NA)
                formData.append('reasons_for_encounter_comment_1', parsedReasons[0]?.comment_1 || 'NA');
                formData.append('reasons_for_encounter_comment_2', parsedReasons[0]?.comment_2 || 'NA');
            }
            @endif

            $.ajax({
                url: `/encounters/${encounterId}/save-diagnosis`,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    showMessage('diagnosis_save_message', response.message, 'success');
                    updateSummary();

                    // Reload encounter history DataTable if it exists
                    if ($.fn.DataTable.isDataTable('#encounter_history_list')) {
                        $('#encounter_history_list').DataTable().ajax.reload(null, false);
                    }

                    // Show conclusion modal after successful save if requested
                    if (showModal) {
                        setTimeout(() => {
                            $('#concludeEncounterModal').modal('show');
                        }, 500);
                    }
                },
                error: function(xhr) {
                    let message = 'Error saving diagnosis';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.errors) {
                            message = Object.values(xhr.responseJSON.errors).flat().join(', ');
                        } else if (xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                    }
                    showMessage('diagnosis_save_message', message, 'error');
                },
                complete: function() {
                    setButtonLoading('save_diagnosis_btn', false);
                }
            });
        }

        function saveDiagnosisAndNext() {
            saveDiagnosis(false);
            setTimeout(() => $('#laboratory_services_tab').click(), 800);
        }

        // Save Labs
        function saveLabs() {
            const services = [];
            const notes = [];
            $('#selected-services tr').each(function() {
                const serviceId = $(this).find('input[name="consult_invest_id[]"]').val();
                const note = $(this).find('input[name="consult_invest_note[]"]').val();
                if (serviceId) {
                    services.push(serviceId);
                    notes.push(note || '');
                }
            });

            if (services.length === 0) {
                showMessage('labs_save_message', 'No lab services selected', 'error');
                return;
            }

            setButtonLoading('save_labs_btn', true);

            $.ajax({
                url: `/encounters/${encounterId}/save-labs`,
                method: 'POST',
                data: {
                    consult_invest_id: services,
                    consult_invest_note: notes,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    showMessage('labs_save_message', response.message, 'success');
                    updateSummary();
                    // Clear selected list
                    $('#selected-services').empty();
                    // Reload history DataTable
                    if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
                        $('#investigation_history_list').DataTable().ajax.reload();
                    }
                    // Switch to history tab
                    try { new bootstrap.Tab(document.getElementById('lab-history-tab')).show(); } catch(e) { $('#lab-history-tab').tab('show'); }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Error saving lab requests';
                    showMessage('labs_save_message', message, 'error');
                },
                complete: function() {
                    setButtonLoading('save_labs_btn', false);
                }
            });
        }

        function saveLabsAndNext() {
            saveLabs();
            setTimeout(() => $('#imaging_services_tab').click(), 800);
        }

        // Save Imaging
        function saveImaging() {
            const services = [];
            const notes = [];
            $('#selected-imaging-services tr').each(function() {
                const serviceId = $(this).find('input[name="consult_imaging_id[]"]').val();
                const note = $(this).find('input[name="consult_imaging_note[]"]').val();
                if (serviceId) {
                    services.push(serviceId);
                    notes.push(note || '');
                }
            });

            if (services.length === 0) {
                showMessage('imaging_save_message', 'No imaging services selected', 'error');
                return;
            }

            setButtonLoading('save_imaging_btn', true);

            $.ajax({
                url: `/encounters/${encounterId}/save-imaging`,
                method: 'POST',
                data: {
                    consult_imaging_id: services,
                    consult_imaging_note: notes,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    showMessage('imaging_save_message', response.message, 'success');
                    updateSummary();
                    // Clear selected list
                    $('#selected-imaging-services').empty();
                    // Reload history DataTable
                    if ($.fn.DataTable.isDataTable('#imaging_history_list')) {
                        $('#imaging_history_list').DataTable().ajax.reload();
                    }
                    // Switch to history tab
                    try { new bootstrap.Tab(document.getElementById('imaging-history-tab')).show(); } catch(e) { $('#imaging-history-tab').tab('show'); }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Error saving imaging requests';
                    showMessage('imaging_save_message', message, 'error');
                },
                complete: function() {
                    setButtonLoading('save_imaging_btn', false);
                }
            });
        }

        function saveImagingAndNext() {
            saveImaging();
            setTimeout(() => $('#medications_tab').click(), 800);
        }

        // Save Prescriptions
        function savePrescriptions() {
            const products = [];
            const doses = [];
            let hasEmptyDose = false;

            $('#selected-products tr').each(function() {
                const productId = $(this).find('input[name="consult_presc_id[]"]').val();
                // Try structured hidden input first, fallback to text input
                let dose = $(this).find('.structured-dose-value').val();
                if (dose === undefined || dose === null) {
                    dose = $(this).find('input[name="consult_presc_dose[]"]').val();
                }
                if (productId) {
                    products.push(productId);
                    doses.push(dose || '');
                    if (!dose || dose.trim() === '') {
                        hasEmptyDose = true;
                    }
                }
            });

            if (products.length === 0) {
                showMessage('prescriptions_save_message', 'No prescriptions selected', 'error');
                return;
            }

            if (hasEmptyDose) {
                if (!confirm('Some prescriptions have empty dosage fields. Do you want to continue?')) {
                    return;
                }
            }

            setButtonLoading('save_prescriptions_btn', true);

            $.ajax({
                url: `/encounters/${encounterId}/save-prescriptions`,
                method: 'POST',
                data: {
                    consult_presc_id: products,
                    consult_presc_dose: doses,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    const msgType = response.empty_doses && response.empty_doses.length > 0 ? 'warning' : 'success';
                    showMessage('prescriptions_save_message', response.message, msgType);
                    updateSummary();
                    // Clear selected list
                    $('#selected-products').empty();
                    // Reload history DataTable
                    if ($.fn.DataTable.isDataTable('#presc_history_list')) {
                        $('#presc_history_list').DataTable().ajax.reload();
                    }
                    // Switch to history tab
                    try { new bootstrap.Tab(document.getElementById('presc-history-tab')).show(); } catch(e) { $('#presc-history-tab').tab('show'); }
                },
                error: function(xhr) {
                    let message = 'Error saving prescriptions';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.errors) {
                            message = Object.values(xhr.responseJSON.errors).flat().join(', ');
                        } else if (xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }
                    }
                    showMessage('prescriptions_save_message', message, 'error');
                },
                complete: function() {
                    setButtonLoading('save_prescriptions_btn', false);
                }
            });
        }

        function savePrescriptionsAndNext() {
            savePrescriptions();
            setTimeout(() => $('#admissions_tab').click(), 800);
        }

        // Finalize Encounter
        function finalizeEncounter() {
            if (!confirm('Are you sure you want to complete this encounter?')) {
                return;
            }

            const btn = document.getElementById('finalize_encounter_btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Completing...';

            $.ajax({
                url: `/encounters/${encounterId}/finalize`,
                method: 'POST',
                data: {
                    end_consultation: $('#end_consultation').is(':checked') ? 1 : 0,
                    consult_admit: $('#consult_admit').is(':checked') ? 1 : 0,
                    admit_note: $('#admit_note').val(),
                    queue_id: queueId,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    showMessage('finalize_message', response.message, 'success');
                    setTimeout(() => {
                        window.location.href = response.redirect;
                    }, 1500);
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Error completing encounter';
                    showMessage('finalize_message', message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-check-circle"></i> Complete Encounter';
                }
            });
        }

        // Update Summary
        function updateSummary() {
            // Fetch real encounter data from database
            $.ajax({
                url: `/encounters/${encounterId}/summary`,
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        const data = response.data;

                        // Update diagnosis summary
                        if (data.diagnosis.saved) {
                            const notesPreview = data.diagnosis.notes ?
                                (data.diagnosis.notes.substring(0, 100) + (data.diagnosis.notes.length > 100 ? '...' : '')) :
                                'Saved';
                            $('#summary_diagnosis').html(`
                                <span class="text-success"><i class="fa fa-check-circle"></i> <strong>Saved</strong></span>
                                <br><small>${notesPreview}</small>
                            `);
                        } else {
                            $('#summary_diagnosis').html(`<span class="text-muted">Not saved yet</span>`);
                        }

                        // Update labs summary
                        if (data.labs.length > 0) {
                            let labsHtml = `<span class="badge bg-success mb-2">${data.labs.length} service(s)</span><br>`;
                            labsHtml += '<ul class="small mb-0 ps-3">';
                            data.labs.forEach(lab => {
                                labsHtml += `<li>${lab.name} ${lab.code ? '[' + lab.code + ']' : ''}</li>`;
                            });
                            labsHtml += '</ul>';
                            $('#summary_labs').html(labsHtml);
                        } else {
                            $('#summary_labs').html(`<span class="text-muted">None selected</span>`);
                        }

                        // Update imaging summary
                        if (data.imaging.length > 0) {
                            let imagingHtml = `<span class="badge bg-success mb-2">${data.imaging.length} service(s)</span><br>`;
                            imagingHtml += '<ul class="small mb-0 ps-3">';
                            data.imaging.forEach(img => {
                                imagingHtml += `<li>${img.name} ${img.code ? '[' + img.code + ']' : ''}</li>`;
                            });
                            imagingHtml += '</ul>';
                            $('#summary_imaging').html(imagingHtml);
                        } else {
                            $('#summary_imaging').html(`<span class="text-muted">None selected</span>`);
                        }

                        // Update prescriptions summary
                        if (data.prescriptions.length > 0) {
                            let prescHtml = `<span class="badge bg-success mb-2">${data.prescriptions.length} medication(s)</span><br>`;
                            prescHtml += '<ul class="small mb-0 ps-3">';
                            data.prescriptions.forEach(presc => {
                                prescHtml += `<li>${presc.name}${presc.dose ? ' - ' + presc.dose : ''}</li>`;
                            });
                            prescHtml += '</ul>';
                            $('#summary_prescriptions').html(prescHtml);
                        } else {
                            $('#summary_prescriptions').html(`<span class="text-muted">None selected</span>`);
                        }
                    }
                },
                error: function(xhr) {
                    console.error('Error loading encounter summary:', xhr);
                }
            });
        }

        // Toggle admit note section
        function toggleAdmitNote() {
            const admitChecked = $('#consult_admit').is(':checked');
            $('#admit_note_section').toggle(admitChecked);
        }

        // Initialize summary on page load
        $(document).ready(function() {
            // Don't load on page load, only when modal is opened
        });
    </script>

    <!-- Unified Admission/Discharge Modal -->
    <div class="modal fade" id="admitDischargeModal" tabindex="-1" aria-labelledby="admitDischargeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background-color: {{ appsettings('hos_color', '#007bff') }}; color: white;">
                    <h5 class="modal-title" id="admitDischargeModalLabel">
                        <i class="fa fa-bed" id="modal_icon"></i> <span id="modal_title_text">Admit Patient</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> <strong>Patient:</strong> {{ $patient->surname }} {{ $patient->first_name }} {{ $patient->other_names }}
                    </div>

                    <form id="admitDischargeForm">
                        @csrf
                        <input type="hidden" name="action" id="modal_action" value="admit">
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        <input type="hidden" name="encounter_id" value="{{ $encounter->id }}">
                        <input type="hidden" name="doctor_id" value="{{ auth()->user()->id }}">
                        @if (isset($admission_request))
                            <input type="hidden" name="admission_request_id" value="{{ $admission_request->id }}">
                        @endif

                        <!-- Admission Section -->
                        <div id="admission_section">
                            <div class="form-group mb-3">
                                <label for="admission_reason_category"><strong>Admission Reason Category</strong> <span class="text-danger">*</span></label>
                                <select class="form-control" name="admission_reason" id="admission_reason_category">
                                    <option value="">-- Select Reason --</option>
                                    <option value="Acute illness or injury">Acute Illness or Injury</option>
                                    <option value="Chronic condition management">Chronic Condition Management</option>
                                    <option value="Post-surgical care">Post-Surgical Care</option>
                                    <option value="Diagnostic workup">Diagnostic Workup</option>
                                    <option value="Maternal care">Maternal Care (Obstetrics)</option>
                                    <option value="Neonatal care">Neonatal Care</option>
                                    <option value="Mental health crisis">Mental Health Crisis</option>
                                    <option value="Palliative or end-of-life care">Palliative or End-of-Life Care</option>
                                    <option value="Rehabilitation">Rehabilitation</option>
                                    <option value="Observation">Observation</option>
                                    <option value="Social or safeguarding reasons">Social or Safeguarding Reasons</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="form-group mb-3">
                                <label for="admit_note"><strong>Detailed Admission Notes</strong> <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="note" id="admit_note" rows="4"
                                          placeholder="Enter detailed clinical notes, diagnosis, and special care instructions..."></textarea>
                            </div>

                            <!-- Ward Availability Section -->
                            <div class="mb-3">
                                <label class="mb-2"><strong><i class="fa fa-hospital"></i> Ward Availability</strong></label>
                                <div id="ward_availability_container" class="border rounded p-2" style="max-height: 280px; overflow-y: auto;">
                                    <div class="text-center text-muted py-3" id="ward_loading">
                                        <i class="fa fa-spinner fa-spin"></i> Loading ward availability...
                                    </div>
                                </div>
                                <input type="hidden" name="preferred_ward_id" id="preferred_ward_id" value="">
                                <small class="text-muted">
                                    <i class="fa fa-info-circle"></i> Ward preference is optional. Bed will be assigned by nursing staff.
                                </small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="admit_priority"><strong>Priority</strong></label>
                                        <select class="form-control" name="priority" id="admit_priority">
                                            <option value="routine">Routine</option>
                                            <option value="urgent">Urgent</option>
                                            <option value="emergency">Emergency</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Discharge Section -->
                        <div id="discharge_section" style="display: none;">
                            <div class="form-group mb-3">
                                <label for="discharge_reason_category"><strong>Discharge Reason</strong> <span class="text-danger">*</span></label>
                                <select class="form-control" name="discharge_reason" id="discharge_reason_category">
                                    <option value="">-- Select Reason --</option>
                                    <option value="Discharged to home">Discharged to Home (Recovered)</option>
                                    <option value="Discharged improved">Discharged Improved (Ongoing Care at Home)</option>
                                    <option value="Discharged against medical advice">Discharged Against Medical Advice (AMA)</option>
                                    <option value="Transfer to another facility">Transfer to Another Facility</option>
                                    <option value="Transfer to higher level of care">Transfer to Higher Level of Care</option>
                                    <option value="Absconded">Absconded (Left Without Notice)</option>
                                    <option value="Deceased">Deceased</option>
                                    <option value="Discharged for financial reasons">Discharged for Financial Reasons</option>
                                    <option value="Discharged for end-of-life care">Discharged for End-of-Life Care (Hospice)</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="form-group mb-3">
                                <label for="discharge_note"><strong>Discharge Summary</strong> <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="discharge_note" id="discharge_note" rows="5"
                                          placeholder="Enter discharge summary, condition at discharge, medications, follow-up instructions..."></textarea>
                            </div>

                            <div class="form-group mb-3">
                                <label for="discharge_followup"><strong>Follow-up Instructions</strong></label>
                                <textarea class="form-control" name="followup_instructions" id="discharge_followup" rows="2"
                                          placeholder="Next appointment, medication refills, warning signs to watch for..."></textarea>
                            </div>
                        </div>

                        <div class="alert alert-warning" id="modal_warning">
                            <i class="fa fa-exclamation-triangle"></i> <strong>Note:</strong> <span id="warning_text">This will create an admission request.</span>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary btn-lg" onclick="submitAdmitDischarge()" id="modal_submit_btn">
                        <i class="fa fa-bed" id="btn_icon"></i> <span id="btn_text">Submit Admission</span>
                    </button>
                </div>
                <div id="modal_message" class="px-3 pb-3"></div>
            </div>
        </div>
    </div>

    <!-- Conclude Encounter Modal -->
    <div class="modal fade" id="concludeEncounterModal" tabindex="-1" aria-labelledby="concludeEncounterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header text-white" style="background-color: {{ appsettings('hos_color', '#007bff') }};">
                    <h5 class="modal-title" id="concludeEncounterModalLabel">
                        <i class="fa fa-check-circle"></i> Conclude Encounter
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Encounter Summary -->
                    <div class="mb-4">
                        <h6 style="color: {{ appsettings('hos_color', '#007bff') }};"><i class="fa fa-info-circle"></i> Encounter Summary</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card-modern summary-card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title" style="color: {{ appsettings('hos_color', '#007bff') }};"><i class="fa fa-stethoscope"></i> Diagnosis & Notes</h6>
                                        <p class="card-text" id="modal_summary_diagnosis">
                                            <span class="text-muted">None selected</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card-modern summary-card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title" style="color: {{ appsettings('hos_color', '#007bff') }};"><i class="fa fa-flask"></i> Lab Requests</h6>
                                        <p class="card-text" id="modal_summary_labs">
                                            <span class="text-muted">None selected</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card-modern summary-card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title" style="color: {{ appsettings('hos_color', '#007bff') }};"><i class="fa fa-x-ray"></i> Imaging Requests</h6>
                                        <p class="card-text" id="modal_summary_imaging">
                                            <span class="text-muted">None selected</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card-modern summary-card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title" style="color: {{ appsettings('hos_color', '#007bff') }};"><i class="fa fa-pills"></i> Prescriptions</h6>
                                        <p class="card-text" id="modal_summary_prescriptions">
                                            <span class="text-muted">None selected</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <strong>Note:</strong> Clicking "Complete Encounter" will finalize this consultation.
                        Make sure all information is correct before proceeding.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-lg text-white" style="background-color: {{ appsettings('hos_color', '#007bff') }};" onclick="finalizeEncounterFromModal()" id="modal_finalize_encounter_btn">
                        <i class="fa fa-check-circle"></i> Complete Encounter
                    </button>
                </div>
                <div id="modal_finalize_message" class="px-3 pb-3"></div>
            </div>
        </div>
    </div>

    <style>
    .summary-card {
        border-left: 4px solid {{ appsettings('hos_color', '#007bff') }};
    }

    /* Toggle Switch Styles */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .toggle-slider {
        background-color: {{ appsettings('hos_color', '#007bff') }};
    }

    input:focus + .toggle-slider {
        box-shadow: 0 0 1px {{ appsettings('hos_color', '#007bff') }};
    }

    input:checked + .toggle-slider:before {
        transform: translateX(26px);
    }
    </style>

    <script>
    // ─── Ward Availability for Admission Modal ───
    function loadWardAvailability() {
        const container = document.getElementById('ward_availability_container');
        container.innerHTML = '<div class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i> Loading ward availability...</div>';

        $.ajax({
            url: '{{ route("ward-availability") }}',
            method: 'GET',
            success: function(wards) {
                if (!wards || wards.length === 0) {
                    container.innerHTML = '<div class="text-center text-muted py-3"><i class="fa fa-info-circle"></i> No active wards configured.</div>';
                    return;
                }

                let html = '<div class="list-group list-group-flush">';

                // "No preference" option (default selected)
                html += `
                    <label class="list-group-item list-group-item-action d-flex align-items-center p-2 ward-option" style="cursor:pointer;">
                        <input type="radio" name="ward_radio" value="" checked class="me-2 ward-radio" onchange="selectWardPreference('')">
                        <div class="flex-grow-1">
                            <span class="fw-bold text-muted"><i class="fa fa-globe"></i> No ward preference</span>
                            <small class="d-block text-muted">Bed will be assigned by nursing staff</small>
                        </div>
                    </label>`;

                wards.forEach(function(ward) {
                    const pct = ward.occupancy_pct;
                    let barColor = pct >= 90 ? '#dc3545' : (pct >= 70 ? '#ffc107' : '#28a745');
                    let textColor = pct >= 90 ? 'text-danger' : (pct >= 70 ? 'text-warning' : 'text-success');
                    let typeIcons = {
                        'general': 'fa-bed', 'icu': 'fa-heartbeat', 'pediatric': 'fa-child',
                        'maternity': 'fa-baby', 'emergency': 'fa-ambulance', 'psychiatric': 'fa-brain',
                        'isolation': 'fa-shield-alt', 'recovery': 'fa-procedures', 'private': 'fa-star',
                        'other': 'fa-hospital'
                    };
                    let icon = typeIcons[ward.type] || 'fa-hospital';
                    let isDisabled = ward.available_beds === 0;

                    html += `
                        <label class="list-group-item list-group-item-action d-flex align-items-center p-2 ward-option ${isDisabled ? 'opacity-50' : ''}" style="cursor:${isDisabled ? 'not-allowed' : 'pointer'};">
                            <input type="radio" name="ward_radio" value="${ward.id}" class="me-2 ward-radio"
                                   ${isDisabled ? 'disabled' : ''}
                                   onchange="selectWardPreference('${ward.id}')">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">
                                        <i class="fa ${icon} me-1"></i> ${ward.name}
                                        <span class="badge bg-secondary ms-1" style="font-size:0.7em;">${ward.type_label}</span>
                                    </span>
                                    <span class="${textColor} fw-bold" style="font-size: 0.85em;">
                                        ${ward.available_beds}/${ward.total_beds} available
                                    </span>
                                </div>
                                <div class="progress mt-1" style="height: 6px;">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: ${pct}%; background-color: ${barColor};"
                                         aria-valuenow="${pct}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                ${ward.floor || ward.building ? `<small class="text-muted">${[ward.building, ward.floor].filter(Boolean).join(', ')}</small>` : ''}
                            </div>
                        </label>`;
                });

                html += '</div>';
                container.innerHTML = html;
            },
            error: function() {
                container.innerHTML = '<div class="text-center text-danger py-3"><i class="fa fa-exclamation-triangle"></i> Failed to load ward availability.</div>';
            }
        });
    }

    function selectWardPreference(wardId) {
        document.getElementById('preferred_ward_id').value = wardId;
    }

    // Open modal for admission request
    function openAdmitModal() {
        // Reset form
        document.getElementById('admitDischargeForm').reset();
        document.getElementById('modal_action').value = 'admit';
        document.getElementById('preferred_ward_id').value = '';

        // Update UI for admission request
        document.getElementById('modal_title_text').textContent = 'Request Patient Admission';
        document.getElementById('modal_icon').className = 'fa fa-bed';
        document.getElementById('btn_text').textContent = 'Submit Admission Request';
        document.getElementById('btn_icon').className = 'fa fa-bed';
        document.getElementById('modal_submit_btn').className = 'btn btn-info btn-lg text-white';
        document.getElementById('warning_text').textContent = 'This will create an admission request. Nursing staff will complete the admission checklist and assign a bed.';

        // Show/hide sections
        document.getElementById('admission_section').style.display = 'block';
        document.getElementById('discharge_section').style.display = 'none';

        // Load live ward availability
        loadWardAvailability();

        $('#admitDischargeModal').modal('show');
    }

    // Open modal for discharge request
    function openDischargeModal() {
        // Reset form
        document.getElementById('admitDischargeForm').reset();
        document.getElementById('modal_action').value = 'discharge';

        // Update UI for discharge request
        document.getElementById('modal_title_text').textContent = 'Request Patient Discharge';
        document.getElementById('modal_icon').className = 'fa fa-sign-out-alt';
        document.getElementById('btn_text').textContent = 'Submit Discharge Request';
        document.getElementById('btn_icon').className = 'fa fa-sign-out-alt';
        document.getElementById('modal_submit_btn').className = 'btn btn-warning btn-lg';
        document.getElementById('warning_text').textContent = 'This will create a discharge request. Nursing staff will complete the discharge checklist before releasing the bed.';

        // Show/hide sections
        document.getElementById('admission_section').style.display = 'none';
        document.getElementById('discharge_section').style.display = 'block';

        $('#admitDischargeModal').modal('show');
    }

    // Submit admission or discharge
    function submitAdmitDischarge() {
        const form = document.getElementById('admitDischargeForm');
        const btn = document.getElementById('modal_submit_btn');
        const action = document.getElementById('modal_action').value;

        // Validate based on action
        if (action === 'admit') {
            if (!document.getElementById('admission_reason_category').value) {
                showMessage('modal_message', 'Please select an admission reason category', 'error');
                return;
            }
            if (!document.getElementById('admit_note').value.trim()) {
                showMessage('modal_message', 'Please enter detailed admission notes', 'error');
                return;
            }
        } else if (action === 'discharge') {
            if (!document.getElementById('discharge_reason_category').value) {
                showMessage('modal_message', 'Please select a discharge reason', 'error');
                return;
            }
            if (!document.getElementById('discharge_note').value.trim()) {
                showMessage('modal_message', 'Please enter discharge summary', 'error');
                return;
            }
        }

        btn.disabled = true;
        btn.innerHTML = '<i class=\"fa fa-spinner fa-spin\"></i> Processing...';

        const formData = new FormData(form);

        // Determine endpoint
        const url = action === 'admit'
            ? '{{ route('admission-requests.store') }}'
            : '/discharge-patient-api/' + formData.get('admission_request_id');

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                const successMsg = action === 'admit'
                    ? 'Admission request submitted! Nursing staff will process the admission checklist.'
                    : 'Discharge request submitted! Nursing staff will complete the discharge checklist before releasing the bed.';
                showMessage('modal_message', response.message || successMsg, 'success');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Error processing request';
                showMessage('modal_message', message, 'error');
                btn.disabled = false;
                const originalText = action === 'admit' ? 'Submit Admission Request' : 'Submit Discharge Request';
                const originalIcon = action === 'admit' ? 'fa-bed' : 'fa-sign-out-alt';
                btn.innerHTML = `<i class=\"fa ${originalIcon}\"></i> ${originalText}`;
            }
        });
    }

    // Update modal summary when modal is opened
    $('#concludeEncounterModal').on('show.bs.modal', function() {
        updateModalSummary();
    });

    // Finalize encounter from modal
    function finalizeEncounterFromModal() {
        if (!confirm('Are you sure you want to complete this encounter?')) {
            return;
        }

        const btn = document.getElementById('modal_finalize_encounter_btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Completing...';

        $.ajax({
            url: `/encounters/${encounterId}/finalize`,
            method: 'POST',
            data: {
                end_consultation: 0,
                consult_admit: 0,
                admit_note: '',
                queue_id: queueId,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                showMessage('modal_finalize_message', response.message, 'success');
                setTimeout(() => {
                    window.location.href = response.redirect;
                }, 1500);
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Error completing encounter';
                showMessage('modal_finalize_message', message, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-check-circle"></i> Complete Encounter';
            }
        });
    }

    // Update modal summary (similar to updateSummary but targets modal elements)
    function updateModalSummary() {
        $.ajax({
            url: `/encounters/${encounterId}/summary`,
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const data = response.data;

                    // Update diagnosis summary
                    if (data.diagnosis.saved) {
                        const notesPreview = data.diagnosis.notes ?
                            (data.diagnosis.notes.substring(0, 100) + (data.diagnosis.notes.length > 100 ? '...' : '')) :
                            'Saved';
                        $('#modal_summary_diagnosis').html(`
                            <span class="text-success"><i class="fa fa-check-circle"></i> <strong>Saved</strong></span>
                            <br><small>${notesPreview}</small>
                        `);
                    } else {
                        $('#modal_summary_diagnosis').html(`<span class="text-muted">Not saved yet</span>`);
                    }

                    // Update labs summary
                    if (data.labs.count > 0) {
                        $('#modal_summary_labs').html(`
                            <span class="text-success"><i class="fa fa-check-circle"></i> ${data.labs.count} lab service(s) requested</span>
                        `);
                    } else {
                        $('#modal_summary_labs').html(`<span class="text-muted">None selected</span>`);
                    }

                    // Update imaging summary
                    if (data.imaging.count > 0) {
                        $('#modal_summary_imaging').html(`
                            <span class="text-success"><i class="fa fa-check-circle"></i> ${data.imaging.count} imaging service(s) requested</span>
                        `);
                    } else {
                        $('#modal_summary_imaging').html(`<span class="text-muted">None selected</span>`);
                    }

                    // Update prescriptions summary
                    if (data.prescriptions.count > 0) {
                        $('#modal_summary_prescriptions').html(`
                            <span class="text-success"><i class="fa fa-check-circle"></i> ${data.prescriptions.count} prescription(s) added</span>
                        `);
                    } else {
                        $('#modal_summary_prescriptions').html(`<span class="text-muted">None selected</span>`);
                    }
                }
            },
            error: function(xhr) {
                console.error('Error loading modal summary:', xhr);
            }
        });
    }
    </script>

    <script>
        $(function() {
            $('#admission-request-list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('patient-admission-requests-list', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                    data: "info",
                    name: "info",
                    orderable: false
                }],
                "paging": true
            });
        });
    </script>

    @include('admin.doctors.partials.report_builder')
    @include('admin.doctors.partials.modals')
    @include('admin.doctors.partials.scripts')
@endsection
