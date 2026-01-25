<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\DisciplinaryQuery;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\HR\PayrollItem;
use App\Models\Staff;
use App\Services\LeaveService;
use App\Services\PayrollService;
use App\Services\HrAttachmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * HRMS Implementation Plan - Section 7.2
 * Employee Self-Service (ESS) Controller
 */
class EssController extends Controller
{
    protected LeaveService $leaveService;
    protected PayrollService $payrollService;
    protected HrAttachmentService $attachmentService;

    public function __construct(
        LeaveService $leaveService,
        PayrollService $payrollService,
        HrAttachmentService $attachmentService
    ) {
        $this->leaveService = $leaveService;
        $this->payrollService = $payrollService;
        $this->attachmentService = $attachmentService;
    }

    /**
     * Get the current user's staff record
     */
    protected function getStaff()
    {
        $staff = auth()->user()->staff;
        if (!$staff) {
            abort(403, 'You do not have a staff profile linked to your account.');
        }
        return $staff;
    }

    /**
     * ESS Dashboard
     */
    public function index()
    {
        $staff = $this->getStaff();

        // Get leave balances
        $leaveTypes = LeaveType::active()->get();
        $balances = [];
        foreach ($leaveTypes as $leaveType) {
            $balances[$leaveType->id] = $this->leaveService->getOrCreateBalance($staff->id, $leaveType->id);
        }

        // Recent leave requests
        $recentLeaveRequests = LeaveRequest::where('staff_id', $staff->id)
            ->with('leaveType')
            ->latest()
            ->limit(5)
            ->get();

        // Pending leave count
        $pendingLeaveCount = LeaveRequest::where('staff_id', $staff->id)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->count();

        // Pending disciplinary queries
        $pendingQueries = DisciplinaryQuery::where('staff_id', $staff->id)
            ->where('status', DisciplinaryQuery::STATUS_ISSUED)
            ->get();

        // Recent payslips
        $recentPayslips = PayrollItem::where('staff_id', $staff->id)
            ->whereHas('payrollBatch', fn($q) => $q->whereIn('status', ['approved', 'paid']))
            ->with('payrollBatch')
            ->latest()
            ->limit(3)
            ->get();

        // Last/most recent payslip
        $lastPayslip = $recentPayslips->first();

        // Pending team approvals count (for supervisors)
        $pendingTeamApprovalsCount = 0;
        if ($staff->is_unit_head || $staff->is_dept_head) {
            $pendingTeamApprovalsCount = LeaveRequest::canApproveAsSupervisor(auth()->user())
                ->where('status', LeaveRequest::STATUS_PENDING)
                ->count();
        }

        return view('admin.hr.ess.index', compact(
            'staff',
            'leaveTypes',
            'balances',
            'recentLeaveRequests',
            'pendingLeaveCount',
            'pendingQueries',
            'recentPayslips',
            'lastPayslip',
            'pendingTeamApprovalsCount'
        ));
    }

