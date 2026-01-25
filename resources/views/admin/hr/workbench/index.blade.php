@extends('admin.layouts.app')

@section('title', 'HR Workbench')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                <i class="mdi mdi-view-dashboard mr-2"></i>HR Workbench
            </h3>
            <p class="text-muted mb-0">Human Resources Management Dashboard</p>
        </div>
        <div class="text-muted">
            <i class="mdi mdi-calendar mr-1"></i>{{ date('l, F j, Y') }}
        </div>
    </div>

    <!-- Quick Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card-modern border-0" style="border-radius: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-white-50">Total Staff</h6>
                            <h2 class="mb-0" style="font-weight: 700;">{{ $stats['total_staff'] ?? 0 }}</h2>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3;">
                            <i class="mdi mdi-account-group"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-white-50">
                            <i class="mdi mdi-check-circle mr-1"></i>{{ $stats['active_staff'] ?? 0 }} Active
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-modern border-0" style="border-radius: 12px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="card-body text-white py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-white-50">Pending Leave</h6>
                            <h2 class="mb-0" style="font-weight: 700;">{{ $stats['pending_leave_requests'] ?? 0 }}</h2>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3;">
                            <i class="mdi mdi-calendar-clock"></i>
                        </div>
                    </div>
                    <a href="{{ route('hr.leave-requests.index', ['status' => 'pending']) }}" class="text-white-50 small">
                        <i class="mdi mdi-arrow-right mr-1"></i>View Requests
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-modern border-0" style="border-radius: 12px; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <div class="card-body text-white py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-white-50">Open Queries</h6>
                            <h2 class="mb-0" style="font-weight: 700;">{{ $stats['open_queries'] ?? 0 }}</h2>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3;">
                            <i class="mdi mdi-gavel"></i>
                        </div>
                    </div>
                    <a href="{{ route('hr.disciplinary.index') }}" class="text-white-50 small">
                        <i class="mdi mdi-arrow-right mr-1"></i>Manage Queries
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-modern border-0" style="border-radius: 12px; background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);">
                <div class="card-body text-white py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-white-50">Suspended</h6>
                            <h2 class="mb-0" style="font-weight: 700;">{{ $stats['active_suspensions'] ?? 0 }}</h2>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3;">
                            <i class="mdi mdi-account-lock"></i>
                        </div>
                    </div>
                    <a href="{{ route('hr.suspensions.index', ['status' => 'active']) }}" class="text-white-50 small">
                        <i class="mdi mdi-arrow-right mr-1"></i>View Details
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card-modern border-0" style="border-radius: 12px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="card-body text-white py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-white-50">On Leave Today</h6>
                            <h2 class="mb-0" style="font-weight: 700;">{{ $stats['on_leave_today'] ?? 0 }}</h2>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3;">
                            <i class="mdi mdi-beach"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-modern border-0" style="border-radius: 12px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="card-body text-white py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-white-50">Payroll Pending</h6>
                            <h2 class="mb-0" style="font-weight: 700;">{{ $stats['pending_payroll'] ?? 0 }}</h2>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3;">
                            <i class="mdi mdi-cash-register"></i>
                        </div>
                    </div>
                    <a href="{{ route('hr.payroll.index', ['status' => 'submitted']) }}" class="text-white-50 small">
                        <i class="mdi mdi-arrow-right mr-1"></i>Review
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-modern border-0" style="border-radius: 12px; background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                <div class="card-body text-dark py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-muted">Terminations (YTD)</h6>
                            <h2 class="mb-0" style="font-weight: 700;">{{ $stats['terminations_ytd'] ?? 0 }}</h2>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3;">
                            <i class="mdi mdi-account-off"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card-modern border-0" style="border-radius: 12px; background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                <div class="card-body text-dark py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-muted">Salary Profiles</h6>
                            <h2 class="mb-0" style="font-weight: 700;">{{ $stats['salary_profiles'] ?? 0 }}</h2>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3;">
                            <i class="mdi mdi-account-cash"></i>
                        </div>
                    </div>
                    <a href="{{ route('hr.salary-profiles.index') }}" class="text-muted small">
                        <i class="mdi mdi-arrow-right mr-1"></i>Manage
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Pending Leave Requests -->
        <div class="col-md-6 mb-4">
            <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-calendar-check text-primary mr-2"></i>Pending Leave Requests
                    </h6>
                    <a href="{{ route('hr.leave-requests.index') }}" class="btn btn-sm btn-outline-primary" style="border-radius: 6px;">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    @if(isset($pendingLeaveRequests) && $pendingLeaveRequests->count())
                    <div class="list-group list-group-flush">
                        @foreach($pendingLeaveRequests as $request)
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <strong>{{ $request->staff->user->firstname ?? '' }} {{ $request->staff->user->surname ?? '' }}</strong>
                                <br>
                                <small class="text-muted">
                                    {{ $request->leaveType->name ?? 'N/A' }} •
                                    {{ \Carbon\Carbon::parse($request->start_date)->format('M d') }} - {{ \Carbon\Carbon::parse($request->end_date)->format('M d, Y') }}
                                    ({{ $request->days_requested }} days)
                                </small>
                            </div>
                            <div>
                                <a href="{{ route('hr.leave-requests.index') }}?view={{ $request->id }}" class="btn btn-sm btn-outline-primary" style="border-radius: 6px;">
                                    <i class="mdi mdi-eye"></i>
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-5 text-muted">
                        <i class="mdi mdi-check-circle-outline" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-2 mb-0">No pending leave requests</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Open Disciplinary Queries -->
        <div class="col-md-6 mb-4">
            <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-gavel text-warning mr-2"></i>Open Disciplinary Queries
                    </h6>
                    <a href="{{ route('hr.disciplinary.index') }}" class="btn btn-sm btn-outline-warning" style="border-radius: 6px;">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    @if(isset($openQueries) && $openQueries->count())
                    <div class="list-group list-group-flush">
                        @foreach($openQueries as $query)
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <strong>{{ $query->staff->user->firstname ?? '' }} {{ $query->staff->user->surname ?? '' }}</strong>
                                <span class="badge badge-{{ $query->severity == 'gross' ? 'danger' : ($query->severity == 'major' ? 'warning' : 'info') }} ml-2">
                                    {{ ucfirst($query->severity) }}
                                </span>
                                <br>
                                <small class="text-muted">
                                    {{ $query->subject }} •
                                    Status: {{ ucfirst($query->status) }}
                                </small>
                            </div>
                            <div>
                                <a href="{{ route('hr.disciplinary.index') }}?view={{ $query->id }}" class="btn btn-sm btn-outline-warning" style="border-radius: 6px;">
                                    <i class="mdi mdi-eye"></i>
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-5 text-muted">
                        <i class="mdi mdi-check-circle-outline" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-2 mb-0">No open queries</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Staff on Leave Today -->
        <div class="col-md-6 mb-4">
            <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-beach text-success mr-2"></i>Staff on Leave Today
                    </h6>
                    <a href="{{ route('hr.leave-calendar.index') }}" class="btn btn-sm btn-outline-success" style="border-radius: 6px;">
                        <i class="mdi mdi-calendar-month mr-1"></i>Calendar
                    </a>
                </div>
                <div class="card-body p-0">
                    @if(isset($staffOnLeave) && $staffOnLeave->count())
                    <div class="list-group list-group-flush">
                        @foreach($staffOnLeave as $leave)
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <strong>{{ $leave->staff->user->firstname ?? '' }} {{ $leave->staff->user->surname ?? '' }}</strong>
                                <br>
                                <small class="text-muted">
                                    {{ $leave->leaveType->name ?? 'N/A' }} •
                                    Returns: {{ \Carbon\Carbon::parse($leave->end_date)->format('M d, Y') }}
                                </small>
                            </div>
                            <span class="badge badge-success" style="border-radius: 6px;">On Leave</span>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-5 text-muted">
                        <i class="mdi mdi-account-check-outline" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-2 mb-0">All staff present today</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recent Payroll Batches -->
        <div class="col-md-6 mb-4">
            <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-cash-multiple text-info mr-2"></i>Recent Payroll
                    </h6>
                    <a href="{{ route('hr.payroll.index') }}" class="btn btn-sm btn-outline-info" style="border-radius: 6px;">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    @if(isset($recentPayroll) && $recentPayroll->count())
                    <div class="list-group list-group-flush">
                        @foreach($recentPayroll as $batch)
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <strong>{{ $batch->batch_number }}</strong>
                                <span class="badge badge-{{ $batch->status == 'approved' ? 'success' : ($batch->status == 'submitted' ? 'warning' : 'secondary') }} ml-2">
                                    {{ ucfirst($batch->status) }}
                                </span>
                                <br>
                                <small class="text-muted">
                                    {{ \Carbon\Carbon::parse($batch->pay_period)->format('F Y') }} •
                                    ₦{{ number_format($batch->total_net_amount, 2) }}
                                </small>
                            </div>
                            <div>
                                <a href="{{ route('hr.payroll.index') }}?view={{ $batch->id }}" class="btn btn-sm btn-outline-info" style="border-radius: 6px;">
                                    <i class="mdi mdi-eye"></i>
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-5 text-muted">
                        <i class="mdi mdi-cash-register" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-2 mb-0">No payroll batches</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-12">
            <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-lightning-bolt text-primary mr-2"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        @can('leave-request.view')
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('hr.leave-calendar.index') }}" class="btn btn-outline-success btn-block py-3" style="border-radius: 8px;">
                                <i class="mdi mdi-calendar-month d-block mb-2" style="font-size: 1.5rem;"></i>
                                Leave Calendar
                            </a>
                        </div>
                        @endcan
                        @can('leave-type.create')
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('hr.leave-types.index') }}" class="btn btn-outline-primary btn-block py-3" style="border-radius: 8px;">
                                <i class="mdi mdi-calendar-plus d-block mb-2" style="font-size: 1.5rem;"></i>
                                Manage Leave Types
                            </a>
                        </div>
                        @endcan
                        @can('disciplinary.create')
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('hr.disciplinary.index') }}" class="btn btn-outline-warning btn-block py-3" style="border-radius: 8px;">
                                <i class="mdi mdi-file-alert d-block mb-2" style="font-size: 1.5rem;"></i>
                                Issue Query
                            </a>
                        </div>
                        @endcan
                        @can('payroll.create')
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('hr.payroll.index') }}" class="btn btn-outline-info btn-block py-3" style="border-radius: 8px;">
                                <i class="mdi mdi-cash-register d-block mb-2" style="font-size: 1.5rem;"></i>
                                Create Payroll
                            </a>
                        </div>
                        @endcan
                        @can('salary-profile.create')
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('hr.salary-profiles.index') }}" class="btn btn-outline-secondary btn-block py-3" style="border-radius: 8px;">
                                <i class="mdi mdi-account-cash d-block mb-2" style="font-size: 1.5rem;"></i>
                                Salary Profiles
                            </a>
                        </div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
