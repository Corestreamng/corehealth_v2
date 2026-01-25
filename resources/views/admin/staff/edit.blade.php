@extends('admin.layouts.app')
@section('title', 'Edit User')
@section('page_name', 'User Management')
@section('subpage_name', 'Edit User')
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
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1 font-weight-bold text-dark">Edit Staff</h2>
                        <p class="text-muted mb-0">Update staff member details</p>
                    </div>
                </div>
                <div class="card-body p-4">
                    {!! Form::model($user, [
                        'method' => 'PATCH',
                        'route' => ['staff.update', $user->id],
                        'enctype' => 'multipart/form-data',
                    ]) !!}
                    {{ csrf_field() }}

                    <div class="row">
                        <!-- Left Column: Image & Files -->
                        <div class="col-lg-3">
                    <div class="card-modern">
                        <div class="card-header-modern">
                            <h5 class="card-title-modern">
                                <i class="mdi mdi-camera-outline text-primary"></i> Profile Image
                            </h5>
                        </div>
                        <div class="card-body text-center p-3">
                            <img src="{!! url('storage/image/user/' . $user->filename) !!}" id="preview-img" class="preview-image mb-3" style="width: 100px; height: 100px;">
                            <div class="upload-zone">
                                <input type="file" name="filename" id="filename" accept="image/*" onchange="previewImage(this)">
                                <i class="mdi mdi-cloud-upload upload-icon"></i>
                                <p class="mb-0 font-weight-bold">Change Image</p>
                                <small class="text-muted">JPG, PNG up to 2MB</small>
                            </div>
                        </div>
                    </div>

                    <div class="card-modern">
                        <div class="card-header-modern">
                            <h5 class="card-title-modern">
                                <i class="mdi mdi-file-document-outline text-primary"></i> Documents
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            @if ($user->old_records)
                                <div class="mb-3 p-2 bg-light rounded">
                                    <a href="{!! url('storage/image/user/old_records/' . $user->old_records) !!}" target="_blank" class="text-primary font-weight-bold">
                                        <i class="mdi mdi-file-pdf mr-1"></i> View Current Record
                                    </a>
                                </div>
                            @endif
                            <label class="form-label-modern">Update Records</label>
                            <input type="file" class="form-control form-control-modern" id="old_records" name="old_records" style="height: auto; padding: 0.5rem;">
                            <small class="text-muted mt-2 d-block">Upload new records to replace existing ones.</small>
                        </div>
                    </div>
                </div>

                        <!-- Right Column: Details -->
                        <div class="col-lg-9">
                            <!-- Personal Information -->
                            <div class="card-modern">
                                <div class="card-header-modern">
                                    <h5 class="card-title-modern">
                                        <i class="mdi mdi-account-details-outline text-primary"></i> Personal Information
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Surname <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-modern" name="surname" value="{!! !empty($user->surname) ? $user->surname : old('surname') !!}" required>
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Firstname <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-modern" name="firstname" value="{!! !empty($user->firstname) ? $user->firstname : old('firstname') !!}" required>
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Othername</label>
                                            <input type="text" class="form-control form-control-modern" name="othername" value="{!! !empty($user->othername) ? $user->othername : old('othername') !!}">
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Gender <span class="text-danger">*</span></label>
                                            <select class="form-control form-control-modern" name="gender" required>
                                                <option value="">Select gender</option>
                                                <option value="Male" {{ ($user->staff_profile->gender ?? '') == 'Male' ? 'selected' : '' }}>Male</option>
                                                <option value="Female" {{ ($user->staff_profile->gender ?? '') == 'Female' ? 'selected' : '' }}>Female</option>
                                                <option value="Others" {{ ($user->staff_profile->gender ?? '') == 'Others' ? 'selected' : '' }}>Others</option>
                                            </select>
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Date of Birth <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control form-control-modern" name="dob" value="{{ $user->staff_profile->date_of_birth ? $user->staff_profile->date_of_birth->format('Y-m-d') : old('dob') }}" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Professional Details -->
                            <div class="card-modern">
                                <div class="card-header-modern">
                                    <h5 class="card-title-modern">
                                        <i class="mdi mdi-doctor text-primary"></i> Professional Details
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Staff Category <span class="text-danger">*</span></label>
                                            <select class="form-control form-control-modern" id="is_admin" name="is_admin" required>
                                                <option value="0">--Select--</option>
                                                @foreach ($statuses as $status)
                                                    <option value="{{ $status->id }}" {{ ($status->id == $user->is_admin || $status->id == old('is_admin')) ? 'selected' : '' }}>{{ $status->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Specialization <small class="text-muted">(Doctors)</small></label>
                                            {!! Form::select('specializations', $specializations, $user->staff_profile->specialization_id ?? null, ['class' => 'form-control form-control-modern', 'placeholder' => 'Select Specialization']) !!}
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Clinic <small class="text-muted">(Doctors)</small></label>
                                            {!! Form::select('clinics', $clinics, $user->staff_profile->clinic_id ?? null, ['class' => 'form-control form-control-modern', 'placeholder' => 'Select Clinic']) !!}
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Consultation Fee <small class="text-muted">(Doctors)</small></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">NGN</span>
                                                </div>
                                                <input type="number" name="consultation_fee" class="form-control form-control-modern" value="{{ $user->staff_profile->consultation_fee ?? old('consultation_fee') }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- HR Employment Details -->
                            <div class="card-modern">
                                <div class="card-header-modern">
                                    <h5 class="card-title-modern">
                                        <i class="mdi mdi-briefcase-outline text-primary"></i> Employment Details
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-lg-3 col-md-6">
                                            <label class="form-label-modern">Employee ID</label>
                                            <input type="text" class="form-control form-control-modern" name="employee_id" value="{{ $user->staff_profile->employee_id ?? old('employee_id') }}" placeholder="e.g. EMP001">
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <label class="form-label-modern">Date Hired</label>
                                            <input type="date" class="form-control form-control-modern" name="date_hired" value="{{ $user->staff_profile->date_hired ?? old('date_hired') }}">
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <label class="form-label-modern">Employment Type</label>
                                            <select class="form-control form-control-modern" name="employment_type">
                                                <option value="">Select Type</option>
                                                <option value="full_time" {{ ($user->staff_profile->employment_type ?? '') == 'full_time' ? 'selected' : '' }}>Full Time</option>
                                                <option value="part_time" {{ ($user->staff_profile->employment_type ?? '') == 'part_time' ? 'selected' : '' }}>Part Time</option>
                                                <option value="contract" {{ ($user->staff_profile->employment_type ?? '') == 'contract' ? 'selected' : '' }}>Contract</option>
                                                <option value="intern" {{ ($user->staff_profile->employment_type ?? '') == 'intern' ? 'selected' : '' }}>Intern</option>
                                            </select>
                                        </div>
                                        <div class="col-lg-3 col-md-6">
                                            <label class="form-label-modern">Employment Status</label>
                                            <select class="form-control form-control-modern" name="employment_status">
                                                <option value="">Select Status</option>
                                                <option value="active" {{ ($user->staff_profile->employment_status ?? '') == 'active' ? 'selected' : '' }}>Active</option>
                                                <option value="suspended" {{ ($user->staff_profile->employment_status ?? '') == 'suspended' ? 'selected' : '' }}>Suspended</option>
                                                <option value="resigned" {{ ($user->staff_profile->employment_status ?? '') == 'resigned' ? 'selected' : '' }}>Resigned</option>
                                                <option value="terminated" {{ ($user->staff_profile->employment_status ?? '') == 'terminated' ? 'selected' : '' }}>Terminated</option>
                                            </select>
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Job Title</label>
                                            <input type="text" class="form-control form-control-modern" name="job_title" value="{{ $user->staff_profile->job_title ?? old('job_title') }}" placeholder="e.g. Senior Nurse">
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Department</label>
                                            <select class="form-control form-control-modern" name="department_id">
                                                <option value="">Select Department</option>
                                                @foreach($departments as $department)
                                                    <option value="{{ $department->id }}" {{ ($user->staff_profile->department_id ?? old('department_id')) == $department->id ? 'selected' : '' }}>
                                                        {{ $department->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bank & Tax Information -->
                            <div class="card-modern">
                                <div class="card-header-modern">
                                    <h5 class="card-title-modern">
                                        <i class="mdi mdi-bank text-primary"></i> Bank & Tax Information
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Bank Name</label>
                                            <input type="text" class="form-control form-control-modern" name="bank_name" value="{{ $user->staff_profile->bank_name ?? old('bank_name') }}" placeholder="e.g. First Bank">
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Account Number</label>
                                            <input type="text" class="form-control form-control-modern" name="bank_account_number" value="{{ $user->staff_profile->bank_account_number ?? old('bank_account_number') }}" placeholder="0123456789">
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Account Name</label>
                                            <input type="text" class="form-control form-control-modern" name="bank_account_name" value="{{ $user->staff_profile->bank_account_name ?? old('bank_account_name') }}" placeholder="Account holder name">
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Tax ID (TIN)</label>
                                            <input type="text" class="form-control form-control-modern" name="tax_id" value="{{ $user->staff_profile->tax_id ?? old('tax_id') }}" placeholder="Tax identification number">
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Pension ID</label>
                                            <input type="text" class="form-control form-control-modern" name="pension_id" value="{{ $user->staff_profile->pension_id ?? old('pension_id') }}" placeholder="Pension number">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Emergency Contact -->
                            <div class="card-modern">
                                <div class="card-header-modern">
                                    <h5 class="card-title-modern">
                                        <i class="mdi mdi-phone-alert text-primary"></i> Emergency Contact
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Contact Name</label>
                                            <input type="text" class="form-control form-control-modern" name="emergency_contact_name" value="{{ $user->staff_profile->emergency_contact_name ?? old('emergency_contact_name') }}" placeholder="Full name">
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Contact Phone</label>
                                            <input type="text" class="form-control form-control-modern" name="emergency_contact_phone" value="{{ $user->staff_profile->emergency_contact_phone ?? old('emergency_contact_phone') }}" placeholder="+234...">
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Relationship</label>
                                            <input type="text" class="form-control form-control-modern" name="emergency_contact_relationship" value="{{ $user->staff_profile->emergency_contact_relationship ?? old('emergency_contact_relationship') }}" placeholder="e.g. Spouse, Parent">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Details -->
                            <div class="card-modern">
                                <div class="card-header-modern">
                                    <h5 class="card-title-modern">
                                        <i class="mdi mdi-card-account-mail-outline text-primary"></i> Contact Details
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Email Address <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control form-control-modern" name="email" value="{{ $user->email ?? old('email') }}" required>
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Phone Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-modern" name="phone_number" value="{{ $user->staff_profile->phone_number ?? old('phone_number') }}" required>
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Residential Address</label>
                                            <textarea class="form-control form-control-modern" name="address" rows="3">{{ $user->staff_profile->home_address ?? old('address') }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Security & Access -->
                            <div class="card-modern">
                                <div class="card-header-modern">
                                    <h5 class="card-title-modern">
                                        <i class="mdi mdi-shield-lock-outline text-primary"></i> Security & Access
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row g-3">
                                        <div class="col-lg-4 col-md-6">
                                            <label class="form-label-modern">Password</label>
                                            <input type="password" class="form-control form-control-modern" name="password" placeholder="Leave blank to keep current">
                                            <small class="text-muted">Only fill if you want to change password</small>
                                        </div>
                                    </div>

                                    <hr class="my-3">

                                    <div class="row g-3">
                                        <div class="col-lg-4 col-md-6">
                                            <div class="custom-control custom-checkbox mb-2">
                                                <input type="checkbox" class="custom-control-input" id="assignRole" name="assignRole" {!! $user->assignRole ? 'checked="checked"' : '' !!}>
                                                <label class="custom-control-label font-weight-bold" for="assignRole">Assign Roles</label>
                                            </div>
                                            {!! Form::select('roles[]', $roles, $userRole, ['class' => 'form-control form-control-modern select2', 'multiple', 'style' => 'width: 100%;']) !!}
                                        </div>
                                        <div class="col-lg-4 col-md-6">
                                            <div class="custom-control custom-checkbox mb-2">
                                                <input type="checkbox" class="custom-control-input" id="assignPermission" name="assignPermission" {!! $user->assignPermission ? 'checked="checked"' : '' !!}>
                                                <label class="custom-control-label font-weight-bold" for="assignPermission">Assign Permissions</label>
                                            </div>
                                            {!! Form::select('permissions[]', $permissions, $userPermission, ['class' => 'form-control form-control-modern select2', 'multiple', 'style' => 'width: 100%;']) !!}
                                        </div>
                                    </div>

                                    <hr class="my-3">

                                    <!-- Leadership Roles -->
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label-modern d-block mb-2">
                                                <i class="mdi mdi-shield-crown-outline text-primary"></i> Leadership Roles
                                            </label>
                                            <div class="d-flex flex-wrap gap-4" style="gap: 1.5rem;">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" id="is_unit_head" name="is_unit_head" value="1" {{ ($user->staff_profile->is_unit_head ?? false) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="is_unit_head">
                                                        <span class="font-weight-bold text-info">Unit Head</span>
                                                        <small class="d-block text-muted">Leads a specific unit within a department</small>
                                                    </label>
                                                </div>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" id="is_dept_head" name="is_dept_head" value="1" {{ ($user->staff_profile->is_dept_head ?? false) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="is_dept_head">
                                                        <span class="font-weight-bold text-warning">Department Head</span>
                                                        <small class="d-block text-muted">Leads an entire department</small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="card-footer bg-white border-top py-3">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('staff.index') }}" class="btn btn-light border px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary-modern px-4">
                                <i class="mdi mdi-content-save mr-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    <script type="text/javascript">
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: 'Select options...',
                allowClear: true
            });
        });

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