    /**
     * My Leave - List and balances
     */
    public function myLeave(Request $request)
    {
        $staff = $this->getStaff();

        $query = LeaveRequest::where('staff_id', $staff->id)
            ->with(['leaveType', 'reviewedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('year')) {
            $query->whereYear('start_date', $request->year);
        }

        $leaveRequests = $query->latest()->paginate(15);

        // Get balances
        $leaveTypes = LeaveType::active()->get();
        $balances = [];
        foreach ($leaveTypes as $leaveType) {
            $balances[$leaveType->id] = $this->leaveService->getOrCreateBalance($staff->id, $leaveType->id);
        }

        // Get available relief staff (excluding self)
        $reliefStaff = Staff::active()->with('user')
            ->where('id', '!=', $staff->id)
            ->orderBy('employee_id')
            ->get();

        return view('admin.hr.ess.my-leave', compact('staff', 'leaveRequests', 'leaveTypes', 'balances', 'reliefStaff'));
    }

    /**
     * Request Leave Form
     */
    public function requestLeave()
    {
        $staff = $this->getStaff();

        $leaveTypes = LeaveType::active()->orderBy('name')->get();
        $reliefStaff = Staff::active()->with('user')
            ->where('id', '!=', $staff->id)
            ->get();

        // Get balances
        $balances = [];
        foreach ($leaveTypes as $leaveType) {
            $balances[$leaveType->id] = $this->leaveService->getOrCreateBalance($staff->id, $leaveType->id);
        }

        return view('admin.hr.ess.request-leave', compact('staff', 'leaveTypes', 'reliefStaff', 'balances'));
    }

    /**
     * Store Leave Request
     */
    public function storeLeaveRequest(Request $request)
    {
        $staff = $this->getStaff();
        $user = auth()->user();

        // Validate request
        $validator = Validator::make($request->all(), [
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_half_day' => 'nullable|boolean',
            'reason' => 'required|string|max:1000',
            'handover_notes' => 'nullable|string|max:2000',
            'contact_during_leave' => 'nullable|string|max:255',
            'relief_staff_id' => 'nullable|exists:staff,id|different:' . $staff->id,
            'document' => 'nullable|file|max:5120|mimes:pdf,doc,docx,jpg,jpeg,png', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get leave type to check constraints
            $leaveType = LeaveType::findOrFail($request->leave_type_id);

            // Validate leave type constraints
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            // Check if half-day is allowed
            if ($request->is_half_day && !$leaveType->allow_half_day) {
                return response()->json([
                    'success' => false,
                    'message' => 'Half-day requests are not allowed for this leave type.'
                ], 422);
            }

            // Check minimum notice period
            if ($leaveType->min_days_notice) {
                $noticeGiven = now()->diffInDays($startDate, false);
                if ($noticeGiven < $leaveType->min_days_notice) {
                    return response()->json([
                        'success' => false,
                        'message' => "This leave type requires at least {$leaveType->min_days_notice} days advance notice."
                    ], 422);
                }
            }

            // Check if document is required
            if ($leaveType->requires_attachment && !$request->hasFile('document')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Supporting document is required for this leave type.'
                ], 422);
            }

            // Calculate days (accounting for half day)
            $totalDays = $this->leaveService->calculateLeaveDays($startDate, $endDate);
            if ($request->is_half_day && $totalDays == 1) {
                $totalDays = 0.5;
            }

            // Check max consecutive days
            if ($leaveType->max_consecutive_days && $totalDays > $leaveType->max_consecutive_days) {
                return response()->json([
                    'success' => false,
                    'message' => "This leave type allows maximum {$leaveType->max_consecutive_days} consecutive days."
                ], 422);
            }

            // Create leave request
            $leaveRequest = $this->leaveService->createLeaveRequest([
                'staff_id' => $staff->id,
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'reason' => $request->reason,
                'handover_notes' => $request->handover_notes,
                'relief_staff_id' => $request->relief_staff_id,
                'contact_during_leave' => $request->contact_during_leave,
                'is_half_day' => $request->is_half_day ? true : false,
            ]);

            // Handle document upload
            if ($request->hasFile('document')) {
                $this->attachmentService->attach(
                    $leaveRequest,
                    $request->file('document'),
                    [
                        'document_type' => 'leave_supporting_document',
                        'uploaded_by' => $user->id,
                        'description' => 'Supporting document for leave request'
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Leave request submitted successfully. Awaiting approval.',
                'data' => $leaveRequest
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Cancel Leave Request
     */
    public function cancelLeaveRequest(LeaveRequest $leaveRequest)
    {
        $staff = $this->getStaff();

        if ($leaveRequest->staff_id !== $staff->id) {
            abort(403, 'You can only cancel your own leave requests.');
        }

        try {
            $this->leaveService->cancelRequest($leaveRequest);
            return back()->with('success', 'Leave request cancelled.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * My Payslips
     */
    public function myPayslips(Request $request)
    {
        $staff = $this->getStaff();

        $query = PayrollItem::where('staff_id', $staff->id)
            ->whereHas('payrollBatch', fn($q) => $q->whereIn('status', ['approved', 'paid']))
            ->with('payrollBatch');

        if ($request->filled('year')) {
            $query->whereHas('payrollBatch', fn($q) => $q->whereYear('pay_period_start', $request->year));
        }

        $payslips = $query->latest()->paginate(12);
        $years = range(now()->year - 2, now()->year);

        // Get latest payslip
        $latestPayslip = PayrollItem::where('staff_id', $staff->id)
            ->whereHas('payrollBatch', fn($q) => $q->whereIn('status', ['approved', 'paid']))
            ->with('payrollBatch')
            ->latest()
            ->first();

        // Calculate YTD values
        $ytdPayslips = PayrollItem::where('staff_id', $staff->id)
            ->whereHas('payrollBatch', fn($q) => $q
                ->whereIn('status', ['approved', 'paid'])
                ->whereYear('pay_period_start', now()->year)
            )
            ->get();

        $ytdGross = $ytdPayslips->sum('gross_salary');
        $ytdDeductions = $ytdPayslips->sum('total_deductions');

        return view('admin.hr.ess.my-payslips', compact('staff', 'payslips', 'years', 'latestPayslip', 'ytdGross', 'ytdDeductions'));
    }

    /**
     * Print Payslip - Opens printable payslip view
     */
    public function printPayslip(PayrollItem $payrollItem)
    {
        $staff = $this->getStaff();

        if ($payrollItem->staff_id !== $staff->id) {
            abort(403, 'You can only print your own payslips.');
        }

        $payrollItem->load(['payrollBatch', 'details.payHead']);
        $payslip = $this->payrollService->getPayslipData($payrollItem);
        $payrollBatch = $payrollItem->payrollBatch;

        return view('admin.hr.payroll.payslip-print', compact('payslip', 'payrollBatch'));
    }

    /**
     * Download Payslip PDF
     */
    public function downloadPayslip(PayrollItem $payrollItem)
    {
        $staff = $this->getStaff();

        if ($payrollItem->staff_id !== $staff->id) {
            abort(403, 'You can only download your own payslips.');
        }

        $payrollItem->load(['payrollBatch', 'details.payHead']);
        $payslipData = $this->payrollService->getPayslipData($payrollItem);

        $pdf = Pdf::loadView('admin.hr.ess.payslip-pdf', [
            'payslip' => $payslipData,
            'staff' => $staff,
            'payrollItem' => $payrollItem,
        ]);

        return $pdf->download("payslip-{$payslipData['payslip_number']}.pdf");
    }

    /**
     * My Disciplinary Records
     */
    public function myDisciplinary()
    {
        $staff = $this->getStaff();

        $queries = DisciplinaryQuery::where('staff_id', $staff->id)
            ->with(['issuedBy', 'decidedBy'])
            ->latest()
            ->paginate(15);

        return view('admin.hr.ess.my-disciplinary', compact('staff', 'queries'));
    }

    /**
     * Show Disciplinary Query
     */
    public function showDisciplinaryQuery(DisciplinaryQuery $disciplinaryQuery)
    {
        $staff = $this->getStaff();

        if ($disciplinaryQuery->staff_id !== $staff->id) {
            abort(403, 'You can only view your own disciplinary queries.');
        }

        $disciplinaryQuery->load(['issuedBy', 'decidedBy', 'attachments']);

        return view('admin.hr.ess.disciplinary-show', compact('staff', 'disciplinaryQuery'));
    }

    /**
     * Respond to Disciplinary Query
     */
    public function respondToDisciplinaryQuery(Request $request, DisciplinaryQuery $disciplinaryQuery)
    {
        $staff = $this->getStaff();

        if ($disciplinaryQuery->staff_id !== $staff->id) {
            abort(403, 'You can only respond to your own disciplinary queries.');
        }

        $request->validate([
            'response' => 'required|string|max:5000',
            'attachments.*' => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
        ]);

        try {
            $disciplinaryQuery->update([
                'staff_response' => $request->response,
                'response_received_at' => now(),
                'status' => DisciplinaryQuery::STATUS_RESPONSE_RECEIVED,
            ]);

            // Handle attachments
            if ($request->hasFile('attachments')) {
                $this->attachmentService->attachMultiple(
                    $disciplinaryQuery,
                    $request->file('attachments'),
                    ['document_type' => 'query_response', 'uploaded_by' => auth()->id()]
                );
            }

            return redirect()->route('hr.ess.my-disciplinary')
                ->with('success', 'Your response has been submitted.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * My Profile
     */
    public function myProfile()
    {
        $staff = $this->getStaff();
        $staff->load('user', 'specialization', 'clinic', 'department');
        $user = auth()->user();

        // Calculate total leave balance
        $leaveTypes = LeaveType::active()->get();
        $leaveBalanceTotal = 0;
        foreach ($leaveTypes as $leaveType) {
            $balance = $this->leaveService->getOrCreateBalance($staff->id, $leaveType->id);
            $leaveBalanceTotal += $balance->available;
        }

        // Count total payslips
        $totalPayslips = PayrollItem::where('staff_id', $staff->id)
            ->whereHas('payrollBatch', fn($q) => $q->whereIn('status', ['approved', 'paid']))
            ->count();

        // Get specializations and clinics for edit dropdowns
        $specializations = \App\Models\Specialization::orderBy('name')->pluck('name', 'id');
        $clinics = \App\Models\Clinic::orderBy('name')->pluck('name', 'id');

        // Get supervisors (unit heads in same department + dept heads in same category)
        // Include the current user if they have supervisor rights (is_unit_head or is_dept_head)
        $supervisors = Staff::with('user')
            ->where('status', 1) // Active staff (status = 1)
            ->where(function ($q) use ($staff, $user) {
                // Unit heads in the same department
                $q->where(function ($sub) use ($staff) {
                    $sub->where('is_unit_head', true)
                        ->where('department_id', $staff->department_id);
                })
                // Dept heads in the same category
                ->orWhere(function ($sub) use ($user) {
                    $sub->where('is_dept_head', true)
                        ->whereHas('user', fn($uq) => $uq->where('is_admin', $user->is_admin));
                });
            })
            ->get();

        return view('admin.hr.ess.my-profile', compact('staff', 'user', 'leaveBalanceTotal', 'totalPayslips', 'specializations', 'clinics', 'supervisors'));
    }

    /**
     * Update Profile (comprehensive - all editable fields)
     */
    public function updateProfile(Request $request)
    {
        $staff = $this->getStaff();
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            // User model fields (surname and firstname are required in DB)
            'surname' => 'sometimes|required|string|max:255',
            'firstname' => 'sometimes|required|string|max:255',
            'othername' => 'nullable|string|max:255',
            // Staff model fields (gender is required in DB with enum values: Male, Female, Others)
            'gender' => 'sometimes|required|in:Male,Female,Others',
            'date_of_birth' => 'nullable|date|before:today',
            'phone_number' => 'nullable|string|max:20',
            'home_address' => 'nullable|string|max:500',
            'specialization_id' => 'nullable|exists:specializations,id',
            'clinic_id' => 'nullable|exists:clinics,id',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:10',
            'bank_account_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'pension_id' => 'nullable|string|max:50',
            'photo' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Update User model fields - only update if values are provided
        $userFields = array_filter($request->only(['surname', 'firstname', 'othername']), function($value) {
            return $value !== null && $value !== '';
        });
        if (!empty($userFields)) {
            $user->update($userFields);
        }

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('staff/photos', 'public');
            $staff->photo = $photoPath;
        }

        // Update Staff model fields - only update fields that have actual values
        // This prevents setting NOT NULL fields to empty values
        $staffFieldKeys = [
            'gender',
            'date_of_birth',
            'phone_number',
            'home_address',
            'specialization_id',
            'clinic_id',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_relationship',
            'bank_name',
            'bank_account_number',
            'bank_account_name',
            'tax_id',
            'pension_id',
        ];

        foreach ($staffFieldKeys as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                // Only update if value is not empty string (allow null for nullable fields)
                // For gender (NOT NULL), skip if empty
                if ($field === 'gender' && empty($value)) {
                    continue;
                }
                // For other fields, set to null if empty string (they're nullable in DB)
                $staff->$field = $value === '' ? null : $value;
            }
        }

        $staff->save();

        return response()->json(['success' => true, 'message' => 'Profile updated successfully.', 'reload' => true]);
    }

    /**
     * Update Password
     */
    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Current password is incorrect.'], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['success' => true, 'message' => 'Password updated successfully.']);
    }

    // ===========================================
    // TEAM APPROVALS (For Unit Heads / Dept Heads)
    // ===========================================

    /**
     * Team Approvals Dashboard
     * Shows pending leave requests for supervisor approval
     */
    public function teamApprovals(Request $request)
    {
        $staff = $this->getStaff();
        $user = auth()->user();

        // Check if user is a unit head or dept head
        if (!$staff->is_unit_head && !$staff->is_dept_head) {
            abort(403, 'You are not authorized to approve leave requests.');
        }

        // Get all requests under this supervisor's jurisdiction (any status)
        $query = LeaveRequest::with(['staff.user', 'staff.specialization', 'leaveType', 'supervisorApprovedBy'])
            ->underSupervisorJurisdiction($user)
            ->latest();

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        // No default filter - show all by default

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        $pendingRequests = $query->paginate(15);

        // Get counts for all statuses
        $pendingCount = LeaveRequest::underSupervisorJurisdiction($user)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->count();
        $supervisorApprovedCount = LeaveRequest::underSupervisorJurisdiction($user)
            ->where('status', LeaveRequest::STATUS_SUPERVISOR_APPROVED)
            ->count();
        $hrApprovedCount = LeaveRequest::underSupervisorJurisdiction($user)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->count();
        $rejectedCount = LeaveRequest::underSupervisorJurisdiction($user)
            ->where('status', LeaveRequest::STATUS_REJECTED)
            ->count();

        $leaveTypes = LeaveType::active()->orderBy('name')->get();

        return view('admin.hr.ess.team-approvals', compact(
            'staff',
            'pendingRequests',
            'pendingCount',
            'supervisorApprovedCount',
            'hrApprovedCount',
            'rejectedCount',
            'leaveTypes'
        ));
    }

    /**
     * Show Team Leave Request Details
     */
    public function showTeamLeaveRequest(LeaveRequest $leaveRequest)
    {
        $staff = $this->getStaff();
        $user = auth()->user();

        // Verify this request can be approved by this supervisor
        if (!$leaveRequest->canBeApprovedBySupervisor($user)) {
            abort(403, 'You are not authorized to view this leave request.');
        }

        $leaveRequest->load([
            'staff.user',
            'staff.specialization',
            'leaveType',
            'reliefStaff.user',
            'reviewedBy',
            'attachments'
        ]);

        // Get staff's leave balance for this leave type
        $balance = $this->leaveService->getOrCreateBalance(
            $leaveRequest->staff_id,
            $leaveRequest->leave_type_id
        );

        return view('admin.hr.ess.team-approval-show', compact('staff', 'leaveRequest', 'balance'));
    }

    /**
     * Approve Team Leave Request
     */
    public function approveTeamLeave(Request $request, LeaveRequest $leaveRequest)
    {
        $staff = $this->getStaff();
        $user = auth()->user();

        // Verify this request can be approved by this supervisor
        if (!$leaveRequest->canBeApprovedBySupervisor($user)) {
            abort(403, 'You are not authorized to approve this leave request.');
        }

        $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        try {
            $this->leaveService->supervisorApproveRequest($leaveRequest, auth()->user(), $request->remarks);

            return redirect()->route('hr.ess.team-approvals.index')
                ->with('success', 'Leave request approved successfully. It will now proceed to HR for final approval.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reject Team Leave Request
     */
    public function rejectTeamLeave(Request $request, LeaveRequest $leaveRequest)
    {
        $staff = $this->getStaff();
        $user = auth()->user();

        // Verify this request can be rejected by this supervisor
        if (!$leaveRequest->canBeApprovedBySupervisor($user)) {
            abort(403, 'You are not authorized to reject this leave request.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        try {
            $this->leaveService->rejectRequest($leaveRequest, auth()->user(), $request->rejection_reason);

            return redirect()->route('hr.ess.team-approvals.index')
                ->with('success', 'Leave request rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ===========================================
    // LEAVE CALENDAR (Individual View)
    // ===========================================

    /**
     * My Leave Calendar - Individual's leave calendar
     */
    public function myCalendar()
    {
        $staff = $this->getStaff();

        // Get leave balances for sidebar
        $leaveTypes = LeaveType::active()->get();
        $balances = [];
        foreach ($leaveTypes as $leaveType) {
            $balances[$leaveType->id] = $this->leaveService->getOrCreateBalance($staff->id, $leaveType->id);
        }

        // Stats for grid calendar (must match keys expected by leave-calendar.index)
        $today = now()->toDateString();
        $stats = [
            'on_leave_today' => LeaveRequest::where('staff_id', $staff->id)
                ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_SUPERVISOR_APPROVED])
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->count(),
            'pending_requests' => LeaveRequest::where('staff_id', $staff->id)
                ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_SUPERVISOR_APPROVED])
                ->count(),
            'approved_this_month' => LeaveRequest::where('staff_id', $staff->id)
                ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_SUPERVISOR_APPROVED])
                ->whereMonth('start_date', now()->month)
                ->count(),
            'total_days_this_month' => LeaveRequest::where('staff_id', $staff->id)
                ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_SUPERVISOR_APPROVED])
                ->whereMonth('start_date', now()->month)
                ->sum('total_days'),
            'upcoming_leaves' => LeaveRequest::where('staff_id', $staff->id)
                ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_SUPERVISOR_APPROVED])
                ->where('start_date', '>', now())
                ->where('start_date', '<=', now()->addDays(7))
                ->count(),
        ];

        // Add departments and user categories for filters in the grid calendar
        $departments = \App\Models\Department::all();
        $userCategories = \App\Models\UserCategory::all();
        return view('admin.hr.ess.my-calendar', compact('staff', 'leaveTypes', 'balances', 'stats', 'departments', 'userCategories'));
    }

    /**
     * Get calendar events for individual user
     */
    public function myCalendarEvents(Request $request)
    {
        $staff = $this->getStaff();

        $query = LeaveRequest::where('staff_id', $staff->id)
            ->whereIn('status', [
                LeaveRequest::STATUS_PENDING,
                LeaveRequest::STATUS_SUPERVISOR_APPROVED,
                LeaveRequest::STATUS_APPROVED
            ])
            ->with('leaveType');

        // Date range filter
        if ($request->filled('start') && $request->filled('end')) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->start, $request->end])
                    ->orWhereBetween('end_date', [$request->start, $request->end])
                    ->orWhere(function ($q2) use ($request) {
                        $q2->where('start_date', '<=', $request->start)
                            ->where('end_date', '>=', $request->end);
                    });
            });
        }

        $leaves = $query->get();

        // Define colors for leave types and statuses
        $leaveTypeColors = [
            'Annual' => '#4facfe',
            'Sick' => '#fa709a',
            'Maternity' => '#f093fb',
            'Paternity' => '#667eea',
            'Compassionate' => '#a8edea',
            'Study' => '#fed6e3',
            'Unpaid' => '#d4fc79',
        ];

        $statusColors = [
            LeaveRequest::STATUS_PENDING => '#ffc107',
            LeaveRequest::STATUS_SUPERVISOR_APPROVED => '#17a2b8',
            LeaveRequest::STATUS_APPROVED => '#28a745',
        ];

        $statusLabels = [
            LeaveRequest::STATUS_PENDING => 'Pending Approval',
            LeaveRequest::STATUS_SUPERVISOR_APPROVED => 'Awaiting HR Approval',
            LeaveRequest::STATUS_APPROVED => 'Approved',
        ];

        $events = [];
        foreach ($leaves as $leave) {
            $color = $statusColors[$leave->status] ?? '#6c757d';
            $leaveTypeName = $leave->leaveType->name ?? 'Leave';
            $typeColor = $leaveTypeColors[$leaveTypeName] ?? '#6c757d';

            $events[] = [
                'id' => $leave->id,
                'title' => $leaveTypeName . ($leave->is_half_day ? ' (½ Day)' : ''),
                'start' => $leave->start_date->format('Y-m-d'),
                'end' => $leave->end_date->addDay()->format('Y-m-d'), // FullCalendar end is exclusive
                'backgroundColor' => $color,
                'borderColor' => $typeColor,
                'textColor' => '#fff',
                'extendedProps' => [
                    'leave_id' => $leave->id,
                    'leave_type' => $leaveTypeName,
                    'start_date' => $leave->start_date->format('M d, Y'),
                    'end_date' => $leave->end_date->format('M d, Y'),
                    'total_days' => $leave->total_days,
                    'status' => $leave->status,
                    'status_label' => $statusLabels[$leave->status] ?? ucfirst($leave->status),
                    'reason' => $leave->reason,
                    'is_half_day' => $leave->is_half_day,
                ],
            ];
        }

        return response()->json($events);
    }

