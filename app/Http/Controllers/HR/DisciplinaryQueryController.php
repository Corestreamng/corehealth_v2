<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\DisciplinaryQuery;
use App\Models\Staff;
use App\Services\DisciplinaryService;
use App\Services\HrAttachmentService;
use Carbon\Carbon;
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
        // Handle stats request
        if ($request->has('stats')) {
            return response()->json([
                'pending' => DisciplinaryQuery::where('status', DisciplinaryQuery::STATUS_ISSUED)->count(),
                'reviewed' => DisciplinaryQuery::whereIn('status', [DisciplinaryQuery::STATUS_RESPONSE_RECEIVED, DisciplinaryQuery::STATUS_UNDER_REVIEW])->count(),
                'warnings' => DisciplinaryQuery::whereIn('outcome', ['warning', 'final_warning'])->count(),
                'dismissals' => DisciplinaryQuery::where('outcome', 'termination')->count(),
            ]);
        }

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

        // Handle DataTable AJAX request
        if ($request->ajax()) {
            return datatables()->of($query)
                ->addIndexColumn()
                ->addColumn('staff_name', function ($q) {
                    $user = $q->staff->user ?? null;
                    return $user ? $user->firstname . ' ' . $user->surname : 'N/A';
                })
                ->addColumn('severity_badge', function ($q) {
                    $colors = [
                        'minor' => 'info',
                        'moderate' => 'warning',
                        'major' => 'danger',
                        'critical' => 'dark'
                    ];
                    $color = $colors[$q->severity] ?? 'secondary';
                    return '<span class="badge badge-' . $color . '">' . ucfirst($q->severity) . '</span>';
                })
                ->addColumn('status_badge', function ($q) {
                    $colors = [
                        DisciplinaryQuery::STATUS_ISSUED => 'warning',
                        DisciplinaryQuery::STATUS_RESPONSE_RECEIVED => 'info',
                        DisciplinaryQuery::STATUS_UNDER_REVIEW => 'primary',
                        DisciplinaryQuery::STATUS_CLOSED => 'secondary'
                    ];
                    $color = $colors[$q->status] ?? 'secondary';
                    $label = str_replace('_', ' ', ucfirst($q->status));
                    return '<span class="badge badge-' . $color . '">' . $label . '</span>';
                })
                ->addColumn('outcome_badge', function ($q) {
                    if (!$q->outcome) return '<span class="text-muted">-</span>';
                    $colors = [
                        'warning' => 'warning',
                        'final_warning' => 'orange',
                        'suspension' => 'danger',
                        'termination' => 'dark',
                        'exonerated' => 'success',
                        'no_action' => 'secondary'
                    ];
                    $color = $colors[$q->outcome] ?? 'secondary';
                    $label = str_replace('_', ' ', ucfirst($q->outcome));
                    return '<span class="badge badge-' . $color . '">' . $label . '</span>';
                })
                ->editColumn('created_at', function ($q) {
                    return $q->created_at->format('d M Y');
                })
                ->addColumn('action', function ($q) {
                    $viewBtn = '<button type="button" class="btn btn-sm btn-info view-btn mr-1" data-id="' . $q->id . '" title="View"><i class="mdi mdi-eye"></i></button>';
                    $editBtn = '<button type="button" class="btn btn-sm btn-primary edit-btn mr-1" data-id="' . $q->id . '" title="Edit"><i class="mdi mdi-pencil"></i></button>';
                    $deleteBtn = '<button type="button" class="btn btn-sm btn-danger delete-btn" data-id="' . $q->id . '" title="Delete"><i class="mdi mdi-delete"></i></button>';
                    return $viewBtn . $editBtn . $deleteBtn;
                })
                ->rawColumns(['severity_badge', 'status_badge', 'outcome_badge', 'action'])
                ->make(true);
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
            if ($request->ajax()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
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

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Disciplinary query issued successfully.',
                    'query' => $query->load(['staff.user', 'issuedBy'])
                ]);
            }

            return redirect()->route('hr.disciplinary.index')
                ->with('success', 'Disciplinary query issued successfully.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(Request $request, DisciplinaryQuery $disciplinaryQuery)
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

        // Return JSON for AJAX requests
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'query' => $disciplinaryQuery,
                'outcomes' => $outcomes
            ]);
        }

        return view('admin.hr.disciplinary.show', compact('disciplinaryQuery', 'outcomes'));
    }

    public function edit(Request $request, DisciplinaryQuery $disciplinaryQuery)
    {
        if ($disciplinaryQuery->status === DisciplinaryQuery::STATUS_CLOSED) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Closed queries cannot be edited.'], 403);
            }
            return back()->with('error', 'Closed queries cannot be edited.');
        }

        $severities = DisciplinaryQuery::getSeverities();

        // Return JSON for AJAX requests
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'query' => $disciplinaryQuery->load(['staff.user']),
                'severities' => $severities
            ]);
        }

        return view('admin.hr.disciplinary.edit', compact('disciplinaryQuery', 'severities'));
    }

    public function update(Request $request, DisciplinaryQuery $disciplinaryQuery)
    {
        if ($disciplinaryQuery->status === DisciplinaryQuery::STATUS_CLOSED) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Closed queries cannot be edited.'], 403);
            }
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
            if ($request->ajax()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $disciplinaryQuery->update($request->only([
            'subject', 'description', 'severity', 'incident_date',
            'expected_response', 'response_deadline'
        ]));

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Disciplinary query updated.',
                'query' => $disciplinaryQuery->load(['staff.user', 'issuedBy'])
            ]);
        }

        return redirect()->route('hr.disciplinary.index')
            ->with('success', 'Disciplinary query updated.');
    }

    public function destroy(Request $request, DisciplinaryQuery $disciplinaryQuery)
    {
        if ($disciplinaryQuery->status !== DisciplinaryQuery::STATUS_ISSUED) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Only newly issued queries can be deleted.'], 403);
            }
            return back()->with('error', 'Only newly issued queries can be deleted.');
        }

        $disciplinaryQuery->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Disciplinary query deleted.'
            ]);
        }

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
        // Check if query can have a decision (must have received response)
        if (!in_array($disciplinaryQuery->status, [DisciplinaryQuery::STATUS_RESPONSE_RECEIVED, DisciplinaryQuery::STATUS_UNDER_REVIEW])) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'This query is not ready for a decision.'], 400);
            }
            return back()->with('error', 'This query is not ready for a decision.');
        }

        $rules = [
            'hr_decision' => 'required|string|max:5000',
            'outcome' => 'required|in:' . implode(',', array_keys(DisciplinaryQuery::getOutcomes())),
        ];

        // Add conditional rules for suspension
        if ($request->outcome === 'suspension') {
            $rules['suspension_type'] = 'required|in:paid,unpaid';
            $rules['suspension_days'] = 'required|integer|min:1|max:365';
            $rules['suspension_start_date'] = 'required|date';
            $rules['suspension_end_date'] = 'nullable|date|after_or_equal:suspension_start_date';
            $rules['suspension_message'] = 'nullable|string|max:500';
        }

        // Add conditional rules for termination
        if ($request->outcome === 'termination') {
            $rules['termination_type'] = 'required|in:voluntary,involuntary,retirement,death,contract_end';
            $rules['termination_reason_category'] = 'required|in:resignation,misconduct,poor_performance,redundancy,retirement,medical,death,contract_expiry,other';
            $rules['termination_notice_date'] = 'required|date';
            $rules['termination_last_working_day'] = 'required|date|after_or_equal:termination_notice_date';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Update the disciplinary query with the decision
            $disciplinaryQuery->update([
                'hr_decision' => $request->hr_decision,
                'outcome' => $request->outcome,
                'decided_by' => auth()->id(),
                'decided_at' => now(),
                'status' => DisciplinaryQuery::STATUS_CLOSED,
            ]);

            $message = 'Decision recorded successfully.';

            // Handle suspension creation if outcome is suspension
            if ($request->outcome === 'suspension') {
                $startDate = $request->suspension_start_date;
                $endDate = $request->suspension_end_date;

                // If end date not provided, calculate from days
                if (!$endDate && $request->suspension_days) {
                    $endDate = Carbon::parse($startDate)->addDays($request->suspension_days)->format('Y-m-d');
                }

                // Create suspension record - this will trigger the model's created event
                // which updates the staff employment status
                \App\Models\HR\StaffSuspension::create([
                    'staff_id' => $disciplinaryQuery->staff_id,
                    'disciplinary_query_id' => $disciplinaryQuery->id,
                    'type' => $request->suspension_type,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'reason' => $disciplinaryQuery->subject . ': ' . $request->hr_decision,
                    'suspension_message' => $request->suspension_message ?? 'Your account has been suspended due to disciplinary action. Please contact HR.',
                    'issued_by' => auth()->id(),
                    'status' => \App\Models\HR\StaffSuspension::STATUS_ACTIVE,
                ]);

                $message = 'Decision recorded and suspension applied successfully. Staff member cannot log in until suspension is lifted.';
            }

            // Handle termination creation if outcome is termination
            if ($request->outcome === 'termination') {
                // Create termination record - this will trigger the model's created event
                // which updates the staff employment status and deactivates user account
                \App\Models\HR\StaffTermination::create([
                    'staff_id' => $disciplinaryQuery->staff_id,
                    'disciplinary_query_id' => $disciplinaryQuery->id,
                    'type' => $request->termination_type,
                    'reason_category' => $request->termination_reason_category,
                    'reason_details' => $disciplinaryQuery->subject . ': ' . $request->hr_decision,
                    'notice_date' => $request->termination_notice_date,
                    'effective_date' => $request->termination_last_working_day,
                    'last_working_day' => $request->termination_last_working_day,
                    'exit_interview_conducted' => false,
                    'clearance_completed' => false,
                    'final_payment_processed' => false,
                    'processed_by' => auth()->id(),
                ]);

                $message = 'Decision recorded and termination processed. Staff member\'s access has been revoked.';
            }

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message
                ]);
            }

            return redirect()->route('hr.disciplinary.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }
}
