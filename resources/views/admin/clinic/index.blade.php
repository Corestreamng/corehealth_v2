@extends('admin.layouts.app')
@section('title', 'Clinics List')
@section('page_name', 'Clinic')
@section('subpage_name', 'List Clinics')

@section('content')
    <div class="container-fluid">
        <div class="card-modern shadow-sm border-0">
            <div class="card-header-modern d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 fw-bold text-dark">
                        <i class="mdi mdi-hospital-building text-primary"></i> Clinics & Departments
                    </h4>
                    <p class="text-muted mb-0 small">Manage hospital clinics and their specialized vitals configurations.</p>
                </div>
                <a href="{{ route('clinics.create') }}" class="btn btn-primary btn-sm shadow-sm">
                    <i class="mdi mdi-plus"></i> Create New Clinic
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered" id="clinics-table" style="width: 100%">
                        <thead class="bg-light text-dark">
                            <tr>
                                <th width="50">#</th>
                                <th>Clinic Name</th>
                                <th>Vitals Metrics</th>
                                <th width="150" class="text-center">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#clinics-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('clinics.index') }}",
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { 
                        data: 'name', 
                        name: 'name',
                        render: function(data, type, row) {
                            return `<div class="fw-bold text-primary">${data}</div>`;
                        }
                    },
                    { 
                        data: 'vitals_count', 
                        name: 'vitals_count',
                        render: function(data, type, row) {
                            return `<span class="badge bg-soft-info text-info border border-info px-2">${data} Fields Configured</span>`;
                        }
                    },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
                ],
                language: {
                    searchPlaceholder: "Search clinics...",
                    search: ""
                },
                dom: '<"d-flex justify-content-between align-items-center mb-3"fB>rtip',
                buttons: ['copy', 'excel', 'pdf', 'print']
            });
        });
    </script>
@endpush
