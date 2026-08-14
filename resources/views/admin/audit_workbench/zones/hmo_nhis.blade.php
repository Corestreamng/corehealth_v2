@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-hospital-building"></i> HMO & NHIS Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'hmo-nhis', 'zoneLabel' => 'HMO & NHIS Zone'])

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

    $('#table-hmo-services').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.hmo-nhis.data', 'services') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'patient_hmo', name: 'patient.user.surname' },
            { data: 'service_item', name: 'product.product_name' },
            { data: 'claims_amount_formatted', name: 'claims_amount' },
            { data: 'validation_status_badge', name: 'validation_status' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-hmo-claims').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.hmo-nhis.data', 'claims') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'hmo_details', name: 'hmo.name' },
            { data: 'claim_amount_formatted', name: 'claims_amount' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));

    $('#table-hmo-remittances').DataTable($.extend({}, commonDtConfig, {
        ajax: {
            url: "{{ route('audit.hmo-nhis.data', 'remittances') }}",
            data: appendMultidimData
        },
        columns: [
            { data: 'payment_date', name: 'payment_date' },
            { data: 'hmo_details', name: 'hmo.name' },
            { data: 'amount_formatted', name: 'amount' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    }));
});
</script>
@endpush
