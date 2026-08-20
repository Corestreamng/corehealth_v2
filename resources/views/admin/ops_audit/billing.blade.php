@extends('admin.ops_audit.layout')

@section('title', 'Ops Audit — Billing & Cashier')

@section('ops_audit_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="font-weight-bold mb-0"><i class="mdi mdi-cash-multiple text-primary me-1"></i> Billing & Cashier Audit</h5>
        <small class="text-muted">Payments, staff bills, and organization bills</small>
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
            <label>Cashier</label>
            <select name="cashier_id" class="form-select">
                <option value="">All Cashiers</option>
                @foreach($users as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
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
        <div class="col-md-auto">
            <button type="button" class="btn btn-primary btn-sm font-weight-bold px-3" id="btnApplyFilters">
                <i class="mdi mdi-filter me-1"></i> Apply
            </button>
        </div>
    </div>
</form>

{{-- Tabs --}}
<ul class="nav nav-tabs ops-tabs mb-0" id="billingTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="tab-payments" data-bs-toggle="tab" href="#pane-payments" role="tab">
            <i class="mdi mdi-currency-usd me-1"></i> Payments
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-organization-bills" data-bs-toggle="tab" href="#pane-organization-bills" role="tab">
            <i class="mdi mdi-domain me-1"></i> Organization Bills
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-staff-bills" data-bs-toggle="tab" href="#pane-staff-bills" role="tab">
            <i class="mdi mdi-account-group me-1"></i> Staff Bills
        </a>
    </li>
</ul>

<div class="tab-content bg-white border border-top-0 rounded-bottom p-3">
    {{-- Tab 1: Payments --}}
    <div class="tab-pane fade show active" id="pane-payments" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-payments"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="payment_method" class="form-select form-select-sm ops-tab-filter" data-tab="payments">
                    <option value="">All Payment Methods</option>
                    <option value="ACCOUNT">Account</option>
                    <option value="BILL_TO_ORG">Bill to Org</option>
                    <option value="BILL_TO_STAFF">Bill to Staff</option>
                    <option value="CASH">Cash</option>
                    <option value="HMO_FULL_COVER">HMO Full Cover</option>
                    <option value="MOBILE">Mobile</option>
                    <option value="POS">POS</option>
                    <option value="REFUND">Refund</option>
                    <option value="TRANSFER">Transfer</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="bank_id" class="form-select form-select-sm ops-tab-filter" data-tab="payments">
                    <option value="">All Banks</option>
                    @php $activeBanks = \App\Models\Bank::active()->orderBy('name')->get(); @endphp
                    @foreach($activeBanks as $b) <option value="{{ $b->id }}">{{ $b->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="entity" class="form-select form-select-sm ajax-entity-search ops-tab-filter" data-tab="payments" data-placeholder="Search Entity/Patient...">
                    <option value="">All Entities</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="payment_type" class="form-select form-select-sm ops-tab-filter" data-tab="payments">
                    <option value="">Payment Type</option>
                    <option value="invoice">Invoice</option>
                    <option value="deposit">Deposit</option>
                    <option value="refund">Refund</option>
                </select>
            </div>

            <div class="col-md-2">
                <select name="is_audited" class="form-select form-select-sm ops-tab-filter" data-tab="payments">
                    <option value="">Audit Status</option>
                    <option value="0">Pending Audit</option>
                    <option value="1">Audited</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-payments">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Patient</th>
                        <th>HMO (Scheme)</th>
                        <th>Ref No</th>
                        <th>Total</th>
                        <th>Discount</th>
                        <th>Method</th>
                        <th>Type</th>
                        <th>Bank</th>
                        <th>Entity</th>
                        <th>Balance</th>
                        <th>Cashier</th>
                        <th>Shift Period</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 2: Organization Bills --}}
    <div class="tab-pane fade" id="pane-organization-bills" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-organization-bills"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="entity" class="form-select form-select-sm ajax-entity-search ops-tab-filter" data-tab="organization-bills" data-placeholder="Search Entity/Patient...">
                    <option value="">All Entities</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="organization_id" class="form-select form-select-sm ops-tab-filter" data-tab="organization_bills">
                    <option value="">All Organizations</option>
                    @foreach($organizations as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="organization_bills">
                    <option value="">Status</option>
                    <option value="pending">Pending</option>
                    <option value="pending_audit">Pending Audit</option>
                    <option value="paid">Paid</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="is_audited" class="form-select form-select-sm ops-tab-filter" data-tab="organization_bills">
                    <option value="">Audit Status</option>
                    <option value="0">Pending Audit</option>
                    <option value="1">Audited</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-organization-bills">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Organization</th>
                        <th>Total</th>
                        <th>Discount</th>
                        <th>Outstanding</th>
                        <th>Status</th>
                        <th>Settlement By</th>
                        <th>Settled At</th>
                        <th>Audited</th>
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

    {{-- Tab 3: Staff Bills --}}
    <div class="tab-pane fade" id="pane-staff-bills" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-staff-bills"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="entity" class="form-select form-select-sm ajax-entity-search ops-tab-filter" data-tab="staff-bills" data-placeholder="Search Entity/Patient...">
                    <option value="">All Entities</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="staff_user_id" class="form-select form-select-sm ops-tab-filter" data-tab="staff_bills">
                    <option value="">Staff Member</option>
                    @foreach($users as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="staff_bills">
                    <option value="">Status</option>
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="is_audited" class="form-select form-select-sm ops-tab-filter" data-tab="staff_bills">
                    <option value="">Audit Status</option>
                    <option value="0">Pending Audit</option>
                    <option value="1">Audited</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-staff-bills">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Staff Member</th>
                        <th>Total</th>
                        <th>Discount</th>
                        <th>Outstanding</th>
                        <th>Status</th>
                        <th>Settlement Payment</th>
                        <th>Settled At</th>
                        <th>Audited</th>
                        <th>Cashier</th>
                        <th>Pay Status</th>
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
    var paymentsUrl = "{{ route('ops-audit.billing.data', 'payments') }}";
    var orgBillsUrl = "{{ route('ops-audit.billing.data', 'organization_bills') }}";
    var staffBillsUrl = "{{ route('ops-audit.billing.data', 'staff_bills') }}";

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
                zeroRecords: '<div class="text-center py-3 text-muted"><i class="mdi mdi-database-off" style="font-size:2rem;"></i><br>No records found for this filter.</div>',
                processing: '<div class="text-center py-3"><i class="mdi mdi-loading mdi-spin text-primary" style="font-size:1.5rem;"></i> Loading...</div>'
            }
        };
    }

    var dtPayments = $('#dt-payments').DataTable(commonOpts(paymentsUrl, [
        { data: 'date_time' },
        { data: 'patient' },
        { data: 'hmo' },
        { data: 'ref_no' },
        { data: 'total' },
        { data: 'discount' },
        { data: 'method' },
        { data: 'type' },
        { data: 'bank' },
        { data: 'entity' },
        { data: 'balance' },
        { data: 'cashier' },
        { data: 'shift' },
        { data: 'audit', orderable: false, searchable: false }
    ], 'kpi-payments'));

    var dtOrgBills = null;
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).attr('href');

        if (target === '#pane-organization-bills' && !dtOrgBills) {
            dtOrgBills = $('#dt-organization-bills').DataTable(commonOpts(orgBillsUrl, [
                { data: 'date' },
                { data: 'patient' },
                { data: 'organization' },
                { data: 'total' },
                { data: 'discount' },
                { data: 'outstanding' },
                { data: 'status' },
                { data: 'settlement_by' },
                { data: 'settled_at' },
                { data: 'audited' },
                { data: 'cashier' },
                { data: 'method' },
                { data: 'pay_status' },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-organization-bills'));
        }

        if (target === '#pane-staff-bills' && !window.dtStaffBills) {
            window.dtStaffBills = $('#dt-staff-bills').DataTable(commonOpts(staffBillsUrl, [
                { data: 'date' },
                { data: 'patient' },
                { data: 'staff_member' },
                { data: 'total' },
                { data: 'discount' },
                { data: 'outstanding' },
                { data: 'status' },
                { data: 'settlement_payment' },
                { data: 'settled_at' },
                { data: 'audited' },
                { data: 'cashier' },
                { data: 'pay_status' },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-staff-bills'));
        }

        setTimeout(function() {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }, 200);
    });

    $('#btnApplyFilters').on('click', function() {
        if (dtPayments) dtPayments.ajax.reload();
        if (dtOrgBills) dtOrgBills.ajax.reload();
        if (window.dtStaffBills) window.dtStaffBills.ajax.reload();
    });

    $(document).on('change', '.ops-tab-filter', function() {
        var tab = $(this).data('tab');
        if (tab === 'payments' && dtPayments) dtPayments.ajax.reload();
        if (tab === 'organization_bills' && dtOrgBills) dtOrgBills.ajax.reload();
        if (tab === 'staff_bills' && window.dtStaffBills) window.dtStaffBills.ajax.reload();
    });
});

</script>
@endpush
