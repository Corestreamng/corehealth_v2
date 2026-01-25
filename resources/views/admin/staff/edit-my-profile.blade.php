@extends('admin.layouts.app')

@section('title', 'Edit Profile')
@section('page_name', 'User Management')
@section('subpage_name', 'Edit Profile')

@section('style')
@php
    $primaryColor = appsettings()->hos_color ?? '#011b33';
@endphp
<style>
    :root {
        --primary-color: {{ $primaryColor }};
        --primary-light: {{ $primaryColor }}15;
    }
</style>
<link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card-modern">
                <div class="card-header-modern">
                    <h3 class="card-title-modern">
                        <i class="mdi mdi-account-edit-outline mr-2"></i> Edit Profile
                    </h3>
                </div>
                <div class="card-body p-4">
                    {!! Form::model($user, [
                        'method' => 'POST',
                        'route' => ['update-my-profile', $user->id],
                        'enctype' => 'multipart/form-data',
                        'id' => 'profile-form'
                    ]) !!}
                    {{ csrf_field() }}

                    <!-- Image Upload -->
                    <div class="row mb-3">
                        <div class="col-md-6 mx-auto">
                            <div class="card-modern bg-light mb-0">
                                <div class="card-body text-center p-3">
                                    <h5 class="text-muted mb-2">Profile Image</h5>
                                    <img src="{!! asset('storage/image/user/' . $user->filename) !!}" id="myimg" class="preview-image mb-2" style="width: 100px; height: 100px;" />
                                    <div class="upload-zone p-2">
                                        <input type="file" name="filename" id="filename" accept="image/*">
                                        <i class="mdi mdi-camera upload-icon" style="font-size: 1.5rem;"></i>
                                        <p class="mb-0 small font-weight-bold">Change Photo</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="section-title mt-4">Personal Information</div>

                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">User Category <small class="text-muted">(Read Only)</small></label>
                            <input type="text" value="{{ Auth::user()->category->name ?? 'N/A' }}" disabled class="form-control form-control-modern bg-light">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Surname <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-modern" name="surname" value="{{ old('surname', $user->surname) }}" required>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Firstname <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-modern" name="firstname" value="{{ old('firstname', $user->firstname) }}" required>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Othername</label>
                            <input type="text" class="form-control form-control-modern" name="othername" value="{{ old('othername', $user->othername) }}">
                        </div>
                    </div>

                    <div class="section-title mt-4">Contact Details</div>

                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Email <small class="text-muted">(Read Only)</small></label>
                            <input type="email" class="form-control form-control-modern bg-light" value="{{ $user->email }}" disabled>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-modern" name="phone_number" value="{{ old('phone_number', $user->staff_profile->phone_number) }}" required>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Residential Address</label>
                            <textarea class="form-control form-control-modern" name="address" rows="2">{{ old('address', $user->staff_profile->home_address) }}</textarea>
                        </div>
                    </div>

                    <div class="section-title mt-4">Professional Details</div>

                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Specialization</label>
                            {!! Form::select('specialization', $specializations, $user->staff_profile->specialization_id ?? null, [
                                'class' => 'form-control form-control-modern select2',
                                'placeholder' => 'Select specialization',
                            ]) !!}
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Clinic</label>
                            {!! Form::select('clinic', $clinics, $user->staff_profile->clinic_id ?? null, [
                                'class' => 'form-control form-control-modern select2',
                                'placeholder' => 'Select clinic',
                            ]) !!}
                        </div>
                    </div>

                    <div class="section-title mt-4">Employment Information <small class="text-muted">(Read Only)</small></div>

                    <div class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label-modern">Employee ID</label>
                            <input type="text" class="form-control form-control-modern bg-light" value="{{ $user->staff_profile->employee_id ?? 'Not Assigned' }}" disabled>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label-modern">Date Hired</label>
                            <input type="text" class="form-control form-control-modern bg-light" value="{{ $user->staff_profile->date_hired ? $user->staff_profile->date_hired->format('M d, Y') : 'Not Set' }}" disabled>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label-modern">Job Title</label>
                            <input type="text" class="form-control form-control-modern bg-light" value="{{ $user->staff_profile->job_title ?? 'Not Set' }}" disabled>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label-modern">Department</label>
                            <input type="text" class="form-control form-control-modern bg-light" value="{{ $user->staff_profile->department->name ?? 'Not Set' }}" disabled>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label-modern">Employment Type</label>
                            <input type="text" class="form-control form-control-modern bg-light" value="{{ ucfirst(str_replace('_', ' ', $user->staff_profile->employment_type ?? 'Not Set')) }}" disabled>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label-modern">Employment Status</label>
                            <input type="text" class="form-control form-control-modern bg-light" value="{{ ucfirst($user->staff_profile->employment_status ?? 'Not Set') }}" disabled>
                        </div>
                    </div>

                    <div class="section-title mt-4">Bank Information</div>

                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Bank Name</label>
                            <input type="text" class="form-control form-control-modern" name="bank_name" value="{{ old('bank_name', $user->staff_profile->bank_name ?? '') }}" placeholder="e.g. First Bank">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Account Number</label>
                            <input type="text" class="form-control form-control-modern" name="bank_account_number" value="{{ old('bank_account_number', $user->staff_profile->bank_account_number ?? '') }}" placeholder="e.g. 0123456789">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Account Name</label>
                            <input type="text" class="form-control form-control-modern" name="bank_account_name" value="{{ old('bank_account_name', $user->staff_profile->bank_account_name ?? '') }}" placeholder="Account holder name">
                        </div>
                    </div>

                    <div class="section-title mt-4">Emergency Contact</div>

                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Contact Name</label>
                            <input type="text" class="form-control form-control-modern" name="emergency_contact_name" value="{{ old('emergency_contact_name', $user->staff_profile->emergency_contact_name ?? '') }}" placeholder="Full name">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Contact Phone</label>
                            <input type="text" class="form-control form-control-modern" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $user->staff_profile->emergency_contact_phone ?? '') }}" placeholder="Phone number">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Relationship</label>
                            <select class="form-control form-control-modern" name="emergency_contact_relationship">
                                <option value="">Select relationship</option>
                                @php
                                    $relationships = ['Spouse', 'Parent', 'Sibling', 'Child', 'Friend', 'Other'];
                                    $currentRel = old('emergency_contact_relationship', $user->staff_profile->emergency_contact_relationship ?? '');
                                @endphp
                                @foreach($relationships as $rel)
                                    <option value="{{ $rel }}" {{ $currentRel == $rel ? 'selected' : '' }}>{{ $rel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="section-title mt-4">Tax & Pension IDs</div>

                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Tax ID (TIN)</label>
                            <input type="text" class="form-control form-control-modern" name="tax_id" value="{{ old('tax_id', $user->staff_profile->tax_id ?? '') }}" placeholder="Tax identification number">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Pension ID</label>
                            <input type="text" class="form-control form-control-modern" name="pension_id" value="{{ old('pension_id', $user->staff_profile->pension_id ?? '') }}" placeholder="Pension identification number">
                        </div>
                    </div>

                    <div class="section-title mt-4">Security</div>

                    <div class="row g-3">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">New Password</label>
                            <input type="password" class="form-control form-control-modern" name="password" placeholder="Leave blank to keep current">
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label-modern">Confirm Password</label>
                            <input type="password" class="form-control form-control-modern" name="password_confirmation" placeholder="Confirm new password">
                        </div>
                    </div>

                    {!! Form::close() !!}
                </div>

                <!-- Action Buttons -->
                <div class="card-footer bg-white border-top py-3">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('home') }}" class="btn btn-light border px-4">Cancel</a>
                        <button type="submit" form="profile-form" class="btn btn-primary-modern px-4" onclick="return confirm('Are you sure you wish to apply the changes above to your profile?')">
                            <i class="mdi mdi-content-save mr-1"></i> Save Changes
                        </button>
                    </div>
                </div>
            </div>
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
