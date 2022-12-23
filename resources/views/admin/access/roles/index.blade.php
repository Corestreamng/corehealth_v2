@extends('admin.layouts.app')
@section('title', 'Hompage')
@section('page_name', 'Home')
@section('subpage_name', 'New Patient')
@section('content')



<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1>Role Management</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Role Management</li>
        </ol>
      </div>
    </div>
  </div><!-- /.container-fluid -->
</section>

 <section class="content">

    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <a href="{{ route('roles.create') }}" class="btn btn-primary" >New Role</a>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
          <div class="pull-right">
          <!-- <a href="{{ route('roles.create') }}" class="btn btn-primary" >New Role</a> -->
          </div>
          <table id="ghaji" class="table table-sm table-responsive table-bordered table-striped display">
            <thead>
            <tr>
              <th>S/N</th>
              <th>Name</th>
              <th>Guard Name</th>
              <th>Edit</th>
              <th>Delete</th>
            </tr>
            </thead>

          </table>
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

<!-- jQuery -->
<script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script>
<!-- Bootstrap 4 -->
<!-- <script src="{{ asset('plugins/bootstrap/js/bootstrap.min.js') }}"></script> -->
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<!-- DataTables -->
<script src="{{ asset('plugins/datatables/jquery.dataTables.js') }}"></script>
<script src="{{ asset('plugins/datatables/dataTables.bootstrap4.js') }}"></script>
<script src="{{ asset('plugins/datatables/dataTables.buttons.min.js') }}"></script>
<script src="{{ asset('plugins/datatables/jszip.min.js') }}"></script>
<script src="{{ asset('plugins/datatables/pdfmake.min.js') }}"></script>
<script src="{{ asset('plugins/datatables/vfs_fonts.js') }}"></script>
<script src="{{ asset('plugins/datatables/buttons.html5.min.js') }}"></script>
<script src="{{ asset('plugins/datatables/buttons.print.min.js') }}"></script>
<script src="{{ asset('plugins/datatables/buttons.colVis.min.js') }}"></script>
<script>
  $(function () {
    // $.noConflict();
    $('#ghaji').DataTable({
        "dom": 'Bfrtip',
        "lengthMenu": [
          [10, 25, 50, 100, -1],
          [10, 25, 50, 100, "All"]
        ],
        "buttons": [
            'pageLength',
            'copyHtml5',
            'excelHtml5',
            'csvHtml5',
            'pdfHtml5',
            'print',
            'colvis'
        ],
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "{{ url('listRoles') }}",
            "type": "GET"
        },
        "columns": [
            { "data": "DT_RowIndex" },
            { "data": "name" },
            { "data": "guard_name" },
            { "data": "edit" },
            { "data": "delete" }
        ],
      "paging": true,
      "lengthChange": false,
      "searching": true,
      "ordering": true,
      "info": true,
      "autoWidth": true
    });
  });


  // Delete a User
  jQuery(document).on('click', '.delete-modal', function() {
      jQuery('.modal-title').text('Delete');
      jQuery('#id_delete').val(jQuery(this).data('id'));
      id = jQuery('#id_delete').val();
      jQuery('#deleteModal').modal('show');
  });

  jQuery(document).on('click', '.delete', function(e) {
    console.log("Delete this record");
    id = jQuery('#id_delete').val();
    console.log(id);
    jQuery.ajax({
        headers: {'X-CSRF-TOKEN': jQuery('meta[name="csrf-token"]').attr('content')},
        type: 'DELETE',
        url: 'roles/' + id,
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

  // $('.modal-footer').on('click', '.delete', function() {
  //     $.ajax({
  //         headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
  //         type: 'DELETE',
  //         url: 'roles/' + id,
  //         data: {
  //             // '_token': $('input[name=_token]').val(),
  //             'id': $("#id_delete").val(),
  //         },
  //         success: function(data) {
  //                 swal({
  //                     position: 'top-end',
  //                     type: 'success',
  //                     title: 'Successfully deleted your information',
  //                     showConfirmButton: false,
  //                     timer: 3000
  //                 });
  //                 window.location.reload();
  //         }
  //     });
  // });
</script>

@endsection

