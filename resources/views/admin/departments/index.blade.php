@extends('admin.layouts.app')
@section('title', 'Departments')
@section('page_name', 'Administration')
@section('subpage_name', 'Departments')

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
        <div class="col-12">
            <div class="card-modern">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1 font-weight-bold text-dark">Departments</h2>
                        <p class="text-muted mb-0">Manage organizational departments</p>
                    </div>
                    <a href="{{ route('departments.create') }}" class="btn btn-primary-modern">
                        <i class="mdi mdi-plus"></i> Add Department
                    </a>
                </div>
                <div class="card-body p-4">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @if($departments->isEmpty())
                        <div class="text-center py-5">
                            <i class="mdi mdi-office-building-outline" style="font-size: 4rem; color: #ccc;"></i>
                            <p class="text-muted mt-3">No departments found. Create your first department!</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover" id="departmentsTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="15%">Code</th>
                                        <th width="25%">Name</th>
                                        <th width="20%">Head of Dept</th>
                                        <th width="10%">Staff Count</th>
                                        <th width="10%">Status</th>
                                        <th width="15%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($departments as $department)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                @if($department->code)
                                                    <span class="badge badge-secondary">{{ $department->code }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <strong>{{ $department->name }}</strong>
                                                @if($department->description)
                                                    <br><small class="text-muted">{{ Str::limit($department->description, 50) }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if($department->headOfDepartment)
                                                    <i class="mdi mdi-account-tie text-primary"></i>
                                                    {{ $department->headOfDepartment->name }}
                                                @else
                                                    <span class="text-muted">Not assigned</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-info">{{ $department->staff_count ?? $department->staff->count() }}</span>
                                            </td>
                                            <td>
                                                @if($department->is_active)
                                                    <span class="badge badge-success">Active</span>
                                                @else
                                                    <span class="badge badge-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('departments.show', $department) }}" class="btn btn-sm btn-outline-info" title="View">
                                                    <i class="mdi mdi-eye"></i>
                                                </a>
                                                <a href="{{ route('departments.edit', $department) }}" class="btn btn-sm btn-outline-warning" title="Edit">
                                                    <i class="mdi mdi-pencil"></i>
                                                </a>
                                                <form action="{{ route('departments.destroy', $department) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Are you sure you want to delete this department?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="mdi mdi-delete"></i>
                                                    </button>
                                                </form>
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
        $('#departmentsTable').DataTable({
            pageLength: 25,
            order: [[2, 'asc']],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search departments..."
            }
        });
    });
</script>
@endsection
