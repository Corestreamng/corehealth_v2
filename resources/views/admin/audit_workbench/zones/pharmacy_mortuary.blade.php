@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-pill"></i> Pharmacy & Mortuary Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'pharmacy-mortuary', 'zoneLabel' => 'Pharmacy & Mortuary Zone'])

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

    $('#table-pharmacy-dispense').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.pharmacy-mortuary.data', 'pharmacy-dispense') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_doctor', name: 'patient.user.surname' },
            { data: 'product_store', name: 'product.product_name' },
            { data: 'classification_badge', name: 'created_at', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-ward-direct-billing').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.pharmacy-mortuary.data', 'ward-direct-billing') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_details', name: 'user.surname' },
            { data: 'product_store', name: 'product.product_name' },
            { data: 'amount_formatted', name: 'payable_amount' },
            { data: 'classification_badge', name: 'created_at', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-mortuary').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.pharmacy-mortuary.data', 'mortuary') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'arrival_time', name: 'arrival_time' },
            { data: 'deceased_details', name: 'patient.user.surname' },
            { data: 'location', name: 'fridge_number' },
            { data: 'status_badge', name: 'status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
});
</script>
@endpush
