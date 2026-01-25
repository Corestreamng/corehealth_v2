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
use Carbon\Carbon;

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
        // Handle stats request
        if ($request->has('stats')) {
            return response()->json([
                'active' => StaffSuspension::where('status', StaffSuspension::STATUS_ACTIVE)->count(),
                'lifted' => StaffSuspension::where('status', 'lifted')
                    ->whereMonth('lifted_at', now()->month)
                    ->whereYear('lifted_at', now()->year)
                    ->count(),
                'total' => StaffSuspension::whereYear('created_at', now()->year)->count(),
            ]);
        }

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

        // Handle DataTable AJAX request
        if ($request->ajax()) {
            return datatables()->of($query)
                ->addIndexColumn()
                ->addColumn('staff_name', function ($s) {
                    $user = $s->staff->user ?? null;
                    return $user ? $user->firstname . ' ' . $user->surname : 'N/A';
                })
                ->addColumn('days', function ($s) {
                    if ($s->start_date && $s->end_date) {
                        return Carbon::parse($s->start_date)->diffInDays(Carbon::parse($s->end_date));
                    }
                    return '-';
                })
                ->addColumn('is_paid', function ($s) {
                    return $s->type === 'paid';
                })
                ->editColumn('start_date', function ($s) {
                    return $s->start_date ? Carbon::parse($s->start_date)->format('d M Y') : '-';
                })
                ->editColumn('end_date', function ($s) {
                    return $s->end_date ? Carbon::parse($s->end_date)->format('d M Y') : '-';
                })
                ->addColumn('status_badge', function ($s) {
                    $colors = [
                        'active' => 'danger',
                        'lifted' => 'success',
                        'expired' => 'secondary'
                    ];
                    $color = $colors[$s->status] ?? 'secondary';
                    return '<span class="badge badge-' . $color . '">' . ucfirst($s->status) . '</span>';
                })
                ->addColumn('action', function ($s) {
                    $viewBtn = '<button type="button" class="btn btn-sm btn-info view-btn mr-1" data-id="' . $s->id . '" title="View"><i class="mdi mdi-eye"></i></button>';
                    $liftBtn = '';
                    if ($s->status === 'active') {
                        $liftBtn = '<button type="button" class="btn btn-sm btn-success lift-btn" data-id="' . $s->id . '" data-name="' . ($s->staff->user->firstname ?? '') . ' ' . ($s->staff->user->surname ?? '') . '" title="Lift"><i class="mdi mdi-lock-open"></i></button>';
                    }
                    return $viewBtn . $liftBtn;
                })
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
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
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'reason' => 'required|string|max:1000',
            'suspension_message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            $suspension = StaffSuspension::create([
                'staff_id' => $request->staff_id,
                'disciplinary_query_id' => $request->disciplinary_query_id,
                'type' => $request->is_paid ? 'paid' : 'unpaid',
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'reason' => $request->reason,
                'suspension_message' => $request->suspension_message ?? 'Your account has been suspended. Please contact HR.',
                'issued_by' => auth()->id(),
                'status' => StaffSuspension::STATUS_ACTIVE,
            ]);

            // Handle attachments
            if ($request->hasFile('attachments')) {
                $this->attachmentService->attachMultiple(
                    $suspension,
                    $request->file('attachments'),
                    ['document_type' => 'suspension_letter', 'uploaded_by' => auth()->id()]
                );
            }

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Staff member has been suspended. They will not be able to log in until the suspension is lifted.',
                    'suspension' => $suspension->load(['staff.user', 'issuedBy'])
                ]);
            }

            return redirect()->route('hr.suspensions.show', $suspension)
                ->with('success', 'Staff member has been suspended. They will not be able to log in until the suspension is lifted.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(Request $request, StaffSuspension $suspension)
    {
        $suspension->load([
            'staff.user',
            'issuedBy',
            'liftedBy',
            'disciplinaryQuery',
            'attachments.uploadedBy'
        ]);

        // Return JSON for AJAX requests
        if ($request->ajax()) {
            $user = $suspension->staff->user ?? null;
            return response()->json([
                'success' => true,
                'id' => $suspension->id,
                'suspension_number' => $suspension->suspension_number ?? 'SUSP-' . $suspension->id,
                'staff_name' => $user ? $user->firstname . ' ' . $user->surname : 'N/A',
                'employee_id' => $suspension->staff->employee_id ?? '-',
                'type' => $suspension->type,
                'type_badge' => '<span class="badge badge-' . ($suspension->type === 'paid' ? 'success' : 'warning') . '">' . ucfirst($suspension->type) . '</span>',
                'is_paid' => $suspension->is_paid ?? ($suspension->type === 'paid'),
                'start_date' => $suspension->start_date ? Carbon::parse($suspension->start_date)->format('d M Y') : '-',
                'end_date' => $suspension->end_date ? Carbon::parse($suspension->end_date)->format('d M Y') : '-',
                'end_date_raw' => $suspension->end_date ? Carbon::parse($suspension->end_date)->format('Y-m-d') : null,
                'days' => $suspension->start_date && $suspension->end_date ? Carbon::parse($suspension->start_date)->diffInDays(Carbon::parse($suspension->end_date)) : '-',
                'reason' => $suspension->reason,
                'suspension_message' => $suspension->suspension_message,
                'status' => $suspension->status,
                'status_badge' => '<span class="badge badge-' . ($suspension->status === 'active' ? 'danger' : ($suspension->status === 'lifted' ? 'success' : 'secondary')) . '">' . ucfirst($suspension->status) . '</span>',
                'issued_by' => $suspension->issuedBy ? $suspension->issuedBy->firstname . ' ' . $suspension->issuedBy->surname : '-',
                'created_at' => $suspension->created_at->format('d M Y H:i'),
                'lifted_by' => $suspension->liftedBy ? $suspension->liftedBy->firstname . ' ' . $suspension->liftedBy->surname : null,
                'lifted_at' => $suspension->lifted_at ? Carbon::parse($suspension->lifted_at)->format('d M Y H:i') : null,
                'lift_reason' => $suspension->lift_reason,
                'disciplinary_query' => $suspension->disciplinaryQuery ? [
                    'id' => $suspension->disciplinaryQuery->id,
                    'query_number' => $suspension->disciplinaryQuery->query_number,
                    'subject' => $suspension->disciplinaryQuery->subject
                ] : null,
            ]);
        }

        return view('admin.hr.suspensions.show', compact('suspension'));
    }

    public function lift(Request $request, StaffSuspension $suspension)
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator);
        }

        try {
            $suspension->update([
                'status' => 'lifted',
                'lifted_by' => auth()->id(),
                'lifted_at' => now(),
                'lift_reason' => $request->notes,
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Suspension has been lifted. Staff member can now log in.'
                ]);
            }

            return redirect()->route('hr.suspensions.show', $suspension)
                ->with('success', 'Suspension has been lifted. Staff member can now log in.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return back()->with('error', $e->getMessage());
        }
    }
}
