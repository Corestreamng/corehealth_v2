@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-bed"></i> Admissions & Discharges Audit</h4>
</div>

@include('admin.audit_workbench.partials.datetime_filter', ['zoneKey' => 'admissions-discharges', 'zoneLabel' => 'Admissions & Discharges Zone'])

<div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden" style="width: 100%;">
    <div class="card-header bg-white border-bottom p-0">
        <ul class="nav nav-tabs nav-fill audit-tabs" id="admissionsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-3 font-weight-bold" id="admissions-tab" data-bs-toggle="tab" data-bs-target="#admissions" type="button" role="tab">
                    <i class="mdi mdi-bed text-info"></i> Inpatient Admissions
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="discharges-tab" data-bs-toggle="tab" data-bs-target="#discharges" type="button" role="tab">
                    <i class="mdi mdi-exit-run text-success"></i> Discharges & Clearance
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-3 font-weight-bold" id="triangulation-tab" data-bs-toggle="tab" data-bs-target="#triangulation" type="button" role="tab">
                    <i class="mdi mdi-calculator-variant text-primary"></i> Ward Triangulation (Admissions vs Store Requisitions vs Bills)
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
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
            processing: true,
            serverSide: true,
            responsive: true,
            order: [
                [0, 'desc']
            ]
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
            columns: [{
                    data: 'created_at',
                    name: 'created_at'
                },
                {
                    data: 'patient_details',
                    name: 'patient.user.surname'
                },
                {
                    data: 'ward_bed',
                    name: 'ward.name'
                },
                {
                    data: 'status_badge',
                    name: 'status'
                },
                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false
                }
            ]
        }));

        $('#table-discharges').DataTable($.extend({}, commonDtConfig, {
            ajax: {
                url: "{{ route('audit.admissions-discharges.data', 'discharges') }}",
                data: appendMultidimData
            },
            columns: [{
                    data: 'discharge_date',
                    name: 'discharge_date'
                },
                {
                    data: 'patient_details',
                    name: 'patient.user.surname'
                },
                {
                    data: 'ward_bed',
                    name: 'ward.name'
                },
                {
                    data: 'stay_days',
                    name: 'created_at'
                },
                {
                    data: 'status_badge',
                    name: 'status'
                },
                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false
                }
            ]
        }));
    });
</script>
@endpush