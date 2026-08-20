@extends('admin.ops_audit.layout')

@section('title', 'Ops Audit — Pharmacy')

@section('ops_audit_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="font-weight-bold mb-0"><i class="mdi mdi-pill text-primary me-1"></i> Pharmacy Audit</h5>
        <small class="text-muted">Dispenses, Returns & Damages, Stock Received, Cashbook</small>
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
            <label>Cashier</label>
            <select name="cashier_id" class="form-select">
                <option value="">All Cashiers</option>
                @foreach($cashiers as $id => $name)
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
<ul class="nav nav-tabs ops-tabs mb-0" id="pharmacyTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="tab-dispenses" data-bs-toggle="tab" href="#pane-dispenses" role="tab">
            <i class="mdi mdi-pill me-1"></i> Dispenses
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-returns" data-bs-toggle="tab" href="#pane-returns" role="tab">
            <i class="mdi mdi-keyboard-return me-1"></i> Returns & Damages
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-stock" data-bs-toggle="tab" href="#pane-stock" role="tab">
            <i class="mdi mdi-package-down me-1"></i> Stock Received (Items)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-requisitions" data-bs-toggle="tab" href="#pane-requisitions" role="tab">
            <i class="mdi mdi-truck-delivery me-1"></i> Requisitions
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-cashbook" data-bs-toggle="tab" href="#pane-cashbook" role="tab">
            <i class="mdi mdi-cash-register me-1"></i> Cashbook
        </a>
    </li>
</ul>

<div class="tab-content bg-white border border-top-0 rounded-bottom p-3">
    {{-- Tab 1: Dispenses --}}
    <div class="tab-pane fade show active" id="pane-dispenses" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-dispenses"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="dispenses">
                    <option value="">All (Except Returned)</option>
                    <option value="1">Pending</option>
                    <option value="2">Approved</option>
                    <option value="3" selected>Dispensed</option>
                </select>
            </div>
            @include('admin.ops_audit.partials.payment_filters', ['tab' => 'dispenses'])
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-dispenses">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Doctor</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Status</th>
                        <th style="min-width: 150px;">Payment Info</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 2: Returns & Damages --}}
    <div class="tab-pane fade" id="pane-returns" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-returns"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="entity" class="form-select form-select-sm ajax-entity-search ops-tab-filter" data-tab="returns" data-placeholder="Search Entity/Patient...">
                    <option value="">All Entities</option>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-returns">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Reason</th>
                        <th>Condition</th>
                        <th>Refund</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 3: Stock Received --}}
    <div class="tab-pane fade" id="pane-stock" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-stock"></div>
        
        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="stock">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="fulfilled">Fulfilled</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-stock">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Req No</th>
                        <th>Product</th>
                        <th>Req Qty</th>
                        <th>App Qty</th>
                        <th>Full Qty</th>
                        <th>Status</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 4: Requisitions --}}
    @include('admin.ops_audit.partials.requisitions_tab_pane')

    {{-- Tab 5: Cashbook --}}
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
        dispenses: "{{ route('ops-audit.pharmacy.data', 'dispenses') }}",
        returns: "{{ route('ops-audit.pharmacy.data', 'returns') }}",
        stock: "{{ route('ops-audit.pharmacy.data', 'stock') }}",
        requisitions: "{{ route('ops-audit.pharmacy.data', 'requisitions') }}",
        cashbook: "{{ route('ops-audit.pharmacy.data', 'cashbook') }}"
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
    dtInstances.dispenses = $('#dt-dispenses').DataTable(commonOpts(dataUrls.dispenses, [
        { data: 'date' }, { data: 'patient' }, { data: 'hmo' }, { data: 'doctor' }, { data: 'product' },
        { data: 'qty' }, { data: 'status' }, { data: 'payment_info', name: 'payment_info', orderable: false, searchable: false },
        { data: 'audit', orderable: false, searchable: false }
    ], 'kpi-dispenses'));

    // Lazy init others
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        var tabId = $(e.target).attr('id').replace('tab-', '');
        
        if (tabId === 'returns' && !dtInstances.returns) {
            dtInstances.returns = $('#dt-returns').DataTable(commonOpts(dataUrls.returns, [
                { data: 'date' }, { data: 'patient' }, { data: 'product' }, { data: 'type' }, { data: 'qty' },
                { data: 'reason' }, { data: 'condition' }, { data: 'refund' },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-returns'));
        }
        else if (tabId === 'stock' && !dtInstances.stock) {
            dtInstances.stock = $('#dt-stock').DataTable(commonOpts(dataUrls.stock, [
                { data: 'date' }, { data: 'requisition_no' }, { data: 'product' }, { data: 'requested_qty' },
                { data: 'approved_qty' }, { data: 'fulfilled_qty' }, { data: 'status' },
                { data: 'audit', orderable: false, searchable: false }
            ], 'kpi-stock'));
        }
        else if (tabId === 'requisitions' && !dtInstances.requisitions) {
            dtInstances.requisitions = $('#dt-requisitions').DataTable(commonOpts(dataUrls.requisitions, [
                { data: 'date' }, { data: 'req_no' }, { data: 'from_store' }, { data: 'to_store' },
                { data: 'status' }, { data: 'requested_by' }, { data: 'approved_by' },
                { data: 'fulfilled_by' }, { data: 'items_count' }, 
                { data: 'req_value' }, { data: 'appr_value' }, { data: 'ful_value' }, { data: 'rej_value' },
                { data: 'audit', orderable: false, searchable: false, className: 'text-center' }          ], 'kpi-requisitions'));
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
