@extends('admin.layouts.app')
@section('title', 'Procedure Categories')
@section('page_name', 'Settings')
@section('subpage_name', 'Procedure Categories')
@section('content')
<section class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">Procedure Categories</h3>
                    <p class="text-muted mb-0">Manage surgical specialty categories for procedures</p>
                </div>
                <a href="{{ route('procedure-categories.create') }}" class="btn btn-primary">
                    <i class="fa fa-plus mr-1"></i> Add Category
                </a>
            </div>

            @if(session('message'))
                <div class="alert alert-{{ session('messageType', 'info') }} alert-dismissible fade show" role="alert">
                    {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <div class="card-modern">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="procedure-categories-table" class="table table-hover table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Description</th>
                                    <th>Procedures</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('#procedure-categories-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("procedure-categories.list") }}',
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'code_badge', name: 'code' },
            { data: 'description', name: 'description', defaultContent: '<span class="text-muted">-</span>' },
            { data: 'procedures_count', name: 'procedures_count', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'asc']]
    });
});
</script>
@endsection
