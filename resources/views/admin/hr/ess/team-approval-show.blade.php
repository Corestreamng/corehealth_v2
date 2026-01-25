@extends('admin.layouts.app')

@section('title', 'ESS - Review Leave Request')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                <i class="mdi mdi-file-document-box-check mr-2"></i>Review Leave Request
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{ route('hr.ess.index') }}">ESS</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('hr.ess.team-approvals.index') }}">Team Approvals</a></li>
                    <li class="breadcrumb-item active">Review Request</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('hr.ess.team-approvals.index') }}" class="btn btn-light" style="border-radius: 8px;">
            <i class="mdi mdi-arrow-left mr-1"></i>Back to List
        </a>
    </div>

    <div class="row">
        <!-- Main Info -->
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
                        ];
                        $statusLabels = [
                            'pending' => 'Pending Supervisor Approval',
                            'supervisor_approved' => 'Awaiting HR Approval',
                            'approved' => 'Fully Approved',
                            'rejected' => 'Rejected',
                            'cancelled' => 'Cancelled',
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

            <!-- Action Buttons (Only for pending requests) -->
            @if($leaveRequest->status === 'pending')
            <div class="card-modern border-0 mb-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-gesture-tap-button text-primary mr-2"></i>Take Action
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <form action="{{ route('hr.ess.team-approvals.approve', $leaveRequest) }}" method="POST" id="approveForm">
                                @csrf
                                <div class="form-group">
                                    <label>Approval Remarks (Optional)</label>
                                    <textarea name="remarks" class="form-control" rows="2" placeholder="Add any remarks..." style="border-radius: 8px;"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success btn-block" style="border-radius: 8px;">
                                    <i class="mdi mdi-check-circle mr-1"></i>Approve Request
                                </button>
                                <small class="text-muted d-block mt-2">
                                    <i class="mdi mdi-information-outline mr-1"></i>Request will be forwarded to HR for final approval.
                                </small>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form action="{{ route('hr.ess.team-approvals.reject', $leaveRequest) }}" method="POST" id="rejectForm">
                                @csrf
                                <div class="form-group">
                                    <label>Rejection Reason <span class="text-danger">*</span></label>
                                    <textarea name="rejection_reason" class="form-control" rows="2" placeholder="Provide a reason for rejection..." required style="border-radius: 8px;"></textarea>
                                </div>
                                <button type="submit" class="btn btn-danger btn-block" style="border-radius: 8px;">
                                    <i class="mdi mdi-close-circle mr-1"></i>Reject Request
                                </button>
                            </form>
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
                    <p class="text-muted mb-2">{{ $leaveRequest->staff->employee_id ?? '' }}</p>
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
                            <small class="text-muted d-block">Time</small>
                            <strong>{{ $leaveRequest->created_at->format('h:i A') }}</strong>
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
                        $balanceRemaining = $balance->balance_remaining ?? 0;
                        $entitlement = $balance->entitlement ?? 0;
                        $percentage = $entitlement > 0 ? ($balanceRemaining / $entitlement) * 100 : 0;
                        $afterApproval = $balanceRemaining - $leaveRequest->total_days;
                    @endphp

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Current Balance</span>
                        <strong>{{ $balanceRemaining }} days</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Requested</span>
                        <strong class="text-warning">-{{ $leaveRequest->total_days }} days</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">After Approval</span>
                        <strong class="{{ $afterApproval < 0 ? 'text-danger' : 'text-success' }}">{{ $afterApproval }} days</strong>
                    </div>

                    @if($afterApproval < 0)
                    <div class="alert alert-danger mt-3 mb-0" style="border-radius: 8px;">
                        <i class="mdi mdi-alert-circle mr-1"></i>
                        <small>Insufficient leave balance!</small>
                    </div>
                    @endif

                    <div class="progress mt-3" style="height: 8px; border-radius: 4px;">
                        <div class="progress-bar bg-{{ $percentage > 50 ? 'success' : ($percentage > 20 ? 'warning' : 'danger') }}"
                             style="width: {{ min($percentage, 100) }}%"></div>
                    </div>
                    <small class="text-muted">{{ number_format($percentage, 0) }}% of {{ $entitlement }} days remaining</small>
                </div>
            </div>

            <!-- Approval History Card -->
            @if($leaveRequest->reviewed_at || $leaveRequest->supervisor_reviewed_at)
            <div class="card-modern border-0 mb-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-history text-primary mr-2"></i>Approval History
                    </h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <div class="d-flex align-items-start">
                                <div class="bg-success-light text-success p-2 rounded-circle mr-3">
                                    <i class="mdi mdi-check"></i>
                                </div>
                                <div>
                                    <strong>Submitted</strong>
                                    <br>
                                    <small class="text-muted">{{ $leaveRequest->created_at->format('M d, Y h:i A') }}</small>
                                </div>
                            </div>
                        </li>
                        @if($leaveRequest->supervisor_reviewed_at)
                        <li class="list-group-item">
                            <div class="d-flex align-items-start">
                                <div class="bg-info-light text-info p-2 rounded-circle mr-3">
                                    <i class="mdi mdi-account-check"></i>
                                </div>
                                <div>
                                    <strong>Supervisor {{ $leaveRequest->status === 'rejected' ? 'Rejected' : 'Approved' }}</strong>
                                    @if($leaveRequest->supervisorReviewedBy)
                                    <br>
                                    <small>by {{ $leaveRequest->supervisorReviewedBy->name ?? 'N/A' }}</small>
                                    @endif
                                    <br>
                                    <small class="text-muted">{{ \Carbon\Carbon::parse($leaveRequest->supervisor_reviewed_at)->format('M d, Y h:i A') }}</small>
                                </div>
                            </div>
                        </li>
                        @endif
                        @if($leaveRequest->reviewed_at && $leaveRequest->status === 'approved')
                        <li class="list-group-item">
                            <div class="d-flex align-items-start">
                                <div class="bg-success-light text-success p-2 rounded-circle mr-3">
                                    <i class="mdi mdi-check-all"></i>
                                </div>
                                <div>
                                    <strong>HR Approved</strong>
                                    @if($leaveRequest->reviewedBy)
                                    <br>
                                    <small>by {{ $leaveRequest->reviewedBy->name ?? 'N/A' }}</small>
                                    @endif
                                    <br>
                                    <small class="text-muted">{{ \Carbon\Carbon::parse($leaveRequest->reviewed_at)->format('M d, Y h:i A') }}</small>
                                </div>
                            </div>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
