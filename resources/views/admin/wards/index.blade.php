@extends('admin.layouts.app')
@section('title', 'Manage Wards')
@section('page_name', 'Hospital Setup')
@section('subpage_name', 'Wards')

@section('content')
<section class="content">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Ward Management</h3>
                <a href="{{ route('wards.create') }}" class="btn btn-primary">
                    <i class="fa fa-plus"></i> New Ward
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="wardsTable" class="table table-sm table-bordered table-striped display">
                        <thead>
                            <tr>
                                <th>S/N</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Capacity</th>
                                <th>Occupancy</th>
                                <th>Status</th>
                                <th>Edit</th>
                                <th>Delete</th>
                            </tr>
                        </thead>
                    </table>
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
                <h5 class="text-center">Are you sure you want to delete this ward?</h5>
                <p class="text-center text-muted">This action cannot be undone.</p>
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
    var table = $('#wardsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('ward-list') }}",
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'type_badge', name: 'type', orderable: true, searchable: true },
            { data: 'location_display', name: 'building' },
            { data: 'capacity', name: 'capacity' },
            { data: 'occupancy', name: 'occupancy', orderable: false, searchable: false },
            { data: 'status_badge', name: 'is_active', orderable: true },
            { data: 'edit', name: 'edit', orderable: false, searchable: false },
            { data: 'delete', name: 'delete', orderable: false, searchable: false }
        ],
        order: [[1, 'asc']]
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
            url: '/wards/' + id,
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
