<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\DisciplinaryQuery;
use App\Models\HR\StaffTermination;
use App\Models\Staff;
use App\Services\DisciplinaryService;
use App\Services\HrAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * HRMS Implementation Plan - Section 7.2
 * Staff Termination Controller
 */
class StaffTerminationController extends Controller
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
        $query = StaffTermination::with(['staff.user', 'processedBy', 'disciplinaryQuery'])
            ->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('reason_category')) {
            $query->where('reason_category', $request->reason_category);
        }
        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        $terminations = $query->paginate(20);
        $staffList = Staff::with('user')->get();
        $types = StaffTermination::getTypes();
        $reasonCategories = StaffTermination::getReasonCategories();

        return view('admin.hr.terminations.index', compact('terminations', 'staffList', 'types', 'reasonCategories'));
    }

    public function create(Request $request)
    {
        $staffList = Staff::active()->with('user')->get();
        $selectedStaff = $request->staff_id ? Staff::with('user')->find($request->staff_id) : null;
        $types = StaffTermination::getTypes();
        $reasonCategories = StaffTermination::getReasonCategories();

        return view('admin.hr.terminations.create', compact('staffList', 'selectedStaff', 'types', 'reasonCategories'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:staff,id',
            'type' => 'required|in:' . implode(',', array_keys(StaffTermination::getTypes())),
            'reason_category' => 'required|in:' . implode(',', array_keys(StaffTermination::getReasonCategories())),
            'reason_details' => 'nullable|string|max:2000',
            'notice_date' => 'required|date',
            'effective_date' => 'required|date|after_or_equal:notice_date',
            'last_working_day' => 'nullable|date|before_or_equal:effective_date',
            'exit_interview_conducted' => 'boolean',
            'exit_interview_notes' => 'nullable|string|max:2000',
            'clearance_completed' => 'boolean',
            'final_payment_processed' => 'boolean',
            'attachments.*' => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $termination = $this->disciplinaryService->createTermination(
                array_merge($request->all(), [
                    'exit_interview_conducted' => $request->boolean('exit_interview_conducted'),
                    'clearance_completed' => $request->boolean('clearance_completed'),
                    'final_payment_processed' => $request->boolean('final_payment_processed'),
                ]),
                auth()->user()
            );

            // Handle attachments
            if ($request->hasFile('attachments')) {
                $this->attachmentService->attachMultiple(
                    $termination,
                    $request->file('attachments'),
                    ['document_type' => 'termination_letter', 'uploaded_by' => auth()->id()]
                );
            }

            return redirect()->route('hr.terminations.show', $termination)
                ->with('success', 'Termination has been processed. Staff member\'s access has been revoked.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(StaffTermination $termination)
    {
        $termination->load([
            'staff.user',
            'processedBy',
            'disciplinaryQuery',
            'attachments.uploadedBy'
        ]);

        return view('admin.hr.terminations.show', compact('termination'));
    }

    public function edit(StaffTermination $termination)
    {
        $types = StaffTermination::getTypes();
        $reasonCategories = StaffTermination::getReasonCategories();

        return view('admin.hr.terminations.edit', compact('termination', 'types', 'reasonCategories'));
    }

    public function update(Request $request, StaffTermination $termination)
    {
        $validator = Validator::make($request->all(), [
            'reason_details' => 'nullable|string|max:2000',
            'last_working_day' => 'nullable|date',
            'exit_interview_conducted' => 'boolean',
            'exit_interview_notes' => 'nullable|string|max:2000',
            'clearance_completed' => 'boolean',
            'final_payment_processed' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $termination->update([
            'reason_details' => $request->reason_details,
            'last_working_day' => $request->last_working_day,
            'exit_interview_conducted' => $request->boolean('exit_interview_conducted'),
            'exit_interview_notes' => $request->exit_interview_notes,
            'clearance_completed' => $request->boolean('clearance_completed'),
            'final_payment_processed' => $request->boolean('final_payment_processed'),
        ]);

        return redirect()->route('hr.terminations.show', $termination)
            ->with('success', 'Termination record updated.');
    }
}
