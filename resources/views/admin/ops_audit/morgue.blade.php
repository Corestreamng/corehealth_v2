@extends('admin.ops_audit.layout')

@section('title', 'Ops Audit — Morgue')

@section('ops_audit_content')
@include('admin.ops_audit.partials._stepped_story_bar', [
    'title' => 'Morgue Audit',
    'subtitle' => 'Admissions, Bills, Cashbook',
    'icon' => 'mdi-coffin',
    'step3_slot' => '
        <div class="col-12">
            <label class="form-label text-muted small fw-bold mb-1">HMO</label>
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
    '
])

{{-- Tabs --}}
<ul class="nav nav-tabs ops-tabs mb-0" id="morgueTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="tab-admissions" data-bs-toggle="tab" href="#pane-admissions" role="tab">
            <i class="mdi mdi-coffin me-1"></i> Admissions
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-bills" data-bs-toggle="tab" href="#pane-bills" role="tab">
            <i class="mdi mdi-receipt me-1"></i> Bills
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-cashbook" data-bs-toggle="tab" href="#pane-cashbook" role="tab">
            <i class="mdi mdi-cash-register me-1"></i> Cashbook
        </a>
    </li>
</ul>

<div class="tab-content bg-white border border-top-0 rounded-bottom p-3">
    {{-- Tab 1: Admissions --}}
    <div class="tab-pane fade show active" id="pane-admissions" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-admissions"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="admissions">
                    <option value="">All Statuses</option>
                    <option value="admitted">Admitted</option>
                    <option value="released">Released</option>
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
                        <th>Body Code</th>
                        <th>Location</th>
                        <th>Arrival</th>
                        <th>Release</th>
                        <th>Status</th>
                        <th style="min-width: 150px;">Payment Info</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 2: Bills --}}
    <div class="tab-pane fade" id="pane-bills" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-bills"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="entity" class="form-select form-select-sm ajax-entity-search ops-tab-filter" data-tab="bills" data-placeholder="Search Entity/Patient...">
                    <option value="">All Entities</option>
                </select>
            </div>
</div>
<div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-bills">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Amount</th>
                        <th>Payment Info</th>
                        <th>Billed By</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    
    {{-- Tab 3: Cashbook --}}
    <div class="tab-pane fade" id="pane-cashbook" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-cashbook"></div>
        
        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="payment_method" class="form-select form-select-sm ops-tab-filter" data-tab="cashbook">
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
                <select name="bank_id" class="form-select form-select-sm ops-tab-filter" data-tab="cashbook">
                    <option value="">All Banks</option>
                    @php $banks = \App\Models\Bank::active()->orderBy('name')->get(); @endphp
                    @foreach($banks as $b) <option value="{{ $b->id }}">{{ $b->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="entity" class="form-select form-select-sm ajax-entity-search ops-tab-filter" data-tab="cashbook" data-placeholder="Search Entity/Patient...">
                    <option value="">All Entities</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="cashier_id" class="form-select form-select-sm ops-tab-filter" data-tab="cashbook">
                    <option value="">All Cashiers</option>
                    @foreach($cashiers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-cashbook">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Item</th>
                        <th width="15%">Entity</th>
                        <th>Patient</th>
                        <th width="12%">Bank</th>
                        <th>Total</th>
                        <th>Method</th>
                        <th>Cashier</th>
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
        admissions: "{{ route('ops-audit.morgue.data', 'admissions') }}",
        bills: "{{ route('ops-audit.morgue.data', 'bills') }}",
        cashbook: "{{ route('ops-audit.morgue.data', 'cashbook') }}"
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
    dtInstances.admissions = $('#dt-admissions').DataTable(commonOpts(dataUrls.admissions, [
        { data: 'date' }, { data: 'patient' }, { data: 'hmo' }, { data: 'body_code' }, { data: 'location' },
        { data: 'arrival' }, { data: 'release' }, { data: 'status' },
        { data: 'payment_info', name: 'payment_info', orderable: false, searchable: false },
        { data: 'audit', orderable: false, searchable: false }
    ], 'kpi-admissions'));

    // Lazy init others
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var tabId = $(e.target).attr('id').replace('tab-', '');
        
        if (tabId === 'bills' && !dtInstances.bills) {
            dtInstances.bills = $('#dt-bills').DataTable(commonOpts(dataUrls.bills, [
                { data: 'date' }, { data: 'patient' }, { data: 'hmo' }, { data: 'amount' },
                { data: 'payment_info' }, { data: 'billed_by' },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-bills'));
        }
        else if (tabId === 'cashbook' && !dtInstances.cashbook) {
            dtInstances.cashbook = $('#dt-cashbook').DataTable(commonOpts(dataUrls.cashbook, [
                { data: 'date' },
                { data: 'reference' },
                { data: 'item' },
                { data: 'entity' },
                { data: 'patient' },
                { data: 'bank' },
                { data: 'total' },
                { data: 'method' },
                { data: 'cashier' },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-cashbook'));
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