    // ===========================================
    // TEAM LEAVE CALENDAR (For Supervisors/HOD)
    // ===========================================

    /**
     * Team Leave Calendar - View team members' leave
     */
    public function teamCalendar()
    {
        $staff = $this->getStaff();
        $user = auth()->user();

        // Verify user has supervisor privileges
        if (!$staff->is_unit_head && !$staff->is_dept_head) {
            abort(403, 'Access denied. Team calendar is only available for supervisors.');
        }

        // Get team members
        $teamMembers = $this->getTeamMembers($staff, $user);

        // Get leave types for filter
        $leaveTypes = LeaveType::active()->get();

        // Stats
        $teamIds = $teamMembers->pluck('id');
        $stats = [
            'on_leave_today' => LeaveRequest::whereIn('staff_id', $teamIds)
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->count(),
            'pending_approval' => LeaveRequest::whereIn('staff_id', $teamIds)
                ->where('status', LeaveRequest::STATUS_PENDING)
                ->count(),
            'upcoming' => LeaveRequest::whereIn('staff_id', $teamIds)
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->where('start_date', '>', now())
                ->where('start_date', '<=', now()->addDays(14))
                ->count(),
            'team_size' => $teamMembers->count(),
        ];

        return view('admin.hr.ess.team-calendar', compact('staff', 'teamMembers', 'leaveTypes', 'stats'));
    }

