@extends('admin.layouts.app')
@section('title', 'Create Staff')
@section('page_name', 'Staff')
@section('subpage_name', 'Create Staff')
@section('content')
    <section class="content">

        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Create staff</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">

                    {!! Form::open(['method' => 'POST', 'route' => 'staff.store', 'class' => 'form-horizontal', 'enctype' => 'multipart/form-data']) !!}
                    {{ csrf_field() }}
                    <div class="row">
                        <div class="form-group">
                            <div class="row">
                                <div class="col-sm-6">
                                    {{ Form::label('filename', 'Passport:') }}
                                    {{ Form::file('filename') }}
                                </div>

                                <div class="">
                                    {{-- <div id="destination" class="h-auto d-inline-block bg-info" style="width: 60px;"></div> --}}
                                    <img src="" class="float-right" id="myimg" width=80>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="row">
                                <div class="col-sm-6">
                                    {{ Form::label('old_records', 'Old Records') }}
                                    {{ Form::file('old_records') }}
                                </div>

                                <div class="">
                                    {{-- <div id="destination" class="h-auto d-inline-block bg-info" style="width: 60px;"></div> --}}
                                    <img src="" class="float-right" id="myimg" width=80>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="form-group">
                        <small class="text-danger"> Fields Marked * Are  Required</small>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="form-group col-sm-6">
                            <label for="is_admin" class=" control-label">Status/Category <span class="text-danger">*</span></label>


                                {!! Form::select('statuses', $statuses, old('is_admin'), ['id' => 'is_admin', 'name' => 'is_admin', 'class' => 'form-control select2', 'placeholder' => 'Pick a value','required' => 'true']) !!}

                        </div>
                        <div class="form-group col-sm-6">
                            <label for="surname" class=" control-label">Surname <span class="text-danger">*</span></label>


                                <input type="text" class="form-control" id="surname" name="surname"
                                    value="{{ old('surname') }}" autofocus placeholder="Enter Surname" required>

                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-6">
                            <label for="inputEmail3" class=" control-label">Firstname <span class="text-danger">*</span></label>


                                <input type="text" class="form-control" id="firstname" name="firstname"
                                    value="{{ old('firstname') }}" placeholder="Firstname" required>
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="othername" class=" control-label">Othernames</label>

                            <div class="">
                                <input type="text" class="form-control" id="othername" name="othername"
                                    value="{{ old('othername') }}" placeholder="Othername">

                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email" class=" control-label">Email <span class="text-danger">*</span></label>

                        <div class="">
                            <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}"
                                placeholder="Email" autocomplete="off">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-sm-6">
                            <label for="phone_number" class=" control-label">Phone Number <span class="text-danger">*</span></label>

                            <div class="">
                                <input type="phone_number" class="form-control" id="phone_number" name="phone_number"
                                    value="{{ old('phone_number') }}" placeholder="Phone Number" required>
                            </div>
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="password" class="control-label">Password</label>

                            <div class="">
                                <input type="password" class="form-control" id="password" name="password"
                                    placeholder="Password" value="123456">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-sm-6">
                            <label for="is_admin" class=" control-label">Specialization <span class="text-danger">*Required for doctors</span></label>

                            <div class="">
                                {!! Form::select('specializations', $specializations, old('specialization'), ['id' => 'specializations', 'name' => 'specialization', 'class' => 'form-control select2', 'placeholder' => 'Pick a value']) !!}
                            </div>
                        </div>

                        <div class="form-group col-sm-6">
                            <label for="is_admin" class=" control-label">Clinic <span class="text-danger">*Required for doctors</span></label>

                            <div class="">
                                {!! Form::select('clinics', $clinics, null, ['id' => 'clinics', 'name' => 'clinic', 'class' => 'form-control select2', 'placeholder' => 'Pick a value']) !!}
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="">Gender <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text" id="gender"><i
                                            class="mdi mdi-gender-male-female"></i></span>
                                    <select class="form-control" placeholder="gender"
                                    aria-label="gender" aria-describedby="gender" name="gender" required>
                                        <option value="">Select gender</option>
                                        <option value="Male" {{(old('gender') == 'Male') ? 'selected': ''}}>Male</option>
                                        <option value="Female" {{(old('gender') == 'Female') ? 'selected': ''}}>Female</option>
                                        <option value="Others" {{(old('gender') == 'Others') ? 'selected': ''}}>Others</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="">Date of Birth <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text" id="dob"><i
                                            class="mdi mdi-calendar"></i></span>
                                    <input type="text" class="form-control" placeholder="dob" aria-label="dob"
                                        aria-describedby="dob" name="dob" value="{{old('dob')}}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="">Address</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="address"><i
                                            class="mdi mdi-map-marker-radius"></i></span>
                                    <textarea name="address" id="address" class="form-control">{{old('address')}}</textarea>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="">Consultation fee <span class="text-danger">*Required for doctors</span></label>
                                <div class="input-group">
                                    <span class="input-group-text" id="consultation_fee-">NGN</span>
                                    <input type="number" name="consultation_fee" class="form-control" value="{{old('consultation_fee')}}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="form-group">
                        <div class="form-check checkbox-success checkbox-circle">
                            <input id="assignRole" type="checkbox" name="assignRole">
                            <label for="active">Assign Role</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="inputPassword3" class=" control-label">Roles</label>
                        <div class="">
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
                        <label for="inputPassword3" class=" control-label">Permissions</label>
                        <div class="">
                            {!! Form::select('permissions[]', $permissions, [], ['class' => 'form-control select2', 'multiple', 'style' => 'width: 100%;', 'data-toggle' => 'select2', 'data-placeholder' => 'Select to assign direct permission...', 'data-allow-clear' => 'true']) !!}
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="float-left">
                                    <a href="{{ route('staff.index') }}" class="pull-right btn btn-danger"><i
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
    {{-- <script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script> --}}
    {{-- <script src="{{ asset('/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
    <script src="{{ asset('plugins/ckeditor/ckeditor.js') }}"></script> --}}
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta3/js/bootstrap-select.min.js" integrity="sha512-yrOmjPdp8qH8hgLfWpSFhC/+R9Cj9USL8uJxYIveJZGAiedxyIxwNw4RsLDlcjNlIRR4kkHaDHSmNHAkxFTmgg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script> -->

    <script>
        //  CKEDITOR.replace('content');
    </script>

    <script>
        $(document).ready(function() {
            // $.noConflict();
            // CKEDITOR.replace('content');
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
