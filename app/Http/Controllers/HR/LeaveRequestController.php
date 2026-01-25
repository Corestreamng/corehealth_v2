<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\Staff;
use App\Services\LeaveService;
use App\Services\HrAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * HRMS Implementation Plan - Section 7.2
 * Leave Request Controller with Two-Level Approval Workflow:
 * 1. First Level: Unit Head (same department) OR Dept Head (same user category)
 * 2. Second Level: HR Manager (only after first level approved)
 */
class LeaveRequestController extends Controller
{
    protected LeaveService $leaveService;
    protected HrAttachmentService $attachmentService;

    public function __construct(LeaveService $leaveService, HrAttachmentService $attachmentService)
    {
        $this->leaveService = $leaveService;
        $this->attachmentService = $attachmentService;
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        // Handle stats request
        if ($request->has('stats')) {
            return response()->json([
                'pending' => LeaveRequest::whereIn('status', [
                    LeaveRequest::STATUS_PENDING,
                    LeaveRequest::STATUS_SUPERVISOR_APPROVED
                ])->count(),
                'approved' => LeaveRequest::where('status', LeaveRequest::STATUS_APPROVED)->count(),
                'rejected' => LeaveRequest::where('status', LeaveRequest::STATUS_REJECTED)->count(),
                'on_leave_today' => LeaveRequest::where('status', LeaveRequest::STATUS_APPROVED)
                    ->where('start_date', '<=', now()->toDateString())
                    ->where('end_date', '>=', now()->toDateString())
                    ->count(),
            ]);
        }

        $query = LeaveRequest::with(['staff.user', 'leaveType', 'reviewedBy', 'supervisorApprovedBy', 'hrApprovedBy'])
            ->latest();

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }
        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }
        if ($request->filled('date_from')) {
            $query->where('start_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('end_date', '<=', $request->date_to);
        }

        // Handle DataTable AJAX request
        if ($request->ajax() || $request->wantsJson()) {
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $searchValue = $request->input('search.value');

            // Search
            if ($searchValue) {
                $query->where(function($q) use ($searchValue) {
                    $q->whereHas('staff.user', function($q) use ($searchValue) {
                        $q->where('firstname', 'like', "%{$searchValue}%")
                          ->orWhere('surname', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('leaveType', function($q) use ($searchValue) {
                        $q->where('name', 'like', "%{$searchValue}%");
                    })
                    ->orWhere('reason', 'like', "%{$searchValue}%");
                });
            }

            $totalRecords = LeaveRequest::count();
            $filteredRecords = $query->count();

            $leaveRequests = $query->skip($start)->take($length)->get();

            $statusColors = [
                'pending' => 'warning',
                'supervisor_approved' => 'info',
                'approved' => 'success',
                'rejected' => 'danger',
                'cancelled' => 'secondary',
            ];

            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $leaveRequests->map(function($leaveRequest, $index) use ($start, $statusColors) {
                    $statusBadge = $statusColors[$leaveRequest->status] ?? 'secondary';
                    $statusLabel = ucwords(str_replace('_', ' ', $leaveRequest->status));

                    return [
                        'DT_RowIndex' => $start + $index + 1,
                        'staff_name' => $leaveRequest->staff->user->name ?? 'N/A',
                        'leave_type' => $leaveRequest->leaveType->name ?? 'N/A',
                        'period' => \Carbon\Carbon::parse($leaveRequest->start_date)->format('M d') . ' - ' .
                                   \Carbon\Carbon::parse($leaveRequest->end_date)->format('M d, Y'),
                        'days_requested' => $leaveRequest->total_days,
                        'status_badge' => '<span class="badge badge-'.$statusBadge.'">'.$statusLabel.'</span>',
                        'created_at' => $leaveRequest->created_at->diffForHumans(),
                        'action' => '<a href="'.route('hr.leave-requests.show', $leaveRequest).'" class="btn btn-sm btn-primary"><i class="mdi mdi-eye"></i></a>',
                    ];
                })
            ]);
        }

        $leaveRequests = $query->paginate(20);
        $leaveTypes = LeaveType::active()->orderBy('name')->get();
        $staffList = Staff::active()->with('user')->get();

        // Get requests pending supervisor approval (for unit/dept heads)
        $pendingForSupervisor = $this->leaveService->getPendingForSupervisor($user);

        // Get requests pending HR approval (supervisor_approved status)
        $pendingForHr = collect();
        if ($user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'HR MANAGER']) || $user->can('leave-request.hr-approve')) {
            $pendingForHr = $this->leaveService->getPendingForHr();
        }

        return view('admin.hr.leave-requests.index', compact(
            'leaveRequests',
            'leaveTypes',
            'staffList',
            'pendingForSupervisor',
            'pendingForHr'
        ));
    }

    public function create(Request $request)
    {
        $staffId = $request->staff_id;
        $staff = null;

        // If HR is creating for a specific staff member
        if ($staffId && auth()->user()->can('leave-request.create')) {
            $staff = Staff::with('user')->findOrFail($staffId);
        }

        // If staff is creating for themselves
        if (!$staff && auth()->user()->staff_profile) {
            $staff = auth()->user()->staff_profile;
        }

        $leaveTypes = LeaveType::active()->orderBy('name')->get();
        $staffList = Staff::active()->with('user')->orderBy('id')->get();
        $reliefStaff = Staff::active()->with('user')
            ->when($staff, fn($q) => $q->where('id', '!=', $staff->id))
            ->get();

        // Get current balances for the staff
        $balances = [];
        if ($staff) {
            foreach ($leaveTypes as $leaveType) {
                $balance = $this->leaveService->getOrCreateBalance($staff->id, $leaveType->id);
                $balances[$leaveType->id] = $balance;
            }
        }

        return view('admin.hr.leave-requests.create', compact('staff', 'leaveTypes', 'staffList', 'reliefStaff', 'balances'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:staff,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_half_day' => 'nullable|boolean',
            'reason' => 'required|string|max:1000',
            'handover_notes' => 'nullable|string|max:2000',
            'contact_during_leave' => 'nullable|string|max:255',
            'relief_staff_id' => 'nullable|exists:staff,id|different:staff_id',
            'attachments.*' => 'nullable|file|max:5120|mimes:pdf,doc,docx,jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Check if HR is creating this request (skip first stage)
            $user = auth()->user();
            $isHrCreating = $user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'HR MANAGER']) || $user->can('leave-request.hr-approve');

            $leaveRequest = $this->leaveService->createLeaveRequest([
                'staff_id' => $request->staff_id,
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_half_day' => $request->is_half_day ?? false,
                'reason' => $request->reason,
                'handover_notes' => $request->handover_notes,
                'contact_during_leave' => $request->contact_during_leave,
                'relief_staff_id' => $request->relief_staff_id,
            ]);

            // If HR created this request, auto-approve first stage (skip HOD approval)
            if ($isHrCreating) {
                $leaveRequest->update([
                    'status' => LeaveRequest::STATUS_SUPERVISOR_APPROVED,
                    'supervisor_approved_by' => $user->id,
                    'supervisor_approved_at' => now(),
                    'supervisor_comments' => 'Auto-approved (created by HR)',
                ]);
            }

            // Handle attachments
            if ($request->hasFile('attachments')) {
                $this->attachmentService->attachMultiple(
                    $leaveRequest,
                    $request->file('attachments'),
                    ['document_type' => 'leave_supporting_document', 'uploaded_by' => auth()->id()]
                );
            }

            // Handle single document upload
            if ($request->hasFile('document')) {
                $this->attachmentService->attachMultiple(
                    $leaveRequest,
                    [$request->file('document')],
                    ['document_type' => 'leave_supporting_document', 'uploaded_by' => auth()->id()]
                );
            }

            if ($request->ajax() || $request->wantsJson()) {
                $message = $isHrCreating
                    ? 'Leave request created successfully. Awaiting HR final approval.'
                    : 'Leave request submitted successfully. Awaiting supervisor approval.';
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => $leaveRequest
                ]);
            }

            return redirect()->route('hr.leave-requests.show', $leaveRequest)
                ->with('success', 'Leave request submitted successfully. Awaiting supervisor approval.');
        } catch (\Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 422);
            }
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(LeaveRequest $leaveRequest)
    {
        $leaveRequest->load([
            'staff.user',
            'leaveType',
            'reliefStaff.user',
            'reviewedBy',
            'supervisorApprovedBy',
            'hrApprovedBy',
            'attachments.uploadedBy'
        ]);

        // Get staff's balance for this leave type
        $balance = $this->leaveService->getOrCreateBalance(
            $leaveRequest->staff_id,
            $leaveRequest->leave_type_id
        );

        // Determine what actions current user can take
        $user = auth()->user();
        $canSupervisorApprove = $leaveRequest->canBeApprovedBySupervisor($user);
        $canHrApprove = $leaveRequest->canBeApprovedByHr($user);
        $canReject = $canSupervisorApprove || $canHrApprove;

        return view('admin.hr.leave-requests.show', compact(
            'leaveRequest',
            'balance',
            'canSupervisorApprove',
            'canHrApprove',
            'canReject'
        ));
    }

    public function edit(LeaveRequest $leaveRequest)
    {
        if (!$leaveRequest->isPending()) {
            return back()->with('error', 'Only pending requests can be edited.');
        }

        $leaveTypes = LeaveType::active()->orderBy('name')->get();
        $reliefStaff = Staff::active()->with('user')
            ->where('id', '!=', $leaveRequest->staff_id)
            ->get();

        return view('admin.hr.leave-requests.edit', compact('leaveRequest', 'leaveTypes', 'reliefStaff'));
    }

    public function update(Request $request, LeaveRequest $leaveRequest)
    {
        if (!$leaveRequest->isPending()) {
            return back()->with('error', 'Only pending requests can be edited.');
        }

        $validator = Validator::make($request->all(), [
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_half_day' => 'nullable|boolean',
            'reason' => 'required|string|max:1000',
            'handover_notes' => 'nullable|string|max:2000',
            'contact_during_leave' => 'nullable|string|max:255',
            'relief_staff_id' => 'nullable|exists:staff,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Recalculate days
        $totalDays = $this->leaveService->calculateLeaveDays(
            \Carbon\Carbon::parse($request->start_date),
            \Carbon\Carbon::parse($request->end_date)
        );

        // Account for half day
        if ($request->is_half_day && $totalDays == 1) {
            $totalDays = 0.5;
        }

        $leaveRequest->update([
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'total_days' => $totalDays,
            'is_half_day' => $request->is_half_day ?? false,
            'reason' => $request->reason,
            'handover_notes' => $request->handover_notes,
            'contact_during_leave' => $request->contact_during_leave,
            'relief_staff_id' => $request->relief_staff_id,
        ]);

        return redirect()->route('hr.leave-requests.show', $leaveRequest)
            ->with('success', 'Leave request updated successfully.');
    }

    public function destroy(LeaveRequest $leaveRequest)
    {
        if (!$leaveRequest->isPending()) {
            return back()->with('error', 'Only pending requests can be deleted.');
        }

        // Release pending days from balance
        $balance = $this->leaveService->getOrCreateBalance(
            $leaveRequest->staff_id,
            $leaveRequest->leave_type_id
        );
        $balance->decrement('pending_days', $leaveRequest->total_days);

        $leaveRequest->delete();

        return redirect()->route('hr.leave-requests.index')
            ->with('success', 'Leave request deleted successfully.');
    }

    /**
     * First Level Approval - Supervisor (Unit Head / Dept Head)
     */
    public function supervisorApprove(Request $request, LeaveRequest $leaveRequest)
    {
        try {
            $this->leaveService->supervisorApproveRequest(
                $leaveRequest,
                auth()->user(),
                $request->comments
            );

            return back()->with('success', 'Leave request approved. Now pending HR approval.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Second Level Approval - HR Manager (Final Approval)
     */
    public function hrApprove(Request $request, LeaveRequest $leaveRequest)
    {
        try {
            $this->leaveService->hrApproveRequest(
                $leaveRequest,
                auth()->user(),
                $request->comments
            );

            return back()->with('success', 'Leave request approved successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Legacy approve - determines which level to use
     */
    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        try {
            $this->leaveService->approveRequest(
                $leaveRequest,
                auth()->user(),
                $request->comments
            );

            $message = $leaveRequest->fresh()->isSupervisorApproved()
                ? 'Leave request approved by supervisor. Now pending HR approval.'
                : 'Leave request approved successfully.';

            return back()->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->leaveService->rejectRequest(
                $leaveRequest,
                auth()->user(),
                $request->reason
            );

            return back()->with('success', 'Leave request rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(LeaveRequest $leaveRequest)
    {
        // Check ownership if not HR
        if (!auth()->user()->can('leave-request.edit')) {
            if ($leaveRequest->staff_id !== auth()->user()->staff_profile?->id) {
                abort(403, 'You can only cancel your own leave requests.');
            }
        }

        try {
            $this->leaveService->cancelRequest($leaveRequest);
            return back()->with('success', 'Leave request cancelled.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function recall(Request $request, LeaveRequest $leaveRequest)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->leaveService->recallRequest(
                $leaveRequest,
                auth()->user(),
                $request->reason
            );

            return back()->with('success', 'Leave request has been recalled.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
