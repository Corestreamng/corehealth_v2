@extends('admin.layouts.admin')

@section('main-content')



<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1>Permission Management</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Permission Management</li>
        </ol>
      </div>
    </div>
  </div><!-- /.container-fluid -->
</section>

 <section class="content">

    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Permission Management</h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
          <!-- <div class="pull-right">
            <button type="button" class="add-modal btn btn-primary" data-toggle="modal">New Role</button>
          </div> -->
        {!! Form::model($permission, ['method' => 'PATCH', 'route'=> ['permissions.update', $permission->id], 'class' => 'form-horizontal', 'role' => 'form']) !!}
            {{ csrf_field() }}
              
            <div class="form-group">
                <label class="control-label col-sm-2" for="title">Name:</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control" id="name" name="name" readonly="true" value="{!! (!empty($permission->name)) ? $permission->name : old('name') !!}" autofocus>
                </div>
            </div>
           

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <div class="col-sm-offset-1 col-sm-6">
                            <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Update</button>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <div class="col-sm-6">
                            <a href="{{ route('permissions.index') }}" class="pull-right btn btn-danger"><i class="fa fa-close"></i> Back </a>
                        </div>
                    </div>
                </div>                        
            </div>

        {!! Form::close() !!}
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
<script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
<!-- Bootstrap 4 -->
<!-- <script src="{{ asset('plugins/bootstrap/js/bootstrap.min.js') }}"></script> -->
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<!-- DataTables -->
<script src="{{ asset('plugins/datatables/jquery.dataTables.js') }}"></script>
<script src="{{ asset('plugins/datatables/dataTables.bootstrap4.js') }}"></script>
<script>
  $(function () {
    $('#ghaji').DataTable({
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
  $('.modal-footer').on('click', '.delete', function() {
      $.ajax({
          headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
          type: 'DELETE',
          url: 'roles/' + id,
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

