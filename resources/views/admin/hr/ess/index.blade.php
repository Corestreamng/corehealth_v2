@extends('admin.layouts.app')

@section('title', 'ESS - My Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                <i class="mdi mdi-account-circle mr-2"></i>Employee Self-Service
            </h3>
            <p class="text-muted mb-0">Welcome back, {{ auth()->user()->firstname ?? 'Employee' }}!</p>
        </div>
        <div class="text-muted">
            <i class="mdi mdi-calendar mr-1"></i>{{ date('l, F j, Y') }}
        </div>
    </div>

    <!-- Quick Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0" style="border-radius: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-white-50">Annual Leave</h6>
                            <h2 class="mb-0" style="font-weight: 700;">{{ $leaveBalances['annual'] ?? 0 }}</h2>
                        </div>
                        <div style="font-size: 2.5rem; opacity: 0.3;">
                            <i class="mdi mdi-beach"></i>
                        </div>
                    </div>
                    <small class="text-white-50">Days remaining</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0" style="border-radius: 12px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="card-body text-white py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-white-50">Sick Leave</h6>
                            <h2 class="mb-0" style="font-weight: 700;">{{ $leaveBalances['sick'] ?? 0 }}</h2>
                        </div>
                        <div style="font-size: 2.5rem; opacity: 0.3;">
                            <i class="mdi mdi-hospital-box"></i>
                        </div>
                    </div>
                    <small class="text-white-50">Days remaining</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0" style="border-radius: 12px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="card-body text-white py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-white-50">Pending Requests</h6>
                            <h2 class="mb-0" style="font-weight: 700;">{{ $pendingLeaveCount ?? 0 }}</h2>
                        </div>
                        <div style="font-size: 2.5rem; opacity: 0.3;">
                            <i class="mdi mdi-clock-outline"></i>
                        </div>
                    </div>
                    <small class="text-white-50">Leave requests</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0" style="border-radius: 12px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="card-body text-white py-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 text-white-50">Last Payslip</h6>
                            <h6 class="mb-0" style="font-weight: 700;">
                                @if($lastPayslip)
                                {{ \Carbon\Carbon::parse($lastPayslip->payrollBatch->pay_period)->format('M Y') }}
                                @else
                                N/A
                                @endif
                            </h6>
                        </div>
                        <div style="font-size: 2.5rem; opacity: 0.3;">
                            <i class="mdi mdi-cash"></i>
                        </div>
                    </div>
                    @if($lastPayslip)
                    <small class="text-white-50">₦{{ number_format($lastPayslip->net_salary, 2) }}</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Quick Actions -->
        <div class="col-md-4 mb-4">
            <div class="card border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-lightning-bolt text-primary mr-2"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <a href="{{ route('hr.ess.my-leave') }}" class="btn btn-outline-primary btn-block mb-3 py-3" style="border-radius: 8px;">
                        <i class="mdi mdi-calendar-plus mr-2"></i>Request Leave
                    </a>
                    <a href="{{ route('hr.ess.my-payslips') }}" class="btn btn-outline-success btn-block mb-3 py-3" style="border-radius: 8px;">
                        <i class="mdi mdi-file-document mr-2"></i>View Payslips
                    </a>
                    <a href="{{ route('hr.ess.my-profile') }}" class="btn btn-outline-info btn-block py-3" style="border-radius: 8px;">
                        <i class="mdi mdi-account-edit mr-2"></i>Update Profile
                    </a>
                </div>
            </div>

            {{-- Team Approvals Card - For Unit Heads/Dept Heads --}}
            @if($staff->is_unit_head || $staff->is_dept_head)
            <div class="card border-0 mt-4" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-left: 4px solid #f5576c;">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-account-check text-danger mr-2"></i>Supervisor Actions
                    </h6>
                </div>
                <div class="card-body">
                    <a href="{{ route('hr.ess.team-approvals.index') }}" class="btn btn-danger btn-block py-3 position-relative" style="border-radius: 8px;">
                        <i class="mdi mdi-clipboard-check-multiple mr-2"></i>Team Approvals
                        @if(isset($pendingTeamApprovalsCount) && $pendingTeamApprovalsCount > 0)
                        <span class="badge badge-light position-absolute" style="top: -8px; right: -8px; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                            {{ $pendingTeamApprovalsCount }}
                        </span>
                        @endif
                    </a>
                    <small class="text-muted d-block mt-2 text-center">
                        @if($staff->is_unit_head && $staff->is_dept_head)
                            Unit Head &amp; Department Head
                        @elseif($staff->is_unit_head)
                            Unit Head
                        @else
                            Department Head
                        @endif
                    </small>
                </div>
            </div>
            @endif
        </div>

        <!-- Recent Leave Requests -->
        <div class="col-md-8 mb-4">
            <div class="card border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-calendar-check text-info mr-2"></i>My Recent Leave Requests
                    </h6>
                    <a href="{{ route('hr.ess.my-leave') }}" class="btn btn-sm btn-outline-primary" style="border-radius: 6px;">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    @if(isset($recentLeaveRequests) && $recentLeaveRequests->count())
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Period</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentLeaveRequests as $request)
                                <tr>
                                    <td>{{ $request->leaveType->name ?? 'N/A' }}</td>
                                    <td>
                                        {{ \Carbon\Carbon::parse($request->start_date)->format('M d') }} -
                                        {{ \Carbon\Carbon::parse($request->end_date)->format('M d, Y') }}
                                    </td>
                                    <td>{{ $request->days_requested }}</td>
                                    <td>
                                        @php
                                            $statusClass = [
                                                'pending' => 'warning',
                                                'approved' => 'success',
                                                'rejected' => 'danger',
                                                'cancelled' => 'secondary'
                                            ][$request->status] ?? 'secondary';
                                        @endphp
                                        <span class="badge badge-{{ $statusClass }}" style="border-radius: 6px;">
                                            {{ ucfirst($request->status) }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-5 text-muted">
                        <i class="mdi mdi-calendar-blank" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-2 mb-0">No leave requests yet</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Leave Balances -->
        <div class="col-md-6 mb-4">
            <div class="card border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-chart-pie text-success mr-2"></i>My Leave Balances ({{ date('Y') }})
                    </h6>
                </div>
                <div class="card-body">
                    @if(isset($allLeaveBalances) && $allLeaveBalances->count())
                    @foreach($allLeaveBalances as $balance)
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>{{ $balance->leaveType->name ?? 'N/A' }}</span>
                        <div class="d-flex align-items-center">
                            <div class="progress mr-3" style="width: 150px; height: 8px; border-radius: 4px;">
                                @php
                                    $percentage = $balance->entitlement > 0
                                        ? (($balance->balance_remaining / $balance->entitlement) * 100)
                                        : 0;
                                @endphp
                                <div class="progress-bar bg-success" style="width: {{ $percentage }}%"></div>
                            </div>
                            <strong>{{ $balance->balance_remaining }}</strong> / {{ $balance->entitlement }}
                        </div>
                    </div>
                    @endforeach
                    @else
                    <div class="text-center py-4 text-muted">
                        <p class="mb-0">No leave balances allocated</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Notifications/Alerts -->
        <div class="col-md-6 mb-4">
            <div class="card border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-bell text-warning mr-2"></i>Notifications
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @if($pendingLeaveCount > 0)
                        <div class="list-group-item d-flex align-items-center">
                            <div class="mr-3">
                                <span class="badge badge-warning badge-pill" style="width: 30px; height: 30px; line-height: 20px;">
                                    <i class="mdi mdi-clock"></i>
                                </span>
                            </div>
                            <div>
                                <strong>Pending Leave Requests</strong>
                                <br>
                                <small class="text-muted">You have {{ $pendingLeaveCount }} leave request(s) awaiting approval</small>
                            </div>
                        </div>
                        @endif

                        @if(isset($activeQuery) && $activeQuery)
                        <div class="list-group-item d-flex align-items-center">
                            <div class="mr-3">
                                <span class="badge badge-danger badge-pill" style="width: 30px; height: 30px; line-height: 20px;">
                                    <i class="mdi mdi-alert"></i>
                                </span>
                            </div>
                            <div>
                                <strong>Active Disciplinary Query</strong>
                                <br>
                                <small class="text-muted">You have an active query requiring response</small>
                                <a href="{{ route('hr.ess.my-disciplinary') }}" class="text-primary ml-2">View</a>
                            </div>
                        </div>
                        @endif

                        @if($lastPayslip && $lastPayslip->created_at->isCurrentMonth())
                        <div class="list-group-item d-flex align-items-center">
                            <div class="mr-3">
                                <span class="badge badge-success badge-pill" style="width: 30px; height: 30px; line-height: 20px;">
                                    <i class="mdi mdi-cash"></i>
                                </span>
                            </div>
                            <div>
                                <strong>New Payslip Available</strong>
                                <br>
                                <small class="text-muted">Your {{ \Carbon\Carbon::parse($lastPayslip->payrollBatch->pay_period)->format('F Y') }} payslip is ready</small>
                                <a href="{{ route('hr.ess.my-payslips') }}" class="text-primary ml-2">View</a>
                            </div>
                        </div>
                        @endif

                        @if(!$pendingLeaveCount && !isset($activeQuery) && (!$lastPayslip || !$lastPayslip->created_at->isCurrentMonth()))
                        <div class="list-group-item text-center py-5 text-muted">
                            <i class="mdi mdi-check-circle-outline" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="mt-2 mb-0">No new notifications</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
