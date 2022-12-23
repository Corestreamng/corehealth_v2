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
        @if(auth()->user()->can('user-create'))
        <a href="{{ route('users.create') }}" id="loading-btn" data-loading-text="Loading..." class="btn btn-primary">
          <i class="fa fa-user"></i>
          New User
        </a>
        @endif
      </div>
      <!-- /.card-header -->
      <div class="card-body">
        <div class="pull-right">
        </div>
        {{-- @if(Auth::user()->hasRole(['Super-Admin', 'Admin']) || Auth::user()->hasPermissionTo('user-list')) --}}
        <table id="ghaji" class="table table-sm table-responsive table-bordered table-striped display">
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
        {{-- @endif --}}
      </div>
    </div>
  </div>

</section>

<!-- Modal form to add a user -->
<div id="addModal" class="modal fade" role="dialog">
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
          {{ csrf_field() }}
          <div class="form-group">
            <label for="is_admin" class="col-sm-4 control-label">Status Category</label>

            <div class="col-sm-10">
              <select class="form-control" id="is_admin" name="is_admin" required placeholder="Select Status Category">
                <option value="0">--Select--</option>
                @foreach($statuses as $status)
                <option value="{{ $status->id }}">{{ $status->name }}</option>
                @endforeach
              </select>
              <div class="errorIs_admin text-center alert alert-danger hidden" role="alert"></div>
            </div>
          </div>
          <div class="form-group">
            <label for="surname" class="col-sm-2 control-label">Surname</label>

            <div class="col-sm-10">
              <input type="text" class="form-control" id="surname" name="surname" value="{{ old('surname') }}" required
                autofocus placeholder="Enter Surname">
              <div class="errorSurname text-center alert alert-danger hidden" role="alert"></div>
            </div>
          </div>
          <div class="form-group">
            <label for="inputEmail3" class="col-sm-2 control-label">Firstname</label>

            <div class="col-sm-10">
              <input type="text" class="form-control" id="firstname" name="firstname" value="{{ old('firstname') }}"
                required placeholder="Firstname">
              <p class="errorFirstname text-center alert alert-danger hidden"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="othername" class="col-sm-2 control-label">Othername</label>

            <div class="col-sm-10">
              <input type="text" class="form-control" id="othername" name="othername" value="{{ old('othername') }}"
                placeholder="Othername">

            </div>
          </div>
          <div class="form-group">
            <label for="email" class="col-sm-2 control-label">Email</label>

            <div class="col-sm-10">
              <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required
                placeholder="Email">
              <p class="errorEmail text-center alert alert-danger hidden"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="inputPassword3" class="col-sm-2 control-label">Password</label>

            <div class="col-sm-10">
              <input type="password" class="form-control" id="password" name="password" required placeholder="Password">
              <p class="errorPassword text-center alert alert-danger hidden"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="visible" class="col-sm-4 control-label">Visible</label>

            <div class="col-sm-10">
              <select class="form-control" id="visible" name="visible">
                <option value="0">--Select--</option>
                @foreach($options as $option)
                <option value="{{ $option->id }}">{{ $option->name }}</option>
                @endforeach
              </select>
              <p class="errorVisible text-center alert alert-danger hidden"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="inputPassword3" class="col-sm-2 control-label">Roles</label>

            <div class="col-sm-10">
              {!! Form::select('roles[]', $roles,[], array('id' => 'roles', 'name' => 'roles', 'class' =>
              'form-control','multiple')) !!}
              <p class="errorRoles text-center alert alert-danger hidden"></p>
            </div>

          </div>


          <!-- <div class="card-footer">
              <button type="submit" id="add" name="submit" class="btn btn-info">Sign in</button>
              <button type="submit" class="btn btn-default float-right">Cancel</button>
            </div> -->
          <!-- /.card-footer -->
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success add" data-dismiss="modal">
          <span id="" class='glyphicon glyphicon-check'></span> Add
        </button>
        <button type="button" class="btn btn-warning" data-dismiss="modal">
          <span class='glyphicon glyphicon-remove'></span> Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal form to edit a user -->
<div id="editModal" class="modal fade" role="dialog">
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
              <select class="form-control" id="is_admin_edit" required placeholder="Select Status Category">
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
            <label for="inputPassword3" class="col-sm-2 control-label">Password</label>

            <div class="col-sm-10">
              <input type="password" class="form-control" id="password_edit" required placeholder="Password">
              <p class="errorPassword text-center alert alert-danger hidden"></p>
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
              <p class="errorVisible text-center alert alert-danger hidden"></p>
            </div>
          </div>
          <div class="form-group">
            <label for="inputPassword3" class="col-sm-2 control-label">Roles</label>

            <div class="col-sm-10">
              {!! Form::select('roles[]', $roles,[], array('id' => 'roles_edit', 'name' => 'roles', 'class' =>
              'form-control','multiple')) !!}
              <p class="errorRoles text-center alert alert-danger hidden"></p>
            </div>
          </div>

        </form>
      </div>
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
<script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

<script src="{{ asset('plugins/datatables/jquery.dataTables.js') }}"></script>
<script src="{{ asset('plugins/datatables/dataTables.bootstrap4.js') }}"></script>
<script src="{{ asset('plugins/datatables/dataTables.buttons.min.js') }}" ></script>
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
            // 'colvis',
            {
                extend: 'collection',
                text: 'Table control',
                buttons: [
                    {
                        text: 'Toggle start date',
                        action: function ( e, dt, node, config ) {
                            dt.column( -2 ).visible( ! dt.column( -2 ).visible() );
                        }
                    },
                    {
                        text: 'Toggle salary',
                        action: function ( e, dt, node, config ) {
                            dt.column( -1 ).visible( ! dt.column( -1 ).visible() );
                        }
                    },
                    {
                        collectionTitle: 'Visibility control',
                        extend: 'colvis'
                    }
                ]
            }
        ],
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "{{ url('listUsers') }}",
            "type": "GET"
        },
        "columns": [
            { "data": "DT_RowIndex" },
            { "data": "filename" },
            { "data": "surname" },
            { "data": "firstname" },
            { "data": "is_admin" },
            // { "data": "email" },
            { "data": "view" },
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
  $(document).on('click', '.delete', function(e) {
      $.ajax({
          headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
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