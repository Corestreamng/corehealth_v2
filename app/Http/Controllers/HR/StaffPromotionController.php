<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\GradeLevel;
use App\Models\HR\StaffPromotion;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\Facades\DataTables;

class StaffPromotionController extends Controller
{
    public function index(Request $request)
    {
        $query = StaffPromotion::with(['staff.user', 'fromGradeLevel', 'toGradeLevel', 'processedBy'])
            ->orderByDesc('promotion_date');

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        if ($request->get('export') === 'csv') {
            $rows = $query->get();
            $csv = "Staff,From Grade,To Grade,New Title,Promotion Date,Effective Date,Next Due,Authority\n";
            foreach ($rows as $r) {
                $csv .= '"'.($r->staff?->user?->surname.' '.$r->staff?->user?->firstname).'","'.($r->fromGradeLevel?->name ?? '').'","'.($r->toGradeLevel?->name ?? '').'","'.($r->to_job_title ?? '').'","'.($r->promotion_date?->format('Y-m-d') ?? '').'","'.($r->effective_date?->format('Y-m-d') ?? '').'","'.($r->next_promotion_due_date?->format('Y-m-d') ?? '').'","'.($r->authority ?? '')."\"\n";
            }
            return response($csv)->header('Content-Type', 'text/csv')->header('Content-Disposition', 'attachment; filename=promotions_'.date('Ymd').'.csv');
        }

        if ($request->ajax()) {
            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('staff_name', fn($p) => '<a href="' . route('hr.tracking.profile', $p->staff_id) . '" class="font-weight-bold text-dark" title="View Tracking Profile">' . e($p->staff?->user?->surname . ' ' . $p->staff?->user?->firstname) . '</a>')
                ->addColumn('grade_change', fn($p) => e($p->fromGradeLevel?->name ?? '—') . ' <i class="mdi mdi-arrow-right text-success"></i> <span class="badge badge-success">' . e($p->toGradeLevel?->name ?? '—') . '</span>')
                ->addColumn('new_title', fn($p) => e($p->to_job_title ?? '—'))
                ->addColumn('date_col', fn($p) => ($p->promotion_date?->format('d M Y') ?? '') . ($p->authority ? '<br><small class="text-muted">' . e($p->authority) . '</small>' : ''))
                ->addColumn('action', function ($p) {
                    $html = '<a href="' . route('hr.promotions.show', $p) . '" class="btn btn-sm btn-outline-info" title="View"><i class="mdi mdi-eye"></i></a> ';
                    $html .= '<button class="btn btn-sm btn-outline-danger delete-btn" data-url="' . route('hr.promotions.destroy', $p) . '" title="Delete"><i class="mdi mdi-delete"></i></button>';
                    return $html;
                })
                ->rawColumns(['staff_name', 'grade_change', 'date_col', 'action'])
                ->make(true);
        }

        $staffList = Staff::with('user')->whereHas('user')->active()->get()->sortBy('user.surname');
        $gradeLevels = GradeLevel::active()->ordered()->get();

        $scopedStaff = null;
        if ($request->filled('staff_id')) {
            $scopedStaff = Staff::with(['user', 'department', 'cadre', 'gradeLevel'])->find($request->staff_id);
        }

        $statsQuery = $scopedStaff ? StaffPromotion::where('staff_id', $scopedStaff->id) : new StaffPromotion;
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'this_year' => (clone $statsQuery)->whereYear('promotion_date', now()->year)->count(),
            'due_soon' => (clone $statsQuery)->where('next_promotion_due_date', '<=', now()->addMonths(3))->where('next_promotion_due_date', '>=', now())->count(),
        ];

        return view('admin.hr.promotions.index', compact('staffList', 'gradeLevels', 'stats', 'scopedStaff'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'to_grade_level_id' => 'nullable|exists:grade_levels,id',
            'to_job_title' => 'nullable|string|max:255',
            'promotion_date' => 'required|date',
            'effective_date' => 'nullable|date',
            'next_promotion_due_date' => 'nullable|date|after:promotion_date',
            'authority' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        $staff = Staff::findOrFail($request->staff_id);

        $promotion = StaffPromotion::create([
            'staff_id' => $staff->id,
            'from_grade_level_id' => $staff->grade_level_id,
            'to_grade_level_id' => $request->to_grade_level_id,
            'from_job_title' => $staff->job_title,
            'to_job_title' => $request->to_job_title,
            'promotion_date' => $request->promotion_date,
            'effective_date' => $request->effective_date ?? $request->promotion_date,
            'next_promotion_due_date' => $request->next_promotion_due_date,
            'authority' => $request->authority,
            'remarks' => $request->remarks,
            'processed_by' => Auth::id(),
        ]);

        // Observer handles syncing to staff table

        if ($request->ajax()) {
            return response()->json(['message' => 'Promotion recorded for ' . ($staff->user?->surname ?? '') . ' ' . ($staff->user?->firstname ?? '')]);
        }

        Alert::success('Success', 'Promotion recorded for ' . ($staff->user?->surname ?? ''));
        return redirect()->route('hr.promotions.index');
    }

    public function show(StaffPromotion $promotion)
    {
        $promotion->load(['staff.user', 'fromGradeLevel', 'toGradeLevel', 'processedBy']);
        return view('admin.hr.promotions.show', compact('promotion'));
    }

    /**
     * Staff promotion history for a specific staff member
     */
    public function staffHistory(Staff $staff)
    {
        $promotions = $staff->promotions()->with(['fromGradeLevel', 'toGradeLevel', 'processedBy'])->get();
        return view('admin.hr.promotions.staff-history', compact('staff', 'promotions'));
    }

    public function destroy(Request $request, StaffPromotion $promotion)
    {
        $promotion->delete();

        if ($request->ajax()) {
            return response()->json(['message' => 'Promotion record removed.']);
        }

        Alert::success('Success', 'Promotion record removed.');
        return redirect()->back();
    }
}
