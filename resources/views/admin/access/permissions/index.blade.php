@extends('admin.layouts.app')
@section('title', 'List Permissions')
@section('page_name', 'Permissions')
@section('subpage_name', 'List Permissions')
@section('content')

    <section class="content">

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <a href="{{ route('permissions.create') }}" class="btn btn-primary">New Permission</a>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    <div class="pull-right">

                    </div>
                    <div class="table-responsive">
                        <table id="ghaji" class="table table-sm  table-bordered table-striped display">
                            <thead>
                                <tr>
                                    <th>S/N</th>
                                    <th>Name</th>
                                    <th>Guard Name</th>
                                    <th>Show</th>
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
                            <div class="col-md-10">
                                <input type="hidden" class="form-control" id="id_delete" name="id_delete">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger delete" data-dismiss="modal">
                        <span class='glyphicon glyphicon-trash'></span> Delete
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
                "serverSide": false,
                "ajax": {
                    "url": "{{ url('listPermissions') }}",
                    "type": "GET"
                },
                "columns": [{
                        "data": "DT_RowIndex"
                    },
                    {
                        "data": "name"
                    },
                    {
                        "data": "guard_name"
                    },
                    {
                        "data": "show"
                    },
                    {
                        "data": "edit"
                    },
                    {
                        "data": "delete"
                    }
                ],
                "paging": true,
                // "lengthChange": false,
                "searching": true,
                // "ordering": true,
                // "info": true,
                // "autoWidth": false
            });
        });


        // Delete a User
        jQuery(document).on('click', '.delete-modal', function() {
            jQuery('.modal-title').text('Delete');
            jQuery('#id_delete').val(jQuery(this).data('id'));
            id = jQuery('#id_delete').val();
            // alert(id);
            console.log(id);
            jQuery('#deleteModal').modal('show');
        });

        jQuery(document).on('click', '.delete', function(e) {
            console.log("Delete this record");
            id = jQuery('#id_delete').val();
            console.log(id);
            jQuery.ajax({
                headers: {
                    'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content')
                },
                type: 'DELETE',
                url: 'permissions/' + id,
                data: {
                    // '_token': jQuery('input[name=_token]').val(),
                    'id': jQuery("#id_delete").val()
                },
                success: function(data) {
                    console.log(data);
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

        // jQuery('.modal-footer').on('click', '.delete', function(e) {
        //     //e.preventDefault();
        //     console.log("here");
        //     id = jQuery('#id_delete').val();
        //     console.log(id);
        //     jQuery.ajax({
        //         headers: {'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content')},
        //         type: 'DELETE',
        //         url: 'permissions/' + id,
        //         data: {
        //             // '_token': jQuery('input[name=_token]').val(),
        //             'id': jQuery("#id_delete").val()
        //         },
        //         success: function(data) {
        //           console.log(data);
        //                 swal({
        //                     position: 'top-end',
        //                     type: 'success',
        //                     title: 'Successfully deleted your information',
        //                     showConfirmButton: false,
        //                     timer: 3000
        //                 });
        //                 // window.location.reload();
        //         }
        //     });
        // });
    </script>

@endsection
