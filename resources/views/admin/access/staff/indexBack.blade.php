@extends('admin.layouts.admin')

@section('main-content')


<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1>User Management</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">User Management</li>
        </ol>
      </div>
    </div>
  </div><!-- /.container-fluid -->
</section>

 <section class="content">

    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">User Management</h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
          <div class="pull-right">
            <button id="addUser" type="button" class="btn btn-primary" data-toggle="modal" data-target=".bd-add-modal">New User</button>
          </div>
          <table id="example1" class="table table-sm table-responsive table-bordered table-striped display">
            <thead>
            <tr>
              <!-- <th>Sn</th> -->
             
              <th>Surname</th>
              <th>Firstname</th>
              <th>Othername</th>
              <th>Status</th>
              <th>Email</th>
              <!-- <th>Show</th> -->
              <th>Edit</th>
              <th>Delete</th>
            </tr>
            </thead>
            
          </table>
        </div>
        <!-- /.card-body -->
      </div>
      
    </div>

</section>

<div id="#addUser" class="modal fade bd-add-modal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
  <div class="modal-content">
      <!-- <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div> -->
      <div class="modal-body">
        <div class="card card-info">
                <div class="card-header">
                  <h3 class="card-title">Horizontal Form</h3>
                </div>
                <!-- /.card-header -->
                <!-- form start -->
                <form class="form-horizontal" method="POST" action="">
                {{ csrf_field() }}
                  <div class="card-body">
                    <div class="form-group">
                      <label for="is_admin" class="col-sm-4 control-label">Status Category</label>

                      <div class="col-sm-10">
                        <select class="form-control{{ $errors->has('is_admin') ? ' is-invalid' : '' }}" id="is_admin" name="is_admin" required placeholder="Select Status Category">
                            <option value="0">--Select--</option>
                          @foreach($statuses as $status)
                            <option value="{{ $status->id }}">{{ $status->name }}</option>
                          @endforeach
                        </select>
                        @if ($errors->has('is_admin'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('is_admin') }}</strong>
                            </span>
                        @endif
                      </div>
                    </div>
                    <div class="form-group">
                      <label for="surname" class="col-sm-2 control-label">Surname</label>

                      <div class="col-sm-10">
                        <input type="text" class="form-control{{ $errors->has('surname') ? ' is-invalid' : '' }}" id="surname" name="surname" value="{{ old('surname') }}" required autofocus placeholder="Enter Surname">
                        @if ($errors->has('surname'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('surname') }}</strong>
                            </span>
                        @endif
                      </div>
                    </div>
                    <div class="form-group">
                      <label for="inputEmail3" class="col-sm-2 control-label">Firstname</label>

                      <div class="col-sm-10">
                        <input type="text" class="form-control{{ $errors->has('firstname') ? ' is-invalid' : '' }}" id="firstname" name="firstname" value="{{ old('firstname') }}" required placeholder="Firstname">
                        @if ($errors->has('firstname'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('firstname') }}</strong>
                            </span>
                        @endif
                      </div>
                    </div>
                    <div class="form-group">
                      <label for="othername" class="col-sm-2 control-label">Othername</label>

                      <div class="col-sm-10">
                        <input type="text" class="form-control" id="othername" name="othername" value="{{ old('othername') }}" placeholder="Othername">
                        
                      </div>
                    </div>
                    <div class="form-group">
                      <label for="email" class="col-sm-2 control-label">Email</label>

                      <div class="col-sm-10">
                        <input type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" id="email" name="email" value="{{ old('email') }}" required placeholder="Email">
                        @if ($errors->has('email'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('email') }}</strong>
                            </span>
                        @endif
                      </div>
                    </div>
                    <div class="form-group">
                      <label for="inputPassword3" class="col-sm-2 control-label">Password</label>

                      <div class="col-sm-10">
                        <input type="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" id="password" name="password" required placeholder="Password">
                        @if ($errors->has('password'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('password') }}</strong>
                            </span>
                        @endif
                      </div>
                    </div>
                    <div class="form-group">
                      <label for="visible" class="col-sm-4 control-label">Visible</label>

                      <div class="col-sm-10">
                        <select class="form-control{{ $errors->has('visible') ? ' is-invalid' : '' }}" id="visible" name="visible">
                            <option value="0">--Select--</option>
                          @foreach($options as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                          @endforeach
                        </select>
                      </div>
                    </div>
                    <div class="form-group">
                      <label for="inputPassword3" class="col-sm-2 control-label">Roles</label>

                      <div class="col-sm-10">
                      {!! Form::select('roles[]', $roles,[], array('class' => 'form-control','multiple')) !!}
                        @if ($errors->has('roles'))
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('roles') }}</strong>
                            </span>
                        @endif
                      </div>
                    </div>

                  </div>
                  <!-- /.card-body -->
                  <div class="card-footer">
                    <button type="submit" id="add" name="submit" class="btn btn-info">Sign in</button>
                    <button type="submit" class="btn btn-default float-right">Cancel</button>
                  </div>
                  <!-- /.card-footer -->
                </form>
              </div>
        </div>
      <!-- <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary">Save changes</button>
      </div> -->
    </div>
  </div>
