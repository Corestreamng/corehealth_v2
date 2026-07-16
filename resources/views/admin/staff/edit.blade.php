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
        .nav-tabs-modern .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 1rem 1.5rem;
            border-bottom: 3px solid transparent;
        }
        .nav-tabs-modern .nav-link.active {
            color: var(--primary-color);
            background: transparent;
            border-bottom: 3px solid var(--primary-color);
        }
        .nav-tabs-modern .nav-link:hover {
            border-color: transparent;
            color: var(--primary-color);
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
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
                {!! Form::model($user, [
                    'method' => 'PATCH',
                    'route' => ['staff.update', $user->id],
                    'enctype' => 'multipart/form-data',
                    'class' => 'form-horizontal'
                ]) !!}
                {{ csrf_field() }}
                <div class="card-body p-4 bg-light">
                    <div class="row">
                        <!-- Left Column: Image & Files -->
                        <div class="col-lg-3">
                            <div class="card-modern mb-3">
                                <div class="card-header-modern py-3">
                                    <h5 class="card-title-modern mb-0 font-weight-bold">
                                        <i class="mdi mdi-camera-outline text-primary"></i> Profile Image
                                    </h5>
                                </div>
                                <div class="card-body text-center p-3">
                                    <img src="{!! url('storage/image/user/' . $user->filename) !!}" id="preview-img" class="preview-image mb-3 rounded-circle shadow-sm" style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #fff;">
                                    <div class="upload-zone p-3 border-dashed rounded bg-light">
                                        <input type="file" name="filename" id="filename" accept="image/*" onchange="previewImage(this)" class="d-none">
                                        <label for="filename" class="mb-0 cursor-pointer d-block w-100">
                                            <i class="mdi mdi-cloud-upload upload-icon text-primary h3 mb-1"></i>
                                            <p class="mb-0 font-weight-bold text-primary">Change Image</p>
                                            <small class="text-muted">JPG, PNG up to 2MB</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="card-modern">
                                <div class="card-header-modern py-3">
                                    <h5 class="card-title-modern mb-0 font-weight-bold">
                                        <i class="mdi mdi-file-document-outline text-primary"></i> Documents
                                    </h5>
                                </div>
                                <div class="card-body p-3">
                                    @if ($user->old_records)
                                        <div class="mb-3 p-2 bg-light rounded text-center border">
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

                        <!-- Right Column: Tabs -->
                        <div class="col-lg-9">
                            <div class="card-modern h-100">
                                <div class="card-header-modern p-0 border-bottom">
                                    <ul class="nav nav-tabs nav-tabs-modern px-3" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" data-toggle="tab" href="#tab-personal" role="tab">
                                                <i class="mdi mdi-account-details-outline mr-1"></i> Setup & Personal
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#tab-employment" role="tab">
                                                <i class="mdi mdi-briefcase-outline mr-1"></i> Employment & HR
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="tab" href="#tab-financial" role="tab">
                                                <i class="mdi mdi-bank mr-1"></i> Financial & Emergency
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-body p-4">
                                    <div class="tab-content">
                                        <!-- TAB 1: SETUP & PERSONAL -->
                                        <div class="tab-pane active" id="tab-personal" role="tabpanel">
                                            <h5 class="section-title">Required Setup</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Staff Category <span class="text-danger">*</span></label>
                                                    <select class="form-control form-control-modern" id="is_admin" name="is_admin" required>
                                                        <option value="">Select Category</option>
                                                        @foreach ($statuses as $status)
                                                            <option value="{{ $status->id }}" {{ ($status->id == $user->is_admin || $status->id == old('is_admin')) ? 'selected' : '' }}>{{ $status->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Clinic <span class="text-danger">*</span></label>
                                                    {!! Form::select('clinic', $clinics, $user->staff_profile?->clinic_id ?? old('clinic'), ['class' => 'form-control form-control-modern', 'placeholder' => 'Select Clinic', 'required' => 'true']) !!}
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Department</label>
                                                    <select class="form-control form-control-modern" name="department_id" id="department_id">
                                                        <option value="">Select Department</option>
                                                        @foreach($departments as $department)
                                                            <option value="{{ $department->id }}" {{ ($user->staff_profile?->department_id ?? old('department_id')) == $department->id ? 'selected' : '' }}>
                                                                {{ $department->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Specialization <small class="text-muted">(Doctors)</small></label>
                                                    {!! Form::select('specialization', $specializations, $user->staff_profile?->specialization_id ?? old('specialization'), ['class' => 'form-control form-control-modern', 'placeholder' => 'Select Specialization']) !!}
                                                </div>
                                                <div class="col-lg-8 col-md-12">
                                                    <label class="form-label-modern">Also See Queues From <small class="text-muted">(Doctors)</small></label>
                                                    <select name="can_see_clinic_queues[]" class="form-control form-control-modern select2" multiple="multiple" data-placeholder="Select additional clinics...">
                                                        @php $selectedClinics = $user->staff_profile?->can_see_clinic_queues ?? []; @endphp
                                                        @foreach($clinics as $clinicId => $clinicName)
                                                            <option value="{{ $clinicId }}" {{ in_array($clinicId, old('can_see_clinic_queues', $selectedClinics)) ? 'selected' : '' }}>{{ $clinicName }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <h5 class="section-title">Personal Information</h5>
                                            <div class="row g-3 mb-4">
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
                                                        <option value="Male" {{ ($user->staff_profile?->gender ?? old('gender')) == 'Male' ? 'selected' : '' }}>Male</option>
                                                        <option value="Female" {{ ($user->staff_profile?->gender ?? old('gender')) == 'Female' ? 'selected' : '' }}>Female</option>
                                                        <option value="Others" {{ ($user->staff_profile?->gender ?? old('gender')) == 'Others' ? 'selected' : '' }}>Others</option>
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Date of Birth <span class="text-danger">*</span></label>
                                                    <input type="date" class="form-control form-control-modern" name="dob" value="{{ $user->staff_profile?->date_of_birth ? $user->staff_profile->date_of_birth->format('Y-m-d') : old('dob') }}" required>
                                                </div>
                                            </div>

                                            <h5 class="section-title">Contact & Access</h5>
                                            <div class="row g-3">
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Email Address <span class="text-danger">*</span></label>
                                                    <input type="email" class="form-control form-control-modern" name="email" value="{{ $user->email ?? old('email') }}" required>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Phone Number <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control form-control-modern" name="phone_number" value="{{ $user->staff_profile?->phone_number ?? old('phone_number') }}" required>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Password</label>
                                                    <input type="password" class="form-control form-control-modern" name="password" placeholder="Leave blank to keep current">
                                                    <small class="text-muted">Only fill if you want to change password</small>
                                                </div>
                                                <div class="col-lg-12">
                                                    <label class="form-label-modern">Residential Address</label>
                                                    <textarea class="form-control form-control-modern" name="address" rows="2">{{ $user->staff_profile?->home_address ?? old('address') }}</textarea>
                                                </div>
                                                <div class="col-lg-6 col-md-6">
                                                    <label class="form-label-modern">Roles</label>
                                                    {!! Form::select('roles[]', $roles, $userRole, ['class' => 'form-control form-control-modern select2', 'multiple', 'style' => 'width: 100%;']) !!}
                                                </div>
                                                <div class="col-lg-6 col-md-6">
                                                    <label class="form-label-modern">Permissions</label>
                                                    {!! Form::select('permissions[]', $permissions, $userPermission, ['class' => 'form-control form-control-modern select2', 'multiple', 'style' => 'width: 100%;']) !!}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- TAB 2: EMPLOYMENT & HR -->
                                        <div class="tab-pane" id="tab-employment" role="tabpanel">
                                            <h5 class="section-title">Job Details</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Employee ID</label>
                                                    <input type="text" class="form-control form-control-modern" name="employee_id" value="{{ $user->staff_profile?->employee_id ?? old('employee_id') }}" placeholder="e.g. EMP001">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Job Title</label>
                                                    <input type="text" class="form-control form-control-modern" name="job_title" value="{{ $user->staff_profile?->job_title ?? old('job_title') }}" placeholder="e.g. Senior Nurse">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Unit</label>
                                                    <select class="form-control form-control-modern" name="unit_id" id="unit_id">
                                                        <option value="">Select Unit</option>
                                                        @foreach($units as $unit)
                                                            <option value="{{ $unit->id }}" {{ ($user->staff_profile?->unit_id ?? old('unit_id')) == $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Employment Type <span class="text-danger">*</span></label>
                                                    <select class="form-control form-control-modern" name="employment_type" required>
                                                        <option value="full_time" {{ ($user->staff_profile?->employment_type ?? old('employment_type', 'full_time')) == 'full_time' ? 'selected' : '' }}>Full Time</option>
                                                        <option value="part_time" {{ ($user->staff_profile?->employment_type ?? old('employment_type')) == 'part_time' ? 'selected' : '' }}>Part Time</option>
                                                        <option value="contract" {{ ($user->staff_profile?->employment_type ?? old('employment_type')) == 'contract' ? 'selected' : '' }}>Contract</option>
                                                        <option value="intern" {{ ($user->staff_profile?->employment_type ?? old('employment_type')) == 'intern' ? 'selected' : '' }}>Intern</option>
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Employment Status <span class="text-danger">*</span></label>
                                                    <select class="form-control form-control-modern" name="employment_status" required>
                                                        <option value="active" {{ ($user->staff_profile?->employment_status ?? old('employment_status', 'active')) == 'active' ? 'selected' : '' }}>Active</option>
                                                        <option value="suspended" {{ ($user->staff_profile?->employment_status ?? old('employment_status')) == 'suspended' ? 'selected' : '' }}>Suspended</option>
                                                        <option value="resigned" {{ ($user->staff_profile?->employment_status ?? old('employment_status')) == 'resigned' ? 'selected' : '' }}>Resigned</option>
                                                        <option value="terminated" {{ ($user->staff_profile?->employment_status ?? old('employment_status')) == 'terminated' ? 'selected' : '' }}>Terminated</option>
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Date Hired</label>
                                                    <input type="date" class="form-control form-control-modern" name="date_hired" value="{{ $user->staff_profile?->date_hired ?? old('date_hired') }}">
                                                </div>
                                            </div>

                                            <h5 class="section-title">Grading & Responsibilities</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Cadre</label>
                                                    <select class="form-control form-control-modern" name="cadre_id">
                                                        <option value="">Select Cadre</option>
                                                        @foreach($cadres as $cadre)
                                                            <option value="{{ $cadre->id }}" {{ ($user->staff_profile?->cadre_id ?? old('cadre_id')) == $cadre->id ? 'selected' : '' }}>{{ $cadre->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Current Grade Level</label>
                                                    <select class="form-control form-control-modern" name="grade_level_id">
                                                        <option value="">Select Grade Level</option>
                                                        @foreach($gradeLevels as $gl)
                                                            <option value="{{ $gl->id }}" {{ ($user->staff_profile?->grade_level_id ?? old('grade_level_id')) == $gl->id ? 'selected' : '' }}>{{ $gl->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Entry Grade Level</label>
                                                    <select class="form-control form-control-modern" name="entry_grade_level_id">
                                                        <option value="">Select Entry Grade</option>
                                                        @foreach($gradeLevels as $gl)
                                                            <option value="{{ $gl->id }}" {{ ($user->staff_profile?->entry_grade_level_id ?? old('entry_grade_level_id')) == $gl->id ? 'selected' : '' }}>{{ $gl->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Job Location</label>
                                                    <input type="text" class="form-control form-control-modern" name="job_location" value="{{ $user->staff_profile?->job_location ?? old('job_location') }}" placeholder="e.g. Main Campus">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Responsibility</label>
                                                    <input type="text" class="form-control form-control-modern" name="responsibility" value="{{ $user->staff_profile?->responsibility ?? old('responsibility') }}" placeholder="e.g. Ward Supervisor">
                                                </div>
                                            </div>

                                            <h5 class="section-title">Leadership Roles</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-12">
                                                    <div class="d-flex flex-wrap gap-4" style="gap: 1.5rem;">
                                                        <div class="custom-control custom-switch">
                                                            <input type="checkbox" class="custom-control-input" id="is_unit_head" name="is_unit_head" value="1" {{ ($user->staff_profile?->is_unit_head ?? old('is_unit_head')) ? 'checked' : '' }}>
                                                            <label class="custom-control-label" for="is_unit_head">
                                                                <span class="font-weight-bold text-info">Unit Head</span>
                                                                <small class="d-block text-muted">Leads a specific unit</small>
                                                            </label>
                                                        </div>
                                                        <div class="custom-control custom-switch">
                                                            <input type="checkbox" class="custom-control-input" id="is_dept_head" name="is_dept_head" value="1" {{ ($user->staff_profile?->is_dept_head ?? old('is_dept_head')) ? 'checked' : '' }}>
                                                            <label class="custom-control-label" for="is_dept_head">
                                                                <span class="font-weight-bold text-warning">Department Head</span>
                                                                <small class="d-block text-muted">Leads an entire department</small>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <h5 class="section-title">Licensing & Dates</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">License Number</label>
                                                    <input type="text" class="form-control form-control-modern" name="license_number" value="{{ $user->staff_profile?->license_number ?? old('license_number') }}" placeholder="Professional license #">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">License Expiry Date</label>
                                                    <input type="date" class="form-control form-control-modern" name="license_expiry_date" value="{{ $user->staff_profile?->license_expiry_date?->format('Y-m-d') ?? old('license_expiry_date') }}">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">National ID (NIN)</label>
                                                    <input type="text" class="form-control form-control-modern" name="national_id_number" value="{{ $user->staff_profile?->national_id_number ?? old('national_id_number') }}" placeholder="National ID number">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Date Confirmed</label>
                                                    <input type="date" class="form-control form-control-modern" name="date_confirmed" value="{{ $user->staff_profile?->date_confirmed?->format('Y-m-d') ?? old('date_confirmed') }}">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Confirmation Due Date</label>
                                                    <input type="date" class="form-control form-control-modern" name="confirmation_due_date" value="{{ $user->staff_profile?->confirmation_due_date?->format('Y-m-d') ?? old('confirmation_due_date') }}">
                                                </div>
                                            </div>

                                            <h5 class="section-title">HR Notes</h5>
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <textarea class="form-control form-control-modern" name="hr_notes" rows="2" placeholder="Internal HR notes about this staff member">{{ $user->staff_profile?->hr_notes ?? old('hr_notes') }}</textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- TAB 3: FINANCIAL & EMERGENCY -->
                                        <div class="tab-pane" id="tab-financial" role="tabpanel">
                                            <h5 class="section-title">Bank & Tax Information</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Bank Name</label>
                                                    <input type="text" class="form-control form-control-modern" name="bank_name" value="{{ $user->staff_profile?->bank_name ?? old('bank_name') }}" placeholder="e.g. First Bank">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Account Number</label>
                                                    <input type="text" class="form-control form-control-modern" name="bank_account_number" value="{{ $user->staff_profile?->bank_account_number ?? old('bank_account_number') }}" placeholder="0123456789">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Account Name</label>
                                                    <input type="text" class="form-control form-control-modern" name="bank_account_name" value="{{ $user->staff_profile?->bank_account_name ?? old('bank_account_name') }}" placeholder="Account holder name">
                                                </div>
                                                <div class="col-lg-6 col-md-6">
                                                    <label class="form-label-modern">Tax ID (TIN)</label>
                                                    <input type="text" class="form-control form-control-modern" name="tax_id" value="{{ $user->staff_profile?->tax_id ?? old('tax_id') }}" placeholder="Tax identification number">
                                                </div>
                                                <div class="col-lg-6 col-md-6">
                                                    <label class="form-label-modern">Pension ID</label>
                                                    <input type="text" class="form-control form-control-modern" name="pension_id" value="{{ $user->staff_profile?->pension_id ?? old('pension_id') }}" placeholder="Pension number">
                                                </div>
                                            </div>

                                            <h5 class="section-title">Emergency Contact</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Contact Name</label>
                                                    <input type="text" class="form-control form-control-modern" name="emergency_contact_name" value="{{ $user->staff_profile?->emergency_contact_name ?? old('emergency_contact_name') }}" placeholder="Full name">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Contact Phone</label>
                                                    <input type="text" class="form-control form-control-modern" name="emergency_contact_phone" value="{{ $user->staff_profile?->emergency_contact_phone ?? old('emergency_contact_phone') }}" placeholder="+234...">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Relationship</label>
                                                    <input type="text" class="form-control form-control-modern" name="emergency_contact_relationship" value="{{ $user->staff_profile?->emergency_contact_relationship ?? old('emergency_contact_relationship') }}" placeholder="e.g. Spouse, Parent">
                                                </div>
                                            </div>

                                            <h5 class="section-title">Extended Personal Details</h5>
                                            <div class="row g-3">
                                                <div class="col-lg-6 col-md-6">
                                                    <label class="form-label-modern">Marital Status</label>
                                                    <select class="form-control form-control-modern" name="marital_status">
                                                        <option value="">Select</option>
                                                        <option value="single" {{ ($user->staff_profile?->marital_status ?? old('marital_status')) == 'single' ? 'selected' : '' }}>Single</option>
                                                        <option value="married" {{ ($user->staff_profile?->marital_status ?? old('marital_status')) == 'married' ? 'selected' : '' }}>Married</option>
                                                        <option value="divorced" {{ ($user->staff_profile?->marital_status ?? old('marital_status')) == 'divorced' ? 'selected' : '' }}>Divorced</option>
                                                        <option value="widowed" {{ ($user->staff_profile?->marital_status ?? old('marital_status')) == 'widowed' ? 'selected' : '' }}>Widowed</option>
                                                    </select>
                                                </div>
                                                <div class="col-lg-6 col-md-6">
                                                    <label class="form-label-modern">Number of Children</label>
                                                    <input type="number" class="form-control form-control-modern" name="number_of_children" value="{{ $user->staff_profile?->number_of_children ?? old('number_of_children', 0) }}" min="0">
                                                </div>
                                                <div class="col-lg-6 col-md-12">
                                                    <label class="form-label-modern">Permanent Home Address</label>
                                                    <textarea class="form-control form-control-modern" name="permanent_home_address" rows="2" placeholder="Hometown / permanent address">{{ $user->staff_profile?->permanent_home_address ?? old('permanent_home_address') }}</textarea>
                                                </div>
                                                <div class="col-lg-6 col-md-12">
                                                    <label class="form-label-modern">Other Talents / Skills</label>
                                                    <textarea class="form-control form-control-modern" name="other_talents" rows="2" placeholder="Additional skills, talents, certifications">{{ $user->staff_profile?->other_talents ?? old('other_talents') }}</textarea>
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
            var myimg = document.getElementById("preview-img");
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
