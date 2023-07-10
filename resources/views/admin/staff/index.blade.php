@extends('admin.layouts.app')
@section('title', 'User Management')
@section('page_name', 'User Management')
@section('subpage_name', 'List Users')
@section('content')
    <section class="content">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    {{-- @if (auth()->user()->can('user-create')) --}}
                        <a href="{{ route('staff.create') }}" id="loading-btn" data-loading-text="Loading..."
                            class="btn btn-primary">
                            <i class="fa fa-user"></i>
                            New User
                        </a>
                    {{-- @endif --}}
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <div class="pull-right">
                    </div>
                    {{-- @if (Auth::user()->hasRole(['Super-Admin', 'Admin']) || Auth::user()->hasPermissionTo('user-list')) --}}
                    <div class="table-responsive">
                        <table id="ghaji" class="table table-sm  table-bordered table-striped display">
                            <thead>
                                <tr>
                                    <th>Id</th>
                                    <th>Image</th>
                                    <th>Surname</th>
                                    <th>Firstname</th>
                                    <th>Category</th>
                                    <!-- <th>Email</th> -->
                                    <th>View</th>
                                    <th>Edit</th>
                                    <th>Delete</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                    {{-- @endif --}}
                </div>
            </div>
        </div>

    </section>


    <!-- Modal form to delete a user -->
    <div id="deleteModal" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title"></h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <h4 class="text-center">Are you sure you want to delete the following user?</h4>
                    <br />
                    <form class="form-horizontal" role="form">
                        <div class="form-group">
                            <div class="col-sm-10">
                                <input type="hidden" class="form-control" id="id_delete">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger delete" data-dismiss="modal">
                        <span id="" class='glyphicon glyphicon-trash'></span> Delete
                    </button>
                    <button type="button" class="btn btn-warning" data-dismiss="modal">
                        <span class='glyphicon glyphicon-remove'></span> Close
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
            // $.noConflict();
            $('#ghaji').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('listStaff') }}",
                    "type": "GET"
                },
                "columns": [{
                        "data": "DT_RowIndex"
                    },
                    {
                        "data": "filename"
                    },
                    {
                        "data": "surname"
                    },
                    {
                        "data": "firstname"
                    },
                    {
                        "data": "is_admin"
                    },
                    // { "data": "email" },
                    {
                        "data": "view"
                    },
                    {
                        "data": "edit"
                    },
                    {
                        "data": "delete"
                    }
                ],
                "paging": true
                // "lengthChange": false,
                // "searching": true,
                // "ordering": true,
                // "info": true,
                // "autoWidth": false
            });
        });

        // Delete a User
        $(document).on('click', '.delete-modal', function() {
            $('.modal-title').text('Delete');
            $('#id_delete').val($(this).data('id'));
            id = $('#id_delete').val();
            $('#deleteModal').modal('show');
        });
        $(document).on('click', '.delete', function(e) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: 'DELETE',
                url: 'users/' + id,
                data: {
                    // '_token': $('input[name=_token]').val(),
                    'id': $("#id_delete").val(),
                },
                success: function(data) {
                    swal({
                        position: 'top-end',
                        type: 'success',
                        title: 'Successfully deleted your information',
                        showConfirmButton: false,
                        timer: 3000
                    });
                    window.location.reload();
                }
            });
        });
    </script>
@endsection
