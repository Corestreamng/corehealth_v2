@extends('admin.ops_audit.layout')

@section('title', 'Ops Audit — Doctor / Encounters')

@section('ops_audit_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="font-weight-bold mb-0"><i class="mdi mdi-stethoscope text-primary me-1"></i> Doctor / Encounters Audit</h5>
        <small class="text-muted">Encounters, Prescriptions, Labs, Imaging, and Admissions</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-success font-weight-bold" onclick="openUniversalStampModal('bulk')">
            <i class="mdi mdi-check-all me-1"></i> Bulk Stamp
        </button>
        <button class="btn btn-sm btn-outline-info font-weight-bold" onclick="printCurrentTab()">
            <i class="mdi mdi-printer me-1"></i> Print
        </button>
    </div>
</div>

{{-- Filter Bar --}}
<form id="ops_audit_filter_form" class="ops-filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label>Start Date</label>
            <input type="date" name="start_date" class="form-control" value="{{ now()->subDays(30)->format('Y-m-d') }}">
        </div>
        <div class="col-md-2">
            <label>End Date</label>
            <input type="date" name="end_date" class="form-control" value="{{ now()->format('Y-m-d') }}">
        </div>
        <div class="col-md-auto">
            <label>Shift</label>
            <div class="d-flex gap-1">
                <button type="button" class="btn btn-outline-secondary shift-btn" data-shift="morning"><i class="mdi mdi-weather-sunny"></i> AM</button>
                <button type="button" class="btn btn-outline-secondary shift-btn" data-shift="afternoon"><i class="mdi mdi-weather-partly-cloudy"></i> PM</button>
                <button type="button" class="btn btn-outline-secondary shift-btn" data-shift="night"><i class="mdi mdi-weather-night"></i> Night</button>
                <button type="button" class="btn btn-outline-secondary shift-btn" data-shift="all"><i class="mdi mdi-clock-outline"></i> All</button>
            </div>
            <input type="hidden" name="shift_start" value="">
            <input type="hidden" name="shift_end" value="">
        </div>
        <div class="col-md-2">
            <label>HMO</label>
            <select name="hmo_id" class="form-control form-control-modern select2 ops-hmo-select2" style="width: 100%;">
                <option value="">All HMOs</option>
                @foreach($hmos as $schemeName => $schemeHmos)
                    <optgroup label="{{ $schemeName }}">
                        @foreach($schemeHmos as $hmo)
                            <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label>Doctor</label>
            <select name="doctor_id" class="form-select">
                <option value="">All Doctors</option>
                @foreach($doctors as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-auto">
            <button type="button" class="btn btn-primary btn-sm font-weight-bold px-3" id="btnApplyFilters">
                <i class="mdi mdi-filter me-1"></i> Apply
            </button>
        </div>
    </div>
</form>

{{-- Tabs --}}
<ul class="nav nav-tabs ops-tabs mb-0" id="doctorTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="tab-encounters" data-bs-toggle="tab" href="#pane-encounters" role="tab">
            <i class="mdi mdi-stethoscope me-1"></i> Encounters
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-admissions" data-bs-toggle="tab" href="#pane-admissions" role="tab">
            <i class="mdi mdi-bed me-1"></i> Admissions
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-prescriptions" data-bs-toggle="tab" href="#pane-prescriptions" role="tab">
            <i class="mdi mdi-pill me-1"></i> Prescriptions
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-labs" data-bs-toggle="tab" href="#pane-labs" role="tab">
            <i class="mdi mdi-flask-empty me-1"></i> Lab Requests
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-imaging" data-bs-toggle="tab" href="#pane-imaging" role="tab">
            <i class="mdi mdi-radiology-box me-1"></i> Imaging
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-procedures" data-bs-toggle="tab" href="#pane-procedures" role="tab">
            <i class="mdi mdi-knife me-1"></i> Procedures
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-referrals" data-bs-toggle="tab" href="#pane-referrals" role="tab">
            <i class="mdi mdi-swap-horizontal me-1"></i> Referrals
        </a>
    </li>
</ul>

<div class="tab-content bg-white border border-top-0 rounded-bottom p-3">
    {{-- Tab 1: Encounters --}}
    <div class="tab-pane fade show active" id="pane-encounters" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-encounters"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="entity" class="form-select form-select-sm ajax-entity-search ops-tab-filter" data-tab="encounters" data-placeholder="Search Entity/Patient...">
                    <option value="">All Entities</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="completed" class="form-select form-select-sm ops-tab-filter" data-tab="encounters">
                    <option value="">All Status</option>
                    <option value="1">Completed</option>
                    <option value="0">Ongoing</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="outcome" class="form-select form-select-sm ops-tab-filter" data-tab="encounters">
                    <option value="">All Outcomes</option>
                    <option value="discharged">Discharged</option>
                    <option value="admitted">Admitted</option>
                    <option value="referred">Referred</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="clinic_id" class="form-select form-select-sm ops-tab-filter" data-tab="encounters">
                    <option value="">All Clinics</option>
                    @foreach($clinics as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-encounters">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Doctor</th>
                        <th>Clinic</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Outcome</th>
                        <th>Rx</th>
                        <th>Labs</th>
                        <th>Img</th>
                        <th>Adm</th>
                        <th>Proc</th>
                        <th>Ref</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 2: Admissions --}}
    <div class="tab-pane fade" id="pane-admissions" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-admissions"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="admissions">
                    <option value="">All Status</option>
                    <option value="pending_checklist">Pending Checklist</option>
                    <option value="admitted">Admitted</option>
                    <option value="discharged">Discharged</option>
                </select>
            </div>
            @include('admin.ops_audit.partials.payment_filters', ['tab' => 'admissions'])
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-admissions">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Doctor</th>
                        <th>Ward</th>
                        <th>Bed</th>
                        <th>ESI</th>
                        <th>Status</th>
                        <th>LOS</th>
                        <th>Total Bill</th>
                        <th style="min-width: 150px;">Payment Info</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 3: Prescriptions --}}
    <div class="tab-pane fade" id="pane-prescriptions" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-prescriptions"></div>
        
        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="prescriptions">
                    <option value="">All Status</option>
                    <option value="1">Pending</option>
                    <option value="2">Approved</option>
                    <option value="3">Dispensed</option>
                    <option value="4">Returned</option>
                </select>
            </div>
            @include('admin.ops_audit.partials.payment_filters', ['tab' => 'prescriptions'])
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-prescriptions">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Doctor</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Store</th>
                        <th>Status</th>
                        <th>Billed By</th>
                        <th>Dispensed By</th>
                        <th style="min-width: 150px;">Payment Info</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 4: Labs --}}
    <div class="tab-pane fade" id="pane-labs" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-labs"></div>
        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="labs">
                    <option value="">All Status</option>
                    <option value="1">Ordered</option>
                    <option value="2">Sample Collected</option>
                    <option value="3">Result Entered</option>
                    <option value="4">Approved</option>
                </select>
            </div>
            @include('admin.ops_audit.partials.payment_filters', ['tab' => 'labs'])
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-labs">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Test</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Sample By</th>
                        <th>Result By</th>
                        <th>Approved By</th>
                        <th>Billed By</th>
                        <th style="min-width: 150px;">Payment Info</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 5: Imaging --}}
    <div class="tab-pane fade" id="pane-imaging" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-imaging"></div>
        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="imaging">
                    <option value="">All Status</option>
                    <option value="1">Ordered</option>
                    <option value="2">Image Captured</option>
                    <option value="3">Result Entered</option>
                    <option value="4">Approved</option>
                </select>
            </div>
            @include('admin.ops_audit.partials.payment_filters', ['tab' => 'imaging'])
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-imaging">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Test</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Sample By</th>
                        <th>Result By</th>
                        <th>Approved By</th>
                        <th>Billed By</th>
                        <th style="min-width: 150px;">Payment Info</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 6: Procedures --}}
    <div class="tab-pane fade" id="pane-procedures" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-procedures"></div>
        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="procedures">
                    <option value="">All Status</option>
                    <option value="requested">Requested</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            @include('admin.ops_audit.partials.payment_filters', ['tab' => 'procedures'])
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-procedures">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Procedure</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Consent</th>
                        <th>Outcome</th>
                        <th>OR</th>
                        <th>Billed By</th>
                        <th style="min-width: 150px;">Payment Info</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 7: Referrals --}}
    <div class="tab-pane fade" id="pane-referrals" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-referrals"></div>
        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="entity" class="form-select form-select-sm ajax-entity-search ops-tab-filter" data-tab="referrals" data-placeholder="Search Entity/Patient...">
                    <option value="">All Entities</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="referral_type" class="form-select form-select-sm ops-tab-filter" data-tab="referrals">
                    <option value="">All Types</option>
                    <option value="internal">Internal</option>
                    <option value="external">External</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="referrals">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="booked">Booked</option>
                    <option value="referred_out">Referred Out</option>
                    <option value="completed">Completed</option>
                    <option value="declined">Declined</option>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-referrals">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Referring Doctor</th>
                        <th>Type</th>
                        <th>Target</th>
                        <th>Urgency</th>
                        <th>Status</th>
                        <th>Actioned By</th>
                        <th style="min-width: 150px;">Payment Info</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('ops_audit_scripts')
