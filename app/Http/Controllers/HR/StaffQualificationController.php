<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\StaffQualification;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\Facades\DataTables;

class StaffQualificationController extends Controller
{
    public function index(Request $request)
    {
        $query = StaffQualification::with(['staff.user', 'verifiedBy'])
            ->orderByDesc('year_of_graduation');

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        if ($request->get('export') === 'csv') {
            $rows = $query->get();
            $csv = "Staff,Type,Qualification,Field of Study,Institution,Year,Date Obtained,Verified\n";
            foreach ($rows as $r) {
                $csv .= '"'.($r->staff?->user?->surname.' '.$r->staff?->user?->firstname.' '.$r->staff?->user?->othername).'","'.ucfirst($r->type).'","'.($r->qualification_name ?? '').'","'.($r->field_of_study ?? '').'","'.($r->institution ?? '').'","'.($r->year_of_graduation ?? '').'","'.($r->date_obtained?->format('Y-m-d') ?? '').'","'.($r->result_seen ? 'Yes' : 'No')."\"\n";
            }
            return response($csv)->header('Content-Type', 'text/csv')->header('Content-Disposition', 'attachment; filename=qualifications_'.date('Ymd').'.csv');
        }

        if ($request->ajax()) {
            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('staff_name', fn($q) => '<a href="' . route('hr.tracking.profile', $q->staff_id) . '" class="font-weight-bold text-dark" title="View Tracking Profile">' . e($q->staff?->user?->surname . ' ' . $q->staff?->user?->firstname . ' ' . $q->staff?->user?->othername) . '</a>')
                ->addColumn('qualification_col', function ($q) {
                    $html = e($q->qualification_name);
                    if ($q->field_of_study) $html .= '<br><small class="text-muted">' . e($q->field_of_study) . '</small>';
                    $badge = $q->type == 'entry' ? 'primary' : 'info';
                    $html .= ' <span class="badge badge-' . $badge . ' ml-1">' . ucfirst($q->type) . '</span>';
                    return $html;
                })
                ->addColumn('institution_col', fn($q) => e($q->institution ?? '—'))
                ->addColumn('year_col', fn($q) => $q->year_of_graduation ?? '—')
                ->addColumn('action', function ($q) {
                    $html = '';
                    if ($q->result_seen) {
                        $html .= '<span class="badge badge-success"><i class="mdi mdi-check-circle"></i></span> ';
                    } else {
                        $html .= '<button class="btn btn-sm btn-outline-success verify-btn" data-url="' . route('hr.qualifications.verify', $q) . '" title="Verify"><i class="mdi mdi-check"></i></button> ';
                    }
                    if ($q->document_path) {
                        $html .= '<a href="' . Storage::url($q->document_path) . '" target="_blank" class="btn btn-sm btn-outline-info" title="View"><i class="mdi mdi-file-document"></i></a> ';
                    }
                    $html .= '<button class="btn btn-sm btn-outline-danger delete-btn" data-url="' . route('hr.qualifications.destroy', $q) . '"><i class="mdi mdi-delete"></i></button>';
                    return $html;
                })
                ->rawColumns(['staff_name', 'qualification_col', 'action'])
                ->make(true);
        }

        $staffList = Staff::with('user')->whereHas('user')->active()->get()->sortBy('user.surname');

        $scopedStaff = null;
        if ($request->filled('staff_id')) {
            $scopedStaff = Staff::with(['user', 'department', 'cadre', 'gradeLevel'])->find($request->staff_id);
        }

        $statsQuery = $scopedStaff ? StaffQualification::where('staff_id', $scopedStaff->id) : new StaffQualification;
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'verified' => (clone $statsQuery)->where('result_seen', true)->count(),
            'unverified' => (clone $statsQuery)->where('result_seen', false)->orWhereNull('result_seen')->count(),
            'with_docs' => (clone $statsQuery)->whereNotNull('document_path')->count(),
        ];

