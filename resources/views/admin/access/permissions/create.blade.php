@extends('admin.layouts.admin')

@section('main-content')
<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1>Create Permissions Management</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Create Permissions Management</li>
        </ol>
      </div>
    </div>
  </div><!-- /.container-fluid -->
</section>

<section class="content">

    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Create Permissions Management</h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
                <div>
                    @if (count($errors) > 0)
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                            <!-- <h5><i class="icon fa fa-info"></i> Alert!</h5> -->
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            
                        </div>
                    @endif
                    @include('partials.notification')
                </div>
                
                {!! Form::open(['method' => 'POST', 'route'=> 'permissions.store', 'class' => 'form-horizontal', 'role' => 'form']) !!}
                    {{ csrf_field() }}
                    <div class="form-group">
                        <label for="name" class="col-sm-2 control-label">Name</label>
                        <div class="col-sm-10">
                        <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required autofocus placeholder="Enter Name">
                        </div>
                    </div>
                    
                    
                                       
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="col-sm-offset-1 col-sm-6">
                                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
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

@endsection

@section('scripts')
<!-- jQuery -->
<script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script>
<!-- <script src="{{ asset('plugins/select2/select2.min.js') }}"></script> -->
<!-- Bootstrap 4 -->
<!-- <script src="{{ asset('plugins/bootstrap/js/bootstrap.min.js') }}"></script> -->
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<!-- DataTables -->
<script src="{{ asset('plugins/datatables/jquery.dataTables.js') }}"></script>
<script src="{{ asset('plugins/datatables/dataTables.bootstrap4.js') }}"></script>
<!-- <script src="{{ asset('plugins/ckeditor/ckeditor.js') }}"></script> -->

<!-- <script>
    // CKEDITOR.replace('content_edit');
    CKEDITOR.replace('content');
</script> -->
<!-- <script>
    $(document).ready(function() {
      $(".select2").select2();
    });
</script> -->

@endsection

