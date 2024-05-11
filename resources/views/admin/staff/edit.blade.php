@extends('admin.layouts.app')
@section('title', 'Edit User')
@section('page_name', 'User Management')
@section('subpage_name', 'Edit User')
@section('content')
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">User Management</h3>
            </div>
            <div class="card-body">
                {!! Form::model($user, [
                    'method' => 'PATCH',
                    'route' => ['staff.update', $user->id],
                    'enctype' => 'multipart/form-data',
                ]) !!}
                {{ csrf_field() }}
                {{-- <input type="hidden" name="_method" value="PUT"> --}}
                <div class="row">
                    <div class="form-group col-md-6">
                        <h4>Active Image</h4>
                        <img src="{!! url('storage/image/user/' . $user->filename) !!}" valign="middle" width="150px" height="120px" />
                        <br>
                        <div class="form-group">
                            <div class="row">
                                <div class="col-md-6 float-left">
                                    {{ Form::label('filename', 'Select New Passport:') }}
                                    {{ Form::file('filename') }}
                                </div>

                                <div class="col-md-4">
                                    {{-- <div id="destination" class="h-auto d-inline-block bg-info" style="width: 60px;"></div> --}}
                                    <img src="" class="float-right" id="myimg" width=80>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="form-group col-md-6">
                        <div class="row">
                            <div class="col-md-4">
                                @if ($user->old_records)
                                    <div class="form-group">
                                        <a href="{!! url('storage/image/user/old_records/' . $user->old_records) !!}" target="_blank"><i class="fa fa-file"></i> Old
                                            Records</a>
                                        <br>
                                        <hr>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                {{ Form::label('old_records', 'Update Old Records') }}
                                {{ Form::file('old_records') }}
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="form-group">
                    <small class="text-danger"> Fields Marked * Are Required</small>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label class="control-label" for="id">User Category: <span
                                class="text-danger">*</span></label>
                        <div class="">
                            <select class="form-control" id="is_admin" name="is_admin" required
                                placeholder="Select Status Category">
                                <option value="0">--Select--</option>
                                @foreach ($statuses as $status)
                                    @if ($status->id == $user->is_admin || $status->id == old('is_admin'))
                                        <option value="{{ $status->id }}" selected>{{ $status->name }}</option>
                                    @else
                                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="control-label" for="title">Surname: <span class="text-danger">*</span></label>
                        <div class="">
                            <input type="text" class="form-control" id="surname" name="surname"
                                value="{!! !empty($user->surname) ? $user->surname : old('surname') !!}" autofocus>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label class="control-label" for="title">Firstname: <span class="text-danger">*</span></label>
                        <div class="">
                            <input type="text" class="form-control" id="firstname" name="firstname"
                                value="{!! !empty($user->firstname) ? $user->firstname : old('firstname') !!}" autofocus>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="control-label" for="title">Othername:</label>
                        <div class="">
                            <input type="text" class="form-control" id="othername" name="othername"
                                value="{!! !empty($user->othername) ? $user->othername : old('othername') !!}" autofocus>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-12">
                        <label class="control-label" for="title">Email:</label>
                        <div class="">
                            <input type="text" class="form-control" id="email" name="email"
                                value="{!! !empty($user->email) ? $user->email : old('email') !!}" autofocus>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="phone_number" class=" control-label">Phone Number <span
                                class="text-danger">*</span></label>

                        <div class="">
                            <input type="phone_number" class="form-control" id="phone_number" name="phone_number"
                                value="{!! !empty($user->staff_profile->phone_number) ? $user->staff_profile->phone_number : old('phone_number') !!}" placeholder="Phone Number" required>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="password" class="control-label">Password</label>

                        <div class="">
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="Password" value="123456">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="is_admin" class=" control-label">Specialization <span class="text-danger">*Required
                                for
                                doctors</span></label>

                        <div class="">
                            {!! Form::select(
                                'specializations',
                                $specializations,
                                $user->staff_profile->specialization_id ? $user->staff_profile->specialization_id : null,
                                [
                                    'id' => 'specializations',
                                    'name' => 'specialization',
                                    'class' => 'form-control ',
                                    'placeholder' => 'Pick a value',
                                ],
                            ) !!}
                        </div>
                    </div>

                    <div class="form-group col-md-6">
                        <label for="is_admin" class=" control-label">Clinic <span class="text-danger">*Required for
                                doctors</span></label>

                        <div class="">
                            {!! Form::select('clinics', $clinics, $user->staff_profile->clinic_id, [
                                'id' => 'clinics',
                                'name' => 'clinic',
                                'class' => 'form-control ',
                                'placeholder' => 'Pick a value',
                            ]) !!}
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="">Gender <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text" id="gender"><i
                                        class="mdi mdi-gender-male-female"></i></span>
                                <select class="form-control" placeholder="gender" aria-label="gender"
                                    aria-describedby="gender" name="gender" required>
                                    <option value="">Select gender</option>
                                    <option value="Male" {{ $user->staff_profile->gender == 'Male' ? 'selected' : '' }}>
                                        Male</option>
                                    <option value="Female"
                                        {{ $user->staff_profile->gender == 'Female' ? 'selected' : '' }}>Female</option>
                                    <option value="Others"
                                        {{ $user->staff_profile->gender == 'Others' ? 'selected' : '' }}>Others</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="">Date of Birth <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text" id="dob"><i class="mdi mdi-calendar"></i></span>
                                <input type="date" class="form-control" placeholder="dob" aria-label="dob"
                                    aria-describedby="dob" name="dob" value="{!! !empty($user->staff_profile->date_of_birth) ? $user->staff_profile->date_of_birth : old('dob') !!}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="">Address</label>
                            <div class="input-group">
                                <span class="input-group-text" id="address"><i
                                        class="mdi mdi-map-marker-radius"></i></span>
                                <textarea name="address" id="address" class="form-control">{!! !empty($user->staff_profile->home_address) ? $user->staff_profile->home_address : old('address') !!}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="">Consultation fee <span class="text-danger">*Required for
                                    doctors</span></label>
                            <div class="input-group">
                                <span class="input-group-text" id="consultation_fee-">NGN</span>
                                <input type="number" name="consultation_fee" class="form-control"
                                    value="{!! !empty($user->staff_profile->consultation_fee)
                                        ? $user->staff_profile->consultation_fee
                                        : old('consultation_fee') !!}">
                            </div>
                        </div>
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
                    <label for="inputPassword3" class="col-md-2 control-label">Roles</label>
                    <div class="">
                        {!! Form::select('roles[]', $roles, $userRole, [
                            'id' => 'roles',
                            'class' => 'form-control select2',
                            'multiple',
                            'style' => 'width: 100%;',
                            'data-toggle' => '',
                            'data-placeholder' => 'Select to assign role...',
                            'data-allow-clear' => 'true',
                        ]) !!}
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
                    <label for="inputPassword3" class="col-md-2 control-label">Permissions</label>
                    <div class="">
                        {!! Form::select('permissions[]', $permissions, $userPermission, [
                            'class' => 'form-control select2',
                            'multiple',
                            'style' => 'width: 100%;',
                            'data-toggle' => '',
                            'data-placeholder' => 'Select to assign permission...',
                            'data-allow-clear' => 'true',
                        ]) !!}
                        <p class="errorRoles text-center alert alert-danger hidden"></p>
                    </div>
                </div>
                {{-- @endif --}}


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

    {{-- <script src="{{ asset('/plugins/select2/select2.min.js') }}"></script> --}}

    <script defer>
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
