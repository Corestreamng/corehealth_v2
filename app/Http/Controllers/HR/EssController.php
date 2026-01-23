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
use Illuminate\Http\Request;
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

        // Pending team approvals count (for supervisors)
        $pendingTeamApprovalsCount = 0;
        if ($staff->is_unit_head || $staff->is_dept_head) {
            $pendingTeamApprovalsCount = LeaveRequest::canApproveAsSupervisor($staff)
                ->where('status', LeaveRequest::STATUS_PENDING)
                ->count();
        }

        return view('admin.hr.ess.index', compact(
            'staff',
            'leaveTypes',
            'balances',
            'recentLeaveRequests',
            'pendingQueries',
            'recentPayslips',
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

        return view('admin.hr.ess.my-leave', compact('staff', 'leaveRequests', 'leaveTypes', 'balances'));
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

        $validator = Validator::make($request->all(), [
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
            'handover_notes' => 'nullable|string|max:1000',
            'relief_staff_id' => 'nullable|exists:staff,id|different:' . $staff->id,
            'attachments.*' => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $leaveRequest = $this->leaveService->createLeaveRequest([
                'staff_id' => $staff->id,
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'reason' => $request->reason,
                'handover_notes' => $request->handover_notes,
                'relief_staff_id' => $request->relief_staff_id,
            ]);

            // Handle attachments
            if ($request->hasFile('attachments')) {
                $this->attachmentService->attachMultiple(
                    $leaveRequest,
                    $request->file('attachments'),
                    ['document_type' => 'leave_document', 'uploaded_by' => auth()->id()]
                );
            }

            return redirect()->route('hr.ess.my-leave')
                ->with('success', 'Leave request submitted successfully. Awaiting approval.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
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

        return view('admin.hr.ess.my-payslips', compact('staff', 'payslips', 'years'));
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
        $staff->load('user', 'specialization', 'clinic');

        return view('admin.hr.ess.my-profile', compact('staff'));
    }

    /**
     * Update Profile (limited fields)
     */
    public function updateProfile(Request $request)
    {
        $staff = $this->getStaff();

        $validator = Validator::make($request->all(), [
            'phone_number' => 'nullable|string|max:20',
            'home_address' => 'nullable|string|max:500',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $staff->update($request->only([
            'phone_number',
            'home_address',
            'emergency_contact_name',
            'emergency_contact_phone',
            'emergency_contact_relationship',
        ]));

        return back()->with('success', 'Profile updated successfully.');
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

        $query = LeaveRequest::with(['staff.user', 'staff.specialization', 'leaveType'])
            ->canApproveAsSupervisor($staff)
            ->latest();

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Default to pending requests
            $query->where('status', LeaveRequest::STATUS_PENDING);
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        $pendingRequests = $query->paginate(15);

        // Get counts for tabs
        $pendingCount = LeaveRequest::canApproveAsSupervisor($staff)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->count();
        $approvedCount = LeaveRequest::canApproveAsSupervisor($staff)
            ->where('status', LeaveRequest::STATUS_SUPERVISOR_APPROVED)
            ->count();

        $leaveTypes = LeaveType::active()->orderBy('name')->get();

        return view('admin.hr.ess.team-approvals', compact(
            'staff',
            'pendingRequests',
            'pendingCount',
            'approvedCount',
            'leaveTypes'
        ));
    }

    /**
     * Show Team Leave Request Details
     */
    public function showTeamLeaveRequest(LeaveRequest $leaveRequest)
    {
        $staff = $this->getStaff();

        // Verify this request can be approved by this supervisor
        if (!$leaveRequest->canBeApprovedBySupervisor($staff)) {
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

        // Verify this request can be approved by this supervisor
        if (!$leaveRequest->canBeApprovedBySupervisor($staff)) {
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

        // Verify this request can be rejected by this supervisor
        if (!$leaveRequest->canBeApprovedBySupervisor($staff)) {
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
}
