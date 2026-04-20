<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\HR\StaffQualification;
use App\Models\HR\StaffTraining;
use App\Models\HR\StaffMedicalExam;
use App\Models\HR\StaffFollowUp;
use App\Models\HR\StaffNextOfKin;
use App\Models\Department;
use App\Models\Unit;
use App\Models\HR\Cadre;
use App\Models\HR\GradeLevel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StaffMasterImportController extends Controller
{
    public function showForm()
    {
        return view('admin.hr.tracking.master-import');
    }

    public function importTemplate()
    {
        $headers = [
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

        $csv = implode(',', $headers) . "\n";
        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename=staff_master_import_template.csv');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'mode' => 'required|in:create,update,both',
        ]);

        $spreadsheet = IOFactory::load($request->file('file')->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $header = array_shift($rows);
        $headerMap = $this->buildHeaderMap($header);

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $errors = [];
        $mode = $request->input('mode', 'both');

        // Pre-load lookup tables
        $departments = Department::pluck('id', 'name')->mapWithKeys(fn($v, $k) => [strtolower($k) => $v]);
        $units = Unit::pluck('id', 'name')->mapWithKeys(fn($v, $k) => [strtolower($k) => $v]);
        $cadres = Cadre::pluck('id', 'name')->mapWithKeys(fn($v, $k) => [strtolower($k) => $v]);
        $gradeLevels = GradeLevel::pluck('id', 'name')->mapWithKeys(fn($v, $k) => [strtolower($k) => $v]);

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $rowNum = $i + 2;
                $data = $this->mapRow($row, $headerMap);

                // Skip empty rows
                if (empty($data['name']) && empty($data['employee_number'])) {
                    continue;
                }

                if (empty($data['name'])) {
                    $errors[] = "Row {$rowNum}: NAME is required.";
                    $stats['skipped']++;
                    continue;
                }

                // Find or create staff
                $staff = null;
                if (!empty($data['employee_number'])) {
                    $staff = Staff::where('employee_id', $data['employee_number'])->first();
                }

                if (!$staff && !empty($data['contact_email'])) {
                    $user = User::where('email', $data['contact_email'])->first();
                    if ($user) {
                        $staff = Staff::where('user_id', $user->id)->first();
                    }
                }

                if ($staff && $mode === 'create') {
                    $stats['skipped']++;
                    continue;
                }

                if (!$staff && $mode === 'update') {
                    $errors[] = "Row {$rowNum}: Staff '{$data['name']}' not found for update.";
                    $stats['skipped']++;
                    continue;
                }

                // Parse name
                $nameParts = preg_split('/[\s,]+/', trim($data['name']), 3);
                $surname = $nameParts[0] ?? '';
                $firstname = $nameParts[1] ?? '';
                $othername = $nameParts[2] ?? '';

                // Resolve lookups
                $deptId = $departments[strtolower(trim($data['department'] ?? ''))] ?? null;
                $unitId = $units[strtolower(trim($data['unit'] ?? ''))] ?? null;
                $cadreId = $cadres[strtolower(trim($data['cadre'] ?? ''))] ?? null;
                $gradeLevelId = $gradeLevels[strtolower(trim($data['present_grade_level'] ?? ''))] ?? null;
                $entryLevelId = $gradeLevels[strtolower(trim($data['entry_level'] ?? ''))] ?? null;

                // Build staff data
                $staffData = array_filter([
                    'employee_id' => $data['employee_number'] ?: null,
                    'gender' => $this->normalizeGender($data['sex'] ?? ''),
                    'date_of_birth' => $this->parseDate($data['date_of_birth']),
                    'home_address' => $data['residential_address'] ?: null,
                    'permanent_home_address' => $data['permanent_home_address'] ?: null,
                    'phone_number' => $data['contact_phone'] ?: null,
                    'department_id' => $deptId,
                    'unit_id' => $unitId,
                    'cadre_id' => $cadreId,
                    'grade_level_id' => $gradeLevelId,
                    'entry_grade_level_id' => $entryLevelId,
                    'job_title' => $data['present_position'] ?: null,
                    'job_location' => $data['job_location'] ?: null,
                    'responsibility' => $data['responsibility'] ?: null,
                    'employment_status' => $this->normalizeStatus($data['employment_status'] ?? ''),
                    'date_hired' => $this->parseDate($data['date_of_hire']),
                    'date_confirmed' => $this->parseDate($data['date_of_confirmation']),
                    'confirmation_due_date' => $this->parseDate($data['date_due_for_confirmation']),
                    'license_number' => $data['license_number'] ?: null,
                    'license_expiry_date' => $this->parseDate($data['license_due_date']),
                    'national_id_number' => $data['national_id'] ?: null,
                    'last_promotion_date' => $this->parseDate($data['date_last_promotion']),
                    'next_promotion_due_date' => $this->parseDate($data['date_next_promotion']),
                    'retirement_date' => $this->parseDate($data['exit_due_to_age']),
                    'max_service_date' => $this->parseDate($data['exit_due_to_service']),
                    'marital_status' => strtolower($data['marital_status'] ?? '') ?: null,
                    'number_of_children' => is_numeric($data['number_of_children'] ?? '') ? (int) $data['number_of_children'] : null,
                    'other_talents' => $data['other_talents'] ?: null,
                    'salary_increment_date' => $this->parseDate($data['salary_increment']),
                ], fn($v) => $v !== null);

                if ($staff) {
                    // Update existing
                    $staff->update($staffData);
                    if ($staff->user) {
                        $staff->user->update(array_filter([
                            'surname' => $surname ?: null,
                            'firstname' => $firstname ?: null,
                            'othername' => $othername ?: null,
                        ], fn($v) => $v !== null));
                    }
                    $stats['updated']++;
                } else {
                    // Create user + staff
                    $email = $data['contact_email'] ?: strtolower($surname . '.' . $firstname . '@placeholder.local');
                    $user = User::where('email', $email)->first();
                    if (!$user) {
                        $user = User::create([
                            'surname' => $surname,
                            'firstname' => $firstname,
                            'othername' => $othername,
                            'email' => $email,
                            'password' => bcrypt('changeme123'),
                            'status' => 1,
                        ]);
                    }
                    $staffData['user_id'] = $user->id;
                    $staff = Staff::create($staffData);
                    $stats['created']++;
                }

                // Import related data
                $this->importQualifications($staff, $data);
                $this->importTrainings($staff, $data);
                $this->importNextOfKin($staff, $data);
                $this->importMedicalExam($staff, $data, $rowNum, $errors);
                $this->importFollowUp($staff, $data);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Master import failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }

        $msg = "Import complete: {$stats['created']} created, {$stats['updated']} updated, {$stats['skipped']} skipped.";
        if (!empty($errors)) {
            $msg .= ' ' . count($errors) . ' warning(s).';
        }

        return response()->json([
            'message' => $msg,
            'stats' => $stats,
            'errors_detail' => array_slice($errors, 0, 20),
        ]);
    }

    private function buildHeaderMap(array $header): array
    {
        $map = [];
        $aliases = [
            'name' => ['name', 'staff name', 'full name'],
            'unit' => ['unit'],
            'department' => ['department', 'dept'],
            'present_position' => ['present position', 'position', 'job title'],
            'entry_level' => ['entry level'],
            'cadre' => ['cadre'],
            'employment_status' => ['employment status', 'status'],
            'sex' => ['sex', 'gender'],
            'license_due_date' => ['license due date', 'licence due date'],
            'license_number' => ['mdcn/license number', 'license number', 'mdcn number', 'licence number'],
            'national_id' => ['national identity number', 'national id', 'nin'],
            'job_location' => ['job location', 'location'],
            'present_grade_level' => ['present grade level', 'grade level'],
            'gross_salary_month' => ['gross salary per month', 'monthly salary'],
            'gross_salary_annual' => ['gross salary per annum', 'annual salary'],
            'responsibility' => ['responsibility'],
            'date_of_hire' => ['date of hire', 'hire date', 'date hired'],
            'years_of_service' => ['total years of service', 'years of service'],
            'date_last_promotion' => ['date of last promotion/advancement', 'date of last promotion', 'last promotion'],
            'date_next_promotion' => ['date due for next promotion', 'next promotion due'],
            'date_of_birth' => ['date of birth', 'dob'],
            'age' => ['age'],
            'exit_due_to_age' => ['expected date of exit due to age', 'exit due to age', 'retirement date'],
            'exit_due_to_service' => ['expected date of exit due to years of service', 'exit due to service'],
            'date_of_confirmation' => ['date of confirmation', 'confirmation date'],
            'date_due_for_confirmation' => ['date due for confirmation', 'confirmation due'],
            'entry_qualification' => ['entry qualification'],
            'field_of_study' => ['field of study'],
            'year_of_graduation' => ['year of graduation'],
            'additional_qualification' => ['additional qualification obtained after employment', 'additional qualification'],
            'date_obtained' => ['date obtained'],
            'result_seen' => ['result seen?', 'result seen'],
            'salary_increment' => ['salary increment'],
            'training_identified' => ['job related training identified'],
            'career_plan' => ['individual career plan'],
            'other_training_need' => ['other training/development need', 'other training need'],
            'trainings_benefited' => ['training(s) benefited', 'trainings benefited'],
            'other_talents' => ['other talents'],
            'residential_address' => ['residential address', 'home address'],
            'permanent_home_address' => ['permanent home address'],
            'contact_phone' => ['contact phone number', 'phone number', 'phone'],
            'contact_email' => ['contact email address', 'email address', 'email'],
            'marital_status' => ['marital status'],
            'number_of_children' => ['number of children', 'no of children'],
            'next_of_kin' => ['next of kin'],
            'nok_relationship' => ['relationship with next of kin', 'nok relationship'],
            'nok_phone' => ['contact phone no of next of kin', 'nok phone'],
            'medical_examination' => ['medical examination'],
            'employee_number' => ['employee number', 'employee id', 'emp no'],
            'follow_up' => ['follow-up', 'follow up'],
        ];

        foreach ($header as $col => $val) {
            $normalized = strtolower(trim($val ?? ''));
            foreach ($aliases as $key => $alts) {
                foreach ($alts as $alt) {
                    if ($normalized === $alt) {
                        $map[$key] = $col;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    private function mapRow(array $row, array $headerMap): array
    {
        $data = [];
        foreach ($headerMap as $key => $col) {
            $data[$key] = trim($row[$col] ?? '');
        }
        return $data;
    }

    private function parseDate(?string $value): ?string
    {
        if (empty($value)) return null;
        try {
            // Handle Excel numeric dates
            if (is_numeric($value)) {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $value))->format('Y-m-d');
            }
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function normalizeGender(string $val): ?string
    {
        $val = strtolower(trim($val));
        if (in_array($val, ['m', 'male'])) return 'male';
        if (in_array($val, ['f', 'female'])) return 'female';
        return $val ?: null;
    }

    private function normalizeStatus(string $val): string
    {
        $val = strtolower(trim($val));
        $statusMap = ['active' => 'active', 'suspended' => 'suspended', 'terminated' => 'terminated', 'retired' => 'retired', 'resigned' => 'resigned'];
        return $statusMap[$val] ?? 'active';
    }

    private function importQualifications(Staff $staff, array $data): void
    {
        // Entry qualification
        if (!empty($data['entry_qualification'])) {
            StaffQualification::updateOrCreate(
                ['staff_id' => $staff->id, 'type' => 'entry'],
                [
                    'qualification_name' => $data['entry_qualification'],
                    'field_of_study' => $data['field_of_study'] ?? null,
                    'year_of_graduation' => is_numeric($data['year_of_graduation'] ?? '') ? (int) $data['year_of_graduation'] : null,
                ]
            );
        }

        // Additional qualifications (semicolon-separated)
        if (!empty($data['additional_qualification'])) {
            $quals = array_map('trim', explode(';', $data['additional_qualification']));
            $dates = !empty($data['date_obtained']) ? array_map('trim', explode(';', $data['date_obtained'])) : [];
            $resultSeen = strtolower($data['result_seen'] ?? '') === 'yes';

            foreach ($quals as $idx => $qualName) {
                if (empty($qualName)) continue;
                StaffQualification::updateOrCreate(
                    ['staff_id' => $staff->id, 'type' => 'additional', 'qualification_name' => $qualName],
                    [
                        'year_of_graduation' => is_numeric($dates[$idx] ?? '') ? (int) $dates[$idx] : null,
                        'result_seen' => $resultSeen,
                    ]
                );
            }
        }
    }

    private function importTrainings(Staff $staff, array $data): void
    {
        $trainingFields = [
            'training_identified' => 'identified',
            'career_plan' => 'career_plan',
            'other_training_need' => 'identified',
            'trainings_benefited' => 'attended',
        ];

        foreach ($trainingFields as $field => $type) {
            if (empty($data[$field])) continue;
            $titles = array_map('trim', explode(';', $data[$field]));
            foreach ($titles as $title) {
                if (empty($title)) continue;
                $status = $type === 'attended' ? 'completed' : 'planned';
                StaffTraining::updateOrCreate(
                    ['staff_id' => $staff->id, 'type' => $type, 'title' => $title],
                    ['status' => $status, 'created_by' => auth()->id()]
                );
            }
        }
    }

    private function importNextOfKin(Staff $staff, array $data): void
    {
        if (empty($data['next_of_kin'])) return;

        StaffNextOfKin::updateOrCreate(
            ['staff_id' => $staff->id, 'is_primary' => true],
            [
                'full_name' => $data['next_of_kin'],
                'relationship' => $data['nok_relationship'] ?? null,
                'phone' => $data['nok_phone'] ?? null,
            ]
        );
    }

    private function importMedicalExam(Staff $staff, array $data, int $rowNum, array &$errors): void
    {
        if (empty($data['medical_examination'])) return;

        // Try to parse "Fit (01/01/2024)" or just "Fit" or free text
        $examText = $data['medical_examination'];
        $result = null;
        $date = null;

        if (preg_match('/^(fit|unfit|conditional)/i', $examText, $m)) {
            $result = strtolower($m[1]);
        }
        if (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/', $examText, $m)) {
            $date = $this->parseDate($m[1]);
        }

        if ($result || $date) {
            StaffMedicalExam::updateOrCreate(
                ['staff_id' => $staff->id, 'exam_type' => 'periodic', 'exam_date' => $date ?? now()->format('Y-m-d')],
                [
                    'result' => $result ?? 'fit',
                    'notes' => $examText,
                    'recorded_by' => auth()->id(),
                ]
            );
        }
    }

    private function importFollowUp(Staff $staff, array $data): void
    {
        if (empty($data['follow_up'])) return;

        StaffFollowUp::updateOrCreate(
            ['staff_id' => $staff->id, 'subject' => 'Imported: ' . substr($data['follow_up'], 0, 100)],
            [
                'details' => $data['follow_up'],
                'status' => 'open',
                'priority' => 'medium',
                'created_by' => auth()->id(),
            ]
        );
    }
}
