@extends('admin.layouts.app')
@section('title', 'Patients Profile')
@section('page_name', 'Patients')
@section('subpage_name', 'Show Patient')
@section('content')
<link rel="stylesheet" href="{{ asset("css/modern-forms.css") }}">
@php
    $section = request()->get('section');
@endphp

<div class="col-12">
    {{-- Workbench Patient Header --}}
    <div class="card-modern mb-3">
        <div class="card-header-modern">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h2 class="mb-1 font-weight-bold text-dark">
                        <i class="mdi mdi-account-circle-outline text-primary"></i>
                        {{ userfullname($user->id) }}
                    </h2>
                    <p class="text-muted mb-0">
                        <span class="mr-3"><i class="mdi mdi-identifier mr-1"></i>File No: <strong>{{ $patient->file_no ?? 'N/A' }}</strong></span>
                        <span class="mr-3"><i class="mdi mdi-gender-male-female mr-1"></i>{{ ucfirst($patient->gender ?? 'N/A') }}</span>
                        <span class="mr-3"><i class="mdi mdi-cake-variant mr-1"></i>DOB: {{ $patient->dob ?? 'N/A' }}</span>
                        @if($patient->hmo)
                            <span class="mr-3"><i class="mdi mdi-shield-check mr-1"></i>{{ $patient->hmo->name }}</span>
                        @endif
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('patient-services-rendered', ['patient_id' => $patient->id]) }}"
                       class="btn btn-outline-primary btn-sm" target="_blank">
                        <i class="mdi mdi-clipboard-list-outline mr-1"></i> Services Rendered
                    </a>
                    @php
                        $show1Workbenches = [
                            ['route' => 'reception.workbench',     'icon' => 'mdi-desktop-mac',             'label' => 'Reception'],
                            ['route' => 'billing.workbench',       'icon' => 'mdi-cash-multiple',           'label' => 'Billing'],
                            ['route' => 'pharmacy.workbench',      'icon' => 'mdi-pill',                    'label' => 'Pharmacy'],
                            ['route' => 'nursing-workbench.index', 'icon' => 'mdi-heart-pulse',             'label' => 'Nursing'],
                            ['route' => 'lab.workbench',           'icon' => 'mdi-flask-outline',           'label' => 'Lab'],
                            ['route' => 'imaging.workbench',       'icon' => 'mdi-image-filter-center-focus','label' => 'Imaging'],
                            ['route' => 'hmo.workbench',           'icon' => 'mdi-shield-account-outline',  'label' => 'HMO'],
                        ];
                    @endphp
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="mdi mdi-briefcase-outline mr-1"></i> Workbenches
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @foreach($show1Workbenches as $wb)
                                @if(\Route::has($wb['route']))
                                    <li>
                                        <a class="dropdown-item" href="{{ route($wb['route']) }}?patient_id={{ $patient->id }}">
                                            <i class="mdi {{ $wb['icon'] }} me-2"></i>{{ $wb['label'] }}
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                    <a href="{{ route('patient.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to Patients
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Nav tabs --}}
    <ul class="nav nav-tabs" id="patientTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ !$section || $section == 'patientInfo' ? 'active' : '' }}" id="patientInfo-tab" data-bs-toggle="tab" data-bs-target="#patientInfo" type="button" role="tab">
                <i class="fa fa-user me-1"></i> Patient Info
            </button>
        </li>
        @can('see-vitals')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'vitalsCardBody' ? 'active' : '' }}" id="vitals-tab" data-bs-toggle="tab" data-bs-target="#vitalsCardBody" type="button" role="tab">
                    <i class="fa fa-heartbeat me-1"></i> Vitals
                </button>
            </li>
        @endcan
        @can('see-nursing-notes')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'nurseChartCardBody' ? 'active' : '' }}" id="nurseChart-tab" data-bs-toggle="tab" data-bs-target="#nurseChartCardBody" type="button" role="tab">
                    <i class="fa fa-notes-medical me-1"></i> Nurse Chart
                </button>
            </li>
        @endcan
        @can('see-nursing-notes')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'injImmHistoryCardBody' ? 'active' : '' }}" id="injImmHistory-tab" data-bs-toggle="tab" data-bs-target="#injImmHistoryCardBody" type="button" role="tab">
                    <i class="fa fa-syringe me-1"></i> Inj/Imm History
                </button>
            </li>
        @endcan
        @can('see-accounts')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'accountsCardBody' ? 'active' : '' }}" id="accounts-tab" data-bs-toggle="tab" data-bs-target="#accountsCardBody" type="button" role="tab">
                    <i class="fa fa-money-bill-wave me-1"></i> Accounts
                </button>
            </li>
        @endcan
        @can('see-admissions')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'addmissionsCardBody' ? 'active' : '' }}" id="admissions-tab" data-bs-toggle="tab" data-bs-target="#addmissionsCardBody" type="button" role="tab">
                    <i class="fa fa-bed me-1"></i> Admission History
                </button>
            </li>
        @endcan
        @hasanyrole('ADMIN|SUPERADMIN|NURSE|DOCTOR|RECEPTIONIST')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'wardNotesCardBody' ? 'active' : '' }}" id="procedures-tab" data-bs-toggle="tab" data-bs-target="#wardNotesCardBody" type="button" role="tab">
                    <i class="fa fa-procedures me-1"></i> Procedure Notes
                </button>
            </li>
        @endhasanyrole
        @hasanyrole('ADMIN|SUPERADMIN|NURSE|DOCTOR|RECEPTIONIST')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'nurseingNotesCardBody' ? 'active' : '' }}" id="nursing-tab" data-bs-toggle="tab" data-bs-target="#nurseingNotesCardBody" type="button" role="tab">
                    <i class="fa fa-user-nurse me-1"></i> Nursing Notes
                </button>
            </li>
        @endhasanyrole
        @can('see-doctor-notes')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'doctorNotesCardBody' ? 'active' : '' }}" id="doctor-tab" data-bs-toggle="tab" data-bs-target="#doctorNotesCardBody" type="button" role="tab">
                    <i class="fa fa-stethoscope me-1"></i> Doctor Notes
                </button>
            </li>
        @endcan
        @hasanyrole('ADMIN|SUPERADMIN|NURSE|DOCTOR|RECEPTIONIST')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'prescriptionsNotesCardBody' ? 'active' : '' }}" id="prescriptions-tab" data-bs-toggle="tab" data-bs-target="#prescriptionsNotesCardBody" type="button" role="tab">
                    <i class="fa fa-pills me-1"></i> Prescriptions
                </button>
            </li>
        @endhasanyrole
        @can('see-investigations')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'investigationsCardBody' ? 'active' : '' }}" id="investigations-tab" data-bs-toggle="tab" data-bs-target="#investigationsCardBody" type="button" role="tab">
                    <i class="fa fa-flask me-1"></i> Investigations
                    <span class="badge bg-danger rounded-pill ms-1 lab-unviewed-badge" id="lab-unviewed-badge" style="display: none; font-size: 0.75rem; padding: 0.25em 0.6em;"></span>
                </button>
            </li>
        @endcan
        @can('see-investigations')
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $section == 'imagingCardBody' ? 'active' : '' }}" id="imaging-tab" data-bs-toggle="tab" data-bs-target="#imagingCardBody" type="button" role="tab">
                    <i class="fa fa-x-ray me-1"></i> Imaging
                    <span class="badge bg-danger rounded-pill ms-1 imaging-unviewed-badge" id="imaging-unviewed-badge" style="display: none; font-size: 0.75rem; padding: 0.25em 0.6em;"></span>
                </button>
            </li>
        @endcan
    </ul>

    {{-- Tab content --}}
    <div class="tab-content pt-3">
        <div class="tab-pane fade {{ !$section || $section == 'patientInfo' ? 'show active' : '' }}" id="patientInfo" role="tabpanel">
            <div class="card-modern">
                <div class="card-body">@include('admin.patients.partials.patient_info')</div>
            </div>
        </div>
        @can('see-vitals')
            <div class="tab-pane fade {{ $section == 'vitalsCardBody' ? 'show active' : '' }}" id="vitalsCardBody" role="tabpanel">
                <div class="mt-2">
                    @include('admin.partials.unified_vitals', ['patient' => $patient])
                </div>
            </div>
            @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var vitalsTab = document.getElementById('vitals-tab');
                    if(vitalsTab){
                         vitalsTab.addEventListener('shown.bs.tab', function (event) {
                            if(window.initUnifiedVitals) {
                                window.initUnifiedVitals({{ $patient->id }}, null, null, null, @json($dynamicRanges ?? []));
                            }
                        });
                         // Handle initial load if tab is active
                        if (vitalsTab.classList.contains('active')) {
                            if(window.initUnifiedVitals) {
                                window.initUnifiedVitals({{ $patient->id }}, null, null, null, @json($dynamicRanges ?? []));
                            }
                        }
                    }
                });
             </script>
             @endpush
        @endcan
        @can('see-nursing-notes')
            <div class="tab-pane fade {{ $section == 'nurseChartCardBody' ? 'show active' : '' }}" id="nurseChartCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.show1_nurse_chart')</div>
                </div>
            </div>
        @endcan
        @can('see-nursing-notes')
            <div class="tab-pane fade {{ $section == 'injImmHistoryCardBody' ? 'show active' : '' }}" id="injImmHistoryCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.injection_immunization_history', ['patient' => $patient])</div>
                </div>
            </div>
        @endcan
        @can('see-accounts')
            <div class="tab-pane fade {{ $section == 'accountsCardBody' ? 'show active' : '' }}" id="accountsCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.show1_accounts')</div>
                </div>
            </div>
        @endcan
        @can('see-admissions')
            <div class="tab-pane fade {{ $section == 'addmissionsCardBody' ? 'show active' : '' }}" id="addmissionsCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.show1_admissions')</div>
                </div>
            </div>
        @endcan
        @hasanyrole('ADMIN|SUPERADMIN|NURSE|DOCTOR|RECEPTIONIST')
            <div class="tab-pane fade {{ $section == 'wardNotesCardBody' ? 'show active' : '' }}" id="wardNotesCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.show1_procedures')</div>
                </div>
            </div>
        @endhasanyrole
        @hasanyrole('ADMIN|SUPERADMIN|NURSE|DOCTOR|RECEPTIONIST')
            <div class="tab-pane fade {{ $section == 'nurseingNotesCardBody' ? 'show active' : '' }}" id="nurseingNotesCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.show1_nursing_notes')</div>
                </div>
            </div>
        @endhasanyrole
        @can('see-doctor-notes')
            <div class="tab-pane fade {{ $section == 'doctorNotesCardBody' ? 'show active' : '' }}" id="doctorNotesCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.doctor_notes')</div>
                </div>
            </div>
        @endcan
        @hasanyrole('ADMIN|SUPERADMIN|NURSE|DOCTOR|RECEPTIONIST')
            <div class="tab-pane fade {{ $section == 'prescriptionsNotesCardBody' ? 'show active' : '' }}" id="prescriptionsNotesCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.show1_presc')</div>
                </div>
            </div>
        @endhasanyrole
        @can('see-investigations')
            <div class="tab-pane fade {{ $section == 'investigationsCardBody' ? 'show active' : '' }}" id="investigationsCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.show1_invest')</div>
                </div>
            </div>
        @endcan
        @can('see-investigations')
            <div class="tab-pane fade {{ $section == 'imagingCardBody' ? 'show active' : '' }}" id="imagingCardBody" role="tabpanel">
                <div class="card-modern">
                    <div class="card-body">@include('admin.patients.partials.show1_imaging')</div>
                </div>
            </div>
        @endcan
    </div>
