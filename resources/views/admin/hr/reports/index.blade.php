@extends('admin.layouts.app')

@section('title', 'HR Reports')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Reports')

@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
        .report-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .bg-primary-light { background: #e7f1ff; }
        .bg-success-light { background: #d4edda; }
        .bg-info-light { background: #d1ecf1; }
        .bg-warning-light { background: #fff3cd; }
        .bg-danger-light { background: #f8d7da; }
        .bg-purple-light { background: #e8dff5; }
        .bg-teal-light { background: #d1f2eb; }
        .bg-secondary-light { background: #e2e3e5; }
        .text-purple { color: #6f42c1; }
        .text-teal { color: #20c997; }
        .list-group-item-action:hover {
            background-color: #f8f9fa;
            transform: translateX(3px);
            transition: all 0.2s ease;
        }
        .list-group-item .mdi-chevron-right {
            opacity: 0.4;
            transition: all 0.2s ease;
        }
        .list-group-item:hover .mdi-chevron-right {
            opacity: 1;
        }
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
                <i class="mdi mdi-chart-box mr-2"></i>HR Reports
            </h3>
            <p class="text-muted mb-0">Generate and view human resources reports</p>
        </div>
    </div>

    <!-- Quick Report Generator -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-lightning-bolt text-primary mr-2"></i>Quick Report Generator
                    </h6>
                </div>
                <div class="card-body">
                    <form id="quickReportForm" class="row align-items-end">
                        <div class="col-md-3">
                            <label class="font-weight-medium">Report Type</label>
                            <select class="form-control" id="reportType" style="border-radius: 8px;">
                                <option value="">Select Report...</option>
                                <option value="staff-headcount">Staff Headcount</option>
                                <option value="leave-summary">Leave Summary</option>
                                <option value="disciplinary">Disciplinary Report</option>
                                <option value="payroll-summary">Payroll Summary</option>
                                <option value="training">Training Report</option>
                                <option value="attendance">Attendance Report</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="font-weight-medium">Start Date</label>
                            <input type="date" class="form-control" id="reportStartDate" value="{{ now()->startOfMonth()->format('Y-m-d') }}" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-3">
                            <label class="font-weight-medium">End Date</label>
                            <input type="date" class="form-control" id="reportEndDate" value="{{ now()->format('Y-m-d') }}" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-block" style="border-radius: 8px;">
                                <i class="mdi mdi-file-chart mr-1"></i>Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Categories -->
    <div class="row">
        <!-- Workforce Reports -->
        <div class="col-md-6 mb-4">
            <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-account-group text-primary mr-2"></i>Workforce Reports
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    @can('staff-registry.view')
                    <a href="{{ route('hr.staff-registry.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-primary-light mr-3">
                            <i class="mdi mdi-account-group text-primary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Staff Headcount</h6>
                            <small class="text-muted">Active staff, units & departments</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                    @endcan
                    <a href="{{ route('hr.promotions.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-purple-light mr-3">
                            <i class="mdi mdi-arrow-up-bold-circle text-purple"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Promotions Report</h6>
                            <small class="text-muted">Promotion history & due dates</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                    <a href="{{ route('hr.qualifications.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-teal-light mr-3">
                            <i class="mdi mdi-certificate text-teal"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Qualifications Report</h6>
                            <small class="text-muted">Staff certifications & verifications</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                    <a href="{{ route('hr.trainings.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-info-light mr-3">
                            <i class="mdi mdi-school text-info"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Training Report</h6>
                            <small class="text-muted">Training records & completion status</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                    <a href="{{ route('hr.medical-exams.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-danger-light mr-3">
                            <i class="mdi mdi-hospital text-danger"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Medical Exam Report</h6>
                            <small class="text-muted">Exam results & upcoming due dates</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Leave & Disciplinary Reports -->
        <div class="col-md-6 mb-4">
            <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-calendar-check text-success mr-2"></i>Leave & Disciplinary Reports
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    @can('leave-request.view')
                    <a href="{{ route('hr.leave-requests.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-success-light mr-3">
                            <i class="mdi mdi-calendar-check text-success"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Leave Summary</h6>
                            <small class="text-muted">Requests, approvals & balances</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                    @endcan
                    @can('leave-request.view')
                    <a href="{{ route('hr.leave-balances.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-warning-light mr-3">
                            <i class="mdi mdi-scale-balance text-warning"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Leave Balances</h6>
                            <small class="text-muted">Entitlements & remaining days</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                    @endcan
                    @can('disciplinary.view')
                    <a href="{{ route('hr.disciplinary.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-danger-light mr-3">
                            <i class="mdi mdi-gavel text-danger"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Disciplinary Report</h6>
                            <small class="text-muted">Queries, outcomes & trends</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                    @endcan
                    <a href="{{ route('hr.suspensions.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-secondary-light mr-3">
                            <i class="mdi mdi-account-lock text-secondary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Suspensions Report</h6>
                            <small class="text-muted">Active & historical suspensions</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                    <a href="{{ route('hr.terminations.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-danger-light mr-3">
                            <i class="mdi mdi-account-off text-danger"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Terminations Report</h6>
                            <small class="text-muted">Exit records & clearance status</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Payroll Reports -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-cash-multiple text-info mr-2"></i>Payroll Reports
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    @can('payroll.view')
                    <a href="{{ route('hr.payroll.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-info-light mr-3">
                            <i class="mdi mdi-cash-register text-info"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Payroll Summary</h6>
                            <small class="text-muted">Batch history & payment totals</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                    @endcan
                    @can('salary-profile.view')
                    <a href="{{ route('hr.salary-profiles.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-primary-light mr-3">
                            <i class="mdi mdi-account-cash text-primary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Salary Profiles</h6>
                            <small class="text-muted">Staff salary structures & deductions</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                    @endcan
                    <a href="{{ route('hr.pay-heads.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-warning-light mr-3">
                            <i class="mdi mdi-format-list-bulleted text-warning"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Pay Heads</h6>
                            <small class="text-muted">Earning & deduction components</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                    <a href="{{ route('hr.grade-levels.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-purple-light mr-3">
                            <i class="mdi mdi-stairs text-purple"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Grade Levels</h6>
                            <small class="text-muted">Salary scales & retirement ages</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Tracking Reports -->
        <div class="col-md-6 mb-4">
            <div class="card-modern border-0" style="border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-clipboard-text text-purple mr-2"></i>Tracking Reports
                    </h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="{{ route('hr.follow-ups.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-teal-light mr-3">
                            <i class="mdi mdi-clipboard-check text-teal"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Follow-ups Report</h6>
                            <small class="text-muted">Open tasks & overdue items</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                    <a href="{{ route('hr.leave-calendar.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-success-light mr-3">
                            <i class="mdi mdi-calendar-month text-success"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Leave Calendar</h6>
                            <small class="text-muted">Visual leave schedule overview</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                    <a href="{{ route('hr.units.index') }}" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="report-icon bg-secondary-light mr-3">
                            <i class="mdi mdi-office-building text-secondary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0" style="font-weight: 600;">Units & Cadres</h6>
                            <small class="text-muted">Organizational structure</small>
                        </div>
                        <i class="mdi mdi-chevron-right" style="font-size: 1.3rem;"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('#quickReportForm').on('submit', function(e) {
        e.preventDefault();
        var type = $('#reportType').val();
        if (!type) {
            toastr.error('Please select a report type');
            return;
        }
        var routes = {
            'staff-headcount': '{{ route("hr.staff-registry.index") }}',
            'leave-summary': '{{ route("hr.leave-requests.index") }}',
            'disciplinary': '{{ route("hr.disciplinary.index") }}',
            'payroll-summary': '{{ route("hr.payroll.index") }}',
            'training': '{{ route("hr.trainings.index") }}',
            'attendance': '{{ route("hr.leave-calendar.index") }}'
        };
        var start = $('#reportStartDate').val();
        var end = $('#reportEndDate').val();
        var url = routes[type] + '?start_date=' + start + '&end_date=' + end;
        window.location.href = url;
    });
});
</script>
@endsection
