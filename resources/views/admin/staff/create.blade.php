@extends('admin.layouts.app')
@section('title', 'Create Staff')
@section('page_name', 'Staff')
@section('subpage_name', 'Create Staff')
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
                        <h2 class="mb-1 font-weight-bold text-dark">Create New Staff</h2>
                        <p class="text-muted mb-0">Add a new staff member to the system</p>
                    </div>
                </div>
                {!! Form::open(['method' => 'POST', 'route' => 'staff.store', 'class' => 'form-horizontal', 'enctype' => 'multipart/form-data']) !!}
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
                                    <img src="{{ asset('img/avatar-placeholder.png') }}" id="preview-img" class="preview-image mb-3 rounded-circle shadow-sm" style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #fff;">
                                    <div class="upload-zone p-3 border-dashed rounded bg-light">
                                        <input type="file" name="filename" id="filename" accept="image/*" onchange="previewImage(this)" class="d-none">
                                        <label for="filename" class="mb-0 cursor-pointer d-block w-100">
                                            <i class="mdi mdi-cloud-upload upload-icon text-primary h3 mb-1"></i>
                                            <p class="mb-0 font-weight-bold text-primary">Click to upload</p>
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
                                    <label class="form-label-modern">Old Records</label>
                                    <input type="file" class="form-control form-control-modern" id="old_records" name="old_records" style="height: auto; padding: 0.5rem;">
                                    <small class="text-muted mt-2 d-block">Upload historical records.</small>
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
                                                    {!! Form::select('is_admin', $statuses, old('is_admin'), ['class' => 'form-control form-control-modern', 'placeholder' => 'Select Category', 'required' => 'true']) !!}
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Clinic <span class="text-danger">*</span></label>
                                                    {!! Form::select('clinic', $clinics, old('clinic'), ['class' => 'form-control form-control-modern', 'placeholder' => 'Select Clinic', 'required' => 'true']) !!}
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Department</label>
                                                    <select class="form-control form-control-modern" name="department_id" id="department_id">
                                                        <option value="">Select Department</option>
                                                        @foreach($departments as $department)
                                                            <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>
                                                                {{ $department->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Specialization <small class="text-muted">(Doctors)</small></label>
                                                    {!! Form::select('specialization', $specializations, old('specialization'), ['class' => 'form-control form-control-modern', 'placeholder' => 'Select Specialization']) !!}
                                                </div>
                                                <div class="col-lg-8 col-md-12">
                                                    <label class="form-label-modern">Also See Queues From <small class="text-muted">(Doctors)</small></label>
                                                    <select name="can_see_clinic_queues[]" class="form-control form-control-modern select2" multiple="multiple" data-placeholder="Select additional clinics...">
                                                        @foreach($clinics as $clinicId => $clinicName)
                                                            <option value="{{ $clinicId }}" {{ in_array($clinicId, old('can_see_clinic_queues', [])) ? 'selected' : '' }}>{{ $clinicName }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <h5 class="section-title">Personal Information</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Surname <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control form-control-modern" name="surname" value="{{ old('surname') }}" placeholder="e.g. Doe" required>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Firstname <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control form-control-modern" name="firstname" value="{{ old('firstname') }}" placeholder="e.g. John" required>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Othername</label>
                                                    <input type="text" class="form-control form-control-modern" name="othername" value="{{ old('othername') }}" placeholder="Middle name">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Gender <span class="text-danger">*</span></label>
                                                    <select class="form-control form-control-modern" name="gender" required>
                                                        <option value="">Select gender</option>
                                                        <option value="Male" {{(old('gender') == 'Male') ? 'selected': ''}}>Male</option>
                                                        <option value="Female" {{(old('gender') == 'Female') ? 'selected': ''}}>Female</option>
                                                        <option value="Others" {{(old('gender') == 'Others') ? 'selected': ''}}>Others</option>
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Date of Birth <span class="text-danger">*</span></label>
                                                    <input type="date" class="form-control form-control-modern" name="dob" value="{{old('dob')}}" required>
                                                </div>
                                            </div>

                                            <h5 class="section-title">Contact & Access</h5>
                                            <div class="row g-3">
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Email Address <span class="text-danger">*</span></label>
                                                    <input type="email" class="form-control form-control-modern" name="email" value="{{ old('email') }}" placeholder="email@example.com" required>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Phone Number <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control form-control-modern" name="phone_number" value="{{ old('phone_number') }}" placeholder="+234..." required>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Password <span class="text-danger">*</span></label>
                                                    <input type="password" class="form-control form-control-modern" name="password" value="123456" required>
                                                    <small class="text-muted">Default: 123456</small>
                                                </div>
                                                <div class="col-lg-12">
                                                    <label class="form-label-modern">Residential Address</label>
                                                    <textarea class="form-control form-control-modern" name="address" rows="2" placeholder="Enter full address">{{ old('address') }}</textarea>
                                                </div>
                                                <div class="col-lg-6 col-md-6">
                                                    <label class="form-label-modern">Roles</label>
                                                    {!! Form::select('roles[]', $roles, [], ['class' => 'form-control form-control-modern select2', 'multiple', 'style' => 'width: 100%;']) !!}
                                                </div>
                                                <div class="col-lg-6 col-md-6">
                                                    <label class="form-label-modern">Permissions</label>
                                                    {!! Form::select('permissions[]', $permissions, [], ['class' => 'form-control form-control-modern select2', 'multiple', 'style' => 'width: 100%;']) !!}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- TAB 2: EMPLOYMENT & HR -->
                                        <div class="tab-pane" id="tab-employment" role="tabpanel">
                                            <h5 class="section-title">Job Details</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Employee ID</label>
                                                    <input type="text" class="form-control form-control-modern" name="employee_id" value="{{ old('employee_id') }}" placeholder="e.g. EMP001">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Job Title</label>
                                                    <input type="text" class="form-control form-control-modern" name="job_title" value="{{ old('job_title') }}" placeholder="e.g. Senior Nurse">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Unit</label>
                                                    <select class="form-control form-control-modern" name="unit_id" id="unit_id">
                                                        <option value="">Select Unit</option>
                                                        @foreach($units as $unit)
                                                            <option value="{{ $unit->id }}" {{ old('unit_id') == $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Employment Type <span class="text-danger">*</span></label>
                                                    <select class="form-control form-control-modern" name="employment_type" required>
                                                        <option value="full_time" {{ old('employment_type', 'full_time') == 'full_time' ? 'selected' : '' }}>Full Time</option>
                                                        <option value="part_time" {{ old('employment_type') == 'part_time' ? 'selected' : '' }}>Part Time</option>
                                                        <option value="contract" {{ old('employment_type') == 'contract' ? 'selected' : '' }}>Contract</option>
                                                        <option value="intern" {{ old('employment_type') == 'intern' ? 'selected' : '' }}>Intern</option>
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Employment Status <span class="text-danger">*</span></label>
                                                    <select class="form-control form-control-modern" name="employment_status" required>
                                                        <option value="active" {{ old('employment_status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                                                        <option value="suspended" {{ old('employment_status') == 'suspended' ? 'selected' : '' }}>Suspended</option>
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Date Hired</label>
                                                    <input type="date" class="form-control form-control-modern" name="date_hired" value="{{ old('date_hired') }}">
                                                </div>
                                            </div>

                                            <h5 class="section-title">Grading & Responsibilities</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Cadre</label>
                                                    <select class="form-control form-control-modern" name="cadre_id">
                                                        <option value="">Select Cadre</option>
                                                        @foreach($cadres as $cadre)
                                                            <option value="{{ $cadre->id }}" {{ old('cadre_id') == $cadre->id ? 'selected' : '' }}>{{ $cadre->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Current Grade Level</label>
                                                    <select class="form-control form-control-modern" name="grade_level_id">
                                                        <option value="">Select Grade Level</option>
                                                        @foreach($gradeLevels as $gl)
                                                            <option value="{{ $gl->id }}" {{ old('grade_level_id') == $gl->id ? 'selected' : '' }}>{{ $gl->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Entry Grade Level</label>
                                                    <select class="form-control form-control-modern" name="entry_grade_level_id">
                                                        <option value="">Select Entry Grade</option>
                                                        @foreach($gradeLevels as $gl)
                                                            <option value="{{ $gl->id }}" {{ old('entry_grade_level_id') == $gl->id ? 'selected' : '' }}>{{ $gl->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Job Location</label>
                                                    <input type="text" class="form-control form-control-modern" name="job_location" value="{{ old('job_location') }}" placeholder="e.g. Main Campus">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Responsibility</label>
                                                    <input type="text" class="form-control form-control-modern" name="responsibility" value="{{ old('responsibility') }}" placeholder="e.g. Ward Supervisor">
                                                </div>
                                            </div>

                                            <h5 class="section-title">Leadership Roles</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-12">
                                                    <div class="d-flex flex-wrap gap-4" style="gap: 1.5rem;">
                                                        <div class="custom-control custom-switch">
                                                            <input type="checkbox" class="custom-control-input" id="is_unit_head" name="is_unit_head" value="1" {{ old('is_unit_head') ? 'checked' : '' }}>
                                                            <label class="custom-control-label" for="is_unit_head">
                                                                <span class="font-weight-bold text-info">Unit Head</span>
                                                                <small class="d-block text-muted">Leads a specific unit</small>
                                                            </label>
                                                        </div>
                                                        <div class="custom-control custom-switch">
                                                            <input type="checkbox" class="custom-control-input" id="is_dept_head" name="is_dept_head" value="1" {{ old('is_dept_head') ? 'checked' : '' }}>
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
                                                    <input type="text" class="form-control form-control-modern" name="license_number" value="{{ old('license_number') }}" placeholder="Professional license #">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">License Expiry Date</label>
                                                    <input type="date" class="form-control form-control-modern" name="license_expiry_date" value="{{ old('license_expiry_date') }}">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">National ID (NIN)</label>
                                                    <input type="text" class="form-control form-control-modern" name="national_id_number" value="{{ old('national_id_number') }}" placeholder="National ID number">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Date Confirmed</label>
                                                    <input type="date" class="form-control form-control-modern" name="date_confirmed" value="{{ old('date_confirmed') }}">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Confirmation Due Date</label>
                                                    <input type="date" class="form-control form-control-modern" name="confirmation_due_date" value="{{ old('confirmation_due_date') }}">
                                                </div>
                                            </div>

                                            <h5 class="section-title">HR Notes</h5>
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <textarea class="form-control form-control-modern" name="hr_notes" rows="2" placeholder="Internal HR notes about this staff member">{{ old('hr_notes') }}</textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- TAB 3: FINANCIAL & EMERGENCY -->
                                        <div class="tab-pane" id="tab-financial" role="tabpanel">
                                            <h5 class="section-title">Bank & Tax Information</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Bank Name</label>
                                                    <input type="text" class="form-control form-control-modern" name="bank_name" value="{{ old('bank_name') }}" placeholder="e.g. First Bank">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Account Number</label>
                                                    <input type="text" class="form-control form-control-modern" name="bank_account_number" value="{{ old('bank_account_number') }}" placeholder="0123456789">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Account Name</label>
                                                    <input type="text" class="form-control form-control-modern" name="bank_account_name" value="{{ old('bank_account_name') }}" placeholder="Account holder name">
                                                </div>
                                                <div class="col-lg-6 col-md-6">
                                                    <label class="form-label-modern">Tax ID (TIN)</label>
                                                    <input type="text" class="form-control form-control-modern" name="tax_id" value="{{ old('tax_id') }}" placeholder="Tax identification number">
                                                </div>
                                                <div class="col-lg-6 col-md-6">
                                                    <label class="form-label-modern">Pension ID</label>
                                                    <input type="text" class="form-control form-control-modern" name="pension_id" value="{{ old('pension_id') }}" placeholder="Pension number">
                                                </div>
                                            </div>

                                            <h5 class="section-title">Emergency Contact</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Contact Name</label>
                                                    <input type="text" class="form-control form-control-modern" name="emergency_contact_name" value="{{ old('emergency_contact_name') }}" placeholder="Full name">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Contact Phone</label>
                                                    <input type="text" class="form-control form-control-modern" name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}" placeholder="+234...">
                                                </div>
                                                <div class="col-lg-4 col-md-6">
                                                    <label class="form-label-modern">Relationship</label>
                                                    <input type="text" class="form-control form-control-modern" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship') }}" placeholder="e.g. Spouse, Parent">
                                                </div>
                                            </div>

                                            <h5 class="section-title">Extended Personal Details</h5>
                                            <div class="row g-3">
                                                <div class="col-lg-6 col-md-6">
                                                    <label class="form-label-modern">Marital Status</label>
                                                    <select class="form-control form-control-modern" name="marital_status">
                                                        <option value="">Select</option>
                                                        <option value="single" {{ old('marital_status') == 'single' ? 'selected' : '' }}>Single</option>
                                                        <option value="married" {{ old('marital_status') == 'married' ? 'selected' : '' }}>Married</option>
                                                        <option value="divorced" {{ old('marital_status') == 'divorced' ? 'selected' : '' }}>Divorced</option>
                                                        <option value="widowed" {{ old('marital_status') == 'widowed' ? 'selected' : '' }}>Widowed</option>
                                                    </select>
                                                </div>
                                                <div class="col-lg-6 col-md-6">
                                                    <label class="form-label-modern">Number of Children</label>
                                                    <input type="number" class="form-control form-control-modern" name="number_of_children" value="{{ old('number_of_children') }}" min="0">
                                                </div>
                                                <div class="col-lg-6 col-md-12">
                                                    <label class="form-label-modern">Permanent Home Address</label>
                                                    <textarea class="form-control form-control-modern" name="permanent_home_address" rows="2" placeholder="Hometown / permanent address">{{ old('permanent_home_address') }}</textarea>
                                                </div>
                                                <div class="col-lg-6 col-md-12">
                                                    <label class="form-label-modern">Other Talents / Skills</label>
                                                    <textarea class="form-control form-control-modern" name="other_talents" rows="2" placeholder="Additional skills, talents, certifications">{{ old('other_talents') }}</textarea>
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
                            <i class="mdi mdi-check mr-1"></i> Create Staff
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
