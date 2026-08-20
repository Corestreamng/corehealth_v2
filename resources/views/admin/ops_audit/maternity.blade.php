@extends('admin.ops_audit.layout')

@section('title', 'Ops Audit — Maternity')

@section('ops_audit_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="font-weight-bold mb-0"><i class="mdi mdi-human-pregnant text-primary me-1"></i> Maternity Audit</h5>
        <small class="text-muted">Enrollments, ANC, Deliveries, Babies, Postnatal, Immunizations, Bills</small>
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
        <div class="col-md-auto">
            <button type="button" class="btn btn-primary btn-sm font-weight-bold px-3" id="btnApplyFilters">
                <i class="mdi mdi-filter me-1"></i> Apply
            </button>
        </div>
    </div>
</form>

{{-- Tabs --}}
<ul class="nav nav-tabs ops-tabs mb-0" id="maternityTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="tab-enrollments" data-bs-toggle="tab" href="#pane-enrollments" role="tab">
            <i class="mdi mdi-account-multiple-plus me-1"></i> Enrollments
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-anc" data-bs-toggle="tab" href="#pane-anc" role="tab">
            <i class="mdi mdi-stethoscope me-1"></i> ANC Visits
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-deliveries" data-bs-toggle="tab" href="#pane-deliveries" role="tab">
            <i class="mdi mdi-baby-buggy me-1"></i> Deliveries
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-babies" data-bs-toggle="tab" href="#pane-babies" role="tab">
            <i class="mdi mdi-baby me-1"></i> Baby Records
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-postnatal" data-bs-toggle="tab" href="#pane-postnatal" role="tab">
            <i class="mdi mdi-human-female me-1"></i> Postnatal
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-immunizations" data-bs-toggle="tab" href="#pane-immunizations" role="tab">
            <i class="mdi mdi-needle me-1"></i> Immunizations
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="tab-bills" data-bs-toggle="tab" href="#pane-bills" role="tab">
            <i class="mdi mdi-receipt me-1"></i> Bills
        </a>
    </li>
</ul>

<div class="tab-content bg-white border border-top-0 rounded-bottom p-3">
    {{-- Tab 1: Enrollments --}}
    <div class="tab-pane fade show active" id="pane-enrollments" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-enrollments"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm ops-tab-filter" data-tab="enrollments">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="postnatal">Postnatal</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            @include('admin.ops_audit.partials.payment_filters', ['tab' => 'enrollments'])
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-enrollments">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>LMP</th>
                        <th>EDD</th>
                        <th>Gravida/Parity</th>
                        <th>Status</th>
                        <th>ANC Visits</th>
                        <th>Deliveries</th>
                        <th>Postnatal</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 2: ANC Visits --}}
    <div class="tab-pane fade" id="pane-anc" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-anc"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="entity" class="form-select form-select-sm ajax-entity-search ops-tab-filter" data-tab="anc" data-placeholder="Search Entity/Patient...">
                    <option value="">All Entities</option>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-anc">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Visit No</th>
                        <th>Gestational Age</th>
                        <th>BP</th>
                        <th>Weight</th>
                        <th>FHR</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 3: Deliveries --}}
    <div class="tab-pane fade" id="pane-deliveries" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-deliveries"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="entity" class="form-select form-select-sm ajax-entity-search ops-tab-filter" data-tab="deliveries" data-placeholder="Search Entity/Patient...">
                    <option value="">All Entities</option>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-deliveries">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Type</th>
                        <th>Place</th>
                        <th>No of Babies</th>
                        <th>Blood Loss</th>
                        <th>Delivered By</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 4: Baby Records --}}
    <div class="tab-pane fade" id="pane-babies" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-babies"></div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-babies">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Mother</th>
                        <th>Sex</th>
                        <th>Weight</th>
                        <th>APGAR (1m/5m)</th>
                        <th>Status</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 5: Postnatal Visits --}}
    <div class="tab-pane fade" id="pane-postnatal" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-postnatal"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="entity" class="form-select form-select-sm ajax-entity-search ops-tab-filter" data-tab="postnatal" data-placeholder="Search Entity/Patient...">
                    <option value="">All Entities</option>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-postnatal">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Days PP</th>
                        <th>BP</th>
                        <th>Condition</th>
                        <th>Baby Weight</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 6: Immunizations --}}
    <div class="tab-pane fade" id="pane-immunizations" role="tabpanel">
        <div class="row g-2 mb-3 ops-kpi-row" id="kpi-immunizations"></div>

        <div class="row g-2 mb-2">
            <div class="col-md-2">
                <select name="entity" class="form-select form-select-sm ajax-entity-search ops-tab-filter" data-tab="immunizations" data-placeholder="Search Entity/Patient...">
                    <option value="">All Entities</option>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-striped ops-datatable w-100" id="dt-immunizations">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>HMO</th>
                        <th>Vaccine</th>
                        <th>Dose</th>
                        <th>Route</th>
                        <th>Administered At</th>
                        <th>Audit ⚡</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- Tab 7: Maternity Bills --}}
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

