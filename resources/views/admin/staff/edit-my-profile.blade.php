@extends('admin.layouts.app')

@section('title', 'Edit User')
@section('page_name', 'User Management')
@section('subpage_name', 'Edit User')

@section('content')
<div class="col-12">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light">
            <h3 class="card-title fw-bold text-primary">Edit Profile</h3>
        </div>
        <div class="card-body">
            {!! Form::model($user, [
                'method' => 'POST',
                'route' => ['update-my-profile', $user->id],
                'enctype' => 'multipart/form-data',
            ]) !!}
            {{ csrf_field() }}

            <!-- Image Upload -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="text-muted mb-2">Current Profile Image</h5>
                    <img src="{!! asset('storage/image/user/' . $user->filename) !!}" width="150" height="120" class="rounded shadow-sm mb-3" />
                    <div class="form-group">
                        {{ Form::label('filename', 'Upload New Passport:', ['class' => 'form-label']) }}
                        {{ Form::file('filename', ['class' => 'form-control']) }}
                        <small class="text-muted">Only .jpg, .png formats allowed.</small>
                    </div>
                    <img src="" id="myimg" class="rounded mt-3" width="80" />
                </div>
                <div class="col-md-6">
                    @if ($user->old_records)
                    <div class="mb-3">
                        <a href="{!! url('storage/image/user/old_records/' . $user->old_records) !!}" target="_blank" class="btn btn-outline-secondary btn-sm">
                            <i class="fa fa-file me-1"></i> View Old Records
                        </a>
                    </div>
                    @endif
                    <div class="form-group">
                        {{ Form::label('old_records', 'Upload Updated Records:', ['class' => 'form-label']) }}
                        {{ Form::file('old_records', ['class' => 'form-control']) }}
                    </div>
                </div>
            </div>

            <hr>

            <div class="alert alert-info small">Fields marked with <span class="text-danger">*</span> are required.</div>

            <!-- Personal Info -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">User Category <small class="text-muted">(Contact admin for changes)</small> <span class="text-danger">*</span></label>
                    <input type="text" value="{{ Auth::user()->category->name }}" disabled class="form-control bg-light">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Surname <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="surname" value="{{ old('surname', $user->surname) }}" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Firstname <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="firstname" value="{{ old('firstname', $user->firstname) }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Othername</label>
                    <input type="text" class="form-control" name="othername" value="{{ old('othername', $user->othername) }}">
                </div>
            </div>

            <!-- Contact -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email <small class="text-muted">(Contact admin to update)</small></label>
                    <input type="email" class="form-control bg-light" value="{{ $user->email }}" disabled>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="phone_number" value="{{ old('phone_number', $user->staff_profile->phone_number) }}" required>
                </div>
            </div>

            <!-- Password Update -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Password <small class="text-muted">(Leave blank to keep current password)</small></label>
                    <input type="password" class="form-control" name="password" placeholder="New Password">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" name="password_confirmation" placeholder="Confirm Password">
                </div>
            </div>

            <!-- Role-Specific Info -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Specialization <span class="text-danger">*</span></label>
                    {!! Form::select('specialization', $specializations, $user->staff_profile->specialization_id, [
                        'id' => 'specializations',
                        'class' => 'form-control',
                        'placeholder' => 'Select specialization',
                    ]) !!}
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Clinic <span class="text-danger">*</span></label>
                    {!! Form::select('clinic', $clinics, $user->staff_profile->clinic_id, [
                        'id' => 'clinics',
                        'class' => 'form-control',
                        'placeholder' => 'Select clinic',
                    ]) !!}
                </div>
            </div>

            <!-- Gender & DOB -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Gender <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="mdi mdi-gender-male-female"></i></span>
                        <select name="gender" class="form-control" required>
                            <option value="">Select gender</option>
                            <option value="Male" {{ $user->staff_profile->gender == 'Male' ? 'selected' : '' }}>Male</option>
                            <option value="Female" {{ $user->staff_profile->gender == 'Female' ? 'selected' : '' }}>Female</option>
                            <option value="Others" {{ $user->staff_profile->gender == 'Others' ? 'selected' : '' }}>Others</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="mdi mdi-calendar"></i></span>
                        <input type="date" name="dob" class="form-control" value="{{ old('dob', $user->staff_profile->date_of_birth) }}" required>
                    </div>
                </div>
            </div>

            <!-- Address & Fee -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="mdi mdi-map-marker-radius"></i></span>
                        <textarea name="address" class="form-control">{{ old('address', $user->staff_profile->home_address) }}</textarea>
                    </div>
                </div>
                {{-- <div class="col-md-6 mb-3">
                    <label class="form-label">Consultation Fee <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₦</span>
                        <input type="number" name="consultation_fee" class="form-control" value="{{ old('consultation_fee', $user->staff_profile->consultation_fee) }}">
                    </div>
                </div> --}}
            </div>

            <!-- Roles and Permissions -->
            @if ($user->assignRole == 1)
            <div class="mb-3">
                <label class="form-label">Roles Assigned:</label><br>
                @foreach ($user->getRoleNames() as $v)
                    <span class="badge bg-success">{{ $v }}</span>
                @endforeach
            </div>
            @endif

            @if ($user->assignPermission == 1)
            <div class="mb-3">
                <label class="form-label">Permissions Assigned:</label><br>
                @foreach ($user->getPermissionNames() as $v)
                    <span class="badge bg-info">{{ $v }}</span>
                @endforeach
            </div>
            @endif

            <!-- Action Buttons -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <a href="{{ route('home') }}" class="btn btn-outline-danger">
                        <i class="fa fa-arrow-left me-1"></i> Cancel
                    </a>
                </div>
                <div class="col-md-6 text-end">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you wish to apply the changes above to your profile?')">
                        <i class="fa fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </div>

            {!! Form::close() !!}
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function () {
        $('.select2').select2();
    });

    document.querySelector('#filename').addEventListener('change', function () {
        const input = this;
        const myimg = document.getElementById("myimg");
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                myimg.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    });
</script>
@endsection
