@extends('admin.layouts.admin')

@section('style')
@endsection

@section('main-content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Create User Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active">Create User Management</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>

    <section class="content">

        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Create User Management</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">

                    {!! Form::open(['method' => 'POST', 'route' => 'users.store', 'class' => 'form-horizontal', 'enctype' => 'multipart/form-data']) !!}
                    {{ csrf_field() }}
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
                    <hr>
                    <div class="form-group">
                        <label for="is_admin" class="col-sm-4 control-label">Status/Category <span class="text-danger">*</span></label>

                        <div class="col-sm-10">
                            {!! Form::select('statuses', $statuses, null, ['id' => 'is_admin', 'name' => 'is_admin', 'class' => 'form-control select2', 'placeholder' => 'Pick a value','required' => 'true']) !!}
                        </div>
                    </div>
                    <!-- <div class="form-group">
                        <label class="control-label col-sm-2" for="id">Clients:</label>
                        <div class="col-sm-10">
                            {!! Form::select('users', $clientUsers, null, [
                                'id' => 'customer_id',
                                'name' => 'customer_id',
                                'placeholder' => 'Pick a
                                                    Value',
                                'class' => 'select2 show-tick form-control',
                                'data-live-search' => 'true',
                            ]) !!}
                        </div>
                    </div> -->
                    <div class="form-group">
                        <label for="designation" class="col-sm-2 control-label">Designation </label>

                        <div class="col-sm-10">
                            <input type="text" class="form-control" id="designation" name="designation"
                                value="{{ old('designation') }}" autofocus
                                placeholder="Enter designation e.g (CEO, COO etc)">
                            <div class="errordesignation text-center alert alert-danger hidden" role="alert"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="surname" class="col-sm-2 control-label">Surname <span class="text-danger">*</span></label>

                        <div class="col-sm-10">
                            <input type="text" class="form-control" id="surname" name="surname"
                                value="{{ old('surname') }}" autofocus placeholder="Enter Surname" required>
                            <div class="errorSurname text-center alert alert-danger hidden" role="alert"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="inputEmail3" class="col-sm-2 control-label">Firstname <span class="text-danger">*</span></label>

                        <div class="col-sm-10">
                            <input type="text" class="form-control" id="firstname" name="firstname"
                                value="{{ old('firstname') }}" placeholder="Firstname" required>
                            <p class="errorFirstname text-center alert alert-danger hidden"></p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="othername" class="col-sm-2 control-label">Othername</label>

                        <div class="col-sm-10">
                            <input type="text" class="form-control" id="othername" name="othername"
                                value="{{ old('othername') }}" placeholder="Othername">

                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email" class="col-sm-2 control-label">Email</label>

                        <div class="col-sm-10">
                            <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}"
                                placeholder="Email" autocomplete="off">
                            <p class="errorEmail text-center alert alert-danger hidden"></p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="phone_number" class="col-sm-2 control-label">Phone Number <span class="text-danger">*</span></label>

                        <div class="col-sm-10">
                            <input type="phone_number" class="form-control" id="phone_number" name="phone_number"
                                value="{{ old('phone_number') }}" placeholder="Phone Number" required>
                            <p class="errorEmail text-center alert alert-danger hidden"></p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="url" class="col-sm-5 control-label">About/Profile Summary</label>
                        <div class="col-sm-10">
                            <textarea id="content" name="content" cols="30" rows="10" class="form-control" placeholder="Enter Content">
                      {{ old('content') }}
                      </textarea>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="inputPassword3" class="col-sm-2 control-label">Password</label>

                        <div class="col-sm-10">
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="Password" value="123456">
                            <p class="errorPassword text-center alert alert-danger hidden"></p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="visible" class="col-sm-4 control-label">Visible <span class="text-danger">*</span></label>
                        <div class="col-sm-10">
                            {!! Form::select('options', $options, null, ['id' => 'visible', 'name' => 'visible', 'class' => 'form-control', 'placeholder' => 'Pick a value','required'=>'true']) !!}
                        </div>
                    </div>
                    <hr>
                    <div class="form-group">
                        <small class="text-danger"> Ignore these following parts if you are creating a patient</small>
                    </div>
                    <hr>
                    <div class="form-group">
                        <div class="form-check checkbox-success checkbox-circle">
                            <input id="assignRole" type="checkbox" name="assignRole">
                            <label for="active">Assign Role</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="inputPassword3" class="col-sm-2 control-label">Roles</label>
                        <div class="col-sm-10">
                            {!! Form::select('roles[]', $roles, [], ['class' => 'form-control select2', 'multiple', 'style' => 'width: 100%;', 'data-toggle' => 'select2', 'data-placeholder' => 'Select to assign role...', 'data-allow-clear' => 'true']) !!}
                        </div>
                    </div>


                    <div class="form-group">
                        <div class="form-check checkbox-success checkbox-circle">
                            <input id="assignPermission" type="checkbox" name="assignPermission">
                            <label for="active">Assign Permission</label>
                        </div>
                    </div>


                    <div class="form-group">
                        <label for="inputPassword3" class="col-sm-2 control-label">Permissions</label>
                        <div class="col-sm-10">
                            {!! Form::select('permissions[]', $permissions, [], ['class' => 'form-control select2', 'multiple', 'style' => 'width: 100%;', 'data-toggle' => 'select2', 'data-placeholder' => 'Select to assign direct permission...', 'data-allow-clear' => 'true']) !!}
                        </div>
                    </div>

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
                                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i>
                                        Save</button>
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
    <script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script>
    <script src="{{ asset('/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
    <script src="{{ asset('plugins/ckeditor/ckeditor.js') }}"></script>
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta3/js/bootstrap-select.min.js" integrity="sha512-yrOmjPdp8qH8hgLfWpSFhC/+R9Cj9USL8uJxYIveJZGAiedxyIxwNw4RsLDlcjNlIRR4kkHaDHSmNHAkxFTmgg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script> -->

    <script>
        //  CKEDITOR.replace('content');
    </script>

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
