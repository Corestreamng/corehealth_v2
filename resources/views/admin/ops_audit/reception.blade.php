@extends('admin.ops_audit.layout')

@section('title', 'Ops Audit — Reception')

@section('ops_audit_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="font-weight-bold mb-0"><i class="mdi mdi-desktop-mac text-primary me-1"></i> Reception Audit</h5>
        <small class="text-muted">Queue registrations, appointments, and referrals</small>
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
            <label>HMO Scheme</label>
            <select name="hmo_scheme_id" class="form-select">
                <option value="">All Schemes</option>
                @foreach($hmoSchemes as $id => $name)
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
<ul class="nav nav-tabs ops-tabs mb-0" id="receptionTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="tab-queues" data-bs-toggle="tab" href="#pane-queues" role="tab">
            <i class="mdi mdi-account-plus me-1"></i> Queue Registrations
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-appointments" data-bs-toggle="tab" href="#pane-appointments" role="tab">
            <i class="mdi mdi-calendar-check me-1"></i> Appointments
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-referrals" data-bs-toggle="tab" href="#pane-referrals" role="tab">
            <i class="mdi mdi-swap-horizontal me-1"></i> Referrals
        </a>
    </li>
</ul>

<div class="tab-content bg-white border border-top-0 rounded-bottom p-3">
    {{-- Tab 1: Queue Registrations --}}
    <div class="tab-pane fade show active" id="pane-queues" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-queues"></div>

        {{-- Extra filters for this tab --}}
        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="source" class="form-select form-select-sm ops-tab-filter" data-tab="queues">
                    <option value="">All Sources</option>
                    <option value="reception">Reception</option>
                    <option value="emergency_intake">Emergency</option>
                    <option value="appointment">Appointment</option>
                    <option value="maternity">Maternity</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="queues">
                    <option value="">All Statuses</option>
                    <option value="1">Waiting</option>
                    <option value="2">In Progress</option>
                    <option value="5">Completed</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="priority" class="form-select form-select-sm ops-tab-filter" data-tab="queues">
                    <option value="">All Priorities</option>
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="emergency">Emergency</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="clinic_id" class="form-select form-select-sm ops-tab-filter" data-tab="queues">
                    <option value="">All Clinics</option>
                    @foreach($clinics as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <select name="gender" class="form-select form-select-sm ops-tab-filter" data-tab="queues">
                    <option value="">Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-queues">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Source</th>
                        <th>Priority</th>
                        <th>Clinic</th>
                        <th>Doctor</th>
                        <th>Receptionist</th>
                        <th>Status</th>
                        <th>Wait</th>
                        <th>Vitals</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 2: Appointments --}}
    <div class="tab-pane fade" id="pane-appointments" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-appointments"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="appointment_type" class="form-select form-select-sm ops-tab-filter" data-tab="appointments">
                    <option value="">All Types</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="follow_up">Follow-Up</option>
                    <option value="referral">Referral</option>
                    <option value="walk_in">Walk-In</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="appointments">
                    <option value="">All Statuses</option>
                    <option value="0">Scheduled</option>
                    <option value="1">Confirmed</option>
                    <option value="2">Checked-In</option>
                    <option value="3">Completed</option>
                    <option value="4">Cancelled</option>
                    <option value="5">No-Show</option>
                    <option value="6">Rescheduled</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="clinic_id" class="form-select form-select-sm ops-tab-filter" data-tab="appointments">
                    <option value="">All Clinics</option>
                    @foreach($clinics as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <select name="gender" class="form-select form-select-sm ops-tab-filter" data-tab="appointments">
                    <option value="">Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-appointments">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Type</th>
                        <th>Clinic</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Booked By</th>
                        <th>Cancel Reason</th>
                        <th>Payable</th>
                        <th>Claims</th>
                        <th>Cashier</th>
                        <th>Method</th>
                        <th>Pay Status</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 3: Referrals --}}
    <div class="tab-pane fade" id="pane-referrals" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-referrals"></div>

        <div class="row g-2 mb-2">
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
            <div class="col-md-2">
                <select name="urgency" class="form-select form-select-sm ops-tab-filter" data-tab="referrals">
                    <option value="">All Urgencies</option>
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="emergency">Emergency</option>
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
    var queuesUrl = "{{ route('ops-audit.reception.data', 'queues') }}";
    var appointmentsUrl = "{{ route('ops-audit.reception.data', 'appointments') }}";
    var referralsUrl = "{{ route('ops-audit.reception.data', 'referrals') }}";

    // Common DataTable options
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
                    // Global filters
                    var form = $('#ops_audit_filter_form').serializeArray();
                    form.forEach(function(f) { d[f.name] = f.value; });

                    // Tab-specific filters
                    var tabName = $(this).closest('.tab-pane').attr('id')?.replace('pane-', '') || '';
                    $(`.ops-tab-filter[data-tab="${tabName}"]`).each(function() {
                        d[$(this).attr('name')] = $(this).val();
                    });
                },
                dataSrc: function(json) {
                    // Render KPIs
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
                zeroRecords: '<div class="text-center py-3 text-muted"><i class="mdi mdi-database-off" style="font-size:2rem;"></i><br>No records found for this filter.</div>',
                processing: '<div class="text-center py-3"><i class="mdi mdi-loading mdi-spin text-primary" style="font-size:1.5rem;"></i> Loading...</div>'
            }
        };
    }

    // Tab 1: Queues
    var dtQueues = $('#dt-queues').DataTable(commonOpts(queuesUrl, [
        { data: 'created_at' },
        { data: 'patient' },
        { data: 'hmo' },
        { data: 'source' },
        { data: 'priority' },
        { data: 'clinic' },
        { data: 'doctor' },
        { data: 'receptionist' },
        { data: 'status' },
        { data: 'wait' },
        { data: 'vitals' },
        { data: 'audit', orderable: false, searchable: false }
    ], 'kpi-queues'));

    // Tab 2: Appointments (lazy init)
    var dtAppointments = null;
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).attr('href');

        if (target === '#pane-appointments' && !dtAppointments) {
            dtAppointments = $('#dt-appointments').DataTable(commonOpts(appointmentsUrl, [
                { data: 'date' },
                { data: 'patient' },
                { data: 'hmo' },
                { data: 'type' },
                { data: 'clinic' },
                { data: 'doctor' },
                { data: 'status' },
                { data: 'booked_by' },
                { data: 'cancel_reason' },
                { data: 'payable' },
                { data: 'claims' },
                { data: 'cashier' },
                { data: 'method' },
                { data: 'pay_status' },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-appointments'));
        }

        if (target === '#pane-referrals' && !window.dtReferrals) {
            window.dtReferrals = $('#dt-referrals').DataTable(commonOpts(referralsUrl, [
                { data: 'date' },
                { data: 'patient' },
                { data: 'hmo' },
                { data: 'referring_doctor' },
                { data: 'type' },
                { data: 'target' },
                { data: 'urgency' },
                { data: 'status' },
                { data: 'actioned_by' },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-referrals'));
        }

        // Fix column widths after tab switch
        setTimeout(function() {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }, 200);
    });

    // Apply filters button
    $('#btnApplyFilters').on('click', function() {
        // Reload all initialized tables
        if (dtQueues) dtQueues.ajax.reload();
        if (dtAppointments) dtAppointments.ajax.reload();
        if (window.dtReferrals) window.dtReferrals.ajax.reload();
    });

    // Tab-specific filter change
    $(document).on('change', '.ops-tab-filter', function() {
        var tab = $(this).data('tab');
        if (tab === 'queues' && dtQueues) dtQueues.ajax.reload();
        if (tab === 'appointments' && dtAppointments) dtAppointments.ajax.reload();
        if (tab === 'referrals' && window.dtReferrals) window.dtReferrals.ajax.reload();
    });
});

function printCurrentTab() {
    var activeTable = $('.tab-pane.active table.ops-datatable');
    if (activeTable.length && $.fn.DataTable.isDataTable(activeTable)) {
        activeTable.DataTable().button('.buttons-print').trigger();
    }
}
</script>
@endpush
