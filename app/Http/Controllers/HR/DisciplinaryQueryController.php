<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\DisciplinaryQuery;
use App\Models\Staff;
use App\Services\DisciplinaryService;
use App\Services\HrAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * HRMS Implementation Plan - Section 7.2
 * Disciplinary Query Controller
 */
class DisciplinaryQueryController extends Controller
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
        $query = DisciplinaryQuery::with(['staff.user', 'issuedBy', 'decidedBy'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }
        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        $queries = $query->paginate(20);
        $staffList = Staff::with('user')->get();
        $severities = DisciplinaryQuery::getSeverities();

        return view('admin.hr.disciplinary.index', compact('queries', 'staffList', 'severities'));
    }

    public function create(Request $request)
    {
        $staffList = Staff::active()->with('user')->get();
        $selectedStaff = $request->staff_id ? Staff::with('user')->find($request->staff_id) : null;
        $severities = DisciplinaryQuery::getSeverities();

        return view('admin.hr.disciplinary.create', compact('staffList', 'selectedStaff', 'severities'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:staff,id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'severity' => 'required|in:' . implode(',', array_keys(DisciplinaryQuery::getSeverities())),
            'incident_date' => 'nullable|date|before_or_equal:today',
            'expected_response' => 'nullable|string|max:2000',
            'response_deadline' => 'nullable|date|after:today',
            'attachments.*' => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $query = $this->disciplinaryService->createQuery($request->all(), auth()->user());

            // Handle attachments
            if ($request->hasFile('attachments')) {
                $this->attachmentService->attachMultiple(
                    $query,
                    $request->file('attachments'),
                    ['document_type' => 'query_response', 'uploaded_by' => auth()->id()]
                );
            }

            return redirect()->route('hr.disciplinary.show', $query)
                ->with('success', 'Disciplinary query issued successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(DisciplinaryQuery $disciplinaryQuery)
    {
        $disciplinaryQuery->load([
            'staff.user',
            'issuedBy',
            'decidedBy',
            'suspension',
            'termination',
            'attachments.uploadedBy'
        ]);

        $outcomes = DisciplinaryQuery::getOutcomes();

        return view('admin.hr.disciplinary.show', compact('disciplinaryQuery', 'outcomes'));
    }

    public function edit(DisciplinaryQuery $disciplinaryQuery)
    {
        if ($disciplinaryQuery->status === DisciplinaryQuery::STATUS_CLOSED) {
            return back()->with('error', 'Closed queries cannot be edited.');
        }

        $severities = DisciplinaryQuery::getSeverities();

        return view('admin.hr.disciplinary.edit', compact('disciplinaryQuery', 'severities'));
    }

    public function update(Request $request, DisciplinaryQuery $disciplinaryQuery)
    {
        if ($disciplinaryQuery->status === DisciplinaryQuery::STATUS_CLOSED) {
            return back()->with('error', 'Closed queries cannot be edited.');
        }

        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'severity' => 'required|in:' . implode(',', array_keys(DisciplinaryQuery::getSeverities())),
            'incident_date' => 'nullable|date',
            'expected_response' => 'nullable|string|max:2000',
            'response_deadline' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $disciplinaryQuery->update($request->only([
            'subject', 'description', 'severity', 'incident_date',
            'expected_response', 'response_deadline'
        ]));

        return redirect()->route('hr.disciplinary.show', $disciplinaryQuery)
            ->with('success', 'Disciplinary query updated.');
    }

    public function destroy(DisciplinaryQuery $disciplinaryQuery)
    {
        if ($disciplinaryQuery->status !== DisciplinaryQuery::STATUS_ISSUED) {
            return back()->with('error', 'Only newly issued queries can be deleted.');
        }

        $disciplinaryQuery->delete();

        return redirect()->route('hr.disciplinary.index')
            ->with('success', 'Disciplinary query deleted.');
    }

    public function respond(Request $request, DisciplinaryQuery $disciplinaryQuery)
    {
        $request->validate([
            'response' => 'required|string|max:5000',
            'attachments.*' => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png',
        ]);

        // Verify ownership if staff is responding
        $staff = auth()->user()->staff;
        if ($staff && $disciplinaryQuery->staff_id !== $staff->id) {
            abort(403, 'You can only respond to your own queries.');
        }

        try {
            $this->disciplinaryService->recordResponse($disciplinaryQuery, $request->response);

            // Handle attachments
            if ($request->hasFile('attachments')) {
                $this->attachmentService->attachMultiple(
                    $disciplinaryQuery,
                    $request->file('attachments'),
                    ['document_type' => 'query_response', 'uploaded_by' => auth()->id()]
                );
            }

            return back()->with('success', 'Response submitted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function decide(Request $request, DisciplinaryQuery $disciplinaryQuery)
    {
        $validator = Validator::make($request->all(), [
            'hr_decision' => 'required|string|max:5000',
            'outcome' => 'required|in:' . implode(',', array_keys(DisciplinaryQuery::getOutcomes())),
            // Suspension fields (if outcome is suspension)
            'suspension_type' => 'required_if:outcome,suspension|in:paid,unpaid',
            'suspension_start' => 'required_if:outcome,suspension|date',
            'suspension_end' => 'nullable|date|after:suspension_start',
            'suspension_message' => 'nullable|string|max:500',
            // Termination fields (if outcome is termination)
            'termination_date' => 'required_if:outcome,termination|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $this->disciplinaryService->processDecision($disciplinaryQuery, $request->all(), auth()->user());

            $message = 'Decision recorded.';
            if ($request->outcome === 'suspension') {
                $message .= ' Suspension has been applied.';
            } elseif ($request->outcome === 'termination') {
                $message .= ' Termination has been processed.';
            }

            return redirect()->route('hr.disciplinary.show', $disciplinaryQuery)
                ->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