<script>
$(function() {
    var dataUrls = {
        encounters: "{{ route('ops-audit.doctor.data', 'encounters') }}",
        admissions: "{{ route('ops-audit.doctor.data', 'admissions') }}",
        prescriptions: "{{ route('ops-audit.doctor.data', 'prescriptions') }}",
        labs: "{{ route('ops-audit.doctor.data', 'labs') }}",
        imaging: "{{ route('ops-audit.doctor.data', 'imaging') }}",
        procedures: "{{ route('ops-audit.doctor.data', 'procedures') }}",
        referrals: "{{ route('ops-audit.doctor.data', 'referrals') }}"
    };

    var dtInstances = {};

    function commonOpts(url, columns, kpiContainer) {
        return {
            dom: '<"d-flex justify-content-between align-items-center mb-2"<"d-flex gap-2"B>f>rt<"d-flex justify-content-between align-items-center mt-2"ip>',
            buttons: [
                { extend: 'copy', className: 'btn btn-xs btn-outline-secondary font-weight-bold' },
                { extend: 'excel', className: 'btn btn-xs btn-outline-success font-weight-bold' },
                { extend: 'pdf', className: 'btn btn-xs btn-outline-danger font-weight-bold' },
                { extend: 'print', className: 'btn btn-xs btn-outline-info font-weight-bold' }
            ],
            processing: true,
            serverSide: true,
            ajax: {
                url: url,
                type: 'GET',
                data: function(d) {
                    var form = $('#ops_audit_filter_form').serializeArray();
                    form.forEach(function(f) { d[f.name] = f.value; });
                    var tabName = kpiContainer ? kpiContainer.replace('kpi-', '') : '';
                    $(`.ops-tab-filter[data-tab="${tabName}"]`).each(function() {
                        d[$(this).attr('name')] = $(this).val();
                    });
                },
                dataSrc: function(json) {
                    if (json.kpis && kpiContainer) {
                        renderOpsKpis(json.kpis, kpiContainer);
                    }
                    return json.data;
                }
            },
            columns: columns,
            order: [[0, 'desc']],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: {
                zeroRecords: '<div class="text-center py-3 text-muted"><i class="mdi mdi-database-off" style="font-size:2rem;"></i><br>No records found.</div>',
                processing: '<div class="text-center py-3"><i class="mdi mdi-loading mdi-spin text-primary" style="font-size:1.5rem;"></i> Loading...</div>'
            }
        };
    }

    // Init tab 1
    dtInstances.encounters = $('#dt-encounters').DataTable(commonOpts(dataUrls.encounters, [
        { data: 'date' }, { data: 'patient' }, { data: 'hmo' }, { data: 'doctor' }, { data: 'clinic' },
        { data: 'duration' }, { data: 'completed' }, { data: 'outcome' }, { data: 'rx' },
        { data: 'labs' }, { data: 'imaging' }, { data: 'admissions' }, { data: 'procedures' }, { data: 'referrals' },
        { data: 'audit', orderable: false, searchable: false }
    ], 'kpi-encounters'));

    // Lazy init others
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var tabId = $(e.target).attr('id').replace('tab-', '');
        
        if (tabId === 'admissions' && !dtInstances.admissions) {
            dtInstances.admissions = $('#dt-admissions').DataTable(commonOpts(dataUrls.admissions, [
                { data: 'date' }, { data: 'patient' }, { data: 'hmo' }, { data: 'doctor' }, { data: 'ward' },
                { data: 'bed' }, { data: 'esi' }, { data: 'status' }, { data: 'los' }, { data: 'total_bill' },
                { data: 'payment_info', name: 'payment_info', orderable: false, searchable: false },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-admissions'));
        }
        else if (tabId === 'prescriptions' && !dtInstances.prescriptions) {
            dtInstances.prescriptions = $('#dt-prescriptions').DataTable(commonOpts(dataUrls.prescriptions, [
                { data: 'date' }, { data: 'patient' }, { data: 'hmo' }, { data: 'doctor' }, { data: 'product' },
                { data: 'qty' }, { data: 'store' }, { data: 'status' }, { data: 'billed_by' }, { data: 'dispensed_by' },
                { data: 'payment_info', name: 'payment_info', orderable: false, searchable: false },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-prescriptions'));
        }
        else if (tabId === 'labs' && !dtInstances.labs) {
            dtInstances.labs = $('#dt-labs').DataTable(commonOpts(dataUrls.labs, [
                { data: 'date' }, { data: 'patient' }, { data: 'hmo' }, { data: 'test' }, { data: 'doctor' },
                { data: 'status' }, { data: 'sample_by' }, { data: 'result_by' }, { data: 'approved_by' }, { data: 'billed_by' },
                { data: 'payment_info', name: 'payment_info', orderable: false, searchable: false },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-labs'));
        }
        else if (tabId === 'imaging' && !dtInstances.imaging) {
            dtInstances.imaging = $('#dt-imaging').DataTable(commonOpts(dataUrls.imaging, [
                { data: 'date' }, { data: 'patient' }, { data: 'hmo' }, { data: 'test' }, { data: 'doctor' },
                { data: 'status' }, { data: 'sample_by' }, { data: 'result_by' }, { data: 'approved_by' }, { data: 'billed_by' },
                { data: 'payment_info', name: 'payment_info', orderable: false, searchable: false },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-imaging'));
        }
        else if (tabId === 'procedures' && !dtInstances.procedures) {
            dtInstances.procedures = $('#dt-procedures').DataTable(commonOpts(dataUrls.procedures, [
                { data: 'date' }, { data: 'patient' }, { data: 'hmo' }, { data: 'procedure' }, { data: 'doctor' },
                { data: 'status' }, { data: 'consent' }, { data: 'outcome' }, { data: 'or' }, { data: 'billed_by' },
                { data: 'payment_info', name: 'payment_info', orderable: false, searchable: false },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-procedures'));
        }
        else if (tabId === 'referrals' && !dtInstances.referrals) {
            dtInstances.referrals = $('#dt-referrals').DataTable(commonOpts(dataUrls.referrals, [
                { data: 'date' }, { data: 'patient' }, { data: 'hmo' }, { data: 'referring_doctor' }, { data: 'type' },
                { data: 'target' }, { data: 'urgency' }, { data: 'status' }, { data: 'actioned_by' }, 
                { data: 'payment_info', name: 'payment_info', orderable: false, searchable: false },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-referrals'));
        }

        setTimeout(function() {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }, 200);
    });

    $('#btnApplyFilters').on('click', function() {
        Object.values(dtInstances).forEach(function(dt) {
            if (dt) dt.ajax.reload();
        });
    });

    $(document).on('change', '.ops-tab-filter', function() {
        var tab = $(this).data('tab');
        if (dtInstances[tab]) dtInstances[tab].ajax.reload();
    });
});
</script>
@endpush
