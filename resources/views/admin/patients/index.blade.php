@extends('admin.layouts.app')
@section('title', 'Patients List')
@section('page_name', 'Patients')
@section('subpage_name', 'List Patients')
@push('styles')
    <link rel="stylesheet" href="{{ asset('plugins/select2/select2.min.css') }}">
@endpush
@section('content')
    <div id="content-wrapper">
        <div class="container-fluid">

            {{-- ── Stats Cards ─────────────────────────────────────────── --}}
            <div class="row mb-3">
                <div class="col-6 col-md-3">
                    <div class="card-modern text-center py-3">
                        <div class="card-body p-2">
                            <div class="text-muted small mb-1"><i class="mdi mdi-account-group mr-1"></i>Total Patients</div>
                            <h3 class="mb-0 text-primary font-weight-bold">{{ number_format($stats['total']) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card-modern text-center py-3">
                        <div class="card-body p-2">
                            <div class="text-muted small mb-1"><i class="mdi mdi-hospital-building mr-1"></i>HMO Patients</div>
                            <h3 class="mb-0 text-success font-weight-bold">{{ number_format($stats['hmo']) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card-modern text-center py-3">
                        <div class="card-body p-2">
                            <div class="text-muted small mb-1"><i class="mdi mdi-calendar-today mr-1"></i>Registered Today</div>
                            <h3 class="mb-0 text-info font-weight-bold">{{ number_format($stats['today']) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card-modern text-center py-3">
                        <div class="card-body p-2">
                            <div class="text-muted small mb-1"><i class="mdi mdi-calendar-month mr-1"></i>This Month</div>
                            <h3 class="mb-0 text-warning font-weight-bold">{{ number_format($stats['month']) }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Main Card ────────────────────────────────────────────── --}}
            <div class="card-modern">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0"><i class="mdi mdi-account-group mr-2"></i>Patients</h3>
                    @can('patient-create')
                        <a href="{{ route('patient.create') }}" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-account-plus mr-1"></i> Add Patient
                        </a>
                    @endcan
                </div>

                {{-- ── Filter Bar ───────────────────────────────────────── --}}
                <div class="filter-bar border-bottom p-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-sm-6 col-md-3">
                            <label class="form-label small mb-1">HMO / Insurance</label>
                            <select id="filterHmo" class="form-control form-control-sm" style="width:100%">
                                <option value="">— All HMOs —</option>
                                @foreach($hmosByScheme as $schemeName => $schemeHmos)
                                    <optgroup label="{{ $schemeName }}">
                                        @foreach($schemeHmos as $hmo)
                                            <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label small mb-1">Scheme</label>
                            <select id="filterScheme" class="form-control form-control-sm">
                                <option value="">— All Schemes —</option>
                                @foreach($schemes as $scheme)
                                    <option value="{{ $scheme->id }}">{{ $scheme->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label small mb-1">Gender</label>
                            <select id="filterGender" class="form-control form-control-sm">
                                <option value="">— All —</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label small mb-1">From</label>
                            <input type="date" id="filterDateFrom" class="form-control form-control-sm">
                        </div>
                        <div class="col-6 col-sm-6 col-md-1">
                            <label class="form-label small mb-1">To</label>
                            <input type="date" id="filterDateTo" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-sm-6 col-md-2 d-flex gap-2 mt-2 mt-md-0">
                            <button id="applyFilters" class="btn btn-primary btn-sm flex-fill">
                                <i class="mdi mdi-filter mr-1"></i> Filter
                            </button>
                            <button id="resetFilters" class="btn btn-outline-secondary btn-sm flex-fill">
                                <i class="mdi mdi-refresh mr-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="patient-list" class="table table-sm table-bordered table-striped" style="width:100%">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Patient</th>
                                    <th>Gender / Age</th>
                                    <th>HMO</th>
                                    <th>Scheme</th>
                                    <th>HMO No</th>
                                    <th>Phone / Email</th>
                                    <th>NOK</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script src="{{ asset('plugins/select2/select2.min.js') }}" defer></script>

    <script>
        $(function () {
            // Select2 for HMO filter
            $('#filterHmo').select2({
                placeholder: '— All HMOs —',
                allowClear: true,
                width: '100%'
            });

            var table = $('#patient-list').DataTable({
                dom: 'Bfrtip',
                iDisplayLength: 50,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                buttons: ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ url("patientsList") }}',
                    type: 'GET',
                    data: function (d) {
                        d.hmo_id     = $('#filterHmo').val();
                        d.scheme_id  = $('#filterScheme').val();
                        d.gender     = $('#filterGender').val();
                        d.date_from  = $('#filterDateFrom').val();
                        d.date_to    = $('#filterDateTo').val();
                    }
                },
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' },
                    {
                        data: 'fullname', name: 'fullname',
                        render: function (data, type, row) {
                            var url = '{{ route("patient.show", ":id") }}'.replace(':id', row.id);
                            var name = '<a href="' + url + '" class="font-weight-bold text-dark">' + (data || '—') + '</a>';
                            var file = row.file_no ? '<br><span class="text-muted small"><i class="mdi mdi-identifier mr-1"></i>' + row.file_no + '</span>' : '';
                            return name + file;
                        }
                    },
                    {
                        data: 'gender', name: 'gender', orderable: false,
                        render: function (data, type, row) {
                            var g = data || '—';
                            var age = row.age || '';
                            var gBadge = g === 'Male'
                                ? '<span class="badge badge-info">' + g + '</span>'
                                : g === 'Female'
                                ? '<span class="badge badge-danger">' + g + '</span>'
                                : '<span class="badge badge-secondary">' + g + '</span>';
                            return gBadge + (age ? '<br><small class="text-muted">' + age + '</small>' : '');
                        }
                    },
                    { data: 'hmo_id', name: 'hmo_id' },
                    { data: 'scheme', name: 'scheme', orderable: false, searchable: false },
                    { data: 'hmo_no', name: 'hmo_no' },
                    {
                        data: 'phone_no', name: 'phone_no', orderable: false,
                        render: function (data, type, row) {
                            var phone = data ? '<div><i class="mdi mdi-phone-outline mr-1 text-muted"></i>' + data + '</div>' : '';
                            var email = row.email && row.email !== 'N/A'
                                ? '<div class="small text-muted"><i class="mdi mdi-email-outline mr-1"></i>' + row.email + '</div>'
                                : '';
                            return phone + email || '—';
                        }
                    },
                    {
                        data: 'nok', name: 'nok', orderable: false, searchable: false,
                        render: function(data) {
                            if (!data || data === 'N/A') return '<span class="text-muted">—</span>';
                            var parts = data.split(' · ');
                            var out = '<div class="small">' + (parts[0] || '') + '</div>';
                            if (parts[1]) out += '<div class="small text-muted"><i class="mdi mdi-phone-outline mr-1"></i>' + parts[1] + '</div>';
                            return out;
                        }
                    },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false }
                ],
                paging: true
            });

            $('#applyFilters').on('click', function () { table.ajax.reload(); });
            $('#resetFilters').on('click', function () {
                $('#filterHmo').val(null).trigger('change');
                $('#filterScheme, #filterGender').val('');
                $('#filterDateFrom, #filterDateTo').val('');
                table.ajax.reload();
            });
        });
    </script>
@endsection
