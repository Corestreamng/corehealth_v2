@extends('admin.layouts.app')
@section('title', 'View User')
@section('page_name', 'User Management')
@section('subpage_name', 'View User')
@section('content')

    <div class="col-12">
        <div modern">
            <div class="card-header">
                <h3 class="card-title">View User</h3>
            </div>
            <div class="card-body">
                <!-- <form class="form-horizontal" role="form"> -->
                {!! Form::model($user, ['method' => 'PATCH', 'route' => ['staff.update', $user->id]]) !!}
                {{ csrf_field() }}
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
                    <label class="control-label col-md-2" for="id">User Category:</label>
                    <div class="col-md-10">
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
                    <label class="control-label col-md-2" for="title">Surname:</label>
                    <div class="col-md-10">
                        <input type="text" class="form-control" id="surname" name="surname" readonly
                            value="{!! !empty($user->surname) ? $user->surname : old('surname') !!}" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-md-2" for="title">Firstname:</label>
                    <div class="col-md-10">
                        <input type="text" class="form-control" id="firstname" name="firstname" readonly
                            value="{!! !empty($user->firstname) ? $user->firstname : old('firstname') !!}" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-md-2" for="title">Othername:</label>
                    <div class="col-md-10">
                        <input type="text" class="form-control" id="othername" name="othername" readonly
                            value="{!! !empty($user->othername) ? $user->othername : old('othername') !!}" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-md-2" for="title">Email:</label>
                    <div class="col-md-10">
                        <input type="text" class="form-control" id="email" name="email" readonly
                            value="{!! !empty($user->email) ? $user->email : old('email') !!}" autofocus>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="phone_number" class=" control-label">Phone Number <span class="text-danger">*</span></label>

                        <div class="">
                            <input type="phone_number" class="form-control" id="phone_number" name="phone_number"
                                value="{!! !empty($user->othername) ? $user->othername : old('othername') !!}" placeholder="Phone Number" required>
                        </div>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="password" class="control-label">Password</label>

                        <div class="">
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="Password" value="pwd" disabled>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="is_admin" class=" control-label">Specialization</label>

                        <div class="">
                            {!! Form::select('specializations', $specializations, ($user->staff_profile) ? ($user->staff_profile->specialization_id) : null , ['id' => 'specializations', 'name' => 'specialization', 'class' => 'form-control select2', 'placeholder' => 'Pick a value']) !!}
                        </div>
                    </div>

                    <div class="form-group col-md-6">
                        <label for="is_admin" class=" control-label">Clinic <span class="text-danger">*Required for doctors</span></label>

                        <div class="">
                            {!! Form::select('clinics', $clinics, $user->staff_profile->clinic_id, ['id' => 'clinics', 'name' => 'clinic', 'class' => 'form-control select2', 'placeholder' => 'Pick a value']) !!}
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
                                <select class="form-control" placeholder="gender"
                                aria-label="gender" aria-describedby="gender" name="gender">
                                    <option value="">Select gender</option>
                                    <option value="Male" {{($user->staff_profile->gender == "Male") ? "selected" : "" }}>Male</option>
                                    <option value="Female" {{($user->staff_profile->gender == "Female") ? "selected" : "" }}>Female</option>
                                    <option value="Others" {{($user->staff_profile->gender == "Others") ? "selected" : "" }}>Others</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="">Date of Birth <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text" id="dob"><i
                                        class="mdi mdi-calendar"></i></span>
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
                            <label for="">Consultation fee</label>
                            <div class="input-group">
                                <span class="input-group-text" id="consultation_fee-">NGN</span>
                                <input type="number" name="consultation_fee" class="form-control" value="{!! !empty($user->staff_profile->consultation_fee) ? $user->staff_profile->consultation_fee : old('consultation_fee') !!}">
                            </div>
                        </div>
                    </div>
                </div>
                @if ($user->assignRole == 1)

                    <div class="form-group">
                        <label for="inputPassword3" class="col-md-2 control-label">Roles Assigned:</label>

                        <div class="col-md-10">
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
                        <label for="inputPassword3" class="col-md-2 control-label">Permission Assigned:</label>

                        <div class="col-md-10">
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
                            <div class="col-md-offset-1 col-md-6">
                                <!-- <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Update</button> -->
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="col-md-6">
                                <a href="{{ route('staff.index') }}" class="pull-right btn btn-danger"><i
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
    {{-- <script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script> --}}
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