</div>
@endsection

@push('ops_audit_scripts')
<script>
    $(function() {
        var dataUrls = {
            enrollments: "{{ route('ops-audit.maternity.data', 'enrollments') }}",
            anc: "{{ route('ops-audit.maternity.data', 'anc') }}",
            deliveries: "{{ route('ops-audit.maternity.data', 'deliveries') }}",
            babies: "{{ route('ops-audit.maternity.data', 'babies') }}",
            postnatal: "{{ route('ops-audit.maternity.data', 'postnatal') }}",
            immunizations: "{{ route('ops-audit.maternity.data', 'immunizations') }}",
            bills: "{{ route('ops-audit.maternity.data', 'bills') }}"
        };

        var dtInstances = {};

        function commonOpts(url, columns, kpiContainer) {
            return {
                dom: '<"d-flex justify-content-between align-items-center mb-2"<"d-flex gap-2"B>f>rt<"d-flex justify-content-between align-items-center mt-2"ip>',
                buttons: [{
                        extend: 'copy',
                        className: 'btn btn-xs btn-outline-secondary font-weight-bold'
                    },
                    {
                        extend: 'excel',
                        className: 'btn btn-xs btn-outline-success font-weight-bold'
                    },
                    {
                        extend: 'pdf',
                        className: 'btn btn-xs btn-outline-danger font-weight-bold'
                    },
                    {
                        extend: 'print',
                        className: 'btn btn-xs btn-outline-info font-weight-bold'
                    }
                ],
                processing: true,
                serverSide: true,
                ajax: {
                    url: url,
                    type: 'GET',
                    data: function(d) {
                        var form = $('#ops_audit_filter_form').serializeArray();
                        form.forEach(function(f) {
                            d[f.name] = f.value;
                        });
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
                order: [
                    [0, 'desc']
                ],
                pageLength: 25,
                lengthMenu: [
                    [10, 25, 50, 100],
                    [10, 25, 50, 100]
                ],
                language: {
                    zeroRecords: '<div class="text-center py-3 text-muted"><i class="mdi mdi-database-off" style="font-size:2rem;"></i><br>No records found.</div>',
                    processing: '<div class="text-center py-3"><i class="mdi mdi-loading mdi-spin text-primary" style="font-size:1.5rem;"></i> Loading...</div>'
                }
            };
        }

        // Init tab 1
        dtInstances.enrollments = $('#dt-enrollments').DataTable(commonOpts(dataUrls.enrollments, [{
                data: 'date'
            }, {
                data: 'patient'
            }, {
                data: 'hmo'
            }, {
                data: 'lmp'
            }, {
                data: 'edd'
            },
            {
                data: 'gravida_parity'
            }, {
                data: 'status'
            }, {
                data: 'anc_count'
            }, {
                data: 'babies_count'
            }, {
                data: 'postnatal_count'
            },
            {
                data: 'audit',
                orderable: false,
                searchable: false
            }
        ], 'kpi-enrollments'));

        // Lazy init others
        $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
            var tabId = $(e.target).attr('id').replace('tab-', '');

            if (tabId === 'anc' && !dtInstances.anc) {
                dtInstances.anc = $('#dt-anc').DataTable(commonOpts(dataUrls.anc, [{
                        data: 'date'
                    }, {
                        data: 'patient'
                    }, {
                        data: 'hmo'
                    }, {
                        data: 'visit_no'
                    }, {
                        data: 'ga'
                    },
                    {
                        data: 'bp'
                    }, {
                        data: 'weight'
                    }, {
                        data: 'fhr'
                    },
                    {
                        data: 'audit',
                        orderable: false,
                        searchable: false
                    }
                ], 'kpi-anc'));
            } else if (tabId === 'deliveries' && !dtInstances.deliveries) {
                dtInstances.deliveries = $('#dt-deliveries').DataTable(commonOpts(dataUrls.deliveries, [{
                        data: 'date'
                    }, {
                        data: 'patient'
                    }, {
                        data: 'hmo'
                    }, {
                        data: 'type'
                    }, {
                        data: 'place'
                    },
                    {
                        data: 'babies'
                    }, {
                        data: 'blood_loss'
                    }, {
                        data: 'delivered_by'
                    },
                    {
                        data: 'audit',
                        orderable: false,
                        searchable: false
                    }
                ], 'kpi-deliveries'));
            } else if (tabId === 'babies' && !dtInstances.babies) {
                dtInstances.babies = $('#dt-babies').DataTable(commonOpts(dataUrls.babies, [{
                        data: 'date'
                    }, {
                        data: 'mother'
                    }, {
                        data: 'sex'
                    }, {
                        data: 'weight'
                    }, {
                        data: 'apgar'
                    },
                    {
                        data: 'status'
                    },
                    {
                        data: 'audit',
                        orderable: false,
                        searchable: false
                    }
                ], 'kpi-babies'));
            } else if (tabId === 'postnatal' && !dtInstances.postnatal) {
                dtInstances.postnatal = $('#dt-postnatal').DataTable(commonOpts(dataUrls.postnatal, [{
                        data: 'date'
                    }, {
                        data: 'patient'
                    }, {
                        data: 'hmo'
                    }, {
                        data: 'days_pp'
                    }, {
                        data: 'bp'
                    },
                    {
                        data: 'condition'
                    }, {
                        data: 'baby_weight'
                    },
                    {
                        data: 'audit',
                        orderable: false,
                        searchable: false
                    }
                ], 'kpi-postnatal'));
            } else if (tabId === 'immunizations' && !dtInstances.immunizations) {
                dtInstances.immunizations = $('#dt-immunizations').DataTable(commonOpts(dataUrls.immunizations, [{
                        data: 'date'
                    }, {
                        data: 'patient'
                    }, {
                        data: 'hmo'
                    }, {
                        data: 'vaccine'
                    }, {
                        data: 'dose'
                    },
                    {
                        data: 'route'
                    }, {
                        data: 'administered_at'
                    },
                    {
                        data: 'audit',
                        orderable: false,
                        searchable: false
                    }
                ], 'kpi-immunizations'));
            } else if (tabId === 'bills' && !dtInstances.bills) {
                dtInstances.bills = $('#dt-bills').DataTable(commonOpts(dataUrls.bills, [{
                        data: 'date'
                    }, {
                        data: 'patient'
                    }, {
                        data: 'hmo'
                    }, {
                        data: 'amount'
                    },
                    { data: 'payment_info' }, { data: 'billed_by' },
                    {
                        data: 'audit',
                        orderable: false,
                        searchable: false
                    }
                ], 'kpi-bills'));
            }

            setTimeout(function() {
                $.fn.dataTable.tables({
                    visible: true,
                    api: true
                }).columns.adjust();
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