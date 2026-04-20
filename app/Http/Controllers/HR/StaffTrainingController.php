<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\StaffTraining;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\Facades\DataTables;

class StaffTrainingController extends Controller
{
    public function index(Request $request)
    {
        $query = StaffTraining::with(['staff.user', 'createdByUser'])
            ->orderByDesc('start_date');

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->get('export') === 'csv') {
            $rows = $query->get();
            $csv = "Staff,Type,Title,Institution,Start Date,End Date,Status\n";
            foreach ($rows as $r) {
                $csv .= '"'.($r->staff?->user?->surname.' '.$r->staff?->user?->firstname).'","'.ucfirst(str_replace('_',' ',$r->type)).'","'.($r->title ?? '').'","'.($r->institution ?? '').'","'.($r->start_date?->format('Y-m-d') ?? '').'","'.($r->end_date?->format('Y-m-d') ?? '').'","'.ucfirst(str_replace('_',' ',$r->status))."\"\n";
            }
            return response($csv)->header('Content-Type', 'text/csv')->header('Content-Disposition', 'attachment; filename=trainings_'.date('Ymd').'.csv');
        }

        if ($request->ajax()) {
            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('staff_name', fn($t) => '<a href="' . route('hr.tracking.profile', $t->staff_id) . '" class="font-weight-bold text-dark" title="View Tracking Profile">' . e($t->staff?->user?->surname . ' ' . $t->staff?->user?->firstname) . '</a>')
                ->addColumn('training_col', function ($t) {
                    $typeColors = ['attended' => 'success', 'identified' => 'warning', 'career_plan' => 'info'];
                    return e($t->title) . '<br><span class="badge badge-' . ($typeColors[$t->type] ?? 'secondary') . '">' . str_replace('_', ' ', ucfirst($t->type)) . '</span>';
                })
                ->addColumn('institution_col', function ($t) {
                    $html = e($t->institution ?? '—');
                    $dates = $t->start_date?->format('d M Y') ?? '';
                    if ($t->end_date) $dates .= ' – ' . $t->end_date->format('d M Y');
                    if ($dates) $html .= '<br><small class="text-muted">' . $dates . '</small>';
                    return $html;
                })
                ->addColumn('status_col', function ($t) {
                    $statusColors = ['planned' => 'secondary', 'in_progress' => 'warning', 'completed' => 'success', 'cancelled' => 'danger'];
                    return '<span class="badge badge-' . ($statusColors[$t->status] ?? 'secondary') . '">' . str_replace('_', ' ', ucfirst($t->status)) . '</span>';
                })
                ->addColumn('action', function ($t) {
                    $html = '';
                    // Status transition buttons
                    if ($t->status === 'planned') {
                        $html .= '<button class="btn btn-sm btn-outline-warning status-btn" data-url="' . route('hr.trainings.update', $t) . '" data-status="in_progress" title="Start"><i class="mdi mdi-play-circle"></i></button> ';
                    }
                    if ($t->status === 'in_progress') {
                        $html .= '<button class="btn btn-sm btn-outline-success status-btn" data-url="' . route('hr.trainings.update', $t) . '" data-status="completed" title="Complete"><i class="mdi mdi-check-circle"></i></button> ';
                    }
                    if (in_array($t->status, ['planned', 'in_progress'])) {
                        $html .= '<button class="btn btn-sm btn-outline-secondary status-btn" data-url="' . route('hr.trainings.update', $t) . '" data-status="cancelled" title="Cancel"><i class="mdi mdi-close-circle"></i></button> ';
                    }
                    if ($t->certificate_path) {
                        $html .= '<a href="' . Storage::url($t->certificate_path) . '" target="_blank" class="btn btn-sm btn-outline-info" title="Certificate"><i class="mdi mdi-file-certificate"></i></a> ';
                    }
                    $html .= '<button class="btn btn-sm btn-outline-danger delete-btn" data-url="' . route('hr.trainings.destroy', $t) . '" title="Delete"><i class="mdi mdi-delete"></i></button>';
                    return $html;
                })
                ->rawColumns(['staff_name', 'training_col', 'institution_col', 'status_col', 'action'])
                ->make(true);
        }

        $staffList = Staff::with('user')->whereHas('user')->active()->get()->sortBy('user.surname');

        $scopedStaff = null;
        if ($request->filled('staff_id')) {
            $scopedStaff = Staff::with(['user', 'department', 'cadre', 'gradeLevel'])->find($request->staff_id);
        }

