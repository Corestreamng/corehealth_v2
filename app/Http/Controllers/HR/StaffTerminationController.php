<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\HR\DisciplinaryQuery;
use App\Models\HR\StaffTermination;
use App\Models\Staff;
use App\Services\DisciplinaryService;
use App\Services\HrAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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
        // Handle stats request
        if ($request->has('stats')) {
            return response()->json([
                'pending' => StaffTermination::where('clearance_completed', false)
                    ->orWhere('final_payment_processed', false)
                    ->count(),
                'resigned' => StaffTermination::where('type', StaffTermination::TYPE_VOLUNTARY)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'dismissed' => StaffTermination::where('type', StaffTermination::TYPE_INVOLUNTARY)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'total' => StaffTermination::whereYear('created_at', now()->year)->count(),
            ]);
        }

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
        if ($request->filled('status')) {
            if ($request->status === 'pending') {
                $query->where(function($q) {
                    $q->where('clearance_completed', false)
                      ->orWhere('final_payment_processed', false);
                });
            } elseif ($request->status === 'completed') {
                $query->where('clearance_completed', true)
                      ->where('final_payment_processed', true);
            }
        }

        // Handle DataTable AJAX request
        if ($request->ajax()) {
            return datatables()->of($query)
                ->addIndexColumn()
                ->addColumn('staff_name', function ($t) {
                    $user = $t->staff->user ?? null;
                    return $user ? $user->firstname . ' ' . $user->surname : 'N/A';
                })
                ->addColumn('termination_type_badge', function ($t) {
                    $colors = [
                        'voluntary' => 'info',
                        'involuntary' => 'danger',
                        'retirement' => 'success',
                        'death' => 'dark',
                        'contract_end' => 'secondary'
                    ];
                    $color = $colors[$t->type] ?? 'secondary';
                    return '<span class="badge badge-' . $color . '">' . ucfirst(str_replace('_', ' ', $t->type)) . '</span>';
                })
                ->editColumn('last_working_day', function ($t) {
                    return $t->last_working_day ? Carbon::parse($t->last_working_day)->format('d M Y') : '-';
                })
                ->addColumn('exit_interview', function ($t) {
                    return $t->exit_interview_conducted
                        ? '<span class="badge badge-success">Done</span>'
                        : '<span class="badge badge-warning">Pending</span>';
                })
                ->addColumn('clearance', function ($t) {
                    return $t->clearance_completed
                        ? '<span class="badge badge-success">Done</span>'
                        : '<span class="badge badge-warning">Pending</span>';
                })
                ->addColumn('status_badge', function ($t) {
                    $completed = $t->clearance_completed && $t->final_payment_processed;
                    return $completed
                        ? '<span class="badge badge-success">Completed</span>'
                        : '<span class="badge badge-warning">Pending</span>';
                })
                ->addColumn('status', function ($t) {
                    return ($t->clearance_completed && $t->final_payment_processed) ? 'completed' : 'pending';
                })
                ->addColumn('action', function ($t) {
                    $viewBtn = '<button type="button" class="btn btn-sm btn-info view-btn mr-1" data-id="' . $t->id . '" title="View"><i class="mdi mdi-eye"></i></button>';
                    $completeBtn = '';
                    if (!$t->clearance_completed || !$t->final_payment_processed) {
                        $completeBtn = '<button type="button" class="btn btn-sm btn-success complete-btn" data-id="' . $t->id . '" title="Complete Exit"><i class="mdi mdi-check-circle"></i></button>';
                    }
                    return $viewBtn . $completeBtn;
                })
                ->rawColumns(['termination_type_badge', 'exit_interview', 'clearance', 'status_badge', 'action'])
                ->make(true);
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
            'termination_type' => 'required|in:' . implode(',', array_keys(StaffTermination::getTypes())),
            'reason' => 'required|string|max:2000',
            'notice_date' => 'required|date',
            'last_working_day' => 'required|date|after_or_equal:notice_date',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            $termination = StaffTermination::create([
                'staff_id' => $request->staff_id,
                'disciplinary_query_id' => $request->disciplinary_query_id,
                'type' => $request->termination_type,
                'reason_category' => $request->termination_type === 'voluntary' ? 'resignation' : 'misconduct',
                'reason_details' => $request->reason,
                'notice_date' => $request->notice_date,
                'effective_date' => $request->last_working_day,
                'last_working_day' => $request->last_working_day,
                'exit_interview_conducted' => $request->exit_interview_scheduled ? false : null,
                'clearance_completed' => false,
                'final_payment_processed' => false,
                'processed_by' => auth()->id(),
            ]);

            // Handle attachments
            if ($request->hasFile('attachments')) {
                $this->attachmentService->attachMultiple(
                    $termination,
                    $request->file('attachments'),
                    ['document_type' => 'termination_letter', 'uploaded_by' => auth()->id()]
                );
            }

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Termination has been processed. Staff member\'s access has been revoked.',
                    'termination' => $termination->load(['staff.user', 'processedBy'])
                ]);
            }

            return redirect()->route('hr.terminations.show', $termination)
                ->with('success', 'Termination has been processed. Staff member\'s access has been revoked.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(Request $request, StaffTermination $termination)
    {
        $termination->load([
            'staff.user',
            'processedBy',
            'disciplinaryQuery',
            'attachments.uploadedBy'
        ]);

        // Return JSON for AJAX requests
        if ($request->ajax()) {
            $user = $termination->staff->user ?? null;
            $types = StaffTermination::getTypes();
            $colors = [
                'voluntary' => 'info',
                'involuntary' => 'danger',
                'retirement' => 'success',
                'death' => 'dark',
                'contract_end' => 'secondary'
            ];
            $color = $colors[$termination->type] ?? 'secondary';

            return response()->json([
                'success' => true,
                'id' => $termination->id,
                'termination_number' => $termination->termination_number,
                'staff_name' => $user ? $user->firstname . ' ' . $user->surname : 'N/A',
                'employee_id' => $termination->staff->employee_id ?? '-',
                'termination_type' => $termination->type,
                'termination_type_badge' => '<span class="badge badge-' . $color . '">' . ($types[$termination->type] ?? ucfirst($termination->type)) . '</span>',
                'reason_category' => $termination->reason_category,
                'reason' => $termination->reason_details,
                'notice_date' => $termination->notice_date ? Carbon::parse($termination->notice_date)->format('d M Y') : '-',
                'effective_date' => $termination->effective_date ? Carbon::parse($termination->effective_date)->format('d M Y') : '-',
                'last_working_day' => $termination->last_working_day ? Carbon::parse($termination->last_working_day)->format('d M Y') : '-',
                'exit_interview_conducted' => $termination->exit_interview_conducted,
                'exit_interview_notes' => $termination->exit_interview_notes,
                'clearance_completed' => $termination->clearance_completed,
                'final_payment_processed' => $termination->final_payment_processed,
                'is_eligible_for_severance' => $termination->is_eligible_for_severance ?? false,
                'severance_amount' => $termination->severance_amount ?? 0,
                'status' => ($termination->clearance_completed && $termination->final_payment_processed) ? 'completed' : 'pending',
                'status_badge' => ($termination->clearance_completed && $termination->final_payment_processed)
                    ? '<span class="badge badge-success">Completed</span>'
                    : '<span class="badge badge-warning">Pending</span>',
                'processed_by' => $termination->processedBy ? $termination->processedBy->firstname . ' ' . $termination->processedBy->surname : '-',
                'created_at' => $termination->created_at->format('d M Y H:i'),
                'disciplinary_query' => $termination->disciplinaryQuery ? [
                    'id' => $termination->disciplinaryQuery->id,
                    'query_number' => $termination->disciplinaryQuery->query_number,
                    'subject' => $termination->disciplinaryQuery->subject
                ] : null,
            ]);
        }

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
            if ($request->ajax()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            $termination->update([
                'reason_details' => $request->reason_details ?? $termination->reason_details,
                'last_working_day' => $request->last_working_day ?? $termination->last_working_day,
                'exit_interview_conducted' => $request->boolean('exit_interview_conducted'),
                'exit_interview_notes' => $request->exit_interview_notes,
                'clearance_completed' => $request->boolean('clearance_completed'),
                'final_payment_processed' => $request->boolean('final_payment_processed'),
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Termination record updated.'
                ]);
            }

            return redirect()->route('hr.terminations.show', $termination)
                ->with('success', 'Termination record updated.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function complete(Request $request, StaffTermination $termination)
    {
        $validator = Validator::make($request->all(), [
            'clearance_completed' => 'boolean',
            'exit_interview_conducted' => 'boolean',
            'exit_interview_notes' => 'nullable|string|max:2000',
            'final_payment_processed' => 'boolean',
            'final_settlement_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            $updateData = [
                'clearance_completed' => $request->boolean('clearance_completed'),
                'exit_interview_conducted' => $request->boolean('exit_interview_conducted'),
                'exit_interview_notes' => $request->exit_interview_notes,
                'final_payment_processed' => $request->boolean('final_payment_processed'),
            ];

            // If final settlement amount is provided and payment is being processed, update it
            if ($request->filled('final_settlement_amount') && $request->final_settlement_amount > 0) {
                $updateData['severance_amount'] = $request->final_settlement_amount;
            }

            $termination->update($updateData);

            // Create pending expense entry if final settlement amount is provided
            if ($request->boolean('final_payment_processed') && $request->filled('final_settlement_amount') && $request->final_settlement_amount > 0) {
                $this->createSettlementExpense($termination, $request->final_settlement_amount);
            }

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Exit process updated successfully.'
                ]);
            }

            return redirect()->route('hr.terminations.show', $termination)
                ->with('success', 'Exit process updated successfully.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Create a pending expense entry for staff final settlement
     */
    protected function createSettlementExpense(StaffTermination $termination, float $amount): Expense
    {
        $termination->load('staff.user');
        $staffName = $termination->staff->user
            ? $termination->staff->user->firstname . ' ' . $termination->staff->user->surname
            : 'Staff #' . $termination->staff_id;

        // Generate expense number
        $lastExpense = Expense::whereYear('created_at', now()->year)->latest('id')->first();
        $nextNumber = $lastExpense ? (intval(substr($lastExpense->expense_number ?? '0', -4)) + 1) : 1;
        $expenseNumber = 'EXP-' . now()->format('Y') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        return Expense::create([
            'expense_number' => $expenseNumber,
            'category' => Expense::CATEGORY_SALARIES,
            'reference_type' => StaffTermination::class,
            'reference_id' => $termination->id,
            'amount' => $amount,
            'title' => "Final Settlement - {$staffName}",
            'description' => "Final settlement payment for terminated staff: {$staffName}. " .
                "Termination Type: " . ucfirst(str_replace('_', ' ', $termination->type)) . ". " .
                "Last Working Day: " . ($termination->last_working_day ? Carbon::parse($termination->last_working_day)->format('d M Y') : 'N/A'),
            'expense_date' => now(),
            'recorded_by' => auth()->id(),
            'status' => Expense::STATUS_PENDING,
            'store_id' => $termination->staff->store_id ?? null,
            'notes' => "Auto-generated from staff termination exit process. Termination #: " . ($termination->termination_number ?? $termination->id),
        ]);
    }
}
