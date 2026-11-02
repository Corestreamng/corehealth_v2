@extends('admin.layouts.app')
@section('title', 'Staff Management')
@section('page_name', 'Staff Management')
@section('subpage_name', 'All Staff')

@section('style')
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
    <link rel="stylesheet" href="{{ asset('css/staff-directory.css') }}">
@endsection

@section('content')
    <div class="container-fluid">
        <div class="card-modern">
            <div class="card-header-modern d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1 font-weight-bold text-dark">
                        <i class="mdi mdi-account-group text-primary"></i> Staff Directory
                    </h2>
                    <p class="text-muted mb-0">Manage all staff members, employment statuses, locum coverage, and permissions</p>
                </div>
                <div class="d-flex align-items-center">
                    <a href="{{ route('locum.index') }}" class="btn btn-outline-primary btn-sm mr-2" id="btn-locum-management">
                        <i class="mdi mdi-badge-account-outline"></i> Locum Management & Passes
                    </a>
                    <a href="{{ route('staff.create') }}" class="btn btn-primary btn-sm">
                        <i class="mdi mdi-plus"></i> Add New Staff
                    </a>
                </div>
            </div>

            <div class="card-body">
                {{-- Filter Bar --}}
                <div class="filter-bar d-flex align-items-center gap-2 flex-wrap">
                    <label class="mb-0 mr-2 font-weight-bold text-dark"><i class="mdi mdi-filter-outline"></i> Filters:</label>
                    <select id="filter-department" class="form-control form-control-sm form-control-modern">
                        <option value="all">All Departments</option>
                        @foreach ($departments as $deptId => $deptName)
                            <option value="{{ $deptId }}">{{ $deptName }}</option>
                        @endforeach
                    </select>
                    <select id="filter-category" class="form-control form-control-sm form-control-modern">
                        <option value="all">All Categories</option>
                        @foreach ($statuses as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    <select id="filter-type" class="form-control form-control-sm form-control-modern">
                        <option value="all">All Employment Types</option>
                        <option value="full_time">Full Time</option>
                        <option value="part_time">Part Time</option>
                        <option value="contract">Contract</option>
                        <option value="intern">Intern</option>
                        <option value="locum">Locum Practitioners</option>
                    </select>
                    <select id="filter-status" class="form-control form-control-sm form-control-modern">
                        <option value="all">All Statuses</option>
                        <option value="active">Active & Probation</option>
                        <option value="on_leave">On Leave</option>
                        <option value="suspended">Suspended</option>
                        <option value="resigned">Resigned</option>
                        <option value="terminated">Terminated</option>
                        <option value="retired">Retired</option>
                        <option value="inactive">All Inactive</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table id="staffTable" class="table table-sm table-bordered table-striped staff-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Staff</th>
                                <th>Department</th>
                                <th>Contact</th>
                                <th>Category & Roles</th>
                                <th>Status</th>
                                <th style="width: 90px;">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Status Transition Modal --}}
    @include('admin.staff.partials._quick_status_modal')
@endsection

@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script>
        window.STAFF_DIRECTORY_CONFIG = {
            listUrl: "{{ route('listStaff') }}",
            updateStatusUrl: "{{ url('staff') }}"
        };
    </script>
    <script src="{{ asset('js/staff-directory.js') }}" defer></script>
@endsection