        return view('admin.hr.qualifications.index', compact('staffList', 'stats', 'scopedStaff'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'type' => 'required|in:entry,additional',
            'qualification_name' => 'required|string|max:255',
            'field_of_study' => 'nullable|string|max:255',
            'institution' => 'nullable|string|max:255',
            'year_of_graduation' => 'nullable|integer|min:1950|max:' . date('Y'),
            'date_obtained' => 'nullable|date',
            'document' => 'nullable|file|mimes:pdf,jpg,png|max:5120',
            'notes' => 'nullable|string',
        ]);

        $data = $request->only(['staff_id', 'type', 'qualification_name', 'field_of_study', 'institution', 'year_of_graduation', 'date_obtained', 'notes']);

        if ($request->hasFile('document')) {
            $data['document_path'] = $request->file('document')->store('hr/qualifications', 'public');
        }

        StaffQualification::create($data);

        if ($request->ajax()) {
            return response()->json(['message' => 'Qualification recorded.']);
        }

        Alert::success('Success', 'Qualification recorded.');
        return redirect()->back();
    }

    public function verify(Request $request, StaffQualification $qualification)
    {
        $qualification->update([
            'result_seen' => true,
            'result_seen_by' => Auth::id(),
            'result_seen_at' => now(),
        ]);

        if ($request->ajax()) {
            return response()->json(['message' => 'Qualification verified.']);
        }

        Alert::success('Success', 'Qualification verified.');
        return redirect()->back();
    }

    public function destroy(Request $request, StaffQualification $qualification)
    {
        if ($qualification->document_path) {
            Storage::disk('public')->delete($qualification->document_path);
        }
        $qualification->delete();

        if ($request->ajax()) {
            return response()->json(['message' => 'Qualification removed.']);
        }

        Alert::success('Success', 'Qualification removed.');
        return redirect()->back();
    }

    public function importTemplate()
    {
        $headers = "staff_id,type,qualification_name,field_of_study,institution,year_of_graduation,date_obtained,notes\n";
        $headers .= "1,entry,B.Sc Nursing,Nursing Science,University of Lagos,2020,,Entry qualification\n";
        return response($headers)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename=qualifications_import_template.csv');
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
        $imported = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            $staffId = trim($row[$header[array_search('staff_id', array_keys($headerMap))] ?? 'A'] ?? '');

            // Map columns
            $data = [];
            foreach (['staff_id', 'type', 'qualification_name', 'field_of_study', 'institution', 'year_of_graduation', 'date_obtained', 'notes'] as $col) {
                $key = $headerMap[$col] ?? null;
                $data[$col] = $key ? trim($row[$key] ?? '') : '';
            }

            if (empty($data['staff_id']) || empty($data['qualification_name'])) {
                if (!empty(array_filter($data))) {
                    $errors[] = "Row {$rowNum}: staff_id and qualification_name are required.";
                }
                continue;
            }

            if (!in_array((int) $data['staff_id'], $staffIds)) {
                $errors[] = "Row {$rowNum}: Staff ID {$data['staff_id']} not found.";
                continue;
            }

            if (!empty($data['type']) && !in_array($data['type'], ['entry', 'additional'])) {
                $data['type'] = 'additional';
            }
            if (empty($data['type'])) $data['type'] = 'additional';

            $data['staff_id'] = (int) $data['staff_id'];
            $data['year_of_graduation'] = !empty($data['year_of_graduation']) ? (int) $data['year_of_graduation'] : null;
            $data['date_obtained'] = !empty($data['date_obtained']) ? date('Y-m-d', strtotime($data['date_obtained'])) : null;

            StaffQualification::create($data);
            $imported++;
        }

        $msg = "{$imported} qualification(s) imported successfully.";
        if (!empty($errors)) {
            $msg .= ' ' . count($errors) . ' row(s) skipped.';
            return response()->json(['message' => $msg, 'errors_detail' => array_slice($errors, 0, 10)], 200);
        }

        return response()->json(['message' => $msg]);
    }
}
