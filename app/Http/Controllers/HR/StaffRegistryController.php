<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\HR\Cadre;
use App\Models\HR\GradeLevel;
use App\Models\HR\Unit;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class StaffRegistryController extends Controller
{
    public function index()
    {
        $departments = Department::orderBy('name')->pluck('name', 'id');
        $cadres = Cadre::where('is_active', true)->orderBy('name')->pluck('name', 'id');
        $gradeLevels = GradeLevel::where('is_active', true)->orderBy('level')->orderBy('step')->pluck('name', 'id');
        $units = Unit::orderBy('name')->get(['id', 'name', 'department_id']);

        return view('admin.hr.staff-registry.index', compact('departments', 'cadres', 'gradeLevels', 'units'));
    }

    public function data(Request $request)
    {
        $query = Staff::with([
            'user:id,surname,firstname,othername,email,filename,is_admin',
            'user.category:id,name',
            'department:id,name',
            'unit:id,name',
            'cadre:id,name',
            'gradeLevel:id,name,level,step',
            'entryGradeLevel:id,name',
            'specialization:id,name',
            'clinic:id,name',
            'currentSalaryProfile:id,staff_id,gross_salary',
        ])
        ->whereHas('user', fn($q) => $q->where('status', '>', 0));

        // Filters
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }
        if ($request->filled('cadre_id')) {
            $query->where('cadre_id', $request->cadre_id);
        }
        if ($request->filled('employment_status')) {
            $query->where('employment_status', $request->employment_status);
        }
        if ($request->filled('grade_level_id')) {
            $query->where('grade_level_id', $request->grade_level_id);
        }
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }
        if ($request->filled('employment_type')) {
            $query->where('employment_type', $request->employment_type);
        }
        if ($request->filled('alert_type')) {
            $now = Carbon::now();
            switch ($request->alert_type) {
                case 'promotion_due':
                    $query->whereNotNull('next_promotion_due_date')->where('next_promotion_due_date', '<=', $now);
                    break;
                case 'confirmation_due':
                    $query->whereNotNull('confirmation_due_date')->whereNull('date_confirmed')->where('confirmation_due_date', '<=', $now);
                    break;
                case 'license_expiring':
                    $query->whereNotNull('license_expiry_date')->where('license_expiry_date', '<=', $now->copy()->addMonths(3));
                    break;
                case 'medical_exam_due':
                    $query->whereNotNull('next_medical_exam_due')->where('next_medical_exam_due', '<=', $now);
                    break;
                case 'retiring_soon':
                    $query->whereNotNull('retirement_date')->where('retirement_date', '<=', $now->copy()->addYear());
                    break;
            }
        }

        return DataTables::of($query)
            ->addColumn('sn', '')
            ->addColumn('full_name', function($s) {
                $name = $s->user ? e($s->user->surname . ', ' . $s->user->firstname . ' ' . ($s->user->othername ?? '')) : '';
                $empId = e($s->employee_id ?? '');
                $initial = $s->user ? strtoupper(substr($s->user->firstname ?? 'U', 0, 1)) : 'U';
                return '<div class="d-flex align-items-center">
                    <div class="mr-2" style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:0.8rem;flex-shrink:0;">'.$initial.'</div>
                    <div><span class="font-weight-medium">'.$name.'</span><br><small class="text-muted">'.$empId.'</small></div>
                </div>';
            })
            ->addColumn('department_name', fn($s) => $s->department->name ?? '')
            ->addColumn('unit_name', fn($s) => $s->unit->name ?? '')
            ->addColumn('cadre_name', fn($s) => $s->cadre->name ?? '')
            ->addColumn('grade_level_name', fn($s) => $s->gradeLevel->name ?? '')
            ->addColumn('entry_grade_name', fn($s) => $s->entryGradeLevel->name ?? '')
            ->addColumn('specialization_name', fn($s) => $s->specialization->name ?? '')
            ->addColumn('category_name', fn($s) => $s->user->category->name ?? '')
            ->addColumn('gender', fn($s) => $s->gender ? ucfirst($s->gender) : '')
            ->addColumn('job_title', fn($s) => e($s->job_title ?? ''))
            ->addColumn('date_hired', function ($s) {
                if (!$s->date_hired) return '';
                return $s->date_hired->format('d M Y');
            })
            ->addColumn('years_of_service', function ($s) {
                return $s->date_hired ? round($s->date_hired->diffInYears(Carbon::now()), 1) : '';
            })
            ->addColumn('salary', function ($s) {
                $gross = $s->currentSalaryProfile?->gross_salary;
                return $gross ? '₦' . number_format($gross, 0) : '';
            })
            ->addColumn('employment_type', fn($s) => $s->employment_type ? ucfirst(str_replace('_', ' ', $s->employment_type)) : '')
            ->addColumn('phone', fn($s) => e($s->phone_number ?? ''))
            ->addColumn('email', fn($s) => e($s->user->email ?? ''))
            ->addColumn('license_status', function ($s) {
                if (!$s->license_expiry_date) return '';
                $exp = Carbon::parse($s->license_expiry_date);
                if ($exp->isPast()) return '<span class="text-danger"><i class="mdi mdi-alert-circle"></i> Expired</span>';
                if ($exp->lte(Carbon::now()->addMonths(3))) return '<span class="text-warning"><i class="mdi mdi-alert"></i> ' . $exp->format('d/m/Y') . '</span>';
                return '<span class="text-success"><i class="mdi mdi-check-circle"></i> ' . $exp->format('d/m/Y') . '</span>';
            })
            ->addColumn('staff_id', fn($s) => $s->id)
            ->addColumn('alerts', function ($s) {
                $alerts = [];
                $now = Carbon::now();

                if ($s->next_promotion_due_date && Carbon::parse($s->next_promotion_due_date)->lte($now)) {
                    $alerts[] = '<span class="badge badge-warning">Promotion Due</span>';
                }
                if ($s->confirmation_due_date && !$s->date_confirmed && Carbon::parse($s->confirmation_due_date)->lte($now)) {
                    $alerts[] = '<span class="badge badge-info">Confirmation Due</span>';
                }
                if ($s->license_expiry_date && Carbon::parse($s->license_expiry_date)->lte($now->copy()->addMonths(3))) {
                    $alerts[] = '<span class="badge badge-danger">License Expiring</span>';
                }
                if ($s->next_medical_exam_due && Carbon::parse($s->next_medical_exam_due)->lte($now)) {
                    $alerts[] = '<span class="badge badge-secondary">Medical Exam Due</span>';
                }
                if ($s->retirement_date && Carbon::parse($s->retirement_date)->lte($now->copy()->addYear())) {
                    $alerts[] = '<span class="badge badge-dark">Retiring Soon</span>';
                }

                return implode(' ', $alerts);
            })
            ->addColumn('action', function ($s) {
                $editUrl = route('staff.edit', $s->user_id);
                $profileUrl = route('hr.tracking.profile', $s->id);
                $leaveUrl = route('hr.leave-requests.index', ['staff_id' => $s->id]);
                $discUrl = route('hr.disciplinary.index', ['staff_id' => $s->id]);
                $salaryUrl = route('hr.salary-profiles.index', ['staff_id' => $s->id]);
                $promoUrl = route('hr.promotions.index', ['staff_id' => $s->id]);
                $qualUrl = route('hr.qualifications.index', ['staff_id' => $s->id]);
                $trainUrl = route('hr.trainings.index', ['staff_id' => $s->id]);
                $medUrl = route('hr.medical-exams.index', ['staff_id' => $s->id]);
                $followUrl = route('hr.follow-ups.index', ['staff_id' => $s->id]);

                return '<div class="dropdown d-inline-block">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" style="border-radius:6px;">
                        <i class="mdi mdi-dots-vertical"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right" style="border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,.12);min-width:12rem;">
                        <a class="dropdown-item" href="'.$editUrl.'"><i class="mdi mdi-account-edit mr-2 text-primary"></i>Edit Profile</a>
                        <a class="dropdown-item font-weight-bold" href="'.$profileUrl.'"><i class="mdi mdi-account-search mr-2 text-dark"></i>Tracking Profile</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="'.$leaveUrl.'"><i class="mdi mdi-calendar-clock mr-2 text-success"></i>Leave History</a>
                        <a class="dropdown-item" href="'.$discUrl.'"><i class="mdi mdi-gavel mr-2 text-warning"></i>Disciplinary</a>
                        <a class="dropdown-item" href="'.$salaryUrl.'"><i class="mdi mdi-cash-multiple mr-2 text-info"></i>Salary Profile</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="'.$promoUrl.'"><i class="mdi mdi-arrow-up-bold-circle mr-2 text-purple"></i>Promotions</a>
                        <a class="dropdown-item" href="'.$qualUrl.'"><i class="mdi mdi-school mr-2 text-teal"></i>Qualifications</a>
                        <a class="dropdown-item" href="'.$trainUrl.'"><i class="mdi mdi-certificate mr-2 text-secondary"></i>Trainings</a>
                        <a class="dropdown-item" href="'.$medUrl.'"><i class="mdi mdi-stethoscope mr-2 text-danger"></i>Medical Exams</a>
                        <a class="dropdown-item" href="'.$followUrl.'"><i class="mdi mdi-clipboard-check-outline mr-2 text-orange"></i>Follow-ups</a>
                    </div>
                </div>';
            })
            ->rawColumns(['alerts', 'action', 'full_name', 'license_status'])
            ->make(true);
    }

    public function alerts()
    {
        $now = Carbon::now();
        $activeScope = fn($q) => $q->where('employment_status', 'active');

        $promotionDue = Staff::whereNotNull('next_promotion_due_date')
            ->where('next_promotion_due_date', '<=', $now)
            ->where('employment_status', 'active')
            ->count();

        $confirmationDue = Staff::whereNotNull('confirmation_due_date')
            ->whereNull('date_confirmed')
            ->where('confirmation_due_date', '<=', $now)
            ->where('employment_status', 'active')
            ->count();

        $licenseExpiring = Staff::whereNotNull('license_expiry_date')
            ->where('license_expiry_date', '<=', $now->copy()->addMonths(3))
            ->where('employment_status', 'active')
            ->count();

        $medicalExamDue = Staff::whereNotNull('next_medical_exam_due')
            ->where('next_medical_exam_due', '<=', $now)
            ->where('employment_status', 'active')
            ->count();

        $retiringSoon = Staff::whereNotNull('retirement_date')
            ->where('retirement_date', '<=', $now->copy()->addYear())
            ->where('employment_status', 'active')
            ->count();

        $totalActive = Staff::where('employment_status', 'active')->count();

        // Demographics
        $genderCounts = Staff::where('employment_status', 'active')
            ->select('gender', DB::raw('count(*) as cnt'))
            ->groupBy('gender')
            ->pluck('cnt', 'gender')
            ->toArray();

        $male = $genderCounts['male'] ?? $genderCounts['Male'] ?? 0;
        $female = $genderCounts['female'] ?? $genderCounts['Female'] ?? 0;

        // Avg years of service
        $avgService = Staff::where('employment_status', 'active')
            ->whereNotNull('date_hired')
            ->selectRaw('AVG(DATEDIFF(NOW(), date_hired) / 365.25) as avg_yrs')
            ->value('avg_yrs');

        // Total monthly payroll
        $totalPayroll = DB::table('staff_salary_profiles')
            ->join('staff', 'staff_salary_profiles.staff_id', '=', 'staff.id')
            ->where('staff.employment_status', 'active')
            ->where('staff_salary_profiles.is_active', true)
            ->sum('staff_salary_profiles.gross_salary');

        // Department distribution (top 8)
        $deptDistribution = Staff::where('employment_status', 'active')
            ->whereNotNull('department_id')
            ->join('departments', 'staff.department_id', '=', 'departments.id')
            ->select('departments.name', DB::raw('count(*) as cnt'))
            ->groupBy('departments.name')
            ->orderByDesc('cnt')
            ->limit(8)
            ->get()
            ->map(fn($d) => ['name' => $d->name, 'count' => $d->cnt])
            ->toArray();

        // Employment type breakdown
        $typeCounts = Staff::where('employment_status', 'active')
            ->select('employment_type', DB::raw('count(*) as cnt'))
            ->groupBy('employment_type')
            ->pluck('cnt', 'employment_type')
            ->toArray();

        return response()->json([
            'promotion_due' => $promotionDue,
            'confirmation_due' => $confirmationDue,
            'license_expiring' => $licenseExpiring,
            'medical_exam_due' => $medicalExamDue,
            'retiring_soon' => $retiringSoon,
            'total_active' => $totalActive,
            'male' => $male,
            'female' => $female,
            'avg_service_years' => round($avgService ?? 0, 1),
            'total_monthly_payroll' => round($totalPayroll ?? 0),
            'dept_distribution' => $deptDistribution,
            'employment_types' => $typeCounts,
        ]);
    }

    public function export(Request $request)
    {
        $staff = Staff::with([
            'user', 'department', 'unit', 'cadre', 'gradeLevel', 'entryGradeLevel',
            'specialization', 'qualifications', 'promotions', 'trainings',
            'medicalExams', 'followUps', 'nextOfKin', 'currentSalaryProfile',
        ])
        ->whereHas('user', fn($q) => $q->where('status', '>', 0))
        ->get();

        $filename = 'staff_registry_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $columns = [
            'S/NO', 'UNIT', 'DEPARTMENT', 'NAME', 'PRESENT POSITION', 'ENTRY LEVEL', 'CADRE',
            'EMPLOYMENT STATUS', 'SEX', 'LICENSE DUE DATE', 'MDCN/LICENSE NUMBER',
            'NATIONAL IDENTITY NUMBER', 'JOB LOCATION', 'PRESENT GRADE LEVEL',
            'GROSS SALARY PER MONTH', 'GROSS SALARY PER ANNUM', 'RESPONSIBILITY',
            'DATE OF HIRE', 'TOTAL YEARS OF SERVICE', 'DATE OF LAST PROMOTION/ADVANCEMENT',
            'DATE DUE FOR NEXT PROMOTION', 'DATE OF BIRTH', 'AGE',
            'EXPECTED DATE OF EXIT DUE TO AGE', 'EXPECTED DATE OF EXIT DUE TO YEARS OF SERVICE',
            'DATE OF CONFIRMATION', 'DATE DUE FOR CONFIRMATION',
            'ENTRY QUALIFICATION', 'FIELD OF STUDY', 'YEAR OF GRADUATION',
            'ADDITIONAL QUALIFICATION OBTAINED AFTER EMPLOYMENT', 'DATE OBTAINED', 'RESULT SEEN?',
            'SALARY INCREMENT', 'JOB RELATED TRAINING IDENTIFIED', 'INDIVIDUAL CAREER PLAN',
            'OTHER TRAINING/DEVELOPMENT NEED', 'TRAINING(S) BENEFITED', 'OTHER TALENTS',
            'RESIDENTIAL ADDRESS', 'PERMANENT HOME ADDRESS', 'CONTACT PHONE NUMBER',
            'CONTACT EMAIL ADDRESS', 'MARITAL STATUS', 'NUMBER OF CHILDREN',
            'NEXT OF KIN', 'RELATIONSHIP WITH NEXT OF KIN', 'CONTACT PHONE NO OF NEXT OF KIN',
            'MEDICAL EXAMINATION', 'EMPLOYEE NUMBER', 'FOLLOW-UP',
        ];

        $callback = function () use ($staff, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($staff as $i => $s) {
                $fullName = trim(($s->user->surname ?? '') . ' ' . ($s->user->firstname ?? '') . ' ' . ($s->user->othername ?? ''));

                // Age calculation
                $age = $s->date_of_birth ? \Carbon\Carbon::parse($s->date_of_birth)->age : '';

                // Total years of service
                $yearsOfService = $s->date_hired ? round(\Carbon\Carbon::parse($s->date_hired)->diffInYears(now()), 1) : '';

                // Entry qualification (type = entry)
                $entryQual = $s->qualifications->firstWhere('type', 'entry');
                $entryQualName = $entryQual?->qualification_name ?? '';
                $entryField = $entryQual?->field_of_study ?? '';
                $entryYear = $entryQual?->year_of_graduation ?? '';

                // Additional qualifications (type != entry)
                $additionalQuals = $s->qualifications->where('type', '!=', 'entry');
                $additionalQualStr = $additionalQuals->pluck('qualification_name')->implode('; ');
                $additionalDateStr = $additionalQuals->map(fn($q) => $q->date_obtained?->format('Y') ?? $q->year_of_graduation ?? '')->implode('; ');
                $resultSeen = $additionalQuals->count() > 0
                    ? ($additionalQuals->every(fn($q) => $q->result_seen) ? 'Yes' : 'Partial')
                    : '';

                // Trainings by type
                $identified = $s->trainings->where('type', 'identified')->pluck('title')->implode('; ');
                $careerPlan = $s->trainings->where('type', 'career_plan')->pluck('title')->implode('; ');
                $otherTraining = $s->trainings->whereNotIn('type', ['identified', 'career_plan', 'attended'])->pluck('title')->implode('; ');
                $attended = $s->trainings->where('type', 'attended')->pluck('title')->implode('; ');
                if (!$attended) {
                    $attended = $s->trainings->where('status', 'completed')->pluck('title')->implode('; ');
                }

                // Next of kin
                $nok = $s->nextOfKin->first(fn($n) => $n->is_primary) ?? $s->nextOfKin->first();
                $nokName = $nok?->full_name ?? '';
                $nokRelationship = $nok?->relationship ?? '';
                $nokPhone = $nok?->phone ?? '';

                // Medical examination
                $lastExam = $s->medicalExams->sortByDesc('exam_date')->first();
                $medExamStr = $lastExam
                    ? ucfirst(str_replace('_', ' ', $lastExam->exam_type)) . ' (' . ($lastExam->exam_date?->format('d/m/Y') ?? '') . ') - ' . ucfirst($lastExam->result ?? '')
                    : '';

                // Follow-up
                $activeFollowUps = $s->followUps->where('status', '!=', 'resolved');
                $followUpStr = $activeFollowUps->count() > 0
                    ? $activeFollowUps->pluck('subject')->implode('; ')
                    : ($s->followUps->count() > 0 ? 'All resolved' : '');

                fputcsv($file, [
                    $i + 1,
                    $s->unit->name ?? '',
                    $s->department->name ?? '',
                    $fullName,
                    $s->job_title ?? '',
                    $s->entryGradeLevel->name ?? '',
                    $s->cadre->name ?? '',
                    ucfirst($s->employment_status ?? ''),
                    $s->gender ?? '',
                    $s->license_expiry_date?->format('d/m/Y') ?? '',
                    $s->license_number ?? '',
                    $s->national_id_number ?? '',
                    $s->job_location ?? '',
                    $s->gradeLevel->name ?? '',
                    $s->currentSalaryProfile?->gross_salary ? number_format($s->currentSalaryProfile->gross_salary, 2) : '',
                    $s->currentSalaryProfile?->gross_salary ? number_format($s->currentSalaryProfile->gross_salary * 12, 2) : '',
                    $s->responsibility ?? '',
                    $s->date_hired?->format('d/m/Y') ?? '',
                    $yearsOfService,
                    $s->last_promotion_date?->format('d/m/Y') ?? '',
                    $s->next_promotion_due_date?->format('d/m/Y') ?? '',
                    $s->date_of_birth?->format('d/m/Y') ?? '',
                    $age,
                    $s->retirement_date?->format('d/m/Y') ?? '',
                    $s->max_service_date?->format('d/m/Y') ?? '',
                    $s->date_confirmed?->format('d/m/Y') ?? '',
                    $s->confirmation_due_date?->format('d/m/Y') ?? '',
                    $entryQualName,
                    $entryField,
                    $entryYear,
                    $additionalQualStr,
                    $additionalDateStr,
                    $resultSeen,
                    $s->salary_increment_date?->format('d/m/Y') ?? '',
                    $identified,
                    $careerPlan,
                    $otherTraining,
                    $attended,
                    $s->other_talents ?? '',
                    $s->home_address ?? '',
                    $s->permanent_home_address ?? '',
                    $s->phone_number ?? '',
                    $s->user->email ?? '',
                    ucfirst($s->marital_status ?? ''),
                    $s->number_of_children ?? '',
                    $nokName,
                    $nokRelationship,
                    $nokPhone,
                    $medExamStr,
                    $s->employee_id ?? '',
                    $followUpStr,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Expandable child-row detail for a staff member (AJAX).
     */
    public function staffDetail(Staff $staff)
    {
        $staff->load([
            'user', 'department', 'unit', 'cadre', 'gradeLevel', 'entryGradeLevel',
            'currentSalaryProfile', 'qualifications', 'promotions', 'trainings',
            'medicalExams', 'followUps', 'nextOfKin',
        ]);

        $now = Carbon::now();

        // Key dates
        $keyDates = [];
        if ($staff->date_hired) $keyDates['Date of Hire'] = $staff->date_hired->format('d M Y') . ' (' . round($staff->date_hired->diffInYears($now), 1) . ' yrs)';
        if ($staff->date_confirmed) $keyDates['Confirmed'] = $staff->date_confirmed->format('d M Y');
        elseif ($staff->confirmation_due_date) $keyDates['Confirmation Due'] = $staff->confirmation_due_date->format('d M Y') . ($staff->confirmation_due_date->isPast() ? ' ⚠ Overdue' : '');
        if ($staff->last_promotion_date) $keyDates['Last Promotion'] = $staff->last_promotion_date->format('d M Y');
        if ($staff->next_promotion_due_date) $keyDates['Next Promotion Due'] = $staff->next_promotion_due_date->format('d M Y') . ($staff->next_promotion_due_date->isPast() ? ' ⚠ Overdue' : '');
        if ($staff->retirement_date) $keyDates['Retirement'] = $staff->retirement_date->format('d M Y');
        if ($staff->max_service_date) $keyDates['Max Service Exit'] = $staff->max_service_date->format('d M Y');
        if ($staff->license_expiry_date) $keyDates['License Expiry'] = $staff->license_expiry_date->format('d M Y') . ($staff->license_expiry_date->isPast() ? ' ⚠ Expired' : '');

        // Qualifications summary
        $entryQual = $staff->qualifications->where('type', 'entry')->first();
        $additionalQuals = $staff->qualifications->where('type', 'additional');

        // Latest follow-up
        $latestFollowUp = $staff->followUps->sortByDesc('created_at')->first();

        // Latest medical exam
        $latestExam = $staff->medicalExams->first();

        // NOK
        $primaryNok = $staff->nextOfKin->where('is_primary', true)->first() ?? $staff->nextOfKin->first();

        return response()->json([
            'staff_id' => $staff->id,
            'employee_id' => $staff->employee_id,
            'gender' => $staff->gender ? ucfirst($staff->gender) : null,
            'date_of_birth' => $staff->date_of_birth?->format('d M Y'),
            'age' => $staff->date_of_birth?->age,
            'phone' => $staff->phone_number,
            'email' => $staff->user?->email,
            'home_address' => $staff->home_address,
            'marital_status' => $staff->marital_status ? ucfirst($staff->marital_status) : null,
            'national_id' => $staff->national_id_number,
            'license_number' => $staff->license_number,
            'employment_type' => $staff->employment_type ? ucfirst(str_replace('_', ' ', $staff->employment_type)) : null,
            'job_location' => $staff->job_location,
            'responsibility' => $staff->responsibility,
            'entry_level' => $staff->entryGradeLevel?->name,
            'current_level' => $staff->gradeLevel?->name,
            'salary_monthly' => $staff->currentSalaryProfile?->gross_salary ? number_format($staff->currentSalaryProfile->gross_salary, 0) : null,
            'salary_annual' => $staff->currentSalaryProfile?->gross_salary ? number_format($staff->currentSalaryProfile->gross_salary * 12, 0) : null,
            'key_dates' => $keyDates,
            'entry_qualification' => $entryQual ? [
                'name' => $entryQual->qualification_name,
                'field' => $entryQual->field_of_study,
                'year' => $entryQual->year_of_graduation,
                'verified' => (bool) $entryQual->result_seen,
            ] : null,
            'additional_qualifications' => $additionalQuals->count(),
            'total_promotions' => $staff->promotions->count(),
            'total_trainings' => $staff->trainings->count(),
            'completed_trainings' => $staff->trainings->where('status', 'completed')->count(),
            'latest_follow_up' => $latestFollowUp ? [
                'subject' => $latestFollowUp->subject,
                'status' => $latestFollowUp->status,
                'priority' => $latestFollowUp->priority,
                'due_date' => $latestFollowUp->due_date?->format('d M Y'),
            ] : null,
            'open_follow_ups' => $staff->followUps->where('status', '!=', 'resolved')->count(),
            'latest_medical_exam' => $latestExam ? [
                'date' => $latestExam->exam_date?->format('d M Y'),
                'type' => $latestExam->exam_type,
                'result' => $latestExam->result,
                'next_due' => $latestExam->next_exam_due?->format('d M Y'),
            ] : null,
            'next_of_kin' => $primaryNok ? [
                'name' => $primaryNok->full_name,
                'relationship' => $primaryNok->relationship,
                'phone' => $primaryNok->phone,
            ] : null,
            'profile_url' => route('hr.tracking.profile', $staff->id),
        ]);
    }
}
