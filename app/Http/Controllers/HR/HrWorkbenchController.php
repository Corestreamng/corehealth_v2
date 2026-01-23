<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\DisciplinaryQuery;
use App\Models\HR\LeaveRequest;
use App\Models\HR\PayrollBatch;
use App\Models\HR\StaffSuspension;
use App\Models\HR\StaffTermination;
use App\Models\Staff;
use App\Services\DisciplinaryService;
use App\Services\LeaveService;
use App\Services\PayrollService;

/**
 * HRMS Implementation Plan - Section 7.2
 * HR Workbench Controller - Dashboard for HR Manager
 */
class HrWorkbenchController extends Controller
{
    protected LeaveService $leaveService;
    protected PayrollService $payrollService;
    protected DisciplinaryService $disciplinaryService;

    public function __construct(
        LeaveService $leaveService,
        PayrollService $payrollService,
        DisciplinaryService $disciplinaryService
    ) {
        $this->leaveService = $leaveService;
        $this->payrollService = $payrollService;
        $this->disciplinaryService = $disciplinaryService;
    }

    public function index()
    {
        // Staff overview
        $staffStats = [
            'total' => Staff::count(),
            'active' => Staff::active()->count(),
            'suspended' => Staff::suspended()->count(),
            'with_salary_profile' => Staff::withSalaryProfile()->count(),
        ];

        // Leave requests pending approval
        $pendingLeaveRequests = LeaveRequest::pending()
            ->with(['staff.user', 'leaveType'])
            ->latest()
            ->limit(10)
            ->get();

        // Disciplinary queries
        $openQueries = DisciplinaryQuery::whereIn('status', [
            DisciplinaryQuery::STATUS_ISSUED,
            DisciplinaryQuery::STATUS_RESPONSE_RECEIVED,
            DisciplinaryQuery::STATUS_UNDER_REVIEW,
        ])
            ->with(['staff.user'])
            ->latest()
            ->limit(10)
            ->get();

        // Overdue query responses
        $overdueQueries = DisciplinaryQuery::where('status', DisciplinaryQuery::STATUS_ISSUED)
            ->where('response_deadline', '<', now())
            ->with(['staff.user'])
            ->get();

        // Active suspensions
        $activeSuspensions = StaffSuspension::active()
            ->with(['staff.user'])
            ->get();

        // Pending payroll batches
        $pendingPayroll = PayrollBatch::submitted()
            ->with('createdBy')
            ->get();

        // Recent terminations
        $recentTerminations = StaffTermination::with(['staff.user', 'processedBy'])
            ->latest()
            ->limit(5)
            ->get();

        // Stats
        $payrollStats = $this->payrollService->getPayrollStats();
        $disciplinaryStats = $this->disciplinaryService->getDisciplinaryStats();

        // Leave requests this month
        $leaveRequestsThisMonth = LeaveRequest::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return view('admin.hr.workbench.index', compact(
            'staffStats',
            'pendingLeaveRequests',
            'openQueries',
            'overdueQueries',
            'activeSuspensions',
            'pendingPayroll',
            'recentTerminations',
            'payrollStats',
            'disciplinaryStats',
            'leaveRequestsThisMonth'
        ));
    }

    public function stats()
    {
        // Return JSON for AJAX requests
        return response()->json([
            'staff' => [
                'total' => Staff::count(),
                'active' => Staff::active()->count(),
                'suspended' => Staff::suspended()->count(),
            ],
            'leave' => [
                'pending' => LeaveRequest::pending()->count(),
                'approved_this_month' => LeaveRequest::approved()
                    ->whereMonth('reviewed_at', now()->month)
                    ->count(),
            ],
            'disciplinary' => $this->disciplinaryService->getDisciplinaryStats(),
            'payroll' => $this->payrollService->getPayrollStats(),
        ]);
    }
}
