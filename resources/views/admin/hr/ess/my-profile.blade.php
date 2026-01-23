@extends('admin.layouts.app')

@section('title', 'ESS - My Profile')

@section('styles')
<link href="{{ asset('plugins/select2/select2.min.css') }}" rel="stylesheet">
<link href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}" rel="stylesheet">
<link href="{{ asset('plugins/bootstrap-datepicker/bootstrap-datepicker.min.css') }}" rel="stylesheet">
@endsection

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                <i class="mdi mdi-account-circle mr-2"></i>My Profile
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{ route('hr.ess.index') }}">ESS</a></li>
                    <li class="breadcrumb-item active">My Profile</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <!-- Profile Summary Card -->
        <div class="col-md-4 mb-4">
            <div class="card border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        @if($staff && $staff->photo)
                        <img src="{{ asset('storage/' . $staff->photo) }}" alt="Profile Photo"
                             class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover; border: 4px solid #e9ecef;">
                        @else
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                             style="width: 120px; height: 120px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: 4px solid #e9ecef;">
                            <span class="text-white" style="font-size: 3rem; font-weight: 700;">
                                {{ strtoupper(substr(auth()->user()->firstname ?? 'U', 0, 1) . substr(auth()->user()->surname ?? 'N', 0, 1)) }}
                            </span>
                        </div>
                        @endif
                    </div>
                    <h4 class="mb-1" style="font-weight: 700;">
                        {{ auth()->user()->firstname ?? '' }} {{ auth()->user()->surname ?? '' }}
                    </h4>
                    <p class="text-muted mb-2">{{ $staff->role_title ?? $staff->user->role ?? 'Staff' }}</p>
                    <p class="text-muted mb-3">
                        <i class="mdi mdi-domain mr-1"></i>{{ $staff->department ?? 'N/A' }}
                    </p>

                    <div class="d-flex justify-content-center">
                        <span class="badge {{ $staff && $staff->status == 'active' ? 'badge-success' : 'badge-secondary' }} px-3 py-2"
                              style="border-radius: 20px;">
                            <i class="mdi mdi-check-circle mr-1"></i>{{ ucfirst($staff->status ?? 'Active') }}
                        </span>
                    </div>
                </div>
                <div class="card-footer bg-light" style="border-radius: 0 0 12px 12px;">
                    <div class="row text-center">
                        <div class="col-4">
                            <h5 class="mb-0" style="font-weight: 700;">{{ $staff->years_of_service ?? 0 }}</h5>
                            <small class="text-muted">Years</small>
                        </div>
                        <div class="col-4">
                            <h5 class="mb-0" style="font-weight: 700;">{{ $leaveBalanceTotal ?? 0 }}</h5>
                            <small class="text-muted">Leave Days</small>
                        </div>
                        <div class="col-4">
                            <h5 class="mb-0" style="font-weight: 700;">{{ $totalPayslips ?? 0 }}</h5>
                            <small class="text-muted">Payslips</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Info Card -->
            <div class="card border-0 mt-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-information text-primary mr-2"></i>Employment Info
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted">Staff ID</td>
                            <td class="text-right"><strong>{{ $staff->staff_id ?? 'N/A' }}</strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Employment Type</td>
                            <td class="text-right">{{ ucfirst($staff->employment_type ?? 'Full-time') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Date Joined</td>
                            <td class="text-right">{{ $staff->date_joined ? \Carbon\Carbon::parse($staff->date_joined)->format('M d, Y') : 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Supervisor</td>
                            <td class="text-right">{{ $staff->supervisor->user->firstname ?? 'N/A' }} {{ $staff->supervisor->user->surname ?? '' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Profile Details -->
        <div class="col-md-8">
            <div class="card border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#personalInfo">
                                <i class="mdi mdi-account mr-1"></i>Personal Info
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#contactInfo">
                                <i class="mdi mdi-phone mr-1"></i>Contact
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#bankInfo">
                                <i class="mdi mdi-bank mr-1"></i>Bank Details
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#emergencyContact">
                                <i class="mdi mdi-heart-pulse mr-1"></i>Emergency
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <form id="profileForm" enctype="multipart/form-data">
                        @csrf
                        <div class="tab-content">
                            <!-- Personal Information -->
                            <div class="tab-pane fade show active" id="personalInfo">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">First Name</label>
                                        <input type="text" class="form-control" value="{{ auth()->user()->firstname ?? '' }}" readonly
                                               style="border-radius: 8px; background-color: #f8f9fa;">
                                        <small class="text-muted">Contact HR to update</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Last Name</label>
                                        <input type="text" class="form-control" value="{{ auth()->user()->surname ?? '' }}" readonly
                                               style="border-radius: 8px; background-color: #f8f9fa;">
                                        <small class="text-muted">Contact HR to update</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Email</label>
                                        <input type="email" class="form-control" value="{{ auth()->user()->email ?? '' }}" readonly
                                               style="border-radius: 8px; background-color: #f8f9fa;">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Date of Birth</label>
                                        <input type="text" name="date_of_birth" class="form-control datepicker"
                                               value="{{ $staff->date_of_birth ? \Carbon\Carbon::parse($staff->date_of_birth)->format('Y-m-d') : '' }}"
                                               placeholder="YYYY-MM-DD" style="border-radius: 8px;">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Gender</label>
                                        <select name="gender" class="form-control" style="border-radius: 8px;">
                                            <option value="">Select Gender</option>
                                            <option value="male" {{ ($staff->gender ?? '') == 'male' ? 'selected' : '' }}>Male</option>
                                            <option value="female" {{ ($staff->gender ?? '') == 'female' ? 'selected' : '' }}>Female</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Marital Status</label>
                                        <select name="marital_status" class="form-control" style="border-radius: 8px;">
                                            <option value="">Select Status</option>
                                            <option value="single" {{ ($staff->marital_status ?? '') == 'single' ? 'selected' : '' }}>Single</option>
                                            <option value="married" {{ ($staff->marital_status ?? '') == 'married' ? 'selected' : '' }}>Married</option>
                                            <option value="divorced" {{ ($staff->marital_status ?? '') == 'divorced' ? 'selected' : '' }}>Divorced</option>
                                            <option value="widowed" {{ ($staff->marital_status ?? '') == 'widowed' ? 'selected' : '' }}>Widowed</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="font-weight-bold">Profile Photo</label>
                                        <input type="file" name="photo" class="form-control" accept="image/*" style="border-radius: 8px;">
                                        <small class="text-muted">Upload JPG, PNG (max 2MB)</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <div class="tab-pane fade" id="contactInfo">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Phone Number</label>
                                        <input type="text" name="phone" class="form-control"
                                               value="{{ $staff->phone ?? auth()->user()->phone ?? '' }}"
                                               placeholder="e.g., 08012345678" style="border-radius: 8px;">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Alternative Phone</label>
                                        <input type="text" name="alt_phone" class="form-control"
                                               value="{{ $staff->alt_phone ?? '' }}"
                                               placeholder="e.g., 08012345678" style="border-radius: 8px;">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="font-weight-bold">Residential Address</label>
                                        <textarea name="address" class="form-control" rows="3"
                                                  placeholder="Enter your full residential address"
                                                  style="border-radius: 8px;">{{ $staff->address ?? '' }}</textarea>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">City</label>
                                        <input type="text" name="city" class="form-control"
                                               value="{{ $staff->city ?? '' }}" placeholder="City" style="border-radius: 8px;">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">State</label>
                                        <input type="text" name="state" class="form-control"
                                               value="{{ $staff->state ?? '' }}" placeholder="State" style="border-radius: 8px;">
                                    </div>
                                </div>
                            </div>

                            <!-- Bank Information -->
                            <div class="tab-pane fade" id="bankInfo">
                                <div class="alert alert-info" style="border-radius: 8px;">
                                    <i class="mdi mdi-information mr-2"></i>
                                    Bank details are used for salary payments. Please ensure accuracy.
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Bank Name</label>
                                        <select name="bank_name" class="form-control select2" style="border-radius: 8px;">
                                            <option value="">Select Bank</option>
                                            @php
                                                $banks = [
                                                    'Access Bank', 'Citibank', 'Ecobank Nigeria', 'Fidelity Bank',
                                                    'First Bank of Nigeria', 'First City Monument Bank', 'Guaranty Trust Bank',
                                                    'Heritage Bank', 'Keystone Bank', 'Polaris Bank', 'Providus Bank',
                                                    'Stanbic IBTC Bank', 'Standard Chartered Bank', 'Sterling Bank',
                                                    'Suntrust Bank', 'Union Bank of Nigeria', 'United Bank for Africa',
                                                    'Unity Bank', 'Wema Bank', 'Zenith Bank'
                                                ];
                                            @endphp
                                            @foreach($banks as $bank)
                                            <option value="{{ $bank }}" {{ ($staff->bank_name ?? '') == $bank ? 'selected' : '' }}>
                                                {{ $bank }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Account Number</label>
                                        <input type="text" name="account_number" class="form-control"
                                               value="{{ $staff->account_number ?? '' }}"
                                               placeholder="10-digit account number" maxlength="10" style="border-radius: 8px;">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="font-weight-bold">Account Name</label>
                                        <input type="text" name="account_name" class="form-control"
                                               value="{{ $staff->account_name ?? '' }}"
                                               placeholder="As it appears on your bank account" style="border-radius: 8px;">
                                    </div>
                                </div>
                            </div>

                            <!-- Emergency Contact -->
                            <div class="tab-pane fade" id="emergencyContact">
                                <div class="alert alert-warning" style="border-radius: 8px;">
                                    <i class="mdi mdi-alert mr-2"></i>
                                    Emergency contact will be notified in case of workplace emergencies.
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Contact Name</label>
                                        <input type="text" name="emergency_contact_name" class="form-control"
                                               value="{{ $staff->emergency_contact_name ?? '' }}"
                                               placeholder="Full name" style="border-radius: 8px;">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Relationship</label>
                                        <select name="emergency_contact_relationship" class="form-control" style="border-radius: 8px;">
                                            <option value="">Select Relationship</option>
                                            @php
                                                $relationships = ['Spouse', 'Parent', 'Sibling', 'Child', 'Friend', 'Other'];
                                            @endphp
                                            @foreach($relationships as $rel)
                                            <option value="{{ strtolower($rel) }}"
                                                {{ ($staff->emergency_contact_relationship ?? '') == strtolower($rel) ? 'selected' : '' }}>
                                                {{ $rel }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Phone Number</label>
                                        <input type="text" name="emergency_contact_phone" class="form-control"
                                               value="{{ $staff->emergency_contact_phone ?? '' }}"
                                               placeholder="e.g., 08012345678" style="border-radius: 8px;">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="font-weight-bold">Alternative Phone</label>
                                        <input type="text" name="emergency_contact_alt_phone" class="form-control"
                                               value="{{ $staff->emergency_contact_alt_phone ?? '' }}"
                                               placeholder="e.g., 08012345678" style="border-radius: 8px;">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="font-weight-bold">Address</label>
                                        <textarea name="emergency_contact_address" class="form-control" rows="2"
                                                  placeholder="Emergency contact address"
                                                  style="border-radius: 8px;">{{ $staff->emergency_contact_address ?? '' }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <div class="text-right">
                            <button type="submit" class="btn btn-primary px-4" style="border-radius: 8px;">
                                <i class="mdi mdi-content-save mr-1"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password Card -->
            <div class="card border-0 mt-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-lock text-warning mr-2"></i>Change Password
                    </h6>
                </div>
                <div class="card-body">
                    <form id="passwordForm">
                        @csrf
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required
                                       placeholder="Current password" style="border-radius: 8px;">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold">New Password</label>
                                <input type="password" name="new_password" class="form-control" required
                                       placeholder="New password (min 8 chars)" style="border-radius: 8px;">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold">Confirm Password</label>
                                <input type="password" name="new_password_confirmation" class="form-control" required
                                       placeholder="Confirm new password" style="border-radius: 8px;">
                            </div>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="btn btn-warning" style="border-radius: 8px;">
                                <i class="mdi mdi-lock-reset mr-1"></i>Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-datepicker/bootstrap-datepicker.min.js') }}"></script>
<script>
$(document).ready(function() {
    // Initialize plugins
    $('.select2').select2({
        theme: 'bootstrap4'
    });

    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        endDate: new Date()
    });

    // Submit profile form
    $('#profileForm').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);

        $.ajax({
            url: '{{ route("ess.my-profile.update") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    if (response.reload) {
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    Object.keys(errors).forEach(function(key) {
                        toastr.error(errors[key][0]);
                    });
                } else {
                    toastr.error('An error occurred. Please try again.');
                }
            }
        });
    });

    // Submit password form
    $('#passwordForm').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serialize();

        $.ajax({
            url: '{{ route("ess.my-profile.password") }}',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#passwordForm')[0].reset();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    Object.keys(errors).forEach(function(key) {
                        toastr.error(errors[key][0]);
                    });
                } else {
                    toastr.error('An error occurred. Please try again.');
                }
            }
        });
    });
});
</script>
@endsection
