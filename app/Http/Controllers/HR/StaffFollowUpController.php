<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\StaffFollowUp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\Facades\DataTables;

class StaffFollowUpController extends Controller
{
    public function index(Request $request)
    {
        $query = StaffFollowUp::with(['staff.user', 'createdByUser', 'resolvedByUser'])
            ->orderByDesc('created_at');

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['open', 'in_progress']);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->get('export') === 'csv') {
            $rows = $query->get();
            $csv = "Staff,Subject,Priority,Due Date,Status,Created By,Resolved By,Resolved At\n";
            foreach ($rows as $r) {
                $csv .= '"'.($r->staff?->user?->surname.' '.$r->staff?->user?->firstname.' '.$r->staff?->user?->othername).'","'.($r->subject ?? '').'","'.ucfirst($r->priority).'","'.($r->due_date?->format('Y-m-d') ?? '').'","'.ucfirst(str_replace('_',' ',$r->status)).'","'.($r->createdByUser?->name ?? '').'","'.($r->resolvedByUser?->name ?? '').'","'.($r->resolved_at?->format('Y-m-d H:i') ?? '')."\"\n";
            }
            return response($csv)->header('Content-Type', 'text/csv')->header('Content-Disposition', 'attachment; filename=follow_ups_'.date('Ymd').'.csv');
        }

        if ($request->ajax()) {
            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('staff_name', fn($f) => '<a href="' . route('hr.tracking.profile', $f->staff_id) . '" class="font-weight-bold text-dark" title="View Tracking Profile">' . e($f->staff?->user?->surname . ' ' . $f->staff?->user?->firstname . ' ' . $f->staff?->user?->othername) . '</a>')
                ->addColumn('subject_col', function ($f) {
                    $html = e($f->subject);
                    if ($f->details) $html .= '<br><small class="text-muted">' . e(\Str::limit($f->details, 60)) . '</small>';
                    return $html;
                })
                ->addColumn('priority_col', function ($f) {
                    $colors = ['low' => 'secondary', 'medium' => 'warning', 'high' => 'danger'];
                    return '<span class="badge badge-' . ($colors[$f->priority] ?? 'secondary') . '">' . ucfirst($f->priority) . '</span>';
                })
                ->addColumn('due_date_col', function ($f) {
                    if (!$f->due_date) return '—';
                    if ($f->due_date->isPast() && $f->status !== 'resolved') return '<span class="text-danger font-weight-bold">' . $f->due_date->format('d M Y') . ' <i class="mdi mdi-alert"></i></span>';
                    return $f->due_date->format('d M Y');
                })
                ->addColumn('status_col', function ($f) {
                    $colors = ['open' => 'warning', 'in_progress' => 'info', 'resolved' => 'success'];
                    $html = '<span class="badge badge-' . ($colors[$f->status] ?? 'secondary') . '">' . str_replace('_', ' ', ucfirst($f->status)) . '</span>';
                    if ($f->resolved_at) $html .= '<br><small class="text-muted">' . $f->resolved_at->format('d M Y') . '</small>';
                    return $html;
                })
                ->addColumn('created_by_col', fn($f) => e($f->createdByUser?->surname ?? '—'))
                ->addColumn('action', function ($f) {
                    $html = '';
                    if ($f->status === 'open') {
                        $html .= '<button class="btn btn-sm btn-outline-info start-btn" data-url="' . route('hr.follow-ups.start', $f) . '" title="Start Progress"><i class="mdi mdi-play-circle"></i></button> ';
                    }
                    if ($f->status !== 'resolved') {
                        $html .= '<button class="btn btn-sm btn-outline-success resolve-btn" data-url="' . route('hr.follow-ups.resolve', $f) . '" title="Resolve"><i class="mdi mdi-check-circle"></i></button> ';
                    }
                    $html .= '<button class="btn btn-sm btn-outline-danger delete-btn" data-url="' . route('hr.follow-ups.destroy', $f) . '" title="Delete"><i class="mdi mdi-delete"></i></button>';
                    return $html;
                })
                ->rawColumns(['staff_name', 'subject_col', 'priority_col', 'due_date_col', 'status_col', 'action'])
                ->make(true);
        }

        $staffList = \App\Models\Staff::with('user')->whereHas('user')->active()->get()->sortBy('user.surname');

        $scopedStaff = null;
        if ($request->filled('staff_id')) {
            $scopedStaff = \App\Models\Staff::with(['user', 'department', 'cadre', 'gradeLevel'])->find($request->staff_id);
        }

        $statsQuery = $scopedStaff ? StaffFollowUp::where('staff_id', $scopedStaff->id) : new StaffFollowUp;
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'open' => (clone $statsQuery)->where('status', 'open')->count(),
            'in_progress' => (clone $statsQuery)->where('status', 'in_progress')->count(),
            'overdue' => (clone $statsQuery)->whereIn('status', ['open', 'in_progress'])->where('due_date', '<', now())->count(),
        ];

        return view('admin.hr.follow-ups.index', compact('staffList', 'stats', 'scopedStaff'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'subject' => 'required|string|max:255',
            'details' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'required|in:low,medium,high',
        ]);

        StaffFollowUp::create(array_merge(
            $request->only(['staff_id', 'subject', 'details', 'due_date', 'priority']),
            ['created_by' => Auth::id(), 'status' => 'open']
        ));

        if ($request->ajax()) {
            return response()->json(['message' => 'Follow-up created.']);
        }

        Alert::success('Success', 'Follow-up created.');
        return redirect()->back();
    }

    public function start(Request $request, StaffFollowUp $followUp)
    {
        if ($followUp->status !== 'open') {
            return $request->ajax()
                ? response()->json(['message' => 'Only open follow-ups can be started.'], 422)
                : redirect()->back();
        }

        $followUp->update(['status' => 'in_progress']);

        if ($request->ajax()) {
            return response()->json(['message' => 'Follow-up marked as in progress.']);
        }

        Alert::success('Success', 'Follow-up marked as in progress.');
        return redirect()->back();
    }

    public function resolve(Request $request, StaffFollowUp $followUp)
    {
        $followUp->update([
            'status' => 'resolved',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        if ($request->ajax()) {
            return response()->json(['message' => 'Follow-up resolved.']);
        }

        Alert::success('Success', 'Follow-up resolved.');
        return redirect()->back();
    }

    public function destroy(Request $request, StaffFollowUp $followUp)
    {
        $followUp->delete();

        if ($request->ajax()) {
            return response()->json(['message' => 'Follow-up removed.']);
        }

        Alert::success('Success', 'Follow-up removed.');
        return redirect()->back();
    }
}