    /**
     * Get team calendar events
     */
    public function teamCalendarEvents(Request $request)
    {
        $staff = $this->getStaff();
        $user = auth()->user();

        // Verify user has supervisor privileges
        if (!$staff->is_unit_head && !$staff->is_dept_head) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        // Get team members
        $teamMembers = $this->getTeamMembers($staff, $user);
        $teamIds = $teamMembers->pluck('id');

        $query = LeaveRequest::whereIn('staff_id', $teamIds)
            ->whereIn('status', [
                LeaveRequest::STATUS_PENDING,
                LeaveRequest::STATUS_SUPERVISOR_APPROVED,
                LeaveRequest::STATUS_APPROVED
            ])
            ->with(['leaveType', 'staff.user', 'staff.department']);

        // Date range filter
        if ($request->filled('start') && $request->filled('end')) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->start, $request->end])
                    ->orWhereBetween('end_date', [$request->start, $request->end])
                    ->orWhere(function ($q2) use ($request) {
                        $q2->where('start_date', '<=', $request->start)
                            ->where('end_date', '>=', $request->end);
                    });
            });
        }

        // Leave type filter
        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        // Staff filter
        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leaves = $query->get();

        // Define colors
        $statusColors = [
            LeaveRequest::STATUS_PENDING => '#ffc107',
            LeaveRequest::STATUS_SUPERVISOR_APPROVED => '#17a2b8',
            LeaveRequest::STATUS_APPROVED => '#28a745',
        ];

        $statusLabels = [
            LeaveRequest::STATUS_PENDING => 'Pending Approval',
            LeaveRequest::STATUS_SUPERVISOR_APPROVED => 'Awaiting HR Approval',
            LeaveRequest::STATUS_APPROVED => 'Approved',
        ];

        $events = [];
        foreach ($leaves as $leave) {
            $staffUser = $leave->staff->user ?? null;
            $staffName = $staffUser ? ($staffUser->firstname . ' ' . $staffUser->surname) : 'Unknown';
            $color = $statusColors[$leave->status] ?? '#6c757d';
            $leaveTypeName = $leave->leaveType->name ?? 'Leave';

            $events[] = [
                'id' => $leave->id,
                'title' => $staffName . ' - ' . $leaveTypeName,
                'start' => $leave->start_date->format('Y-m-d'),
                'end' => $leave->end_date->addDay()->format('Y-m-d'), // FullCalendar end is exclusive
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => $leave->status === LeaveRequest::STATUS_PENDING ? '#000' : '#fff',
                'extendedProps' => [
                    'leave_id' => $leave->id,
                    'staff_id' => $leave->staff_id,
                    'staff_name' => $staffName,
                    'employee_id' => $leave->staff->employee_id ?? '',
                    'department' => $leave->staff->department->name ?? '',
                    'leave_type' => $leaveTypeName,
                    'start_date' => $leave->start_date->format('M d, Y'),
                    'end_date' => $leave->end_date->format('M d, Y'),
                    'total_days' => $leave->total_days,
                    'status' => $leave->status,
                    'status_label' => $statusLabels[$leave->status] ?? ucfirst($leave->status),
                    'reason' => $leave->reason,
                    'is_half_day' => $leave->is_half_day,
                    'can_approve' => $leave->status === LeaveRequest::STATUS_PENDING,
                ],
            ];
        }

        return response()->json($events);
    }

    /**
     * Get team members for a supervisor
     */
    protected function getTeamMembers($staff, $user, $includeSelf = true)
    {
        $query = Staff::with('user', 'department')
            ->where(function ($q) use ($staff, $user) {
                if ($staff->is_unit_head) {
                    // Unit heads see their department members
                    $q->where('department_id', $staff->department_id);
                }
                if ($staff->is_dept_head) {
                    // Department heads see same user category (is_admin column in users table)
                    $q->orWhereHas('user', fn($uq) => $uq->where('is_admin', $user->is_admin));
                }
            })
            ->active()
            ->orderBy('employee_id');

        if (!$includeSelf) {
            $query->where('id', '!=', $staff->id);
        }

        return $query->get();
    }

    /**
     * Get team on leave for a specific date
     */
    public function teamOnLeave(Request $request)
    {
        $staff = $this->getStaff();
        $user = auth()->user();

        // Verify user has supervisor privileges
        if (!$staff->is_unit_head && !$staff->is_dept_head) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $date = $request->get('date', now()->toDateString());

        // Get team members
        $teamMembers = $this->getTeamMembers($staff, $user);
        $teamIds = $teamMembers->pluck('id');

        $onLeave = LeaveRequest::whereIn('staff_id', $teamIds)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->with(['staff.user', 'staff.department', 'leaveType'])
            ->get();

        $staffOnLeave = $onLeave->map(function ($leave) {
            $user = $leave->staff->user ?? null;
            return [
                'id' => $leave->staff_id,
                'name' => $user ? ($user->firstname . ' ' . $user->surname) : 'Unknown',
                'employee_id' => $leave->staff->employee_id ?? '',
                'department' => $leave->staff->department->name ?? '',
                'leave_type' => $leave->leaveType->name ?? 'Leave',
                'return_date' => $leave->end_date->addDay()->format('M d, Y'),
            ];
        });

        return response()->json([
            'date' => $date,
            'formatted_date' => Carbon::parse($date)->format('D, M d, Y'),
            'count' => $staffOnLeave->count(),
            'staff' => $staffOnLeave,
        ]);
    }
}
