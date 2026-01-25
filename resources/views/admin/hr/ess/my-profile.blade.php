@extends('admin.layouts.app')

@section('title', 'My Profile')

@section('style')
<link href="{{ asset('plugins/select2/select2.min.css') }}" rel="stylesheet">
<link href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}" rel="stylesheet">
@php
    $primaryColor = appsettings()->hos_color ?? '#011b33';
@endphp
<style>
    :root {
        --primary-color: {{ $primaryColor }};
        --primary-light: {{ $primaryColor }}15;
    }
    .info-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    .info-card-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, {{ $primaryColor }}dd 100%);
        color: #fff;
        padding: 1rem 1.5rem;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .info-card-header.header-green { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
    .info-card-header.header-red { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
    .info-card-header.header-orange { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
    .info-card-header.header-purple { background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%); }
    .info-card-header.header-teal { background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%); }
    .info-card-header.header-gray { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); }
    .info-card-header i { margin-right: 0.5rem; }
    .info-card-header .edit-toggle {
        background: rgba(255,255,255,0.2);
        border: none;
        color: #fff;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .info-card-header .edit-toggle:hover { background: rgba(255,255,255,0.3); }
    .info-card-body { padding: 1.5rem; }
    .info-row {
        display: flex;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f0f0f0;
        align-items: center;
    }
    .info-row:last-child { border-bottom: none; }
    .info-label {
        font-weight: 600;
        color: #666;
        min-width: 160px;
        font-size: 0.875rem;
    }
    .info-value { color: #333; flex: 1; }
    .info-value.text-muted { color: #999 !important; font-style: italic; }
    .profile-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, {{ $primaryColor }}cc 100%);
        padding: 2rem;
        color: #fff;
        border-radius: 12px 12px 0 0;
        text-align: center;
    }
    .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid rgba(255,255,255,0.3);
        object-fit: cover;
        margin-bottom: 1rem;
        cursor: pointer;
        transition: all 0.3s;
    }
    .profile-avatar:hover { border-color: rgba(255,255,255,0.6); transform: scale(1.05); }
    .profile-avatar-placeholder {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid rgba(255,255,255,0.3);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,0.2);
        margin-bottom: 1rem;
        cursor: pointer;
        transition: all 0.3s;
    }
    .profile-avatar-placeholder:hover { border-color: rgba(255,255,255,0.6); background: rgba(255,255,255,0.3); }
    .profile-avatar-placeholder span { font-size: 2.5rem; font-weight: 700; color: #fff; }
    .photo-upload-hint { font-size: 0.75rem; opacity: 0.8; margin-top: -0.5rem; margin-bottom: 0.5rem; }
    .profile-name { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem; }
    .profile-role { font-size: 1rem; opacity: 0.9; margin-bottom: 0.5rem; }
    .badge-leadership {
        display: inline-block;
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        margin: 0.25rem;
        background: rgba(255,255,255,0.2);
    }
    .status-badge { display: inline-block; padding: 0.35rem 1rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
    .status-active { background: #d4edda; color: #155724; }
    .status-suspended { background: #f8d7da; color: #721c24; }
    .status-resigned, .status-terminated { background: #e2e3e5; color: #383d41; }
    .profile-stats {
        display: flex;
        justify-content: center;
        gap: 2rem;
        padding: 1rem;
        background: rgba(0,0,0,0.1);
        border-radius: 0 0 12px 12px;
    }
    .profile-stat { text-align: center; }
    .profile-stat h4 { font-weight: 700; margin-bottom: 0; }
    .profile-stat small { opacity: 0.8; }
    .role-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        margin: 0.25rem;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 500;
        background: #e3f2fd;
        color: #1565c0;
    }
    .form-control-edit {
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        padding: 0.5rem 0.75rem;
        width: 100%;
        transition: all 0.2s;
    }
    .form-control-edit:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px var(--primary-light);
        outline: none;
    }
    .edit-mode .info-value { display: none; }
    .edit-mode .edit-field { display: block !important; }
    .edit-field { display: none; flex: 1; }
    .btn-save-section { margin-top: 1rem; display: none; }
    .edit-mode .btn-save-section { display: block; }
    .section-notice {
        background: #f8f9fa;
        border-left: 4px solid var(--primary-color);
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        border-radius: 0 8px 8px 0;
        font-size: 0.875rem;
        color: #666;
    }
    .section-notice.notice-warning { border-left-color: #ffc107; background: #fffbf0; }
    .section-notice i { margin-right: 0.5rem; }
    /* Validation error styles */
    .field-error {
        color: #dc3545;
        font-size: 0.8rem;
        margin-top: 0.25rem;
        display: block;
    }
    .form-control-edit.is-invalid {
        border-color: #dc3545;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.15);
    }
    .validation-errors-container {
        background: #fff5f5;
        border: 1px solid #f8d7da;
        border-left: 4px solid #dc3545;
        border-radius: 0 8px 8px 0;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        display: none;
    }
    .validation-errors-container.show { display: block; }
    .validation-errors-container ul {
        margin: 0;
        padding-left: 1.25rem;
        color: #721c24;
        font-size: 0.875rem;
    }
    .validation-errors-container .error-title {
        font-weight: 600;
        color: #721c24;
        margin-bottom: 0.5rem;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                <i class="mdi mdi-account-circle mr-2"></i>My Profile
            </h3>
            <p class="text-muted mb-0">Manage your personal information and account settings</p>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Profile Summary -->
        <div class="col-lg-4">
            <!-- Profile Card with Photo Upload -->
            <form id="photoForm" enctype="multipart/form-data">
                @csrf
                <div class="info-card">
                    <div class="profile-header">
                        <input type="file" name="photo" id="photoInput" accept="image/*" style="display: none;">
                        @if($staff && $staff->photo)
                            <img src="{{ asset('storage/' . $staff->photo) }}" class="profile-avatar"
                                 alt="Profile" id="profileAvatar" onclick="document.getElementById('photoInput').click()">
                        @elseif($user->filename && $user->filename != 'default.png')
                            <img src="{{ asset('storage/image/user/' . $user->filename) }}" class="profile-avatar"
                                 alt="Profile" id="profileAvatar" onclick="document.getElementById('photoInput').click()">
                        @else
                            <div class="profile-avatar-placeholder" onclick="document.getElementById('photoInput').click()">
                                <span>{{ strtoupper(substr($user->firstname ?? 'U', 0, 1) . substr($user->surname ?? 'N', 0, 1)) }}</span>
                            </div>
                        @endif
                        <p class="photo-upload-hint"><i class="mdi mdi-camera"></i> Click to change photo</p>

                        <h3 class="profile-name">{{ $user->firstname ?? '' }} {{ $user->surname ?? '' }}</h3>
                        <p class="profile-role">{{ $staff->job_title ?? $user->category->name ?? 'Staff' }}</p>

                        @if($staff && $staff->is_dept_head)
                            <span class="badge-leadership"><i class="mdi mdi-shield-crown"></i> Department Head</span>
                        @endif
                        @if($staff && $staff->is_unit_head)
                            <span class="badge-leadership"><i class="mdi mdi-shield-account"></i> Unit Head</span>
                        @endif

                        <div class="mt-2">
                            @php $empStatus = $staff->employment_status ?? 'active'; @endphp
                            <span class="status-badge status-{{ $empStatus }}">
                                <i class="mdi mdi-{{ $empStatus === 'active' ? 'check-circle' : 'alert-circle' }} mr-1"></i>
                                {{ ucfirst($empStatus) }}
                            </span>
                        </div>
                    </div>
                    <div class="profile-stats text-white" style="background: {{ $primaryColor }};">
                        <div class="profile-stat">
                            <h4>{{ $staff && $staff->date_hired ? \Carbon\Carbon::parse($staff->date_hired)->diffInYears(now()) : 0 }}</h4>
                            <small>Years</small>
                        </div>
                        <div class="profile-stat">
                            <h4>{{ $leaveBalanceTotal ?? 0 }}</h4>
                            <small>Leave Days</small>
                        </div>
                        <div class="profile-stat">
                            <h4>{{ $totalPayslips ?? 0 }}</h4>
                            <small>Payslips</small>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Employment Info Card (Read-only) -->
            <div class="info-card">
                <div class="info-card-header">
                    <span><i class="mdi mdi-briefcase-outline"></i> Employment Info</span>
                    <small class="text-white-50">HR Managed</small>
                </div>
                <div class="info-card-body">
                    <div class="info-row">
                        <span class="info-label">Employee ID</span>
                        <span class="info-value">{{ $staff->employee_id ?? 'Not assigned' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Department</span>
                        <span class="info-value">{{ $staff->department->name ?? 'Not assigned' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Job Title</span>
                        <span class="info-value {{ !($staff->job_title ?? null) ? 'text-muted' : '' }}">{{ $staff->job_title ?? 'Not set' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Employment Type</span>
                        <span class="info-value">{{ ucfirst(str_replace('_', ' ', $staff->employment_type ?? 'full_time')) }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date Hired</span>
                        <span class="info-value">{{ $staff && $staff->date_hired ? \Carbon\Carbon::parse($staff->date_hired)->format('M d, Y') : 'Not set' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Staff Category</span>
                        <span class="info-value">{{ $user->category->name ?? 'Not set' }}</span>
                    </div>
                </div>
            </div>

            <!-- Roles Card -->
            @if($user->getRoleNames()->count() > 0)
            <div class="info-card">
                <div class="info-card-header header-purple">
                    <span><i class="mdi mdi-shield-key-outline"></i> System Roles</span>
                </div>
                <div class="info-card-body">
                    @foreach($user->getRoleNames() as $role)
                        <span class="role-badge">{{ $role }}</span>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Supervisors Card -->
            <div class="info-card">
                <div class="info-card-header header-teal">
                    <span><i class="mdi mdi-account-supervisor-outline"></i> My Supervisors</span>
                </div>
                <div class="info-card-body">
                    @if(isset($supervisors) && $supervisors->count() > 0)
                        @foreach($supervisors as $supervisor)
                            <div class="d-flex align-items-center mb-2 {{ !$loop->last ? 'pb-2 border-bottom' : '' }}">
                                <div class="mr-3">
                                    @if($supervisor->user && $supervisor->user->filename && $supervisor->user->filename != 'default.png')
                                        <img src="{{ asset('storage/image/user/' . $supervisor->user->filename) }}"
                                             class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                    @else
                                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                                             style="width: 40px; height: 40px; background: var(--primary-light); color: var(--primary-color); font-weight: 600;">
                                            {{ strtoupper(substr($supervisor->user->firstname ?? 'U', 0, 1)) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-grow-1">
                                    <div class="font-weight-bold">{{ $supervisor->user->firstname ?? '' }} {{ $supervisor->user->surname ?? '' }}</div>
                                    <small class="text-muted">{{ $supervisor->job_title ?? 'Supervisor' }}</small>
                                </div>
                                <div>
                                    @if($supervisor->is_dept_head)
                                        <span class="badge badge-info"><i class="mdi mdi-shield-crown mr-1"></i>Dept Head</span>
                                    @endif
                                    @if($supervisor->is_unit_head)
                                        <span class="badge badge-secondary"><i class="mdi mdi-shield-account mr-1"></i>Unit Head</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center text-muted py-3">
                            <i class="mdi mdi-account-supervisor-outline" style="font-size: 2rem; opacity: 0.5;"></i>
                            <p class="mb-0 mt-2">No supervisors assigned</p>
                            <small>Unit heads and department heads in your department will appear here.</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right Column: Editable Details -->
        <div class="col-lg-8">
            <!-- Personal Information -->
            <form id="personalForm">
                @csrf
                <div class="info-card" id="personalCard">
                    <div class="info-card-header">
                        <span><i class="mdi mdi-account-outline"></i> Personal Information</span>
                        <button type="button" class="edit-toggle" onclick="toggleEdit('personalCard')">
                            <i class="mdi mdi-pencil"></i> Edit
                        </button>
                    </div>
                    <div class="info-card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="info-row">
                                    <span class="info-label">Surname</span>
                                    <span class="info-value">{{ $user->surname ?? 'Not set' }}</span>
                                    <div class="edit-field">
                                        <input type="text" name="surname" class="form-control-edit" value="{{ $user->surname ?? '' }}" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-row">
                                    <span class="info-label">First Name</span>
                                    <span class="info-value">{{ $user->firstname ?? 'Not set' }}</span>
                                    <div class="edit-field">
                                        <input type="text" name="firstname" class="form-control-edit" value="{{ $user->firstname ?? '' }}" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-row">
                                    <span class="info-label">Other Name</span>
                                    <span class="info-value {{ !$user->othername ? 'text-muted' : '' }}">{{ $user->othername ?? 'Not set' }}</span>
                                    <div class="edit-field">
                                        <input type="text" name="othername" class="form-control-edit" value="{{ $user->othername ?? '' }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-row">
                                    <span class="info-label">Gender</span>
                                    <span class="info-value {{ !($staff->gender ?? null) ? 'text-muted' : '' }}">{{ $staff->gender ?? 'Not set' }}</span>
                                    <div class="edit-field">
                                        <select name="gender" class="form-control-edit" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male" {{ ($staff->gender ?? '') == 'Male' ? 'selected' : '' }}>Male</option>
                                            <option value="Female" {{ ($staff->gender ?? '') == 'Female' ? 'selected' : '' }}>Female</option>
                                            <option value="Others" {{ ($staff->gender ?? '') == 'Others' ? 'selected' : '' }}>Others</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-row">
                                    <span class="info-label">Date of Birth</span>
                                    <span class="info-value {{ !($staff->date_of_birth ?? null) ? 'text-muted' : '' }}">
                                        {{ $staff && $staff->date_of_birth ? \Carbon\Carbon::parse($staff->date_of_birth)->format('M d, Y') : 'Not set' }}
                                    </span>
                                    <div class="edit-field">
                                        <input type="date" name="date_of_birth" class="form-control-edit"
                                               value="{{ $staff && $staff->date_of_birth ? $staff->date_of_birth->format('Y-m-d') : '' }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-row">
                                    <span class="info-label">Email</span>
                                    <span class="info-value">{{ $user->email ?? 'Not set' }}</span>
                                    <div class="edit-field">
                                        <input type="email" class="form-control-edit bg-light" value="{{ $user->email ?? '' }}" disabled title="Email cannot be changed">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-right btn-save-section">
                            <button type="button" class="btn btn-light mr-2" onclick="cancelEdit('personalCard')">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="mdi mdi-content-save mr-1"></i>Save Changes</button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Contact Information -->
            <form id="contactForm">
                @csrf
                <div class="info-card" id="contactCard">
                    <div class="info-card-header header-teal">
                        <span><i class="mdi mdi-phone-outline"></i> Contact Information</span>
                        <button type="button" class="edit-toggle" onclick="toggleEdit('contactCard')">
                            <i class="mdi mdi-pencil"></i> Edit
                        </button>
                    </div>
                    <div class="info-card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row">
                                    <span class="info-label">Phone Number</span>
                                    <span class="info-value {{ !($staff->phone_number ?? null) ? 'text-muted' : '' }}">{{ $staff->phone_number ?? 'Not set' }}</span>
                                    <div class="edit-field">
                                        <input type="text" name="phone_number" class="form-control-edit" value="{{ $staff->phone_number ?? '' }}" placeholder="e.g., 08012345678">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-row">
                                    <span class="info-label">Home Address</span>
                                    <span class="info-value {{ !($staff->home_address ?? null) ? 'text-muted' : '' }}">{{ $staff->home_address ?? 'Not set' }}</span>
                                    <div class="edit-field">
                                        <input type="text" name="home_address" class="form-control-edit" value="{{ $staff->home_address ?? '' }}" placeholder="Enter home address">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-right btn-save-section">
                            <button type="button" class="btn btn-light mr-2" onclick="cancelEdit('contactCard')">Cancel</button>
                            <button type="submit" class="btn btn-info"><i class="mdi mdi-content-save mr-1"></i>Save Changes</button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Professional Information -->
            <form id="professionalForm">
                @csrf
                <div class="info-card" id="professionalCard">
                    <div class="info-card-header header-purple">
                        <span><i class="mdi mdi-stethoscope"></i> Professional Information</span>
                        <button type="button" class="edit-toggle" onclick="toggleEdit('professionalCard')">
                            <i class="mdi mdi-pencil"></i> Edit
                        </button>
                    </div>
                    <div class="info-card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row">
                                    <span class="info-label">Specialization</span>
                                    <span class="info-value {{ !($staff->specialization ?? null) ? 'text-muted' : '' }}">{{ $staff->specialization->name ?? 'Not applicable' }}</span>
                                    <div class="edit-field">
                                        <select name="specialization_id" class="form-control-edit select2">
                                            <option value="">Select Specialization</option>
                                            @foreach($specializations as $id => $name)
                                                <option value="{{ $id }}" {{ ($staff->specialization_id ?? '') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-row">
                                    <span class="info-label">Assigned Clinic</span>
                                    <span class="info-value {{ !($staff->clinic ?? null) ? 'text-muted' : '' }}">{{ $staff->clinic->name ?? 'Not applicable' }}</span>
                                    <div class="edit-field">
                                        <select name="clinic_id" class="form-control-edit select2">
                                            <option value="">Select Clinic</option>
                                            @foreach($clinics as $id => $name)
                                                <option value="{{ $id }}" {{ ($staff->clinic_id ?? '') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-right btn-save-section">
                            <button type="button" class="btn btn-light mr-2" onclick="cancelEdit('professionalCard')">Cancel</button>
                            <button type="submit" class="btn btn-purple" style="background: #6f42c1; border-color: #6f42c1; color: #fff;"><i class="mdi mdi-content-save mr-1"></i>Save Changes</button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Emergency Contact -->
            <form id="emergencyForm">
                @csrf
                <div class="info-card" id="emergencyCard">
                    <div class="info-card-header header-red">
                        <span><i class="mdi mdi-phone-alert"></i> Emergency Contact</span>
                        <button type="button" class="edit-toggle" onclick="toggleEdit('emergencyCard')">
                            <i class="mdi mdi-pencil"></i> Edit
                        </button>
                    </div>
                    <div class="info-card-body">
                        <div class="section-notice notice-warning">
                            <i class="mdi mdi-alert-outline"></i> This contact will be notified in case of workplace emergencies.
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="info-row">
                                    <span class="info-label">Contact Name</span>
                                    <span class="info-value {{ !($staff->emergency_contact_name ?? null) ? 'text-muted' : '' }}">{{ $staff->emergency_contact_name ?? 'Not set' }}</span>
                                    <div class="edit-field">
                                        <input type="text" name="emergency_contact_name" class="form-control-edit" value="{{ $staff->emergency_contact_name ?? '' }}" placeholder="Full name">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-row">
                                    <span class="info-label">Relationship</span>
                                    <span class="info-value {{ !($staff->emergency_contact_relationship ?? null) ? 'text-muted' : '' }}">{{ ucfirst($staff->emergency_contact_relationship ?? 'Not set') }}</span>
                                    <div class="edit-field">
                                        <select name="emergency_contact_relationship" class="form-control-edit">
                                            <option value="">Select Relationship</option>
                                            @foreach(['spouse', 'parent', 'sibling', 'child', 'friend', 'other'] as $rel)
                                                <option value="{{ $rel }}" {{ ($staff->emergency_contact_relationship ?? '') == $rel ? 'selected' : '' }}>{{ ucfirst($rel) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-row">
                                    <span class="info-label">Phone Number</span>
                                    <span class="info-value {{ !($staff->emergency_contact_phone ?? null) ? 'text-muted' : '' }}">{{ $staff->emergency_contact_phone ?? 'Not set' }}</span>
                                    <div class="edit-field">
                                        <input type="text" name="emergency_contact_phone" class="form-control-edit" value="{{ $staff->emergency_contact_phone ?? '' }}" placeholder="e.g., 08012345678">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-right btn-save-section">
                            <button type="button" class="btn btn-light mr-2" onclick="cancelEdit('emergencyCard')">Cancel</button>
                            <button type="submit" class="btn btn-danger"><i class="mdi mdi-content-save mr-1"></i>Save Changes</button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Bank Information -->
            <form id="bankForm">
                @csrf
                <div class="info-card" id="bankCard">
                    <div class="info-card-header header-green">
                        <span><i class="mdi mdi-bank"></i> Bank Information</span>
                        <button type="button" class="edit-toggle" onclick="toggleEdit('bankCard')">
                            <i class="mdi mdi-pencil"></i> Edit
                        </button>
                    </div>
                    <div class="info-card-body">
                        <div class="section-notice">
                            <i class="mdi mdi-information-outline"></i> Bank details are used for salary payments. Please ensure accuracy.
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="info-row">
                                    <span class="info-label">Bank Name</span>
                                    <span class="info-value {{ !($staff->bank_name ?? null) ? 'text-muted' : '' }}">{{ $staff->bank_name ?? 'Not set' }}</span>
                                    <div class="edit-field">
                                        <select name="bank_name" class="form-control-edit select2">
                                            <option value="">Select Bank</option>
                                            @php
                                                $banks = ['Access Bank', 'Citibank', 'Ecobank Nigeria', 'Fidelity Bank', 'First Bank of Nigeria', 'First City Monument Bank', 'Guaranty Trust Bank', 'Heritage Bank', 'Keystone Bank', 'Polaris Bank', 'Providus Bank', 'Stanbic IBTC Bank', 'Standard Chartered Bank', 'Sterling Bank', 'Suntrust Bank', 'Union Bank of Nigeria', 'United Bank for Africa', 'Unity Bank', 'Wema Bank', 'Zenith Bank'];
                                            @endphp
                                            @foreach($banks as $bank)
                                                <option value="{{ $bank }}" {{ ($staff->bank_name ?? '') == $bank ? 'selected' : '' }}>{{ $bank }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-row">
                                    <span class="info-label">Account Number</span>
                                    <span class="info-value {{ !($staff->bank_account_number ?? null) ? 'text-muted' : '' }}">
                                        @if($staff && $staff->bank_account_number)
                                            {{ substr($staff->bank_account_number, 0, 3) }}****{{ substr($staff->bank_account_number, -3) }}
                                        @else
                                            Not set
                                        @endif
                                    </span>
                                    <div class="edit-field">
                                        <input type="text" name="bank_account_number" class="form-control-edit" value="{{ $staff->bank_account_number ?? '' }}" placeholder="10-digit account number" maxlength="10">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-row">
                                    <span class="info-label">Account Name</span>
                                    <span class="info-value {{ !($staff->bank_account_name ?? null) ? 'text-muted' : '' }}">{{ $staff->bank_account_name ?? 'Not set' }}</span>
                                    <div class="edit-field">
                                        <input type="text" name="bank_account_name" class="form-control-edit" value="{{ $staff->bank_account_name ?? '' }}" placeholder="As on bank account">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-right btn-save-section">
                            <button type="button" class="btn btn-light mr-2" onclick="cancelEdit('bankCard')">Cancel</button>
                            <button type="submit" class="btn btn-success"><i class="mdi mdi-content-save mr-1"></i>Save Changes</button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Tax & Pension -->
            <form id="taxForm">
                @csrf
                <div class="info-card" id="taxCard">
                    <div class="info-card-header header-gray">
                        <span><i class="mdi mdi-file-document-outline"></i> Tax & Pension IDs</span>
                        <button type="button" class="edit-toggle" onclick="toggleEdit('taxCard')">
                            <i class="mdi mdi-pencil"></i> Edit
                        </button>
                    </div>
                    <div class="info-card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row">
                                    <span class="info-label">Tax ID (TIN)</span>
                                    <span class="info-value {{ !($staff->tax_id ?? null) ? 'text-muted' : '' }}">{{ $staff->tax_id ?? 'Not set' }}</span>
                                    <div class="edit-field">
                                        <input type="text" name="tax_id" class="form-control-edit" value="{{ $staff->tax_id ?? '' }}" placeholder="Tax identification number">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-row">
                                    <span class="info-label">Pension ID</span>
                                    <span class="info-value {{ !($staff->pension_id ?? null) ? 'text-muted' : '' }}">{{ $staff->pension_id ?? 'Not set' }}</span>
                                    <div class="edit-field">
                                        <input type="text" name="pension_id" class="form-control-edit" value="{{ $staff->pension_id ?? '' }}" placeholder="Pension identification number">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-right btn-save-section">
                            <button type="button" class="btn btn-light mr-2" onclick="cancelEdit('taxCard')">Cancel</button>
                            <button type="submit" class="btn btn-secondary"><i class="mdi mdi-content-save mr-1"></i>Save Changes</button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Change Password -->
            <div class="info-card">
                <div class="info-card-header header-orange">
                    <span><i class="mdi mdi-lock-outline"></i> Security - Change Password</span>
                </div>
                <div class="info-card-body">
                    <form id="passwordForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="info-label mb-2">Current Password</label>
                                <input type="password" name="current_password" class="form-control-edit" required placeholder="Enter current password">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="info-label mb-2">New Password</label>
                                <input type="password" name="password" class="form-control-edit" required placeholder="Min 8 characters">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="info-label mb-2">Confirm Password</label>
                                <input type="password" name="password_confirmation" class="form-control-edit" required placeholder="Confirm new password">
                            </div>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="btn btn-warning"><i class="mdi mdi-lock-reset mr-1"></i>Update Password</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Suspension Info -->
            @if($staff && ($staff->employment_status ?? '') === 'suspended' && $staff->suspended_at)
            <div class="info-card" style="border: 2px solid #f8d7da;">
                <div class="info-card-header header-red">
                    <span><i class="mdi mdi-alert-circle-outline"></i> Suspension Information</span>
                </div>
                <div class="info-card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-row">
                                <span class="info-label">Suspended On</span>
                                <span class="info-value">{{ \Carbon\Carbon::parse($staff->suspended_at)->format('F d, Y') }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-row">
                                <span class="info-label">Suspension Ends</span>
                                <span class="info-value">{{ $staff->suspension_end_date ? \Carbon\Carbon::parse($staff->suspension_end_date)->format('F d, Y') : 'Indefinite' }}</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="info-row">
                                <span class="info-label">Reason</span>
                                <span class="info-value">{{ $staff->suspension_reason ?? 'No reason provided' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });
});

function toggleEdit(cardId) {
    const card = document.getElementById(cardId);
    card.classList.add('edit-mode');
    card.querySelector('.edit-toggle').style.display = 'none';
    $(card).find('.select2').select2({ theme: 'bootstrap4', width: '100%' });
    // Clear any previous errors when entering edit mode
    clearFormErrors(card.closest('form'));
}

function cancelEdit(cardId) {
    const card = document.getElementById(cardId);
    card.classList.remove('edit-mode');
    card.querySelector('.edit-toggle').style.display = 'inline-block';
    const form = card.closest('form');
    if (form) {
        form.reset();
        clearFormErrors(form);
    }
}

// Clear validation errors from a form
function clearFormErrors(form) {
    if (!form) return;
    $(form).find('.form-control-edit').removeClass('is-invalid');
    $(form).find('.field-error').remove();
    $(form).find('.validation-errors-container').removeClass('show').html('');
}

// Display validation errors in a form
function displayFormErrors(form, errors) {
    clearFormErrors(form);

    // Get or create error container
    let errorContainer = $(form).find('.validation-errors-container');
    if (errorContainer.length === 0) {
        errorContainer = $('<div class="validation-errors-container"></div>');
        $(form).find('.info-card-body').prepend(errorContainer);
    }

    // Build error list
    let errorHtml = '<div class="error-title"><i class="mdi mdi-alert-circle"></i> Please fix the following errors:</div><ul>';

    // Process each field error
    Object.keys(errors).forEach(function(fieldName) {
        const fieldErrors = errors[fieldName];
        const errorMsg = Array.isArray(fieldErrors) ? fieldErrors[0] : fieldErrors;

        // Add to error list
        errorHtml += '<li>' + escapeHtml(errorMsg) + '</li>';

        // Find the input field and mark as invalid
        const field = $(form).find('[name="' + fieldName + '"]');
        if (field.length) {
            field.addClass('is-invalid');
            // Add inline error below the field
            const editField = field.closest('.edit-field');
            if (editField.length && !editField.find('.field-error').length) {
                editField.append('<span class="field-error">' + escapeHtml(errorMsg) + '</span>');
            }
        }
    });

    errorHtml += '</ul>';
    errorContainer.html(errorHtml).addClass('show');

    // Scroll to error container
    $('html, body').animate({
        scrollTop: errorContainer.offset().top - 100
    }, 300);
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Photo upload
$('#photoInput').on('change', function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if ($('#profileAvatar').length) {
                $('#profileAvatar').attr('src', e.target.result);
            } else {
                $('.profile-avatar-placeholder').replaceWith('<img src="' + e.target.result + '" class="profile-avatar" alt="Profile" id="profileAvatar">');
            }
        };
        reader.readAsDataURL(file);

        const formData = new FormData();
        formData.append('photo', file);
        formData.append('_token', '{{ csrf_token() }}');

        $.ajax({
            url: '{{ route("hr.ess.my-profile.update") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: { 'X-HTTP-Method-Override': 'PUT' },
            success: function(response) { if (response.success) toastr.success('Photo updated!'); },
            error: function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    Object.values(xhr.responseJSON.errors).forEach(errors => toastr.error(Array.isArray(errors) ? errors[0] : errors));
                } else {
                    toastr.error('Failed to upload photo.');
                }
            }
        });
    }
});

function submitForm(form, successMsg) {
    const $form = $(form);
    const $submitBtn = $form.find('button[type="submit"]');
    const originalBtnText = $submitBtn.html();

    // Show loading state
    $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i>Saving...');
    clearFormErrors(form);

    $.ajax({
        url: '{{ route("hr.ess.my-profile.update") }}',
        type: 'POST',
        data: new FormData(form),
        processData: false,
        contentType: false,
        headers: { 'X-HTTP-Method-Override': 'PUT' },
        success: function(response) {
            $submitBtn.prop('disabled', false).html(originalBtnText);
            if (response.success) {
                toastr.success(response.message || successMsg);
                setTimeout(() => location.reload(), 1000);
            } else {
                toastr.error(response.message || 'Update failed');
            }
        },
        error: function(xhr) {
            $submitBtn.prop('disabled', false).html(originalBtnText);
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                displayFormErrors(form, xhr.responseJSON.errors);
                toastr.error('Please correct the errors below.');
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                toastr.error(xhr.responseJSON.message);
            } else {
                toastr.error('An error occurred. Please try again.');
            }
        }
    });
}

$('#personalForm').on('submit', function(e) { e.preventDefault(); submitForm(this, 'Personal info updated!'); });
$('#contactForm').on('submit', function(e) { e.preventDefault(); submitForm(this, 'Contact info updated!'); });
$('#professionalForm').on('submit', function(e) { e.preventDefault(); submitForm(this, 'Professional info updated!'); });
$('#emergencyForm').on('submit', function(e) { e.preventDefault(); submitForm(this, 'Emergency contact updated!'); });
$('#bankForm').on('submit', function(e) { e.preventDefault(); submitForm(this, 'Bank info updated!'); });
$('#taxForm').on('submit', function(e) { e.preventDefault(); submitForm(this, 'Tax & Pension updated!'); });

$('#passwordForm').on('submit', function(e) {
    e.preventDefault();
    const form = this;
    const $submitBtn = $(form).find('button[type="submit"]');
    const originalBtnText = $submitBtn.html();

    $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i>Updating...');
    clearFormErrors(form);

    $.ajax({
        url: '{{ route("hr.ess.my-profile.password") }}',
        type: 'POST',
        data: $(form).serialize(),
        success: function(response) {
            $submitBtn.prop('disabled', false).html(originalBtnText);
            if (response.success) {
                toastr.success(response.message);
                $(form)[0].reset();
            } else {
                toastr.error(response.message);
            }
        },
        error: function(xhr) {
            $submitBtn.prop('disabled', false).html(originalBtnText);
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                displayFormErrors(form, xhr.responseJSON.errors);
                toastr.error('Please correct the errors below.');
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                toastr.error(xhr.responseJSON.message);
            } else {
                toastr.error('An error occurred. Please try again.');
            }
        }
    });
});
</script>
@endsection
