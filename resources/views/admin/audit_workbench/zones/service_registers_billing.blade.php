@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-book-open-page-variant"></i> Service Registers vs Billing Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'service-registers-billing', 'zoneLabel' => 'Service Registers vs Billing Zone'])

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

    $('#table-clinical-services').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.service-registers-billing.data', 'services') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_details', name: 'patient.user.surname' },
            { data: 'doctor_details', name: 'doctor.surname' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-billed-services').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.service-registers-billing.data', 'billing') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_details', name: 'patient.user.surname' },
            { data: 'service_details', name: 'service.service_name' },
            { data: 'amount_formatted', name: 'payable_amount' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
    $('#table-procedures').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.service-registers-billing.data', 'procedures') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'date_cat', name: 'created_at' },
            { data: 'patient_details', name: 'patient.user.surname' },
            { data: 'proc_name', name: 'free_form_name' },
            { data: 'status_badge', name: 'procedure_status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-maternity').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.service-registers-billing.data', 'maternity') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'enrollment_date', name: 'created_at' },
            { data: 'patient_details', name: 'patient.user.surname' },
            { data: 'edd_gestation', name: 'edd' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
});
</script>
@endpush
