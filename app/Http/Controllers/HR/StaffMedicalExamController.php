<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\StaffMedicalExam;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\Facades\DataTables;

class StaffMedicalExamController extends Controller
{
    public function index(Request $request)
    {
        $query = StaffMedicalExam::with(['staff.user', 'recordedBy'])
            ->orderByDesc('exam_date');

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        if ($request->filled('exam_type')) {
            $query->where('exam_type', $request->exam_type);
        }

        if ($request->filled('result')) {
            $query->where('result', $request->result);
        }

        if ($request->get('export') === 'csv') {
            $rows = $query->get();
            $csv = "Staff,Exam Type,Exam Date,Result,Conducted By,Next Due,Notes\n";
            foreach ($rows as $r) {
                $csv .= '"'.($r->staff?->user?->surname.' '.$r->staff?->user?->firstname).'","'.ucfirst(str_replace('_',' ',$r->exam_type)).'","'.($r->exam_date?->format('Y-m-d') ?? '').'","'.ucfirst($r->result).'","'.($r->conducted_by ?? '').'","'.($r->next_exam_due?->format('Y-m-d') ?? '').'","'.str_replace('"','""',$r->notes ?? '')."\"\n";
            }
            return response($csv)->header('Content-Type', 'text/csv')->header('Content-Disposition', 'attachment; filename=medical_exams_'.date('Ymd').'.csv');
        }

        if ($request->ajax()) {
            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('staff_name', fn($e) => '<a href="' . route('hr.tracking.profile', $e->staff_id) . '" class="font-weight-bold text-dark" title="View Tracking Profile">' . e($e->staff?->user?->surname . ' ' . $e->staff?->user?->firstname) . '</a>')
                ->addColumn('exam_col', function ($e) {
                    $typeLabels = ['pre_employment' => 'Pre-Empl', 'periodic' => 'Periodic', 'exit' => 'Exit'];
                    return '<span class="badge badge-info">' . ($typeLabels[$e->exam_type] ?? ucfirst($e->exam_type)) . '</span><br><small class="text-muted">' . ($e->exam_date?->format('d M Y') ?? '') . '</small>';
                })
                ->addColumn('result_col', function ($e) {
                    $colors = ['fit' => 'success', 'unfit' => 'danger', 'conditional' => 'warning'];
                    $html = '<span class="badge badge-' . ($colors[$e->result] ?? 'secondary') . '">' . ucfirst($e->result) . '</span>';
                    if ($e->conducted_by) $html .= '<br><small class="text-muted">' . e($e->conducted_by) . '</small>';
                    return $html;
                })
                ->addColumn('next_due_col', function ($e) {
                    if (!$e->next_exam_due) return '—';
                    if ($e->next_exam_due->isPast()) return '<span class="text-danger font-weight-bold">' . $e->next_exam_due->format('d M Y') . ' <i class="mdi mdi-alert"></i></span>';
                    return $e->next_exam_due->format('d M Y');
                })
                ->addColumn('action', function ($e) {
                    $html = '<button class="btn btn-sm btn-outline-primary edit-exam-btn" data-id="' . $e->id . '" data-result="' . $e->result . '" data-next-due="' . ($e->next_exam_due?->format('Y-m-d') ?? '') . '" data-notes="' . e($e->notes ?? '') . '" data-url="' . route('hr.medical-exams.update', $e) . '" title="Edit"><i class="mdi mdi-pencil"></i></button> ';
                    if ($e->document_path) {
                        $html .= '<a href="' . Storage::url($e->document_path) . '" target="_blank" class="btn btn-sm btn-outline-info" title="View Report"><i class="mdi mdi-file-document"></i></a> ';
                    }
                    $html .= '<button class="btn btn-sm btn-outline-danger delete-btn" data-url="' . route('hr.medical-exams.destroy', $e) . '" title="Delete"><i class="mdi mdi-delete"></i></button>';
                    return $html;
                })
                ->rawColumns(['staff_name', 'exam_col', 'result_col', 'next_due_col', 'action'])
                ->make(true);
        }

        $staffList = Staff::with('user')->whereHas('user')->active()->get()->sortBy('user.surname');

        $scopedStaff = null;
        if ($request->filled('staff_id')) {
            $scopedStaff = Staff::with(['user', 'department', 'cadre', 'gradeLevel'])->find($request->staff_id);
        }

        $statsQuery = $scopedStaff ? StaffMedicalExam::where('staff_id', $scopedStaff->id) : new StaffMedicalExam;
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'fit' => (clone $statsQuery)->where('result', 'fit')->count(),
            'overdue' => (clone $statsQuery)->where('next_exam_due', '<', now())->count(),
            'upcoming' => (clone $statsQuery)->whereBetween('next_exam_due', [now(), now()->addMonths(3)])->count(),
        ];

        return view('admin.hr.medical-exams.index', compact('staffList', 'stats', 'scopedStaff'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'exam_date' => 'required|date',
            'exam_type' => 'required|in:pre_employment,periodic,exit',
            'result' => 'required|in:fit,unfit,conditional',
            'next_exam_due' => 'nullable|date|after:exam_date',
            'conducted_by' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'document' => 'nullable|file|mimes:pdf,jpg,png|max:5120',
        ]);

        $data = $request->only(['staff_id', 'exam_date', 'exam_type', 'result', 'next_exam_due', 'conducted_by', 'notes']);
        $data['recorded_by'] = Auth::id();

        if ($request->hasFile('document')) {
            $data['document_path'] = $request->file('document')->store('hr/medical-exams', 'public');
        }

        StaffMedicalExam::create($data);
        // Observer handles syncing to staff table

        if ($request->ajax()) {
            return response()->json(['message' => 'Medical exam recorded.']);
        }

        Alert::success('Success', 'Medical exam recorded.');
        return redirect()->back();
    }

    public function update(Request $request, StaffMedicalExam $medicalExam)
    {
        $request->validate([
            'result' => 'required|in:fit,unfit,conditional',
            'next_exam_due' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $medicalExam->update($request->only(['result', 'next_exam_due', 'notes']));

        if ($request->ajax()) {
            return response()->json(['message' => 'Medical exam updated.']);
        }

        Alert::success('Success', 'Medical exam updated.');
        return redirect()->back();
    }

    public function destroy(Request $request, StaffMedicalExam $medicalExam)
    {
        if ($medicalExam->document_path) {
            Storage::disk('public')->delete($medicalExam->document_path);
        }
        $medicalExam->delete();

        if ($request->ajax()) {
            return response()->json(['message' => 'Medical exam record removed.']);
        }

        Alert::success('Success', 'Medical exam record removed.');
        return redirect()->back();
    }
}
