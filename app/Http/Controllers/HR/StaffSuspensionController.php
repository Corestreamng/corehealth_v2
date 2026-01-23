<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\DisciplinaryQuery;
use App\Models\HR\StaffSuspension;
use App\Models\Staff;
use App\Services\DisciplinaryService;
use App\Services\HrAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * HRMS Implementation Plan - Section 7.2
 * Staff Suspension Controller
 */
class StaffSuspensionController extends Controller
{
    protected DisciplinaryService $disciplinaryService;
    protected HrAttachmentService $attachmentService;

    public function __construct(DisciplinaryService $disciplinaryService, HrAttachmentService $attachmentService)
    {
        $this->disciplinaryService = $disciplinaryService;
        $this->attachmentService = $attachmentService;
    }

    public function index(Request $request)
    {
        $query = StaffSuspension::with(['staff.user', 'issuedBy', 'disciplinaryQuery'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        $suspensions = $query->paginate(20);
        $staffList = Staff::with('user')->get();

        return view('admin.hr.suspensions.index', compact('suspensions', 'staffList'));
    }

    public function create(Request $request)
    {
        $staffList = Staff::active()->with('user')->get();
        $selectedStaff = $request->staff_id ? Staff::with('user')->find($request->staff_id) : null;
        $queries = DisciplinaryQuery::where('status', '!=', DisciplinaryQuery::STATUS_CLOSED)
            ->whereDoesntHave('suspension')
            ->with('staff.user')
            ->get();

        return view('admin.hr.suspensions.create', compact('staffList', 'selectedStaff', 'queries'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:staff,id',
            'disciplinary_query_id' => 'nullable|exists:disciplinary_queries,id',
            'type' => 'required|in:paid,unpaid',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'reason' => 'required|string|max:1000',
            'suspension_message' => 'nullable|string|max:500',
            'attachments.*' => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $suspension = $this->disciplinaryService->createSuspension($request->all(), auth()->user());

            // Handle attachments
            if ($request->hasFile('attachments')) {
                $this->attachmentService->attachMultiple(
                    $suspension,
                    $request->file('attachments'),
                    ['document_type' => 'suspension_letter', 'uploaded_by' => auth()->id()]
                );
            }

            return redirect()->route('hr.suspensions.show', $suspension)
                ->with('success', 'Staff member has been suspended. They will not be able to log in until the suspension is lifted.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(StaffSuspension $suspension)
    {
        $suspension->load([
            'staff.user',
            'issuedBy',
            'liftedBy',
            'disciplinaryQuery',
            'attachments.uploadedBy'
        ]);

        return view('admin.hr.suspensions.show', compact('suspension'));
    }

    public function lift(Request $request, StaffSuspension $suspension)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->disciplinaryService->liftSuspension($suspension, auth()->user(), $request->reason);

            return redirect()->route('hr.suspensions.show', $suspension)
                ->with('success', 'Suspension has been lifted. Staff member can now log in.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
