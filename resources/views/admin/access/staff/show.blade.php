@extends('admin.layouts.admin')

@section('main-content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>View User</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active">View User</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">View User</h3>
            </div>
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
                <!-- <form class="form-horizontal" role="form"> -->
                {!! Form::model($user, ['method' => 'PATCH', 'route' => ['users.update', $user->id]]) !!}
                {{ csrf_field() }}
                <input type="hidden" name="_method" value="PUT">
                <div class="form-group">
                    <img src="{!! url('storage/image/user/' . $user->filename) !!}" valign="middle" width="150px" height="120px" />
                    <br>
                    <hr>
                </div>

                @if ($user->old_records)
                    <div class="form-group">
                        <a href="{!! url('storage/image/user/old_records/' . $user->old_records) !!}" target="_blank"><i class="fa fa-file"></i> Old Records</a>
                        <br>
                        <hr>
                    </div>
                @endif
                <div class="form-group">
                    <label class="control-label col-sm-2" for="id">Status Category:</label>
                    <div class="col-sm-10">
                        <select class="form-control" id="is_admin" name="is_admin" readonly
                            placeholder="Select Status Category">
                            @foreach ($statuses as $status)
                                @if ($status->id == $user->is_admin)
                                    <option selected value="{{ $status->id }}">{{ $status->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-sm-2" for="title">Surname:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="surname" name="surname" readonly
                            value="{!! !empty($user->surname) ? $user->surname : old('surname') !!}" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2" for="title">Firstname:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="firstname" name="firstname" readonly
                            value="{!! !empty($user->firstname) ? $user->firstname : old('firstname') !!}" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2" for="title">Othername:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="othername" name="othername" readonly
                            value="{!! !empty($user->othername) ? $user->othername : old('othername') !!}" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2" for="title">Email:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="email" name="email" readonly
                            value="{!! !empty($user->email) ? $user->email : old('email') !!}" autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="visible" class="col-sm-4 control-label">Visible</label>

                    <div class="col-sm-10">
                        <select class="form-control" id="visible" name="visible" readonly>
                            @foreach ($options as $option)
                                @if ($option->id == $user->visible)
                                    <option selected value="{{ $option->id }}">{{ $option->name }}</option>
                                @endif
                            @endforeach
                        </select>

                    </div>
                </div>
                @if ($user->assignRole == 1)

                    <div class="form-group">
                        <label for="inputPassword3" class="col-sm-2 control-label">Roles Assigned:</label>

                        <div class="col-sm-10">
                            @if (!empty($user->getRoleNames()))
                                @foreach ($user->getRoleNames() as $v)
                                    <label class="badge badge-success">{{ $v }}</label>
                                @endforeach
                            @endif
                        </div>
                    </div>

                @endif

                @if ($user->assignPermission == 1)

                    <div class="form-group">
                        <label for="inputPassword3" class="col-sm-2 control-label">Permission Assigned:</label>

                        <div class="col-sm-10">
                            @if (!empty($user->getPermissionNames()))
                                @foreach ($user->getPermissionNames() as $v)
                                    <label class="badge badge-success">{{ $v }}</label>
                                @endforeach
                            @endif
                        </div>
                    </div>

                @endif


                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="col-sm-offset-1 col-sm-6">
                                <!-- <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Update</button> -->
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="col-sm-6">
                                <a href="{{ route('users.index') }}" class="pull-right btn btn-danger"><i
                                        class="fa fa-close"></i> Back </a>
                            </div>
                        </div>
                    </div>
                </div>

                {!! Form::close() !!}
                <!-- </form> -->
            </div>
        </div>
    </div>


@endsection

@section('scripts')
    <!-- jQuery -->
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
    <!-- Bootstrap 4 -->
    <!-- <script src="{{ asset('plugins/bootstrap/js/bootstrap.min.js') }}"></script> -->
    <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

    <script>
        $(document).ready(function() {
            $(".select2").select2();
        });
    </script>
@endsection