</div>

@include('admin.patients.partials.modals')
@endsection



@section('scripts')
    <!-- jQuery (needed for product search & DataTables) -->
    <script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script>
    <script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
    <!-- Bootstrap 4 -->
    <script src="{{ asset('/plugins/dataT/datatables.min.js') }}"></script>
    <!-- Clinical Context (needed for unviewed badge counts and result tracking) -->
    <script src="{{ asset('js/clinical-context.js') }}"></script>
    <script>
        window.currentPatientId = {{ $patient->id }};
        $(document).ready(function() {
            $(".select2").select2();
            if (typeof window.loadUnviewedCounts === 'function') {
                window.loadUnviewedCounts(window.currentPatientId);
            }
        });
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
                    "url": "{{ route('EncounterHistoryList', $patient->id) }}",
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
            $('#payment_history_list').DataTable({
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
                    "url": "{{ route('patientPaymentHistoryList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "user_id",
                        name: "user_id"
                    },
                    {
                        data: "total",
                        name: "total"
                    },
                    {
                        data: "payment_type",
                        name: "payment_type"
                    },
                    {
                        data: "product_or_service_request",
                        name: "product_or_service_request"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
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
                    "url": "{{ url('investigationHistoryList', $patient->id) }}",
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
    <script>
        $(function() {
            $('#misc_bill_bills_hist').DataTable({
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
                    "url": "{{ url('miscBillHistList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "dose",
                        name: "dose"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                ],

                "paging": true
            });
        });
    </script>

    {{-- Imaging JavaScript Functions --}}
    <script>
        // Imaging DataTables
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
                    "url": "{{ url('imagingHistoryList', $patient->id) }}",
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
        function setImagingResViewInModal(obj) {
            let res_obj = JSON.parse($(obj).attr('data-result-obj'));

            // Basic service info
            $('.imaging_res_service_name_view').text($(obj).attr('data-service-name'));

            // Patient information
            let patientName = res_obj.patient.user.firstname + ' ' + res_obj.patient.user.surname;
            $('#imaging_patient_name').html(patientName);
            $('#imaging_patient_id').html(res_obj.patient.file_no);

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
            $('#imaging_patient_age').html(age);

            // Gender
            let gender = res_obj.patient.gender ? res_obj.patient.gender.toUpperCase() : 'N/A';
            $('#imaging_patient_gender').html(gender);

            // Test information
            $('#imaging_test_id').html(res_obj.id);
            $('#imaging_result_date').html(res_obj.result_date || 'N/A');
            $('#imaging_result_by').html(res_obj.results_person.firstname + ' ' + res_obj.results_person.surname);

            // Status
            let statusBadge = '';
            if (res_obj.status) {
                let statusClass = 'badge-';
                let statusText = String(res_obj.status); // Convert to string
                switch(statusText.toLowerCase()) {
                    case 'completed':
                    case '3':
                    case '4':
                        statusClass += 'success';
                        statusText = 'Completed';
                        break;
                    case 'pending':
                    case '1':
                        statusClass += 'warning';
                        statusText = 'Pending';
                        break;
                    case 'in progress':
                    case '2':
                        statusClass += 'info';
                        statusText = 'In Progress';
                        break;
                    default: statusClass += 'secondary';
                }
                statusBadge = '<span class="badge ' + statusClass + '">' + statusText + '</span>';
            }
            $('#imaging_status').html(statusBadge || 'N/A');

            // Signature date (use result date)
            $('#imaging_signature_date').html(res_obj.result_date || '');

            // Generated date (current date)
            let now = new Date();
            let generatedDate = now.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            $('#imaging_generated_date').html(generatedDate);

            // Handle V2 results (structured data)
            if (res_obj.result_data && typeof res_obj.result_data === 'object') {
                let resultsHtml = '<table class="imaging-result-table"><thead><tr>';
                resultsHtml += '<th style="width: 40%;">Parameter</th>';
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
                        let statusClass = 'imaging-status-' + param.status.toLowerCase().replace(' ', '-');
                        statusHtml = '<span class="imaging-result-status-badge ' + statusClass + '">' + param.status + '</span>';
                    }
                    resultsHtml += '<td>' + statusHtml + '</td>';
                    resultsHtml += '</tr>';
                });

                resultsHtml += '</tbody></table>';
                $('#imaging_res').html(resultsHtml);
            } else {
                // V1 results (HTML content)
                $('#imaging_res').html(res_obj.result);
            }

            // Handle attachments
            $('#imaging_attachments').html('');
            if (res_obj.attachments) {
                let attachments = typeof res_obj.attachments === 'string' ? JSON.parse(res_obj.attachments) : res_obj.attachments;
                if (attachments && attachments.length> 0) {
                    let attachHtml = '<div class="imaging-result-attachments"><h6 style="margin-bottom: 15px;"><i class="mdi mdi-paperclip"></i> Attachments</h6><div class="row">';
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
                    $('#imaging_attachments').html(attachHtml);
                }
            }

            // Track polymorphic view event and load access history
            let viewableType = $(obj).attr('data-viewable-type') || 'imaging';
            let viewableId = $(obj).attr('data-viewable-id') || res_obj.id;
            if (typeof trackResultView === 'function') {
                trackResultView(viewableType, viewableId, 'modal');
                if (typeof loadResultAuditHistory === 'function') {
                    loadResultAuditHistory(viewableType, viewableId, 'imaging_view_history_section', 'imaging_view_history_rows');
                }
            } else {
                $('#imaging_view_history_section').hide();
            }

            $('#imagingResViewModal').modal('show');
        }
    </script>

    <script>
        function PrintElem(elem) {
            @php
                $hosColor = appsettings()->hos_color ?? '#0066cc';
            @endphp

            var mywindow = window.open('', 'PRINT', 'height=800,width=1000');

            // Get all style tags from the current modal
            var styleContent = '';
            var modalParent = document.getElementById(elem).closest('.modal-content');
            if (modalParent) {
                var styleTags = modalParent.querySelectorAll('style');
                styleTags.forEach(function(styleTag) {
                    styleContent += styleTag.innerHTML;
                });
            }

            mywindow.document.write('<html><head><title>' + document.title + '</title>');
            mywindow.document.write('<meta charset="UTF-8">');

            // Add custom styles for printing
            mywindow.document.write(`<style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    padding: 20px;
                    background: white;
                    width: 100%;
                    max-width: 100%;
                }
                #resultViewTable, #imagingResultViewTable {
                    width: 100% !important;
                    max-width: 100% !important;
                }

                /* Lab/Imaging Result Styles */
                .result-header, .imaging-result-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 20px;
                    border-bottom: 3px solid {{ $hosColor }};
                    page-break-inside: avoid;
                }
                .result-header-left, .imaging-result-header-left {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                .result-logo, .imaging-result-logo {
                    width: 70px;
                    height: 70px;
                    object-fit: contain;
                }
                .result-hospital-name, .imaging-result-hospital-name {
                    font-size: 24px;
                    font-weight: bold;
                    color: {{ $hosColor }};
                    text-transform: uppercase;
                }
                .result-header-right, .imaging-result-header-right {
                    text-align: right;
                    font-size: 13px;
                    color: #666;
                    line-height: 1.6;
                }
                .result-title-section, .imaging-result-title-section {
                    background: {{ $hosColor }};
                    color: white;
                    text-align: center;
                    padding: 15px;
                    font-size: 28px;
                    font-weight: bold;
                    letter-spacing: 6px;
                    page-break-inside: avoid;
                }
                .result-patient-info, .imaging-result-patient-info {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                    padding: 20px;
                    background: #f8f9fa;
                    page-break-inside: avoid;
                }
                .result-info-box, .imaging-result-info-box {
                    background: white;
                    padding: 15px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                }
                .result-info-row, .imaging-result-info-row {
                    display: flex;
                    padding: 8px 0;
                    border-bottom: 1px solid #eee;
                }
                .result-info-row:last-child, .imaging-result-info-row:last-child {
                    border-bottom: none;
                }
                .result-info-label, .imaging-result-info-label {
                    font-weight: 600;
                    color: #333;
                    min-width: 120px;
                }
                .result-info-value, .imaging-result-info-value {
                    color: #666;
                    flex: 1;
                }
                .result-section, .imaging-result-section {
                    padding: 20px;
                }
                .result-section-title, .imaging-result-section-title {
                    font-size: 20px;
                    font-weight: bold;
                    color: {{ $hosColor }};
                    margin-bottom: 15px;
                    padding-bottom: 10px;
                    border-bottom: 2px solid {{ $hosColor }};
                    page-break-after: avoid;
                }
                .result-table, .imaging-result-table {
                    width: 100% !important;
                    max-width: 100% !important;
                    border-collapse: collapse;
                    margin-top: 15px;
                    table-layout: fixed;
                }
                .result-table thead, .imaging-result-table thead {
                    background: {{ $hosColor }};
                    color: white;
                }
                .result-table th, .imaging-result-table th {
                    padding: 12px;
                    text-align: left;
                    font-weight: 600;
                    border: 1px solid #dee2e6;
                }
                .result-table td, .imaging-result-table td {
                    padding: 10px 12px;
                    border: 1px solid #ddd;
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                }
                .result-table tbody tr:nth-child(even),
                .imaging-result-table tbody tr:nth-child(even) {
                    background: #f8f9fa;
                }
                .result-status-badge, .imaging-result-status-badge {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .status-normal, .imaging-status-normal {
                    background: #d4edda;
                    color: #155724;
                }
                .status-high, .imaging-status-high {
                    background: #f8d7da;
                    color: #721c24;
                }
                .status-low, .imaging-status-low {
                    background: #fff3cd;
                    color: #856404;
                }
                .status-abnormal, .imaging-status-abnormal {
                    background: #f8d7da;
                    color: #721c24;
                }
                .result-attachments, .imaging-result-attachments {
                    margin-top: 20px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    page-break-inside: avoid;
                }
                .result-footer, .imaging-result-footer {
                    padding: 20px;
                    border-top: 2px solid #eee;
                    font-size: 12px;
                    color: #999;
                    text-align: center;
                    page-break-inside: avoid;
                }

                /* Badge styles */
                .badge {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .badge-success {
                    background: #28a745;
                    color: white;
                }
                .badge-warning {
                    background: #ffc107;
                    color: #000;
                }
                .badge-info {
                    background: #17a2b8;
                    color: white;
                }
                .badge-secondary {
                    background: #6c757d;
                    color: white;
                }

                /* Button styles for attachments */
                .btn {
                    display: inline-block;
                    padding: 6px 12px;
                    margin: 5px;
                    border: 1px solid transparent;
                    border-radius: 4px;
                    text-decoration: none;
                    font-size: 14px;
                }
                .btn-outline-primary {
                    color: {{ $hosColor }};
                    border-color: {{ $hosColor }};
                }
                .row {
                    display: flex;
                    flex-wrap: wrap;
                    margin: -5px;
                }
                .col-md-4 {
                    flex: 0 0 33.333333%;
                    max-width: 33.333333%;
                    padding: 5px;
                }

                /* Icon styles */
                .mdi:before {
                    font-family: "Material Design Icons";
                }

                /* Print specific */
                @media print {
                    body {
                        padding: 0;
                        width: 100%;
                        max-width: 100%;
                    }
                    #resultViewTable, #imagingResultViewTable {
                        width: 100% !important;
                        max-width: 100% !important;
                    }
                    .result-table, .imaging-result-table {
                        width: 100% !important;
                        max-width: 100% !important;
                    }
                    .btn-outline-primary {
                        display: none;
                    }
                }

                @page {
                    margin: 0.5cm;
                    size: A4;
                }
            </style>`);

            mywindow.document.write('</head><body>');
            mywindow.document.write(document.getElementById(elem).innerHTML);
            mywindow.document.write('</body></html>');

            mywindow.document.close(); // IE>= 10

            // Wait for the window and its contents to load
            mywindow.onload = function() {
                mywindow.focus(); // Set focus for IE
                setTimeout(function() {
                    mywindow.print();
                    // Optional: Uncomment the line below to close the window after printing
                    // mywindow.close();
                }, 250);
            };

            return true;
        }
    </script>
    <script>
        function setBedModal(obj) {
            $('#assign_bed_req_id').val($(obj).attr('data-id'));
            $('#assign_bed_patient_id').val($(obj).attr('data-patient-id') || '{{ $patient->id ?? "" }}');
            $('#assign_bed_reassign').val($(obj).attr('data-reassign'));
            $('#bed_coverage_info').hide(); // Reset coverage display
            $('#assignBedModal').modal('show');
        }
    </script>

    <script>
        function newBedModal(obj) {
            $('#assign_bed_req_id').val($(obj).attr('data-id'));
            $('#assign_bed_patient_id').val($(obj).attr('data-patient-id') || '{{ $patient->id ?? "" }}');
            $('#assign_bed_reassign').val($(obj).attr('data-reassign'));
            $('#bed_coverage_info').hide(); // Reset coverage display
            $('#assignBedModal').modal('show');
        }
    </script>
    <script>
        function setBillModal(obj) {
            $('#assign_bed_req_id_').val($(obj).attr('data-id'));
            $('#admit_days').val($(obj).attr('data-days'));
            $('#admit_price').val($(obj).attr('data-price'));
            $('#admit_bed_details').html($(obj).attr('data-bed'));
            $('#admit_total').val(parseFloat($(obj).attr('data-price')) * parseFloat($(obj).attr('data-days')));
            $('#assignBillModal').modal('show');
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

    <script>
        $(function() {
            $('#pending-paymnet-list').DataTable({
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
                    "url": "{{ route('product-services-requesters-list', $patient->user_id) }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "service_id",
                        name: "service_id"
                    },
                    {
                        data: "product_id",
                        name: "product_id"
                    },
                    {
                        data: "show",
                        name: "show"
                    },
                ],
                "paging": true
            });
        });
    </script>

    {{-- Prescriptions history --}}
    <script>
        $(function () {
            $('#show1_presc_history_table').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('prescHistoryList', $patient->id) }}",
                    "type": "GET"
                },
                "columns": [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'product_name', name: 'product_name' },
                    { data: 'product_code', name: 'product_code' },
                    { data: 'dose', name: 'dose' },
                    { data: 'qty', name: 'qty' },
                    {
                        data: 'status', name: 'status',
                        render: function (data) {
                            var m = {0: ['secondary','Requested'], 1: ['info','Billed'], 2: ['success','Dispensed'], 3: ['danger','Cancelled'], 4: ['warning','Pending']};
                            var s = m[parseInt(data)] || ['secondary', data];
                            return '<span class="badge badge-' + s[0] + '">' + s[1] + '</span>';
                        }
                    },
                    { data: 'requested_by', name: 'requested_by' },
                    { data: 'requested_at', name: 'requested_at' },
                    {
                        data: 'is_paid', name: 'is_paid',
                        render: function (data) {
                            return data ? '<span class="badge badge-success">Paid</span>' : '<span class="badge badge-warning">Unpaid</span>';
                        }
                    }
                ],
                "paging": true
            });
        });
    </script>

    {{-- Nursing notes (type_id = 5) --}}
    <script>
        $(function () {
            $('#show1_nursing_notes_table').DataTable({
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('nursing-workbench/patient/' . $patient->id . '/nursing-notes') }}",
                    "type": "GET",
                    "data": function (d) { d.type_id = 5; }
                },
                "columns": [
                    { data: 'info', name: 'info', orderable: false, searchable: false }
                ],
                "ordering": false,
                "lengthChange": false,
                "pageLength": 10,
                "searching": false,
                "dom": "<'row'<'col-sm-12'tr>><'row'<'col-sm-5'i><'col-sm-7'p>>",
                "paging": true
            });
        });
    </script>

    {{-- Procedures (all / surgical / non-surgical) --}}
    <script>
        $(function () {
            var procBaseUrl = '{{ url("patient-procedures/list-by-patient") }}/{{ $patient->id }}';

            function dtCfgProc(extraParam) {
                return {
                    "dom": 'Bfrtip',
                    "iDisplayLength": 25,
                    "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                    "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                    "processing": true,
                    "serverSide": true,
                    "ajax": {
                        "url": procBaseUrl + (extraParam ? '?type=' + extraParam : ''),
                        "type": "GET"
                    },
                    "columns": [
                        { data: 'info', name: 'info', orderable: false, searchable: false }
                    ],
                    "paging": true
                };
            }

            $('#show1_proc_all_table').DataTable(dtCfgProc(null));

            var surgicalInited = false, nonSurgicalInited = false;

            $('#show1ProcSurgicalTab').on('click', function () {
                if (!surgicalInited) {
                    surgicalInited = true;
                    $('#show1_proc_surgical_table').DataTable(dtCfgProc('surgical'));
                }
            });

            $('#show1ProcNonSurgicalTab').on('click', function () {
                if (!nonSurgicalInited) {
                    nonSurgicalInited = true;
                    $('#show1_proc_nonsurgical_table').DataTable(dtCfgProc('non-surgical'));
                }
            });
        });
    </script>

    @include('admin.partials.vitals-scripts')
@endsection
