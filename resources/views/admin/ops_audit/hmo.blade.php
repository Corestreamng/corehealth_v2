@extends('admin.ops_audit.layout')

@section('title', 'Ops Audit — HMO / Insurance')

@section('ops_audit_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="font-weight-bold mb-0"><i class="mdi mdi-hospital-building text-primary me-1"></i> HMO / Insurance Audit</h5>
        <small class="text-muted">HMO claims, validations, and remittances</small>
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
<ul class="nav nav-tabs ops-tabs mb-0" id="hmoTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="tab-claims" data-bs-toggle="tab" href="#pane-claims" role="tab">
            <i class="mdi mdi-file-document-outline me-1"></i> HMO Claims
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-coverage" data-bs-toggle="tab" href="#pane-coverage" role="tab">
            <i class="mdi mdi-shield-check me-1"></i> Validations (Coverage Mode)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-remittances" data-bs-toggle="tab" href="#pane-remittances" role="tab">
            <i class="mdi mdi-cash-multiple me-1"></i> HMO Remittances
        </a>
    </li>
</ul>

<div class="tab-content bg-white border border-top-0 rounded-bottom p-3">
    {{-- Tab 1: Claims --}}
    <div class="tab-pane fade show active" id="pane-claims" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-claims"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="claims">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="paid">Paid</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="created_by" class="form-select form-select-sm ops-tab-filter" data-tab="claims">
                    <option value="">Created By</option>
                    @foreach($users as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="processed_by" class="form-select form-select-sm ops-tab-filter" data-tab="claims">
                    <option value="">Processed By</option>
                    @foreach($users as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="cashier_id" class="form-select form-select-sm ops-tab-filter" data-tab="claims">
                    <option value="">Cashier</option>
                    @foreach($users as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-claims">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO (Scheme)</th>
                        <th>Claims Amount</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Processed By</th>
                        <th>Payment Ref</th>
                        <th>Cashier</th>
                        <th>Pay Status</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 2: Coverage --}}
    <div class="tab-pane fade" id="pane-coverage" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-coverage"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="coverage_mode" class="form-select form-select-sm ops-tab-filter" data-tab="coverage">
                    <option value="">All Modes</option>
                    <option value="express">Express (Auto)</option>
                    <option value="primary">Primary (Validate)</option>
                    <option value="secondary">Secondary (Auth Code)</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="validation_status" class="form-select form-select-sm ops-tab-filter" data-tab="coverage">
                    <option value="">Validation Status</option>
                    <option value="pending">Pending</option>
                    <option value="validated">Validated</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm ops-tab-filter" data-tab="coverage">
                    <option value="">Item Type</option>
                    <option value="drug">Drug</option>
                    <option value="service">Service</option>
                    <option value="lab">Lab Test</option>
                    <option value="imaging">Imaging</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="has_auth_code" class="form-select form-select-sm ops-tab-filter" data-tab="coverage">
                    <option value="">Auth Code Y/N</option>
                    <option value="yes">Has Auth Code</option>
                    <option value="no">No Auth Code</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="validated_by" class="form-select form-select-sm ops-tab-filter" data-tab="coverage">
                    <option value="">Validated By</option>
                    @foreach($users as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-coverage">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO (Scheme)</th>
                        <th>Coverage Mode</th>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Amount</th>
                        <th>Payable</th>
                        <th>Claims</th>
                        <th>Auth Code</th>
                        <th>Validated By</th>
                        <th>Val Status</th>
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

    {{-- Tab 3: Remittances --}}
    <div class="tab-pane fade" id="pane-remittances" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-remittances"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="bank_id" class="form-select form-select-sm ops-tab-filter" data-tab="remittances">
                    <option value="">All Banks</option>
                    @foreach($banks as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="payment_method" class="form-select form-select-sm ops-tab-filter" data-tab="remittances">
                    <option value="">Payment Method</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cheque">Cheque</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="created_by" class="form-select form-select-sm ops-tab-filter" data-tab="remittances">
                    <option value="">Created By</option>
                    @foreach($users as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-remittances">
                <thead>
                    <tr>
                        <th>Payment Date</th>
                        <th>HMO</th>
                        <th>Period From-To</th>
                        <th>Amount Remitted</th>
                        <th>Ref No</th>
                        <th>Payment Method</th>
                        <th>Receiving Bank</th>
                        <th>Created By</th>
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
    var claimsUrl = "{{ route('ops-audit.hmo.data', 'claims') }}";
    var coverageUrl = "{{ route('ops-audit.hmo.data', 'coverage') }}";
    var remittancesUrl = "{{ route('ops-audit.hmo.data', 'remittances') }}";

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

    var dtClaims = $('#dt-claims').DataTable(commonOpts(claimsUrl, [
        { data: 'date' },
        { data: 'patient' },
        { data: 'hmo' },
        { data: 'claims_amount' },
        { data: 'status' },
        { data: 'created_by' },
        { data: 'processed_by' },
        { data: 'payment_ref' },
        { data: 'cashier' },
        { data: 'pay_status' },
        { data: 'audit', orderable: false, searchable: false }
    ], 'kpi-claims'));

    var dtCoverage = null;
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var target = $(e.target).attr('href');

        if (target === '#pane-coverage' && !dtCoverage) {
            dtCoverage = $('#dt-coverage').DataTable(commonOpts(coverageUrl, [
                { data: 'date' },
                { data: 'patient' },
                { data: 'hmo' },
                { data: 'coverage_mode' },
                { data: 'type' },
                { data: 'item' },
                { data: 'qty' },
                { data: 'amount' },
                { data: 'payable' },
                { data: 'claims' },
                { data: 'auth_code' },
                { data: 'validated_by' },
                { data: 'validation_status' },
                { data: 'cashier' },
                { data: 'method' },
                { data: 'pay_status' },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-coverage'));
        }

        if (target === '#pane-remittances' && !window.dtRemittances) {
            window.dtRemittances = $('#dt-remittances').DataTable(commonOpts(remittancesUrl, [
                { data: 'payment_date' },
                { data: 'hmo' },
                { data: 'period' },
                { data: 'amount_remitted' },
                { data: 'ref_no' },
                { data: 'payment_method' },
                { data: 'bank' },
                { data: 'created_by' },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-remittances'));
        }

        setTimeout(function() {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }, 200);
    });

    $('#btnApplyFilters').on('click', function() {
        if (dtClaims) dtClaims.ajax.reload();
        if (dtCoverage) dtCoverage.ajax.reload();
        if (window.dtRemittances) window.dtRemittances.ajax.reload();
    });

    $(document).on('change', '.ops-tab-filter', function() {
        var tab = $(this).data('tab');
        if (tab === 'claims' && dtClaims) dtClaims.ajax.reload();
        if (tab === 'coverage' && dtCoverage) dtCoverage.ajax.reload();
        if (tab === 'remittances' && window.dtRemittances) window.dtRemittances.ajax.reload();
    });
});

</script>
@endpush
