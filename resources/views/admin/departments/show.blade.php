@extends('admin.layouts.app')
@section('title', 'View Department')
@section('page_name', 'Administration')
@section('subpage_name', 'View Department')

@section('style')
@php
    $primaryColor = appsettings()->hos_color ?? '#011b33';
@endphp
<style>
    :root {
        --primary-color: {{ $primaryColor }};
        --primary-light: {{ $primaryColor }}15;
    }
</style>
<link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-10 col-md-12 mx-auto">
            <div class="card-modern">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1 font-weight-bold text-dark">{{ $department->name }}</h2>
                        <p class="text-muted mb-0">
                            @if($department->code)
                                <span class="badge badge-secondary mr-2">{{ $department->code }}</span>
                            @endif
                            @if($department->is_active)
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-danger">Inactive</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <a href="{{ route('departments.edit', $department) }}" class="btn btn-warning">
                            <i class="mdi mdi-pencil"></i> Edit
                        </a>
                        <a href="{{ route('departments.index') }}" class="btn btn-light border">
                            <i class="mdi mdi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card-modern bg-light mb-3">
                                <div class="card-body p-3">
                                    <h6 class="text-muted mb-2">Head of Department</h6>
                                    @if($department->headOfDepartment)
                                        <div class="d-flex align-items-center">
                                            <img src="{{ asset('storage/image/user/' . ($department->headOfDepartment->filename ?? 'avatar.png')) }}"
                                                 class="rounded-circle mr-3"
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                            <div>
                                                <strong>{{ $department->headOfDepartment->name }}</strong>
                                                <br>
                                                <small class="text-muted">{{ $department->headOfDepartment->email }}</small>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted">Not assigned</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card-modern bg-light mb-3">
                                <div class="card-body p-3">
                                    <h6 class="text-muted mb-2">Statistics</h6>
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <h3 class="mb-0 text-primary">{{ $department->staff->count() }}</h3>
                                            <small class="text-muted">Total Staff</small>
                                        </div>
                                        <div class="col-6">
                                            <h3 class="mb-0 text-success">{{ $department->staff->where('employment_status', 'active')->count() }}</h3>
                                            <small class="text-muted">Active Staff</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($department->description)
                    <div class="mb-4">
                        <h6 class="text-muted mb-2">Description</h6>
                        <p>{{ $department->description }}</p>
                    </div>
                    @endif

                    <hr>

                    <h5 class="mb-3">
                        <i class="mdi mdi-account-group text-primary"></i> Staff Members ({{ $department->staff->count() }})
                    </h5>

                    @if($department->staff->isEmpty())
                        <div class="text-center py-4">
                            <i class="mdi mdi-account-off-outline" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-2">No staff assigned to this department yet.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover" id="staffTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Job Title</th>
                                        <th>Employment Type</th>
                                        <th>Status</th>
                                        <th>Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($department->staff as $staff)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="{{ asset('storage/image/user/' . ($staff->user->filename ?? 'avatar.png')) }}"
                                                         class="rounded-circle mr-2"
                                                         style="width: 35px; height: 35px; object-fit: cover;">
                                                    <div>
                                                        <strong>{{ $staff->user->name ?? 'N/A' }}</strong>
                                                        @if($staff->is_dept_head)
                                                            <span class="badge badge-warning ml-1">HOD</span>
                                                        @endif
                                                        @if($staff->is_unit_head)
                                                            <span class="badge badge-info ml-1">Unit Head</span>
                                                        @endif
                                                        <br>
                                                        <small class="text-muted">{{ $staff->employee_id ?? 'No ID' }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ $staff->job_title ?? '-' }}</td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $staff->employment_type ?? '-')) }}</td>
                                            <td>
                                                @if($staff->employment_status == 'active')
                                                    <span class="badge badge-success">Active</span>
                                                @elseif($staff->employment_status == 'suspended')
                                                    <span class="badge badge-warning">Suspended</span>
                                                @else
                                                    <span class="badge badge-secondary">{{ ucfirst($staff->employment_status ?? 'N/A') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($staff->user && $staff->user->roles->count() > 0)
                                                    @foreach($staff->user->roles as $role)
                                                        <span class="badge badge-outline-primary">{{ $role->name }}</span>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        $('#staffTable').DataTable({
            pageLength: 10,
            order: [[1, 'asc']],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search staff..."
            }
        });
    });
</script>
@endsection
