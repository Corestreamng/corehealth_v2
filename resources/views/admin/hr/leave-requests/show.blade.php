@extends('admin.layouts.app')

@section('title', 'Leave Request Details')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                <i class="mdi mdi-file-document-box-check mr-2"></i>Leave Request Details
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{ route('hr.workbench.index') }}">HR Workbench</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('hr.leave-requests.index') }}">Leave Requests</a></li>
                    <li class="breadcrumb-item active">{{ $leaveRequest->request_number ?? 'Details' }}</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('hr.leave-requests.index') }}" class="btn btn-light" style="border-radius: 8px;">
            <i class="mdi mdi-arrow-left mr-1"></i>Back to List
        </a>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Request Details Card -->
            <div class="card-modern border-0 mb-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-information-outline text-primary mr-2"></i>Request Details
                    </h6>
                    @php
                        $statusColors = [
                            'pending' => 'warning',
                            'supervisor_approved' => 'info',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            'cancelled' => 'secondary',
                            'recalled' => 'dark',
                        ];
                        $statusLabels = [
                            'pending' => 'Pending Supervisor Approval',
                            'supervisor_approved' => 'Awaiting HR Approval',
                            'approved' => 'Fully Approved',
                            'rejected' => 'Rejected',
                            'cancelled' => 'Cancelled',
                            'recalled' => 'Recalled',
                        ];
                    @endphp
                    <span class="badge badge-{{ $statusColors[$leaveRequest->status] ?? 'secondary' }}" style="border-radius: 6px; padding: 6px 12px;">
                        {{ $statusLabels[$leaveRequest->status] ?? ucfirst($leaveRequest->status) }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="text-muted small mb-1">Leave Type</label>
                            <div>
                                <span class="badge" style="background-color: {{ $leaveRequest->leaveType->color ?? '#6c757d' }}20; color: {{ $leaveRequest->leaveType->color ?? '#6c757d' }}; border-radius: 6px; padding: 8px 16px; font-size: 1rem;">
                                    {{ $leaveRequest->leaveType->name ?? 'N/A' }}
                                </span>
                                @if($leaveRequest->leaveType->is_paid)
                                    <span class="badge badge-success ml-1" style="border-radius: 4px;">Paid</span>
                                @else
                                    <span class="badge badge-secondary ml-1" style="border-radius: 4px;">Unpaid</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="text-muted small mb-1">Total Days</label>
                            <div>
                                <h4 class="font-weight-bold mb-0">
                                    {{ $leaveRequest->total_days }}
                                    <small class="text-muted">days</small>
                                    @if($leaveRequest->is_half_day)
                                        <span class="badge badge-info ml-1" style="border-radius: 4px;">Half Day</span>
                                    @endif
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="text-muted small mb-1">Start Date</label>
                            <div>
                                <i class="mdi mdi-calendar text-primary mr-1"></i>
                                <strong>{{ \Carbon\Carbon::parse($leaveRequest->start_date)->format('l, F j, Y') }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="text-muted small mb-1">End Date</label>
                            <div>
                                <i class="mdi mdi-calendar text-primary mr-1"></i>
                                <strong>{{ \Carbon\Carbon::parse($leaveRequest->end_date)->format('l, F j, Y') }}</strong>
                            </div>
                        </div>
                    </div>

                    @if($leaveRequest->reason)
                    <div class="mb-4">
                        <label class="text-muted small mb-1">Reason for Leave</label>
                        <div class="bg-light p-3" style="border-radius: 8px;">
                            {{ $leaveRequest->reason }}
                        </div>
                    </div>
                    @endif

                    @if($leaveRequest->handover_notes)
                    <div class="mb-4">
                        <label class="text-muted small mb-1">Handover Notes</label>
                        <div class="bg-light p-3" style="border-radius: 8px;">
                            {{ $leaveRequest->handover_notes }}
                        </div>
                    </div>
                    @endif

                    @if($leaveRequest->contact_during_leave)
                    <div class="mb-4">
                        <label class="text-muted small mb-1">Contact During Leave</label>
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-phone text-primary mr-2"></i>
                            <strong>{{ $leaveRequest->contact_during_leave }}</strong>
                        </div>
                    </div>
                    @endif

                    @if($leaveRequest->reliefStaff)
                    <div class="mb-4">
                        <label class="text-muted small mb-1">Relief Staff</label>
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm bg-info-light text-info mr-2" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                {{ strtoupper(substr($leaveRequest->reliefStaff->user->firstname ?? 'R', 0, 1)) }}
                            </div>
                            <div>
                                <strong>{{ $leaveRequest->reliefStaff->user->name ?? 'N/A' }}</strong>
                                <br>
                                <small class="text-muted">{{ $leaveRequest->reliefStaff->specialization->name ?? '' }}</small>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Approval Timeline Card -->
            <div class="card-modern border-0 mb-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-timeline-text text-primary mr-2"></i>Approval Timeline
                    </h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <!-- Request Submitted -->
                        <div class="timeline-item mb-4">
                            <div class="d-flex">
                                <div class="timeline-badge bg-primary mr-3" style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="mdi mdi-file-document-outline text-white"></i>
                                </div>
                                <div>
                                    <strong>Request Submitted</strong>
                                    <p class="text-muted mb-0 small">
                                        {{ $leaveRequest->created_at->format('M d, Y h:i A') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Supervisor Approval -->
                        <div class="timeline-item mb-4">
                            <div class="d-flex">
                                @if($leaveRequest->supervisor_approved_at)
                                    <div class="timeline-badge bg-success mr-3" style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="mdi mdi-check text-white"></i>
                                    </div>
                                    <div>
                                        <strong class="text-success">Supervisor Approved</strong>
                                        <p class="text-muted mb-0 small">
                                            {{ $leaveRequest->supervisor_approved_at->format('M d, Y h:i A') }}
                                            by {{ $leaveRequest->supervisorApprovedBy->name ?? 'N/A' }}
                                        </p>
                                        @if($leaveRequest->supervisor_comments)
                                            <small class="text-muted"><em>"{{ $leaveRequest->supervisor_comments }}"</em></small>
                                        @endif
                                    </div>
                                @else
                                    <div class="timeline-badge {{ $leaveRequest->status == 'rejected' ? 'bg-danger' : 'bg-light' }} mr-3" style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="mdi {{ $leaveRequest->status == 'rejected' ? 'mdi-close text-white' : 'mdi-clock-outline text-muted' }}"></i>
                                    </div>
                                    <div>
                                        <strong class="{{ $leaveRequest->status == 'rejected' ? 'text-danger' : 'text-muted' }}">
                                            {{ $leaveRequest->status == 'rejected' ? 'Rejected' : 'Awaiting Supervisor Approval' }}
                                        </strong>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- HR Approval -->
                        <div class="timeline-item">
                            <div class="d-flex">
                                @if($leaveRequest->hr_approved_at)
                                    <div class="timeline-badge bg-success mr-3" style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="mdi mdi-check-all text-white"></i>
                                    </div>
                                    <div>
                                        <strong class="text-success">HR Approved (Final)</strong>
                                        <p class="text-muted mb-0 small">
                                            {{ $leaveRequest->hr_approved_at->format('M d, Y h:i A') }}
                                            by {{ $leaveRequest->hrApprovedBy->name ?? 'N/A' }}
                                        </p>
                                        @if($leaveRequest->hr_comments)
                                            <small class="text-muted"><em>"{{ $leaveRequest->hr_comments }}"</em></small>
                                        @endif
                                    </div>
                                @elseif($leaveRequest->supervisor_approved_at)
                                    <div class="timeline-badge bg-warning mr-3" style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="mdi mdi-clock-outline text-white"></i>
                                    </div>
                                    <div>
                                        <strong class="text-warning">Awaiting HR Approval</strong>
                                    </div>
                                @else
                                    <div class="timeline-badge bg-light mr-3" style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="mdi mdi-dots-horizontal text-muted"></i>
                                    </div>
                                    <div>
                                        <strong class="text-muted">Pending</strong>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attachments Card -->
            @if($leaveRequest->attachments && $leaveRequest->attachments->count() > 0)
            <div class="card-modern border-0 mb-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-paperclip text-primary mr-2"></i>Attachments
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($leaveRequest->attachments as $attachment)
                        <div class="col-md-4 mb-3">
                            <div class="border p-3 d-flex align-items-center" style="border-radius: 8px;">
                                <i class="mdi mdi-file-document text-primary mr-2" style="font-size: 1.5rem;"></i>
                                <div class="flex-grow-1 text-truncate">
                                    <small class="d-block font-weight-medium text-truncate">{{ $attachment->original_filename }}</small>
                                    <small class="text-muted">{{ number_format($attachment->file_size / 1024, 1) }} KB</small>
                                </div>
                                <a href="{{ Storage::url($attachment->file_path) }}" target="_blank" class="btn btn-sm btn-light ml-2">
                                    <i class="mdi mdi-download"></i>
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Action Buttons -->
            @if($canSupervisorApprove || $canHrApprove)
            <div class="card-modern border-0 mb-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-gesture-tap-button text-primary mr-2"></i>Take Action
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            @if($canSupervisorApprove)
                            <form action="{{ route('hr.leave-requests.supervisor-approve', $leaveRequest) }}" method="POST">
                                @csrf
                                <div class="form-group">
                                    <label>Approval Comments (Optional)</label>
                                    <textarea name="comments" class="form-control" rows="2" placeholder="Add any comments..." style="border-radius: 8px;"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success btn-block" style="border-radius: 8px;">
                                    <i class="mdi mdi-check-circle mr-1"></i>Supervisor Approve
                                </button>
                                <small class="text-muted d-block mt-2">
                                    <i class="mdi mdi-information-outline mr-1"></i>Request will be forwarded to HR for final approval.
                                </small>
                            </form>
                            @elseif($canHrApprove)
                            <form action="{{ route('hr.leave-requests.hr-approve', $leaveRequest) }}" method="POST">
                                @csrf
                                <div class="form-group">
                                    <label>Approval Comments (Optional)</label>
                                    <textarea name="comments" class="form-control" rows="2" placeholder="Add any comments..." style="border-radius: 8px;"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success btn-block" style="border-radius: 8px;">
                                    <i class="mdi mdi-check-all mr-1"></i>HR Approve (Final)
                                </button>
                                <small class="text-muted d-block mt-2">
                                    <i class="mdi mdi-information-outline mr-1"></i>This will be the final approval.
                                </small>
                            </form>
                            @endif
                        </div>
                        <div class="col-md-6">
                            @if($canReject)
                            <form action="{{ route('hr.leave-requests.reject', $leaveRequest) }}" method="POST">
                                @csrf
                                <div class="form-group">
                                    <label>Rejection Reason <span class="text-danger">*</span></label>
                                    <textarea name="reason" class="form-control" rows="2" placeholder="Provide a reason for rejection..." required style="border-radius: 8px;"></textarea>
                                </div>
                                <button type="submit" class="btn btn-danger btn-block" style="border-radius: 8px;">
                                    <i class="mdi mdi-close-circle mr-1"></i>Reject Request
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Employee Info Card -->
            <div class="card-modern border-0 mb-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-account text-primary mr-2"></i>Employee Information
                    </h6>
                </div>
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-3" style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: 700;">
                        {{ strtoupper(substr($leaveRequest->staff->user->firstname ?? 'U', 0, 1)) }}{{ strtoupper(substr($leaveRequest->staff->user->lastname ?? '', 0, 1)) }}
                    </div>
                    <h5 class="font-weight-bold mb-1">{{ $leaveRequest->staff->user->name ?? 'N/A' }}</h5>
                    <p class="text-muted mb-2">{{ $leaveRequest->staff->staff_number ?? $leaveRequest->staff->employee_id ?? '' }}</p>
                    <span class="badge badge-light" style="border-radius: 6px; padding: 5px 12px;">
                        {{ $leaveRequest->staff->specialization->name ?? 'N/A' }}
                    </span>
                </div>
                <div class="card-footer bg-light" style="border-radius: 0 0 12px 12px;">
                    <div class="row text-center">
                        <div class="col-6">
                            <small class="text-muted d-block">Requested On</small>
                            <strong>{{ $leaveRequest->created_at->format('M d, Y') }}</strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Request #</small>
                            <strong>{{ $leaveRequest->request_number ?? 'N/A' }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Balance Card -->
            <div class="card-modern border-0 mb-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-scale-balance text-primary mr-2"></i>Leave Balance
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <span class="badge" style="background-color: {{ $leaveRequest->leaveType->color ?? '#6c757d' }}20; color: {{ $leaveRequest->leaveType->color ?? '#6c757d' }}; border-radius: 6px; padding: 6px 12px;">
                            {{ $leaveRequest->leaveType->name ?? 'N/A' }}
                        </span>
                    </div>

                    @php
                        $available = $balance->available ?? 0;
                        $entitled = $balance->total_entitled ?? 0;
                        $percentage = $entitled > 0 ? ($available / $entitled) * 100 : 0;
                        $afterApproval = $available - $leaveRequest->total_days;
                    @endphp

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Current Balance</span>
                        <strong>{{ number_format($available, 1) }} days</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Entitlement</span>
                        <strong>{{ number_format($entitled, 1) }} days</strong>
                    </div>
                    <div class="progress mb-3" style="height: 8px; border-radius: 4px;">
                        <div class="progress-bar bg-primary" style="width: {{ min($percentage, 100) }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-2">
                        <span class="text-muted">After Approval</span>
                        <strong class="{{ $afterApproval < 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($afterApproval, 1) }} days
                        </strong>
                    </div>
                    @if($afterApproval < 0)
                        <div class="alert alert-warning mt-3 mb-0" style="border-radius: 6px;">
                            <i class="mdi mdi-alert mr-1"></i>
                            <small>Employee will exceed their leave balance.</small>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Leave Type Requirements -->
            @if($leaveRequest->leaveType)
            <div class="card-modern border-0 mb-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-clipboard-list text-primary mr-2"></i>Leave Type Details
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @if($leaveRequest->leaveType->max_consecutive_days)
                        <li class="mb-2">
                            <i class="mdi mdi-calendar-range text-muted mr-2"></i>
                            Max {{ $leaveRequest->leaveType->max_consecutive_days }} consecutive days
                        </li>
                        @endif
                        @if($leaveRequest->leaveType->min_days_notice)
                        <li class="mb-2">
                            <i class="mdi mdi-clock-alert text-muted mr-2"></i>
                            {{ $leaveRequest->leaveType->min_days_notice }} days notice required
                        </li>
                        @endif
                        <li class="mb-2">
                            <i class="mdi {{ $leaveRequest->leaveType->requires_attachment ? 'mdi-check-circle text-success' : 'mdi-close-circle text-muted' }} mr-2"></i>
                            {{ $leaveRequest->leaveType->requires_attachment ? 'Document Required' : 'Document Optional' }}
                        </li>
                        <li class="mb-2">
                            <i class="mdi {{ $leaveRequest->leaveType->allow_half_day ? 'mdi-check-circle text-success' : 'mdi-close-circle text-muted' }} mr-2"></i>
                            {{ $leaveRequest->leaveType->allow_half_day ? 'Half Day Allowed' : 'No Half Day' }}
                        </li>
                        <li>
                            <i class="mdi {{ $leaveRequest->leaveType->is_paid ? 'mdi-cash text-success' : 'mdi-cash-remove text-warning' }} mr-2"></i>
                            {{ $leaveRequest->leaveType->is_paid ? 'Paid Leave' : 'Unpaid Leave' }}
                        </li>
                    </ul>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
