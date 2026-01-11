@extends('admin.layouts.app')
@section('title', 'Checklist Templates')
@section('page_name', 'Hospital Setup')
@section('subpage_name', 'Checklist Templates')

@section('content')
<section class="content">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Admission & Discharge Checklist Templates</h3>
                <a href="{{ route('checklist-templates.create') }}" class="btn btn-primary">
                    <i class="fa fa-plus"></i> New Template
                </a>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="mdi mdi-information"></i> 
                    <strong>About Checklists:</strong> 
                    These templates define the checklist items that nurses must complete before a patient can be admitted to a bed or discharged from the hospital.
                </div>
                
                <ul class="nav nav-tabs mb-3" id="templateTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#all-templates">All Templates</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#admission-templates">
                            <i class="mdi mdi-login text-success"></i> Admission
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#discharge-templates">
                            <i class="mdi mdi-logout text-info"></i> Discharge
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="all-templates">
                        <div class="table-responsive">
                            <table id="templatesTable" class="table table-sm table-bordered table-striped display">
                                <thead>
                                    <tr>
                                        <th>S/N</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Items</th>
                                        <th>Status</th>
                                        <th>Edit</th>
                                        <th>Delete</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="admission-templates">
                        <p class="text-muted">Filter showing only admission templates.</p>
                    </div>
                    <div class="tab-pane fade" id="discharge-templates">
                        <p class="text-muted">Filter showing only discharge templates.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Delete Modal -->
<div id="deleteModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Confirm Delete</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <h5 class="text-center">Are you sure you want to delete this template?</h5>
                <p class="text-center text-muted">All checklist items will also be deleted. This action cannot be undone.</p>
                <input type="hidden" id="id_delete">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger delete-confirm">
                    <i class="fa fa-trash"></i> Delete
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fa fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
$(function() {
    var table = $('#templatesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('checklist-template-list') }}",
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'type_badge', name: 'type', orderable: true },
            { data: 'items_count', name: 'items_count', orderable: false },
            { data: 'status_badge', name: 'is_active', orderable: true },
            { data: 'edit', name: 'edit', orderable: false, searchable: false },
            { data: 'delete', name: 'delete', orderable: false, searchable: false }
        ],
        order: [[2, 'asc'], [1, 'asc']]
    });

    // Delete modal
    $(document).on('click', '.delete-modal', function() {
        $('#id_delete').val($(this).data('id'));
        $('#deleteModal').modal('show');
    });

    // Confirm delete
    $('.delete-confirm').click(function() {
        var id = $('#id_delete').val();
        $.ajax({
            url: '/checklist-templates/' + id,
            type: 'DELETE',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                $('#deleteModal').modal('hide');
                if (response.success) {
                    toastr.success(response.message);
                    table.ajax.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                $('#deleteModal').modal('hide');
                var response = xhr.responseJSON;
                toastr.error(response.message || 'An error occurred while deleting.');
            }
        });
    });
});
</script>
@endsection
