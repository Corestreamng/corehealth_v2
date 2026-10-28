@extends('admin.layouts.app')
@section('title', 'Locum & Ephemeral Access Passes')
@section('page_name', 'Locum Passes')
@section('subpage_name', 'Locum Management')

@section('style')
    <link rel="stylesheet" href="{{ asset('css/locum-management.css') }}">
@endsection

@section('content')
<div class="container-fluid">
    {{-- Header Ribbon --}}
    <div class="card mb-3 border-0 shadow-sm">
        <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h3 class="font-weight-bold text-dark mb-1">
                    <i class="mdi mdi-badge-account-outline text-primary me-2"></i> Locum Management & Ephemeral Access Passes
                </h3>
                <p class="text-muted mb-0 small">Shift-bounded temporary clinical credentials, Spatie role scoping, and regulatory compliance (HIPAA & Caldicott).</p>
            </div>
            <div class="d-flex align-items-center mt-2 mt-md-0">
                <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary btn-sm mr-2">
                    <i class="mdi mdi-arrow-left me-1"></i> Staff Directory
                </a>
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-bs-toggle="modal" data-target="#modal-create-locum" data-bs-target="#modal-create-locum">
                    <i class="mdi mdi-plus-circle me-1"></i> Issue Locum Pass / Register Locum
                </button>
            </div>
        </div>
    </div>

    {{-- KPI Telemetry Cards --}}
    <div class="row mb-3">
        <div class="col-xl-3 col-md-6 mb-2">
            <div class="locum-kpi-card d-flex align-items-center">
                <div class="locum-kpi-icon bg-success-light text-success me-3">
                    <i class="mdi mdi-check-circle"></i>
                </div>
                <div>
                    <h3 class="mb-0 font-weight-bold text-success">{{ $activePasses }}</h3>
                    <small class="text-muted font-weight-bold">Active Shift Passes</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-2">
            <div class="locum-kpi-card d-flex align-items-center">
                <div class="locum-kpi-icon bg-warning-light text-warning me-3">
                    <i class="mdi mdi-clock-alert"></i>
                </div>
                <div>
                    <h3 class="mb-0 font-weight-bold text-warning">{{ $expiringSoon }}</h3>
                    <small class="text-muted font-weight-bold">Expiring Soon (&lt; 2 hrs)</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-2">
            <div class="locum-kpi-card d-flex align-items-center">
                <div class="locum-kpi-icon bg-primary-light text-primary me-3">
                    <i class="mdi mdi-doctor"></i>
                </div>
                <div>
                    <h3 class="mb-0 font-weight-bold text-primary">{{ $totalLocums }}</h3>
                    <small class="text-muted font-weight-bold">Total Locum Personnel</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-2">
            <div class="locum-kpi-card d-flex align-items-center">
                <div class="locum-kpi-icon bg-danger-light text-danger me-3">
                    <i class="mdi mdi-cancel"></i>
                </div>
                <div>
                    <h3 class="mb-0 font-weight-bold text-danger">{{ $revokedPasses }}</h3>
                    <small class="text-muted font-weight-bold">Revoked Passes (7 Days)</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Main DataTable Card --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-3">
            {{-- Filter Strip --}}
            <div class="locum-filter-strip d-flex align-items-center flex-wrap">
                <span class="filter-label font-weight-bold text-muted mr-3 mb-2">
                    <i class="mdi mdi-filter-variant mr-1"></i> Filters:
                </span>

                <div class="filter-item mr-3 mb-2" style="min-width: 220px;">
                    <select id="filter-cadre" class="form-control form-control-sm">
                        <option value="all">All Cadres & Specialties</option>
                        @foreach($cadres as $key => $cadre)
                            <option value="{{ $key }}">{{ $cadre['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-item mr-3 mb-2" style="min-width: 180px;">
                    <select id="filter-status" class="form-control form-control-sm">
                        <option value="all">All Statuses</option>
                        <option value="active" selected>Active Shift</option>
                        <option value="expired">Expired Passes</option>
                        <option value="revoked">Revoked Passes</option>
                    </select>
                </div>

                <div class="filter-item mr-3 mb-2" style="min-width: 220px;">
                    <select id="filter-clinic" class="form-control form-control-sm">
                        <option value="all">All Clinics & Units</option>
                        @foreach($clinics as $cId => $cName)
                            <option value="{{ $cId }}">{{ $cName }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Table --}}
            <div class="table-responsive">
                <table id="locumTable" class="table table-sm table-hover locum-table w-100">
                    <thead>
                        <tr>
                            <th>Locum Practitioner</th>
                            <th>Cadre & Assigned Roles</th>
                            <th>Licensing & Council</th>
                            <th>Shift Duty Status</th>
                            <th class="text-center" style="width: 100px;">PIN Key</th>
                            <th class="text-center" style="width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('admin.locum.partials._modals')
@endsection

@section('scripts')
    <script>
        window.LOCUM_CONFIG = {
            baseUrl: '{{ url("/locum") }}',
            dataUrl: '{{ route("locum.data") }}',
            storeUrl: '{{ route("locum.store") }}',
            issuePassUrl: '{{ url("/locum/__UID__/issue-pass") }}',
            extendPassUrl: '{{ url("/locum/pass/__PID__/extend") }}',
            revokePassUrl: '{{ url("/locum/pass/__PID__/revoke") }}',
            printPassUrl: '{{ url("/locum/pass/__PID__/print") }}',
            passDetailsUrl: '{{ url("/locum/pass/__PID__/details") }}'
        };
    </script>
    <script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
    <script src="{{ asset('js/locum-management.js') }}"></script>
@endsection