</div>

<div id="editModal" class="modal fade" role="dialog" >
  <div class="modal-dialog modal-lg">
  <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        
      <form class="form-horizontal" role="form">
          <div class="form-group">
              <label class="control-label col-sm-2" for="id">Status Category:</label>
              <div class="col-sm-10">
                  <select class="form-control{{ $errors->has('is_admin') ? ' is-invalid' : '' }}" id="is_admin" name="is_admin" required placeholder="Select Status Category">
                      <option value="0">--Select--</option>
                    @foreach($statuses as $status)
                      <option value="{{ $status->id }}">{{ $status->name }}</option>
                     
                    @endforeach
                  </select>
                  <input type="hidden" class="form-control" id="id_edit" disabled>
              </div>
          </div>
          <div class="form-group">
              <label class="control-label col-sm-2" for="title">Surname:</label>
              <div class="col-sm-10">
                  <input type="text" class="form-control" id="surname_edit" autofocus>
                  <p class="errorSurname text-center alert alert-danger hidden"></p>
              </div>
          </div>
          <div class="form-group">
              <label class="control-label col-sm-2" for="title">Firstname:</label>
              <div class="col-sm-10">
                  <input type="text" class="form-control" id="firstname_edit" autofocus>
                  <p class="errorFirstname text-center alert alert-danger hidden"></p>
              </div>
          </div>
          <div class="form-group">
              <label class="control-label col-sm-2" for="title">Othername:</label>
              <div class="col-sm-10">
                  <input type="text" class="form-control" id="othername_edit" autofocus>
                  <p class="errorOthername text-center alert alert-danger hidden"></p>
              </div>
          </div>
          <div class="form-group">
              <label class="control-label col-sm-2" for="title">Email:</label>
              <div class="col-sm-10">
                  <input type="text" class="form-control" id="email_edit" autofocus>
                  <p class="errorEmail text-center alert alert-danger hidden"></p>
              </div>
          </div>
          <div class="form-group">
            <label for="visible" class="col-sm-4 control-label">Visible</label>

            <div class="col-sm-10">
              <select class="form-control" id="visible_edit">
                  <option value="0">--Select--</option>
                @foreach($options as $option)
                  <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="inputPassword3" class="col-sm-2 control-label">Roles</label>

            <div class="col-sm-10">
            {!! Form::select('roles[]', $roles,[], array('class' => 'form-control','multiple')) !!}
              @if ($errors->has('roles'))
                  <span class="invalid-feedback" role="alert">
                      <strong>{{ $errors->first('roles') }}</strong>
                  </span>
              @endif
            </div>
          </div>

      </form>
        <!-- <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary">Save changes</button>
        </div> -->
        <div class="modal-footer">
            <button type="button" class="btn btn-primary edit" data-dismiss="modal">
                <span class='glyphicon glyphicon-check'></span> Edit
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
    $('#example1').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "{{ url('listUsers') }}",
            "type": "GET"
        },
        "columns": [
            // { "data": "sn" },
           
            { "data": "surname" },
            { "data": "firstname" },
            { "data": "othername" },
            { "data": "is_admin" },
            { "data": "email" },
            // { "data": "show" }, 
            { "data": "edit" }, 
            { "data": "delete" }
        ]
      // "paging": true,
      // "lengthChange": false,
      // "searching": true,
      // "ordering": true,
      // "info": true,
      // "autoWidth": false
    });

    $(document).on('click', '.bd-edit-modal', function() {
        // $('#footer_action_button').text(" Update");
        // $('#footer_action_button').addClass('glyphicon-check');
        // $('#footer_action_button').removeClass('glyphicon-trash');
        // $('.actionBtn').addClass('btn-success');
        // $('.actionBtn').removeClass('btn-danger');
        // $('.actionBtn').removeClass('delete');
        // $('.actionBtn').addClass('edit');
        // $('.modal-title').text('Edit');
        // $('.deleteContent').hide();
        // $('.form-horizontal').show();
        // var stuff = $(this).data('info').split(',');
        // fillmodalData(stuff)
        $('#surname_edit').val($(this).data('surname'));
        // $('#updateUser').modal('show');
        // $('#updateUser').on('show.bs.modal', function (e) {
          // do something...
          // $('#updateUser').modal('show');
        // });
    });

    $("#add").click(function() {
      event.preventDefault();
      var form_data = $(this).serialize();
      $.ajax({
          type: 'POST',
          url: "{{route('users.store') }}",
          data: form_data,
          dataType:"json",
          success: function(data) {
              if ((data.errors)){
                $('.error').removeClass('hidden');
                  $('.error').text(data.errors.name);
              }
              else {
                swal(
                  'Good job!',
                  'Information Save Successfully',
                  'success'
                )
              }
          },

      });
      $('#name').val('');
      });
  });

  // Edit a post
  $(document).on('click', '.edit-modal', function() {
      $('.modal-title').text('Edit');
      $('#id_edit').val($(this).data('id'));
      $('#is_admin_edit').val($(this).data('is_admin'));
      $('#surname_edit').val($(this).data('surname'));
      $('#firstname_edit').val($(this).data('firstname'));
      $('#othername_edit').val($(this).data('othername'));
      $('#email_edit').val($(this).data('email'));
      $('#visible_edit').val($(this).data('visible'));
      $('#role_edit').val($(this).data('role'));
      id = $('#id_edit').val();
      $('#editModal').modal('show');
  });
  $('.modal-footer').on('click', '.edit', function() {
      $.ajax({
          type: 'PUT',
          url: 'users/' + id,
          data: {
              '_token': $('input[name=_token]').val(),
              'id': $("#id_edit").val(),
              'is_admin': $('#is_admin_edit').val(),
              'surname': $('#surname_edit').val(),
              'firstname': $('#firstname_edit').val(),
              'othername': $('#othername_edit').val(),
              'email': $('#email_edit').val(),
              'visible': $('#visible_edit').val(),
              'role': $('#role_edit').val()
          },
          success: function(data) {
              $('.errorSurname').addClass('hidden');
              $('.errorFirstname').addClass('hidden');

              if ((data.errors)) {
                  setTimeout(function () {
                      $('#editModal').modal('show');
                      toastr.error('Validation error!', 'Error Alert', {timeOut: 5000});
                  }, 500);

                  if (data.errors.title) {
                      $('.errorTitle').removeClass('hidden');
                      $('.errorTitle').text(data.errors.title);
                  }
                  if (data.errors.content) {
                      $('.errorContent').removeClass('hidden');
                      $('.errorContent').text(data.errors.content);
                  }
              } else {
                  toastr.success('Successfully updated Post!', 'Success Alert', {timeOut: 5000});
                  $('.item' + data.id).replaceWith("<tr class='item" + data.id + "'><td class='col1'>" + data.id + "</td><td>" + data.title + "</td><td>" + data.content + "</td><td class='text-center'><input type='checkbox' class='edit_published' data-id='" + data.id + "'></td><td>Right now</td><td><button class='show-modal btn btn-success' data-id='" + data.id + "' data-title='" + data.title + "' data-content='" + data.content + "'><span class='glyphicon glyphicon-eye-open'></span> Show</button> <button class='edit-modal btn btn-info' data-id='" + data.id + "' data-title='" + data.title + "' data-content='" + data.content + "'><span class='glyphicon glyphicon-edit'></span> Edit</button> <button class='delete-modal btn btn-danger' data-id='" + data.id + "' data-title='" + data.title + "' data-content='" + data.content + "'><span class='glyphicon glyphicon-trash'></span> Delete</button></td></tr>");

                  
                  
                  
                  
                  // $('.col1').each(function (index) {
                  //     $(this).html(index+1);
                  // });
              }
          }
      });
  });
</script>

@endsection

