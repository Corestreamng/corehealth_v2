@extends('admin.layouts.app')
@section('title', 'New Role')
@section('page_name', 'Roles')
@section('subpage_name', 'New Role')

@section('content')

    <section class="content">

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Create Role</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    {!! Form::open(['method' => 'POST', 'route' => 'roles.store', 'class' => 'form-horizontal', 'role' => 'form']) !!}
                    {{ csrf_field() }}
                    <div class="form-group">
                        <label for="name" class="col-sm-2 control-label">Name</label>
                        <div class="col-sm-10">
                            <input type="text" class="form-control" id="name" name="name"
                                value="{{ old('name') }}" required autofocus placeholder="Enter Name">
                        </div>
                    </div>

                    <div class="form-group col-md-12">
                        <label for="permission">Assign permission to the role above:</label>
                        <div class="col-md-12">
                            <div class="row">
                                @foreach ($permission as $value)
                                    <div class="col-md-4">
                                        <div class="checkbox">
                                            <input type="checkbox" class="form-check-input" id="{{ $value->id }}"
                                                name="permission[]" value="{{ $value->id }}">
                                            <label class="form-check-label"
                                                for="{{ $value->id }}">{{ $value->name }}</label>

                                            <br />
                                        </div>
                                    </div>
                                @endforeach
                                <!-- <div class="errorPermission text-center alert alert-danger hidden" role="alert"></div> -->
                            </div>
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
                                    <a href="{{ route('roles.index') }}" class="pull-right btn btn-danger"><i
                                            class="fa fa-close"></i> Back </a>
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
    {{-- <script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script> --}}
    <script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
    <!-- Bootstrap 4 -->
    <!-- <script src="{{ asset('plugins/bootstrap/js/bootstrap.min.js') }}"></script> -->
    <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <!-- DataTables -->
    <script src="{{ asset('plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('plugins/datatables/dataTables.bootstrap4.js') }}"></script>
    <script src="{{ asset('plugins/ckeditor/ckeditor.js') }}"></script>

    <script>
        // CKEDITOR.replace('content_edit');
        CKEDITOR.replace('content');
    </script>
    <script>
        $(document).ready(function() {
            $(".select2").select2();
        });
    </script>

@endsection
