@extends('admin.layouts.app')
@section('title', 'View Staff')
@section('page_name', 'User Management')
@section('subpage_name', 'View Staff')
@section('style')
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
        }
        .info-card-header i {
            margin-right: 0.5rem;
        }
        .info-card-body {
            padding: 1.5rem;
        }
        .info-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #666;
            min-width: 160px;
            font-size: 0.875rem;
        }
        .info-value {
            color: #333;
            flex: 1;
        }
        .info-value.text-muted {
            color: #999 !important;
            font-style: italic;
        }
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, {{ $primaryColor }}cc 100%);
            padding: 2rem;
            color: #fff;
            border-radius: 12px 12px 0 0;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid rgba(255,255,255,0.3);
            object-fit: cover;
        }
        .profile-name {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .profile-category {
            font-size: 1rem;
            opacity: 0.9;
        }
        .badge-leadership {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 0.5rem;
        }
        .badge-unit-head {
            background: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
        }
        .badge-dept-head {
            background: rgba(255, 193, 7, 0.2);
            color: #d39e00;
        }
        .status-badge {
            display: inline-block;
            padding: 0.35rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-suspended {
            background: #f8d7da;
            color: #721c24;
        }
        .status-resigned, .status-terminated {
            background: #e2e3e5;
            color: #383d41;
        }
        .quick-action-btn {
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .role-badge, .permission-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            margin: 0.25rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .role-badge {
            background: #e3f2fd;
            color: #1565c0;
        }
        .permission-badge {
            background: #f3e5f5;
            color: #7b1fa2;
        }
    </style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Quick Actions Bar -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary quick-action-btn">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to List
                    </a>
                </div>
                <div>
                    <a href="{{ route('staff.edit', $user->id) }}" class="btn btn-primary quick-action-btn">
                        <i class="mdi mdi-pencil mr-1"></i> Edit Staff
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- Left Column: Profile Card -->
                <div class="col-lg-4">
                    <!-- Profile Summary Card -->
                    <div class="info-card">
                        <div class="profile-header text-center">
                            <img src="{{ url('storage/image/user/' . $user->filename) }}" class="profile-avatar mb-3" alt="Profile Photo">
                            <h3 class="profile-name">{{ $user->surname }} {{ $user->firstname }} {{ $user->othername }}</h3>
                            <p class="profile-category mb-2">{{ $user->category->name ?? 'Staff' }}</p>
                            @if($user->staff_profile)
                                @if($user->staff_profile->is_dept_head)
                                    <span class="badge-leadership badge-dept-head"><i class="mdi mdi-shield-crown"></i> Department Head</span>
                                @endif
                                @if($user->staff_profile->is_unit_head)
                                    <span class="badge-leadership badge-unit-head"><i class="mdi mdi-shield-account"></i> Unit Head</span>
                                @endif
                            @endif
                        </div>
                        <div class="info-card-body">
                            <div class="info-row">
                                <span class="info-label"><i class="mdi mdi-email-outline"></i> Email</span>
                                <span class="info-value">{{ $user->email }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="mdi mdi-phone"></i> Phone</span>
                                <span class="info-value">{{ $user->staff_profile->phone_number ?? 'Not set' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="mdi mdi-account-check"></i> Status</span>
                                <span class="info-value">
                                    @php
                                        $empStatus = $user->staff_profile->employment_status ?? 'active';
                                    @endphp
                                    <span class="status-badge status-{{ $empStatus }}">{{ ucfirst($empStatus) }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Documents Card -->
                    @if($user->old_records)
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="mdi mdi-file-document-outline"></i> Documents
                        </div>
                        <div class="info-card-body">
                            <a href="{{ url('storage/image/user/old_records/' . $user->old_records) }}" target="_blank" class="btn btn-outline-primary btn-block">
                                <i class="mdi mdi-file-pdf mr-1"></i> View Uploaded Records
                            </a>
                        </div>
                    </div>
                    @endif

                    <!-- Roles & Permissions Card -->
                    @if($user->assignRole || $user->assignPermission)
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="mdi mdi-shield-key-outline"></i> Access Control
                        </div>
                        <div class="info-card-body">
                            @if($user->assignRole && count($user->getRoleNames()) > 0)
                                <p class="font-weight-bold mb-2">Roles</p>
                                <div class="mb-3">
                                    @foreach($user->getRoleNames() as $role)
                                        <span class="role-badge">{{ $role }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if($user->assignPermission && count($user->getPermissionNames()) > 0)
                                <p class="font-weight-bold mb-2">Direct Permissions</p>
                                <div>
                                    @foreach($user->getPermissionNames() as $permission)
                                        <span class="permission-badge">{{ $permission }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Right Column: Details -->
                <div class="col-lg-8">
                    <!-- Personal Information -->
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="mdi mdi-account-outline"></i> Personal Information
                        </div>
                        <div class="info-card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Surname</span>
                                        <span class="info-value">{{ $user->surname }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">First Name</span>
                                        <span class="info-value">{{ $user->firstname }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Other Name</span>
                                        <span class="info-value {{ empty(trim($user->othername)) ? 'text-muted' : '' }}">{{ trim($user->othername) ?: 'Not set' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Gender</span>
                                        <span class="info-value {{ !($user->staff_profile->gender ?? null) ? 'text-muted' : '' }}">{{ $user->staff_profile->gender ?? 'Not set' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Date of Birth</span>
                                        <span class="info-value {{ !($user->staff_profile->date_of_birth ?? null) ? 'text-muted' : '' }}">
                                            {{ ($user->staff_profile->date_of_birth ?? null) ? $user->staff_profile->date_of_birth->format('F d, Y') : 'Not set' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Address</span>
                                        <span class="info-value {{ !($user->staff_profile->home_address ?? null) ? 'text-muted' : '' }}">{{ $user->staff_profile->home_address ?? 'Not set' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="mdi mdi-stethoscope"></i> Professional Information
                        </div>
                        <div class="info-card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Staff Category</span>
                                        <span class="info-value">{{ $user->category->name ?? 'Not set' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Specialization</span>
                                        <span class="info-value {{ !($user->staff_profile->specialization_id ?? null) ? 'text-muted' : '' }}">
                                            {{ $specializations[$user->staff_profile->specialization_id ?? ''] ?? 'Not applicable' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Clinic</span>
                                        <span class="info-value {{ !($user->staff_profile->clinic_id ?? null) ? 'text-muted' : '' }}">
                                            {{ $clinics[$user->staff_profile->clinic_id ?? ''] ?? 'Not applicable' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Consultation Fee</span>
                                        <span class="info-value">
                                            @if($user->staff_profile->consultation_fee ?? null)
                                                <strong>₦{{ number_format($user->staff_profile->consultation_fee, 2) }}</strong>
                                            @else
                                                <span class="text-muted">Not applicable</span>
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employment Details -->
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="mdi mdi-briefcase-outline"></i> Employment Details
                        </div>
                        <div class="info-card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Employee ID</span>
                                        <span class="info-value {{ !($user->staff_profile->employee_id ?? null) ? 'text-muted' : '' }}">{{ $user->staff_profile->employee_id ?? 'Not assigned' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Date Hired</span>
                                        <span class="info-value {{ !($user->staff_profile->date_hired ?? null) ? 'text-muted' : '' }}">
                                            {{ ($user->staff_profile->date_hired ?? null) ? $user->staff_profile->date_hired->format('F d, Y') : 'Not set' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Job Title</span>
                                        <span class="info-value {{ !($user->staff_profile->job_title ?? null) ? 'text-muted' : '' }}">{{ $user->staff_profile->job_title ?? 'Not set' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Department</span>
                                        <span class="info-value {{ !($user->staff_profile->department_id ?? null) ? 'text-muted' : '' }}">
                                            {{ $user->staff_profile->department->name ?? 'Not assigned' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Employment Type</span>
                                        <span class="info-value {{ !($user->staff_profile->employment_type ?? null) ? 'text-muted' : '' }}">
                                            {{ ($user->staff_profile->employment_type ?? null) ? ucfirst(str_replace('_', ' ', $user->staff_profile->employment_type)) : 'Not set' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Employment Status</span>
                                        <span class="info-value">
                                            @php
                                                $empStatus = $user->staff_profile->employment_status ?? 'active';
                                            @endphp
                                            <span class="status-badge status-{{ $empStatus }}">{{ ucfirst($empStatus) }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bank & Tax Information -->
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="mdi mdi-bank"></i> Bank & Tax Information
                        </div>
                        <div class="info-card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Bank Name</span>
                                        <span class="info-value {{ !($user->staff_profile->bank_name ?? null) ? 'text-muted' : '' }}">{{ $user->staff_profile->bank_name ?? 'Not set' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Account Number</span>
                                        <span class="info-value {{ !($user->staff_profile->bank_account_number ?? null) ? 'text-muted' : '' }}">
                                            @if($user->staff_profile->bank_account_number ?? null)
                                                {{ substr($user->staff_profile->bank_account_number, 0, 3) }}****{{ substr($user->staff_profile->bank_account_number, -3) }}
                                            @else
                                                Not set
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Account Name</span>
                                        <span class="info-value {{ !($user->staff_profile->bank_account_name ?? null) ? 'text-muted' : '' }}">{{ $user->staff_profile->bank_account_name ?? 'Not set' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Tax ID (TIN)</span>
                                        <span class="info-value {{ !($user->staff_profile->tax_id ?? null) ? 'text-muted' : '' }}">{{ $user->staff_profile->tax_id ?? 'Not set' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Pension ID</span>
                                        <span class="info-value {{ !($user->staff_profile->pension_id ?? null) ? 'text-muted' : '' }}">{{ $user->staff_profile->pension_id ?? 'Not set' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="mdi mdi-phone-alert"></i> Emergency Contact
                        </div>
                        <div class="info-card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Contact Name</span>
                                        <span class="info-value {{ !($user->staff_profile->emergency_contact_name ?? null) ? 'text-muted' : '' }}">{{ $user->staff_profile->emergency_contact_name ?? 'Not set' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Contact Phone</span>
                                        <span class="info-value {{ !($user->staff_profile->emergency_contact_phone ?? null) ? 'text-muted' : '' }}">{{ $user->staff_profile->emergency_contact_phone ?? 'Not set' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Relationship</span>
                                        <span class="info-value {{ !($user->staff_profile->emergency_contact_relationship ?? null) ? 'text-muted' : '' }}">{{ $user->staff_profile->emergency_contact_relationship ?? 'Not set' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Suspension Info (only if suspended) -->
                    @if(($user->staff_profile->employment_status ?? '') === 'suspended' && ($user->staff_profile->suspended_at ?? null))
                    <div class="info-card" style="border: 2px solid #f8d7da;">
                        <div class="info-card-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                            <i class="mdi mdi-alert-circle-outline"></i> Suspension Information
                        </div>
                        <div class="info-card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Suspended On</span>
                                        <span class="info-value">{{ $user->staff_profile->suspended_at->format('F d, Y') }}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Suspended By</span>
                                        <span class="info-value">
                                            @if($user->staff_profile->suspended_by)
                                                {{ \App\Models\User::find($user->staff_profile->suspended_by)->firstname ?? 'System' }}
                                            @else
                                                Not recorded
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">Suspension Ends</span>
                                        <span class="info-value">
                                            {{ $user->staff_profile->suspension_end_date ? $user->staff_profile->suspension_end_date->format('F d, Y') : 'Indefinite' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="info-row">
                                        <span class="info-label">Reason</span>
                                        <span class="info-value">{{ $user->staff_profile->suspension_reason ?? 'No reason provided' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // View-only page - no scripts needed
</script>
@endsection
