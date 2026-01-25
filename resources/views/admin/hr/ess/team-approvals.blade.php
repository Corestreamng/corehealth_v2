@extends('admin.layouts.app')

@section('title', 'ESS - Team Approvals')

@section('styles')
<link href="{{ asset('plugins/select2/select2.min.css') }}" rel="stylesheet">
<link href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}" rel="stylesheet">
@endsection

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                <i class="mdi mdi-account-check mr-2"></i>Team Approvals
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{ route('hr.ess.index') }}">ESS</a></li>
                    <li class="breadcrumb-item active">Team Approvals</li>
                </ol>
            </nav>
        </div>
        <div>
            @if($staff->is_unit_head)
                <span class="badge badge-info mr-2" style="padding: 8px 12px; border-radius: 6px;">
                    <i class="mdi mdi-account-star mr-1"></i>Unit Head
                </span>
            @endif
            @if($staff->is_dept_head)
                <span class="badge badge-primary" style="padding: 8px 12px; border-radius: 6px;">
                    <i class="mdi mdi-account-supervisor mr-1"></i>Department Head
                </span>
            @endif
        </div>
    </div>

    <!-- Status Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <a href="{{ route('hr.ess.team-approvals.index', ['status' => 'pending']) }}" class="text-decoration-none">
                <div class="card border-0" style="border-radius: 12px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 text-white-50">Pending</h6>
                                <h3 class="mb-0" style="font-weight: 700;">{{ $pendingCount }}</h3>
                            </div>
                            <div style="font-size: 2rem; opacity: 0.3;">
                                <i class="mdi mdi-clock-outline"></i>
                            </div>
                        </div>
                        <small class="text-white-50">Awaiting your approval</small>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('hr.ess.team-approvals.index', ['status' => 'supervisor_approved']) }}" class="text-decoration-none">
                <div class="card border-0" style="border-radius: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 text-white-50">Approved by Me</h6>
                                <h3 class="mb-0" style="font-weight: 700;">{{ $supervisorApprovedCount }}</h3>
                            </div>
                            <div style="font-size: 2rem; opacity: 0.3;">
                                <i class="mdi mdi-check"></i>
                            </div>
                        </div>
                        <small class="text-white-50">Awaiting HR approval</small>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('hr.ess.team-approvals.index', ['status' => 'approved']) }}" class="text-decoration-none">
                <div class="card border-0" style="border-radius: 12px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                    <div class="card-body text-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 text-white-50">HR Approved</h6>
                                <h3 class="mb-0" style="font-weight: 700;">{{ $hrApprovedCount }}</h3>
                            </div>
                            <div style="font-size: 2rem; opacity: 0.3;">
                                <i class="mdi mdi-check-all"></i>
                            </div>
                        </div>
                        <small class="text-white-50">Fully approved</small>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('hr.ess.team-approvals.index', ['status' => 'rejected']) }}" class="text-decoration-none">
                <div class="card border-0" style="border-radius: 12px; background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
                    <div class="card-body text-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1 text-white-50">Rejected</h6>
                                <h3 class="mb-0" style="font-weight: 700;">{{ $rejectedCount }}</h3>
                            </div>
                            <div style="font-size: 2rem; opacity: 0.3;">
                                <i class="mdi mdi-close-circle"></i>
                            </div>
                        </div>
                        <small class="text-white-50">Rejected requests</small>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 mb-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="card-body py-3">
            <form action="{{ route('hr.ess.team-approvals.index') }}" method="GET" class="row align-items-end">
                <div class="col-md-4">
                    <label class="small text-muted mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm" style="border-radius: 8px;">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="supervisor_approved" {{ request('status') == 'supervisor_approved' ? 'selected' : '' }}>Supervisor Approved</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>HR Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="small text-muted mb-1">Leave Type</label>
                    <select name="leave_type_id" class="form-control form-control-sm" style="border-radius: 8px;">
                        <option value="">All Types</option>
                        @foreach($leaveTypes as $leaveType)
                            <option value="{{ $leaveType->id }}" {{ request('leave_type_id') == $leaveType->id ? 'selected' : '' }}>
                                {{ $leaveType->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-sm mr-2" style="border-radius: 8px;">
                        <i class="mdi mdi-filter mr-1"></i>Filter
                    </button>
                    <a href="{{ route('hr.ess.team-approvals.index') }}" class="btn btn-light btn-sm" style="border-radius: 8px;">
                        <i class="mdi mdi-refresh mr-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Leave Requests Table -->
    <div class="card border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0;">
            <h6 class="mb-0" style="font-weight: 600;">
                <i class="mdi mdi-format-list-bulleted text-primary mr-2"></i>Team Leave Requests
            </h6>
            <small class="text-muted">
                Showing {{ $pendingRequests->firstItem() ?? 0 }} - {{ $pendingRequests->lastItem() ?? 0 }} of {{ $pendingRequests->total() }} requests
            </small>
        </div>
        <div class="card-body">
            @if($pendingRequests->isEmpty())
                <div class="text-center py-5">
                    <i class="mdi mdi-clipboard-check-outline text-muted" style="font-size: 4rem;"></i>
                    <h5 class="mt-3 text-muted">No leave requests found</h5>
                    <p class="text-muted">
                        @if(request('status'))
                            No {{ request('status') == 'supervisor_approved' ? 'supervisor approved' : request('status') }} leave requests found.
                        @else
                            There are no leave requests from your team members.
                        @endif
                    </p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover" style="width: 100%;">
                        <thead class="bg-light">
                            <tr>
                                <th>Employee</th>
                                <th>Unit/Dept</th>
                                <th>Leave Type</th>
                                <th>Dates</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingRequests as $request)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm bg-primary-light text-primary mr-2" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            {{ strtoupper(substr($request->staff->user->firstname ?? 'U', 0, 1)) }}
                                        </div>
                                        <div>
                                            <span class="font-weight-medium">{{ $request->staff->user->name ?? 'N/A' }}</span>
                                            <br>
                                            <small class="text-muted">{{ $request->staff->employee_id ?? '' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $request->staff->specialization->name ?? 'N/A' }}</small>
                                </td>
                                <td>
                                    <span class="badge" style="background-color: {{ $request->leaveType->color ?? '#6c757d' }}20; color: {{ $request->leaveType->color ?? '#6c757d' }}; border-radius: 6px; padding: 5px 10px;">
                                        {{ $request->leaveType->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="font-weight-medium">{{ \Carbon\Carbon::parse($request->start_date)->format('M d, Y') }}</span>
                                    <br>
                                    <small class="text-muted">to {{ \Carbon\Carbon::parse($request->end_date)->format('M d, Y') }}</small>
                                </td>
                                <td>
                                    <span class="font-weight-bold">{{ $request->total_days }}</span>
                                    <small class="text-muted">days</small>
                                    @if($request->is_half_day)
                                        <br><span class="badge badge-info badge-sm" style="border-radius: 4px; font-size: 0.65rem;">Half Day</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $statusColors = [
                                            'pending' => 'warning',
                                            'supervisor_approved' => 'info',
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'cancelled' => 'secondary',
                                        ];
                                        $statusLabels = [
                                            'pending' => 'Pending',
                                            'supervisor_approved' => 'Supervisor Approved',
                                            'approved' => 'Approved',
                                            'rejected' => 'Rejected',
                                            'cancelled' => 'Cancelled',
                                        ];
                                    @endphp
                                    <span class="badge badge-{{ $statusColors[$request->status] ?? 'secondary' }}" style="border-radius: 6px; padding: 5px 10px;">
                                        {{ $statusLabels[$request->status] ?? ucfirst($request->status) }}
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $request->created_at->diffForHumans() }}</small>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('hr.ess.team-approvals.show', $request) }}" class="btn btn-sm btn-light" style="border-radius: 6px;" title="View Details">
                                        <i class="mdi mdi-eye"></i>
                                    </a>
                                    @if($request->status === 'pending')
                                        <button type="button" class="btn btn-sm btn-success approve-btn"
                                                data-id="{{ $request->id }}"
                                                data-name="{{ $request->staff->user->name ?? 'Employee' }}"
                                                data-toggle="modal" data-target="#approveModal"
                                                style="border-radius: 6px;" title="Approve">
                                            <i class="mdi mdi-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger reject-btn"
                                                data-id="{{ $request->id }}"
                                                data-name="{{ $request->staff->user->name ?? 'Employee' }}"
                                                data-toggle="modal" data-target="#rejectModal"
                                                style="border-radius: 6px;" title="Reject">
                                            <i class="mdi mdi-close"></i>
                                        </button>
                                    @elseif($request->status === 'supervisor_approved')
                                        <span class="badge badge-info" style="border-radius: 6px; padding: 5px 8px;">
                                            <i class="mdi mdi-clock-outline mr-1"></i>Pending HR
                                        </span>
                                    @elseif($request->status === 'approved')
                                        <span class="badge badge-success" style="border-radius: 6px; padding: 5px 8px;">
                                            <i class="mdi mdi-check-all mr-1"></i>Completed
                                        </span>
                                    @elseif($request->status === 'rejected')
                                        <span class="badge badge-danger" style="border-radius: 6px; padding: 5px 8px;">
                                            <i class="mdi mdi-close-circle mr-1"></i>Rejected
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-end mt-3">
                    {{ $pendingRequests->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-success text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-check-circle mr-2"></i>Approve Leave Request
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="approveForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p>You are about to approve the leave request for <strong id="approveEmployeeName"></strong>.</p>
                    <p class="text-muted small">After your approval, the request will be forwarded to HR for final approval.</p>
                    <div class="form-group">
                        <label>Remarks (Optional)</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Add any remarks for the approval..." style="border-radius: 8px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-success" style="border-radius: 8px;">
                        <i class="mdi mdi-check mr-1"></i>Approve Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-danger text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-close-circle mr-2"></i>Reject Leave Request
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="rejectForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p>You are about to reject the leave request for <strong id="rejectEmployeeName"></strong>.</p>
                    <div class="form-group">
                        <label>Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Please provide a reason for rejecting this request..." required style="border-radius: 8px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="border-radius: 8px;">
                        <i class="mdi mdi-close mr-1"></i>Reject Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Handle approve button click
    $('.approve-btn').on('click', function() {
        var requestId = $(this).data('id');
        var employeeName = $(this).data('name');

        $('#approveEmployeeName').text(employeeName);
        $('#approveForm').attr('action', '/hr/ess/team-approvals/' + requestId + '/approve');
    });

    // Handle reject button click
    $('.reject-btn').on('click', function() {
        var requestId = $(this).data('id');
        var employeeName = $(this).data('name');

        $('#rejectEmployeeName').text(employeeName);
        $('#rejectForm').attr('action', '/hr/ess/team-approvals/' + requestId + '/reject');
    });
});
</script>
@endsection
