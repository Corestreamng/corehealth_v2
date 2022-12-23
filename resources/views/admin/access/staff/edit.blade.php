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

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">User Management</h3>
            </div>
            <div class="card-body">

                {!! Form::model($user, ['method' => 'PATCH', 'route' => ['users.update', $user->id], 'enctype' => 'multipart/form-data']) !!}
                {{ csrf_field() }}
                <input type="hidden" name="_method" value="PUT">
                <div class="form-group">
                    <h4>Active Image</h4>
                    <img src="{!! url('storage/image/user/' . $user->filename) !!}" valign="middle" width="150px" height="120px" />
                    <br>
                    <hr>
                    <label for="title" class="control-label">Change Image</label>
                    <hr>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-sm-6">
                                {{ Form::label('filename', 'Passport:') }}
                                {{ Form::file('filename') }}
                            </div>

                            <div class="col-sm-4">
                                {{-- <div id="destination" class="h-auto d-inline-block bg-info" style="width: 60px;"></div> --}}
                                <img src="" class="float-right" id="myimg" width=80>
                            </div>
                        </div>
                    </div>

                </div>
                <hr>
                <div class="form-group">
                    <div class="row">
                        <div class="col-sm-6">
                            {{ Form::label('old_records', 'Old Records(Applies to patients only):') }}
                            {{ Form::file('old_records') }}
                        </div>

                        <div class="col-sm-4">
                            {{-- <div id="destination" class="h-auto d-inline-block bg-info" style="width: 60px;"></div> --}}
                            <img src="" class="float-right" id="myimg" width=80>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="form-group">
                    <small class="text-danger"> Fields Marked * Are  Required</small>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2" for="id">Status Category: <span class="text-danger">*</span></label>
                    <div class="col-sm-10">
                        <select class="form-control" id="is_admin" name="is_admin" required
                            placeholder="Select Status Category">
                            <option value="0">--Select--</option>
                            @foreach ($statuses as $status)
                                @if ($status->id == $user->is_admin)
                                    <option selected value="{{ $status->id }}">{{ $status->name }}</option>
                                @else
                                    <option value="{{ $status->id }}">{{ $status->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2" for="id">Clients:</label>
                    <div class="col-sm-10">
                        {!! Form::select('users', $clientUsers, $user->customer_id, [
                            'id' => 'customer_id',
                            'name' => 'customer_id',
                            'placeholder' => 'Pick a
                                                Value',
                            'class' => 'select2 show-tick form-control',
                            'data-live-search' => 'true',
                        ]) !!}
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2" for="title">Designation:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="designation" name="designation"
                            value="{!! !empty($user->designation) ? $user->designation : old('designation') !!}" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2" for="title">Surname: <span class="text-danger">*</span></label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="surname" name="surname"
                            value="{!! !empty($user->surname) ? $user->surname : old('surname') !!}" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2" for="title">Firstname: <span class="text-danger">*</span></label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="firstname" name="firstname"
                            value="{!! !empty($user->firstname) ? $user->firstname : old('firstname') !!}" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2" for="title">Othername:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="othername" name="othername"
                            value="{!! !empty($user->othername) ? $user->othername : old('othername') !!}" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2" for="title">Email:</label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="email" name="email" value="{!! !empty($user->email) ? $user->email : old('email') !!}"
                            autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-2" for="phone">Phone Number: <span class="text-danger">*</span></label>
                    <div class="col-sm-10">
                        <input type="text" class="form-control" id="phone_number" name="phone_number"
                            value="{!! !empty($user->phone_number) ? $user->phone_number : old('phone_number') !!}" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label for="url" class="col-sm-5 control-label">Content</label>
                    <div class="col-sm-10">
                        <textarea id="content" name="content" cols="30" rows="10" class="form-control" placeholder="Enter Content">
                        {!! !empty($user->content) ? $user->content : old('content') !!}
                        </textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label for="inputPassword3" class="col-sm-3 control-label">Password <span class="text-danger">*</span></label>

                    <div class="col-sm-9">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" value="123456">
                    </div>
                </div>
                <div class="form-group">
                    <label for="visible" class="col-sm-4 control-label">Visible</label>

                    <div class="col-sm-10">
                        <select class="form-control" id="visible" name="visible">
                            <option value="0">--Select--</option>
                            @foreach ($options as $option)
                                @if ($option->id == $user->visible)
                                    <option selected value="{{ $option->id }}">{{ $option->name }}</option>
                                @else
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        <p class="errorVisible text-center alert alert-danger hidden"></p>
                    </div>
                </div>
                <hr>
                <div class="form-group">
                    <small class="text-danger"> Ignore these following parts if you are creating a patient</small>
                </div>
                <hr>
                {{-- @if ($user->assignRole == 1) --}}
                <div class="form-group">
                    <div class="form-check checkbox-success checkbox-circle">
                        <input id="assignRole" type="checkbox" name="assignRole" {!! $user->assignRole ? 'checked="checked"' : '' !!}>
                        <label for="active">Click to assign role</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="inputPassword3" class="col-sm-2 control-label">Roles</label>
                    <div class="col-sm-10">
                        {!! Form::select('roles[]', $roles, $userRole, ['id' => 'roles', 'class' => 'form-control select2', 'multiple', 'style' => 'width: 100%;', 'data-toggle' => 'select2', 'data-placeholder' => 'Select to assign role...', 'data-allow-clear' => 'true']) !!}
                        <p class="errorRoles text-center alert alert-danger hidden"></p>
                    </div>
                </div>
                {{-- @endif --}}

                {{-- @if ($user->assignPermission == 1) --}}
                <div class="form-group">
                    <div class="form-check checkbox-success checkbox-circle">
                        <input id="assignPermission" type="checkbox" name="assignPermission" {!! $user->assignPermission ? 'checked="checked"' : '' !!}>
                        <label for="active">Click to assign permission</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="inputPassword3" class="col-sm-2 control-label">Permissions</label>
                    <div class="col-sm-10">
                        {!! Form::select('permissions[]', $permissions, $userPermission, ['class' => 'form-control select2', 'multiple', 'style' => 'width: 100%;', 'data-toggle' => 'select2', 'data-placeholder' => 'Select to assign permission...', 'data-allow-clear' => 'true']) !!}
                        <p class="errorRoles text-center alert alert-danger hidden"></p>
                    </div>
                </div>
                {{-- @endif --}}


                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="float-left">
                                <a href="{{ route('users.index') }}" class="pull-right btn btn-danger"><i
                                        class="fa fa-close"></i> Back </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="float-right">
                                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
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
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('plugins/ckeditor/ckeditor.js') }}"></script>
    <script src="{{ asset('plugins/select2/select2.min.js') }}"></script>

    <script>
        $(document).ready(function() {
            // $.noConflict();
            CKEDITOR.replace('content');
            $(".select2").select2();
        });
    </script>
    <script type="text/javascript">
        function readURL() {
            var myimg = document.getElementById("myimg");
            var input = document.getElementById("filename");
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    console.log("changed");
                    myimg.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        document.querySelector('#filename').addEventListener('change', function() {
            readURL()
        });
    </script>
@endsection
