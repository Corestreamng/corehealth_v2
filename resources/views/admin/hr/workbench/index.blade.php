@extends('admin.layouts.app')

@section('title', 'HR Workbench')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Dashboard')

@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
        .quick-actions-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px; }
        .quick-actions-header h5 { color: white; }
        .quick-action-card { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.5rem 0.5rem; border-radius: 12px; text-decoration: none; color: white; transition: all 0.3s ease; text-align: center; height: 120px; position: relative; overflow: hidden; }
        .quick-action-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0); transition: all 0.3s ease; }
        .quick-action-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); color: white; text-decoration: none; }
        .quick-action-card:hover::before { background: rgba(255,255,255,0.1); }
        .quick-action-card .icon-wrapper { font-size: 2.5rem; margin-bottom: 0.5rem; position: relative; z-index: 1; }
        .quick-action-card .action-title { font-weight: 600; font-size: 0.95rem; display: block; position: relative; z-index: 1; }
        .quick-action-card .action-desc { font-size: 0.75rem; opacity: 0.9; display: block; position: relative; z-index: 1; }
        .nav-card { display: flex; align-items: center; padding: 1.25rem; background: white; border-radius: 12px; text-decoration: none; transition: all 0.3s ease; border: 2px solid transparent; box-shadow: 0 2px 8px rgba(0,0,0,0.08); height: 100%; }
        .nav-card:hover { transform: translateX(5px); box-shadow: 0 4px 16px rgba(0,0,0,0.15); text-decoration: none; }
        .nav-card-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-size: 1.5rem; flex-shrink: 0; }
        .nav-card-content { flex: 1; }
        .nav-card-content h6 { margin: 0 0 0.25rem 0; font-size: 1rem; font-weight: 600; color: #212529; }
        .nav-card-content p { margin: 0; font-size: 0.8rem; color: #6c757d; }
        .nav-card-arrow { font-size: 1.5rem; opacity: 0.5; transition: all 0.3s ease; }
        .nav-card:hover .nav-card-arrow { opacity: 1; transform: translateX(3px); }
        .nav-card-primary .nav-card-icon { background: #e7f1ff; color: #007bff; }
        .nav-card-primary:hover { border-color: #007bff; }
        .nav-card-success .nav-card-icon { background: #d4edda; color: #28a745; }
        .nav-card-success:hover { border-color: #28a745; }
        .nav-card-info .nav-card-icon { background: #d1ecf1; color: #17a2b8; }
        .nav-card-info:hover { border-color: #17a2b8; }
        .nav-card-warning .nav-card-icon { background: #fff3cd; color: #ffc107; }
        .nav-card-warning:hover { border-color: #ffc107; }
        .nav-card-danger .nav-card-icon { background: #f8d7da; color: #dc3545; }
        .nav-card-danger:hover { border-color: #dc3545; }
        .nav-card-secondary .nav-card-icon { background: #e2e3e5; color: #6c757d; }
        .nav-card-secondary:hover { border-color: #6c757d; }
        .nav-card-purple .nav-card-icon { background: #e8dff5; color: #6f42c1; }
        .nav-card-purple:hover { border-color: #6f42c1; }
        .nav-card-teal .nav-card-icon { background: #d1f2eb; color: #20c997; }
        .nav-card-teal:hover { border-color: #20c997; }
        .nav-card-cyan .nav-card-icon { background: #d1ecf1; color: #17a2b8; }
        .nav-card-cyan:hover { border-color: #17a2b8; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection

@section('content')
<div class="container-fluid">
    @include('admin.hr.partials.hr-subnav')

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

    <!-- Quick Actions Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card-modern quick-actions-header">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="mdi mdi-lightning-bolt mr-2"></i>Quick Actions</h5>
                        <span class="badge badge-light">Frequently Used</span>
                    </div>
                    <div class="row">
                        @can('staff-registry.view')
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="{{ route('hr.staff-registry.index') }}" class="quick-action-card bg-primary">
                                <div class="icon-wrapper"><i class="mdi mdi-account-group"></i></div>
                                <span class="action-title">Staff Registry</span>
                                <small class="action-desc">View all staff</small>
                            </a>
                        </div>
                        @endcan
                        @can('leave-request.view')
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="{{ route('hr.leave-calendar.index') }}" class="quick-action-card bg-success">
                                <div class="icon-wrapper"><i class="mdi mdi-calendar-month"></i></div>
                                <span class="action-title">Leave Calendar</span>
                                <small class="action-desc">View schedule</small>
                            </a>
                        </div>
                        @endcan
                        @can('disciplinary.create')
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="{{ route('hr.disciplinary.index') }}" class="quick-action-card bg-warning">
                                <div class="icon-wrapper"><i class="mdi mdi-file-alert"></i></div>
                                <span class="action-title">Issue Query</span>
                                <small class="action-desc">Disciplinary action</small>
                            </a>
                        </div>
                        @endcan
                        @can('payroll.create')
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="{{ route('hr.payroll.index') }}" class="quick-action-card bg-info">
                                <div class="icon-wrapper"><i class="mdi mdi-cash-register"></i></div>
                                <span class="action-title">Payroll</span>
                                <small class="action-desc">Create payroll</small>
                            </a>
                        </div>
                        @endcan
                        @can('salary-profile.view')
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="{{ route('hr.salary-profiles.index') }}" class="quick-action-card bg-secondary">
                                <div class="icon-wrapper"><i class="mdi mdi-account-cash"></i></div>
                                <span class="action-title">Salaries</span>
                                <small class="action-desc">Salary profiles</small>
                            </a>
                        </div>
                        @endcan
                        <div class="col-lg-2 col-md-4 col-6 mb-3">
                            <a href="{{ route('hr.leave-requests.index') }}" class="quick-action-card bg-danger">
                                <div class="icon-wrapper"><i class="mdi mdi-calendar-clock"></i></div>
                                <span class="action-title">Leave Requests</span>
                                <small class="action-desc">Manage requests</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
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

    <!-- HR Enhancement Alerts Row -->
    <div class="row mb-4">
        <div class="col-12 mb-2">
            <h6 class="font-weight-bold text-uppercase text-muted">
                <i class="mdi mdi-bell-alert-outline mr-1"></i> Staff Alerts
                <a href="{{ route('hr.staff-registry.index') }}" class="btn btn-sm btn-outline-primary ml-2">View Full Registry</a>
            </h6>
        </div>
        <div class="col-md-2">
        <a href="{{ route('hr.promotions.index') }}" class="text-decoration-none">
        <div class="card border-left border-warning shadow-sm" style="border-left-width:4px !important; border-radius:8px;">
            <div class="card-body py-2 text-center">
                <small class="text-muted">Promotion Due</small>
                <h4 class="text-warning mb-0 font-weight-bold">{{ $stats['promotion_due'] ?? 0 }}</h4>
            </div>
        </div>
        </a>
    </div>
    <div class="col-md-2">
        <div class="card border-left border-info shadow-sm" style="border-left-width:4px !important; border-radius:8px;">
            <div class="card-body py-2 text-center">
                <small class="text-muted">Confirmation Due</small>
                <h4 class="text-info mb-0 font-weight-bold">{{ $stats['confirmation_due'] ?? 0 }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <a href="{{ route('hr.qualifications.index') }}" class="text-decoration-none">
        <div class="card border-left border-danger shadow-sm" style="border-left-width:4px !important; border-radius:8px;">
            <div class="card-body py-2 text-center">
                <small class="text-muted">License Expiring</small>
                <h4 class="text-danger mb-0 font-weight-bold">{{ $stats['license_expiring'] ?? 0 }}</h4>
            </div>
        </div>
        </a>
    </div>
    <div class="col-md-2">
        <a href="{{ route('hr.medical-exams.index') }}" class="text-decoration-none">
        <div class="card border-left border-secondary shadow-sm" style="border-left-width:4px !important; border-radius:8px;">
            <div class="card-body py-2 text-center">
                <small class="text-muted">Medical Exam Due</small>
                <h4 class="text-secondary mb-0 font-weight-bold">{{ $stats['medical_exam_due'] ?? 0 }}</h4>
            </div>
        </div>
        </a>
    </div>
    <div class="col-md-2">
        <div class="card border-left border-dark shadow-sm" style="border-left-width:4px !important; border-radius:8px;">
            <div class="card-body py-2 text-center">
                <small class="text-muted">Retiring Soon</small>
                <h4 class="text-dark mb-0 font-weight-bold">{{ $stats['retiring_soon'] ?? 0 }}</h4>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <a href="{{ route('hr.follow-ups.index') }}" class="text-decoration-none">
        <div class="card border-left border-primary shadow-sm" style="border-left-width:4px !important; border-radius:8px;">
            <div class="card-body py-2 text-center">
                <small class="text-muted">Open Follow-ups</small>
                <h4 class="text-primary mb-0 font-weight-bold">{{ $stats['open_follow_ups'] ?? 0 }}</h4>
            </div>
        </div>
        </a>
    </div>

    <!-- Navigate To -->
    <div class="row mb-4">
        <div class="col-12">
            <h5 class="mb-3"><i class="mdi mdi-view-grid mr-2"></i>Navigate To</h5>
        </div>
        @can('leave-type.view')
        <div class="col-lg-3 col-md-6 mb-3">
            <a href="{{ route('hr.leave-types.index') }}" class="nav-card nav-card-success">
                <div class="nav-card-icon"><i class="mdi mdi-calendar-check"></i></div>
                <div class="nav-card-content"><h6>Leave Management</h6><p>Types, requests & balances</p></div>
                <i class="mdi mdi-chevron-right nav-card-arrow"></i>
            </a>
        </div>
        @endcan
        @can('disciplinary.view')
        <div class="col-lg-3 col-md-6 mb-3">
            <a href="{{ route('hr.disciplinary.index') }}" class="nav-card nav-card-warning">
                <div class="nav-card-icon"><i class="mdi mdi-gavel"></i></div>
                <div class="nav-card-content"><h6>Disciplinary</h6><p>Queries, suspensions & terminations</p></div>
                <i class="mdi mdi-chevron-right nav-card-arrow"></i>
            </a>
        </div>
        @endcan
        @can('payroll.view')
        <div class="col-lg-3 col-md-6 mb-3">
            <a href="{{ route('hr.payroll.index') }}" class="nav-card nav-card-info">
                <div class="nav-card-icon"><i class="mdi mdi-cash-multiple"></i></div>
                <div class="nav-card-content"><h6>Payroll</h6><p>Pay heads, profiles & batches</p></div>
                <i class="mdi mdi-chevron-right nav-card-arrow"></i>
            </a>
        </div>
        @endcan
        @can('staff-registry.view')
        <div class="col-lg-3 col-md-6 mb-3">
            <a href="{{ route('hr.staff-registry.index') }}" class="nav-card nav-card-primary">
                <div class="nav-card-icon"><i class="mdi mdi-account-group"></i></div>
                <div class="nav-card-content"><h6>Staff Registry</h6><p>Employee records & profiles</p></div>
                <i class="mdi mdi-chevron-right nav-card-arrow"></i>
            </a>
        </div>
        @endcan
        <div class="col-lg-3 col-md-6 mb-3">
            <a href="{{ route('hr.promotions.index') }}" class="nav-card nav-card-purple">
                <div class="nav-card-icon"><i class="mdi mdi-arrow-up-bold-circle"></i></div>
                <div class="nav-card-content"><h6>Staff Tracking</h6><p>Promotions, training & follow-ups</p></div>
                <i class="mdi mdi-chevron-right nav-card-arrow"></i>
            </a>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <a href="{{ route('hr.units.index') }}" class="nav-card nav-card-secondary">
                <div class="nav-card-icon"><i class="mdi mdi-cog"></i></div>
                <div class="nav-card-content"><h6>Configuration</h6><p>Units, cadres & grade levels</p></div>
                <i class="mdi mdi-chevron-right nav-card-arrow"></i>
            </a>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <a href="{{ route('hr.ess.index') }}" class="nav-card nav-card-teal">
                <div class="nav-card-icon"><i class="mdi mdi-account-circle"></i></div>
                <div class="nav-card-content"><h6>Employee Self-Service</h6><p>Leave, payslips & profile</p></div>
                <i class="mdi mdi-chevron-right nav-card-arrow"></i>
            </a>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <a href="{{ route('hr.qualifications.index') }}" class="nav-card nav-card-danger">
                <div class="nav-card-icon"><i class="mdi mdi-certificate"></i></div>
                <div class="nav-card-content"><h6>Qualifications</h6><p>Certifications & training records</p></div>
                <i class="mdi mdi-chevron-right nav-card-arrow"></i>
            </a>
        </div>
    </div>

    <div class="row">
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

</div>
@endsection