        $statsQuery = $scopedStaff ? StaffTraining::where('staff_id', $scopedStaff->id) : new StaffTraining;
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'completed' => (clone $statsQuery)->where('status', 'completed')->count(),
            'in_progress' => (clone $statsQuery)->where('status', 'in_progress')->count(),
            'planned' => (clone $statsQuery)->where('status', 'planned')->count(),
        ];

        return view('admin.hr.trainings.index', compact('staffList', 'stats', 'scopedStaff'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'type' => 'required|in:attended,identified,career_plan',
            'title' => 'required|string|max:255',
            'institution' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:planned,in_progress,completed,cancelled',
            'certificate' => 'nullable|file|mimes:pdf,jpg,png|max:5120',
            'notes' => 'nullable|string',
        ]);

        $data = $request->only(['staff_id', 'type', 'title', 'description', 'institution', 'start_date', 'end_date', 'status', 'notes']);
        $data['created_by'] = Auth::id();

        if ($request->hasFile('certificate')) {
            $data['certificate_path'] = $request->file('certificate')->store('hr/trainings', 'public');
        }

        StaffTraining::create($data);

        if ($request->ajax()) {
            return response()->json(['message' => 'Training record added.']);
        }

        Alert::success('Success', 'Training record added.');
        return redirect()->back();
    }

    public function update(Request $request, StaffTraining $training)
    {
        $request->validate([
            'status' => 'required|in:planned,in_progress,completed,cancelled',
            'end_date' => 'nullable|date',
            'certificate' => 'nullable|file|mimes:pdf,jpg,png|max:5120',
        ]);

        $data = $request->only(['status', 'end_date', 'notes']);

        if ($request->hasFile('certificate')) {
            if ($training->certificate_path) {
                Storage::disk('public')->delete($training->certificate_path);
            }
            $data['certificate_path'] = $request->file('certificate')->store('hr/trainings', 'public');
        }

        $training->update($data);

        if ($request->ajax()) {
            return response()->json(['message' => 'Training updated.']);
        }

        Alert::success('Success', 'Training updated.');
        return redirect()->back();
    }

    public function destroy(Request $request, StaffTraining $training)
    {
        if ($training->certificate_path) {
            Storage::disk('public')->delete($training->certificate_path);
        }
        $training->delete();

        if ($request->ajax()) {
            return response()->json(['message' => 'Training record removed.']);
        }

        Alert::success('Success', 'Training record removed.');
        return redirect()->back();
    }

    public function importTemplate()
    {
        $headers = "staff_id,type,title,institution,start_date,end_date,status,notes\n";
        $headers .= "1,attended,Fire Safety Training,Red Cross,2024-01-15,2024-01-17,completed,Annual training\n";
        return response($headers)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename=trainings_import_template.csv');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $spreadsheet = IOFactory::load($request->file('file')->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $header = array_shift($rows);
        $headerMap = array_flip(array_map(fn($h) => strtolower(trim($h ?? '')), $header));

        $staffIds = Staff::pluck('id')->toArray();
        $validTypes = ['attended', 'identified', 'career_plan'];
        $validStatuses = ['planned', 'in_progress', 'completed', 'cancelled'];
        $imported = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            $data = [];
            foreach (['staff_id', 'type', 'title', 'institution', 'start_date', 'end_date', 'status', 'notes'] as $col) {
                $key = $headerMap[$col] ?? null;
                $data[$col] = $key ? trim($row[$key] ?? '') : '';
            }

            if (empty($data['staff_id']) || empty($data['title'])) {
                if (!empty(array_filter($data))) {
                    $errors[] = "Row {$rowNum}: staff_id and title are required.";
                }
                continue;
            }

            if (!in_array((int) $data['staff_id'], $staffIds)) {
                $errors[] = "Row {$rowNum}: Staff ID {$data['staff_id']} not found.";
                continue;
            }

            $data['staff_id'] = (int) $data['staff_id'];
            $data['type'] = in_array($data['type'], $validTypes) ? $data['type'] : 'attended';
            $data['status'] = in_array($data['status'], $validStatuses) ? $data['status'] : 'completed';
            $data['start_date'] = !empty($data['start_date']) ? $data['start_date'] : null;
            $data['end_date'] = !empty($data['end_date']) ? $data['end_date'] : null;
            $data['created_by'] = Auth::id();

            StaffTraining::create($data);
            $imported++;
        }

        $msg = "{$imported} training(s) imported successfully.";
        if (!empty($errors)) {
            $msg .= ' ' . count($errors) . ' row(s) skipped.';
            return response()->json(['message' => $msg, 'errors_detail' => array_slice($errors, 0, 10)], 200);
        }

        return response()->json(['message' => $msg]);
    }
}
